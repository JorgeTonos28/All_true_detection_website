<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_and_contract_are_public_and_footer_links_to_them(): void
    {
        $this->seed();
        $this->get(route('api.docs'))->assertOk()->assertSee('Autenticación y permisos')->assertSee('invoices:read')->assertSee('result.published')->assertSee('Idempotency-Key')->assertSee('Referencia de endpoints')->assertDontSee('MAIL_PASSWORD');
        $this->get(route('api.contract'))->assertOk()->assertHeader('Content-Type', 'application/json');
        $html = $this->get('/')->assertOk()->getContent();
        $footer = substr($html, strpos($html, '<footer'));
        $this->assertStringContainsString(route('api.docs'), $footer);
        $this->assertStringNotContainsString(route('api.docs'), substr($html, 0, strpos($html, '</header>')));
    }
}
