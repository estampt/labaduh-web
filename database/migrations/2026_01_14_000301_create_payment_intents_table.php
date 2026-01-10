<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();

            $table->string('purpose')->index(); // order, subscription
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('PHP');

            $table->enum('status', ['pending','requires_action','succeeded','failed','cancelled'])->default('pending')->index();

            $table->string('provider')->default('paymongo');
            $table->string('provider_intent_id')->nullable()->index();
            $table->json('provider_payload')->nullable();

            // For redirect-based payments like GCash
            $table->string('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['purpose','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
