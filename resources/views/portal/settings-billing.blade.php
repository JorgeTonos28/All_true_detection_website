<section class="panel stack">
    <div><h2>Precios de servicios</h2><p class="muted">Importes unitarios en {{ config('portal.currency') }}. Al cotizar podrá confirmarlos o ajustarlos para ese caso.</p></div>
    <input type="hidden" name="catalog_pricing_enabled" value="0">
    <label class="identity-switch"><input type="checkbox" role="switch" name="catalog_pricing_enabled" value="1" @checked(old('catalog_pricing_enabled',$pricingEnabled))><span><strong>Usar precios configurados al cotizar</strong><small>Los servicios sin precio se cotizan manualmente. Apagar conserva los precios guardados.</small></span></label>
    <div class="table-wrap"><table><thead><tr><th>Servicio</th><th>Precio unitario ({{ config('portal.currency') }})</th></tr></thead><tbody>
    @foreach($services as $service)<tr><td>{{ $service->name }}</td><td><input aria-label="Precio de {{ $service->name }}" type="number" name="service_prices[{{ $service->id }}]" value="{{ old('service_prices.'.$service->id,$service->default_price !== null ? \App\Support\Money::decimal($service->default_price) : '') }}" min="0.01" max="1000000" step="0.01" placeholder="Cotización manual"></td></tr>@endforeach
    </tbody></table></div>
</section>
<section class="panel stack" data-bank-editor>
    <div><h2>Cuentas para transferencias y depósitos</h2><p class="muted">Se muestran en la solicitud, factura y API de facturación cuando hay saldo pendiente. Cada factura muestra las cuentas activas de su moneda.</p></div>
    <input type="hidden" name="bank_accounts_present" value="1">
    @php($bankRows = old('bank_accounts',$bankAccounts->map(fn($account)=>['id'=>$account->id,'bank_name'=>$account->bank_name,'account_type'=>$account->account_type,'account_holder'=>$account->account_holder,'account_number'=>$account->account_number,'currency'=>$account->currency,'is_active'=>$account->is_active])->all()))
    <div class="stack" data-bank-rows>@foreach($bankRows as $index=>$bank)@include('portal.settings-bank-row')@endforeach</div>
    <p class="muted" data-bank-empty @if(count($bankRows)) hidden @endif>No hay cuentas configuradas. Agregue una para indicar al cliente dónde pagar.</p>
    <div><button type="button" class="secondary" data-add-bank>+ Agregar cuenta bancaria</button></div><p class="muted" role="status" data-bank-feedback>Hasta 20 cuentas. Retirar una cuenta la desactiva para futuros pagos.</p>
    <template data-bank-template>@include('portal.settings-bank-row',['index'=>'__INDEX__','bank'=>[]])</template>
</section>
