<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

class CreateS3Logger
{
    public function __invoke(array $config)
    {
        $bucket = env('AWS_BUCKET');
        $region = env('AWS_DEFAULT_REGION');
        $key = env('AWS_ACCESS_KEY_ID');
        $secret = env('AWS_SECRET_ACCESS_KEY');
        $prefix = trim(env('AWS_PREFIX', ''), '/');
        $path = trim(env('LOG_S3_PATH', 'logs/laravel.log'), '/');
        $level = \Monolog\Logger::toMonologLevel(env('LOG_S3_LEVEL', 'warning'));

        if ($bucket && $region && $key && $secret) {
            $client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);
            $client->registerStreamWrapper();
            $keyPath = $prefix ? ($prefix . '/' . $path) : $path;
            $stream = 's3://' . $bucket . '/' . $keyPath;
            $handler = new StreamHandler($stream, $level);
            return new Logger('s3', [$handler]);
        }

        $fallback = storage_path('logs/laravel.log');
        $handler = app()->environment('production')
            ? new NullHandler($level)
            : new StreamHandler($fallback, $level);
        return new Logger('s3', [$handler]);
    }
}
