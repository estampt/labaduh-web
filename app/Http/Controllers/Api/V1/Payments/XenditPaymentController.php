<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\OrderTimelineKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ✅ NEW Xendit SDK imports
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class XenditPaymentController extends Controller
{
    /**
     * Create a Xendit Invoice for GCash payment.
     * Trigger AFTER order is WEIGHT_ACCEPTED (same as Stripe).
     */
    public function createGcashInvoice(Request $request, Order $order)
    {
        $user = $request->user();

        abort_unless((int) $order->customer_id === (int) $user->id, 403, 'Forbidden');
        abort_unless($order->status === OrderTimelineKeys::WEIGHT_ACCEPTED, 409, 'Order not ready for payment.');
        abort_if(($order->payment_status ?? null) === 'paid', 409, 'Order already paid.');

        // ✅ DB amount is DECIMAL pesos; Invoice expects major units PHP
        $finalPesos = (float) $order->final_total;
        abort_if($finalPesos <= 0, 422, 'Final total not set.');

        $amountMajor = round($finalPesos, 2);

        return DB::transaction(function () use ($order, $amountMajor) {
            // Create payment row
            $externalId = 'ord_'.$order->id.'_xinv_'.Str::uuid();

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'xendit',
                'method' => 'gcash',
                'status' => 'pending',
                'currency' => 'PHP',
                // store cents in DB for consistency with Stripe
                'amount_final' => (int) round($amountMajor * 100),
                'provider_ref2' => $externalId, // external_id
                'dedupe_key' => 'xendit:invoice:'.$externalId,
            ]);

            // ✅ Init Xendit (NEW SDK)
            Configuration::setXenditKey(config('services.xendit.secret'));

            $api = new InvoiceApi();

            // ✅ Create invoice request
            $req = new CreateInvoiceRequest([
                'external_id' => $externalId,
                'amount' => $amountMajor,
                'currency' => 'PHP',
                'description' => 'Labaduh Order #'.$order->id,

                // Optional if you have it; safe to omit if not in schema
                // 'payer_email' => $order->customer_email ?? null,

                // Add metadata for linking/debugging
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'payment_id' => (string) $payment->id,
                    'method' => 'gcash',
                ],
            ]);

            $invoice = $api->createInvoice($req);

            // ✅ Persist references
            $payment->update([
                'provider_ref' => $invoice->getId(), // invoice id
                'metadata' => array_merge($payment->metadata ?? [], [
                    'invoice_status' => $invoice->getStatus(),
                    'invoice_url' => $invoice->getInvoiceUrl(),
                    'expiry_date' => method_exists($invoice, 'getExpiryDate') ? $invoice->getExpiryDate() : null,
                ]),
            ]);

            // Keep order unpaid until webhook says paid
            $order->payment_status = $order->payment_status ?: 'unpaid';
            $order->save();

            return response()->json([
                'payment_id' => $payment->id,
                'provider' => 'xendit',
                'invoice_id' => $invoice->getId(),
                'external_id' => $externalId,
                'status' => $invoice->getStatus(),
                'amount' => $amountMajor,
                'currency' => 'PHP',
                'invoice_url' => $invoice->getInvoiceUrl(),
            ]);
        });
    }
}
