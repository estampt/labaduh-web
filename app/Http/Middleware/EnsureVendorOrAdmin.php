<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class EnsureVendorOrAdmin { public function handle(Request $r, Closure $n){ $u=$r->user(); if(!$u) return response()->json(['message'=>'Unauthenticated.'],401); if(in_array($u->role,['vendor','admin'],true)) return $n($r); return response()->json(['message'=>'Forbidden. Vendor/Admin only.'],403);} }
