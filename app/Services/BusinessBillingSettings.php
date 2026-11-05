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
        return Setting::where('key', 'catalog_pricing_enabled')->value('value') === '1';
    }

    public static function suggestedPrice(Service $service, ?string $option = null, ?bool $pricingEnabled = null): ?int
    {
        if (! ($pricingEnabled ?? self::pricingEnabled()) || ! $service->is_active) {
            return null;
        }
        $options = $service->optionLabels();
        if (! $options) {
            return $option === null ? $service->default_price : null;
        }

        return $option !== null && isset($options[$option]) ? $service->optionPrices->firstWhere('option_code', $option)?->unit_price : null;
    }

    public static function paymentAccounts(string $currency): array
    {
        return BusinessBankAccount::where('is_active', true)->where('currency', $currency)
            ->orderBy('sort_order')->orderBy('id')->get()->map(fn ($account) => $account->paymentInstructions())->all();
    }

    public function save(User $actor, array $input): void
    {
        Gate::forUser($actor)->authorize('manage-business');
        $priceRules = ['nullable', 'regex:/^[0-9]{1,7}(?:\.[0-9]{1,2})?$/D', 'numeric', 'min:0.01', 'max:1000000'];
        $data = Validator::make($input, [
            'catalog_pricing_enabled' => ['required', 'boolean'],
            'service_prices' => ['sometimes', 'array', 'max:50'],
            'service_prices.*' => $priceRules,
            'service_option_prices' => ['sometimes', 'array', 'max:50'],
            'service_option_prices.*' => ['array', 'min:1', 'max:12'],
            'service_option_prices.*.*' => $priceRules,
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
            if (! $services->has($id) || $services[$id]->optionLabels()) {
                throw ValidationException::withMessages(['service_prices' => 'Defina el precio de cada modalidad de una familia, o de un servicio activo sin modalidades.']);
            }
        }
        foreach ($data['service_option_prices'] ?? [] as $id => $options) {
            if (! $services->has($id) || array_diff_key($options, $services[$id]->optionLabels())) {
                throw ValidationException::withMessages(['service_option_prices' => 'Una de las modalidades no pertenece a un servicio activo. Actualice la página.']);
            }
        }
        DB::transaction(function () use ($actor, $data, $services) {
            Setting::where('key', 'company_name')->lockForUpdate()->firstOrFail();
            Setting::firstOrCreate(['key' => 'catalog_pricing_enabled'], ['label' => 'Precios de catálogo', 'group' => 'Facturación', 'type' => 'boolean', 'value' => '0']);
            Setting::put('catalog_pricing_enabled', $data['catalog_pricing_enabled'] ? '1' : '0');
            foreach ($data['service_prices'] ?? [] as $id => $amount) {
                $services[$id]->update(['default_price' => $amount === null || $amount === '' ? null : Money::parse((string) $amount)]);
            }
            foreach ($data['service_option_prices'] ?? [] as $id => $options) {
                foreach ($options as $code => $amount) {
                    if ($amount === null || $amount === '') {
                        $services[$id]->optionPrices()->where('option_code', $code)->delete();
                    } else {
                        $services[$id]->optionPrices()->updateOrCreate(['option_code' => $code], ['unit_price' => Money::parse((string) $amount)]);
                    }
                }
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
                ['pricing_enabled' => (bool) $data['catalog_pricing_enabled'], 'service_count' => count($data['service_prices'] ?? []),
                    'option_price_count' => array_sum(array_map('count', $data['service_option_prices'] ?? [])), 'bank_account_count' => count($data['bank_accounts'] ?? [])]);
        }, 3);
    }
}
