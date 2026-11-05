<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\IdentitySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IdentityWebPayment;
use Tests\TestCase;

class DiditWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_toggle_is_guarded_and_public_pitch_and_catalog_follow_effective_mode(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/portal/settings?tab=identity')->assertOk()->assertSee('role="switch"', false)->assertSee('Automatización no disponible')->assertDontSee('synthetic-private-api-key');
        $data = ['company_name' => 'All True Detection', 'identity_verification_mode' => 'didit'];
        $this->put('/portal/settings', $data)->assertSessionHasErrors('identity_verification_mode');
        $this->assertSame('manual', IdentitySettings::mode());
        DiditProviderTest::configure();
        $this->put('/portal/settings', $data)->assertSessionHasNoErrors();
        $this->assertSame('didit', IdentitySettings::mode());
        $this->get('/')->assertOk()->assertSee('Verificación de identidad con Didit');
        $client = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($client)->get('/portal/settings')->assertForbidden();
        $this->put('/portal/settings', $data)->assertForbidden();
        config(['identity.didit.api_key' => null]);
        $this->get('/')->assertOk()->assertSee('Verificación de identidad mediante revisión humana');
    }

    public function test_automatic_composer_omits_selfies_and_requires_separate_consent_to_start_capture(): void
    {
        Notification::fake();
        Queue::fake();
        $this->seed();
        DiditProviderTest::configure();
        Setting::put('identity_verification_mode', 'didit');
        $client = app(CreateNewUser::class)->create(['name' => 'Synthetic', 'email' => 'webdidit@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'account_type' => 'individual', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($client)->get('/portal/orders/new?service=identity-verification')->assertOk()->assertSee('captura guiada')->assertDontSee('[selfie]', false);
        $data = PersonVerificationWorkflowTest::data(['identity-verification']);
        $data['service_codes'] = ['identity-verification'];
        $this->post('/portal/orders', $data)->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame('didit', $order->person->identity_mode);
        $this->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Preparar verificación con Didit')->assertDontSee('Abrir captura segura');
        $url = '/portal/orders/'.$order->public_id.'/people/'.$order->person->public_id.'/identity-session';
        $this->post($url, [])->assertSessionHasErrors(['consent', 'notice_version']);
        $this->postJson($url, ['consent' => 1, 'notice_version' => config('identity.notice_version')])->assertConflict();
        IdentityWebPayment::settle($order);
        $this->post($url, ['consent' => 1, 'notice_version' => config('identity.notice_version')])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('identity_sessions', 1);
        $this->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Preparando el enlace')->assertDontSee('Approved');
        $this->get('/aviso-de-privacidad')->assertOk()->assertSee('didit-2026-10-v1')->assertSee('prueba de vida')->assertSee('https://didit.me/terms/privacy-policy/', false);
        $this->get('/verificacion/retorno?status=Approved&verificationSessionId=fake')->assertOk()->assertSee('no confirma una aprobación');
    }
}
