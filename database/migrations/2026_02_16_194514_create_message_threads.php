<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('scope', 20); // 'order' | 'shop'

            $table->unsignedBigInteger('order_id')->nullable();       // required if scope=order
            $table->unsignedBigInteger('shop_id')->nullable(); // required if scope=shop (and recommended for order too)

            $table->unsignedBigInteger('customer_user_id');
            $table->unsignedBigInteger('vendor_user_id')->nullable();

            $table->timestamp('locked_at')->nullable();
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['customer_user_id']);
            $table->index(['vendor_user_id']);
            $table->index(['shop_id']);
            $table->index(['order_id']);
            $table->index(['scope']);

            // Uniqueness rules:
            // 1) one order thread per order
            $table->unique(['scope', 'order_id'], 'threads_scope_order_unique');
            // 2) one shop thread per customer+shop
            $table->unique(['scope', 'shop_id', 'customer_user_id'], 'threads_scope_shop_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};
