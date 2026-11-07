<?php

namespace Tests\Feature;

use App\Console\Commands\ServePortalLocally;
use Tests\TestCase;

class LocalPortalServerTest extends TestCase
{
    public function test_child_php_receives_document_limits_and_laravel_still_handles_routing(): void
    {
        $command = new class extends ServePortalLocally
        {
            protected function host()
            {
                return '127.0.0.1';
            }

            protected function port()
            {
                return 8000;
            }

            public function childCommand(): array
            {
                return $this->serverCommand();
            }
        };
        $args = $command->childCommand();
        foreach (['upload_max_filesize=10M', 'post_max_size=24M', 'max_file_uploads=20', 'max_input_vars=2000'] as $setting) {
            $this->assertContains($setting, $args);
        }
        $this->assertContains('-S', $args);
        $this->assertContains('127.0.0.1:8000', $args);
        $this->assertStringEndsWith('server.php', end($args));
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('php artisan portal:serve', $composer['scripts']['dev'][1]);
        $this->assertStringContainsString('concurrently', $composer['scripts']['dev'][1]);
    }

    public function test_local_development_server_is_not_available_in_production(): void
    {
        config(['app.env' => 'production']);
        $this->artisan('portal:serve')->expectsOutputToContain('solo está disponible en el entorno local')->assertExitCode(1);
    }
}
