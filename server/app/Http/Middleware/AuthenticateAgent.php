<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Host;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public const REQUEST_ATTRIBUTE = 'nicewatch.host';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Missing bearer token'], Response::HTTP_UNAUTHORIZED);
        }

        $host = Host::query()->where('api_token', $token)->first();

        if ($host === null) {
            return response()->json(['message' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $host);

        return $next($request);
    }
}
