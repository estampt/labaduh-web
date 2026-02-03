<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Who created the order
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();

            // Match your tracking UI (pickup_scheduled, picked_up, washing, ready, delivered, cancelled)
            $table->string('status', 32)->default('published');

            // Search context (for discovery/quote reproducibility)
            $table->decimal('search_lat', 10, 7)->nullable();
            $table->decimal('search_lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_km')->default(3);

            // Pickup selection (ASAP / tomorrow / schedule)
            $table->string('pickup_mode', 16)->default('tomorrow'); // asap|tomorrow|schedule
            $table->dateTime('pickup_window_start')->nullable();
            $table->dateTime('pickup_window_end')->nullable();

            // Delivery selection (pickup_deliver / walk_in)
            $table->string('delivery_mode', 16)->default('pickup_deliver'); // pickup_deliver|walk_in

            // Address (flexible: use IDs if you have an addresses table; keep snapshots too)
            $table->unsignedBigInteger('pickup_address_id')->nullable();
            $table->unsignedBigInteger('delivery_address_id')->nullable();
            $table->json('pickup_address_snapshot')->nullable();
            $table->json('delivery_address_snapshot')->nullable();

            // Totals
            $table->char('currency', 3)->default('SGD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Vendor acceptance (filled later when accepted)
            $table->foreignId('accepted_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('accepted_shop_id')->nullable()->constrained('vendor_shops')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
