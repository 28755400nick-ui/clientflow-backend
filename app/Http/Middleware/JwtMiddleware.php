<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function __construct(private JwtService $jwtService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Token no proporcionado.',
            ], 401);
        }

        try {
            $payload = $this->jwtService->decodeToken($token);

            if ($payload->type !== 'access') {
                return response()->json([
                    'message' => 'Tipo de token inválido.',
                ], 401);
            }

            $request->attributes->set('jwt_user_id', $payload->sub);
            $request->attributes->set('jwt_payload', $payload);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 401);
        }

        return $next($request);
    }
}
