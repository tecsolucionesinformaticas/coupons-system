<?php
require_once CS_PLUGIN_PATH . 'includes/comercios_handles.php';
require_once CS_PLUGIN_PATH . 'includes/propuestas_handles.php';

add_action('admin_init', 'cs_handle_admin_form_submits');

// Router de páginas
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

        // Podés agregar más casos para otras secciones en el futuro:
        // case 'cs_cupones':
        //     cs_handle_cupones_actions($action);
        //     break;
    }
}

// Router página comercios
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

// Router página propuestas
function cs_handle_propuestas_actions($action) {
    switch ($action) {
        case 'create':
            cs_propuestas_handle_create();
            break;

        case 'delete':
            cs_propuestas_handle_delete();
            break;
			
		case 'approve':
			cs_propuestas_handle_approve();
			break;
			
		case 'list':
            // Detectar si el formulario de bulk se envió por POST
            if (
                $_SERVER['REQUEST_METHOD'] === 'POST' &&
                isset($_POST['cs_mass_action_nonce']) &&
                wp_verify_nonce($_POST['cs_mass_action_nonce'], 'cs_mass_action') &&
                current_user_can('manage_options') // o tu permiso adecuado
            ) {
                cs_propuestas_mass_action_handler();
            }
            break;
    }
}

add_action('admin_post_cs_propuestas_mass_action', 'cs_propuestas_mass_action_handler');