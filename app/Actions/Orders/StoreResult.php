<?php
namespace App\Actions\Orders;
use App\Models\Order;
use App\Models\OrderResult;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
class StoreResult
{
    public function handle(User $actor, Order $order, string $summary, ?UploadedFile $file = null): OrderResult
    {
        Gate::forUser($actor)->authorize('update', $order);
        $path = $file?->store('results/'.$order->public_id, 'local');
        try {
            return DB::transaction(function () use ($actor, $order, $summary, $file, $path) {
                $result = $order->results()->create(['created_by'=>$actor->id,'summary'=>$summary,'file_path'=>$path,'file_name'=>$file ? basename($file->getClientOriginalName()) : null,'mime_type'=>$file?->getMimeType()]);
                Audit::record($actor, 'result.prepared', $result);
                return $result;
            });
        } catch (\Throwable $e) { if ($path) { Storage::disk('local')->delete($path); } throw $e; }
    }
}
