<?php
/**
 * TicketSat  Builder de preguntas (estilo Google Forms)
 */
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

global $DB;
$baseUrl   = Plugin::getWebDir('ticketsat', true);
$surveysId = (int)($_GET['surveys_id'] ?? $_POST['surveys_id'] ?? 0);
if (!$surveysId) Html::redirect($baseUrl . '/front/survey.php');

$surveyRow = [];
foreach ($DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys',
    'WHERE' => ['id' => $surveysId], 'LIMIT' => 1]) as $r) { $surveyRow = $r; }
if (!$surveyRow) Html::redirect($baseUrl . '/front/survey.php');

/*  Acciones POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $validTypes = array_keys(PluginTicketsatQuestion::getTypes());

    if ($action === 'update_survey_info') {
        $DB->update('glpi_plugin_ticketsat_surveys', [
            'name'             => trim($_POST['name'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'active'           => (int)($_POST['active'] ?? 1),
            'trigger_on_solve' => isset($_POST['trigger_on_solve']) ? 1 : 0,
            'trigger_on_close' => isset($_POST['trigger_on_close']) ? 1 : 0,
            'date_mod'         => date('Y-m-d H:i:s'),
        ], ['id' => $surveysId]);
    }

    if ($action === 'add_question') {
        $maxRank = 0;
        foreach ($DB->request(['SELECT' => ['MAX' => 'rank AS max_rank'],
            'FROM' => 'glpi_plugin_ticketsat_questions',
            'WHERE' => ['plugin_ticketsat_surveys_id' => $surveysId]]) as $r) {
            $maxRank = (int)($r['max_rank'] ?? 0);
        }
        $type = in_array($_POST['type'] ?? 'scale', $validTypes) ? $_POST['type'] : 'scale';
        $qId = $DB->insert('glpi_plugin_ticketsat_questions', [
            'plugin_ticketsat_surveys_id' => $surveysId,
            'question'      => htmlspecialchars_decode(trim($_POST['question'] ?? '')),
            'type'          => $type,
            'required'      => (int)($_POST['required'] ?? 0),
            'rank'          => $maxRank + 1,
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s'),
        ]) ? $DB->insertId() : 0;

        if ($qId && in_array($type, PluginTicketsatQuestion::getOptionTypes())) {
            $lines = array_filter(array_map('trim', (array)($_POST['options'] ?? [])));
            foreach (array_values($lines) as $i => $opt) {
                $DB->insert('glpi_plugin_ticketsat_options', [
                    'plugin_ticketsat_questions_id' => $qId,
                    'label' => $opt, 'rank' => $i + 1,
                ]);
            }
        }
    }

    if ($action === 'update_question') {
        $qId  = (int)($_POST['question_id'] ?? 0);
        $type = in_array($_POST['type'] ?? 'scale', $validTypes) ? $_POST['type'] : 'scale';
        $DB->update('glpi_plugin_ticketsat_questions', [
            'question' => htmlspecialchars_decode(trim($_POST['question'] ?? '')),
            'type'     => $type,
            'required' => (int)($_POST['required'] ?? 0),
            'date_mod' => date('Y-m-d H:i:s'),
        ], ['id' => $qId, 'plugin_ticketsat_surveys_id' => $surveysId]);
        $DB->delete('glpi_plugin_ticketsat_options', ['plugin_ticketsat_questions_id' => $qId]);
        if (in_array($type, PluginTicketsatQuestion::getOptionTypes())) {
            $lines = array_filter(array_map('trim', (array)($_POST['options'] ?? [])));
            foreach (array_values($lines) as $i => $opt) {
                $DB->insert('glpi_plugin_ticketsat_options', [
                    'plugin_ticketsat_questions_id' => $qId,
                    'label' => $opt, 'rank' => $i + 1,
                ]);
            }
        }
    }

    if ($action === 'delete_question') {
        $qId = (int)($_POST['question_id'] ?? 0);
        $DB->delete('glpi_plugin_ticketsat_options',   ['plugin_ticketsat_questions_id' => $qId]);
        $DB->delete('glpi_plugin_ticketsat_questions', ['id' => $qId,
            'plugin_ticketsat_surveys_id' => $surveysId]);
    }

    Html::redirect($baseUrl . '/front/question.form.php?surveys_id=' . $surveysId);
}

/*  Cargar datos  */
$questions = [];
foreach ($DB->request(['FROM' => 'glpi_plugin_ticketsat_questions',
    'WHERE' => ['plugin_ticketsat_surveys_id' => $surveysId],
    'ORDER' => 'rank ASC']) as $q) {
    $q['options'] = [];
    if (in_array($q['type'], PluginTicketsatQuestion::getOptionTypes())) {
        foreach ($DB->request(['FROM' => 'glpi_plugin_ticketsat_options',
            'WHERE' => ['plugin_ticketsat_questions_id' => $q['id']],
            'ORDER' => 'rank ASC']) as $o) { $q['options'][] = $o; }
    }
    $questions[] = $q;
}

$types  = PluginTicketsatQuestion::getTypes();
$color  = htmlspecialchars($surveyRow['header_color'] ?? '#6C63FF');
// Calcula luminancia para elegir texto blanco o oscuro con máximo contraste
$_hex = ltrim($color, '#');
if (strlen($_hex) === 3) $_hex = $_hex[0].$_hex[0].$_hex[1].$_hex[1].$_hex[2].$_hex[2];
$_r = hexdec(substr($_hex, 0, 2)); $_g = hexdec(substr($_hex, 2, 2)); $_b = hexdec(substr($_hex, 4, 2));
$_lum = (0.299 * $_r + 0.587 * $_g + 0.114 * $_b) / 255;
$chipText = $_lum > 0.55 ? '#1a1a2e' : '#ffffff';
$sName  = htmlspecialchars($surveyRow['name']);
$sDesc  = htmlspecialchars($surveyRow['description'] ?? '');
$isNew  = isset($_GET['new']);
$numQ   = count($questions);

Html::header("Encuestas de Satisfaccion", $_SERVER['PHP_SELF'], 'admin', 'PluginTicketsatSurvey', 'survey');

$typeIcons = [
    'stars'     => 'ti-star',
    'scale'     => 'ti-adjustments-horizontal',
    'yesno'     => 'ti-toggle-right',
    'multiple'  => 'ti-circle-dot',
    'abcd'      => 'ti-alphabet-latin',
    'checkbox'  => 'ti-checkbox',
    'dropdown'  => 'ti-chevron-down',
    'open'      => 'ti-text-size',
    'open_long' => 'ti-align-left',
];
$typeDesc = [
    'stars'     => 'Estrellas del 1 al 5',
    'scale'     => 'Numeros del 1 al 10',
    'yesno'     => 'Solo una opcion: Si o No',
    'multiple'  => 'Una sola respuesta de la lista',
    'abcd'      => 'Opciones A, B, C, D... (estilo examen)',
    'checkbox'  => 'Varias respuestas posibles',
    'dropdown'  => 'Selecciona de un menu',
    'open'      => 'Texto corto',
    'open_long' => 'Parrafo / texto largo',
];
?>
<style>
/* 
   QUESTION BUILDER  TicketSat
 */
:root { --qb-c: <?= $color ?>; --qb-chip-text: <?= $chipText ?>; }
.qb-body { background:#f3f4f8; min-height:calc(100vh - 56px); padding:0 0 80px; }

/* Top bar */
.qb-topbar {
  background:#fff; border-bottom:1px solid #e0e0e0;
  padding:10px 24px; display:flex; align-items:center;
  justify-content:space-between; gap:10px; flex-wrap:wrap;
  position:sticky; top:0; z-index:100;
}
.qb-breadcrumb { display:flex; align-items:center; gap:6px; font-size:.88rem; color:#5f6368; }
.qb-breadcrumb a { color:#5f6368; text-decoration:none; }
.qb-breadcrumb a:hover { color:#1a73e8; }
.qb-title { font-size:.92rem; font-weight:600; color:#202124; max-width:220px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* Step indicator (top bar center) */
.qb-steps {
  display:flex; align-items:center; gap:0;
  background:#f8f9fa; border-radius:24px; padding:4px;
}
.qb-step { display:flex; align-items:center; gap:6px; padding:6px 14px;
  border-radius:20px; font-size:.8rem; font-weight:500; color:#80868b; }
.qb-step.done   { color:var(--qb-c); }
.qb-step.active { background:var(--qb-c); color:#fff; box-shadow:0 2px 6px rgba(0,0,0,.2); }
.qb-step-arrow  { color:#c0c0c0; font-size:.72rem; }

/* Top-right actions */
.qb-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.qb-btn-ghost {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 16px; border-radius:6px; font-size:.84rem; font-weight:600;
  background:none; border:1.5px solid #dadce0; color:#5f6368;
  cursor:pointer; text-decoration:none; transition:.15s;
}
.qb-btn-ghost:hover { background:#f1f3f4; color:#3c4043; }
.qb-btn-color {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 18px; border-radius:6px; font-size:.84rem; font-weight:700;
  background:var(--qb-c); color:#fff; border:none; cursor:pointer; transition:.15s;
  text-decoration:none;
}
.qb-btn-color:hover { filter:brightness(1.1); color:#fff; }

/* Main layout */
.qb-layout { max-width:720px; margin:28px auto 0; padding:0 14px; }

/* Cards */
.qb-card {
  background:#fff; border-radius:10px;
  box-shadow:0 1px 4px rgba(0,0,0,.14);
  margin-bottom:14px; overflow:hidden; position:relative;
  transition:box-shadow .2s;
}
.qb-card:focus-within { box-shadow:0 3px 16px rgba(0,0,0,.18); }

/* Header card */
.qb-hero {
  border-top:8px solid var(--qb-c);
  padding:26px 30px 20px;
}
.qb-hero-title {
  width:100%; border:none; border-bottom:2px solid transparent;
  outline:none; font-size:1.9rem; font-weight:400; color:#202124;
  padding:4px 0 10px; background:transparent; font-family:inherit;
  transition:border-color .2s; box-sizing:border-box; display:block;
}
.qb-hero-title:hover { border-bottom-color:#dadce0; }
.qb-hero-title:focus { border-bottom-color:var(--qb-c); }
.qb-hero-desc {
  width:100%; border:none; border-bottom:1px solid transparent;
  outline:none; font-size:.97rem; color:#5f6368;
  padding:4px 0 6px; background:transparent; resize:none;
  min-height:34px; font-family:inherit;
  transition:border-color .2s; box-sizing:border-box; display:block;
}
.qb-hero-desc:hover { border-bottom-color:#dadce0; }
.qb-hero-desc:focus { border-bottom-color:var(--qb-c); }
.qb-hero-sep { border:none; border-top:1px solid #e8eaed; margin:16px 0 12px; }
.qb-hero-meta { display:flex; align-items:center; gap:18px; flex-wrap:wrap; font-size:.84rem; color:#3c4043; }
.qb-hero-meta label { display:flex; align-items:center; gap:7px; cursor:pointer; margin:0; }
.qb-hero-save { display:flex; justify-content:flex-end; margin-top:14px; }

/* Badge conteo */
.qb-count-badge {
  display:inline-flex; align-items:center; gap:6px;
  padding:5px 14px; border-radius:20px;
  background:rgba(0,0,0,.06); color:#5f6368;
  font-size:.8rem; font-weight:600; margin-bottom:4px;
}

/* Question card */
.qb-q-card { display:flex; }
.qb-q-accent { width:5px; flex-shrink:0; background:transparent;
  border-radius:10px 0 0 10px; transition:.2s; }
.qb-q-card:focus-within .qb-q-accent { background:var(--qb-c); }
.qb-q-body { flex:1; padding:22px 26px 16px; min-width:0; }

/* Question top row */
.qb-q-row { display:flex; gap:12px; align-items:flex-start; margin-bottom:14px; }
.qb-q-num {
  width:30px; height:30px; border-radius:50%; flex-shrink:0;
  background:var(--qb-c); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-size:.8rem; font-weight:700; margin-top:7px;
}
.qb-q-field {
  flex:1; border:none; border-bottom:1px solid #dadce0;
  outline:none; font-size:1rem; color:#202124;
  padding:8px 0 6px; background:transparent; font-family:inherit;
  transition:.2s; min-width:0;
}
.qb-q-field:hover { border-bottom-color:#80868b; }
.qb-q-field:focus { border-bottom:2px solid var(--qb-c); padding-bottom:5px; }

/* Type selector */
.qb-type-wrap { flex-shrink:0; }
.qb-type-sel {
  border:1.5px solid #dadce0; border-radius:6px;
  font-size:.85rem; height:38px; width:210px;
  background:#fafafa; padding:0 8px; outline:none;
  appearance:auto; color:#3c4043;
}
.qb-type-sel:focus { border-color:var(--qb-c); }

/* Type description chip */
.qb-type-chip {
  display:inline-flex; align-items:center; gap:5px;
  font-size:.78rem;
  color:var(--tblr-primary-fg, #fff);
  background:var(--tblr-primary, #6C63FF);
  border-radius:20px; padding:4px 12px; margin-bottom:8px; font-weight:700;
  letter-spacing:.02em;
  box-shadow:0 2px 6px rgba(0,0,0,.18);
  text-shadow: none;
}

/* Previews */
.qb-preview { margin:10px 0 8px; }
.qb-scale-row { display:flex; gap:6px; flex-wrap:wrap; }
.qb-scale-box {
  width:38px; height:38px; border:1.5px solid #dadce0; border-radius:7px;
  display:flex; align-items:center; justify-content:center;
  font-size:.82rem; font-weight:600; color:#9aa0a6;
}
.qb-scale-labels { display:flex; justify-content:space-between;
  margin-top:5px; font-size:.72rem; color:#9aa0a6; }
.qb-stars-row { font-size:2rem; color:#dadce0; letter-spacing:3px; }
.qb-open-line {
  border-bottom:1px solid #dadce0; padding:10px 0;
  font-size:.9rem; color:#9aa0a6; font-style:italic;
}
.qb-options-textarea {
  border:1.5px solid #dadce0; border-radius:6px;
  font-size:.9rem; resize:vertical; width:100%;
  padding:8px 12px; font-family:inherit; outline:none;
}
.qb-options-textarea:focus { border-color:var(--qb-c); }
.qb-options-hint { font-size:.76rem; color:#80868b; margin-top:4px; }

/* Preview options list */
.qb-opt-preview { margin-top:6px; }
.qb-opt-line {
  display:flex; align-items:center; gap:10px;
  padding:9px 12px; border:1.5px solid #e0e0e0;
  border-radius:7px; margin-bottom:5px; font-size:.9rem; color:#5f6368;
}
.qb-opt-circle { width:18px; height:18px; border-radius:50%;
  border:2px solid #dadce0; flex-shrink:0; }
.qb-opt-square { width:18px; height:18px; border-radius:4px;
  border:2px solid #dadce0; flex-shrink:0; }

/* Footer of question card */
.qb-q-footer {
  display:flex; align-items:center; justify-content:space-between;
  border-top:1px solid #f1f1f1; padding-top:12px; margin-top:14px;
  flex-wrap:wrap; gap:8px;
}
.qb-req-label { display:flex; align-items:center; gap:7px;
  font-size:.85rem; color:#3c4043; cursor:pointer; margin:0; }
.qb-sep-v { width:1px; height:18px; background:#e0e0e0; }
.qb-foot-right { display:flex; align-items:center; gap:10px; }
.qb-save-btn {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 20px; border-radius:5px; font-size:.85rem; font-weight:700;
  background:var(--qb-c); color:#fff; border:none; cursor:pointer; transition:.15s;
}
.qb-save-btn:hover { filter:brightness(1.1); }
.qb-del-btn {
  display:inline-flex; align-items:center; gap:5px;
  padding:7px 14px; border-radius:5px; font-size:.82rem; font-weight:600;
  background:none; border:1.5px solid #f5c6cb; color:#d93025; cursor:pointer; transition:.15s;
}
.qb-del-btn:hover { background:#fce8e6; border-color:#d93025; }

/* Add card */
.qb-add-card {
  border:2px dashed #dadce0; background:rgba(255,255,255,.85);
  box-shadow:none; padding:24px 28px;
  transition:border-color .2s, box-shadow .2s;
}
.qb-add-card:focus-within {
  border-color:var(--qb-c);
  box-shadow:0 2px 10px rgba(0,0,0,.12);
}
.qb-add-header {
  display:flex; align-items:center; gap:10px;
  margin-bottom:18px;
}
.qb-add-circle {
  width:34px; height:34px; border-radius:50%; flex-shrink:0;
  background:var(--qb-c); color:#fff;
  display:flex; align-items:center; justify-content:center; font-size:1.1rem;
}
.qb-add-title { font-size:.97rem; font-weight:700; color:#3c4043; }
.qb-add-btn {
  display:inline-flex; align-items:center; gap:7px;
  padding:10px 26px; border-radius:6px;
  background:var(--qb-c); color:#fff;
  border:none; font-size:.9rem; font-weight:700;
  cursor:pointer; font-family:inherit;
  box-shadow:0 2px 8px rgba(0,0,0,.18); transition:.15s;
}
.qb-add-btn:hover { filter:brightness(1.1); box-shadow:0 4px 14px rgba(0,0,0,.22); }

/* Empty state */
.qb-empty { text-align:center; padding:36px 20px 28px; }
.qb-empty i { font-size:2.8rem; color:#dadce0; display:block; margin-bottom:8px; }
.qb-empty p { color:#80868b; margin:0; font-size:.93rem; }

/* Bottom actions */
.qb-bottom { display:flex; justify-content:center; gap:10px; margin-top:10px; flex-wrap:wrap; }

/* ========== OPTION BUILDER (reemplaza textarea) ========== */
.opts-builder { margin:10px 0 8px; }
.opts-builder .opt-list { display:flex; flex-direction:column; gap:6px; margin-bottom:8px; }
.opts-builder .opt-row {
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; border:1.5px solid #e8eaed;
  border-radius:8px; background:#fafafa; transition:border-color .2s;
}
.opts-builder .opt-row:hover { border-color:#bdc1c6; }
.opts-builder .opt-row:focus-within { border-color:var(--qb-c); background:#fff; }
.opts-builder .opt-icon { font-size:1.05rem; color:#c0c0c0; flex-shrink:0; }
.opts-builder .opt-inp {
  flex:1; border:none; outline:none; background:transparent;
  font-size:.9rem; color:#202124; font-family:inherit; padding:0;
}
.opts-builder .opt-del {
  background:none; border:none; color:#9aa0a6; cursor:pointer;
  font-size:.82rem; padding:2px 7px; border-radius:4px;
  flex-shrink:0; transition:.15s; line-height:1; font-family:inherit;
}
.opts-builder .opt-del:hover { background:#fce8e6; color:#d93025; }
.opts-builder .opt-add-btn {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 14px; border-radius:6px; background:none;
  border:1.5px dashed #c0c0c0; color:#5f6368;
  font-size:.84rem; cursor:pointer; transition:.15s;
  font-family:inherit; margin-top:2px;
}
.opts-builder .opt-add-btn:hover {
  border-color:var(--qb-c); color:var(--qb-c);
  background:rgba(108,99,255,.05);
}
.opts-hidden { display:none !important; }

/* ========== YESNO / OPEN_LONG / DROPDOWN PREVIEWS ========== */
.qb-yesno-row { display:flex; gap:10px; margin:8px 0; }
.qb-yesno-btn {
  display:inline-flex; align-items:center; gap:7px;
  padding:9px 28px; border:1.5px solid #dadce0; border-radius:24px;
  font-size:.92rem; color:#5f6368; font-weight:500;
  background:#fafafa;
}
.qb-open-long {
  border:1.5px solid #dadce0; border-radius:6px;
  padding:10px 12px; font-size:.9rem; color:#9aa0a6;
  font-style:italic; min-height:68px; display:flex; align-items:flex-start;
}
.qb-dropdown-preview {
  display:inline-flex; align-items:center; justify-content:space-between;
  border:1.5px solid #dadce0; border-radius:6px;
  padding:8px 16px; font-size:.9rem; color:#9aa0a6; width:230px;
}
</style>

<div class="qb-body">

  <!--  Top bar  -->
  <div class="qb-topbar">
    <div class="qb-breadcrumb">
      <a href="<?= $baseUrl ?>/front/survey.php"><i class="ti ti-clipboard-list"></i></a>
      <span style="color:#e0e0e0">/</span>
      <span class="qb-title"><?= $sName ?></span>
    </div>

    <div class="qb-steps">
      <div class="qb-step done"><i class="ti ti-check"></i> Configuracion</div>
      <span class="qb-step-arrow"><i class="ti ti-chevron-right"></i></span>
      <div class="qb-step active"><i class="ti ti-list-check"></i> Preguntas</div>
      <span class="qb-step-arrow"><i class="ti ti-chevron-right"></i></span>
      <div class="qb-step"><i class="ti ti-checks"></i> Listo</div>
    </div>

    <div class="qb-actions">
      <a href="<?= $baseUrl ?>/front/survey.form.php?id=<?= $surveysId ?>" class="qb-btn-ghost">
        <i class="ti ti-settings-2"></i> Configuracion
      </a>
      <a href="<?= $baseUrl ?>/front/survey.php" class="qb-btn-ghost">
        <i class="ti ti-layout-grid"></i> Mis encuestas
      </a>
    </div>
  </div>

  <div class="qb-layout">

    <!--  Tarjeta cabecera (info de encuesta editable)  -->
    <div class="qb-card qb-hero">
      <form method="POST">
        <input type="hidden" name="action"     value="update_survey_info">
        <input type="hidden" name="surveys_id" value="<?= $surveysId ?>">

        <input class="qb-hero-title" type="text" name="name" required
               value="<?= $sName ?>" placeholder="Titulo de la encuesta">
        <textarea class="qb-hero-desc" name="description" rows="2"
                  placeholder="Descripcion de la encuesta (opcional)"><?= $sDesc ?></textarea>

        <hr class="qb-hero-sep">

        <div class="qb-hero-meta">
          <label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="trigger_on_solve"
                     role="switch" <?= !empty($surveyRow['trigger_on_solve']) ? 'checked' : '' ?>>
            </div>
            Al resolver
          </label>
          <label>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="trigger_on_close"
                     role="switch" <?= !empty($surveyRow['trigger_on_close']) ? 'checked' : '' ?>>
            </div>
            Al cerrar
          </label>
          <div style="display:flex;align-items:center;gap:8px">
            <span>Estado:</span>
            <select name="active" class="form-select form-select-sm" style="width:auto">
              <option value="1" <?= !empty($surveyRow['active']) ? 'selected' : '' ?>>Activa</option>
              <option value="0" <?= empty($surveyRow['active']) ? 'selected' : '' ?>>Inactiva</option>
            </select>
          </div>
        </div>

        <div class="qb-hero-save">
          <button type="submit" class="qb-save-btn">
            <i class="ti ti-device-floppy"></i> Guardar encuesta
          </button>
        </div>
        <?php Html::closeForm(); ?>
      </form>
    </div>

    <!--  Badge contador  -->
    <?php if ($numQ > 0): ?>
    <div class="qb-count-badge">
      <i class="ti ti-list-numbers"></i>
      <?= $numQ ?> pregunta<?= $numQ !== 1 ? 's' : '' ?> en esta encuesta
    </div>
    <?php endif; ?>

    <!--  Preguntas existentes  -->
    <?php if (empty($questions)): ?>
    <div class="qb-card">
      <div class="qb-empty">
        <i class="ti ti-message-circle-question"></i>
        <p>Aun no hay preguntas. Agrega la primera en el formulario de abajo.</p>
      </div>
    </div>
    <?php endif; ?>

    <?php foreach ($questions as $i => $q):
    $hasOptList = in_array($q['type'], ['multiple','abcd','checkbox','dropdown']);
      $needsOpts = $hasOptList;
      $icon    = $typeIcons[$q['type']] ?? 'ti-help-circle';
      $desc    = $typeDesc[$q['type']] ?? $q['type'];
    ?>
    <div class="qb-card qb-q-card" id="qcard-<?= $q['id'] ?>">
      <div class="qb-q-accent"></div>
      <div class="qb-q-body">

        <!-- Formulario editar pregunta -->
        <form method="POST" onsubmit="return collectBeforeSubmit(this)" id="qb-form-<?= $q['id'] ?>">
          <input type="hidden" name="action"      value="update_question">
          <input type="hidden" name="surveys_id"  value="<?= $surveysId ?>">
          <input type="hidden" name="question_id" value="<?= $q['id'] ?>">

          <div class="qb-q-row">
            <div class="qb-q-num"><?= $i + 1 ?></div>
            <input class="qb-q-field" type="text" name="question" required
                   value="<?= htmlspecialchars($q['question']) ?>"
                   placeholder="Enunciado de la pregunta">
            <div class="qb-type-wrap">
              <select class="qb-type-sel" name="type"
                      onchange="qbToggle(this, <?= (int)$q['id'] ?>)">
                <?php foreach ($types as $k => $v): ?>
                <option value="<?= $k ?>" <?= $q['type'] === $k ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Chip tipo -->
          <div style="padding-left:42px">
            <span class="qb-type-chip" id="chip-<?= $q['id'] ?>">
              <i class="ti <?= $icon ?>"></i><?= $desc ?>
            </span>

            <!-- Preview del tipo (para tipos sin opciones) -->
            <div class="qb-preview" id="preview-<?= $q['id'] ?>" <?= $needsOpts ? 'style="display:none"' : '' ?>>
              <?php if ($q['type'] === 'scale'): ?>
                <div class="qb-scale-row">
                  <?php for ($n=1; $n<=10; $n++) echo "<div class=\"qb-scale-box\">$n</div>"; ?>
                </div>
                <div class="qb-scale-labels"><span>Muy bajo</span><span>Muy alto</span></div>
              <?php elseif ($q['type'] === 'stars'): ?>
                <div class="qb-stars-row">&#9733; &#9733; &#9733; &#9733; &#9733;</div>
              <?php elseif ($q['type'] === 'open'): ?>
                <div class="qb-open-line">El usuario escribira aqui su respuesta...</div>
              <?php elseif ($q['type'] === 'open_long'): ?>
                <div class="qb-open-long">El usuario escribira un parrafo aqui...</div>
              <?php elseif ($q['type'] === 'yesno'): ?>
                <div class="qb-yesno-row">
                  <span class="qb-yesno-btn"><i class="ti ti-check"></i> S&iacute;</span>
                  <span class="qb-yesno-btn"><i class="ti ti-x"></i> No</span>
                </div>
              <?php elseif ($q['type'] === 'abcd'): ?>
                <div style="font-size:.82rem;color:#80868b;margin-top:6px">
                  <i class="ti ti-alphabet-latin"></i>
                  Las opciones se mostraran como A), B), C), D)...
                </div>
              <?php endif; ?>
            </div>

            <!-- Builder de opciones (multiple / checkbox / dropdown) -->
            <div class="opts-builder" id="opts-<?= $q['id'] ?>"
                 data-type="<?= $q['type'] ?>"
                 <?= $hasOptList ? '' : 'style="display:none"' ?>>
              <div class="opt-list">
                <?php
                  $existingOpts = array_map(fn($o) => $o['label'], $q['options']);
                  if (empty($existingOpts)) $existingOpts = [''];
                  foreach ($existingOpts as $optLbl):
                    $optIcon = ($q['type'] === 'checkbox') ? 'ti-checkbox' : 'ti-circle-dot';
                ?>
                <div class="opt-row">
                  <span class="opt-icon ti <?= $optIcon ?>"></span>
                  <input type="text" class="opt-inp" name="options[]"
                         value="<?= htmlspecialchars($optLbl) ?>"
                         placeholder="Escribe una opcion...">
                  <button type="button" class="opt-del"
                          onclick="removeOpt(this)" title="Quitar opcion">&#x2715;</button>
                </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="opt-add-btn"
                      onclick="addOpt(this.closest('.opts-builder'))">
                <i class="ti ti-plus"></i> Agregar opcion
              </button>
            </div>

          </div>
          <?php Html::closeForm(); ?>
        </form>

        <!-- Footer fuera del form principal: permite incluir el form de eliminar junto al botón guardar -->
        <div class="qb-q-footer">
          <label class="qb-req-label">
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="required"
                     value="1" role="switch"
                     form="qb-form-<?= $q['id'] ?>"
                     <?= $q['required'] ? 'checked' : '' ?>>
            </div>
            Obligatoria
          </label>
          <div class="qb-foot-right">
            <form method="POST" onsubmit="return confirm('¿Eliminar esta pregunta?')" style="margin:0">
              <input type="hidden" name="action"      value="delete_question">
              <input type="hidden" name="surveys_id"  value="<?= $surveysId ?>">
              <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
              <button type="submit" class="qb-del-btn" title="Eliminar pregunta">
                <i class="ti ti-trash"></i>
              </button>
              <?php Html::closeForm(); ?>
            </form>
            <button type="submit" form="qb-form-<?= $q['id'] ?>" class="qb-save-btn">
              <i class="ti ti-device-floppy"></i> Guardar cambios
            </button>
          </div>
        </div>

      </div>
    </div><!-- .qb-q-card -->
    <?php endforeach; ?>

    <!--  Tarjeta: agregar nueva pregunta  -->
    <div class="qb-card qb-add-card">
      <div class="qb-add-header">
        <div class="qb-add-circle"><i class="ti ti-plus"></i></div>
        <span class="qb-add-title">Agregar nueva pregunta</span>
      </div>
      <form method="POST" onsubmit="return collectBeforeSubmit(this)">
        <input type="hidden" name="action"     value="add_question">
        <input type="hidden" name="surveys_id" value="<?= $surveysId ?>">

        <div class="qb-q-row" style="margin-bottom:10px">
          <input class="qb-q-field" type="text" name="question" required
                 placeholder="Que le quieres preguntar al usuario?">
          <div class="qb-type-wrap">
            <select class="qb-type-sel" name="type" id="newQType"
                    onchange="qbToggle(this,'new')">
              <?php foreach ($types as $k => $v): ?>
              <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Preview tipo (nueva pregunta) -->
        <div class="qb-preview" id="preview-new">
          <div class="qb-stars-row">&#9733; &#9733; &#9733; &#9733; &#9733;</div>
        </div>

        <!-- Builder opciones nueva pregunta (multiple/checkbox/dropdown) -->
        <div class="opts-builder" id="opts-new" data-type="multiple" style="display:none">
          <div class="opt-list">
            <div class="opt-row">
              <span class="opt-icon ti ti-circle-dot"></span>
              <input type="text" class="opt-inp" name="options[]" placeholder="Opcion 1" disabled>
              <button type="button" class="opt-del" onclick="removeOpt(this)" title="Quitar">&#x2715;</button>
            </div>
            <div class="opt-row">
              <span class="opt-icon ti ti-circle-dot"></span>
              <input type="text" class="opt-inp" name="options[]" placeholder="Opcion 2" disabled>
              <button type="button" class="opt-del" onclick="removeOpt(this)" title="Quitar">&#x2715;</button>
            </div>
          </div>
          <button type="button" class="opt-add-btn"
                  onclick="addOpt(this.closest('.opts-builder'))">
            <i class="ti ti-plus"></i> Agregar opcion
          </button>
        </div>

        <div class="qb-q-footer" style="border-top:none;padding-top:8px;margin-top:10px">
          <label class="qb-req-label">
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="required" value="1" role="switch">
            </div>
            Obligatoria
          </label>
          <button type="submit" class="qb-add-btn">
            <i class="ti ti-plus"></i> Agregar pregunta
          </button>
        </div>
        <?php Html::closeForm(); ?>
      </form>
    </div>

    <!--  Bottom actions  -->
    <div class="qb-bottom">
      <a href="<?= $baseUrl ?>/front/survey.form.php?id=<?= $surveysId ?>" class="qb-btn-ghost">
        <i class="ti ti-settings-2"></i> Configuracion avanzada
      </a>
      <a href="<?= $baseUrl ?>/front/dashboard.php?surveys_id=<?= $surveysId ?>" class="qb-btn-ghost">
        <i class="ti ti-chart-bar"></i> Ver estadisticas
      </a>
      <a href="<?= $baseUrl ?>/front/survey.php" class="qb-btn-color" style="text-decoration:none">
        <i class="ti ti-checks"></i> Terminar edicion
      </a>
    </div>

  </div><!-- .qb-layout -->
</div><!-- .qb-body -->

<script>
var QBI = <?= json_encode($typeIcons) ?>;
var QBD = <?= json_encode($typeDesc)  ?>;

/* ============================================================
   OPTION BUILDER — funciones globales
   ============================================================ */
function addOpt(builder, value) {
  var type = builder.dataset.type || 'multiple';
  var iconCls = (type === 'checkbox') ? 'ti-checkbox' : 'ti-circle-dot';
  var row = document.createElement('div');
  row.className = 'opt-row';
  row.innerHTML =
    '<span class="opt-icon ti ' + iconCls + '"></span>' +
    '<input type="text" class="opt-inp" name="options[]" value="' +
      (value ? value.replace(/&/g,'&amp;').replace(/"/g,'&quot;') : '') +
    '" placeholder="Escribe una opcion...">' +
    '<button type="button" class="opt-del" onclick="removeOpt(this)" title="Quitar">&#x2715;</button>';
  builder.querySelector('.opt-list').appendChild(row);
  row.querySelector('.opt-inp').focus();
}

function removeOpt(btn) {
  var list = btn.closest('.opt-list');
  if (list.querySelectorAll('.opt-row').length > 1) {
    btn.closest('.opt-row').remove();
  }
}

function updateBuilderIcons(builder, type) {
  var iconCls = (type === 'checkbox') ? 'ti-checkbox'
              : (type === 'abcd')     ? 'ti-letter-a'
              : 'ti-circle-dot';
  builder.querySelectorAll('.opt-icon').forEach(function(el) {
    el.className = 'opt-icon ti ' + iconCls;
  });
  builder.dataset.type = type;
}

/* collectBeforeSubmit — los options[] son gestionados via disabled/enabled, no hace falta nada aqui */
function collectBeforeSubmit(form) { return true; }

/* ============================================================
   qbToggle — cambia la UI segun el tipo seleccionado
   ============================================================ */
function qbToggle(sel, qId) {
  var t = sel.value;
  var hasOpts    = (t === 'multiple' || t === 'abcd' || t === 'checkbox' || t === 'dropdown');
  var hasPreview = !hasOpts;

  var optsEl = document.getElementById('opts-'    + qId);
  var prevEl = document.getElementById('preview-' + qId);
  var chipEl = document.getElementById('chip-'    + qId);

  if (optsEl) {
    optsEl.style.display = hasOpts ? 'block' : 'none';
    optsEl.querySelectorAll('.opt-inp').forEach(function(inp) { inp.disabled = !hasOpts; });
  }
  if (prevEl) prevEl.style.display = hasPreview ? 'block' : 'none';

  /* Actualizar previa estatica */
  if (prevEl && hasPreview) {
    var html = '';
    if (t === 'scale') {
      html = '<div class="qb-scale-row">';
      for (var n=1; n<=10; n++) html += '<div class="qb-scale-box">' + n + '</div>';
      html += '</div><div class="qb-scale-labels"><span>Muy bajo</span><span>Muy alto</span></div>';
    } else if (t === 'stars') {
      html = '<div class="qb-stars-row">&#9733; &#9733; &#9733; &#9733; &#9733;</div>';
    } else if (t === 'open') {
      html = '<div class="qb-open-line">El usuario escribira aqui su respuesta...</div>';
    } else if (t === 'open_long') {
      html = '<div class="qb-open-long">El usuario escribira un parrafo aqui...</div>';
    } else if (t === 'yesno') {
      html = '<div class="qb-yesno-row">' +
        '<span class="qb-yesno-btn"><i class="ti ti-check"></i> Sí</span>' +
        '<span class="qb-yesno-btn"><i class="ti ti-x"></i> No</span>' +
        '</div>';
    }
    prevEl.innerHTML = html;
  }

  /* Actualizar icono del builder de opciones si aplica */
  if (optsEl && hasOpts) {
    updateBuilderIcons(optsEl, t);
  }

  /* Actualizar chip de tipo */
  if (chipEl) {
    var icon = QBI[t] || 'ti-help-circle';
    var desc = QBD[t] || t;
    chipEl.innerHTML = '<i class="ti ' + icon + '"></i>' + desc;
  }
}

/* Al cargar: deshabilitar inputs de builders ocultos para que no se envíen */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.opts-builder').forEach(function(builder) {
    var isHidden = builder.style.display === 'none';
    builder.querySelectorAll('.opt-inp').forEach(function(inp) {
      inp.disabled = isHidden;
    });
  });
});
</script>
<?php Html::footer(); ?>
