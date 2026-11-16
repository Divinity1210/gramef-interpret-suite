<?php

/*
|--------------------------------------------------------------------------
| PHP Configuration Overrides
|--------------------------------------------------------------------------
|
| Override PHP settings to prevent timeouts and memory issues during
| authentication and database operations.
|
*/

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
ini_set('max_input_time', 300);

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

/*
|--------------------------------------------------------------------------
| Vercel Serverless: Redirect storage to /tmp (writable directory)
|--------------------------------------------------------------------------
|
| Vercel's filesystem is read-only except for /tmp. Laravel needs writable
| storage for compiled views, cache, sessions, and logs.
|
*/

if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
    // --- Storage path: /tmp/storage ---
    $storagePath = '/tmp/storage';
    foreach ([
        $storagePath,
        $storagePath . '/app',
        $storagePath . '/app/public',
        $storagePath . '/framework',
        $storagePath . '/framework/cache',
        $storagePath . '/framework/cache/data',
        $storagePath . '/framework/sessions',
        $storagePath . '/framework/views',
        $storagePath . '/logs',
    ] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    $app->useStoragePath($storagePath);

    // --- Bootstrap cache: redirect to /tmp (writable) via env vars ---
    $tmpBootstrapCache = '/tmp/bootstrap/cache';
    if (!is_dir($tmpBootstrapCache)) {
        mkdir($tmpBootstrapCache, 0755, true);
    }
    // Copy pre-generated cache files from the deployed (read-only) code
    $sourceCache = __DIR__ . '/cache';
    foreach (['packages.php', 'services.php'] as $cacheFile) {
        $src = $sourceCache . '/' . $cacheFile;
        $dst = $tmpBootstrapCache . '/' . $cacheFile;
        if (file_exists($src) && !file_exists($dst)) {
            copy($src, $dst);
        }
    }
    // Tell Laravel to look in /tmp for these cache files
    putenv('APP_SERVICES_CACHE=' . $tmpBootstrapCache . '/services.php');
    putenv('APP_PACKAGES_CACHE=' . $tmpBootstrapCache . '/packages.php');
    $_ENV['APP_SERVICES_CACHE'] = $tmpBootstrapCache . '/services.php';
    $_ENV['APP_PACKAGES_CACHE'] = $tmpBootstrapCache . '/packages.php';
}

return $app;
