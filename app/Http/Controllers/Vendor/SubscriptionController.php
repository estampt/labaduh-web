<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Vendor/Subscription', [
            'current' => [
                'tier' => 'free',
                'renews_at' => null,
            ],
            'plans' => [
                ['tier' => 'free', 'price' => 0, 'boost' => 0],
                ['tier' => 'pro', 'price' => 999, 'boost' => 10],
                ['tier' => 'premium', 'price' => 1999, 'boost' => 20],
            ],
        ]);
    }
}
