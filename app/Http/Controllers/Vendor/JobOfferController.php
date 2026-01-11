<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobOfferController extends Controller
{
    public function index(Request $request)
    {
        // Hook this up to your job_offers table later.
        return Inertia::render('Vendor/JobOffers', [
            'offers' => [],
        ]);
    }
}
