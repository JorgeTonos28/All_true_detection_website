<?php

namespace Tests\Feature;

use App\Actions\Identity\ManagePartnerAccess;
use App\Enums\Role;
use App\Models\CustomerAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityPartnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_cannot_self_grant_credit_and_admin_grants_are_revocable_and_audited(): void
    {
        $account = CustomerAccount::create(['name' => 'Synthetic', 'billing_email' => 'synthetic@example.test', 'identity_api_enabled' => true]);
        $this->assertFalse($account->fresh()->hasIdentityApiCredit());
        $admin = User::factory()->create(['role' => Role::Admin]);
        $action = app(ManagePartnerAccess::class);
        $account = $action->handle($admin, $account, ['enabled' => true, 'daily_limit' => 12]);
        $this->assertTrue($account->hasIdentityApiCredit());
        $this->assertSame(12, $account->identity_api_daily_limit);
        $this->assertSame($admin->id, $account->identity_api_approved_by);
        $this->assertFalse($action->handle($admin, $account, ['enabled' => false, 'daily_limit' => 12])->hasIdentityApiCredit());
        $this->assertDatabaseHas('audit_events', ['action' => 'identity.partner_suspended']);
    }

    public function test_client_cannot_authorize_partner_credit(): void
    {
        $account = CustomerAccount::create(['name' => 'Synthetic', 'billing_email' => 'synthetic@example.test']);
        $client = User::factory()->create(['role' => Role::Client, 'customer_account_id' => $account->id]);
        $this->expectException(AuthorizationException::class);
        app(ManagePartnerAccess::class)->handle($client, $account, ['enabled' => true, 'daily_limit' => 10]);
    }
}
