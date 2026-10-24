<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonVerificationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_verification_schema_and_orphan_protection(): void
    {
        $this->assertTrue(Schema::hasTable('order_people'));
        $this->assertTrue(Schema::hasColumn('order_attachments', 'purpose'));
        $this->assertTrue(Schema::hasColumn('order_results', 'verification_findings'));
        $this->expectException(QueryException::class);
        DB::table('order_people')->insert(['order_id' => 987654, 'profile' => 'encrypted-placeholder']);
    }
}
