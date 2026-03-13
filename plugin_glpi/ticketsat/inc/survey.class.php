<?php
/**
 * TicketSat — Modelo de Encuesta
 */
class PluginTicketsatSurvey extends CommonDBTM {

    static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return $nb > 1 ? 'Encuestas de Satisfacción' : 'Encuesta de Satisfacción';
    }

    static function getMenuName() {
        return 'Encuestas de Satisfacción';
    }

    static function getMenuContent() {
        $base = Plugin::getWebDir('ticketsat', false);
        $menu = [];
        $menu['title'] = 'Encuestas de Satisfacción';
        $menu['page']  = $base . '/front/survey.php';
        $menu['icon']  = 'ti ti-star';
        $menu['links'] = [
            'add'    => $base . '/front/survey.form.php',
            'search' => $base . '/front/survey.php',
            '<i class="ti ti-chart-bar"></i> Dashboard'   => $base . '/front/dashboard.php',
            '<i class="ti ti-message-circle"></i> Respuestas' => $base . '/front/response.php',
        ];
        return $menu;
    }

    static function getTable($classname = null) {
        return 'glpi_plugin_ticketsat_surveys';
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        return self::getTypeName(2);
    }

    /**
     * Devuelve las preguntas de esta encuesta ordenadas por rango.
     */
    function getQuestions() {
        global $DB;
        $questions = [];
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_ticketsat_questions',
            'WHERE' => ['plugin_ticketsat_surveys_id' => $this->getID()],
            'ORDER' => 'rank ASC, id ASC',
        ]);
        foreach ($iterator as $row) {
            // Cargar opciones para preguntas que las usan
            $row['options'] = [];
            if (in_array($row['type'], PluginTicketsatQuestion::getOptionTypes())) {
                $row['options'] = $this->getQuestionOptions((int) $row['id']);
            }
            $questions[] = $row;
        }
        return $questions;
    }

    public function getQuestionOptions(int $questionId) {
        global $DB;
        $opts = [];
        $it = $DB->request([
            'FROM'  => 'glpi_plugin_ticketsat_options',
            'WHERE' => ['plugin_ticketsat_questions_id' => $questionId],
            'ORDER' => 'rank ASC',
        ]);
        foreach ($it as $row) {
            $opts[] = $row;
        }
        return $opts;
    }

    /**
     * Devuelve estadísticas de respuestas para esta encuesta.
     */
    function getStats() {
        global $DB;

        $total = countElementsInTable('glpi_plugin_ticketsat_responses', [
            'plugin_ticketsat_surveys_id' => $this->getID(),
        ]);
        $completed = countElementsInTable('glpi_plugin_ticketsat_responses', [
            'plugin_ticketsat_surveys_id' => $this->getID(),
            'completed' => 1,
        ]);

        return [
            'total'     => (int) $total,
            'completed' => (int) $completed,
            'pending'   => (int) $total - (int) $completed,
            'rate'      => $total > 0 ? round($completed / $total * 100, 1) : 0,
        ];
    }
}
