<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\CreateOrder;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderReporting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderReportingTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $email): User
    {
        $user = app(CreateNewUser::class)->create(['account_type' => 'individual', 'name' => 'Cliente de prueba', 'email' => $email, 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function order(User $client, string $subject, string $date): Order
    {
        $order = app(CreateOrder::class)->handle($client, ['subject' => $subject, 'consent' => true, 'items' => [['service_code' => 'training-equipment', 'quantity' => 1]]]);
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }

    public function test_dashboard_and_list_share_local_day_filters_and_financial_totals(): void
    {
        Notification::fake();
        $this->seed();
        $client = $this->client('report@example.test');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $before = $this->order($client, 'Fuera del día anterior', '2026-11-04 03:59:59');
        $inside = $this->order($client, 'Caso de noviembre', '2026-11-04 04:00:00');
        $after = $this->order($client, 'Fuera del día siguiente', '2026-11-05 04:00:00');
        foreach ([$before, $inside, $after] as $order) {
            $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items()->sole()->id => 10000]]);
            if ($order->id === $inside->id) {
                app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 4000, 'method' => 'cash', 'reference' => 'REPORT-PAYMENT']);
            }
        }
        $other = $this->client('otherreport@example.test');
        $this->order($other, 'Datos de otra cuenta', '2026-11-04 04:00:00');
        $this->actingAs($client);
        $filters = '?from=2026-11-04&to=2026-11-04&billing=pending&q=Caso';
        $this->get('/portal'.$filters)->assertOk()->assertViewHas('money', fn ($money) => $money['DOP'] === ['collected' => 4000, 'outstanding' => 6000])->assertSee('Caso de noviembre')->assertSee('Filtros activos')->assertSee('Saldo pendiente')->assertDontSee('Total pagado')->assertDontSee('Total cobrado')->assertDontSee('Fuera del día')->assertDontSee('Datos de otra cuenta');
        $this->get('/portal/orders'.$filters)->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1)->assertSee('Caso de noviembre')->assertDontSee('Fuera del día');
        $this->get('/portal/orders?to=2026-11-04')->assertOk();
        $this->getJson('/portal?from=2026-11-05&to=2026-11-04')->assertUnprocessable();
    }

    public function test_reporting_keeps_currencies_separate_and_collaborators_scoped(): void
    {
        Notification::fake();
        $this->seed();
        $client = $this->client('currencies@example.test');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $collaborator = User::factory()->create(['role' => Role::Collaborator]);
        $first = $this->order($client, 'Caso en pesos', '2026-11-04 12:00:00');
        $first->update(['assigned_to' => $collaborator->id]);
        app(IssueInvoice::class)->handle($admin, $first, ['prices' => [$first->items()->sole()->id => 10000]]);
        config(['portal.currency' => 'USD']);
        $second = $this->order($client, 'Caso en dólares', '2026-11-04 12:00:00');
        app(IssueInvoice::class)->handle($admin, $second, ['prices' => [$second->items()->sole()->id => 2000]]);
        $totals = OrderReporting::money(Order::visibleTo($client));
        $this->assertSame(10000, $totals['DOP']['outstanding']);
        $this->assertSame(2000, $totals['USD']['outstanding']);
        $this->assertSame(['DOP' => ['collected' => 0, 'outstanding' => 10000]], OrderReporting::money(Order::visibleTo($collaborator)));
    }
}
