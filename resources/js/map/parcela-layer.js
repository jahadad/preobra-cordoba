/**
 * Parcela polygons layer: viewport-filtered loading at zoom 15+.
 * Supports single click and Ctrl/Cmd+click multi-select.
 */

import { getZoneColor } from './zona-layer';

let debounceTimer = null;
const selectedIds = new Set();

// Cache of feature properties keyed by id, for multi-select retrieval
let featureCache = new Map();

export function initParcelaLayer(map) {
    // Empty source
    map.addSource('parcelas', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] },
    });

    // Fill layer
    map.addLayer({
        id: 'parcelas-fill',
        type: 'fill',
        source: 'parcelas',
        paint: {
            'fill-color': '#bdbdbd',
            'fill-opacity': 0.5,
        },
        minzoom: 15,
    });

    // Outline layer
    map.addLayer({
        id: 'parcelas-outline',
        type: 'line',
        source: 'parcelas',
        paint: {
            'line-color': '#333',
            'line-width': 0.8,
        },
        minzoom: 15,
    });

    // Selected highlight
    map.addLayer({
        id: 'parcelas-selected',
        type: 'fill',
        source: 'parcelas',
        paint: {
            'fill-color': '#f59e0b',
            'fill-opacity': 0.6,
        },
        filter: ['==', ['get', 'id'], -1],
    });

    map.addLayer({
        id: 'parcelas-selected-outline',
        type: 'line',
        source: 'parcelas',
        paint: {
            'line-color': '#d97706',
            'line-width': 2.5,
        },
        filter: ['==', ['get', 'id'], -1],
    });

    // Load parcelas on move
    map.on('moveend', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadParcelas(map), 300);
    });

    // Click parcela
    map.on('click', 'parcelas-fill', (e) => {
        const f = e.features[0];
        if (!f) return;

        const id = f.properties.id;
        const isMulti = e.originalEvent.ctrlKey || e.originalEvent.metaKey;

        if (isMulti) {
            // Toggle parcela in/out of selection
            if (selectedIds.has(id)) {
                selectedIds.delete(id);
                featureCache.delete(id);
            } else {
                selectedIds.add(id);
                featureCache.set(id, { ...f.properties, _lng: e.lngLat.lng, _lat: e.lngLat.lat });
            }

            applySelectionFilter(map);

            if (selectedIds.size === 0) {
                // All removed, close sidebar
                window.dispatchEvent(new CustomEvent('parcela-cleared'));
            } else if (selectedIds.size === 1) {
                // Back to single mode
                const singleProps = featureCache.values().next().value;
                window.dispatchEvent(new CustomEvent('parcela-selected', { detail: singleProps }));
            } else {
                // Multi mode
                const allProps = Array.from(featureCache.values());
                window.dispatchEvent(new CustomEvent('parcelas-combined', { detail: allProps }));
            }
        } else {
            // Normal click: clear set, select single
            selectedIds.clear();
            featureCache.clear();

            selectedIds.add(id);
            const props = { ...f.properties, _lng: e.lngLat.lng, _lat: e.lngLat.lat };
            featureCache.set(id, props);

            applySelectionFilter(map);

            window.dispatchEvent(new CustomEvent('parcela-selected', { detail: props }));
        }
    });

    // Cursor
    map.on('mouseenter', 'parcelas-fill', () => {
        map.getCanvas().style.cursor = 'pointer';
    });
    map.on('mouseleave', 'parcelas-fill', () => {
        map.getCanvas().style.cursor = '';
    });
}

function applySelectionFilter(map) {
    if (selectedIds.size === 0) {
        map.setFilter('parcelas-selected', ['==', ['get', 'id'], -1]);
        map.setFilter('parcelas-selected-outline', ['==', ['get', 'id'], -1]);
    } else {
        const ids = Array.from(selectedIds);
        const filter = ['in', ['get', 'id'], ['literal', ids]];
        map.setFilter('parcelas-selected', filter);
        map.setFilter('parcelas-selected-outline', filter);
    }
}

function loadParcelas(map) {
    if (map.getZoom() < 15) {
        map.getSource('parcelas').setData({ type: 'FeatureCollection', features: [] });
        updateStats(0);
        return;
    }

    const bounds = map.getBounds();
    const bbox = [
        bounds.getWest().toFixed(6),
        bounds.getSouth().toFixed(6),
        bounds.getEast().toFixed(6),
        bounds.getNorth().toFixed(6),
    ].join(',');

    fetch(`/api/parcelas?bbox=${bbox}`)
        .then(r => r.json())
        .then(geojson => {
            map.getSource('parcelas').setData(geojson);
            updateStats(geojson.features.length);

            // Build color expression from actual data
            if (geojson.features.length > 0) {
                const colorExpr = buildParcelaColorExpression(geojson.features);
                map.setPaintProperty('parcelas-fill', 'fill-color', colorExpr);
            }

            // Re-apply selection if still active
            if (selectedIds.size > 0) {
                applySelectionFilter(map);
            }
        })
        .catch(err => console.error('Failed to load parcelas:', err));
}

function buildParcelaColorExpression(features) {
    const seen = new Set();
    const matchParts = [];

    for (const f of features) {
        const perfil = f.properties.perfil_o_zona;
        if (perfil && !seen.has(perfil)) {
            seen.add(perfil);
            matchParts.push(perfil, getZoneColor(perfil));
        }
    }

    if (matchParts.length === 0) {
        return '#bdbdbd';
    }

    return ['match', ['get', 'perfil_o_zona'], ...matchParts, '#bdbdbd'];
}

function updateStats(count) {
    const statsEl = document.getElementById('stats');
    const countEl = document.getElementById('stats-parcelas');
    if (count > 0) {
        countEl.textContent = `${count.toLocaleString('es-AR')} parcelas en vista`;
        statsEl.classList.remove('hidden');
    } else {
        statsEl.classList.add('hidden');
    }
}

export function selectParcela(map, id) {
    selectedIds.clear();
    featureCache.clear();
    selectedIds.add(id);
    applySelectionFilter(map);
}

export function clearSelection(map) {
    selectedIds.clear();
    featureCache.clear();
    applySelectionFilter(map);
}
