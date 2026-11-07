<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_schema_migrates_on_an_empty_database(): void
    {
        foreach (['customer_accounts', 'services', 'orders', 'invoices', 'payments', 'order_results', 'conversations', 'messages', 'audit_events', 'idempotency_keys', 'webhook_endpoints', 'webhook_deliveries'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }
}
