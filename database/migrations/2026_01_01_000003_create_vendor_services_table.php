<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_weight_kg', 6, 2)->default(6);
            $table->decimal('base_price', 10, 2);
            $table->decimal('price_per_extra_kg', 10, 2)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['vendor_id','service_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('vendor_services'); }
};
