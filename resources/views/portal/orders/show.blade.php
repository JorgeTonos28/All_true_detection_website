@extends('portal.layout')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/verification.css') }}?v={{ filemtime(public_path('assets/css/verification.css')) }}">@endpush
@section('title',$order->subject)
@section('subtitle','Solicitud '.$order->public_id)
@section('actions')<a class="button secondary" href="{{ route('portal.messages.show',$order->conversation) }}">Conversar con {{ auth()->user()->isStaff() ? 'el cliente' : 'ATD' }}</a>@endsection
@section('content')
<p class="account-type-label">{{ $order->account->name }} · {{ $order->account->typeLabel() }}</p>
@if($creditOrder)<div class="info-strip">Solicitud exclusiva de identidad API a crédito. La conclusión técnica se entrega automáticamente a la integración autorizada. El consumo se concilia por período; no se emite una factura individual en este expediente.</div>@else
<ol class="order-progress" aria-label="Progreso de la solicitud"><li class="done"><strong>1. Recibida</strong><small>{{ $order->created_at->timezone(config('portal.display_timezone'))->format('d/m/Y') }}</small></li><li class="{{ $order->status->value !== 'submitted' ? 'done' : '' }}"><strong>2. Revisión y cotización</strong><small>{{ $order->invoice ? 'Factura disponible' : 'En revisión' }}</small></li><li class="{{ in_array($order->status->value,['in_progress','completed']) ? 'done' : '' }}"><strong>3. En ejecución</strong><small>{{ $order->status->value === 'completed' ? 'Trabajo completado' : 'Seguimiento del caso' }}</small></li><li class="{{ $order->canReleaseResults() && $results->whereNotNull('published_at')->isNotEmpty() ? 'done' : '' }}"><strong>4. Entrega</strong><small>{{ $order->canReleaseResults() && $results->whereNotNull('published_at')->isNotEmpty() ? 'Resultados disponibles' : 'Pendiente de pago / publicación' }}</small></li></ol>
@endif
<div class="order-overview"><span class="badge status-{{ $order->status->value }}">{{ $order->status->label() }}</span><span>{{ $order->created_at->timezone(config('portal.display_timezone'))->format('d/m/Y H:i') }}</span><span>Cliente: <strong>{{ $order->account->name }}</strong></span>@if($order->external_reference)<span>Referencia: {{ $order->external_reference }}</span>@endif</div>
<div class="detail-grid">
<div class="stack">
<section class="panel"><h2>Alcance de la solicitud</h2><p class="preserve">{{ $order->details ?: 'Sin instrucciones adicionales.' }}</p><h3>Servicios solicitados</h3><ul class="clean-list">@foreach($order->items as $item)<li><strong>{{ $item->service_name }}</strong><span>{{ $item->quantity }} {{ $item->quantity === 1 ? 'unidad' : 'unidades' }}</span></li>@endforeach</ul></section>
@include('portal.orders.people-detail')
<section class="panel"><h2>Documentos de entrada</h2><p class="muted">PDF, Word DOCX sin macros, JPG, PNG o WebP. Máximo 10 MB por archivo, 30 documentos y 100 MB por solicitud.</p>
<div class="attachment-gallery">@forelse($order->attachments as $attachment)<article class="attachment-card">
    @if(in_array($attachment->mime_type,\App\Support\PrivateImage::TYPES,true))<button type="button" class="image-thumbnail" data-image-preview="{{ route('portal.attachments.preview',$attachment) }}" data-image-name="{{ $attachment->file_name }}" aria-label="Ampliar {{ $attachment->file_name }}"><img src="{{ route('portal.attachments.preview',$attachment) }}" alt="Vista previa de {{ $attachment->file_name }}" loading="lazy" decoding="async"></button>@endif
    <p>{{ $attachment->file_name }}</p><small>{{ \App\Support\PersonVerification::LABELS[$attachment->purpose] ?? 'Documento privado' }}</small>
    @if($attachment->person)<small>{{ $attachment->person->profile['first_name'] }} {{ $attachment->person->profile['last_name'] }}</small>@endif
    <a href="{{ route('portal.attachments.download',$attachment) }}">Descargar archivo</a>
</article>@empty<p class="muted">No hay documentos cargados.</p>@endforelse</div>
@if(!auth()->user()->isStaff() && !in_array($order->status->value,['completed','cancelled']))<form method="post" enctype="multipart/form-data" action="{{ route('portal.attachments.store',$order) }}" class="stack" data-attachment-form>@csrf
    <div class="form-grid"><label>Persona<select name="person_id" data-upload-person><option value="">Documento general de la solicitud</option>@foreach($order->people as $person)<option value="{{ $person->public_id }}" data-service-codes="{{ $person->services->pluck('code')->join(',') }}">{{ $person->profile['first_name'] }} {{ $person->profile['last_name'] }}</option>@endforeach</select></label>
    <label>Categoría<select name="purpose" data-upload-purpose>@foreach(\App\Support\PersonVerification::LABELS as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label></div>
    @include('portal.orders.file-input',['name'=>'file','label'=>'Agregar documento','required'=>true,'accept'=>'.pdf,.docx,.jpg,.jpeg,.png,.webp'])
    <p class="muted">Seleccione la persona antes de agregar evidencia clasificada. El mismo archivo no se puede repetir, aunque cambie su nombre.</p><div><button>Subir documento</button></div>
</form>@endif</section>
<section class="panel"><h2>Resultados y entrega</h2>
@if($results->isEmpty())<div class="info-strip">{{ $creditOrder ? 'La conclusión técnica de este servicio se consulta por la API, no como un informe publicado en este panel.' : ($order->canReleaseResults() ? 'El equipo aún no ha publicado la entrega.' : 'La entrega se habilitará al completar el trabajo, confirmar el pago total y publicar los resultados.') }}</div>@endif
@foreach($results as $result)<article class="result-card"><div class="panel-heading"><h3>Entrega {{ $loop->iteration }}</h3><span class="badge">{{ $result->published_at ? 'Publicada' : 'Preparada · privada' }}</span></div><p class="preserve">{{ $result->summary }}</p>
@if($result->verification_findings)<div class="verification-conclusions"><p class="muted">{{ match($result->verification_findings['method'] ?? 'manual') {'didit'=>'Procesamiento Didit','mixed'=>'Didit + revisión profesional',default=>'Revisión profesional'} }} · {{ $result->verification_findings['reviewed_at'] }}</p>@foreach($result->verification_findings['checks'] as $check)<article>
    @php($checkedPerson = isset($check['person_id']) ? $order->people->firstWhere('public_id',$check['person_id']) : null)
    @if($checkedPerson)<p><strong>{{ $checkedPerson->profile['first_name'] }} {{ $checkedPerson->profile['last_name'] }}</strong></p>@endif
    <h4>{{ $order->items->first(fn($item)=>$item->service->code===$check['service_code'])?->service_name ?? $check['service_code'] }}</h4>@if(($check['environment'] ?? null)==='sandbox')<p class="alert">SIMULACIÓN SANDBOX · No constituye una verificación real.</p>@endif<strong>{{ \App\Support\PersonVerification::OUTCOMES[$check['outcome']] }}</strong><p class="preserve">{{ $check['notes'] }}</p></article>@endforeach</div>@endif
@if($result->file_path)
    @if(in_array($result->mime_type,\App\Support\PrivateImage::TYPES,true))<button type="button" class="image-thumbnail" data-image-preview="{{ route('portal.results.preview',$result) }}" data-image-name="{{ $result->file_name }}" aria-label="Ampliar {{ $result->file_name }}"><img src="{{ route('portal.results.preview',$result) }}" alt="Vista previa del resultado publicado" loading="lazy" decoding="async"></button>@endif
    <a class="button secondary" href="{{ route('portal.results.download',$result) }}">Descargar {{ $result->file_name }}</a>
@endif
@if(auth()->user()->isAdmin() && !$result->published_at)<form method="post" action="{{ route('portal.results.publish',$result) }}">@csrf<button @disabled(!$order->canReleaseResults())>Publicar para el cliente</button></form>@endif</article>@endforeach
@if(auth()->user()->isStaff() && !$creditOrder)<details class="form-details"><summary>Preparar nueva entrega</summary><p>El resultado se guarda de forma privada. Para publicarlo, un administrador debe confirmar el pago total y completar la orden. Utilice esta sección para los resultados finales.</p><form method="post" enctype="multipart/form-data" action="{{ route('portal.results.store',$order) }}" class="stack">
@csrf
@include('portal.orders.people-findings-form')
<label>Resumen del resultado<textarea name="summary" minlength="5" maxlength="20000" required>{{ old('summary') }}</textarea></label><label>Documento final (opcional, hasta 10 MB)<input type="file" name="file" accept=".pdf,.docx,.jpg,.jpeg,.png,.webp"></label><button @disabled($verificationStatus && !$verificationStatus['ready_for_review'])>Guardar resultado privado</button></form></details>@endif
</section>
</div>
<aside class="stack">
@if(auth()->user()->isStaff())<section class="panel"><h2>Gestión del caso</h2><form method="post" action="{{ route('portal.orders.update',$order) }}" class="stack">@csrf @method('PATCH')
@if(auth()->user()->isAdmin())<label>Responsable<select name="assigned_to"><option value="">Sin asignar</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected($order->assigned_to === $member->id)>{{ $member->name }}</option>@endforeach</select></label>@else<p>Responsable: {{ $order->assignee?->name ?? 'Sin asignar' }}</p>@endif
<label>Estado<select name="status">@foreach(array_merge([$order->status],$order->status->nextStates()) as $state)@continue($state === \App\Enums\OrderStatus::Cancelled && !auth()->user()->isAdmin())<option value="{{ $state->value }}" @selected($order->status === $state)>{{ $state->label() }}</option>@endforeach</select></label><small>Para cotizar, emita la factura en Facturación. El estado cambiará a Cotizada automáticamente. Complete la evidencia y las conclusiones antes de finalizar el trabajo.</small><button>Actualizar caso</button></form></section>@endif
<section class="panel"><h2>Facturación</h2>
@if($creditOrder)
    <span class="badge">Consumo a crédito</span><p>ATD coordina el cobro por corte según el acuerdo comercial. No hay un pago individual pendiente para habilitar la captura o la conclusión técnica.</p>
    @if(auth()->user()->isAdmin() || !auth()->user()->isStaff())<a href="{{ route('portal.integrations.identity') }}">Consultar consumo y acceso →</a>@endif
@elseif($order->invoice)
    @php($invoice = $order->invoice)
    <span class="badge {{ $invoice->status === 'paid' ? 'status-completed' : '' }}">{{ $invoice->status === 'paid' ? 'Pago confirmado' : 'Pago pendiente' }}</span><p class="reference">{{ $invoice->number }}</p><dl class="amounts"><dt>Subtotal</dt><dd>{{ \App\Support\Money::format($invoice->subtotal,$invoice->currency) }}</dd><dt>Impuestos</dt><dd>{{ \App\Support\Money::format($invoice->tax,$invoice->currency) }}</dd><dt>Total</dt><dd><strong>{{ \App\Support\Money::format($invoice->total,$invoice->currency) }}</strong></dd><dt>Saldo pendiente</dt><dd>{{ \App\Support\Money::format($invoice->outstanding(),$invoice->currency) }}</dd></dl><a class="button secondary" href="{{ route('portal.invoices.show',$invoice) }}">Ver factura</a>
    @if($invoice->outstanding() > 0)@include('portal.payment-accounts')@endif
    @if($invoice->payments->isNotEmpty())<h3>Pagos registrados</h3><ul class="clean-list">@foreach($invoice->payments as $payment)<li><span>{{ \App\Support\Money::format($payment->amount,$invoice->currency) }}<small>{{ $payment->reference }}</small></span><small>{{ $payment->received_at->format('d/m/Y') }}</small></li>@endforeach</ul>@endif
    @if(auth()->user()->isAdmin() && $invoice->status !== 'paid')<details class="form-details"><summary>Confirmar pago recibido</summary><p>Registre únicamente fondos ya recibidos. Esta operación autoriza la futura entrega de resultados.</p><form method="post" action="{{ route('portal.payments.store',$invoice) }}" class="stack">@csrf<label>Importe recibido ({{ $invoice->currency }})<input type="number" name="amount" min="0.01" max="{{ \App\Support\Money::decimal($invoice->outstanding()) }}" step="0.01" required></label><label>Vía<select name="method"><option value="bank_transfer">Transferencia</option><option value="cash">Efectivo</option><option value="check">Cheque</option><option value="other">Otra vía</option></select></label><label>Referencia única<input name="reference" maxlength="100" required></label><label>Fecha de recepción<input type="date" name="received_at" max="{{ now()->format('Y-m-d') }}"></label><button>Confirmar pago</button></form></details>@endif
@else
    <p>ATD revisará el alcance y emitirá la cotización. El pago se coordina de forma manual.</p>
    @if($quoteLines)@include('portal.orders.quote-form')@endif
@endif
</section>
<section class="panel"><h3>Seguimiento</h3><p>Las actualizaciones y consultas quedan registradas en la conversación de esta solicitud.</p><a href="{{ route('portal.messages.show',$order->conversation) }}">Abrir conversación →</a></section>
</aside>
</div>
@include('portal.orders.image-preview')
@endsection
@push('scripts')<script src="{{ asset('assets/js/document-previews.js') }}?v={{ filemtime(public_path('assets/js/document-previews.js')) }}" defer></script><script src="{{ asset('assets/js/order-documents.js') }}?v={{ filemtime(public_path('assets/js/order-documents.js')) }}" defer></script>@endpush
