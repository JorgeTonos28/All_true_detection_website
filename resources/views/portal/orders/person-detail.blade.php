@if($order->person)
@php($profile = $order->person->profile)
@php($verification = $verificationStatus)
<section class="panel person-detail"><div class="panel-heading"><h2>Persona del expediente</h2><span class="badge">Información privada</span></div>
    <dl class="person-facts">
        <div><dt>Nombre completo</dt><dd>{{ $profile['first_name'] }} {{ $profile['last_name'] }}</dd></div>
        <div><dt>Cédula</dt><dd>{{ $profile['identity_number'] }}</dd></div>
        <div><dt>Dirección</dt><dd class="preserve">{{ $profile['address'] }}</dd></div>
        <div><dt>Finalidad autorizada</dt><dd class="preserve">{{ $profile['purpose'] }}</dd></div>
        @foreach(['birth_date'=>'Nacimiento','phone'=>'Teléfono','email'=>'Correo'] as $key=>$label)
            @if(!empty($profile[$key]))<div><dt>{{ $label }}</dt><dd>{{ $profile[$key] }}</dd></div>@endif
        @endforeach
        @foreach(['degree'=>['institution'=>'Institución','degree_name'=>'Título','graduation_year'=>'Año de graduación','registration_number'=>'Registro académico'],'exequatur'=>['profession'=>'Profesión','number'=>'Número de exequátur','issuing_authority'=>'Entidad emisora','issued_on'=>'Fecha de expedición']] as $group=>$fields)
            @foreach($fields as $key=>$label)@if(!empty($profile[$group][$key]))<div><dt>{{ $label }}</dt><dd>{{ $profile[$group][$key] }}</dd></div>@endif @endforeach
        @endforeach
    </dl>
    <p class="muted">Una persona, {{ $order->items->whereIn('service.code',\App\Support\PersonVerification::SERVICES)->count() }} servicio(s) de verificación. Autorización confirmada por el solicitante el {{ $order->consented_at->format('d/m/Y H:i') }} UTC.</p>
    <div class="info-strip">@if($verification['ready_for_review'])Evidencia obligatoria recibida. Pendiente de la revisión humana y del proceso de entrega.@else Falta cargar: {{ implode(', ',array_map(fn($key)=>\App\Support\PersonVerification::LABELS[$key],$verification['missing'])) }}. La solicitud no avanzará hasta recibirla.@endif</div>
</section>
@endif
