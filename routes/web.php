<?php

use App\Http\Controllers\Api\MapController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('map'))->name('map');

Route::prefix('api')->group(function () {
    Route::get('/zonas', [MapController::class, 'zonas']);
    Route::get('/parcelas/search', [MapController::class, 'search']);
    Route::get('/parcelas/{parcela}', [MapController::class, 'show']);
    Route::get('/parcelas', [MapController::class, 'parcelas']);
});

// Admin: trigger import (protected by secret token)
// Usage: /admin/import?token=...&bbox=-64.26,-31.40,-64.22,-31.35 (optional bbox to import specific area)
// Without bbox: imports ALL remaining parcelas
Route::get('/admin/import', function (\Illuminate\Http\Request $request) {
    if ($request->input('token') !== config('app.admin_token')) {
        abort(403);
    }

    $count = \App\Models\Parcela::count();

    // Build command with optional bbox filter
    $filterArg = '';
    if ($bbox = $request->input('bbox')) {
        $parts = array_map('floatval', explode(',', $bbox));
        if (count($parts) === 4) {
            [$w, $s, $e, $n] = $parts;
            // GeoServer BBOX function with explicit CRS handles reprojection
            $cql = "BBOX(geom,{$w},{$s},{$e},{$n},'EPSG:4326')";
            $filterArg = ' --filter="' . $cql . '"';
        }
    }

    $cmd = 'php ' . base_path('artisan') . ' preobra:import-parcelas' . $filterArg . ' --assign-zonas --calcular-rinde > /tmp/import.log 2>&1 &';
    exec($cmd);

    return response()->json([
        'status' => 'Import started in background',
        'parcelas_before' => $count,
        'filter' => $filterArg ?: 'none (full import)',
        'message' => 'Check /admin/status?token=... to monitor progress',
    ]);
});

// Assign zonas + calculate rinde for existing parcelas (batched, safe)
Route::get('/admin/assign-zonas', function (\Illuminate\Http\Request $request) {
    if ($request->input('token') !== config('app.admin_token')) {
        abort(403);
    }

    $before = \DB::selectOne('SELECT COUNT(*) as c FROM parcelas WHERE zona_ocupacion_id IS NOT NULL')->c;

    $cmd = 'php ' . base_path('artisan') . ' preobra:assign-zonas > /tmp/assign.log 2>&1 &';
    exec($cmd);

    return response()->json([
        'status' => 'Assign zonas + rinde started in background',
        'parcelas_with_zona_before' => (int) $before,
    ]);
});

Route::get('/admin/status', function (\Illuminate\Http\Request $request) {
    if ($request->input('token') !== config('app.admin_token')) {
        abort(403);
    }

    return response()->json([
        'parcelas' => \App\Models\Parcela::count(),
        'zonas' => \App\Models\ZonaOcupacion::count(),
        'rinde' => \App\Models\RindeCache::count(),
        'parcelas_with_zona' => \DB::selectOne('SELECT COUNT(*) as c FROM parcelas WHERE zona_ocupacion_id IS NOT NULL')->c,
    ]);
});
