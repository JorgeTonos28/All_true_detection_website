@extends('portal.layout')
@section('title',auth()->user()->isStaff() ? 'Centro de operaciones' : 'Su portal de servicios')
@section('subtitle',auth()->user()->isStaff() ? 'Solicitudes, conversaciones y entregas bajo control.' : 'Todo el seguimiento de sus solicitudes, en un solo lugar.')
@section('actions')@unless(auth()->user()->isStaff())<a class="button" href="{{ route('portal.orders.create') }}">+ Nueva solicitud</a>@endunless @endsection
@section('content')
<div class="metrics">
    <section><span>Total de solicitudes</span><strong>{{ $counts->sum() }}</strong></section>
    <section><span>En atención</span><strong>{{ $counts->except(['completed','cancelled'])->sum() }}</strong></section>
    <section><span>Completadas</span><strong>{{ $counts['completed'] ?? 0 }}</strong></section>
    <section><span>Conversaciones abiertas</span><strong>{{ $conversationCount }}</strong></section>
</div>
<section class="panel"><div class="panel-heading"><h2>Solicitudes recientes</h2><a href="{{ route('portal.orders.index') }}">Ver todas →</a></div>@include('portal.orders.table')</section>
<div class="info-strip"><strong>Entregas protegidas</strong><p>Los resultados se habilitan cuando el trabajo está completado, el pago ha sido confirmado y ATD publica la entrega.</p></div>
@endsection
