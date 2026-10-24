<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\AuthenticationLinks;
use App\Support\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalActivity extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    protected ?string $applicationUrl = null;

    public string $copy = 'Consulte el seguimiento de su caso en su espacio privado de All True Detection.';

    public string $actionLabel = 'Ver detalle';

    public ?string $reference = null;

    public function __construct(public string $title, public string $path = '/portal', string $copy = 'Consulte el seguimiento de su caso en su espacio privado de All True Detection.', string $actionLabel = 'Ver detalle', ?string $reference = null)
    {
        $this->copy = $copy;
        $this->actionLabel = $actionLabel;
        $this->reference = $reference;
        $this->applicationUrl = config('app.url');
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make($this->title, [$this->copy], $this->actionLabel, AuthenticationLinks::generator($this->applicationUrl)->to($this->path), $this->reference, $this->applicationUrl);
    }

    public function toDatabase(object $notifiable): array
    {
        return ['title' => $this->title, 'copy' => $this->copy, 'path' => $this->path, 'reference' => $this->reference];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof User || ($notifiable->is_active && $notifiable->hasVerifiedEmail());
    }
}
