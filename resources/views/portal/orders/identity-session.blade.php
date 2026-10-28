@if($person->identity_mode === 'didit')
@php($identitySession = $person->identitySession)
<div class="stack">
    <h3>Captura de identidad · Didit</h3>
    @if($creditOrder ?? false)
        <p class="info-strip">Identidad API a crédito. El enlace de captura y la conclusión técnica se consultan desde la integración autorizada con <code>identity:read</code>. No requiere prepago ni publicación manual. Este panel muestra solo seguimiento.</p>
        @if($identitySession)<p class="muted">Referencia: {{ $identitySession->public_id }} · {{ $identitySession->publicState() }} · revisión {{ $identitySession->decision_revision }}</p>@endif
    @else
    @if(config('identity.didit.environment')==='sandbox')<p class="info-strip">Modo sandbox: esta captura genera comprobaciones simuladas, no una verificación real.</p>@endif
    @if(!$identitySession)
        @if(!auth()->user()->isStaff())
        <form method="post" action="{{ route('portal.identity.store',[$order,$person->public_id]) }}" class="stack">@csrf
            <input type="hidden" name="notice_version" value="{{ config('identity.notice_version') }}">
            <p class="muted">Didit capturará el documento, rostro y prueba de vida activa 3D, con un reto guiado ante la cámara. No se envían automáticamente a Didit los archivos ya cargados en ATD.</p>
            @if(config('identity.didit.environment')==='live' && !$order->hasConfirmedPayment())<p class="info-strip">ATD debe emitir la factura y confirmar su pago total antes de habilitar una captura real con Didit.</p>@endif
            <label class="check"><input type="checkbox" name="consent" value="1" required>He informado a la persona y cuento con su autorización para el tratamiento por Didit descrito en el <a href="{{ route('privacy') }}">aviso de privacidad</a>, versión {{ config('identity.notice_version') }}.</label>
            <div><button @disabled(!\App\Services\IdentitySettings::ready() || (config('identity.didit.environment')==='live' && !$order->hasConfirmedPayment()))>Preparar verificación con Didit</button></div>
        </form>
        @else<p class="muted">El cliente debe confirmar la autorización e iniciar la captura desde su cuenta o integración API.</p>@endif
    @else
        <p class="muted">Referencia ATD: {{ $identitySession->public_id }}</p>
        @switch($identitySession->publicState())
            @case('preparing')<p class="info-strip">Preparando el enlace. Actualice esta página en unos segundos. El worker y el scheduler deben estar activos.</p>@break
            @case('awaiting_person')<p class="info-strip">Captura pendiente. El enlace es privado y corresponde únicamente a esta persona.</p>
                @if(!auth()->user()->isStaff() && $identitySession->verification_url)<p><a class="button" href="{{ $identitySession->verification_url }}" target="_blank" rel="noopener noreferrer nofollow">Abrir captura segura con Didit ↗</a></p>@endif
                @break
            @case('received')<p class="info-strip">Procesamiento recibido. La entrega se consulta en Resultados cuando ATD complete el caso, confirme el pago y publique el informe. Este aviso no confirma una aprobación.</p>@break
            @case('attention_required')<p class="alert">No se pudo confirmar la preparación. Contacte con ATD con esta referencia; no cree otra solicitud para reintentar. La conciliación evita sesiones y cobros duplicados.</p>@break
            @default<p class="info-strip">Captura en procesamiento. El seguimiento se actualiza automáticamente en segundo plano; actualice esta página para consultarlo.</p>
        @endswitch
    @endif
    @endif
</div>
@endif
