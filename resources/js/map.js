import 'maplibre-gl/dist/maplibre-gl.css';
import maplibregl from 'maplibre-gl';
import { initZonaLayer } from './map/zona-layer';
import { initParcelaLayer } from './map/parcela-layer';
import { initSidebar } from './map/sidebar';
import { initSearch } from './map/search';

const map = new maplibregl.Map({
    container: 'map',
    style: {
        version: 8,
        sources: {
            'carto-light': {
                type: 'raster',
                tiles: [
                    'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
                    'https://b.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
                    'https://c.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
                ],
                tileSize: 256,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
            },
        },
        layers: [
            {
                id: 'carto-light-layer',
                type: 'raster',
                source: 'carto-light',
                minzoom: 0,
                maxzoom: 20,
            },
        ],
    },
    center: [-64.1833, -31.4167], // Cordoba
    zoom: 12,
    maxZoom: 19,
    minZoom: 10,
});

map.addControl(new maplibregl.NavigationControl(), 'bottom-right');

map.on('load', () => {
    initZonaLayer(map);
    initParcelaLayer(map);
    initSidebar(map);
    initSearch(map);
});

// Export for modules that need it
window.__preobra_map = map;
