<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('delivery_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('per_km_rate', 10, 2)->default(0);
            $table->decimal('min_fee', 10, 2)->default(0);
            $table->decimal('max_fee', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['vendor_id','is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('delivery_pricing_rules'); }
};
