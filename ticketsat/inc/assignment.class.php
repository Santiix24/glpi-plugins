<?php
/** TicketSat — Asignación de encuesta a categoría ITIL o entidad */
class PluginTicketsatAssignment extends CommonDBTM {
    static $rightname = 'config';
    static function getTable($classname = null) { return 'glpi_plugin_ticketsat_assignments'; }
}
