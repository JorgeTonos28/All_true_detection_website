<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Cliente', 'email' => 'client@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function order(User $user, string $key = 'request-1'): Order
    {
        return app(CreateOrder::class)->handle($user, ['subject' => 'Validar título', 'details' => 'Datos de la evaluación de prueba', 'consent' => true, 'items' => [['service_code' => 'profile-verification', 'quantity' => 2]]], $key);
    }

    public function test_complete_workflow_requires_paid_invoice_and_explicit_publication(): void
    {
        Notification::fake();
        $this->seed();
        $client = $this->customer();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = $this->order($client);
        $same = $this->order($client);
        $this->assertSame($order->id, $same->id);
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 10000], 'tax_basis_points' => 1800]);
        $this->assertSame(23600, $invoice->total);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
        $result = app(StoreResult::class)->handle($admin, $order, 'Resultado reservado');
        try {
            app(PublishResult::class)->handle($admin, $result);
            $this->fail('Unpaid result was published');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertFalse(Gate::forUser($client)->allows('viewResults', $order->fresh()));
        $payment = ['amount' => 10000, 'method' => 'bank_transfer', 'reference' => 'BANK-1'];
        app(RecordPayment::class)->handle($admin, $invoice, $payment);
        app(RecordPayment::class)->handle($admin, $invoice, $payment);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame('unpaid', $invoice->fresh()->status);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 13600, 'method' => 'cash', 'reference' => 'CASH-1']);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNull($result->fresh()->published_at);
        $result = app(PublishResult::class)->handle($admin, $result);
        $this->assertNotNull($result->published_at);
        $this->assertTrue(Gate::forUser($client)->allows('viewResults', $order->fresh()));
    }

    public function test_idempotency_key_rejects_changed_payload(): void
    {
        $this->seed();
        $client = $this->customer();
        $this->order($client);
        $this->expectException(HttpException::class);
        app(CreateOrder::class)->handle($client, ['subject' => 'Otro caso', 'details' => 'Datos diferentes', 'items' => [['service_code' => 'polygraph', 'quantity' => 1]]], 'request-1');
    }

    public function test_staff_cannot_confirm_payments_and_overpayment_is_rejected(): void
    {
        $this->seed();
        $client = $this->customer();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = $this->order($client);
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 100]]);
        $staff = User::factory()->create(['role' => Role::Collaborator]);
        try {
            app(RecordPayment::class)->handle($staff, $invoice, ['amount' => 100, 'method' => 'cash', 'reference' => 'bad']);
            $this->fail('Staff recorded payment');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('payments', 0);
        }
        try {
            app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 201, 'method' => 'cash', 'reference' => 'too-much']);
            $this->fail('Overpayment');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertDatabaseCount('payments', 0);
    }
}
