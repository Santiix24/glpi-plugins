<?php
/**
 * TicketSat — Hooks
 */

/**
 * Devuelve '#000000' o '#ffffff' según la luminosidad del color de fondo,
 * garantizando siempre legibilidad del texto.
 */
function ts_contrast_color(string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return '#ffffff';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    // Luminosidad relativa (fórmula WCAG)
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 0.55 ? '#000000' : '#ffffff';
}

/**
 * Banner de encuesta pendiente inyectado al abrir un ticket resuelto/cerrado.
 * Se muestra solo al usuario solicitante que tiene una encuesta sin completar.
 */
function plugin_ticketsat_pre_item_form($params) {
    if (!isset($params['item']) || !($params['item'] instanceof Ticket)) {
        return;
    }
    $ticket = $params['item'];
    if (!$ticket->getID()) return;

    $status = (int)($ticket->fields['status'] ?? 0);
    if (!in_array($status, [Ticket::SOLVED, Ticket::CLOSED])) {
        return;
    }

    $userId = (int)Session::getLoginUserID();
    if (!$userId) return;

    global $DB;
    $baseUrl = Plugin::getWebDir('ticketsat', true);

    $responseRow = null;
    foreach ($DB->request([
        'FROM'  => 'glpi_plugin_ticketsat_responses',
        'WHERE' => [
            'tickets_id' => $ticket->getID(),
            'users_id'   => $userId,
            'completed'  => 0,
        ],
        'LIMIT' => 1,
    ]) as $r) { $responseRow = $r; }

    if (!$responseRow) return;

    $color = '#6C63FF';
    foreach ($DB->request([
        'SELECT' => ['header_color', 'name'],
        'FROM'   => 'glpi_plugin_ticketsat_surveys',
        'WHERE'  => ['id' => $responseRow['plugin_ticketsat_surveys_id']],
        'LIMIT'  => 1,
    ]) as $s) {
        $color = htmlspecialchars($s['header_color'] ?? '#6C63FF');
    }

    $respondUrl = htmlspecialchars($baseUrl . '/front/respond.php?token=' . urlencode($responseRow['token']));

    echo <<<HTML
    <div id="ts-survey-banner" style="
        background:linear-gradient(135deg,{$color}18,{$color}08);
        border:2px solid {$color};
        border-radius:12px;
        padding:16px 22px;
        margin:0 0 18px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        flex-wrap:wrap;
        box-shadow:0 2px 14px {$color}30;
    ">
        <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
            <div style="width:46px;height:46px;border-radius:50%;background:{$color};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="ti ti-star" style="font-size:1.4rem;color:#fff"></i>
            </div>
            <div>
                <div style="font-size:.97rem;font-weight:700;color:#202124">&#x00a1;Califica tu experiencia!</div>
                <div style="font-size:.85rem;color:#5f6368;margin-top:2px">Tu ticket fue resuelto. Responde la encuesta y ayudanos a mejorar el servicio.</div>
            </div>
        </div>
        <a href="{$respondUrl}" style="
            display:inline-flex;align-items:center;gap:8px;
            padding:10px 22px;border-radius:8px;
            background:{$color};color:#fff;
            font-size:.88rem;font-weight:700;
            text-decoration:none;white-space:nowrap;
            box-shadow:0 2px 8px {$color}44;
        ">
            <i class="ti ti-clipboard-check"></i> Responder encuesta
        </a>
        <button onclick="document.getElementById('ts-survey-banner').style.display='none'" style="
            background:none;border:none;color:#9aa0a6;cursor:pointer;
            font-size:1.1rem;padding:4px;line-height:1;flex-shrink:0;
        " title="Cerrar">&times;</button>
    </div>
    HTML;
}

/**
 * Widget en la pantalla de Inicio (Central) con encuestas pendientes del usuario.
 */
function plugin_ticketsat_display_central() {
    $userId = (int)Session::getLoginUserID();
    if (!$userId) return;

    global $DB;
    $baseUrl = Plugin::getWebDir('ticketsat', true);

    // Leer color personalizado del encabezado de notificación desde glpi_configs
    $notifHeaderColor = '#6C63FF';
    $cfgIt = $DB->request([
        'SELECT' => ['value'],
        'FROM'   => 'glpi_configs',
        'WHERE'  => ['context' => 'ticketsat', 'name' => 'notification_header_color'],
        'LIMIT'  => 1,
    ]);
    foreach ($cfgIt as $cfgRow) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $cfgRow['value'])) {
            $notifHeaderColor = $cfgRow['value'];
        }
    }

    $pending = [];
    foreach ($DB->request([
        'SELECT'    => ['r.token', 'r.tickets_id', 'r.plugin_ticketsat_surveys_id',
                        's.name AS survey_name', 's.header_color',
                        't.name AS ticket_name'],
        'FROM'      => 'glpi_plugin_ticketsat_responses AS r',
        'LEFT JOIN' => [
            'glpi_plugin_ticketsat_surveys AS s' => [
                'ON' => ['r' => 'plugin_ticketsat_surveys_id', 's' => 'id'],
            ],
            'glpi_tickets AS t' => [
                'ON' => ['r' => 'tickets_id', 't' => 'id'],
            ],
        ],
        'WHERE'  => ['r.users_id' => $userId, 'r.completed' => 0],
        'ORDER'  => 'r.id DESC',
        'LIMIT'  => 10,
    ]) as $row) { $pending[] = $row; }

    if (empty($pending)) return;

    $count = count($pending);
    $notifTextColor = ts_contrast_color($notifHeaderColor);
    echo '<div class="card mb-3" style="border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.12)">';
    echo '<div class="card-header d-flex align-items-center gap-2" style="background:' . htmlspecialchars($notifHeaderColor) . ';color:' . $notifTextColor . ';font-weight:700;font-size:.93rem">';
    echo '<i class="ti ti-clipboard-list"></i> Encuestas pendientes <span class="badge rounded-pill" style="background:#ffffff33;color:#fff;margin-left:4px">' . $count . '</span>';
    echo '</div>';
    echo '<div class="list-group list-group-flush">';
    foreach ($pending as $r) {
        $url        = htmlspecialchars($baseUrl . '/front/respond.php?token=' . urlencode($r['token']));
        $tId        = (int)$r['tickets_id'];
        $name       = htmlspecialchars($r['survey_name'] ?? 'Encuesta de satisfaccion');
        $ticketName = htmlspecialchars($r['ticket_name'] ?? '');
        $ticketLabel = $ticketName ? $ticketName . ' <span style="opacity:.6;font-size:.75rem">(#' . $tId . ')</span>'
                                   : 'Ticket #' . $tId;
        echo '<a href="' . $url . '" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" style="text-decoration:none">';
        echo '<div style="width:10px;height:10px;border-radius:50%;background:' . htmlspecialchars($notifHeaderColor) . ';flex-shrink:0"></div>';
        echo '<div style="flex:1;min-width:0">';
        echo '<div style="font-weight:600;color:#202124;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' . $name . '</div>';
        echo '<div style="font-size:.8rem;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><i class="ti ti-ticket" style="margin-right:3px"></i>' . $ticketLabel . '</div>';
        echo '</div>';
        echo '<span style="font-size:.78rem;color:' . $notifTextColor . ';background:' . htmlspecialchars($notifHeaderColor) . ';padding:4px 12px;border-radius:20px;font-weight:700;flex-shrink:0">Responder</span>';
        echo '</a>';
    }
    echo '</div></div>';
}

/**
 * Se ejecuta cuando se actualiza cualquier ítem en GLPI.
 * Detecta el cambio de estado de un ticket a Resuelto o Cerrado.
 */
function plugin_ticketsat_item_update($item) {
    if (!($item instanceof Ticket)) {
        return;
    }

    // Solo actuar si el campo 'status' fue modificado en esta actualización
    if (!isset($item->input['status'])) {
        return;
    }

    $newStatus = (int) $item->input['status'];
    if (!in_array($newStatus, [Ticket::SOLVED, Ticket::CLOSED])) {
        return;
    }

    global $DB;

    // Evitar duplicados: si ya existe una encuesta para este ticket, no crear otra
    $existing = $DB->request([
        'FROM'  => 'glpi_plugin_ticketsat_responses',
        'WHERE' => ['tickets_id' => $item->getID()],
        'LIMIT' => 1,
    ]);
    if (count($existing) > 0) {
        return;
    }

    // Buscar la encuesta apropiada para este ticket
    $survey = plugin_ticketsat_find_survey_for_ticket($item);
    if (!$survey) {
        return;
    }

    // Verificar trigger según estado
    if ($newStatus === Ticket::SOLVED && !$survey['trigger_on_solve']) {
        return;
    }
    if ($newStatus === Ticket::CLOSED && !$survey['trigger_on_close']) {
        return;
    }

    // Obtener solicitantes y crear registro de encuesta por cada uno
    $requesters = $item->getUsers(CommonITILActor::REQUESTER);
    if (empty($requesters)) {
        return;
    }

    foreach ($requesters as $requester) {
        if (empty($requester['users_id'])) {
            continue;
        }
        plugin_ticketsat_create_response_request($survey, $item, (int) $requester['users_id']);
    }
}

/**
 * Encuentra la encuesta más apropiada para un ticket dado.
 * Prioridad: categoría del ticket → entidad → primera encuesta global activa.
 */
function plugin_ticketsat_find_survey_for_ticket($ticket) {
    global $DB;

    $itilCategoryId = (int) ($ticket->fields['itilcategories_id'] ?? 0);
    $entityId       = (int) ($ticket->fields['entities_id'] ?? 0);

    // 1. Por categoría ITIL
    if ($itilCategoryId > 0) {
        $iterator = $DB->request([
            'SELECT'    => ['s.*'],
            'FROM'      => 'glpi_plugin_ticketsat_surveys AS s',
            'LEFT JOIN' => [
                'glpi_plugin_ticketsat_assignments AS a' => [
                    'ON' => ['s' => 'id', 'a' => 'plugin_ticketsat_surveys_id'],
                ],
            ],
            'WHERE' => [
                's.active'   => 1,
                'a.itemtype' => 'ITILCategory',
                'a.items_id' => $itilCategoryId,
            ],
            'LIMIT' => 1,
        ]);
        foreach ($iterator as $row) {
            return $row;
        }
    }

    // 2. Por entidad
    if ($entityId >= 0) {
        $iterator = $DB->request([
            'SELECT'    => ['s.*'],
            'FROM'      => 'glpi_plugin_ticketsat_surveys AS s',
            'LEFT JOIN' => [
                'glpi_plugin_ticketsat_assignments AS a' => [
                    'ON' => ['s' => 'id', 'a' => 'plugin_ticketsat_surveys_id'],
                ],
            ],
            'WHERE' => [
                's.active'   => 1,
                'a.itemtype' => 'Entity',
                'a.items_id' => $entityId,
            ],
            'LIMIT' => 1,
        ]);
        foreach ($iterator as $row) {
            return $row;
        }
    }

    // 3. Primera encuesta activa (global)
    $iterator = $DB->request([
        'FROM'  => 'glpi_plugin_ticketsat_surveys',
        'WHERE' => ['active' => 1],
        'ORDER' => 'id ASC',
        'LIMIT' => 1,
    ]);
    foreach ($iterator as $row) {
        return $row;
    }

    return null;
}

/**
 * Crea el registro de solicitud de encuesta en la BD.
 */
function plugin_ticketsat_create_response_request(array $survey, $ticket, int $userId) {
    $token    = bin2hex(random_bytes(32));
    $now      = date('Y-m-d H:i:s');

    $response = new PluginTicketsatResponse();
    $newId = $response->add([
        'plugin_ticketsat_surveys_id' => (int) $survey['id'],
        'tickets_id'                  => $ticket->getID(),
        'users_id'                    => $userId,
        'token'                       => $token,
        'completed'                   => 0,
        'date_send'                   => $now,
        'date_creation'               => $now,
        'date_mod'                    => $now,
    ]);

    // Enviar correo automático al usuario al momento de crear la solicitud
    if ($newId) {
        plugin_ticketsat_send_survey_email(
            $userId,
            $ticket->fields['name'] ?? ('Ticket #' . $ticket->getID()),
            $ticket->getID(),
            $survey['name'] ?? 'Encuesta de satisfacción',
            $token,
            (int)$newId
        );
    }
}

/**
 * Envía el correo de notificación de encuesta al usuario.
 * Encola el mensaje en glpi_queuednotifications para que GLPI lo despache
 * con el transporte de correo configurado (SMTP, etc.).
 */
function plugin_ticketsat_send_survey_email(
    int    $userId,
    string $ticketName,
    int    $ticketId,
    string $surveyName,
    string $token,
    int    $responseId
): void {
    global $DB, $CFG_GLPI;

    // Datos del usuario
    $user = new User();
    if (!$user->getFromDB($userId)) return;

    $userEmail = $user->getDefaultEmail();
    if (!$userEmail) return;   // sin correo → silencioso

    $userName   = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''))
                  ?: ($user->fields['name'] ?? 'Usuario');
    $ticketLabel = $ticketName ?: ('Ticket #' . $ticketId);
    $surveyUrl   = rtrim($CFG_GLPI['url_base'] ?? '', '/')
                 . Plugin::getWebDir('ticketsat', false)
                 . '/front/respond.php?token=' . urlencode($token);
    $adminEmail  = $CFG_GLPI['admin_email']      ?? 'noreply@glpi.local';
    $adminName   = $CFG_GLPI['admin_email_name'] ?? 'GLPI';
    $subject     = 'Su opinión importa — ' . $surveyName;

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
      <p style="color:#374151;font-size:1rem;margin-top:0">
        Hola <strong style="color:#1e293b">{$userName}</strong>,
      </p>
      <p style="color:#374151;line-height:1.6">
        Tu caso ha sido <strong>resuelto</strong>. Nos gustaría conocer tu experiencia completando
        una breve encuesta de satisfacción:
      </p>

      <!-- Ticket -->
      <div style="background:#f8fafc;border-left:4px solid #3358c4;border-radius:8px;padding:16px 20px;margin:20px 0">
        <div style="font-weight:700;color:#1e293b;font-size:1rem">{$ticketLabel}</div>
        <div style="font-size:.84rem;color:#64748b;margin-top:4px">Encuesta: <em>{$surveyName}</em></div>
      </div>

      <p style="color:#64748b;font-size:.9rem;line-height:1.6">
        Solo tomará un par de minutos. Tu respuesta es valiosa para brindar un mejor servicio.
      </p>

      <!-- CTA -->
      <div style="text-align:center;margin:28px 0 20px">
        <a href="{$surveyUrl}"
           style="background:linear-gradient(135deg,#1a2e6b,#3358c4);color:#fff;
                  padding:14px 36px;border-radius:30px;text-decoration:none;
                  font-weight:700;font-size:1rem;display:inline-block;
                  box-shadow:0 4px 14px rgba(51,88,196,.4)">
          ✏️&nbsp; Responder encuesta ahora
        </a>
      </div>

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
              . "Tu caso ha sido resuelto. Te invitamos a completar una encuesta de satisfacción.\n\n"
              . "Ticket: {$ticketLabel}\n"
              . "Encuesta: {$surveyName}\n\n"
              . "Responde aquí: {$surveyUrl}\n\n"
              . "Correo automático del sistema GLPI. Por favor no respondas a este mensaje.";

    try {
        $DB->insert('glpi_queuednotifications', [
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
            'messageid'                => 'ticketsat-' . $responseId . '-' . time() . '@glpi',
            'headers'                  => '',
            'documents'                => '',
            'mode'                     => 'mailing',
        ]);

        // Intentar despacho inmediato si GLPI expone el método
        if (
            class_exists('QueuedNotification') &&
            method_exists('QueuedNotification', 'sendById')
        ) {
            $qn = new QueuedNotification();
            $qn->sendById($DB->insertId());
        }
    } catch (\Throwable $e) {
        // Silencioso: el correo quedará en cola para el siguiente cron de GLPI
        Toolbox::logDebug('TicketSat email error: ' . $e->getMessage());
    }
}
