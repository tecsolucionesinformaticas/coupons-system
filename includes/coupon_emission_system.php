<?php
/**
 * Sistema de Emisión Automática de Cupones
 */

// Activar el cron job al activar el plugin
register_activation_hook(__FILE__, 'cs_schedule_coupon_emission');
register_deactivation_hook(__FILE__, 'cs_unschedule_coupon_emission');

function cs_schedule_coupon_emission() {
    if (!wp_next_scheduled('cs_daily_coupon_emission')) {
        wp_schedule_event(time(), 'daily', 'cs_daily_coupon_emission');
    }
}

function cs_unschedule_coupon_emission() {
    wp_clear_scheduled_hook('cs_daily_coupon_emission');
}

// Hook para la emisión diaria
add_action('cs_daily_coupon_emission', 'cs_process_daily_coupon_emission');

/**
 * Función principal de emisión diaria de cupones
 */
function cs_process_daily_coupon_emission() {
    global $wpdb;
    
    $proposals_table = $wpdb->prefix . 'coupon_proposals';
    $coupons_table = $wpdb->prefix . 'coupons';
    
    // Obtener propuestas aprobadas
    $proposals = $wpdb->get_results("
        SELECT * FROM {$proposals_table} 
        WHERE estado = 'aprobado' 
        AND fecha_inicio <= CURDATE()
    ");
    
    $log_entries = [];
    $total_emitted = 0;
    
    foreach ($proposals as $proposal) {
        try {
            $emitted = cs_process_proposal_emission($proposal);
            $total_emitted += $emitted;
            
            if ($emitted > 0) {
                $log_entries[] = "Propuesta {$proposal->id} ({$proposal->nombre}): {$emitted} cupones emitidos";
            }
        } catch (Exception $e) {
            $log_entries[] = "Error en propuesta {$proposal->id}: " . $e->getMessage();
            error_log("Error emitiendo cupones para propuesta {$proposal->id}: " . $e->getMessage());
        }
    }
    
    // Log del proceso
    if (!empty($log_entries)) {
        $log_message = "Emisión diaria de cupones - Total: {$total_emitted}\n" . implode("\n", $log_entries);
        error_log($log_message);
        
        // Opcional: enviar email al admin
        if ($total_emitted > 0) {
            cs_notify_admin_coupon_emission($total_emitted, $log_entries);
        }
    }
}

/**
 * Procesar emisión para una propuesta específica
 */
function cs_process_proposal_emission($proposal) {
    global $wpdb;
    
    $today = new DateTime();
    $start_date = new DateTime($proposal->fecha_inicio);
    $coupons_table = $wpdb->prefix . 'coupons';
    
    // Calcular qué ciclos deberían haber ocurrido hasta hoy
    $days_since_start = $today->diff($start_date)->days;
    
    // Convertir frecuencia a días
    $frequency_in_days = cs_convert_to_days($proposal->frecuencia_emision, $proposal->unidad_frecuencia);
    
    // Calcular cuántos ciclos deberían haber ocurrido
    $expected_cycles = min(
        floor($days_since_start / $frequency_in_days) + 1,
        $proposal->cantidad_ciclos
    );
    
    // Verificar cuántos ciclos ya fueron emitidos
    $emitted_cycles = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT DATE(fecha_inicio)) / %d as cycles
        FROM {$coupons_table} 
        WHERE proposal_id = %d
    ", $proposal->cupones_por_ciclo, $proposal->id));
    
    $cycles_to_emit = $expected_cycles - $emitted_cycles;
    
    if ($cycles_to_emit <= 0) {
        return 0; // No hay ciclos que emitir
    }
    
    $total_emitted = 0;
    
    // Emitir cupones para cada ciclo pendiente
    for ($cycle = $emitted_cycles; $cycle < $expected_cycles; $cycle++) {
        $cycle_start_date = clone $start_date;
        $cycle_start_date->add(new DateInterval('P' . ($cycle * $frequency_in_days) . 'D'));
        
        // Solo emitir si la fecha del ciclo es hoy o anterior
        if ($cycle_start_date <= $today) {
            $emitted_in_cycle = cs_emit_coupons_for_cycle($proposal, $cycle_start_date, $cycle);
            $total_emitted += $emitted_in_cycle;
        }
    }
    
    return $total_emitted;
}

/**
 * Emitir cupones para un ciclo específico
 */
function cs_emit_coupons_for_cycle($proposal, $cycle_start_date, $cycle_number) {
    global $wpdb;
    
    $coupons_table = $wpdb->prefix . 'coupons';
    $emitted_count = 0;
    
    // Calcular fecha de vencimiento
    $end_date = clone $cycle_start_date;
    $duration_interval = cs_create_date_interval($proposal->duracion_validez, $proposal->unidad_validez);
    $end_date->add($duration_interval);
    
    // Emitir cupones para este ciclo
    for ($i = 0; $i < $proposal->cupones_por_ciclo; $i++) {
        $coupon_data = [
            'proposal_id' => $proposal->id,
            'comercio_id' => $proposal->comercio_id,
            'tipo' => $proposal->tipo_cupon,
            'valor' => $proposal->valor,
            'valor_restante' => $proposal->valor, // Nuevo campo para valor restante
            'fecha_inicio' => $cycle_start_date->format('Y-m-d'),
            'fecha_fin' => $end_date->format('Y-m-d'),
            'permite_uso_parcial' => $proposal->uso_parcial,
            'estado' => 'asignado_admin',
            'propietario_email' => null,
            'propietario_user_id' => null,
            'codigo_hash' => cs_generate_unique_hash(),
            'qr_token' => cs_generate_unique_qr_token(),
            'codigo_serie' => cs_generate_coupon_code(),
            'codigo_secreto' => cs_generate_secret_code(),
            'created_at' => current_time('mysql'),
            'unidad_descripcion' => $proposal->unidad_descripcion
        ];
        
        $result = $wpdb->insert($coupons_table, $coupon_data);
        
        if ($result !== false) {
            $emitted_count++;
            
            // Hook para acciones posteriores a la emisión
            do_action('cs_coupon_emitted', $wpdb->insert_id, $proposal, $cycle_number);
        } else {
            error_log("Error insertando cupón para propuesta {$proposal->id}: " . $wpdb->last_error);
        }
    }
    
    return $emitted_count;
}

/**
 * Generar código único del cupón (3 letras + 4 dígitos)
 */
function cs_generate_coupon_code() {
    global $wpdb;
    
    // Caracteres seguros (sin I, O, L, B, S, Q, Z para letras y sin 0, 1, 8, 9 para números)
    $safe_letters = 'ACDEFGHJKMNPRTUVWXY';
    $safe_numbers = '23456';
    
    $max_attempts = 100;
    $attempt = 0;
    
    do {
        $code = '';
        
        // 3 letras
        for ($i = 0; $i < 3; $i++) {
            $code .= $safe_letters[random_int(0, strlen($safe_letters) - 1)];
        }
        
        // 4 dígitos
        for ($i = 0; $i < 4; $i++) {
            $code .= $safe_numbers[random_int(0, strlen($safe_numbers) - 1)];
        }
        
        // Verificar unicidad
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}coupons WHERE codigo_serie = %s",
            $code
        ));
        
        $attempt++;
        
    } while ($exists > 0 && $attempt < $max_attempts);
    
    if ($attempt >= $max_attempts) {
        throw new Exception('No se pudo generar un código único después de ' . $max_attempts . ' intentos');
    }
    
    return $code;
}

/**
 * Generar código secreto (4 caracteres alfanuméricos)
 */
function cs_generate_secret_code() {
    global $wpdb;
    
    $safe_chars = 'ACDEFGHJKMNPRTUVWXY23456';
    
    $max_attempts = 100;
    $attempt = 0;
    
    do {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $safe_chars[random_int(0, strlen($safe_chars) - 1)];
        }
        
        // Verificar unicidad
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}coupons WHERE codigo_secreto = %s",
            $code
        ));
        
        $attempt++;
        
    } while ($exists > 0 && $attempt < $max_attempts);
    
    if ($attempt >= $max_attempts) {
        throw new Exception('No se pudo generar un código secreto único');
    }
    
    return $code;
}

/**
 * Generar hash único de 64 caracteres
 */
function cs_generate_unique_hash() {
    global $wpdb;
    
    $max_attempts = 10;
    $attempt = 0;
    
    do {
        $hash = hash('sha256', uniqid() . random_bytes(32) . microtime(true));
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}coupons WHERE codigo_hash = %s",
            $hash
        ));
        
        $attempt++;
        
    } while ($exists > 0 && $attempt < $max_attempts);
    
    return $hash;
}

/**
 * Generar token QR único de 64 caracteres
 */
function cs_generate_unique_qr_token() {
    global $wpdb;
    
    $max_attempts = 10;
    $attempt = 0;
    
    do {
        $token = hash('sha256', 'qr_' . uniqid() . random_bytes(32) . microtime(true));
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}coupons WHERE qr_token = %s",
            $token
        ));
        
        $attempt++;
        
    } while ($exists > 0 && $attempt < $max_attempts);
    
    return $token;
}

/**
 * Convertir unidad de tiempo a días
 */
function cs_convert_to_days($amount, $unit) {
    switch ($unit) {
        case 'dias':
            return $amount;
        case 'semanas':
            return $amount * 7;
        case 'meses':
            return $amount * 30; // Aproximación
        default:
            return $amount;
    }
}

/**
 * Crear DateInterval basado en cantidad y unidad
 */
function cs_create_date_interval($amount, $unit) {
    switch ($unit) {
        case 'dias':
            return new DateInterval('P' . $amount . 'D');
        case 'semanas':
            return new DateInterval('P' . ($amount * 7) . 'D');
        case 'meses':
            return new DateInterval('P' . $amount . 'M');
        default:
            return new DateInterval('P' . $amount . 'D');
    }
}

/**
 * Notificar al admin sobre la emisión de cupones
 */
function cs_notify_admin_coupon_emission($total_emitted, $log_entries) {
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    
    $subject = "[{$site_name}] Emisión diaria de cupones - {$total_emitted} cupones emitidos";
    
    $message = "Se han emitido {$total_emitted} cupones hoy.\n\n";
    $message .= "Detalle:\n" . implode("\n", $log_entries);
    $message .= "\n\nFecha: " . current_time('Y-m-d H:i:s');
    
    wp_mail($admin_email, $subject, $message);
}

/**
 * Función para ejecutar emisión manual (para testing)
 */
function cs_manual_coupon_emission() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ejecutar esta acción.');
    }
    
    cs_process_daily_coupon_emission();
    
    wp_redirect(admin_url('admin.php?page=cs_dashboard&emission_executed=1'));
    exit;
}

// Hook para emisión manual desde admin
add_action('admin_action_cs_manual_emission', 'cs_manual_coupon_emission');