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
        $service = Service::where('code', 'training-equipment')->sole();
        $other = Service::where('code', 'drug-screening')->sole();
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
        $service = Service::where('code', 'training-equipment')->sole();
        try {
            app(BusinessBillingSettings::class)->save(User::factory()->create(['role' => Role::Admin]),
                ['catalog_pricing_enabled' => true, 'service_prices' => [$service->id => '10.25'], 'bank_accounts' => [['account_number' => '<script>invalid</script>']]]);
            $this->fail('Invalid instructions must fail atomically.');
        } catch (ValidationException) {
            $this->assertNull($service->fresh()->default_price);
            $this->assertDatabaseCount('business_bank_accounts', 0);
        }
    }

    public function test_admin_can_save_and_render_the_separate_billing_tab(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get('/portal/settings?tab=billing')->assertOk()->assertSee('Precios de servicios')->assertSee('Agregar cuenta bancaria')->assertDontSee('name="hero_title"', false);
        $this->put('/portal/settings?tab=billing', ['section' => 'billing', 'catalog_pricing_enabled' => '1', 'bank_accounts_present' => '1', 'bank_accounts' => [
            ['bank_name' => 'Banco de prueba', 'account_type' => 'checking', 'account_holder' => 'ATD Ficticia', 'account_number' => '000123456', 'currency' => 'DOP', 'is_active' => '1'],
        ]])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/portal/settings?tab=billing')->assertOk()->assertSee('000123456');
        $this->assertDatabaseCount('business_bank_accounts', 1);
    }

    public function test_admin_sets_leaf_prices_without_copying_the_former_family_price(): void
    {
        $this->seed();
        $polygraph = Service::where('code', 'polygraph')->sole();
        $polygraph->update(['default_price' => 990000]);
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $url = '/portal/settings?tab=billing';
        $this->get($url)->assertOk()->assertSee('Nuevo ingreso')->assertSee('Prueba específica')
            ->assertDontSee('name="service_prices['.$polygraph->id.']"', false)->assertDontSee('9900.00');
        $form = ['section' => 'billing', 'catalog_pricing_enabled' => '1'];
        $this->put($url, $form + ['service_option_prices' => [$polygraph->id => ['pre_employment' => '1250.75', 'routine' => '1600.00', 'specific' => null]]])->assertSessionHasNoErrors();
        $this->assertSame(125075, BusinessBillingSettings::suggestedPrice($polygraph->fresh(), 'pre_employment'));
        $this->assertSame(160000, BusinessBillingSettings::suggestedPrice($polygraph->fresh(), 'routine'));
        $this->assertNull(BusinessBillingSettings::suggestedPrice($polygraph->fresh(), 'specific'));
        $this->assertNull(BusinessBillingSettings::suggestedPrice($polygraph->fresh()));
        $this->put($url, $form + ['service_option_prices' => [$polygraph->id => ['investigative' => '100.00', 'routine' => '2.00']]])->assertSessionHasErrors('service_option_prices');
        $this->put($url, $form + ['service_prices' => [$polygraph->id => '10.00']])->assertSessionHasErrors('service_prices');
        $this->assertSame(160000, BusinessBillingSettings::suggestedPrice($polygraph->fresh(), 'routine'));
        $this->assertSame(990000, $polygraph->fresh()->default_price);
        $this->put($url, ['section' => 'billing', 'catalog_pricing_enabled' => '0'])->assertSessionHasNoErrors();
        $this->assertNull(BusinessBillingSettings::suggestedPrice($polygraph->fresh(), 'routine'));
        $this->assertSame(160000, $polygraph->optionPrices()->where('option_code', 'routine')->sole()->unit_price);
    }
}
