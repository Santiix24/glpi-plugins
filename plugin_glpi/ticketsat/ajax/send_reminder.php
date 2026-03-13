<?php
/**
 * TicketSat — Envío de recordatorio por correo a un usuario con encuesta pendiente
 */
include('../../../inc/includes.php');
Session::checkRight('config', READ);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

global $DB, $CFG_GLPI;

$responseId = (int)($_POST['response_id'] ?? 0);
if (!$responseId) {
    echo json_encode(['success' => false, 'message' => 'ID de respuesta inválido.']);
    exit;
}

// Cargar respuesta + encuesta + ticket
$itR = $DB->request([
    'SELECT'    => [
        'r.id', 'r.token', 'r.users_id', 'r.tickets_id', 'r.completed',
        's.name AS survey_name',
        't.name AS ticket_name',
    ],
    'FROM'      => 'glpi_plugin_ticketsat_responses AS r',
    'LEFT JOIN' => [
        'glpi_plugin_ticketsat_surveys AS s' => ['ON' => ['r' => 'plugin_ticketsat_surveys_id', 's' => 'id']],
        'glpi_tickets AS t'                  => ['ON' => ['r' => 'tickets_id', 't' => 'id']],
    ],
    'WHERE' => ['r.id' => $responseId],
    'LIMIT' => 1,
]);
$response = null;
foreach ($itR as $row) { $response = $row; }

if (!$response) {
    echo json_encode(['success' => false, 'message' => 'Registro de encuesta no encontrado.']);
    exit;
}
if ($response['completed']) {
    echo json_encode(['success' => false, 'message' => 'Esta encuesta ya fue completada.']);
    exit;
}

// Datos del usuario
$user = new User();
$user->getFromDB($response['users_id']);
$userEmail = $user->getDefaultEmail();
$userName  = trim($user->fields['firstname'] . ' ' . $user->fields['realname']) ?: $user->fields['name'];

if (!$userEmail) {
    echo json_encode(['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado en el sistema.']);
    exit;
}

// Datos del correo
$surveyName  = $response['survey_name']  ?? 'Encuesta de satisfacción';
$ticketName  = $response['ticket_name']  ?: ('Ticket #' . $response['tickets_id']);
$surveyUrl   = rtrim($CFG_GLPI['url_base'], '/') . Plugin::getWebDir('ticketsat', false)
             . '/front/respond.php?token=' . urlencode($response['token']);
$adminEmail  = $CFG_GLPI['admin_email']      ?? 'noreply@glpi.local';
$adminName   = $CFG_GLPI['admin_email_name'] ?? 'GLPI';
$subject     = 'Recordatorio: ' . $surveyName . ' — encuesta pendiente';

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4fb;font-family:Arial,Helvetica,sans-serif">
  <div style="max-width:560px;margin:36px auto 24px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.09)">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1a2e6b 0%,#3358c4 100%);padding:32px 36px;text-align:center">
      <div style="font-size:2.8rem;line-height:1">⭐</div>
      <h1 style="color:#fff;margin:10px 0 4px;font-size:1.35rem;font-weight:700">Encuesta de Satisfacción</h1>
      <p style="color:rgba(255,255,255,.75);margin:0;font-size:.9rem">Tu opinión nos ayuda a mejorar</p>
    </div>

    <!-- Cuerpo -->
    <div style="padding:32px 36px">
      <p style="color:#374151;font-size:1rem;margin-top:0">Hola <strong style="color:#1e293b">{$userName}</strong>,</p>
      <p style="color:#374151;line-height:1.6">
        Queremos recordarte que tienes una <strong>encuesta de satisfacción pendiente</strong>
        relacionada al siguiente caso:
      </p>

      <!-- Ticket card -->
      <div style="background:#f8fafc;border-left:4px solid #3358c4;border-radius:8px;padding:16px 20px;margin:20px 0">
        <div style="font-weight:700;color:#1e293b;font-size:1rem">{$ticketName}</div>
        <div style="font-size:.84rem;color:#64748b;margin-top:4px">Encuesta: <em>{$surveyName}</em></div>
      </div>

      <p style="color:#64748b;font-size:.9rem;line-height:1.6">
        Solo te tomará unos minutos. Tu respuesta es muy valiosa para nosotros y nos ayuda a brindarte un mejor servicio.
      </p>

      <!-- CTA Button -->
      <div style="text-align:center;margin:28px 0 20px">
        <a href="{$surveyUrl}"
           style="background:linear-gradient(135deg,#1a2e6b,#3358c4);color:#fff;
                  padding:14px 36px;border-radius:30px;text-decoration:none;
                  font-weight:700;font-size:1rem;display:inline-block;
                  box-shadow:0 4px 14px rgba(51,88,196,.4)">
          ✏️&nbsp; Responder encuesta ahora
        </a>
      </div>

      <!-- Fallback URL -->
      <p style="color:#94a3b8;font-size:.78rem;text-align:center;line-height:1.5">
        Si el botón no funciona, copia este enlace en tu navegador:<br>
        <a href="{$surveyUrl}" style="color:#3358c4;word-break:break-all">{$surveyUrl}</a>
      </p>
    </div>

    <!-- Footer -->
    <div style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 36px;text-align:center;color:#94a3b8;font-size:.76rem">
      Correo automático del sistema GLPI &mdash; Por favor no respondas a este mensaje.
    </div>
  </div>
</body>
</html>
HTML;

$textBody = "Hola {$userName},\n\n"
          . "Te recordamos que tienes una encuesta de satisfacción pendiente.\n\n"
          . "Ticket: {$ticketName}\n"
          . "Encuesta: {$surveyName}\n\n"
          . "Responde aquí: {$surveyUrl}\n\n"
          . "Correo automático del sistema GLPI. Por favor no respondas a este mensaje.";

// Insertar en la cola de notificaciones de GLPI
try {
    $inserted = $DB->insert('glpi_queuednotifications', [
        'itemtype'                 => 'PluginTicketsatResponse',
        'items_id'                 => $responseId,
        'notificationtemplates_id' => 0,
        'entities_id'              => 0,
        'is_deleted'               => 0,
        'sent_try'                 => 0,
        'create_time'              => date('Y-m-d H:i:s'),
        'send_time'                => date('Y-m-d H:i:s'),
        'sent_time'                => null,
        'to'                       => $userEmail,
        'toname'                   => $userName,
        'from'                     => $adminEmail,
        'fromname'                 => $adminName,
        'replyto'                  => $adminEmail,
        'replytoname'              => $adminName,
        'subject'                  => $subject,
        'body_text'                => $textBody,
        'body_html'                => $htmlBody,
        'messageid'                => 'ticketsat-reminder-' . $responseId . '-' . time() . '@glpi',
        'headers'                  => '',
        'documents'                => '',
        'mode'                     => 'mailing',
    ]);

    if ($inserted) {
        // Intentar enviar inmediatamente (requiere cron de GLPI activo)
        $queueId = $DB->insertId();
        $qn = new QueuedNotification();
        if (method_exists($qn, 'sendById')) {
            $qn->sendById($queueId);
        }
        echo json_encode([
            'success' => true,
            'message' => "Recordatorio enviado a {$userEmail}",
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo encolar el correo.']);
    }
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar: ' . htmlspecialchars($e->getMessage()),
    ]);
}
