<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pickup_provider', 16)->default('vendor')->after('delivery_mode');
            // vendor | driver

            $table->string('delivery_provider', 16)->default('vendor')->after('pickup_provider');
            // vendor | driver

            $table->unsignedBigInteger('pickup_driver_id')->nullable()->after('delivery_provider');
            $table->unsignedBigInteger('delivery_driver_id')->nullable()->after('pickup_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pickup_provider','delivery_provider','pickup_driver_id','delivery_driver_id']);
        });
    }
};
