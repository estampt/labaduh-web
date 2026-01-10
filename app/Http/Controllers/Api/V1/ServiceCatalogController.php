<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Service;
class ServiceCatalogController extends Controller { public function index(){ return Service::where('is_active', true)->with('options')->get(); } }
