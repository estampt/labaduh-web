<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            // Keep MEDIUMINT UNSIGNED PK
            $table->mediumIncrements('id');

            $table->string('name', 100);

            // NEW: JSON translations (for multilingual support)
            // Example structure: { "en": "Europe", "es": "Europa", "fr": "Europe" }
            $table->json('translations')->nullable()->comment('Multilingual region names');

            // region belongs to continent (continents.id is typically BIGINT UNSIGNED)
            $table->foreignId('continent_id')
                  ->constrained('continents')
                  ->cascadeOnDelete();

            $table->tinyInteger('flag')->default(1);
            $table->string('wikiDataId', 255)->nullable()->comment('Rapid API GeoDB Cities');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
