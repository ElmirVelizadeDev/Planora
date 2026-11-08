<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp_hash', 255);
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        DB::table('pending_registrations')
            ->whereNull('verified_at')
            ->whereNotNull('otp_hash')
            ->orderBy('id')
            ->chunk(200, function ($registrations) {
                foreach ($registrations as $registration) {
                    DB::table('email_verification_otps')->updateOrInsert(
                        ['email' => $registration->email],
                        [
                            'otp_hash' => $registration->otp_hash,
                            'expires_at' => $registration->expires_at ?? now()->subSecond(),
                            'attempts' => $registration->attempts ?? 0,
                            'last_sent_at' => $registration->last_sent_at,
                            'created_at' => $registration->created_at,
                            'updated_at' => $registration->updated_at,
                        ],
                    );
                }
            });

        // Completed pending rows contain no account data needed after verification.
        DB::table('pending_registrations')->whereNotNull('verified_at')->delete();
        DB::table('pending_registrations')->orderBy('id')->chunk(200, function ($registrations) {
            foreach ($registrations as $registration) {
                DB::table('pending_registrations')->where('id', $registration->id)->update([
                    'expires_at' => \Illuminate\Support\Carbon::parse($registration->created_at)->addDay(),
                ]);
            }
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex('pending_registrations_verified_at_expires_at_index');
            $table->dropColumn(['otp_hash', 'last_sent_at', 'attempts', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->string('otp_hash', 255)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->index('expires_at');
            $table->index(['verified_at', 'expires_at']);
        });

        DB::table('email_verification_otps')->orderBy('id')->chunk(200, function ($otps) {
            foreach ($otps as $otp) {
                DB::table('pending_registrations')->where('email', $otp->email)->update([
                    'otp_hash' => $otp->otp_hash,
                    'last_sent_at' => $otp->last_sent_at,
                    'attempts' => $otp->attempts,
                    'updated_at' => $otp->updated_at,
                ]);
            }
        });

        Schema::dropIfExists('email_verification_otps');
    }
};
