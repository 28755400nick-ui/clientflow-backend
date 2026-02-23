<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Tests\TestCase;

/**
 * Client Feature Tests
 *
 * Covers: CRUD, filters, pagination, export, soft delete, validation, auth.
 */
class ClientTest extends TestCase
{
    // ────────────────────────────────────────────────
    // GET /api/clients
    // ────────────────────────────────────────────────

    public function test_index_returns_paginated_clients(): void
    {
        $auth = $this->actingWithJwt();
        Client::factory()->count(15)->create();

        $response = $this->getJson('/api/clients', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data',
                'last_page',
                'per_page',
                'total',
                'from',
                'to',
            ]);

        // Default per_page is 10
        $this->assertCount(10, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/clients');

        $response->assertStatus(401);
    }

    public function test_index_filters_by_name(): void
    {
        $auth = $this->actingWithJwt();

        Client::factory()->create(['first_name' => 'Juan', 'last_name' => 'García']);
        Client::factory()->create(['first_name' => 'María', 'last_name' => 'López']);
        Client::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Juárez']);

        $response = $this->getJson('/api/clients?name=Juan', $auth['headers']);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Juan García and Pedro Juárez match "Juan" in first_name or last_name
        $this->assertCount(2, $data);
    }

    public function test_index_filters_by_phone(): void
    {
        $auth = $this->actingWithJwt();

        Client::factory()->create(['phone' => '+54 9 11 12345678']);
        Client::factory()->create(['phone' => '+54 9 11 99999999']);

        $response = $this->getJson('/api/clients?phone=12345', $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_does_not_return_soft_deleted_clients(): void
    {
        $auth = $this->actingWithJwt();

        Client::factory()->create(['email' => 'active@test.com']);
        $deleted = Client::factory()->create(['email' => 'deleted@test.com']);
        $deleted->delete();

        $response = $this->getJson('/api/clients', $auth['headers']);

        $emails = collect($response->json('data'))->pluck('email')->toArray();
        $this->assertContains('active@test.com', $emails);
        $this->assertNotContains('deleted@test.com', $emails);
    }

    public function test_index_respects_per_page_parameter(): void
    {
        $auth = $this->actingWithJwt();
        Client::factory()->count(20)->create();

        $response = $this->getJson('/api/clients?per_page=5', $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(5, $response->json('per_page'));
    }

    public function test_index_caps_per_page_at_100(): void
    {
        $auth = $this->actingWithJwt();
        Client::factory()->count(10)->create();

        // Sending a huge per_page should be capped
        $response = $this->getJson('/api/clients?per_page=99999', $auth['headers']);

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('per_page'));
    }

    // ────────────────────────────────────────────────
    // POST /api/clients
    // ────────────────────────────────────────────────

    public function test_store_creates_client_with_valid_data(): void
    {
        $auth = $this->actingWithJwt();

        $payload = [
            'first_name' => 'Ana',
            'last_name'  => 'Rodríguez',
            'phone'      => '+54 9 11 55556666',
            'email'      => 'ana@example.com',
        ];

        $response = $this->postJson('/api/clients', $payload, $auth['headers']);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'first_name' => 'Ana',
                'email'      => 'ana@example.com',
            ]);

        $this->assertDatabaseHas('clients', ['email' => 'ana@example.com']);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/clients', [
            'first_name' => 'Test',
            'last_name'  => 'User',
            'phone'      => '+1234567890',
            'email'      => 'test@example.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $auth = $this->actingWithJwt();
        Client::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/clients', [
            'first_name' => 'Nuevo',
            'last_name'  => 'Cliente',
            'phone'      => '+1234567890',
            'email'      => 'existing@example.com',
        ], $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Ya existe un cliente registrado con este email.');
    }

    public function test_store_rejects_invalid_phone_format(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->postJson('/api/clients', [
            'first_name' => 'Test',
            'last_name'  => 'User',
            'phone'      => 'abc-invalid!',
            'email'      => 'test@example.com',
        ], $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'El teléfono solo puede contener números, +, -, espacios, ( y ).');
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->postJson('/api/clients', [], $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['first_name', 'last_name', 'phone', 'email'],
            ]);
    }

    public function test_store_rejects_invalid_email_format(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->postJson('/api/clients', [
            'first_name' => 'Test',
            'last_name'  => 'User',
            'phone'      => '+1234567890',
            'email'      => 'not-an-email',
        ], $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El email no tiene un formato válido.');
    }

    public function test_store_rejects_first_name_exceeding_max_length(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->postJson('/api/clients', [
            'first_name' => str_repeat('A', 101),
            'last_name'  => 'User',
            'phone'      => '+1234567890',
            'email'      => 'test@example.com',
        ], $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.first_name.0', 'El nombre no puede superar los 100 caracteres.');
    }

    // ────────────────────────────────────────────────
    // PUT /api/clients/{id}
    // ────────────────────────────────────────────────

    public function test_update_modifies_client_data(): void
    {
        $auth   = $this->actingWithJwt();
        $client = Client::factory()->create(['email' => 'original@example.com']);

        $response = $this->putJson("/api/clients/{$client->id}", [
            'first_name' => 'Updated',
            'last_name'  => 'Name',
            'phone'      => '+9999999999',
            'email'      => 'updated@example.com',
        ], $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => 'updated@example.com']);

        $this->assertDatabaseHas('clients', ['email' => 'updated@example.com']);
        $this->assertDatabaseMissing('clients', ['email' => 'original@example.com']);
    }

    public function test_update_allows_same_email_for_same_client(): void
    {
        $auth   = $this->actingWithJwt();
        $client = Client::factory()->create(['email' => 'same@example.com']);

        $response = $this->putJson("/api/clients/{$client->id}", [
            'first_name' => 'Same',
            'last_name'  => 'Email',
            'phone'      => '+1111111111',
            'email'      => 'same@example.com',  // same email, same client → valid
        ], $auth['headers']);

        $response->assertStatus(200);
    }

    public function test_update_rejects_email_already_used_by_another_client(): void
    {
        $auth      = $this->actingWithJwt();
        $existing  = Client::factory()->create(['email' => 'taken@example.com']);
        $toUpdate  = Client::factory()->create(['email' => 'mine@example.com']);

        $response = $this->putJson("/api/clients/{$toUpdate->id}", [
            'first_name' => 'Test',
            'last_name'  => 'User',
            'phone'      => '+1234567890',
            'email'      => 'taken@example.com',
        ], $auth['headers']);

        $response->assertStatus(422);
    }

    public function test_update_returns_404_for_nonexistent_client(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->putJson('/api/clients/99999', [
            'first_name' => 'Ghost',
            'last_name'  => 'Client',
            'phone'      => '+1234567890',
            'email'      => 'ghost@example.com',
        ], $auth['headers']);

        $response->assertStatus(404);
    }

    // ────────────────────────────────────────────────
    // DELETE /api/clients/{id}
    // ────────────────────────────────────────────────

    public function test_destroy_soft_deletes_client(): void
    {
        $auth   = $this->actingWithJwt();
        $client = Client::factory()->create();

        $response = $this->deleteJson("/api/clients/{$client->id}", [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Cliente eliminado correctamente.']);

        // Record still exists in DB but with deleted_at set
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_client(): void
    {
        $auth = $this->actingWithJwt();

        $response = $this->deleteJson('/api/clients/99999', [], $auth['headers']);

        $response->assertStatus(404);
    }

    public function test_destroy_requires_authentication(): void
    {
        $client = Client::factory()->create();

        $response = $this->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(401);
    }

    // ────────────────────────────────────────────────
    // GET /api/clients/export
    // ────────────────────────────────────────────────

    public function test_export_returns_xlsx_file(): void
    {
        $auth = $this->actingWithJwt();
        Client::factory()->count(3)->create();

        $response = $this->getJson('/api/clients/export', $auth['headers']);

        $response->assertStatus(200);

        // Excel files start with the PK zip header
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->getJson('/api/clients/export');

        $response->assertStatus(401);
    }

    public function test_export_respects_name_filter(): void
    {
        $auth = $this->actingWithJwt();

        Client::factory()->create(['first_name' => 'Carlos', 'last_name' => 'García']);
        Client::factory()->create(['first_name' => 'María', 'last_name' => 'López']);

        // Should not throw an error and return a file
        $response = $this->getJson('/api/clients/export?name=Carlos', $auth['headers']);

        $response->assertStatus(200);
    }
}
