<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreAttachment;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\User;
use App\Support\PersonVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PersonVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        Notification::fake();
        Storage::fake('local');
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Cliente ficticio', 'email' => 'verification@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public static function data(array $codes = ['university-degree', 'exequatur', 'identity-verification']): array
    {
        $person = PersonVerificationDomainTest::profile();
        if (in_array('university-degree', $codes, true)) {
            $person['degree'] = ['institution' => 'Institución ficticia', 'degree_name' => 'Título de prueba'];
        }
        if (in_array('exequatur', $codes, true)) {
            $person['exequatur'] = ['profession' => 'Profesión de prueba'];
        }

        return ['subject' => 'Verificación autorizada', 'details' => 'Datos de un expediente enteramente ficticio', 'consent' => true, 'items' => array_map(fn ($code) => ['service_code' => $code, 'quantity' => 1], $codes), 'person' => $person];
    }

    public function test_three_services_share_one_person_and_evidence_is_required_before_work(): void
    {
        $client = $this->client();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = app(CreateOrder::class)->handle($client, self::data(), 'person-case-1');
        $this->assertSame($order->id, app(CreateOrder::class)->handle($client, self::data(), 'person-case-1')->id);
        $this->assertDatabaseCount('order_people', 1);
        $this->assertSame(3, $order->items()->count());
        $this->assertSame(['identity_front', 'selfie'], PersonVerification::evidenceStatus($order)['missing']);
        try {
            app(UpdateOrder::class)->handle($admin, $order, ['status' => 'reviewing']);
            $this->fail('No debe avanzar sin documentos.');
        } catch (ValidationException) {
            $this->assertSame('submitted', $order->fresh()->status->value);
        }
        $attachments = app(StoreAttachment::class);
        $front = $attachments->handle($client, $order, UploadedFile::fake()->image('frente.png'), 'front-key-1', 'identity_front');
        $attachments->handle($client, $order, UploadedFile::fake()->image('selfie.png'), null, 'selfie');
        $this->assertTrue(PersonVerification::evidenceStatus($order)['ready_for_review']);
        $this->assertSame('identity_front', $front->purpose);
        try {
            $attachments->handle($client, $order, UploadedFile::fake()->image('frente.png'), 'front-key-1', 'selfie');
            $this->fail('Una clave no puede cambiar la clasificación del documento.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $findings = [];
        foreach (PersonVerification::SERVICES as $code) {
            $findings[$code] = ['outcome' => 'inconclusive', 'notes' => 'Conclusión ficticia: se necesita más evidencia.'];
        }
        $result = app(StoreResult::class)->handle($admin, $order, 'Informe humano reservado', null, $findings);
        $this->assertSame('manual', $result->verification_findings['method']);
        $this->assertStringNotContainsString('Conclusión', DB::table('order_results')->value('verification_findings'));
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => $order->items->mapWithKeys(fn ($item) => [$item->id => 100])->all()]);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
        try {
            app(PublishResult::class)->handle($admin, $result);
            $this->fail('No debe publicar conclusiones sin pago.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 300, 'method' => 'cash', 'reference' => 'SYNTHETIC-1']);
        $this->assertNull($result->fresh()->published_at);
        app(PublishResult::class)->handle($admin, $result);
        $this->assertNotNull($result->fresh()->published_at);
    }

    public function test_atomic_initial_evidence_and_changed_person_idempotency(): void
    {
        $client = $this->client();
        try {
            app(CreateOrder::class)->handle($client, self::data(), 'atomic-person-1', [], ['identity_front' => UploadedFile::fake()->image('front.png')], true);
            $this->fail('Falta la selfie obligatoria.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
        $data = self::data(['identity-verification']);
        $order = app(CreateOrder::class)->handle($client, $data, 'identity-case-1', [], ['selfie' => UploadedFile::fake()->image('selfie.png')], true);
        $this->assertTrue(PersonVerification::evidenceStatus($order)['ready_for_review']);
        $data['person']['first_name'] = 'Otra persona ficticia';
        $this->expectException(HttpException::class);
        app(CreateOrder::class)->handle($client, $data, 'identity-case-1', [], ['selfie' => UploadedFile::fake()->image('selfie.png')], true);
    }

    public function test_selfie_cannot_be_a_pdf(): void
    {
        $client = $this->client();
        $order = app(CreateOrder::class)->handle($client, self::data(['identity-verification']));
        $this->expectException(ValidationException::class);
        app(StoreAttachment::class)->handle($client, $order, UploadedFile::fake()->create('not-a-selfie.pdf', 1, 'application/pdf'), null, 'selfie');
    }

    public function test_completion_requires_a_prepared_human_conclusion(): void
    {
        $client = $this->client();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $order = app(CreateOrder::class)->handle($client, self::data(['identity-verification']), null, [], ['selfie' => UploadedFile::fake()->image('selfie.png')], true);
        app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 100]]);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        $this->expectException(HttpException::class);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
    }

    public function test_personal_idempotency_uses_a_keyed_fingerprint_and_recognizes_previous_keys(): void
    {
        $client = $this->client();
        $data = self::data(['identity-verification']);
        $order = app(CreateOrder::class)->handle($client, $data, 'keyed-fingerprint-1');
        $fingerprint = DB::table('idempotency_keys')->value('request_hash');
        $canonical = ['subject' => $data['subject'], 'details' => $data['details'], 'external_reference' => null, 'consent' => true, 'items' => $data['items'], 'person' => Arr::sortRecursive(PersonVerification::profile($data))];
        $encoded = json_encode($canonical, JSON_THROW_ON_ERROR);
        $this->assertNotSame(hash('sha256', $encoded), $fingerprint);
        $this->assertSame(hash_hmac('sha256', "atd:person-order:v1\0".$encoded, config('app.key')), $fingerprint);
        $previous = config('app.key');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => [$previous]]);
        $this->assertSame($order->id, app(CreateOrder::class)->handle($client, $data, 'keyed-fingerprint-1')->id);
        $this->assertDatabaseCount('orders', 1);
    }
}
