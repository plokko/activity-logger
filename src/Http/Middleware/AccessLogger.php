<?php

namespace Plokko\ActivityLogger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Plokko\ActivityLogger\Facades\ActivityLog;
use Symfony\Component\HttpFoundation\Response;

class AccessLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Log request
        try {
            ActivityLog::logAccessRequest(
                $request,
                $response
            );
        } catch (\Throwable $error) {
            Log::error('An error occurred while saving access log: '.$error->getMessage(), compact('request', 'error'));
        }

        return $response;
    }
}
