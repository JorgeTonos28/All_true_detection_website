<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\StoreAttachment;
use App\Actions\Orders\StoreResult;
use App\Enums\Role;
use App\Models\User;
use App\Support\PersonVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiplePeopleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        Notification::fake();
        Storage::fake('local');
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Prueba de lote', 'email' => 'batch@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public static function data(): array
    {
        return ['subject' => 'Lote sintético', 'consent' => true, 'items' => [['service_code' => 'psychometric', 'quantity' => 2], ['service_code' => 'drug-screening', 'quantity' => 1]], 'people' => [
            ['first_name' => 'Primera', 'last_name' => 'Ficticia', 'authorization_confirmed' => true, 'service_codes' => ['psychometric', 'drug-screening']],
            ['first_name' => 'Segunda', 'last_name' => 'Ficticia', 'authorization_confirmed' => true, 'service_codes' => ['psychometric']],
        ]];
    }

    public function test_multiple_people_share_order_without_irrelevant_identity_or_required_details(): void
    {
        $client = $this->client();
        $order = app(CreateOrder::class)->handle($client, self::data(), 'batch-order-001');
        $this->assertNull($order->details);
        $this->assertCount(2, $order->people);
        $this->assertSame(3, DB::table('order_person_services')->count());
        $this->assertSame(2, $order->items()->whereHas('service', fn ($q) => $q->where('code', 'psychometric'))->value('quantity'));
        $this->assertStringNotContainsString('Ficticia', DB::table('order_people')->value('profile'));
        $this->assertSame($order->id, app(CreateOrder::class)->handle($client, self::data(), 'batch-order-001')->id);
        $this->assertTrue(PersonVerification::evidenceStatus($order)['ready_for_review']);
        $findings = [];
        foreach ($order->people as $person) {
            foreach ($person->services as $service) {
                $findings[$person->public_id][$service->code] = ['outcome' => 'inconclusive', 'notes' => 'Conclusión sintética de prueba'];
            }
        }
        $result = app(StoreResult::class)->handle(User::factory()->create(['role' => Role::Admin]), $order, 'Entrega sintética', null, $findings);
        $this->assertCount(3, $result->verification_findings['checks']);
        $this->assertSame($order->people[0]->public_id, $result->verification_findings['checks'][0]['person_id']);
    }

    public function test_quantities_services_duplicates_and_irrelevant_identity_are_validated(): void
    {
        $this->client();
        $variants = [];
        $data = self::data();
        $data['items'][0]['quantity'] = 1;
        $variants[] = $data;
        $data = self::data();
        $data['people'][0]['service_codes'][] = 'training-equipment';
        $variants[] = $data;
        $data = self::data();
        $data['people'][0]['identity_number'] = '00000000000';
        $variants[] = $data;
        $data = self::data();
        $data['people'][0]['authorization_confirmed'] = false;
        $variants[] = $data;
        foreach ($variants as $invalid) {
            try {
                PersonVerification::people($invalid);
                $this->fail('Debe rechazar el lote inválido.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_exact_duplicate_front_back_is_rejected_even_after_renaming_and_retry_is_safe(): void
    {
        $client = $this->client();
        $order = app(CreateOrder::class)->handle($client, PersonVerificationWorkflowTest::data(['identity-verification']));
        $action = app(StoreAttachment::class);
        $first = $action->handle($client, $order, UploadedFile::fake()->image('frente.png'), 'front-001', 'identity_front');
        $this->assertSame($first->id, $action->handle($client, $order, UploadedFile::fake()->image('frente.png'), 'front-001', 'identity_front')->id);
        try {
            $action->handle($client, $order, UploadedFile::fake()->image('renombrado.png'), null, 'identity_back');
            $this->fail('Debe rechazar el mismo contenido.');
        } catch (ValidationException) {
            $this->assertSame(1, $order->attachments()->count());
            $this->assertCount(1, Storage::disk('local')->allFiles());
        }
    }

    public function test_required_title_and_exequatur_are_separate_from_supporting_and_atomic(): void
    {
        $client = $this->client();
        $data = PersonVerificationWorkflowTest::data(['university-degree', 'exequatur']);
        $profile = $data['person'];
        unset($data['person']);
        $data['people'] = [$profile + ['service_codes' => ['university-degree', 'exequatur']]];
        try {
            app(CreateOrder::class)->handle($client, $data, null, [PersonVerificationWorkflowTest::evidence('supporting')], [], true, [0 => ['identity_front' => PersonVerificationWorkflowTest::evidence('identity_front')]]);
            $this->fail('Los documentos de apoyo no sustituyen el título ni el exequátur.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
        $evidence = array_combine(['identity_front', 'degree_document', 'exequatur_document'], array_map(PersonVerificationWorkflowTest::evidence(...), ['identity_front', 'degree_document', 'exequatur_document']));
        $order = app(CreateOrder::class)->handle($client, $data, null, [], [], true, [0 => $evidence]);
        $this->assertTrue(PersonVerification::evidenceStatus($order)['ready_for_review']);
        $this->assertSame(3, $order->attachments()->where('order_person_id', $order->people->first()->id)->count());
    }
}
