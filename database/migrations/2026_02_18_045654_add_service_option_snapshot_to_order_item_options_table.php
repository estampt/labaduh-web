<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            $table->string('service_option_name')->nullable()->after('service_option_id');
            $table->text('service_option_description')->nullable()->after('service_option_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            $table->dropColumn(['service_option_name', 'service_option_description']);
        });
    }

};
