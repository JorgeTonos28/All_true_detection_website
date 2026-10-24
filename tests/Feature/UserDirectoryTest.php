<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CustomerAccount;
use App\Models\User;
use App\Services\UserDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        return $admin;
    }

    public function test_any_word_can_match_different_user_and_account_fields_with_role_and_status_filters(): void
    {
        $this->admin();
        $account = CustomerAccount::create(['name' => 'Acme Industrial', 'billing_email' => 'billing@acme.example', 'tax_id' => 'RNCespecial', 'billing_address' => 'DireccionUnica']);
        $ana = User::factory()->create(['name' => 'Ana Robles', 'email' => 'ana@example.com', 'customer_account_id' => $account->id]);
        $staff = User::factory()->create(['name' => 'Pedro Castillo', 'email' => 'pedro@example.com', 'role' => Role::Collaborator]);
        $inactive = User::factory()->create(['name' => 'Lucia Robles', 'email' => 'lucia@example.com', 'is_active' => false]);
        User::factory()->create(['name' => 'Ninguna coincidencia', 'email' => 'otro@example.com']);

        $response = $this->getJson('/portal/users?q=ACME%20CASTILLO');
        $response->assertOk()->assertSee('ana@example.com')->assertSee('pedro@example.com')->assertDontSee('otro@example.com');
        $this->getJson('/portal/users?q=robles&status=inactive')->assertOk()->assertSee('lucia@example.com')->assertDontSee('ana@example.com');
        $this->getJson('/portal/users?q=robles%20castillo&role=collaborator')->assertOk()->assertSee('pedro@example.com')->assertDontSee('ana@example.com');
        $directory = app(UserDirectory::class);
        $this->assertSame([$ana->id], $directory->query('RNCespecial')->pluck('id')->all());
        $this->assertSame([$ana->id], $directory->query('DireccionUnica')->pluck('id')->all());
        $this->assertSame([$staff->id], $directory->query('Colaborador')->pluck('id')->all());
        $this->assertSame([$inactive->id], $directory->query('', null, 'inactive')->pluck('id')->all());
    }

    public function test_directory_paginates_and_treats_wildcards_as_literal_text_and_excludes_secrets(): void
    {
        $this->admin();
        User::factory()->count(30)->create(['name' => 'Directorio Ejemplo']);
        $special = User::factory()->create(['name' => 'Empresa 100%_!', 'google_id' => 'private-google-needle', 'two_factor_secret' => encrypt('private-totp-needle')]);
        $response = $this->get('/portal/users?q=Directorio');
        $response->assertOk()->assertViewHas('users', fn ($users) => $users->count() === 25 && $users->total() === 30);
        $this->assertStringContainsString('q=Directorio', $response->viewData('users')->nextPageUrl());
        $this->getJson('/portal/users?q=Directorio&page=2')->assertOk()->assertSee('Mostrando 26');
        $this->assertSame([$special->id], app(UserDirectory::class)->query('%_!')->pluck('id')->all());
        $this->assertSame(0, app(UserDirectory::class)->query('private-google-needle')->count());
        $this->assertSame(0, app(UserDirectory::class)->query('private-totp-needle')->count());
        $this->assertArrayNotHasKey('password', app(UserDirectory::class)->query()->first()->getAttributes());
        $this->getJson('/portal/users?q='.urlencode(str_repeat('x ', 13)))->assertUnprocessable();
        $this->getJson('/portal/users?role=unknown')->assertUnprocessable();
        $this->getJson('/portal/users?status=unknown')->assertUnprocessable();
    }

    public function test_user_details_and_account_lookup_are_admin_only_and_do_not_expose_credentials(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['name' => '<script>unsafe()</script>', 'google_id' => 'secret-google-id', 'two_factor_secret' => encrypt('secret-totp-value'), 'remember_token' => 'secret-remember-value']);
        $this->get('/portal/users/'.$member->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>unsafe()', false);
        $this->getJson('/portal/users/'.$member->id)->assertOk()->assertSee('Guardar acceso')->assertDontSee('secret-google-id')->assertDontSee('secret-totp-value')->assertDontSee('secret-remember-value');
        $this->getJson('/portal/users/accounts?q=x')->assertOk()->assertExactJson(['accounts' => []]);
        for ($i = 0; $i < 25; $i++) {
            CustomerAccount::create(['name' => 'Empresa Buscar '.$i, 'billing_email' => 'cuenta'.$i.'@example.com']);
        }
        $this->getJson('/portal/users/accounts?q=Empresa')->assertOk()->assertJsonCount(20, 'accounts');

        $client = User::factory()->create();
        $staff = User::factory()->create(['role' => Role::Collaborator, 'two_factor_confirmed_at' => now()]);
        foreach ([$client, $staff] as $actor) {
            $this->actingAs($actor);
            foreach (['/portal/users', '/portal/users/'.$admin->id, '/portal/users/accounts?q=Empresa'] as $url) {
                $this->getJson($url)->assertForbidden();
            }
        }
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => 0]);
        $this->getJson('/portal/users/'.$member->id)->assertStatus(423);
    }
}
