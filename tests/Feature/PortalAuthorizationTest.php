<?php
namespace Tests\Feature;
use App\Enums\Role;
use App\Models\CustomerAccount;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
class PortalAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    public function test_account_isolation_and_staff_assignment_are_enforced(): void
    {
        $client = User::factory()->create();
        $account = CustomerAccount::create(['name' => 'Cliente', 'billing_email' => $client->email]);
        $client->forceFill(['customer_account_id' => $account->id])->save();
        $order = Order::create(['customer_account_id' => $account->id, 'requested_by' => $client->id, 'subject' => 'Caso', 'details' => 'Confidencial', 'consented_at' => now()]);
        $other = User::factory()->create();
        $collaborator = User::factory()->create(['role' => Role::Collaborator]);
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->assertTrue(Gate::forUser($client)->allows('view', $order));
        $this->assertFalse(Gate::forUser($other)->allows('view', $order));
        $this->assertFalse(Gate::forUser($collaborator)->allows('view', $order));
        $order->update(['assigned_to' => $collaborator->id]);
        $this->assertTrue(Gate::forUser($collaborator)->allows('update', $order));
        $this->assertFalse(Gate::forUser($collaborator)->allows('administer', $order));
        $this->assertTrue(Gate::forUser($admin)->allows('administer', $order));
        $this->assertNotSame('Confidencial', $order->getRawOriginal('details'));
    }
    public function test_seeders_do_not_create_predictable_admin_credentials_or_overwrite_settings(): void
    {
        $this->seed();
        $this->assertDatabaseCount('services', 11);
        $this->assertDatabaseCount('users', 0);
        \App\Models\Setting::put('company_name', 'Marca del cliente');
        $this->seed();
        $this->assertSame('Marca del cliente', \App\Models\Setting::values()['company_name']);
    }
}
