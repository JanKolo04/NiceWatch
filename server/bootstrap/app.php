<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a reverse proxy (Traefik/nginx/Caddy) that terminates TLS and
        // forwards over plain HTTP. Trust the X-Forwarded-* headers so Laravel
        // knows the original request was HTTPS — otherwise it generates http://
        // asset URLs (Vite @vite) and the browser blocks them as mixed content
        // (→ no CSS), redirects break, and Secure cookies aren't set.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
