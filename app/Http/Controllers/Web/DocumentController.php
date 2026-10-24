<?php

namespace App\Http\Controllers\Web;

use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreAttachment;
use App\Actions\Orders\StoreResult;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\OrderResult;
use App\Services\Audit;
use App\Support\PrivateUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private function fileRules(): array
    {
        return PrivateUpload::rules();
    }

    public function attach(Request $request, Order $order, StoreAttachment $action)
    {
        Gate::authorize('view', $order);
        $request->validate(['file' => $this->fileRules(), 'purpose' => ['sometimes', 'string', 'max:32']]);
        $action->handle($request->user(), $order, $request->file('file'), null, $request->input('purpose', 'supporting'));

        return back()->with('status', 'Documento privado agregado.');
    }

    public function downloadAttachment(OrderAttachment $attachment)
    {
        Gate::authorize('view', $attachment->order);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);
        Audit::record(request()->user(), 'document.downloaded', $attachment);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private']);
    }

    public function result(Request $request, Order $order, StoreResult $action)
    {
        Gate::authorize('update', $order);
        $data = $request->validate(['summary' => ['required', 'string', 'min:5', 'max:20000'], 'file' => array_merge(['nullable'], array_slice($this->fileRules(), 1)), 'findings' => ['sometimes', 'array']]);
        $action->handle($request->user(), $order, $data['summary'], $request->file('file'), $data['findings'] ?? []);

        return back()->with('status', 'Resultado preparado. Falta la publicación por un administrador general.');
    }

    public function publish(Request $request, OrderResult $result, PublishResult $action)
    {
        $action->handle($request->user(), $result);

        return back()->with('status', 'Entrega publicada para el cliente.');
    }

    public function downloadResult(Request $request, OrderResult $result)
    {
        $order = $result->order;
        Gate::authorize('viewResults', $order);
        abort_unless($request->user()->isStaff() || $result->published_at, 404);
        abort_unless($result->file_path && Storage::disk('local')->exists($result->file_path), 404);
        Audit::record($request->user(), 'result.downloaded', $result);

        return Storage::disk('local')->download($result->file_path, $result->file_name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private']);
    }
}
