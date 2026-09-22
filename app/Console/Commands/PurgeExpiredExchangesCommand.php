<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Services\ExchangeService;
use Illuminate\Console\Command;

/**
 * Deletes every exchange past its expiration date: storage folder, files and
 * a purged_at tombstone (see ExchangeService::purge()). Scheduled daily in
 * routes/console.php.
 */
class PurgeExpiredExchangesCommand extends Command
{
    protected $signature = 'exchanges:purge';

    protected $description = 'Delete files and storage for exchanges past their expiration date';

    public function handle(ExchangeService $exchanges): int
    {
        $due = Exchange::query()->dueForPurge()->get();

        if ($due->isEmpty()) {
            $this->info('No exchanges due for purge.');

            return self::SUCCESS;
        }

        foreach ($due as $exchange) {
            $exchanges->purge($exchange);
            $this->info("Purged exchange {$exchange->code}.");
        }

        $this->info("Purged {$due->count()} exchange(s).");

        return self::SUCCESS;
    }
}
