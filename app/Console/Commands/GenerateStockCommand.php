<?php

namespace App\Console\Commands;

use App\Models\Entry;
use App\Services\StockGenerationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stock:generate')]
#[Description('Generate stock in the portal system for entries with stock_generated=false')]
class GenerateStockCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(StockGenerationService $service): int
    {
        $entries = Entry::where('stock_generated', false)->whereNull('error')->orderBy('id')->get();

        if ($entries->isEmpty()) {
            $this->info('No pending entries to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$entries->count()} entr(y/ies)...");

        $succeeded = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            $this->info("Entry #{$entry->id} [{$entry->type}] {$entry->code} at {$entry->location_code}");

            $service->generate($entry);

            $entry->refresh();

            if ($entry->stock_generated) {
                $succeeded++;
                $this->info('  -> success');
            } else {
                $failed++;
                $this->error("  -> failed: {$entry->error}");
            }
        }

        $this->newLine();
        $this->info("Done. Succeeded: {$succeeded}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
