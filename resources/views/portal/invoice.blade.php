@extends('portal.layout')
@section('title','Factura comercial')
@section('subtitle',$invoice->number)
@section('content')
<article class="panel invoice">
<div class="two-columns"><section><h2>{{ $invoice->issuer['company_name'] ?? 'All True Detection' }}</h2><p>{{ $invoice->issuer['address'] ?? '' }}<br>{{ $invoice->issuer['email'] ?? '' }}<br>{{ $invoice->issuer['phone'] ?? '' }}</p></section><section><h2>Facturado a</h2><p>{{ $invoice->recipient['name'] }}<br>{{ $invoice->recipient['billing_email'] }}<br>{{ $invoice->recipient['tax_id'] ?? '' }}<br>{{ $invoice->recipient['billing_address'] ?? '' }}</p></section></div>
<p><strong>Emisión:</strong> {{ $invoice->issued_at->format('d/m/Y') }} @if($invoice->due_date) · <strong>Vencimiento:</strong> {{ $invoice->due_date->format('d/m/Y') }}@endif · {{ $invoice->currency }}</p>
<div class="table-wrap"><table><thead><tr><th>Servicio</th><th>Cantidad</th><th>Precio unitario</th><th>Importe</th></tr></thead><tbody>@foreach($invoice->order->items as $item)<tr><td>{{ $item->service_name }}</td><td>{{ $item->quantity }}</td><td>{{ \App\Support\Money::format($item->unit_price,$invoice->currency) }}</td><td>{{ \App\Support\Money::format($item->unit_price*$item->quantity,$invoice->currency) }}</td></tr>@endforeach</tbody></table></div>
<dl class="amounts"><dt>Subtotal</dt><dd>{{ \App\Support\Money::format($invoice->subtotal,$invoice->currency) }}</dd><dt>Impuestos ({{ $invoice->tax_basis_points / 100 }} %)</dt><dd>{{ \App\Support\Money::format($invoice->tax,$invoice->currency) }}</dd><dt>Total</dt><dd><strong>{{ \App\Support\Money::format($invoice->total,$invoice->currency) }}</strong></dd><dt>Saldo pendiente</dt><dd>{{ \App\Support\Money::format($invoice->outstanding(),$invoice->currency) }}</dd></dl>
<p><span class="badge">{{ $invoice->status === 'paid' ? 'Pago confirmado' : 'Pendiente de pago' }}</span></p><p class="muted">Factura comercial. No es un comprobante fiscal ni incluye NCF. El pago se confirma manualmente por ATD. Los resultados se publican después del pago total.</p><p class="print-hidden">Puede usar la opción Imprimir de su navegador para guardar esta factura en PDF.</p><a class="print-hidden" href="{{ route('portal.orders.show',$invoice->order) }}">← Volver a la solicitud</a>
</article>
@endsection
