<?php

namespace App\Console\Commands;

use App\Models\RefreshToken;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Scheduled cleanup of expired refresh tokens.
 *
 * Register in routes/console.php:
 *   Schedule::command('tokens:clean')->daily();
 *
 * Without this, the refresh_tokens table grows indefinitely.
 */
class CleanExpiredRefreshTokens extends Command
{
    protected $signature = 'tokens:clean';

    protected $description = 'Delete expired refresh tokens from the database';

    public function handle(): int
    {
        $deleted = RefreshToken::where('expires_at', '<', Carbon::now())->delete();

        $this->info("Deleted {$deleted} expired refresh token(s).");

        return self::SUCCESS;
    }
}
