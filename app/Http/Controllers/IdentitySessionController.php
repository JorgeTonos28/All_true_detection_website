<?php

namespace App\Http\Controllers;

use App\Actions\Orders\RequestIdentitySession;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdentitySessionController extends Controller
{
    public function store(Request $request, Order $order, string $person, RequestIdentitySession $action)
    {
        Gate::authorize('view', $order);
        $record = $order->people()->where('public_id', $person)->firstOrFail();
        $session = $action->handle($request->user(), $order, $record, $request->only(['consent', 'notice_version']));
        if (! $request->is('api/*')) {
            return back()->with('status', 'Verificación preparada. El enlace aparecerá aquí cuando el proveedor confirme la sesión; puede actualizar esta página.');
        }

        return response()->json(['data' => $this->data($order, $record->public_id, $session)], 202);
    }

    public function show(Request $request, Order $order, string $person)
    {
        Gate::authorize('view', $order);
        abort_if($request->user()->isStaff(), 403);
        $record = $order->people()->where('public_id', $person)->firstOrFail();
        $session = $record->identitySession()->firstOrFail();

        return response()->json(['data' => $this->data($order, $record->public_id, $session)]);
    }

    private function data($order, $personId, $session): array
    {
        return ['id' => $session->public_id, 'order_id' => $order->public_id, 'person_id' => $personId,
            'provider' => 'didit', 'state' => $session->publicState(), 'environment' => $session->environment,
            'verification_url' => $session->state === 'awaiting_person' ? $session->verification_url : null,
            'consented_at' => $session->consented_at->toIso8601String(), 'notice_version' => $session->notice_version,
            'links' => ['self' => url('/api/v1/orders/'.$order->public_id.'/people/'.$personId.'/identity-session'), 'results' => url('/api/v1/orders/'.$order->public_id.'/results')]];
    }
}
