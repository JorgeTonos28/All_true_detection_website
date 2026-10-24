@extends('portal.layout')
@section('title','Nueva solicitud')
@section('subtitle','Cuéntenos qué necesita. Revisaremos el alcance y le enviaremos una cotización.')
@section('content')
@php($selectedCodes = old('service_codes', $selectedService ? [$selectedService] : []))
<form method="post" enctype="multipart/form-data" action="{{ route('portal.orders.store') }}" class="order-compose" data-order-compose>@csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <section class="panel stack">
        <div><p class="eyebrow">01 · SU SOLICITUD</p><h2>Servicios y detalles</h2></div>
        <fieldset class="service-picker" data-service-picker>
            <legend>¿Qué servicios necesita? <small>Seleccione uno o varios.</small></legend>
            <details class="service-dropdown">
                <summary><span data-service-label>{{ count($selectedCodes) ? count($selectedCodes).' servicios seleccionados' : 'Seleccionar servicios' }}</span><span aria-hidden="true">⌄</span></summary>
                <div class="service-dropdown-content">
                    <label>Buscar servicio<input type="search" placeholder="Escriba para filtrar los servicios" data-service-filter autocomplete="off"></label>
                    <div class="service-options">@foreach($services as $service)
                        <label class="service-option" data-service-option><input type="checkbox" name="service_codes[]" value="{{ $service->code }}" data-label="{{ $service->name }}" @checked(in_array($service->code,$selectedCodes))><span><strong>{{ $service->name }}</strong><small>{{ $service->description }}</small></span></label>
                    @endforeach</div>
                    <small data-service-empty hidden>No hay servicios que coincidan.</small>
                </div>
            </details>
            <div class="selected-services" data-selected-services aria-live="polite"></div>
        </fieldset>
        <div class="form-grid"><label>Título de la solicitud<input name="subject" value="{{ old('subject') }}" maxlength="200" required placeholder="Ej. Validación de credenciales profesionales"></label><label>Referencia de su empresa (opcional)<input name="external_reference" value="{{ old('external_reference') }}" maxlength="100" placeholder="Ej. RRHH-2026-014"></label></div>
        <label>Detalles de la solicitud<textarea name="details" rows="8" minlength="10" maxlength="10000" required placeholder="Indique el alcance, el objetivo y cualquier información necesaria para revisar el caso.">{{ old('details') }}</textarea></label>
        <div class="compose-documents"><p class="eyebrow">02 · DOCUMENTACIÓN</p><h3>Adjuntar documentos <span class="muted">(opcional)</span></h3><p class="muted">PDF, Word DOCX sin macros, JPG, PNG o WebP. Hasta 5 archivos, 10 MB por archivo y 20 MB en total. Puede agregar más documentos después.</p><label>Seleccionar documentos<input type="file" name="documents[]" accept=".pdf,.docx,.jpg,.jpeg,.png,.webp" multiple data-order-files></label><p class="file-feedback" data-file-feedback role="status"></p></div>
        <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))>Confirmo que dispongo de autorización para solicitar estas verificaciones y acepto el tratamiento de datos conforme al <a href="{{ route('privacy') }}">aviso de privacidad</a>.</label>
        <div class="actions"><button data-order-submit>Enviar solicitud</button><a class="button secondary" href="{{ route('portal.orders.index') }}">Cancelar</a></div>
    </section>
    <aside class="panel compose-guide"><p class="eyebrow">ASÍ CONTINUAMOS</p><h2>De la solicitud a su entrega</h2><ol><li><strong>Recibimos su caso</strong><p>Guardamos los servicios, los detalles y sus documentos privados.</p></li><li><strong>Revisamos y cotizamos</strong><p>Le avisamos cuando la factura esté disponible y coordinamos el pago.</p></li><li><strong>Trabajamos y entregamos</strong><p>ATD publica los resultados al completar el trabajo y confirmar el pago.</p></li></ol><a href="{{ route('portal.messages.index') }}">¿Necesita orientación? Escríbanos →</a></aside>
</form>
@endsection
@push('scripts')<script src="{{ asset('assets/js/portal-orders.js') }}?v={{ filemtime(public_path('assets/js/portal-orders.js')) }}" defer></script>@endpush
