<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Services\Audit;
use App\Services\WebhookUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public const SCOPES = ['orders:read', 'orders:write', 'results:read', 'messages:read', 'messages:write'];

    public function index(Request $request)
    {
        $user = $request->user();

        return view('portal.security', ['tokens' => $user->tokens()->latest('id')->get(), 'endpoints' => ! $user->isStaff() && $user->customer_account_id ? WebhookEndpoint::where('customer_account_id', $user->customer_account_id)->with(['deliveries' => fn ($q) => $q->latest('id')->limit(10)])->get() : collect(), 'scopes' => self::SCOPES]);
    }

    public function token(Request $request)
    {
        $user = $request->user();
        abort_if($user->isStaff() || ! $user->customer_account_id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'scopes' => ['required', 'array', 'min:1', 'max:5'], 'scopes.*' => ['required', 'distinct', Rule::in(self::SCOPES)]]);
        abort_if($user->tokens()->count() >= 10, 422, 'Revoque un token antes de crear otro.');
        $token = $user->createToken($data['name'], $data['scopes'], now()->addDays(config('portal.token_days')));
        Audit::record($user, 'token.created', $token->accessToken, ['scopes' => $data['scopes']]);

        return back()->with('token', $token->plainTextToken)->with('status', 'Token creado. Cópielo ahora: no volverá a mostrarse.');
    }

    public function revoke(Request $request, int $token)
    {
        $token = $request->user()->tokens()->findOrFail($token);
        Audit::record($request->user(), 'token.revoked', $token);
        $token->delete();

        return back()->with('status', 'Token revocado.');
    }

    public function webhook(Request $request, WebhookUrl $urls)
    {
        $user = $request->user();
        abort_if($user->isStaff() || ! $user->customer_account_id, 403);
        $data = $request->validate(['url' => ['required', 'url:https', 'max:2048']]);
        $urls->host($data['url']);
        abort_if(WebhookEndpoint::where('customer_account_id', $user->customer_account_id)->where('is_active', true)->count() >= 5, 422, 'Límite de endpoints activos alcanzado.');
        $secret = Str::random(64);
        $endpoint = WebhookEndpoint::create(['customer_account_id' => $user->customer_account_id, 'url' => $data['url'], 'secret' => $secret]);
        Audit::record($user, 'webhook.created', $endpoint);

        return back()->with('webhook_secret', $secret)->with('status', 'Webhook creado. Guarde ahora el secreto de firma.');
    }

    public function disableWebhook(Request $request, WebhookEndpoint $endpoint)
    {
        abort_unless(! $request->user()->isStaff() && $endpoint->customer_account_id === $request->user()->customer_account_id, 403);
        $endpoint->update(['is_active' => false]);
        Audit::record($request->user(), 'webhook.disabled', $endpoint);

        return back()->with('status', 'Webhook desactivado.');
    }
}
