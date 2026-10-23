<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Acceso | All True Detection</title><link rel="icon" href="{{ asset('favicon.ico') }}"><link rel="stylesheet" href="{{ asset('assets/css/portal.css') }}"></head>
<body class="auth-body"><main class="auth-shell">
<aside class="auth-brand"><a href="{{ route('home') }}"><img src="{{ asset('assets/images/brand-lockup-light.png') }}" alt="All True Detection" width="240"></a><div><p class="eyebrow">PORTAL DE CLIENTES</p><h1>Su confianza.<br>Con seguimiento.</h1><p>Solicite servicios, converse con nuestro equipo y reciba sus entregas en un espacio privado.</p></div><p>Evaluamos. Detectamos. Prevenimos.</p></aside>
<section class="auth-panel">
@php
    $titles = ['login'=>'Bienvenido de nuevo','register'=>'Cree su cuenta','forgot-password'=>'Recupere el acceso','reset-password'=>'Establezca su contraseña','verify-email'=>'Verifique su correo','confirm-password'=>'Confirme su identidad','two-factor-challenge'=>'Un paso más de seguridad'];
    $actions = ['login'=>'login.store','register'=>'register.store','forgot-password'=>'password.email','reset-password'=>'password.update','confirm-password'=>'password.confirm.store','two-factor-challenge'=>'two-factor.login.store'];
@endphp
<a href="{{ route('home') }}" class="muted">← Volver al sitio</a><h2>{{ $titles[$mode] }}</h2>
@if (session('status'))<div class="notice" role="status">{{ \App\Support\FlashMessage::label(session('status')) }}</div>@endif
@if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
@if ($mode === 'verify-email')
    <p>Le enviamos un enlace para confirmar su correo. Revise también la carpeta de spam.</p>
    <form method="post" action="{{ route('verification.send') }}">@csrf<button>Reenviar enlace</button></form>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary">Cerrar sesión</button></form>
@else
<form method="post" action="{{ route($actions[$mode]) }}" class="stack">@csrf
    @if ($mode === 'reset-password')<input type="hidden" name="token" value="{{ $token }}">@endif
    @if ($mode === 'register')
        <label>Nombre completo<input name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required></label>
        <label>Empresa (opcional)<input name="company" value="{{ old('company') }}" maxlength="200" autocomplete="organization"></label>
    @endif
    @if (in_array($mode, ['login','register','forgot-password','reset-password']))<label>Correo electrónico<input type="email" name="email" value="{{ old('email', $email ?? '') }}" autocomplete="email" maxlength="255" required autofocus></label>@endif
    @if (in_array($mode, ['login','register','reset-password','confirm-password']))<label>Contraseña<input type="password" name="password" autocomplete="{{ in_array($mode, ['register','reset-password']) ? 'new-password' : 'current-password' }}" maxlength="128" required></label>@endif
    @if (in_array($mode, ['register','reset-password']))<small>Al menos 12 caracteres, mayúsculas, minúsculas y números.</small><label>Confirmar contraseña<input type="password" name="password_confirmation" autocomplete="new-password" required></label>@endif
    @if ($mode === 'register')<label class="check"><input type="checkbox" name="terms" value="1" required> Acepto los <a href="{{ route('terms') }}">términos</a> y el <a href="{{ route('privacy') }}">aviso de privacidad</a>.</label>@endif
    @if ($mode === 'two-factor-challenge')<p>Use el código de su autenticadora o un código de recuperación.</p><label>Código de autenticación<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"></label><label>Código de recuperación (alternativo)<input name="recovery_code" autocomplete="off" maxlength="100"></label>@endif
    <button type="submit">{{ match($mode) {'login'=>'Entrar al portal','register'=>'Crear cuenta','forgot-password'=>'Enviar enlace de recuperación','reset-password'=>'Guardar contraseña',default=>'Continuar'} }}</button>
</form>
@endif
@if ($mode === 'login')
    <a href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
    @if (config('services.google.client_id'))<a class="button secondary" href="{{ route('google.redirect') }}">Continuar con Google</a>@endif
    <p>¿Aún no tiene cuenta? <a href="{{ route('register') }}">Registrarse</a></p>
@elseif ($mode === 'register')
    @if (config('services.google.client_id'))<a class="button secondary" href="{{ route('google.redirect') }}">Registrarse con Google</a>@endif
    <p>¿Ya tiene cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
@elseif ($mode === 'forgot-password')<a href="{{ route('login') }}">Volver a iniciar sesión</a>
@endif
@if($mode === 'confirm-password' && auth()->user()?->google_id)<a class="button secondary" href="{{ route('google.reauthenticate') }}">Confirmar identidad con Google</a>@endif
</section></main></body></html>
