<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Link to your orders table (assumes orders.id exists)
            $table->unsignedBigInteger('order_id');

            // Provider + method
            $table->string('provider', 32); // stripe | xendit
            $table->string('method', 32);   // card | gcash

            // Status lifecycle
            // examples: initiated, requires_action, authorized, paid, failed, canceled, expired, refunded, partially_refunded
            $table->string('status', 32)->default('initiated');

            // Currency + amounts stored in MINOR units (e.g., PHP cents)
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('amount_estimated')->nullable();   // initial quote
            $table->unsignedBigInteger('amount_authorized')->nullable();  // Stripe only (auth hold)
            $table->unsignedBigInteger('amount_final')->nullable();       // final after weighing
            $table->unsignedBigInteger('amount_captured')->nullable();    // Stripe captured amount
            $table->unsignedBigInteger('amount_refunded')->default(0);

            // Provider references
            $table->string('provider_ref', 191)->nullable();      // Stripe PI id / Xendit invoice id
            $table->string('provider_ref2', 191)->nullable();     // optional: charge id / payment id
            $table->string('client_secret', 191)->nullable();     // Stripe client secret (optional to store)

            // Idempotency + safety (critical)
            $table->string('idempotency_key', 80)->nullable();
            $table->string('dedupe_key', 120)->nullable(); // e.g. provider:provider_ref for uniqueness

            // For async payments
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Debug-friendly metadata (do NOT store raw card data; only safe metadata)
            $table->json('metadata')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes / constraints
            $table->index(['order_id', 'created_at']);
            $table->index(['provider', 'method', 'status']);
            $table->index(['provider_ref']);
            $table->unique(['dedupe_key']); // ensures no duplicate records for same provider object

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
