<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [

        // Stack: combines daily + stderr in production
        'stack' => [
            'driver' => 'stack',
            'channels' => env('APP_ENV') === 'production'
                ? ['daily', 'stderr']
                : ['single', 'stderr'],
            'ignore_exceptions' => false,
        ],

        // Single file (development)
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        // Daily rotation (production - keeps 14 days)
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'warning'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        // Stderr (for Docker log collection)
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'warning'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        // Audit log (business-critical events)
        'audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
            'days' => 90,
            'replace_placeholders' => true,
        ],

        // Payment log (financial transactions)
        'payment' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payment.log'),
            'level' => 'info',
            'days' => 365,
            'replace_placeholders' => true,
        ],

        // Null (discard)
        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Emergency (stdout fallback)
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
