@extends('portal.layout')
@section('title','Mensajes')
@section('subtitle','Conversaciones con historial y avisos por correo.')
@section('content')
<div class="two-columns"><section class="panel"><h2>Conversaciones</h2><div class="thread-list">@forelse($conversations as $conversation)<a href="{{ route('portal.messages.show',$conversation) }}"><div><strong>{{ $conversation->subject }}</strong><small>{{ auth()->user()->isStaff() ? $conversation->contact_name : ($conversation->order_id ? 'Solicitud de servicio' : 'Consulta general') }} · {{ $conversation->updated_at->format('d/m/Y H:i') }}</small></div><span class="badge">{{ $conversation->status === 'open' ? 'Abierta' : 'Cerrada' }} · {{ $conversation->messages_count }}</span></a>@empty<div class="empty-state"><h3>Aún no hay conversaciones</h3><p>Aquí aparecerán sus consultas y el seguimiento de sus solicitudes.</p></div>@endforelse</div>@include('portal.pagination',['paginator'=>$conversations])</section>
@unless(auth()->user()->isStaff())<section class="panel"><h2>Nueva consulta</h2><form method="post" action="{{ route('portal.messages.store') }}" class="stack">@csrf<label>Asunto<input name="subject" value="{{ old('subject') }}" maxlength="200" required></label><label>Mensaje<textarea name="body" minlength="5" maxlength="10000" rows="6" required>{{ old('body') }}</textarea></label><button>Enviar mensaje</button></form></section>@endunless
</div>
@endsection
