@if($order->person)
<fieldset class="verification-fields stack"><legend>Conclusiones humanas por servicio</legend><p>Registre únicamente comprobaciones realizadas y sus limitaciones. Las selfies aportadas no equivalen a una prueba de vida. No use esta conclusión como decisión automática de elegibilidad.</p>
@if(!$verificationStatus['ready_for_review'])<p class="alert">Falta evidencia obligatoria. Solicítela al cliente antes de preparar las conclusiones.</p>@endif
@foreach($order->items as $item)
    @if(in_array($item->service->code,\App\Support\PersonVerification::SERVICES,true))
    <fieldset class="verification-subfields stack"><legend>{{ $item->service_name }}</legend>
        <label>Conclusión de {{ $item->service_name }}<select name="findings[{{ $item->service->code }}][outcome]" required><option value="">Seleccionar conclusión</option>@foreach(\App\Support\PersonVerification::OUTCOMES as $code=>$label)<option value="{{ $code }}" @selected(old('findings.'.$item->service->code.'.outcome')===$code)>{{ $label }}</option>@endforeach</select></label>
        <label>Fundamento y límites de {{ $item->service_name }}<textarea name="findings[{{ $item->service->code }}][notes]" minlength="5" maxlength="5000" required>{{ old('findings.'.$item->service->code.'.notes') }}</textarea></label>
    </fieldset>
    @endif
@endforeach
</fieldset>
@endif
