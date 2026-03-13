<?php
/**
 * TicketSat — Desinstalación: renombra las tablas como respaldo en lugar de borrarlas.
 * Esto garantiza que ninguna encuesta ni respuesta se pierda accidentalmente.
 * Para borrar definitivamente, eliminar manualmente las tablas _bak_* desde la DB.
 */
function plugin_ticketsat_db_uninstall() {
    global $DB;

    $tables = [
        'glpi_plugin_ticketsat_answers',
        'glpi_plugin_ticketsat_responses',
        'glpi_plugin_ticketsat_assignments',
        'glpi_plugin_ticketsat_options',
        'glpi_plugin_ticketsat_questions',
        'glpi_plugin_ticketsat_surveys',
    ];

    $suffix = '_bak_' . date('Ymd_His');

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            // Renombrar en lugar de eliminar → los datos quedan en <tabla>_bak_YYYYMMDD_HHiiss
            $backup = $table . $suffix;
            // Si ya existe un backup anterior con ese nombre exacto (improbable), eliminarlo primero
            if ($DB->tableExists($backup)) {
                $DB->doQuery("DROP TABLE `{$backup}`");
            }
            $DB->doQuery("RENAME TABLE `{$table}` TO `{$backup}`");
        }
    }

    // Limpiar configuración propia del plugin (no contiene datos de encuestas)
    $DB->delete('glpi_configs', ['context' => 'ticketsat']);

    return true;
}
