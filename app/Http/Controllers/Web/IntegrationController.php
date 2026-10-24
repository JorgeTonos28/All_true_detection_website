<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\OrderAttachment;
use App\Models\OrderResult;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Audit;
use App\Services\WebhookManagement;
use App\Support\IntegrationScopes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class IntegrationController extends Controller
{
    private function authorizeViewer(Request $request): void
    {
        abort_unless($request->user()->isAdmin() || (! $request->user()->isStaff() && $request->user()->customer_account_id), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeViewer($request);
        $data = $request->validate(['section' => ['nullable', Rule::in(['overview', 'tokens', 'webhooks', 'deliveries', 'requests'])], 'status' => ['nullable', Rule::in(['pending', 'delivered', 'failed', 'errors'])]]);
        $admin = $request->user()->isAdmin();
        $account = $request->user()->customer_account_id;
        $logs = ApiRequestLog::query()->when(! $admin, fn ($q) => $q->where('customer_account_id', $account));
        $endpoints = WebhookEndpoint::query()->when(! $admin, fn ($q) => $q->where('customer_account_id', $account));
        $deliveries = WebhookDelivery::query()->when(! $admin, fn ($q) => $q->whereHas('endpoint', fn ($q) => $q->where('customer_account_id', $account)));
        $day = (clone $logs)->where('created_at', '>=', now()->subDay());
        $summary = (clone $day)->selectRaw('COUNT(*) as total, AVG(duration_ms) as mean_ms, SUM(CASE WHEN status >= 400 THEN 1 ELSE 0 END) as errors')->first();
        $sample = (clone $day)->latest('id')->limit(1000)->pluck('duration_ms')->sort()->values();
        $metrics = ['requests' => (int) $summary->total, 'errors' => (int) $summary->errors, 'mean' => (int) $summary->mean_ms, 'p95' => $sample->isEmpty() ? null : $sample[(int) ceil($sample->count() * .95) - 1], 'sample' => $sample->count(), 'active_endpoints' => (clone $endpoints)->where('is_active', true)->count(), 'pending' => (clone $deliveries)->whereNull('delivered_at')->whereNull('failed_at')->count(), 'failed' => (clone $deliveries)->whereNotNull('failed_at')->count()];
        $tokens = PersonalAccessToken::where('tokenable_type', (new User)->getMorphClass())->when(! $admin, fn ($q) => $q->where('tokenable_id', $request->user()->id));
        $section = $data['section'] ?? 'overview';
        $status = $data['status'] ?? null;
        $rows = match ($section) {
            'tokens' => $tokens->with('tokenable')->latest('id')->paginate(20),
            'webhooks' => $endpoints->with('account')->withCount('deliveries')->latest('id')->paginate(20),
            'deliveries' => $deliveries->with(['endpoint.account', 'order'])->when($status === 'failed', fn ($q) => $q->whereNotNull('failed_at'))->when($status === 'delivered', fn ($q) => $q->whereNotNull('delivered_at'))->when($status === 'pending', fn ($q) => $q->whereNull('delivered_at')->whereNull('failed_at'))->latest('id')->paginate(25),
            'requests' => $logs->with(['user', 'account'])->when($status === 'errors', fn ($q) => $q->where('status', '>=', 400))->latest('id')->paginate(25),
            default => null,
        };
        $rows?->withQueryString();
        $bytes = 0;
        foreach ([OrderAttachment::class, OrderResult::class] as $model) {
            $bytes += $model::when(! $admin, fn ($q) => $q->whereHas('order', fn ($q) => $q->where('customer_account_id', $account)))->sum('file_size');
        }

        return view('portal.integrations.index', compact('section', 'status', 'rows', 'metrics', 'admin', 'bytes') + ['scopes' => IntegrationScopes::LABELS, 'events' => WebhookEndpoint::EVENTS, 'scheduler' => Cache::get('portal.webhooks.dispatched_at'), 'queuePending' => $admin ? DB::table('jobs')->count() : null, 'queueFailed' => $admin ? DB::table('failed_jobs')->count() : null]);
    }

    public function show(Request $request, WebhookDelivery $delivery, WebhookManagement $management)
    {
        $this->authorizeViewer($request);
        $management->authorize($request->user(), $delivery->endpoint);

        return view('portal.integrations.delivery', ['delivery' => $delivery->load(['endpoint.account', 'order']), 'attempts' => $delivery->history()->latest('id')->paginate(25)]);
    }

    public function rotate(Request $request, WebhookEndpoint $endpoint, WebhookManagement $management)
    {
        $secret = $management->rotate($request->user(), $endpoint);

        return back()->with('webhook_secret', $secret)->with('status', 'Clave rotada. Actualice el receptor; ambas firmas son válidas durante 24 horas.');
    }

    public function test(Request $request, WebhookEndpoint $endpoint, WebhookManagement $management)
    {
        $delivery = $management->test($request->user(), $endpoint);

        return redirect()->route('portal.integrations.delivery', $delivery)->with('status', 'Prueba encolada. El scheduler la enviará al endpoint; revise aquí el resultado.');
    }

    public function replay(Request $request, WebhookDelivery $delivery, WebhookManagement $management)
    {
        $management->replay($request->user(), $delivery);

        return back()->with('status', 'Evento reencolado con el mismo identificador.');
    }

    public function revoke(Request $request, int $token)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $token = PersonalAccessToken::where('tokenable_type', (new User)->getMorphClass())->findOrFail($token);
        Audit::record($request->user(), 'token.revoked', $token);
        $token->delete();

        return back()->with('status', 'Token revocado.');
    }
}
