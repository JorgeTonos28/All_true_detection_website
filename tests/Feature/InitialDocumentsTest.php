<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\StoreAttachment;
use App\Models\Order;
use App\Support\PrivateUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InitialDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function client()
    {
        Notification::fake();
        Storage::fake('local');
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Documentos', 'email' => 'docs@example.com', 'password' => 'PasswordCliente123', 'password_confirmation' => 'PasswordCliente123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_order_and_initial_files_are_atomic_and_idempotent(): void
    {
        $client = $this->client();
        $this->actingAs($client);
        $payload = ['subject' => 'Con documentos', 'details' => 'Descripción suficiente del caso', 'service_codes' => ['exequatur'], 'consent' => true, 'idempotency_key' => 'initial-documents'];
        $this->post('/portal/orders', $payload + ['documents' => [UploadedFile::fake()->create('uno.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('foto.png', 20, 20)]])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(2, $order->attachments()->count());
        foreach ($order->attachments as $file) {
            $this->assertNotNull($file->file_size);
            $this->assertSame(64, strlen($file->sha256));
            Storage::disk('local')->assertExists($file->file_path);
        }
        $before = count(Storage::disk('local')->allFiles());
        $this->post('/portal/orders', $payload + ['documents' => [UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf')]])->assertConflict();
        $this->assertSame(1, Order::count());
        $this->assertSame($before, count(Storage::disk('local')->allFiles()));
    }

    public function test_invalid_audio_and_budget_failure_leave_no_order_or_orphan_files(): void
    {
        $client = $this->client();
        $data = ['subject' => 'Falla controlada', 'details' => 'Detalle suficiente', 'items' => [['service_code' => 'exequatur', 'quantity' => 1]]];
        try {
            app(CreateOrder::class)->handle($client, $data, null, [UploadedFile::fake()->create('valido.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('audio.mp3', 1, 'audio/mpeg')]);
            $this->fail('Debe rechazar audio.');
        } catch (ValidationException) {
            $this->assertSame(0, Order::count());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
        config(['portal.document_account_max_bytes' => 1]);
        try {
            app(CreateOrder::class)->handle($client, $data, null, [UploadedFile::fake()->create('limite.pdf', 1, 'application/pdf')]);
            $this->fail('Debe respetar la cuota.');
        } catch (HttpException $error) {
            $this->assertSame(422, $error->getStatusCode());
            $this->assertSame(0, Order::count());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_attachment_retries_with_same_key_do_not_duplicate_documents(): void
    {
        $client = $this->client();
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Idempotente', 'details' => 'Detalle suficiente', 'items' => [['service_code' => 'exequatur', 'quantity' => 1]]]);
        $action = app(StoreAttachment::class);
        $first = $action->handle($client, $order, UploadedFile::fake()->create('evidencia.pdf', 1, 'application/pdf'), 'document-retry-1');
        $second = $action->handle($client, $order, UploadedFile::fake()->create('evidencia.pdf', 1, 'application/pdf'), 'document-retry-1');
        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_large_request_is_rejected_before_document_processing(): void
    {
        $client = $this->client();
        $this->actingAs($client)->withHeaders(['Content-Length' => 23 * 1024 * 1024])->post('/portal/orders')->assertStatus(413);
    }

    public function test_word_document_is_inspected_and_disguised_macros_are_rejected(): void
    {
        $this->client();
        foreach ([false, true] as $macro) {
            $path = tempnam(sys_get_temp_dir(), 'atd-docx-');
            $zip = new \ZipArchive;
            $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
            $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>Test</w:p></w:body></w:document>');
            if ($macro) {
                $zip->addFromString('word/vbaProject.bin', 'macro-content');
            }
            $zip->close();
            try {
                $file = new UploadedFile($path, 'documento.docx', null, null, true);
                if ($macro) {
                    try {
                        PrivateUpload::validate($file);
                        $this->fail('Debe rechazar un Word con macros.');
                    } catch (ValidationException) {
                        $this->assertTrue(true);
                    }
                } else {
                    PrivateUpload::validate($file);
                    $this->assertTrue(true);
                }
            } finally {
                unlink($path);
            }
        }
    }
}
