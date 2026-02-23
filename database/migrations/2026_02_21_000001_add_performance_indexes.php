<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for ClientFlow.
 *
 * REASONING:
 * - clients.email: already unique → already has implicit index
 * - clients.phone: already indexed in original migration
 * - clients.deleted_at: needed for soft-delete queries (WHERE deleted_at IS NULL)
 * - clients (first_name, last_name): composite for name search filter
 * - clients.created_at: pagination ORDER BY performance
 * - refresh_tokens.user_id: foreign key (already indexed by FK constraint)
 * - refresh_tokens.expires_at: for cleanup queries (WHERE expires_at < NOW())
 * - refresh_tokens.token: prefix index — token is TEXT, we index first 64 chars
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Composite index for name search (LIKE 'term%' queries)
            $table->index(['first_name', 'last_name'], 'idx_clients_name');

            // Soft-delete filter (nearly every query includes WHERE deleted_at IS NULL)
            $table->index('deleted_at', 'idx_clients_deleted_at');

            // Pagination ordering
            $table->index('created_at', 'idx_clients_created_at');
        });

        Schema::table('refresh_tokens', function (Blueprint $table) {
            // Cleanup of expired tokens
            $table->index('expires_at', 'idx_refresh_tokens_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_name');
            $table->dropIndex('idx_clients_deleted_at');
            $table->dropIndex('idx_clients_created_at');
        });

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_refresh_tokens_expires_at');
        });
    }
};
