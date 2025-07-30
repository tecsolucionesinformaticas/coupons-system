<?php
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class Proposals_List_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'coupon_proposals';
        
        parent::__construct([
            'singular' => 'propuesta',
            'plural'   => 'propuestas',
            'ajax'     => false
        ]);
    }
    
    // Configura las columnas
    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'nombre'    => 'Nombre',
            'descripcion' => 'Descripción',
            'tipo_cupon' => 'Tipo de Cupón',
            'valor'     => 'Valor',
            'fecha_inicio' => 'Fecha Inicio',
            'estado'    => 'Estado',
            'actions'   => 'Acciones'
        ];
    }
    
    // Columnas que pueden ser ordenadas (ahora con múltiples columnas)
    public function get_sortable_columns() {
        return [
            'nombre' => ['nombre', false],
            'tipo_cupon' => ['tipo_cupon', false],
            'valor' => ['valor', false],
            'fecha_inicio' => ['fecha_inicio', true],
            'estado' => ['estado', false]
        ];
    }
    
    // Datos para la tabla con mejor paginación
    public function prepare_items() {
        global $wpdb;
        
        // Columnas
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Paginación mejorada
        $per_page = $this->get_items_per_page('proposals_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Ordenación múltiple
        $orderby = 'id';
        $order = 'DESC';
        
        if (isset($_GET['orderby'])) {
            $orderby = sanitize_sql_orderby($_GET['orderby']);
            $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        }
        
        // Búsqueda mejorada
        $where = '';
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);
            $where = $wpdb->prepare(" WHERE (nombre LIKE %s OR descripcion LIKE %s OR tipo_cupon LIKE %s)", 
                                   "%{$search}%", "%{$search}%", "%{$search}%");
        }
        
        // Filtro por estado
        if (isset($_REQUEST['estado_filter']) && !empty($_REQUEST['estado_filter'])) {
            $estado = sanitize_text_field($_REQUEST['estado_filter']);
            $where .= $where ? " AND estado = '{$estado}'" : " WHERE estado = '{$estado}'";
        }
        
        // Consulta principal optimizada
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$this->table_name}{$where}");
        
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        // Configura la paginación mejorada
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
            'orderby'     => $orderby,
            'order'       => $order
        ]);
    }
    
    // Render de columnas con mejor formato
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'nombre':
                return '<strong>' . esc_html($item[$column_name]) . '</strong>';
            case 'descripcion':
                return esc_html(wp_trim_words($item[$column_name], 10));
            case 'tipo_cupon':
                return ucfirst(esc_html($item[$column_name]));
            case 'valor':
                return number_format($item[$column_name], 2);
            case 'fecha_inicio':
                return date_i18n(get_option('date_format'), strtotime($item[$column_name]));
            case 'estado':
                $estado = $item[$column_name];
                $class = '';
                if ($estado === 'aprobado') $class = 'status-approved';
                if ($estado === 'rechazado') $class = 'status-rejected';
                return '<span class="' . $class . '">' . ucfirst(esc_html($estado)) . '</span>';
            default:
                return esc_html(print_r($item, true));
        }
    }
    
    // Columna de acciones siempre visibles
    public function column_actions($item) {
        $actions = [
            'view' => sprintf(
                '<a href="?page=%s&action=%s&propuesta=%s" class="button view">Ver</a>',
                esc_attr($_REQUEST['page']),
                'view',
                absint($item['id'])
            ),
            'approve' => sprintf(
                '<a href="?page=%s&action=%s&propuesta=%s" class="button approve">Aprobar</a>',
                esc_attr($_REQUEST['page']),
                'approve',
                absint($item['id'])
            ),
            'delete' => sprintf(
                '<a href="?page=%s&action=%s&propuesta=%s" class="button delete" onclick="return confirm(\'¿Estás seguro?\')">Eliminar</a>',
                esc_attr($_REQUEST['page']),
                'delete',
                absint($item['id'])
            )
        ];
        
        // Mostramos siempre los botones (sin hover)
        return '<div class="actions-container">' . 
               implode(' ', $actions) . 
               '</div>';
    }
    
    // Columna checkbox para acciones masivas
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="propuestas[]" value="%s" />',
            $item['id']
        );
    }
    
    // Opciones para acciones masivas
    public function get_bulk_actions() {
        return [
            'approve' => 'Aprobar seleccionados',
            'delete' => 'Eliminar seleccionados'
        ];
    }
    
    // Filtros por estado con mejor estilo
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $estado = isset($_REQUEST['estado_filter']) ? $_REQUEST['estado_filter'] : '';
            ?>
            <div class="alignleft actions">
                <label for="estado_filter" class="screen-reader-text">Filtrar por estado</label>
                <select name="estado_filter" id="estado_filter" style="float:none;">
                    <option value="">Todos los estados</option>
                    <option value="pendiente" <?php selected($estado, 'pendiente'); ?>>Pendiente</option>
                    <option value="aprobado" <?php selected($estado, 'aprobado'); ?>>Aprobado</option>
                    <option value="rechazado" <?php selected($estado, 'rechazado'); ?>>Rechazado</option>
                </select>
                <?php submit_button('Filtrar', 'secondary', 'filter_action', false, ['style' => 'margin-left: 10px;']); ?>
            </div>
            <?php
        }
    }
    
    // Procesar acciones integrado con tus funciones
    public function process_actions() {
        // Acciones individuales
        if (isset($_GET['action']) && isset($_GET['propuesta'])) {
            $action = $_GET['action'];
            $id = absint($_GET['propuesta']);
            
            try {
                switch ($action) {
                    case 'approve':
                        cs_propuestas_handle_approve($id);
                        break;
                    case 'delete':
                        cs_propuestas_handle_delete($id);
                        break;
                }
            } catch (Exception $e) {
                wp_die($e->getMessage());
            }
        }
        
        // Acciones masivas (usando tu handler)
        if (isset($_POST['action']) || isset($_POST['action2'])) {
            $action = isset($_POST['action']) && $_POST['action'] != -1 ? $_POST['action'] : $_POST['action2'];
            
            if (isset($_POST['propuestas']) && is_array($_POST['propuestas'])) {
                check_admin_referer('bulk-' . $this->_args['plural']);
                
                // Preparamos los datos para tu handler
                $_POST['cs_ids'] = $_POST['propuestas'];
                $_POST['cs_mass_action_nonce'] = wp_create_nonce('cs_mass_action');
                $_POST['action'] = $action;
                
                // Llamamos a tu handler
                cs_propuestas_mass_action_handler();
            }
        }
    }
}