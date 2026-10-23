<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyAccountEmail extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct()
    {
        $this->afterCommit();
    }

    protected function buildMailMessage($url)
    {
        return (new MailMessage)->subject('Verifique su correo — All True Detection')->greeting('Hola')->line('Confirme su correo para acceder al portal y a sus solicitudes.')->action('Verificar mi correo', $url)->line('Si no creó esta cuenta, puede ignorar este mensaje.');
    }
}
