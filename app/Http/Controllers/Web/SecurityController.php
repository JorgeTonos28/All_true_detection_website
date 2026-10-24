<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Audit;
use App\Services\WebhookManagement;
use App\Support\IntegrationScopes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public const SCOPES = IntegrationScopes::ALL;

    public function index(Request $request)
    {
        return view('portal.security');
    }

    public function token(Request $request)
    {
        $user = $request->user();
        abort_if($user->isStaff() || ! $user->customer_account_id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'scopes' => ['required', 'array', 'min:1', 'max:6'], 'scopes.*' => ['required', 'distinct', Rule::in(self::SCOPES)], 'lifetime' => ['nullable', 'in:expiring,persistent'], 'expires_days' => ['nullable', 'integer', 'min:1', 'max:90']]);
        $token = DB::transaction(function () use ($user, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $user->hasVerifiedEmail() && ! $user->isStaff() && $user->customer_account_id, 403);
            abort_if($user->tokens()->count() >= 10, 422, 'Revoque un token antes de crear otro.');
            $persistent = ($data['lifetime'] ?? 'expiring') === 'persistent';
            $token = $user->createToken($data['name'], $data['scopes'], $persistent ? null : now()->addDays($data['expires_days'] ?? config('portal.token_days')));
            Audit::record($user, 'token.created', $token->accessToken, ['scopes' => $data['scopes'], 'lifetime' => $persistent ? 'persistent' : 'expiring']);

            return $token;
        });

        return back()->with('token', $token->plainTextToken)->with('status', 'Token creado. Cópielo ahora: no volverá a mostrarse.');
    }

    public function revoke(Request $request, int $token)
    {
        $token = $request->user()->tokens()->findOrFail($token);
        Audit::record($request->user(), 'token.revoked', $token);
        $token->delete();

        return back()->with('status', 'Token revocado.');
    }

    public function webhook(Request $request, WebhookManagement $management)
    {
        $user = $request->user();
        abort_if($user->isStaff() || ! $user->customer_account_id, 403);
        $data = $request->validate(['url' => ['required', 'url:https', 'max:2048'], 'events' => ['nullable', 'array', 'min:1', 'max:6'], 'events.*' => ['distinct', Rule::in(WebhookEndpoint::EVENTS)]]);
        [$endpoint, $secret] = $management->create($user, $data);

        return back()->with('webhook_secret', $secret)->with('status', 'Webhook creado. Guarde ahora el secreto de firma.');
    }

    public function disableWebhook(Request $request, WebhookEndpoint $endpoint, WebhookManagement $management)
    {
        $management->authorize($request->user(), $endpoint);
        $endpoint->update(['is_active' => false]);
        Audit::record($request->user(), 'webhook.disabled', $endpoint);

        return back()->with('status', 'Webhook desactivado.');
    }
}
