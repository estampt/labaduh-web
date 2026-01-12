<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()
                    ->after('vendor_id')
                    ->constrained('vendor_shops')
                    ->nullOnDelete();
                $table->index('shop_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shop_id')) {
                // drop FK safely (name can vary)
                try {
                    $table->dropForeign(['shop_id']);
                } catch (\Throwable $e) {}
                try {
                    $table->dropIndex(['shop_id']);
                } catch (\Throwable $e) {}
                $table->dropColumn('shop_id');
            }
        });
    }
};
