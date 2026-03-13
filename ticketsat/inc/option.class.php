<?php
/** TicketSat — Modelo de Opción de respuesta (para preguntas multiple/checkbox) */
class PluginTicketsatOption extends CommonDBTM {
    static $rightname = 'config';
    static function getTable($classname = null) { return 'glpi_plugin_ticketsat_options'; }
}
