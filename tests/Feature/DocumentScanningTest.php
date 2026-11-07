<?php

namespace Tests\Feature;

use App\Support\PrivateUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DocumentScanningTest extends TestCase
{
    public function test_clean_documents_are_scanned_without_using_a_shell_command_string(): void
    {
        config(['portal.document_scan_binary' => '/usr/bin/clamdscan']);
        Process::fake([Process::result(exitCode: 0)]);
        $file = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');
        PrivateUpload::validate($file);
        Process::assertRan(fn ($process) => $process->command === ['/usr/bin/clamdscan', '--no-summary', '--', $file->getPathname()]);
    }

    public function test_infected_documents_are_rejected(): void
    {
        config(['portal.document_scan_binary' => '/usr/bin/clamdscan']);
        Process::fake([Process::result(exitCode: 1)]);
        $this->expectException(ValidationException::class);
        PrivateUpload::validate(UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'));
    }

    public function test_production_does_not_silently_accept_unscanned_documents(): void
    {
        config(['portal.document_scan_binary' => null]);
        $this->app['env'] = 'production';
        $this->expectException(HttpException::class);
        PrivateUpload::validate(UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'));
    }
}
