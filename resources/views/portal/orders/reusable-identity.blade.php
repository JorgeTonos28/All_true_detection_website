@if(config('portal.reusable_identity_ui_enabled') && $person->identity_mode==='didit' && $person->identitySession && !auth()->user()->isStaff())
@php($reuseSource = $person->identitySession)
@if($person->identity_subject_id)
<p class="info-strip">Autenticación recurrente · referencia {{ $person->identitySubject->public_id }}. Incluye captura activa nueva y comparación facial, no una nueva validación de la cédula.</p>
@elseif($reuseSource->enrolledSubject)
<p class="info-strip">Esta verificación tiene una referencia de reutilización registrada hasta {{ $reuseSource->enrolledSubject->expires_at->format('d/m/Y') }}. <a href="{{ route('portal.identities.index') }}">Consultar mis identidades →</a></p>
@elseif($reuseSource->access_channel==='web' && \App\Services\IdentityReferences::releasedSource($reuseSource) && \App\Services\IdentitySettings::reuseReady())
<details class="form-details"><summary>Habilitar próximas verificaciones sin volver a subir la cédula</summary>
<form method="post" action="{{ route('portal.identities.enroll',[$order,$person->public_id]) }}" class="stack">@csrf
<input type="hidden" name="notice_version" value="{{ config('identity.reuse_notice_version') }}">
<p>Didit reutilizará la referencia facial aprobada para comparar nuevas capturas activas. ATD guarda un vínculo y el perfil básico cifrado, sin descargar el retrato. La vigencia máxima es de 90 días desde esta verificación original; después deberá verificarse nuevamente con documento.</p>
<label class="check"><input type="checkbox" name="consent" value="1" required>He informado a la persona y cuento con su autorización expresa para esta reutilización, descrita en el <a href="{{ route('privacy') }}">aviso de privacidad</a> {{ config('identity.reuse_notice_version') }}.</label>
<div><button>Registrar referencia reutilizable</button></div></form></details>
@endif
@endif
