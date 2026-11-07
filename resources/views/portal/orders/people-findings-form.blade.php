@if($order->people->isNotEmpty())
<fieldset class="verification-fields stack"><legend>Conclusiones por servicio</legend><p>Registre las comprobaciones profesionales y sus límites por persona. Las conclusiones Didit se incorporan desde el proveedor y no pueden editarse aquí. No use una conclusión como decisión automática de elegibilidad.</p>
@if(!$verificationStatus['ready_for_review'])<p class="alert">Falta evidencia obligatoria. Solicítela al cliente antes de preparar las conclusiones.</p>@endif
@foreach($order->people as $person)
    <details class="person-record" @if($order->people->count()===1) open @endif><summary>{{ $person->profile['first_name'] }} {{ $person->profile['last_name'] }}<small>{{ $person->services->count() }} servicios que requieren conclusión</small></summary>
    @foreach($person->services as $service)
        @if($person->identity_mode==='didit' && $service->code==='identity-verification')<p class="info-strip">{{ $person->identitySession?->decision ? 'Conclusión Didit confirmada; se incorpora automáticamente a la entrega.' : 'La verificación Didit está pendiente. No se puede preparar la conclusión todavía.' }}</p>@continue @endif
        @php($key = 'findings.'.$person->public_id.'.'.$service->code)
        <fieldset class="verification-subfields stack"><legend>{{ $service->name }}</legend>
            <label>Conclusión<select name="findings[{{ $person->public_id }}][{{ $service->code }}][outcome]" required><option value="">Seleccionar conclusión</option>@foreach(\App\Support\PersonVerification::outcomesFor($service->code) as $code=>$label)<option value="{{ $code }}" @selected(old($key.'.outcome')===$code)>{{ $label }}</option>@endforeach</select></label>
            <label>Fundamento y límites<textarea name="findings[{{ $person->public_id }}][{{ $service->code }}][notes]" minlength="5" maxlength="5000" required>{{ old($key.'.notes') }}</textarea></label>
        </fieldset>
    @endforeach
    </details>
@endforeach
</fieldset>
@endif
