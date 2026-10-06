<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    /**
     * All zones as GeoJSON FeatureCollection.
     * Cached 1 hour (zones don't change).
     */
    public function zonas(): JsonResponse
    {
        $rows = DB::select("
            SELECT
                z.id,
                z.perfil_o_zona,
                z.zona_nombre,
                z.fos,
                z.fot,
                z.pisos_maximos,
                z.es_edificable,
                z.caracter_urbanistico,
                ST_AsGeoJSON(z.geom, 6) AS geojson
            FROM zonas_ocupacion z
        ");

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'id' => (int) $row->id,
                'geometry' => $this->fixGeometry($row->geojson),
                'properties' => [
                    'id' => (int) $row->id,
                    'perfil_o_zona' => $row->perfil_o_zona,
                    'zona_nombre' => $row->zona_nombre,
                    'fos' => $row->fos ? (float) $row->fos : null,
                    'fot' => $row->fot ? (float) $row->fot : null,
                    'pisos_maximos' => $row->pisos_maximos ? (int) $row->pisos_maximos : null,
                    'es_edificable' => (bool) $row->es_edificable,
                    'caracter_urbanistico' => $row->caracter_urbanistico,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Parcelas in viewport (bbox filter).
     * Returns GeoJSON FeatureCollection with embedded rinde data.
     */
    public function parcelas(Request $request): JsonResponse
    {
        $request->validate([
            'bbox' => 'required|string',
        ]);

        $parts = explode(',', $request->input('bbox'));
        if (count($parts) !== 4) {
            return response()->json(['error' => 'bbox must have 4 values: w,s,e,n'], 422);
        }

        // bbox from MapLibre: w(lng), s(lat), e(lng), n(lat)
        [$w, $s, $e, $n] = array_map('floatval', $parts);

        // Sanity: reasonable Cordoba-area bounds
        if ($w < -66 || $w > -62 || $e < -66 || $e > -62 || $s < -33 || $s > -30 || $n < -33 || $n > -30) {
            return response()->json(['error' => 'bbox out of Cordoba range'], 422);
        }

        if ($w >= $e || $s >= $n) {
            return response()->json(['error' => 'Invalid bbox dimensions'], 422);
        }

        // Data was imported with (lng, lat) WKT order, so envelope must match
        $envelope = "ST_GeomFromText('POLYGON(($w $s, $e $s, $e $n, $w $n, $w $s))', 4326)";

        $rows = DB::select("
            SELECT
                p.id,
                p.nomenclatura,
                p.designacion_oficial,
                p.superficie_tierra,
                p.superficie_mejoras,
                p.zona_ocupacion_id,
                z.perfil_o_zona,
                z.zona_nombre,
                z.fos,
                z.fot,
                z.pisos_maximos,
                z.es_edificable,
                r.superficie_terreno,
                r.fos_aplicado,
                r.fot_aplicado,
                r.pisos_max_aplicados,
                r.superficie_ocupable_planta,
                r.superficie_total_construible,
                r.pisos_efectivos,
                r.superficie_vendible,
                r.unidades_estimadas,
                r.modo_calculo,
                ST_AsGeoJSON(p.geom, 6) AS geojson
            FROM parcelas p
            LEFT JOIN zonas_ocupacion z ON z.id = p.zona_ocupacion_id
            LEFT JOIN rinde_cache r ON r.parcela_id = p.id
            WHERE ST_Intersects(p.geom, {$envelope})
              AND (z.es_edificable = 1 OR p.zona_ocupacion_id IS NULL)
            LIMIT 2000
        ");

        $features = [];
        foreach ($rows as $row) {
            $features[] = $this->parcelaFeature($row);
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Single parcela with full rinde details.
     */
    public function show(int $parcela): JsonResponse
    {
        $rows = DB::select("
            SELECT
                p.id,
                p.nomenclatura,
                p.designacion_oficial,
                p.superficie_tierra,
                p.superficie_mejoras,
                p.zona_ocupacion_id,
                z.perfil_o_zona,
                z.zona_nombre,
                z.fos,
                z.fot,
                z.pisos_maximos,
                z.es_edificable,
                z.altura_maxima,
                z.retiro_frente,
                z.retiro_lateral,
                z.retiro_fondo,
                r.superficie_terreno,
                r.fos_aplicado,
                r.fot_aplicado,
                r.altura_max_aplicada,
                r.pisos_max_aplicados,
                r.superficie_ocupable_planta,
                r.superficie_total_construible,
                r.pisos_efectivos,
                r.superficie_vendible,
                r.unidades_estimadas,
                r.modo_calculo,
                ST_AsGeoJSON(p.geom, 6) AS geojson
            FROM parcelas p
            LEFT JOIN zonas_ocupacion z ON z.id = p.zona_ocupacion_id
            LEFT JOIN rinde_cache r ON r.parcela_id = p.id
            WHERE p.id = ?
            LIMIT 1
        ", [$parcela]);

        if (empty($rows)) {
            return response()->json(['error' => 'Parcela not found'], 404);
        }

        $row = $rows[0];

        // Extract center for catastro link
        $geom = json_decode($row->geojson, true);
        $center = $this->extractFirstCoordinate($geom);

        return response()->json([
            'type' => 'Feature',
            'id' => (int) $row->id,
            'geometry' => $this->fixGeometry($row->geojson),
            'properties' => [
                'id' => (int) $row->id,
                'nomenclatura' => $row->nomenclatura,
                'designacion_oficial' => $row->designacion_oficial,
                'superficie_tierra' => $row->superficie_tierra ? (float) $row->superficie_tierra : null,
                'superficie_mejoras' => $row->superficie_mejoras ? (float) $row->superficie_mejoras : 0,
                'perfil_o_zona' => $row->perfil_o_zona,
                'zona_nombre' => $row->zona_nombre,
                'fos' => $row->fos ? (float) $row->fos : null,
                'fot' => $row->fot ? (float) $row->fot : null,
                'pisos_maximos' => $row->pisos_maximos ? (int) $row->pisos_maximos : null,
                'es_edificable' => $row->es_edificable !== null ? (bool) $row->es_edificable : null,
                'altura_maxima' => $row->altura_maxima ? (float) $row->altura_maxima : null,
                'retiro_frente' => $row->retiro_frente,
                'retiro_lateral' => $row->retiro_lateral,
                'retiro_fondo' => $row->retiro_fondo,
                'rinde' => $this->rindeProps($row),
                '_lng' => $center ? $center[0] : null,
                '_lat' => $center ? $center[1] : null,
            ],
        ]);
    }

    /**
     * Search parcelas by nomenclatura prefix or address (geocoded via Nominatim).
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json([]);
        }

        // Nomenclatura = pure digits (16-digit catastral code), e.g. "110101120"
        // Address = has letters, commas, or spaces mixed with digits, e.g. "4242 Saturnino Navarro"
        $isNomenclatura = preg_match('/^\d+$/', $q);

        if ($isNomenclatura) {
            return $this->searchByNomenclatura($q);
        }

        return $this->searchByAddress($q);
    }

    /**
     * Search by nomenclatura prefix (existing logic).
     */
    private function searchByNomenclatura(string $q): JsonResponse
    {
        $q = preg_replace('/[^a-zA-Z0-9\-]/', '', $q);

        $rows = DB::select("
            SELECT
                p.id,
                p.nomenclatura,
                p.designacion_oficial,
                ST_AsGeoJSON(p.geom, 6) AS geojson
            FROM parcelas p
            LEFT JOIN zonas_ocupacion z ON z.id = p.zona_ocupacion_id
            WHERE p.nomenclatura LIKE ?
              AND (z.es_edificable = 1 OR p.zona_ocupacion_id IS NULL)
            LIMIT 10
        ", [$q . '%']);

        $results = [];
        foreach ($rows as $row) {
            $geom = json_decode($row->geojson, true);
            $center = $this->extractFirstCoordinate($geom);

            $results[] = [
                'id' => (int) $row->id,
                'nomenclatura' => $row->nomenclatura,
                'designacion_oficial' => $row->designacion_oficial,
                'center' => $center,
                'source' => 'nomenclatura',
            ];
        }

        return response()->json($results);
    }

    /**
     * Search by address: geocode via Nominatim, then find parcelas at that point.
     */
    private function searchByAddress(string $q): JsonResponse
    {
        // Append ", Córdoba, Argentina" if not already present for better geocoding
        $searchQuery = $q;
        if (stripos($q, 'cordoba') === false && stripos($q, 'córdoba') === false) {
            $searchQuery .= ', Córdoba, Argentina';
        }

        // Call Nominatim geocoder (OSM, free, no auth)
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $searchQuery,
            'format' => 'json',
            'limit' => 5,
            'viewbox' => '-64.35,-31.55,-64.05,-31.30', // Cordoba city bounding box
            'bounded' => 1,
            'addressdetails' => 1,
        ]);

        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'header' => "User-Agent: PreObra/1.0 (contact@preobra.com)\r\nAccept-Language: es\r\n",
                'timeout' => 5,
            ],
        ]));

        if ($response === false) {
            return response()->json([]);
        }

        $places = json_decode($response, true);
        if (empty($places)) {
            return response()->json([]);
        }

        $results = [];

        foreach ($places as $place) {
            $lat = (float) $place['lat'];
            $lng = (float) $place['lon'];

            // Sanity check: must be within Cordoba bounds
            if ($lng < -64.5 || $lng > -63.8 || $lat < -31.6 || $lat > -31.2) {
                continue;
            }

            // Find the 3 closest parcelas to this geocoded point.
            // Nominatim points land on the street center, so the exact parcela
            // is ambiguous. Show top 3 nearest so the user can pick the right one.
            $point = "ST_GeomFromText('POINT($lng $lat)', 4326)";
            $buffer = 0.0003; // ~33m
            $w = $lng - $buffer;
            $e = $lng + $buffer;
            $s = $lat - $buffer;
            $n = $lat + $buffer;
            $envelope = "ST_GeomFromText('POLYGON(($w $s, $e $s, $e $n, $w $n, $w $s))', 4326)";

            $rows = DB::select("
                SELECT
                    p.id,
                    p.nomenclatura,
                    p.designacion_oficial,
                    p.superficie_tierra,
                    ST_AsGeoJSON(p.geom, 6) AS geojson,
                    ST_Distance(p.geom, {$point}) AS dist
                FROM parcelas p
                LEFT JOIN zonas_ocupacion z ON z.id = p.zona_ocupacion_id
                WHERE ST_Intersects(p.geom, {$envelope})
                  AND (z.es_edificable = 1 OR p.zona_ocupacion_id IS NULL)
                ORDER BY dist ASC
                LIMIT 3
            ");

            // Format the display name from Nominatim
            $displayName = $place['display_name'];
            // Trim to just the street part (before the city name)
            $parts = explode(',', $displayName);
            $shortName = implode(',', array_slice($parts, 0, min(3, count($parts))));

            foreach ($rows as $row) {
                $geom = json_decode($row->geojson, true);
                $center = $this->extractFirstCoordinate($geom);

                $results[] = [
                    'id' => (int) $row->id,
                    'nomenclatura' => $row->nomenclatura,
                    'designacion_oficial' => $row->designacion_oficial,
                    'superficie_tierra' => $row->superficie_tierra ? (float) $row->superficie_tierra : null,
                    'address' => trim($shortName),
                    'center' => $center ?: [$lng, $lat],
                    'source' => 'address',
                ];
            }

            // If no parcelas found at this location, still show the geocoded point
            if (empty($rows)) {
                $results[] = [
                    'id' => null,
                    'nomenclatura' => null,
                    'designacion_oficial' => null,
                    'address' => trim($shortName),
                    'center' => [$lng, $lat],
                    'source' => 'address_only',
                ];
            }
        }

        // Deduplicate by parcela id
        $seen = [];
        $unique = [];
        foreach ($results as $r) {
            $key = $r['id'] ?? ('addr_' . implode(',', $r['center']));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $r;
            }
        }

        return response()->json(array_slice($unique, 0, 10));
    }

    /**
     * Build a parcela GeoJSON feature from a DB row.
     */
    private function parcelaFeature(object $row): array
    {
        return [
            'type' => 'Feature',
            'id' => (int) $row->id,
            'geometry' => $this->fixGeometry($row->geojson),
            'properties' => [
                'id' => (int) $row->id,
                'nomenclatura' => $row->nomenclatura,
                'designacion_oficial' => $row->designacion_oficial,
                'superficie_tierra' => $row->superficie_tierra ? (float) $row->superficie_tierra : null,
                'superficie_mejoras' => $row->superficie_mejoras ? (float) $row->superficie_mejoras : 0,
                'perfil_o_zona' => $row->perfil_o_zona,
                'zona_nombre' => $row->zona_nombre,
                'fos' => $row->fos ? (float) $row->fos : null,
                'fot' => $row->fot ? (float) $row->fot : null,
                'pisos_maximos' => $row->pisos_maximos ? (int) $row->pisos_maximos : null,
                'es_edificable' => $row->es_edificable !== null ? (bool) $row->es_edificable : null,
                'rinde' => $this->rindeProps($row),
            ],
        ];
    }

    /**
     * Extract rinde properties from a joined DB row.
     */
    private function rindeProps(object $row): ?array
    {
        if (!$row->superficie_ocupable_planta) {
            return null;
        }

        return [
            'superficie_terreno' => (float) $row->superficie_terreno,
            'fos_aplicado' => (float) $row->fos_aplicado,
            'fot_aplicado' => $row->fot_aplicado ? (float) $row->fot_aplicado : null,
            'pisos_max_aplicados' => $row->pisos_max_aplicados ? (int) $row->pisos_max_aplicados : null,
            'superficie_ocupable_planta' => (float) $row->superficie_ocupable_planta,
            'superficie_total_construible' => (float) $row->superficie_total_construible,
            'pisos_efectivos' => (int) $row->pisos_efectivos,
            'superficie_vendible' => (float) $row->superficie_vendible,
            'unidades_estimadas' => (int) $row->unidades_estimadas,
            'modo_calculo' => $row->modo_calculo,
        ];
    }

    /**
     * Fix ST_AsGeoJSON coordinate order.
     *
     * MySQL ST_AsGeoJSON with SRID 4326 outputs [lat, lng] but GeoJSON spec
     * requires [lng, lat]. Swap every coordinate pair.
     */
    private function fixGeometry(string $geojsonStr): object
    {
        $geom = json_decode($geojsonStr, true);
        $geom['coordinates'] = $this->swapCoords($geom['coordinates']);

        return (object) $geom;
    }

    /**
     * Recursively swap [lat, lng] to [lng, lat] in coordinate arrays.
     */
    private function swapCoords(array $coords): array
    {
        if (isset($coords[0]) && is_numeric($coords[0])) {
            return [$coords[1], $coords[0]];
        }

        return array_map([$this, 'swapCoords'], $coords);
    }

    /**
     * Extract first coordinate pair from a GeoJSON geometry (in MySQL lat,lng order).
     * Returns [lng, lat] for MapLibre flyTo.
     */
    private function extractFirstCoordinate(array $geom): ?array
    {
        $coords = $geom['coordinates'] ?? null;
        if (!$coords) {
            return null;
        }

        $point = $coords;
        while (is_array($point) && isset($point[0]) && is_array($point[0])) {
            $point = $point[0];
        }

        // Swap: MySQL ST_AsGeoJSON gives [lat, lng], we need [lng, lat]
        if (is_array($point) && count($point) >= 2) {
            return [(float) $point[1], (float) $point[0]];
        }

        return null;
    }
}
