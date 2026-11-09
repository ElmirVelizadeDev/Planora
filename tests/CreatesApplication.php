<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if ($app->environment('testing')) {
            $driver = config('database.default');
            $database = config("database.connections.{$driver}.database");

            if ($driver !== 'mysql' || ! is_string($database) || ! preg_match('/(^|[_-])test(?:ing)?($|[_-])/i', $database)) {
                throw new \RuntimeException(
                    'Tests require an isolated MySQL database whose name contains "test". Configure it in .env.testing; refusing to use a development database.'
                );
            }
        }

        return $app;
    }
}
