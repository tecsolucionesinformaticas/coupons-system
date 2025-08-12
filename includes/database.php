<?php 
function cs_tickets_create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $table_proposals = $wpdb->prefix . 'coupon_proposals';
    $table_coupons   = $wpdb->prefix . 'coupons';

    $sql = "
	CREATE TABLE $table_proposals (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		comercio_id BIGINT UNSIGNED NOT NULL,
		nombre VARCHAR(255) NOT NULL,
		descripcion TEXT,
		tipo_cupon ENUM('importe', 'unidad') NOT NULL,
		unidad_descripcion VARCHAR(255),
		valor DECIMAL(10,2) NOT NULL,
		uso_parcial BOOLEAN NOT NULL DEFAULT 0,
		fecha_inicio DATE NOT NULL,
		duracion_validez INT NOT NULL,
		unidad_validez ENUM('dias', 'semanas', 'meses') NOT NULL,
		cantidad_ciclos INT NOT NULL,
		frecuencia_emision INT NOT NULL,
		unidad_frecuencia ENUM('dias', 'semanas', 'meses') NOT NULL,
		cupones_por_ciclo INT NOT NULL,
		estado ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		creado_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
		INDEX idx_comercio_estado (comercio_id, estado),
		INDEX idx_fecha_estado (fecha_inicio, estado)
	) $charset_collate;

    CREATE TABLE $table_coupons (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        proposal_id BIGINT UNSIGNED DEFAULT NULL,
        comercio_id BIGINT UNSIGNED NOT NULL,
        tipo ENUM('importe', 'unidad') NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        valor_restante DECIMAL(10,2) NOT NULL,
        unidad_descripcion VARCHAR(255) DEFAULT NULL,
        fecha_inicio DATE NOT NULL,
        fecha_fin DATE NOT NULL,
        permite_uso_parcial BOOLEAN NOT NULL DEFAULT 0,
        estado ENUM(
            'asignado_admin',
            'asignado_email',
            'asignado_user',
            'canjeado',
            'parcial',
            'completado',
            'anulado',
            'vencido'
        ) DEFAULT 'asignado_admin',
        propietario_email VARCHAR(255) DEFAULT NULL,
        propietario_user_id BIGINT UNSIGNED DEFAULT NULL,
        codigo_serie CHAR(7) NOT NULL UNIQUE,
        codigo_secreto CHAR(4) NOT NULL UNIQUE,
        codigo_hash CHAR(64) NOT NULL UNIQUE,
        qr_token CHAR(64) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        usado_por BIGINT UNSIGNED DEFAULT NULL,
        fecha_ultimo_uso DATETIME DEFAULT NULL,
        notas_uso TEXT DEFAULT NULL,
        
        INDEX idx_proposal (proposal_id),
        INDEX idx_comercio (comercio_id),
        INDEX idx_estado (estado),
        INDEX idx_propietario_email (propietario_email),
        INDEX idx_propietario_user (propietario_user_id),
        INDEX idx_fecha_fin (fecha_fin),
        INDEX idx_codigo_serie (codigo_serie),
        INDEX idx_codigo_secreto (codigo_secreto),
        INDEX idx_fechas_validez (fecha_inicio, fecha_fin),
        
        FOREIGN KEY (proposal_id) REFERENCES $table_proposals(id) ON DELETE SET NULL,
        FOREIGN KEY (comercio_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE,
        FOREIGN KEY (propietario_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL,
        FOREIGN KEY (usado_por) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
    ) $charset_collate;
    ";

    dbDelta($sql);
    
    // Agregar trigger para actualizar updated_at (si MySQL lo soporta)
    $wpdb->query("
        CREATE TRIGGER IF NOT EXISTS tr_coupons_updated_at 
        BEFORE UPDATE ON $table_coupons 
        FOR EACH ROW 
        SET NEW.updated_at = CURRENT_TIMESTAMP
    ");
}