<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $title, public string $path = '/portal')
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting('Hola')->line('Hay una actualización disponible en el portal de All True Detection.')->line('Para proteger su información, consulte los detalles iniciando sesión.')->action('Abrir portal', url($this->path));
    }
}
