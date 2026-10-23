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
@unless($user->isStaff())
<section class="panel"><div class="panel-heading"><div><h2>Tokens de integración API</h2><p class="muted">Solo los permisos seleccionados. Vencimiento: {{ config('portal.token_days') }} días.</p></div><a href="{{ route('portal.api-guide') }}">Guía de integración →</a></div>
@if(session('token'))<div class="notice"><strong>Guarde este token ahora</strong><p class="secret"><code>{{ session('token') }}</code></p></div>@endif
<form method="post" action="{{ route('portal.tokens.store') }}" class="stack">@csrf<label>Nombre de la integración<input name="name" maxlength="100" placeholder="Ej. Sistema de recursos humanos" required></label><fieldset class="scope-list"><legend>Permisos del token</legend>@foreach($scopes as $scope)<label class="check"><input type="checkbox" name="scopes[]" value="{{ $scope }}">{{ $scope }}</label>@endforeach</fieldset><div><button>Generar token</button></div></form>
<ul class="clean-list">@forelse($tokens as $token)<li><div><strong>{{ $token->name }}</strong><small>{{ implode(', ',$token->abilities) }} · Vence {{ $token->expires_at?->format('d/m/Y') ?? 'sin fecha' }}</small></div><form method="post" action="{{ route('portal.tokens.revoke',$token->id) }}">@csrf @method('DELETE')<button class="secondary">Revocar</button></form></li>@empty<li>Sin tokens emitidos.</li>@endforelse</ul>
</section>
<section class="panel"><h2>Webhooks</h2><p>Reciba avisos firmados cuando cambien sus solicitudes. El equipo ATD debe autorizar el dominio de destino antes de que pueda registrarlo.</p>
@if(session('webhook_secret'))<div class="notice"><strong>Secreto de firma (se muestra una sola vez)</strong><p class="secret"><code>{{ session('webhook_secret') }}</code></p></div>@endif
<form method="post" action="{{ route('portal.webhooks.store') }}" class="inline-form">@csrf<label>URL HTTPS<input type="url" name="url" placeholder="https://su-sistema.com/webhooks/atd" maxlength="2048" required></label><button>Registrar endpoint</button></form>
@forelse($endpoints as $endpoint)<details class="form-details"><summary>{{ $endpoint->url }} · {{ $endpoint->is_active ? 'Activo' : 'Desactivado' }}</summary>
@if($endpoint->is_active)<form method="post" action="{{ route('portal.webhooks.disable',$endpoint) }}">@csrf @method('DELETE')<button class="secondary">Desactivar endpoint</button></form>@endif
<ul class="clean-list">@forelse($endpoint->deliveries as $delivery)<li><span>{{ $delivery->event_type }}<small class="reference">{{ $delivery->event_id }}</small></span><span>{{ $delivery->delivered_at ? 'Entregado' : ($delivery->failed_at ? 'Fallido' : 'Pendiente') }}<small>Intentos: {{ $delivery->attempts }} · HTTP {{ $delivery->response_status ?? '—' }}</small></span></li>@empty<li>Sin eventos registrados.</li>@endforelse</ul></details>@empty<p class="muted">Sin endpoints registrados.</p>@endforelse
</section>
@endunless
@endsection
