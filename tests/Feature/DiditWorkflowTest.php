<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\RequestIdentitySession;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\IdentitySession;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProcessIdentitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DiditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $email): User
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Synthetic', 'email' => $email, 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function bootDidit(): void
    {
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        DiditProviderTest::configure();
        $this->seed();
        Setting::put('identity_verification_mode', 'didit');
    }

    public function test_api_flow_is_idempotent_scoped_and_withholds_decision_until_paid_published(): void
    {
        $this->bootDidit();
        $client = $this->client('automated@example.test');
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Synthetic', ['orders:read', 'orders:write', 'results:read'])->plainTextToken];
        $payload = PersonVerificationWorkflowTest::data(['identity-verification']);
        $response = $this->postJson('/api/v1/orders', $payload, $headers + ['Idempotency-Key' => 'didit-e2e'])->assertCreated()->assertJsonPath('data.people.0.identity_mode', 'didit');
        $order = Order::where('public_id', $response->json('data.id'))->firstOrFail();
        $url = '/api/v1/orders/'.$order->public_id.'/people/'.$order->person->public_id.'/identity-session';
        $this->postJson($url, [], $headers)->assertUnprocessable();
        $consent = ['consent' => true, 'notice_version' => config('identity.notice_version')];
        $id = $this->postJson($url, $consent, $headers)->assertStatus(202)->assertJsonPath('data.state', 'preparing')->json('data.id');
        $this->postJson($url, $consent, $headers)->assertStatus(202)->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('identity_sessions', 1);
        Http::assertNothingSent();
        $session = IdentitySession::firstOrFail();
        $session->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person', 'verification_url' => 'https://verify.didit.me/session/synthetic']);
        $this->getJson($url, $headers)->assertOk()->assertJsonPath('data.verification_url', 'https://verify.didit.me/session/synthetic')->assertHeader('Cache-Control', 'no-store, private');
        $body = DiditProviderTest::decision($session);
        $responses = Http::fakeSequence()->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $result = $order->results()->firstOrFail();
        $this->assertNull($result->published_at);
        $this->assertNull($result->created_by);
        $this->assertSame('verified', $result->verification_findings['checks'][0]['outcome']);
        $this->getJson($url, $headers)->assertOk()->assertJsonPath('data.state', 'received')->assertJsonPath('data.verification_url', null)->assertDontSee('verified')->assertDontSee('Approved');
        $this->getJson('/api/v1/orders/'.$order->public_id, $headers)->assertOk()->assertDontSee('verified')->assertDontSee('Approved');
        $this->getJson('/api/v1/orders/'.$order->public_id.'/results', $headers)->assertConflict();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 100]]);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 100, 'method' => 'cash', 'reference' => 'SYNTHETIC-DIDIT']);
        $this->getJson('/api/v1/orders/'.$order->public_id.'/results', $headers)->assertOk()->assertJsonCount(0, 'data');
        app(PublishResult::class)->handle($admin, $result);
        $this->getJson('/api/v1/orders/'.$order->public_id.'/results', $headers)->assertOk()->assertJsonPath('data.0.verification_findings.method', 'didit');
        $body['status'] = 'Kyc Expired';
        $responses->push($body);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertNull($result->fresh()->published_at);
        $this->getJson('/api/v1/orders/'.$order->public_id.'/results', $headers)->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('order_results', 2);
    }

    public function test_mode_changes_do_not_rewrite_old_orders_and_manual_and_client_isolation_remain(): void
    {
        $this->bootDidit();
        $client = $this->client('snapshot@example.test');
        $data = PersonVerificationWorkflowTest::data(['identity-verification']);
        $automatic = app(CreateOrder::class)->handle($client, $data, 'snapshot-order');
        Setting::put('identity_verification_mode', 'manual');
        $manual = app(CreateOrder::class)->handle($client, $data);
        $this->assertSame('didit', $automatic->person->identity_mode);
        $this->assertSame('manual', $manual->person->identity_mode);
        $this->assertSame($automatic->id, app(CreateOrder::class)->handle($client, $data, 'snapshot-order')->id);
        $other = $this->client('outsider@example.test');
        $headers = ['Authorization' => 'Bearer '.$other->createToken('Synthetic', ['orders:read', 'orders:write'])->plainTextToken];
        $url = '/api/v1/orders/'.$automatic->public_id.'/people/'.$automatic->person->public_id.'/identity-session';
        $this->postJson($url, ['consent' => true, 'notice_version' => config('identity.notice_version')], $headers)->assertForbidden();
        $this->assertDatabaseCount('identity_sessions', 0);
    }

    public function test_provider_checks_cannot_be_overwritten_by_a_manual_result_form(): void
    {
        $this->bootDidit();
        $client = $this->client('tamper@example.test');
        $order = app(CreateOrder::class)->handle($client, PersonVerificationWorkflowTest::data(['identity-verification']));
        app(RequestIdentitySession::class)->handle($client, $order, $order->person, ['consent' => true, 'notice_version' => config('identity.notice_version')]);
        $session = IdentitySession::firstOrFail();
        $session->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person']);
        Http::fake(['*' => Http::response(DiditProviderTest::decision($session))]);
        app(ProcessIdentitySession::class)->handle($session->id);
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->expectException(ValidationException::class);
        app(StoreResult::class)->handle($admin, $order, 'Synthetic', null, ['identity-verification' => ['outcome' => 'not_verified', 'notes' => 'Synthetic overwrite']]);
    }
}
