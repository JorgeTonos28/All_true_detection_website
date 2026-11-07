@extends('portal.layout')
@section('title','Identidades reutilizables')
@section('subtitle', $admin ? 'Referencias consentidas por cuenta, vigencia y revocación.' : 'Solicite otra comprobación facial sin volver a cargar la cédula.')
@section('actions')<a class="button secondary" href="{{ route('portal.orders.create',['service'=>'identity-verification']) }}">Nueva verificación completa</a>@endsection
@section('content')
<div class="info-strip"><strong>Una referencia, nuevas capturas</strong><p>Didit conserva la referencia facial de la verificación aprobada. ATD guarda el vínculo y los datos básicos cifrados, no descarga ese retrato. Cada reverificación requiere una captura activa nueva; no vuelve a validar el documento. La vigencia máxima es de 90 días desde la verificación original y no se extiende con cada selfie.</p></div>
@if(!\App\Services\IdentitySettings::reuseReady())<p class="alert">La captura recurrente no está habilitada: ATD debe completar el workflow y la configuración de privacidad. Puede consultar o revocar referencias existentes.</p>@endif
<section class="panel stack"><div class="panel-heading"><h2>{{ $admin ? 'Referencias por cliente' : 'Mis referencias' }}</h2><span class="badge">{{ $subjects->total() }} registradas</span></div>
<form method="get" class="filters"><label>Referencia exacta<input name="q" value="{{ request('q') }}" maxlength="26" placeholder="Identificador ATD"></label><button class="secondary">Buscar</button>@if(request('q'))<a href="{{ route('portal.identities.index') }}">Limpiar</a>@endif</form>
<div class="table-wrap"><table><thead><tr><th>Persona / referencia</th>@if($admin)<th>Cuenta</th>@endif<th>Entorno</th><th>Vigencia</th><th>Gestión</th></tr></thead><tbody>
@forelse($subjects as $identity)
@php($profile = $identity->profile)
@php($available = \App\Services\IdentityReferences::usable($identity))
<tr><td><strong>{{ $profile['first_name'] }} {{ $profile['last_name'] }}</strong><small>Cédula ••••{{ substr($profile['identity_number'],-4) }}</small><small><code>{{ $identity->public_id }}</code></small></td>
@if($admin)<td>{{ $identity->account->name }}</td>@endif
<td><span class="badge">{{ $identity->environment==='sandbox' ? 'Sandbox · simulación' : 'Live' }}</span></td>
<td><span class="badge {{ $available ? 'status-completed' : '' }}">{{ $identity->revoked_at ? 'Revocada' : ($identity->expires_at->isPast() ? 'Vencida' : ($available ? 'Disponible' : 'Revisión necesaria')) }}</span><small>Hasta {{ $identity->expires_at->format('d/m/Y H:i') }} UTC</small></td>
<td><button type="button" class="secondary" data-identity-open="identity-{{ $identity->id }}" aria-haspopup="dialog" aria-controls="identity-{{ $identity->id }}">Gestionar</button>
<dialog class="portal-dialog" id="identity-{{ $identity->id }}" aria-labelledby="identity-title-{{ $identity->id }}"><div class="dialog-heading"><div><p class="eyebrow">REFERENCIA CONSENTIDA</p><h2 id="identity-title-{{ $identity->id }}">{{ $profile['first_name'] }} {{ $profile['last_name'] }}</h2></div><button type="button" class="secondary" data-identity-close>Cerrar</button></div><div class="stack">
<p class="muted">Cédula ••••{{ substr($profile['identity_number'],-4) }} · {{ $identity->environment }}<br>Vigencia hasta {{ $identity->expires_at->format('d/m/Y H:i') }} UTC</p>
@unless($admin)
<p>La solicitud web genera su propia cotización y requiere pago antes de habilitar el enlace Didit. Puede existir solo una captura pendiente por referencia.</p>
<form method="post" action="{{ route('portal.identities.verify',$identity) }}" class="stack">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}"><input type="hidden" name="notice_version" value="{{ config('identity.reuse_notice_version') }}">
<label>Finalidad autorizada<input name="purpose" value="{{ $profile['purpose'] }}" minlength="5" maxlength="1000"></label>
<label>Detalles (opcional)<textarea name="details" maxlength="10000"></textarea></label>
<label class="check"><input type="checkbox" name="consent" value="1" required>He informado a la persona y tengo su autorización para esta nueva captura y comparación con la referencia conservada por Didit, conforme al <a href="{{ route('privacy') }}">aviso de privacidad</a> {{ config('identity.reuse_notice_version') }}.</label>
<button @disabled(!$available || !\App\Services\IdentitySettings::reuseReady() || \App\Services\IdentitySettings::mode()!=='didit')>Solicitar reverificación</button></form>
@endunless
<a href="{{ route('portal.orders.show',$identity->sourceSession->order) }}">Ver expediente original →</a>
@if(!$identity->revoked_at)<form method="post" action="{{ route('portal.identities.revoke',$identity) }}">@csrf<p class="muted">Revocar impide nuevas capturas en ATD. No elimina datos de Didit ni revoca enlaces ya entregados. Para supresión, contacte con ATD.</p><button class="secondary">Revocar referencia</button></form>@endif
</div></dialog></td></tr>
@empty<tr><td colspan="{{ $admin ? 5 : 4 }}">No hay referencias registradas. Tras recibir una verificación documental aprobada, abra su expediente y autorice expresamente su reutilización. Las revisiones manuales no sirven como referencia biométrica.</td></tr>@endforelse
</tbody></table></div>{{ $subjects->links('portal.pagination') }}</section>
@endsection
@push('scripts')<script src="{{ asset('assets/js/reusable-identities.js') }}" defer></script>@endpush
