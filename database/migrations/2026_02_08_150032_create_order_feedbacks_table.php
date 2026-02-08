<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('vendor_shop_id');
            $table->unsignedBigInteger('customer_id');

            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->unique('order_id');

            $table->index(['vendor_shop_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('vendor_shop_id')->references('id')->on('vendor_shops')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('order_feedbacks');
    }
};
