<?php
/**
 * TicketSat — Guarda los colores del banner admin directamente en glpi_configs
 */
include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

header('Content-Type: application/json');

global $DB;

function ts_save_cfg(string $name, string $value): void {
    global $DB;
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) return;
    ts_save_cfg_raw($name, $value);
}
function ts_save_cfg_raw(string $name, string $value): void {
    global $DB;
    $exists = false;
    $it = $DB->request(['SELECT'=>['id'],'FROM'=>'glpi_configs','WHERE'=>['context'=>'ticketsat','name'=>$name],'LIMIT'=>1]);
    foreach ($it as $r) { $exists = true; }
    if ($exists) {
        $DB->update('glpi_configs', ['value'=>$value], ['context'=>'ticketsat','name'=>$name]);
    } else {
        $DB->insert('glpi_configs', ['context'=>'ticketsat','name'=>$name,'value'=>$value]);
    }
}

// Reset
if (!empty($_POST['reset'])) {
    $DB->delete('glpi_configs', ['context'=>'ticketsat','name'=>'banner_color1']);
    $DB->delete('glpi_configs', ['context'=>'ticketsat','name'=>'banner_color2']);
    $DB->delete('glpi_configs', ['context'=>'ticketsat','name'=>'banner_angle']);
    echo json_encode(['success'=>true]);
    exit;
}

$c1 = trim($_POST['color1'] ?? '');
$c2 = trim($_POST['color2'] ?? '');

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $c1) || !preg_match('/^#[0-9a-fA-F]{6}$/', $c2)) {
    echo json_encode(['success'=>false,'message'=>'Colores inválidos']);
    exit;
}

ts_save_cfg('banner_color1', $c1);
ts_save_cfg('banner_color2', $c2);
$angle = (int)($_POST['angle'] ?? 135);
if ($angle < 0) $angle = 0;
if ($angle > 360) $angle = 360;
ts_save_cfg_raw('banner_angle', (string)$angle);
echo json_encode(['success'=>true]);
