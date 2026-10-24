@extends('portal.layout')
@section('title','Usuarios y acceso')
@section('subtitle','Encuentre usuarios y gestione sus permisos desde un solo lugar.')
@section('actions')
<a class="button" href="#invite-user" data-open-invite>+ Invitar usuario</a>
@endsection
@section('content')
<section class="panel user-directory" data-user-directory>
    <div class="panel-heading"><div><h2>Directorio de usuarios</h2><p class="muted">Clientes, colaboradores y administradores de ATD.</p></div><span class="badge">Acceso administrado</span></div>
    <form method="get" action="{{ route('portal.users.index') }}" class="filters user-filters" data-user-filters role="search">
        <label>Buscar usuarios<input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, correo, empresa o cualquier palabra…" maxlength="200" autocomplete="off" aria-describedby="user-search-help" data-user-search></label>
        <label>Rol<select name="role"><option value="">Todos los roles</option>@foreach($roles as $role)<option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
        <label>Acceso<select name="status"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activo</option><option value="inactive" @selected(request('status') === 'inactive')>Desactivado</option></select></label>
        <button type="submit" class="secondary">Buscar</button>
    </form>
    <p id="user-search-help" class="muted search-help">Coincide con cualquiera de las palabras. También puede buscar por empresa, rol, estado o identificador.</p>
    <p class="search-feedback" role="status" aria-live="polite" data-search-feedback></p>
    <div data-user-results>@include('portal.users.table')</div>
</section>

<dialog class="portal-dialog" id="manage-user" aria-labelledby="manage-user-title" data-manage-dialog>
    <div class="dialog-heading"><h2 id="manage-user-title">Gestionar usuario</h2><button type="button" class="secondary" data-close-dialog aria-label="Cerrar gestión de usuario">Cerrar ×</button></div>
    <div data-manage-content></div>
</dialog>

<dialog class="portal-dialog invite-dialog" id="invite-user" aria-labelledby="invite-user-title" data-invite-dialog @if($errors->hasAny(['name','email','company','customer_account_id'])) data-validation-errors @endif>
    <div class="dialog-heading"><div><p class="eyebrow">NUEVO ACCESO</p><h2 id="invite-user-title">Invitar usuario</h2></div><button type="button" class="secondary" data-close-dialog aria-label="Cerrar invitación">Cerrar ×</button></div>
    @if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('portal.users.store') }}" class="stack">@csrf
        <div class="form-grid">
            <label>Nombre<input name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required></label>
            <label>Correo electrónico<input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></label>
            <label>Rol<select name="role" data-invite-role>@foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('role','client') === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
        </div>
        <fieldset class="stack" data-client-account>
            <legend>Cuenta del cliente</legend>
            <label>Buscar cuenta existente<input type="search" placeholder="Nombre de empresa o correo (mínimo 2 caracteres)" maxlength="120" data-account-search data-url="{{ route('portal.users.accounts') }}" autocomplete="off"></label>
            <label>Cuenta de cliente<select name="customer_account_id" data-account-select><option value="">Crear una cuenta nueva</option>@if($selectedAccount)<option value="{{ $selectedAccount->id }}" selected>{{ $selectedAccount->name }} · {{ $selectedAccount->billing_email }}</option>@endif</select></label>
            <small role="status" data-account-feedback>Busque para ver hasta 20 cuentas coincidentes.</small>
            <label>Empresa (para una cuenta nueva)<input name="company" value="{{ old('company') }}" maxlength="200"></label>
            <p class="muted">Los clientes de una misma cuenta comparten sus solicitudes.</p>
        </fieldset>
        <p class="muted">Enviaremos un enlace para establecer la contraseña. Los administradores y colaboradores deben configurar 2FA.</p>
        <div class="dialog-actions"><button type="button" class="secondary" data-close-dialog>Cancelar</button><button>Crear y enviar invitación</button></div>
    </form>
</dialog>
<noscript><p>Puede buscar con el botón Buscar y abrir la ficha de cada usuario. Para invitar usuarios, active JavaScript.</p></noscript>
@endsection
@push('scripts')<script src="{{ asset('assets/js/portal-users.js') }}?v={{ filemtime(public_path('assets/js/portal-users.js')) }}" defer></script>@endpush
