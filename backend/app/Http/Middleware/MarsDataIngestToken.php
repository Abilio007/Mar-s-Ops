<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarsDataIngestToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.mars.ingest_token');

        abort_if(! is_string($expectedToken) || $expectedToken === '', 503, 'Mars data ingestion is not configured.');
        abort_unless(
            hash_equals($expectedToken, (string) $request->header('X-Mars-Data-Token')),
            401,
            'A valid data-ingest token is required.',
        );

        return $next($request);
    }
}
