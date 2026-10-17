<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredHost = parse_url(config('app.url'), PHP_URL_HOST);
        $localHost = app()->environment('local', 'native', 'testing') && in_array($request->getHost(), ['localhost', '127.0.0.1'], true);
        abort_unless($localHost || (is_string($configuredHost) && $request->getHost() === $configuredHost), 400, 'Host no autorizado.');
        Vite::useCspNonce();
        $response = $next($request);
        $nonce = Vite::cspNonce();
        $connect = app()->environment('local', 'native') ? "'self' http://localhost:5173 ws://localhost:5173" : "'self'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src $connect; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Request-ID', (string) Str::uuid());

        return $response;
    }
}
