<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class EnsureAdminOnly { public function handle(Request $r, Closure $n){ $u=$r->user(); if(!$u) return response()->json(['message'=>'Unauthenticated.'],401); if($u->role==='admin') return $n($r); return response()->json(['message'=>'Forbidden. Admin only.'],403);} }
