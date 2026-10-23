@extends('portal.layout')
@section('title','Solicitudes')
@section('subtitle','Consulte el avance, los documentos y las entregas de cada caso.')
@section('actions')@unless(auth()->user()->isStaff())<a class="button" href="{{ route('portal.orders.create') }}">+ Nueva solicitud</a>@endunless @endsection
@section('content')
<section class="panel"><form method="get" class="filters"><label>Buscar<input name="q" value="{{ request('q') }}" maxlength="100" placeholder="Asunto, referencia o identificador"></label><label>Estado<select name="status"><option value="">Todos</option>@foreach($statuses as $state)<option value="{{ $state->value }}" @selected(request('status') === $state->value)>{{ $state->label() }}</option>@endforeach</select></label><button>Filtrar</button>@if(request()->hasAny(['q','status']))<a href="{{ route('portal.orders.index') }}">Limpiar</a>@endif</form>@include('portal.orders.table')@include('portal.pagination',['paginator'=>$orders])</section>
@endsection
