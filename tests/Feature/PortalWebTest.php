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
use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed();
    }

    private function client(string $email = 'client@example.com'): User
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Cliente', 'email' => $email, 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-test-code'])), 'two_factor_confirmed_at' => now()]);
    }

    public function test_portal_pages_render_for_each_role_and_admin_pages_are_protected(): void
    {
        $client = $this->client();
        $this->actingAs($client)->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['/portal', '/portal/orders', '/portal/orders/new', '/portal/messages', '/portal/security', '/portal/api-guide', '/portal/api-contract'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/portal/users', '/portal/settings', '/portal/operations', '/admin'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach (['/portal', '/portal/users', '/portal/settings', '/portal/operations', '/portal/security'] as $url) {
            $this->get($url)->assertOk();
        }
        $staff = User::factory()->create(['role' => Role::Collaborator]);
        $this->actingAs($staff)->get('/portal')->assertRedirect('/portal/security');
        $this->get('/portal/settings')->assertRedirect('/portal/security');
    }

    public function test_customer_can_request_services_and_input_files_stay_private(): void
    {
        Storage::fake('local');
        $client = $this->client();
        $this->actingAs($client);
        $this->post('/portal/orders', ['subject' => 'Verificación', 'details' => 'Detalle suficiente para revisión', 'service_codes' => ['polygraph', 'profile-verification'], 'consent' => true, 'idempotency_key' => 'web-order-1'])->assertRedirect();
        $order = Order::first();
        $this->assertSame(2, $order->items()->count());
        $this->get('/portal/orders/'.$order->public_id)->assertOk()->assertSee('Evaluaciones con Polígrafo');
        $this->post('/portal/orders/'.$order->public_id.'/attachments', ['file' => UploadedFile::fake()->create('evidencia.pdf', 10, 'application/pdf')])->assertRedirect();
        $attachment = $order->attachments()->first();
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->get('/portal/attachments/'.$attachment->public_id)->assertOk()->assertDownload('evidencia.pdf');
        $other = $this->client('other@example.com');
        $this->actingAs($other)->get('/portal/attachments/'.$attachment->public_id)->assertForbidden();
    }

    public function test_results_cannot_leak_before_payment_or_before_publication_on_web_or_api(): void
    {
        Storage::fake('local');
        $client = $this->client();
        $admin = $this->admin();
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Resultado', 'details' => 'Detalle suficiente', 'items' => [['service_code' => 'polygraph', 'quantity' => 1]]]);
        $result = app(StoreResult::class)->handle($admin, $order, 'SECRETO-DEL-RESULTADO', UploadedFile::fake()->create('resultado.pdf', 10, 'application/pdf'));
        $this->actingAs($client)->get('/portal/orders/'.$order->public_id)->assertOk()->assertDontSee('SECRETO-DEL-RESULTADO');
        $this->get('/portal/results/'.$result->public_id)->assertForbidden();
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => [$order->items->first()->id => 1000]]);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
        app(UpdateOrder::class)->handle($admin, $order, ['status' => 'completed']);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => 1000, 'method' => 'cash', 'reference' => 'PAGO-1']);
        $this->get('/portal/orders/'.$order->public_id)->assertDontSee('SECRETO-DEL-RESULTADO');
        $this->get('/portal/results/'.$result->public_id)->assertNotFound();
        app(PublishResult::class)->handle($admin, $result);
        $this->get('/portal/orders/'.$order->public_id)->assertSee('SECRETO-DEL-RESULTADO');
        $this->get('/portal/results/'.$result->public_id)->assertOk()->assertDownload('resultado.pdf');
        $this->get('/portal/invoices/'.$invoice->public_id)->assertOk()->assertSee('DOP 10.00');
    }

    public function test_public_contact_persists_thread_and_customer_history_is_isolated(): void
    {
        $this->post('/contact', ['name' => 'Cliente', 'email' => 'client@example.com', 'subject' => 'Consulta', 'body' => 'Necesito información sobre sus servicios.', 'consent' => true])->assertRedirect();
        $conversation = Conversation::first();
        $this->assertNull($conversation->customer_account_id);
        $client = $this->client();
        $this->actingAs($client)->get('/portal/messages')->assertOk();
        $this->assertSame($client->customer_account_id, $conversation->fresh()->customer_account_id);
        $this->get('/portal/messages/'.$conversation->public_id)->assertOk()->assertSee('Necesito información');
        $this->post('/portal/messages/'.$conversation->public_id, ['body' => '<script>alert(1)</script>'])->assertRedirect();
        $this->get('/portal/messages/'.$conversation->public_id)->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $other = $this->client('other@example.com');
        $this->actingAs($other)->get('/portal/messages/'.$conversation->public_id)->assertForbidden();
    }

    public function test_token_creation_is_recent_auth_only_and_scope_limited(): void
    {
        $client = $this->client();
        $this->actingAs($client);
        $this->post('/portal/tokens', ['name' => 'Test', 'scopes' => ['orders:read']])->assertRedirect('/user/confirm-password');
        $this->withSession(['auth.password_confirmed_at' => time()])->post('/portal/tokens', ['name' => 'Test', 'scopes' => ['*']])->assertSessionHasErrors('scopes.0');
        $this->post('/portal/tokens', ['name' => 'Test', 'scopes' => ['orders:read']])->assertSessionHas('token');
        $this->assertSame(['orders:read'], $client->tokens()->first()->abilities);
    }

    public function test_admin_enters_decimal_money_and_invoices_keep_original_billing_data(): void
    {
        $client = $this->client();
        $admin = $this->admin();
        $order = app(CreateOrder::class)->handle($client, ['subject' => 'Caso de facturación', 'details' => 'Detalle suficiente', 'items' => [['service_code' => 'polygraph', 'quantity' => 3]]]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/portal/orders/'.$order->public_id.'/invoice', ['prices' => [$order->items->first()->id => '10.05'], 'tax_percent' => '18'])->assertRedirect();
        $invoice = $order->invoice()->first();
        $this->assertSame(3015, $invoice->subtotal);
        $this->assertSame(543, $invoice->tax);
        $this->assertSame(3558, $invoice->total);
        $this->post('/portal/invoices/'.$invoice->public_id.'/payments', ['amount' => '35.58', 'method' => 'cash', 'reference' => 'MONEY-TEST'])->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->actingAs($client)->put('/portal/account', ['name' => 'Nuevo nombre', 'company' => 'Nueva empresa', 'billing_email' => 'factura@example.com'])->assertRedirect();
        $this->assertSame('Nueva empresa', $client->account->fresh()->name);
        $this->assertNotSame('Nueva empresa', $invoice->fresh()->recipient['name']);
    }

    public function test_admin_can_invite_and_deactivate_users_but_cannot_remove_own_access(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/portal/users', ['name' => 'Colaborador', 'email' => 'staff@example.com', 'role' => 'collaborator'])->assertRedirect();
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $this->assertSame(Role::Collaborator, $staff->role);
        $staff->createToken('revoke', ['orders:read']);
        $this->patch('/portal/users/'.$staff->id, ['role' => 'collaborator', 'is_active' => 0])->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertSame(0, $staff->tokens()->count());
        $this->patch('/portal/users/'.$admin->id, ['role' => 'client', 'is_active' => 0])->assertUnprocessable();
    }
}
