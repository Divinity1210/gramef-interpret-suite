<?php
header('Content-Type: application/json');

$checks = [];
$checks['php_version'] = PHP_VERSION;

// Check if key files exist on the filesystem
$baseDir = __DIR__ . '/..';
$checks['files_exist'] = [
    'AuthController' => file_exists($baseDir . '/app/Http/Controllers/Api/Mobile/AuthController.php'),
    'Api_dir' => is_dir($baseDir . '/app/Http/Controllers/Api'),
    'Mobile_dir' => is_dir($baseDir . '/app/Http/Controllers/Api/Mobile'),
    'Controllers_listing' => is_dir($baseDir . '/app/Http/Controllers/Api/Mobile')
        ? scandir($baseDir . '/app/Http/Controllers/Api/Mobile')
        : 'DIR NOT FOUND',
];

// Test autoloader
$checks['class_exists_test'] = class_exists('App\\Http\\Controllers\\Api\\Mobile\\AuthController');

// Try manual require
if (!$checks['class_exists_test']) {
    $file = $baseDir . '/app/Http/Controllers/Api/Mobile/AuthController.php';
    if (file_exists($file)) {
        try {
            require_once $file;
            $checks['manual_require'] = 'OK - class now exists: ' . class_exists('App\\Http\\Controllers\\Api\\Mobile\\AuthController');
        } catch (\Throwable $e) {
            $checks['manual_require'] = 'FAILED: ' . $e->getMessage();
        }
    } else {
        $checks['manual_require'] = 'FILE NOT FOUND';
    }
}

try {
    require $baseDir . '/vendor/autoload.php';
    $app = require $baseDir . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $dummyRequest = Illuminate\Http\Request::create('/api/health-internal', 'GET');
    $kernel->handle($dummyRequest);

    $checks['laravel'] = 'OK';
    $checks['app_debug'] = config('app.debug');

    try {
        $pdo = $app->make('db')->connection()->getPdo();
        $checks['database'] = 'Connected';
    } catch (\Throwable $e) {
        $checks['database'] = 'FAILED: ' . $e->getMessage();
    }
} catch (\Throwable $e) {
    $checks['error'] = $e->getMessage();
}

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
