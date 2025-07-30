<?php

function singularizar_si_es_necesario($cantidad, $palabraPlural) {
	$mapaSingular = [
		'días' => 'día',
		'semanas' => 'semana',
		'meses' => 'mes',
		'unidades' => 'unidad',
		'cupones' => 'cupón'
	];

	if ($cantidad == 1 && array_key_exists($palabraPlural, $mapaSingular)) {
		return "$cantidad " . $mapaSingular[$palabraPlural];
	}
	return "$cantidad $palabraPlural";
}

function cs_plural_es($cantidad, $singular, $plural = null) {
	if ($cantidad == 1) {
		return "1 $singular";
	}
	return "$cantidad " . ($plural ?: $singular . 's');
}

function cs_formatear_cantidad_cupones($cupones, $frecuencia_cantidad, $frecuencia_unidad, $ciclos) {
	// Mapa singular
	$singular_map = [
		'días'    => 'día',
		'semanas' => 'semana',
		'meses'   => 'mes',
		'cupones' => 'cupón'
	];

	$unidad = strtolower(trim($frecuencia_unidad));
	$unidad_singular = $singular_map[$unidad] ?? $unidad;

	$frecuencia_txt = ($frecuencia_cantidad == 1)
		? "por $unidad_singular"
		: "cada $frecuencia_cantidad $unidad";

	$cupones_txt = cs_plural_es($cupones, 'cupón', 'cupones');

	$total_duracion = $frecuencia_cantidad * $ciclos;
	$total_txt = "durante " . cs_plural_es($total_duracion, $unidad_singular, $unidad);

	return "$cupones_txt $frecuencia_txt $total_txt";
}

function cs_usuario_debe_aprobar_propuesta($propuesta) {
	if (!is_object($propuesta)) return false;

	$current_user_id = get_current_user_id();
	$es_admin = current_user_can('manage_options');
	$lo_creo_un_admin = user_can($propuesta->creado_por, 'manage_options');

	return (
		($lo_creo_un_admin && $current_user_id === intval($propuesta->comercio_id)) || // Lo creó un admin, debe aprobar el comercio
		(!$lo_creo_un_admin && $es_admin)                                              // Lo creó el comercio, debe aprobar el admin
	);
}

function cs_propuesta_creada_por_admin($propuesta) {
	return is_object($propuesta) && user_can($propuesta->creado_por, 'manage_options');
}