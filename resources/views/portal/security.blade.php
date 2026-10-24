@extends('portal.layout')
@section('title','Seguridad y acceso')
@section('subtitle','Proteja su cuenta y controle las conexiones autorizadas.')
@section('content')
@php($user = auth()->user())
<div class="two-columns">
<section class="panel stack"><h2>Autenticación de dos factores</h2><p>Utilice una aplicación autenticadora compatible con TOTP y guarde los códigos de recuperación en un lugar seguro.</p>
@if(!$user->two_factor_secret)
    <span class="badge">Pendiente de configurar</span><form method="post" action="{{ route('two-factor.enable') }}">@csrf<button>Configurar 2FA</button></form>
@elseif(!$user->two_factor_confirmed_at)
    <p>Escanee el código y confirme con los seis dígitos de su autenticadora.</p><div class="qr-code">{!! $user->twoFactorQrCodeSvg() !!}</div>
    <form method="post" action="{{ route('two-factor.confirm') }}" class="stack">@csrf<label>Código de verificación<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label><button>Confirmar activación</button></form>
@else
    <span class="badge status-completed">2FA activa</span><details><summary>Ver códigos de recuperación</summary><p>Cada código funciona una sola vez. No comparta estos códigos.</p><ul class="recovery-codes">@foreach($user->recoveryCodes() as $code)<li><code>{{ $code }}</code></li>@endforeach</ul></details><form method="post" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf<button class="secondary">Regenerar códigos</button></form>
@endif
@if($user->two_factor_secret)<form method="post" action="{{ route('two-factor.disable') }}">@csrf @method('DELETE')<button class="secondary">Desactivar 2FA</button></form>@endif
@if($user->isStaff())<p class="muted">El personal debe mantener 2FA confirmada para acceder a las funciones de operación.</p>@endif
</section>
<section class="panel stack"><h2>Cuenta y contraseña</h2><p><strong>{{ $user->email }}</strong><br><span class="badge status-completed">Correo verificado</span></p>
<form method="post" action="{{ route('user-password.update') }}" class="stack">@csrf @method('PUT')<label>Contraseña actual<input type="password" name="current_password" autocomplete="current-password" required></label><label>Nueva contraseña<input type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required></label><label>Confirmar nueva contraseña<input type="password" name="password_confirmation" autocomplete="new-password" required></label><small>Al menos 12 caracteres, mayúsculas, minúsculas y números. Cambiarla revoca los tokens de integración.</small><button>Actualizar contraseña</button></form>
<h3>Google</h3>
@if($user->google_id)<p>Google está vinculado a su cuenta.</p>@elseif(config('services.google.client_id'))<a class="button secondary" href="{{ route('google.link') }}">Vincular mi cuenta Google</a>@else<p class="muted">La conexión con Google estará disponible cuando ATD complete su configuración.</p>@endif
</section>
</div>
@if($user->isAdmin() || !$user->isStaff())
<section class="panel"><h2>Conexiones de su aplicación</h2><p>Tokens, webhooks y monitoreo tienen un espacio dedicado, separado de la seguridad personal.</p><a class="button secondary" href="{{ route('portal.integrations') }}">Abrir Integraciones API</a></section>
@endif
@endsection
