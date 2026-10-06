<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PreObra - Inteligencia Zonal</title>
    @vite(['resources/css/app.css', 'resources/js/map.js'])
</head>
<body class="bg-gray-100 overflow-hidden">
    <div id="app" class="relative h-screen w-screen">

        {{-- Top bar --}}
        <div class="absolute top-0 left-0 right-0 z-10 flex items-center gap-3 px-4 py-2 bg-white/90 backdrop-blur shadow-sm">
            <span class="text-lg font-bold text-gray-800 tracking-tight">PreObra</span>

            {{-- Search --}}
            <div class="relative flex-1 max-w-md">
                <input
                    id="search-input"
                    type="text"
                    placeholder="Buscar por direccion o nomenclatura..."
                    class="w-full pl-9 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    autocomplete="off"
                >
                <svg class="absolute left-2.5 top-2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <div id="search-results" class="hidden absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 max-h-64 overflow-y-auto"></div>
            </div>

            {{-- Stats --}}
            <div id="stats" class="hidden text-xs text-gray-500">
                <span id="stats-parcelas"></span>
            </div>
        </div>

        {{-- Map container --}}
        <div id="map" class="h-full w-full"></div>

        {{-- Zone tooltip (follows cursor) --}}
        <div id="zona-tooltip" class="hidden absolute z-20 px-2 py-1 text-xs font-medium bg-gray-900 text-white rounded shadow pointer-events-none"></div>

        {{-- Sidebar --}}
        <div id="sidebar" class="absolute top-12 right-0 bottom-0 w-80 bg-white shadow-xl z-10 translate-x-full transition-transform duration-200 ease-out overflow-y-auto">
            <div class="p-4">
                {{-- Close button --}}
                <button id="sidebar-close" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                {{-- Single parcela header --}}
                <div id="sb-single-header" class="mb-4">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Parcela</h2>
                    <p id="sb-nomenclatura" class="text-lg font-bold text-gray-800 font-mono"></p>
                    <p id="sb-designacion" class="text-sm text-gray-500"></p>
                    <span id="sb-estado-badge" class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded"></span>
                    <a id="sb-catastro-link" href="#" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 mt-2 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Ver en Catastro
                    </a>
                </div>

                {{-- Combined parcelas header --}}
                <div id="sb-combined-header" class="hidden mb-4">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Lote Combinado</h2>
                    <span id="sb-combined-count" class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded bg-orange-100 text-orange-800"></span>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-sm text-gray-500">Sup. total:</span>
                        <span id="sb-combined-sup" class="text-sm font-semibold"></span>
                    </div>
                    <ul id="sb-combined-list" class="mt-2 max-h-24 overflow-y-auto space-y-0.5"></ul>
                    <div id="sb-combined-warning" class="hidden mt-2 p-2 bg-amber-50 rounded text-xs text-amber-700"></div>
                    <button id="sb-clear-combined" class="mt-3 w-full px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Limpiar seleccion
                    </button>
                    <p class="mt-2 text-xs text-gray-400">Ctrl+click para agregar o quitar parcelas</p>
                </div>

                {{-- Zona info --}}
                <div id="sb-zona-section" class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Zona</h3>
                    <div class="flex items-center gap-2 mb-1">
                        <span id="sb-zona-badge" class="inline-block px-2 py-0.5 text-sm font-bold rounded"></span>
                        <span id="sb-zona-nombre" class="text-sm text-gray-600"></span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-2 text-center">
                        <div>
                            <div class="text-xs text-gray-400">FOS</div>
                            <div id="sb-fos" class="text-sm font-semibold"></div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">FOT</div>
                            <div id="sb-fot" class="text-sm font-semibold"></div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Pisos</div>
                            <div id="sb-pisos" class="text-sm font-semibold"></div>
                        </div>
                    </div>
                </div>

                {{-- Rinde section --}}
                <div id="sb-rinde-section" class="mb-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Rinde Estimado</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Sup. terreno</span>
                            <span id="sb-sup-terreno" class="text-sm font-semibold"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Planta ocupable</span>
                            <span id="sb-planta-ocupable" class="text-sm font-semibold"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Total construible</span>
                            <span id="sb-total-construible" class="text-sm font-semibold text-green-700"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Pisos efectivos</span>
                            <span id="sb-pisos-efectivos" class="text-sm font-semibold"></span>
                        </div>
                        <hr class="border-gray-200">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Vendible (75%)</span>
                            <span id="sb-vendible" class="text-sm font-semibold text-blue-700"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Unidades est. (50m&sup2;)</span>
                            <span id="sb-unidades" class="text-sm font-bold text-lg"></span>
                        </div>
                        <div class="mt-1">
                            <span id="sb-modo" class="inline-block px-2 py-0.5 text-xs rounded bg-gray-200 text-gray-600"></span>
                        </div>
                    </div>
                </div>

                {{-- Unit mix simulator --}}
                <div id="sb-simulator" class="hidden mb-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Simulador de Unidades</h3>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-gray-500">Tamano promedio</span>
                        <span id="sim-size-label" class="text-sm font-semibold">50 m&sup2;</span>
                    </div>
                    <input
                        id="sim-slider"
                        type="range"
                        min="25"
                        max="120"
                        step="5"
                        value="50"
                        class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                    >
                    <div class="flex justify-between text-xs text-gray-400 mt-0.5 mb-3">
                        <span>25m&sup2;</span>
                        <span>120m&sup2;</span>
                    </div>

                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <div class="text-3xl font-bold text-blue-700" id="sim-units">22</div>
                        <div class="text-xs text-gray-500 mt-0.5" id="sim-type">departamentos de 50 m&sup2;</div>
                    </div>

                    <div class="grid grid-cols-4 gap-1.5 mt-3">
                        <button data-size="35" class="sim-preset px-1.5 py-1.5 text-xs rounded border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors text-center">
                            <div class="font-semibold">35m&sup2;</div>
                            <div class="text-gray-400">Studio</div>
                        </button>
                        <button data-size="50" class="sim-preset px-1.5 py-1.5 text-xs rounded border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors text-center">
                            <div class="font-semibold">50m&sup2;</div>
                            <div class="text-gray-400">2 amb</div>
                        </button>
                        <button data-size="70" class="sim-preset px-1.5 py-1.5 text-xs rounded border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors text-center">
                            <div class="font-semibold">70m&sup2;</div>
                            <div class="text-gray-400">3 amb</div>
                        </button>
                        <button data-size="100" class="sim-preset px-1.5 py-1.5 text-xs rounded border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors text-center">
                            <div class="font-semibold">100m&sup2;</div>
                            <div class="text-gray-400">Duplex</div>
                        </button>
                    </div>
                </div>

                {{-- No rinde message --}}
                <div id="sb-no-rinde" class="hidden mb-4 p-3 bg-amber-50 rounded-lg text-sm text-amber-700">
                    Sin datos de rinde. La parcela puede no tener zona asignada o no ser edificable.
                </div>

                {{-- No zona message --}}
                <div id="sb-no-zona" class="hidden mb-4 p-3 bg-gray-50 rounded-lg text-sm text-gray-500">
                    Sin zona de ocupacion asignada.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
