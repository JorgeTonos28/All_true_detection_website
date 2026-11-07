@extends('portal.layout')
@section('title',auth()->user()->isStaff() ? 'Gestión de solicitudes' : 'Mis solicitudes')
@section('subtitle',auth()->user()->isStaff() ? 'Organice los casos, su asignación y los pendientes de facturación.' : 'Todo el seguimiento de sus servicios, documentos y entregas en un lugar.')
@section('actions')@unless(auth()->user()->isStaff())<a class="button" href="{{ route('portal.orders.create') }}">+ Nueva solicitud</a>@endunless @endsection
@section('content')
<div class="metrics"><section><span>Total de solicitudes</span><strong>{{ $summary['total'] }}</strong></section><section><span>En atención</span><strong>{{ $summary['working'] }}</strong></section><section><span>Pago pendiente</span><strong>{{ $summary['pending'] }}</strong></section><section><span>Trabajo completado</span><strong>{{ $summary['completed'] }}</strong></section></div>
<section class="panel"><div class="panel-heading"><h2>{{ auth()->user()->isStaff() ? 'Bandeja de trabajo' : 'Seguimiento de sus casos' }}</h2></div>
@include('portal.orders.filters',['filterRoute'=>'portal.orders.index'])
@include('portal.orders.table')@include('portal.pagination',['paginator'=>$orders])</section>
@endsection
