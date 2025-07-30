<?php
function cs_comercios_page() {
    if ( ! current_user_can('manage_options') ) return;

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'add':
            cs_comercios_render_form_add();
            break;
        case 'edit':
            cs_comercios_render_form_edit();
            break;
        default:
            cs_comercios_render_list();
            break;
    }
}

function cs_comercios_render_list() {
    echo '<div class="wrap">';
    echo '<h1>Comercios</h1>';

    if (isset($_GET['created'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Comercio creado correctamente.</p></div>';
    }

	if (isset($_GET['updated'])) {
		echo '<div class="notice notice-success is-dismissible"><p>Comercio actualizado correctamente.</p></div>';
	}
	
	if (isset($_GET['deleted'])) {
		echo '<div class="notice notice-success is-dismissible"><p>Comercio eliminado correctamente.</p></div>';
	}	

    echo '<a href="' . admin_url('admin.php?page=cs_comercios&action=add') . '" class="button button-primary">Agregar Comercio</a>';

    $args = [
        'role'    => 'comercio',
        'orderby' => 'display_name',
        'order'   => 'ASC'
    ];
    $comercios = get_users($args);

    if ( empty($comercios) ) {
        echo '<p>No hay comercios registrados.</p>';
        echo '</div>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Nombre</th><th>Email</th><th>Acciones</th></tr></thead><tbody>';

    foreach ( $comercios as $comercio ) {
        $edit_url = admin_url('admin.php?page=cs_comercios&action=edit&user_id=' . $comercio->ID);
        $delete_url = wp_nonce_url(
			admin_url('admin.php?page=cs_comercios&action=delete&user_id=' . $comercio->ID),
			'cs_delete_comercio_' . $comercio->ID
		);
        echo '<tr>';
        echo '<td>' . esc_html($comercio->display_name) . '</td>';
        echo '<td>' . esc_html($comercio->user_email) . '</td>';
        echo '<td>';
        echo '<a href="' . esc_url($edit_url) . '">Editar</a> | ';
        echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'¿Estás seguro de que deseas eliminar este comercio?\')">Eliminar</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function cs_comercios_render_form_add() {
    echo '<div class="wrap">';
    echo '<h1>Agregar Comercio</h1>';
	
	if (isset($_GET['errors'])) {
		$errors = json_decode(urldecode($_GET['errors']), true);
		if (is_array($errors)) {
			echo '<div class="notice notice-error"><ul>';
			foreach ($errors as $error) {
				echo '<li>' . esc_html($error) . '</li>';
			}
			echo '</ul></div>';
		}
	}
	
    echo '<form method="post" action="' . admin_url('admin.php?page=cs_comercios&action=create') . '">';
    wp_nonce_field('cs_add_comercio', 'cs_add_comercio_nonce');
	
	$nombre_value = isset($_GET['nombre']) ? esc_attr(urldecode($_GET['nombre'])) : '';
	$email_value = isset($_GET['email']) ? esc_attr(urldecode($_GET['email'])) : '';

    echo '<table class="form-table">';
    echo '<tr><th><label for="nombre">Nombre</label></th><td><input type="text" name="nombre" id="nombre" class="regular-text" required value="' . $nombre_value . '"></td></tr>';
	echo '<tr><th><label for="email">Email</label></th><td><input type="email" name="email" id="email" class="regular-text" required value="' . $email_value . '"></td></tr>';
    echo '<tr><th><label for="password">Contraseña</label></th><td><input type="password" name="password" id="password" class="regular-text" required></td></tr>';
    echo '</table>';

    submit_button('Crear Comercio');
    echo '</form>';
    echo '</div>';
}

function cs_comercios_render_form_edit() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Permisos insuficientes.');
    }

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $user = get_userdata($user_id);

    if ( ! $user || ! in_array('comercio', (array) $user->roles) ) {
        wp_die('Comercio no válido.');
    }

    // Mensajes
    if ( isset($_GET['updated']) ) {
        echo '<div class="notice notice-success is-dismissible"><p>Comercio actualizado correctamente.</p></div>';
    }

    $errors = [];
    if ( isset($_GET['errors']) ) {
        $errors = json_decode(urldecode($_GET['errors']), true);
        if ( is_array($errors) && count($errors) > 0 ) {
            echo '<div class="notice notice-error"><ul>';
            foreach ( $errors as $error ) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    // Valores previos o actuales
    $nombre = $_GET['nombre'] ?? $user->display_name;
    $email = $_GET['email'] ?? $user->user_email;
    $username = $user->user_login;

    ?>
    <div class="wrap">
        <h1>Editar Comercio</h1>
        <form method="post" action="<?php echo admin_url('admin.php?page=cs_comercios&action=update'); ?>">
            <?php wp_nonce_field('cs_edit_comercio_' . $user_id, 'cs_edit_comercio_nonce'); ?>
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">

            <table class="form-table">
                <tr>
					<th>
						<label for="username">Nombre de usuario</label>
						<span class="dashicons dashicons-editor-help" title="Este nombre de usuario se asignó al crear el comercio y no puede modificarse."></span>
					</th>
					<td>
						<input type="text" id="username" class="regular-text" value="<?php echo esc_attr($username); ?>" readonly>
					</td>
				</tr>
                <tr>
                    <th><label for="nombre">Nombre</label></th>
                    <td><input type="text" name="nombre" id="nombre" class="regular-text" value="<?php echo esc_attr($nombre); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="email">Email</label></th>
                    <td><input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr($email); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="password">Contraseña (dejar vacía para no cambiar)</label></th>
                    <td><input type="password" name="password" id="password" class="regular-text"></td>
                </tr>
            </table>

            <?php submit_button('Actualizar Comercio'); ?>
        </form>
    </div>
    <?php
}