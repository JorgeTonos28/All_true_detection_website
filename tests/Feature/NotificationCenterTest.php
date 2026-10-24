<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\PortalActivity;
use App\Notifications\RecoverAccountPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_private_actionable_and_mail_has_atd_branding(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);
        $client = User::factory()->create();
        $other = User::factory()->create();
        Notification::sendNow($client, new PortalActivity('Su entrega ATD está lista', '/portal/orders', 'El equipo publicó una entrega. Entre al portal para consultarla.', 'Consultar mi entrega', 'ATD-TEST'));
        $notice = $client->notifications()->firstOrFail();
        $mail = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertStringContainsString('brand-lockup-light.png', $mail->getHtmlBody());
        $this->assertStringContainsString('Consultar mi entrega', $mail->getHtmlBody());
        $this->assertStringNotContainsString('Hay una actualización disponible', $mail->getHtmlBody());
        $this->actingAs($other)->post('/portal/notifications/'.$notice->id.'/open')->assertNotFound();
        $this->actingAs($client)->get('/portal/notifications')->assertOk()->assertSee('Su entrega ATD está lista');
        $this->getJson('/portal/notifications/summary')->assertOk()->assertSee('1 sin atender');
        $this->post('/portal/notifications/'.$notice->id.'/open')->assertRedirect('/portal/orders');
        $this->assertNotNull($notice->fresh()->read_at);
        $this->assertSame(0, $client->unreadNotifications()->count());
    }

    public function test_mail_authentication_buttons_preserve_token_and_origin_with_custom_template(): void
    {
        config(['app.url' => 'https://atd.example.com']);
        $user = User::factory()->create();
        $mail = (new RecoverAccountPassword('sample-token'))->toMail($user);
        $this->assertStringContainsString('https://atd.example.com/reset-password/sample-token', $mail->actionUrl);
        $this->assertSame('mail.activity', $mail->view['html']);
        $this->assertSame('Establezca su acceso a ATD', $mail->subject);
    }

    public function test_staff_receives_new_case_notification_and_clients_receive_event_specific_copy(): void
    {
        Notification::fake();
        $this->seed();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $client = app(CreateNewUser::class)->create(['name' => 'Avisos', 'email' => 'avisos@example.com', 'password' => 'PasswordCliente123', 'password_confirmation' => 'PasswordCliente123', 'terms' => true]);
        $client->forceFill(['email_verified_at' => now()])->save();
        app(CreateOrder::class)->handle($client, ['subject' => 'Caso privado', 'details' => 'Detalle privado que no debe ir al correo', 'items' => [['service_code' => 'exequatur', 'quantity' => 1]]]);
        Notification::assertSentTo($admin, PortalActivity::class, fn ($notice) => $notice->title === 'Nueva solicitud por revisar');
        Notification::assertSentTo($client, PortalActivity::class, fn ($notice) => $notice->title === 'Recibimos su solicitud' && ! str_contains($notice->copy, 'Detalle privado'));
    }
}
