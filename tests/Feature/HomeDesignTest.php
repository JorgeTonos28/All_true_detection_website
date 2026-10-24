<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_generated_art_and_all_active_services_remain_requestable(): void
    {
        $this->seed();
        $response = $this->get('/')->assertOk()->assertSee('Catálogo de servicios')->assertSee('service-request')->assertDontSee('trust-console')->assertDontSee('92%')->assertDontSee('30-60%');
        foreach (Service::where('is_active', true)->pluck('code') as $code) {
            $response->assertSee(route('services.request', ['service' => $code]));
        }
        foreach (['hero-lattice-v1.webp', 'services-evidence-v1.webp', 'risk-topography-v1.webp', 'sector-network-v1.webp'] as $file) {
            $response->assertSee('art/'.$file);
            $this->assertFileExists(public_path('assets/images/art/'.$file));
            $this->assertSame('image/webp', getimagesize(public_path('assets/images/art/'.$file))['mime']);
        }
        foreach (['servicios', 'proceso', 'sectores', 'confidencialidad', 'faq', 'contacto'] as $anchor) {
            $response->assertSee('id="'.$anchor.'"', false);
        }
        $this->assertStringNotContainsString('addEventListener("scroll"', file_get_contents(public_path('assets/js/site.js')));
    }
}
