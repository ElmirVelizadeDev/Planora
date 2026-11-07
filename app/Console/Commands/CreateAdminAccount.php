<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminAccount extends Command
{
    protected $signature = 'planora:admin:create {--email=} {--name=}';

    protected $description = 'Create an administrator account without changing an existing account';

    public function handle(UserActivityLogger $activityLogger): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Administrator name')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Administrator email'))));

        $validator = Validator::make(compact('name', 'email'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->error('An account with that email already exists. No account was changed.');

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password');
        $confirmation = $this->secret('Confirm administrator password');

        $passwordValidator = Validator::make([
            'password' => $password,
            'confirmation' => $confirmation,
        ], [
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()],
            'confirmation' => ['required', 'same:password'],
        ]);

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $email, $password, $activityLogger) {
                if (User::withTrashed()->where('email', $email)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('An account with that email already exists.');
                }

                $user = new User();
                $user->name = $name;
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->role = 'admin';
                $user->status = 'active';
                $user->save();
                $activityLogger->record('user.created', 'Administrator account created by the local Artisan command.', $user, null, ['role' => 'admin']);
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Administrator account created. The password was not displayed.');

        return self::SUCCESS;
    }
}
