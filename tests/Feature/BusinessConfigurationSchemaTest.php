<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\BusinessBankAccount;
use App\Models\CustomerAccount;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessConfigurationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_accounts_and_prices_remain_unclassified_until_confirmed(): void
    {
        $account = CustomerAccount::create(['name' => 'Cuenta anterior', 'billing_email' => 'legacy@example.test']);
        $this->assertNull($account->fresh()->account_type);
        $account->update(['account_type' => AccountType::Business]);
        $this->assertSame(AccountType::Business, $account->fresh()->account_type);
        $service = Service::create(['code' => 'schema-test', 'name' => 'Prueba', 'description' => 'Catálogo anterior']);
        $this->assertNull($service->fresh()->default_price);
        $service->update(['default_price' => 150025, 'options' => [['code' => 'routine', 'name' => 'Rutina']]]);
        $this->assertSame(150025, $service->fresh()->default_price);
        $this->assertSame('routine', $service->fresh()->options[0]['code']);
    }

    public function test_bank_numbers_keep_leading_zeroes_and_are_encrypted_and_hidden(): void
    {
        $bank = BusinessBankAccount::factory()->create();
        $this->assertSame('000123456789', $bank->fresh()->account_number);
        $this->assertNotSame('000123456789', DB::table('business_bank_accounts')->value('account_number'));
        $this->assertArrayNotHasKey('account_number', $bank->fresh()->toArray());
        $this->assertSame('000123456789', $bank->fresh()->paymentInstructions()['account_number']);
    }

    public function test_database_rejects_unknown_account_types(): void
    {
        $this->expectException(QueryException::class);
        DB::table('customer_accounts')->insert(['public_id' => (string) Str::ulid(), 'name' => 'Invalid', 'account_type' => 'administrator']);
    }
}
