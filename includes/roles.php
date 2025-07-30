<?php

function cs_register_roles() {
    add_role(
        'comercio',
        'Comercio',
        [
            'read' => true,
        ]
    );
}
