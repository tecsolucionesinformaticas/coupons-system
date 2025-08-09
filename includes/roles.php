<?php

function cs_register_roles() {
	$admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('access_cs_plugin');
        $admin->add_cap('view_propuestas');
        $admin->add_cap('manage_comercios');
    }
	
    // Crear rol 'comercio' si no existe
    if (!get_role('comercio')) {
        add_role(
            'comercio',
            'Comercio',
            [
                'read' => true,
                'access_cs_plugin' => true,
                'view_propuestas' => true,
            ]
        );
    } else {
        // En caso ya exista, asegurarse de que tenga las capacidades necesarias
        $role = get_role('comercio');
        if ($role) {
            $role->add_cap('access_cs_plugin');
            $role->add_cap('view_propuestas');
        }
    }
}
add_action('init', 'cs_register_roles');

function cs_add_capabilities_to_roles() {
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap('view_propuestas')) {
        $admin->add_cap('view_propuestas');
    }

    $comercio = get_role('comercio');
    if ($comercio && !$comercio->has_cap('view_propuestas')) {
        $comercio->add_cap('view_propuestas');
    }
}
add_action('init', 'cs_add_capabilities_to_roles');