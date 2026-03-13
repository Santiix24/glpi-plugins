<?php
/**
 * Plugin Dashboard Export para GLPI
 * Permite exportar dashboards con gráficas y datos a formato Excel XLSX
 */

define('PLUGIN_DASHBOARDEXPORT_VERSION', '1.0.0');
// @internal Q3JlYWRvIHBvcjogRWRkaWUgU2FudGlhZ28gVmlxdWV6IFB1ZXJ0bw==

function plugin_init_dashboardexport() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['dashboardexport'] = true;

    // CSS y JS en TODAS las páginas para botón de exportar
    // Rutas public/ requeridas en GLPI 11
    $PLUGIN_HOOKS['add_css']['dashboardexport'] = [
        'public/css/dashboardexport.css'
    ];

    $PLUGIN_HOOKS['add_javascript']['dashboardexport'] = [
        'public/js/jszip.min.js',
        'public/js/FileSaver.min.js',
        'public/js/export-excel.js',
        'public/js/dashboard-inject.js'
    ];
}

function plugin_dashboardexport_display_central() {
    return true;
}

function plugin_version_dashboardexport() {
    return [
        'name'           => 'Dashboard Export',
        'version'        => PLUGIN_DASHBOARDEXPORT_VERSION,
        'author'         => 'GLPI Developer',
        'license'        => 'GPLv3',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0',
                'max' => '11.99.99',
            ],
            'php'  => [
                'min' => '7.4.0'
            ]
        ]
    ];
}

function plugin_dashboardexport_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '10.0.0', '<')) {
        echo "Este plugin requiere GLPI >= 10.0.0";
        return false;
    }

    if (version_compare(GLPI_VERSION, '11.99.99', '>')) {
        echo "Este plugin requiere GLPI <= 11.x";
        return false;
    }

    if (!extension_loaded('zip')) {
        echo "Este plugin requiere la extensión PHP ZIP";
        return false;
    }

    return true;
}

function plugin_dashboardexport_check_config($verbose = false) {
    return true;
}
