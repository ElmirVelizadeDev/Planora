<?php

namespace Tests\Feature\Errors;

use App\Models\User;
use App\Services\Api\V1\Admin\SystemMaintenance\MaintenanceScopeStore;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        app(MaintenanceScopeStore::class)->reset();
        Route::middleware('api')->get('/api/v1/__test/http-error/{status}', function (int $status) {
            abort($status, 'private exception detail must not leak');
        });
        Route::middleware('api')->get('/api/v1/__test/http-error/500', function () {
            abort(500, 'private exception detail must not leak');
        });
    }

    public function test_branded_error_templates_keep_their_http_status_and_do_not_expose_exception_details(): void
    {
        foreach ([401, 403, 404, 419, 429] as $status) {
            $response = $this->get('/api/v1/__test/http-error/'.$status, ['Accept' => 'text/html']);

            $response->assertStatus($status)
                ->assertSee('Planora')
                ->assertDontSee('private exception detail must not leak');
        }
    }

    public function test_server_errors_return_safe_json_for_api_clients(): void
    {
        $this->getJson('/api/v1/__test/http-error/500')
            ->assertStatus(500)
            ->assertJson(['message' => 'Planora could not complete that request. Please try again.'])
            ->assertDontSee('private exception detail must not leak');
    }

    public function test_html_server_error_responses_are_sanitized_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        $request = Request::create('/internal-error', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $response = app(\App\Exceptions\Handler::class)->render($request, new HttpException(500, 'private exception detail must not leak'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Something went wrong', $response->getContent());
        $this->assertStringNotContainsString('private exception detail must not leak', $response->getContent());
    }

    public function test_maintenance_mode_blocks_browser_and_api_requests_with_503(): void
    {
        $maintenanceMode = app()->maintenanceMode();
        $this->assertFalse($maintenanceMode->active(), 'The feature test must not start while the app is in maintenance mode.');

        $maintenanceMode->activate([
            'except' => [],
            'redirect' => null,
            'retry' => 75,
            'refresh' => null,
            'secret' => null,
            'status' => 503,
            'template' => view('errors.503', ['retryAfter' => 75])->render(),
        ]);

        try {
            $this->get('/')->assertStatus(503)->assertSee('We will be back soon');
            $ordinaryUser = new User();
            $ordinaryUser->forceFill([
                'id' => 777,
                'name' => 'Ordinary QA',
                'email' => 'ordinary-qa@planora.test',
                'password' => Hash::make('maintenance-test-password'),
                'role' => 'user',
                'status' => 'active',
                'moderator_permissions' => [],
            ]);
            $this->actingAs($ordinaryUser, 'sanctum')->getJson('/api/v1/user')
                ->assertStatus(503)
                ->assertHeader('Retry-After', '75')
                ->assertJson(['message' => config('planora.maintenance.message')]);
        } finally {
            $maintenanceMode->deactivate();
        }
    }

    public function test_scoped_maintenance_navigation_never_sends_visitors_back_to_a_blocked_public_home(): void
    {
        $store = app(MaintenanceScopeStore::class);
        $store->disableAll();
        $store->set('public_website', true);

        auth()->logout();
        $guestResponse = $this->get('/');
        $guestResponse->assertStatus(503)
            ->assertSee('Check again')
            ->assertDontSee('Go to Planora');

        $user = User::factory()->create([
            'name' => 'Maintenance QA',
            'password' => Hash::make('maintenance-test-password'),
            'role' => 'user',
            'status' => 'active',
            'moderator_permissions' => [],
        ]);

        $this->withSession(['password_hash_web' => $user->getAuthPassword()])
            ->actingAs($user, 'web')
            ->get('/')
            ->assertStatus(503)
            ->assertSee('Go to dashboard');
        $this->withSession(['password_hash_web' => $user->getAuthPassword()])
            ->actingAs($user, 'web')
            ->get('/dashboard')
            ->assertOk();

        $store->set('dashboard_workspace', true);
        $this->withSession(['password_hash_web' => $user->getAuthPassword()])
            ->actingAs($user, 'web')
            ->get('/')
            ->assertStatus(503)
            ->assertDontSee('Go to dashboard')
            ->assertDontSee('Go to Planora');

        $admin = User::factory()->create([
            'name' => 'Maintenance Admin',
            'password' => Hash::make('maintenance-test-password'),
            'role' => 'admin',
            'status' => 'active',
            'moderator_permissions' => [],
        ]);
        $this->withSession(['password_hash_web' => $admin->getAuthPassword()])
            ->actingAs($admin, 'web')
            ->get('/')
            ->assertStatus(503)
            ->assertSee('Go to Admin dashboard');
        $this->withSession(['password_hash_web' => $admin->getAuthPassword()])
            ->actingAs($admin, 'web')
            ->get('/admin/dashboard')
            ->assertOk();

        $store->disableAll();
        auth()->logout();
        $this->get('/')->assertOk();
    }

    public function test_unauthorized_and_unknown_frontend_entry_routes_load_the_spa_shell(): void
    {
        $this->get('/401')->assertOk()->assertSee('id="app"', false);
        $this->get('/404')->assertOk()->assertSee('id="app"', false);
    }

    public function test_maintenance_page_is_standalone_and_uses_configured_copy_without_app_assets(): void
    {
        config([
            'planora.maintenance.message' => 'Scheduled maintenance in progress.',
            'planora.maintenance.estimated_at' => 'Nov 14, 2026 6:00 PM AZT',
        ]);

        $html = view('errors.503', ['retryAfter' => 60])->render();

        $this->assertStringContainsString('Scheduled maintenance in progress.', $html);
        $this->assertStringContainsString('Nov 14, 2026 6:00 PM AZT', $html);
        $this->assertStringNotContainsString('@vite', $html);
    }

    public function test_maintenance_error_only_offers_navigation_that_is_open_for_the_current_role_and_scopes(): void
    {
        $maintenanceView = fn (array $scopes): string => view('errors.page', [
            'code' => 503,
            'title' => 'Service temporarily unavailable',
            'description' => 'Planora is temporarily unavailable.',
            'retryAfter' => 60,
            'maintenanceScopes' => $scopes,
        ])->render();

        auth()->logout();
        $guestDuringPublicMaintenance = $maintenanceView([
            'public_website' => true,
            'authentication' => false,
            'dashboard_workspace' => false,
        ]);
        $this->assertStringNotContainsString('Go to Planora', $guestDuringPublicMaintenance);
        $this->assertStringNotContainsString('Go to dashboard', $guestDuringPublicMaintenance);
        $this->assertStringContainsString('Check again', $guestDuringPublicMaintenance);

        $user = User::factory()->create(['name' => 'Maintenance QA', 'password' => Hash::make('maintenance-test-password'), 'role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'web');
        $userDashboardAvailable = $maintenanceView([
            'public_website' => true,
            'authentication' => false,
            'dashboard_workspace' => false,
        ]);
        $this->assertStringContainsString('Go to dashboard', $userDashboardAvailable);
        $this->assertStringNotContainsString('Go to Planora', $userDashboardAvailable);

        $userDashboardBlocked = $maintenanceView([
            'public_website' => true,
            'authentication' => false,
            'dashboard_workspace' => true,
        ]);
        $this->assertStringNotContainsString('Go to dashboard', $userDashboardBlocked);
        $this->assertStringNotContainsString('Go to Planora', $userDashboardBlocked);

        $admin = User::factory()->create(['name' => 'Admin QA', 'password' => Hash::make('maintenance-test-password'), 'role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin, 'web');
        $adminDashboardAvailable = $maintenanceView([
            'public_website' => true,
            'authentication' => true,
            'dashboard_workspace' => true,
        ]);
        $this->assertStringContainsString('Go to Admin dashboard', $adminDashboardAvailable);
    }

    public function test_guest_theme_cookie_controls_standalone_error_page_theme_safely(): void
    {
        $request = Request::create('/', 'GET', [], ['planora_guest_theme' => 'dark']);
        app(\App\Http\Middleware\EncryptCookies::class)->handle($request, function (Request $decryptedRequest) {
            $this->assertSame('dark', $decryptedRequest->cookie('planora_guest_theme'));
            return response('cookie accepted');
        });

        $request = Request::create('/', 'GET', [], ['planora_guest_theme' => 'dark']);
        $this->app->instance('request', $request);
        $darkHtml = view('errors.page', ['code' => 500, 'title' => 'Error', 'description' => 'Try again.'])->render();
        $this->assertStringContainsString('data-theme="dark"', $darkHtml);
        $this->assertStringContainsString('meta name="color-scheme" content="dark"', $darkHtml);

        $request = Request::create('/', 'GET', [], ['planora_guest_theme' => 'not-a-theme']);
        $this->app->instance('request', $request);
        $lightHtml = view('errors.page', ['code' => 500, 'title' => 'Error', 'description' => 'Try again.'])->render();
        $this->assertStringContainsString('data-theme="light"', $lightHtml);
    }

    public function test_public_routes_and_spa_error_pages_include_the_early_guest_theme_bootstrap(): void
    {
        foreach (['/', '/login', '/register', '/terms-and-conditions', '/privacy-policy', '/401', '/403', '/404', '/419', '/429', '/500', '/503'] as $path) {
            $this->withCookie('planora_guest_theme', 'dark')
                ->get($path)
                ->assertOk()
                ->assertSee('planora.guest-theme.v1', false)
                ->assertSee('document.documentElement.dataset.theme = theme', false);
        }
    }

    public function test_spa_shell_bootstraps_guest_theme_early_and_prefers_current_accounts_saved_theme(): void
    {
        $this->withCookie('planora_guest_theme', 'dark')
            ->get('/')
            ->assertOk()
            ->assertSee('planora.guest-theme.v1', false)
            ->assertSee('document.documentElement.dataset.theme = theme', false);

        $user = new User();
        $user->forceFill(['id' => 778, 'name' => 'Theme QA', 'email' => 'theme-qa@planora.test', 'password' => Hash::make('maintenance-test-password'), 'role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'web')->get('/')
            ->assertOk()
            ->assertSee('planora.appearance.v1.', false);
    }
}
