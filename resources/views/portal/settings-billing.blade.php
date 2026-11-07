<section class="panel stack">
    <div><h2>Precios de servicios</h2><p class="muted">Importes unitarios en {{ config('portal.currency') }}. Defina una tarifa para cada modalidad de poligrafía y perfiles. Al cotizar podrá confirmar o ajustar los montos.</p></div>
    <input type="hidden" name="catalog_pricing_enabled" value="0">
    <label class="identity-switch"><input type="checkbox" role="switch" name="catalog_pricing_enabled" value="1" @checked(old('catalog_pricing_enabled',$pricingEnabled))><span><strong>Usar precios configurados al cotizar</strong><small>Los servicios sin precio se cotizan manualmente. Apagar conserva los precios guardados.</small></span></label>
    <div class="table-wrap"><table class="catalog-price-table">
        <thead><tr><th>Servicio o modalidad</th><th>Precio unitario ({{ config('portal.currency') }})</th></tr></thead>
        @foreach($services->groupBy('category') as $category=>$categoryServices)
            <tbody>
                <tr class="price-category"><th colspan="2" scope="rowgroup">{{ \App\Support\ServiceCatalog::categories()[$category] ?? 'Otros servicios' }}</th></tr>
                @foreach($categoryServices as $service)
                    @if($service->options)
                        <tr class="price-family"><td colspan="2"><strong>{{ $service->name }}</strong><small>Precio por modalidad</small></td></tr>
                        @foreach($service->options as $option)
                            @php($amount = $service->optionPrices->firstWhere('option_code',$option['code'])?->unit_price)
                            <tr class="price-option"><td>{{ $option['name'] }}</td><td>
                                <input aria-label="Precio de {{ $service->name }}: {{ $option['name'] }}" type="number" name="service_option_prices[{{ $service->id }}][{{ $option['code'] }}]" value="{{ old('service_option_prices.'.$service->id.'.'.$option['code'],$amount !== null ? \App\Support\Money::decimal($amount) : '') }}" min="0.01" max="1000000" step="0.01" placeholder="Cotización manual">
                            </td></tr>
                        @endforeach
                    @else
                        <tr><td>{{ $service->name }}</td><td><input aria-label="Precio de {{ $service->name }}" type="number" name="service_prices[{{ $service->id }}]" value="{{ old('service_prices.'.$service->id,$service->default_price !== null ? \App\Support\Money::decimal($service->default_price) : '') }}" min="0.01" max="1000000" step="0.01" placeholder="Cotización manual"></td></tr>
                    @endif
                @endforeach
            </tbody>
        @endforeach
    </table></div>
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
