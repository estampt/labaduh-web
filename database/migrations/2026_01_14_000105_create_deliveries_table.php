<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('vendor_shops')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            $table->enum('type', ['pickup','dropoff'])->index();
            $table->enum('status', ['pending','assigned','accepted','arrived','in_transit','completed','cancelled'])->default('pending')->index();

            $table->timestamp('scheduled_at')->nullable()->index();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();

            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('fee', 10, 2)->nullable();

            $table->timestamps();

            $table->index(['vendor_id','shop_id','status']);
            $table->unique(['order_id','type']); // one pickup + one dropoff per order
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
