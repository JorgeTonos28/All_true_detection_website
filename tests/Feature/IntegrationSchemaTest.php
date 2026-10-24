<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_schema_supports_private_monitoring_leases_and_file_integrity(): void
    {
        $this->assertTrue(Schema::hasColumns('api_request_logs', ['request_id', 'user_id', 'customer_account_id', 'token_id', 'route', 'status', 'duration_ms']));
        $this->assertFalse(Schema::hasColumn('api_request_logs', 'body'));
        $this->assertFalse(Schema::hasColumn('api_request_logs', 'token'));
        $this->assertTrue(Schema::hasColumns('notifications', ['notifiable_id', 'data', 'read_at']));
        $this->assertTrue(Schema::hasColumns('webhook_deliveries', ['processing_token', 'processing_until', 'replay_count']));
        $this->assertTrue(Schema::hasColumns('order_attachments', ['file_size', 'sha256', 'idempotency_key']));
        $this->assertTrue(Schema::hasColumns('order_results', ['file_size', 'sha256']));
    }
}
