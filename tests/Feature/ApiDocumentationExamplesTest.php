<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApiDocumentationExamplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_documented_creation_payloads_are_accepted_and_public_docs_render(): void
    {
        Notification::fake();
        $this->seed();
        $client = app(CreateNewUser::class)->create(['name' => 'Documentación', 'email' => 'docs-examples@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        $headers = ['Authorization' => 'Bearer '.$client->createToken('Ejemplos', ['orders:read', 'orders:write'])->plainTextToken];
        $source = file_get_contents(base_path('docs/api.md'));
        preg_match_all('/```json\s*\n(.*?)\n```/s', $source, $blocks);
        $this->assertCount(2, $blocks[1]);
        foreach ($blocks[1] as $index => $body) {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $this->postJson('/api/v1/orders', $data, $headers + ['Idempotency-Key' => 'documentation-example-'.$index])->assertCreated();
        }
        preg_match('/--data "(.+)"$/m', $source, $curl);
        $this->assertNotEmpty($curl);
        $data = json_decode(str_replace('\\"', '"', $curl[1]), true, 512, JSON_THROW_ON_ERROR);
        $this->postJson('/api/v1/orders', $data, $headers + ['Idempotency-Key' => 'documentation-cmd-example'])->assertCreated();
        $this->get('/desarrolladores/api')->assertOk()->assertSee('people')->assertSee('degree_document')->assertSee('preview_url');
    }
}
