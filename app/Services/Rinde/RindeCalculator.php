<?php

namespace App\Services\Rinde;

use App\Models\Parcela;
use App\Models\RindeCache;
use App\Models\ZonaOcupacion;
use Illuminate\Support\Facades\DB;

class RindeCalculator
{
    // Average sellable percentage after common areas (hallways, stairs, elevator)
    private const FACTOR_COMUNES = 0.75;

    // Average apartment size in m2 for unit estimation (Cordoba market)
    private const M2_PROMEDIO_DEPTO = 50;

    /**
     * Calculate rinde for a single parcela.
     *
     * @return array Calculation result
     */
    public function calcular(Parcela $parcela): array
    {
        $zona = $parcela->zona;

        if (! $zona || ! $zona->es_edificable) {
            return $this->noEdificable($parcela);
        }

        $superficie = (float) $parcela->superficie_tierra;
        if ($superficie <= 0) {
            return $this->noEdificable($parcela);
        }

        $fos = (float) $zona->fos;
        if ($fos <= 0) {
            return $this->noEdificable($parcela);
        }

        $plantaOcupable = $superficie * $fos;

        // Two calculation modes
        if ($zona->usaFot()) {
            return $this->calcularConFot($parcela, $zona, $superficie, $fos, $plantaOcupable);
        }

        return $this->calcularConPisos($parcela, $zona, $superficie, $fos, $plantaOcupable);
    }

    /**
     * Mode A: Zones WITH FOT (most residential zones)
     * FOT is the limiter: total_construible = superficie * fot
     */
    private function calcularConFot(
        Parcela $parcela,
        ZonaOcupacion $zona,
        float $superficie,
        float $fos,
        float $plantaOcupable,
    ): array {
        $fot = (float) $zona->fot;
        $totalConstruible = $superficie * $fot;

        // Effective floors: min between FOT-derived floors and pisos_max
        $pisosPorFot = (int) ceil($totalConstruible / $plantaOcupable);
        $pisosMax = $zona->pisos_maximos;

        $pisosEfectivos = $pisosMax ? min($pisosPorFot, $pisosMax) : $pisosPorFot;

        // Recalculate if pisos limit is binding
        if ($pisosMax && $pisosMax < $pisosPorFot) {
            $totalConstruible = $plantaOcupable * $pisosMax;
        }

        $vendible = $totalConstruible * self::FACTOR_COMUNES;
        $unidades = (int) floor($vendible / self::M2_PROMEDIO_DEPTO);

        return [
            'modo_calculo' => 'fot',
            'superficie_terreno' => $superficie,
            'zona_codigo' => $zona->perfil_o_zona,
            'fos_aplicado' => $fos,
            'fot_aplicado' => $fot,
            'altura_max_aplicada' => $zona->altura_maxima,
            'pisos_max_aplicados' => $pisosMax,
            'superficie_ocupable_planta' => round($plantaOcupable, 2),
            'superficie_total_construible' => round($totalConstruible, 2),
            'pisos_efectivos' => $pisosEfectivos,
            'superficie_vendible' => round($vendible, 2),
            'unidades_estimadas' => $unidades,
        ];
    }

    /**
     * Mode B: Zones WITHOUT FOT (corredores, centro, high-density)
     * Pisos is the limiter: total_construible = planta * pisos
     */
    private function calcularConPisos(
        Parcela $parcela,
        ZonaOcupacion $zona,
        float $superficie,
        float $fos,
        float $plantaOcupable,
    ): array {
        $pisosMax = $zona->pisos_maximos;

        if (! $pisosMax || $pisosMax <= 0) {
            return $this->noEdificable($parcela);
        }

        $totalConstruible = $plantaOcupable * $pisosMax;
        $vendible = $totalConstruible * self::FACTOR_COMUNES;
        $unidades = (int) floor($vendible / self::M2_PROMEDIO_DEPTO);

        return [
            'modo_calculo' => 'pisos',
            'superficie_terreno' => $superficie,
            'zona_codigo' => $zona->perfil_o_zona,
            'fos_aplicado' => $fos,
            'fot_aplicado' => null,
            'altura_max_aplicada' => $zona->altura_maxima,
            'pisos_max_aplicados' => $pisosMax,
            'superficie_ocupable_planta' => round($plantaOcupable, 2),
            'superficie_total_construible' => round($totalConstruible, 2),
            'pisos_efectivos' => $pisosMax,
            'superficie_vendible' => round($vendible, 2),
            'unidades_estimadas' => $unidades,
        ];
    }

    private function noEdificable(Parcela $parcela): array
    {
        return [
            'modo_calculo' => 'fot',
            'superficie_terreno' => (float) $parcela->superficie_tierra,
            'zona_codigo' => $parcela->zona?->perfil_o_zona ?? 'N/A',
            'fos_aplicado' => 0,
            'fot_aplicado' => 0,
            'altura_max_aplicada' => 0,
            'pisos_max_aplicados' => 0,
            'superficie_ocupable_planta' => 0,
            'superficie_total_construible' => 0,
            'pisos_efectivos' => 0,
            'superficie_vendible' => 0,
            'unidades_estimadas' => 0,
        ];
    }

    /**
     * Calculate and cache rinde for all parcelas that have a zona assigned.
     *
     * @return int Number of parcelas calculated
     */
    public function calcularMasivo(): int
    {
        $calculated = 0;

        Parcela::whereNotNull('zona_ocupacion_id')
            ->with('zona')
            ->chunk(1000, function ($parcelas) use (&$calculated) {
                $rows = [];

                foreach ($parcelas as $parcela) {
                    $result = $this->calcular($parcela);
                    $rows[] = array_merge($result, [
                        'parcela_id' => $parcela->id,
                        'calculated_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Bulk upsert
                foreach ($rows as $row) {
                    RindeCache::updateOrCreate(
                        ['parcela_id' => $row['parcela_id']],
                        $row,
                    );
                }

                $calculated += count($rows);
            });

        return $calculated;
    }
}
