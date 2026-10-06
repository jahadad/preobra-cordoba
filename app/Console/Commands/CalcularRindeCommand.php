<?php

namespace App\Console\Commands;

use App\Services\Rinde\RindeCalculator;
use Illuminate\Console\Command;

class CalcularRindeCommand extends Command
{
    protected $signature = 'preobra:calcular-rinde';
    protected $description = 'Pre-calculate rinde (buildable potential) for all parcelas with assigned zones';

    public function handle(RindeCalculator $calculator): int
    {
        $this->info('Calculating rinde for all parcelas...');

        $startTime = microtime(true);

        try {
            $calculated = $calculator->calcularMasivo();
        } catch (\Throwable $e) {
            $this->error("Calculation failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $startTime, 1);

        $this->info("Done: {$calculated} parcelas calculated in {$elapsed}s");

        return self::SUCCESS;
    }
}
