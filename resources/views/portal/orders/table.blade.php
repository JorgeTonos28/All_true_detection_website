@if($orders->isEmpty())
<div class="empty-state"><h3>Aún no hay solicitudes</h3><p>Las solicitudes aparecerán aquí con su estado y seguimiento.</p>@unless(auth()->user()->isStaff())<a class="button" href="{{ route('portal.orders.create') }}">Solicitar un servicio</a>@endunless</div>
@else
<div class="table-wrap"><table><thead><tr><th>Solicitud</th>@if(auth()->user()->isStaff())<th>Cliente</th>@endif<th>Estado</th><th>Pago</th><th>Recibida</th></tr></thead><tbody>
@foreach($orders as $order)<tr><td><a class="row-title" href="{{ route('portal.orders.show',$order) }}">{{ $order->subject }}</a><small class="reference">{{ $order->public_id }}</small></td>@if(auth()->user()->isStaff())<td>{{ $order->account->name }}</td>@endif<td><span class="badge status-{{ $order->status->value }}">{{ $order->status->label() }}</span></td><td>{{ $order->invoice ? ($order->invoice->status === 'paid' ? 'Pagada' : 'Pendiente') : 'Por cotizar' }}</td><td>{{ $order->created_at->timezone('America/Santo_Domingo')->format('d/m/Y') }}</td></tr>@endforeach
</tbody></table></div>
@endif
