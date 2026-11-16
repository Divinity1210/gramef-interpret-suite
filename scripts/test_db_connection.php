<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

// Bootstrap the Laravel application to load configuration and services
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Attempt a simple query to validate the database connection
    app('db')->connection()->select('select 1 as ok');
    echo "Laravel DB: connected\n";
    exit(0);
} catch (\Throwable $e) {
    echo "Laravel DB error: " . $e->getMessage() . "\n";
    exit(1);
}