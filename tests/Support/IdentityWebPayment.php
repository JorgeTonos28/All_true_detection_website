<?php

namespace Tests\Support;

use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

class IdentityWebPayment
{
    // Only test/preview fixtures call this; no payment processor or real money is involved.
    public static function settle(Order $order, ?User $admin = null): void
    {
        $admin ??= User::factory()->create(['role' => Role::Admin]);
        $prices = $order->items->mapWithKeys(fn ($item) => [$item->id => 100])->all();
        $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => $prices]);
        app(RecordPayment::class)->handle($admin, $invoice, ['amount' => $invoice->total, 'method' => 'cash', 'reference' => 'SYNTHETIC-WEB-PAYMENT']);
    }
}
