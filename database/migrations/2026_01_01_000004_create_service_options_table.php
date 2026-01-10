<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('service_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->enum('price_type', ['fixed','per_kg','per_item'])->default('fixed');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('service_options'); }
};
