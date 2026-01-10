<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class EnsureApprovedVendor { public function handle(Request $r, Closure $n){ $u=$r->user(); if(!$u) return response()->json(['message'=>'Unauthenticated.'],401); if($u->role==='admin') return $n($r); if($u->role!=='vendor') return $n($r); $vendor=$u->vendor; if(!$vendor || !$vendor->isApproved()) return response()->json(['message'=>'Vendor account pending approval.','approval_status'=>$vendor?->approval_status ?? 'pending'],403); return $n($r);} }
