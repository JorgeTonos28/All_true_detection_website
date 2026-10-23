<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('portal.users', ['users' => User::with('account')->orderBy('name')->paginate(25), 'accounts' => CustomerAccount::orderBy('name')->get(), 'roles' => Role::cases()]);
    }

    public function store(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'role' => ['required', Rule::enum(Role::class)], 'customer_account_id' => ['nullable', 'integer', 'exists:customer_accounts,id'], 'company' => ['nullable', 'string', 'max:200']]);
        $user = DB::transaction(function () use ($request, $data) {
            $accountId = null;
            if ($data['role'] === 'client') {
                $accountId = $data['customer_account_id'] ?? CustomerAccount::create(['name' => ($data['company'] ?? null) ?: $data['name'], 'billing_email' => $data['email']])->id;
            }
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::random(64)]);
            $user->forceFill(['role' => $data['role'], 'customer_account_id' => $accountId])->save();
            Audit::record($request->user(), 'user.invited', $user, ['role' => $data['role'], 'customer_account_id' => $accountId]);

            return $user;
        });
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('status', 'Usuario creado. Se envió un enlace para establecer su contraseña.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)], 'is_active' => ['required', 'boolean']]);
        abort_if($user->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active']), 422, 'No puede retirar su propio acceso administrativo.');
        DB::transaction(function () use ($request, $user, $data) {
            $admins = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $actor = User::findOrFail($request->user()->id);
            abort_unless($actor->isAdmin() && $actor->is_active, 403);
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($user->isAdmin() && $user->is_active && ($data['role'] !== 'admin' || ! $data['is_active']) && $admins->count() <= 1, 422, 'Debe conservar al menos un administrador general activo.');
            if ($data['role'] === 'client' && ! $user->customer_account_id) {
                $user->customer_account_id = CustomerAccount::create(['name' => $user->name, 'billing_email' => $user->email])->id;
            }
            $user->forceFill($data + ['remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            Audit::record($request->user(), 'user.access_changed', $user, ['role' => $data['role'], 'is_active' => (bool) $data['is_active']]);
        });

        return back()->with('status', 'Permisos actualizados y credenciales de integración revocadas.');
    }

    public function resend(Request $request, User $user)
    {
        abort_unless($user->is_active, 422);
        Password::sendResetLink(['email' => $user->email]);
        Audit::record($request->user(), 'user.invitation_resent', $user);

        return back()->with('status', 'Se solicitó un nuevo enlace de acceso.');
    }
}
