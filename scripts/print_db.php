<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'default=' . config('database.default') . PHP_EOL;
echo 'mysql.database=' . config('database.connections.mysql.database') . PHP_EOL;
echo 'db.name.query=';
try {
    $name = Illuminate\Support\Facades\DB::select('select database() as name');
    if ($name && isset($name[0]->name)) {
        echo $name[0]->name . PHP_EOL;
    } else {
        echo 'unknown' . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'error: ' . $e->getMessage() . PHP_EOL;
}