<?php
// Actualización del archivo menu.php

require_once CS_PLUGIN_PATH . 'includes/utils.php';
require_once CS_PLUGIN_PATH . 'includes/comercios_pages.php';
require_once CS_PLUGIN_PATH . 'includes/propuestas_pages.php';
require_once CS_PLUGIN_PATH . 'includes/coupon_management_system.php';


function cs_register_admin_menu() {
    add_menu_page(
        'Dashboard',
        'Cupones',
        'manage_options',
        'cs_dashboard',
        'cs_dashboard_page',
        'dashicons-tickets-alt',
        25
    );
	
	// Creamos explícitamente la primera entrada del submenú que apunta
    // al mismo slug del top-level. Esto mostrará "Dashboard" como primera
    // opción del submenú en lugar de la duplicación automática.
    add_submenu_page(
        'cs_dashboard',
        'Dashboard',              // page_title del submenú
        'Dashboard',              // menu_title mostrado en la lista de submenús
        'manage_options',
        'cs_dashboard',           // mismo slug del menú padre
        'cs_dashboard_page'       // misma callback
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
    
	add_submenu_page(
		'cs_dashboard',
		'Cupones',
		'Cupones',
		'read',
		'cs_cupones',
		'cs_cupones_page'
	);	
	
	// 2. Ocultar menú para comercio (aunque tienen permiso)
	// Ocultar el menú para rol comercio con CSS
	add_action('admin_head', function() {
		if (current_user_can('comercio_role') && !current_user_can('manage_options')) {
			echo '<style>#toplevel_page_cs_dashboard ul.wp-submenu li a[href="admin.php?page=cs_cupones"] { display: none !important; }</style>';
			echo '<style>#toplevel_page_cs_dashboard { display: none !important; }</style>';
		}
	});
	
    add_submenu_page(
        'cs_dashboard',
        'Emisión Manual',
        'Emisión Manual',
        'manage_options',
        'cs_emision_manual',
        'cs_emision_manual_page'
    );
    
	// Submenú para comercios (solo ven sus cupones)
	if ( current_user_can('comercio_role') ) {
		add_menu_page(
			'Mis Cupones',
			'Mis Cupones',
			'read',
			'cs_mis_cupones',
			'cs_comercio_cupones_page',
			'dashicons-tickets',
			30
		);

		// Agregar submenú para Propuestas (para que pueda aceptar propuestas de emisión)
		add_submenu_page(
			'cs_mis_cupones',              // slug del menú padre
			'Propuestas',
			'Propuestas',
			'read',                        // permiso mínimo
			'cs_propuestas',
			'cs_propuestas_page'
		);
	}
}
add_action('admin_menu', 'cs_register_admin_menu');

function cs_dashboard_page() {
    echo '<div class="wrap">';
    echo '<h1>Dashboard de Cupones</h1>';
    
    // Estadísticas generales para admin
    if (current_user_can('manage_options')) {
        $manager = cs_get_coupon_manager();
        $stats = $manager->get_coupon_stats();
        
        echo '<div class="dashboard-widgets-wrap">';
        echo '<div class="metabox-holder">';
        
        // Widget de estadísticas
        echo '<div class="postbox">';
        echo '<h2 class="hndle">Resumen de Cupones</h2>';
        echo '<div class="inside">';
        cs_render_coupon_stats($stats);
        echo '</div>';
        echo '</div>';
        
        // Widget de acciones rápidas
        echo '<div class="postbox">';
        echo '<h2 class="hndle">Acciones Rápidas</h2>';
        echo '<div class="inside">';
        echo '<p><a href="' . admin_url('admin.php?page=cs_propuestas&action=add') . '" class="button button-primary">Nueva Propuesta</a></p>';
        echo '<p><a href="' . admin_url('admin.php?page=cs_comercios&action=add') . '" class="button button-secondary">Nuevo Comercio</a></p>';
        
        $manual_url = wp_nonce_url(
            admin_url('admin.php?page=cs_emision_manual&ejecutar=1'),
            'cs_manual_emission'
        );
        echo '<p><a href="' . esc_url($manual_url) . '" class="button button-secondary" onclick="return confirm(\'¿Ejecutar emisión manual?\')">Emisión Manual</a></p>';
        echo '</div>';
        echo '</div>';
        
        // Widget de próximas emisiones
        echo '<div class="postbox">';
        echo '<h2 class="hndle">Próximas Emisiones</h2>';
        echo '<div class="inside">';
        cs_render_upcoming_emissions();
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Renderizar próximas emisiones
 */
function cs_render_upcoming_emissions() {
    global $wpdb;
    
    $proposals_table = $wpdb->prefix . 'coupon_proposals';
    $coupons_table = $wpdb->prefix . 'coupons';
    
    $proposals = $wpdb->get_results("
        SELECT p.*, u.display_name as comercio_nombre,
               COUNT(c.id) as cupones_emitidos
        FROM {$proposals_table} p
        LEFT JOIN {$wpdb->users} u ON p.comercio_id = u.ID
        LEFT JOIN {$coupons_table} c ON p.id = c.proposal_id
        WHERE p.estado = 'aprobado' 
        AND p.fecha_inicio <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        GROUP BY p.id
        ORDER BY p.fecha_inicio ASC
        LIMIT 10
    ");
    
    if (empty($proposals)) {
        echo '<p>No hay emisiones programadas para los próximos 30 días.</p>';
        return;
    }
    
    echo '<table class="widefat">';
    echo '<thead><tr><th>Propuesta</th><th>Comercio</th><th>Próxima Emisión</th><th>Estado</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($proposals as $proposal) {
        // Calcular próxima emisión
        $start_date = new DateTime($proposal->fecha_inicio);
        $today = new DateTime();
        
        $frequency_days = cs_convert_to_days($proposal->frecuencia_emision, $proposal->unidad_frecuencia);
        $expected_cycles = floor($today->diff($start_date)->days / $frequency_days) + 1;
        $emitted_cycles = floor($proposal->cupones_emitidos / $proposal->cupones_por_ciclo);
        
        if ($emitted_cycles < $proposal->cantidad_ciclos) {
            $next_cycle = $emitted_cycles;
            $next_emission = clone $start_date;
            $next_emission->add(new DateInterval('P' . ($next_cycle * $frequency_days) . 'D'));
            
            $status = '';
            if ($next_emission <= $today) {
                $status = '<span style="color: #dc3232;">Pendiente</span>';
            } else {
                $status = '<span style="color: #00a0d2;">Programada</span>';
            }
            
            echo '<tr>';
            echo '<td>' . esc_html($proposal->nombre) . '</td>';
            echo '<td>' . esc_html($proposal->comercio_nombre) . '</td>';
            echo '<td>' . $next_emission->format('d/m/Y') . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody>';
    echo '</table>';
}

/**
 * Página de cupones para comercios
 */
function cs_comercio_cupones_page() {
    if (!current_user_can('read') || !in_array('comercio', wp_get_current_user()->roles)) {
        wp_die('No tienes permisos para acceder a esta página.');
    }
    
    $manager = cs_get_coupon_manager();
    $current_user_id = get_current_user_id();
    
    // Filtros
    $filters = [];
    if (!empty($_GET['estado'])) {
        $filters['estado'] = sanitize_text_field($_GET['estado']);
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
    
    $coupons = $manager->get_coupons_for_user($current_user_id, $filters);
    $stats = $manager->get_coupon_stats($current_user_id);
    
    echo '<div class="wrap">';
    echo '<h1>Mis Cupones</h1>';
    
    // Estadísticas del comercio
    echo '<div class="cs-comercio-stats" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">';
    echo '<h2>Resumen de mis cupones</h2>';
    cs_render_coupon_stats($stats);
    echo '</div>';
    
    // Filtros simplificados para comercios
    echo '<div class="cs-filters" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 4px;">';
    echo '<form method="get" style="display: flex; gap: 15px; align-items: end;">';
    echo '<input type="hidden" name="page" value="cs_mis_cupones">';
    
    echo '<div>';
    echo '<label for="estado">Estado:</label><br>';
    echo '<select name="estado" id="estado">';
    echo '<option value="">Todos</option>';
    $estados_comercio = [
        'asignado_admin' => 'Asignado al Administrador',
        'asignado_cliente' => 'Asignado al Cliente',
        'parcial' => 'Parcialmente Usados',
        'completado' => 'Completados',
		'anulado' => 'Anulado',
		'vencido' => 'Vencido'
    ];
    foreach ($estados_comercio as $value => $label) {
        $selected = selected($filters['estado'] ?? '', $value, false);
        echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
    }
    echo '</select>';
    echo '</div>';
    
	echo '<div>';
	echo '<label for="rol">Rol:</label><br>';
	echo '<select name="rol" id="rol">';
	echo '<option value="">Todos</option>';
	echo '<option value="propietario"' . selected($filters['rol'] ?? '', 'propietario', false) . '>Propietario</option>';
	echo '<option value="emisor"' . selected($filters['rol'] ?? '', 'emisor', false) . '>Emisor</option>';
	echo '</select>';
	echo '</div>';

	
    echo '<div>';
    echo '<label for="s">Buscar:</label><br>';
    echo '<input type="text" name="s" id="s" placeholder="Código o propuesta..." value="' . esc_attr($filters['search'] ?? '') . '">';
    echo '</div>';
    
    echo '<div>';
    echo '<input type="submit" class="button" value="Filtrar">';
    if (!empty(array_filter($filters))) {
        echo ' <a href="' . admin_url('admin.php?page=cs_mis_cupones') . '" class="button">Limpiar</a>';
    }
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    
    // Tabla de cupones del comercio
    cs_render_comercio_coupons_table($coupons);
    
    echo '</div>';
}

/**
 * Tabla de cupones para comercios (vista simplificada)
 */
function cs_render_comercio_coupons_table($coupons) {
    if (empty($coupons)) {
        echo '<p>No tienes cupones con los filtros aplicados.</p>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Valor</th>';
    echo '<th>Propietario</th>';
    echo '<th>Estado</th>';
    echo '<th>Vigencia</th>';
	echo '<th>Rol</th>';
    echo '<th>Acciones</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($coupons as $coupon) {
        echo '<tr>';
        
        // Código
        echo '<td><strong>' . esc_html($coupon->codigo_serie) . '</strong></td>';
        
        // Valor
        echo '<td>';
        if ($coupon->tipo === 'importe') {
            echo '$' . number_format($coupon->valor_restante, 2) . ' / '  . number_format($coupon->valor, 2);
        } else {
            echo intval($coupon->valor_restante) . ' / ' . intval($coupon->valor) . ' ' . esc_html($coupon->unidad_descripcion ?: 'unidades');
        }
        echo '</td>';
        
        // Propietario
        echo '<td>';
        if ($coupon->propietario_email) {
			echo esc_html($coupon->propietario_email);
		} elseif ($coupon->propietario_user_id) {
			$user_info = get_userdata($coupon->propietario_user_id);
			if ($user_info) {
				echo esc_html($user_info->display_name);  // o $user_info->user_login, o combinar nombre y apellido
			} else {
				echo esc_html($coupon->propietario_user_id); // fallback por si no encuentra usuario
			}
		} else {
			echo '<em>Sin asignar</em>';
		}
		echo '</td>';

        
        // Estado
        $status_color = cs_get_status_color($coupon->estado);
        echo '<td><span style="color: ' . $status_color . '; font-weight: bold;">';
        echo esc_html(cs_get_status_label($coupon->estado));
        echo '</span></td>';
        
        // Vigencia
        echo '<td>';
        echo date_i18n('d/m/Y', strtotime($coupon->fecha_inicio)) . '<br>';
        echo '<small>hasta ' . date_i18n('d/m/Y', strtotime($coupon->fecha_fin)) . '</small>';
        echo '</td>';
        
		// Rol calculado
		$rol = $coupon->rol_en_cupon ?? 'otro';
		if ($rol === 'emisor') {
			$rol_label = 'Emisor';
		} elseif ($rol === 'propietario') {
			$rol_label = 'Propietario';
		} else {
			$rol_label = '—';
		}
		echo '<td>' . esc_html($rol_label) . '</td>';
		
        // Acciones (limitadas para comercios)
        echo '<td>';
        $actions = [];
        
        // Solo pueden transferir cupones pendientes o asignados por admin
        if (
			in_array($coupon->estado, ['asignado_admin', 'asignado_email', 'asignado_user'])
			&& intval($coupon->propietario_user_id) === get_current_user_id()
		) {
			$actions[] = '<a href="' . admin_url('admin.php?page=cs_cupones&action=transfer&id=' . $coupon->id) . '">Transferir</a>';
		}
        
        // Ver detalles básicos
        $actions[] = '<a href="#" onclick="viewCouponDetails(' . $coupon->id . '); return false;">Ver</a>';
        
        echo implode(' | ', $actions);
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // JavaScript para comercios
    echo '<script>    
    function viewCouponDetails(couponId) {
        // Abrir modal o redirigir a página de detalles
        window.open("' . admin_url('admin.php?page=cs_cupones&action=view&id=') . '" + couponId, "_blank");
    }
    </script>';
}

// Hook para agregar capacidades del rol comercio
add_action('init', 'cs_add_comercio_capabilities');

function cs_add_comercio_capabilities() {
    $role = get_role('comercio');
    if ($role) {
        $role->add_cap('comercio_role');
    }
}

// Actualización del archivo principal del plugin (coupons-system.php)
// Agregar estas líneas al final:

// Incluir nuevos archivos
require_once CS_PLUGIN_PATH . 'includes/coupon_emission_system.php';
require_once CS_PLUGIN_PATH . 'includes/coupon_admin_pages.php';

// Función de desactivación
register_deactivation_hook(__FILE__, 'cs_deactivate_plugin');
function cs_deactivate_plugin() {
    cs_unschedule_coupon_emission();
}

// AJAX para usuarios no logueados (para futuras funcionalidades públicas)
add_action('wp_ajax_nopriv_cs_check_coupon', 'cs_ajax_check_coupon_public');

function cs_ajax_check_coupon_public() {
    // Verificar cupón por código público (para usuarios finales)
    try {
        $codigo_serie = sanitize_text_field($_POST['codigo_serie']);
        
        global $wpdb;
        $coupon = $wpdb->get_row($wpdb->prepare("
            SELECT c.*, u.display_name as comercio_nombre, p.nombre as propuesta_nombre
            FROM {$wpdb->prefix}coupons c
            LEFT JOIN {$wpdb->users} u ON c.comercio_id = u.ID
            LEFT JOIN {$wpdb->prefix}coupon_proposals p ON c.proposal_id = p.id
            WHERE c.codigo_serie = %s
        ", $codigo_serie));
        
        if (!$coupon) {
            wp_send_json_error(['message' => 'Cupón no encontrado']);
        }
        
        // Solo mostrar información básica para usuarios no logueados
        $public_info = [
            'codigo_serie' => $coupon->codigo_serie,
            'comercio' => $coupon->comercio_nombre,
            'propuesta' => $coupon->propuesta_nombre,
            'estado' => cs_get_status_label($coupon->estado),
            'vigente' => (strtotime($coupon->fecha_fin) >= time() && strtotime($coupon->fecha_inicio) <= time()),
            'fecha_fin' => date_i18n('d/m/Y', strtotime($coupon->fecha_fin))
        ];
        
        if ($coupon->tipo === 'importe') {
            $public_info['valor'] = '$' . number_format($coupon->valor_restante, 2);
        } else {
            $public_info['valor'] = intval($coupon->valor_restante) . ' ' . ($coupon->unidad_descripcion ?: 'unidades');
        }
        
        wp_send_json_success($public_info);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// Shortcode para mostrar cupones del usuario en frontend
add_shortcode('mis_cupones', 'cs_shortcode_mis_cupones');

function cs_shortcode_mis_cupones($atts) {
    if (!is_user_logged_in()) {
        return '<p>Debes iniciar sesión para ver tus cupones.</p>';
    }
    
    $atts = shortcode_atts([
        'limite' => 10,
        'mostrar_vencidos' => 'no'
    ], $atts);
    
    $manager = cs_get_coupon_manager();
    $filters = [];
    
    if ($atts['mostrar_vencidos'] === 'no') {
        // Solo cupones válidos
    }
    
    $coupons = $manager->get_coupons_for_user(get_current_user_id(), $filters);
    
    if (empty($coupons)) {
        return '<p>No tienes cupones asignados.</p>';
    }
    
    $output = '<div class="cs-user-coupons">';
    $output .= '<h3>Mis Cupones</h3>';
    
    $count = 0;
    foreach ($coupons as $coupon) {
        if ($count >= intval($atts['limite'])) break;
        
        $is_valid = (strtotime($coupon->fecha_fin) >= time() && strtotime($coupon->fecha_inicio) <= time());
        $css_class = $is_valid ? 'cs-coupon-valid' : 'cs-coupon-expired';
        
        $output .= '<div class="cs-coupon-card ' . $css_class . '" style="
            border: 1px solid #ddd; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 4px;
            background: ' . ($is_valid ? '#f0f8ff' : '#f5f5f5') . '
        ">';
        
        $output .= '<h4>' . esc_html($coupon->codigo_serie) . '</h4>';
        $output .= '<p><strong>Comercio:</strong> ' . esc_html($coupon->comercio_nombre) . '</p>';
        
        if ($coupon->propuesta_nombre) {
            $output .= '<p><strong>Propuesta:</strong> ' . esc_html($coupon->propuesta_nombre) . '</p>';
        }
        
        $output .= '<p><strong>Valor:</strong> ';
        if ($coupon->tipo === 'importe') {
            $output .= '$' . number_format($coupon->valor_restante, 2);
        } else {
            $output .= intval($coupon->valor_restante) . ' ' . esc_html($coupon->unidad_descripcion ?: 'unidades');
        }
        $output .= '</p>';
        
        $output .= '<p><strong>Válido hasta:</strong> ' . date_i18n('d/m/Y', strtotime($coupon->fecha_fin)) . '</p>';
        
        $status_color = cs_get_status_color($coupon->estado);
        $output .= '<p><strong>Estado:</strong> <span style="color: ' . $status_color . ';">' . cs_get_status_label($coupon->estado) . '</span></p>';
        
        $output .= '</div>';
        $count++;
    }
    
    $output .= '</div>';
    
    return $output;
}

// CSS básico para el frontend
add_action('wp_head', 'cs_frontend_styles');

function cs_frontend_styles() {
    echo '<style>
    .cs-user-coupons .cs-coupon-card {
        transition: transform 0.2s;
    }
    .cs-user-coupons .cs-coupon-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .cs-coupon-expired {
        opacity: 0.7;
    }
    </style>';
}