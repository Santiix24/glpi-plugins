<?php
/**
 * TicketSat — Menu lateral de GLPI
 */
class PluginTicketsatMenu extends CommonGLPI {

    static $rightname = 'config';

    static function getMenuName() {
        return 'TicketSat';
    }

    static function getMenuContent() {
        $menu = [
            'title' => 'TicketSat',
            'page'  => Plugin::getWebDir('ticketsat', true) . '/front/survey.php',
            'icon'  => 'ti ti-star',
        ];

        $menu['options'] = [
            'survey' => [
                'title' => 'Encuestas',
                'page'  => Plugin::getWebDir('ticketsat', true) . '/front/survey.php',
                'links' => [
                    'add'    => Plugin::getWebDir('ticketsat', true) . '/front/survey.form.php',
                    'search' => Plugin::getWebDir('ticketsat', true) . '/front/survey.php',
                ],
                'icon' => 'ti ti-clipboard-list',
            ],
            'dashboard' => [
                'title' => 'Dashboard',
                'page'  => Plugin::getWebDir('ticketsat', true) . '/front/dashboard.php',
                'links' => [
                    'search' => Plugin::getWebDir('ticketsat', true) . '/front/dashboard.php',
                ],
                'icon' => 'ti ti-chart-bar',
            ],
            'response' => [
                'title' => 'Respuestas',
                'page'  => Plugin::getWebDir('ticketsat', true) . '/front/response.php',
                'links' => [
                    'search' => Plugin::getWebDir('ticketsat', true) . '/front/response.php',
                ],
                'icon' => 'ti ti-message-circle',
            ],
        ];

        return $menu;
    }

    static function getMenuFavicon() {
        return 'ti ti-star';
    }
}
