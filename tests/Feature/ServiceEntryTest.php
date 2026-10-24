<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Messages\CreateConversation;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_selection_survives_registration_and_verification(): void
    {
        Notification::fake();
        $this->seed();
        $destination = route('portal.orders.create', ['service' => 'exequatur']);
        $this->get('/solicitar?service=exequatur')->assertRedirect('/login')->assertSessionHas('url.intended', $destination);
        $this->post('/register', ['name' => 'Cliente', 'email' => 'entry@example.com', 'password' => 'PasswordCliente123', 'password_confirmation' => 'PasswordCliente123', 'terms' => true])->assertRedirect($destination);
        $user = User::where('email', 'entry@example.com')->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user->fresh());
        $this->get($destination)->assertOk()->assertViewHas('selectedService', 'exequatur')->assertSee('← Volver');
        $this->get('/')->assertSee(route('services.request', ['service' => 'exequatur']));
    }

    public function test_public_call_to_action_does_not_forbid_staff(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->get('/solicitar')->assertRedirect('/portal/orders');
        $this->get('/portal/orders/new')->assertRedirect('/portal/orders');
    }

    public function test_historical_messages_use_the_current_client_name(): void
    {
        Notification::fake();
        $client = app(CreateNewUser::class)->create(['name' => 'Nombre anterior', 'email' => 'name@example.com', 'password' => 'PasswordCliente123', 'password_confirmation' => 'PasswordCliente123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $conversation = app(CreateConversation::class)->handle($client, ['subject' => 'Cambio de nombre', 'body' => 'Consulta inicial de prueba.']);
        $client->update(['name' => 'Nombre actualizado']);
        $this->actingAs($client)->get('/portal/messages/'.$conversation->public_id)->assertOk()->assertSee('Nombre actualizado')->assertDontSee('Nombre anterior');
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->get('/portal/messages')->assertOk()->assertSee('Nombre actualizado');
    }
}
