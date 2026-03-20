<?php

namespace App\Http\Controllers\Api\V1\Payments;

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

        // ✅ Verify callback token (Xendit sends x-callback-token header if configured)
        $token = $request->header('x-callback-token') ?? $request->header('X-CALLBACK-TOKEN');
        $sigVerified = ($token && $token === config('services.xendit.webhook_token')) ? 'yes' : 'no';

        // Xendit invoice events often include: event, status, id, external_id, amount, currency
        $eventType = $data['event'] ?? ($data['status'] ?? 'unknown');

        $invoiceId = $data['id'] ?? null;
        $externalId = $data['external_id'] ?? null;

        // Find payment by invoice id or external id
        $payment = Payment::query()
            ->where('provider', 'xendit')
            ->where(function ($q) use ($invoiceId, $externalId) {
                if ($invoiceId) $q->orWhere('provider_ref', $invoiceId);
                if ($externalId) $q->orWhere('provider_ref2', $externalId);
            })
            ->latest()
            ->first();

        if (!$payment) {
            // Return 200 so Xendit doesn't keep retrying forever
            return response('payment not found', 200);
        }

        DB::transaction(function () use ($payment, $data, $hash, $sigVerified, $eventType, $invoiceId) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $before = $payment->status;

            $status = strtolower((string)($data['status'] ?? ''));

            // ✅ Map Xendit invoice status -> our payment status
            if ($status === 'paid' || $eventType === 'invoice.paid') {
                $payment->status = 'paid';
                $payment->paid_at = $payment->paid_at ?? now();
            } elseif ($status === 'expired' || $eventType === 'invoice.expired') {
                $payment->status = 'expired';
                $payment->failed_at = $payment->failed_at ?? now();
            } elseif ($status === 'failed' || $eventType === 'invoice.failed') {
                $payment->status = 'failed';
                $payment->failed_at = $payment->failed_at ?? now();
            }

            // Xendit amount is major units
            $amountMajor = $data['amount'] ?? null;
            $amountCents = is_numeric($amountMajor) ? (int) round(((float)$amountMajor) * 100) : null;

            $payment->metadata = array_merge($payment->metadata ?? [], [
                'invoice_status' => $data['status'] ?? null,
            ]);

            $payment->save();

            // Audit event
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

            // ✅ Sync to order
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();
            if (!$order) return;

            // If already paid, do nothing
            if (($order->payment_status ?? null) === 'paid') return;

            if ($payment->status === 'paid') {
                $order->payment_status = 'paid';

                // advance order after payment
                if ($order->status === OrderTimelineKeys::WEIGHT_ACCEPTED||$order->status === OrderTimelineKeys::AWAITING_PAYMENT) {
                    $order->status = OrderTimelineKeys::READY_FOR_WASHING;

                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        OrderTimelineKeys::READY_FOR_WASHING,
                        'system',
                        null,
                        ['provider' => 'xendit', 'payment_id' => $payment->id]
                    );
                }

                $order->save();

                app(OrderTimelineRecorder::class)->record(
                    $order,
                    'payment_paid',
                    'system',
                    null,
                    ['provider' => 'xendit', 'payment_id' => $payment->id]
                );
            }

            if (in_array($payment->status, ['failed', 'expired'], true)) {
                $order->payment_status = 'failed';
                $order->save();
            }
        });

        return response('ok', 200);
    }
}
