/**
 * Zone polygons layer: fetch all zones once, color by type, hover tooltip.
 */

// Exact match colors for known zone codes
const ZONE_COLORS = {
    // Residential density gradient (low to high)
    'A': '#c8e6c9', 'A1': '#b9debb',
    'B': '#a5d6a7',
    'C1': '#81c784', 'C2': '#72be76', 'C3': '#66bb6a', 'C4': '#5cb860', 'C5': '#4caf50',
    'D': '#43a047',
    'E': '#388e3c', 'EÑ': '#357a38', 'EO': '#347036', 'EP': '#326834',
    'EQ': '#2f6030', 'ES': '#2d582d', 'ET': '#2b502b', 'EU': '#294929',
    'F': '#1b5e20', 'F1': '#1e6b24', 'F2': '#217828', 'F3': '#24852c', 'F4': '#279230', 'F5': '#2a9f34',
    // Corridors (blues)
    'G1': '#90caf9', 'G2': '#64b5f6', 'G3': '#42a5f5',
    // Mid-density / mixed (teals)
    'H1': '#80cbc4', 'H2': '#4db6ac',
    // Special / planned areas (purples)
    'J1': '#ce93d8', 'J2': '#ba68c8',
    'K': '#ab47bc', 'M': '#9c27b0', 'N': '#8e24aa', 'N1': '#7b1fa2',
    'V': '#7e57c2',
    // Roman numeral zones (amber/orange gradient)
    'I': '#ffe082', 'II': '#ffd54f', 'III': '#ffca28', 'IV': '#ffc107',
    'IX': '#ffb300', 'X': '#ffa000',
    'XXV': '#ff8f00', 'XXVI': '#ff6f00',
    'VI': '#e8a855', 'VII': '#e09540', 'VIII': '#d4843a',
    // PA zones (rose/pink)
    'PA1': '#f48fb1', 'PA2': '#f06292', 'PA4': '#ec407a', 'PA5': '#e91e63',
    'PA6': '#d81b60', 'PA7': '#c2185b', 'PA8': '#ad1457', 'PA9': '#880e4f',
    'PA10': '#e57395', 'PA11': '#d4648a', 'PA12': '#c3557f',
    // APH zones (deep rose)
    'APH1': '#e8b4b8', 'APH2': '#d4979c',
    // Non-buildable / reserves (grays and pale tones)
    'EV': '#e8e8e8', 'IS': '#dcdcdc', 'RM': '#d0d0d0',
    'RF': '#c4c4c4', 'RV': '#b8b8b8', 'AE': '#acacac',
    // URE
    'URE': '#a1887f',
};

const DEFAULT_COLOR = '#bdbdbd';

function getZoneColor(perfil) {
    // Exact match first
    if (ZONE_COLORS[perfil]) return ZONE_COLORS[perfil];

    // Prefix-based fallbacks for Ordinance/Decreto zones
    if (perfil.startsWith('Ord.')) return '#ffcc80'; // Orange-light for special ordinances
    if (perfil.startsWith('Dto.')) return '#ffe0b2'; // Lighter orange for decretos

    return DEFAULT_COLOR;
}

/**
 * Build a MapLibre match expression for zone fill colors.
 */
function buildColorExpression(features) {
    const seen = new Set();
    const matchParts = [];

    for (const f of features) {
        const perfil = f.properties.perfil_o_zona;
        if (perfil && !seen.has(perfil)) {
            seen.add(perfil);
            matchParts.push(perfil, getZoneColor(perfil));
        }
    }

    return ['match', ['get', 'perfil_o_zona'], ...matchParts, DEFAULT_COLOR];
}

export function initZonaLayer(map) {
    // Add empty source, then fetch
    map.addSource('zonas', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] },
    });

    // Fill layer
    map.addLayer({
        id: 'zonas-fill',
        type: 'fill',
        source: 'zonas',
        paint: {
            'fill-color': DEFAULT_COLOR,
            'fill-opacity': [
                'interpolate', ['linear'], ['zoom'],
                10, 0.4,
                14, 0.35,
                15, 0.15,
                17, 0.08,
            ],
        },
    });

    // Outline layer
    map.addLayer({
        id: 'zonas-outline',
        type: 'line',
        source: 'zonas',
        paint: {
            'line-color': '#666',
            'line-width': [
                'interpolate', ['linear'], ['zoom'],
                10, 0.5,
                14, 1,
                17, 0.3,
            ],
            'line-opacity': [
                'interpolate', ['linear'], ['zoom'],
                14, 0.6,
                17, 0.2,
            ],
        },
    });

    // Hover highlight
    map.addLayer({
        id: 'zonas-hover',
        type: 'fill',
        source: 'zonas',
        paint: {
            'fill-color': '#fff',
            'fill-opacity': 0.3,
        },
        filter: ['==', ['get', 'id'], -1],
    });

    // Fetch zone data
    fetch('/api/zonas')
        .then(r => r.json())
        .then(geojson => {
            map.getSource('zonas').setData(geojson);

            // Update fill color using match expression
            if (geojson.features.length > 0) {
                map.setPaintProperty('zonas-fill', 'fill-color', buildColorExpression(geojson.features));
            }
        })
        .catch(err => console.error('Failed to load zones:', err));

    // Tooltip element
    const tooltip = document.getElementById('zona-tooltip');

    map.on('mousemove', 'zonas-fill', (e) => {
        if (map.getZoom() >= 15) return; // Don't show zone tooltip at parcela level
        const f = e.features[0];
        if (!f) return;

        map.setFilter('zonas-hover', ['==', ['get', 'id'], f.properties.id]);
        map.getCanvas().style.cursor = 'pointer';

        const props = f.properties;
        let text = props.perfil_o_zona;
        if (props.zona_nombre) text += ` - ${props.zona_nombre}`;

        tooltip.textContent = text;
        tooltip.style.left = (e.point.x + 12) + 'px';
        tooltip.style.top = (e.point.y - 12) + 'px';
        tooltip.classList.remove('hidden');
    });

    map.on('mouseleave', 'zonas-fill', () => {
        map.setFilter('zonas-hover', ['==', ['get', 'id'], -1]);
        map.getCanvas().style.cursor = '';
        tooltip.classList.add('hidden');
    });
}

export { getZoneColor };
