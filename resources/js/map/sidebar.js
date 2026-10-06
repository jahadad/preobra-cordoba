/**
 * Right sidebar with parcela rinde details + unit mix simulator.
 * Supports single parcela and combined (multi-select) mode.
 */

import { clearSelection } from './parcela-layer';

const fmt = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 1 });
const fmtInt = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 });

// Current vendible m2 for the simulator
let currentVendible = 0;

// Minimum unit size per Cordoba building code (vivienda minima ~25m2 util)
const MIN_UNIT_SIZE = 25;

const UNIT_TYPES = {
    25: 'monoamb. mínimos',
    30: 'monoambientes',
    35: 'studios',
    40: 'studios grandes',
    45: '1.5 ambientes',
    50: '2 ambientes',
    55: '2 amb. grandes',
    60: '2.5 ambientes',
    65: '3 ambientes',
    70: '3 amb. grandes',
    75: '3.5 ambientes',
    80: '4 ambientes',
    85: '4 amb. grandes',
    90: 'semipiso',
    95: 'semipiso grande',
    100: 'dúplex',
    105: 'dúplex grande',
    110: 'triplex',
    115: 'triplex grande',
    120: 'piso completo',
};

function getUnitType(size) {
    // Find closest match
    const keys = Object.keys(UNIT_TYPES).map(Number);
    const closest = keys.reduce((prev, curr) =>
        Math.abs(curr - size) < Math.abs(prev - size) ? curr : prev
    );
    return UNIT_TYPES[closest];
}

export function initSidebar(map) {
    const sidebar = document.getElementById('sidebar');
    const closeBtn = document.getElementById('sidebar-close');

    // Listen for single parcela selection
    window.addEventListener('parcela-selected', (e) => {
        showSingleHeader();
        populateSidebar(e.detail);
        sidebar.classList.remove('translate-x-full');
    });

    // Listen for combined parcela selection
    window.addEventListener('parcelas-combined', (e) => {
        populateCombined(e.detail);
        sidebar.classList.remove('translate-x-full');
    });

    // Listen for cleared selection
    window.addEventListener('parcela-cleared', () => {
        sidebar.classList.add('translate-x-full');
    });

    // Close sidebar
    closeBtn.addEventListener('click', () => {
        sidebar.classList.add('translate-x-full');
        clearSelection(map);
    });

    // "Limpiar seleccion" button
    const clearBtn = document.getElementById('sb-clear-combined');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            sidebar.classList.add('translate-x-full');
            clearSelection(map);
        });
    }

    // Click on empty map area closes sidebar
    map.on('click', (e) => {
        if (e.originalEvent.ctrlKey || e.originalEvent.metaKey) return;
        const parcelaFeatures = map.queryRenderedFeatures(e.point, { layers: ['parcelas-fill'] });
        if (parcelaFeatures.length === 0) {
            sidebar.classList.add('translate-x-full');
            clearSelection(map);
        }
    });

    // Simulator slider
    const slider = document.getElementById('sim-slider');
    slider.addEventListener('input', () => {
        updateSimulator(parseInt(slider.value));
    });

    // Preset buttons
    document.querySelectorAll('.sim-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const size = parseInt(btn.dataset.size);
            slider.value = size;
            updateSimulator(size);
        });
    });
}

function updateSimulator(size) {
    if (currentVendible <= 0) return;

    const units = Math.floor(currentVendible / size);

    setText('sim-size-label', size + ' m\u00B2');
    setText('sim-units', fmtInt.format(units));
    setText('sim-type', getUnitType(size) + ' de ' + size + ' m\u00B2');

    // Highlight active preset
    document.querySelectorAll('.sim-preset').forEach(btn => {
        const btnSize = parseInt(btn.dataset.size);
        if (btnSize === size) {
            btn.classList.add('bg-blue-50', 'border-blue-400');
        } else {
            btn.classList.remove('bg-blue-50', 'border-blue-400');
        }
    });
}

function showSingleHeader() {
    document.getElementById('sb-single-header').classList.remove('hidden');
    document.getElementById('sb-combined-header').classList.add('hidden');
}

function showCombinedHeader() {
    document.getElementById('sb-single-header').classList.add('hidden');
    document.getElementById('sb-combined-header').classList.remove('hidden');
}

function populateCombined(propsList) {
    showCombinedHeader();

    const count = propsList.length;
    setText('sb-combined-count', count + ' parcelas combinadas');

    // List nomenclaturas
    const listEl = document.getElementById('sb-combined-list');
    listEl.innerHTML = '';
    for (const p of propsList) {
        const li = document.createElement('li');
        li.className = 'font-mono text-xs text-gray-600';
        li.textContent = p.nomenclatura || 'Sin nomenclatura';
        listEl.appendChild(li);
    }

    // Check zone consistency
    const zones = [...new Set(propsList.map(p => p.perfil_o_zona).filter(Boolean))];
    const warningEl = document.getElementById('sb-combined-warning');

    if (zones.length > 1) {
        warningEl.textContent = 'Las parcelas seleccionadas estan en zonas diferentes (' + zones.join(', ') + '). El calculo usa la zona de la primera parcela seleccionada.';
        warningEl.classList.remove('hidden');
    } else {
        warningEl.classList.add('hidden');
    }

    // Use first parcela's zone data for calculation
    const ref = propsList[0];
    const fos = parseFloat(ref.fos) || 0;
    const fot = parseFloat(ref.fot) || 0;
    const pisos_max = parseInt(ref.pisos_maximos) || 0;

    // Zona section
    const zonaSection = document.getElementById('sb-zona-section');
    const noZona = document.getElementById('sb-no-zona');

    if (ref.perfil_o_zona) {
        zonaSection.classList.remove('hidden');
        noZona.classList.add('hidden');

        const badge = document.getElementById('sb-zona-badge');
        badge.textContent = ref.perfil_o_zona;
        badge.style.backgroundColor = ref.es_edificable === false ? '#e5e7eb' : '#dbeafe';
        badge.style.color = ref.es_edificable === false ? '#6b7280' : '#1e40af';

        setText('sb-zona-nombre', ref.zona_nombre || '');
        setText('sb-fos', fos ? (fos * 100).toFixed(0) + '%' : '-');
        setText('sb-fot', fot ? fmt.format(fot) : '-');
        setText('sb-pisos', pisos_max ? pisos_max + '' : '-');
    } else {
        zonaSection.classList.add('hidden');
        noZona.classList.remove('hidden');
    }

    // Combined superficie
    let combinedSup = 0;
    for (const p of propsList) {
        combinedSup += parseFloat(p.superficie_tierra) || 0;
    }

    setText('sb-combined-sup', fmt.format(combinedSup) + ' m\u00B2');

    // Combined rinde calculation
    const rindeSection = document.getElementById('sb-rinde-section');
    const noRinde = document.getElementById('sb-no-rinde');
    const simulator = document.getElementById('sb-simulator');

    if (combinedSup > 0 && fos > 0) {
        rindeSection.classList.remove('hidden');
        noRinde.classList.add('hidden');
        simulator.classList.remove('hidden');

        const plantaOcupable = combinedSup * fos;
        let totalConstruible;
        let pisosEfectivos;
        let modo;

        if (fot > 0 && (pisos_max === 0 || fot <= fos * pisos_max)) {
            // FOT mode
            totalConstruible = combinedSup * fot;
            pisosEfectivos = fos > 0 ? Math.ceil(totalConstruible / plantaOcupable) : 0;
            modo = 'fot';
        } else {
            // Pisos mode
            totalConstruible = plantaOcupable * pisos_max;
            pisosEfectivos = pisos_max;
            modo = 'pisos';
        }

        const vendible = totalConstruible * 0.75;
        const unidades = Math.floor(vendible / 50);

        setText('sb-sup-terreno', fmt.format(combinedSup) + ' m\u00B2');
        setText('sb-planta-ocupable', fmt.format(plantaOcupable) + ' m\u00B2');
        setText('sb-total-construible', fmt.format(totalConstruible) + ' m\u00B2');
        setText('sb-pisos-efectivos', pisosEfectivos + '');
        setText('sb-vendible', fmt.format(vendible) + ' m\u00B2');
        setText('sb-unidades', fmtInt.format(unidades));
        setText('sb-modo', modo === 'fot' ? 'Modo FOT' : 'Modo Pisos');

        currentVendible = vendible;
        const slider = document.getElementById('sim-slider');
        slider.value = 50;
        updateSimulator(50);
    } else {
        rindeSection.classList.add('hidden');
        noRinde.classList.remove('hidden');
        simulator.classList.add('hidden');
        currentVendible = 0;
    }
}

function populateSidebar(props) {
    // Parse rinde if it's a string (from GeoJSON feature properties)
    let rinde = props.rinde;
    if (typeof rinde === 'string') {
        try { rinde = JSON.parse(rinde); } catch { rinde = null; }
    }

    // Header
    setText('sb-nomenclatura', props.nomenclatura || 'Sin nomenclatura');
    setText('sb-designacion', props.designacion_oficial || '');

    // Estado badge (baldio vs edificado)
    const estadoBadge = document.getElementById('sb-estado-badge');
    const mejoras = parseFloat(props.superficie_mejoras) || 0;
    if (mejoras > 0) {
        estadoBadge.textContent = 'Edificado (' + fmt.format(mejoras) + ' m\u00B2)';
        estadoBadge.className = 'inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-800';
    } else {
        estadoBadge.textContent = 'Baldio';
        estadoBadge.className = 'inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800';
    }

    // Catastro link
    const catastroLink = document.getElementById('sb-catastro-link');
    if (props._lat && props._lng) {
        catastroLink.href = `https://mapascordoba.gob.ar/viewer/mapa/15?lat=${props._lat}&lon=${props._lng}&zoom=19`;
        catastroLink.classList.remove('hidden');
    } else {
        catastroLink.classList.add('hidden');
    }

    // Zona section
    const zonaSection = document.getElementById('sb-zona-section');
    const noZona = document.getElementById('sb-no-zona');

    if (props.perfil_o_zona) {
        zonaSection.classList.remove('hidden');
        noZona.classList.add('hidden');

        const badge = document.getElementById('sb-zona-badge');
        badge.textContent = props.perfil_o_zona;
        badge.style.backgroundColor = props.es_edificable === false ? '#e5e7eb' : '#dbeafe';
        badge.style.color = props.es_edificable === false ? '#6b7280' : '#1e40af';

        setText('sb-zona-nombre', props.zona_nombre || '');
        setText('sb-fos', props.fos ? (props.fos * 100).toFixed(0) + '%' : '-');
        setText('sb-fot', props.fot ? fmt.format(props.fot) : '-');
        setText('sb-pisos', props.pisos_maximos ? props.pisos_maximos + '' : '-');
    } else {
        zonaSection.classList.add('hidden');
        noZona.classList.remove('hidden');
    }

    // Rinde section
    const rindeSection = document.getElementById('sb-rinde-section');
    const noRinde = document.getElementById('sb-no-rinde');
    const simulator = document.getElementById('sb-simulator');

    if (rinde) {
        rindeSection.classList.remove('hidden');
        noRinde.classList.add('hidden');
        simulator.classList.remove('hidden');

        setText('sb-sup-terreno', fmt.format(rinde.superficie_terreno) + ' m\u00B2');
        setText('sb-planta-ocupable', fmt.format(rinde.superficie_ocupable_planta) + ' m\u00B2');
        setText('sb-total-construible', fmt.format(rinde.superficie_total_construible) + ' m\u00B2');
        setText('sb-pisos-efectivos', rinde.pisos_efectivos + '');
        setText('sb-vendible', fmt.format(rinde.superficie_vendible) + ' m\u00B2');
        setText('sb-unidades', fmtInt.format(rinde.unidades_estimadas));
        setText('sb-modo', rinde.modo_calculo === 'fot' ? 'Modo FOT' : 'Modo Pisos');

        // Update simulator
        currentVendible = rinde.superficie_vendible;
        const slider = document.getElementById('sim-slider');
        slider.value = 50;
        updateSimulator(50);
    } else {
        rindeSection.classList.add('hidden');
        noRinde.classList.remove('hidden');
        simulator.classList.add('hidden');
        currentVendible = 0;
    }
}

function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}
