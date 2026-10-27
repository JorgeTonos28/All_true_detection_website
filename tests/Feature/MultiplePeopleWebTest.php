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

class MultiplePeopleWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        config(['portal.require_staff_two_factor' => false]);
        $this->seed();
        $client = app(CreateNewUser::class)->create(['name' => 'Cliente sintético', 'email' => 'batch-web@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($client);
    }

    public function test_services_and_people_are_modal_and_orders_menu_stays_active(): void
    {
        $response = $this->get('/portal/orders/new')->assertOk()->assertSee('id="service-picker-dialog"', false)->assertSee('data-person-template', false)->assertSee('data-image-dialog', false)->assertSee('Detalles de la solicitud');
        $response->assertSee('href="'.route('portal.orders.index').'"  aria-current="page"', false);
        $this->assertStringContainsString('blob:', $response->headers->get('Content-Security-Policy'));
        $this->assertDoesNotMatchRegularExpression('/<textarea[^>]*name="details"[^>]*required/', $response->getContent());
    }

    public function test_portal_creates_one_order_with_calculated_service_quantities_and_no_required_details(): void
    {
        $data = MultiplePeopleWorkflowTest::data();
        $data['service_codes'] = array_column($data['items'], 'service_code');
        unset($data['items']);
        $this->post('/portal/orders', $data)->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertNull($order->details);
        $this->assertSame(2, $order->people()->count());
        $this->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Primera Ficticia')->assertSee('Segunda Ficticia')->assertSee('Sin instrucciones adicionales.');
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('findings['.$order->people[1]->public_id.'][psychometric][outcome]', false);
    }

    public function test_portal_requires_each_persons_title_separately_and_preview_links_are_private(): void
    {
        $data = PersonVerificationWorkflowTest::data(['university-degree', 'exequatur']);
        $data['service_codes'] = array_column($data['items'], 'service_code');
        $data['people'] = [$data['person'] + ['service_codes' => $data['service_codes']]];
        unset($data['person'], $data['items']);
        $this->post('/portal/orders', $data)->assertSessionHasErrors(['person_evidence.0.degree_document', 'person_evidence.0.exequatur_document', 'person_evidence.0.identity_front']);
        $evidence = array_combine(['identity_front', 'degree_document', 'exequatur_document'], array_map(PersonVerificationWorkflowTest::evidence(...), ['identity_front', 'degree_document', 'exequatur_document']));
        $this->post('/portal/orders', $data + ['person_evidence' => [0 => $evidence]])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $file = $order->attachments()->firstOrFail();
        $this->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee(route('portal.attachments.preview', $file))->assertSee('data-image-preview', false)->assertSee('Título universitario escaneado o fotografiado');
        $this->assertSame(3, $order->attachments()->count());
    }

    public function test_supporting_documents_share_the_twenty_file_initial_limit(): void
    {
        $data = ['subject' => 'Capacitación sintética', 'service_codes' => ['training-equipment'], 'consent' => true];
        $documents = [];
        for ($i = 0; $i < 6; $i++) {
            $documents[] = UploadedFile::fake()->image('apoyo-'.$i.'.png', 100 + $i, 80);
        }
        $this->post('/portal/orders', $data + ['documents' => $documents])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('order_people', 0);
        $this->assertDatabaseCount('order_attachments', 6);
    }
}
