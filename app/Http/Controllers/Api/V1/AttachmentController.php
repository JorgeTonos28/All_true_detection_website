<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\StoreAttachment;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Order;
use App\Services\Audit;
use App\Support\PrivateUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachmentController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('view', $order);

        return AttachmentResource::collection($order->attachments()->with('order')->latest('id')->paginate(30));
    }

    public function store(Request $request, Order $order, StoreAttachment $action)
    {
        Gate::authorize('view', $order);
        $request->validate(['file' => PrivateUpload::rules(), 'purpose' => ['sometimes', 'string', 'max:32']]);
        $key = $request->header('Idempotency-Key');
        if ($key !== null && ! preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Use entre 8 y 128 caracteres válidos.']);
        }

        return (new AttachmentResource($action->handle($request->user(), $order, $request->file('file'), $key, $request->input('purpose', 'supporting'))->load('order')))->response()->setStatusCode(201);
    }

    public function download(Order $order, string $attachment)
    {
        Gate::authorize('view', $order);
        $attachment = $order->attachments()->where('public_id', $attachment)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);
        Audit::record(request()->user(), 'document.downloaded', $attachment);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name, array_filter(['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private', 'X-Content-SHA256' => $attachment->sha256]));
    }
}
