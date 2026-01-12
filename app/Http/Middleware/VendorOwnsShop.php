<?php

namespace App\Http\Middleware;

use App\Models\VendorShop;
use Closure;
use Illuminate\Http\Request;

class VendorOwnsShop
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $shopParam = $request->route('shop') ?? $request->route('shop_id') ?? $request->route('vendorShop');
        $shopId = null;

        if ($shopParam instanceof VendorShop) {
            $shopId = $shopParam->id;
        } elseif (is_numeric($shopParam)) {
            $shopId = (int) $shopParam;
        }

        if (!$shopId) {
            // Nothing to validate; let controller validate
            return $next($request);
        }

        $shop = VendorShop::query()->find($shopId);
        if (!$shop) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        // vendor user should have vendor_id
        if ((int) ($user->vendor_id ?? 0) !== (int) $shop->vendor_id) {
            return response()->json(['message' => 'Forbidden (shop not owned).'], 403);
        }

        // Put loaded model for controllers to reuse
        $request->attributes->set('owned_shop', $shop);

        return $next($request);
    }
}
