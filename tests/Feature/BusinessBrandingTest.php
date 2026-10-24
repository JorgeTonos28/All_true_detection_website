<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_identity_updates_are_used_across_public_portal_auth_and_legal_pages(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put('/portal/settings', [
            'company_name' => 'ATD Servicios',
            'logo' => UploadedFile::fake()->image('logo.png', 240, 80),
            'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertRedirect();
        $values = Setting::values();
        $logo = Storage::disk('public')->url($values['logo_path']);
        $icon = Storage::disk('public')->url($values['favicon_path']);
        Storage::disk('public')->assertExists($values['logo_path']);
        $this->get('/')->assertOk()->assertSee($logo, false)->assertSee($icon, false)->assertDontSee('brand-lockup-dark.png');
        $this->get('/portal')->assertOk()->assertSee('ATD Servicios')->assertSee($logo, false)->assertSee($icon, false);
        $this->post('/logout');
        foreach (['/login', '/aviso-de-privacidad', '/terminos-y-condiciones'] as $url) {
            $this->get($url)->assertOk()->assertSee('ATD Servicios')->assertSee($logo, false)->assertSee($icon, false);
        }
    }
}
