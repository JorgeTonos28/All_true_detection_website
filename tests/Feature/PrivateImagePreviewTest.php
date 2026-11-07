<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreAttachment;
use App\Actions\Orders\StoreResult;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateImagePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $email = 'preview@example.test'): User
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Prueba visual', 'email' => $email, 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'account_type' => 'individual', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        config(['portal.require_staff_two_factor' => false]);
        $this->seed();
    }

    public function test_input_image_preview_is_authenticated_scoped_and_never_public_or_pdf(): void
    {
        $client = $this->client();
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Entrada privada', 'items' => [['service_code' => 'polygraph', 'quantity' => 1]]]);
        $image = app(StoreAttachment::class)->handle($client, $order, UploadedFile::fake()->image('privada.png'));
        $pdf = app(StoreAttachment::class)->handle($client, $order, UploadedFile::fake()->create('privado.pdf', 1, 'application/pdf'));
        $web = '/portal/attachments/'.$image->public_id.'/preview';
        $this->get($web)->assertRedirect('/login');
        $response = $this->actingAs($client)->get($web)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->get('/portal/attachments/'.$pdf->public_id.'/preview')->assertStatus(415);
        $this->actingAs($this->client('other-preview@example.test'))->get($web)->assertForbidden();
        $api = '/api/v1/orders/'.$order->public_id.'/attachments/'.$image->public_id.'/preview';
        $this->app['auth']->forgetGuards();
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Vista', ['orders:read'])->plainTextToken];
        $this->getJson($api, $headers)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->app['auth']->forgetGuards();
        $this->getJson($api, ['Authorization' => 'Bearer '.$client->createToken('Sin permiso', ['messages:read'])->plainTextToken])->assertForbidden();
    }

    public function test_result_previews_obey_completion_payment_publication_and_account_gates(): void
    {
        $client = $this->client();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Entrega privada', 'items' => [['service_code' => 'polygraph', 'quantity' => 1]]]);
        $result = app(StoreResult::class)->handle($admin, $order, 'Resultado reservado', UploadedFile::fake()->image('resultado.png'));
        $web = '/portal/results/'.$result->public_id.'/preview';
        $api = '/api/v1/orders/'.$order->public_id.'/results/'.$result->public_id.'/preview';
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Resultados', ['results:read'])->plainTextToken];
        $this->actingAs($client)->get($web)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson($api, $headers)->assertConflict();
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 100]]);
        $order->update(['status' => 'completed']);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 100, 'method' => 'cash', 'reference' => 'PREVIEW-TEST-1']);
        $this->actingAs($client)->get($web)->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson($api, $headers)->assertNotFound();
        app(PublishResult::class)->handle($admin, $result);
        $this->actingAs($client)->get($web)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->app['auth']->forgetGuards();
        $this->getJson($api, $headers)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->client('other-result@example.test'))->get($web)->assertForbidden();
    }
}
