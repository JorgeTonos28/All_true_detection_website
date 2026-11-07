<form method="post" action="{{ route('portal.invoices.issue',$order) }}" class="stack quote-form">
    @csrf
    @foreach($quoteLines as $key=>$line)
        <label class="quote-line">
            <strong>{{ $line['option_name'] ?? $line['service_name'] }}</strong>
            @if($line['option_name'])<small>{{ $line['service_name'] }}</small>@endif
            <small>Precio unitario ({{ config('portal.currency') }}) · {{ $line['quantity'] }} {{ $line['quantity'] === 1 ? 'unidad' : 'unidades' }}</small>
            <input type="number" name="prices[{{ $key }}]" value="{{ old('prices.'.$key,$line['suggested_price'] !== null ? \App\Support\Money::decimal($line['suggested_price']) : '') }}" min="0.01" max="1000000" step="0.01" required>
            <small>{{ $line['suggested_price'] !== null ? 'Precio sugerido, puede ajustarlo.' : ($line['has_options'] && !$line['option_code'] ? 'Modalidad sin especificar. Ingrese el precio acordado.' : 'Ingrese el precio para esta partida.') }}</small>
        </label>
    @endforeach
    <label>Impuesto (%)<input type="number" name="tax_percent" value="{{ old('tax_percent',0) }}" min="0" max="100" step="0.01" required><small>Indique el porcentaje aplicable a esta factura.</small></label>
    <label>Vencimiento (opcional)<input type="date" name="due_date" value="{{ old('due_date') }}" min="{{ now()->format('Y-m-d') }}"></label>
    <p class="muted">Revise los importes antes de confirmar. La factura fija las partidas y envía al cliente el aviso de cotización. Documento comercial sin NCF.</p>
    <button @disabled($verificationStatus && !$verificationStatus['ready_for_review'])>Confirmar cotización y emitir factura</button>
</form>
