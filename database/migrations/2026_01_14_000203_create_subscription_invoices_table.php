<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_subscription_id')->constrained('vendor_subscriptions')->cascadeOnDelete();

            $table->string('invoice_no')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('PHP');
            $table->enum('status', ['pending','paid','failed','refunded'])->default('pending')->index();

            $table->string('provider')->nullable();
            $table->string('provider_invoice_id')->nullable()->index();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
