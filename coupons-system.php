<?php
/**
 * Plugin Name: Sistema de Cupones
 * Description: Plugin personalizado para gestión de tickets/cupones con roles específicos.
 * Version: 0.1
 * Author: TEC Soluciones Informáticas
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Evita el acceso directo

// Definimos constantes básicas
define( 'CS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once CS_PLUGIN_PATH . 'includes/routes.php';
require_once CS_PLUGIN_PATH . 'includes/menu.php';
require_once CS_PLUGIN_PATH . 'includes/roles.php';
require_once CS_PLUGIN_PATH . 'includes/database.php';

// Activación
register_activation_hook(__FILE__, 'cs_activate_plugin');

// Actualizar función de activación
function cs_activate_plugin() {
    cs_register_roles();
	cs_tickets_create_tables();
    cs_schedule_coupon_emission(); // Programar cron job
    
    // Flush rewrite rules si es necesario
    flush_rewrite_rules();
}

add_action('init', function () {
    if (!session_id()) {
        session_start();
    }
});