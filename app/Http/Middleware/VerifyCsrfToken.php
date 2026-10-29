<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs excluded from CSRF verification.
     * Payment webhooks from external services don't include CSRF tokens.
     */
    protected $except = [
        'api/webhook/*',
        'api/v2/webhook/*',
    ];
}
