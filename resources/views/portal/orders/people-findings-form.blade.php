@if($order->people->isNotEmpty())
<fieldset class="verification-fields stack"><legend>Conclusiones humanas por servicio</legend><p>Registre las comprobaciones y sus límites por persona. Las selfies aportadas no equivalen a una prueba de vida. No use esta conclusión como decisión automática de elegibilidad.</p>
@if(!$verificationStatus['ready_for_review'])<p class="alert">Falta evidencia obligatoria. Solicítela al cliente antes de preparar las conclusiones.</p>@endif
@foreach($order->people as $person)
    <details class="person-record" @if($order->people->count()===1) open @endif><summary>{{ $person->profile['first_name'] }} {{ $person->profile['last_name'] }}<small>{{ $person->services->count() }} servicios que requieren conclusión</small></summary>
    @foreach($person->services as $service)
        @php($key = 'findings.'.$person->public_id.'.'.$service->code)
        <fieldset class="verification-subfields stack"><legend>{{ $service->name }}</legend>
            <label>Conclusión<select name="findings[{{ $person->public_id }}][{{ $service->code }}][outcome]" required><option value="">Seleccionar conclusión</option>@foreach(\App\Support\PersonVerification::OUTCOMES as $code=>$label)<option value="{{ $code }}" @selected(old($key.'.outcome')===$code)>{{ $label }}</option>@endforeach</select></label>
            <label>Fundamento y límites<textarea name="findings[{{ $person->public_id }}][{{ $service->code }}][notes]" minlength="5" maxlength="5000" required>{{ old($key.'.notes') }}</textarea></label>
        </fieldset>
    @endforeach
    </details>
@endforeach
</fieldset>
@endif
