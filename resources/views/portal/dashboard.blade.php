@extends('portal.layout')
@section('title',auth()->user()->isStaff() ? 'Centro de operaciones' : 'Su portal de servicios')
@section('subtitle',auth()->user()->isStaff() ? 'Solicitudes, conversaciones y entregas bajo control.' : 'Todo el seguimiento de sus solicitudes, en un solo lugar.')
@section('actions')@unless(auth()->user()->isStaff())<a class="button" href="{{ route('portal.orders.create') }}">+ Nueva solicitud</a>@endunless @endsection
@section('content')
<section class="panel">@include('portal.orders.filters',['filterRoute'=>'portal.dashboard'])<p class="muted search-help">El rango corresponde a la fecha de recepción de las solicitudes, en {{ config('portal.display_timezone') }}.</p></section>
<div class="metrics metrics-expanded">
    <section><span>Total de solicitudes</span><strong>{{ $counts->sum() }}</strong></section>
    <section><span>En atención</span><strong>{{ $counts->except(['completed','cancelled'])->sum() }}</strong></section>
    <section><span>Completadas</span><strong>{{ $counts['completed'] ?? 0 }}</strong></section>
    <section><span>{{ $filtered ? 'Conversaciones de estas solicitudes' : 'Conversaciones abiertas' }}</span><strong>{{ $conversationCount }}</strong></section>
    <section><span>{{ auth()->user()->isStaff() ? 'Total cobrado' : 'Total pagado' }}</span>@foreach($money as $currency=>$totals)<strong class="money-value currency-line">{{ \App\Support\Money::format($totals['collected'],$currency) }}</strong>@endforeach<small>Pagos confirmados por ATD</small></section>
    <section><span>{{ auth()->user()->isStaff() ? 'Por cobrar' : 'Saldo pendiente' }}</span>@foreach($money as $currency=>$totals)<strong class="money-value currency-line">{{ \App\Support\Money::format($totals['outstanding'],$currency) }}</strong>@endforeach<small>Saldo de facturas emitidas</small></section>
</div>
<p class="muted search-help">Los importes corresponden a las solicitudes que cumplen los filtros. Las monedas se presentan por separado.</p>
<section class="panel"><div class="panel-heading"><h2>{{ $filtered ? 'Solicitudes filtradas' : 'Solicitudes recientes' }}</h2><a href="{{ route('portal.orders.index',request()->only(['q','status','billing','from','to','mine'])) }}">Ver todas →</a></div>@include('portal.orders.table')</section>
<div class="info-strip"><strong>Entregas protegidas</strong><p>Los informes se habilitan al completar el trabajo, confirmar el pago y publicar la entrega. Solo la conclusión técnica de identidad API a crédito se devuelve automáticamente a los partners autorizados.</p></div>
@endsection
