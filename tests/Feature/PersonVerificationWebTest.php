<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonVerificationWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_and_shared_profile_initial_evidence_and_staff_conclusion_controls(): void
    {
        Notification::fake();
        Storage::fake('local');
        config(['portal.require_staff_two_factor' => false]);
        $this->seed();
        $client = app(CreateNewUser::class)->create(['name' => 'Prueba web', 'email' => 'web-person@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($client)->get(route('portal.orders.create', ['service' => 'identity-verification']))->assertOk()->assertSee('name="person[first_name]"', false)->assertSee('Selfie frontal')->assertSee('revisión humana');
        $data = PersonVerificationWorkflowTest::data();
        $data['service_codes'] = array_column($data['items'], 'service_code');
        unset($data['items']);
        $this->post('/portal/orders', $data)->assertSessionHasErrors(['evidence.identity_front', 'evidence.selfie']);
        $this->assertDatabaseCount('orders', 0);
        $this->post('/portal/orders', $data + ['evidence' => ['identity_front' => UploadedFile::fake()->image('front.png'), 'selfie' => UploadedFile::fake()->image('selfie.png')]])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Persona Ficticia')->assertSee('00000000000')->assertSee('Evidencia obligatoria recibida')->assertDontSee('Conclusiones humanas por servicio');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin)->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Conclusiones humanas por servicio')->assertSee('findings[identity-verification][outcome]', false);
        $this->post('/portal/orders/'.$order->public_id.'/results', ['summary' => 'Resumen privado', 'findings' => []])->assertSessionHasErrors();
        $this->assertDatabaseCount('order_results', 0);
    }
}
