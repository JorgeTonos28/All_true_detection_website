<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiplePeopleMigrationTest extends TestCase
{
    public function test_upgrade_preserves_historical_ciphertext_and_associates_existing_evidence(): void
    {
        // Deliberately isolated from both the configured DB and the shared MySQL test transaction.
        $previous = DB::getDefaultConnection();
        config(['database.connections.order_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('order_upgrade');
        try {
            Artisan::call('migrate', ['--database' => 'order_upgrade', '--force' => true]);
            $migration = require database_path('migrations/2026_10_26_000001_support_multiple_order_people.php');
            $migration->down();
            $userId = DB::table('users')->insertGetId(['name' => 'Ficticio', 'email' => 'upgrade@example.test', 'password' => 'not-a-login-hash', 'role' => 'client', 'is_active' => true]);
            $accountId = DB::table('customer_accounts')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => 'Cuenta ficticia', 'billing_email' => 'upgrade@example.test']);
            $orderId = DB::table('orders')->insertGetId(['public_id' => (string) Str::ulid(), 'customer_account_id' => $accountId, 'requested_by' => $userId, 'subject' => 'Caso anterior', 'details' => 'opaque-details-ciphertext', 'consented_at' => now()]);
            $serviceId = DB::table('services')->insertGetId(['code' => 'exequatur', 'name' => 'Exequátur', 'description' => 'Prueba', 'is_active' => true, 'sort_order' => 1]);
            DB::table('order_items')->insert(['order_id' => $orderId, 'service_id' => $serviceId, 'service_name' => 'Exequátur', 'quantity' => 1]);
            $personId = DB::table('order_people')->insertGetId(['order_id' => $orderId, 'profile' => 'opaque-profile-ciphertext']);
            DB::table('order_attachments')->insert(['public_id' => (string) Str::ulid(), 'order_id' => $orderId, 'uploaded_by' => $userId, 'file_path' => 'private/fictitious', 'file_name' => 'front.png', 'mime_type' => 'image/png', 'purpose' => 'identity_front']);
            $migration->up();
            $person = DB::table('order_people')->first();
            $this->assertSame('opaque-profile-ciphertext', $person->profile);
            $this->assertSame(1, $person->evidence_version);
            $this->assertSame(26, strlen($person->public_id));
            $this->assertSame($personId, DB::table('order_attachments')->value('order_person_id'));
            $this->assertSame($serviceId, DB::table('order_person_services')->where('order_person_id', $personId)->value('service_id'));
            $this->assertSame('opaque-details-ciphertext', DB::table('orders')->value('details'));
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('order_upgrade');
        }
    }
}
