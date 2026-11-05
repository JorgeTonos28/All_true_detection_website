<?php

namespace App\Services;

use App\Models\BusinessBankAccount;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BusinessBillingSettings
{
    public static function pricingEnabled(): bool
    {
        return (Setting::values()['catalog_pricing_enabled'] ?? '0') === '1';
    }

    public static function suggestedPrice(Service $service): ?int
    {
        return self::pricingEnabled() && $service->is_active ? $service->default_price : null;
    }

    public static function paymentAccounts(string $currency): array
    {
        return BusinessBankAccount::where('is_active', true)->where('currency', $currency)
            ->orderBy('sort_order')->orderBy('id')->get()->map(fn ($account) => $account->paymentInstructions())->all();
    }

    public function save(User $actor, array $input): void
    {
        Gate::forUser($actor)->authorize('manage-business');
        $data = Validator::make($input, [
            'catalog_pricing_enabled' => ['required', 'boolean'],
            'service_prices' => ['sometimes', 'array', 'max:50'],
            'service_prices.*' => ['nullable', 'regex:/^[0-9]{1,7}(?:\.[0-9]{1,2})?$/D', 'numeric', 'min:0.01', 'max:1000000'],
            'bank_accounts' => ['sometimes', 'array', 'list', 'max:20'],
            'bank_accounts.*' => ['array:id,bank_name,account_type,account_holder,account_number,currency,is_active'],
            'bank_accounts.*.id' => ['nullable', 'integer', 'distinct', 'exists:business_bank_accounts,id'],
            'bank_accounts.*.bank_name' => ['required', 'string', 'max:120'],
            'bank_accounts.*.account_type' => ['required', 'in:checking,savings'],
            'bank_accounts.*.account_holder' => ['required', 'string', 'max:200'],
            'bank_accounts.*.account_number' => ['required', 'string', 'regex:/^[A-Za-z0-9 -]{4,64}$/D'],
            'bank_accounts.*.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'],
            'bank_accounts.*.is_active' => ['required', 'boolean'],
        ])->validate();
        $services = Service::where('is_active', true)->get()->keyBy('id');
        foreach (array_keys($data['service_prices'] ?? []) as $id) {
            if (! $services->has($id)) {
                throw ValidationException::withMessages(['service_prices' => 'Uno de los servicios ya no está disponible. Actualice la página.']);
            }
        }
        DB::transaction(function () use ($actor, $data, $services) {
            Setting::where('key', 'company_name')->lockForUpdate()->firstOrFail();
            Setting::firstOrCreate(['key' => 'catalog_pricing_enabled'], ['label' => 'Precios de catálogo', 'group' => 'Facturación', 'type' => 'boolean', 'value' => '0']);
            Setting::put('catalog_pricing_enabled', $data['catalog_pricing_enabled'] ? '1' : '0');
            foreach ($data['service_prices'] ?? [] as $id => $amount) {
                $services[$id]->update(['default_price' => $amount === null || $amount === '' ? null : Money::parse((string) $amount)]);
            }
            if (array_key_exists('bank_accounts', $data)) {
                $kept = [];
                foreach ($data['bank_accounts'] as $position => $row) {
                    $account = isset($row['id']) ? BusinessBankAccount::whereKey($row['id'])->lockForUpdate()->firstOrFail() : new BusinessBankAccount;
                    $account->fill(collect($row)->except('id')->all() + ['sort_order' => $position])->save();
                    $kept[] = $account->id;
                }
                BusinessBankAccount::whereNotIn('id', $kept)->update(['is_active' => false]);
            }
            Audit::record($actor, 'business.billing_settings_updated', (new Setting)->forceFill(['id' => 0]),
                ['pricing_enabled' => (bool) $data['catalog_pricing_enabled'], 'service_count' => count($data['service_prices'] ?? []), 'bank_account_count' => count($data['bank_accounts'] ?? [])]);
        }, 3);
    }
}
