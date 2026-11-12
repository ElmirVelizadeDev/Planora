<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanoraUsersSeeder::class);

        if (app()->environment('local')) {
            $this->call([CategorySeeder::class, ProjectDemoSeeder::class, UserTasksDemoSeeder::class]);
        }
    }
}
