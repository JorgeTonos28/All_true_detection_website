<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;

class BrandedMail
{
    public static function make(string $title, array $lines, string $action, string $url, ?string $reference = null, ?string $origin = null): MailMessage
    {
        $settings = Setting::values();
        $generator = AuthenticationLinks::generator($origin);
        $logo = filled($settings['logo_path'] ?? null) ? $generator->to('/storage/'.$settings['logo_path']) : $generator->asset('assets/images/brand-lockup-light.png');
        $mail = (new MailMessage)->subject($title)->greeting('Hola')->action($action, $url);
        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail->view(['html' => 'mail.activity', 'text' => 'mail.activity-text'], [
            'heading' => $title, 'copyLines' => $lines, 'actionLabel' => $action, 'actionUrl' => $url,
            'reference' => $reference, 'companyName' => $settings['company_name'] ?? 'All True Detection',
            'logoUrl' => $logo, 'homeUrl' => $generator->to('/'), 'contactEmail' => $settings['email'] ?? config('mail.from.address'),
        ]);
    }
}
