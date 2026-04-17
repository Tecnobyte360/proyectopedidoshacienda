<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.api.key', env('API_KEY'));

        if (empty($expected)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API_KEY no configurada en el servidor.',
            ], 500);
        }

        $provided = $request->header('X-API-KEY')
            ?? $request->header('Authorization')
            ?? $request->query('api_key');

        // Si viene como "Bearer xxxx"
        if (is_string($provided) && str_starts_with($provided, 'Bearer ')) {
            $provided = substr($provided, 7);
        }

        if (!hash_equals($expected, (string) $provided)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API key inválida o ausente.',
            ], 401);
        }

        return $next($request);
    }
}
