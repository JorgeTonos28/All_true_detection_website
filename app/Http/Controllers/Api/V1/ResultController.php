<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResultResource;
use App\Models\Order;
use App\Services\Audit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ResultController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('view', $order);
        abort_unless($order->canReleaseResults(), 409, 'Los resultados todavía no están disponibles.');

        return ResultResource::collection($order->results()->whereNotNull('published_at')->with('order')->latest('id')->paginate(25));
    }

    public function download(Order $order, string $result)
    {
        Gate::authorize('view', $order);
        abort_unless($order->canReleaseResults(), 409, 'Los resultados todavía no están disponibles.');
        $result = $order->results()->where('public_id', $result)->whereNotNull('published_at')->firstOrFail();
        abort_unless($result->file_path && Storage::disk('local')->exists($result->file_path), 404);
        Audit::record(request()->user(), 'result.downloaded', $result);

        return Storage::disk('local')->download($result->file_path, $result->file_name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private']);
    }
}
