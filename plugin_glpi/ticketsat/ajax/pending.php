<?php
/**
 * TicketSat — Encuestas pendientes del usuario actual (JSON)
 * Usado por ticketsat.js para mostrar el banner de notificación
 */
include('../../../inc/includes.php');
Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

global $DB;
$userId = (int)Session::getLoginUserID();

$now = date('Y-m-d H:i:s');

$itR = $DB->request([
    'SELECT' => ['r.id', 'r.token', 'r.tickets_id', 'r.date_send',
                 's.name AS survey_name', 's.expiry_days',
                 't.name AS ticket_name'],
    'FROM'   => 'glpi_plugin_ticketsat_responses AS r',
    'LEFT JOIN' => [
        'glpi_plugin_ticketsat_surveys AS s' => [
            'ON' => ['r' => 'plugin_ticketsat_surveys_id', 's' => 'id']
        ],
        'glpi_tickets AS t' => [
            'ON' => ['r' => 'tickets_id', 't' => 'id']
        ],
    ],
    'WHERE'  => [
        'r.users_id'  => $userId,
        'r.completed' => 0,
        's.active'    => 1,
    ],
    'ORDER'  => 'r.id DESC',
    'LIMIT'  => 10,
]);

$surveys = [];
foreach ($itR as $row) {
    // Verificar expiración
    $expiryDays = (int)($row['expiry_days'] ?? 7);
    if ($row['date_send']) {
        $expiryTs = strtotime($row['date_send']) + $expiryDays * 86400;
        if ($expiryTs < time()) continue; // expirada
    }
    $surveys[] = [
        'id'          => (int)$row['id'],
        'token'       => $row['token'],
        'tickets_id'  => (int)$row['tickets_id'],
        'survey_name' => $row['survey_name'],
        'ticket_name' => $row['ticket_name'] ?? '',
    ];
}

echo json_encode(['count' => count($surveys), 'surveys' => $surveys]);
