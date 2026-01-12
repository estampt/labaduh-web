<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_capacities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('vendor_shops')
                ->cascadeOnDelete();

            $table->date('date');

            // Capacity constraints
            $table->unsignedInteger('max_orders')->nullable();
            $table->decimal('max_kg', 10, 2)->nullable();

            // Running counters (optional but useful)
            $table->unsignedInteger('booked_orders')->default(0);
            $table->decimal('booked_kg', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['shop_id', 'date'], 'uniq_shop_capacity_date');
            $table->index(['shop_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_capacities');
    }
};
