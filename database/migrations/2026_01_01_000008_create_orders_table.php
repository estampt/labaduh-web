<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ✅ Creator
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();

            // ✅ Status (flexible, avoids enum pain)
            $table->string('status', 32)->default('published')->index();

            // ✅ Search context (matching)
            $table->decimal('search_lat', 10, 7)->nullable();
            $table->decimal('search_lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_km')->default(3);

            // ✅ Pickup selection
            $table->string('pickup_mode', 16)->default('tomorrow'); // asap|tomorrow|schedule
            $table->dateTime('pickup_window_start')->nullable();
            $table->dateTime('pickup_window_end')->nullable();

            // ✅ Optional legacy schedule fields (keep if your code uses them)
            $table->date('pickup_date')->nullable();
            $table->time('pickup_time_start')->nullable();
            $table->time('pickup_time_end')->nullable();

            // ✅ Delivery selection
            $table->string('delivery_mode', 16)->default('pickup_deliver'); // pickup_deliver|walk_in

            // ✅ Addresses
            $table->unsignedBigInteger('pickup_address_id')->nullable();
            $table->unsignedBigInteger('delivery_address_id')->nullable();
            $table->json('pickup_address_snapshot')->nullable();
            $table->json('delivery_address_snapshot')->nullable();

            // ✅ Logistics coordinates (optional but no clash)
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();

            // ✅ Metrics
            $table->decimal('distance_km', 10, 2)->default(0);

            // ✅ Totals (use consistent precision)
            $table->char('currency', 3)->default('SGD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // ✅ Acceptance (filled later)
            $table->foreignId('accepted_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('accepted_shop_id')->nullable()->constrained('vendor_shops')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Helpful indexes
            $table->index(['customer_id', 'status']);
            $table->index(['accepted_vendor_id', 'status']);
            $table->index(['accepted_shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};