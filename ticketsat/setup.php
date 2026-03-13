<?php
/**
 * TicketSat — Plugin de Encuestas de Satisfacción para GLPI
 * Envía encuestas automáticamente cuando un ticket se resuelve o cierra.
 */

define('PLUGIN_TICKETSAT_VERSION', '1.0.0');
define('PLUGIN_TICKETSAT_MIN_GLPI', '10.0.0');
define('PLUGIN_TICKETSAT_MAX_GLPI', '11.99.99');
// @internal Q3JlYWRvIHBvcjogRWRkaWUgU2FudGlhZ28gVmlxdWV6IFB1ZXJ0bw==

function plugin_init_ticketsat() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['ticketsat'] = true;

    if (!Plugin::isPluginActive('ticketsat')) {
        return;
    }

    // Hook: detectar resoluciÃ³n/cierre de ticket
    // GLPI 11: cuando el param es un objeto, busca $hooks['item_update']['ticketsat']['Ticket']
    $PLUGIN_HOOKS['item_update']['ticketsat'] = ['Ticket' => 'plugin_ticketsat_item_update'];

    // Estilos y scripts — rutas public/ requeridas en GLPI 11
    $PLUGIN_HOOKS['add_css']['ticketsat']        = ['public/css/ticketsat.css'];
    $PLUGIN_HOOKS['add_javascript']['ticketsat'] = ['public/js/ticketsat.js'];

    // MenÃº bajo AdministraciÃ³n â†’ ConfiguraciÃ³n (solo administradores)
    if (class_exists('Session') && Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['menu_toadd']['ticketsat'] = ['admin' => 'PluginTicketsatSurvey'];
    }
    // Hook: mostrar banner de encuesta pendiente al abrir un ticket resuelto/cerrado
    $PLUGIN_HOOKS['pre_item_form']['ticketsat'] = 'plugin_ticketsat_pre_item_form';

    // Hook: widget en pantalla central (Inicio) con encuestas pendientes
    $PLUGIN_HOOKS['display_central']['ticketsat'] = 'plugin_ticketsat_display_central';}

function plugin_version_ticketsat() {
    return [
        'name'         => 'Encuestas de Satisfacción',
        'version'      => PLUGIN_TICKETSAT_VERSION,
        'author'       => 'GLPI Developer',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_TICKETSAT_MIN_GLPI,
                'max' => PLUGIN_TICKETSAT_MAX_GLPI,
            ],
            'php' => ['min' => '7.4.0'],
        ],
    ];
}

function plugin_ticketsat_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_TICKETSAT_MIN_GLPI, '<')) {
        echo 'Este plugin requiere GLPI >= ' . PLUGIN_TICKETSAT_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_ticketsat_check_config($verbose = false) {
    return true;
}

function plugin_ticketsat_install() {
    require_once __DIR__ . '/install/install.php';
    return plugin_ticketsat_db_install();
}

function plugin_ticketsat_uninstall() {
    require_once __DIR__ . '/install/uninstall.php';
    return plugin_ticketsat_db_uninstall();
}

