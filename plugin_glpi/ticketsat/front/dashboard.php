<?php
/**
 * TicketSat — Dashboard de estadísticas y gráficas
 */
include('../../../inc/includes.php');
Session::checkRight('config', READ);

global $DB, $CFG_GLPI;
$baseUrl = Plugin::getWebDir('ticketsat', true);

$surveysId = (int)($_GET['surveys_id'] ?? 0);

// Cargar encuestas disponibles
$surveys = [];
$it = $DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys', 'ORDER' => 'name ASC']);
foreach ($it as $row) { $surveys[] = $row; }

// Encuesta seleccionada
$selectedSurvey = null;
if ($surveysId) {
    $it2 = $DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys', 'WHERE' => ['id' => $surveysId], 'LIMIT' => 1]);
    foreach ($it2 as $row) { $selectedSurvey = $row; }
} elseif (!empty($surveys)) {
    $selectedSurvey = $surveys[0];
    $surveysId = $selectedSurvey['id'];
}

// Estadísticas globales
$statsGlobal = [
    'total'     => (int)countElementsInTable('glpi_plugin_ticketsat_responses', ['plugin_ticketsat_surveys_id' => $surveysId]),
    'completed' => (int)countElementsInTable('glpi_plugin_ticketsat_responses', ['plugin_ticketsat_surveys_id' => $surveysId, 'completed' => 1]),
];
$statsGlobal['pending'] = $statsGlobal['total'] - $statsGlobal['completed'];
$statsGlobal['rate'] = $statsGlobal['total'] > 0 ? round($statsGlobal['completed'] / $statsGlobal['total'] * 100, 1) : 0;

Html::header('Encuestas de Satisfacción — Dashboard', $_SERVER['PHP_SELF'], 'admin', 'PluginTicketsatSurvey', 'dashboard');
?>
<div class="container-fluid mt-3">
    <!-- Selector de encuesta -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2><i class="ti ti-chart-bar me-2"></i>Dashboard de Satisfacción</h2>
        <div class="d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" style="min-width:220px"
                    onchange="location.href='?surveys_id='+this.value">
                <?php foreach ($surveys as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $surveysId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($surveysId): ?>
            <a href="<?= $baseUrl ?>/ajax/export.php?surveys_id=<?= $surveysId ?>&format=xlsx"
               class="btn btn-sm btn-success"><i class="ti ti-file-spreadsheet me-1"></i>Excel</a>
            <a href="<?= $baseUrl ?>/ajax/export.php?surveys_id=<?= $surveysId ?>&format=csv"
               class="btn btn-sm btn-outline-secondary"><i class="ti ti-download me-1"></i>CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$selectedSurvey): ?>
        <div class="alert alert-info">No hay encuestas creadas todavía.</div>
    <?php else: ?>

    <!-- KPIs -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <div style="font-size:2.5rem;font-weight:700;color:#4472C4"><?= $statsGlobal['total'] ?></div>
                    <div class="text-muted">Total enviadas</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <div style="font-size:2.5rem;font-weight:700;color:#1D6F42"><?= $statsGlobal['completed'] ?></div>
                    <div class="text-muted">Respondidas</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <div style="font-size:2.5rem;font-weight:700;color:#ED7D31"><?= $statsGlobal['pending'] ?></div>
                    <div class="text-muted">Pendientes</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-info">
                <div class="card-body">
                    <div style="font-size:2.5rem;font-weight:700;color:#5B9BD5"><?= $statsGlobal['rate'] ?>%</div>
                    <div class="text-muted">Tasa de respuesta</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Respuestas recibidas: quién respondió y qué ticket -->
    <?php
    $respuestasRecibidas = [];
    $itResp = $DB->request([
        'SELECT'    => [
            'r.id', 'r.date_answered', 'r.tickets_id',
            'u.firstname', 'u.realname', 'u.name AS username',
            't.name AS ticket_name',
        ],
        'FROM'      => 'glpi_plugin_ticketsat_responses AS r',
        'LEFT JOIN' => [
            'glpi_users AS u'   => ['ON' => ['r' => 'users_id',   'u' => 'id']],
            'glpi_tickets AS t' => ['ON' => ['r' => 'tickets_id', 't' => 'id']],
        ],
        'WHERE'  => ['r.plugin_ticketsat_surveys_id' => $surveysId, 'r.completed' => 1],
        'ORDER'  => 'r.date_answered DESC',
        'LIMIT'  => 50,
    ]);
    foreach ($itResp as $row) { $respuestasRecibidas[] = $row; }
    ?>
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2" style="background:#f8f9fa">
            <i class="ti ti-users text-primary"></i>
            <strong>Respuestas recibidas</strong>
            <span class="badge text-bg-primary ms-1"><?= count($respuestasRecibidas) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($respuestasRecibidas)): ?>
                <div class="p-3 text-muted"><i class="ti ti-info-circle me-1"></i>No hay respuestas registradas aún.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="padding:10px 16px">Usuario</th>
                            <th style="padding:10px 16px">Ticket respondido</th>
                            <th style="padding:10px 16px">Fecha</th>
                            <th style="padding:10px 16px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($respuestasRecibidas as $resp):
                        $fullName   = trim(($resp['firstname'] ?? '') . ' ' . ($resp['realname'] ?? ''));
                        $login      = htmlspecialchars($resp['username'] ?? '');
                        $ticketName = htmlspecialchars($resp['ticket_name'] ?? '');
                        $ticketId   = (int)$resp['tickets_id'];
                        $date       = $resp['date_answered']
                                        ? date('d/m/Y H:i', strtotime($resp['date_answered']))
                                        : '—';
                    ?>
                    <tr>
                        <td style="padding:10px 16px">
                            <div style="font-weight:600"><?= htmlspecialchars($fullName ?: $login) ?></div>
                            <div class="text-muted" style="font-size:.78rem"><i class="ti ti-user" style="margin-right:3px"></i><?= $login ?></div>
                        </td>
                        <td style="padding:10px 16px">
                            <div style="font-weight:600"><?= $ticketName ?: '<span class="text-muted">Sin título</span>' ?></div>
                            <div class="text-muted" style="font-size:.78rem">#<?= $ticketId ?></div>
                        </td>
                        <td style="padding:10px 16px;white-space:nowrap;color:#6c757d;font-size:.88rem"><?= $date ?></td>
                        <td style="padding:8px 16px">
                            <a href="<?= $baseUrl ?>/front/response_detail.php?id=<?= (int)$resp['id'] ?>"
                               class="btn btn-sm btn-outline-primary" style="font-size:.78rem;white-space:nowrap">
                                <i class="ti ti-eye me-1"></i>Ver detalle
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>
<?php Html::footer(); ?>

