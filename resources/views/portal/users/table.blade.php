<div class="directory-summary"><strong>{{ number_format($users->total()) }} {{ $users->total() === 1 ? 'usuario' : 'usuarios' }}</strong><span class="muted">@if($users->count())Mostrando {{ $users->firstItem() }}–{{ $users->lastItem() }}@else Sin coincidencias @endif</span></div>
<div class="table-wrap">
<table class="users-table">
    <caption class="sr-only">Directorio de usuarios. Abra un nombre para gestionar su acceso.</caption>
    <thead><tr><th scope="col">Usuario</th><th scope="col">Cuenta / empresa</th><th scope="col">Rol</th><th scope="col">Acceso</th><th scope="col">Seguridad</th><th scope="col"><span class="sr-only">Opciones</span></th></tr></thead>
    <tbody>
    @forelse($users as $member)
        <tr data-user-row>
            <td><a class="row-title user-name" href="{{ route('portal.users.show',$member) }}" data-manage-user>{{ $member->name }}@if($member->id === auth()->id())<span class="self-label">Tú</span>@endif</a><small>{{ $member->email }}</small><span class="reference">#{{ $member->id }}</span></td>
            <td>{{ $member->account?->name ?? 'Equipo ATD' }}@if($member->customer_account_id)<small>Cuenta #{{ $member->customer_account_id }}</small>@endif</td>
            <td><span class="badge">{{ $member->role->label() }}</span></td>
            <td><span class="badge {{ $member->is_active ? 'status-completed' : 'status-cancelled' }}">{{ $member->is_active ? 'Activo' : 'Desactivado' }}</span></td>
            <td><small>{{ $member->email_verified_at ? 'Correo verificado' : 'Por verificar' }}</small><small>{{ $member->two_factor_confirmed_at ? '2FA activa' : 'Sin 2FA' }}</small></td>
            <td><a class="button secondary" href="{{ route('portal.users.show',$member) }}" data-manage-user aria-label="Gestionar a {{ $member->name }}">Gestionar</a></td>
        </tr>
    @empty
        <tr><td colspan="6"><div class="empty-state"><h3>No encontramos usuarios</h3><p>Pruebe otra palabra o cambie los filtros de rol y acceso.</p></div></td></tr>
    @endforelse
    </tbody>
</table>
</div>
@include('portal.pagination',['paginator'=>$users])
