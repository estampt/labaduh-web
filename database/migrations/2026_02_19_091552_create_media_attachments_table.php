<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation
            $table->morphs('owner'); // owner_type + owner_id

            // Storage info
            $table->string('disk')->default('public');
            $table->string('path');

            // Metadata
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Optional categorization
            $table->string('category')->nullable();
            // ex: weight_review, pricing_update, delivery_proof

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
