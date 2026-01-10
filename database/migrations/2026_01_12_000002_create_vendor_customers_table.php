<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->index();
            $table->foreignId('first_completed_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('first_completed_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id','customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_customers');
    }
};
