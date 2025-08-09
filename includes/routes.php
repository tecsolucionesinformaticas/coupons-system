<?php
require_once CS_PLUGIN_PATH . 'includes/comercios_handles.php';
require_once CS_PLUGIN_PATH . 'includes/propuestas_handles.php';

add_action('admin_init', 'cs_handle_admin_form_submits');

// Router principal
function cs_handle_admin_form_submits() {
    $page = $_GET['page'] ?? '';
    $action = $_GET['action'] ?? '';

    switch ($page) {
        case 'cs_comercios':
            cs_handle_comercios_actions($action);
            break;

        case 'cs_propuestas':
            cs_handle_propuestas_actions($action);
            break;
    }
}

// Router página comercios (sin cambios)
function cs_handle_comercios_actions($action) {
    switch ($action) {
        case 'create':
            cs_comercios_handle_create();
            break;

        case 'update':
            cs_comercios_handle_update();
            break;

        case 'delete':
            cs_comercios_handle_delete();
            break;
    }
}

// Router página propuestas mejorado
function cs_handle_propuestas_actions($action) {
    switch ($action) {
        case 'create':
            // Solo manejar si es POST
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                cs_propuestas_handle_create();
            }
            break;

        case 'delete':
            // Solo manejar si es GET con nonce válido
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['_wpnonce'])) {
                cs_propuestas_handle_delete();
            }
            break;
			
		case 'approve':
            // Solo manejar si es GET con nonce válido
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['_wpnonce'])) {
                cs_propuestas_handle_approve();
            }
			break;
    }
    
    // Manejar acciones masivas POST independientemente de la acción GET
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['cs_mass_action_nonce']) &&
        wp_verify_nonce($_POST['cs_mass_action_nonce'], 'cs_mass_action') &&
        (isset($_POST['action']) || isset($_POST['action2']))
    ) {
        cs_propuestas_mass_action_handler();
    }
}