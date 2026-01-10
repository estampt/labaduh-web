<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->index(); // users.id (role=customer)
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();

            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['vendor_id','is_visible']);
            $table->unique(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reviews');
    }
};
