@extends('portal.layout')
@section('title','Detalle de evento')
@section('subtitle','Historial de recepción y reintentos. Fechas UTC; sin contenido del expediente.')
@section('content')
<section class="panel"><div class="panel-heading"><div><h2>{{ $delivery->event_type }}</h2><code>{{ $delivery->event_id }}</code></div><span class="badge">{{ $delivery->delivered_at ? 'Entregado' : ($delivery->failed_at ? 'Agotado' : 'Pendiente') }}</span></div>
<dl class="member-facts"><div><dt>Destino</dt><dd>{{ parse_url($delivery->endpoint->url,PHP_URL_HOST) }}</dd></div><div><dt>Referencia</dt><dd>{{ $delivery->order?->public_id ?? 'Prueba sin expediente' }}</dd></div><div><dt>Próximo intento</dt><dd>{{ $delivery->next_attempt_at?->format('d/m/Y H:i:s') ?? 'No programado' }}</dd></div><div><dt>Reenvíos manuales</dt><dd>{{ $delivery->replay_count }}</dd></div></dl>
@if($delivery->failed_at && $delivery->endpoint->is_active)<form method="post" action="{{ route('portal.integrations.replay',$delivery) }}">@csrf<p>Corrija la causa en su receptor antes de reencolar. El evento conserva su identificador: el receptor debe deduplicar.</p><button>Reencolar evento</button></form>@endif
@unless($delivery->endpoint->is_active)<div class="alert">El endpoint está desactivado. No se permiten reenvíos.</div>@endunless
@if($delivery->order)<p><a href="{{ route('portal.orders.show',$delivery->order) }}">Abrir solicitud →</a></p>@endif
</section>
<section class="panel"><h2>Historial de intentos</h2><div class="table-wrap"><table><thead><tr><th>Fecha UTC</th><th>HTTP</th><th>Duración</th><th>Diagnóstico</th></tr></thead><tbody>@forelse($attempts as $attempt)<tr><td>{{ $attempt->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $attempt->response_status ?? 'Sin respuesta' }}</td><td>{{ $attempt->duration_ms }} ms</td><td>{{ match($attempt->error_code) { 'http_error'=>'El receptor respondió fuera de 2xx', 'transport_or_policy'=>'Fallo de red, timeout o destino no autorizado', default=>'Recepción confirmada' } }}</td></tr>@empty<tr><td colspan="4">Sin intentos registrados. El scheduler debe despachar el evento y el worker procesarlo. Los intentos anteriores a esta actualización no tienen desglose.</td></tr>@endforelse</tbody></table></div>{{ $attempts->links('portal.pagination') }}</section>
@endsection
