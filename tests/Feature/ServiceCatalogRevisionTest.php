<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceCatalogRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_copy_update_preserves_business_edits(): void
    {
        $this->seed();
        Setting::put('hero_subtitle', 'Texto propio del negocio');
        Setting::put('meta_description', 'All True Detection ofrece evaluación de perfiles, polígrafo, verificación, antidoping, investigación, capacitación y análisis asistido por IA para decisiones corporativas más seguras.');
        $migration = require database_path('migrations/2026_11_04_000004_align_default_catalog_copy.php');
        $migration->up();
        $this->assertSame('Texto propio del negocio', Setting::values()['hero_subtitle']);
        $this->assertStringContainsString('credenciales', Setting::values()['meta_description']);
        $this->assertStringNotContainsString('IA', Setting::values()['meta_description']);
    }

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
        $this->assertSame(11, Service::where('is_active', true)->count());
        $this->assertTrue(Service::where('code', 'bailiff-credentials')->sole()->is_active);
        $this->assertTrue(Service::where('code', 'university-degree')->sole()->is_active);
        $this->assertTrue(Service::where('code', 'training-equipment')->sole()->is_active);
    }

    public function test_remote_interview_rollback_preserves_service_identity_and_pricing(): void
    {
        $service = Service::where('code', 'remote-interview-rppg')->sole();
        $service->update(['default_price' => 52500]);
        $migration = require database_path('migrations/2026_11_06_000001_add_remote_interview_service.php');

        $migration->down();
        $this->assertFalse($service->fresh()->is_active);

        $migration->up();
        $this->assertTrue($service->fresh()->is_active);
        $this->assertSame(52500, $service->fresh()->default_price);
        $this->assertSame('assessments', $service->fresh()->category);
    }
}
