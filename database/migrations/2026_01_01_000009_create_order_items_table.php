<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_service_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight_kg', 10, 2)->default(0);
            $table->unsignedInteger('items')->default(0);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('options_total', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();
            $table->index(['order_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('order_items'); }
};
