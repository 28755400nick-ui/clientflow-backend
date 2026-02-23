<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private int $accessTokenTtl;
    private int $refreshTokenTtl;

    public function __construct()
    {
        $this->secretKey      = config('jwt.secret');
        $this->accessTokenTtl  = config('jwt.access_ttl', 15);
        $this->refreshTokenTtl = config('jwt.refresh_ttl', 7);
    }

    public function generateAccessToken(User $user): string
    {
        $now = Carbon::now();

        $payload = [
            'iss'   => config('app.url'),
            'sub'   => $user->id,
            'email' => $user->email,
            'name'  => $user->name,
            'iat'   => $now->timestamp,
            'exp'   => $now->copy()->addMinutes($this->accessTokenTtl)->timestamp,
            'type'  => 'access',
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    public function generateRefreshToken(User $user): string
    {
        $now       = Carbon::now();
        $expiresAt = $now->copy()->addDays($this->refreshTokenTtl);

        $payload = [
            'iss'  => config('app.url'),
            'sub'  => $user->id,
            'iat'  => $now->timestamp,
            'exp'  => $expiresAt->timestamp,
            'type' => 'refresh',
            'jti'  => bin2hex(random_bytes(16)),
        ];

        $token = JWT::encode($payload, $this->secretKey, $this->algorithm);

        // Revocar tokens anteriores del usuario y guardar el nuevo
        RefreshToken::where('user_id', $user->id)->delete();

        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function decodeToken(string $token): object
    {
        return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
    }

    public function validateRefreshToken(string $token): ?User
    {
        try {
            $payload = $this->decodeToken($token);

            if ($payload->type !== 'refresh') {
                return null;
            }

            $stored = RefreshToken::where('token', $token)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (! $stored) {
                return null;
            }

            return User::find($payload->sub);
        } catch (Exception $e) {
            return null;
        }
    }

    public function revokeRefreshToken(string $token): void
    {
        RefreshToken::where('token', $token)->delete();
    }

    public function revokeAllUserTokens(int $userId): void
    {
        RefreshToken::where('user_id', $userId)->delete();
    }
}
