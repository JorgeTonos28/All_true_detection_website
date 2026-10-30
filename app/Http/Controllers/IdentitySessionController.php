<?php

namespace App\Http\Controllers;

use App\Actions\Orders\RequestIdentitySession;
use App\Models\Order;
use App\Services\Audit;
use App\Services\IdentityPartnerAccess;
use App\Services\IdentityReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdentitySessionController extends Controller
{
    public function store(Request $request, Order $order, string $person, RequestIdentitySession $action)
    {
        Gate::authorize('view', $order);
        $record = $order->people()->where('public_id', $person)->firstOrFail();
        $api = $request->is('api/*');
        if ($api) {
            $request->validate(['portrait_image' => 'prohibited', 'reference_image' => 'prohibited', 'workflow_id' => 'prohibited', 'decision' => 'prohibited']);
        }
        $session = $action->handle($request->user(), $order, $record, $request->only(['consent', 'notice_version']), $api ? 'api_credit' : 'web');
        if (! $request->is('api/*')) {
            return back()->with('status', 'Verificación preparada. El enlace aparecerá aquí cuando el proveedor confirme la sesión; puede actualizar esta página.');
        }

        return response()->json(['data' => $this->data($order, $record->public_id, $session, $request->user()->tokenCan('identity:read'))], 202);
    }

    public function show(Request $request, Order $order, string $person)
    {
        Gate::authorize('view', $order);
        abort_if($request->user()->isStaff(), 403);
        IdentityPartnerAccess::authorize($request->user(), 'identity:read');
        abort_if($order->status->value === 'cancelled', 409, 'Esta solicitud está cancelada.');
        $record = $order->people()->where('public_id', $person)->firstOrFail();
        $session = $record->identitySession()->firstOrFail();
        abort_unless($session->access_channel === 'api_credit', 403);

        return response()->json(['data' => $this->data($order, $record->public_id, $session, true)]);
    }

    public function result(Request $request, Order $order, string $person)
    {
        Gate::authorize('view', $order);
        IdentityPartnerAccess::authorize($request->user(), 'identity:read');
        abort_if($order->status->value === 'cancelled', 409, 'Esta solicitud está cancelada.');
        $record = $order->people()->where('public_id', $person)->firstOrFail();
        $session = $record->identitySession()->firstOrFail();
        abort_unless($session->access_channel === 'api_credit' && $session->credit_authorized_at && $session->credit_authorized_by, 403);
        abort_if(app()->isProduction() && $session->environment !== 'live', 409, 'Un resultado sandbox no es una verificación de producción.');
        abort_unless($session->credit_accepted_at && $session->decision, 409, 'El resultado técnico aún no está disponible.');
        abort_unless(IdentityReferences::validResult($session), 409, 'La referencia original ha cambiado; el resultado requiere revisión.');
        Audit::record($request->user(), 'identity.result_read', $session);

        return response()->json(['data' => ['id' => $session->public_id, 'order_id' => $order->public_id,
            'person_id' => $record->public_id, 'provider' => 'didit', 'environment' => $session->environment,
            'billing_mode' => 'partner_credit', 'revision' => $session->decision_revision,
            'verification_kind' => $session->verification_kind, 'document_reverified' => $session->verification_kind === 'initial',
            'identity_id' => $session->subject?->public_id,
            'outcome' => $session->decision['outcome'], 'reason' => $session->decision['reason'],
            'reviewed_at' => $session->decision['reviewed_at'], 'requires_review' => $session->decision['outcome'] === 'inconclusive']]);
    }

    private function data($order, $personId, $session, bool $canReadCapture): array
    {
        return ['id' => $session->public_id, 'order_id' => $order->public_id, 'person_id' => $personId,
            'provider' => 'didit', 'state' => $session->publicState(), 'environment' => $session->environment,
            'billing_mode' => 'partner_credit', 'revision' => $session->decision_revision,
            'verification_kind' => $session->verification_kind, 'identity_id' => $session->subject?->public_id,
            'verification_url' => $canReadCapture && $session->state === 'awaiting_person' ? $session->verification_url : null,
            'consented_at' => $session->consented_at->toIso8601String(), 'notice_version' => $session->notice_version,
            'links' => ['self' => url('/api/v1/orders/'.$order->public_id.'/people/'.$personId.'/identity-session'), 'result' => url('/api/v1/orders/'.$order->public_id.'/people/'.$personId.'/identity-session/result')]];
    }
}
