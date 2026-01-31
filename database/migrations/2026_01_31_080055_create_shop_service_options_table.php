<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_service_options', function (Blueprint $table) {
            $table->id(); // shop_service_options.id

            $table->foreignId('shop_service_id')
                ->constrained('shop_services')
                ->cascadeOnDelete();

            $table->foreignId('service_option_id')
                ->constrained('service_options') // must exist in master
                ->restrictOnDelete();

            // flat add-on price
            $table->decimal('price', 12, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // prevent duplicates under the same shop_service
            $table->unique(['shop_service_id', 'service_option_id'], 'shop_service_options_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_service_options');
    }
};
