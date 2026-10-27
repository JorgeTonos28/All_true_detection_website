<?php

namespace Tests\Feature;

use App\Models\CustomerAccount;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiplePeopleSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_unicode_details_fit_after_encryption_without_truncation(): void
    {
        $user = User::factory()->create();
        $account = CustomerAccount::create(['name' => 'Cuenta sintética', 'billing_email' => 'capacity@example.test']);
        $details = str_repeat('🧪', 10000);
        $order = Order::create(['customer_account_id' => $account->id, 'requested_by' => $user->id, 'subject' => 'Capacidad sintética', 'details' => $details, 'consented_at' => now()]);
        $this->assertGreaterThan(65535, strlen(DB::table('orders')->where('id', $order->id)->value('details')));
        $this->assertSame($details, $order->fresh()->details);
    }

    public function test_nullable_details_multiple_people_and_cross_order_attachment_constraint(): void
    {
        $user = User::factory()->create();
        $account = CustomerAccount::create(['name' => 'Cuenta sintética', 'billing_email' => 'schema@example.test']);
        $orders = collect([1, 2])->map(fn ($i) => Order::create(['customer_account_id' => $account->id, 'requested_by' => $user->id, 'subject' => 'Caso '.$i, 'details' => null, 'consented_at' => now()]));
        $person = $orders[0]->people()->create(['profile' => PersonVerificationDomainTest::profile()]);
        $orders[0]->people()->create(['profile' => PersonVerificationDomainTest::profile()]);
        $this->assertCount(2, $orders[0]->people);
        $this->assertNull($orders[0]->fresh()->details);
        $this->expectException(QueryException::class);
        DB::table('order_attachments')->insert(['public_id' => '01TESTCROSSORDERPERSON00000', 'order_id' => $orders[1]->id, 'order_person_id' => $person->id, 'uploaded_by' => $user->id, 'file_path' => 'private/test', 'file_name' => 'test.png', 'mime_type' => 'image/png']);
    }
}
