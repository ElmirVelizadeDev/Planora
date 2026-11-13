<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->unique();
            $table->string('module', 40)->nullable();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 190)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('actor_role', 30)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('status', 16)->default('success');

            $table->index(['module', 'created_at'], 'user_activity_logs_module_created_index');
            $table->index(['event', 'created_at'], 'user_activity_logs_event_created_index');
            $table->index(['subject_type', 'subject_id'], 'user_activity_logs_subject_index');
            $table->index(['status', 'created_at'], 'user_activity_logs_status_created_index');
            $table->index(['actor_id', 'created_at'], 'user_activity_logs_actor_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table): void {
            $table->dropIndex('user_activity_logs_actor_created_index');
            $table->dropIndex('user_activity_logs_status_created_index');
            $table->dropIndex('user_activity_logs_subject_index');
            $table->dropIndex('user_activity_logs_event_created_index');
            $table->dropIndex('user_activity_logs_module_created_index');
            $table->dropUnique(['event_id']);
            $table->dropColumn([
                'event_id', 'module', 'subject_type', 'subject_id', 'subject_label',
                'old_values', 'new_values', 'actor_name', 'actor_email', 'actor_role',
                'ip_address', 'user_agent', 'request_method', 'request_path', 'request_id', 'status',
            ]);
        });
    }
};
