<?php
/**
 * TicketSat — Exportar respuestas en Excel (XLSX) o CSV
 *
 * Parámetros GET:
 *   surveys_id  int   ID de la encuesta
 *   format      str   "xlsx" | "csv"  (por defecto xlsx)
 */
include('../../../inc/includes.php');
Session::checkRight('config', READ);

global $DB;
$surveysId = (int)($_GET['surveys_id'] ?? 0);
$format    = in_array($_GET['format'] ?? '', ['xlsx', 'csv']) ? $_GET['format'] : 'xlsx';

if (!$surveysId) {
    http_response_code(400);
    exit('surveys_id requerido');
}

// Cargar encuesta
$itS = $DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys', 'WHERE' => ['id' => $surveysId], 'LIMIT' => 1]);
$survey = null; foreach ($itS as $s) { $survey = $s; }
if (!$survey) { http_response_code(404); exit('Encuesta no encontrada'); }

// Cargar preguntas ordenadas
$itQ = $DB->request(['FROM' => 'glpi_plugin_ticketsat_questions',
    'WHERE' => ['plugin_ticketsat_surveys_id' => $surveysId], 'ORDER' => 'rank ASC']);
$questions = []; foreach ($itQ as $q) { $questions[$q['id']] = $q; }

// Cabeceras de columna
$headers = ['# Respuesta', '# Ticket', 'Usuario', 'Fecha de Envío', 'Fecha de Respuesta', 'Estado'];

$typeLabels = [
    'scale'    => 'Escala 1-10',
    'stars'    => 'Estrellas 1-5',
    'multiple' => 'Opción múltiple',
    'checkbox' => 'Casillas múltiples',
    'dropdown' => 'Lista desplegable',
    'open'     => 'Texto corto',
    'open_long'=> 'Texto largo',
    'yesno'    => 'Sí / No',
    'abcd'     => 'Opción A-B-C-D',
    'nps'      => 'NPS (0-10)',
];

foreach ($questions as $q) {
    $typeLabel = $typeLabels[$q['type']] ?? $q['type'];
    $headers[] = $q['question'] . ' [' . $typeLabel . ']';
}

// Construir filas
$rows = [];
$itR = $DB->request([
    'FROM'  => 'glpi_plugin_ticketsat_responses',
    'WHERE' => ['plugin_ticketsat_surveys_id' => $surveysId, 'completed' => 1],
    'ORDER' => 'id ASC',
]);
foreach ($itR as $resp) {
    $user = new User();
    $user->getFromDB($resp['users_id']);

    $row = [
        $resp['id'],
        '#' . $resp['tickets_id'],
        $user->getFriendlyName(),
        $resp['date_send']     ? date('d/m/Y H:i', strtotime($resp['date_send']))     : '',
        $resp['date_answered'] ? date('d/m/Y H:i', strtotime($resp['date_answered'])) : '',
        $resp['completed'] ? 'Completada' : 'Pendiente',
    ];

    // Cargar respuestas por pregunta
    $itA = $DB->request(['FROM' => 'glpi_plugin_ticketsat_answers',
        'WHERE' => ['plugin_ticketsat_responses_id' => $resp['id']]]);
    $ansMap = []; foreach ($itA as $a) { $ansMap[$a['plugin_ticketsat_questions_id']] = $a; }

    foreach ($questions as $qId => $q) {
        $ans = $ansMap[$qId] ?? null;
        if (!$ans) { $row[] = ''; continue; }
        if (in_array($q['type'], ['scale', 'stars'])) {
            $row[] = $ans['answer_value'] ?? '';
        } else {
            $row[] = $ans['answer_text'] ?? '';
        }
    }
    $rows[] = $row;
}

// Nombre de archivo seguro
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $survey['name']);
$safeName = trim($safeName, '_');
$filename = 'Formulario_' . $safeName . '_' . date('Ymd');

if ($format === 'csv') {
    /* ============================
       EXPORTAR CSV
       ============================ */
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    // BOM para Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

/* ============================
   EXPORTAR XLSX (sin librerías externas)
   Genera un archivo XLSX mínimo válido con XML
   ============================ */

// Función auxiliar: escapar XML
function ts_xml($v) {
    return htmlspecialchars((string)$v, ENT_XML1, 'UTF-8');
}

// Construir strings compartidos
$sharedStrings = [];
$ssIndex = [];

function ts_ss($val, &$sharedStrings, &$ssIndex) {
    $val = (string)$val;
    if (!isset($ssIndex[$val])) {
        $ssIndex[$val] = count($sharedStrings);
        $sharedStrings[] = $val;
    }
    return $ssIndex[$val];
}

// Filas completas
$allRows = [$headers] + [];
array_unshift($rows, $headers);
$allRows = $rows;

// Pre-poblar sharedStrings con datos de texto (no numérico)
$cellData = []; // [row][col] = ['type' => 's'|'n', 'value' => ...]
foreach ($allRows as $rIdx => $row) {
    $cellData[$rIdx] = [];
    foreach ($row as $cIdx => $val) {
        if (is_numeric($val) && $val !== '' && $rIdx > 0) {
            $cellData[$rIdx][$cIdx] = ['type' => 'n', 'value' => $val];
        } else {
            $cellData[$rIdx][$cIdx] = ['type' => 's', 'value' => ts_ss($val, $sharedStrings, $ssIndex)];
        }
    }
}

// Función para nombre de celda Excel: col número => letras
function ts_col_name($n) {
    $s = '';
    $n++;
    while ($n > 0) {
        $n--; $s = chr(65 + ($n % 26)) . $s; $n = (int)($n / 26);
    }
    return $s;
}

// ---- sheet1.xml ----
$sheetRows = '';
foreach ($cellData as $rIdx => $cols) {
    $isHeader = $rIdx === 0;
    $sheetRows .= '<row r="' . ($rIdx + 1) . '"' . ($isHeader ? ' ht="20" customHeight="1"' : '') . '>';
    foreach ($cols as $cIdx => $cell) {
        $cellRef = ts_col_name($cIdx) . ($rIdx + 1);
        $style   = $isHeader ? ' s="1"' : '';
        if ($cell['type'] === 'n') {
            $sheetRows .= '<c r="' . $cellRef . '"' . $style . '><v>' . ts_xml($cell['value']) . '</v></c>';
        } else {
            $sheetRows .= '<c r="' . $cellRef . '" t="s"' . $style . '><v>' . ts_xml($cell['value']) . '</v></c>';
        }
    }
    $sheetRows .= '</row>';
}

$colCount = count($headers);
$lastCol  = ts_col_name($colCount - 1);
$filterRef = 'A1:' . $lastCol . '1';

$sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetViews>
    <sheetView tabSelected="1" workbookViewId="0">
      <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  <sheetData>' . $sheetRows . '</sheetData>
  <autoFilter ref="' . $filterRef . '"/>
</worksheet>';

// ---- sharedStrings.xml ----
$ssItems = '';
foreach ($sharedStrings as $s) {
    $ssItems .= '<si><t xml:space="preserve">' . ts_xml($s) . '</t></si>';
}
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
     count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
. $ssItems . '</sst>';

// ---- workbook.xml ----
$sheetTitle = mb_substr($survey['name'], 0, 31); // Excel limita a 31 caracteres
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . ts_xml($sheetTitle) . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

// ---- workbook.xml.rels ----
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"
    Target="sharedStrings.xml"/>
  <Relationship Id="rId3"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';

// ---- _rels/.rels ----
$dotRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';

// ---- [Content_Types].xml ----
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

// ---- styles.xml ----
// xf índice 0 = normal | xf índice 1 = encabezado (bold, fondo azul, texto blanco)
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF2E62BB"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
  </cellXfs>
</styleSheet>';

// ---- Crear ZIP en memoria ----
$tmpFile = tempnam(sys_get_temp_dir(), 'ticketsat_');
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $dotRels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
$zip->addFromString('xl/sharedStrings.xml', $ssXml);
$zip->addFromString('xl/styles.xml', $styles);
$zip->close();

$content = file_get_contents($tmpFile);
unlink($tmpFile);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-cache');
echo $content;
exit;
