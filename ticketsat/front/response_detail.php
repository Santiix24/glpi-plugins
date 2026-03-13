<?php
/**
 * TicketSat — Detalle de una respuesta completada
 */
include('../../../inc/includes.php');
Session::checkRight('config', READ);

global $DB;
$id = (int)($_GET['id'] ?? 0);
if (!$id) Html::redirect(Plugin::getWebDir('ticketsat', false) . '/front/response.php');

// Cargar respuesta
$itR = $DB->request(['FROM' => 'glpi_plugin_ticketsat_responses', 'WHERE' => ['id' => $id], 'LIMIT' => 1]);
$response = null;
foreach ($itR as $r) { $response = $r; }
if (!$response) Html::redirect(Plugin::getWebDir('ticketsat', false) . '/front/response.php');

// Encuesta
$surveyId = $response['plugin_ticketsat_surveys_id'];
$itS = $DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys', 'WHERE' => ['id' => $surveyId], 'LIMIT' => 1]);
$survey = null; foreach ($itS as $s) { $survey = $s; }

// Usuario
$user = new User(); $user->getFromDB($response['users_id']);

// Preguntas y respuestas
$itQ = $DB->request(['FROM' => 'glpi_plugin_ticketsat_questions',
    'WHERE' => ['plugin_ticketsat_surveys_id' => $surveyId], 'ORDER' => 'rank ASC']);
$questions = []; foreach ($itQ as $q) { $questions[$q['id']] = $q; }

$itA = $DB->request(['FROM' => 'glpi_plugin_ticketsat_answers',
    'WHERE' => ['plugin_ticketsat_responses_id' => $id]]);
$answers = []; foreach ($itA as $a) { $answers[$a['plugin_ticketsat_questions_id']] = $a; }

Html::header('Encuestas de Satisfacción — Detalle respuesta', $_SERVER['PHP_SELF'], 'admin', 'PluginTicketsatSurvey', 'response');
?>
<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="ti ti-eye me-2"></i>Detalle de respuesta #<?= $id ?></h2>
        <a href="<?= Plugin::getWebDir('ticketsat', true) ?>/front/dashboard.php?surveys_id=<?= $surveyId ?>"
           class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Volver al Dashboard</a>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Encuesta</th><td><?= htmlspecialchars($survey['name'] ?? '—') ?></td></tr>
                        <tr><th>Ticket</th><td>
                            <a href="<?= $CFG_GLPI['root_doc'] ?>/front/ticket.form.php?id=<?= $response['tickets_id'] ?>"
                               target="_blank">#<?= $response['tickets_id'] ?></a>
                        </td></tr>
                        <tr><th>Usuario</th><td><?= htmlspecialchars($user->getFriendlyName()) ?></td></tr>
                        <tr><th>Enviada</th><td><?= date('d/m/Y H:i', strtotime($response['date_send'])) ?></td></tr>
                        <tr><th>Respondida</th><td><?= $response['date_answered'] ? date('d/m/Y H:i', strtotime($response['date_answered'])) : '—' ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <h4>Respuestas</h4>
    <?php foreach ($questions as $qId => $q):
        $ans = $answers[$qId] ?? null;
    ?>
    <div class="card mb-2">
        <div class="card-body">
            <p class="mb-1"><strong><?= htmlspecialchars($q['question']) ?></strong>
               <span class="badge ms-2" style="background:var(--tblr-primary,#6C63FF);color:var(--tblr-primary-fg,#fff);font-weight:600"><?= PluginTicketsatQuestion::getTypeLabel($q['type']) ?></span></p>
            <?php if (!$ans): ?>
                <em class="text-muted">Sin respuesta</em>
            <?php elseif (in_array($q['type'], ['scale', 'stars'])): ?>
                <?php $val = (int)$ans['answer_value'];
                      $max = $q['type'] === 'stars' ? 5 : 10; ?>
                <span class="badge bg-primary fs-6"><?= $val ?> / <?= $max ?></span>
                <?php if ($q['type'] === 'stars'): ?>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="ti ti-star<?= $i <= $val ? '-filled text-warning' : ' text-muted' ?>"></i>
                <?php endfor; ?>
                <?php endif; ?>
            <?php elseif ($q['type'] === 'checkbox'): ?>
                <?php foreach (explode('|', $ans['answer_text'] ?? '') as $lbl): ?>
                    <?php if (trim($lbl)): ?><span class="badge bg-info me-1"><?= htmlspecialchars(trim($lbl)) ?></span><?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <?= htmlspecialchars($ans['answer_text'] ?? '') ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php Html::footer(); ?>

