<?php
/**
 * TicketSat — Modelo de Pregunta
 * Tipos: scale (1-10), stars (1-5), multiple (opción única), checkbox (múltiple), open (texto libre)
 */
class PluginTicketsatQuestion extends CommonDBTM {

    static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return $nb > 1 ? 'Preguntas' : 'Pregunta';
    }

    static function getTable($classname = null) {
        return 'glpi_plugin_ticketsat_questions';
    }

    static function getTypeLabel(string $type): string {
        return static::getTypes()[$type] ?? $type;
    }

    static function getTypes(): array {
        return [
            'stars'     => 'Calificación con estrellas (1–5)',
            'scale'     => 'Escala numérica (1–10)',
            'yesno'     => 'Sí / No (una sola opción)',
            'multiple'  => 'Opción múltiple — una sola respuesta',
            'abcd'      => 'Opción A / B / C / D (estilo examen)',
            'checkbox'  => 'Casillas — varias respuestas',
            'dropdown'  => 'Lista desplegable',
            'open'      => 'Respuesta corta (texto)',
            'open_long' => 'Párrafo (texto largo)',
        ];
    }

    /** Tipos que almacenan opciones en la tabla glpi_plugin_ticketsat_options */
    static function getOptionTypes(): array {
        return ['multiple', 'abcd', 'checkbox', 'dropdown'];
    }
}
