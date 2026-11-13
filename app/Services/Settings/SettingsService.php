<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'planora.settings.v1';
    private static mixed $environmentSmtpPassword = null;
    private static bool $environmentConfigCaptured = false;

    public function __construct(private readonly SettingsRegistry $registry)
    {
    }

    public function all(): array
    {
        if (Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY, []);
        }

        $values = $this->defaults();
        try {
            $rows = Setting::query()->get()->keyBy('key');
        } catch (Throwable) {
            return $values;
        }

        foreach ($this->registry->groups() as $group => $definitions) {
            foreach ($definitions as $key => $definition) {
                if ($definition['sensitive'] ?? false) {
                    continue;
                }
                $row = $rows->get($key);
                if ($row) $values[$group][$key] = $this->decode($row);
            }
        }

        Cache::put(self::CACHE_KEY, $values, now()->addMinutes(5));

        return $values;
    }

    public function group(string $group): array
    {
        $definitions = $this->registry->group($group);
        if (! $definitions) {
            throw new \InvalidArgumentException('Unknown settings group.');
        }

        $values = $this->all()[$group] ?? [];
        if ($group === 'general') {
            $path = $values['application_logo'] ?? null;
            unset($values['application_logo']);
            $values['application_logo_url'] = is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
        }
        if ($group !== 'email') {
            return $values;
        }

        unset($values['mail_password']);
        $values['mail_password_configured'] = $this->isSecretConfigured('mail_password');

        return $values;
    }

    public function get(string $key): mixed
    {
        foreach ($this->all() as $group) {
            if (array_key_exists($key, $group)) {
                return $group[$key];
            }
        }

        return null;
    }

    public function isSecretConfigured(string $key): bool
    {
        try {
            $stored = Setting::query()->where('key', $key)->whereNotNull('value')->exists();
            return $stored || ($key === 'mail_password' && filled(config('mail.mailers.smtp.password')));
        } catch (Throwable) {
            return false;
        }
    }

    public function update(string $group, array $input, User $actor, ?string $secretValue = null, bool $removeSecret = false): array
    {
        $definitions = $this->registry->group($group);
        if (! $definitions) {
            throw new \InvalidArgumentException('Unknown settings group.');
        }

        $unknown = array_diff(array_keys($input), array_keys($definitions));
        if ($unknown) {
            throw new \InvalidArgumentException('Unsupported setting keys were supplied.');
        }

        $before = $this->all()[$group] ?? [];
        DB::transaction(function () use ($group, $input, $definitions, $actor, $secretValue, $removeSecret): void {
            foreach ($input as $key => $value) {
                $definition = $definitions[$key];
                if (($definition['sensitive'] ?? false) && ($value === null || $value === '')) {
                    continue;
                }

                $encoded = $this->encode($value, $definition);
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['group' => $group, 'value' => $encoded, 'type' => $definition['type'], 'is_sensitive' => (bool) ($definition['sensitive'] ?? false), 'updated_by' => $actor->id],
                );
            }
            if ($group === 'email') {
                if ($removeSecret) {
                    Setting::query()->where('key', 'mail_password')->delete();
                } elseif ($secretValue !== null && $secretValue !== '') {
                    Setting::query()->updateOrCreate(['key' => 'mail_password'], [
                        'group' => 'email', 'value' => Crypt::encryptString($secretValue), 'type' => 'string',
                        'is_sensitive' => true, 'updated_by' => $actor->id,
                    ]);
                }
            }
        });

        Cache::forget(self::CACHE_KEY);
        $this->applyRuntimeConfig();

        return ['before' => $before, 'after' => $this->group($group)];
    }

    public function reset(string $group, User $actor): array
    {
        $definitions = $this->registry->group($group);
        if (! $definitions) {
            throw new \InvalidArgumentException('Unknown settings group.');
        }

        $before = $this->all()[$group] ?? [];
        $oldLogo = $group === 'general' ? ($before['application_logo'] ?? null) : null;
        DB::transaction(function () use ($group, $definitions, $actor): void {
            foreach ($definitions as $key => $definition) {
                if (($definition['sensitive'] ?? false) || in_array($key, ['application_logo'], true)) {
                    Setting::query()->where('key', $key)->delete();
                    continue;
                }
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['group' => $group, 'value' => $this->encode($definition['default'], $definition), 'type' => $definition['type'], 'is_sensitive' => false, 'updated_by' => $actor->id],
                );
            }
        });
        Cache::forget(self::CACHE_KEY);
        $this->applyRuntimeConfig();
        if ($this->isBrandingPath($oldLogo)) Storage::disk('public')->delete($oldLogo);

        return ['before' => $before, 'after' => $this->group($group)];
    }

    public function applyRuntimeConfig(): void
    {
        $values = $this->all();
        $defaults = $this->registry->groups();
        if (! self::$environmentConfigCaptured) {
            self::$environmentSmtpPassword = config('mail.mailers.smtp.password');
            self::$environmentConfigCaptured = true;
        }
        Config::set('app.name', $values['general']['application_name'] ?? $defaults['general']['application_name']['default']);
        Config::set('app.timezone', $values['general']['timezone'] ?? $defaults['general']['timezone']['default']);
        Config::set('app.date_format', $values['general']['date_format'] ?? 'Y-m-d');
        Config::set('app.time_format', $values['general']['time_format'] ?? '24h');
        date_default_timezone_set((string) config('app.timezone', 'UTC'));

        $email = $values['email'] ?? [];
        $mailer = $email['mail_mailer'] ?? $defaults['email']['mail_mailer']['default'];
        $mailConfigurationChanged = $mailer !== config('mail.default')
            || ($email['mail_host'] ?? $defaults['email']['mail_host']['default']) !== config('mail.mailers.smtp.host')
            || ($email['mail_port'] ?? $defaults['email']['mail_port']['default']) !== config('mail.mailers.smtp.port')
            || (($email['mail_encryption'] ?? 'tls') === 'none' ? null : ($email['mail_encryption'] ?? 'tls')) !== config('mail.mailers.smtp.encryption')
            || ($email['mail_username'] ?? $defaults['email']['mail_username']['default']) !== config('mail.mailers.smtp.username')
            || ($this->secret('mail_password') ?? self::$environmentSmtpPassword) !== config('mail.mailers.smtp.password');
        Config::set('mail.default', $mailer);
        Config::set('mail.from.address', $email['mail_from_address'] ?? $defaults['email']['mail_from_address']['default']);
        Config::set('mail.from.name', $email['mail_from_name'] ?? $defaults['email']['mail_from_name']['default']);
        if ($mailer === 'smtp') {
            Config::set('mail.mailers.smtp.host', $email['mail_host'] ?? $defaults['email']['mail_host']['default']);
            Config::set('mail.mailers.smtp.port', $email['mail_port'] ?? $defaults['email']['mail_port']['default']);
            Config::set('mail.mailers.smtp.encryption', ($email['mail_encryption'] ?? 'tls') === 'none' ? null : $email['mail_encryption']);
            Config::set('mail.mailers.smtp.username', $email['mail_username'] ?? $defaults['email']['mail_username']['default']);
            Config::set('mail.mailers.smtp.password', $this->secret('mail_password') ?? self::$environmentSmtpPassword);
        }
        if ($mailConfigurationChanged) app('mail.manager')->purge();
    }

    public function secret(string $key): ?string
    {
        try {
            $row = Setting::query()->where('key', $key)->where('is_sensitive', true)->first();
            return $row?->value ? Crypt::decryptString($row->value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function updateSecret(string $key, ?string $value, User $actor): void
    {
        if ($value === null) {
            Setting::query()->where('key', $key)->delete();
        } else {
            $definition = $this->registry->group('email')[$key] ?? null;
            if (! $definition || ! ($definition['sensitive'] ?? false)) {
                throw new \InvalidArgumentException('Unsupported sensitive setting.');
            }
            Setting::query()->updateOrCreate(['key' => $key], [
                'group' => 'email', 'value' => Crypt::encryptString($value), 'type' => $definition['type'],
                'is_sensitive' => true, 'updated_by' => $actor->id,
            ]);
        }
        Cache::forget(self::CACHE_KEY);
        $this->applyRuntimeConfig();
    }

    public function uploadLogo(\Illuminate\Http\UploadedFile $file, User $actor): string
    {
        $path = $file->store('settings/branding', 'public');
        $oldPath = $this->get('application_logo');
        try {
            DB::transaction(function () use ($path, $actor): void {
                Setting::query()->updateOrCreate(['key' => 'application_logo'], [
                    'group' => 'general', 'value' => $this->encode($path, ['type' => 'string']), 'type' => 'string',
                    'is_sensitive' => false, 'updated_by' => $actor->id,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
        Cache::forget(self::CACHE_KEY);
        if ($this->isBrandingPath($oldPath) && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return Storage::disk('public')->url($path);
    }

    public function removeLogo(User $actor): void
    {
        $path = $this->get('application_logo');
        DB::transaction(fn () => Setting::query()->where('key', 'application_logo')->delete());
        Cache::forget(self::CACHE_KEY);
        if ($this->isBrandingPath($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function publicValues(): array
    {
        $general = $this->all()['general'] ?? [];
        $path = $this->get('application_logo');
        $general['application_logo_url'] = is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
        return $general;
    }

    public function overview(): array
    {
        $all = $this->all();
        $latest = null;
        $persisted = [];
        try {
            $latest = Setting::query()->with('updater:id,name')->latest('updated_at')->first();
            $persisted = Setting::query()->select('group')->selectRaw('COUNT(*) AS total')->groupBy('group')->pluck('total', 'group')->all();
        } catch (Throwable) {
            // The application remains usable before the additive settings migration is deployed.
        }

        return [
            'groups' => [
                'general' => ['configured' => (int) ($persisted['general'] ?? 0) > 0 && filled($all['general']['workspace_name'] ?? null), 'updated_at' => $this->latestForGroup('general')],
                'security' => ['configured' => true, 'updated_at' => $this->latestForGroup('security')],
                'email' => ['configured' => $this->emailConfigured($all['email'] ?? []), 'updated_at' => $this->latestForGroup('email')],
            ],
            'updated_at' => $latest?->updated_at?->toIso8601String(),
            'updated_by' => $latest?->updater?->name,
        ];
    }

    public function passwordPolicy(): array
    {
        return $this->all()['security'] ?? ($this->defaults()['security'] ?? []);
    }

    private function latestForGroup(string $group): ?string
    {
        try {
            return Setting::query()->where('group', $group)->max('updated_at');
        } catch (Throwable) {
            return null;
        }
    }

    private function emailConfigured(array $email): bool
    {
        $mailer = $email['mail_mailer'] ?? config('mail.default');
        if (in_array($mailer, ['log', 'array', 'sendmail'], true)) return true;
        return $mailer === 'smtp'
            && filled($email['mail_host'] ?? null)
            && filled($email['mail_from_address'] ?? null)
            && ($this->isSecretConfigured('mail_password') || blank($email['mail_username'] ?? null));
    }

    private function encode(mixed $value, array $definition): ?string
    {
        if ($value === null) return null;
        $raw = match ($definition['type']) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            default => (string) $value,
        };
        return ($definition['sensitive'] ?? false) ? Crypt::encryptString($raw) : $raw;
    }

    private function defaults(): array
    {
        $values = [];
        foreach ($this->registry->groups() as $group => $definitions) {
            foreach ($definitions as $key => $definition) {
                $values[$group][$key] = $definition['default'];
            }
        }

        return $values;
    }

    private function isBrandingPath(mixed $path): bool
    {
        return is_string($path) && str_starts_with($path, 'settings/branding/') && ! str_contains($path, '..');
    }

    private function decode(Setting $row): mixed
    {
        $raw = $row->is_sensitive ? Crypt::decryptString((string) $row->value) : $row->value;
        return match ($row->type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            default => $raw,
        };
    }
}
