<section class="payment-instructions" aria-label="Cuentas para transferencia o depósito">
<h3>Transferencia o depósito</h3>
@forelse($invoice->paymentAccounts() as $paymentAccount)
<article class="payment-account"><strong>{{ $paymentAccount['bank_name'] }}</strong><dl>
<dt>Tipo</dt><dd>{{ $paymentAccount['account_type'] === 'savings' ? 'Ahorros' : 'Corriente' }} · {{ $paymentAccount['currency'] }}</dd>
<dt>Titular</dt><dd>{{ $paymentAccount['account_holder'] }}</dd>
<dt>Número de cuenta</dt><dd><code>{{ $paymentAccount['account_number'] }}</code></dd></dl></article>
@empty<p>Consulte con ATD en la conversación de su solicitud para coordinar la forma de pago.</p>@endforelse
<p><strong>Referencia:</strong> {{ $invoice->number }}</p><small>Indique esta referencia al pagar y comparta el comprobante en la conversación. ATD confirma manualmente los fondos recibidos.</small>
</section>
