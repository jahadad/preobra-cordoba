<?php

namespace App\Console\Commands;

use App\Services\Idecor\ParcelaSyncService;
use Illuminate\Console\Command;

class ImportParcelasCommand extends Command
{
    protected $signature = 'preobra:import-parcelas
        {--filter= : Additional CQL filter (e.g. "Superficie_Tierra_Urbana > 200")}
        {--limit=0 : Max parcelas to import (0 = unlimited)}
        {--assign-zonas : Run spatial join to assign zones after import}
        {--calcular-rinde : Calculate rinde after import and zone assignment}';

    protected $description = 'Import parcelas from IDECOR WFS for Cordoba Capital';

    public function handle(ParcelaSyncService $service): int
    {
        $filter = $this->option('filter');
        $limit = (int) $this->option('limit');

        $this->info('Importing parcelas from IDECOR WFS...');
        $this->info('Layer: ' . config('idecor.layers.parcelas'));
        $this->info('Localidad: ' . config('idecor.parcelas_localidad'));

        if ($filter) {
            $this->info("Filter: {$filter}");
        }
        if ($limit > 0) {
            $this->info("Limit: {$limit} parcelas");
        }

        $this->warn('This may take several minutes for large datasets. Be patient.');

        $startTime = microtime(true);

        try {
            $result = $service->importAll($filter, $limit);
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $startTime, 1);

        $this->info("Import done in {$elapsed}s");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Imported', $result['imported']],
                ['Skipped', $result['skipped']],
                ['Total processed', $result['total']],
            ]
        );

        // Spatial join
        if ($this->option('assign-zonas')) {
            $this->info('Running spatial join (assigning zones to parcelas)...');
            $joinStart = microtime(true);

            $assigned = $service->assignZonas();

            $joinElapsed = round(microtime(true) - $joinStart, 1);
            $this->info("Assigned {$assigned} parcelas to zones in {$joinElapsed}s");

            // Purge non-buildable parcelas (plazas, espacios verdes, etc.)
            $this->info('Purging non-buildable parcelas...');
            $purged = $service->purgeNonBuildable();
            $this->info("Purged {$purged} non-buildable parcelas");
        }

        // Calculate rinde
        if ($this->option('calcular-rinde')) {
            $this->info('Calculating rinde for all parcelas with assigned zones...');
            $rindeStart = microtime(true);

            $calculator = app(\App\Services\Rinde\RindeCalculator::class);
            $calculated = $calculator->calcularMasivo();

            $rindeElapsed = round(microtime(true) - $rindeStart, 1);
            $this->info("Calculated rinde for {$calculated} parcelas in {$rindeElapsed}s");
        }

        return self::SUCCESS;
    }
}
