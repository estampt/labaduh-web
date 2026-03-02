<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentEvent;

class PaymentEventRecorder
{
    public function record(
        Payment $payment,
        string $provider,
        string $eventType,
        ?string $providerEventId,
        string $signatureVerified = 'unknown',
        ?array $payload = null,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?int $amount = null,
        ?string $currency = null,
        ?string $notes = null,
        ?string $payloadHash = null
    ): PaymentEvent {
        return PaymentEvent::create([
            'payment_id' => $payment->id,
            'provider' => $provider,
            'event_type' => $eventType,
            'provider_event_id' => $providerEventId,
            'signature_verified' => $signatureVerified,
            'payload_hash' => $payloadHash,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'amount' => $amount,
            'currency' => $currency,
            'payload' => $payload,
            'notes' => $notes,
        ]);
    }
}
