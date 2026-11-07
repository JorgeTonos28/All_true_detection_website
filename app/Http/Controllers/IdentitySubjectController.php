<?php

namespace App\Http\Controllers;

use App\Actions\Identity\CreateRecurringOrder;
use App\Actions\Identity\EnrollIdentity;
use App\Actions\Identity\RevokeIdentity;
use App\Models\IdentitySubject;
use App\Models\Order;
use App\Services\IdentityPartnerAccess;
use App\Services\IdentityReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdentitySubjectController extends Controller
{
    public function index(Request $request)
    {
        $api = $request->is('api/*');
        $admin = ! $api && $request->user()->isAdmin();
        abort_unless($admin || (! $request->user()->isStaff() && $request->user()->customer_account_id), 403);
        if ($api) {
            IdentityPartnerAccess::authorize($request->user(), 'identity:read');
        }
        $data = $request->validate(['q' => 'nullable|string|max:26']);
        $subjects = IdentitySubject::query()->when(! $admin, fn ($q) => $q->where('customer_account_id', $request->user()->customer_account_id))
            ->when(filled($data['q'] ?? null), fn ($q) => $q->where('public_id', $data['q']))
            ->with(['account', 'sourceSession.order'])->latest('id')->paginate(20)->withQueryString();
        if ($api) {
            return response()->json(['data' => $subjects->map(fn ($subject) => $this->data($subject)), 'meta' => ['current_page' => $subjects->currentPage(), 'last_page' => $subjects->lastPage(), 'per_page' => 20, 'total' => $subjects->total()]]);
        }

        return view('portal.identities.index', compact('subjects', 'admin'));
    }

    public function enroll(Request $request, Order $order, string $person, EnrollIdentity $action)
    {
        Gate::authorize('view', $order);
        $record = $order->people()->where('public_id', $person)->firstOrFail();
        $source = $record->identitySession()->firstOrFail();
        $api = $request->is('api/*');
        $subject = $action->handle($request->user(), $source, $request->only(['consent', 'notice_version']), $api ? 'api_credit' : 'web');

        return $api ? response()->json(['data' => $this->data($subject)], 201)
            : redirect()->route('portal.identities.index')->with('status', 'Referencia registrada con consentimiento. Su vigencia se cuenta desde la verificación original.');
    }

    public function verify(Request $request, IdentitySubject $identity, CreateRecurringOrder $action)
    {
        $api = $request->is('api/*');
        $data = $request->only(['subject', 'details', 'external_reference', 'purpose', 'consent', 'notice_version', 'portrait_image', 'reference_image', 'identity_number', 'workflow_id', 'decision']);
        $key = $api ? $request->header('Idempotency-Key', '') : $request->input('idempotency_key', '');
        $order = $action->handle($request->user(), $identity, $data, is_string($key) ? $key : '', $api ? 'api_credit' : 'web');
        if (! $api) {
            return redirect()->route('portal.orders.show', $order)->with('status', 'Reverificación solicitada. ATD debe cotizar y confirmar el pago antes de habilitar su captura.');
        }
        $person = $order->people()->firstOrFail();

        // A create-only integration does not receive the stored profile in this response.
        return response()->json(['data' => ['order_id' => $order->public_id, 'person_id' => $person->public_id, 'identity_id' => $identity->public_id,
            'verification_kind' => 'recurring', 'identity_session_url' => url('/api/v1/orders/'.$order->public_id.'/people/'.$person->public_id.'/identity-session')]], 202);
    }

    public function revoke(Request $request, IdentitySubject $identity, RevokeIdentity $action)
    {
        $api = $request->is('api/*');
        $action->handle($request->user(), $identity, $api);

        return $api ? response()->noContent() : back()->with('status', 'Referencia revocada en ATD. No se eliminan los datos conservados por Didit; coordine con ATD cualquier solicitud de supresión.');
    }

    private function data(IdentitySubject $subject): array
    {
        $profile = $subject->profile;

        return ['id' => $subject->public_id, 'display_name' => $profile['first_name'].' '.$profile['last_name'],
            'identity_number_hint' => '••••'.substr($profile['identity_number'], -4), 'environment' => $subject->environment,
            'state' => $subject->revoked_at ? 'revoked' : ($subject->expires_at->isPast() ? 'expired' : (IdentityReferences::usable($subject) ? 'available' : 'unavailable')),
            'expires_at' => $subject->expires_at->toIso8601String(), 'consented_at' => $subject->consented_at->toIso8601String(), 'notice_version' => $subject->notice_version];
    }
}
