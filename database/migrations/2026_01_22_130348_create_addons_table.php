<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('group_key', 50)->nullable()->index(); // fragrance, speed, treatment
            $table->string('description')->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->enum('price_type', ['fixed','per_kg','per_item']); // add percent later if you want
            $table->boolean('is_required')->default(false);
            $table->boolean('is_multi_select')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
