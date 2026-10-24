<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
@php($business = \App\Models\Setting::values())
<title>@yield('title','Portal') | {{ $business['company_name'] ?? 'All True Detection' }}</title>
@include('shared.favicon',['settings'=>$business])
<link rel="stylesheet" href="{{ asset('assets/css/portal.css') }}?v={{ filemtime(public_path('assets/css/portal.css')) }}">
@stack('styles')
</head>
<body><a class="skip-link" href="#content">Saltar al contenido</a>
<div class="portal-shell">
<aside class="sidebar">
    <a class="portal-brand" href="{{ route('portal.dashboard') }}"><img src="{{ filled($business['logo_path'] ?? null) ? \Illuminate\Support\Facades\Storage::disk('public')->url($business['logo_path']) : asset('assets/images/brand-lockup-light.png') }}" alt="{{ $business['company_name'] ?? 'All True Detection' }}"></a>
    <p class="eyebrow">{{ auth()->user()->isStaff() ? 'OPERACIONES' : 'MI CUENTA' }}</p>
    <nav aria-label="Portal">
        @foreach (['portal.dashboard'=>'Resumen','portal.orders.index'=>'Solicitudes','portal.messages.index'=>'Mensajes'] as $name=>$label)
            <a href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        @if(auth()->user()->isAdmin())
            @foreach (['portal.users.index'=>'Usuarios y acceso','portal.settings'=>'Mi negocio','portal.operations'=>'Auditoría e integraciones'] as $name=>$label)
                <a href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        @endif
        @unless(auth()->user()->isStaff())<a href="{{ route('portal.account') }}" @if(request()->routeIs('portal.account')) aria-current="page" @endif>Datos de mi cuenta</a>@endunless
        @if(auth()->user()->isAdmin() || !auth()->user()->isStaff())<a href="{{ route('portal.integrations') }}" @if(request()->routeIs('portal.integrations*')) aria-current="page" @endif>Integraciones API</a>@endif
        <a href="{{ route('portal.security') }}" @if(request()->routeIs('portal.security')) aria-current="page" @endif>Seguridad y acceso</a>
    </nav>
    <div class="sidebar-footer"><a href="{{ route('home') }}">Visitar sitio web ↗</a><p>Información privada.<br>Acceso autorizado.</p></div>
</aside>
<div class="workspace">
    <header class="portal-topbar"><span class="muted">Portal de servicios</span><div class="identity"><div data-notification-menu data-url="{{ route('portal.notifications.summary') }}">@include('portal.notification-menu')</div><span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->label() }}</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">Salir</button></form></div></header>
    <main class="portal-content" id="content">
        <a class="portal-back" href="{{ \App\Support\PortalNavigation::back(request()) }}">← Volver</a>
        <header class="page-heading"><div><p class="eyebrow">{{ mb_strtoupper($business['company_name'] ?? 'All True Detection') }}</p><h1>@yield('title','Portal')</h1><p class="muted">@yield('subtitle')</p></div><div class="actions">@yield('actions')</div></header>
        @if(session('status'))<div class="notice" role="status">{{ \App\Support\FlashMessage::label(session('status')) }}</div>@endif
        @if($errors->any())<div class="alert" role="alert"><strong>Revise la información</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="portal-footer">© {{ date('Y') }} {{ $business['company_name'] ?? 'All True Detection' }} · <a href="{{ route('privacy') }}">Privacidad</a></footer>
</div>
</div><script src="{{ asset('assets/js/portal-shell.js') }}" defer></script>@stack('scripts')</body></html>
