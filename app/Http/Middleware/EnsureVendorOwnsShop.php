<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class EnsureVendorOwnsShop { public function handle(Request $r, Closure $n){ $v=$r->route('vendor'); $s=$r->route('shop'); if($v && $s && (int)$s->vendor_id !== (int)$v->id) return response()->json(['message'=>'Forbidden. Shop does not belong to vendor.'],403); return $n($r);} }
