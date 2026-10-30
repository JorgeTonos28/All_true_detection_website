<?php

namespace Tests\Support;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Identity\EnrollIdentity;
use App\Actions\Identity\ManagePartnerAccess;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\RequestIdentitySession;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\IdentitySession;
use App\Models\IdentitySubject;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProcessIdentitySession;
use App\Support\IntegrationScopes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\DiditProviderTest;
use Tests\Feature\PersonVerificationWorkflowTest;

class ReusableIdentityScenario
{
    public User $client;

    public User $admin;

    public Order $order;

    public IdentitySession $source;

    public string $token;

    public array $bodies = [];

    public array $creates = [];

    public bool $readFailure = false;

    public function __construct(public string $channel = 'api_credit')
    {
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        DiditProviderTest::configure();
        config(['identity.didit.authentication_workflow_id' => '33333333-3333-4333-8333-333333333333', 'identity.didit.reuse_privacy_approved' => true]);
        Setting::put('identity_verification_mode', 'didit');
        $this->client = app(CreateNewUser::class)->create(['name' => 'Synthetic Reuse', 'email' => Str::random(12).'@example.test', 'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123', 'terms' => true]);
        $this->client->forceFill(['email_verified_at' => now()])->save();
        $this->admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        app(ManagePartnerAccess::class)->handle($this->admin, $this->client->account, ['enabled' => true, 'daily_limit' => 20]);
        $token = $this->client->createToken('Synthetic reuse', IntegrationScopes::ALL);
        $this->token = $token->plainTextToken;
        $this->client->withAccessToken($token->accessToken);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/workflows/')) {
                return Http::response(['workflow_id' => config('identity.didit.authentication_workflow_id'), 'status' => 'published', 'is_archived' => false, 'features' => 'LIVENESS + FACE_MATCH',
                    'workflow_graph' => ['nodes' => ['liveness' => ['feature' => 'LIVENESS', 'config' => ['face_liveness_method' => 'ACTIVE_3D']]]]]);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/session/')) {
                $this->creates[] = $request->data();
                $id = (string) Str::uuid();
                $local = IdentitySession::where('public_id', $request['metadata']['atd_session_id'])->firstOrFail();
                $this->bodies[$id] = ['session_id' => $id, 'workflow_id' => $local->workflow_id, 'vendor_data' => $local->providerReference(), 'metadata' => $request['metadata'], 'environment' => 'sandbox', 'status' => 'Approved',
                    'id_verifications' => null, 'liveness_checks' => [['method' => 'ACTIVE_3D', 'status' => 'Approved']], 'face_matches' => [['status' => 'Approved', 'source_image_session_id' => $id]]];

                return Http::response(['session_id' => $id, 'workflow_id' => $local->workflow_id, 'vendor_data' => $local->providerReference(), 'metadata' => $request['metadata'], 'url' => 'https://verify.didit.me/session/synthetic'], 201);
            }
            foreach ($this->bodies as $id => $body) {
                if (str_ends_with($request->url(), '/session/'.$id.'/decision/')) {
                    if ($this->readFailure) {
                        return Http::response([], 503);
                    }

                    return Http::response($body);
                }
            }

            return Http::response([], 500);
        });
        $this->order = app(CreateOrder::class)->handle($this->client, PersonVerificationWorkflowTest::data(['identity-verification']));
        if ($channel === 'web') {
            IdentityWebPayment::settle($this->order, $this->admin);
        }
        $this->source = app(RequestIdentitySession::class)->handle($this->client, $this->order, $this->order->person, ['consent' => true, 'notice_version' => config('identity.notice_version')], $channel);
        $this->source->update(['provider_session_id' => (string) Str::uuid(), 'state' => 'awaiting_person']);
        $this->bodies[$this->source->provider_session_id] = DiditProviderTest::decision($this->source);
        app(ProcessIdentitySession::class)->handle($this->source->id);
        if ($channel === 'web') {
            app(UpdateOrder::class)->handle($this->admin, $this->order, ['status' => 'in_progress']);
            app(UpdateOrder::class)->handle($this->admin, $this->order, ['status' => 'completed']);
            app(PublishResult::class)->handle($this->admin, $this->order->results()->firstOrFail());
        }
        $this->source->refresh();
    }

    public function consent(): array
    {
        return ['consent' => true, 'notice_version' => config('identity.reuse_notice_version')];
    }

    public function enroll(): IdentitySubject
    {
        return app(EnrollIdentity::class)->handle($this->client, $this->source, $this->consent(), $this->channel);
    }
}
