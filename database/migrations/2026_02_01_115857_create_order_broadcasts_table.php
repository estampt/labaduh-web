<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_broadcasts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('vendor_shops')->cascadeOnDelete();

            $table->string('status', 16)->default('sent'); // sent|viewed|accepted|rejected|expired
            $table->decimal('quoted_total', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'shop_id'], 'order_broadcasts_unique');
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_broadcasts');
    }
};
