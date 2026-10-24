<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PortalApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(array $scopes = ['orders:read', 'orders:write', 'results:read', 'messages:read', 'messages:write']): array
    {
        $email = uniqid().'@example.com';
        $user = app(CreateNewUser::class)->create(['name' => 'API client', 'email' => $email, 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, $user->createToken('API', $scopes, now()->addDay())->plainTextToken];
    }

    private function payload(): array
    {
        return ['subject' => 'Validar título', 'details' => 'Solicito una validación de credenciales.', 'consent' => true, 'items' => [['service_code' => 'profile-verification', 'quantity' => 1]]];
    }

    public function test_api_enforces_auth_scopes_isolation_and_idempotency(): void
    {
        Notification::fake();
        $this->seed();
        $this->get('/api/v1/orders')->assertUnauthorized()->assertJsonStructure(['error' => ['code', 'message'], 'meta' => ['request_id']]);
        [$client,$token] = $this->token();
        $headers = ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'api-order-001'];
        $one = $this->postJson('/api/v1/orders', $this->payload(), $headers)->assertCreated()->assertJsonPath('data.status', 'submitted');
        $id = $one->json('data.id');
        $this->postJson('/api/v1/orders', $this->payload(), $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('orders', 1);
        $changed = $this->payload();
        $changed['subject'] = 'Otro caso';
        $this->postJson('/api/v1/orders', $changed, $headers)->assertConflict();
        $this->getJson('/api/v1/orders/'.$id.'/results', $headers)->assertConflict()->assertDontSee('summary');
        [, $otherToken] = $this->token(['orders:read']);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/orders/'.$id, ['Authorization' => 'Bearer '.$otherToken])->assertForbidden();
        $this->postJson('/api/v1/orders', $this->payload(), ['Authorization' => 'Bearer '.$otherToken, 'Idempotency-Key' => 'api-order-002'])->assertForbidden();
        $this->getJson('/api/v1/orders', ['Authorization' => 'Bearer '.$otherToken])->assertOk()->assertJsonCount(0, 'data');
        $client->tokens()->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/orders', $headers)->assertUnauthorized();
    }

    public function test_validation_does_not_accept_client_chosen_prices_or_accounts(): void
    {
        Notification::fake();
        $this->seed();
        [$user,$token] = $this->token();
        $headers = ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'api-validate-1'];
        $data = $this->payload();
        $data['customer_account_id'] = 999;
        $data['role'] = 'admin';
        $data['price'] = 1;
        $this->postJson('/api/v1/orders', $data, $headers)->assertCreated();
        $order = Order::first();
        $this->assertSame($user->customer_account_id, $order->customer_account_id);
        $this->assertNull($order->items->first()->unit_price);
        $data['items'][0]['quantity'] = -1;
        $this->postJson('/api/v1/orders', $data, ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'api-validate-2'])->assertUnprocessable()->assertJsonStructure(['error' => ['fields']]);
    }

    public function test_expired_tokens_and_deactivated_accounts_are_rejected(): void
    {
        [$user,$token] = $this->token();
        $user->tokens()->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/orders', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $token = $user->createToken('new', ['orders:read'])->plainTextToken;
        $user->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/orders', ['Authorization' => 'Bearer '.$token])->assertForbidden();
    }

    public function test_api_requires_json_and_a_clear_idempotency_key(): void
    {
        Notification::fake();
        $this->seed();
        [, $token] = $this->token();
        $this->post('/api/v1/orders', $this->payload(), ['Authorization' => 'Bearer '.$token])->assertStatus(415)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson('/api/v1/orders', $this->payload(), ['Authorization' => 'Bearer '.$token])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['Idempotency-Key']]]);
        $this->withHeader('Authorization', 'Bearer '.$token)->call('POST', '/api/v1/orders', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{invalid')->assertStatus(400)->assertJsonPath('error.code', 'http_400');
    }

    public function test_retry_of_a_pre_upgrade_idempotency_key_does_not_duplicate_order(): void
    {
        Notification::fake();
        $this->seed();
        [$user, $token] = $this->token();
        $headers = ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'legacy-order-key'];
        $first = $this->postJson('/api/v1/orders', $this->payload(), $headers)->assertCreated();
        DB::table('idempotency_keys')->where('user_id', $user->id)->update(['request_hash' => hash('sha256', json_encode($this->payload(), JSON_THROW_ON_ERROR))]);
        $this->postJson('/api/v1/orders', $this->payload(), $headers)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_production_api_requires_https_and_records_rejection_without_secrets(): void
    {
        Notification::fake();
        $this->seed();
        [, $token] = $this->token(['orders:read']);
        $this->app['env'] = 'production';
        $this->withToken($token)->get('http://localhost/api/v1/orders')->assertStatus(426)->assertHeader('X-Request-ID');
        $this->assertDatabaseHas('api_request_logs', ['status' => 426]);
        $this->withToken($token)->get('https://localhost/api/v1/orders')->assertOk();
    }
}
