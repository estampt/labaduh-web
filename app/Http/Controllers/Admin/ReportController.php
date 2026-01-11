<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Reports', [
            'charts' => [
                'fulfillment_distribution' => [
                    ['name' => 'third_party', 'value' => 70],
                    ['name' => 'inhouse', 'value' => 20],
                    ['name' => 'walk_in', 'value' => 10],
                ],
            ],
        ]);
    }
}
