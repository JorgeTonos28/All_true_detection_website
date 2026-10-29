<?php

namespace Tests\Feature;

use App\Actions\Identity\CreateRecurringOrder;
use App\Actions\Identity\EnrollIdentity;
use App\Actions\Identity\RevokeIdentity;
use App\Actions\Orders\RequestIdentitySession;
use App\Actions\Orders\UpdateOrder;
use App\Services\IdentityReferences;
use App\Services\ProcessIdentitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IdentityWebPayment;
use Tests\Support\ReusableIdentityScenario;
use Tests\TestCase;

class ReusableIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_and_recurrent_capture_use_provider_reference_without_sending_id_or_images(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $subject = $case->enroll();
        $this->assertSame($subject->id, $case->enroll()->id);
        $this->assertEquals($case->source->consented_at->copy()->addDays(90), $subject->expires_at);
        $this->assertStringNotContainsString($subject->profile['identity_number'], DB::table('identity_subjects')->value('profile'));
        $this->assertArrayNotHasKey('provider_reference', $subject->toArray());
        $action = app(CreateRecurringOrder::class);
        $order = $action->handle($case->client, $subject, $case->consent(), 'recurring-synthetic-001', 'api_credit');
        $this->assertSame($order->id, $action->handle($case->client, $subject, $case->consent(), 'recurring-synthetic-001', 'api_credit')->id);
        $session = $order->person->identitySession;
        $this->assertSame('recurring', $session->verification_kind);
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('verified', $session->fresh()->decision['outcome']);
        $this->assertSame('completed', $order->fresh()->status->value);
        $this->assertCount(1, $case->creates);
        $this->assertSame($case->source->public_id, $case->creates[0]['vendor_data']);
        foreach (['portrait_image', 'expected_details', 'identity_number'] as $field) {
            $this->assertArrayNotHasKey($field, $case->creates[0]);
        }
        $this->assertSame($session->public_id, $case->creates[0]['metadata']['atd_session_id']);
        $this->assertSame($subject->expires_at->toIso8601String(), $subject->fresh()->expires_at->toIso8601String());
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_web_recurrent_order_still_requires_its_own_confirmed_payment(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario('web');
        $subject = $case->enroll();
        $order = app(CreateRecurringOrder::class)->handle($case->client, $subject, $case->consent(), 'web-recurring-synthetic');
        $this->assertNull($order->person->identitySession);
        try {
            app(RequestIdentitySession::class)->handle($case->client, $order, $order->person, $case->consent());
            $this->fail('Cada captura web necesita pago propio.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        IdentityWebPayment::settle($order, $case->admin);
        $session = app(RequestIdentitySession::class)->handle($case->client, $order, $order->person, $case->consent());
        app(ProcessIdentitySession::class)->handle($session->id);
        $this->assertSame('verified', $session->fresh()->decision['outcome']);
        $this->assertFalse($order->fresh()->canReleaseResults());
        $this->assertNull($order->results()->firstOrFail()->published_at);
    }

    public function test_reference_expiry_and_withdrawal_cannot_be_renewed_by_reenrollment(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $subject = $case->enroll();
        app(RevokeIdentity::class)->handle($case->client, $subject, true);
        $this->assertFalse(IdentityReferences::usable($subject->fresh()));
        $this->expectException(HttpException::class);
        app(EnrollIdentity::class)->handle($case->client, $case->source, $case->consent(), 'api_credit');
    }

    public function test_single_pending_capture_and_safe_cancellation_do_not_duplicate_remote_work(): void
    {
        $this->seed();
        $case = new ReusableIdentityScenario;
        $subject = $case->enroll();
        $action = app(CreateRecurringOrder::class);
        $order = $action->handle($case->client, $subject, $case->consent(), 'pending-capture-001', 'api_credit');
        try {
            $action->handle($case->client, $subject, $case->consent(), 'pending-capture-002', 'api_credit');
            $this->fail('No se permiten dos capturas pendientes de una referencia.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertDatabaseCount('orders', 2);
        app(UpdateOrder::class)->handle($case->admin, $order, ['status' => 'cancelled']);
        $other = $action->handle($case->client, $subject, $case->consent(), 'pending-capture-003', 'api_credit');
        $this->assertNotSame($order->id, $other->id);
        $this->assertCount(0, $case->creates);
    }
}
