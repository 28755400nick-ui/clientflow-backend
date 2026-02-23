<?php

namespace Tests\Unit;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtService;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Tests\TestCase;

/**
 * Unit Tests for JwtService
 *
 * Tests the JWT generation, decoding, refresh token validation,
 * and revocation logic in isolation.
 */
class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    // ────────────────────────────────────────────────
    // generateAccessToken
    // ────────────────────────────────────────────────

    public function test_access_token_is_valid_jwt_string(): void
    {
        $user  = User::factory()->create();
        $token = $this->jwtService->generateAccessToken($user);

        // JWT has exactly 3 dot-separated parts
        $this->assertCount(3, explode('.', $token));
    }

    public function test_access_token_payload_contains_user_data(): void
    {
        $user    = User::factory()->create(['email' => 'test@example.com']);
        $token   = $this->jwtService->generateAccessToken($user);
        $payload = $this->jwtService->decodeToken($token);

        $this->assertEquals($user->id, $payload->sub);
        $this->assertEquals('test@example.com', $payload->email);
        $this->assertEquals('access', $payload->type);
    }

    public function test_access_token_expires_in_15_minutes(): void
    {
        $user    = User::factory()->create();
        $token   = $this->jwtService->generateAccessToken($user);
        $payload = $this->jwtService->decodeToken($token);

        $expectedExpiry = Carbon::now()->addMinutes(15)->timestamp;
        $this->assertEqualsWithDelta($expectedExpiry, $payload->exp, 5);
    }

    // ────────────────────────────────────────────────
    // generateRefreshToken
    // ────────────────────────────────────────────────

    public function test_refresh_token_is_stored_in_database(): void
    {
        $user = User::factory()->create();
        $this->jwtService->generateRefreshToken($user);

        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user->id]);
    }

    public function test_refresh_token_payload_has_type_refresh(): void
    {
        $user    = User::factory()->create();
        $token   = $this->jwtService->generateRefreshToken($user);
        $payload = $this->jwtService->decodeToken($token);

        $this->assertEquals('refresh', $payload->type);
    }

    public function test_generating_new_refresh_token_revokes_previous_ones(): void
    {
        $user = User::factory()->create();

        $this->jwtService->generateRefreshToken($user);
        $this->jwtService->generateRefreshToken($user);

        // Only one refresh token should exist at a time
        $this->assertEquals(1, RefreshToken::where('user_id', $user->id)->count());
    }

    public function test_refresh_token_has_unique_jti(): void
    {
        $user    = User::factory()->create();
        $token1  = $this->jwtService->generateRefreshToken($user);

        $user2   = User::factory()->create();
        $token2  = $this->jwtService->generateRefreshToken($user2);

        $payload1 = $this->jwtService->decodeToken($token1);
        $payload2 = $this->jwtService->decodeToken($token2);

        $this->assertNotEquals($payload1->jti, $payload2->jti);
    }

    // ────────────────────────────────────────────────
    // validateRefreshToken
    // ────────────────────────────────────────────────

    public function test_validate_refresh_token_returns_user_for_valid_token(): void
    {
        $user  = User::factory()->create();
        $token = $this->jwtService->generateRefreshToken($user);

        $resolved = $this->jwtService->validateRefreshToken($token);

        $this->assertNotNull($resolved);
        $this->assertEquals($user->id, $resolved->id);
    }

    public function test_validate_refresh_token_returns_null_for_access_token(): void
    {
        $user        = User::factory()->create();
        $accessToken = $this->jwtService->generateAccessToken($user);

        $result = $this->jwtService->validateRefreshToken($accessToken);

        $this->assertNull($result);
    }

    public function test_validate_refresh_token_returns_null_for_invalid_string(): void
    {
        $result = $this->jwtService->validateRefreshToken('invalid.token.string');

        $this->assertNull($result);
    }

    public function test_validate_refresh_token_returns_null_for_expired_token(): void
    {
        $user = User::factory()->create();

        // Manually insert an expired refresh token in the DB
        $token = $this->jwtService->generateRefreshToken($user);

        RefreshToken::where('user_id', $user->id)->update([
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $result = $this->jwtService->validateRefreshToken($token);

        $this->assertNull($result);
    }

    public function test_validate_refresh_token_returns_null_for_revoked_token(): void
    {
        $user  = User::factory()->create();
        $token = $this->jwtService->generateRefreshToken($user);

        // Manually delete the token from DB (simulating revocation)
        RefreshToken::where('user_id', $user->id)->delete();

        $result = $this->jwtService->validateRefreshToken($token);

        $this->assertNull($result);
    }

    // ────────────────────────────────────────────────
    // revokeRefreshToken / revokeAllUserTokens
    // ────────────────────────────────────────────────

    public function test_revoke_refresh_token_deletes_specific_token(): void
    {
        $user  = User::factory()->create();
        $token = $this->jwtService->generateRefreshToken($user);

        $this->assertDatabaseCount('refresh_tokens', 1);

        $this->jwtService->revokeRefreshToken($token);

        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_revoke_all_user_tokens_deletes_all_tokens_for_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->jwtService->generateRefreshToken($user1);
        $this->jwtService->generateRefreshToken($user2);

        $this->jwtService->revokeAllUserTokens($user1->id);

        $this->assertDatabaseMissing('refresh_tokens', ['user_id' => $user1->id]);
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user2->id]);
    }

    // ────────────────────────────────────────────────
    // decodeToken
    // ────────────────────────────────────────────────

    public function test_decode_token_throws_exception_for_tampered_token(): void
    {
        $user  = User::factory()->create();
        $token = $this->jwtService->generateAccessToken($user);

        // Tamper with the signature
        $parts    = explode('.', $token);
        $parts[2] = 'tampered_signature';
        $tampered = implode('.', $parts);

        $this->expectException(\Exception::class);
        $this->jwtService->decodeToken($tampered);
    }
}
