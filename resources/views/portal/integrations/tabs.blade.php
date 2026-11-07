<nav class="integration-tabs" aria-label="Secciones de integraciones">
    @foreach(['overview'=>'Resumen','tokens'=>'Credenciales','webhooks'=>'Webhooks','deliveries'=>'Entregas de eventos','requests'=>'Registro de llamadas'] as $key=>$label)
        <a href="{{ route('portal.integrations',['section'=>$key]) }}" @if(($section ?? null)===$key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a href="{{ route('portal.integrations.identity') }}" @if(request()->routeIs('portal.integrations.identity')) aria-current="page" @endif>Identidad y crédito</a>
</nav>
