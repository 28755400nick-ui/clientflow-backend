<?php

namespace Tests;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Create a user and return a valid JWT access token + headers.
     * Use in Feature tests to authenticate requests.
     */
    protected function actingWithJwt(?User $user = null): array
    {
        $user ??= User::factory()->create();

        $jwtService   = app(JwtService::class);
        $accessToken  = $jwtService->generateAccessToken($user);
        $refreshToken = $jwtService->generateRefreshToken($user);

        return [
            'user'          => $user,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'headers'       => ['Authorization' => "Bearer {$accessToken}"],
        ];
    }

    /**
     * Return Authorization header for a raw token string.
     */
    protected function bearerHeader(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }
}
