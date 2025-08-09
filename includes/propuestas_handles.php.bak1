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

    // Sanitización y recolección de campos
    $nombre             = sanitize_text_field($_POST['nombre']);
    $descripcion        = sanitize_textarea_field($_POST['descripcion']);
    $comercio_id        = intval($_POST['comercio_id']);
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

        wp_redirect(admin_url('admin.php?page=cs_propuestas&error=1'));
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
	if ( ! current_user_can('manage_options') ) {
		if (!defined('CS_MASS_OPERATION')) {
			wp_die('No tenés permisos suficientes para realizar esta acción.');
		} else {
			throw new Exception('Permiso denegado.');
		}
	}

	global $wpdb;

	if ($id === null) {
		$id = intval($_GET['id'] ?? 0);
	}
	
	if (! $id) {
		if (!defined('CS_MASS_OPERATION')) {
			wp_die('ID inválido.');
		} else {
			throw new Exception('ID inválido.');
		}
	}

	$tabla = $wpdb->prefix . 'coupon_proposals';
	$propuesta = $wpdb->get_row("SELECT * FROM $tabla WHERE id = $id");

	if (! $propuesta) {
		if (!defined('CS_MASS_OPERATION')) {
			wp_die('La propuesta no existe o ya fue eliminada.');
		} else {
			throw new Exception('La propuesta no existe o ya fue eliminada.');
		}
	}

	// Borrar
	$wpdb->delete($tabla, ['id' => $id]);

	// Redirigir solo si es individual
	if (!defined('CS_MASS_OPERATION')) {
		wp_redirect(admin_url('admin.php?page=cs_propuestas&deleted=1'));
		exit;
	}
}

function cs_propuestas_handle_approve($id = null) {
	global $wpdb;

	if ($id === null) {
		$id = intval($_GET['id'] ?? 0);
	}

	if (!$id) {
		if (!defined('CS_MASS_OPERATION')) {
			wp_redirect(admin_url('admin.php?page=cs_propuestas&error=invalid_id'));
			exit;
		} else {
			throw new Exception('ID inválido.');
		}
	}

	$tabla = $wpdb->prefix . 'coupon_proposals';
	$propuesta = $wpdb->get_row("SELECT * FROM $tabla WHERE id = $id");

	if (!$propuesta) {
		if (!defined('CS_MASS_OPERATION')) {
			wp_redirect(admin_url('admin.php?page=cs_propuestas&error=not_found'));
			exit;
		} else {
			throw new Exception('La propuesta no existe.');
		}
	}

	if ($propuesta->estado !== 'pendiente') {
		if (!defined('CS_MASS_OPERATION')) {
			wp_redirect(admin_url('admin.php?page=cs_propuestas&error=already_processed'));
			exit;
		} else {
			throw new Exception('La propuesta ya fue procesada.');
		}
	}
	
	// Validar si el usuario actual tiene permiso para aprobar
	if (!cs_usuario_debe_aprobar_propuesta($propuesta)) {
		if (!defined('CS_MASS_OPERATION')) {
			wp_redirect(admin_url('admin.php?page=cs_propuestas&error=no_permission'));
			exit;
		} else {
			throw new Exception('No tiene permiso para aprobar esta propuesta.');
		}
	}

	// Aprobar
	$result = $wpdb->update($tabla, ['estado' => 'aprobado'], ['id' => $id]);
	
	if ($result === false) {
		// Loguear error
		error_log('Error al actualizar propuesta ID ' . $id . ': ' . $wpdb->last_error);
		if (!defined('CS_MASS_OPERATION')) {
			wp_redirect(admin_url('admin.php?page=cs_propuestas&error=db_update_failed'));
			exit;
		} else {
			throw new Exception('Error al actualizar la propuesta en la base de datos.');
		}
	}

	// Redirigir solo si es individual
	if (!defined('CS_MASS_OPERATION')) {
		wp_redirect(admin_url('admin.php?page=cs_propuestas&approved=1'));
		exit;
	}
}

// Enganchamos admin.php?action=cs_propuestas_mass_action
add_action( 'admin_action_cs_propuestas_mass_action', 'cs_propuestas_mass_action_handler' );

function cs_propuestas_mass_action_handler() {
	error_log(print_r($_GET, true));
	
    // 1) Validar nonce GET
	$nonce = $_POST['cs_mass_action_nonce'] ?? '';
	if ( ! wp_verify_nonce($nonce, 'cs_mass_action') ) {
		wp_die('Acceso no autorizado');
	}

    // 2) IDs seleccionados
    $ids = array_map( 'intval', (array) ($_POST['cs_ids'] ?? []) );
    if ( empty($ids) ) {
        wp_redirect( admin_url('admin.php?page=cs_propuestas&error=no_ids') );
        exit;
    }

    // 3) La acción viene en action=approve|delete
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    $counts = [ 'approved' => 0, 'deleted' => 0 ];
    $errors = [];

    define('CS_MASS_OPERATION', true); // para que los handlers individuales no redirijan

	

    foreach ( $ids as $id ) {
        try {
            switch ( $action ) {
                case 'approve':
                    cs_propuestas_handle_approve( $id );
                    $counts['approved']++;
                    break;

                case 'delete':
                    cs_propuestas_handle_delete( $id );
                    $counts['deleted']++;
                    break;

                default:
                    throw new Exception("Acción inválida: $action");
            }
        } catch ( Throwable $e ) {
            $errors[] = "ID {$id}: " . $e->getMessage();
        }
    }

    // 4) Redirigir con conteos y errores
    $url = admin_url('admin.php?page=cs_propuestas');
    if ( $counts['approved'] ) $url = add_query_arg('mass_approved', $counts['approved'], $url);
    if ( $counts['deleted'] )  $url = add_query_arg('mass_deleted',  $counts['deleted'],  $url);
    if ( $errors )             $url = add_query_arg('mass_errors', urlencode( implode('; ', $errors) ), $url);

    wp_redirect( $url );
    exit;
}


