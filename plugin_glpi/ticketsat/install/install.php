<?php
/**
 * TicketSat — Instalación de tablas en la base de datos
 */
function plugin_ticketsat_db_install() {
    global $DB;

    $queries = [

        // Encuestas
        "CREATE TABLE IF NOT EXISTS `glpi_plugin_ticketsat_surveys` (
          `id`               int(11)      NOT NULL AUTO_INCREMENT,
          `name`             varchar(255) NOT NULL DEFAULT '',
          `description`      text,
          `active`           tinyint(1)   NOT NULL DEFAULT 1,
          `trigger_on_solve` tinyint(1)   NOT NULL DEFAULT 1,
          `trigger_on_close` tinyint(1)   NOT NULL DEFAULT 0,
          `expiry_days`      int(11)      NOT NULL DEFAULT 7,
          `header_color`     varchar(20)  NOT NULL DEFAULT '#4472C4',
          `logo_url`         varchar(500) DEFAULT NULL,
          `welcome_message`  text,
          `thank_you_msg`    text,
          `button_label`     varchar(100) NOT NULL DEFAULT 'Enviar respuesta',
          `show_progress`    tinyint(1)   NOT NULL DEFAULT 1,
          `anonymous_mode`   tinyint(1)   NOT NULL DEFAULT 0,
          `date_creation`    timestamp    NULL DEFAULT NULL,
          `date_mod`         timestamp    NULL DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Preguntas
        "CREATE TABLE IF NOT EXISTS `glpi_plugin_ticketsat_questions` (
          `id`                            int(11)      NOT NULL AUTO_INCREMENT,
          `plugin_ticketsat_surveys_id`   int(11)      NOT NULL DEFAULT 0,
          `question`                      text         NOT NULL,
          `type`                          varchar(50)  NOT NULL DEFAULT 'scale',
          `required`                      tinyint(1)   NOT NULL DEFAULT 0,
          `rank`                          int(11)      NOT NULL DEFAULT 0,
          `date_creation`                 timestamp    NULL DEFAULT NULL,
          `date_mod`                      timestamp    NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `plugin_ticketsat_surveys_id` (`plugin_ticketsat_surveys_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Opciones de respuesta (para tipo múltiple)
        "CREATE TABLE IF NOT EXISTS `glpi_plugin_ticketsat_options` (
          `id`                              int(11)      NOT NULL AUTO_INCREMENT,
          `plugin_ticketsat_questions_id`   int(11)      NOT NULL DEFAULT 0,
          `label`                           varchar(255) NOT NULL DEFAULT '',
          `rank`                            int(11)      NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `plugin_ticketsat_questions_id` (`plugin_ticketsat_questions_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Asignaciones (encuesta → categoría o entidad)
        "CREATE TABLE IF NOT EXISTS `glpi_plugin_ticketsat_assignments` (
          `id`                            int(11)      NOT NULL AUTO_INCREMENT,
          `plugin_ticketsat_surveys_id`   int(11)      NOT NULL DEFAULT 0,
          `itemtype`                      varchar(100) DEFAULT NULL,
          `items_id`                      int(11)      NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `plugin_ticketsat_surveys_id` (`plugin_ticketsat_surveys_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Solicitudes de respuesta (una por ticket+usuario)
        "CREATE TABLE IF NOT EXISTS `glpi_plugin_ticketsat_responses` (
          `id`                            int(11)      NOT NULL AUTO_INCREMENT,
          `plugin_ticketsat_surveys_id`   int(11)      NOT NULL DEFAULT 0,
          `tickets_id`                    int(11)      NOT NULL DEFAULT 0,
          `users_id`                      int(11)      NOT NULL DEFAULT 0,
          `token`                         varchar(64)  NOT NULL DEFAULT '',
          `completed`                     tinyint(1)   NOT NULL DEFAULT 0,
          `date_send`                     timestamp    NULL DEFAULT NULL,
          `date_answered`                 timestamp    NULL DEFAULT NULL,
          `date_creation`                 timestamp    NULL DEFAULT NULL,
          `date_mod`                      timestamp    NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `token` (`token`),
          KEY `tickets_id` (`tickets_id`),
          KEY `plugin_ticketsat_surveys_id` (`plugin_ticketsat_surveys_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Respuestas individuales por pregunta
        "CREATE TABLE IF NOT EXISTS `glpi_plugin_ticketsat_answers` (
          `id`                               int(11) NOT NULL AUTO_INCREMENT,
          `plugin_ticketsat_responses_id`    int(11) NOT NULL DEFAULT 0,
          `plugin_ticketsat_questions_id`    int(11) NOT NULL DEFAULT 0,
          `answer_text`                      text,
          `answer_value`                     int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `plugin_ticketsat_responses_id` (`plugin_ticketsat_responses_id`),
          KEY `plugin_ticketsat_questions_id` (`plugin_ticketsat_questions_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    ];

    foreach ($queries as $sql) {
        $DB->doQuery($sql);
    }

    return true;
}
