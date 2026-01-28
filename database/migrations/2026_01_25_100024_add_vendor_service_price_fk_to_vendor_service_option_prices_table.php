<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_service_option_prices', function (Blueprint $table) {
            // In case an FK already exists with a different name, try dropping by column
            // (If it doesn't exist, MySQL will error — see note below)
            // $table->dropForeign(['vendor_service_price_id']);

            $table->foreign('vendor_service_price_id')
                ->references('id')
                ->on('vendor_service_prices')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_service_option_prices', function (Blueprint $table) {
            $table->dropForeign(['vendor_service_price_id']);
        });
    }
};
