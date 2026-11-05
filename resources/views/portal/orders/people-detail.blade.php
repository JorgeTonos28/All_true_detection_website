@if($order->people->isNotEmpty())
<section class="panel person-detail"><div class="panel-heading"><h2>Personas del expediente</h2><span class="badge">{{ $order->people->count() }} · Información privada</span></div>
    @foreach($order->people as $person)
        @php($profile = $person->profile)
        @php($verification = collect($verificationStatus['people'] ?? [])->firstWhere('person_id',$person->public_id))
        <details class="person-record" @if($order->people->count()===1) open @endif><summary>{{ $profile['first_name'] }} {{ $profile['last_name'] }}<small>{{ $person->services->pluck('name')->join(' · ') }}</small></summary>
            <dl class="person-facts">
                @foreach(['identity_number'=>'Cédula','address'=>'Dirección','purpose'=>'Finalidad autorizada','birth_date'=>'Nacimiento','phone'=>'Teléfono','email'=>'Correo'] as $key=>$label)
                    @if(!empty($profile[$key]))<div><dt>{{ $label }}</dt><dd class="preserve">{{ $profile[$key] }}</dd></div>@endif
                @endforeach
                @foreach(['degree'=>['institution'=>'Institución','degree_name'=>'Título','graduation_year'=>'Año de graduación','registration_number'=>'Registro académico'],'exequatur'=>['profession'=>'Profesión','number'=>'Número de exequátur','issuing_authority'=>'Entidad emisora','issued_on'=>'Fecha de expedición'],'bailiff'=>['credential_number'=>'Número de credencial','issuing_authority'=>'Entidad emisora','jurisdiction'=>'Jurisdicción']] as $group=>$fields)
                    @foreach($fields as $key=>$label)@if(!empty($profile[$group][$key]))<div><dt>{{ $label }}</dt><dd>{{ $profile[$group][$key] }}</dd></div>@endif @endforeach
                @endforeach
                @foreach($profile['service_options'] ?? [] as $serviceCode=>$optionCode)
                    @php($serviceRecord = $person->services->firstWhere('code',$serviceCode))
                    @php($optionLabel = collect($serviceRecord?->options ?? [])->firstWhere('code',$optionCode)['name'] ?? null)
                    @if($optionLabel)<div><dt>{{ $serviceRecord->name }}</dt><dd>{{ $optionLabel }}</dd></div>@endif
                @endforeach
            </dl>
            @if($verification)<div class="info-strip">@if($verification['ready_for_review'])Documentación de entrada completa. {{ $person->identity_mode==='didit' ? 'El procesamiento de identidad se consulta abajo.' : 'Pendiente de revisión profesional.' }} {{ ($creditOrder ?? false) ? 'Conclusión técnica por API para el partner autorizado.' : 'La entrega conserva sus controles de pago y publicación.' }}@else Falta cargar: {{ implode(', ',array_map(fn($key)=>\App\Support\PersonVerification::LABELS[$key],$verification['missing'])) }}. La solicitud no avanzará hasta recibirla.@endif</div>@endif
            @include('portal.orders.identity-session')
        </details>
    @endforeach
    <p class="muted">Autorización confirmada por el solicitante el {{ $order->consented_at->timezone('America/Santo_Domingo')->format('d/m/Y H:i') }}. Cada servicio y documento se conserva vinculado a su persona.</p>
</section>
@endif
