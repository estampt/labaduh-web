<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'email_otp_hash')) {
                $table->string('email_otp_hash', 255)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'email_otp_expires_at')) {
                $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp_hash');
            }

            if (!Schema::hasColumn('users', 'phone_otp_hash')) {
                $table->string('phone_otp_hash', 255)->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('users', 'phone_otp_expires_at')) {
                $table->timestamp('phone_otp_expires_at')->nullable()->after('phone_otp_hash');
            }

            if (!Schema::hasColumn('users', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('email_verified_at');
                $table->index('is_verified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['email_otp_hash','email_otp_expires_at','phone_otp_hash','phone_otp_expires_at','is_verified'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    try { $table->dropColumn($col); } catch (\Throwable $e) {}
                }
            }
        });
    }
};
