<?php

namespace App\Http\Controllers\Web;

use App\Actions\Identity\ManagePartnerAccess;
use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\IdentitySession;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdentityPartnerController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user()->isAdmin();
        abort_unless($admin || (! $request->user()->isStaff() && $request->user()->customer_account_id), 403);
        $data = $request->validate(['q' => 'nullable|string|max:120', 'account' => 'nullable|string|size:26',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'environment' => ['nullable', Rule::in(['live', 'sandbox'])]]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth()->toDateString(), 'UTC')->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? now()->toDateString(), 'UTC')->addDay()->startOfDay();
        if ($to->lessThanOrEqualTo($from) || $from->diffInDays($to) > 93) {
            throw ValidationException::withMessages(['to' => 'Seleccione un período de 1 a 93 días.']);
        }
        $environment = $data['environment'] ?? 'live';
        $account = $admin ? (filled($data['account'] ?? null) ? CustomerAccount::where('public_id', $data['account'])->firstOrFail() : null) : $request->user()->account;
        abort_if(! $admin && filled($data['account'] ?? null) && $data['account'] !== $account->public_id, 403);
        $query = IdentitySession::where('access_channel', 'api_credit')->where('environment', $environment)
            ->when($account, fn ($q) => $q->whereHas('order', fn ($q) => $q->where('customer_account_id', $account->id)))
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('credit_accepted_at', '>=', $from)->where('credit_accepted_at', '<', $to))
                ->orWhere(fn ($q) => $q->whereNull('credit_accepted_at')->where('created_at', '>=', $from)->where('created_at', '<', $to)));
        $metrics = (clone $query)->selectRaw("COUNT(*) as reserved, SUM(CASE WHEN credit_accepted_at IS NOT NULL THEN 1 ELSE 0 END) as accepted, SUM(CASE WHEN state = 'received' THEN 1 ELSE 0 END) as concluded, SUM(CASE WHEN state IN ('create_uncertain', 'needs_attention') THEN 1 ELSE 0 END) as attention")->first();
        $sessions = $query->with('order.account')->latest('id')->paginate(25, ['*'], 'sessions_page')->withQueryString();
        $accounts = $admin ? CustomerAccount::query()->when(filled($data['q'] ?? null), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$data['q'].'%')->orWhere('public_id', $data['q'])))
            ->orderByDesc('identity_api_enabled')->orderBy('name')->paginate(15, ['*'], 'accounts_page')->withQueryString() : null;

        return view('portal.integrations.identity', compact('admin', 'account', 'accounts', 'sessions', 'metrics', 'from', 'to', 'environment'));
    }

    public function update(Request $request, CustomerAccount $account, ManagePartnerAccess $action)
    {
        $action->handle($request->user(), $account, $request->only(['enabled', 'daily_limit']));

        return back()->with('status', 'Acceso de identidad actualizado. La suspensión se aplica también a los tokens existentes.');
    }
}
