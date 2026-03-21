<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ErrorLoggingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        try {
            $response = $next($request);
            
            // Log slow requests only (> 500ms) in development to reduce logging overhead
            if (app()->environment('local')) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                if ($executionTime > 500) {
                    Log::warning('Slow request detected', [
                        'method' => $request->method(),
                        'url' => $request->fullUrl(),
                        'status' => $response->getStatusCode(),
                        'execution_time' => $executionTime . 'ms',
                    ]);
                }
            }
            
            return $response;
            
        } catch (\Exception $e) {
            // Log detailed error information
            Log::error('Application Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'request_data' => $request->except(['password', 'password_confirmation']),
            ]);
            
            // Store error in database for monitoring
            try {
                DB::table('error_logs')->insert([
                    'error_type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => auth()->id(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_data' => json_encode($request->except(['password', 'password_confirmation'])),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $dbException) {
                Log::error('Failed to log error to database', [
                    'error' => $dbException->getMessage()
                ]);
            }
            
            // Re-throw the exception
            throw $e;
        }
    }
}
