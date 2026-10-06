<?php

namespace App\Services\Idecor;

use App\Models\ZonaOcupacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZonaSyncService
{
    public function __construct(
        private WfsClient $wfs,
    ) {}

    /**
     * Import all Ord. 8256 zones from IDECOR WFS.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importAll(): array
    {
        $layer = config('idecor.layers.zonas');
        $nonBuildable = config('idecor.non_buildable_zones');

        $imported = 0;
        $skipped = 0;

        foreach ($this->wfs->paginateFeatures($layer) as $features) {
            foreach ($features as $feature) {
                try {
                    $this->upsertZona($feature, $nonBuildable);
                    $imported++;
                } catch (\Throwable $e) {
                    Log::warning('Zone import failed', [
                        'fid' => $feature['properties']['fid'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }
        }

        return compact('imported', 'skipped');
    }

    private function upsertZona(array $feature, array $nonBuildable): void
    {
        $props = $feature['properties'];
        $fid = $props['fid'];
        $perfilOZona = trim($props['perfil_o_zona'] ?? '');

        // Parse pisos_maximos string: "Planta Baja + 4 pisos" -> 5
        $pisosMax = $this->parsePisos($props['pisos_maximos'] ?? '');

        // FOS comes as integer (70), convert to decimal (0.70)
        $fos = isset($props['fos']) && $props['fos'] > 0
            ? round($props['fos'] / 100, 2)
            : null;

        // FOT: some zones have null or 0
        $fot = isset($props['fot']) && $props['fot'] > 0
            ? round($props['fot'], 2)
            : null;

        // Parse retiro_fondo from otros_retiros text
        $retiroFondo = $this->parseRetiroFondo($props['otros_retiros'] ?? '');

        $esBuildable = ! in_array($perfilOZona, $nonBuildable) && ! empty($perfilOZona);

        // Build geometry from GeoJSON
        $geometry = $feature['geometry'];
        $geomWkt = $this->geojsonToWkt($geometry);

        $data = [
            'perfil_o_zona' => $perfilOZona,
            'zona_nombre' => $props['zona'] ?? null,
            'caracter_urbanistico' => $props['caracter_urbanistico'] ?? null,
            'fos' => $fos,
            'fot' => $fot,
            'altura_maxima' => ($props['altura_maxima'] ?? 0) > 0 ? $props['altura_maxima'] : null,
            'pisos_maximos' => $pisosMax,
            'pisos_maximos_raw' => $props['pisos_maximos'] ?? null,
            'retiro_frente' => $props['retiro_frente'] ?? null,
            'retiro_lateral' => $props['retiro_le'] ?? null,
            'retiro_fondo' => $retiroFondo,
            'otras_disposiciones' => $props['otras_disposiciones'] ?? null,
            'ord_original' => $props['ord_original'] ?? null,
            'ord_modificatoria' => $props['ord_modificatoria'] ?? null,
            'es_edificable' => $esBuildable,
            'actividad_industrial' => $props['actividad_industrial'] ?? null,
            'plano_a_45' => $props['plano_a_45'] ?? null,
        ];

        $now = now()->toDateTimeString();

        // Full raw SQL upsert (geom is NOT NULL, can't use Eloquent for initial insert)
        DB::statement(
            "INSERT INTO zonas_ocupacion
                (idecor_fid, perfil_o_zona, zona_nombre, caracter_urbanistico, fos, fot,
                 altura_maxima, pisos_maximos, pisos_maximos_raw, retiro_frente, retiro_lateral,
                 retiro_fondo, otras_disposiciones, ord_original, ord_modificatoria, es_edificable,
                 actividad_industrial, plano_a_45, geom, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), ?, ?)
             ON DUPLICATE KEY UPDATE
                perfil_o_zona = VALUES(perfil_o_zona),
                zona_nombre = VALUES(zona_nombre),
                caracter_urbanistico = VALUES(caracter_urbanistico),
                fos = VALUES(fos),
                fot = VALUES(fot),
                altura_maxima = VALUES(altura_maxima),
                pisos_maximos = VALUES(pisos_maximos),
                pisos_maximos_raw = VALUES(pisos_maximos_raw),
                retiro_frente = VALUES(retiro_frente),
                retiro_lateral = VALUES(retiro_lateral),
                retiro_fondo = VALUES(retiro_fondo),
                otras_disposiciones = VALUES(otras_disposiciones),
                ord_original = VALUES(ord_original),
                ord_modificatoria = VALUES(ord_modificatoria),
                es_edificable = VALUES(es_edificable),
                actividad_industrial = VALUES(actividad_industrial),
                plano_a_45 = VALUES(plano_a_45),
                geom = VALUES(geom),
                updated_at = VALUES(updated_at)",
            [
                $fid,
                $data['perfil_o_zona'],
                $data['zona_nombre'],
                $data['caracter_urbanistico'],
                $data['fos'],
                $data['fot'],
                $data['altura_maxima'],
                $data['pisos_maximos'],
                $data['pisos_maximos_raw'],
                $data['retiro_frente'],
                $data['retiro_lateral'],
                $data['retiro_fondo'],
                $data['otras_disposiciones'],
                $data['ord_original'],
                $data['ord_modificatoria'],
                $esBuildable ? 1 : 0,
                $data['actividad_industrial'],
                $data['plano_a_45'],
                $geomWkt,
                $now,
                $now,
            ]
        );
    }

    /**
     * Parse "Planta Baja + N pisos" -> total floors including PB.
     * "Planta Baja + 4 pisos" -> 5
     * "PB + 12 pisos" -> 13
     * "Planta Baja + 2 plantas" -> 3
     */
    public function parsePisos(string $raw): ?int
    {
        $raw = trim($raw);
        if (empty($raw)) {
            return null;
        }

        // Match patterns like "PB + 4", "Planta Baja + 12 pisos", "PB + 2 plantas"
        if (preg_match('/(\d+)\s*(pisos?|plantas?)?/i', $raw, $matches)) {
            $upperFloors = (int) $matches[1];

            // Add 1 for Planta Baja if the string mentions PB
            if (preg_match('/planta\s*baja|PB/i', $raw)) {
                return $upperFloors + 1;
            }

            return $upperFloors;
        }

        return null;
    }

    /**
     * Extract "Retiro de Fondo: 3m" from otros_retiros text.
     */
    private function parseRetiroFondo(string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // Try to extract just the fondo part
        if (preg_match('/(?:retiro\s*(?:de\s*)?fondo[:\s]*)([\d,.]+\s*m)/i', $text, $matches)) {
            return $matches[1];
        }

        // Return full text if it's relevant
        return strlen($text) > 0 ? $text : null;
    }

    /**
     * Convert GeoJSON geometry to WKT for MySQL.
     * Handles MultiPolygon -> POLYGON (takes first polygon for MySQL compatibility).
     */
    private function geojsonToWkt(array $geometry): string
    {
        $type = $geometry['type'];
        $coords = $geometry['coordinates'];

        if ($type === 'MultiPolygon') {
            // Take the first polygon (MySQL SPATIAL INDEX needs POLYGON, not MULTIPOLYGON for simple queries)
            $polygon = $coords[0];
            return 'POLYGON(' . $this->ringsToWkt($polygon) . ')';
        }

        if ($type === 'Polygon') {
            return 'POLYGON(' . $this->ringsToWkt($coords) . ')';
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
