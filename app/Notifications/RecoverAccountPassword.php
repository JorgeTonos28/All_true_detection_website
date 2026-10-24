<?php

namespace App\Notifications;

use App\Support\AuthenticationLinks;
use App\Support\BrandedMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        return BrandedMail::make('Establezca su acceso a ATD', ['Su cuenta está lista para establecer o recuperar su contraseña de acceso.', 'El enlace vence en '.config('auth.passwords.users.expire').' minutos.', 'Si no solicitó este cambio ni una invitación a ATD, ignore este correo.'], 'Establecer mi contraseña', $url, origin: $this->applicationUrl);
    }
}
