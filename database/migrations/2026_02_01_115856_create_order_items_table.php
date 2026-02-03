<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();

            // Customer quantity
            $table->decimal('qty', 10, 2);
            $table->string('uom', 16)->nullable(); // kg / piece etc (snapshot)

            // Snapshot of pricing rule used (from chosen shop quote / or best min quote)
            $table->string('pricing_model', 32)->nullable();
            $table->decimal('minimum', 10, 2)->nullable();
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('price_per_uom', 12, 2)->nullable();

            // Computed item price (service only, excluding options)
            $table->decimal('computed_price', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['order_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
