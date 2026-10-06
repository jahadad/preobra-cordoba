/**
 * Search by nomenclatura or address with autocomplete.
 */

import { selectParcela } from './parcela-layer';

let debounceTimer = null;

export function initSearch(map) {
    const input = document.getElementById('search-input');
    const resultsEl = document.getElementById('search-results');

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();

        if (q.length < 3) {
            resultsEl.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`/api/parcelas/search?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(results => renderResults(results, map, resultsEl, input))
                .catch(err => console.error('Search failed:', err));
        }, 400);
    });

    // Close results on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#search-input') && !e.target.closest('#search-results')) {
            resultsEl.classList.add('hidden');
        }
    });

    // Close results on Escape
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            resultsEl.classList.add('hidden');
            input.blur();
        }
    });
}

function renderResults(results, map, resultsEl, input) {
    if (results.length === 0) {
        resultsEl.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">Sin resultados</div>';
        resultsEl.classList.remove('hidden');
        return;
    }

    resultsEl.innerHTML = results.map(r => {
        const isAddress = r.source === 'address' || r.source === 'address_only';
        const hasParcel = r.id !== null;

        let primaryLine = '';
        let secondaryLine = '';

        if (isAddress && hasParcel) {
            // Address search found a parcela: show designation + surface to distinguish
            const desig = r.designacion_oficial || r.nomenclatura;
            const sup = r.superficie_tierra ? ` (${Math.round(r.superficie_tierra)} m\u00B2)` : '';
            primaryLine = `<span class="text-sm font-semibold text-gray-800">${escapeHtml(desig)}${sup}</span>`;
            secondaryLine = `<span class="text-xs text-gray-500">${escapeHtml(r.address)}</span>`;
        } else if (isAddress) {
            // Address search, no parcela at that point
            primaryLine = `<span class="text-sm font-semibold text-gray-800">${escapeHtml(r.address)}</span>`;
            secondaryLine = `<span class="text-xs text-gray-400">Ver ubicacion en mapa</span>`;
        } else {
            // Nomenclatura search
            primaryLine = `<span class="text-sm font-mono font-semibold text-gray-800">${r.nomenclatura}</span>`;
            secondaryLine = `<span class="text-xs text-gray-500 truncate">${r.designacion_oficial || ''}</span>`;
        }

        const icon = isAddress
            ? `<svg class="w-4 h-4 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`
            : `<svg class="w-4 h-4 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>`;

        return `
            <button
                class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-0 flex items-start gap-2"
                data-id="${r.id || ''}"
                data-lng="${r.center ? r.center[0] : ''}"
                data-lat="${r.center ? r.center[1] : ''}"
                data-source="${r.source}"
            >
                ${icon}
                <div class="min-w-0">
                    <div>${primaryLine}</div>
                    <div>${secondaryLine}</div>
                </div>
            </button>
        `;
    }).join('');

    resultsEl.classList.remove('hidden');

    // Click handler for results
    resultsEl.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id ? parseInt(btn.dataset.id) : null;
            const lng = parseFloat(btn.dataset.lng);
            const lat = parseFloat(btn.dataset.lat);
            const source = btn.dataset.source;

            resultsEl.classList.add('hidden');

            // Set input text to the primary line content
            const primaryEl = btn.querySelector('.font-semibold');
            if (primaryEl) {
                input.value = primaryEl.textContent;
            }

            if (!isNaN(lng) && !isNaN(lat)) {
                map.flyTo({ center: [lng, lat], zoom: 17, duration: 1500 });

                if (id) {
                    // Parcela found: fly to it and select
                    map.once('moveend', () => {
                        setTimeout(() => {
                            selectParcela(map, id);

                            fetch(`/api/parcelas/${id}`)
                                .then(r => r.json())
                                .then(feature => {
                                    window.dispatchEvent(new CustomEvent('parcela-selected', {
                                        detail: feature.properties,
                                    }));
                                })
                                .catch(err => console.error('Failed to load parcela:', err));
                        }, 500);
                    });
                }
                // If no parcela (address_only), just fly to the location
            }
        });
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
