<?php
require_once CS_PLUGIN_PATH . 'includes/propuestas_list_class.php';

function cs_propuestas_page() {
    if ( ! current_user_can('manage_options') ) return;

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'add':
            cs_propuestas_render_form_add();
            break;
		case 'view':
			cs_propuestas_render_view();
			break;
        default:
            cs_propuestas_render_list();
            break;
    }
}

function cs_propuestas_render_form_add() {
    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>Nueva Propuesta de Cupones</h1>';

	// Mostrar errores si los hay
	if (!empty($_SESSION['cs_errores'])) {
		echo '<div class="notice notice-error"><ul>';
		foreach ($_SESSION['cs_errores'] as $error) {
			echo '<li>' . esc_html($error) . '</li>';
		}
		echo '</ul></div>';
	}

	// Guardar temporalmente los datos viejos
	$old = $_SESSION['cs_old_input'] ?? [];

    echo '<form method="post" action="' . admin_url('admin.php?page=cs_propuestas&action=create') . '">';
    wp_nonce_field('cs_add_propuesta', 'cs_add_propuesta_nonce');

    // Comercios disponibles
    $comercios = get_users(['role' => 'comercio', 'orderby' => 'display_name', 'order' => 'ASC']);

    echo '<table class="form-table">';

    // Nombre y descripción
    echo '<tr><th><label for="nombre">Nombre de la propuesta</label></th>';
    echo '<td><input type="text" name="nombre" id="nombre" required class="regular-text" value="' . esc_attr($old['nombre'] ?? '') . '"></td></tr>';

    echo '<tr><th><label for="descripcion">Descripción</label></th>';
    echo '<td><textarea name="descripcion" id="descripcion" rows="4" class="large-text">' . esc_textarea($old['descripcion'] ?? '') . '</textarea></td></tr>';

    // Comercio
    echo '<tr><th><label for="comercio_id">Comercio</label></th><td><select name="comercio_id" required>';
    foreach ($comercios as $c) {
		$selected = (isset($old['comercio_id']) && $old['comercio_id'] == $c->ID) ? 'selected' : '';
		echo '<option value="' . esc_attr($c->ID) . '" ' . $selected . '>' . esc_html($c->display_name) . '</option>';
	}

    // Tipo de cupón
    echo '<tr><th><label for="tipo_cupon">Tipo de cupón</label></th>';
    echo '<td><select name="tipo_cupon" id="tipo_cupon" required>
        <option value="importe" ' . selected($old['tipo_cupon'] ?? '', 'importe', false) . '>Importe ($)</option>
		<option value="unidad" ' . selected($old['tipo_cupon'] ?? '', 'unidad', false) . '>Unidad</option>
    </select></td></tr>';
	echo '<tr id="unidad_descripcion_row" style="display:none;">
        <th><label for="unidad_descripcion">Descripción del servicio/producto</label></th>
        <td><input type="text" name="unidad_descripcion" id="unidad_descripcion" placeholder="Ej: Menú del día" value="' . esc_attr($old['unidad_descripcion'] ?? '') . '"></td>
      </tr>';

    // Valor
	echo '<tr><th><label for="valor">Valor por cupón</label></th>';
	echo '<td><div style="display:flex; align-items:center;">';
	echo '<span id="valor-prefix" style="margin-right:4px;">$</span>';
	echo '<input type="number" name="valor" id="valor" step="0.01" min="0.01" value="' . esc_attr($old['valor'] ?? 1) . '" required>';
	echo '<span id="valor-suffix" style="margin-left:4px; display:none;">unidades</span>';
	echo '</div></td></tr>';



    // Uso parcial
    echo '<tr><th><label for="uso_parcial">¿Permite uso parcial?</label></th>';
    echo '<td><input type="checkbox" name="uso_parcial" id="uso_parcial" value="1" ' . checked(!empty($old['uso_parcial']), true, false) . '> Sí</td></tr>';

    // Inicio de vigencia
    echo '<tr><th><label for="fecha_inicio">Inicio de vigencia</label></th>';
    echo '<td><input type="date" name="fecha_inicio" id="fecha_inicio" required></td></tr>';

    // Duración de validez
    echo '<tr><th><label for="duracion_validez">Duración de validez de cada cupón</label></th>';
    echo '<td><input type="number" name="duracion_validez" min="1" value="1" required>
        <select name="unidad_validez">
            <option value="dias">Días</option>
            <option value="semanas">Semanas</option>
            <option value="meses" selected>Meses</option>
        </select></td></tr>';

    // Cantidad de ciclos
    echo '<tr><th><label for="cantidad_ciclos">Cantidad de ciclos</label></th>';
    echo '<td><input type="number" name="cantidad_ciclos" min="1" value="12" required></td></tr>';

    // Frecuencia de emisión
    echo '<tr><th><label for="frecuencia_emision">Frecuencia de emisión</label></th>';
    echo '<td><input type="number" name="frecuencia_emision" min="1" value="1" required>
        <select name="unidad_frecuencia">
            <option value="dias">Días</option>
            <option value="semanas">Semanas</option>
            <option value="meses" selected>Meses</option>
        </select></td></tr>';

    // Cupones por ciclo
    echo '<tr><th><label for="cupones_por_ciclo">Cantidad de cupones por ciclo</label></th>';
    echo '<td><input type="number" name="cupones_por_ciclo" min="1" value="1" required></td></tr>';

    echo '</table>';

    // Al final del formulario, antes del cierre
	echo '<p>';
	submit_button('Crear Propuesta', 'primary', 'submit', false);
	echo '&nbsp;';
	submit_button('Previsualizar Propuesta', 'secondary', 'preview', false, ['id' => 'preview-button']);
	echo '</p>';

	echo '<div id="preview-container" style="margin-top:30px; display:none;">';
	echo '<h2>Vista previa de la propuesta</h2>';
	echo '<div id="preview-content" style="border:1px solid #ccc; padding:20px; background:#fff;"></div>';
	echo '</div>';

    echo '</form>';
	
	unset($_SESSION['cs_errores'], $_SESSION['cs_old_input']);
	
    echo '</div>';
	echo '
	<script>
	document.getElementById("preview-button").addEventListener("click", function(e) {
		e.preventDefault();

		const nombre = document.getElementById("nombre").value;
		const descripcion = document.getElementById("descripcion").value;
		const comercio = document.querySelector("select[name=\'comercio_id\']").selectedOptions[0].text;
		const tipo = document.getElementById("tipo_cupon").value;
		const valor = document.getElementById("valor").value;
		const uso_parcial = document.getElementById("uso_parcial").checked ? "Sí" : "No";
		const fecha_inicio = document.getElementById("fecha_inicio").value;
		const duracion = parseInt(document.querySelector("input[name=\'duracion_validez\']").value);
		const unidad_duracion = document.querySelector("select[name=\'unidad_validez\']").value;
		const ciclos = parseInt(document.querySelector("input[name=\'cantidad_ciclos\']").value);
		const frecuencia = parseInt(document.querySelector("input[name=\'frecuencia_emision\']").value);
		const unidad_frecuencia = document.querySelector("select[name=\'unidad_frecuencia\']").value;
		const cupones_por_ciclo = parseInt(document.querySelector("input[name=\'cupones_por_ciclo\']").value);
		const unidad_descripcion = document.getElementById("unidad_descripcion").value || "(sin descripción)";

		if (!fecha_inicio || isNaN(ciclos) || isNaN(frecuencia) || isNaN(duracion) || isNaN(cupones_por_ciclo)) {
			alert("Por favor completa todos los campos requeridos.");
			return;
		}

		const preview = document.getElementById("preview-content");
		const previewContainer = document.getElementById("preview-container");
		preview.innerHTML = "";

		const formatter = new Intl.DateTimeFormat("es-AR");

		// Función para sumar períodos
		function sumarFecha(base, cantidad, unidad) {
			const fecha = new Date(base);
			switch(unidad) {
				case "dias":    fecha.setDate(fecha.getDate() + cantidad); break;
				case "semanas": fecha.setDate(fecha.getDate() + cantidad * 7); break;
				case "meses":   fecha.setMonth(fecha.getMonth() + cantidad); break;
			}
			return fecha;
		}

		let tabla = "<table style=\'width:100%; border-collapse: collapse;\'><thead><tr>" +
			"<th style=\'border:1px solid #ccc; padding:8px;\'>#</th>" +
			"<th style=\'border:1px solid #ccc; padding:8px;\'>Inicio</th>" +
			"<th style=\'border:1px solid #ccc; padding:8px;\'>Vencimiento</th>" +
			"<th style=\'border:1px solid #ccc; padding:8px;\'>Valor</th>" +
			"</tr></thead><tbody>";

		const fechaBase = new Date(fecha_inicio);

		let count = 1;
		for (let ciclo = 0; ciclo < ciclos; ciclo++) {
			const inicioCiclo = sumarFecha(fechaBase, ciclo * frecuencia, unidad_frecuencia);
			for (let i = 0; i < cupones_por_ciclo; i++) {
				const inicio = new Date(inicioCiclo);
				const vencimiento = sumarFecha(inicio, duracion, unidad_duracion);

				tabla += "<tr>" +
					"<td style=\'border:1px solid #ccc; padding:6px; text-align:center;\'>" + count++ + "</td>" +
					"<td style=\'border:1px solid #ccc; padding:6px;\'>" + formatter.format(inicio) + "</td>" +
					"<td style=\'border:1px solid #ccc; padding:6px;\'>" + formatter.format(vencimiento) + "</td>" +
					"<td style=\'border:1px solid #ccc; padding:6px;\'>" + (tipo === "importe" ? "$" + valor : singularizarSiEsNecesario(parseInt(valor), "unidades") + " de " + unidad_descripcion) + "</td>" +
				"</tr>";
			}
		}

		tabla += "</tbody></table>";

		// Armamos el resumen
		preview.innerHTML = `
			<p><strong>Nombre:</strong> ${nombre}</p>
			<p><strong>Comercio:</strong> ${comercio}</p>
			<p><strong>Descripción:</strong> ${descripcion || "-"}</p>
			<p><strong>Tipo:</strong> ${tipo}${tipo === "unidad" ? " (" + unidad_descripcion + ")" : ""}</p>
			<p><strong>Valor por cupón:</strong> ${tipo === "importe" ? "$" + valor : singularizarSiEsNecesario(parseInt(valor), "unidades") + " de " + unidad_descripcion}</p>
			<p><strong>¿Uso parcial?:</strong> ${uso_parcial}</p>
			<p><strong>Fecha de inicio:</strong> ${formatter.format(fechaBase)}</p>
			<p><strong>Frecuencia de emisión:</strong> Cada ${singularizarSiEsNecesario(frecuencia, unidad_frecuencia)}</p>
			<p><strong>Cupones por ciclo:</strong> ${cupones_por_ciclo}</p>
			<p><strong>Duración de cada cupón:</strong> ${singularizarSiEsNecesario(duracion, unidad_duracion)}</p>
			<p><strong>Cantidad total de cupones:</strong> ${(ciclos * cupones_por_ciclo)}</p>
			<hr>
			<h3>Detalle de cupones:</h3>
			${tabla}
		`;

		previewContainer.style.display = "block";
	});
	</script>';
	echo '
	<script>
	document.getElementById("tipo_cupon").addEventListener("change", function() {
		const value = this.value;
		const row = document.getElementById("unidad_descripcion_row");
		const prefix = document.getElementById("valor-prefix");
		const suffix = document.getElementById("valor-suffix");

		if (value === "unidad") {
			row.style.display = "table-row";
			prefix.style.display = "none";
			suffix.style.display = "inline";
			document.getElementById("valor").step = "1";
			document.getElementById("valor").min = "1";
		} else {
			row.style.display = "none";
			suffix.style.display = "none";
			prefix.style.display = "inline";
			document.getElementById("unidad_descripcion").value = "";
			document.getElementById("valor").step = "0.01";
			document.getElementById("valor").min = "0.01";
		}
	});
	// Setear fecha por defecto (mañana)
	document.addEventListener("DOMContentLoaded", function () {
		const fechaInput = document.getElementById("fecha_inicio");
		if (fechaInput) {
			const mañana = new Date();
			mañana.setDate(mañana.getDate() + 1);
			fechaInput.valueAsDate = mañana;
		}
	});
	// Mostrar elementos específicos para "unidad" al cargar
	document.addEventListener("DOMContentLoaded", function () {
		document.getElementById("tipo_cupon").dispatchEvent(new Event("change"));
	});
	</script>';
	echo '
	<script>
	function singularizarSiEsNecesario(cantidad, palabraPlural) {
		const mapaSingular = {
			"días": "día",
			"semanas": "semana",
			"meses": "mes",
			"unidades": "unidad",
			"cupones": "cupón"
		};

		if (cantidad === 1 && palabraPlural in mapaSingular) {
			return `${cantidad} ${mapaSingular[palabraPlural]}`;
		}
		return `${cantidad} ${palabraPlural}`;
	}
	</script>
	';

}

function cs_propuestas_render_view() {
	global $wpdb;

	$id = intval($_GET['id'] ?? 0);
	if (!$id) {
		echo '<div class="notice notice-error"><p>ID inválido.</p></div>';
		return;
	}

	$tabla = $wpdb->prefix . 'coupon_proposals';
	$propuesta = $wpdb->get_row("SELECT * FROM $tabla WHERE id = $id");

	if (!$propuesta) {
		echo '<div class="notice notice-error"><p>Propuesta no encontrada.</p></div>';
		return;
	}

	// Obtener nombre del comercio
	$comercio = get_user_by('id', $propuesta->comercio_id);
	$nombre_comercio = $comercio ? $comercio->display_name : 'Desconocido';

	// Helpers locales
	$cs_format_plural = function($n, $unidad) {
		$mapa = ['días' => 'día', 'semanas' => 'semana', 'meses' => 'mes', 'unidades' => 'unidad', 'cupones' => 'cupón'];
		return ($n == 1) ? "1 " . ($mapa[$unidad] ?? $unidad) : "$n $unidad";
	};

	$sumar_fecha = function($fecha, $cantidad, $unidad) {
		$f = new DateTime($fecha);
		switch ($unidad) {
			case 'dias':    $f->modify("+$cantidad days"); break;
			case 'semanas': $f->modify("+".($cantidad * 7)." days"); break;
			case 'meses':   $f->modify("+$cantidad months"); break;
		}
		return $f;
	};

	echo '<div class="wrap">';
	echo '<h1>Vista de propuesta</h1>';

	echo '<p><strong>Nombre:</strong> ' . esc_html($propuesta->nombre) . '</p>';
	echo '<p><strong>Comercio:</strong> ' . esc_html($nombre_comercio) . '</p>';
	echo '<p><strong>Descripción:</strong> ' . esc_html($propuesta->descripcion) . '</p>';
	
	$estado_texto = '';

	if ($propuesta->estado === 'aprobado') {
		$estado_texto = 'Aprobado';
	} elseif ($propuesta->estado === 'pendiente') {
		$estado_texto = 'Pendiente de aprobación por ' . (cs_propuesta_creada_por_admin($propuesta) ? 'el comercio' : 'el administrador');
	} else {
		$estado_texto = ucfirst($propuesta->estado); // Fallback por si hay otro estado
	}

	echo '<p><strong>Estado:</strong> ' . esc_html($estado_texto) . '</p>';
	
	echo '<p><strong>Tipo:</strong> ' . esc_html($propuesta->tipo_cupon) . ($propuesta->unidad_descripcion ? " (" . esc_html($propuesta->unidad_descripcion) . ")" : '') . '</p>';

	echo '<p><strong>Valor por cupón:</strong> ';
	if ($propuesta->tipo_cupon === 'importe') {
		echo '$' . number_format($propuesta->valor, 2);
	} else {
		echo $cs_format_plural($propuesta->valor, 'unidades') . ' de ' . esc_html($propuesta->unidad_descripcion);
	}
	echo '</p>';

	echo '<p><strong>¿Uso parcial?:</strong> ' . ($propuesta->uso_parcial ? 'Sí' : 'No') . '</p>';
	echo '<p><strong>Fecha de inicio:</strong> ' . date_i18n('d/m/Y', strtotime($propuesta->fecha_inicio)) . '</p>';
	echo '<p><strong>Frecuencia de emisión:</strong> Cada ' . $cs_format_plural($propuesta->frecuencia_emision, $propuesta->unidad_frecuencia) . '</p>';
	echo '<p><strong>Cupones por ciclo:</strong> ' . intval($propuesta->cupones_por_ciclo) . '</p>';
	echo '<p><strong>Duración de cada cupón:</strong> ' . $cs_format_plural($propuesta->duracion_validez, $propuesta->unidad_validez) . '</p>';
	echo '<p><strong>Cantidad total de cupones:</strong> ' . ($propuesta->cantidad_ciclos * $propuesta->cupones_por_ciclo) . '</p>';

	echo '<hr><h2>Detalle de cupones</h2>';

	echo '<table class="widefat striped">';
	echo '<thead><tr><th>#</th><th>Inicio</th><th>Vencimiento</th><th>Valor</th></tr></thead><tbody>';

	$fechaBase = $propuesta->fecha_inicio;
	$count = 1;

	for ($ciclo = 0; $ciclo < $propuesta->cantidad_ciclos; $ciclo++) {
		$inicioCiclo = $sumar_fecha($fechaBase, $ciclo * $propuesta->frecuencia_emision, $propuesta->unidad_frecuencia);
		for ($i = 0; $i < $propuesta->cupones_por_ciclo; $i++) {
			$inicio = clone $inicioCiclo;
			$vencimiento = $sumar_fecha($inicio->format('Y-m-d'), $propuesta->duracion_validez, $propuesta->unidad_validez);

			$valor = $propuesta->tipo_cupon === 'importe'
				? '$' . number_format($propuesta->valor, 2)
				: $cs_format_plural($propuesta->valor, 'unidades') . ' de ' . esc_html($propuesta->unidad_descripcion);

			echo '<tr>';
			echo '<td>' . $count++ . '</td>';
			echo '<td>' . $inicio->format('d/m/Y') . '</td>';
			echo '<td>' . $vencimiento->format('d/m/Y') . '</td>';
			echo '<td>' . $valor . '</td>';
			echo '</tr>';
		}
	}

	echo '</tbody></table>';
	
	echo '<p style="margin-top: 20px;">';

	if ($propuesta->estado === 'pendiente' && cs_usuario_debe_aprobar_propuesta($propuesta)) {
		$approve_url = admin_url('admin.php?page=cs_propuestas&action=approve&id=' . intval($propuesta->id));
		echo '<a href="' . admin_url('admin.php?page=cs_propuestas&action=approve&id=' . intval($id)) . '" class="button button-primary" onclick="return confirm(\'¿Seguro que quieres aprobar esta propuesta?\');">Aprobar propuesta</a>';
	}

	echo '<a href="' . admin_url('admin.php?page=cs_propuestas') . '" class="button">Volver al listado</a>';
	echo '</p>';

	echo '</div>';
}

function cs_propuestas_render_list() {
    $proposals_table = new Proposals_List_Table();
    $proposals_table->process_actions();
    
    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Propuestas de Cupones</h1>';
    
    // Botón para agregar nuevo registro
    echo '<a href="' . admin_url('admin.php?page=cs_propuestas_add') . '" class="page-title-action">Agregar Nueva</a>';
    echo '<hr class="wp-header-end">';
    
    // Mostrar mensajes de estado
    if (isset($_GET['approved'])) {
        echo '<div class="notice notice-success"><p>Propuesta aprobada correctamente.</p></div>';
    }
    if (isset($_GET['deleted'])) {
        echo '<div class="notice notice-success"><p>Propuesta eliminada correctamente.</p></div>';
    }
    if (isset($_GET['mass_approved'])) {
        echo '<div class="notice notice-success"><p>' . intval($_GET['mass_approved']) . ' propuestas aprobadas correctamente.</p></div>';
    }
    if (isset($_GET['mass_deleted'])) {
        echo '<div class="notice notice-success"><p>' . intval($_GET['mass_deleted']) . ' propuestas eliminadas correctamente.</p></div>';
    }
    if (isset($_GET['mass_errors'])) {
        echo '<div class="notice notice-error"><p>Errores: ' . esc_html(urldecode($_GET['mass_errors'])) . '</p></div>';
    }
    
    // Formulario de búsqueda
    $proposals_table->search_box('Buscar propuestas', 'search_id');
    
    // Mostrar la tabla
    echo '<form method="post" id="propuestas-filter">';
    $proposals_table->prepare_items();
    $proposals_table->display();
    echo '</form>';
    
    echo '</div>';
    
    // Estilos para los botones de acción
    echo '<style>
        .actions-container { display: flex; gap: 5px; }
        .actions-container .button { display: inline-block; margin: 0; }
        .status-approved { color: #46b450; font-weight: bold; }
        .status-rejected { color: #dc3232; font-weight: bold; }
    </style>';
}


