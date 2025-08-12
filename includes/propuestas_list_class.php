<?php
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class Proposals_List_Table extends WP_List_Table {
    
    private $table_name;
	private $comercio_id = null;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'coupon_proposals';
        
        parent::__construct([
            'singular' => 'propuesta',
            'plural'   => 'propuestas',
            'ajax'     => false
        ]);
    }
    
	public function set_comercio_id($user_id) {
		$this->comercio_id = intval($user_id);
	}
	
    // Configura las columnas
    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'nombre'       => 'Nombre',
            'comercio'     => 'Comercio',
            'descripcion'  => 'Descripción',
            'tipo_cupon'   => 'Tipo',
            'valor'        => 'Valor',
            'fecha_inicio' => 'Fecha Inicio',
            'estado'       => 'Estado',
            'creado_por'   => 'Creado por',
            'actions'      => 'Acciones'
        ];
    }
    
    // Columnas ordenables
    public function get_sortable_columns() {
        return [
            'nombre'       => ['nombre', false],
            'comercio'     => ['comercio_nombre', false],
            'tipo_cupon'   => ['tipo_cupon', false],
            'valor'        => ['valor', false],
            'fecha_inicio' => ['fecha_inicio', true],
            'estado'       => ['estado', false],
            'creado_por'   => ['creado_por', false]
        ];
    }
    
    // Datos para la tabla con filtros mejorados
    public function prepare_items() {
        global $wpdb;
        
        // Columnas
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Paginación
        $per_page = $this->get_items_per_page('proposals_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Ordenación
        $orderby = 'p.id';
        $order = 'DESC';
        
        if (isset($_GET['orderby'])) {
            $orderby_param = sanitize_text_field($_GET['orderby']);
            $valid_orderby = [
                'nombre' => 'p.nombre',
                'comercio_nombre' => 'u.display_name',
                'tipo_cupon' => 'p.tipo_cupon',
                'valor' => 'p.valor',
                'fecha_inicio' => 'p.fecha_inicio',
                'estado' => 'p.estado',
                'creado_por' => 'p.creado_por'
            ];
            
            if (isset($valid_orderby[$orderby_param])) {
                $orderby = $valid_orderby[$orderby_param];
                $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
            }
        }
        
        // Construir WHERE con múltiples filtros
        $where_conditions = [];
        $where_params = [];
		
		// Si el usuario tiene rol comercio (y no es admin), filtrar por su propio ID
		$current_user = wp_get_current_user();
		error_log("comercio_id: " . $this->comercio_id);
		if ($this->comercio_id != null) {
			$where_conditions[] = "p.comercio_id = %d";
			$where_params[] = $this->comercio_id;
		}
        
        // Búsqueda por texto
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);
            $where_conditions[] = "(p.nombre LIKE %s OR p.descripcion LIKE %s OR u.display_name LIKE %s)";
            $where_params[] = "%{$search}%";
            $where_params[] = "%{$search}%";
            $where_params[] = "%{$search}%";
        }
        
        // Filtro por estado
        if (isset($_REQUEST['filtro_estado']) && !empty($_REQUEST['filtro_estado'])) {
            $estado = sanitize_text_field($_REQUEST['filtro_estado']);
            $where_conditions[] = "p.estado = %s";
            $where_params[] = $estado;
        }
        
        // Filtro por tipo
        if (isset($_REQUEST['filtro_tipo']) && !empty($_REQUEST['filtro_tipo'])) {
            $tipo = sanitize_text_field($_REQUEST['filtro_tipo']);
            $where_conditions[] = "p.tipo_cupon = %s";
            $where_params[] = $tipo;
        }
        
        // Filtro por comercio
        if (isset($_REQUEST['filtro_comercio']) && !empty($_REQUEST['filtro_comercio'])) {
            $comercio_id = intval($_REQUEST['filtro_comercio']);
            $where_conditions[] = "p.comercio_id = %d";
            $where_params[] = $comercio_id;
        }
        
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Base query con todos los JOIN primero, luego WHERE
		$joins = "
			LEFT JOIN {$wpdb->users} u ON p.comercio_id = u.ID
			LEFT JOIN {$wpdb->users} creator ON p.creado_por = creator.ID
		";

		$base_query = "
			FROM {$this->table_name} p
			{$joins}
			{$where_sql}
		";
        
        // Contar total
        $count_query = "SELECT COUNT(*) " . $base_query;
        if (!empty($where_params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_params));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }
        
        // Query principal
        $main_query = "
            SELECT p.*, u.display_name AS comercio_nombre, 
                   creator.display_name AS creado_por_nombre
            {$base_query}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $query_params = array_merge($where_params, [$per_page, $offset]);
        
        if (!empty($query_params)) {
            $this->items = $wpdb->get_results($wpdb->prepare($main_query, $query_params), ARRAY_A);
        } else {
            $this->items = $wpdb->get_results($main_query . $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);
        }
        
        // Configurar paginación
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
    
    // Render de columnas mejorado
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'nombre':
                return '<strong>' . esc_html($item['nombre']) . '</strong>';
            case 'comercio':
                return esc_html($item['comercio_nombre'] ?: 'Sin comercio');
            case 'descripcion':
                return esc_html(wp_trim_words($item['descripcion'], 8));
            case 'tipo_cupon':
                return ucfirst(esc_html($item['tipo_cupon']));
            case 'valor':
                if ($item['tipo_cupon'] === 'importe') {
                    return '$' . number_format($item['valor'], 2);
                } else {
                    $desc = $item['unidad_descripcion'] ?: 'unidades';
                    return intval($item['valor']) . ' ' . esc_html($desc);
                }
            case 'fecha_inicio':
                return date_i18n('d/m/Y', strtotime($item['fecha_inicio']));
            case 'estado':
                $estado = $item['estado'];
                $class = '';
                $texto = ucfirst($estado);
                
                if ($estado === 'aprobado') {
                    $class = 'status-approved';
                } elseif ($estado === 'rechazado') {
                    $class = 'status-rejected';
                } elseif ($estado === 'pendiente') {
                    $class = 'status-pending';
                    // Determinar quién debe aprobar
                    $propuesta_obj = (object) $item;
                    if (cs_propuesta_creada_por_admin($propuesta_obj)) {
                        $texto = 'Pendiente (comercio)';
                    } else {
                        $texto = 'Pendiente (admin)';
                    }
                }
                
                return '<span class="' . $class . '">' . esc_html($texto) . '</span>';
            case 'creado_por':
                return esc_html($item['creado_por_nombre'] ?: 'Desconocido');
            default:
                return esc_html($item[$column_name] ?? '');
        }
    }
    
    // Columna de acciones corregida
    public function column_actions($item) {
        $propuesta_id = intval($item['id']);
        $propuesta_obj = (object) $item;
        
        $actions = [];
        
        // Botón Ver (siempre disponible)
        $actions['view'] = sprintf(
            '<a href="%s" class="button button-small">Ver</a>',
            esc_url(admin_url("admin.php?page=cs_propuestas&action=view&id={$propuesta_id}"))
        );
        
        // Botón Aprobar (solo si está pendiente y el usuario puede aprobar)
        if ($item['estado'] === 'pendiente' && cs_usuario_debe_aprobar_propuesta($propuesta_obj)) {
            $actions['approve'] = sprintf(
                '<a href="%s" class="button button-primary button-small" onclick="return confirm(\'¿Seguro que deseas aprobar esta propuesta?\')">Aprobar</a>',
                esc_url(wp_nonce_url(
                    admin_url("admin.php?page=cs_propuestas&action=approve&id={$propuesta_id}"),
                    'cs_approve_propuesta_' . $propuesta_id
                ))
            );
        }
		
		// Botón Rechazar (solo si está pendiente y usuario NO es el creador)
		$current_user_id = get_current_user_id();
		if ($item['estado'] === 'pendiente' && intval($item['creado_por']) !== $current_user_id) {
			$actions['reject'] = sprintf(
				'<a href="%s" class="button button-small button-danger" onclick="return confirm(\'¿Seguro que deseas rechazar esta propuesta?\')">Rechazar</a>',
				esc_url(wp_nonce_url(
					admin_url("admin.php?page=cs_propuestas&action=reject&id={$propuesta_id}"),
					'cs_reject_propuesta_' . $propuesta_id
				))
			);
		}
        
        // Botón Eliminar (solo admin y pendiente)
        if (current_user_can('manage_options') && $item['estado'] === 'pendiente') {
            $actions['delete'] = sprintf(
                '<a href="%s" class="button button-small cs-delete-link" data-propuesta-id="%d" data-nombre="%s" data-comercio="%s">Eliminar</a>',
                esc_url(wp_nonce_url(
                    admin_url("admin.php?page=cs_propuestas&action=delete&id={$propuesta_id}"),
                    'cs_delete_propuesta_' . $propuesta_id
                )),
                $propuesta_id,
                esc_attr($item['nombre']),
                esc_attr($item['comercio_nombre'])
            );
        }
        
        return '<div class="actions-container">' . implode(' ', $actions) . '</div>';
    }
    
    // Columna checkbox
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="cs_ids[]" value="%s" />',
            $item['id']
        );
    }
    
    // Acciones masivas
    public function get_bulk_actions() {
        $actions = [];
        
		$actions['approve'] = 'Aprobar seleccionadas';
		$actions['reject'] = 'Rechazar seleccionadas';
		$actions['delete'] = 'Eliminar seleccionadas';
        
        return $actions;
    }
    
    // Filtros mejorados que conservan los valores
    public function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        $filtro_estado = $_GET['filtro_estado'] ?? '';
        $filtro_tipo = $_GET['filtro_tipo'] ?? '';
        $filtro_comercio = $_GET['filtro_comercio'] ?? '';
        
        $estados = [
            '' => 'Todos los estados',
            'pendiente' => 'Pendiente',
            'aprobado' => 'Aprobado',
            'rechazado' => 'Rechazado'
        ];
        
        $tipos = [
            '' => 'Todos los tipos',
            'importe' => 'Importe',
            'unidad' => 'Unidad'
        ];
        
        // Obtener comercios
        $comercios = get_users([
            'role' => 'comercio',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);
        
        ?>
        <div class="alignleft actions" style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
            <!-- Filtro Estado -->
            <div>
                <label for="filtro_estado" style="margin-right: 5px;">Estado:</label>
                <select name="filtro_estado" id="filtro_estado">
                    <?php foreach ($estados as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($filtro_estado, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filtro Tipo -->
            <div>
                <label for="filtro_tipo" style="margin-right: 5px;">Tipo:</label>
                <select name="filtro_tipo" id="filtro_tipo">
                    <?php foreach ($tipos as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($filtro_tipo, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filtro Comercio -->
            <div>
                <label for="filtro_comercio" style="margin-right: 5px;">Comercio:</label>
                <select name="filtro_comercio" id="filtro_comercio">
                    <option value="">Todos los comercios</option>
                    <?php foreach ($comercios as $comercio): ?>
                        <option value="<?php echo esc_attr($comercio->ID); ?>" <?php selected($filtro_comercio, $comercio->ID); ?>>
                            <?php echo esc_html($comercio->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Botón Filtrar -->
            <div>
                <?php submit_button('Filtrar', 'secondary', 'filter_action', false); ?>
                <?php if ($filtro_estado || $filtro_tipo || $filtro_comercio): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cs_propuestas')); ?>" class="button">Limpiar filtros</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- JavaScript para preservar filtros -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar filtros actuales a todos los enlaces de paginación
            const currentFilters = {
                filtro_estado: '<?php echo esc_js($filtro_estado); ?>',
                filtro_tipo: '<?php echo esc_js($filtro_tipo); ?>',
                filtro_comercio: '<?php echo esc_js($filtro_comercio); ?>'
            };
            
            // Actualizar enlaces de paginación
            document.querySelectorAll('.tablenav-pages a').forEach(link => {
                const url = new URL(link.href);
                Object.entries(currentFilters).forEach(([key, value]) => {
                    if (value) url.searchParams.set(key, value);
                });
                link.href = url.toString();
            });
            
            // Confirmar eliminación individual
            document.querySelectorAll('.cs-delete-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const nombre = this.dataset.nombre;
                    const comercio = this.dataset.comercio;
                    if (confirm(`¿Seguro que deseas eliminar la propuesta "${nombre}" del comercio "${comercio}"?\n\nEsta acción no se puede deshacer.`)) {
                        window.location.href = this.href;
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // NO implementamos process_actions aquí para evitar conflictos
    // El routing se maneja en routes.php
}