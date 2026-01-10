<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayMongoService
{
    public function createCheckout(array $payload): array
    {
        // NOTE: This is a scaffold. Implement your PayMongo payload based on their latest docs.
        // Expected return should include checkout_url and provider ids.
        $base = config('payments.paymongo.base_url');
        $key = config('payments.paymongo.secret_key');

        // If key not configured, return a mocked response so dev won't break
        if (!$key) {
            return [
                'provider_intent_id' => null,
                'checkout_url' => null,
                'raw' => ['mock' => true, 'payload' => $payload],
            ];
        }

        $res = Http::withBasicAuth($key, '')
            ->acceptJson()
            ->post($base . '/checkout_sessions', $payload);

        return [
            'provider_intent_id' => data_get($res->json(), 'data.id'),
            'checkout_url' => data_get($res->json(), 'data.attributes.checkout_url'),
            'raw' => $res->json(),
        ];
    }
}
