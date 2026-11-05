<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceCatalogRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_revision_preserves_historical_lines_and_prices(): void
    {
        Notification::fake();
        $this->seed();
        $legacy = Service::create(['code' => 'investigations', 'name' => 'Investigación anterior', 'description' => 'Oferta anterior', 'is_active' => true]);
        $priced = Service::where('code', 'polygraph')->firstOrFail();
        $priced->update(['default_price' => 123450]);
        $client = app(CreateNewUser::class)->create(['account_type' => 'individual', 'name' => 'Cliente anterior', 'email' => 'catalog-legacy@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Expediente anterior al catálogo', 'consent' => true, 'items' => [['service_code' => 'investigations', 'quantity' => 1]]]);
        $migration = require database_path('migrations/2026_11_04_000003_refresh_customer_service_catalog.php');
        $migration->up();
        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertSame($legacy->id, $order->items()->sole()->service_id);
        $this->assertSame('Investigación anterior', $order->items()->sole()->service_name);
        $this->assertSame(123450, $priced->fresh()->default_price);
        $this->assertSame(10, Service::where('is_active', true)->count());
        $this->assertTrue(Service::where('code', 'bailiff-credentials')->sole()->is_active);
        $this->assertTrue(Service::where('code', 'university-degree')->sole()->is_active);
        $this->assertTrue(Service::where('code', 'training-equipment')->sole()->is_active);
    }
}
