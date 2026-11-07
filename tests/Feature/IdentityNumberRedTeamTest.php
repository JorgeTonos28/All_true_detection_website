<?php

namespace Tests\Feature;

use App\Services\DiditDecisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ReusableIdentityScenario;
use Tests\TestCase;

class IdentityNumberRedTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_letters_or_extra_punctuation_cannot_be_discarded_to_match_a_dominican_id(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        foreach (['PREFIX00000000000', '00000000000SUFFIX', '+00000000000', '000.0000000.0'] as $number) {
            $body = $case->bodies[$case->source->provider_session_id];
            $body['id_verifications'][0]['personal_number'] = $number;
            $body['id_verifications'][0]['document_number'] = null;
            $this->assertSame('inconclusive', DiditDecisions::summarize($case->source, $body)['outcome'], $number);
        }
        $body['id_verifications'][0]['personal_number'] = '000-0000000-0';
        $this->assertSame('verified', DiditDecisions::summarize($case->source, $body)['outcome']);
    }
}
