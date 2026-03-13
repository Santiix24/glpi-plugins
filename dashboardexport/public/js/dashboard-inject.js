/**
 * Dashboard Export Plugin - Sistema de Captura de Widgets
 * Captura TODOS los widgets del dashboard GLPI de forma completa
 */

(function() {
    'use strict';

    console.log('🚀 Dashboard Export Plugin cargado');

    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardExport);
    } else {
        initDashboardExport();
    }

    function initDashboardExport() {
        // Verificar si estamos en una página de dashboard
        const isDashboardPage = window.location.pathname.includes('/front/central.php') || 
                               document.querySelector('.grid-stack') !== null;
        
        if (!isDashboardPage) {
            console.log('❌ No es una página de dashboard');
            return;
        }

        console.log('✅ Página de dashboard detectada');

        // Esperar a que el dashboard se cargue completamente
        waitForDashboard();
    }

    function waitForDashboard() {
        let attempts = 0;
        const maxAttempts = 50;

        const checkInterval = setInterval(() => {
            attempts++;
            
            const gridStack = document.querySelector('.grid-stack');
            const widgets = document.querySelectorAll('.grid-stack-item');
            
            if (gridStack && widgets.length > 0) {
                console.log(`✅ Dashboard cargado con ${widgets.length} widgets`);
                clearInterval(checkInterval);
                injectExportButton();
            } else if (attempts >= maxAttempts) {
                console.log('❌ Timeout esperando dashboard');
                clearInterval(checkInterval);
            }
        }, 200);
    }

    // Extraer texto limpio de un elemento quitando íconos SVG/FontAwesome
    function cleanElText(el) {
        if (!el) return '';
        var clone = el.cloneNode(true);
        var icons = clone.querySelectorAll('svg, i, .fa, .ti, .caret, span.caret');
        for (var j = icons.length - 1; j >= 0; j--) {
            if (icons[j].parentNode) icons[j].parentNode.removeChild(icons[j]);
        }
        return clone.textContent.replace(/[\r\n\t]/g, ' ').replace(/\s+/g, ' ').trim();
    }

    // Detectar el nombre del tablero activo para mostrarlo en el botón
    function detectDashboardName() {
        // Prioridad: dropdown selector de GLPI (el que muestra Asistencia, Central, etc.)
        var candidates = [
            document.querySelector('.dashboard_select button.dropdown-toggle'),
            document.querySelector('.dashboard_select .btn'),
            document.querySelector('.dashboard_select button'),
            document.querySelector('[data-bs-toggle="dropdown"].btn'),
            document.querySelector('.nav-tabs .nav-link.active'),
        ];
        for (var i = 0; i < candidates.length; i++) {
            var txt = cleanElText(candidates[i]);
            if (txt && txt.length > 0 && txt.length < 80) return txt;
        }
        return '';
    }

    function buildButtonHTML(dashName) {
        // Hoja de cálculo con cuadrícula — icónico y reconocible
        var icon = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" style="flex-shrink:0">' +
            '<rect x="3" y="2" width="18" height="20" rx="2" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.55)" stroke-width="1.2"/>' +
            '<line x1="3" y1="8" x2="21" y2="8" stroke="rgba(255,255,255,0.45)" stroke-width="1"/>' +
            '<line x1="3" y1="13" x2="21" y2="13" stroke="rgba(255,255,255,0.45)" stroke-width="1"/>' +
            '<line x1="3" y1="18" x2="21" y2="18" stroke="rgba(255,255,255,0.45)" stroke-width="1"/>' +
            '<line x1="9" y1="8" x2="9" y2="22" stroke="rgba(255,255,255,0.45)" stroke-width="1"/>' +
            '<line x1="15" y1="8" x2="15" y2="22" stroke="rgba(255,255,255,0.45)" stroke-width="1"/>' +
            '<text x="5.5" y="6.5" font-family="Arial,sans-serif" font-size="5" font-weight="bold" fill="rgba(255,255,255,0.9)">XLS</text>' +
            '</svg>';

        var label = '<span style="display:flex;flex-direction:column;align-items:flex-start;line-height:1.25;gap:1px">' +
            '<span style="font-size:12px;font-weight:700;letter-spacing:0.2px">Exportar a Excel</span>';

        if (dashName) {
            label += '<span style="font-size:10px;font-weight:400;opacity:0.80;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="Tablero: ' + dashName + '">' + dashName + '</span>';
        }
        label += '</span>';

        return '<span style="display:inline-flex;align-items:center;gap:9px;pointer-events:none">' + icon + label + '</span>';
    }

    function injectExportButton() {
        const dashboardToolbar = document.querySelector('.dashboard .toolbar') ||
                                 document.querySelector('.page-header .d-flex') ||
                                 document.querySelector('.dashboard');

        if (!dashboardToolbar) {
            console.log('❌ No se encontró toolbar del dashboard');
            return;
        }

        if (document.getElementById('export-dashboard-excel-btn')) {
            // Refrescar el nombre del tablero si el botón ya existe
            var existing = document.getElementById('export-dashboard-excel-btn');
            existing.innerHTML = buildButtonHTML(detectDashboardName());
            return;
        }

        var dashName = detectDashboardName();

        const exportButton = document.createElement('button');
        exportButton.id = 'export-dashboard-excel-btn';
        exportButton.className = 'btn';
        exportButton.title = 'Exportar este tablero a Excel con gráficas editables';
        exportButton.innerHTML = buildButtonHTML(dashName);

        exportButton.style.cssText = [
            'display: inline-flex',
            'align-items: center',
            'gap: 8px',
            'padding: 7px 16px',
            'margin-left: 10px',
            'background: linear-gradient(160deg, #217346 0%, #1a5c37 60%, #14472b 100%)',
            'color: #ffffff',
            'border: none',
            'border-radius: 8px',
            'font-size: 13px',
            'cursor: pointer',
            'box-shadow: 0 2px 6px rgba(0,0,0,0.28), 0 1px 2px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.12)',
            'transition: all 0.17s ease',
            'white-space: nowrap',
            'flex-shrink: 0',
            'text-align: left'
        ].join(';');

        exportButton.addEventListener('mouseenter', function() {
            this.style.background = 'linear-gradient(160deg, #27894f 0%, #1f6e3e 60%, #18542f 100%)';
            this.style.boxShadow = '0 5px 12px rgba(0,0,0,0.32), 0 2px 4px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.14)';
            this.style.transform = 'translateY(-2px)';
        });
        exportButton.addEventListener('mouseleave', function() {
            if (!this.disabled) {
                this.style.background = 'linear-gradient(160deg, #217346 0%, #1a5c37 60%, #14472b 100%)';
                this.style.boxShadow = '0 2px 6px rgba(0,0,0,0.28), 0 1px 2px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.12)';
                this.style.transform = 'translateY(0)';
            }
        });
        exportButton.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(0px)';
            this.style.boxShadow = '0 1px 3px rgba(0,0,0,0.3), inset 0 1px 3px rgba(0,0,0,0.2)';
        });

        exportButton.addEventListener('click', handleExportClick);

        dashboardToolbar.appendChild(exportButton);
        console.log('✅ Botón de exportación agregado: "' + (dashName || 'sin nombre') + '"');
    }

    async function handleExportClick(e) {
        e.preventDefault();
        e.stopPropagation();

        const button = e.currentTarget;
        const originalHTML = button.innerHTML;
        
        try {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';

            console.log('📊 Iniciando captura de dashboard...');

            // Pre-escanear todas las instancias de Chart.js
            scanAllChartInstances();

            // Capturar todos los widgets
            const dashboardData = captureDashboardData();

            if (!dashboardData.widgets || dashboardData.widgets.length === 0) {
                throw new Error('No se encontraron widgets para exportar');
            }

            console.log(`✅ Capturados ${dashboardData.widgets.length} widgets`);
            console.log('Datos capturados:', dashboardData);
            
            // Mostrar resumen en consola
            console.log('\n📋 RESUMEN DE CAPTURA:');
            console.log(`Total de widgets: ${dashboardData.widgets.length}`);
            dashboardData.widgets.forEach((w, i) => {
                console.log(`  ${i+1}. [${w.type}] "${w.title}" = "${w.value}" @ (${w.position.x},${w.position.y})`);
            });
            console.log('');

            // Exportar a Excel
            if (typeof window.exportDashboardToExcel === 'function') {
                await window.exportDashboardToExcel(dashboardData);
                
                // Mostrar mensaje de éxito con el nombre del tablero
                const message = `Dashboard "${dashboardData.title}" exportado exitosamente con ${dashboardData.widgets.length} widgets`;
                showNotification(message, 'success');
            } else {
                throw new Error('Módulo de exportación no disponible');
            }

        } catch (error) {
            console.error('❌ Error en exportación:', error);
            showNotification('Error al exportar: ' + error.message, 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalHTML;
        }
    }

    function scanAllChartInstances() {
        window._glpiChartMap = new Map();
        window._glpiChartistMap = new Map();
        window._glpiSVGMap = new Map();
        console.log('🔍 Pre-scanning ALL chart libraries...');
        console.log('  window.Chart:', !!window.Chart);
        console.log('  window.Chartist:', !!window.Chartist);

        // ========== CHARTIST.JS DETECTION ==========
        scanChartistInstances();
        
        // ========== SVG GENERIC DETECTION ==========
        scanSVGCharts();

        if (!window.Chart) {
            // Intentar buscar Chart en otros namespaces
            var possibleCharts = [window.Chart, window.ChartJs, window.chartjs];
            for (var i = 0; i < possibleCharts.length; i++) {
                if (possibleCharts[i]) {
                    window.Chart = possibleCharts[i];
                    console.log('  Chart encontrado en namespace alternativo');
                    break;
                }
            }
        }

        if (!window.Chart) {
            console.log('  ⚠️ Chart.js NO disponible globalmente');
            // Continue anyway - we may have Chartist or SVG charts
        }

        if (window.Chart) {
            console.log('  Chart.js version:', window.Chart.version || 'desconocida');
        }

        // Método 1: Chart.instances (Chart.js 3+)
        try {
            var instances = window.Chart.instances;
            if (instances) {
                var list = Array.isArray(instances) ? instances : Object.values(instances);
                console.log('  Chart.instances: ' + list.length + ' instancias');
                list.forEach(function(inst, idx) {
                    if (!inst) return;
                    try {
                        var cvs = inst.canvas;
                        if (!cvs) return;
                        var cd = safeExtractChart(inst);
                        if (cd && cd.labels.length > 0 && cd.datasets.length > 0) {
                            window._glpiChartMap.set(cvs, cd);
                            console.log('    ✅ Instance ' + idx + ': type=' + cd.type + ', labels=' + cd.labels.length + ', datasets=' + cd.datasets.length);
                            console.log('       Labels:', cd.labels.slice(0, 5).join(', '));
                            cd.datasets.forEach(function(ds, di) {
                                console.log('       DS' + di + ' "' + ds.label + '": [' + ds.data.slice(0, 5).join(', ') + ']');
                            });
                        }
                    } catch(e) {
                        console.log('    ⚠️ Instance ' + idx + ' error:', e.message);
                    }
                });
            }
        } catch(e) {
            console.log('  ⚠️ Chart.instances error:', e.message);
        }

        // Método 2: Chart.getChart para todos los canvas en el grid
        try {
            if (window.Chart.getChart) {
                document.querySelectorAll('.grid-stack canvas').forEach(function(cvs) {
                    if (window._glpiChartMap.has(cvs)) return;
                    try {
                        var ch = window.Chart.getChart(cvs);
                        if (ch) {
                            var cd = safeExtractChart(ch);
                            if (cd && cd.labels.length > 0) {
                                window._glpiChartMap.set(cvs, cd);
                                console.log('    ✅ getChart: type=' + cd.type + ', labels=' + cd.labels.length);
                            }
                        }
                    } catch(e) {}
                });
            }
        } catch(e) {}

        // Método 3: canvas.$chartjs (Chart.js 3+ internal)
        document.querySelectorAll('.grid-stack canvas').forEach(function(cvs) {
            if (window._glpiChartMap.has(cvs)) return;
            try {
                if (cvs.$chartjs && cvs.$chartjs.chart) {
                    var cd = safeExtractChart(cvs.$chartjs.chart);
                    if (cd && cd.labels.length > 0) {
                        window._glpiChartMap.set(cvs, cd);
                        console.log('    ✅ $chartjs: type=' + cd.type + ', labels=' + cd.labels.length);
                    }
                }
            } catch(e) {}
        });

        // Método 4: Escanear TODAS las propiedades del canvas
        document.querySelectorAll('.grid-stack canvas').forEach(function(cvs) {
            if (window._glpiChartMap.has(cvs)) return;
            try {
                var keys = Object.getOwnPropertyNames(cvs);
                for (var k = 0; k < keys.length; k++) {
                    try {
                        var val = cvs[keys[k]];
                        if (val && typeof val === 'object' && val.data && val.data.labels) {
                            var cd = safeExtractChart(val);
                            if (cd && cd.labels.length > 0) {
                                window._glpiChartMap.set(cvs, cd);
                                console.log('    ✅ canvas[' + keys[k] + ']: type=' + cd.type);
                                break;
                            }
                        }
                    } catch(e) {}
                }
            } catch(e) {}
        });

        var totalCharts = window._glpiChartMap.size + window._glpiChartistMap.size + window._glpiSVGMap.size;
        console.log('📊 Pre-scan TOTAL: ' + totalCharts + ' gráficos encontrados');
        console.log('   Chart.js: ' + window._glpiChartMap.size);
        console.log('   Chartist: ' + window._glpiChartistMap.size);
        console.log('   SVG:      ' + window._glpiSVGMap.size);
    }

    // ========================================================================
    // CHARTIST.JS EXTRACTION - Detecta y extrae datos de gráficas Chartist
    // GLPI usa Chartist para TODAS sus gráficas de dashboard
    //
    // Tipos de widgets GLPI soportados:
    //   Pie:   pie, donut, halfpie, halfdonut
    //   Bar:   bar, hbar, bars(múltiple), hBars(múltiple), stackedbars, stackedHBars
    //   Line:  line, lines(múltiple), area, areas(múltiple)
    // ========================================================================
    function scanChartistInstances() {
        var allChartElements = new Set();
        
        // MÉTODO PRINCIPAL: buscar los contenedores .g-chart de GLPI (pie/bar/line)
        // Estos tienen clases como: "pie", "pie donut", "bar horizontal", "line area", etc.
        document.querySelectorAll('.grid-stack .g-chart').forEach(function(gChart) {
            var ctEl = gChart.querySelector('.ct-chart');
            if (ctEl) allChartElements.add(ctEl);
        });
        
        // MÉTODO ALTERNATIVO: buscar directamente divs con clase ct-chart que tengan SVG
        document.querySelectorAll('.grid-stack .ct-chart').forEach(function(el) {
            if (el.querySelector('svg')) allChartElements.add(el);
        });
        
        // MÉTODO FALLBACK: Buscar SVGs con elementos de Chartist
        document.querySelectorAll('.grid-stack svg').forEach(function(svg) {
            var hasCt = svg.querySelector('.ct-bar, .ct-line, .ct-slice-pie, .ct-slice-donut, .ct-point');
            if (hasCt) {
                var parent = svg.closest('.ct-chart') || svg.parentElement;
                if (parent) allChartElements.add(parent);
            }
        });
        
        var ctCharts = Array.from(allChartElements);
        console.log('  Chartist: ' + ctCharts.length + ' elementos .ct-chart encontrados');
        
        ctCharts.forEach(function(ctEl, idx) {
            try {
                var chartData = extractChartistData(ctEl);
                if (chartData && chartData.labels.length > 0 && chartData.datasets.length > 0) {
                    var svgEl = ctEl.querySelector('svg');
                    window._glpiChartistMap.set(ctEl, chartData);
                    if (svgEl) window._glpiChartistMap.set(svgEl, chartData);
                    
                    // También mapear desde el .g-chart padre
                    var gChart = ctEl.closest('.g-chart');
                    if (gChart) window._glpiChartistMap.set(gChart, chartData);
                    
                    console.log('    ✅ Chartist[' + idx + ']: tipo=' + chartData.type + 
                                ', labels=' + chartData.labels.length + 
                                ', series=' + chartData.datasets.length +
                                ', datos=[' + (chartData.datasets[0].data.slice(0,4).join(', ')) + ']');
                } else {
                    console.log('    ⚠️ Chartist[' + idx + ']: sin datos válidos');
                }
            } catch(e) {
                console.log('    ⚠️ Chartist[' + idx + '] error:', e.message);
            }
        });
    }
    
    // Detecta el tipo exacto de gráfica GLPI desde las clases CSS y opciones de Chartist
    function detectGLPIChartType(ctElement, chartistInstance) {
        // Buscar el contenedor .g-chart padre para leer las clases de GLPI
        var gChart = ctElement.closest('.g-chart') || ctElement.parentElement;
        var gClasses = gChart ? (gChart.className || '') : '';
        var svg = ctElement.querySelector('svg');
        
        // --- PIE / DONUT variants ---
        if (gClasses.includes('pie') || (svg && svg.querySelector('.ct-slice-pie, .ct-slice-donut, .ct-slice-donut-solid'))) {
            var isDonut = gClasses.includes('donut');
            var isHalf = gClasses.includes('half');
            
            // Verificar también con opciones de Chartist
            if (chartistInstance && chartistInstance.options) {
                if (chartistInstance.options.donut !== undefined) {
                    isDonut = chartistInstance.options.donut;
                }
                if (chartistInstance.options.startAngle === 270) {
                    isHalf = true;
                }
            }
            
            if (isDonut && isHalf) return 'halfdonut';
            if (!isDonut && isHalf) return 'halfpie';
            if (isDonut) return 'doughnut';
            return 'pie';
        }
        
        // --- BAR variants ---
        if (gClasses.includes('bar') || (svg && svg.querySelector('.ct-bar'))) {
            var isHoriz = gClasses.includes('horizontal');
            var isStacked = gClasses.includes('stacked') || gClasses.includes('stack');
            var isMultiple = !gClasses.includes('distributed');
            
            // Leer opciones reales de Chartist
            if (chartistInstance && chartistInstance.options) {
                if (chartistInstance.options.horizontalBars) isHoriz = true;
                if (chartistInstance.options.stackBars) isStacked = true;
                if (chartistInstance.options.distributeSeries) isMultiple = false;
            }
            
            // Determinar si hay múltiples series comprobando los datos
            var numSeries = 0;
            if (chartistInstance && chartistInstance.data && chartistInstance.data.series) {
                var s0 = chartistInstance.data.series[0];
                numSeries = chartistInstance.data.series.length;
                // Si la primera serie es un array de objetos (no números), son múltiples series
                if (Array.isArray(s0) || (s0 && typeof s0 === 'object' && s0.data)) {
                    isMultiple = true;
                }
            }
            
            if (isHoriz && isStacked) return 'stackedHBar';
            if (!isHoriz && isStacked) return 'stackedBar';
            if (isHoriz && isMultiple && numSeries > 1) return 'hBars';
            if (!isHoriz && isMultiple && numSeries > 1) return 'bars';
            if (isHoriz) return 'hbar';
            return 'bar';
        }
        
        // --- LINE / AREA variants ---
        if (gClasses.includes('line') || (svg && svg.querySelector('.ct-line'))) {
            var isArea = gClasses.includes('area') || (svg && svg.querySelector('.ct-area'));
            var numLineSeries = 0;
            
            if (chartistInstance && chartistInstance.data && chartistInstance.data.series) {
                numLineSeries = chartistInstance.data.series.length;
            }
            
            if (isArea && numLineSeries > 1) return 'areas';
            if (isArea) return 'area';
            if (numLineSeries > 1) return 'lines';
            return 'line';
        }
        
        return 'bar'; // fallback
    }

    function extractChartistData(ctElement) {
        if (!ctElement) return null;
        
        var svg = ctElement.tagName === 'svg' ? ctElement : ctElement.querySelector('svg');
        if (!svg) return null;
        
        // Obtener instancia nativa de Chartist (GLPI la guarda en element.__chartist__)
        var chartistInstance = ctElement.__chartist__;
        if (!chartistInstance && svg) chartistInstance = svg.__chartist__;
        if (!chartistInstance) {
            // Buscar en el padre .ct-chart si entramos por el SVG
            var ctParent = ctElement.closest ? ctElement.closest('.ct-chart') : null;
            if (ctParent) chartistInstance = ctParent.__chartist__;
        }
        
        // Detectar el tipo exacto de gráfica GLPI
        var chartType = detectGLPIChartType(ctElement, chartistInstance);
        
        var labels = [];
        var datasets = [];
        
        // ====== EXTRACCIÓN DE DATOS NATIVOS DE CHARTIST (más precisa) ======
        if (chartistInstance && chartistInstance.data) {
            var ctData = chartistInstance.data;
            
            // Obtener labels
            if (ctData.labels && ctData.labels.length > 0) {
                labels = ctData.labels.map(function(l) { return String(l || '').trim(); }).filter(Boolean);
            }
            
            var ctSeries = ctData.series || [];
            
            // ---- PIE / DOUGHNUT / HALFPIE / HALFDONUT ----
            if (chartType === 'pie' || chartType === 'doughnut' || 
                chartType === 'halfpie' || chartType === 'halfdonut') {
                
                var pieValues = [];
                var pieColors = [];
                
                ctSeries.forEach(function(item, idx) {
                    var val = 0;
                    var serLabel = '';
                    
                    if (typeof item === 'number') {
                        val = item;
                    } else if (item && typeof item === 'object') {
                        val = item.value !== undefined ? item.value : (item.data || 0);
                        serLabel = item.meta || item.label || '';
                    }
                    
                    pieValues.push(parseFloat(val) || 0);
                    
                    // Extraer label desde los datos de la serie (GLPI guarda el label en .meta)
                    if (serLabel && labels.length <= idx) {
                        labels.push(serLabel);
                    } else if (labels.length <= idx) {
                        labels.push('Categoría ' + (idx + 1));
                    }
                    
                    // Extraer color del slice SVG
                    var slices = svg.querySelectorAll('.ct-slice-pie, .ct-slice-donut, .ct-slice-donut-solid');
                    if (slices[idx]) {
                        var fill = window.getComputedStyle(slices[idx]).fill;
                        if (fill && fill !== 'none') pieColors.push(fill);
                    }
                });
                
                if (pieValues.length > 0) {
                    datasets.push({
                        label: 'Valores',
                        data: pieValues,
                        backgroundColor: pieColors.length > 0 ? pieColors : null
                    });
                }
                
            // ---- BAR variants: bar, hbar, bars, hBars, stackedBar, stackedHBar ----
            } else if (['bar','hbar','bars','hBars','stackedBar','stackedHBar'].indexOf(chartType) >= 0) {
                
                var isDistributed = !Array.isArray(ctSeries[0]) && 
                                    (!ctSeries[0] || typeof ctSeries[0] !== 'object' || !ctSeries[0].data);
                
                if (isDistributed || ctSeries.length === 1) {
                    // Serie simple (simpleBar / simpleHbar)
                    var barValues = [];
                    var barColors = [];
                    var serData = ctSeries[0];
                    
                    if (Array.isArray(serData)) {
                        serData = ctSeries; // el array raíz es la serie
                        serData = ctSeries;
                    }
                    
                    // Extraer valores de la serie
                    var rawSeries = Array.isArray(ctSeries[0]) ? ctSeries[0] : ctSeries;
                    rawSeries.forEach(function(item, idx) {
                        var val = 0;
                        var itemLabel = '';
                        if (typeof item === 'number') {
                            val = item;
                        } else if (item && typeof item === 'object') {
                            val = item.value !== undefined ? item.value : (item.data || 0);
                            itemLabel = item.meta || item.label || '';
                        }
                        barValues.push(parseFloat(val) || 0);
                        if (itemLabel && labels.length <= idx) labels.push(itemLabel);
                        else if (labels.length <= idx) labels.push('Elemento ' + (idx + 1));
                        
                        // Color por barra: en modo distributivo cada punto es un .ct-series propio
                        // Usar selector específico sin ambigüedad
                        var specificBar = svg.querySelector('.ct-series:nth-child(' + (idx + 1) + ') .ct-bar');
                        if (specificBar) {
                            var stroke = window.getComputedStyle(specificBar).stroke;
                            if (stroke && stroke !== 'none' && stroke !== 'rgb(0, 0, 0)') {
                                barColors.push(stroke);
                            }
                        }
                    });
                    
                    // Fallback: si no se obtuvieron colores individuales, leer uno por serie
                    if (barColors.length === 0) {
                        svg.querySelectorAll('.ct-series').forEach(function(serEl) {
                            var barEl = serEl.querySelector('.ct-bar');
                            if (barEl) {
                                var stroke = window.getComputedStyle(barEl).stroke;
                                if (stroke && stroke !== 'none') barColors.push(stroke);
                            }
                        });
                    }
                    
                    if (barValues.length > 0) {
                        datasets.push({
                            label: 'Valores',
                            data: barValues,
                            backgroundColor: barColors.length >= barValues.length ? barColors : null
                        });
                    }
                } else {
                    // Múltiples series (multipleBars, stackedBars, etc.)
                    var seriesGroups = svg.querySelectorAll('.ct-series');
                    
                    // ==========================================================
                    // Extraer nombres de series desde el DOM de la leyenda GLPI
                    // GLPI renderiza la leyenda como HTML fuera del SVG, ya sea
                    // como .ct-legend, .legend, o lista dentro de .g-chart
                    // ==========================================================
                    var legendLabels = [];
                    var rootEl = ctElement.closest ? ctElement.closest('.grid-stack-item') : ctElement.parentElement;
                    if (rootEl) {
                        // Buscar en tódos los posibles contenedores de leyenda
                        var lgSelectors = [
                            '.ct-legend li', '.legend li', '[class*="legend"] li',
                            '.ct-legend span', '.legend-item', '[class*="legend-label"]'
                        ];
                        for (var lsi = 0; lsi < lgSelectors.length && legendLabels.length === 0; lsi++) {
                            var lgItems = rootEl.querySelectorAll(lgSelectors[lsi]);
                            lgItems.forEach(function(li) {
                                var txt = li.textContent.trim().replace(/\s+/g, ' ');
                                if (txt && txt.length > 0 && txt.length < 80) legendLabels.push(txt);
                            });
                        }
                    }
                    
                    ctSeries.forEach(function(ser, serIdx) {
                        var serValues = [];
                        var serName = legendLabels[serIdx] || ('Serie ' + (serIdx + 1));
                        var serColor = null;
                        
                        var rawData = [];
                        if (Array.isArray(ser)) {
                            rawData = ser;
                            // Intentar extraer nombre del meta del primer dato válido
                            if (serName === ('Serie ' + (serIdx + 1))) {
                                var firstWithMeta = rawData.find(function(d) { return d && typeof d === 'object' && (d.meta || d.label || d.name); });
                                if (firstWithMeta) serName = String(firstWithMeta.meta || firstWithMeta.label || firstWithMeta.name);
                            }
                        } else if (ser && typeof ser === 'object') {
                            var fromObj = ser.name || ser.label || ser.meta;
                            if (fromObj && serName === ('Serie ' + (serIdx + 1))) serName = String(fromObj);
                            rawData = ser.data || [];
                            if (serName === ('Serie ' + (serIdx + 1)) && rawData[0] && typeof rawData[0] === 'object') {
                                var fm = rawData[0].meta || rawData[0].label || rawData[0].name;
                                if (fm) serName = String(fm);
                            }
                        }
                        
                        rawData.forEach(function(item) {
                            var val = typeof item === 'number' ? item :
                                      (item && typeof item === 'object' ? (item.value || item.y || 0) : 0);
                            serValues.push(parseFloat(val) || 0);
                        });
                        
                        // Extraer color de la serie desde el SVG
                        if (seriesGroups[serIdx]) {
                            var barEl = seriesGroups[serIdx].querySelector('.ct-bar');
                            if (barEl) serColor = window.getComputedStyle(barEl).stroke || null;
                        }
                        
                        if (serValues.length > 0) {
                            datasets.push({
                                label: serName,
                                data: serValues,
                                backgroundColor: serColor
                            });
                        }
                    });
                    
                    // Asegurar que los labels tengan la misma longitud que los datos
                    if (labels.length === 0 && datasets.length > 0) {
                        for (var li = 0; li < datasets[0].data.length; li++) {
                            labels.push('Elemento ' + (li + 1));
                        }
                    }
                }
                
            // ---- LINE / AREA / LINES / AREAS ----
            } else if (['line','area','lines','areas'].indexOf(chartType) >= 0) {
                
                var lineSeriesGroups = svg.querySelectorAll('.ct-series');
                
                // Extraer nombres desde leyenda DOM
                var lineLegenLabels = [];
                var lineRootEl = ctElement.closest ? ctElement.closest('.grid-stack-item') : ctElement.parentElement;
                if (lineRootEl) {
                    var lineLgSelectors = ['.ct-legend li', '.legend li', '[class*="legend"] li', '.ct-legend span'];
                    for (var llsi = 0; llsi < lineLgSelectors.length && lineLegenLabels.length === 0; llsi++) {
                        lineRootEl.querySelectorAll(lineLgSelectors[llsi]).forEach(function(li) {
                            var txt = li.textContent.trim().replace(/\s+/g, ' ');
                            if (txt && txt.length > 0 && txt.length < 80) lineLegenLabels.push(txt);
                        });
                    }
                }
                
                ctSeries.forEach(function(ser, serIdx) {
                    var serValues = [];
                    var serName = lineLegenLabels[serIdx] || ('Serie ' + (serIdx + 1));
                    var serColor = null;
                    
                    var rawData = [];
                    if (Array.isArray(ser)) {
                        rawData = ser;
                        if (serName === ('Serie ' + (serIdx + 1))) {
                            var lFirstMeta = rawData.find(function(d) { return d && typeof d === 'object' && (d.meta || d.label || d.name); });
                            if (lFirstMeta) serName = String(lFirstMeta.meta || lFirstMeta.label || lFirstMeta.name);
                        }
                    } else if (ser && typeof ser === 'object') {
                        var lFromObj = ser.name || ser.label || ser.meta;
                        if (lFromObj && serName === ('Serie ' + (serIdx + 1))) serName = String(lFromObj);
                        rawData = ser.data || [];
                    }
                    
                    rawData.forEach(function(item) {
                        var val = typeof item === 'number' ? item :
                                  (item && typeof item === 'object' ? (item.value || item.y || 0) : 0);
                        serValues.push(parseFloat(val) || 0);
                    });
                    
                    // Color de la serie
                    if (lineSeriesGroups[serIdx]) {
                        var lineEl = lineSeriesGroups[serIdx].querySelector('.ct-line, .ct-point');
                        if (lineEl) serColor = window.getComputedStyle(lineEl).stroke || null;
                    }
                    
                    if (serValues.length > 0) {
                        datasets.push({
                            label: serName,
                            data: serValues,
                            backgroundColor: serColor
                        });
                    }
                });
                
                if (labels.length === 0 && datasets.length > 0) {
                    for (var pi = 0; pi < datasets[0].data.length; pi++) {
                        labels.push('Punto ' + (pi + 1));
                    }
                }
            }
        } else {
            // ====== FALLBACK: extracción desde el SVG DOM ======
            console.log('      ⚠️ Sin datos nativos de Chartist, extrayendo desde SVG DOM...');
            
            // Extraer labels desde texto del SVG (filtrar números del eje Y)
            var labelEls = svg.querySelectorAll('.ct-label');
            labelEls.forEach(function(el) {
                var text = (el.textContent || '').trim();
                if (text && !/^\d+(\.\d+)?$/.test(text)) {
                    if (!labels.includes(text)) labels.push(text);
                }
            });
            
            if (chartType === 'pie' || chartType === 'doughnut' || 
                chartType === 'halfpie' || chartType === 'halfdonut') {
                var slices = svg.querySelectorAll('.ct-slice-pie, .ct-slice-donut, .ct-slice-donut-solid');
                var fbValues = [];
                var fbColors = [];
                slices.forEach(function(s, idx) {
                    var val = s.getAttribute('ct:value');
                    fbValues.push(val !== null ? (parseFloat(val) || 0) : 10);
                    var fill = window.getComputedStyle(s).fill;
                    if (fill && fill !== 'none') fbColors.push(fill);
                    if (labels.length <= idx) labels.push('Categoría ' + (idx + 1));
                });
                if (fbValues.length > 0) {
                    datasets.push({ label: 'Valores', data: fbValues, backgroundColor: fbColors });
                }
            } else {
                // Extraer desde .ct-bar o .ct-point con atributo ct:value
                var valEls = svg.querySelectorAll('.ct-bar, .ct-point');
                var fbSerMap = {};
                valEls.forEach(function(el) {
                    var sg = el.closest('.ct-series');
                    var key = sg ? (sg.className.baseVal || 'default') : 'default';
                    var val = el.getAttribute('ct:value');
                    if (!fbSerMap[key]) fbSerMap[key] = [];
                    fbSerMap[key].push(val !== null ? (parseFloat(val) || 0) : 0);
                });
                
                Object.keys(fbSerMap).forEach(function(key, idx) {
                    datasets.push({
                        label: 'Serie ' + (idx + 1),
                        data: fbSerMap[key],
                        backgroundColor: null
                    });
                });
                
                if (labels.length === 0 && datasets.length > 0) {
                    for (var fi = 0; fi < datasets[0].data.length; fi++) {
                        labels.push('Elemento ' + (fi + 1));
                    }
                }
            }
        }
        
        // ====== VALIDAR Y NORMALIZAR ======
        if (labels.length === 0 || datasets.length === 0 || datasets[0].data.length === 0) {
            return null;
        }
        
        // Sincronizar longitud de labels con datos
        var dataLen = datasets[0].data.length;
        while (labels.length < dataLen) labels.push('Elemento ' + (labels.length + 1));
        if (labels.length > dataLen) labels = labels.slice(0, dataLen);
        
        return { type: chartType, labels: labels, datasets: datasets };
    }
        
    // ========================================================================
    // SVG GENERIC EXTRACTION - Detecta y extrae datos de gráficas SVG genéricas
    // ========================================================================
    function scanSVGCharts() {
        // Buscar SVGs que parecen ser gráficas (tienen rects, circles, paths)
        var svgs = document.querySelectorAll('.grid-stack svg:not(.ct-chart svg)');
        console.log('  SVG: ' + svgs.length + ' elementos SVG encontrados (excl. Chartist)');
        
        svgs.forEach(function(svg, idx) {
            try {
                // Verificar si parece una gráfica
                var rects = svg.querySelectorAll('rect');
                var paths = svg.querySelectorAll('path');
                var circles = svg.querySelectorAll('circle');
                
                // Debe tener suficientes elementos para ser una gráfica
                if (rects.length >= 2 || paths.length >= 1 || circles.length >= 3) {
                    var chartData = extractSVGChartData(svg);
                    if (chartData && chartData.labels.length > 0 && chartData.datasets.length > 0) {
                        window._glpiSVGMap.set(svg, chartData);
                        console.log('    ✅ SVG ' + idx + ': type=' + chartData.type + ', labels=' + chartData.labels.length);
                    }
                }
            } catch(e) {
                console.log('    ⚠️ SVG ' + idx + ' error:', e.message);
            }
        });
    }

    function extractSVGChartData(svg) {
        if (!svg) return null;
        
        var chartType = 'bar';
        var labels = [];
        var values = [];
        var colors = [];
        
        var rects = svg.querySelectorAll('rect');
        var paths = svg.querySelectorAll('path');
        var circles = svg.querySelectorAll('circle');
        var texts = svg.querySelectorAll('text');
        
        // Extraer labels de elementos text
        texts.forEach(function(t) {
            var text = t.textContent.trim();
            // Filtrar números y textos muy cortos
            if (text.length >= 2 && !/^\d+(\.\d+)?%?$/.test(text)) {
                labels.push(text);
            }
        });
        
        // Detectar tipo de gráfica
        if (rects.length >= 3) {
            // Analizar orientación de los rectángulos
            var firstRect = rects[0];
            var width = parseFloat(firstRect.getAttribute('width') || 0);
            var height = parseFloat(firstRect.getAttribute('height') || 0);
            
            // Si hay varios rectángulos con width > height, probablemente es horizontal
            var horizontalCount = 0;
            var verticalCount = 0;
            rects.forEach(function(r) {
                var w = parseFloat(r.getAttribute('width') || 0);
                var h = parseFloat(r.getAttribute('height') || 0);
                if (w > h * 1.5) horizontalCount++;
                else if (h > w * 1.5) verticalCount++;
            });
            
            chartType = horizontalCount > verticalCount ? 'hbar' : 'bar';
            
            // Extraer valores de los rectángulos
            rects.forEach(function(r, idx) {
                var w = parseFloat(r.getAttribute('width') || 0);
                var h = parseFloat(r.getAttribute('height') || 0);
                // Ignorar rectángulos muy pequeños o muy grandes (probablemente son fondos)
                if ((w > 5 && h > 5) && (w < 500 && h < 500)) {
                    values.push(chartType === 'hbar' ? w : h);
                    var fill = r.getAttribute('fill') || window.getComputedStyle(r).fill;
                    if (fill && fill !== 'none') colors.push(fill);
                }
            });
        } else if (paths.length >= 1) {
            // Detectar si es pie/donut o línea
            var hasArcs = false;
            var d = paths[0].getAttribute('d') || '';
            
            paths.forEach(function(p) {
                var pathD = p.getAttribute('d') || '';
                if (pathD.includes('A') || pathD.includes('a')) {
                    hasArcs = true;
                }
            });
            
            if (hasArcs && paths.length <= 20) {
                // Probablemente es pie/donut
                chartType = 'pie';
                paths.forEach(function(p, idx) {
                    // Calcular valor aproximado del sector
                    var pathD = p.getAttribute('d') || '';
                    var arcMatch = pathD.match(/A[\s,]*([\d.]+)/);
                    var val = arcMatch ? parseFloat(arcMatch[1]) : 10;
                    values.push(val);
                    var fill = p.getAttribute('fill') || window.getComputedStyle(p).fill;
                    if (fill && fill !== 'none') colors.push(fill);
                    if (labels.length < idx + 1) labels.push('Sector ' + (idx + 1));
                });
            } else {
                // Probablemente es línea
                chartType = 'line';
                // Extraer puntos de la línea
                paths.forEach(function(p, idx) {
                    var pathD = p.getAttribute('d') || '';
                    var points = pathD.match(/[\d.]+/g);
                    if (points && points.length >= 2) {
                        // Tomar valores Y (cada segundo número después del primero)
                        var yValues = [];
                        for (var i = 1; i < points.length; i += 2) {
                            yValues.push(parseFloat(points[i]));
                        }
                        if (yValues.length > 0) values.push(yValues);
                    }
                    var stroke = p.getAttribute('stroke') || window.getComputedStyle(p).stroke;
                    if (stroke && stroke !== 'none') colors.push(stroke);
                });
            }
        } else if (circles.length >= 3) {
            // Probablemente es scatter o bubble chart - convertir a bar
            chartType = 'bar';
            circles.forEach(function(c, idx) {
                var r = parseFloat(c.getAttribute('r') || 5);
                values.push(r * 10); // Escalar radio
                var fill = c.getAttribute('fill') || window.getComputedStyle(c).fill;
                if (fill && fill !== 'none') colors.push(fill);
                if (labels.length < idx + 1) labels.push('Punto ' + (idx + 1));
            });
        }
        
        // Si no hay labels suficientes, generar
        var neededLabels = Array.isArray(values[0]) ? values[0].length : values.length;
        while (labels.length < neededLabels) {
            labels.push('Cat ' + (labels.length + 1));
        }
        
        // Normalizar a datasets
        var datasets = [];
        if (Array.isArray(values[0])) {
            values.forEach(function(seriesData, idx) {
                datasets.push({
                    label: 'Serie ' + (idx + 1),
                    data: seriesData,
                    backgroundColor: colors[idx] || null
                });
            });
        } else if (values.length > 0) {
            datasets.push({
                label: 'Valores',
                data: values,
                backgroundColor: colors
            });
        }
        
        if (labels.length === 0 || datasets.length === 0) return null;
        
        return {
            type: chartType,
            labels: labels.slice(0, neededLabels),
            datasets: datasets
        };
    }

    function safeExtractChart(chartInstance) {
        if (!chartInstance) return null;
        try {
            var data = chartInstance.data;
            if (!data) return null;

            var type = 'bar';
            try {
                type = chartInstance.config.type || 'bar';
            } catch(e1) {
                try { type = chartInstance.config._config.type || 'bar'; } catch(e2) {
                    try { type = chartInstance.type || 'bar'; } catch(e3) {}
                }
            }

            // Detectar barra horizontal
            var horizontal = false;
            try {
                horizontal = chartInstance.options && chartInstance.options.indexAxis === 'y';
            } catch(e) {}
            if (horizontal && type === 'bar') type = 'hbar';

            var labels = [];
            if (data.labels && Array.isArray(data.labels)) {
                labels = data.labels.map(function(l) { return String(l); });
            }

            var datasets = [];
            if (data.datasets && Array.isArray(data.datasets)) {
                datasets = data.datasets.map(function(ds) {
                    return {
                        label: ds.label || 'Serie',
                        data: (ds.data || []).map(function(v) { return Number(v) || 0; }),
                        backgroundColor: ds.backgroundColor,
                        borderColor: ds.borderColor
                    };
                });
            }

            if (labels.length === 0 && datasets.length === 0) return null;

            return { type: type, labels: labels, datasets: datasets };
        } catch(e) {
            console.log('    safeExtractChart error:', e.message);
            return null;
        }
    }

    function captureDashboardData() {
        const gridStack = document.querySelector('.grid-stack');
        
        if (!gridStack) {
            throw new Error('Grid-stack no encontrado');
        }

        // Detectar el nombre del tablero activo
        let dashboardTitle = 'Dashboard';

        // Método 1 (mayor prioridad): Dropdown selector de GLPI
        // Es el control que muestra 'Asistencia', 'Central', 'Activos', etc.
        const dropdownCandidates = [
            document.querySelector('.dashboard_select button.dropdown-toggle'),
            document.querySelector('.dashboard_select .btn'),
            document.querySelector('.dashboard_select button'),
        ];
        for (const el of dropdownCandidates) {
            const txt = cleanElText(el);
            if (txt && txt.length > 0 && txt.length < 80) {
                dashboardTitle = txt;
                console.log(`  ✓ Método 1 (dropdown): "${dashboardTitle}"`);
                break;
            }
        }

        // Método 2: Tab activo
        if (dashboardTitle === 'Dashboard') {
            const activeTab = document.querySelector('.nav-tabs .nav-link.active');
            const txt = cleanElText(activeTab);
            if (txt && txt.length > 0 && txt.length < 80) {
                dashboardTitle = txt;
                console.log(`  ✓ Método 2 (tab): "${dashboardTitle}"`);
            }
        }

        // Método 3: Título del documento
        if (dashboardTitle === 'Dashboard') {
            const parts = document.title.split(/\s*[-|]\s*/);
            const candidate = parts.find(p => p.trim().length > 0 && p.trim() !== 'GLPI');
            if (candidate) {
                dashboardTitle = candidate.trim();
                console.log(`  ✓ Método 3 (document.title): "${dashboardTitle}"`);
            }
        }

        dashboardTitle = dashboardTitle.replace(/[\r\n\t]/g, ' ').replace(/\s+/g, ' ').trim();
        
        console.log(`\n📊 DASHBOARD FINAL DETECTADO: "${dashboardTitle}"\n`);

        const data = {
            title: dashboardTitle,
            exportDate: new Date().toLocaleString('es-ES'),
            widgets: [],
            gridConfig: {
                columns: 12, // Grid de 12 columnas por defecto en GLPI
                cellHeight: 60 // Altura estimada de celda
            }
        };

        // Capturar todos los widgets
        const widgetElements = gridStack.querySelectorAll('.grid-stack-item');
        
        console.log(`🔍 Encontrados ${widgetElements.length} elementos grid-stack-item`);

        widgetElements.forEach((element, index) => {
            try {
                const widget = captureWidget(element, index);
                if (widget) {
                    data.widgets.push(widget);
                    console.log(`  ✓ Widget ${index + 1}/${widgetElements.length}:`);
                    console.log(`     Tipo: ${widget.type}`);
                    console.log(`     Título: "${widget.title}"`);
                    console.log(`     Valor: "${widget.value}"`);
                    console.log(`     Posición: (${widget.position.x},${widget.position.y}) Tamaño: ${widget.position.width}x${widget.position.height}`);
                    console.log(`     Color: #${widget.color}`);
                }
            } catch (error) {
                console.error(`  ✗ Error capturando widget ${index + 1}:`, error);
            }
        });

        return data;
    }

    function captureWidget(element, index) {
        const content = element.querySelector('.grid-stack-item-content') || element;
        const card = content.querySelector('.card') || content;

        // Obtener posición en el grid
        const position = {
            x: parseInt(element.getAttribute('data-gs-x') || element.getAttribute('gs-x') || 0),
            y: parseInt(element.getAttribute('data-gs-y') || element.getAttribute('gs-y') || 0),
            width: parseInt(element.getAttribute('data-gs-w') || element.getAttribute('gs-w') || 3),
            height: parseInt(element.getAttribute('data-gs-h') || element.getAttribute('gs-h') || 2)
        };
        
        // NUEVO ENFOQUE: Capturar TODO el contenido visible de forma robusta
        let titleText = '';
        let bodyText = '';
        let tableData = null;
        let chartData = null;
        
        // 1. Buscar título en múltiples posibles ubicaciones
        const titleSelectors = [
            '.main-label',          // GLPI: título principal de widget de gráfica
            '.g-chart > p',         // GLPI: párrafo directo dentro del .g-chart
            '.card-header h2',
            '.card-header h3', 
            '.card-header h4',
            '.card-header span',
            '.card-header .card-title',
            '.card-header',
            '.card-title',
            'h2', 'h3', 'h4',
            '.label',
            'p'
        ];
        
        for (const selector of titleSelectors) {
            const titleEl = card.querySelector(selector);
            if (titleEl && titleEl.textContent.trim()) {
                const txt = titleEl.textContent.trim();
                // Ignorar textos muy largos (probablemente cuerpo, no título) y elementos de leyenda
                if (txt.length > 0 && txt.length < 120 && !titleEl.closest('.legend, .legendLabel, .ct-legend')) {
                    titleText = txt;
                    break;
                }
            }
        }
        
        // 2. Buscar cuerpo del widget
        const bodySelectors = [
            '.card-body',
            '.dashboard-card',
            '.widget-content',
            '.card-content'
        ];
        
        let bodyElement = null;
        for (const selector of bodySelectors) {
            bodyElement = card.querySelector(selector);
            if (bodyElement) break;
        }
        
        if (!bodyElement) {
            bodyElement = card; // Usar toda la card si no hay cuerpo específico
        }
        
        // 3. Capturar TABLAS si existen
        const table = bodyElement.querySelector('table');
        if (table) {
            tableData = captureTableData(table);
        }
        
        // 4. Capturar texto visible (clonar para no destruir el DOM)
        const bodyClone = bodyElement.cloneNode(true);
        bodyClone.querySelectorAll('style, script, svg').forEach(el => el.remove());
        bodyText = bodyClone.textContent.trim();

        // 4b. Estructura específica de GLPI bigNumber: sobreescribe título/valor si aplica
        const bigNumberEl = card.querySelector('.g-bigNumber, .big-number, [class*="bigNumber"]');
        if (bigNumberEl) {
            const countEl = bigNumberEl.querySelector('.count, span.count');
            const labelEl = bigNumberEl.querySelector('.label, span.label');
            // Si hay conteo explícito, usarlo como valor; si hay etiqueta, usarla como título
            if (countEl && countEl.textContent.trim()) {
                bodyText = countEl.textContent.trim();
            }
            if (labelEl && labelEl !== countEl && labelEl.textContent.trim()) {
                titleText = labelEl.textContent.trim();
            } else if (!titleText && countEl) {
                // Sin etiqueta separada: tomar el texto que NO es el número del widget
                const allText = bigNumberEl.textContent.trim();
                const numPart = (countEl.textContent || '').trim();
                const rest = allText.replace(numPart, '').trim();
                if (rest.length > 0 && rest.length < 100) titleText = rest;
            }
        }
        
        // 5. Intentar capturar datos de gráficos (MÚLTIPLES FUENTES)
        // ================================================================
        // Prioridad: 1) Chartist.js (GLPI default) 2) Chart.js 3) SVG genérico 4) Tabla
        // ================================================================
        
        // Buscar en todo el widget, no solo en bodyElement
        const widgetEl = element;
        
        // Selectores para Chartist (GLPI usa Chartist para TODAS sus gráficas)
        const ctChart = widgetEl.querySelector('.ct-chart, [class*="ct-chart"], svg.ct-chart-bar, svg.ct-chart-line, svg.ct-chart-pie, svg.ct-chart-donut') || 
                        bodyElement.querySelector('.ct-chart, [class*="ct-chart"]');
        
        // También detectar SVG que contenga elementos Chartist
        let ctSVG = null;
        if (!ctChart) {
            const allSVGs = widgetEl.querySelectorAll('svg');
            for (const svg of allSVGs) {
                if (svg.querySelector('.ct-bar, .ct-line, .ct-point, .ct-slice-pie, .ct-slice-donut, .ct-slice-donut-solid')) {
                    ctSVG = svg;
                    break;
                }
            }
        }
        
        const canvas = bodyElement.querySelector('canvas');
        const svgElement = bodyElement.querySelector('svg:not(.ct-chart svg)');
        
        // --- MÉTODO 1: Chartist.js (GLPI usa Chartist por defecto) ---
        if (ctChart || ctSVG) {
            const chartEl = ctChart || ctSVG;
            console.log(`    📊 Chartist encontrado en widget ${index + 1}`);
            
            // Intentar desde pre-escaneo
            if (window._glpiChartistMap) {
                // Buscar el elemento o su SVG hijo en el map
                if (window._glpiChartistMap.has(chartEl)) {
                    chartData = window._glpiChartistMap.get(chartEl);
                } else {
                    const svgInside = chartEl.tagName === 'svg' ? chartEl : chartEl.querySelector('svg');
                    if (svgInside && window._glpiChartistMap.has(svgInside)) {
                        chartData = window._glpiChartistMap.get(svgInside);
                    }
                }
                // Fallback: buscar por padre .g-chart
                if (!chartData) {
                    const gChartParent = chartEl.closest ? chartEl.closest('.g-chart') : null;
                    if (gChartParent && window._glpiChartistMap.has(gChartParent)) {
                        chartData = window._glpiChartistMap.get(gChartParent);
                    }
                }
                // Fallback: buscar por ct-chart dentro del widget
                if (!chartData) {
                    window._glpiChartistMap.forEach(function(val, key) {
                        if (!chartData && widgetEl.contains(key)) {
                            chartData = val;
                        }
                    });
                }
                if (chartData) {
                    console.log(`    ✅ Pre-scan Chartist: type=${chartData.type}, labels=${chartData.labels.length}, ds=${chartData.datasets.length}`);
                }
            }
            
            // Intentar extracción directa si no hay en pre-scan
            if (!chartData) {
                try {
                    chartData = extractChartistData(chartEl);
                    if (chartData && chartData.labels.length > 0) {
                        console.log(`    ✅ Chartist capturado: type=${chartData.type}, ${chartData.labels.length} labels`);
                    }
                } catch(e) {
                    console.log(`    ⚠️ Error extrayendo Chartist:`, e.message);
                }
            }
        }
        
        // --- MÉTODO 2: Chart.js (canvas) ---
        if (!chartData && canvas) {
            console.log(`    🎨 Canvas encontrado en widget ${index + 1}`);
            
            // Primero: intentar desde el pre-escaneo global
            if (window._glpiChartMap && window._glpiChartMap.has(canvas)) {
                chartData = window._glpiChartMap.get(canvas);
                console.log(`    ✅ Pre-scan Chart.js: type=${chartData.type}, labels=${chartData.labels.length}, ds=${chartData.datasets.length}`);
            }
            
            // Si no se encontró en pre-scan, intentar métodos directos
            if (!chartData) {
                // Método directo 1: Chart.getChart
                try {
                    if (window.Chart && window.Chart.getChart) {
                        const ch = window.Chart.getChart(canvas);
                        if (ch) chartData = safeExtractChart(ch);
                    }
                } catch(e) {}
                
                // Método directo 2: canvas.$chartjs
                if (!chartData) {
                    try {
                        if (canvas.$chartjs && canvas.$chartjs.chart) {
                            chartData = safeExtractChart(canvas.$chartjs.chart);
                        }
                    } catch(e) {}
                }
                
                // Método directo 3: canvas.__chart
                if (!chartData && canvas.__chart) {
                    try { chartData = safeExtractChart(canvas.__chart); } catch(e) {}
                }

                // Método directo 4: iterar Chart.instances
                if (!chartData && window.Chart && window.Chart.instances) {
                    try {
                        const list = Array.isArray(window.Chart.instances) ? window.Chart.instances : Object.values(window.Chart.instances);
                        for (const inst of list) {
                            if (inst && inst.canvas === canvas) {
                                chartData = safeExtractChart(inst);
                                break;
                            }
                        }
                    } catch(e) {}
                }
            }
            
            // Validar resultado
            if (chartData && chartData.datasets && chartData.datasets.length > 0 && chartData.labels.length > 0) {
                console.log(`    ✅ Chart.js capturado: type=${chartData.type}, ${chartData.labels.length} labels, ${chartData.datasets.length} datasets`);
            } else {
                console.log(`    ⚠️ Chart.js: No se pudieron extraer datos estructurados`);
                chartData = null;
            }
        }
        
        // --- MÉTODO 3: SVG genérico ---
        if (!chartData && svgElement) {
            console.log(`    📐 SVG encontrado en widget ${index + 1}`);
            
            // Intentar desde pre-escaneo
            if (window._glpiSVGMap && window._glpiSVGMap.has(svgElement)) {
                chartData = window._glpiSVGMap.get(svgElement);
                console.log(`    ✅ Pre-scan SVG: type=${chartData.type}, labels=${chartData.labels.length}, ds=${chartData.datasets.length}`);
            }
            
            // Intentar extracción directa
            if (!chartData) {
                try {
                    chartData = extractSVGChartData(svgElement);
                    if (chartData && chartData.labels.length > 0) {
                        console.log(`    ✅ SVG capturado: type=${chartData.type}, ${chartData.labels.length} labels`);
                    }
                } catch(e) {
                    console.log(`    ⚠️ Error extrayendo SVG:`, e.message);
                }
            }
        }
        
        // --- MÉTODO 4: Fallback a tabla asociada ---
        if (!chartData && tableData && tableData.rows && tableData.rows.length > 0) {
            // Intentar convertir tabla a datos de gráfica
            if (tableData.headers.length >= 2 && tableData.rows.length >= 1) {
                console.log(`    📋 Convirtiendo tabla a datos de gráfica...`);
                
                const labels = tableData.rows.map(r => r[0] || '');
                const datasets = [];
                
                for (let c = 1; c < tableData.headers.length; c++) {
                    const values = tableData.rows.map(r => {
                        const val = r[c] || '0';
                        return parseFloat(val.replace(/[^\d.-]/g, '')) || 0;
                    });
                    
                    // Solo agregar si hay valores numéricos
                    if (values.some(v => v !== 0)) {
                        datasets.push({
                            label: tableData.headers[c] || `Serie ${c}`,
                            data: values
                        });
                    }
                }
                
                if (datasets.length > 0 && labels.length > 0) {
                    chartData = {
                        type: 'bar', // Por defecto bar para tablas
                        labels: labels,
                        datasets: datasets
                    };
                    console.log(`    ✅ Tabla→Gráfica: ${chartData.labels.length} labels, ${chartData.datasets.length} datasets`);
                }
            }
        }
        
        // 5b. Si hay canvas pero no se capturaron datos, capturar como imagen PNG
        let canvasImage = null;
        const hasChartElement = canvas || ctChart || svgElement;
        if (hasChartElement && !chartData) {
            // Intentar capturar imagen del canvas
            if (canvas) {
                try {
                    canvasImage = canvas.toDataURL('image/png');
                    if (canvasImage && canvasImage.length > 100) {
                        console.log(`    📸 Canvas capturado como imagen (${Math.round(canvasImage.length / 1024)}KB)`);
                    } else {
                        canvasImage = null;
                    }
                } catch(e) {
                    console.log(`    ⚠️ No se pudo capturar canvas como imagen: ${e.message}`);
                    canvasImage = null;
                }
            }
            // Si es SVG o Chartist sin datos, intentar capturar SVG como text/imagen
            if (!canvasImage && (ctChart || svgElement)) {
                const svgEl = ctChart ? ctChart.querySelector('svg') : svgElement;
                if (svgEl) {
                    try {
                        const serializer = new XMLSerializer();
                        const svgString = serializer.serializeToString(svgEl);
                        // Convertir SVG a base64 para insertar como imagen
                        canvasImage = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgString)));
                        console.log(`    📸 SVG capturado como imagen (${Math.round(canvasImage.length / 1024)}KB)`);
                    } catch(e) {
                        console.log(`    ⚠️ No se pudo serializar SVG: ${e.message}`);
                    }
                }
            }
        }
        
        // 6. Detectar tipo simple
        const hasTable = !!table;
        const hasChart = !!chartData || !!canvasImage;
        // Detectar widgets numéricos: "3" solo, o "3\nCasos", o "3 Casos"
        const numberMatch = bodyText.match(/^\s*([\d.,]+[hHdDsSmM]?)\s*$/)
                         || bodyText.match(/^\s*([\d.,]+[hHdDsSmM]?)\s*[\r\n]+/)
                         || bodyText.match(/^\s*([\d.,]+[hHdDsSmM]?)\s+[A-Za-záéíóúñÁÉÍÓÚÑ]/);
        const isNumber = numberMatch && !hasTable && !hasChart;
        
        let type = 'generic';
        if (isNumber) type = 'number';
        else if (hasTable) type = 'table';
        else if (hasChart) type = 'chart';

        // Para widgets numéricos, separar el nombre del número
        if (isNumber && (!titleText || /^[\d.,]+$/.test(titleText.trim()))) {
            // El título podría ser el texto después del número
            const afterNum = bodyText.replace(/^\s*[\d.,]+[hHdDsSmM]?\s*[\r\n]*/, '').trim();
            if (afterNum.length > 0 && afterNum.length < 100) {
                titleText = afterNum;
            }
        }
        
        // 7. Extraer color del widget
        const color = extractBackgroundColor(element);
        
        console.log(`\n📦 WIDGET ${index + 1}:`);
        console.log(`  📍 Posición: (${position.x}, ${position.y}) Tamaño: ${position.width}x${position.height}`);
        console.log(`  📝 Título: "${titleText}"`);
        console.log(`  📄 Contenido: "${bodyText.substring(0, 80)}${bodyText.length > 80 ? '...' : ''}"`);
        console.log(`  📊 Tipo: ${type}`);
        console.log(`  🎨 Color: #${color}`);
        if (hasTable) console.log(`  ✅ Tabla detectada: ${tableData.rows.length} filas`);
        if (hasChart) console.log(`  ✅ Gráfico detectado`);

        // 8. Crear objeto de datos completo
        const widgetData = {
            id: `widget_${index}`,
            position: position,
            type: type,
            // Para charts no usar bodyText como fallback (contiene etiquetas de leyenda concatenadas)
            title: titleText || (type === 'chart' ? ('Gráfico ' + (index + 1)) : bodyText.substring(0, 40)) || 'Widget',
            value: bodyText,
            color: color,
            tableData: tableData,
            chartData: chartData,
            canvasImage: canvasImage
        };

        return widgetData;
    }

    // Función auxiliar para capturar datos de tablas HTML
    function captureTableData(table) {
        const data = { headers: [], rows: [] };
        
        try {
            // Capturar encabezados
            const headerCells = table.querySelectorAll('thead th, thead td');
            if (headerCells.length > 0) {
                headerCells.forEach(cell => {
                    data.headers.push(cell.textContent.trim());
                });
            } else {
                // Si no hay thead, usar la primera fila como encabezados
                const firstRow = table.querySelector('tr');
                if (firstRow) {
                    firstRow.querySelectorAll('th, td').forEach(cell => {
                        data.headers.push(cell.textContent.trim());
                    });
                }
            }
            
            // Capturar filas de datos
            const bodyRows = table.querySelectorAll('tbody tr');
            const rowsToProcess = bodyRows.length > 0 ? bodyRows : table.querySelectorAll('tr');
            
            rowsToProcess.forEach((row, idx) => {
                // Saltar la primera fila si no hay thead y ya la usamos como encabezados
                if (!table.querySelector('thead') && idx === 0) return;
                
                const rowData = [];
                row.querySelectorAll('td, th').forEach(cell => {
                    rowData.push(cell.textContent.trim());
                });
                
                if (rowData.length > 0) {
                    data.rows.push(rowData);
                }
            });
        } catch (e) {
            console.log('  ⚠️ Error capturando tabla:', e);
        }
        
        return data;
    }

    function detectWidgetType(element) {
        // Detectar gráficos (Chart.js canvas o ct-chart)
        if (element.querySelector('canvas') || element.querySelector('.ct-chart')) {
            return 'chart';
        }

        // Detectar tablas
        if (element.querySelector('table')) {
            return 'table';
        }

        // Detectar widgets de número grande (big number)
        // GLPI usa varias estructuras posibles para números grandes
        const bigNumber = element.querySelector('.big-number') || 
                         element.querySelector('.card-body h3') ||
                         element.querySelector('.card-body h2') ||
                         element.querySelector('.display-4') ||
                         element.querySelector('.main-number');
        
        if (bigNumber) {
            return 'number';
        }

        // Detectar widgets de resumen (múltiples valores)
        if (element.querySelector('.summary-numbers') || 
            element.querySelectorAll('.card-body .row .col').length > 2) {
            return 'summary';
        }

        return 'generic';
    }

    function captureNumberWidget(element, widgetData, bodyText) {
        // Extraer el número más grande del texto
        const numbers = bodyText.match(/\d+/g);
        
        if (numbers && numbers.length > 0) {
            // Usar el primer número encontrado (generalmente el más grande en widgets de número)
            widgetData.value = numbers[0];
        } else {
            widgetData.value = bodyText || '0';
        }

        return widgetData;
    }

    function captureChartWidget(element, widgetData, bodyText) {
        // Intentar extraer datos del gráfico
        const canvas = element.querySelector('canvas');
        let chartData = null;
        
        if (canvas) {
            console.log(`    🎨 Canvas encontrado`);
            
            // Intentar múltiples métodos para obtener datos de Chart.js
            if (canvas.chart) {
                chartData = extractChartJsData(canvas.chart);
            } else if (window.Chart && window.Chart.instances) {
                for (let instance of Object.values(window.Chart.instances)) {
                    if (instance && instance.canvas === canvas) {
                        chartData = extractChartJsData(instance);
                        break;
                    }
                }
            }
        }
        
        if (chartData && chartData.datasets && chartData.datasets.length > 0) {
            widgetData.chartData = chartData;
            widgetData.value = `Gráfico: ${chartData.datasets.length} serie(s)`;
        } else {
            // Si no hay datos de gráfico, al menos capturar el texto visible
            widgetData.chartData = {
                type: 'chart',
                labels: ['Dato'],
                datasets: [{
                    label: widgetData.title,
                    data: [bodyText.match(/\d+/) ? bodyText.match(/\d+/)[0] : 0]
                }]
            };
            widgetData.value = bodyText || 'Gráfico';
        }

        return widgetData;
    }

    function extractChartJsData(chart) {
        try {
            if (!chart || !chart.data) {
                return null;
            }
            
            const data = {
                type: chart.config?.type || chart.type || 'unknown',
                labels: chart.data.labels || [],
                datasets: []
            };
            
            // Extraer datasets con todos sus datos
            if (chart.data.datasets) {
                data.datasets = chart.data.datasets.map(ds => ({
                    label: ds.label || 'Serie',
                    data: ds.data || [],
                    backgroundColor: Array.isArray(ds.backgroundColor) ? ds.backgroundColor : [ds.backgroundColor],
                    borderColor: ds.borderColor,
                    type: ds.type || data.type
                }));
            }
            
            return data;
        } catch (error) {
            console.error('Error extrayendo datos de Chart.js:', error);
            return { type: 'unknown', error: error.message };
        }
    }

    function captureTableWidget(element, widgetData) {
        // Capturar datos de la tabla
        const table = element.querySelector('table');
        if (table) {
            widgetData.tableData = extractTableData(table);
            widgetData.value = `${widgetData.tableData.rows.length} filas × ${widgetData.tableData.headers.length} columnas`;
            console.log(`    📋 Tabla: ${widgetData.tableData.headers.length} cols, ${widgetData.tableData.rows.length} filas`);
            console.log(`    Headers:`, widgetData.tableData.headers);
        } else {
            console.log(`    ⚠ No se encontró <table>`);
            widgetData.value = 'Tabla sin datos';
        }

        return widgetData;
    }

    function captureSummaryWidget(element, widgetData, bodyText) {
        // Obtener todos los números del texto
        const numbers = bodyText.match(/\d+/g) || [];
        const lines = bodyText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        
        const summaryItems = [];
        
        // Intentar emparejar números con texto
        lines.forEach(line => {
            const num = line.match(/\d+/);
            const text = line.replace(/\d+/g, '').trim();
            
            if (num && text) {
                summaryItems.push({ value: num[0], label: text });
            }
        });
        
        widgetData.summaryData = summaryItems;
        widgetData.value = summaryItems.length > 0 
            ? `${summaryItems.length} items` 
            : bodyText || 'Resumen';

        return widgetData;
    }

    function extractBackgroundColor(element) {
        let bgColor;

        // Intentar múltiples elementos en orden de prioridad
        const elementsToCheck = [
            element.querySelector('.card-header'),
            element.querySelector('.card'),
            element.querySelector('.grid-stack-item-content'),
            element
        ];
        
        for (const el of elementsToCheck) {
            if (!el) continue;
            
            bgColor = window.getComputedStyle(el).backgroundColor;
            if (isValidColor(bgColor)) {
                const hex = rgbToHex(bgColor);
                console.log(`  🎨 Color detectado de ${el.className}: ${bgColor} = #${hex}`);
                return hex;
            }
        }
        
        // Color por defecto si no se encuentra ninguno válido
        console.log(`  🎨 Usando color por defecto`);
        return '2196F3'; // Azul por defecto
    }

    function isValidColor(bgColor) {
        if (!bgColor || bgColor === 'transparent' || bgColor === 'rgba(0, 0, 0, 0)') {
            return false;
        }
        
        // Convertir a hex y verificar si es blanco o negro
        const hex = rgbToHex(bgColor);
        if (hex === 'FFFFFF' || hex === '000000') {
            return false;
        }
        
        return true;
    }

    function rgbToHex(rgb) {
        if (!rgb || rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') {
            return '2196F3'; // Azul por defecto
        }

        const match = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!match) {
            return '2196F3';
        }

        const r = parseInt(match[1]);
        const g = parseInt(match[2]);
        const b = parseInt(match[3]);

        const hex = ((r << 16) | (g << 8) | b).toString(16).padStart(6, '0').toUpperCase();
        return hex;
    }

    function extractTableData(table) {
        const data = {
            headers: [],
            rows: []
        };

        // Capturar encabezados
        const headerCells = table.querySelectorAll('thead th, thead td');
        headerCells.forEach(cell => {
            data.headers.push(cell.textContent.trim());
        });

        // Si no hay thead, usar la primera fila
        if (data.headers.length === 0) {
            const firstRow = table.querySelector('tr');
            if (firstRow) {
                const cells = firstRow.querySelectorAll('th, td');
                cells.forEach(cell => {
                    data.headers.push(cell.textContent.trim());
                });
            }
        }

        // Capturar filas de datos
        const bodyRows = table.querySelectorAll('tbody tr');
        bodyRows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td, th');
            cells.forEach(cell => {
                rowData.push(cell.textContent.trim());
            });
            if (rowData.length > 0) {
                data.rows.push(rowData);
            }
        });

        // Si no hay tbody, capturar todas las filas excepto la primera
        if (data.rows.length === 0) {
            const allRows = Array.from(table.querySelectorAll('tr')).slice(1);
            allRows.forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('td, th');
                cells.forEach(cell => {
                    rowData.push(cell.textContent.trim());
                });
                if (rowData.length > 0) {
                    data.rows.push(rowData);
                }
            });
        }

        return data;
    }

    function showNotification(message, type) {
        console.log(`${type.toUpperCase()}: ${message}`);
        
        // Intentar usar el sistema de notificaciones de GLPI
        if (typeof glpi_toast_info === 'function' && type === 'success') {
            glpi_toast_info(message);
        } else if (typeof glpi_toast_error === 'function' && type === 'error') {
            glpi_toast_error(message);
        } else {
            // Fallback a alert
            alert(message);
        }
    }

})();
