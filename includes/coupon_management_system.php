<?php
/**
 * Sistema de Gestión de Cupones
 */

/**
 * Clase para gestión de cupones con diferentes vistas según el rol
 */
class CS_Coupon_Manager {
    
    private $wpdb;
    private $table_coupons;
    private $table_proposals;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_coupons = $wpdb->prefix . 'coupons';
        $this->table_proposals = $wpdb->prefix . 'coupon_proposals';
    }
    
    /**
     * Obtener cupones según el rol del usuario
     */
    public function get_coupons_for_user($user_id = null, $filters = []) {
        $user_id = $user_id ?: get_current_user_id();
        $user = get_userdata($user_id);
        
        if (!$user) {
            return [];
        }
        
        if (user_can($user, 'manage_options')) {
            return $this->get_admin_coupons($filters);
        } elseif (in_array('comercio', $user->roles)) {
            return $this->get_comercio_coupons($user_id, $filters);
        } else {
            return $this->get_user_coupons($user_id, $filters);
        }
    }
    
    /**
     * Cupones para administradores (todos)
     */
    private function get_admin_coupons($filters = []) {
        $where_conditions = ['1=1'];
        $params = [];
        
        // Aplicar filtros
        if (!empty($filters['estado'])) {
            $where_conditions[] = 'c.estado = %s';
            $params[] = $filters['estado'];
        }
        
        if (!empty($filters['comercio_id'])) {
            $where_conditions[] = 'c.comercio_id = %d';
            $params[] = intval($filters['comercio_id']);
        }
        
        if (!empty($filters['fecha_desde'])) {
            $where_conditions[] = 'c.fecha_inicio >= %s';
            $params[] = $filters['fecha_desde'];
        }
        
        if (!empty($filters['fecha_hasta'])) {
            $where_conditions[] = 'c.fecha_fin <= %s';
            $params[] = $filters['fecha_hasta'];
        }
        
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where_conditions[] = '(c.codigo_serie LIKE %s OR u.display_name LIKE %s OR p.nombre LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT c.*, 
                   u.display_name as comercio_nombre,
                   p.nombre as propuesta_nombre,
                   owner.display_name as propietario_nombre
            FROM {$this->table_coupons} c
            LEFT JOIN {$this->wpdb->users} u ON c.comercio_id = u.ID
            LEFT JOIN {$this->table_proposals} p ON c.proposal_id = p.id
            LEFT JOIN {$this->wpdb->users} owner ON c.propietario_user_id = owner.ID
            WHERE {$where_sql}
            ORDER BY c.created_at DESC
        ";
        
        if (!empty($params)) {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
        } else {
            return $this->wpdb->get_results($sql);
        }
    }
    
    /**
     * Cupones para comercios (solo los suyos)
     */
	public function get_comercio_coupons($user_id, $filters = []) {
		global $wpdb;
		$table_coupons    = $wpdb->prefix . 'coupons';
		$table_propuestas = $wpdb->prefix . 'coupon_proposals';
		error_log('Filters en get_comercio_coupons: ' . print_r($filters, true));

		// Obtener comercio_id asociado a este usuario.
		// Ajusta esto según cómo guardes la relación (user_meta, tabla, etc.)
		$comercio_id = get_user_meta($user_id, 'comercio_id', true);
		// Si no existe mapping explícito, intenta asumir que el user_id es el comercio_id
		if (empty($comercio_id)) {
			$comercio_id = (int) $user_id;
		}

		// Base WHERE: cupones donde es propietario actual o fue emitido por su comercio
		$where_clauses = ["(c.propietario_user_id = %d OR c.comercio_id = %d)"];
		$params = [$user_id, $comercio_id];

		// Filtros extras: estado
		if (!empty($filters['estado'])) {
			$where_clauses[] = "c.estado = %s";
			$params[] = sanitize_text_field($filters['estado']);
		}

		// Filtro de búsqueda (código o nombre de propuesta)
		if (!empty($filters['search'])) {
			$search = '%' . $wpdb->esc_like($filters['search']) . '%';
			$where_clauses[] = "(c.codigo_serie LIKE %s OR p.nombre LIKE %s)";
			$params[] = $search;
			$params[] = $search;
		}

		error_log('Filtro rol: ' . print_r($filters['rol'] ?? 'null', true));

		// Filtro por rol: propietario / emisor
		if (!empty($filters['rol'])) {
			if ($filters['rol'] === 'propietario') {
				$where_clauses[] = "c.propietario_user_id = %d";
				$params[] = $user_id;
			} elseif ($filters['rol'] === 'emisor') {
				$where_clauses[] = "c.comercio_id = %d";
				$params[] = $comercio_id;
			}
			// si viene otro valor ignoramos (o podrías devolver error)
		}

		$where_sql = implode(' AND ', $where_clauses);

		// SQL: calculamos rol_en_cupon (emisor o propietario). Si por inconsistencia ambos, elegimos 'emisor'
		$sql = "
			SELECT
				c.*,
				p.nombre AS propuesta_nombre,
				CASE
					WHEN c.comercio_id = %d THEN 'emisor'
					WHEN c.propietario_user_id = %d THEN 'propietario'
					ELSE 'otro'
				END AS rol_en_cupon
			FROM {$table_coupons} AS c
			LEFT JOIN {$table_propuestas} AS p ON p.id = c.proposal_id
			WHERE {$where_sql}
			ORDER BY c.created_at DESC
		";

		// Preparar parámetros en el orden que usa el SQL:
		// 1) CASE placeholders: comercio_id, user_id
		// 2) luego los params construidos arriba ($params)
		$prepare_params = array_merge([(int)$comercio_id, (int)$user_id], $params);

		$results = $wpdb->get_results( $wpdb->prepare($sql, ...$prepare_params) );

		// Si detectás cupón con rol 'otro' (no debería ocurrir porque WHERE ya limita), podés loguearlo:
		foreach ($results as $r) {
			if ($r->rol_en_cupon === 'otro') {
				error_log("CS: cupón id {$r->id} devuelto con rol 'otro' para user {$user_id} / comercio {$comercio_id}");
			}
			// Si por inconsistencia ambos son iguales (improbable), podrías loguearlo:
			if ($r->propietario_user_id == $r->comercio_id) {
				error_log("CS: inconsistencia: cupón {$r->id} tiene propietario_user_id == comercio_id ({$r->propietario_user_id})");
			}
		}

		return $results;
	}

    
    /**
     * Cupones para usuarios finales (solo los asignados)
     */
    private function get_user_coupons($user_id, $filters = []) {
        $user = get_userdata($user_id);
        
        $where_conditions = [
            '(c.propietario_user_id = %d OR c.propietario_email = %s)'
        ];
        $params = [$user_id, $user->user_email];
        
        // Solo cupones activos para usuarios finales
        $where_conditions[] = "c.estado IN ('asignado_user', 'asignado_email', 'parcial')";
        
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where_conditions[] = '(c.codigo_serie LIKE %s OR p.nombre LIKE %s OR u.display_name LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT c.*, 
                   u.display_name as comercio_nombre,
                   p.nombre as propuesta_nombre
            FROM {$this->table_coupons} c
            LEFT JOIN {$this->wpdb->users} u ON c.comercio_id = u.ID
            LEFT JOIN {$this->table_proposals} p ON c.proposal_id = p.id
            WHERE {$where_sql}
            ORDER BY c.fecha_fin ASC
        ";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * Transferir propiedad de un cupón
     */
    public function transfer_coupon_ownership($coupon_id, $new_owner_identifier, $transfer_type = 'email') {
        // Validaciones
        if (!current_user_can('manage_options') && !$this->user_can_transfer_coupon($coupon_id)) {
            throw new Exception('No tienes permisos para transferir este cupón.');
        }
		       
        $coupon = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_coupons} WHERE id = %d",
            $coupon_id
        ));

        if (!$coupon) {
            throw new Exception('Cupón no encontrado.');
        }
        
        if (!in_array($coupon->estado, ['pendiente_comercio', 'asignado_admin', 'asignado_email', 'asignado_user'])) {
            throw new Exception('Este cupón no puede ser transferido en su estado actual.');
        }

        $current_user_id = get_current_user_id();
        $current_user = get_userdata($current_user_id);

		// Bloquear si emisor y propietario son la misma entidad
		if ($transfer_type === 'email') {
			$existing_user = get_user_by('email', $new_owner_identifier);
			if ($existing_user && intval($existing_user->ID) === intval($coupon->comercio_id)) {
				throw new Exception('No se puede transferir. El comercio no puede ser emisor y propietario de su propio ticket.');
			}
		} elseif ($transfer_type === 'user_id') {
			if (intval($new_owner_identifier) === intval($coupon->comercio_id)) {
				throw new Exception('No se puede transferir. El comercio no puede ser emisor y propietario de su propio ticket.');
			}
		}
        
        // Validar que no se transfiera a sí mismo       
        if ($transfer_type === 'user_id') {
            if (intval($new_owner_identifier) === $current_user_id) {
                throw new Exception('No puedes transferir un cupón a ti mismo.');
            }
            if (intval($new_owner_identifier) === intval($coupon->propietario_user_id)) {
                throw new Exception('El cupón ya pertenece a este usuario.');
            }
        } elseif ($transfer_type === 'email') {
            if ($new_owner_identifier === $current_user->user_email) {
                throw new Exception('No puedes transferir un cupón a tu propio email.');
            }
            if ($new_owner_identifier === $coupon->propietario_email) {
                throw new Exception('El cupón ya está asignado a este email.');
            }
        }
        
        // Realizar la transferencia
        $update_data = ['updated_at' => current_time('mysql')];
        
        if ($transfer_type === 'user_id') {
            $new_user = get_userdata($new_owner_identifier);
            if (!$new_user) {
                throw new Exception('Usuario destino no encontrado.');
            }
            
            $update_data['propietario_user_id'] = $new_owner_identifier;
            $update_data['propietario_email'] = $new_user->user_email;
            $update_data['estado'] = 'asignado_user';
            
        } elseif ($transfer_type === 'email') {
            if (!is_email($new_owner_identifier)) {
                throw new Exception('Email inválido.');
            }
            
            $existing_user = get_user_by('email', $new_owner_identifier);
            
            if ($existing_user) {
                $update_data['propietario_user_id'] = $existing_user->ID;
                $update_data['propietario_email'] = $new_owner_identifier;
                $update_data['estado'] = 'asignado_user';
            } else {
                $update_data['propietario_user_id'] = null;
                $update_data['propietario_email'] = $new_owner_identifier;
                $update_data['estado'] = 'asignado_email';
            }
        } else {
            throw new Exception('Tipo de transferencia inválido.');
        }
        
        $result = $this->wpdb->update(
            $this->table_coupons,
            $update_data,
            ['id' => $coupon_id],
            ['%s', '%s', '%s', '%s'], // Formatos para los valores
            ['%d'] // Formato para el WHERE
        );
        
        if ($result === false) {
            throw new Exception('Error al actualizar el cupón en la base de datos.');
        }
        
        // Hook para acciones posteriores a la transferencia
        do_action('cs_coupon_transferred', $coupon_id, $coupon, $new_owner_identifier, $transfer_type);
        
        // Log de la transferencia
        $this->log_coupon_transfer($coupon_id, $coupon, $new_owner_identifier, $transfer_type);
        
        return true;
    }
    
    /**
     * Verificar si el usuario puede transferir un cupón específico
     */
	private function user_can_transfer_coupon($coupon_id) {
		$current_user_id = get_current_user_id();
		$user = get_userdata($current_user_id);

		// Admin siempre puede
		if (user_can($user, 'manage_options')) {
			return true;
		}

		// Comercio solo puede transferir cupones de su propiedad
		if (in_array('comercio', $user->roles)) {
			$coupon = $this->wpdb->get_row($this->wpdb->prepare(
				"SELECT propietario_user_id FROM {$this->table_coupons} WHERE id = %d",
				$coupon_id
			));

			return $coupon && intval($coupon->propietario_user_id) === $current_user_id;
		}

		return false;
	}
    
    /**
     * Registrar transferencia de cupón
     */
    private function log_coupon_transfer($coupon_id, $coupon, $new_owner, $transfer_type) {
        $current_user = get_userdata(get_current_user_id());
        $log_message = sprintf(
            'Cupón %s transferido por %s (%d) a %s via %s',
            $coupon->codigo_serie,
            $current_user->display_name,
            $current_user->ID,
            $new_owner,
            $transfer_type
        );
        
        error_log($log_message);
        
        // Opcional: guardar en tabla de logs si existe
        do_action('cs_log_coupon_action', $coupon_id, 'transfer', $log_message);
    }
    
    /**
     * Canjear/usar cupón (parcial o total)
     */
    public function redeem_coupon($codigo_secreto, $amount_to_redeem = null, $user_id = null) {
        $coupon = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_coupons} WHERE codigo_secreto = %s",
            $codigo_secreto
        ));
        
        if (!$coupon) {
            throw new Exception('Cupón no encontrado.');
        }
        
        // Validaciones de estado
        if (!in_array($coupon->estado, ['asignado_user', 'asignado_email', 'parcial'])) {
            throw new Exception('Este cupón no puede ser canjeado en su estado actual: ' . $coupon->estado);
        }
        
        // Validar fechas
        $today = new DateTime();
        $fecha_inicio = new DateTime($coupon->fecha_inicio);
        $fecha_fin = new DateTime($coupon->fecha_fin);
        
        if ($today < $fecha_inicio) {
            throw new Exception('Este cupón aún no está vigente.');
        }
        
        if ($today > $fecha_fin) {
            // Marcar como vencido
            $this->wpdb->update(
                $this->table_coupons,
                ['estado' => 'vencido'],
                ['id' => $coupon->id]
            );
            throw new Exception('Este cupón ha vencido.');
        }
        
        // Determinar cantidad a canjear
        if ($amount_to_redeem === null) {
            $amount_to_redeem = $coupon->valor_restante;
        }
        
        if ($amount_to_redeem <= 0) {
            throw new Exception('La cantidad a canjear debe ser mayor a 0.');
        }
        
        if ($amount_to_redeem > $coupon->valor_restante) {
            throw new Exception('La cantidad solicitada excede el valor disponible en el cupón.');
        }
        
        // Si no permite uso parcial, debe canjearse completo
        if (!$coupon->permite_uso_parcial && $amount_to_redeem != $coupon->valor_restante) {
            throw new Exception('Este cupón debe ser canjeado completamente.');
        }
        
        // Calcular nuevo valor restante
        $nuevo_valor_restante = $coupon->valor_restante - $amount_to_redeem;
        
        // Determinar nuevo estado
        $nuevo_estado = 'completado';
        if ($nuevo_valor_restante > 0) {
            $nuevo_estado = 'parcial';
        }
        
        // Actualizar cupón
        $update_data = [
            'valor_restante' => $nuevo_valor_restante,
            'estado' => $nuevo_estado,
            'fecha_ultimo_uso' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        if ($user_id) {
            $update_data['usado_por'] = $user_id;
        }
        
        $result = $this->wpdb->update(
            $this->table_coupons,
            $update_data,
            ['id' => $coupon->id]
        );
        
        if ($result === false) {
            throw new Exception('Error al actualizar el cupón.');
        }
        
        // Hook para acciones posteriores al canje
        do_action('cs_coupon_redeemed', $coupon->id, $amount_to_redeem, $nuevo_valor_restante, $user_id);
        
        // Log del canje
        $this->log_coupon_redemption($coupon, $amount_to_redeem, $nuevo_valor_restante, $user_id);
        
        return [
            'success' => true,
            'amount_redeemed' => $amount_to_redeem,
            'remaining_value' => $nuevo_valor_restante,
            'status' => $nuevo_estado,
            'coupon_code' => $coupon->codigo_serie
        ];
    }
    
    /**
     * Registrar canje de cupón
     */
    private function log_coupon_redemption($coupon, $amount_redeemed, $remaining_value, $user_id) {
        $user_info = $user_id ? get_userdata($user_id)->display_name . " (ID: $user_id)" : 'Usuario no identificado';
        
        $log_message = sprintf(
            'Cupón %s canjeado: %s de %s %s. Restante: %s. Usuario: %s',
            $coupon->codigo_serie,
            $amount_redeemed,
            $coupon->valor,
            $coupon->tipo === 'importe' ? '$' : $coupon->unidad_descripcion,
            $remaining_value,
            $user_info
        );
        
        error_log($log_message);
        do_action('cs_log_coupon_action', $coupon->id, 'redeem', $log_message);
    }
    
    /**
     * Anular cupón
     */
    public function cancel_coupon($coupon_id, $reason = '') {
        if (!current_user_can('manage_options')) {
            throw new Exception('No tienes permisos para anular cupones.');
        }
        
        $coupon = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_coupons} WHERE id = %d",
            $coupon_id
        ));
        
        if (!$coupon) {
            throw new Exception('Cupón no encontrado.');
        }
        
        if ($coupon->estado === 'anulado') {
            throw new Exception('El cupón ya está anulado.');
        }
        
        if ($coupon->estado === 'completado') {
            throw new Exception('No se puede anular un cupón completamente usado.');
        }
        
        $update_data = [
            'estado' => 'anulado',
            'updated_at' => current_time('mysql')
        ];
        
        if ($reason) {
            $update_data['notas_uso'] = 'ANULADO: ' . sanitize_text_field($reason);
        }
        
        $result = $this->wpdb->update(
            $this->table_coupons,
            $update_data,
            ['id' => $coupon_id]
        );
        
        if ($result === false) {
            throw new Exception('Error al anular el cupón.');
        }
        
        // Hook y log
        do_action('cs_coupon_cancelled', $coupon_id, $reason);
        $this->log_coupon_cancellation($coupon, $reason);
        
        return true;
    }
    
    /**
     * Registrar anulación de cupón
     */
    private function log_coupon_cancellation($coupon, $reason) {
        $current_user = get_userdata(get_current_user_id());
        $log_message = sprintf(
            'Cupón %s anulado por %s. Razón: %s',
            $coupon->codigo_serie,
            $current_user->display_name,
            $reason ?: 'Sin razón especificada'
        );
        
        error_log($log_message);
        do_action('cs_log_coupon_action', $coupon->id, 'cancel', $log_message);
    }
    
    /**
	 * Obtener estadísticas de cupones separadas por tipo
	 */
	public function get_coupon_stats($comercio_id = null) {
		// Si es un comercio logueado, forzamos su propio ID
		if (current_user_can('comercio_role')) {
			$comercio_id = get_current_user_id();
		}

		$where_clause = $comercio_id ? "WHERE comercio_id = " . intval($comercio_id) : "";

		$stats = $this->wpdb->get_results("
			SELECT 
				tipo,
				estado,
				COUNT(*) as total,
				SUM(valor) as valor_total,
				SUM(valor_restante) as valor_restante_total
			FROM {$this->table_coupons}
			{$where_clause}
			GROUP BY tipo, estado
		");

		// Estructura final: separada por tipo
		$formatted_stats = [
			'importe' => [
				'by_status' => [],
				'totals' => ['count' => 0, 'value' => 0, 'remaining' => 0]
			],
			'unidad' => [
				'by_status' => [],
				'totals' => ['count' => 0, 'value' => 0, 'remaining' => 0]
			]
		];

		foreach ($stats as $stat) {
			$tipo = $stat->tipo; // 'importe' o 'unidad'
			if (!isset($formatted_stats[$tipo])) {
				// Por si aparece un tipo desconocido
				$formatted_stats[$tipo] = [
					'by_status' => [],
					'totals' => ['count' => 0, 'value' => 0, 'remaining' => 0]
				];
			}

			$formatted_stats[$tipo]['by_status'][$stat->estado] = [
				'count' => intval($stat->total),
				'total_value' => floatval($stat->valor_total),
				'remaining_value' => floatval($stat->valor_restante_total)
			];

			// Sumar totales para ese tipo
			$formatted_stats[$tipo]['totals']['count'] += intval($stat->total);
			$formatted_stats[$tipo]['totals']['value'] += floatval($stat->valor_total);
			$formatted_stats[$tipo]['totals']['remaining'] += floatval($stat->valor_restante_total);
		}

		return $formatted_stats;
	}
}


/**
 * Funciones de utilidad para cupones
 */

/**
 * Obtener instancia del manager de cupones
 */
function cs_get_coupon_manager() {
    static $manager = null;
    if ($manager === null) {
        $manager = new CS_Coupon_Manager();
    }
    return $manager;
}

/**
 * Transferir cupón - función wrapper
 */
function cs_transfer_coupon($coupon_id, $new_owner, $type = 'email') {
    $manager = cs_get_coupon_manager();
    return $manager->transfer_coupon_ownership($coupon_id, $new_owner, $type);
}

/**
 * Canjear cupón - función wrapper
 */
function cs_redeem_coupon($secret_code, $amount = null, $user_id = null) {
    $manager = cs_get_coupon_manager();
    return $manager->redeem_coupon($secret_code, $amount, $user_id);
}

/**
 * Anular cupón - función wrapper
 */
function cs_cancel_coupon($coupon_id, $reason = '') {
    $manager = cs_get_coupon_manager();
    return $manager->cancel_coupon($coupon_id, $reason);
}

/**
 * Obtener cupones para el usuario actual
 */
function cs_get_user_coupons($filters = []) {
    $manager = cs_get_coupon_manager();
    
    if (!empty($_GET['estado'])) {
        $estado = sanitize_text_field($_GET['estado']);
        
        if ($estado === 'asignado_cliente') {
            // Aquí guardamos directamente el array para filtrar por varios estados
            $filters['estado'] = ['asignado_email', 'asignado_user'];
        } else {
            $filters['estado'] = [$estado];  // siempre como array para simplificar lógica en la consulta
        }
    }
    
    if (!empty($_GET['s'])) {
        $filters['search'] = sanitize_text_field($_GET['s']);
    }
    
    if (!empty($_GET['rol'])) {
        $rol = sanitize_text_field($_GET['rol']);
        if (in_array($rol, ['propietario', 'emisor'], true)) {
            $filters['rol'] = $rol;
        }
    }
    
    return $manager->get_coupons_for_user(get_current_user_id(), $filters);
}

/**
 * Hook para limpiar cupones vencidos diariamente
 */
add_action('cs_daily_coupon_emission', 'cs_cleanup_expired_coupons');

function cs_cleanup_expired_coupons() {
    global $wpdb;
    
    $table_coupons = $wpdb->prefix . 'coupons';
    
    // Marcar cupones vencidos
    $updated = $wpdb->query("
        UPDATE {$table_coupons} 
        SET estado = 'vencido', updated_at = NOW()
        WHERE fecha_fin < CURDATE() 
        AND estado NOT IN ('completado', 'anulado', 'vencido')
    ");
    
    if ($updated > 0) {
        error_log("Cupones marcados como vencidos: {$updated}");
    }
}

/**
 * AJAX handlers para operaciones de cupones
 */
add_action('wp_ajax_cs_transfer_coupon', 'cs_ajax_transfer_coupon');
add_action('wp_ajax_cs_redeem_coupon', 'cs_ajax_redeem_coupon');
add_action('wp_ajax_cs_cancel_coupon', 'cs_ajax_cancel_coupon');

function cs_ajax_transfer_coupon() {
    // Seguridad básica
    $coupon_id = intval( $_POST['coupon_id'] ?? 0 );
    if ( ! $coupon_id ) {
        wp_send_json_error(['message' => 'ID de cupón inválido.']);
    }

    // Nonce: enviá el mismo nonce 'transfer_nonce' en el request (campo name 'security')
    if ( ! check_ajax_referer( 'transfer_coupon_' . $coupon_id, 'security', false ) ) {
        wp_send_json_error(['message' => 'Nonce inválido.']);
    }

	$transfer_type = sanitize_text_field( $_POST['transfer_type'] ?? 'email' );
	if ( ! in_array( $transfer_type, ['email', 'user_id'], true ) ) {
		wp_send_json_error(['message' => 'Tipo de transferencia inválido.']);
	}

    try {
        if ( $transfer_type === 'user_id' ) {
            $new_owner = intval( $_POST['new_owner_user'] ?? 0 );
            if ( $new_owner <= 0 ) {
                throw new Exception('ID de usuario inválido.');
            }
            cs_transfer_coupon( $coupon_id, $new_owner, 'user_id' );
        } else {
            $new_owner = sanitize_email( $_POST['new_owner_email'] ?? '' );
            if ( ! is_email( $new_owner ) ) {
                throw new Exception('Email inválido.');
            }
            cs_transfer_coupon( $coupon_id, $new_owner, 'email' );
        }

        wp_send_json_success(['message' => 'Transferencia realizada.']);
    } catch ( Exception $e ) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

function cs_ajax_redeem_coupon() {
    try {
        if (!wp_verify_nonce($_POST['nonce'], 'cs_coupon_redeem')) {
            throw new Exception('Nonce inválido');
        }
        
        $secret_code = sanitize_text_field($_POST['secret_code']);
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
        
        $result = cs_redeem_coupon($secret_code, $amount, get_current_user_id());
        
        wp_send_json_success($result);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

function cs_ajax_cancel_coupon() {
    try {
        if (!wp_verify_nonce($_POST['nonce'], 'cs_coupon_action')) {
            throw new Exception('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            throw new Exception('No tienes permisos');
        }
        
        $coupon_id = intval($_POST['coupon_id']);
        $reason = sanitize_text_field($_POST['reason']);
        
        cs_cancel_coupon($coupon_id, $reason);
        
        wp_send_json_success(['message' => 'Cupón anulado exitosamente']);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}