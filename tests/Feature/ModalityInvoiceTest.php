<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Orders\CreateOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\BusinessBillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModalityInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function actors(): array
    {
        Notification::fake();
        $this->seed();
        $client = app(CreateNewUser::class)->create(['account_type' => 'individual', 'name' => 'Cliente ficticio', 'email' => 'modality@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);

        return [$client, $admin];
    }

    public function test_quotation_splits_modalities_by_person_and_preserves_confirmed_lines_on_every_surface(): void
    {
        [$client, $admin] = $this->actors();
        $polygraph = Service::where('code', 'polygraph')->sole();
        $profiles = Service::where('code', 'profile-verification')->sole();
        $polygraph->update(['default_price' => 990000]);
        app(BusinessBillingSettings::class)->save($admin, ['catalog_pricing_enabled' => true, 'service_option_prices' => [
            $polygraph->id => ['pre_employment' => '1200.50', 'routine' => '1850.00'], $profiles->id => ['basic' => '875.50'],
        ]]);
        $person = ['first_name' => 'Persona', 'last_name' => 'Ficticia', 'authorization_confirmed' => true];
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Evaluaciones con modalidades distintas', 'consent' => true,
            'items' => [['service_code' => 'polygraph', 'quantity' => 4], ['service_code' => 'profile-verification', 'quantity' => 1]],
            'people' => [
                $person + ['service_codes' => ['polygraph', 'profile-verification'], 'service_options' => ['polygraph' => 'pre_employment', 'profile-verification' => 'basic']],
                $person + ['service_codes' => ['polygraph'], 'service_options' => ['polygraph' => 'routine']],
                $person + ['service_codes' => ['polygraph'], 'service_options' => ['polygraph' => 'pre_employment']],
                $person + ['service_codes' => ['polygraph']],
            ],
        ]);
        $polygraphItem = $order->items()->where('service_id', $polygraph->id)->sole();
        $profileItem = $order->items()->where('service_id', $profiles->id)->sole();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('portal.orders.show', $order))->assertOk()->assertSee('1200.50')->assertSee('1850.00')->assertSee('875.50')
            ->assertSee('Modalidad sin especificar')->assertDontSee('9900.00');
        $route = route('portal.invoices.issue', $order);
        $confirmed = [$polygraphItem->id.':pre_employment' => '1350.25', $polygraphItem->id.':routine' => '1850.00', $polygraphItem->id => '650.00', $profileItem->id.':basic' => '875.50'];
        $this->post($route, ['prices' => [$polygraphItem->id.':specific' => '3.00'] + $confirmed, 'tax_percent' => '18'])->assertSessionHasErrors('prices');
        $this->post($route, ['prices' => array_diff_key($confirmed, [$polygraphItem->id.':routine' => true]), 'tax_percent' => '18'])->assertSessionHasErrors('prices');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(OrderStatus::Submitted, $order->fresh()->status);
        $this->post($route, ['prices' => $confirmed, 'tax_percent' => '18'])->assertSessionHasNoErrors()->assertRedirect();
        $invoice = $order->invoice()->sole();
        $this->assertSame(607600, $invoice->subtotal);
        $this->assertSame(109368, $invoice->tax);
        $this->assertSame(716968, $invoice->total);
        $this->assertNull($polygraphItem->fresh()->unit_price);
        $this->assertSame(87550, (int) $profileItem->fresh()->unit_price);
        $this->assertSame(['pre_employment', 'routine', null, 'basic'], array_column($invoice->billingLines(), 'option_code'));
        $this->assertSame([2, 1, 1, 1], array_column($invoice->billingLines(), 'quantity'));

        app(BusinessBillingSettings::class)->save($admin, ['catalog_pricing_enabled' => false, 'service_option_prices' => [$polygraph->id => ['pre_employment' => '9999.00']]]);
        $polygraph->update(['options' => [['code' => 'pre_employment', 'name' => 'Nombre de catálogo nuevo']]]);
        $this->get(route('portal.invoices.show', $invoice))->assertOk()->assertSee('Nuevo ingreso')->assertSee('Rutina o control')->assertSee('1,350.25')->assertDontSee('Nombre de catálogo nuevo');

        app('auth')->forgetGuards();
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Billing tests', ['orders:read', 'invoices:read'])->plainTextToken];
        $this->getJson('/api/v1/invoices/'.$invoice->public_id, $headers)->assertOk()->assertJsonCount(4, 'data.lines')
            ->assertJsonPath('data.lines.0.option_name', 'Nuevo ingreso')->assertJsonPath('data.lines.0.unit_price', 135025)
            ->assertJsonPath('data.lines.0.total', 270050)->assertJsonPath('data.lines.1.option_code', 'routine')
            ->assertJsonPath('data.lines.3.service_code', 'profile-verification')->assertJsonPath('data.total', 716968);
        $this->get('/api/v1/invoices/'.$invoice->public_id.'/document', $headers)->assertOk()->assertSee('Nuevo ingreso')->assertSee('Rutina o control')->assertSee('7,169.68');
    }

    public function test_historical_invoices_use_their_original_lines_even_when_new_tariffs_exist(): void
    {
        [$client, $admin] = $this->actors();
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Cotización anterior', 'consent' => true, 'items' => [['service_code' => 'polygraph', 'quantity' => 2]]]);
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items()->sole()->id => 75000]]);
        DB::table('invoices')->where('id', $invoice->id)->update(['line_items' => null]);
        $service = Service::where('code', 'polygraph')->sole();
        app(BusinessBillingSettings::class)->save($admin, ['catalog_pricing_enabled' => true, 'service_option_prices' => [$service->id => ['routine' => '1900.00']]]);
        $service->update(['name' => 'Nombre actualizado']);
        $this->assertSame([['service_name' => 'Servicios poligráficos', 'quantity' => 2, 'unit_price' => 75000, 'total' => 150000]], $invoice->fresh()->billingLines());
        $this->assertSame(150000, $invoice->fresh()->total);
    }
}
