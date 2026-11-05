<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $client = app(CreateNewUser::class)->create(['name' => 'Prueba web', 'email' => 'web-person@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'account_type' => 'individual', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($client)->get(route('portal.orders.create', ['service' => 'identity-verification']))->assertOk()->assertSee('name="people[__INDEX__][first_name]"', false)->assertSee('Selfie frontal')->assertSee('revisión humana');
        $data = PersonVerificationWorkflowTest::data();
        $data['service_codes'] = array_column($data['items'], 'service_code');
        unset($data['items']);
        $this->post('/portal/orders', $data)->assertSessionHasErrors(['evidence.identity_front', 'evidence.selfie']);
        $this->assertDatabaseCount('orders', 0);
        $evidence = array_combine(['identity_front', 'selfie', 'degree_document', 'exequatur_document'], array_map(PersonVerificationWorkflowTest::evidence(...), ['identity_front', 'selfie', 'degree_document', 'exequatur_document']));
        $this->post('/portal/orders', $data + ['evidence' => $evidence])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Persona Ficticia')->assertSee('00000000000')->assertSee('Documentación de entrada completa')->assertDontSee('Conclusiones por servicio');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin)->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Conclusiones por servicio')->assertSee('findings['.$order->people->first()->public_id.'][identity-verification][outcome]', false);
        $this->post('/portal/orders/'.$order->public_id.'/results', ['summary' => 'Resumen privado', 'findings' => []])->assertSessionHasErrors();
        $this->assertDatabaseCount('order_results', 0);
    }
}
