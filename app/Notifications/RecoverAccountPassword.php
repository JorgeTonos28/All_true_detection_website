<?php

namespace App\Notifications;

use App\Support\AuthenticationLinks;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RecoverAccountPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    protected ?string $applicationUrl = null;

    public function __construct(#[\SensitiveParameter] $token)
    {
        parent::__construct($token);
        $this->applicationUrl = config('app.url');
        $this->afterCommit();
    }

    protected function resetUrl($notifiable)
    {
        return AuthenticationLinks::generator($this->applicationUrl)->route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    protected function buildMailMessage($url)
    {
        return (new MailMessage)->subject('Establecer contraseña — All True Detection')->greeting('Hola')->line('Recibimos una solicitud para establecer o recuperar la contraseña de su cuenta.')->action('Establecer contraseña', $url)->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos.')->line('Si no solicitó este cambio ni una invitación a ATD, ignore este correo.');
    }
}
