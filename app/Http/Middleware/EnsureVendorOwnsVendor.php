<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class EnsureVendorOwnsVendor { public function handle(Request $r, Closure $n){ $u=$r->user(); $v=$r->route('vendor'); if(!$u) return response()->json(['message'=>'Unauthenticated.'],401); if($u->role==='admin') return $n($r); if($u->role==='vendor' && $u->vendor_id && $v && (int)$u->vendor_id===(int)$v->id) return $n($r); return response()->json(['message'=>'Forbidden. Vendor ownership mismatch.'],403);} }
