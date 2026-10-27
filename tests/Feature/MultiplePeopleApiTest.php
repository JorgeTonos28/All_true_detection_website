<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiplePeopleApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $email = 'batch-api@example.test'): array
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Integración sintética', 'email' => $email, 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return ['Authorization' => 'Bearer '.$user->createToken('Prueba', ['orders:read', 'orders:write'])->plainTextToken];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->seed();
    }

    public function test_batch_api_is_idempotent_and_profiles_are_detail_only(): void
    {
        $headers = $this->token() + ['Idempotency-Key' => 'batch-api-001'];
        $data = MultiplePeopleWorkflowTest::data();
        $response = $this->postJson('/api/v1/orders', $data, $headers)->assertCreated()->assertJsonCount(2, 'data.people')->assertJsonPath('data.details', null)->assertJsonPath('data.person', null)->assertJsonPath('data.people.0.first_name', 'Primera');
        $id = $response->json('data.id');
        $personId = $response->json('data.people.0.id');
        $this->assertSame(26, strlen($personId));
        $this->postJson('/api/v1/orders', $data, $headers)->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.people.0.id', $personId);
        $this->getJson('/api/v1/orders', $headers)->assertOk()->assertJsonMissingPath('data.0.people')->assertDontSee('Ficticia');
        $data['people'][1]['first_name'] = 'Cambio';
        $this->postJson('/api/v1/orders', $data, $headers)->assertConflict();
        $other = $this->token('other-batch@example.test');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/orders/'.$id, $other)->assertForbidden()->assertDontSee('Ficticia');
    }

    public function test_evidence_is_scoped_to_person_and_order_and_content_cannot_be_reused(): void
    {
        $headers = $this->token();
        $data = MultiplePeopleWorkflowTest::data();
        $data['items'] = [['service_code' => 'identity-verification', 'quantity' => 2]];
        foreach ($data['people'] as $index => &$person) {
            $person = PersonVerificationDomainTest::profile() + ['service_codes' => ['identity-verification']];
            $person['identity_number'] = $index === 0 ? '00000000000' : '00000000001';
        }
        unset($person);
        $response = $this->postJson('/api/v1/orders', $data, $headers + ['Idempotency-Key' => 'evidence-batch-001'])->assertCreated();
        $id = $response->json('data.id');
        $people = $response->json('data.people');
        $path = '/api/v1/orders/'.$id.'/attachments';
        $uploadHeaders = $headers + ['Content-Type' => 'multipart/form-data'];
        $this->post($path, ['file' => UploadedFile::fake()->image('selfie.png'), 'purpose' => 'selfie'], $uploadHeaders)->assertUnprocessable();
        foreach ($people as $index => $person) {
            $this->post($path, ['file' => UploadedFile::fake()->image('selfie-'.$index.'.png', 100 + $index, 80), 'purpose' => 'selfie', 'person_id' => $person['id']], $uploadHeaders)->assertCreated()->assertJsonPath('data.person_id', $person['id']);
        }
        $this->getJson('/api/v1/orders/'.$id, $headers)->assertOk()->assertJsonPath('data.verification.ready_for_review', true)->assertJsonCount(2, 'data.verification.people');
        $this->post($path, ['file' => UploadedFile::fake()->image('renombrada.png', 100, 80), 'purpose' => 'identity_front', 'person_id' => $people[0]['id']], $uploadHeaders)->assertUnprocessable();
        $other = $this->postJson('/api/v1/orders', MultiplePeopleWorkflowTest::data(), $headers + ['Idempotency-Key' => 'other-person-order'])->assertCreated();
        $this->post($path, ['file' => UploadedFile::fake()->image('otra.png', 110, 80), 'purpose' => 'selfie', 'person_id' => $other->json('data.people.0.id')], $uploadHeaders)->assertNotFound();
        $this->assertSame(2, Order::where('public_id', $id)->firstOrFail()->attachments()->count());
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_catalog_exposes_service_requirements_and_details_accept_short_or_null_values(): void
    {
        $headers = $this->token();
        $services = collect($this->getJson('/api/v1/services', $headers)->assertOk()->json('data'))->keyBy('code');
        $this->assertFalse($services['training-equipment']['per_person']);
        $this->assertFalse($services['psychometric']['requires_identity_number']);
        $this->assertContains('degree_document', $services['university-degree']['required_evidence']);
        foreach ([null, 'Breve'] as $index => $details) {
            $this->postJson('/api/v1/orders', MultiplePeopleWorkflowTest::data() + ['details' => $details], $headers + ['Idempotency-Key' => 'optional-details-'.$index])->assertCreated()->assertJsonPath('data.details', $details);
        }
        $bad = MultiplePeopleWorkflowTest::data();
        $bad['people'][0]['id'] = '01CLIENTSELECTEDIDENTIFIER00';
        $this->postJson('/api/v1/orders', $bad, $headers + ['Idempotency-Key' => 'invalid-person-id'])->assertUnprocessable();
        $bad = MultiplePeopleWorkflowTest::data();
        $bad['people'] = array_fill(0, 11, $bad['people'][0]);
        $this->postJson('/api/v1/orders', $bad, $headers + ['Idempotency-Key' => 'too-many-people'])->assertUnprocessable();
    }
}
