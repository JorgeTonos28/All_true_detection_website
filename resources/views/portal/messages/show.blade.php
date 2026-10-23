@extends('portal.layout')
@section('title',$conversation->subject)
@section('subtitle','Conversación privada · '.($conversation->status === 'open' ? 'Abierta' : 'Cerrada'))
@section('actions')@if($conversation->order)<a class="button secondary" href="{{ route('portal.orders.show',$conversation->order) }}">Ver solicitud</a>@endif @endsection
@section('content')
<section class="panel conversation">
@if(auth()->user()->isStaff())<p><strong>{{ $conversation->contact_name }}</strong> · {{ $conversation->contact_email }}</p>@endif
<p class="muted">Los avisos se envían por correo. Responda desde este portal para conservar el historial. Los resultados finales se publican en la solicitud.</p>
@if($conversation->status === 'open')<form method="post" action="{{ route('portal.messages.reply',$conversation) }}" class="stack message-compose">@csrf<label>Escribir respuesta<textarea name="body" maxlength="10000" rows="4" required>{{ old('body') }}</textarea></label><button>Enviar respuesta</button></form>@endif
<div class="message-history">@forelse($messages as $message)<article class="message {{ $message->sender?->isStaff() ? 'message-staff' : '' }}"><header><strong>{{ $message->sender?->isStaff() ? 'Equipo ATD' : $conversation->contact_name }}</strong><time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone('America/Santo_Domingo')->format('d/m/Y H:i') }}</time></header><p class="preserve">{{ $message->body }}</p></article>@empty<p>No hay mensajes todavía.</p>@endforelse</div>
@include('portal.pagination',['paginator'=>$messages])
@if(auth()->user()->isAdmin())<form method="post" action="{{ route('portal.messages.status',$conversation) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $conversation->status === 'open' ? 'closed' : 'open' }}"><button class="secondary">{{ $conversation->status === 'open' ? 'Cerrar conversación' : 'Reabrir conversación' }}</button></form>@endif
</section>
@endsection
