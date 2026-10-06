<?php

namespace App\Console\Commands;

use App\Services\Idecor\ParcelaSyncService;
use App\Services\Rinde\RindeCalculator;
use Illuminate\Console\Command;

class AssignZonasCommand extends Command
{
    protected $signature = 'preobra:assign-zonas';
    protected $description = 'Assign zones to parcelas and calculate rinde (batched)';

    public function handle(ParcelaSyncService $syncService, RindeCalculator $calculator): int
    {
        $this->info('Assigning zones to parcelas (batched)...');
        $startTime = microtime(true);

        $assigned = $syncService->assignZonas();

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->info("Assigned {$assigned} parcelas to zones in {$elapsed}s");

        $this->info('Purging non-buildable parcelas...');
        $purged = $syncService->purgeNonBuildable();
        $this->info("Purged {$purged} non-buildable parcelas");

        $this->info('Calculating rinde...');
        $rindeStart = microtime(true);
        $calculated = $calculator->calcularMasivo();
        $rindeElapsed = round(microtime(true) - $rindeStart, 1);
        $this->info("Calculated rinde for {$calculated} parcelas in {$rindeElapsed}s");

        return self::SUCCESS;
    }
}
