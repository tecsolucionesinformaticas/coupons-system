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
		creado_por BIGINT UNSIGNED NOT NULL DEFAULT 0
	) $charset_collate;

    CREATE TABLE $table_coupons (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        proposal_id BIGINT UNSIGNED DEFAULT NULL,
        comercio_id BIGINT UNSIGNED NOT NULL,
        tipo ENUM('importe', 'unidad') NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        fecha_inicio DATE NOT NULL,
        fecha_fin DATE NOT NULL,
        permite_uso_parcial BOOLEAN NOT NULL DEFAULT 0,
        estado ENUM(
            'pendiente_comercio',
            'asignado_admin',
            'asignado_email',
            'asignado_user',
            'canjeado',
            'parcial'
        ) DEFAULT 'pendiente_comercio',
        propietario_email VARCHAR(255) DEFAULT NULL,
        propietario_user_id BIGINT UNSIGNED DEFAULT NULL,
        codigo_hash CHAR(64) NOT NULL UNIQUE,
        qr_token CHAR(64) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;
    ";

    dbDelta($sql);
}