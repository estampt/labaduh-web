<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'subscription_tier')) {
                $table->string('subscription_tier')->default('free')->after('is_active')->index();
            }
            if (!Schema::hasColumn('vendors', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable()->after('subscription_tier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'subscription_expires_at')) $table->dropColumn('subscription_expires_at');
            if (Schema::hasColumn('vendors', 'subscription_tier')) $table->dropColumn('subscription_tier');
        });
    }
};
