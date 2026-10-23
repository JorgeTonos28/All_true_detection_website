<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Service;
class ServiceController extends Controller
{
    public function index()
    {
        return response()->json(['data'=>Service::where('is_active',true)->orderBy('sort_order')->get(['code','name','description'])]);
    }
}
