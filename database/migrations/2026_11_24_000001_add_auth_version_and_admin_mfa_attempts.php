<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('auth_version')->default(1);
        });

        Schema::create('admin_mfa_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('flow', 24);
            $table->string('challenge_key', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('window_expires_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'flow', 'challenge_key']);
            $table->index(['challenge_key', 'window_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_mfa_attempts');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('auth_version');
        });
    }
};
