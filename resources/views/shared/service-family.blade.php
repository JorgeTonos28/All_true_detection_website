<details class="service-family" @if($expanded) open @endif>
    <summary><span>{{ $familyName }}</span><small>{{ $familyServices->count() }} {{ $familyServices->count()===1 ? 'servicio' : 'servicios' }}</small><span class="family-toggle" aria-hidden="true">+</span></summary>
    <div class="service-family-content">@foreach($familyServices as $service)
        <article class="service-entry"><div><h3>{{ $service['title'] }}</h3><p>{{ $service['text'] }}</p></div><a class="service-request" href="{{ route('services.request',['service'=>$service['code']]) }}" aria-label="Solicitar {{ $service['title'] }}">Solicitar <span aria-hidden="true">↗</span></a></article>
    @endforeach</div>
</details>
