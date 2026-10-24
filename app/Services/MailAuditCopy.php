<?php

namespace App\Services;

use Illuminate\Mail\Events\MessageSending;
use InvalidArgumentException;

class MailAuditCopy
{
    public function handle(MessageSending $event): void
    {
        foreach (config('mail.audit_bcc', []) as $address) {
            $address = trim((string) $address);

            if ($address === '') {
                continue;
            }

            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('MAIL_AUDIT_BCC contiene una dirección inválida.');
            }

            foreach ($event->message->getBcc() as $existing) {
                if (strcasecmp($existing->getAddress(), $address) === 0) {
                    continue 2;
                }
            }

            $event->message->addBcc($address);
        }
    }
}
