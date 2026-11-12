<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CategorySeeder extends Seeder
{
    private const CATEGORIES = [
        ['name' => 'Web Development', 'description' => 'Build and improve responsive web experiences, application features, and integrations.', 'color' => '#5B6CFA'],
        ['name' => 'UI/UX Design', 'description' => 'Research user needs and shape clear, accessible product experiences and visual systems.', 'color' => '#8B5CF6'],
        ['name' => 'Mobile Development', 'description' => 'Deliver reliable mobile application features across supported devices and platforms.', 'color' => '#0EA5E9'],
        ['name' => 'Database', 'description' => 'Design, optimize, and maintain data models, queries, and database reliability.', 'color' => '#0D9488'],
        ['name' => 'Marketing', 'description' => 'Plan product campaigns, launch communications, and customer outreach.', 'color' => '#F59E0B'],
        ['name' => 'Research', 'description' => 'Gather customer insight and validate product decisions through evidence and experiments.', 'color' => '#3B82F6'],
        ['name' => 'Bug Fixes', 'description' => 'Investigate defects and deliver focused fixes that improve product quality.', 'color' => '#EF4444'],
        ['name' => 'Documentation', 'description' => 'Create useful product guides, technical references, and team documentation.', 'color' => '#64748B'],
        ['name' => 'DevOps', 'description' => 'Improve deployment workflows, infrastructure, observability, and platform reliability.', 'color' => '#10B981'],
        ['name' => 'Content Creation', 'description' => 'Develop clear, engaging content for product education and customer communication.', 'color' => '#EC4899'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('CategorySeeder is restricted to the local environment.');
        }

        DB::transaction(function (): void {
            foreach (self::CATEGORIES as $attributes) {
                $exists = Category::withTrashed()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($attributes['name'])])
                    ->exists();

                if (! $exists) {
                    Category::query()->create($attributes);
                }
            }
        });
    }
}
