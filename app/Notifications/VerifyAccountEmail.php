<?php

namespace App\Notifications;

use App\Support\AuthenticationLinks;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyAccountEmail extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    protected ?string $applicationUrl = null;

    public function __construct()
    {
        $this->applicationUrl = config('app.url');
        $this->afterCommit();
    }

    protected function verificationUrl($notifiable)
    {
        return AuthenticationLinks::generator($this->applicationUrl)->temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        );
    }

    protected function buildMailMessage($url)
    {
        return (new MailMessage)->subject('Verifique su correo — All True Detection')->greeting('Hola')->line('Confirme su correo para acceder al portal y a sus solicitudes.')->action('Verificar mi correo', $url)->line('Si no creó esta cuenta, puede ignorar este mensaje.');
    }
}
