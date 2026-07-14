<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! env('SIAFCO_MEASURE_PERFORMANCE', false)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        DB::enableQueryLog();

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $memory = round(
            (memory_get_peak_usage(true) - $startMemory) / 1024 / 1024,
            2
        );

        $queries = DB::getQueryLog();

        if ($duration > 500 || count($queries) > 30) {
            Log::warning('Slow request detected', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => optional($request->route())->getName(),
                'duration_ms' => $duration,
                'query_count' => count($queries),
                'memory_mb' => $memory,
                'user_id' => auth()->id(),
            ]);
        }

        return $response;
    }
}
