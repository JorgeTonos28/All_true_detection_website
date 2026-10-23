<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RecoverAccountPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(#[\SensitiveParameter] $token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    protected function buildMailMessage($url)
    {
        return (new MailMessage)->subject('Establecer contraseña — All True Detection')->greeting('Hola')->line('Recibimos una solicitud para establecer o recuperar la contraseña de su cuenta.')->action('Establecer contraseña', $url)->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos.')->line('Si no solicitó este cambio ni una invitación a ATD, ignore este correo.');
    }
}
