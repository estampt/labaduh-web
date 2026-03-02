<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentEventRecorder;
use App\Support\OrderTimelineKeys;
use App\Services\OrderTimelineRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class XenditWebhookController extends Controller
{
    public function __construct(private PaymentEventRecorder $recorder) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $data = json_decode($payload, true) ?? [];
        $hash = hash('sha256', $payload);

        // Xendit commonly sends a callback token header (configure in dashboard)
        $token = $request->header('x-callback-token') ?? $request->header('X-CALLBACK-TOKEN');
        $sigVerified = ($token && $token === config('services.xendit.webhook_token')) ? 'yes' : 'no';

        // Example: invoice.paid / invoice.expired
        $eventType = $data['event'] ?? ($data['status'] ?? 'unknown');

        // Find payment by provider_ref (invoice id) OR external_id
        $invoiceId = $data['id'] ?? null;
        $externalId = $data['external_id'] ?? null;

        $payment = Payment::query()
            ->where('provider', 'xendit')
            ->where(function ($q) use ($invoiceId, $externalId) {
                if ($invoiceId) $q->orWhere('provider_ref', $invoiceId);
                if ($externalId) $q->orWhere('provider_ref2', $externalId);
            })
            ->latest()
            ->first();

        if (!$payment) {
            return response('payment not found', 200);
        }

        // ✅ Do everything idempotently inside a transaction (prevents double transitions)
        DB::transaction(function () use (&$payment, $data, $eventType, $sigVerified, $invoiceId, $hash) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $before = $payment->status;

            $status = strtolower((string)($data['status'] ?? ''));
            if ($status === 'paid' || ($eventType === 'invoice.paid')) {
                // Idempotent
                if ($payment->status !== 'paid') {
                    $payment->status = 'paid';
                    $payment->paid_at = $payment->paid_at ?? now();
                    $payment->save();
                }
            } elseif ($status === 'expired' || ($eventType === 'invoice.expired')) {
                if (!in_array($payment->status, ['expired', 'paid'], true)) {
                    $payment->status = 'expired';
                    $payment->failed_at = $payment->failed_at ?? now();
                    $payment->save();
                }
            } elseif ($status === 'failed' || ($eventType === 'invoice.failed')) {
                if (!in_array($payment->status, ['failed', 'paid'], true)) {
                    $payment->status = 'failed';
                    $payment->failed_at = $payment->failed_at ?? now();
                    $payment->save();
                }
            }

            // amount from xendit payload is usually major units; convert to cents if provided
            $amountMajor = $data['amount'] ?? null;
            $amountCents = is_numeric($amountMajor) ? (int) round(((float)$amountMajor) * 100) : null;

            // ✅ Record webhook event (audit trail)
            $this->recorder->record(
                payment: $payment,
                provider: 'xendit',
                eventType: $eventType,
                providerEventId: $invoiceId,
                signatureVerified: $sigVerified,
                payload: $data,
                statusBefore: $before,
                statusAfter: $payment->status,
                amount: $amountCents,
                currency: $data['currency'] ?? 'PHP',
                payloadHash: $hash
            );

            // ✅ Sync order.payment_status + optional order.status progression
            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return;
            }

            // If already paid, do nothing (idempotent)
            if (($order->payment_status ?? null) === 'paid') {
                return;
            }

            if ($payment->status === 'paid') {
                // Update order payment fields
                $order->payment_status = 'paid';
                if (property_exists($order, 'paid_at') || array_key_exists('paid_at', $order->getAttributes())) {
                    $order->paid_at = $order->paid_at ?? now();
                }
                $order->save();

                // Timeline record (optional but recommended)
                app(OrderTimelineRecorder::class)->record(
                    $order,
                    'payment_paid',
                    'system',
                    null,
                    [
                        'provider' => 'xendit',
                        'method' => $payment->method,
                        'payment_id' => $payment->id,
                        'invoice_id' => $payment->provider_ref,
                    ]
                );

                // ✅ Optional auto-advance ONLY if your flow is ready
                // Your customer confirms weight via weightAccepted() -> WEIGHT_ACCEPTED
                if ($order->status === OrderTimelineKeys::READY_FOR_WASHING) {
                    // If you have a constant for WASHING, prefer it; else keep as-is to avoid breaking transitions.
                    $next = defined(OrderTimelineKeys::class . '::WASHING')
                        ? constant(OrderTimelineKeys::class . '::WASHING')
                        : null;

                    if ($next && $next !== $order->status) {
                        $order->status = $next;
                        $order->save();

                        app(OrderTimelineRecorder::class)->record(
                            $order,
                            $next,
                            'system',
                            null,
                            ['reason' => 'auto_after_payment']
                        );
                    }
                }
            }
        });

        return response('ok', 200);
    }
}
