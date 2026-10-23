<?php
namespace App\Http\Controllers\Web\Auth;
use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google todavía no está configurado.');
        $request->session()->put('google.link_user', $request->user()?->id);
        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }
    public function callback(Request $request)
    {
        try { $google = Socialite::driver('google')->user(); }
        catch (InvalidStateException) { return redirect()->route('login')->withErrors(['email' => 'La sesión de Google expiró. Inténtelo de nuevo.']); }
        catch (\Throwable) { return redirect()->route('login')->withErrors(['email' => 'Google no pudo completar el acceso. Inténtelo de nuevo.']); }
        $verified = ($google->user['verified_email'] ?? $google->user['email_verified'] ?? false) === true;
        abort_unless($verified && filter_var($google->getEmail(), FILTER_VALIDATE_EMAIL), 403, 'Google debe proporcionar un correo verificado.');
        $email = mb_strtolower(trim($google->getEmail()));
        $linkId = $request->session()->pull('google.link_user');
        $known = User::where('google_id', $google->getId())->first();
        if ($linkId) {
            abort_unless($request->user()?->id === $linkId && $request->user()->is_active && $request->user()->email === $email && (! $known || $known->id === $linkId), 403);
            $user = $request->user();
            $user->forceFill(['google_id' => $google->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            $request->session()->put('auth.password_confirmed_at', time());
            return redirect()->route('portal.security')->with('status', 'Cuenta Google vinculada.');
        }
        if (! $known && User::where('email', $email)->exists()) {
            return redirect()->route('login')->withErrors(['email' => 'Esta cuenta ya existe. Inicie sesión con su contraseña y vincule Google desde Seguridad.']);
        }
        $user = $known ?? DB::transaction(function () use ($google, $email) {
            $account = CustomerAccount::create(['name' => mb_substr($google->getName() ?: $email, 0, 200), 'billing_email' => $email]);
            $user = new User(['name' => mb_substr($google->getName() ?: 'Cliente', 0, 120), 'email' => $email, 'password' => Str::random(64)]);
            $user->forceFill(['customer_account_id' => $account->id, 'google_id' => $google->getId(), 'email_verified_at' => now()])->save();
            return $user;
        });
        abort_unless($user->is_active, 403);
        $request->session()->regenerate();
        if ($user->two_factor_confirmed_at) {
            $request->session()->put(['login.id' => $user->id, 'login.remember' => false]);
            return redirect()->route('two-factor.login');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.password_confirmed_at', time());
        return redirect()->route('portal.dashboard');
    }
}
