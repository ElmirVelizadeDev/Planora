<?php

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Settings\TestSettingsEmailRequest;
use App\Http\Requests\Api\V1\Admin\Settings\UpdateSettingsRequest;
use App\Http\Requests\Api\V1\Admin\Settings\UploadSettingsLogoRequest;
use App\Services\Api\V1\Admin\UserActivityLogger;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdminSettingsController extends Controller
{
    public function index(SettingsService $settings): JsonResponse
    {
        return response()->json(['data' => $settings->overview()]);
    }

    public function show(string $group, SettingsService $settings, SettingsRegistry $registry): JsonResponse
    {
        abort_unless($registry->group($group), 404);
        return response()->json(['data' => $settings->group($group)]);
    }

    public function update(UpdateSettingsRequest $request, string $group, SettingsService $settings, SettingsRegistry $registry, UserActivityLogger $activityLogger): JsonResponse
    {
        abort_unless($registry->group($group), 404);
        $values = $request->validated();
        $removePassword = (bool) ($values['remove_mail_password'] ?? false);
        unset($values['remove_mail_password']);
        $secret = $values['mail_password'] ?? null;
        unset($values['mail_password']);

        try {
            $change = $settings->update($group, $values, $request->user(), is_string($secret) && $secret !== '' ? $secret : null, $removePassword);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $changedKeys = array_values(array_filter(array_unique([...array_keys($values), ...($secret !== null && $secret !== '' ? ['mail_password'] : []), ...($removePassword ? ['mail_password'] : [])]), function ($key) use ($change) {
            return ($change['before'][$key] ?? null) !== ($change['after'][$key] ?? null) || $key === 'mail_password';
        }));
        $this->recordSettingsEvent($activityLogger, 'settings.updated', 'Administrator updated workspace settings.', $request->user(), [
            'group' => $group,
            'changed_keys' => $changedKeys,
            'secret_updated' => is_string($secret) && $secret !== '',
            'secret_removed' => $removePassword,
        ]);

        return response()->json(['message' => 'Settings saved.', 'data' => $settings->group($group)]);
    }

    public function reset(string $group, \Illuminate\Http\Request $request, SettingsService $settings, SettingsRegistry $registry, UserActivityLogger $activityLogger): JsonResponse
    {
        abort_unless($registry->group($group), 404);
        $settings->reset($group, $request->user());
        $this->recordSettingsEvent($activityLogger, 'settings.reset', 'Administrator reset workspace settings to defaults.', $request->user(), ['group' => $group]);
        return response()->json(['message' => 'Settings restored to defaults.', 'data' => $settings->group($group)]);
    }

    public function uploadLogo(UploadSettingsLogoRequest $request, SettingsService $settings, UserActivityLogger $activityLogger): JsonResponse
    {
        $url = $settings->uploadLogo($request->file('logo'), $request->user());
        $this->recordSettingsEvent($activityLogger, 'settings.logo_updated', 'Administrator updated workspace branding.', $request->user(), ['group' => 'general', 'changed_keys' => ['application_logo']]);
        return response()->json(['message' => 'Workspace logo updated.', 'data' => ['application_logo_url' => $url]]);
    }

    public function removeLogo(\Illuminate\Http\Request $request, SettingsService $settings, UserActivityLogger $activityLogger): JsonResponse
    {
        $settings->removeLogo($request->user());
        $this->recordSettingsEvent($activityLogger, 'settings.logo_removed', 'Administrator removed workspace branding.', $request->user(), ['group' => 'general', 'changed_keys' => ['application_logo']]);
        return response()->json(['message' => 'Workspace logo removed.', 'data' => ['application_logo_url' => null]]);
    }

    public function testEmail(TestSettingsEmailRequest $request, SettingsService $settings): JsonResponse
    {
        $settings->applyRuntimeConfig();
        try {
            Mail::raw('This is a Planora administrator email configuration test.', function ($message) use ($request): void {
                $message->to($request->validated('recipient'))->subject('Planora email configuration test');
            });
        } catch (Throwable $exception) {
            Log::warning('Settings test email failed.', ['exception_type' => get_class($exception)]);
            return response()->json(['message' => 'The configured mail transport could not send the test email. Verify the mail settings and try again.'], 422);
        }

        return response()->json(['message' => 'The mail transport accepted the test email.']);
    }

    private function recordSettingsEvent(UserActivityLogger $logger, string $event, string $description, \App\Models\User $actor, array $context): void
    {
        try {
            $logger->record($event, $description, actor: $actor, context: $context, module: 'Settings');
        } catch (Throwable $exception) {
            Log::warning('Settings audit event could not be recorded.', ['exception_type' => get_class($exception)]);
        }
    }
}
