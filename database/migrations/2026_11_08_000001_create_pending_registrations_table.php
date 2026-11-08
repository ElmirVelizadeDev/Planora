<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('password_hash')->nullable();
            $table->string('otp_hash', 255)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['verified_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
