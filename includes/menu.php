<?php
require_once CS_PLUGIN_PATH . 'includes/utils.php';
require_once CS_PLUGIN_PATH . 'includes/comercios_pages.php';
require_once CS_PLUGIN_PATH . 'includes/propuestas_pages.php';

function cs_register_admin_menu() {
    add_menu_page(
        'Cupones',
        'Cupones',
        'manage_options',
        'cs_dashboard',
        'cs_dashboard_page',
        'dashicons-tickets-alt',
        25
    );

    add_submenu_page(
        'cs_dashboard',
        'Comercios',
        'Comercios',
        'manage_options',
        'cs_comercios',
        'cs_comercios_page'
    );
	
	add_submenu_page(
		'cs_dashboard',
		'Propuestas',
		'Propuestas',
		'manage_options',
		'cs_propuestas',
		'cs_propuestas_page'
	);
}
add_action('admin_menu', 'cs_register_admin_menu');

function cs_dashboard_page() {
    echo '<div class="wrap"><h1>Dashboard de Cupones</h1><p>(Contenido próximamente)</p></div>';
}
