<?php
/**
 * TicketSat  Formulario de respuesta a encuesta (usuario)
 * Acceso por token: ?token=xxx
 */
include('../../../inc/includes.php');
Session::checkLoginUser();

global $DB, $CFG_GLPI;
$baseUrl = Plugin::getWebDir('ticketsat', true);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if (!$token) {
    Html::displayErrorAndDie('Token inválido.');
}

$responseRow = PluginTicketsatResponse::getByToken($token);
if (!$responseRow) {
    Html::displayErrorAndDie('Encuesta no encontrada o token inválido.');
}

if ((int)$responseRow['users_id'] !== (int)Session::getLoginUserID()) {
    Html::displayErrorAndDie('No tienes permiso para responder esta encuesta.');
}

$alreadyDone = (int)$responseRow['completed'] === 1;

$surveyObj = new PluginTicketsatSurvey();
$surveyObj->getFromDB($responseRow['plugin_ticketsat_surveys_id']);
$questions = $surveyObj->getQuestions();

$ticket = new Ticket();
$ticket->getFromDB($responseRow['tickets_id']);

/* ---- Valores de personalización con compatibilidad retroactiva ---- */
$tsColor      = htmlspecialchars($surveyObj->fields['header_color']    ?? '#4472C4');
$tsLogo       = htmlspecialchars($surveyObj->fields['logo_url']        ?? '');
$tsWelcome    = htmlspecialchars($surveyObj->fields['welcome_message'] ?? '');
$tsThanks     = htmlspecialchars($surveyObj->fields['thank_you_msg']   ?? '¡Gracias por tu respuesta! Tu opinión nos ayuda a mejorar el servicio.');
$tsBtnLabel   = htmlspecialchars($surveyObj->fields['button_label']    ?? 'Enviar respuesta');
$tsShowProg   = (int)($surveyObj->fields['show_progress'] ?? 1);
$totalQ       = count($questions);

/* ---- Guardar respuestas ---- */
$saved  = false;
$errors = [];
if (!$alreadyDone && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    foreach ($questions as $q) {
        $qId   = (int)$q['id'];
        $type  = $q['type'];
        $ansTxt = null;
        $ansVal = null;

        if ($type === 'open' || $type === 'open_long') {
            $ansTxt = trim($_POST['q_' . $qId] ?? '');
            if ($q['required'] && $ansTxt === '') {
                $errors[] = 'La pregunta "' . htmlspecialchars($q['question']) . '" es obligatoria.';
            }
        } elseif (in_array($type, ['scale', 'stars'])) {
            $ansVal = (int)($_POST['q_' . $qId] ?? 0);
            if ($q['required'] && $ansVal === 0) {
                $errors[] = 'La pregunta "' . htmlspecialchars($q['question']) . '" es obligatoria.';
            }
        } elseif (in_array($type, ['multiple', 'abcd', 'dropdown', 'yesno'])) {
            $ansTxt = trim($_POST['q_' . $qId] ?? '');
            if ($q['required'] && $ansTxt === '') {
                $errors[] = 'La pregunta "' . htmlspecialchars($q['question']) . '" es obligatoria.';
            }
        } elseif ($type === 'checkbox') {
            $vals   = $_POST['q_' . $qId] ?? [];
            $ansTxt = is_array($vals) ? implode('|', $vals) : $vals;
        }

        if (empty($errors)) {
            $DB->insert('glpi_plugin_ticketsat_answers', [
                'plugin_ticketsat_responses_id' => (int)$responseRow['id'],
                'plugin_ticketsat_questions_id' => $qId,
                'answer_text'  => $ansTxt,
                'answer_value' => $ansVal,
            ]);
        }
    }

    if (empty($errors)) {
        $DB->update('glpi_plugin_ticketsat_responses', [
            'completed'     => 1,
            'date_answered' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s'),
        ], ['id' => $responseRow['id']]);
        $saved       = true;
        $alreadyDone = true;
    }
}

Html::header('Encuesta de Satisfacción', $_SERVER['PHP_SELF'], 'helpdesk', 'ticket', 'satisfy');
?>
<div class="ts-survey-wrapper">
  <div class="ts-survey-card" style="--ts-color:<?= $tsColor ?>">

    <!--  CABECERA  -->
    <div class="ts-survey-header">
      <div class="ts-header-content">
        <h3><?= htmlspecialchars($surveyObj->fields['name']) ?></h3>
        <div class="ts-ticket-ref">Ticket #<?= $ticket->getID() ?> &nbsp;<?= htmlspecialchars($ticket->fields['name']) ?></div>

        <?php if ($tsWelcome): ?>
          <div class="ts-survey-desc"><?= nl2br($tsWelcome) ?></div>
        <?php elseif (!empty($surveyObj->fields['description'])): ?>
          <div class="ts-survey-desc"><?= htmlspecialchars($surveyObj->fields['description']) ?></div>
        <?php endif; ?>

        <?php if ($tsShowProg && $totalQ > 0 && !$alreadyDone): ?>
          <div class="ts-progress-bar-wrap">
            <div class="ts-progress-bar-fill" id="ts-progress-fill" style="width:0%"></div>
          </div>
        <?php endif; ?>
      </div><!-- /.ts-header-content -->

      <?php if ($tsLogo): ?>
        <div class="ts-logo-wrap">
          <img src="<?= $tsLogo ?>" alt="Logo" class="ts-logo">
        </div>
      <?php endif; ?>
    </div><!-- /.ts-survey-header -->

    <!--  CUERPO  -->
    <div class="ts-survey-body">

      <?php if ($saved || $alreadyDone): ?>
        <!-- GRACIAS -->
        <div class="ts-thanks-wrap">
          <div class="ts-thanks-icon"><?= $saved ? '🎉' : 'ℹ' ?></div>
          <div class="ts-thanks-title"><?= $saved ? '¡Gracias por tu respuesta!' : 'Ya respondiste esta encuesta' ?></div>
          <div class="ts-thanks-sub"><?= $saved ? $tsThanks : 'Solo se puede responder una vez. ¡Gracias!' ?></div>
          <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:16px">
            <a href="<?= $CFG_GLPI['root_doc'] ?>/front/ticket.form.php?id=<?= $ticket->getID() ?>"
               class="ts-submit-btn" style="text-decoration:none;background:var(--ts-color,#4472C4)">
              <i class="ti ti-ticket"></i> Ver ticket
            </a>
            <a href="<?= $CFG_GLPI['root_doc'] ?>/front/helpdesk.public.php"
               class="ts-submit-btn" style="text-decoration:none;background:#64748b">
              <i class="ti ti-arrow-left"></i> Volver
            </a>
          </div>
        </div>

      <?php else: ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="border-radius:10px;margin-bottom:20px">
          <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" id="ts-survey-form">
          <input type="hidden" name="token"         value="<?= htmlspecialchars($token) ?>">
          <input type="hidden" name="submit_survey" value="1">

          <?php foreach ($questions as $i => $q):
            $qId   = (int)$q['id'];
            $qNum  = $i + 1;
          ?>
          <div class="ts-q-block" data-q="<?= $qNum ?>">
            <div class="ts-q-text">
              <span class="ts-q-num"><?= $qNum ?></span>
              <?= htmlspecialchars($q['question']) ?>
              <?php if ($q['required']): ?><span class="ts-required">*</span><?php endif; ?>
            </div>

            <?php if ($q['type'] === 'scale'): ?>
              <div class="ts-scale-group">
                <?php for ($n = 1; $n <= 10; $n++): ?>
                <label class="ts-scale-btn">
                  <input type="radio" name="q_<?= $qId ?>" value="<?= $n ?>" class="ts-q-input">
                  <span><?= $n ?></span>
                </label>
                <?php endfor; ?>
              </div>
              <div class="ts-scale-labels">
                <span>Muy insatisfecho</span><span>Muy satisfecho</span>
              </div>

            <?php elseif ($q['type'] === 'stars'): ?>
              <div class="ts-stars-group" id="stars_<?= $qId ?>">
                <?php for ($n = 5; $n >= 1; $n--): ?>
                <input type="radio" name="q_<?= $qId ?>" id="star_<?= $qId ?>_<?= $n ?>" value="<?= $n ?>" class="ts-q-input">
                <label for="star_<?= $qId ?>_<?= $n ?>" title="<?= $n ?> estrella(s)">&#9733;</label>
                <?php endfor; ?>
              </div>
              <span class="ts-stars-val" id="starlabel_<?= $qId ?>">Selecciona una calificación</span>

            <?php elseif ($q['type'] === 'multiple'): ?>
              <?php foreach ($q['options'] ?? [] as $opt): ?>
              <label class="ts-option-item">
                <input type="radio" name="q_<?= $qId ?>" value="<?= htmlspecialchars($opt['label']) ?>" class="ts-q-input">
                <span><?= htmlspecialchars($opt['label']) ?></span>
              </label>
              <?php endforeach; ?>

            <?php elseif ($q['type'] === 'abcd'): ?>
              <?php
                $letters = range('A', 'Z');
                foreach ($q['options'] ?? [] as $idx => $opt):
                  $letter = $letters[$idx] ?? chr(65 + $idx);
              ?>
              <label class="ts-option-item">
                <input type="radio" name="q_<?= $qId ?>" value="<?= htmlspecialchars($opt['label']) ?>" class="ts-q-input">
                <span><strong><?= $letter ?>)</strong> <?= htmlspecialchars($opt['label']) ?></span>
              </label>
              <?php endforeach; ?>

            <?php elseif ($q['type'] === 'yesno'): ?>
              <label class="ts-option-item">
                <input type="radio" name="q_<?= $qId ?>" value="Si" class="ts-q-input">
                <span><i class="ti ti-check" style="color:#16a34a"></i> S&iacute;</span>
              </label>
              <label class="ts-option-item">
                <input type="radio" name="q_<?= $qId ?>" value="No" class="ts-q-input">
                <span><i class="ti ti-x" style="color:#dc2626"></i> No</span>
              </label>

            <?php elseif ($q['type'] === 'dropdown'): ?>
              <select name="q_<?= $qId ?>" class="ts-q-input ts-dropdown">
                <option value="">-- Selecciona una opci&oacute;n --</option>
                <?php foreach ($q['options'] ?? [] as $opt): ?>
                <option value="<?= htmlspecialchars($opt['label']) ?>">
                  <?= htmlspecialchars($opt['label']) ?>
                </option>
                <?php endforeach; ?>
              </select>

            <?php elseif ($q['type'] === 'checkbox'): ?>
              <?php foreach ($q['options'] ?? [] as $opt): ?>
              <label class="ts-option-item">
                <input type="checkbox" name="q_<?= $qId ?>[]" value="<?= htmlspecialchars($opt['label']) ?>" class="ts-q-input">
                <span><?= htmlspecialchars($opt['label']) ?></span>
              </label>
              <?php endforeach; ?>

            <?php elseif ($q['type'] === 'open'): ?>
              <textarea name="q_<?= $qId ?>" class="ts-open-text ts-q-input" rows="3"
                        placeholder="Escribe tu respuesta aquí..."></textarea>
            <?php elseif ($q['type'] === 'open_long'): ?>
              <textarea name="q_<?= $qId ?>" class="ts-open-text ts-q-input" rows="6"
                        placeholder="Escribe tu respuesta aqu&iacute;..."></textarea>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <div class="text-end mt-4" style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;align-items:center">
            <a href="<?= $CFG_GLPI['root_doc'] ?>/front/helpdesk.public.php"
               class="ts-submit-btn" style="text-decoration:none;background:#64748b">
              <i class="ti ti-arrow-left"></i> Volver
            </a>
            <button type="submit" class="ts-submit-btn">
              <i class="ti ti-send"></i> <?= $tsBtnLabel ?>
            </button>
          </div>
          <?php Html::closeForm(); ?>
      <?php endif; ?>

    </div><!-- /.ts-survey-body -->
  </div><!-- /.ts-survey-card -->
</div><!-- /.ts-survey-wrapper -->

<script>
(function () {
  var total  = <?= $totalQ ?>;
  var showPg = <?= $tsShowProg ? 'true' : 'false' ?>;

  /* Barra de progreso */
  function updateProgress() {
    if (!showPg || total === 0) return;
    var fill = document.getElementById('ts-progress-fill');
    if (!fill) return;
    var answered = document.querySelectorAll(
      'input.ts-q-input:checked, textarea.ts-q-input:not([value=""]), select.ts-q-input'
    ).length;
    /* textos abiertos */
    document.querySelectorAll('textarea.ts-q-input').forEach(function (ta) {
      if (ta.value.trim() !== '') answered++;
    });
    fill.style.width = Math.min(100, Math.round(answered / total * 100)) + '%';
  }

  /* Estrellas: label del valor seleccionado */
  document.querySelectorAll('.ts-stars-group input[type="radio"]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var lbl = document.getElementById('starlabel_' + this.name.replace('q_', ''));
      if (lbl) lbl.textContent = ['', '1 estrella', '2 estrellas', '3 estrellas', '4 estrellas', '5 estrellas'][+this.value] || '';
      updateProgress();
    });
  });

  /* Opciones: clase seleccionada */
  document.querySelectorAll('.ts-option-item input').forEach(function (inp) {
    inp.addEventListener('change', function () {
      if (inp.type === 'radio') {
        document.querySelectorAll('input[name="' + inp.name + '"]').forEach(function (r) {
          r.closest('.ts-option-item').classList.remove('ts-option-checked');
        });
      }
      if (inp.checked) inp.closest('.ts-option-item').classList.add('ts-option-checked');
      else inp.closest('.ts-option-item').classList.remove('ts-option-checked');
      updateProgress();
    });
  });

  document.querySelectorAll('.ts-q-input').forEach(function (inp) {
    inp.addEventListener('change', updateProgress);
    if (inp.tagName === 'TEXTAREA') inp.addEventListener('input', updateProgress);
  });
})();
</script>

<?php Html::footer(); ?>
