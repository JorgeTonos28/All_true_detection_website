<?php

namespace App\Notifications;

use App\Support\AuthenticationLinks;
use App\Support\BrandedMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        return BrandedMail::make('Confirme su correo y continúe con ATD', ['Falta un paso para activar su cuenta: confirme que este correo le pertenece.', 'Después podrá solicitar servicios, seguir el avance de sus casos y consultar sus entregas.', 'Si no creó esta cuenta, ignore este mensaje.'], 'Verificar mi correo', $url, origin: $this->applicationUrl);
    }
}
