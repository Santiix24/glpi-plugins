<?php
/** TicketSat — Solicitud de respuesta a encuesta (una por ticket+usuario) */
class PluginTicketsatResponse extends CommonDBTM {
    static $rightname = 'config';
    static function getTable($classname = null) { return 'glpi_plugin_ticketsat_responses'; }

    /** Busca un response activo por token. Retorna array o false. */
    static function getByToken(string $token) {
        global $DB;
        $it = $DB->request([
            'FROM'  => 'glpi_plugin_ticketsat_responses',
            'WHERE' => ['token' => $token],
            'LIMIT' => 1,
        ]);
        foreach ($it as $row) {
            return $row;
        }
        return false;
    }

    /** Respuestas pendientes del usuario actual */
    static function getPendingForUser(int $userId): array {
        global $DB;
        $out = [];
        $it = $DB->request([
            'FROM'  => 'glpi_plugin_ticketsat_responses',
            'WHERE' => [
                'users_id'  => $userId,
                'completed' => 0,
            ],
            'ORDER' => 'date_send DESC',
        ]);
        foreach ($it as $row) {
            $out[] = $row;
        }
        return $out;
    }
}
