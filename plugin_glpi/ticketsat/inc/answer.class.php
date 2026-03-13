<?php
/** TicketSat — Respuesta individual a una pregunta */
class PluginTicketsatAnswer extends CommonDBTM {
    static $rightname = 'config';
    static function getTable($classname = null) { return 'glpi_plugin_ticketsat_answers'; }
}
