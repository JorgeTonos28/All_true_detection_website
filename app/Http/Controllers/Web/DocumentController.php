<?php
namespace App\Http\Controllers\Web;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreResult;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\OrderResult;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
class DocumentController extends Controller
{
    private function fileRules(): array { return ['required','file','mimes:pdf,jpg,jpeg,png,webp','extensions:pdf,jpg,jpeg,png,webp','max:10240']; }
    public function attach(Request $request, Order $order)
    {
        Gate::authorize('view',$order);
        abort_if(in_array($order->status, [\App\Enums\OrderStatus::Completed,\App\Enums\OrderStatus::Cancelled],true),409,'La orden ya está cerrada.');
        $request->validate(['file'=>$this->fileRules()]);
        abort_if($order->attachments()->count()>=30,422,'Límite de documentos alcanzado.');
        $file=$request->file('file'); $path=$file->store('attachments/'.$order->public_id,'local');
        try {
            DB::transaction(function () use ($request,$order,$file,$path) {
                $attachment=$order->attachments()->create(['uploaded_by'=>$request->user()->id,'file_path'=>$path,'file_name'=>basename($file->getClientOriginalName()),'mime_type'=>$file->getMimeType()]);
                Audit::record($request->user(),'document.uploaded',$attachment);
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        return back()->with('status','Documento privado agregado.');
    }
    public function downloadAttachment(OrderAttachment $attachment)
    {
        Gate::authorize('view',$attachment->order);
        abort_unless(Storage::disk('local')->exists($attachment->file_path),404);
        Audit::record(request()->user(),'document.downloaded',$attachment);
        return Storage::disk('local')->download($attachment->file_path,$attachment->file_name,['Content-Type'=>'application/octet-stream','Cache-Control'=>'no-store, private']);
    }
    public function result(Request $request, Order $order, StoreResult $action)
    {
        Gate::authorize('update',$order);
        $data=$request->validate(['summary'=>['required','string','min:5','max:20000'],'file'=>array_merge(['nullable'],array_slice($this->fileRules(),1))]);
        $action->handle($request->user(),$order,$data['summary'],$request->file('file'));
        return back()->with('status','Resultado preparado. Falta la publicación por un administrador general.');
    }
    public function publish(Request $request, OrderResult $result, PublishResult $action)
    {
        $action->handle($request->user(),$result);
        return back()->with('status','Entrega publicada para el cliente.');
    }
    public function downloadResult(Request $request, OrderResult $result)
    {
        $order=$result->order;
        Gate::authorize('viewResults',$order);
        abort_unless($request->user()->isStaff() || $result->published_at,404);
        abort_unless($result->file_path && Storage::disk('local')->exists($result->file_path),404);
        Audit::record($request->user(),'result.downloaded',$result);
        return Storage::disk('local')->download($result->file_path,$result->file_name,['Content-Type'=>'application/octet-stream','Cache-Control'=>'no-store, private']);
    }
}
