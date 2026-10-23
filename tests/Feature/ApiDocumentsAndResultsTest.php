<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\User;
use App\Support\PrivateUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiDocumentsAndResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_upload_download_and_results_follow_the_same_private_workflow(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->seed();
        $client = app(CreateNewUser::class)->create(['name' => 'Cliente API', 'email' => 'api-docs@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Verificación por API', 'details' => 'Detalle del caso para revisar', 'items' => [['service_code' => 'exequatur', 'quantity' => 1]]]);
        $token = $client->createToken('Automation', ['orders:read', 'orders:write', 'results:read'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $upload = $this->post('/api/v1/orders/'.$order->public_id.'/attachments', ['file' => UploadedFile::fake()->create('titulo.pdf', 10, 'application/pdf')], $headers + ['Content-Type' => 'multipart/form-data'])->assertCreated()->assertDontSee('file_path');
        $attachment = $upload->json('data.id');
        $this->getJson('/api/v1/orders/'.$order->public_id.'/attachments', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->get('/api/v1/orders/'.$order->public_id.'/attachments/'.$attachment.'/download', $headers)->assertOk()->assertDownload('titulo.pdf');
        $result = app(StoreResult::class)->handle($admin, $order, 'RESULTADO-PRIVADO-API', UploadedFile::fake()->create('resultado.pdf', 10, 'application/pdf'));
        $resultUrl = '/api/v1/orders/'.$order->public_id.'/results';
        $this->getJson($resultUrl, $headers)->assertConflict()->assertDontSee('RESULTADO-PRIVADO-API');
        $this->get($resultUrl.'/'.$result->public_id.'/download', $headers)->assertConflict();
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 1000]]);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 1000, 'method' => 'cash', 'reference' => 'API-PAYMENT']);
        $this->getJson($resultUrl, $headers)->assertOk()->assertJsonCount(0, 'data');
        app(PublishResult::class)->handle($admin, $result);
        $this->getJson($resultUrl, $headers)->assertOk()->assertJsonPath('data.0.summary', 'RESULTADO-PRIVADO-API');
        $this->get($resultUrl.'/'.$result->public_id.'/download', $headers)->assertOk()->assertDownload('resultado.pdf');
        $this->post('/api/v1/orders/'.$order->public_id.'/attachments', ['file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')], $headers + ['Content-Type' => 'multipart/form-data'])->assertConflict();
        $this->assertSame(1, $order->attachments()->count());
    }

    public function test_document_validation_rejects_executable_disguised_as_pdf(): void
    {
        Storage::fake('local');
        $this->expectException(ValidationException::class);
        $fake = UploadedFile::fake()->createWithContent('fake.pdf', '<?php echo "malicious";');
        $file = new UploadedFile($fake->getPathname(), 'fake.pdf', 'application/pdf', null, true);
        Validator::make(['file' => $file], ['file' => PrivateUpload::rules()])->validate();
    }
}
