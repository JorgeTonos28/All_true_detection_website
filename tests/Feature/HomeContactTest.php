<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HomeContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_ctas_open_the_shared_contact_dialog_without_requiring_an_account(): void
    {
        $this->seed();
        $response = $this->get('/')->assertOk()->assertSee('id="evaluation-contact"', false)->assertSee('data-contact-open', false)->assertSee(route('services.request', ['service' => 'exequatur']));
        $this->assertSame(3, substr_count($response->getContent(), 'data-contact-open'));
        $this->assertSame(2, substr_count($response->getContent(), 'data-public-contact'));
    }

    public function test_ajax_contact_persists_a_private_thread_and_uses_existing_validation(): void
    {
        Notification::fake();
        $data = ['name' => 'Consulta de prueba', 'email' => 'modal@example.com', 'subject' => 'Evaluación inicial', 'body' => 'Necesito orientación para seleccionar el servicio.', 'consent' => true];
        $this->postJson('/contact', $data)->assertCreated()->assertJsonStructure(['message'])->assertDontSee('customer_account_id');
        $this->assertSame(1, Conversation::count());
        $this->assertNull(Conversation::first()->customer_account_id);
        $this->postJson('/contact', $data + ['website' => 'spam.example.com'])->assertUnprocessable();
        $data['email'] = 'invalid';
        $this->postJson('/contact', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(1, Conversation::count());
    }
}
