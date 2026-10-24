<?php

namespace Tests\Unit;

use App\Services\MailAuditCopy;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailAuditCopyTest extends TestCase
{
    public function test_every_application_email_receives_the_configured_hidden_copy(): void
    {
        config(['mail.audit_bcc' => ['info@alltruedetection.com']]);

        Mail::raw('Mensaje de prueba', function ($message): void {
            $message->to('cliente@example.com');
        });

        $sent = Mail::mailer()->getSymfonyTransport()->messages()->last();
        $bcc = array_map(static fn (Address $address): string => $address->getAddress(), $sent->getOriginalMessage()->getBcc());

        $this->assertSame(['info@alltruedetection.com'], $bcc);
    }

    public function test_an_existing_hidden_copy_is_not_duplicated(): void
    {
        config(['mail.audit_bcc' => ['info@alltruedetection.com']]);
        $message = (new Email)->to('cliente@example.com')->bcc('info@alltruedetection.com');

        app(MailAuditCopy::class)->handle(new MessageSending($message));

        $this->assertCount(1, $message->getBcc());
    }

    public function test_invalid_hidden_copy_configuration_fails_closed(): void
    {
        config(['mail.audit_bcc' => ['not-an-email']]);

        $this->expectException(InvalidArgumentException::class);
        app(MailAuditCopy::class)->handle(new MessageSending(new Email));
    }
}
