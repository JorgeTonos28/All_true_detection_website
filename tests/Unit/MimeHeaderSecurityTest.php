<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Header\ParameterizedHeader;

class MimeHeaderSecurityTest extends TestCase
{
    public function test_mime_parameter_names_reject_non_tokens_including_a_trailing_newline(): void
    {
        foreach (["filename\n", "filename\r\nX-Injected", 'file name', 'file;name'] as $name) {
            try {
                new ParameterizedHeader('Content-Disposition', 'attachment', [$name => 'synthetic.pdf']);
                $this->fail('Un nombre de parámetro MIME no válido debe rechazarse.');
            } catch (RfcComplianceException) {
                $this->addToAssertionCount(1);
            }
        }
        $header = new ParameterizedHeader('Content-Disposition', 'attachment', ['filename' => 'synthetic.pdf']);
        $this->assertStringContainsString('filename=synthetic.pdf', $header->getBodyAsString());
    }
}
