<?php
function cs_comercios_handle_create() {
    if (
        ! isset($_POST['cs_add_comercio_nonce']) ||
        ! wp_verify_nonce($_POST['cs_add_comercio_nonce'], 'cs_add_comercio')
    ) {
        wp_redirect(admin_url('admin.php?page=cs_comercios&action=add&error=nonce'));
        exit;
    }

    $nombre = sanitize_text_field($_POST['nombre']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];

    $errors = [];

    // Validar email
    if (username_exists($email) || email_exists($email)) {
        $errors[] = 'Este email ya está registrado.';
    }

    // Validar nombre duplicado
    $comercios = get_users([
        'role' => 'comercio',
        'search' => $nombre,
        'search_columns' => ['display_name'],
    ]);
    foreach ($comercios as $comercio) {
        if (strtolower($comercio->display_name) === strtolower($nombre)) {
            $errors[] = 'Este nombre de comercio ya está en uso.';
            break;
        }
    }

    // Validar contraseña
    if (strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una letra y un número.';
    }

    if (!empty($errors)) {
        $query = http_build_query([
            'page' => 'cs_comercios',
            'action' => 'add',
            'errors' => urlencode(json_encode($errors)),
            'nombre' => urlencode($nombre),
            'email' => urlencode($email)
        ]);
        wp_redirect(admin_url('admin.php?' . $query));
        exit;
    }

    // Crear usuario
    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        $query = http_build_query([
            'page' => 'cs_comercios',
            'action' => 'add',
            'errors' => urlencode(json_encode(['Error al crear el usuario.']))
        ]);
        wp_redirect(admin_url('admin.php?' . $query));
        exit;
    }

    // Actualizar display_name
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $nombre,
		'nickname' => $nombre,
		'first_name' => $nombre,
		'user_nicename' => sanitize_title($nombre),
    ]);

    // Asignar rol comercio (IMPORTANTE)
    $user = new WP_User($user_id);
    $user->set_role('comercio');

    wp_redirect(admin_url('admin.php?page=cs_comercios&created=1'));
    exit;
}


function cs_comercios_handle_update() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Permisos insuficientes.');
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (
        ! isset($_POST['cs_edit_comercio_nonce']) ||
        ! wp_verify_nonce($_POST['cs_edit_comercio_nonce'], 'cs_edit_comercio_' . $user_id)
    ) {
        wp_die('Nonce inválido.');
    }

    $user = get_userdata($user_id);

    if ( ! $user || ! in_array('comercio', (array) $user->roles) ) {
        wp_die('Comercio no válido.');
    }

    $nombre = sanitize_text_field($_POST['nombre']);
    $email = sanitize_email($_POST['email']);
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $errors = [];

    // Validar email duplicado
    $existing_user = get_user_by('email', $email);
    if ($existing_user && $existing_user->ID !== $user_id) {
        $errors[] = 'Este email ya está en uso por otro usuario.';
    }

    // Validar nombre duplicado (solo entre otros comercios)
    $comercios = get_users([
        'role' => 'comercio',
        'exclude' => [$user_id],
        'search' => $nombre,
        'search_columns' => ['display_name'],
    ]);
    foreach ($comercios as $comercio) {
        if (strtolower($comercio->display_name) === strtolower($nombre)) {
            $errors[] = 'Este nombre de comercio ya está en uso.';
            break;
        }
    }

    // Validar contraseña (si fue ingresada)
    if (! empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra y un número.';
        }
    }

    // Si hay errores, redirigir con mensajes y conservar valores
    if (!empty($errors)) {
        $query = http_build_query([
            'page' => 'cs_comercios',
            'action' => 'edit',
            'user_id' => $user_id,
            'errors' => urlencode(json_encode($errors)),
            'nombre' => urlencode($nombre),
            'email' => urlencode($email)
        ]);
        wp_redirect(admin_url('admin.php?' . $query));
        exit;
    }

    // Actualizar usuario
    $args = [
        'ID' => $user_id,
        'display_name' => $nombre,
		'nickname' => $nombre,
		'first_name' => $nombre,
		'user_nicename' => sanitize_title($nombre),
        'user_email' => $email,
    ];

    if (! empty($password)) {
        $args['user_pass'] = $password;
    }

    $result = wp_update_user($args);

    if ( is_wp_error($result) ) {
        wp_die('Error al actualizar el usuario.');
    }

    wp_redirect(admin_url('admin.php?page=cs_comercios&updated=1'));
    exit;
}

function cs_comercios_handle_delete() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('No tenés permisos para realizar esta acción.');
    }

    $user_id = intval($_GET['user_id']);

    if (
        ! isset($_GET['_wpnonce']) ||
        ! wp_verify_nonce($_GET['_wpnonce'], 'cs_delete_comercio_' . $user_id)
    ) {
        wp_die('Nonce inválido. Acción no autorizada.');
    }

    $user = get_userdata($user_id);

    if ( ! $user ) {
        wp_die('Usuario no encontrado.');
    }

    if ( ! in_array('comercio', (array) $user->roles) ) {
        wp_die('Este usuario no es un comercio y no puede ser eliminado desde aquí.');
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user_id);

    wp_redirect(admin_url('admin.php?page=cs_comercios&deleted=1'));
    exit;
}