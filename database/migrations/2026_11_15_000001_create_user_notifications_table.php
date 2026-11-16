<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title', 180);
            $table->string('description', 255);
            $table->string('related_type', 40)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('deduplication_key', 64)->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'read_at', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
