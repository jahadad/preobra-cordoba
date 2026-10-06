<?php

return [
    'wfs_base_url' => env('IDECOR_WFS_BASE_URL', 'https://idecor-ws.mapascordoba.gob.ar/geoserver/idecor'),

    'layers' => [
        'parcelas' => env('IDECOR_PARCELAS_LAYER', 'idecor:parcelas'),
        'zonas' => env('IDECOR_ZONAS_LAYER', 'idecor:CBA_Ordenanza_8256'),
    ],

    'parcelas_localidad' => env('IDECOR_PARCELAS_LOCALIDAD', 'CORDOBA'),

    // WFS pagination: max features per request
    'page_size' => (int) env('IDECOR_WFS_PAGE_SIZE', 1000),

    // Request coordinates in WGS84 so MySQL can handle them without reprojection
    'srs' => 'EPSG:4326',

    // Zones that are non-buildable (all params null, exclude from rinde)
    'non_buildable_zones' => ['EV', 'IS', 'RM', 'RF', 'RV', 'AE'],
];
