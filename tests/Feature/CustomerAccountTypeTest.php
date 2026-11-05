<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\CustomerAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAccountTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_explicit_type_and_business_name(): void
    {
        Notification::fake();
        $data = ['name' => 'Persona de prueba', 'email' => 'typed@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true];
        $this->post('/register', $data)->assertSessionHasErrors('account_type');
        $this->post('/register', $data + ['account_type' => 'business'])->assertSessionHasErrors('company');
        $this->post('/register', $data + ['account_type' => 'business', 'company' => 'Empresa de prueba', 'role' => 'admin'])->assertSessionHasNoErrors();
        $user = User::where('email', 'typed@example.test')->sole();
        $this->assertSame(Role::Client, $user->role);
        $this->assertSame(AccountType::Business, $user->account->account_type);
        $this->assertSame('Empresa de prueba', $user->account->name);
        $this->assertFalse($user->account->hasIdentityApiCredit());
    }

    public function test_legacy_accounts_confirm_their_type_without_replacing_the_tenant_or_granting_credit(): void
    {
        Notification::fake();
        $this->seed();
        $account = CustomerAccount::create(['name' => 'Cuenta anterior', 'billing_email' => 'legacy-type@example.test']);
        $client = User::factory()->create(['role' => Role::Client, 'customer_account_id' => $account->id]);
        $this->actingAs($client)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get('/portal')->assertRedirect('/portal/account');
        $this->get('/portal/account')->assertOk()->assertSee('Tipo de cuenta');
        $this->put('/portal/account', ['account_type' => 'individual', 'name' => 'Titular de prueba', 'company' => 'Titular de la cuenta', 'billing_email' => 'legacy-type@example.test'])->assertSessionHasNoErrors()->assertRedirect('/portal');
        $this->get('/portal')->assertOk()->assertSee('Persona individual');
        $this->assertSame($account->id, $client->fresh()->customer_account_id);
        $this->assertSame(AccountType::Individual, $account->fresh()->account_type);
        $this->assertFalse($account->fresh()->hasIdentityApiCredit());
    }

    public function test_api_identifies_the_real_account_type_and_ignores_tenant_overrides(): void
    {
        Notification::fake();
        $this->seed();
        $client = app(CreateNewUser::class)->create(['account_type' => 'individual', 'name' => 'Cliente de API', 'email' => 'type-api@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $other = CustomerAccount::create(['name' => 'Empresa ajena', 'account_type' => 'business', 'billing_email' => 'other-type@example.test']);
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Types', ['orders:read', 'orders:write'])->plainTextToken, 'Idempotency-Key' => 'account-type-001'];
        $this->postJson('/api/v1/orders', ['subject' => 'Prueba de cuenta tipada', 'consent' => true, 'account_type' => 'business', 'customer_account_id' => $other->id,
            'items' => [['service_code' => 'training-equipment', 'quantity' => 1]]], $headers)->assertCreated()
            ->assertJsonPath('data.customer_account.id', $client->account->public_id)->assertJsonPath('data.customer_account.account_type', 'individual');
        $this->assertSame(AccountType::Business, $other->fresh()->account_type);
    }
}
