<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('payment_id');

            // Provider event info
            $table->string('provider', 32);                 // stripe | xendit
            $table->string('event_type', 120);              // e.g. payment_intent.succeeded / invoice.paid
            $table->string('provider_event_id', 191)->nullable(); // Stripe event id / Xendit callback id

            // Integrity / dedupe
            $table->string('signature_verified', 16)->default('unknown'); // yes | no | unknown
            $table->string('payload_hash', 64)->nullable();               // sha256 hex
            $table->string('idempotency_key', 80)->nullable();

            // State change
            $table->string('status_before', 32)->nullable();
            $table->string('status_after', 32)->nullable();

            // Useful amounts from event (minor units)
            $table->unsignedBigInteger('amount')->nullable();
            $table->char('currency', 3)->nullable();

            // Store *sanitized* payload (or subset) for debugging
            $table->json('payload')->nullable();

            // Optional operator/system notes
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'created_at']);
            $table->index(['provider', 'event_type']);
            $table->index(['provider_event_id']);
            $table->index(['payload_hash']);

            // Prevent duplicates for the same provider event id (when available)
            $table->unique(['provider', 'provider_event_id']);

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
