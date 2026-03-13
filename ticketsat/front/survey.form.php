<?php
/**
 * TicketSat  Crear / Editar encuesta (wizard estilo Google Forms)
 */
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

global $DB;
$baseUrl = Plugin::getWebDir('ticketsat', true);

/*  Guardar  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['header_color'] ?? '')
        ? $_POST['header_color'] : '#6C63FF';

    $data = [
        'name'             => trim($_POST['name'] ?? ''),
        'description'      => trim($_POST['description'] ?? ''),
        'active'           => (int)($_POST['active'] ?? 1),
        'trigger_on_solve' => isset($_POST['trigger_on_solve']) ? 1 : 0,
        'trigger_on_close' => isset($_POST['trigger_on_close']) ? 1 : 0,
        'expiry_days'      => max(1, (int)($_POST['expiry_days'] ?? 7)),
        'header_color'     => $color,
        'logo_url'         => trim($_POST['logo_url'] ?? ''),
        'welcome_message'  => trim($_POST['welcome_message'] ?? ''),
        'thank_you_msg'    => trim($_POST['thank_you_msg'] ?? ''),
        'button_label'     => trim($_POST['button_label'] ?? 'Enviar respuesta') ?: 'Enviar respuesta',
        'show_progress'    => isset($_POST['show_progress']) ? 1 : 0,
        'anonymous_mode'   => isset($_POST['anonymous_mode']) ? 1 : 0,
        'date_mod'         => date('Y-m-d H:i:s'),
    ];

    $id    = (int)($_POST['id'] ?? 0);
    $isNew = ($id === 0);
    if ($id > 0) {
        $DB->update('glpi_plugin_ticketsat_surveys', $data, ['id' => $id]);
    } else {
        $data['date_creation'] = date('Y-m-d H:i:s');
        $DB->insert('glpi_plugin_ticketsat_surveys', $data);
        $id = $DB->insertId();
    }

    /* Asignaciones */
    $DB->delete('glpi_plugin_ticketsat_assignments', ['plugin_ticketsat_surveys_id' => $id]);
    if (!empty($_POST['assignment_itemtype'])) {
        $itemtype = in_array($_POST['assignment_itemtype'], ['ITILCategory', 'Entity'])
            ? $_POST['assignment_itemtype'] : '';
        $itemsId  = (int)($_POST['assignment_items_id'] ?? 0);
        if ($itemtype) {
            $DB->insert('glpi_plugin_ticketsat_assignments', [
                'plugin_ticketsat_surveys_id' => $id,
                'itemtype' => $itemtype,
                'items_id' => $itemsId,
            ]);
        }
    }

    if ($isNew) {
        Html::redirect($baseUrl . '/front/question.form.php?surveys_id=' . $id . '&new=1');
    } else {
        Html::redirect($baseUrl . '/front/survey.php');
    }
}

/*  Cargar datos  */
$id = (int)($_GET['id'] ?? 0);
$survey = $assign = [];
if ($id > 0) {
    foreach ($DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys',
        'WHERE' => ['id' => $id], 'LIMIT' => 1]) as $r) { $survey = $r; }
    foreach ($DB->request(['FROM' => 'glpi_plugin_ticketsat_assignments',
        'WHERE' => ['plugin_ticketsat_surveys_id' => $id], 'LIMIT' => 1]) as $r) { $assign = $r; }
}

$sv = fn($k, $d = '') => htmlspecialchars($survey[$k] ?? $d);
$sc = fn($k) => (int)($survey[$k] ?? 0);
$isEdit  = $id > 0;
$color   = $survey['header_color'] ?? '#6C63FF';
$pgTitle = $isEdit ? 'Editar encuesta' : 'Nueva encuesta';

Html::header("Encuestas de Satisfaccion", $_SERVER['PHP_SELF'], 'admin', 'PluginTicketsatSurvey', 'survey');
?>
<style>
/* 
   WIZARD SURVEY FORM  TicketSat
 */
:root { --ts-c: <?= htmlspecialchars($color) ?>; }
.wz-body { background:#f3f4f8; min-height:calc(100vh - 56px); padding:0 0 60px; }

/* Top bar */
.wz-topbar {
  background:#fff; border-bottom:1px solid #e0e0e0;
  padding:12px 28px; display:flex; align-items:center;
  justify-content:space-between; gap:10px; flex-wrap:wrap;
  position:sticky; top:0; z-index:100;
}
.wz-breadcrumb { display:flex; align-items:center; gap:6px; font-size:.88rem; color:#5f6368; }
.wz-breadcrumb a { color:#5f6368; text-decoration:none; display:flex; align-items:center; gap:4px; }
.wz-breadcrumb a:hover { color:#1a73e8; }
.wz-breadcrumb .sep { font-size:.8rem; }

/* Step indicator */
.wz-steps {
  display:flex; align-items:center; gap:0;
  background:#f8f9fa; border-radius:24px; padding:4px;
}
.wz-step {
  display:flex; align-items:center; gap:7px;
  padding:7px 16px; border-radius:20px;
  font-size:.82rem; font-weight:500; color:#80868b; transition:.2s;
}
.wz-step.active {
  background:var(--ts-c); color:#fff;
  box-shadow:0 2px 8px rgba(0,0,0,.18);
}
.wz-step.done { color:var(--ts-c); }
.wz-step-arrow { color:#c0c0c0; font-size:.75rem; }

/* Content area */
.wz-content { max-width:720px; margin:30px auto 0; padding:0 16px; }

/* Cards Google-Forms-style */
.wz-card {
  background:#fff; border-radius:10px;
  box-shadow:0 1px 4px rgba(0,0,0,.15);
  margin-bottom:18px; overflow:hidden;
  transition:box-shadow .2s;
}
.wz-card:focus-within { box-shadow:0 3px 16px rgba(0,0,0,.18); }

/* Hero / identity card */
.wz-hero {
  border-top:8px solid var(--ts-c);
  padding:28px 32px 24px;
}
.wz-big-input {
  width:100%; border:none; border-bottom:2px solid transparent;
  outline:none; font-size:2rem; font-weight:400; color:#202124;
  padding:4px 0 10px; background:transparent; font-family:inherit;
  transition:border-color .2s; box-sizing:border-box; display:block;
}
.wz-big-input:hover { border-bottom-color:#dadce0; }
.wz-big-input:focus { border-bottom-color:var(--ts-c); }
.wz-sub-input {
  width:100%; border:none; border-bottom:1px solid transparent;
  outline:none; font-size:.97rem; color:#5f6368;
  padding:4px 0 6px; background:transparent; resize:none;
  min-height:36px; font-family:inherit;
  transition:border-color .2s; box-sizing:border-box; display:block;
}
.wz-sub-input:hover { border-bottom-color:#dadce0; }
.wz-sub-input:focus { border-bottom-color:var(--ts-c); }

/* Section cards */
.wz-section-lbl {
  padding:16px 24px 0;
  font-size:.78rem; font-weight:700; letter-spacing:.06em;
  text-transform:uppercase; color:var(--ts-c);
}
.wz-section-body { padding:14px 24px 20px; }
.wz-section-body .form-label { font-size:.88rem; font-weight:600; color:#3c4043; }
.wz-field-hint { font-size:.78rem; color:#80868b; margin-top:3px; }

/* Color picker row */
.wz-color-row { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.wz-color-swatch {
  width:52px; height:52px; border-radius:10px; border:2px solid rgba(0,0,0,.1);
  cursor:pointer; transition:.2s; flex-shrink:0;
}
.wz-preset-colors { display:flex; gap:8px; flex-wrap:wrap; }
.wz-preset-dot {
  width:28px; height:28px; border-radius:50%; cursor:pointer; border:2px solid transparent;
  transition:.15s; flex-shrink:0;
}
.wz-preset-dot:hover { transform:scale(1.2); }
.wz-preset-dot.selected { border-color:#fff; box-shadow:0 0 0 2px var(--ts-c); }
.wz-color-hex { font-size:.82rem; color:#5f6368; font-family:monospace; }

/* Header preview */
.wz-preview-box {
  border-radius:10px; overflow:hidden; border:1.5px solid #e0e0e0;
  max-width:360px; margin-top:16px;
}
.wz-preview-head {
  padding:16px 20px; color:#fff;
  background:linear-gradient(135deg, var(--ts-c) 0%, rgba(0,0,0,.4) 100%);
}
.wz-preview-head-title { font-size:.95rem; font-weight:700; }
.wz-preview-head-ref   { font-size:.78rem; opacity:.75; margin-top:3px; }
.wz-preview-foot { padding:7px 14px; background:#f8fafc;
  font-size:.74rem; color:#80868b; text-align:center; }

/* Toggle rows */
.wz-toggle-row {
  display:flex; align-items:center; gap:14px;
  padding:14px 0; border-bottom:1px solid #f0f0f0;
}
.wz-toggle-row:last-child { border-bottom:none; padding-bottom:0; }
.wz-toggle-info { flex:1; }
.wz-toggle-info strong { display:block; font-size:.9rem; color:#202124; font-weight:600; }
.wz-toggle-info small  { color:#80868b; font-size:.8rem; }

/* CTA buttons */
.wz-cta-bar {
  display:flex; gap:12px; justify-content:flex-end;
  padding:20px 0 0; flex-wrap:wrap;
}
.wz-btn-primary {
  display:inline-flex; align-items:center; gap:8px;
  padding:11px 28px; border-radius:6px;
  background:var(--ts-c); color:#fff;
  border:none; font-size:.95rem; font-weight:700;
  cursor:pointer; font-family:inherit;
  box-shadow:0 2px 8px rgba(0,0,0,.2);
  transition:filter .15s, box-shadow .15s;
}
.wz-btn-primary:hover { filter:brightness(1.1); box-shadow:0 4px 16px rgba(0,0,0,.25); }
.wz-btn-secondary {
  display:inline-flex; align-items:center; gap:8px;
  padding:11px 22px; border-radius:6px;
  background:#fff; color:#5f6368;
  border:1.5px solid #dadce0; font-size:.92rem; font-weight:600;
  cursor:pointer; font-family:inherit; text-decoration:none;
  transition:background .15s, border-color .15s;
}
.wz-btn-secondary:hover { background:#f1f3f4; border-color:#bdc1c6; color:#3c4043; }
</style>

<div class="wz-body">

  <!-- Top bar -->
  <div class="wz-topbar">
    <div class="wz-breadcrumb">
      <a href="<?= $baseUrl ?>/front/survey.php">
        <i class="ti ti-clipboard-list"></i> Encuestas
      </a>
      <span class="sep"><i class="ti ti-chevron-right"></i></span>
      <span><?= $pgTitle ?></span>
    </div>

    <div class="wz-steps">
      <div class="wz-step active">
        <i class="ti ti-settings-2"></i> Configurar
      </div>
      <span class="wz-step-arrow"><i class="ti ti-chevron-right"></i></span>
      <div class="wz-step <?= $isEdit ? 'done' : '' ?>">
        <i class="ti ti-list-check"></i> Preguntas
      </div>
      <span class="wz-step-arrow"><i class="ti ti-chevron-right"></i></span>
      <div class="wz-step">
        <i class="ti ti-checks"></i> Listo
      </div>
    </div>
  </div>

  <!-- Contenido del wizard -->
  <div class="wz-content">
    <form method="POST" action="">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

      <!--  1. Identidad  -->
      <div class="wz-card wz-hero">
        <input class="wz-big-input" type="text" name="name" required
               id="surveyNameInput"
               value="<?= $sv('name') ?>"
               placeholder="Nombre de la encuesta *">
        <textarea class="wz-sub-input" name="description" rows="2"
                  placeholder="Descripcion breve (uso interno, no lo ve el usuario)"><?= $sv('description') ?></textarea>
      </div>

      <!--  2. Identidad visual  -->
      <div class="wz-card">
        <div class="wz-section-lbl"><i class="ti ti-palette me-1"></i>Identidad visual</div>
        <div class="wz-section-body">

          <div class="mb-4">
            <label class="form-label">Color principal</label>
            <div class="wz-color-row">
              <input type="color" name="header_color" id="colorPicker"
                     value="<?= htmlspecialchars($color) ?>"
                     style="width:0;height:0;opacity:0;position:absolute;pointer-events:none">
              <div class="wz-color-swatch" id="colorSwatch"
                   style="background:<?= htmlspecialchars($color) ?>"
                   title="Haz clic para escoger color"
                   onclick="document.getElementById('colorPicker').click()"></div>
              <div>
                <div class="wz-preset-colors" id="presetColors">
                  <?php
                  $presets = ['#6C63FF','#1a73e8','#0F9D58','#F4511E','#E91E63','#9C27B0','#00BCD4','#FF5722','#607D8B','#212121'];
                  foreach ($presets as $pc):
                  ?>
                  <div class="wz-preset-dot <?= $color === $pc ? 'selected' : '' ?>"
                       style="background:<?= $pc ?>"
                       data-color="<?= $pc ?>"
                       onclick="setColor('<?= $pc ?>')"
                       title="<?= $pc ?>"></div>
                  <?php endforeach; ?>
                </div>
                <div class="wz-color-hex mt-2" id="colorHex"><?= htmlspecialchars($color) ?></div>
              </div>
            </div>
            <div class="wz-field-hint">Se aplica al encabezado y botones de la encuesta enviada al usuario</div>

            <!-- Preview -->
            <div class="wz-preview-box" id="headerPreviewBox">
              <div class="wz-preview-head" id="headerPreview">
                <div class="wz-preview-head-title" id="previewTitle">
                  <?= $sv('name') ?: 'Nombre de la encuesta' ?>
                </div>
                <div class="wz-preview-head-ref">Ticket #2048  Vista previa</div>
              </div>
              <div class="wz-preview-foot">Asi vera el usuario el encabezado</div>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label">URL del logo <span style="color:#80868b;font-weight:400">(opcional)</span></label>
            <input type="url" name="logo_url" class="form-control" style="max-width:420px"
                   value="<?= $sv('logo_url') ?>"
                   placeholder="https://tu-empresa.com/logo.png">
            <div class="wz-field-hint">Se muestra en el encabezado de la encuesta. Debe ser una URL publica.</div>
          </div>
        </div>
      </div>

      <!--  3. Mensajes  -->
      <div class="wz-card">
        <div class="wz-section-lbl"><i class="ti ti-message-2 me-1"></i>Mensajes al usuario</div>
        <div class="wz-section-body">
          <div class="mb-3">
            <label class="form-label">Mensaje de bienvenida</label>
            <textarea name="welcome_message" class="form-control" rows="2"
                      placeholder="Ej: Nos importa tu opinion. Toma 2 minutos..."><?= $sv('welcome_message') ?></textarea>
            <div class="wz-field-hint">Aparece justo debajo del titulo de la encuesta</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Mensaje de agradecimiento</label>
            <textarea name="thank_you_msg" class="form-control" rows="2"
                      placeholder="Ej: iGracias! Tu opinion nos ayuda a mejorar."><?= $sv('thank_you_msg') ?></textarea>
            <div class="wz-field-hint">Se muestra cuando el usuario completa la encuesta</div>
          </div>
          <div>
            <label class="form-label">Etiqueta del boton de envio</label>
            <input type="text" name="button_label" class="form-control" style="max-width:280px"
                   value="<?= $sv('button_label', 'Enviar respuesta') ?>"
                   placeholder="Enviar respuesta">
          </div>
        </div>
      </div>

      <!--  4. Comportamiento y activacion  -->
      <div class="wz-card">
        <div class="wz-section-lbl"><i class="ti ti-bolt me-1"></i>Activacion y comportamiento</div>
        <div class="wz-section-body">

          <div class="wz-toggle-row">
            <div class="wz-toggle-info">
              <strong>Enviar al resolver el ticket</strong>
              <small>La encuesta se genera cuando el ticket cambia a estado "Resuelto"</small>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="trigger_on_solve"
                     role="switch" <?= ($survey['trigger_on_solve'] ?? 1) ? 'checked' : '' ?>>
            </div>
          </div>

          <div class="wz-toggle-row">
            <div class="wz-toggle-info">
              <strong>Enviar al cerrar el ticket</strong>
              <small>La encuesta se genera cuando el ticket cambia a estado "Cerrado"</small>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="trigger_on_close"
                     role="switch" <?= ($survey['trigger_on_close'] ?? 0) ? 'checked' : '' ?>>
            </div>
          </div>

          <div class="wz-toggle-row">
            <div class="wz-toggle-info">
              <strong>Mostrar barra de progreso</strong>
              <small>El usuario ve que porcentaje de preguntas ha completado</small>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="show_progress"
                     role="switch" <?= ($survey['show_progress'] ?? 1) ? 'checked' : '' ?>>
            </div>
          </div>

          <div class="wz-toggle-row">
            <div class="wz-toggle-info">
              <strong>Modo anonimo</strong>
              <small>Las respuestas no muestran el nombre del usuario en los reportes</small>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="anonymous_mode"
                     role="switch" <?= ($survey['anonymous_mode'] ?? 0) ? 'checked' : '' ?>>
            </div>
          </div>

          <div class="wz-toggle-row">
            <div class="wz-toggle-info">
              <strong>Estado de la encuesta</strong>
              <small>Solo las encuestas activas se envian automaticamente</small>
            </div>
            <select name="active" class="form-select form-select-sm" style="width:130px;flex-shrink:0">
              <option value="1" <?= ($survey['active'] ?? 1) == 1 ? 'selected' : '' ?>>Activa</option>
              <option value="0" <?= ($survey['active'] ?? 1) == 0 ? 'selected' : '' ?>>Inactiva</option>
            </select>
          </div>

          <div class="wz-toggle-row">
            <div class="wz-toggle-info">
              <strong>Dias de vigencia</strong>
              <small>Tiempo que tiene el usuario para responder desde que se envio la encuesta</small>
            </div>
            <div class="d-flex align-items-center gap-2" style="flex-shrink:0">
              <input type="number" name="expiry_days" class="form-control form-control-sm"
                     style="width:75px" min="1" max="365"
                     value="<?= max(1, (int)($survey['expiry_days'] ?? 7)) ?>">
              <span style="font-size:.85rem;color:#5f6368">dias</span>
            </div>
          </div>

        </div>
      </div>

      <!--  5. Asignacion (opcional)  -->
      <div class="wz-card">
        <div class="wz-section-lbl"><i class="ti ti-link me-1"></i>Asignacion <span style="text-transform:none;font-weight:400;color:#80868b">(opcional)</span></div>
        <div class="wz-section-body">
          <p style="font-size:.85rem;color:#80868b;margin-bottom:16px">
            Limita esta encuesta a una categoria ITIL o entidad especifica. Deja en blanco para usarla como encuesta global.
          </p>
          <div class="row g-3">
            <div class="col-sm-5">
              <label class="form-label">Tipo</label>
              <select name="assignment_itemtype" class="form-select">
                <option value="">Global (sin asignacion)</option>
                <option value="ITILCategory" <?= ($assign['itemtype'] ?? '') === 'ITILCategory' ? 'selected' : '' ?>>Categoria ITIL</option>
                <option value="Entity"       <?= ($assign['itemtype'] ?? '') === 'Entity'       ? 'selected' : '' ?>>Entidad</option>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label">ID del elemento</label>
              <input type="number" name="assignment_items_id" class="form-control" min="0"
                     value="<?= (int)($assign['items_id'] ?? 0) ?>"
                     placeholder="ID de categoria o entidad">
            </div>
          </div>
        </div>
      </div>

      <!--  Botones  -->
      <div class="wz-cta-bar">
        <a href="<?= $baseUrl ?>/front/survey.php" class="wz-btn-secondary">
          <i class="ti ti-x"></i> Cancelar
        </a>
        <?php if ($isEdit): ?>
        <a href="<?= $baseUrl ?>/front/question.form.php?surveys_id=<?= $id ?>" class="wz-btn-secondary">
          <i class="ti ti-list-check"></i> Ver preguntas
        </a>
        <?php endif; ?>
        <button type="submit" class="wz-btn-primary">
          <?php if ($isEdit): ?>
            <i class="ti ti-device-floppy"></i> Guardar cambios
          <?php else: ?>
            <i class="ti ti-arrow-right"></i> Siguiente: Agregar preguntas
          <?php endif; ?>
        </button>
      </div>

      <?php Html::closeForm(); ?>
    </form>
  </div>
</div>

<script>
(function () {
  var picker  = document.getElementById('colorPicker');
  var swatch  = document.getElementById('colorSwatch');
  var hexEl   = document.getElementById('colorHex');
  var prev    = document.getElementById('headerPreview');
  var nameIn  = document.getElementById('surveyNameInput');
  var prevTtl = document.getElementById('previewTitle');
  var dots    = document.querySelectorAll('.wz-preset-dot');

  window.setColor = function (c) {
    picker.value = c;
    applyColor(c);
    dots.forEach(function (d) {
      d.classList.toggle('selected', d.dataset.color === c);
    });
  };

  function applyColor(c) {
    swatch.style.background = c;
    hexEl.textContent = c;
    if (prev) prev.style.background = 'linear-gradient(135deg,' + c + ' 0%,rgba(0,0,0,.45) 100%)';
    document.documentElement.style.setProperty('--ts-c', c);
  }

  picker.addEventListener('input',  function () { applyColor(this.value); dots.forEach(function (d) { d.classList.remove('selected'); }); });
  picker.addEventListener('change', function () { applyColor(this.value); });

  if (nameIn && prevTtl) {
    nameIn.addEventListener('input', function () {
      prevTtl.textContent = this.value || 'Nombre de la encuesta';
    });
  }
})();
</script>
<?php Html::footer(); ?>
