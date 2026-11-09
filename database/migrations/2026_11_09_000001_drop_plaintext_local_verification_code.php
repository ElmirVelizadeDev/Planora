<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('email_verification_otps', 'otp_code')) {
            Schema::table('email_verification_otps', function (Blueprint $table) {
                $table->dropColumn('otp_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('email_verification_otps', 'otp_code')) {
            Schema::table('email_verification_otps', function (Blueprint $table) {
                $table->string('otp_code', 6)->nullable();
            });
        }
    }
};
