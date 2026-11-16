<?php

/**
 * Standalone PSR-4 autoloader for the web-push packages.
 *
 * This is intentionally separate from the main Composer autoload
 * so it can be uploaded to cPanel without touching vendor/composer/.
 *
 * Usage: require_once __DIR__ . '/webpush_autoload.php';
 */

spl_autoload_register(function ($class) {
    $vendorDir = dirname(__DIR__, 2) . '/vendor';

    // PSR-4 namespace => directory mappings
    $map = [
        'Minishlink\\WebPush\\' => $vendorDir . '/minishlink/web-push/src/',
        'Base64Url\\' => $vendorDir . '/spomky-labs/base64url/src/',
        'SpomkyLabs\\Pki\\' => $vendorDir . '/spomky-labs/pki-framework/src/',
        'Jose\\Component\\' => $vendorDir . '/web-token/jwt-library/',
    ];

    foreach ($map as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }

    return false;
});
