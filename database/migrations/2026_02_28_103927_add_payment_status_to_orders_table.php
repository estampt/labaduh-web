<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->enum('payment_status', [
                'unpaid',
                'authorized',
                'paid',
                'failed',
                'refunded',
            ])
            ->default('unpaid')
            ->after('status'); // change position if needed

        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn('payment_status');

        });
    }
};
