@extends('portal.layout')
@section('title',auth()->user()->isStaff() ? 'Centro de operaciones' : 'Su portal de servicios')
@section('subtitle',auth()->user()->isStaff() ? 'Solicitudes, conversaciones y entregas bajo control.' : 'Todo el seguimiento de sus solicitudes, en un solo lugar.')
@section('actions')
<button type="button" class="secondary" data-open-summary-filters aria-haspopup="dialog" aria-controls="summary-filters">{{ $filtered ? 'Filtros activos' : 'Filtros' }}</button>
@unless(auth()->user()->isStaff())<a class="button" href="{{ route('portal.orders.create') }}">+ Nueva solicitud</a>@endunless
@endsection
@section('content')
<dialog class="portal-dialog summary-filter-dialog" id="summary-filters" aria-labelledby="summary-filters-title" @if($errors->hasAny(['q','status','billing','mine','from','to'])) data-filter-errors @endif>
    <div class="dialog-heading"><div><h2 id="summary-filters-title">Filtrar resumen</h2><p class="muted">Los filtros se aplican a las tarjetas y a las solicitudes de esta vista.</p></div><button class="secondary" type="button" data-close-summary-filters>Cerrar</button></div>
    @if($errors->hasAny(['q','status','billing','mine','from','to']))<p class="alert" role="alert">{{ $errors->first() }}</p>@endif
    @include('portal.orders.filters',['filterRoute'=>'portal.dashboard'])
    <p class="muted search-help">El rango usa la fecha de recepción en {{ config('portal.display_timezone') }}.</p>
</dialog>
<noscript><a href="{{ route('portal.orders.index') }}">Filtrar en Solicitudes</a></noscript>
<div class="metrics metrics-expanded {{ auth()->user()->isStaff() ? '' : 'metrics-client' }}">
    <section><span>Total de solicitudes</span><strong>{{ $counts->sum() }}</strong></section>
    <section><span>En atención</span><strong>{{ $counts->except(['completed','cancelled'])->sum() }}</strong></section>
    <section><span>Completadas</span><strong>{{ $counts['completed'] ?? 0 }}</strong></section>
    <section><span>{{ $filtered ? 'Conversaciones de estas solicitudes' : 'Conversaciones abiertas' }}</span><strong>{{ $conversationCount }}</strong></section>
    @if(auth()->user()->isStaff())<section><span>Total cobrado</span>@foreach($money as $currency=>$totals)<strong class="money-value currency-line">{{ \App\Support\Money::format($totals['collected'],$currency) }}</strong>@endforeach<small>Pagos confirmados por ATD</small></section>@endif
    <section><span>{{ auth()->user()->isStaff() ? 'Por cobrar' : 'Saldo pendiente' }}</span>@foreach($money as $currency=>$totals)<strong class="money-value currency-line">{{ \App\Support\Money::format($totals['outstanding'],$currency) }}</strong>@endforeach<small>Saldo de facturas emitidas</small></section>
</div>
<p class="muted search-help">Los importes corresponden a las solicitudes que cumplen los filtros. Las monedas se presentan por separado.</p>
<section class="panel"><div class="panel-heading"><h2>{{ $filtered ? 'Solicitudes filtradas' : 'Solicitudes recientes' }}</h2><a href="{{ route('portal.orders.index',request()->only(['q','status','billing','from','to','mine'])) }}">Ver todas →</a></div>@include('portal.orders.table')</section>
<div class="info-strip"><strong>Entregas protegidas</strong><p>Los informes se habilitan al completar el trabajo, confirmar el pago y publicar la entrega. Solo la conclusión técnica de identidad API a crédito se devuelve automáticamente a los partners autorizados.</p></div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/summary-filters.js') }}?v={{ filemtime(public_path('assets/js/summary-filters.js')) }}" defer></script>@endpush
