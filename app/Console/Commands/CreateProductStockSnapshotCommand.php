<?php

namespace App\Console\Commands;

use App\Services\ProductStockSnapshotService;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stock:snapshot {date : Stock generation date in YYYY-MM-DD format}')]
#[Description('Create a product stock snapshot from stock generation logs for a date')]
class CreateProductStockSnapshotCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProductStockSnapshotService $service): int
    {
        $dateInput = (string) $this->argument('date');

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $dateInput);
        } catch (InvalidFormatException) {
            $this->error('The date must be a valid date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        if ($date->format('Y-m-d') !== $dateInput) {
            $this->error('The date must be a valid date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $inserted = $service->create($date);

        $this->info("Created {$inserted} product stock snapshot row(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
