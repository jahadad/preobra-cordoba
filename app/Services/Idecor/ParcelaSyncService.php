<?php

namespace App\Services\Idecor;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParcelaSyncService
{
    public function __construct(
        private WfsClient $wfs,
    ) {}

    /**
     * Import parcelas from IDECOR WFS in bulk.
     *
     * @param string|null $cqlFilter  Additional CQL filter (e.g. "Superficie_Tierra_Urbana > 200")
     * @param int         $limit      Max parcelas to import (0 = unlimited)
     * @return array{imported: int, skipped: int, total: int}
     */
    public function importAll(?string $cqlFilter = null, int $limit = 0): array
    {
        $layer = config('idecor.layers.parcelas');
        $localidad = config('idecor.parcelas_localidad');

        $baseFilter = "localidad='{$localidad}'";
        if ($cqlFilter) {
            $baseFilter .= " AND {$cqlFilter}";
        }

        $imported = 0;
        $skipped = 0;
        $batchSize = 500;
        $batch = [];

        $wfsOptions = ['cqlFilter' => $baseFilter];

        // If limited, use single request instead of pagination
        if ($limit > 0) {
            $wfsOptions['maxFeatures'] = $limit;
        }

        $featurePages = $limit > 0
            ? [$this->wfs->getFeatures($layer, $wfsOptions)['features']]
            : $this->wfs->paginateFeatures($layer, $wfsOptions);

        foreach ($featurePages as $features) {
            foreach ($features as $feature) {
                $row = $this->featureToRow($feature);
                if ($row === null) {
                    $skipped++;
                    continue;
                }

                $batch[] = $row;

                if (count($batch) >= $batchSize) {
                    $imported += $this->insertBatch($batch);
                    $batch = [];
                }

                if ($limit > 0 && ($imported + $skipped) >= $limit) {
                    break 2;
                }
            }
        }

        // Flush remaining
        if (! empty($batch)) {
            $imported += $this->insertBatch($batch);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $imported + $skipped,
        ];
    }

    /**
     * Convert a WFS feature to a database row array.
     */
    private function featureToRow(array $feature): ?array
    {
        $props = $feature['properties'] ?? [];
        $nomenclatura = $props['Nomenclatura'] ?? null;

        if (empty($nomenclatura)) {
            return null;
        }

        $geometry = $feature['geometry'] ?? null;
        if (! $geometry) {
            return null;
        }

        $geomWkt = $this->geojsonToWkt($geometry);

        return [
            'nomenclatura' => $nomenclatura,
            'superficie_tierra' => $props['Superficie_Tierra_Urbana'] ?? null,
            'superficie_mejoras' => $props['Superficie_Mejoras'] ?? null,
            'valuacion' => $props['Valuacion'] ?? null,
            'designacion_oficial' => $props['desig_oficial'] ?? null,
            'nro_cuenta' => $props['Nro_Cuenta'] ?? null,
            'departamento' => $props['departamento'] ?? null,
            'localidad' => $props['localidad'] ?? null,
            'geom_wkt' => $geomWkt,
        ];
    }

    /**
     * Bulk insert parcelas using raw SQL for geometry handling.
     */
    private function insertBatch(array $rows): int
    {
        $now = now()->toDateTimeString();
        $inserted = 0;

        foreach ($rows as $row) {
            try {
                $wkt = $row['geom_wkt'];
                unset($row['geom_wkt']);

                DB::statement(
                    "INSERT INTO parcelas (nomenclatura, superficie_tierra, superficie_mejoras, valuacion, designacion_oficial, nro_cuenta, departamento, localidad, geom, synced_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                       superficie_tierra = VALUES(superficie_tierra),
                       superficie_mejoras = VALUES(superficie_mejoras),
                       valuacion = VALUES(valuacion),
                       synced_at = VALUES(synced_at),
                       updated_at = VALUES(updated_at)",
                    [
                        $row['nomenclatura'],
                        $row['superficie_tierra'],
                        $row['superficie_mejoras'],
                        $row['valuacion'],
                        $row['designacion_oficial'],
                        $row['nro_cuenta'],
                        $row['departamento'],
                        $row['localidad'],
                        $wkt,
                        $now,
                        $now,
                        $now,
                    ]
                );
                $inserted++;
            } catch (\Throwable $e) {
                Log::warning('Parcela insert failed', [
                    'nomenclatura' => $row['nomenclatura'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $inserted;
    }

    /**
     * Assign each parcela to its zone via spatial intersection.
     * Uses MySQL ST_Intersects with the centroid of the parcela against zone polygons.
     *
     * @return int Number of parcelas updated
     */
    public function assignZonas(): int
    {
        // Process in batches of 500 to avoid overloading MySQL with massive spatial joins.
        $totalAffected = 0;
        $batchSize = 500;

        do {
            $affected = DB::update("
                UPDATE parcelas p
                SET p.zona_ocupacion_id = (
                    SELECT z.id
                    FROM zonas_ocupacion z
                    WHERE ST_Intersects(p.geom, z.geom)
                    LIMIT 1
                )
                WHERE p.zona_ocupacion_id IS NULL
                LIMIT {$batchSize}
            ");

            $totalAffected += $affected;
            Log::info("assignZonas batch: {$affected} parcelas assigned (total: {$totalAffected})");

            // Brief pause between batches to let the DB breathe
            if ($affected > 0) {
                usleep(200000); // 200ms
            }
        } while ($affected > 0);

        return $totalAffected;
    }

    /**
     * Delete parcelas assigned to non-buildable zones (plazas, espacios verdes, etc.).
     * Also deletes their rinde_cache entries.
     * Run after assignZonas() to free DB space.
     *
     * @return int Number of parcelas deleted
     */
    public function purgeNonBuildable(): int
    {
        // Delete rinde_cache first (FK constraint)
        DB::delete("
            DELETE r FROM rinde_cache r
            INNER JOIN parcelas p ON p.id = r.parcela_id
            INNER JOIN zonas_ocupacion z ON z.id = p.zona_ocupacion_id
            WHERE z.es_edificable = 0
        ");

        // Delete parcelas in non-buildable zones
        $deleted = DB::delete("
            DELETE p FROM parcelas p
            INNER JOIN zonas_ocupacion z ON z.id = p.zona_ocupacion_id
            WHERE z.es_edificable = 0
        ");

        return $deleted;
    }

    private function geojsonToWkt(array $geometry): string
    {
        $type = $geometry['type'];
        $coords = $geometry['coordinates'];

        if ($type === 'MultiPolygon') {
            $polygon = $coords[0];
            return 'POLYGON(' . $this->ringsToWkt($polygon) . ')';
        }

        if ($type === 'Polygon') {
            return 'POLYGON(' . $this->ringsToWkt($coords) . ')';
        }

        if ($type === 'Point') {
            return "POINT({$coords[0]} {$coords[1]})";
        }

        throw new \RuntimeException("Unsupported geometry type: {$type}");
    }

    private function ringsToWkt(array $rings): string
    {
        $parts = [];
        foreach ($rings as $ring) {
            $points = [];
            foreach ($ring as $coord) {
                $points[] = $coord[0] . ' ' . $coord[1];
            }
            $parts[] = '(' . implode(', ', $points) . ')';
        }
        return implode(', ', $parts);
    }
}
