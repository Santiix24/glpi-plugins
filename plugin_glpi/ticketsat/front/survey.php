<?php
/**
 * TicketSat  Lista de encuestas (panel de administración)
 */
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header('Encuestas de Satisfacción', $_SERVER['PHP_SELF'], 'admin', 'PluginTicketsatSurvey', 'survey');

global $DB, $CFG_GLPI;
$baseUrl = Plugin::getWebDir('ticketsat', true);

/* ---- Eliminación ---- */
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $qIt = $DB->request(['FROM' => 'glpi_plugin_ticketsat_questions', 'WHERE' => ['plugin_ticketsat_surveys_id' => $id]]);
    foreach ($qIt as $q) {
        $DB->delete('glpi_plugin_ticketsat_options', ['plugin_ticketsat_questions_id' => $q['id']]);
    }
    $DB->delete('glpi_plugin_ticketsat_questions',   ['plugin_ticketsat_surveys_id' => $id]);
    $DB->delete('glpi_plugin_ticketsat_assignments', ['plugin_ticketsat_surveys_id' => $id]);
    $DB->delete('glpi_plugin_ticketsat_surveys',     ['id' => $id]);
    Html::redirect($baseUrl . '/front/survey.php');
}

$surveys = [];
foreach ($DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys', 'ORDER' => 'name ASC']) as $row) {
    $surveys[] = $row;
}

// Colores personalizados del banner admin (SQL directo, no requiere registro previo)
function ts_get_cfg(string $name, string $default): string {
    global $DB;
    $it = $DB->request(['SELECT'=>['value'],'FROM'=>'glpi_configs','WHERE'=>['context'=>'ticketsat','name'=>$name],'LIMIT'=>1]);
    foreach ($it as $r) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $r['value'])) return $r['value'];
    }
    return $default;
}
function ts_get_cfg_raw(string $name, string $default): string {
    global $DB;
    $it = $DB->request(['SELECT'=>['value'],'FROM'=>'glpi_configs','WHERE'=>['context'=>'ticketsat','name'=>$name],'LIMIT'=>1]);
    foreach ($it as $r) { return $r['value']; }
    return $default;
}
// Devuelve '#000000' o '#ffffff' según luminosidad para garantizar contraste de texto
if (!function_exists('ts_contrast_color')) {
    function ts_contrast_color(string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#ffffff';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.55 ? '#000000' : '#ffffff';
    }
}
$bannerColor1 = ts_get_cfg('banner_color1', '');
$bannerColor2 = ts_get_cfg('banner_color2', '');
$bannerAngle  = (int) ts_get_cfg_raw('banner_angle', '135');
$notifColor   = ts_get_cfg('notification_header_color', '#6C63FF');
$csrfToken    = Session::getNewCSRFToken();

?>

<div class="container-fluid py-3">
<?php
$dynGrad = '';
if ($bannerColor1 || $bannerColor2) {
    $c1 = $bannerColor1 ?: '#1e3a5f';
    $c2 = $bannerColor2 ?: '#2e62bb';
    $ag = $bannerAngle;
    $dynGrad = "linear-gradient({$ag}deg,{$c1} 0%,{$c2} 100%)";
}
?>
<style id="ts-dyn-style"><?= $dynGrad ? ".ts-admin-header{background:{$dynGrad} !important}" : '' ?></style>
  <!-- Cabecera admin -->
  <div class="ts-admin-header" id="ts-admin-header">
    <div>
      <h2><i class="ti ti-clipboard-list me-2"></i>Encuestas de Satisfacción</h2>
      <p>Gestiona y personaliza tus encuestas de satisfacción de tickets</p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <!-- Color picker del banner -->
      <div style="position:relative;display:inline-flex;align-items:center">
        <button id="ts-color-toggle"
          title="Cambiar colores del banner"
          style="background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.45);color:inherit;
                 border-radius:8px;padding:7px 12px;cursor:pointer;display:flex;align-items:center;gap:6px;
                 font-size:.85rem;font-weight:600;backdrop-filter:blur(4px)">
          <i class="ti ti-palette"></i>
          <span style="display:inline-flex;gap:3px;align-items:center">
            <span id="ts-swatch1" style="width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.7);background:<?= $bannerColor1 ?: '#1e3a5f' ?>"></span>
            <span id="ts-swatch2" style="width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.7);background:<?= $bannerColor2 ?: '#2e62bb' ?>"></span>
          </span>
        </button>
        <div id="ts-color-popover"
          style="display:none;position:absolute;top:calc(100% + 8px);right:0;z-index:9999;
                 background:#fff;border-radius:16px;padding:20px;box-shadow:0 12px 40px rgba(0,0,0,.22);
                 min-width:270px">

          <!-- Preview del gradiente -->
          <div id="ts-gradient-preview"
            style="height:44px;border-radius:10px;margin-bottom:14px;
                   background:linear-gradient(<?= $bannerAngle ?>deg,<?= $bannerColor1 ?: '#1e3a5f' ?> 0%,<?= $bannerColor2 ?: '#2e62bb' ?> 100%);
                   box-shadow:0 2px 8px rgba(0,0,0,.15)"></div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
            <div>
              <label style="font-size:.72rem;color:#6b7280;font-weight:600;display:block;margin-bottom:5px">◀ Color inicio</label>
              <input type="color" id="ts-color1-input" value="<?= $bannerColor1 ?: '#1e3a5f' ?>"
                style="width:100%;height:42px;border:1.5px solid #e5e7eb;border-radius:9px;cursor:pointer;padding:3px">
            </div>
            <div>
              <label style="font-size:.72rem;color:#6b7280;font-weight:600;display:block;margin-bottom:5px">Color fin ▶</label>
              <input type="color" id="ts-color2-input" value="<?= $bannerColor2 ?: '#2e62bb' ?>"
                style="width:100%;height:42px;border:1.5px solid #e5e7eb;border-radius:9px;cursor:pointer;padding:3px">
            </div>
          </div>

          <div style="font-size:.72rem;color:#6b7280;font-weight:700;margin-bottom:7px;letter-spacing:.03em">PRESETS</div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:14px">
            <?php foreach ([
              ['#1e3a5f','#4472C4'],['#312e81','#6C63FF'],['#0c4a6e','#0ea5e9'],
              ['#064e3b','#10b981'],['#3f6212','#84cc16'],['#713f12','#f59e0b'],
              ['#7f1d1d','#ef4444'],['#581c87','#d946ef'],['#18181b','#52525b'],
            ] as [$_p1,$_p2]): ?>
            <span data-c1="<?= $_p1 ?>" data-c2="<?= $_p2 ?>"
              style="height:26px;border-radius:8px;cursor:pointer;border:2.5px solid transparent;
                     background:linear-gradient(135deg,<?= $_p1 ?> 0%,<?= $_p2 ?> 100%);
                     transition:border-color .15s,transform .1s"
              onmouseenter="this.style.borderColor='#6b7280';this.style.transform='scale(1.05)'"
              onmouseleave="this.style.borderColor='transparent';this.style.transform='scale(1)'"></span>
            <?php endforeach; ?>
          </div>

          <!-- Ángulo del gradiente -->
          <div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
              <label style="font-size:.72rem;color:#6b7280;font-weight:700">Dirección del gradiente</label>
              <span id="ts-angle-label" style="font-size:.72rem;font-weight:700;color:#374151"><?= $bannerAngle ?>°</span>
            </div>
            <input type="range" id="ts-angle-input" min="0" max="360" value="<?= $bannerAngle ?>"
              style="width:100%;accent-color:var(--tblr-primary,#2e62bb);cursor:pointer">
            <div style="display:flex;justify-content:space-between;font-size:.68rem;color:#9ca3af;margin-top:2px">
              <span>→</span><span>↓</span><span>←</span><span>↑</span><span>→</span>
            </div>
          </div>

          <div style="display:flex;gap:8px">
            <button id="ts-color-save"
              style="flex:1;background:var(--tblr-primary,#2e62bb);color:#fff;border:none;
                     border-radius:9px;padding:9px;font-weight:700;cursor:pointer;font-size:.85rem;
                     transition:opacity .2s">
              Guardar
            </button>
            <button id="ts-color-reset"
              style="flex:1;background:#f3f4f6;color:#374151;border:none;
                     border-radius:9px;padding:9px;font-weight:600;cursor:pointer;font-size:.85rem">
              Restablecer
            </button>
          </div>
        </div>
      </div>
      <a href="<?= $baseUrl ?>/front/survey.form.php" class="ts-new-btn">
        <i class="ti ti-plus"></i> Nueva Encuesta
      </a>
    </div><!-- /.d-flex -->
  </div><!-- /.ts-admin-header -->

  <!-- Popover oculto del color de notificación (se muestra desde la barra superior) -->
  <div id="ts-notif-popover" style="display:none;position:fixed;z-index:99999;
       background:#fff;border-radius:16px;padding:20px;box-shadow:0 12px 40px rgba(0,0,0,.22);min-width:260px">
    <div style="font-size:.78rem;font-weight:700;color:#374151;margin-bottom:12px;display:flex;align-items:center;gap:7px">
      <i class="ti ti-bell" style="color:#6C63FF"></i> Color del banner de Encuestas pendientes
    </div>
    <!-- Preview -->
    <div id="ts-notif-preview"
      style="border-radius:9px;padding:10px 14px;display:flex;align-items:center;gap:8px;
             background:<?= htmlspecialchars($notifColor) ?>;color:#fff;font-size:.82rem;font-weight:700;
             box-shadow:0 2px 8px rgba(0,0,0,.13);margin-bottom:14px">
      <i class="ti ti-clipboard-list"></i> Encuestas pendientes
      <span style="background:rgba(255,255,255,.25);font-size:.7rem;padding:1px 8px;border-radius:20px;margin-left:2px">1</span>
    </div>
    <input type="color" id="ts-notif-color-input" value="<?= htmlspecialchars($notifColor) ?>"
      style="width:100%;height:40px;border:1.5px solid #e5e7eb;border-radius:9px;cursor:pointer;padding:3px;margin-bottom:12px">

    <?php if (!empty($surveys)): ?>
    <div style="font-size:.68rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">
      Del formulario
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px">
      <?php foreach ($surveys as $_s):
        $_sc = htmlspecialchars($_s['header_color'] ?? '#4472C4');
        $_sn = htmlspecialchars($_s['name']);
      ?>
      <span data-nc="<?= $_sc ?>" title="<?= $_sn ?> — <?= $_sc ?>"
        style="height:26px;padding:0 10px;border-radius:7px;cursor:pointer;border:2.5px solid transparent;
               background:<?= $_sc ?>;display:inline-flex;align-items:center;
               font-size:.68rem;font-weight:700;color:<?= ts_contrast_color(ltrim($_s['header_color'] ?? '#4472C4','#')) ?>;
               max-width:120px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
               transition:border-color .15s,transform .1s"
        onmouseenter="this.style.borderColor='#374151';this.style.transform='scale(1.05)'"
        onmouseleave="this.style.borderColor='transparent';this.style.transform='scale(1)'">
        <?= $_sn ?>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="font-size:.68rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">
      Presets
    </div>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-bottom:14px">
      <?php foreach (['#6C63FF','#4472C4','#0ea5e9','#10b981','#f59e0b','#ef4444','#d946ef','#52525b','#1a2e6b','#064e3b'] as $_nc): ?>
      <span data-nc="<?= $_nc ?>" title="<?= $_nc ?>"
        style="height:26px;border-radius:7px;cursor:pointer;border:2.5px solid transparent;
               background:<?= $_nc ?>;transition:border-color .15s,transform .1s"
        onmouseenter="this.style.borderColor='#374151';this.style.transform='scale(1.1)'"
        onmouseleave="this.style.borderColor='transparent';this.style.transform='scale(1)'"></span>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px">
      <button id="ts-notif-save"
        style="flex:1;background:var(--tblr-primary,#2e62bb);color:#fff;border:none;border-radius:9px;
               padding:8px;font-weight:700;cursor:pointer;font-size:.83rem">
        <i class="ti ti-device-floppy" style="margin-right:3px"></i>Guardar
      </button>
      <button id="ts-notif-reset"
        style="flex:1;background:#f3f4f6;color:#374151;border:1.5px solid #e5e7eb;
               border-radius:9px;padding:8px;font-weight:600;cursor:pointer;font-size:.83rem">
        Restablecer
      </button>
    </div>
  </div>

  <?php if (empty($surveys)): ?>
    <div style="text-align:center;padding:60px 20px;color:#64748b">
      <div style="font-size:4rem;margin-bottom:16px"></div>
      <h4>Aún no hay encuestas</h4>
      <p>Crea tu primera encuesta para empezar a recibir retroalimentación de tus usuarios.</p>
      <a href="<?= $baseUrl ?>/front/survey.form.php" class="ts-btn-primary">
        <i class="ti ti-plus"></i> Crear primera encuesta
      </a>
    </div>

  <?php else: ?>
    <div class="ts-survey-grid">
      <?php foreach ($surveys as $s):
        $surveyObj = new PluginTicketsatSurvey();
        $surveyObj->getFromDB($s['id']);
        $stats = $surveyObj->getStats();
        $numQ  = countElementsInTable('glpi_plugin_ticketsat_questions', ['plugin_ticketsat_surveys_id' => $s['id']]);
        $color = htmlspecialchars($s['header_color'] ?? '#4472C4');
        $rateClass = $stats['rate'] >= 60 ? 'success' : ($stats['rate'] >= 30 ? 'warning' : 'danger');
      ?>
      <div class="ts-survey-card-admin">
        <div class="ts-card-top" style="background:<?= $color ?>"></div>
        <div class="ts-card-body">
          <div class="ts-card-title"><?= htmlspecialchars($s['name']) ?></div>
          <?php if (!empty($s['description'])): ?>
          <div class="ts-card-desc"><?= htmlspecialchars($s['description']) ?></div>
          <?php endif; ?>
          <div class="ts-card-meta">
            <?php if ($s['active']): ?>
              <span class="ts-badge active"><i class="ti ti-check"></i> Activa</span>
            <?php else: ?>
              <span class="ts-badge inactive"><i class="ti ti-pause"></i> Inactiva</span>
            <?php endif; ?>
            <?php if ($s['trigger_on_solve']): ?>
              <span class="ts-badge solve"><i class="ti ti-check-circle"></i> Al resolver</span>
            <?php endif; ?>
            <?php if ($s['trigger_on_close']): ?>
              <span class="ts-badge close"><i class="ti ti-lock"></i> Al cerrar</span>
            <?php endif; ?>
          </div>
          <div class="ts-card-stats">
            <div class="ts-stat-item">
              <span class="val"><?= (int)$numQ ?></span>
              <span class="lbl">Preguntas</span>
            </div>
            <div class="ts-stat-divider"></div>
            <div class="ts-stat-item">
              <span class="val"><?= $stats['completed'] ?></span>
              <span class="lbl">Respuestas</span>
            </div>
            <div class="ts-stat-divider"></div>
            <div class="ts-stat-item">
              <span class="val" style="color:<?= $rateClass === 'success' ? '#15803d' : ($rateClass === 'warning' ? '#92400e' : '#dc2626') ?>">
                <?= $stats['rate'] ?>%
              </span>
              <span class="lbl">Tasa resp.</span>
            </div>
            <div class="ts-stat-divider"></div>
            <div class="ts-stat-item">
              <span class="val"><?= (int)$s['expiry_days'] ?>d</span>
              <span class="lbl">Vigencia</span>
            </div>
          </div>
        </div>
        <div class="ts-card-actions">
          <a href="<?= $baseUrl ?>/front/survey.form.php?id=<?= $s['id'] ?>" class="ts-action-btn edit">
            <i class="ti ti-edit"></i> Editar
          </a>
          <a href="<?= $baseUrl ?>/front/question.form.php?surveys_id=<?= $s['id'] ?>" class="ts-action-btn qs">
            <i class="ti ti-list-numbers"></i> Preguntas
          </a>
          <a href="<?= $baseUrl ?>/front/dashboard.php?surveys_id=<?= $s['id'] ?>" class="ts-action-btn dash">
            <i class="ti ti-chart-bar"></i> Stats
          </a>
          <a href="?delete_id=<?= $s['id'] ?>" class="ts-action-btn del"
             onclick="return confirm('¿Eliminar <?= htmlspecialchars(addslashes($s['name'])) ?> y todas sus respuestas? Esta acción no se puede deshacer.')">
            <i class="ti ti-trash"></i> Eliminar
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<script>
(function() {
    var toggle      = document.getElementById('ts-color-toggle');
    var popover     = document.getElementById('ts-color-popover');
    var inp1        = document.getElementById('ts-color1-input');
    var inp2        = document.getElementById('ts-color2-input');
    var angleSlider = document.getElementById('ts-angle-input');
    var angleLabel  = document.getElementById('ts-angle-label');
    var sw1         = document.getElementById('ts-swatch1');
    var sw2         = document.getElementById('ts-swatch2');
    var preview     = document.getElementById('ts-gradient-preview');
    var header      = document.getElementById('ts-admin-header');
    var dynStyle    = document.getElementById('ts-dyn-style');
    var saveBtn     = document.getElementById('ts-color-save');
    var resetBtn    = document.getElementById('ts-color-reset');
    var ajaxUrl     = '<?= $baseUrl ?>/ajax/save_header_color.php';
    var csrfToken   = '<?= $csrfToken ?>';

    function applyPreview(c1, c2, angle) {
        var grad = 'linear-gradient(' + angle + 'deg,' + c1 + ' 0%,' + c2 + ' 100%)';
        sw1.style.background     = c1;
        sw2.style.background     = c2;
        preview.style.background = grad;
        // Actualizar el <style> con !important para superar reglas CSS del tema
        dynStyle.textContent = '.ts-admin-header{background:' + grad + ' !important}';
    }

    function currentAngle() { return parseInt(angleSlider.value, 10); }

    // Slider de ángulo
    angleSlider.addEventListener('input', function() {
        angleLabel.textContent = this.value + '°';
        applyPreview(inp1.value, inp2.value, currentAngle());
    });

    // Abrir/cerrar popover
    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        popover.style.display = popover.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', function(e) {
        if (!popover.contains(e.target) && e.target !== toggle) {
            popover.style.display = 'none';
        }
    });

    // Preview en tiempo real (input = mientras arrastra, change = al cerrar el picker)
    inp1.addEventListener('input',  function() { applyPreview(inp1.value, inp2.value, currentAngle()); });
    inp1.addEventListener('change', function() { applyPreview(inp1.value, inp2.value, currentAngle()); });
    inp2.addEventListener('input',  function() { applyPreview(inp1.value, inp2.value, currentAngle()); });
    inp2.addEventListener('change', function() { applyPreview(inp1.value, inp2.value, currentAngle()); });

    // Presets de gradiente
    popover.querySelectorAll('span[data-c1]').forEach(function(dot) {
        dot.addEventListener('click', function() {
            inp1.value = this.dataset.c1;
            inp2.value = this.dataset.c2;
            applyPreview(inp1.value, inp2.value, currentAngle());
        });
    });

    // Guardar
    saveBtn.addEventListener('click', function() {
        var c1 = inp1.value, c2 = inp2.value, angle = currentAngle();
        saveBtn.disabled = true;
        saveBtn.textContent = 'Guardando...';
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': csrfToken
            },
            body: 'color1=' + encodeURIComponent(c1)
                + '&color2=' + encodeURIComponent(c2)
                + '&angle='  + encodeURIComponent(angle)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                saveBtn.textContent = '✓ Guardado';
                setTimeout(function() {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar';
                    popover.style.display = 'none';
                }, 1200);
            } else {
                saveBtn.textContent = 'Error: ' + (d.message || '');
                saveBtn.disabled = false;
                setTimeout(function() { saveBtn.textContent = 'Guardar'; }, 2500);
            }
        })
        .catch(function() {
            saveBtn.textContent = 'Error de red';
            saveBtn.disabled = false;
            setTimeout(function() { saveBtn.textContent = 'Guardar'; }, 2500);
        });
    });

    // Restablecer
    resetBtn.addEventListener('click', function() {
        resetBtn.textContent = 'Restableciendo...';
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': csrfToken
            },
            body: 'reset=1'
        })
        .then(function() { location.reload(); })
        .catch(function() { location.reload(); });
    });
})();

// ---- Color de notificación: botón inyectado en la barra de navegación superior ----
(function() {
    var popover  = document.getElementById('ts-notif-popover');
    var inp      = document.getElementById('ts-notif-color-input');
    var preview  = document.getElementById('ts-notif-preview');
    var saveBtn  = document.getElementById('ts-notif-save');
    var resetBtn = document.getElementById('ts-notif-reset');
    var ajaxUrl  = '<?= $baseUrl ?>/ajax/save_header_color.php';
    var csrfToken = '<?= $csrfToken ?>';

    // Crear botón e inyectarlo JUSTO DESPUÉS del enlace "Respuestas" en la subnavbar
    function injectNavBtn() {
        // Buscar el enlace de Respuestas — es el ancla más segura porque está en esta misma página
        var anchor = document.querySelector('a[href*="response.php"]');
        if (!anchor) return;

        var parentLi = anchor.closest('li') || anchor.parentElement;
        var parentUl = parentLi ? parentLi.parentElement : null;
        if (!parentUl) return;

        var li = document.createElement('li');
        li.className = 'nav-item';
        // Mismo tag <a class="nav-link"> que usa GLPI para Dashboard y Respuestas
        var a = document.createElement('a');
        a.id        = 'ts-notif-nav-btn';
        a.href      = '#';
        a.className = 'nav-link';
        a.title     = 'Color del banner Encuestas pendientes';
        a.style.cssText = 'display:inline-flex;align-items:center;gap:5px;cursor:pointer';
        a.innerHTML =
            '<span id="ts-notif-nav-swatch" style="width:10px;height:10px;border-radius:50%;' +
            'background:<?= htmlspecialchars($notifColor) ?>;border:1.5px solid currentColor;' +
            'flex-shrink:0;display:inline-block;opacity:.85"></span>' +
            '<i class="ti ti-bell"></i> Color notificación';
        li.appendChild(a);

        // Insertar justo después del <li> de Respuestas
        parentUl.insertBefore(li, parentLi.nextSibling);

        a.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var rect = this.getBoundingClientRect();
            popover.style.top  = (rect.bottom + 6) + 'px';
            popover.style.left = Math.max(4, rect.right - 260) + 'px';
            popover.style.display = popover.style.display === 'none' ? 'block' : 'none';
        });
    }

    // Cerrar al click fuera
    document.addEventListener('click', function(e) {
        var btn = document.getElementById('ts-notif-nav-btn');
        if (btn && !popover.contains(e.target) && !btn.contains(e.target)) {
            popover.style.display = 'none';
        }
    });

    // Calcula negro o blanco según luminosidad para garantizar contraste
    function contrastColor(hex) {
        hex = hex.replace('#', '');
        if (hex.length !== 6) return '#ffffff';
        var r = parseInt(hex.substr(0,2),16);
        var g = parseInt(hex.substr(2,2),16);
        var b = parseInt(hex.substr(4,2),16);
        var lum = (0.299*r + 0.587*g + 0.114*b) / 255;
        return lum > 0.55 ? '#000000' : '#ffffff';
    }

    function applyPreview(color) {
        preview.style.background = color;
        preview.style.color = contrastColor(color);
        var sw = document.getElementById('ts-notif-nav-swatch');
        if (sw) sw.style.background = color;
    }

    inp.addEventListener('input',  function() { applyPreview(inp.value); });
    inp.addEventListener('change', function() { applyPreview(inp.value); });

    // Presets
    document.querySelectorAll('#ts-notif-popover span[data-nc]').forEach(function(dot) {
        dot.addEventListener('click', function() {
            inp.value = this.dataset.nc;
            applyPreview(inp.value);
        });
    });

    // Guardar
    saveBtn.addEventListener('click', function() {
        saveBtn.disabled = true;
        saveBtn.innerHTML = 'Guardando...';
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': csrfToken
            },
            body: 'notification_color=' + encodeURIComponent(inp.value)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                saveBtn.innerHTML = '✓ Guardado';
                setTimeout(function() {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="ti ti-device-floppy" style="margin-right:3px"></i>Guardar';
                    popover.style.display = 'none';
                }, 1300);
            } else {
                saveBtn.innerHTML = 'Error';
                saveBtn.disabled = false;
            }
        })
        .catch(function() { saveBtn.innerHTML = 'Error'; saveBtn.disabled = false; });
    });

    // Restablecer
    resetBtn.addEventListener('click', function() {
        var def = '#6C63FF';
        inp.value = def;
        applyPreview(def);
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': csrfToken
            },
            body: 'notification_color=' + encodeURIComponent(def)
        }).catch(function() {});
    });

    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectNavBtn);
    } else {
        injectNavBtn();
    }
})();
</script>
<?php Html::footer(); ?>
