<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Contact
            if (!Schema::hasColumn('users', 'contact_number')) {
                $table->string('contact_number', 40)->nullable()->after('email');
                $table->index('contact_number');
            }

            // Structured address
            if (!Schema::hasColumn('users', 'address_line1')) {
                $table->string('address_line1')->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('users', 'address_line2')) {
                $table->string('address_line2')->nullable()->after('address_line1');
            }
            if (!Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('address_line2');
            }

            // Location master FKs
            if (!Schema::hasColumn('users', 'country_id')) {
                $table->foreignId('country_id')->nullable()->after('postal_code')
                    ->constrained('countries')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'state_province_id')) {
                $table->foreignId('state_province_id')->nullable()->after('country_id')
                    ->constrained('state_province')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('state_province_id')
                    ->constrained('cities')->nullOnDelete();
            }

            // Geo
            if (!Schema::hasColumn('users', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('city_id');
            }
            if (!Schema::hasColumn('users', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            $table->index(['latitude', 'longitude']);

            // Social IDs
            if (!Schema::hasColumn('users', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->after('facebook_id');
            }
            if (!Schema::hasColumn('users', 'twitter_id')) {
                $table->string('twitter_id')->nullable()->after('google_id');
            }
            if (!Schema::hasColumn('users', 'apple_id')) {
                $table->string('apple_id')->nullable()->after('twitter_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // Add unique indexes (best-effort)
            if (Schema::hasColumn('users', 'facebook_id')) { try { $table->unique('facebook_id'); } catch (\Throwable $e) {} }
            if (Schema::hasColumn('users', 'google_id')) { try { $table->unique('google_id'); } catch (\Throwable $e) {} }
            if (Schema::hasColumn('users', 'twitter_id')) { try { $table->unique('twitter_id'); } catch (\Throwable $e) {} }
            if (Schema::hasColumn('users', 'apple_id')) { try { $table->unique('apple_id'); } catch (\Throwable $e) {} }

            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }

            if (!Schema::hasColumn('users', 'badge')) {
                $table->string('badge', 50)->nullable()->after('phone_verified_at');
                $table->index('badge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'country_id')) { try { $table->dropForeign(['country_id']); } catch (\Throwable $e) {} }
            if (Schema::hasColumn('users', 'state_province_id')) { try { $table->dropForeign(['state_province_id']); } catch (\Throwable $e) {} }
            if (Schema::hasColumn('users', 'city_id')) { try { $table->dropForeign(['city_id']); } catch (\Throwable $e) {} }

            foreach (['facebook_id','google_id','twitter_id','apple_id'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    try { $table->dropUnique("users_{$col}_unique"); } catch (\Throwable $e) {}
                }
            }

            $cols = [
                'contact_number','address_line1','address_line2','postal_code',
                'country_id','state_province_id','city_id','latitude','longitude',
                'facebook_id','google_id','twitter_id','apple_id',
                'phone_verified_at','badge',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    try { $table->dropColumn($col); } catch (\Throwable $e) {}
                }
            }
        });
    }
};
