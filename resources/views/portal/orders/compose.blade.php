@extends('portal.layout')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/verification.css') }}?v={{ filemtime(public_path('assets/css/verification.css')) }}">@endpush
@section('title','Nueva solicitud')
@section('subtitle','Combine servicios y personas en un solo expediente. ATD revisará el alcance antes de cotizar.')
@section('content')
@php($selectedCodes = old('service_codes', $selectedService ? [$selectedService] : []))
<noscript><p class="alert">Active JavaScript para seleccionar los servicios, completar las personas y revisar los archivos antes de enviar.</p></noscript>
<form id="order-compose" method="post" enctype="multipart/form-data" action="{{ route('portal.orders.store') }}" class="order-compose" data-order-compose>@csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="stack">
        <section class="panel stack">
            @if(\App\Services\IdentitySettings::mode()==='didit')<p class="info-strip">Identidad con Didit: después de enviar podrá preparar una captura guiada por persona. La persona debe participar con su documento y cámara; no envíe aquí selfies para sustituir la prueba de vida. {{ config('identity.didit.environment')==='sandbox' ? 'Entorno de prueba: resultados simulados.' : '' }}</p>@endif
            <div><h2>¿Qué necesita solicitar?</h2><p class="muted">Elija los servicios; después podrá asignarlos a una o varias personas.</p></div>
            <button type="button" class="service-picker-trigger secondary" data-open-services aria-haspopup="dialog" aria-controls="service-picker-dialog"><span data-service-label>Seleccionar servicios</span><span aria-hidden="true">+</span></button>
            <div class="selected-services" data-selected-services aria-live="polite"></div>
            <p class="info-strip" data-service-guidance="{{ \App\Support\ServiceCatalog::REMOTE_INTERVIEW }}" @unless(in_array(\App\Support\ServiceCatalog::REMOTE_INTERVIEW, $selectedCodes, true)) hidden @endunless>ATD coordinará la entrevista remota y el consentimiento informado después de revisar la solicitud. Para este servicio basta con la ficha autorizada de cada participante. Las grabaciones y las lecturas de pulso se obtienen durante la entrevista.</p>
            <div class="form-grid"><label>Título de la solicitud<input name="subject" value="{{ old('subject') }}" maxlength="200" required placeholder="Ej. Evaluaciones de nuevo ingreso"></label><label>Referencia de su empresa (opcional)<input name="external_reference" value="{{ old('external_reference') }}" maxlength="100" placeholder="Ej. RRHH-2026-014"></label></div>
        </section>
        <section class="panel stack" data-people-section>
            @if(old('people'))<p class="info-strip">Sus datos se conservaron. Por seguridad del navegador, vuelva a seleccionar los archivos de las personas antes de enviar.</p>@endif
            <div class="panel-heading"><div><h2>Personas de la solicitud</h2><p class="muted">Una ficha por persona, con sus servicios y documentos. Hasta 10 personas por solicitud.</p></div><button type="button" class="secondary" data-add-person>Agregar persona</button></div>
            <div class="people-list" data-people-list>
                @foreach(old('people',[]) as $index=>$entry)
                    @include('portal.orders.person-editor', ['index'=>$index, 'entry'=>$entry])
                @endforeach
            </div>
            <p class="compose-empty" data-people-empty>Seleccione servicios y agregue la primera persona. Solo verá los campos pertinentes para sus servicios.</p>
        </section>
        <section class="panel stack">
            <h2>Información adicional</h2>
            <label>Detalles de la solicitud <span class="muted">(opcional)</span><textarea name="details" rows="4" maxlength="10000" placeholder="Añada instrucciones, disponibilidad u otra información útil, si lo necesita.">{{ old('details') }}</textarea></label>
            <div class="compose-documents"><h3>Documentos de apoyo <span class="muted">(opcional)</span></h3><p class="muted">Autorizaciones y otros documentos generales. El título y el exequátur se adjuntan expresamente en la ficha de cada persona; no se sustituyen con estos archivos.</p>
                @include('portal.orders.file-input',['name'=>'documents[]','label'=>'Seleccionar documentos de apoyo','required'=>false,'multiple'=>true,'accept'=>'.pdf,.docx,.jpg,.jpeg,.png,.webp'])
            </div>
            <p class="upload-budget">Imágenes y documentos escritos únicamente. Hasta 10 MB por archivo, 20 archivos y 20 MB entre todas las personas en este envío. Si supera el límite, divida las personas en solicitudes más pequeñas.</p>
            <p class="file-feedback" data-file-feedback role="status" aria-live="polite"></p>
            <p class="compose-error" data-compose-error role="alert" hidden></p>
            <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))>Confirmo que dispongo de autorización para solicitar estas verificaciones y acepto el tratamiento de datos conforme al <a href="{{ route('privacy') }}">aviso de privacidad</a>.</label>
            <div class="actions"><button data-order-submit disabled>Enviar solicitud</button><a class="button secondary" href="{{ route('portal.orders.index') }}">Cancelar</a></div>
        </section>
    </div>
    <aside class="panel compose-guide"><h2>Su expediente, organizado</h2><div class="compose-totals"><strong data-person-count>0</strong><span data-person-count-label>personas</span><strong data-service-count>0</strong><span data-service-count-label>servicios distintos</span></div><p class="muted">Puede pedir servicios diferentes para cada persona, sin repetir sus datos.</p><ol><li><strong>Envío único</strong><p>Personas y documentos privados, juntos en una solicitud.</p></li><li><strong>Una cotización</strong><p>ATD revisa el alcance y coordina el pago manual.</p></li><li><strong>Entrega protegida</strong><p>Los resultados se publican tras completar el trabajo y confirmar el pago.</p></li></ol><a href="{{ route('portal.messages.index') }}">¿Necesita orientación? Escríbanos →</a></aside>
    <dialog class="portal-dialog services-dialog" id="service-picker-dialog" aria-labelledby="service-picker-title" data-services-dialog>
        <div class="dialog-heading"><div><h2 id="service-picker-title">Seleccione los servicios</h2><p class="muted">Combine los servicios que necesita. Las modalidades se eligen en la ficha de cada persona.</p></div><button type="button" class="secondary" data-close-dialog aria-label="Cerrar selector de servicios">Cerrar</button></div>
        <div class="catalog-controls">
            <label>Buscar servicio<input type="search" data-service-filter placeholder="Ej. nuevo ingreso, título" autocomplete="off"></label>
            <div class="catalog-tabs" role="group" aria-label="Filtrar por familia">
                @foreach(['all'=>'Todas','assessments'=>'Evaluaciones','verifications'=>'Verificaciones','additional'=>'Capacitación'] as $key=>$label)
                    <button type="button" class="secondary" data-catalog-category="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="service-options" data-catalog-list>
            @foreach($services->groupBy('category') as $category=>$categoryServices)
                <section class="catalog-group" data-service-group="{{ $category }}" aria-labelledby="catalog-group-{{ $loop->index }}">
                    <header class="catalog-group-heading">
                        <h3 id="catalog-group-{{ $loop->index }}">{{ \App\Support\ServiceCatalog::categories()[$category] ?? 'Otros servicios' }}</h3>
                        <small>{{ $category === 'additional' ? 'Solicitud general' : 'Por persona' }}</small>
                    </header>
                    <div class="catalog-group-options">
                        @foreach($categoryServices as $service)
                            <label class="service-option" data-service-option>
                                <input type="checkbox" name="service_codes[]" value="{{ $service->code }}" data-label="{{ $service->name }}" @checked(in_array($service->code,$selectedCodes))>
                                <span class="catalog-option-copy">
                                    <strong>{{ $service->name }}</strong>
                                    @if($service->options)
                                        <small class="catalog-modalities-label">Modalidades</small>
                                        <small class="catalog-modalities">{{ implode(' · ',array_column($service->options,'name')) }}</small>
                                    @else
                                        <small>{{ $service->code==='identity-verification' ? \App\Services\IdentitySettings::description() : $service->description }}</small>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
            <p class="catalog-empty muted" data-service-empty role="status" hidden>No hay servicios que coincidan con estos filtros.</p>
        </div>
        <div class="dialog-actions"><span class="muted" data-services-count aria-live="polite"></span><button type="button" data-close-dialog>Confirmar servicios</button></div>
    </dialog>
    <template data-person-template>@include('portal.orders.person-editor',['index'=>'__INDEX__','entry'=>[]])</template>
</form>
@include('portal.orders.image-preview')
@endsection
@push('scripts')<script src="{{ asset('assets/js/document-previews.js') }}?v={{ filemtime(public_path('assets/js/document-previews.js')) }}" defer></script><script src="{{ asset('assets/js/order-composer.js') }}?v={{ filemtime(public_path('assets/js/order-composer.js')) }}" defer></script>@endpush
