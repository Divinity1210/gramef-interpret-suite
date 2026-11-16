<?php

// Normalize the request URI for Laravel
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

// Register PSR-4 fallback autoloader for App\ namespace
// (vercel-php uses --classmap-authoritative which skips PSR-4 fallback)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ── Serve static files directly from public/ (bypass Laravel for performance) ──
$uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$mimeTypes = [
    'css' => 'text/css',
    'js' => 'application/javascript',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'webp' => 'image/webp',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'eot' => 'application/vnd.ms-fontobject',
];

$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
if (isset($mimeTypes[$ext])) {
    $staticPath = __DIR__ . '/../public' . $uri;
    if (file_exists($staticPath) && is_file($staticPath)) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        header('Cache-Control: public, max-age=604800');
        readfile($staticPath);
        exit;
    }
    // Debug: if ?debug_static=1, show why the file wasn't found
    if (isset($_GET['debug_static'])) {
        header('Content-Type: application/json');
        $publicDir = __DIR__ . '/../public';
        echo json_encode([
            'requested_uri' => $uri,
            'static_path' => $staticPath,
            'file_exists' => file_exists($staticPath),
            'public_dir_exists' => is_dir($publicDir),
            'public_css_dir_exists' => is_dir($publicDir . '/css'),
            'public_dir_listing' => is_dir($publicDir) ? array_slice(scandir($publicDir), 0, 20) : 'NOT FOUND',
            'css_dir_listing' => is_dir($publicDir . '/css') ? scandir($publicDir . '/css') : 'NOT FOUND',
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

require __DIR__ . '/../public/index.php';
