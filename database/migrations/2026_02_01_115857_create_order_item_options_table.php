<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('service_option_id')->constrained('service_options')->restrictOnDelete();

            // Snapshot pricing (flat add-on)
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_required')->default(false);
            $table->decimal('computed_price', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['order_item_id', 'service_option_id'], 'order_item_options_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_options');
    }
};
