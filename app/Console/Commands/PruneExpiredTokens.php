<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PruneExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Run with: php artisan tokens:prune-expired
     */
    protected $signature = 'tokens:prune-expired';

    /**
     * The console command description.
     */
    protected $description = 'Delete expired Sanctum personal access tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        $count = PersonalAccessToken::whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->delete();

        $this->info("Pruned {$count} expired tokens.");
        return Command::SUCCESS;
    }
}
