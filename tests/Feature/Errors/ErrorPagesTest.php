<?php

namespace Tests\Feature\Errors;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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
}
