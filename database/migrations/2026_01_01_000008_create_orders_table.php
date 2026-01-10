<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('vendor_shops')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();

            $table->date('pickup_date')->nullable();
            $table->time('pickup_time_start')->nullable();
            $table->time('pickup_time_end')->nullable();

            $table->enum('status', ['pending','accepted','pickup_scheduled','picked_up','washing','ready_for_delivery','delivered','completed','cancelled'])->default('pending');

            $table->decimal('distance_km', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->index(['vendor_id','shop_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
