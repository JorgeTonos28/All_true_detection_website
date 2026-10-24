@if($orders->isEmpty())
<div class="empty-state"><h3>No hay solicitudes para mostrar</h3><p>Revise los filtros o cree su primera solicitud con los servicios y documentos que necesita.</p>@unless(auth()->user()->isStaff())<a class="button" href="{{ route('portal.orders.create') }}">Solicitar un servicio</a>@endunless</div>
@else
<div class="table-wrap"><table><thead><tr><th>Solicitud</th>@if(auth()->user()->isStaff())<th>Cliente / responsable</th>@endif<th>Estado</th><th>Facturación</th><th>Recibida</th><th>Detalle</th></tr></thead><tbody>
@foreach($orders as $order)
    <tr><td><a class="row-title" href="{{ route('portal.orders.show',$order) }}">{{ $order->subject }}</a><small class="reference">{{ $order->public_id }}</small>@if($order->relationLoaded('items'))<small>{{ $order->items->pluck('service_name')->join(' · ') }}</small>@endif @if(isset($order->attachments_count))<small>{{ $order->attachments_count }} documentos</small>@endif</td>
    @if(auth()->user()->isStaff())<td>{{ $order->account->name }}<small>{{ $order->assignee?->name ?? 'Sin asignar' }}</small></td>@endif
    <td><span class="badge status-{{ $order->status->value }}">{{ $order->status->label() }}</span></td>
    <td>@if($order->invoice)<span class="badge {{ $order->invoice->status === 'paid' ? 'status-completed' : 'status-quoted' }}">{{ $order->invoice->status === 'paid' ? 'Pagada' : 'Pago pendiente' }}</span>@else<span class="muted">Por cotizar</span>@endif</td>
    <td>{{ $order->created_at->timezone('America/Santo_Domingo')->format('d/m/Y') }}</td><td><a class="button secondary" href="{{ route('portal.orders.show',$order) }}">Ver caso</a></td></tr>
@endforeach
</tbody></table></div>
@endif
