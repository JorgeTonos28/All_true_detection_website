<?php

namespace Tests\Support\ApiLab;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Identity\ManagePartnerAccess;
use App\Actions\Orders\PublishResult;
use App\Actions\Orders\StoreResult;
use App\Actions\Orders\UpdateOrder;
use App\Enums\Role;
use App\Models\IdentitySession;
use App\Models\IdentitySubject;
use App\Models\Order;
use App\Models\OrderResult;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\DiditClient;
use App\Services\ProcessIdentitySession;
use App\Support\IntegrationScopes;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** CLI/loopback infrastructure, never registered in the application. */
class Lab
{
    public static string $root;

    public static function boot(bool $initialize = false): Application
    {
        $base = dirname(__DIR__, 3);
        $parent = $base.'/storage/app/private/api-lab';
        $root = getenv('ATD_API_LAB_ROOT');
        if ($initialize && is_string($root) && preg_match('/^[a-zA-Z0-9_-]+$/D', basename($root))) {
            if (! is_dir($parent)) {
                mkdir($parent, 0700, true);
            }
            if (strtolower(dirname($root)) !== strtolower(realpath($parent)) || file_exists($root)) {
                throw new RuntimeException('El laboratorio requiere una carpeta nueva bajo storage/app/private/api-lab.');
            }
            mkdir($root, 0700);
            file_put_contents($root.'/marker.json', json_encode(['version' => 1, 'key' => 'base64:'.base64_encode(random_bytes(32))], JSON_THROW_ON_ERROR));
        }
        $resolved = is_string($root) ? realpath($root) : false;
        if (! $resolved || ! is_dir($parent) || strtolower(dirname($resolved)) !== strtolower(realpath($parent)) || ! is_file($resolved.'/marker.json')) {
            throw new RuntimeException('No hay un laboratorio aislado válido.');
        }
        self::$root = $resolved;
        $marker = json_decode(file_get_contents($resolved.'/marker.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($marker['version'] ?? null) !== 1 || empty($marker['key'])) {
            throw new RuntimeException('Marcador no válido.');
        }
        foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/private', 'app/public', 'provider', 'fixtures'] as $directory) {
            if (! is_dir($resolved.'/'.$directory)) {
                mkdir($resolved.'/'.$directory, 0700, true);
            }
        }
        $mock = getenv('ATD_API_LAB_PROVIDER') !== 'didit';
        $environment = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => $marker['key'],
            'APP_CONFIG_CACHE' => $resolved.'/config-cache.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $resolved.'/lab.sqlite',
            'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            'LOG_CHANNEL' => 'single', 'DIDIT_LOCAL_POLLING' => 'true'];
        if ($mock) {
            $environment += ['DIDIT_API_KEY' => 'synthetic-lab-only', 'DIDIT_WEBHOOK_SECRET' => 'synthetic-api-lab-signature-secret',
                'DIDIT_APPLICATION_ID' => '11111111-1111-4111-8111-111111111111', 'DIDIT_WORKFLOW_ID' => '22222222-2222-4222-8222-222222222222',
                'DIDIT_AUTHENTICATION_WORKFLOW_ID' => '33333333-3333-4333-8333-333333333333', 'DIDIT_ENVIRONMENT' => 'sandbox',
                'DIDIT_PRIVACY_APPROVED' => 'true', 'DIDIT_REUSE_PRIVACY_APPROVED' => 'true', 'DIDIT_PRODUCTION_APPROVED' => 'false'];
        }
        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        if ($initialize) {
            touch($resolved.'/lab.sqlite');
        }
        $app = require $base.'/bootstrap/app.php';
        $app->useStoragePath($resolved);
        $app->make(Kernel::class)->bootstrap();
        config(['cache.stores.file.path' => $resolved.'/framework/cache/data', 'cache.stores.file.lock_path' => $resolved.'/framework/cache/data',
            'session.cookie' => 'atd_api_lab', 'portal.document_scan_binary' => null, 'logging.channels.single.path' => $resolved.'/logs/lab.log']);
        if (DB::connection()->getDatabaseName() !== $resolved.'/lab.sqlite' || config('mail.default') !== 'array' || ! $app->environment('local')) {
            throw new RuntimeException('Falló el aislamiento.');
        }
        Notification::fake();
        if ($mock) {
            Http::preventStrayRequests();
            self::mockProvider();
        } elseif (config('identity.didit.environment') !== 'sandbox' || ! config('identity.didit.local_polling')) {
            throw new RuntimeException('El piloto externo requiere Didit Sandbox y polling local.');
        }

        return $app;
    }

    public static function seed(): array
    {
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        DB::statement('PRAGMA journal_mode=WAL');
        $admin = User::factory()->create(['name' => 'Administración sintética', 'email' => 'admin@api-lab.test', 'role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $manifest = ['root' => self::$root, 'tokens' => [], 'users' => [], 'notice' => config('identity.notice_version'), 'reuse_notice' => config('identity.reuse_notice_version')];
        foreach (['client', 'other', 'untrusted', 'inactive', 'unverified'] as $name) {
            $password = Str::password(24);
            $user = app(CreateNewUser::class)->create(['name' => 'Integrador sintético '.$name, 'email' => $name.'@api-lab.test', 'password' => $password, 'password_confirmation' => $password, 'terms' => true]);
            $user->forceFill(['email_verified_at' => $name === 'unverified' ? null : now(), 'is_active' => $name !== 'inactive'])->save();
            if (in_array($name, ['client', 'other'], true)) {
                app(ManagePartnerAccess::class)->handle($admin, $user->account, ['enabled' => true, 'daily_limit' => 100]);
            }
            $manifest['users'][$name] = $user->id;
            $manifest['tokens'][$name] = $user->createToken('API lab only', IntegrationScopes::ALL, now()->addHours(4))->plainTextToken;
        }
        $client = User::findOrFail($manifest['users']['client']);
        foreach (IntegrationScopes::ALL as $scope) {
            $manifest['tokens'][$scope] = $client->createToken('API lab '.$scope, [$scope], now()->addHours(4))->plainTextToken;
        }
        $manifest['tokens']['expired'] = $client->createToken('Expired lab', IntegrationScopes::ALL, now()->subMinute())->plainTextToken;
        $staff = User::factory()->create(['name' => 'Staff sintético', 'email' => 'staff@api-lab.test', 'role' => Role::Admin]);
        $manifest['tokens']['staff'] = $staff->createToken('Unsupported staff lab', IntegrationScopes::ALL)->plainTextToken;
        WebhookEndpoint::create(['customer_account_id' => $client->customer_account_id, 'created_by' => $client->id, 'url' => 'https://receiver.example.test/atd', 'secret' => Str::random(48), 'is_active' => true, 'events' => ['order.created', 'invoice.issued', 'payment.recorded', 'result.published', 'identity.verification.updated']]);
        file_put_contents(self::$root.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        return ['ok' => true, 'manifest' => self::$root.'/manifest.json'];
    }

    public static function operate(array $data): array
    {
        if (in_array($data['action'], ['provider', 'provider-behavior', 'expire-reference'], true) && getenv('ATD_API_LAB_PROVIDER') === 'didit') {
            throw new RuntimeException('Las mutaciones sintéticas no se admiten en el piloto externo.');
        }
        $admin = User::where('email', 'admin@api-lab.test')->firstOrFail();
        $order = isset($data['order_id']) ? Order::where('public_id', $data['order_id'])->firstOrFail() : null;
        switch ($data['action']) {
            case 'mode':
                Setting::put('identity_verification_mode', $data['mode'] === 'didit' ? 'didit' : 'manual');
                break;
            case 'clear-cache':
                Cache::flush();
                break;
            case 'invoice':
                $invoice = app(IssueInvoice::class)->handle($admin, $order, ['prices' => $order->items->mapWithKeys(fn ($item) => [$item->id => 1250])->all(), 'tax_basis_points' => $data['tax_basis_points'] ?? 0, 'due_date' => today()->subDay()->toDateString()]);

                return ['ok' => true, 'invoice_id' => $invoice->public_id, 'total' => $invoice->total];
            case 'result':
                $findings = [];
                foreach ($order->people()->with('services')->get() as $person) {
                    foreach ($person->services as $service) {
                        if ($service->code === 'identity-verification' && $person->identity_mode === 'didit') {
                            continue;
                        }
                        $findings[$person->public_id][$service->code] = ['outcome' => 'inconclusive', 'notes' => 'RESULTADO SINTÉTICO RESERVADO — sin revisión profesional real.'];
                    }
                }
                $file = ! empty($data['file']) ? new UploadedFile(self::$root.'/fixtures/result.pdf', 'result.pdf', 'application/pdf', null, true) : null;
                $result = app(StoreResult::class)->handle($admin, $order, 'ENTREGA SINTÉTICA RESERVADA', $file, $findings);

                return ['ok' => true, 'result_id' => $result->public_id];
            case 'complete':
                app(UpdateOrder::class)->handle($admin, $order, ['status' => 'in_progress']);
                app(UpdateOrder::class)->handle($admin, $order->fresh(), ['status' => 'completed']);
                break;
            case 'cancel':
                app(UpdateOrder::class)->handle($admin, $order, ['status' => 'cancelled']);
                break;
            case 'payment':
                app(RecordPayment::class)->handle($admin, $order->invoice()->firstOrFail(), ['amount' => $data['amount'] ?? $order->invoice->total, 'method' => 'cash', 'reference' => $data['reference'] ?? 'LAB-'.Str::ulid()]);
                break;
            case 'publish':
                app(PublishResult::class)->handle($admin, OrderResult::where('public_id', $data['result_id'])->firstOrFail());
                break;
            case 'partner':
                app(ManagePartnerAccess::class)->handle($admin, User::where('email', ($data['user'] ?? 'client').'@api-lab.test')->firstOrFail()->account, ['enabled' => $data['enabled'], 'daily_limit' => $data['limit'] ?? 100]);
                break;
            case 'provider':
                $session = $order->people()->firstOrFail()->identitySession()->firstOrFail();
                $path = self::$root.'/provider/'.$session->provider_session_id.'.json';
                $body = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
                $body['status'] = $data['status'];
                if (isset($data['method'])) {
                    $body['liveness_checks'][0]['method'] = $data['method'];
                }
                if (! empty($data['mismatch'])) {
                    $body['id_verifications'][0]['personal_number'] = '99999999999';
                }
                if (! empty($data['empty_checks'])) {
                    $body['liveness_checks'] = $body['face_matches'] = $body['id_verifications'] = [];
                }
                file_put_contents($path, json_encode($body, JSON_THROW_ON_ERROR), LOCK_EX);
                break;
            case 'provider-behavior':
                file_put_contents(self::$root.'/behavior.json', json_encode(array_intersect_key($data, array_flip(['create_timeout', 'read_failure', 'passive_workflow'])), JSON_THROW_ON_ERROR), LOCK_EX);
                break;
            case 'sync':
                foreach ($order->people()->with('identitySession')->get() as $person) {
                    if ($person->identitySession) {
                        app(ProcessIdentitySession::class)->handle($person->identitySession->id);
                    }
                }
                break;
            case 'provider-diagnostics':
                $session = $order->people()->firstOrFail()->identitySession()->firstOrFail();
                $body = app(DiditClient::class)->decision($session);
                $expected = $session->person->profile['identity_number'];

                return ['ok' => true, 'status' => $body['status'], 'environment' => $body['environment'],
                    'document_checks' => array_map(fn ($check) => ['status' => $check['status'] ?? null, 'country' => $check['issuing_state'] ?? null, 'method' => $check['verification_method'] ?? null,
                        'has_number' => filled($check['personal_number'] ?? null) || filled($check['document_number'] ?? null),
                        'dom_number_format' => collect(['personal_number', 'document_number'])->contains(fn ($key) => is_string($check[$key] ?? null) && preg_match('/^[0-9]{11}$/D', preg_replace('/[^0-9]/', '', $check[$key]))),
                        'number_matches_request' => collect(['personal_number', 'document_number'])->contains(fn ($key) => is_string($check[$key] ?? null) && preg_replace('/[^0-9]/', '', $check[$key]) === $expected)], $body['id_verifications'] ?? []),
                    'liveness_checks' => array_map(fn ($check) => ['status' => $check['status'] ?? null, 'method' => $check['method'] ?? null], $body['liveness_checks'] ?? []),
                    'face_checks' => array_map(fn ($check) => ['status' => $check['status'] ?? null], $body['face_matches'] ?? [])];
            case 'expire-reference':
                IdentitySubject::where('public_id', $data['identity_id'])->firstOrFail()->forceFill(['expires_at' => now()->subMinute()])->save();
                break;
            case 'snapshot':
                return ['ok' => true, 'orders' => Order::count(), 'sessions' => IdentitySession::count(), 'provider_creates' => count(glob(self::$root.'/provider/*.json')),
                    'files' => count(Storage::disk('local')->allFiles()), 'stored_file_rows' => DB::table('order_attachments')->whereNotNull('file_path')->count() + OrderResult::whereNotNull('file_path')->count(),
                    'foreign_key_violations' => count(DB::select('PRAGMA foreign_key_check')),
                    'webhooks' => WebhookDelivery::get()->map(fn ($d) => ['type' => $d->event_type, 'payload' => $d->payload])->all()];
            case 'envelope':
                $session = $order->people()->firstOrFail()->identitySession()->firstOrFail();
                $providerId = $session->provider_session_id;
                if (! $providerId) {
                    foreach (glob(self::$root.'/provider/*.json') as $path) {
                        $candidate = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
                        if (data_get($candidate, 'metadata.atd_session_id') === $session->public_id) {
                            $providerId = $candidate['session_id'];
                            break;
                        }
                    }
                }

                return ['ok' => true, 'body' => ['event_id' => (string) Str::uuid(), 'session_id' => $providerId, 'application_id' => $session->application_id, 'environment' => $session->environment,
                    'workflow_id' => $session->workflow_id, 'vendor_data' => $session->providerReference(), 'metadata' => ['atd_session_id' => $session->public_id], 'webhook_type' => 'status.updated', 'status' => 'Approved', 'timestamp' => now()->timestamp]];
            default:
                throw new RuntimeException('Acción de laboratorio desconocida.');
        }

        return ['ok' => true];
    }

    private static function mockProvider(): void
    {
        Http::fake(function ($request) {
            $base = 'https://verification.didit.me/v3';
            $behavior = is_file(self::$root.'/behavior.json') ? json_decode(file_get_contents(self::$root.'/behavior.json'), true, 32, JSON_THROW_ON_ERROR) : [];
            if ($request->method() === 'GET' && str_starts_with($request->url(), $base.'/workflows/')) {
                $id = basename(rtrim($request->url(), '/'));
                $recurring = $id === config('identity.didit.authentication_workflow_id');

                return Http::response(['workflow_id' => $id, 'status' => 'published', 'is_archived' => false, 'features' => ($recurring ? '' : 'OCR + ').'LIVENESS + FACE_MATCH',
                    'workflow_graph' => ['nodes' => ['liveness' => ['feature' => 'LIVENESS', 'config' => ['face_liveness_method' => empty($behavior['passive_workflow']) ? 'ACTIVE_3D' : 'PASSIVE']]]]]);
            }
            if ($request->method() === 'POST' && $request->url() === $base.'/session/') {
                $session = IdentitySession::where('public_id', $request['metadata']['atd_session_id'])->firstOrFail();
                $id = (string) Str::uuid();
                $body = ['session_id' => $id, 'workflow_id' => $session->workflow_id, 'vendor_data' => $session->providerReference(), 'metadata' => $request['metadata'], 'environment' => 'sandbox', 'status' => 'Not Started',
                    'url' => 'https://verify.didit.me/session/api-lab-synthetic', 'id_verifications' => $session->verification_kind === 'initial' ? [['status' => 'Approved', 'issuing_state' => 'DOM', 'verification_method' => 'document', 'personal_number' => $session->person->profile['identity_number']]] : [],
                    'liveness_checks' => [['status' => 'Approved', 'method' => 'ACTIVE_3D']], 'face_matches' => [['status' => 'Approved', 'source_image_session_id' => $id]]];
                file_put_contents(self::$root.'/provider/'.$id.'.json', json_encode($body, JSON_THROW_ON_ERROR), LOCK_EX);
                if (! empty($behavior['create_timeout'])) {
                    throw new ConnectionException('Synthetic accepted request with lost response.');
                }

                return Http::response($body, 201);
            }
            if ($request->method() === 'GET' && preg_match('~/session/([a-f0-9-]{36})/decision/$~D', $request->url(), $matches)) {
                if (! empty($behavior['read_failure'])) {
                    return Http::response([], 503);
                }
                $path = self::$root.'/provider/'.$matches[1].'.json';

                return Http::response(is_file($path) ? json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR) : [], is_file($path) ? 200 : 404);
            }

            throw new RuntimeException('Salida de red bloqueada en el laboratorio sintético.');
        });
    }
}
