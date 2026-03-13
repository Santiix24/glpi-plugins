<?php
/**
 * TicketSat — Lista de respuestas recibidas (administrador)
 */
include('../../../inc/includes.php');
Session::checkRight('config', READ);

global $DB, $CFG_GLPI;
$baseUrl = Plugin::getWebDir('ticketsat', true);
$surveysId = (int)($_GET['surveys_id'] ?? 0);

// Encuestas para filtro
$surveys = [];
$it = $DB->request(['FROM' => 'glpi_plugin_ticketsat_surveys', 'ORDER' => 'name ASC']);
foreach ($it as $row) { $surveys[] = $row; }

// Paginación
$perPage  = 25;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

// WHERE base
$where = [];
if ($surveysId) $where['r.plugin_ticketsat_surveys_id'] = $surveysId;

// Total — compatible GLPI 11 (sin $DB->fetchAssoc)
$total = (int)countElementsInTable(
    'glpi_plugin_ticketsat_responses',
    $surveysId ? ['plugin_ticketsat_surveys_id' => $surveysId] : []
);
$pages = max(1, (int)ceil($total / $perPage));

// Filas — usando doQuery() + fetch_assoc() nativo (compatible GLPI 11)
$whereClause = $surveysId ? 'WHERE r.plugin_ticketsat_surveys_id = ' . (int)$surveysId : '';
$sql = "SELECT r.id, r.tickets_id, r.users_id, r.completed, r.date_send, r.date_answered, r.token,
               s.name AS survey_name,
               u.name AS user_name, u.realname AS user_realname, u.firstname AS user_firstname,
               t.name AS ticket_name
        FROM glpi_plugin_ticketsat_responses r
        LEFT JOIN glpi_plugin_ticketsat_surveys s ON s.id = r.plugin_ticketsat_surveys_id
        LEFT JOIN glpi_users u ON u.id = r.users_id
        LEFT JOIN glpi_tickets t ON t.id = r.tickets_id
        {$whereClause}
        ORDER BY r.id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$rowsIt = $DB->doQuery($sql);

Html::header('Encuestas de Satisfacción — Respuestas', $_SERVER['PHP_SELF'], 'admin', 'PluginTicketsatSurvey', 'response');
?>
<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2><i class="ti ti-message-circle me-2"></i>Respuestas de encuestas</h2>
        <div class="d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" style="min-width:200px"
                    onchange="location.href='?surveys_id='+this.value">
                <option value="0">— Todas las encuestas —</option>
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

    <!-- Tabla -->
    <div class="table-responsive">
        <table class="table table-hover table-sm table-striped">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Encuesta</th>
                    <th>Ticket</th>
                    <th>Respondido por</th>
                    <th>Enviada</th>
                    <th>Respondida</th>
                    <th>Estado</th>
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $found = false;
            while ($row = $rowsIt->fetch_assoc()):
                $found = true;
                $fullName = trim(($row['user_firstname'] ?? '') . ' ' . ($row['user_realname'] ?? ''));
                if (!$fullName) $fullName = $row['user_name'] ?? '?';
            ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['survey_name'] ?? '—') ?></td>
                <td>
                    <a href="<?= $CFG_GLPI['root_doc'] ?>/front/ticket.form.php?id=<?= $row['tickets_id'] ?>"
                       target="_blank" title="<?= htmlspecialchars($row['ticket_name'] ?? '') ?>">
                      #<?= $row['tickets_id'] ?>
                      <?php if (!empty($row['ticket_name'])): ?>
                      <span class="text-muted" style="font-size:.8rem"> — <?= htmlspecialchars(mb_strtolower(mb_substr($row['ticket_name'], 0, 45)) . (mb_strlen($row['ticket_name']) > 45 ? '…' : '')) ?></span>
                      <?php endif; ?>
                    </a>
                </td>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($fullName) ?></div>
                    <div style="font-size:.78rem;color:#64748b"><?= htmlspecialchars($row['user_name'] ?? '') ?></div>
                </td>
                <td><?= $row['date_send'] ? date('d/m/Y H:i', strtotime($row['date_send'])) : '—' ?></td>
                <td><?= $row['date_answered'] ? date('d/m/Y H:i', strtotime($row['date_answered'])) : '—' ?></td>
                <td>
                    <?php if ($row['completed']): ?>
                        <span class="badge bg-success">Completada</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Pendiente</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['completed']): ?>
                    <a href="<?= $baseUrl ?>/front/response_detail.php?id=<?= $row['id'] ?>"
                       class="btn btn-xs btn-outline-primary btn-sm">
                        <i class="ti ti-eye"></i>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if (!$found): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No hay respuestas aún.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($pages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm justify-content-center">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?surveys_id=<?= $surveysId ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <p class="text-muted text-end small"><?= $total ?> registros en total</p>
</div>
<?php Html::footer(); ?>

