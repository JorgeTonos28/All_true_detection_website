@extends('portal.layout')
@section('title','Nueva solicitud')
@section('subtitle','Seleccione los servicios y describa el alcance. ATD revisará su caso antes de cotizar.')
@section('content')
<form method="post" action="{{ route('portal.orders.store') }}" class="stack">@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
<div class="two-columns"><section class="panel stack"><h2>¿Qué necesita verificar?</h2><label>Asunto<input name="subject" value="{{ old('subject') }}" maxlength="200" required placeholder="Ej. Verificación de un candidato"></label><label>Descripción del caso<textarea name="details" rows="7" minlength="10" maxlength="10000" required>{{ old('details') }}</textarea></label><label>Su referencia interna (opcional)<input name="external_reference" value="{{ old('external_reference') }}" maxlength="100"></label><p class="muted">Después de crear la solicitud podrá agregar documentos privados. Cargue únicamente información para la que disponga de autorización.</p></section>
<section class="panel"><h2>Servicios</h2><fieldset class="service-choices"><legend class="muted">Puede seleccionar más de uno.</legend>@foreach($services as $service)<label class="service-choice"><input type="checkbox" name="service_codes[]" value="{{ $service->code }}" @checked(in_array($service->code,old('service_codes',[])))><span><strong>{{ $service->name }}</strong><small>{{ $service->description }}</small></span></label>@endforeach</fieldset></section></div>
<section class="panel stack"><label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))>Confirmo que dispongo de autorización para solicitar estas verificaciones y acepto el tratamiento de datos conforme al <a href="{{ route('privacy') }}">aviso de privacidad</a>.</label><div class="actions"><button>Enviar solicitud</button><a class="button secondary" href="{{ route('portal.orders.index') }}">Cancelar</a></div></section>
</form>
@endsection
