<?php
require_once CS_PLUGIN_PATH . 'includes/utils.php';
require_once CS_PLUGIN_PATH . 'includes/comercios_pages.php';
require_once CS_PLUGIN_PATH . 'includes/propuestas_pages.php';

function cs_register_admin_menu() {
    // Mostrar menú solo si el usuario tiene acceso al plugin
    if ( current_user_can('access_cs_plugin') ) {
        add_menu_page(
            'Cupones',
            'Cupones',
            'access_cs_plugin',
            'cs_dashboard',
            'cs_dashboard_page',
            'dashicons-tickets-alt',
            25
        );

        // Submenú: Propuestas (para admin y comercio)
        add_submenu_page(
            'cs_dashboard',
            'Listado de Propuestas',
            'Propuestas',
            'view_propuestas',
            'cs_propuestas',
            'cs_propuestas_page'
        );

        // Submenú: Comercios (solo admin)
        if ( current_user_can('manage_options') ) {
            add_submenu_page(
                'cs_dashboard',
                'Comercios',
                'Comercios',
                'manage_options',
                'cs_comercios',
                'cs_comercios_page'
            );
        }
    }
}
add_action('admin_menu', 'cs_register_admin_menu');

function cs_dashboard_page() {
    echo '<div class="wrap"><h1>Dashboard de Cupones</h1><p>(Contenido próximamente)</p></div>';
}
