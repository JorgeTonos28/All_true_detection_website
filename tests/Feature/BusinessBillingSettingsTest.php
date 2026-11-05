<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BusinessBankAccount;
use App\Models\Service;
use App\Models\User;
use App\Services\BusinessBillingSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessBillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prices_are_optional_preserved_when_disabled_and_bank_removals_are_deactivated(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $service = Service::firstOrFail();
        $other = Service::whereKeyNot($service->id)->firstOrFail();
        $bank = BusinessBankAccount::factory()->create();
        $usd = BusinessBankAccount::factory()->create(['currency' => 'USD']);
        $settings = app(BusinessBillingSettings::class);
        $settings->save($admin, ['catalog_pricing_enabled' => true, 'service_prices' => [$service->id => '1250.75', $other->id => null]]);
        $this->assertSame(125075, BusinessBillingSettings::suggestedPrice($service->fresh()));
        $this->assertNull(BusinessBillingSettings::suggestedPrice($other->fresh()));
        $this->assertCount(1, BusinessBillingSettings::paymentAccounts('DOP'));
        $this->assertSame('000123456789', BusinessBillingSettings::paymentAccounts('DOP')[0]['account_number']);
        $settings->save($admin, ['catalog_pricing_enabled' => false, 'bank_accounts' => []]);
        $this->assertNull(BusinessBillingSettings::suggestedPrice($service->fresh()));
        $this->assertSame(125075, $service->fresh()->default_price);
        $this->assertFalse($bank->fresh()->is_active);
        $this->assertFalse($usd->fresh()->is_active);
    }

    public function test_clients_cannot_set_prices_or_bank_instructions(): void
    {
        $this->seed();
        $client = User::factory()->create(['role' => Role::Client]);
        $this->expectException(AuthorizationException::class);
        app(BusinessBillingSettings::class)->save($client, ['catalog_pricing_enabled' => true]);
    }

    public function test_invalid_bank_data_prevents_partial_price_updates(): void
    {
        $this->seed();
        $service = Service::firstOrFail();
        try {
            app(BusinessBillingSettings::class)->save(User::factory()->create(['role' => Role::Admin]),
                ['catalog_pricing_enabled' => true, 'service_prices' => [$service->id => '10.25'], 'bank_accounts' => [['account_number' => '<script>invalid</script>']]]);
            $this->fail('Invalid instructions must fail atomically.');
        } catch (ValidationException) {
            $this->assertNull($service->fresh()->default_price);
            $this->assertDatabaseCount('business_bank_accounts', 0);
        }
    }
}
