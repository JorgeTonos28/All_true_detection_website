@extends('portal.layout')
@section('title','Notificaciones')
@section('subtitle','Sus pendientes y las novedades de sus solicitudes, mensajes y facturas.')
@section('actions')<form method="post" action="{{ route('portal.notifications.read-all') }}">@csrf<button class="secondary">Marcar todas como atendidas</button></form>@endsection
@section('content')
<section class="panel"><div class="actions"><a href="{{ route('portal.notifications.index') }}">Todas</a><a href="{{ route('portal.notifications.index',['unread'=>1]) }}">Sin atender</a></div><div class="notification-list">
@forelse($notifications as $notice)<article class="notification-card {{ $notice->read_at ? '' : 'notification-unread' }}"><div><span class="badge">{{ $notice->read_at ? 'Atendida' : 'Nueva' }}</span><h3>{{ $notice->data['title'] ?? 'Notificación ATD' }}</h3><p>{{ $notice->data['copy'] ?? '' }}</p>@if($notice->data['reference'] ?? null)<small class="reference">{{ $notice->data['reference'] }}</small>@endif<small>{{ $notice->created_at->timezone('America/Santo_Domingo')->format('d/m/Y H:i') }}</small></div><form method="post" action="{{ route('portal.notifications.open',$notice->id) }}">@csrf<button class="secondary">Abrir detalle →</button></form></article>@empty<div class="empty-state"><h3>Está al día</h3><p>No hay notificaciones pendientes para mostrar.</p></div>@endforelse
</div>@include('portal.pagination',['paginator'=>$notifications])</section>
@endsection
