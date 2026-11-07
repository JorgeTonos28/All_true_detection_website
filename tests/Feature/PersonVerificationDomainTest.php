<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Support\PersonVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonVerificationDomainTest extends TestCase
{
    use RefreshDatabase;

    public static function profile(): array
    {
        return ['first_name' => 'Persona', 'last_name' => 'Ficticia', 'identity_number' => '000-0000000-0', 'address' => 'Dirección ficticia para pruebas', 'purpose' => 'Verificación de prueba autorizada', 'authorization_confirmed' => true];
    }

    public function test_profiles_are_encrypted_and_multiple_people_can_share_an_order(): void
    {
        Notification::fake();
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Cliente', 'email' => 'person@example.test', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'account_type' => 'individual', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $order = app(CreateOrder::class)->handle($user, ['subject' => 'Caso ficticio', 'details' => 'Detalle suficiente del caso', 'items' => [['service_code' => 'polygraph', 'quantity' => 1]]]);
        $person = $order->person()->create(['profile' => self::profile()]);
        $this->assertSame('Persona', $person->fresh()->profile['first_name']);
        $this->assertStringNotContainsString('Ficticia', DB::table('order_people')->value('profile'));
        $this->assertArrayNotHasKey('profile', $person->toArray());
        $other = $order->people()->create(['profile' => self::profile()]);
        $this->assertSame(2, $order->people()->count());
        $this->assertNotSame($person->public_id, $other->public_id);
    }

    public function test_conditional_fields_quantity_and_unknown_keys_are_rejected(): void
    {
        $items = [['service_code' => 'identity-verification', 'quantity' => 1]];
        $this->assertSame('00000000000', PersonVerification::profile(['items' => $items, 'person' => self::profile()])['identity_number']);
        foreach ([
            ['items' => [['service_code' => 'identity-verification', 'quantity' => 2]], 'person' => self::profile()],
            ['items' => [['service_code' => 'university-degree', 'quantity' => 1]], 'person' => self::profile()],
            ['items' => $items, 'person' => self::profile() + ['provider_decision' => 'approved']],
            ['items' => $items, 'person' => array_replace(self::profile(), ['authorization_confirmed' => false])],
        ] as $invalid) {
            try {
                PersonVerification::profile($invalid);
                $this->fail('Debe rechazar los datos no válidos.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }
}
