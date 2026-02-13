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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /// Log request ///
        try {
            ActivityLog::logAccessRequest($request);
        } catch (\Throwable $error) {
            Log::error('An error occurred while saving access log: ' . $error->getMessage(), compact('request', 'error'));
        }

        /// Proceed with request ///
        return $next($request);
    }
}
