<?php
/**
 * Páginas de administración para cupones
 */

/**
 * Página principal de cupones
 */
function cs_cupones_page() {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'view':
            cs_cupones_render_view();
            break;
        case 'transfer':
            cs_cupones_render_transfer();
            break;
        default:
            cs_cupones_render_list();
            break;
    }
}

/**
 * Renderizar listado de cupones
 */
function cs_cupones_render_list() {
    $manager = cs_get_coupon_manager();
    
    // Procesar filtros
    $filters = [];
    if (!empty($_GET['estado'])) {
        $filters['estado'] = sanitize_text_field($_GET['estado']);
    }
    if (!empty($_GET['comercio_id'])) {
        $filters['comercio_id'] = intval($_GET['comercio_id']);
    }
    if (!empty($_GET['fecha_desde'])) {
        $filters['fecha_desde'] = sanitize_text_field($_GET['fecha_desde']);
    }
    if (!empty($_GET['fecha_hasta'])) {
        $filters['fecha_hasta'] = sanitize_text_field($_GET['fecha_hasta']);
    }
    if (!empty($_GET['s'])) {
        $filters['search'] = sanitize_text_field($_GET['s']);
    }
    
    $coupons = $manager->get_coupons_for_user(null, $filters);
    $stats = $manager->get_coupon_stats();
    
    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Gestión de Cupones</h1>';
    echo '<hr class="wp-header-end">';
    
    // Mostrar mensajes
    cs_show_coupon_messages();
    
    // Estadísticas rápidas
    cs_render_coupon_stats($stats);
    
    // Filtros
    cs_render_coupon_filters($filters);
    
    // Tabla de cupones
    cs_render_coupons_table($coupons);
    
    echo '</div>';
}

/**
 * Renderizar estadísticas de cupones
 */
function cs_render_coupon_stats($stats) {
    echo '<div class="cs-stats-container" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">';
    
    $status_labels = [
        'pendiente_comercio' => 'Pendientes',
        'asignado_admin' => 'Asignados al Admin',
        'asignado_email' => 'Asignados por Email',
        'asignado_user' => 'Asignados a Usuario',
        'parcial' => 'Parcialmente Usados',
        'completado' => 'Completados',
        'anulado' => 'Anulados',
        'vencido' => 'Vencidos'
    ];
    
    foreach ($status_labels as $status => $label) {
        if (isset($stats['by_status'][$status])) {
            $stat = $stats['by_status'][$status];
            $color = cs_get_status_color($status);
            
            echo '<div class="cs-stat-card" style="
                border: 1px solid #ddd; 
                padding: 15px; 
                border-radius: 4px; 
                background: white;
                border-left: 4px solid ' . $color . ';
                min-width: 150px;
            ">';
            echo '<h3 style="margin: 0 0 10px 0; color: ' . $color . ';">' . $stat['count'] . '</h3>';
            echo '<p style="margin: 0; font-weight: bold;">' . $label . '</p>';
            echo '<small>Valor: $' . number_format($stat['remaining_value'], 2) . '</small>';
            echo '</div>';
        }
    }
    
    // Total
    echo '<div class="cs-stat-card" style="
        border: 1px solid #ddd; 
        padding: 15px; 
        border-radius: 4px; 
        background: #f0f8ff;
        border-left: 4px solid #0073aa;
        min-width: 150px;
    ">';
    echo '<h3 style="margin: 0 0 10px 0; color: #0073aa;">' . $stats['totals']['count'] . '</h3>';
    echo '<p style="margin: 0; font-weight: bold;">Total Cupones</p>';
    echo '<small>Valor restante: $' . number_format($stats['totals']['remaining'], 2) . '</small>';
    echo '</div>';
    
    echo '</div>';
}

/**
 * Obtener color según estado
 */
function cs_get_status_color($status) {
    $colors = [
        'pendiente_comercio' => '#f56e28',
        'asignado_admin' => '#0073aa',
        'asignado_email' => '#00a0d2',
        'asignado_user' => '#46b450',
        'parcial' => '#ffb900',
        'completado' => '#00ba37',
        'anulado' => '#dc3232',
        'vencido' => '#646970'
    ];
    
    return $colors[$status] ?? '#646970';
}

/**
 * Renderizar filtros
 */
function cs_render_coupon_filters($current_filters) {
    echo '<div class="cs-filters" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 4px;">';
    echo '<form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">';
    echo '<input type="hidden" name="page" value="cs_cupones">';
    
    // Estado
    echo '<div>';
    echo '<label for="estado">Estado:</label><br>';
    echo '<select name="estado" id="estado">';
    echo '<option value="">Todos</option>';
    $estados = [
        'pendiente_comercio' => 'Pendientes',
        'asignado_admin' => 'Asignados por Admin',
        'asignado_email' => 'Asignados por Email',
        'asignado_user' => 'Asignados a Usuario',
        'parcial' => 'Parcialmente Usados',
        'completado' => 'Completados',
        'anulado' => 'Anulados',
        'vencido' => 'Vencidos'
    ];
    foreach ($estados as $value => $label) {
        $selected = selected($current_filters['estado'] ?? '', $value, false);
        echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
    }
    echo '</select>';
    echo '</div>';
    
    // Comercio
    echo '<div>';
    echo '<label for="comercio_id">Comercio:</label><br>';
    echo '<select name="comercio_id" id="comercio_id">';
    echo '<option value="">Todos</option>';
    $comercios = get_users(['role' => 'comercio', 'orderby' => 'display_name']);
    foreach ($comercios as $comercio) {
        $selected = selected($current_filters['comercio_id'] ?? '', $comercio->ID, false);
        echo "<option value=\"{$comercio->ID}\" {$selected}>{$comercio->display_name}</option>";
    }
    echo '</select>';
    echo '</div>';
    
    // Fechas
    echo '<div>';
    echo '<label for="fecha_desde">Desde:</label><br>';
    echo '<input type="date" name="fecha_desde" id="fecha_desde" value="' . esc_attr($current_filters['fecha_desde'] ?? '') . '">';
    echo '</div>';
    
    echo '<div>';
    echo '<label for="fecha_hasta">Hasta:</label><br>';
    echo '<input type="date" name="fecha_hasta" id="fecha_hasta" value="' . esc_attr($current_filters['fecha_hasta'] ?? '') . '">';
    echo '</div>';
    
    // Búsqueda
    echo '<div>';
    echo '<label for="s">Buscar:</label><br>';
    echo '<input type="text" name="s" id="s" placeholder="Código, comercio, propuesta..." value="' . esc_attr($current_filters['search'] ?? '') . '">';
    echo '</div>';
    
    // Botones
    echo '<div>';
    echo '<input type="submit" class="button" value="Filtrar">';
    if (!empty(array_filter($current_filters))) {
        echo ' <a href="' . admin_url('admin.php?page=cs_cupones') . '" class="button">Limpiar</a>';
    }
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
}

/**
 * Renderizar tabla de cupones
 */
function cs_render_coupons_table($coupons) {
    if ( empty( $coupons ) ) {
        echo '<p>' . esc_html__( 'No se encontraron cupones con los filtros aplicados.', 'cs' ) . '</p>';
        return;
    }

    // URL y nonce para acciones en lote (por si las usás más adelante)
    $bulk_nonce = wp_create_nonce( 'cs_bulk_action' );

    echo '<form id="cs-coupons-form" method="post">';
    echo '<input type="hidden" name="cs_bulk_nonce" value="' . esc_attr( $bulk_nonce ) . '">';

    echo '<div class="tablenav top">';
    echo '  <div class="alignleft actions">';
    echo '    <select id="bulk-action-selector-top" name="action">';
    echo '      <option value="-1">' . esc_html__( 'Acciones en lote', 'cs' ) . '</option>';
    echo '      <option value="transfer">' . esc_html__( 'Transferir seleccionados', 'cs' ) . '</option>';
    echo '      <option value="cancel">' . esc_html__( 'Anular seleccionados', 'cs' ) . '</option>';
    echo '    </select>';
    echo '    <input type="button" class="button action" value="' . esc_attr__( 'Aplicar', 'cs' ) . '" id="doaction">';
    echo '  </div>';
    echo '</div>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '  <td class="check-column"><input type="checkbox" id="cb-select-all-1" /></td>';
    echo '  <th>' . esc_html__( 'Código', 'cs' ) . '</th>';
    echo '  <th>' . esc_html__( 'Comercio', 'cs' ) . '</th>';
    echo '  <th>' . esc_html__( 'Tipo / Valor', 'cs' ) . '</th>';
    echo '  <th>' . esc_html__( 'Propietario', 'cs' ) . '</th>';
    echo '  <th>' . esc_html__( 'Estado', 'cs' ) . '</th>';
    echo '  <th>' . esc_html__( 'Vigencia', 'cs' ) . '</th>';
    echo '  <th>' . esc_html__( 'Acciones', 'cs' ) . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ( $coupons as $coupon ) {
        // Aseguramos tipos y valores por si vienen nulos
        $coupon_id      = isset( $coupon->id ) ? intval( $coupon->id ) : 0;
        $codigo_serie   = isset( $coupon->codigo_serie ) ? $coupon->codigo_serie : '';
        $comercio_name  = ! empty( $coupon->comercio_nombre ) ? $coupon->comercio_nombre : '';
        $propuesta_name = ! empty( $coupon->propuesta_nombre ) ? $coupon->propuesta_nombre : '';
        $propuesta_desc = ! empty( $coupon->propuesta_descripcion ) ? $coupon->propuesta_descripcion : '';
        $tipo           = isset( $coupon->tipo ) ? $coupon->tipo : '';
        $valor          = isset( $coupon->valor ) ? floatval( $coupon->valor ) : 0;
        $valor_restante = isset( $coupon->valor_restante ) ? floatval( $coupon->valor_restante ) : 0;
        $unidad_desc    = ! empty( $coupon->unidad_descripcion ) ? $coupon->unidad_descripcion : 'unidades';
        $propietario    = '';
        $estado         = isset( $coupon->estado ) ? $coupon->estado : '';
        $fecha_inicio   = ! empty( $coupon->fecha_inicio ) ? $coupon->fecha_inicio : '';
        $fecha_fin      = ! empty( $coupon->fecha_fin ) ? $coupon->fecha_fin : '';

        // Determinar propietario: nombre -> email -> user_id (si existe)
        if ( ! empty( $coupon->propietario_nombre ) ) {
            $propietario = $coupon->propietario_nombre;
            if ( ! empty( $coupon->propietario_email ) ) {
                $propietario .= ' (' . $coupon->propietario_email . ')';
            }
        } elseif ( ! empty( $coupon->propietario_email ) ) {
            $propietario = $coupon->propietario_email;
        } elseif ( ! empty( $coupon->propietario_user_id ) ) {
            $user = get_userdata( intval( $coupon->propietario_user_id ) );
            if ( $user ) {
                $propietario = $user->display_name . ' (' . $user->user_email . ')';
            }
        }

        if ( empty( $propietario ) ) {
            $propietario = '<em>' . esc_html__( 'Sin asignar', 'cs' ) . '</em>';
        } else {
            $propietario = esc_html( $propietario );
        }

        // Estado / color
        $status_label = function_exists( 'cs_get_status_label' ) ? cs_get_status_label( $estado ) : $estado;
        $status_color = function_exists( 'cs_get_status_color' ) ? cs_get_status_color( $estado ) : '#000';

        // Preparar acciones por fila (se reinicia en cada iteración)
        $actions = array();

        // Ver
        $actions[] = '<a href="' . esc_url( admin_url( 'admin.php?page=cs_cupones&action=view&id=' . $coupon_id ) ) . '">' . esc_html__( 'Ver', 'cs' ) . '</a>';

        // Transferir (según estados permitidos)
        if ( in_array( $estado, array( 'pendiente_comercio', 'asignado_admin', 'asignado_email', 'asignado_user' ), true ) ) {
            $actions[] = '<a href="' . esc_url( admin_url( 'admin.php?page=cs_cupones&action=transfer&id=' . $coupon_id ) ) . '">' . esc_html__( 'Transferir', 'cs' ) . '</a>';
        }

        // Anular (si no está anulado/completado)
        if ( 'anulado' !== $estado && 'completado' !== $estado ) {
            // inline onclick as before (usa la función JS global cancelCoupon)
            $actions[] = '<a href="#" onclick="cancelCoupon(' . esc_attr( $coupon_id ) . '); return false;" style="color:#dc3232;">' . esc_html__( 'Anular', 'cs' ) . '</a>';
        }

        // Construir campo Tipo/Valor
        if ( $tipo === 'importe' ) {
            $valor_str        = '$' . number_format( $valor, 2 );
            $valor_rest_str   = '$' . number_format( $valor_restante, 2 );
            $tipo_valor_html  = esc_html__( 'Importe', 'cs' ) . '<br><strong>' . esc_html( $valor_str ) . '</strong><br><small>' . esc_html__( 'Restante:', 'cs' ) . ' ' . esc_html( $valor_rest_str ) . '</small>';
        } else {
            // unidad
            $tipo_valor_html = esc_html( ucfirst( $tipo ) ) . '<br><strong>' . esc_html( intval( $valor ) . ' ' . $unidad_desc ) . '</strong><br><small>' . esc_html( 'Restante:' ) . ' ' . esc_html( intval( $valor_restante ) . ' / ' . intval( $valor ) . ' ' . $unidad_desc ) . '</small>';
        }

        // Vigencia: mostrar fecha inicio y fin (formateadas)
        $vigencia_html = '';
        if ( ! empty( $fecha_inicio ) ) {
            $vigencia_html .= esc_html( date_i18n( 'd/m/Y', strtotime( $fecha_inicio ) ) );
        } else {
            $vigencia_html .= '-';
        }
        $vigencia_html .= '<br><small>' . esc_html__( 'hasta', 'cs' ) . ' ';
        if ( ! empty( $fecha_fin ) ) {
            $vigencia_html .= esc_html( date_i18n( 'd/m/Y', strtotime( $fecha_fin ) ) );
        } else {
            $vigencia_html .= '-';
        }
        $vigencia_html .= '</small>';

        // Imprimir fila
        echo '<tr>';
        // checkbox
        echo '<th scope="row" class="check-column"><input type="checkbox" name="coupon[]" value="' . esc_attr( $coupon_id ) . '"></th>';

        // Código
        echo '<td><strong>' . esc_html( $codigo_serie ) . '</strong><br><small>' . esc_html__( 'ID:', 'cs' ) . ' ' . esc_html( $coupon_id ) . '</small></td>';

        // Comercio
        echo '<td>' . esc_html( $comercio_name ?: esc_html__( 'Sin nombre', 'cs' ) ) . '</td>';

        // Tipo/Valor
        echo '<td>' . $tipo_valor_html . '</td>';

        // Propietario
        echo '<td>' . $propietario . '</td>';

        // Estado
        echo '<td><span style="color:' . esc_attr( $status_color ) . '; font-weight:bold;">' . esc_html( $status_label ) . '</span></td>';

        // Vigencia
        echo '<td>' . $vigencia_html . '</td>';

        // Acciones
        echo '<td>' . implode( ' | ', $actions ) . '</td>';

        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // JavaScript para acciones
    echo '<script>
    document.getElementById("cb-select-all-1").addEventListener("change", function() {
        const checkboxes = document.querySelectorAll("input[name=\"coupon[]\"]");
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
    
    document.getElementById("doaction").addEventListener("click", function() {
        const action = document.getElementById("bulk-action-selector-top").value;
        const selected = document.querySelectorAll("input[name=\"coupon[]\"]:checked");
        
        if (action === "-1") {
            alert("Selecciona una acción");
            return false;
        }
        
        if (selected.length === 0) {
            alert("Selecciona al menos un cupón");
            return false;
        }
        
        if (action === "cancel") {
            const reason = prompt("Razón para anular los cupones:");
            if (!reason) return false;
            
            if (confirm("¿Seguro que deseas anular " + selected.length + " cupón(es)?")) {
                bulkCancelCoupons(Array.from(selected).map(cb => cb.value), reason);
            }
        } else if (action === "transfer") {
            const newOwner = prompt("Email del nuevo propietario:");
            if (!newOwner) return false;
            
            if (confirm("¿Transferir " + selected.length + " cupón(es) a " + newOwner + "?")) {
                bulkTransferCoupons(Array.from(selected).map(cb => cb.value), newOwner);
            }
        }
    });
    
    function cancelCoupon(couponId) {
        const reason = prompt("Razón para anular el cupón:");
        if (!reason) return;
        
        if (confirm("¿Seguro que deseas anular este cupón?")) {
            jQuery.post(ajaxurl, {
                action: "cs_cancel_coupon",
                coupon_id: couponId,
                reason: reason,
                nonce: "' . wp_create_nonce('cs_coupon_action') . '"
            }).done(function(response) {
                if (response.success) {
                    alert("Cupón anulado exitosamente");
                    location.reload();
                } else {
                    alert("Error: " + response.data.message);
                }
            });
        }
    }
    
    function bulkCancelCoupons(couponIds, reason) {
        let completed = 0;
        let errors = 0;
        
        couponIds.forEach(function(couponId) {
            jQuery.post(ajaxurl, {
                action: "cs_cancel_coupon",
                coupon_id: couponId,
                reason: reason,
                nonce: "' . wp_create_nonce('cs_coupon_action') . '"
            }).done(function(response) {
                if (response.success) {
                    completed++;
                } else {
                    errors++;
                }
                
                if (completed + errors === couponIds.length) {
                    alert(`Proceso completado: ${completed} anulados, ${errors} errores`);
                    location.reload();
                }
            });
        });
    }
    
    function bulkTransferCoupons(couponIds, newOwner) {
        let completed = 0;
        let errors = 0;
        
        couponIds.forEach(function(couponId) {
            jQuery.post(ajaxurl, {
                action: "cs_transfer_coupon",
                coupon_id: couponId,
                new_owner: newOwner,
                type: "email",
                nonce: "' . wp_create_nonce('cs_coupon_action') . '"
            }).done(function(response) {
                if (response.success) {
                    completed++;
                } else {
                    errors++;
                }
                
                if (completed + errors === couponIds.length) {
                    alert(`Proceso completado: ${completed} transferidos, ${errors} errores`);
                    location.reload();
                }
            });
        });
    }
    </script>';
}

/**
 * Obtener etiqueta legible del estado
 */
function cs_get_status_label($status) {
    $labels = [
        'pendiente_comercio' => 'Pendiente',
        'asignado_admin' => 'Asignado (Admin)',
        'asignado_email' => 'Asignado (Email)',
        'asignado_user' => 'Asignado (Usuario)',
        'parcial' => 'Parcialmente usado',
        'completado' => 'Completado',
        'anulado' => 'Anulado',
        'vencido' => 'Vencido'
    ];
    
    return $labels[$status] ?? ucfirst($status);
}

/**
 * Renderizar vista detallada de cupón
 */
function cs_cupones_render_view() {
    $coupon_id = intval( $_GET['id'] ?? 0 );

    if ( ! $coupon_id ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'ID de cupón inválido.', 'cs' ) . '</p></div>';
        return;
    }

    global $wpdb;
    $table_coupons   = $wpdb->prefix . 'coupons';
    $table_proposals = $wpdb->prefix . 'coupon_proposals';
	$current_user_id = get_current_user_id();

    $coupon = $wpdb->get_row(
        $wpdb->prepare(
            "
        SELECT c.*, 
               u.display_name as comercio_nombre,
               p.nombre as propuesta_nombre,
               p.descripcion as propuesta_descripcion,
               owner.display_name as propietario_nombre,
               owner.user_email as propietario_email,
               used_by.display_name as usado_por_nombre
        FROM {$table_coupons} c
        LEFT JOIN {$wpdb->users} u ON c.comercio_id = u.ID
        LEFT JOIN {$table_proposals} p ON c.proposal_id = p.id
        LEFT JOIN {$wpdb->users} owner ON c.propietario_user_id = owner.ID
        LEFT JOIN {$wpdb->users} used_by ON c.usado_por = used_by.ID
        WHERE c.id = %d
        ",
            $coupon_id
        )
    );

    if ( ! $coupon ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Cupón no encontrado.', 'cs' ) . '</p></div>';
        return;
    }
	
    // Permitir acceso solo si:
    // 1) Es administrador (manage_options)
    // 2) Es propietario del cupón
    // 3) Es el comercio emisor del cupón

    if (
        ! current_user_can( 'manage_options' ) &&
        intval( $coupon->propietario_user_id ) !== $current_user_id &&
        intval( $coupon->comercio_id ) !== $current_user_id
    ) {
        wp_die( esc_html__( 'No tenés permisos para ver este cupón.', 'cs' ) );
    }

    // Prepara valores para mostrar
    $tipo               = isset( $coupon->tipo ) ? $coupon->tipo : '';
    $unidad_desc        = ! empty( $coupon->unidad_descripcion ) ? $coupon->unidad_descripcion : 'unidades';
    $valor              = isset( $coupon->valor ) ? floatval( $coupon->valor ) : 0;
    $valor_restante     = isset( $coupon->valor_restante ) ? floatval( $coupon->valor_restante ) : 0;
    $permite_uso_parcial = ! empty( $coupon->permite_uso_parcial );

    // URLs/acciones seguras
    $view_url     = esc_url( admin_url( 'admin.php?page=cs_cupones&action=view&id=' . $coupon->id ) );
    $transfer_url = esc_url( admin_url( 'admin.php?page=cs_cupones&action=transfer&id=' . $coupon->id ) );
    $list_url     = esc_url( admin_url( 'admin.php?page=cs_cupones' ) );

    // Nonces
    $nonce_action = wp_create_nonce( 'cs_coupon_action' );
    $nonce_redeem = wp_create_nonce( 'cs_coupon_redeem' );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Detalle del Cupón', 'cs' ) . '</h1>';

    echo '<div style="display:flex; gap:20px; margin:20px 0;">';

    // ------------------------
    // Información principal (lado izquierdo)
    // ------------------------
    echo '<div style="flex:2; background:#fff; padding:20px; border:1px solid #ddd; border-radius:4px;">';
    echo '<h2>' . esc_html__( 'Información General', 'cs' ) . '</h2>';
    echo '<table class="form-table">';

    // Código serie / secreto / estado / comercio / propuesta
    echo '<tr><th>' . esc_html__( 'Código de Serie:', 'cs' ) . '</th><td><strong style="font-size:16px;">' . esc_html( $coupon->codigo_serie ) . '</strong></td></tr>';
	
	$is_owner = intval($coupon->propietario_user_id) === $current_user_id;
	if($is_owner){
		echo '<tr><th>' . esc_html__( 'Código Secreto:', 'cs' ) . '</th><td><code style="background:#f0f0f0; padding:2px 6px;">' . esc_html( $coupon->codigo_secreto ) . '</code></td></tr>';
	}

    $status_color = function_exists( 'cs_get_status_color' ) ? cs_get_status_color( $coupon->estado ) : '#000';
    $status_label = function_exists( 'cs_get_status_label' ) ? cs_get_status_label( $coupon->estado ) : esc_html( $coupon->estado );

    echo '<tr><th>' . esc_html__( 'Estado:', 'cs' ) . '</th><td><span style="color:' . esc_attr( $status_color ) . '; font-weight:bold;">' . esc_html( $status_label ) . '</span></td></tr>';
    echo '<tr><th>' . esc_html__( 'Comercio:', 'cs' ) . '</th><td>' . esc_html( $coupon->comercio_nombre ?: esc_html__( 'Sin nombre', 'cs' ) ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Propuesta:', 'cs' ) . '</th><td>' . esc_html( $coupon->propuesta_nombre ?: 'N/A' ) . '</td></tr>';

    if ( ! empty( $coupon->propuesta_descripcion ) ) {
        echo '<tr><th>' . esc_html__( 'Descripción:', 'cs' ) . '</th><td>' . esc_html( $coupon->propuesta_descripcion ) . '</td></tr>';
    }

    // Tipo y valores
    echo '<tr><th>' . esc_html__( 'Tipo:', 'cs' ) . '</th><td>' . esc_html( ucfirst( $tipo ) ) . '</td></tr>';

    // Valor original
    echo '<tr><th>' . esc_html__( 'Valor Original:', 'cs' ) . '</th><td>';
    if ( $tipo === 'importe' ) {
        echo esc_html( '$' . number_format( $valor, 2 ) );
    } else {
        echo esc_html( intval( $valor ) . ' ' . $unidad_desc );
    }
    echo '</td></tr>';

    // Valor restante
    echo '<tr><th>' . esc_html__( 'Valor Restante:', 'cs' ) . '</th><td>';
    if ( $tipo === 'importe' ) {
        echo esc_html( '$' . number_format( $valor_restante, 2 ) ) . ' / ' . esc_html( '$' . number_format( $valor, 2 ) );
    } else {
        echo esc_html( intval( $valor_restante ) . ' / ' . intval( $valor ) . ' ' . $unidad_desc );
    }
    echo '</td></tr>';

    // Uso parcial
    echo '<tr><th>' . esc_html__( 'Uso Parcial:', 'cs' ) . '</th><td>' . ( $permite_uso_parcial ? esc_html__( 'Permitido', 'cs' ) : esc_html__( 'No permitido', 'cs' ) ) . '</td></tr>';

    // Fechas
    echo '<tr><th>' . esc_html__( 'Fecha Inicio:', 'cs' ) . '</th><td>' . esc_html( date_i18n( 'd/m/Y', strtotime( $coupon->fecha_inicio ) ) ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Fecha Fin:', 'cs' ) . '</th><td>' . esc_html( date_i18n( 'd/m/Y', strtotime( $coupon->fecha_fin ) ) ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Creado:', 'cs' ) . '</th><td>' . esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $coupon->created_at ) ) ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Actualizado:', 'cs' ) . '</th><td>' . esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $coupon->updated_at ) ) ) . '</td></tr>';

    // Propietario
    echo '<tr><th>' . esc_html__( 'Propietario:', 'cs' ) . '</th><td>';
    if ( ! empty( $coupon->propietario_nombre ) ) {
        echo esc_html( $coupon->propietario_nombre );
        if ( ! empty( $coupon->propietario_email ) ) {
            echo ' (' . esc_html( $coupon->propietario_email ) . ')';
        }
    } elseif ( ! empty( $coupon->propietario_email ) ) {
        echo esc_html( $coupon->propietario_email );
    } else {
        echo '<em>' . esc_html__( 'Sin asignar', 'cs' ) . '</em>';
    }
    echo '</td></tr>';

    // Uso y notas
    if ( ! empty( $coupon->fecha_ultimo_uso ) ) {
        echo '<tr><th>' . esc_html__( 'Último Uso:', 'cs' ) . '</th><td>' . esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $coupon->fecha_ultimo_uso ) ) ) . '</td></tr>';
    }

    if ( ! empty( $coupon->usado_por_nombre ) ) {
        echo '<tr><th>' . esc_html__( 'Usado por:', 'cs' ) . '</th><td>' . esc_html( $coupon->usado_por_nombre ) . '</td></tr>';
    }

    if ( ! empty( $coupon->notas_uso ) ) {
        echo '<tr><th>' . esc_html__( 'Notas:', 'cs' ) . '</th><td>' . esc_html( $coupon->notas_uso ) . '</td></tr>';
    }

    echo '</table>';
    echo '</div>'; // cierre info principal

    // ------------------------
    // Panel de acciones (lado derecho)
    // ------------------------
    echo '<div style="flex:1; background:#fff; padding:20px; border:1px solid #ddd; border-radius:4px; height:fit-content;">';
    echo '<h2>' . esc_html__( 'Acciones', 'cs' ) . '</h2>';

    $current_user = wp_get_current_user();
	$is_admin = current_user_can( 'manage_options' );

	// Transferir: solo si el estado permite y es propietario
	if ( $is_owner && in_array( $coupon->estado, array( 'pendiente_comercio', 'asignado_admin', 'asignado_email', 'asignado_user' ), true ) ) {
		echo '<p><a href="' . $transfer_url . '" class="button button-primary">' . esc_html__( 'Transferir Cupón', 'cs' ) . '</a></p>';
	}

	// Anular: solo si no anulado ni completado y si es admin
	if ( $is_admin && 'anulado' !== $coupon->estado && 'completado' !== $coupon->estado ) {
		echo '<p><button type="button" onclick="cs_cancelCoupon(' . esc_attr( $coupon->id ) . ')" class="button" style="color:#dc3232;">' . esc_html__( 'Anular Cupón', 'cs' ) . '</button></p>';
	}

    // Canjear (si corresponde)
    if ( in_array( $coupon->estado, array( 'asignado_user', 'asignado_email', 'parcial' ), true ) ) {
        echo '<hr>';
        echo '<h3>' . esc_html__( 'Canjear Cupón', 'cs' ) . '</h3>';
        echo '<form id="cs-redeem-form">';

        if ( $permite_uso_parcial && $valor_restante > 0 ) {
            // Si es importe, permitir step decimal
            $step = $tipo === 'importe' ? '0.01' : '1';
            echo '<p><label for="cs-redeem-amount">' . esc_html__( 'Cantidad a canjear:', 'cs' ) . '</label><br>';
            echo '<input type="number" id="cs-redeem-amount" name="amount" step="' . esc_attr( $step ) . '" min="0.01" max="' . esc_attr( $valor_restante ) . '" value="' . esc_attr( $valor_restante ) . '"></p>';
        }

        echo '<p><button type="button" onclick="cs_redeemCoupon(' . esc_attr( $coupon->id ) . ')" class="button button-secondary">' . esc_html__( 'Canjear', 'cs' ) . '</button></p>';
        echo '</form>';
    }

    echo '<hr>';
    echo '<p><a href="' . $list_url . '" class="button">' . esc_html__( '← Volver al Listado', 'cs' ) . '</a></p>';

    echo '</div>'; // cierre panel acciones

    echo '</div>'; // cierre contenedor flex
    echo '</div>'; // cierre wrap

    // ------------------------
    // JavaScript (inyección segura de datos)
    // ------------------------
    $js_vars = array(
        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
        'cancel_nonce'  => $nonce_action,
        'redeem_nonce'  => $nonce_redeem,
        'secret_code'   => $coupon->codigo_secreto,
        'coupon_id'     => intval( $coupon->id ),
    );
    $js_vars_json = wp_json_encode( $js_vars );
    ?>
    <script type="text/javascript">
    (function($){
        var CS = <?php echo $js_vars_json; ?>;

        window.cs_cancelCoupon = function(couponId) {
            var reason = prompt("Razón para anular el cupón:");
            if (!reason) {
                return;
            }
            if (!confirm("¿Seguro que deseas anular este cupón?")) {
                return;
            }

            $.post(CS.ajaxurl, {
                action: "cs_cancel_coupon",
                coupon_id: couponId,
                reason: reason,
                nonce: CS.cancel_nonce
            }, function(response){
                try {
                    if ( response && response.success ) {
                        alert("Cupón anulado exitosamente");
                        location.reload();
                    } else {
                        alert("Error: " + (response && response.data && response.data.message ? response.data.message : "Error desconocido"));
                    }
                } catch(e) {
                    alert("Error en la respuesta del servidor.");
                }
            }, 'json');
        };

        window.cs_redeemCoupon = function(couponId) {
            var amountField = document.getElementById('cs-redeem-amount');
            var amount = null;
            if (amountField) {
                amount = parseFloat(amountField.value);
                if (isNaN(amount) || amount <= 0) {
                    alert("Ingrese una cantidad válida.");
                    return;
                }
            }

            if (!confirm("¿Confirmar el canje de este cupón?")) {
                return;
            }

            $.post(CS.ajaxurl, {
                action: "cs_redeem_coupon",
                secret_code: CS.secret_code,
                amount: amount,
                nonce: CS.redeem_nonce
            }, function(response){
                try {
                    if ( response && response.success ) {
                        alert("Cupón canjeado exitosamente. Valor restante: " + (response.data && response.data.remaining_value ? response.data.remaining_value : '0'));
                        location.reload();
                    } else {
                        alert("Error: " + (response && response.data && response.data.message ? response.data.message : "Error desconocido"));
                    }
                } catch(e) {
                    alert("Error en la respuesta del servidor.");
                }
            }, 'json');
        };
    })(jQuery);
    </script>
    <?php
} // fin function


/**
 * Renderizar formulario de transferencia
 */
function cs_cupones_render_transfer() {
	$coupon_id = intval($_GET['id'] ?? 0);
	
    if (!$coupon_id) {
        echo '<div class="notice notice-error"><p>ID de cupón inválido.</p></div>';
        return;
    }
    
    // Procesar transferencia
	if ( $_SERVER['REQUEST_METHOD'] === 'POST'
		 && isset($_POST['transfer_nonce'])
		 && wp_verify_nonce($_POST['transfer_nonce'], 'transfer_coupon_' . $coupon_id)
	) {
		$transfer_type = sanitize_text_field( $_POST['transfer_type'] ?? 'email' );

		if ( $transfer_type === 'user_id' ) {
			$new_owner = intval( $_POST['new_owner_user'] ?? 0 );
			if ( $new_owner <= 0 ) {
				echo '<div class="notice notice-error"><p>ID de usuario inválido.</p></div>';
			} else {
				try {
					cs_transfer_coupon( $coupon_id, $new_owner, 'user_id' );
					echo '<div class="notice notice-success"><p>Cupón transferido exitosamente.</p></div>';
				} catch ( Exception $e ) {
					echo '<div class="notice notice-error"><p>Error: ' . esc_html( $e->getMessage() ) . '</p></div>';
				}
			}
		} else { // email
			$new_owner = sanitize_email( $_POST['new_owner_email'] ?? '' );
			if ( ! is_email( $new_owner ) ) {
				echo '<div class="notice notice-error"><p>Email inválido.</p></div>';
			} else {
				try {
					cs_transfer_coupon( $coupon_id, $new_owner, 'email' );
					echo '<div class="notice notice-success"><p>Cupón transferido exitosamente.</p></div>';
				} catch ( Exception $e ) {
					echo '<div class="notice notice-error"><p>Error: ' . esc_html( $e->getMessage() ) . '</p></div>';
				}
			}
		}
	}
    
    global $wpdb;
    $coupon = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, u.display_name as comercio_nombre 
         FROM {$wpdb->prefix}coupons c
         LEFT JOIN {$wpdb->users} u ON c.comercio_id = u.ID 
         WHERE c.id = %d",
        $coupon_id
    ));
    
    if (!$coupon) {
        echo '<div class="notice notice-error"><p>Cupón no encontrado.</p></div>';
        return;
    }
    
    echo '<div class="wrap">';
    echo '<h1>Transferir Cupón</h1>';
    
    echo '<div class="card" style="max-width: 600px;">';
    echo '<h2>Cupón: ' . esc_html($coupon->codigo_serie) . '</h2>';
    echo '<p><strong>Comercio:</strong> ' . esc_html($coupon->comercio_nombre) . '</p>';
    echo '<p><strong>Estado actual:</strong> ' . cs_get_status_label($coupon->estado) . '</p>';
    
    if ($coupon->propietario_email) {
        echo '<p><strong>Propietario actual:</strong> ' . esc_html($coupon->propietario_email) . '</p>';
    }
    
    echo '<form method="post">';
    wp_nonce_field('transfer_coupon_' . $coupon_id, 'transfer_nonce');
    
    echo '<table class="form-table">';
	// Obtener usuarios elegibles (ajusta roles según tu política)
	$usuarios = get_users([
		'role__in' => ['comercio'], // <-- ajustar
		'orderby' => 'display_name',
		'order' => 'ASC'
	]);

	if (in_array('comercio', wp_get_current_user()->roles)) {
		// Para comercio: solo email, sin radio
		echo '<input type="hidden" name="transfer_type" value="email">';
		echo '<tr>';
		echo '<th><label for="new_owner_email">Nuevo propietario (email):</label></th>';
		echo '<td><input type="email" id="new_owner_email" name="new_owner_email" class="regular-text" required></td>';
		echo '</tr>';
	} else {
		// Para admin: radio + email y select
		echo '<tr>';
		echo '<th><label for="transfer_type">Tipo de transferencia:</label></th>';
		echo '<td>';
		echo '<input type="radio" id="type_email" name="transfer_type" value="email" checked>';
		echo '<label for="type_email">Por Email</label><br>';
		echo '<input type="radio" id="type_user" name="transfer_type" value="user_id">';
		echo '<label for="type_user">Por Usuario</label>';
		echo '</td>';
		echo '</tr>';

		echo '<tr class="transfer_email_row">';
		echo '<th><label for="new_owner_email">Nuevo propietario (email):</label></th>';
		echo '<td><input type="email" id="new_owner_email" name="new_owner_email" class="regular-text" required></td>';
		echo '</tr>';

		echo '<tr class="transfer_user_row" style="display:none;">';
		echo '<th><label for="new_owner_user">Nuevo propietario (usuario):</label></th>';
		echo '<td>';
		echo '<select id="new_owner_user" name="new_owner_user">';
		echo '<option value="">-- Seleccione un usuario --</option>';
		foreach ($usuarios as $u) {
			echo '<option value="' . esc_attr($u->ID) . '">' . esc_html($u->display_name . ' (' . $u->user_email . ')') . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
	}

	echo '<tr><td colspan="2"><p class="description">Selecciona un usuario o ingresa un email según el tipo elegido.</p></td></tr>';
	echo '</table>';

    
    echo '<p class="submit">';
    echo '<input type="submit" class="button button-primary" value="Transferir Cupón">';
    echo ' <a href="' . admin_url('admin.php?page=cs_cupones&action=view&id=' . $coupon_id) . '" class="button">Cancelar</a>';
    echo '</p>';
    
    echo '</form>';
    echo '</div>';
    
    echo '</div>';
	echo "
		<script>
		document.addEventListener('DOMContentLoaded', function(){
			const typeEmail = document.getElementById('type_email');
			const typeUser  = document.getElementById('type_user');
			const inputEmail = document.getElementById('new_owner_email');
			const selectUser = document.getElementById('new_owner_user');

			function toggleFields() {
				if (typeEmail.checked) {
					inputEmail.style.display = '';
					inputEmail.required = true;
					selectUser.style.display = 'none';
					selectUser.required = false;
				} else {
					inputEmail.style.display = 'none';
					inputEmail.required = false;
					selectUser.style.display = '';
					selectUser.required = true;
				}
			}

			typeEmail.addEventListener('change', toggleFields);
			typeUser.addEventListener('change', toggleFields);
			// Inicial
			toggleFields();
		});
		</script>

	";
}

/**
 * Página de emisión manual
 */
function cs_emision_manual_page() {
    if (isset($_GET['ejecutar']) && $_GET['ejecutar'] === '1') {
        if (wp_verify_nonce($_GET['_wpnonce'], 'cs_manual_emission')) {
            cs_process_daily_coupon_emission();
            echo '<div class="notice notice-success"><p>Emisión manual ejecutada correctamente.</p></div>';
        }
    }
    
    echo '<div class="wrap">';
    echo '<h1>Emisión Manual de Cupones</h1>';
    
    echo '<div class="card">';
    echo '<h2>Ejecutar Emisión Manual</h2>';
    echo '<p>Esta función ejecuta el mismo proceso que se ejecuta diariamente de forma automática.</p>';
    echo '<p><strong>Advertencia:</strong> Esta acción procesará todas las propuestas aprobadas y emitirá los cupones correspondientes según sus cronogramas.</p>';
    
    $manual_url = wp_nonce_url(
        admin_url('admin.php?page=cs_emision_manual&ejecutar=1'),
        'cs_manual_emission'
    );
    
    echo '<p>';
    echo '<a href="' . esc_url($manual_url) . '" class="button button-primary" onclick="return confirm(\'¿Confirmar la emisión manual de cupones?\')">Ejecutar Emisión Manual</a>';
    echo '</p>';
    echo '</div>';
    
    // Estadísticas de la última emisión
    $stats = cs_get_coupon_manager()->get_coupon_stats();
    
    echo '<div class="card">';
    echo '<h2>Estadísticas Generales</h2>';
    cs_render_coupon_stats($stats);
    echo '</div>';
    
    echo '</div>';
}

/**
 * Mostrar mensajes de estado (en admin)
 */
function cs_show_coupon_messages() {
    // Mensajes permitidos y su tipo (success|error)
    $messages = array(
        'transferred' => array( 'text' => 'Cupón transferido correctamente.', 'type' => 'success' ),
        'cancelled'   => array( 'text' => 'Cupón anulado correctamente.',     'type' => 'success' ),
        'redeemed'    => array( 'text' => 'Cupón canjeado correctamente.',     'type' => 'success' ),
        'error'       => array( 'text' => 'Ha ocurrido un error.',            'type' => 'error' ),
    );

    foreach ( $messages as $key => $meta ) {
        // Solo mostrar si el parámetro GET existe y es truthy
        if ( isset( $_GET[ $key ] ) && $_GET[ $key ] !== '' ) {
            $type = ( $meta['type'] === 'error' ) ? 'error' : 'success';
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr( $type ),
                esc_html( $meta['text'] )
            );
        }
    }
}