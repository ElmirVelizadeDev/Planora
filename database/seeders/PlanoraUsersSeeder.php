<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlanoraUsersSeeder extends Seeder
{
    /** @var array<int, array{name: string, email: string, role: string}> */
    private const USERS = [
        ['name' => 'Admin', 'email' => 'admin@gmail.com', 'role' => 'admin'],
        ['name' => 'Moderator One', 'email' => 'moderator1@gmail.com', 'role' => 'moderator'],
        ['name' => 'Moderator Two', 'email' => 'moderator2@gmail.com', 'role' => 'moderator'],
        ['name' => 'User One', 'email' => 'user1@gmail.com', 'role' => 'user'],
        ['name' => 'User Two', 'email' => 'user2@gmail.com', 'role' => 'user'],
        ['name' => 'User Three', 'email' => 'user3@gmail.com', 'role' => 'user'],
        ['name' => 'User Four', 'email' => 'user4@gmail.com', 'role' => 'user'],
        ['name' => 'User Five', 'email' => 'user5@gmail.com', 'role' => 'user'],
        ['name' => 'User Six', 'email' => 'user6@gmail.com', 'role' => 'user'],
        ['name' => 'User Seven', 'email' => 'user7@gmail.com', 'role' => 'user'],
        ['name' => 'Moderator Three', 'email' => 'moderator3@gmail.com', 'role' => 'moderator'],
        ['name' => 'User Eight', 'email' => 'user8@gmail.com', 'role' => 'user'],
        ['name' => 'User Nine', 'email' => 'user9@gmail.com', 'role' => 'user'],
        ['name' => 'User Ten', 'email' => 'user10@gmail.com', 'role' => 'user'],
        ['name' => 'User Eleven', 'email' => 'user11@gmail.com', 'role' => 'user'],
        ['name' => 'User Twelve', 'email' => 'user12@gmail.com', 'role' => 'user'],
        ['name' => 'User Thirteen', 'email' => 'user13@gmail.com', 'role' => 'user'],
        ['name' => 'User Fourteen', 'email' => 'user14@gmail.com', 'role' => 'user'],
        ['name' => 'User Fifteen', 'email' => 'user15@gmail.com', 'role' => 'user'],
        ['name' => 'User Sixteen', 'email' => 'user16@gmail.com', 'role' => 'user'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $seedUser) {
            // These exact fixture accounts are verified by the trusted seeder, not by public registration.
            $existingUser = User::withTrashed()->where('email', $seedUser['email'])->first();
            if ($existingUser) {
                if ($existingUser->email_verified_at === null) {
                    $existingUser->email_verified_at = now();
                    $existingUser->saveQuietly();
                }

                continue;
            }

            $user = new User();
            $user->name = $seedUser['name'];
            $user->email = $seedUser['email'];
            $user->password = Hash::make('password');
            $user->role = $seedUser['role'];
            $user->status = 'active';
            $user->email_verified_at = now();
            $user->moderator_permissions = $seedUser['role'] === 'moderator'
                ? ['users.view']
                : null;
            $user->save();
        }
    }
}
