<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtService;
use Tests\TestCase;

/**
 * Auth Feature Tests
 *
 * Covers: login, refresh token, logout, /me endpoint.
 * Uses SQLite in-memory (phpunit.xml config) — no real DB needed.
 */
class AuthTest extends TestCase
{
    // ────────────────────────────────────────────────
    // POST /api/login
    // ────────────────────────────────────────────────

    public function test_login_with_valid_credentials_returns_tokens(): void
    {
        $user = User::factory()->create([
            'email'    => 'admin@clientflow.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'admin@clientflow.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ])
            ->assertJsonFragment(['token_type' => 'Bearer']);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email'    => 'admin@clientflow.com',
            'password' => bcrypt('correct_password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'admin@clientflow.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Credenciales inválidas.']);
    }

    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'nobody@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_with_missing_email_returns_422(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    public function test_login_with_missing_password_returns_422(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['password']]);
    }

    public function test_login_with_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_stores_refresh_token_in_database(): void
    {
        $user = User::factory()->create([
            'email'    => 'admin@clientflow.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/login', [
            'email'    => 'admin@clientflow.com',
            'password' => 'secret123',
        ]);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
        ]);
    }

    // ────────────────────────────────────────────────
    // POST /api/refresh
    // ────────────────────────────────────────────────

    public function test_refresh_with_valid_token_returns_new_tokens(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->postJson('/api/refresh', [
            'refresh_token' => $auth['refresh_token'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);

        // New refresh token should differ from original (token rotation)
        $this->assertNotEquals(
            $auth['refresh_token'],
            $response->json('refresh_token')
        );
    }

    public function test_refresh_with_invalid_token_returns_401(): void
    {
        $response = $this->postJson('/api/refresh', [
            'refresh_token' => 'totally.invalid.token',
        ]);

        $response->assertStatus(401);
    }

    public function test_refresh_with_access_token_returns_401(): void
    {
        // Access tokens must NOT be accepted by the refresh endpoint
        $auth = $this->actingWithJwt();

        $response = $this->postJson('/api/refresh', [
            'refresh_token' => $auth['access_token'],
        ]);

        $response->assertStatus(401);
    }

    public function test_refresh_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/refresh', []);

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Refresh token no proporcionado.']);
    }

    public function test_refresh_token_is_rotated_after_use(): void
    {
        $auth = $this->actingWithJwt();
        $oldToken = $auth['refresh_token'];

        // First refresh
        $this->postJson('/api/refresh', ['refresh_token' => $oldToken]);

        // Old token should be invalid now
        $second = $this->postJson('/api/refresh', [
            'refresh_token' => $oldToken,
        ]);

        $second->assertStatus(401);
    }

    // ────────────────────────────────────────────────
    // POST /api/logout
    // ────────────────────────────────────────────────

    public function test_logout_revokes_all_user_refresh_tokens(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->postJson('/api/logout', [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Sesión cerrada correctamente.']);

        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $auth['user']->id,
        ]);
    }

    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    // ────────────────────────────────────────────────
    // GET /api/me
    // ────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->getJson('/api/me', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email'])
            ->assertJsonFragment([
                'id'    => $auth['user']->id,
                'email' => $auth['user']->email,
            ]);
    }

    public function test_me_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Token no proporcionado.']);
    }

    public function test_me_with_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/api/me', [
            'Authorization' => 'Bearer invalid.token.here',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Token inválido o expirado.']);
    }

    public function test_me_with_refresh_token_instead_of_access_token_returns_401(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->getJson('/api/me', [
            'Authorization' => "Bearer {$auth['refresh_token']}",
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Tipo de token inválido.']);
    }
}
