<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Vendor;

class VendorOwnsVendor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Admin bypass (adjust if your app uses different flags/roles)
        $role = $user->role ?? null;
        $isAdmin = ($role === 'admin') || (($user->is_admin ?? false) == true);
        if ($isAdmin) {
            return $next($request);
        }

        // Route param can be model-bound (Vendor) or an id string
        $routeVendor = $request->route('vendor');

        $routeVendorId = null;
        if ($routeVendor instanceof Vendor) {
            $routeVendorId = $routeVendor->id;
        } else {
            $routeVendorId = is_numeric($routeVendor) ? (int)$routeVendor : null;
        }

        if (!$routeVendorId) {
            return response()->json(['message' => 'Vendor route parameter missing/invalid.'], 400);
        }

        $userVendorId = $user->vendor_id ?? null;

        if (!$userVendorId) {
            return response()->json(['message' => 'Forbidden. Vendor account required.'], 403);
        }

        if ((int)$userVendorId !== (int)$routeVendorId) {
            return response()->json(['message' => 'Forbidden. You do not own this vendor.'], 403);
        }

        return $next($request);
    }
}
