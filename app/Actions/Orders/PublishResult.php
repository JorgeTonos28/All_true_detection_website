<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderResult;
use App\Models\User;
use App\Services\Audit;
use App\Services\OrderEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishResult
{
    public function handle(User $actor, OrderResult $result): OrderResult
    {
        return DB::transaction(function () use ($actor, $result) {
            $order = Order::whereKey($result->order_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('administer', $order);
            $result = OrderResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->canReleaseResults(), 409, 'Complete la orden y confirme el pago total antes de publicar.');
            if (! $result->published_at) {
                $result->update(['published_at' => now(), 'published_by' => $actor->id]);
                Audit::record($actor, 'result.published', $result);
                OrderEvents::record($order, 'result.published');
            }

            return $result;
        }, 3);
    }
}
