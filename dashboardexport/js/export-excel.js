/**
 * GLPI Dashboard → Excel (.xlsx)
 * Gráficas nativas editables + tarjetas de colores
 * XML basado exactamente en el demo funcional probado
 */
(function () {
    'use strict';

    var COLS = 12;
    var RPG  = 4;
    var HDR  = 2;
    var SN   = 'Dashboard';
    var PAL  = ['4472C4','ED7D31','A5A5A5','FFC000','5B9BD5',
                '70AD47','264478','9B57A0','00B050','BF8F00'];

    window.exportDashboardToExcel = async function (data) {
        try {
            console.log('📊 Generando Excel con gráficas…');
            var info = buildAll(data);
            var blob = await zipIt(info);
            var ts = new Date().toISOString().slice(0, 10).replace(/-/g, '');
            var fn = 'GLPI_' + clean(data.title) + '_' + ts + '.xlsx';
            saveAs(blob, fn);
            console.log('✅ Descargado:', fn);
        } catch (e) {
            console.error('❌ Error:', e);
            alert('Error generando Excel: ' + e.message);
        }
    };

    function clean(s) {
        return (s || 'Dashboard').replace(/[^\w\s]/g, '_').substring(0, 40).trim();
    }

    /* ====================================================================
       PASO 1 — Construir estructura de datos
       ==================================================================== */
    function buildAll(data) {
        var cells  = [];
        var merges = [];
        var charts = [];
        var images = [];
        var colorMap = {};
        var colorList = [];

        function regColor(hex) {
            hex = normHex(hex);
            if (colorMap[hex] === undefined) {
                colorMap[hex] = colorList.length;
                colorList.push(hex);
            }
            return colorMap[hex];
        }

        var maxGR = 0;
        (data.widgets || []).forEach(function (w) {
            var b = w.position.y + w.position.height;
            if (b > maxGR) maxGR = b;
        });

        /* ---- Encabezado ---- */
        var dashTitle = data.title || 'Dashboard GLPI';
        cells.push({r: 1, c: 1, v: 'Tablero: ' + dashTitle, s: 'title'});
        merges.push({r1: 1, c1: 1, r2: 1, c2: COLS});
        cells.push({r: 2, c: 1, v: 'Exportado el: ' + (data.exportDate || new Date().toLocaleString('es-ES')), s: 'sub'});
        merges.push({r1: 2, c1: 1, r2: 2, c2: COLS});

        /* ---- Widgets ---- */
        var chartQ = [];
        (data.widgets || []).forEach(function (w) {
            var r1 = HDR + 1 + w.position.y * RPG;
            var rN = r1 + w.position.height * RPG - 1;
            var c1 = w.position.x + 1;
            var cN = c1 + w.position.width - 1;
            var bg = normHex(w.color || 'E0E0E0');

            var cd = extractCD(w);
            if (cd && cd.labels.length >= 1) {
                chartQ.push({w: w, cd: cd, r1: r1, rN: rN, c1: c1, cN: cN, bg: bg});
            } else if (w.canvasImage) {
                /* Widget con gráfico capturado como imagen */
                // Eliminar cualquier prefijo data:image/..., ya sea png, svg, jpeg, etc.
                var imgBase64 = w.canvasImage.replace(/^data:image\/[^;]+;base64,/, '');
                // Solo agregar si es válido y no es SVG (que no se puede incrustar fácilmente en Excel)
                if (imgBase64 && imgBase64.length > 100 && !w.canvasImage.includes('svg+xml')) {
                    images.push({
                        base64: imgBase64,
                        title: w.title || 'Gráfico',
                        fromCol: c1 - 1,
                        fromRow: r1 - 1,
                        toCol: cN,
                        toRow: rN
                    });
                    console.log('  📸 Imagen "' + (w.title || 'Gráfico') + '" pos=(' + (c1-1) + ',' + (r1-1) + ')');
                } else {
                    // Si es SVG o inválido, crear tarjeta simple
                    var ci = regColor(bg);
                    makeCard(cells, merges, w, r1, rN, c1, cN, ci);
                    console.log('  ⚠️ Imagen SVG o inválida, usando tarjeta para "' + (w.title || 'Widget') + '"');
                }
            } else {
                var ci = regColor(bg);
                makeCard(cells, merges, w, r1, rN, c1, cN, ci);
            }
        });

        /* ---- Datos de gráficas (debajo del grid) ---- */
        var dataRow = HDR + maxGR * RPG + 4;

        chartQ.forEach(function (cq, idx) {
            var cd    = cq.cd;
            var title = cq.w.title || ('Gráfico ' + (idx + 1));
            var ctype = guessCT(cq.w, cd);
            var nPts  = cd.labels.length;
            var nDS   = cd.datasets.length;
            var bg    = cq.bg || 'FFFFFF';

            /* Título sección */
            cells.push({r: dataRow, c: 1, v: title, s: 'section'});
            merges.push({r1: dataRow, c1: 1, r2: dataRow, c2: 1 + nDS});
            dataRow++;

            /* Encabezados columna */
            cells.push({r: dataRow, c: 1, v: 'Categoría', s: 'tHead'});
            cd.datasets.forEach(function (ds, di) {
                cells.push({r: dataRow, c: 2 + di, v: ds.label || ('Serie ' + (di + 1)), s: 'tHead'});
            });
            dataRow++;

            /* Filas de datos */
            var fRow = dataRow;
            cd.labels.forEach(function (lbl, li) {
                cells.push({r: dataRow, c: 1, v: String(lbl), s: 'tCell'});
                cd.datasets.forEach(function (ds, di) {
                    cells.push({r: dataRow, c: 2 + di, v: Number(ds.data[li]) || 0, s: 'tCell', isNum: true});
                });
                dataRow++;
            });
            var lRow = dataRow - 1;

            /* Fórmulas para la gráfica */
            var catF = qt(SN) + '!$A$' + fRow + ':$A$' + lRow;
            var series = cd.datasets.map(function (ds, di) {
                var cl = colL(2 + di);
                // Preservar backgroundColor array para pie/doughnut
                var bgColors = null;
                if (ds.backgroundColor) {
                    if (Array.isArray(ds.backgroundColor)) {
                        bgColors = ds.backgroundColor.map(function(c) {
                            return hex6FromAny(c) || PAL[0];
                        });
                    }
                }
                return {
                    name:    ds.label || ('Serie ' + (di + 1)),
                    formula: qt(SN) + '!$' + cl + '$' + fRow + ':$' + cl + '$' + lRow,
                    values:  ds.data.map(function (v) { return Number(v) || 0; }),
                    color:   serColor(ds, di),
                    backgroundColor: bgColors // Array de colores para pie/doughnut
                };
            });

            /* Posición: anclar en el lugar del widget (0-based) */
            charts.push({
                idx:       idx,
                type:      ctype,
                title:     title,
                catF:      catF,
                catLabels: cd.labels.map(String),
                series:    series,
                nPts:      nPts,
                fromCol:   cq.c1 - 1,
                fromRow:   cq.r1 - 1,
                toCol:     cq.cN,
                toRow:     cq.rN,
                color:     bg
            });

            console.log('  📈 Gráfica "' + title + '" tipo=' + ctype + ' pts=' + nPts + ' series=' + series.length);
            dataRow += 2;
        });

        /* ---- Columnas / filas ---- */
        var cols = [];
        for (var i = 1; i <= Math.max(COLS, 14); i++) cols.push({n: i, w: 20});

        var totalR = Math.max(dataRow + 5, HDR + maxGR * RPG + 10);
        var rows = [];
        for (var j = 1; j <= totalR; j++) {
            var h = 22;
            if (j === 1) h = 40;
            else if (j <= HDR) h = 22;
            else if (j <= HDR + maxGR * RPG) h = 55; // alto suficiente para 3 líneas de texto con wrapText
            rows.push({n: j, h: h});
        }

        console.log('📊 Total: ' + (data.widgets || []).length + ' widgets, ' + charts.length + ' gráficas nativas, ' + images.length + ' imágenes, ' + colorList.length + ' colores');
        return {cells: cells, merges: merges, charts: charts, images: images, colors: colorList, cols: cols, rows: rows, title: data.title || 'Dashboard'};
    }

    /* ====================================================================
       TARJETA NUMÉRICA / CONTENIDO
       Solo 2 merges: número arriba + nombre abajo. Sin celdas extra.
       ==================================================================== */
    function makeCard(cells, merges, w, r1, rN, c1, cN, ci) {
        var raw = (w.value || '').replace(/,/g, '');
        var m   = raw.match(/[\d.]+/);
        var num = m ? m[0] : '0';
        var isN = /^\d+(\.\d+)?$/.test(num);

        // Extraer nombre: usar title, pero si title parece un número, buscar en value
        var name = w.title || '';
        if (!name || /^[\d.,\s]+$/.test(name)) {
            // Intentar extraer texto después del número en w.value
            var after = (w.value || '').replace(/^[\s\d.,hHdDsSmM]+/, '').trim();
            if (after.length > 0 && after.length < 100) name = after;
            else name = name || 'Widget';
        }

        var nameRow = rN;
        var numEnd  = Math.max(rN - 1, r1);

        cells.push({r: r1, c: c1, v: isN ? Number(num) : num, s: 'bigNum', ci: ci, isNum: isN});
        merges.push({r1: r1, c1: c1, r2: numEnd, c2: cN});

        cells.push({r: nameRow, c: c1, v: name, s: 'cardName', ci: ci});
        merges.push({r1: nameRow, c1: c1, r2: nameRow, c2: cN});
    }

    /* ====================================================================
       EXTRAER DATOS PARA GRÁFICAS
       ==================================================================== */
    function extractCD(w) {
        if (w.chartData && w.chartData.labels && w.chartData.labels.length > 0 &&
            w.chartData.datasets && w.chartData.datasets.length > 0) {
            var ok = w.chartData.datasets.some(function (ds) {
                return ds.data && ds.data.some(function (v) { return Number(v) !== 0; });
            });
            if (ok) return w.chartData;
        }
        if (w.tableData && w.tableData.headers && w.tableData.headers.length >= 2 &&
            w.tableData.rows && w.tableData.rows.length > 0) {
            var labels = w.tableData.rows.map(function (r) { return r[0] || ''; });
            var datasets = [];
            for (var c = 1; c < w.tableData.headers.length; c++) {
                datasets.push({
                    label: w.tableData.headers[c],
                    data: w.tableData.rows.map(function (r) { return Number(r[c]) || 0; })
                });
            }
            if (datasets.some(function (ds) { return ds.data.some(function (v) { return v !== 0; }); })) {
                return {labels: labels, datasets: datasets};
            }
        }
        return null;
    }

    function guessCT(w, cd) {
        // Prioridad 1: Tipo explícito del chartData (incluye todos los tipos de GLPI)
        if (w.chartData && w.chartData.type) {
            var t = w.chartData.type.toLowerCase();
            // Tipos circulares
            if (t === 'pie') return 'pie';
            if (t === 'halfpie') return 'halfpie';
            if (t === 'doughnut' || t === 'donut') return 'doughnut';
            if (t === 'halfdonut') return 'halfdonut';
            // Tipos de línea/área
            if (t === 'line' || t === 'lines') return 'line';
            if (t === 'area' || t === 'areas') return 'area';
            // Tipos de barra horizontal
            if (t === 'hbar' || t === 'horizontalbar') return 'hbar';
            if (t === 'stackedhbar') return 'stackedHBar';
            if (t === 'hbars') {
                // Múltiples series horizontales: si labels son fechas → stacked
                var hHasDate = cd && cd.labels && cd.labels.some(function(l) { return /^\d{4}[-\/]\d{2}/.test(String(l)); });
                return hHasDate ? 'stackedHBar' : 'hbar';
            }
            // Tipos de barra vertical apilada
            if (t === 'stackedbar') return 'stackedBar';
            // Tipos de barra vertical normal/múltiple
            if (t === 'bar' || t === 'column') return 'bar';
            if (t === 'bars') {
                // Múltiples series: si labels son fechas/meses → stacked (patrón GLPI)
                var hasDate = cd && cd.labels && cd.labels.some(function(l) { return /^\d{4}[-\/]\d{2}/.test(String(l)); });
                return hasDate ? 'stackedBar' : 'bar';
            }
            if (t === 'radar' || t === 'polar') return 'bar'; // Excel no soporta radar nativamente
            if (t === 'scatter' || t === 'bubble') return 'bar';
            return 'bar';
        }
        
        // Prioridad 2: Heurística por título del widget
        var n = (w.title || '').toLowerCase();
        
        // Gráficas circulares (solo si el título lo indica explícitamente)
        if (/\bpie\b|pastel|torta|\bsector\b/.test(n)) return 'pie';
        if (/\bdonut\b|\bdona\b|\bdoughnut\b|anillo|rosquilla/.test(n)) return 'doughnut';
        
        // Gráficas de línea/área
        if (/line|linea|línea|evol|tendencia|tiempo|trend/.test(n)) return 'line';
        if (/area|área|acumul/.test(n)) return 'area';
        if (/mes|año|dia|día|semana|month|year|day|week|fecha|date/.test(n)) return 'line';
        
        // Gráficas de barras horizontales
        if (/horizontal|horiz/.test(n)) return 'hbar';
        if (/ranking|top|mejor|peor/.test(n)) return 'hbar';
        
        // Prioridad 3: Heurística por datos
        // Si hay muchos labels largos, usar hbar (se ve mejor)
        if (cd && cd.labels) {
            var avgLabelLen = cd.labels.reduce(function(a, l) { return a + String(l).length; }, 0) / cd.labels.length;
            if (avgLabelLen > 15 && cd.labels.length <= 10) return 'hbar';
        }
        
        return 'bar'; // Default
    }

    function serColor(ds, idx) {
        var raw = ds.backgroundColor
            ? (Array.isArray(ds.backgroundColor) ? ds.backgroundColor[0] : ds.backgroundColor)
            : ds.borderColor;
        if (raw) { var h = hex6(raw); if (h) return h; }
        return PAL[idx % PAL.length];
    }

    function hex6(c) {
        if (!c || typeof c !== 'string') return null;
        c = c.trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(c)) return c.slice(1).toUpperCase();
        if (/^#[0-9A-Fa-f]{3}$/.test(c)) return (c[1]+c[1]+c[2]+c[2]+c[3]+c[3]).toUpperCase();
        var m = c.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
        if (m) return p2(m[1]) + p2(m[2]) + p2(m[3]);
        return null;
    }

    /* ====================================================================
       PASO 2 — Crear ZIP
       ==================================================================== */
    async function zipIt(info) {
        var z = new JSZip();
        var hasDrawing = info.charts.length > 0 || info.images.length > 0;

        z.file('[Content_Types].xml', xCT(info.charts, info.images));
        z.folder('_rels').file('.rels', xRootRels());
        z.folder('docProps').file('app.xml', xApp());
        z.folder('docProps').file('core.xml', xCore(info.title));

        var xl = z.folder('xl');
        xl.file('workbook.xml', xWB());
        xl.folder('_rels').file('workbook.xml.rels', xWBRels());
        xl.file('styles.xml', xStyles(info.colors));
        xl.folder('worksheets').file('sheet1.xml', xSheet(info));

        if (hasDrawing) {
            xl.folder('worksheets').folder('_rels').file('sheet1.xml.rels', xSheetRels());
            xl.folder('drawings').file('drawing1.xml', xDrawing(info.charts, info.images));
            xl.folder('drawings').folder('_rels').file('drawing1.xml.rels', xDrawRels(info.charts, info.images));

            info.charts.forEach(function (ch, i) {
                xl.folder('charts').file('chart' + (i + 1) + '.xml', xChart(ch));
            });

            if (info.images.length > 0) {
                var media = xl.folder('media');
                info.images.forEach(function (img, i) {
                    media.file('image' + (i + 1) + '.png', img.base64, {base64: true});
                });
            }
        }

        return z.generateAsync({type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
    }

    /* ====================================================================
       PASO 3 — Generadores XML (estructura idéntica al demo funcional)
       ==================================================================== */

    function xCT(charts, images) {
        var x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">\n';
        x += '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>\n';
        x += '  <Default Extension="xml" ContentType="application/xml"/>\n';
        if (images && images.length > 0) {
            x += '  <Default Extension="png" ContentType="image/png"/>\n';
        }
        x += '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>\n';
        x += '  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>\n';
        x += '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>\n';
        if ((charts && charts.length > 0) || (images && images.length > 0)) {
            x += '  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>\n';
        }
        if (charts && charts.length > 0) {
            charts.forEach(function (_, i) {
                x += '  <Override PartName="/xl/charts/chart' + (i+1) + '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>\n';
            });
        }
        x += '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>\n';
        x += '  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>\n';
        x += '</Types>';
        return x;
    }

    function xRootRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
        '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>\n' +
        '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>\n' +
        '  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>\n' +
        '</Relationships>';
    }

    function xApp() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
        '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">\n' +
        '  <Application>GLPI Dashboard Export</Application>\n  <AppVersion>1.0</AppVersion>\n</Properties>';
    }

    function xCore(t) {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
        '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"' +
        ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"' +
        ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">\n' +
        '  <dc:title>' + esc(t) + '</dc:title>\n  <dc:creator>GLPI</dc:creator>\n' +
        '  <dcterms:created xsi:type="dcterms:W3CDTF">' + new Date().toISOString() + '</dcterms:created>\n' +
        '</cp:coreProperties>';
    }

    function xWB() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' +
        ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">\n' +
        '  <sheets><sheet name="' + SN + '" sheetId="1" r:id="rId1"/></sheets>\n</workbook>';
    }

    function xWBRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
        '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>\n' +
        '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>\n' +
        '</Relationships>';
    }

    /* ======================== ESTILOS ========================
       Fonts: 0=default 1=title(18b oscuro) 2=sub(11 gris) 3=bigNum(36b dark)
              4=cardName(13b dark) 5=section(13b) 6=tHead(11b white) 7=tCell(11)
       Fills: 0=none 1=gray125 2=blue(#4472C4) 3..N=dinámicos
       Borders: 0=none 1=thin
       CellXfs: 0=default 1=title(oscuro sin fondo) 2=sub(gris sin fondo) 3=section 4=tHead 5=tCell
                6+ci*2 = bigNum  6+ci*2+1 = cardName
    */
    function xStyles(colors) {
        var x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">\n';

        x += '  <fonts count="8">\n';
        x += '    <font><sz val="11"/><name val="Calibri"/></font>\n';                                              // 0 default
        x += '    <font><b/><sz val="18"/><color rgb="FF1D1D1D"/><name val="Calibri"/></font>\n';                   // 1 title (oscuro)
        x += '    <font><sz val="11"/><color rgb="FF666666"/><name val="Calibri"/></font>\n';                        // 2 sub (gris)
        x += '    <font><b/><sz val="36"/><color rgb="FF333333"/><name val="Calibri"/></font>\n';                    // 3 bigNum
        x += '    <font><b/><sz val="13"/><color rgb="FF333333"/><name val="Calibri"/></font>\n';                    // 4 cardName
        x += '    <font><b/><sz val="13"/><name val="Calibri"/></font>\n';                                           // 5 section
        x += '    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>\n';                   // 6 tHead white
        x += '    <font><sz val="11"/><name val="Calibri"/></font>\n';                                              // 7 tCell
        x += '  </fonts>\n';

        var nF = 3 + colors.length; // 0=none 1=gray125 2=blue, 3..N=dinámicos
        x += '  <fills count="' + nF + '">\n';
        x += '    <fill><patternFill patternType="none"/></fill>\n';                                                 // 0
        x += '    <fill><patternFill patternType="gray125"/></fill>\n';                                              // 1
        x += '    <fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/></patternFill></fill>\n';          // 2 azul (tHead)
        colors.forEach(function (c) {
            x += '    <fill><patternFill patternType="solid"><fgColor rgb="FF' + c + '"/></patternFill></fill>\n';
        });
        x += '  </fills>\n';

        x += '  <borders count="2">\n    <border/>\n';
        x += '    <border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border>\n';
        x += '  </borders>\n';

        x += '  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>\n';

        var nX = 6 + colors.length * 2;
        x += '  <cellXfs count="' + nX + '">\n';
        x += '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>\n';                                                                                                 // 0 default
        x += '    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="left" vertical="center"/></xf>\n';                            // 1 title (sin fondo)
        x += '    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="left" vertical="center"/></xf>\n';                            // 2 sub (sin fondo)
        x += '    <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="left" vertical="center"/></xf>\n';                          // 3 section
        x += '    <xf numFmtId="0" fontId="6" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>\n'; // 4 tHead (azul)
        x += '    <xf numFmtId="0" fontId="7" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>\n';                              // 5 tCell

        colors.forEach(function (_, ci) {
            var fi = 3 + ci; // dinámicos empiezan en 3
            x += '    <xf numFmtId="0" fontId="3" fillId="' + fi + '" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>\n';
            x += '    <xf numFmtId="0" fontId="4" fillId="' + fi + '" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>\n';
        });
        x += '  </cellXfs>\n</styleSheet>';
        return x;
    }

    /* ======================== HOJA ======================== */
    function xSheet(info) {
        var x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n';
        x += '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        x += ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">\n';

        var mr = 1, mc = 1;
        info.cells.forEach(function (c) { if (c.r > mr) mr = c.r; if (c.c > mc) mc = c.c; });
        x += '  <dimension ref="A1:' + colL(mc) + mr + '"/>\n';
        x += '  <sheetViews><sheetView workbookViewId="0"/></sheetViews>\n';

        x += '  <cols>\n';
        info.cols.forEach(function (c) {
            x += '    <col min="' + c.n + '" max="' + c.n + '" width="' + c.w + '" customWidth="1"/>\n';
        });
        x += '  </cols>\n';

        x += '  <sheetData>\n';
        var byR = {};
        info.cells.forEach(function (c) { (byR[c.r] = byR[c.r] || []).push(c); });

        Object.keys(byR).map(Number).sort(function (a, b) { return a - b; }).forEach(function (rn) {
            var rd = info.rows[rn - 1];
            var a = 'r="' + rn + '"';
            if (rd) a += ' ht="' + rd.h + '" customHeight="1"';
            x += '    <row ' + a + '>';
            byR[rn].sort(function (a, b) { return a.c - b.c; }).forEach(function (cell) {
                var ref = colL(cell.c) + cell.r;
                var si  = styleIdx(cell.s, cell.ci);
                if (cell.isNum) {
                    x += '<c r="' + ref + '" s="' + si + '"><v>' + cell.v + '</v></c>';
                } else {
                    x += '<c r="' + ref + '" s="' + si + '" t="inlineStr"><is><t>' + esc(String(cell.v)) + '</t></is></c>';
                }
            });
            x += '</row>\n';
        });
        x += '  </sheetData>\n';

        if (info.merges.length > 0) {
            x += '  <mergeCells count="' + info.merges.length + '">\n';
            info.merges.forEach(function (m) {
                x += '    <mergeCell ref="' + colL(m.c1) + m.r1 + ':' + colL(m.c2) + m.r2 + '"/>\n';
            });
            x += '  </mergeCells>\n';
        }

        if (info.charts.length > 0 || info.images.length > 0) x += '  <drawing r:id="rId1"/>\n';
        x += '</worksheet>';
        return x;
    }

    function xSheetRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
        '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>\n' +
        '</Relationships>';
    }

    /* ======================== DRAWING ======================== */
    function xDrawing(charts, images) {
        var x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n';
        x += '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"';
        x += ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"';
        x += ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        x += ' xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">\n';

        var idCounter = 1;

        /* --- Gráficos nativos --- */
        charts.forEach(function (ch, i) {
            x += '  <xdr:twoCellAnchor>\n';
            x += '    <xdr:from><xdr:col>' + ch.fromCol + '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' + ch.fromRow + '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>\n';
            x += '    <xdr:to><xdr:col>' + ch.toCol + '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' + ch.toRow + '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>\n';
            x += '    <xdr:graphicFrame macro="">\n';
            x += '      <xdr:nvGraphicFramePr>\n';
            x += '        <xdr:cNvPr id="' + idCounter + '" name="Chart ' + (i + 1) + '"/>\n';
            x += '        <xdr:cNvGraphicFramePr/>\n';
            x += '      </xdr:nvGraphicFramePr>\n';
            x += '      <xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>\n';
            x += '      <a:graphic>\n';
            x += '        <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">\n';
            x += '          <c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" r:id="rId' + (i + 1) + '"/>\n';
            x += '        </a:graphicData>\n';
            x += '      </a:graphic>\n';
            x += '    </xdr:graphicFrame>\n';
            x += '    <xdr:clientData/>\n';
            x += '  </xdr:twoCellAnchor>\n';
            idCounter++;
        });

        /* --- Imágenes de canvas --- */
        if (images && images.length > 0) {
            images.forEach(function (img, i) {
                var rIdNum = charts.length + i + 1;
                x += '  <xdr:twoCellAnchor editAs="oneCell">\n';
                x += '    <xdr:from><xdr:col>' + img.fromCol + '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' + img.fromRow + '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>\n';
                x += '    <xdr:to><xdr:col>' + img.toCol + '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' + img.toRow + '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>\n';
                x += '    <xdr:pic>\n';
                x += '      <xdr:nvPicPr>\n';
                x += '        <xdr:cNvPr id="' + idCounter + '" name="Picture ' + (i + 1) + '" descr="' + esc(img.title) + '"/>\n';
                x += '        <xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr>\n';
                x += '      </xdr:nvPicPr>\n';
                x += '      <xdr:blipFill>\n';
                x += '        <a:blip r:embed="rId' + rIdNum + '"/>\n';
                x += '        <a:stretch><a:fillRect/></a:stretch>\n';
                x += '      </xdr:blipFill>\n';
                x += '      <xdr:spPr>\n';
                x += '        <a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></a:xfrm>\n';
                x += '        <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>\n';
                x += '      </xdr:spPr>\n';
                x += '    </xdr:pic>\n';
                x += '    <xdr:clientData/>\n';
                x += '  </xdr:twoCellAnchor>\n';
                idCounter++;
            });
        }

        x += '</xdr:wsDr>';
        return x;
    }

    function xDrawRels(charts, images) {
        var x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n';
        x += '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n';
        charts.forEach(function (_, i) {
            x += '  <Relationship Id="rId' + (i + 1) + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart' + (i + 1) + '.xml"/>\n';
        });
        if (images && images.length > 0) {
            images.forEach(function (_, i) {
                var rIdNum = charts.length + i + 1;
                x += '  <Relationship Id="rId' + rIdNum + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image' + (i + 1) + '.png"/>\n';
            });
        }
        x += '</Relationships>';
        return x;
    }

    /* ======================== CHART XML ======================== */
    function xChart(ch) {
        var x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n';
        x += '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">\n';
        // Fondo del área del gráfico = color del widget; sin borde exterior
        x += '  <c:spPr><a:solidFill><a:srgbClr val="' + (ch.color || 'FFFFFF') + '"/></a:solidFill><a:ln><a:noFill/></a:ln></c:spPr>\n';
        x += '  <c:chart>\n';
        x += '    <c:autoTitleDeleted val="0"/>\n';
        x += '    <c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>' + esc(ch.title) + '</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>\n';
        x += '    <c:plotArea>\n';

        // Seleccionar tipo de gráfica nativa de Excel
        switch (ch.type) {
            case 'pie':
                x += cPie(ch);
                break;
            case 'halfpie':
                x += cHalfPie(ch);
                break;
            case 'doughnut':
                x += cDoughnut(ch);
                break;
            case 'halfdonut':
                x += cHalfDonut(ch);
                break;
            case 'line':
            case 'area':
            case 'lines':
            case 'areas':
                x += cLine(ch);
                break;
            case 'hbar':
                x += cHBar(ch);
                break;
            case 'stackedBar':
                x += cStackedBar(ch);
                break;
            case 'stackedHBar':
                x += cStackedHBar(ch);
                break;
            case 'bar':
            default:
                x += cBar(ch);
                break;
        }

        x += '    </c:plotArea>\n';
        // Leyenda: a la derecha si tiene múltiples series, abajo si es simple
        var lgPos = (ch.series && ch.series.length > 1) ? 'r' : 'b';
        x += '    <c:legend><c:legendPos val="' + lgPos + '"/><c:overlay val="0"/></c:legend>\n';
        x += '    <c:plotVisOnly val="1"/>\n';
        x += '  </c:chart>\n</c:chartSpace>';
        return x;
    }

    /* --- PIE --- */
    function cPie(ch) {
        var x = '      <c:pieChart>\n        <c:varyColors val="1"/>\n';
        var s = ch.series[0];
        if (s) {
            x += '        <c:ser>\n          <c:idx val="0"/>\n          <c:order val="0"/>\n';
            // Agregar colores por punto para pie
            x += cDPt(ch, s);
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        }
        x += '      </c:pieChart>\n';
        return x;
    }

    /* --- DOUGHNUT (Dona) --- */
    function cDoughnut(ch) {
        var x = '      <c:doughnutChart>\n        <c:varyColors val="1"/>\n';
        var s = ch.series[0];
        if (s) {
            x += '        <c:ser>\n          <c:idx val="0"/>\n          <c:order val="0"/>\n';
            // Agregar colores por punto para doughnut
            x += cDPt(ch, s);
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        }
        // Hole size: 50% por defecto (similar a Chart.js doughnut)
        x += '        <c:holeSize val="50"/>\n';
        x += '      </c:doughnutChart>\n';
        return x;
    }

    /* -----------------------------------------------------------------------
       HALF PIE (Semicírculo) — Ghost slice technique
       Agrega un slice fantasma = suma total, invisible (color del fondo),
       y rota el inicio 270° para que el plano quede abajo.
    ----------------------------------------------------------------------- */
    function cHalfPie(ch) {
        var s = ch.series[0];
        if (!s) return '      <c:pieChart>\n        <c:varyColors val="1"/>\n        <c:firstSliceAng val="270"/>\n      </c:pieChart>\n';
        var vals = s.values || [];
        var totalSum = vals.reduce(function(a, b) { return a + b; }, 0) || 1;
        var ghostColor = hex6FromAny(ch.color) || 'FFFFFF'; // color del fondo del widget
        var nReal = vals.length;

        var x = '      <c:pieChart>\n        <c:varyColors val="1"/>\n';
        x += '        <c:ser>\n          <c:idx val="0"/>\n          <c:order val="0"/>\n';

        // Data points: colores reales + ghost blanco
        for (var i = 0; i <= nReal; i++) {
            var ptC = (i < nReal)
                ? (hex6FromAny(s.backgroundColor && s.backgroundColor[i]) || PAL[i % PAL.length])
                : ghostColor;
            x += '          <c:dPt><c:idx val="' + i + '"/>\n';
            x += '            <c:spPr><a:solidFill><a:srgbClr val="' + ptC + '"/></a:solidFill>';
            x += '<a:ln><a:noFill/></a:ln></c:spPr></c:dPt>\n';
        }

        // Categorías como literales (incluye label vacío del ghost)
        x += '          <c:cat>\n            <c:strLit>\n';
        x += '              <c:ptCount val="' + (nReal + 1) + '"/>\n';
        (ch.catLabels || []).forEach(function(lbl, idx) {
            x += '              <c:pt idx="' + idx + '"><c:v>' + esc(lbl) + '</c:v></c:pt>\n';
        });
        x += '              <c:pt idx="' + nReal + '"><c:v></c:v></c:pt>\n'; // ghost
        x += '            </c:strLit>\n          </c:cat>\n';

        // Valores como literales (incluye ghost = totalSum)
        x += '          <c:val>\n            <c:numLit>\n';
        x += '              <c:formatCode>General</c:formatCode>\n';
        x += '              <c:ptCount val="' + (nReal + 1) + '"/>\n';
        vals.forEach(function(v, idx) {
            x += '              <c:pt idx="' + idx + '"><c:v>' + v + '</c:v></c:pt>\n';
        });
        x += '              <c:pt idx="' + nReal + '"><c:v>' + totalSum + '</c:v></c:pt>\n'; // ghost
        x += '            </c:numLit>\n          </c:val>\n';

        x += '        </c:ser>\n';
        x += '        <c:firstSliceAng val="270"/>\n'; // inicio en la parte inferior
        x += '      </c:pieChart>\n';
        return x;
    }

    /* -----------------------------------------------------------------------
       HALF DONUT (Semicírculo con agujero) — mismo truco que halfPie
    ----------------------------------------------------------------------- */
    function cHalfDonut(ch) {
        var s = ch.series[0];
        if (!s) return '      <c:doughnutChart>\n        <c:varyColors val="1"/>\n        <c:firstSliceAng val="270"/>\n        <c:holeSize val="50"/>\n      </c:doughnutChart>\n';
        var vals = s.values || [];
        var totalSum = vals.reduce(function(a, b) { return a + b; }, 0) || 1;
        var ghostColor = hex6FromAny(ch.color) || 'FFFFFF';
        var nReal = vals.length;

        var x = '      <c:doughnutChart>\n        <c:varyColors val="1"/>\n';
        x += '        <c:ser>\n          <c:idx val="0"/>\n          <c:order val="0"/>\n';

        // Data points con colores + ghost
        for (var i = 0; i <= nReal; i++) {
            var ptC = (i < nReal)
                ? (hex6FromAny(s.backgroundColor && s.backgroundColor[i]) || PAL[i % PAL.length])
                : ghostColor;
            x += '          <c:dPt><c:idx val="' + i + '"/>\n';
            x += '            <c:spPr><a:solidFill><a:srgbClr val="' + ptC + '"/></a:solidFill>';
            x += '<a:ln><a:noFill/></a:ln></c:spPr></c:dPt>\n';
        }

        // Categorías
        x += '          <c:cat>\n            <c:strLit>\n';
        x += '              <c:ptCount val="' + (nReal + 1) + '"/>\n';
        (ch.catLabels || []).forEach(function(lbl, idx) {
            x += '              <c:pt idx="' + idx + '"><c:v>' + esc(lbl) + '</c:v></c:pt>\n';
        });
        x += '              <c:pt idx="' + nReal + '"><c:v></c:v></c:pt>\n';
        x += '            </c:strLit>\n          </c:cat>\n';

        // Valores
        x += '          <c:val>\n            <c:numLit>\n';
        x += '              <c:formatCode>General</c:formatCode>\n';
        x += '              <c:ptCount val="' + (nReal + 1) + '"/>\n';
        vals.forEach(function(v, idx) {
            x += '              <c:pt idx="' + idx + '"><c:v>' + v + '</c:v></c:pt>\n';
        });
        x += '              <c:pt idx="' + nReal + '"><c:v>' + totalSum + '</c:v></c:pt>\n';
        x += '            </c:numLit>\n          </c:val>\n';

        x += '        </c:ser>\n';
        x += '        <c:firstSliceAng val="270"/>\n';
        x += '        <c:holeSize val="50"/>\n';
        x += '      </c:doughnutChart>\n';
        return x;
    }

    /* --- AREA (Área rellena) --- */
    function cArea(ch) {
        var x = '      <c:areaChart>\n        <c:grouping val="standard"/>\n';
        ch.series.forEach(function (s, si) {
            x += '        <c:ser>\n          <c:idx val="' + si + '"/>\n          <c:order val="' + si + '"/>\n';
            x += '          <c:tx><c:v>' + esc(s.name) + '</c:v></c:tx>\n';
            // Color de relleno semi-transparente
            x += '          <c:spPr>\n';
            x += '            <a:solidFill><a:srgbClr val="' + s.color + '"><a:alpha val="70000"/></a:srgbClr></a:solidFill>\n';
            x += '            <a:ln w="25400"><a:solidFill><a:srgbClr val="' + s.color + '"/></a:solidFill></a:ln>\n';
            x += '          </c:spPr>\n';
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        });
        x += '        <c:axId val="1"/>\n        <c:axId val="2"/>\n';
        x += '      </c:areaChart>\n';
        x += cAxes(ch.nPts, false);
        return x;
    }

    /* --- Data Points colors (para pie/doughnut con múltiples colores) --- */
    function cDPt(ch, s) {
        var x = '';
        var numPoints = ch.nPts || (s.values ? s.values.length : 0);
        
        // Determinar colores a usar
        var colors = [];
        
        // Prioridad 1: backgroundColor array en la serie
        if (s.backgroundColor && Array.isArray(s.backgroundColor)) {
            colors = s.backgroundColor.slice(0, numPoints);
        }
        
        // Prioridad 2: Si no hay suficientes colores, completar con la paleta
        while (colors.length < numPoints) {
            colors.push(PAL[colors.length % PAL.length]);
        }
        
        // Generar elementos dPt para cada punto
        if (numPoints > 0) {
            for (var idx = 0; idx < numPoints; idx++) {
                var hexColor = hex6FromAny(colors[idx]) || PAL[idx % PAL.length];
                x += '          <c:dPt>\n';
                x += '            <c:idx val="' + idx + '"/>\n';
                x += '            <c:bubble3D val="0"/>\n';
                x += '            <c:spPr><a:solidFill><a:srgbClr val="' + hexColor + '"/></a:solidFill><a:ln><a:noFill/></a:ln></c:spPr>\n';
                x += '          </c:dPt>\n';
            }
        }
        return x;
    }

    /* --- Convertir cualquier color a hex6 --- */
    function hex6FromAny(c) {
        if (!c || typeof c !== 'string') return null;
        c = c.trim();
        // Ya es hex
        if (/^#?[0-9A-Fa-f]{6}$/.test(c)) return c.replace('#', '').toUpperCase();
        if (/^#?[0-9A-Fa-f]{3}$/.test(c)) {
            c = c.replace('#', '');
            return (c[0]+c[0]+c[1]+c[1]+c[2]+c[2]).toUpperCase();
        }
        // rgb/rgba
        var m = c.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
        if (m) return p2(m[1]) + p2(m[2]) + p2(m[3]);
        return null;
    }

    /* --- LINE --- */
    function cLine(ch) {
        var x = '      <c:lineChart>\n        <c:grouping val="standard"/>\n';
        ch.series.forEach(function (s, si) {
            x += '        <c:ser>\n          <c:idx val="' + si + '"/>\n          <c:order val="' + si + '"/>\n';
            x += '          <c:tx><c:v>' + esc(s.name) + '</c:v></c:tx>\n';
            x += '          <c:spPr><a:ln w="28575"><a:solidFill><a:srgbClr val="' + s.color + '"/></a:solidFill></a:ln></c:spPr>\n';
            x += '          <c:marker><c:symbol val="circle"/><c:size val="4"/></c:marker>\n';
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        });
        x += '        <c:axId val="1"/>\n        <c:axId val="2"/>\n';
        x += '      </c:lineChart>\n';
        x += cAxes(ch.nPts, false);
        return x;
    }

    /* --- BAR --- */
    function cBar(ch) {
        // Serie única con colores por barra (modo distributivo de GLPI) → comportamiento igual que pie
        var s0 = ch.series[0];
        var useDist = ch.series.length === 1 && s0 &&
                     Array.isArray(s0.backgroundColor) && s0.backgroundColor.length > 1;
        var x = '      <c:barChart>\n        <c:barDir val="col"/>\n        <c:grouping val="clustered"/>\n        <c:varyColors val="' + (useDist ? '1' : '0') + '"/>\n';
        x += '        <c:gapWidth val="100"/>\n';
        ch.series.forEach(function (s, si) {
            x += '        <c:ser>\n          <c:idx val="' + si + '"/>\n          <c:order val="' + si + '"/>\n';
            x += '          <c:tx><c:v>' + esc(s.name) + '</c:v></c:tx>\n';
            if (useDist && si === 0) {
                // Color individual por barra (igual que pie)
                x += cDPt(ch, s);
            } else {
                x += '          <c:spPr><a:solidFill><a:srgbClr val="' + s.color + '"/></a:solidFill><a:ln><a:noFill/></a:ln></c:spPr>\n';
            }
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        });
        x += '        <c:axId val="1"/>\n        <c:axId val="2"/>\n';
        x += '      </c:barChart>\n';
        x += cAxes(ch.nPts, false);
        return x;
    }

    /* --- HORIZONTAL BAR --- */
    function cHBar(ch) {
        // Serie única con colores por barra (modo distributivo de GLPI)
        var s0 = ch.series[0];
        var useDist = ch.series.length === 1 && s0 &&
                     Array.isArray(s0.backgroundColor) && s0.backgroundColor.length > 1;
        var x = '      <c:barChart>\n        <c:barDir val="bar"/>\n        <c:grouping val="clustered"/>\n        <c:varyColors val="' + (useDist ? '1' : '0') + '"/>\n';
        x += '        <c:gapWidth val="80"/>\n';
        ch.series.forEach(function (s, si) {
            x += '        <c:ser>\n          <c:idx val="' + si + '"/>\n          <c:order val="' + si + '"/>\n';
            x += '          <c:tx><c:v>' + esc(s.name) + '</c:v></c:tx>\n';
            if (useDist && si === 0) {
                x += cDPt(ch, s);
            } else {
                x += '          <c:spPr><a:solidFill><a:srgbClr val="' + s.color + '"/></a:solidFill><a:ln><a:noFill/></a:ln></c:spPr>\n';
            }
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        });
        x += '        <c:axId val="1"/>\n        <c:axId val="2"/>\n';
        x += '      </c:barChart>\n';
        x += cAxes(ch.nPts, true);
        return x;
    }

    /* --- STACKED BAR VERTICAL --- */
    function cStackedBar(ch) {
        var x = '      <c:barChart>\n        <c:barDir val="col"/>\n        <c:grouping val="stacked"/>\n        <c:varyColors val="0"/>\n';
        x += '        <c:gapWidth val="30"/>\n';
        x += '        <c:overlap val="100"/>\n';
        ch.series.forEach(function (s, si) {
            x += '        <c:ser>\n          <c:idx val="' + si + '"/>\n          <c:order val="' + si + '"/>\n';
            x += '          <c:tx><c:v>' + esc(s.name) + '</c:v></c:tx>\n';
            x += '          <c:spPr><a:solidFill><a:srgbClr val="' + s.color + '"/></a:solidFill><a:ln><a:noFill/></a:ln></c:spPr>\n';
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        });
        x += '        <c:axId val="1"/>\n        <c:axId val="2"/>\n';
        x += '      </c:barChart>\n';
        x += cAxes(ch.nPts, false);
        return x;
    }

    /* --- STACKED HORIZONTAL BAR --- */
    function cStackedHBar(ch) {
        var x = '      <c:barChart>\n        <c:barDir val="bar"/>\n        <c:grouping val="stacked"/>\n        <c:varyColors val="0"/>\n';
        x += '        <c:gapWidth val="30"/>\n';
        x += '        <c:overlap val="100"/>\n';
        ch.series.forEach(function (s, si) {
            x += '        <c:ser>\n          <c:idx val="' + si + '"/>\n          <c:order val="' + si + '"/>\n';
            x += '          <c:tx><c:v>' + esc(s.name) + '</c:v></c:tx>\n';
            x += '          <c:spPr><a:solidFill><a:srgbClr val="' + s.color + '"/></a:solidFill><a:ln><a:noFill/></a:ln></c:spPr>\n';
            x += cCat(ch);
            x += cVal(s, ch.nPts);
            x += '        </c:ser>\n';
        });
        x += '        <c:axId val="1"/>\n        <c:axId val="2"/>\n';
        x += '      </c:barChart>\n';
        x += cAxes(ch.nPts, true);
        return x;
    }

    function cCat(ch) {
        var x = '          <c:cat>\n            <c:strRef>\n';
        x += '              <c:f>' + ch.catF + '</c:f>\n';
        x += '              <c:strCache>\n                <c:ptCount val="' + ch.nPts + '"/>\n';
        ch.catLabels.forEach(function (l, i) {
            x += '                <c:pt idx="' + i + '"><c:v>' + esc(l) + '</c:v></c:pt>\n';
        });
        x += '              </c:strCache>\n            </c:strRef>\n          </c:cat>\n';
        return x;
    }

    function cVal(s, n) {
        var x = '          <c:val>\n            <c:numRef>\n';
        x += '              <c:f>' + s.formula + '</c:f>\n';
        x += '              <c:numCache>\n                <c:ptCount val="' + n + '"/>\n';
        s.values.forEach(function (v, i) {
            x += '                <c:pt idx="' + i + '"><c:v>' + (Number(v) || 0) + '</c:v></c:pt>\n';
        });
        x += '              </c:numCache>\n            </c:numRef>\n          </c:val>\n';
        return x;
    }

    function cAxes(nCats, horiz) {
        // Rotación de etiquetas: -45° cuando hay muchas categorías en eje vertical
        var rot = (!horiz && nCats > 6) ? '-2700000' : '0';
        var txPr = (rot !== '0') ?
            '        <c:txPr><a:bodyPr rot="' + rot + '"/><a:lstStyle/>' +
            '<a:p><a:pPr><a:defRPr sz="800"/></a:pPr></a:p></c:txPr>\n' : '';
        var catAxis = '      <c:catAx>\n        <c:axId val="1"/>\n        <c:scaling><c:orientation val="minMax"/></c:scaling>\n' +
               '        <c:delete val="0"/>\n        <c:axPos val="' + (horiz ? 'l' : 'b') + '"/>\n' +
               txPr +
               '        <c:tickLblPos val="nextTo"/>\n        <c:crossAx val="2"/>\n      </c:catAx>\n';
        var valAxis = '      <c:valAx>\n        <c:axId val="2"/>\n        <c:scaling><c:orientation val="minMax"/></c:scaling>\n' +
               '        <c:delete val="0"/>\n        <c:axPos val="' + (horiz ? 'b' : 'l') + '"/>\n' +
               '        <c:majorGridlines/>\n' +
               '        <c:tickLblPos val="nextTo"/>\n        <c:crossAx val="1"/>\n      </c:valAx>\n';
        return catAxis + valAxis;
    }

    /* ======================== UTILIDADES ======================== */
    function styleIdx(s, ci) {
        var base = {title: 1, sub: 2, section: 3, tHead: 4, tCell: 5};
        if (base[s] !== undefined) return base[s];
        if (s === 'bigNum' && ci >= 0) return 6 + ci * 2;
        if (s === 'cardName' && ci >= 0) return 6 + ci * 2 + 1;
        return 0;
    }

    function colL(n) {
        var s = '';
        while (n > 0) { s = String.fromCharCode(65 + (n - 1) % 26) + s; n = Math.floor((n - 1) / 26); }
        return s;
    }

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
    }

    function normHex(c) {
        if (!c) return 'E0E0E0';
        c = String(c).replace('#', '').toUpperCase();
        if (c.length === 3) c = c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
        if (/^[0-9A-F]{6}$/.test(c)) return c;
        return 'E0E0E0';
    }

    function p2(n) { return ('0' + parseInt(n, 10).toString(16)).slice(-2).toUpperCase(); }
    function qt(s) { return "'" + s + "'"; }

})();
