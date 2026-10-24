<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'portal:test-mail {to? : Destinatario de la prueba; por defecto usa la copia operativa} {--subject= : Asunto de la prueba}';

    protected $description = 'Envía un correo de prueba con la configuración actual y la copia operativa';

    public function handle(): int
    {
        $to = $this->argument('to') ?: config('mail.audit_bcc.0') ?: config('mail.from.address');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Define un destinatario válido o MAIL_AUDIT_BCC antes de probar el correo.');

            return self::INVALID;
        }

        $subject = $this->option('subject') ?: 'Prueba de correo ATD';

        Mail::raw(
            "Esta es una prueba de correo de All True Detection.\n\nMailer: ".config('mail.default')."\nFecha: ".now()->toIso8601String(),
            static function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            }
        );

        $this->info("Correo de prueba enviado a {$to} usando el mailer ".config('mail.default').'.');
        $this->line('La copia operativa se añade automáticamente mediante MAIL_AUDIT_BCC.');

        return self::SUCCESS;
    }
}
