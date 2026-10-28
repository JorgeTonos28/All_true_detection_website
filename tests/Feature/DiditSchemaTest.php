<?php

namespace Tests\Feature;

use App\Models\CustomerAccount;
use App\Models\IdentitySession;
use App\Models\Order;
use App\Models\User;
use App\Services\IdentitySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiditSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_encrypt_capabilities_and_decisions_and_default_to_manual(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $account = CustomerAccount::create(['name' => 'Synthetic', 'billing_email' => 'synthetic@example.test']);
        $order = Order::create(['customer_account_id' => $account->id, 'requested_by' => $user->id, 'subject' => 'Synthetic', 'consented_at' => now()]);
        $person = $order->people()->create(['profile' => ['first_name' => 'Synthetic']]);
        $this->assertSame('manual', $person->fresh()->identity_mode);
        $session = IdentitySession::create(['order_id' => $order->id, 'order_person_id' => $person->id, 'application_id' => (string) Str::uuid(), 'workflow_id' => (string) Str::uuid(), 'environment' => 'sandbox', 'consented_at' => now(), 'notice_version' => 'synthetic', 'verification_url' => 'https://verify.didit.me/session/secret', 'provider_status' => 'Approved', 'decision' => ['outcome' => 'verified']]);
        $raw = DB::table('identity_sessions')->first();
        $this->assertStringNotContainsString('secret', $raw->verification_url);
        $this->assertStringNotContainsString('verified', $raw->decision);
        $this->assertStringNotContainsString('Approved', $raw->provider_status);
        $this->assertArrayNotHasKey('decision', $session->toArray());
        $this->assertArrayNotHasKey('verification_url', $session->toArray());
        $this->assertSame($person->id, $session->person->id);
        $this->assertSame('manual', IdentitySettings::mode());
        $this->assertFalse(IdentitySettings::ready());
    }
}
