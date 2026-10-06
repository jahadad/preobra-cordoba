<?php

namespace App\Console\Commands;

use App\Services\Idecor\ZonaSyncService;
use Illuminate\Console\Command;

class ImportZonasCommand extends Command
{
    protected $signature = 'preobra:import-zonas';
    protected $description = 'Import Ord. 8256 zones from IDECOR WFS';

    public function handle(ZonaSyncService $service): int
    {
        $this->info('Importing zonas from IDECOR WFS...');
        $this->info('Layer: ' . config('idecor.layers.zonas'));

        $startTime = microtime(true);

        try {
            $result = $service->importAll();
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $startTime, 1);

        $this->info("Done in {$elapsed}s");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Imported', $result['imported']],
                ['Skipped', $result['skipped']],
            ]
        );

        return self::SUCCESS;
    }
}
