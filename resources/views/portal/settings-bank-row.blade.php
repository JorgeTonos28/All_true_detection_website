<article class="bank-editor stack" data-bank-row>
    <div class="panel-heading"><h3>Cuenta para depósitos</h3><button type="button" class="secondary" data-remove-bank>Retirar cuenta</button></div>
    @if(!empty($bank['id']))<input type="hidden" name="bank_accounts[{{ $index }}][id]" value="{{ $bank['id'] }}">@endif
    <div class="form-grid">
        <label>Banco<input name="bank_accounts[{{ $index }}][bank_name]" value="{{ $bank['bank_name'] ?? '' }}" maxlength="120" required></label>
        <label>Tipo de cuenta<select name="bank_accounts[{{ $index }}][account_type]" required><option value="savings" @selected(($bank['account_type'] ?? 'savings')==='savings')>Ahorros</option><option value="checking" @selected(($bank['account_type'] ?? '')==='checking')>Corriente</option></select></label>
        <label>Nombre del titular<input name="bank_accounts[{{ $index }}][account_holder]" value="{{ $bank['account_holder'] ?? '' }}" maxlength="200" required></label>
        <label>Número de cuenta<input name="bank_accounts[{{ $index }}][account_number]" value="{{ $bank['account_number'] ?? '' }}" maxlength="64" autocomplete="off" required><small>Se conservan los ceros iniciales.</small></label>
        <label>Moneda<select name="bank_accounts[{{ $index }}][currency]">@foreach(array_unique(['DOP','USD','EUR',config('portal.currency'),$bank['currency'] ?? config('portal.currency')]) as $currency)<option value="{{ $currency }}" @selected(($bank['currency'] ?? config('portal.currency'))===$currency)>{{ $currency }}</option>@endforeach</select></label>
        <label class="check"><input type="hidden" name="bank_accounts[{{ $index }}][is_active]" value="0"><input type="checkbox" name="bank_accounts[{{ $index }}][is_active]" value="1" @checked($bank['is_active'] ?? true)>Mostrar a clientes para recibir pagos</label>
    </div>
</article>
