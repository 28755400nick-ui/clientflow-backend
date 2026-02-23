<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private JwtService $jwtService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        $accessToken  = $this->jwtService->generateAccessToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        return response()->json([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => config('jwt.access_ttl') * 60,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('refresh_token');

        if (! $token) {
            return response()->json([
                'message' => 'Refresh token no proporcionado.',
            ], 401);
        }

        $user = $this->jwtService->validateRefreshToken($token);

        if (! $user) {
            return response()->json([
                'message' => 'Refresh token inválido o expirado.',
            ], 401);
        }

        $this->jwtService->revokeRefreshToken($token);

        $accessToken     = $this->jwtService->generateAccessToken($user);
        $newRefreshToken = $this->jwtService->generateRefreshToken($user);

        return response()->json([
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => config('jwt.access_ttl') * 60,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->attributes->get('jwt_user_id');
        $this->jwtService->revokeAllUserTokens($userId);

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $userId = $request->attributes->get('jwt_user_id');
        $user   = User::find($userId);

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ]);
    }
}
