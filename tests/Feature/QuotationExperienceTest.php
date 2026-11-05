<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\BusinessBankAccount;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PortalActivity;
use App\Services\BusinessBillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QuotationExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Notification::fake();
        $this->seed();
        $client = app(CreateNewUser::class)->create(['account_type' => 'individual', 'name' => 'Cliente de prueba', 'email' => 'quote@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $client->account->update(['account_type' => 'individual']);
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Capacitación de prueba', 'details' => null, 'consent' => true, 'items' => [['service_code' => 'training-equipment', 'quantity' => 2]]]);

        return [$client, $admin, $order];
    }

    public function test_suggested_prices_remain_editable_and_invoice_email_invites_payment(): void
    {
        [$client, $admin, $order] = $this->scenario();
        $service = Service::where('code', 'training-equipment')->firstOrFail();
        app(BusinessBillingSettings::class)->save($admin, ['catalog_pricing_enabled' => true, 'service_prices' => [$service->id => '1250.75']]);
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['tax_basis_points' => 1800]);
        $this->assertSame(250150, $invoice->subtotal);
        $this->assertSame(45027, $invoice->tax);
        $this->assertSame('individual', $invoice->recipient['account_type']);
        $service->update(['default_price' => 99999]);
        $this->assertSame(125075, $order->items()->sole()->unit_price);
        Notification::assertSentTo($client, PortalActivity::class, fn ($notice) => str_contains($notice->title, 'cotización') && str_contains($notice->copy, 'DOP 2,951.77') && str_contains($notice->path, '/portal/invoices/'));
    }

    public function test_invoice_exposes_only_active_same_currency_accounts_with_scope_and_tenant_checks(): void
    {
        [$client, $admin, $order] = $this->scenario();
        $bank = BusinessBankAccount::factory()->create();
        BusinessBankAccount::factory()->create(['currency' => 'USD', 'account_number' => '000999999999']);
        BusinessBankAccount::factory()->create(['is_active' => false, 'account_number' => '000888888888']);
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items()->sole()->id => 10000]]);
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Invoice', ['invoices:read'])->plainTextToken];
        $this->getJson('/api/v1/invoices/'.$invoice->public_id, $headers)->assertOk()->assertJsonCount(1, 'data.payment_accounts')->assertJsonPath('data.payment_accounts.0.account_number', $bank->account_number)->assertDontSee('000999999999')->assertDontSee('000888888888');
        $other = app(CreateNewUser::class)->create(['account_type' => 'individual', 'name' => 'Otra cuenta', 'email' => 'otherquote@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $other->forceFill(['email_verified_at' => now()])->save();
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/invoices/'.$invoice->public_id, ['Authorization' => 'Bearer '.$other->createToken('Other', ['invoices:read'])->plainTextToken])->assertForbidden()->assertDontSee($bank->account_number);
    }

    public function test_a_separate_billing_mailbox_receives_the_invoice_notice(): void
    {
        [$client, $admin, $order] = $this->scenario();
        $client->account->update(['billing_email' => 'billing@example.test']);
        app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items()->sole()->id => 10000]]);
        Notification::assertSentOnDemand(PortalActivity::class, fn ($notice, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === 'billing@example.test' && str_contains($notice->title, 'cotización'));
    }

    public function test_quote_state_cannot_be_entered_without_issuing_an_invoice(): void
    {
        [$client, $admin, $order] = $this->scenario();
        try {
            app(UpdateOrder::class)->handle($admin, $order, ['status' => 'quoted']);
            $this->fail('A quote must be issued through billing.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
            $this->assertDatabaseCount('invoices', 0);
        }
    }
}
