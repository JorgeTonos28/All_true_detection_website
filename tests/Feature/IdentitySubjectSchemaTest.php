<?php

namespace Tests\Feature;

use App\Models\IdentitySubject;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdentitySubjectSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_schema_is_additive_and_cannot_be_self_approved_by_mass_assignment(): void
    {
        $this->assertTrue(Schema::hasColumns('identity_subjects', ['source_identity_session_id', 'consented_at', 'expires_at', 'revoked_at']));
        $this->assertTrue(Schema::hasColumns('identity_sessions', ['verification_kind', 'provider_reference', 'provider_completed_at']));
        $this->assertTrue(Schema::hasColumn('order_people', 'identity_subject_id'));
        $this->expectException(MassAssignmentException::class);
        IdentitySubject::create(['customer_account_id' => 1, 'provider_reference' => 'untrusted']);
    }
}
