<?php
function cs_propuestas_handle_create() {
    if (
        !isset($_POST['cs_add_propuesta_nonce']) ||
        !wp_verify_nonce($_POST['cs_add_propuesta_nonce'], 'cs_add_propuesta')
    ) {
        wp_die('Nonce inválido.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'coupon_proposals';

	$user = wp_get_current_user();
    $is_comercio = in_array('comercio', $user->roles);

    // Para comercio, ignorar $_POST['comercio_id'] y tomar el ID del usuario actual
    if ($is_comercio) {
        $comercio_id = $user->ID;
    } else {
        // Para admin o roles con permiso, tomar el comercio_id enviado en el formulario
        $comercio_id = intval($_POST['comercio_id']);
    }

    // Sanitización y recolección de campos
    $nombre             = sanitize_text_field($_POST['nombre']);
    $descripcion        = sanitize_textarea_field($_POST['descripcion']);
    $tipo_cupon         = $_POST['tipo_cupon'];
    $unidad_descripcion = sanitize_text_field($_POST['unidad_descripcion'] ?? '');
    $valor              = floatval($_POST['valor']);
    $uso_parcial        = isset($_POST['uso_parcial']) ? 1 : 0;
    $fecha_inicio       = sanitize_text_field($_POST['fecha_inicio']);
    $duracion_validez   = intval($_POST['duracion_validez']);
    $unidad_validez     = sanitize_text_field($_POST['unidad_validez']);
    $cantidad_ciclos    = intval($_POST['cantidad_ciclos']);
    $frecuencia_emision = intval($_POST['frecuencia_emision']);
    $unidad_frecuencia  = sanitize_text_field($_POST['unidad_frecuencia']);
    $cupones_por_ciclo  = intval($_POST['cupones_por_ciclo']);

    // Validación básica
    $errores = [];

    if (empty($nombre)) {
        $errores[] = 'El nombre de la propuesta es obligatorio.';
    }

    if (empty($fecha_inicio)) {
        $errores[] = 'La fecha de inicio es obligatoria.';
    }

    if ($valor <= 0) {
        $errores[] = 'El valor debe ser mayor a 0.';
    }

    if ($tipo_cupon === 'unidad' && empty($unidad_descripcion)) {
        $errores[] = 'Debe especificar la descripción del producto o servicio.';
    }

    // Validar unicidad: el nombre debe ser único por comercio
    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE comercio_id = %d AND nombre = %s",
        $comercio_id,
        $nombre
    ));
    if ($existe > 0) {
        $errores[] = 'Ya existe una propuesta con ese nombre para este comercio.';
    }

    if (!empty($errores)) {
        // Guardamos los valores en la sesión temporalmente
        $_SESSION['cs_errores'] = $errores;
        $_SESSION['cs_old_input'] = $_POST;

        wp_redirect(admin_url('admin.php?page=cs_propuestas&action=add&error=1'));
        exit;
    }
	
	$creado_por = get_current_user_id();

    // Insertar
    $wpdb->insert($table, [
        'nombre'             => $nombre,
        'descripcion'        => $descripcion,
        'comercio_id'        => $comercio_id,
        'tipo_cupon'         => $tipo_cupon,
        'unidad_descripcion' => $unidad_descripcion,
        'valor'              => $valor,
        'uso_parcial'        => $uso_parcial,
        'fecha_inicio'       => $fecha_inicio,
        'duracion_validez'   => $duracion_validez,
        'unidad_validez'     => $unidad_validez,
        'cantidad_ciclos'    => $cantidad_ciclos,
        'frecuencia_emision' => $frecuencia_emision,
        'unidad_frecuencia'  => $unidad_frecuencia,
        'cupones_por_ciclo'  => $cupones_por_ciclo,
        'estado'             => 'pendiente',
        'created_at'         => current_time('mysql'),
		'creado_por' => $creado_por
    ]);

    // Limpieza de errores previos
    unset($_SESSION['cs_errores'], $_SESSION['cs_old_input']);

    wp_redirect(admin_url('admin.php?page=cs_propuestas&created=1'));
    exit;
}

function cs_propuestas_handle_delete($id = null) {
    if (!current_user_can('manage_options') && !defined('CS_MASS_OPERATION')) {
        wp_die('No tenés permisos suficientes para realizar esta acción.');
    }

    global $wpdb;

    if ($id === null) {
        $id = intval($_GET['id'] ?? 0);

        if (!defined('CS_MASS_OPERATION')) {
            $nonce = $_GET['_wpnonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'cs_delete_propuesta_' . $id)) {
                wp_die('Nonce inválido.');
            }
        }
    }

    if (!$id) {
        if (!defined('CS_MASS_OPERATION')) {
            wp_die('ID inválido.');
        } else {
            throw new Exception('ID inválido.');
        }
    }

    $tabla = $wpdb->prefix . 'coupon_proposals';
    $propuesta = $wpdb->get_row("SELECT * FROM $tabla WHERE id = $id");

    if (!$propuesta) {
        if (!defined('CS_MASS_OPERATION')) {
            wp_die('La propuesta no existe o ya fue eliminada.');
        } else {
            throw new Exception('La propuesta no existe o ya fue eliminada.');
        }
    }

    $current_user_id = get_current_user_id();

    if (current_user_can('manage_options')) {
        if ($propuesta->estado !== 'pendiente') {
            if (!defined('CS_MASS_OPERATION')) {
                wp_die('No se puede eliminar una propuesta aprobada.');
            } else {
                throw new Exception('No se puede eliminar una propuesta aprobada.');
            }
        }
    } else {
        $esCreador = $propuesta->creado_por == $current_user_id;
        $esPendiente = $propuesta->estado === 'pendiente';
        $creadoPorAdmin = user_can($propuesta->creado_por, 'manage_options');

        if (!$esPendiente || !$esCreador || $creadoPorAdmin) {
            if (!defined('CS_MASS_OPERATION')) {
                wp_die('No tiene permiso para eliminar esta propuesta.');
            } else {
                throw new Exception('No tiene permiso para eliminar esta propuesta.');
            }
        }
    }

    $result = $wpdb->delete($tabla, ['id' => $id]);

    if ($result === false) {
        error_log("Error al eliminar propuesta ID {$id}: {$wpdb->last_error}");
        if (!defined('CS_MASS_OPERATION')) {
            wp_die('Error al eliminar la propuesta.');
        } else {
            throw new Exception('Error al eliminar la propuesta.');
        }
    }

    if (!defined('CS_MASS_OPERATION')) {
        $redirect_url = admin_url('admin.php?page=cs_propuestas&deleted=1');
        $redirect_url = cs_add_current_filters_to_url($redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
}



function cs_propuestas_handle_approve($id = null) {
    global $wpdb;

    if ($id === null) {
        $id = intval($_GET['id'] ?? 0);

        if (!defined('CS_MASS_OPERATION')) {
            $nonce = $_GET['_wpnonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'cs_approve_propuesta_' . $id)) {
                wp_die('Nonce inválido.');
            }
        }
    }

    if (!$id) {
        if (!defined('CS_MASS_OPERATION')) {
            wp_die('ID inválido.');
        } else {
            throw new Exception('ID inválido.');
        }
    }

    $tabla = $wpdb->prefix . 'coupon_proposals';
    $propuesta = $wpdb->get_row("SELECT * FROM $tabla WHERE id = $id");

    if (!$propuesta) {
        if (!defined('CS_MASS_OPERATION')) {
            wp_die('La propuesta no existe o ya fue eliminada.');
        } else {
            throw new Exception('La propuesta no existe o ya fue eliminada.');
        }
    }

    $current_user_id = get_current_user_id();

    // No se puede aprobar si ya fue procesada
    if ($propuesta->estado !== 'pendiente') {
        if (!defined('CS_MASS_OPERATION')) {
            wp_die('La propuesta ya fue procesada.');
        } else {
            throw new Exception('La propuesta ya fue procesada.');
        }
    }

    // Validación de permisos
    if (current_user_can('manage_options')) {
        if ($propuesta->creado_por == $current_user_id) {
            if (!defined('CS_MASS_OPERATION')) {
                wp_die('No puede aprobar una propuesta que usted mismo creó.');
            } else {
                throw new Exception('No puede aprobar una propuesta que usted mismo creó.');
            }
        }
    } else {
        if ($propuesta->comercio_id != $current_user_id) {
            if (!defined('CS_MASS_OPERATION')) {
                wp_die('No tiene permiso para aprobar esta propuesta.');
            } else {
                throw new Exception('No tiene permiso para aprobar esta propuesta.');
            }
        }

        if ($propuesta->creado_por == $current_user_id) {
            if (!defined('CS_MASS_OPERATION')) {
                wp_die('No puede aprobar una propuesta que usted mismo creó.');
            } else {
                throw new Exception('No puede aprobar una propuesta que usted mismo creó.');
            }
        }
    }

    $result = $wpdb->update($tabla, ['estado' => 'aprobado'], ['id' => $id]);

    if ($result === false) {
        error_log("Error al aprobar propuesta ID {$id}: {$wpdb->last_error}");
        if (!defined('CS_MASS_OPERATION')) {
            wp_die('Error al actualizar la propuesta.');
        } else {
            throw new Exception('Error al actualizar la propuesta.');
        }
    }

    if (!defined('CS_MASS_OPERATION')) {
        $redirect_url = admin_url('admin.php?page=cs_propuestas&approved=1');
        $redirect_url = cs_add_current_filters_to_url($redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
}


// Handler mejorado para acciones masivas
function cs_propuestas_mass_action_handler() {   
    // Verificar nonce
    $nonce = $_POST['cs_mass_action_nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'cs_mass_action')) {
        wp_die('Acceso no autorizado. Token de seguridad inválido.');
    }
    
    // Obtener IDs seleccionados
    $ids = array_map('intval', (array) ($_POST['cs_ids'] ?? []));
    if (empty($ids)) {
        $redirect_url = admin_url('admin.php?page=cs_propuestas&error=no_ids');
        $redirect_url = cs_add_current_filters_to_url($redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
    
    // Obtener la acción (puede venir de action o action2)
    $action = sanitize_text_field($_POST['action'] ?? '');
    if ($action === '-1' || empty($action)) {
        $action = sanitize_text_field($_POST['action2'] ?? '');
    }
    
    if (!in_array($action, ['approve', 'delete'])) {
        $redirect_url = admin_url('admin.php?page=cs_propuestas&error=invalid_action');
        $redirect_url = cs_add_current_filters_to_url($redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
    
    // Contadores y errores
    $counts = ['approved' => 0, 'deleted' => 0];
    $errors = [];
    
    // Definir que estamos en operación masiva
    define('CS_MASS_OPERATION', true);
    
    foreach ($ids as $id) {
        try {
            switch ($action) {
                case 'approve':
                    cs_propuestas_handle_approve($id);
                    $counts['approved']++;
                    break;
                    
                case 'delete':
                    cs_propuestas_handle_delete($id);
                    $counts['deleted']++;
                    break;
            }
        } catch (Exception $e) {
            $errors[] = "ID {$id}: " . $e->getMessage();
        }
    }
    
    // Construir URL de redirección con resultados y filtros conservados
    $redirect_url = admin_url('admin.php?page=cs_propuestas');
    
    // Agregar contadores de éxito
    if ($counts['approved'] > 0) {
        $redirect_url = add_query_arg('mass_approved', $counts['approved'], $redirect_url);
    }
    if ($counts['deleted'] > 0) {
        $redirect_url = add_query_arg('mass_deleted', $counts['deleted'], $redirect_url);
    }
    
    // Agregar errores si los hay
    if (!empty($errors)) {
        $redirect_url = add_query_arg('mass_errors', urlencode(implode('; ', $errors)), $redirect_url);
    }
    
    // Conservar filtros actuales
    $redirect_url = cs_add_current_filters_to_url($redirect_url);
    
    wp_redirect($redirect_url);
    exit;
}

// Función helper para conservar filtros en redirecciones
function cs_add_current_filters_to_url($url) {
    $filters_to_preserve = ['filtro_estado', 'filtro_tipo', 'filtro_comercio', 's', 'paged'];
    
    foreach ($filters_to_preserve as $filter) {
        if (!empty($_GET[$filter]) || !empty($_POST[$filter])) {
            $value = sanitize_text_field($_GET[$filter] ?? $_POST[$filter] ?? '');
            if (!empty($value)) {
                $url = add_query_arg($filter, $value, $url);
            }
        }
    }
    
    return $url;
}