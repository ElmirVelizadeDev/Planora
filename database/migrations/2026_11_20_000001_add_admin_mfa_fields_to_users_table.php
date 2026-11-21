<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->timestamp('mfa_enrollment_expires_at')->nullable();
            $table->unsignedBigInteger('mfa_last_counter')->nullable();
        });

        Schema::create('admin_mfa_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_mfa_recovery_codes');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mfa_secret', 'mfa_enabled_at', 'mfa_enrollment_expires_at', 'mfa_last_counter']);
        });
    }
};
