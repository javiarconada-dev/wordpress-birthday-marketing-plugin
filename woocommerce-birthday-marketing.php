<?php
/**
 * Plugin Name: WooCommerce Birthday Marketing
 * Description: Automatically send birthday discount emails to registered customers. Includes popup registration form, unique WooCommerce coupon generation, and customer management panel.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// 1. CREAR TABLA EN LA BASE DE DATOS AL ACTIVAR EL PLUGIN
// ============================================================
register_activation_hook(__FILE__, 'ps_crear_tabla');

function ps_crear_tabla() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'cumpleanos';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $tabla (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        telefono VARCHAR(20),
        email VARCHAR(100) NOT NULL,
        fecha_nacimiento DATE NOT NULL,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


// ============================================================
// 2. PROGRAMAR EL CRON DIARIO AL ACTIVAR EL PLUGIN
// ============================================================
register_activation_hook(__FILE__, 'ps_activar_cron');
register_deactivation_hook(__FILE__, 'ps_desactivar_cron');

function ps_activar_cron() {
    if (!wp_next_scheduled('ps_check_cumpleanos')) {
        wp_schedule_event(strtotime('09:00:00'), 'daily', 'ps_check_cumpleanos');
    }
}

function ps_desactivar_cron() {
    wp_clear_scheduled_hook('ps_check_cumpleanos');
}


// ============================================================
// 3. SHORTCODE DEL FORMULARIO [formulario_cumpleanos]
// ============================================================
add_shortcode('formulario_cumpleanos', 'ps_mostrar_formulario');

function ps_mostrar_formulario() {
    $mensaje = '';
    $clase_mensaje = '';

    // Procesar el formulario cuando se envia
    if (isset($_POST['ps_nonce']) && wp_verify_nonce($_POST['ps_nonce'], 'ps_formulario')) {
        $nombre   = sanitize_text_field($_POST['nombre'] ?? '');
        $telefono = sanitize_text_field($_POST['telefono'] ?? '');
        $email    = sanitize_email($_POST['email'] ?? '');
        $fecha    = sanitize_text_field($_POST['fecha_nacimiento'] ?? '');

        if (empty($nombre) || empty($email) || empty($fecha) || empty($telefono)) {
            $mensaje = 'Por favor rellena todos los campos obligatorios.';
            $clase_mensaje = 'ps-msg-error';
        } elseif (!is_email($email)) {
            $mensaje = 'El email no es válido.';
            $clase_mensaje = 'ps-msg-error';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int)substr($fecha,5,2), (int)substr($fecha,8,2), (int)substr($fecha,0,4)) || strtotime($fecha) >= time()) {
            $mensaje = 'Por favor introduce una fecha de nacimiento válida.';
            $clase_mensaje = 'ps-msg-error';
        } else {
            global $wpdb;
            $tabla = $wpdb->prefix . 'cumpleanos';

            $existe_email = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla WHERE email = %s", $email));
            $existe_telefono = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla WHERE telefono = %s", $telefono));

            if ($existe_email) {
                $mensaje = 'Este email ya está registrado.';
                $clase_mensaje = 'ps-msg-warning';
            } elseif ($existe_telefono) {
                $mensaje = 'Este teléfono ya está registrado.';
                $clase_mensaje = 'ps-msg-warning';
            } else {
                $inserted = $wpdb->insert($tabla, [
                    'nombre'           => $nombre,
                    'telefono'         => $telefono,
                    'email'            => $email,
                    'fecha_nacimiento' => $fecha,
                ]);
                if (!$inserted) {
                    $mensaje = 'Ha ocurrido un error al guardar tus datos. Por favor inténtalo de nuevo.';
                    $clase_mensaje = 'ps-msg-error';
                } else {
                    $primer_nombre_form = explode(' ', trim($nombre))[0];
                    $plantilla_saludo  = get_option('ps_exito_saludo', 'Gracias por registrarte, {{nombre}} y sobre todo por confiar en nosotros.');
                    $plantilla_promesa = get_option('ps_exito_promesa', 'Te enviaremos el descuento el día de tu cumpleaños.');
                    $mensaje_saludo  = str_replace('{{nombre}}', esc_html($primer_nombre_form), esc_html($plantilla_saludo));
                    $mensaje_promesa = str_replace('{{nombre}}', esc_html($primer_nombre_form), esc_html($plantilla_promesa));
                    $clase_mensaje = 'ps-msg-ok';
                }
            }
        }
    }

    ob_start(); ?>
    <style>
        .ps-form-wrap {
            max-width: 480px;
            margin: 0 auto;
            font-family: 'Lora', serif;
            font-size: 14px;
            font-weight: 200;
            letter-spacing: 0.5px;
            line-height: 2;
            color: #333;
        }
        .ps-form-wrap p {
            margin-bottom: 16px;
        }
        .ps-form-wrap label {
            display: block;
            font-family: 'Lora', serif;
            font-size: 13px;
            font-weight: 200;
            letter-spacing: 0.5px;
            color: #333;
            margin-bottom: 4px;
        }
        .ps-form-wrap input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid rgba(32, 7, 7, 0.8);
            border-radius: 4px;
            font-family: 'Lora', serif;
            font-size: 14px;
            font-weight: 200;
            letter-spacing: 0.5px;
            color: #333;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .ps-form-wrap input:focus {
            border-color: #000;
        }
        .ps-form-wrap button {
            background: #333;
            color: #fff;
            border: none;
            padding: 12px 32px;
            font-family: 'Lora', serif;
            font-size: 13px;
            font-weight: 200;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            border-radius: 0;
            transition: background 0.2s;
            display: block;
            margin: 0 auto;
        }
        .ps-form-wrap button:hover {
            background: #000;
        }
        .ps-form-wrap .ps-msg-ok {
            border-left: 3px solid #333;
            padding: 8px 12px;
            font-style: italic;
            color: #333;
            margin-bottom: 16px;
        }
        .ps-form-wrap .ps-msg-error {
            border-left: 3px solid #a00;
            padding: 8px 12px;
            font-style: italic;
            color: #a00;
            margin-bottom: 16px;
        }
        .ps-form-wrap .ps-msg-warning {
            border-left: 3px solid #888;
            padding: 8px 12px;
            font-style: italic;
            color: #888;
            margin-bottom: 16px;
        }
    </style>

    <div class="ps-form-wrap">
        <?php if ($clase_mensaje === 'ps-msg-ok'): ?>
            <div style="text-align:center; padding: 20px 0;">
                <img src="' . get_option('ps_popup_imagen', '') . '"
                     alt="Personal Showroom"
                     style="width:100%; max-width:400px; height:auto; display:block; margin:0 auto 28px;">
                <p class="ps-msg-ok" style="border:none; text-align:center; font-size:15px; margin:0 0 16px;">
                    <?php echo $mensaje_saludo; ?>
                </p>
                <p class="ps-msg-ok" style="border:none; text-align:center; font-size:15px; margin:0;">
                    <?php echo $mensaje_promesa; ?>
                </p>
            </div>
        <?php else: ?>
            <?php if ($mensaje): ?>
                <p class="<?php echo $clase_mensaje; ?>"><?php echo $mensaje; ?></p>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('ps_formulario', 'ps_nonce'); ?>
                <p>
                    <label>Nombre y apellidos *</label>
                    <input type="text" name="nombre" required>
                </p>
                <p>
                    <label>Teléfono *</label>
                    <input type="tel" name="telefono" required>
                </p>
                <p>
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </p>
                <p>
                    <label>Fecha de nacimiento *</label>
                    <input type="date" name="fecha_nacimiento" required>
                </p>
                <p>
                    <button type="submit">Registrarme</button>
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


// ============================================================
// 4. LOGICA DE CUMPLEANOS - se ejecuta cada dia via cron
// ============================================================
add_action('ps_check_cumpleanos', 'ps_enviar_emails_cumpleanos');

function ps_enviar_emails_cumpleanos() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'cumpleanos';

    $hoy_mes = date('m');
    $hoy_dia = date('d');

    $contactos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tabla WHERE MONTH(fecha_nacimiento) = %d AND DAY(fecha_nacimiento) = %d",
        $hoy_mes,
        $hoy_dia
    ));

    foreach ($contactos as $contacto) {
        ps_enviar_email_cumpleanos($contacto);
    }
}

function ps_generar_cupon($contacto) {
    // Codigo unico: CUMPLE-NOMBRE-AÑO-ID
    $codigo = 'CUMPLE-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $contacto->nombre), 0, 8)) . '-' . date('Y') . '-' . $contacto->id;

    // Comprobar si ya existe un cupon para este contacto este año
    if (wc_get_coupon_id_by_code($codigo)) return $codigo;

    // Crear el cupon en WooCommerce
    $cupon = array(
        'post_title'   => $codigo,
        'post_content' => '',
        'post_status'  => 'publish',
        'post_author'  => 1,
        'post_type'    => 'shop_coupon',
    );
    $id = wp_insert_post($cupon);

    if ($id) {
        $caducidad = date('Y-m-d', strtotime('+10 days'));
        update_post_meta($id, 'discount_type', 'percent');
        update_post_meta($id, 'coupon_amount', '10');
        update_post_meta($id, 'usage_limit', '1');
        update_post_meta($id, 'usage_limit_per_user', '1');
        update_post_meta($id, 'date_expires', strtotime($caducidad));
        update_post_meta($id, 'individual_use', 'yes');
        update_post_meta($id, 'email_restrictions', $contacto->email);
    }

    return $codigo;
}

function ps_enviar_email_cumpleanos($contacto) {
    $nombre_tienda = get_bloginfo('name');
    $email_tienda  = get_option('admin_email');

    // Generar cupon unico
    $codigo_cupon = ps_generar_cupon($contacto);
    $primer_nombre   = explode(' ', trim($contacto->nombre))[0];
    $caducidad_texto = date('d/m/Y', strtotime('+10 days'));

    $asunto_plantilla = get_option('ps_email_asunto', '¡Feliz Cumpleaños, {{nombre}}! Un regalo especial te espera');
    $asunto = str_replace(['{{nombre}}', '{{tienda}}'], [$primer_nombre, $nombre_tienda], $asunto_plantilla);

    $reemplazos = [['{{nombre}}', '{{tienda}}'], [$primer_nombre, $nombre_tienda]];

    $intro_plantilla  = get_option('ps_email_intro', 'Hoy es tu día y {{tienda}} quiere celebrarlo contigo.');
    $cuerpo_plantilla = get_option('ps_email_cuerpo', 'Como regalo de cumpleaños tenemos un descuento exclusivo del 10% en todas tus compras, tanto en la tienda online como en nuestra tienda física:');
    $cierre_plantilla = get_option('ps_email_cierre', "Con todo nuestro cariño,\nEl equipo de {{tienda}}");

    $intro_html  = esc_html(str_replace($reemplazos[0], $reemplazos[1], $intro_plantilla));
    $cuerpo_html = esc_html(str_replace($reemplazos[0], $reemplazos[1], $cuerpo_plantilla));
    $cierre_html = nl2br(esc_html(str_replace($reemplazos[0], $reemplazos[1], $cierre_plantilla)));

    $cuerpo = "
    <html>
    <body style='font-family:Lora,serif; color:#333; max-width:600px; margin:0 auto; font-weight:200; letter-spacing:0.5px;'>
        <div style='text-align:center; padding:40px 0 20px;'>
            <h1 style='color:#333; font-weight:200; letter-spacing:1px; font-size:24px;'>
                ¡Feliz Cumpleaños, " . esc_html($primer_nombre) . "!
            </h1>
        </div>
        <div style='padding:30px; background:#f9f9f9;'>
            <p style='line-height:2;'>{$intro_html}</p>
            <p style='line-height:2;'>{$cuerpo_html}</p>

            <div style='text-align:center; margin:28px 0;'>
                <div style='display:inline-block; border:1px solid #333; padding:20px 40px;'>
                    <p style='margin:0; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#999;'>Tu código de descuento</p>
                    <p style='margin:10px 0 0; font-size:24px; letter-spacing:4px; font-weight:400; color:#333;'>{$codigo_cupon}</p>
                </div>
                <p style='margin:12px 0 0; font-size:12px; color:#aaa; letter-spacing:0.5px;'>
                    Válido hasta el {$caducidad_texto} &middot; Un solo uso &middot; Solo para este email
                </p>
            </div>

            <div style='text-align:center; margin:30px 0;'>
                <a href='' . get_site_url() . '' target='_blank'
                   style='background:#333; color:#fff; padding:12px 32px; text-decoration:none; font-family:Lora,serif; font-size:13px; font-weight:200; letter-spacing:1px; text-transform:uppercase;'>
                    Ver la tienda
                </a>
            </div>

            <p style='color:#999; font-size:13px; line-height:2; margin-top:30px;'>{$cierre_html}</p>
        </div>
    </body>
    </html>
    ";

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$nombre_tienda} <{$email_tienda}>",
    ];

    wp_mail($contacto->email, $asunto, $cuerpo, $headers);
}


// ============================================================
// 5. PAGINA DE ADMINISTRACION - ver los contactos registrados
// ============================================================
add_action('admin_menu', 'ps_menu_admin');
add_action('admin_enqueue_scripts', 'ps_enqueue_media');

function ps_enqueue_media($hook) {
    if (strpos($hook, 'ps-cumpleanos') !== false) {
        wp_enqueue_media();
    }
}

function ps_menu_admin() {
    add_menu_page(
        'Cumpleaños',
        'Cumpleaños',
        'manage_options',
        'ps-cumpleanos',
        'ps_pagina_admin',
        'dashicons-calendar-alt',
        30
    );
    add_submenu_page(
        'ps-cumpleanos',
        'Ajustes del popup',
        'Ajustes',
        'manage_options',
        'ps-cumpleanos-ajustes',
        'ps_pagina_ajustes'
    );
}

function ps_pagina_ajustes() {
    if (isset($_POST['ps_ajustes_nonce']) && wp_verify_nonce($_POST['ps_ajustes_nonce'], 'ps_guardar_ajustes')) {
        update_option('ps_popup_imagen',       esc_url_raw($_POST['ps_popup_imagen'] ?? ''));
        update_option('ps_popup_imagen_movil', esc_url_raw($_POST['ps_popup_imagen_movil'] ?? ''));
        update_option('ps_popup_titulo',    sanitize_textarea_field($_POST['ps_popup_titulo'] ?? ''));
        update_option('ps_popup_subtitulo', sanitize_textarea_field($_POST['ps_popup_subtitulo'] ?? ''));
        update_option('ps_exito_saludo',    sanitize_textarea_field($_POST['ps_exito_saludo'] ?? ''));
        update_option('ps_exito_promesa',   sanitize_textarea_field($_POST['ps_exito_promesa'] ?? ''));
        update_option('ps_email_asunto',    sanitize_text_field($_POST['ps_email_asunto'] ?? ''));
        update_option('ps_email_intro',     sanitize_textarea_field($_POST['ps_email_intro'] ?? ''));
        update_option('ps_email_cuerpo',    sanitize_textarea_field($_POST['ps_email_cuerpo'] ?? ''));
        update_option('ps_email_cierre',    sanitize_textarea_field($_POST['ps_email_cierre'] ?? ''));
        echo '<div class="notice notice-success"><p>Ajustes guardados correctamente.</p></div>';
    }

    $imagen_actual       = get_option('ps_popup_imagen', '');
    $imagen_movil_actual = get_option('ps_popup_imagen_movil', '');
    $popup_titulo    = get_option('ps_popup_titulo', 'En tu cumpleaños te haremos un regalo');
    $popup_subtitulo = get_option('ps_popup_subtitulo', 'Regístrate y te haremos un descuento en todas tus compras.');
    $exito_saludo    = get_option('ps_exito_saludo', 'Gracias por registrarte, {{nombre}} y sobre todo por confiar en nosotros.');
    $exito_promesa   = get_option('ps_exito_promesa', 'Te enviaremos el descuento el día de tu cumpleaños.');
    $email_asunto    = get_option('ps_email_asunto', '¡Feliz Cumpleaños, {{nombre}}! Un regalo especial te espera');
    $email_intro     = get_option('ps_email_intro', 'Hoy es tu día y {{tienda}} quiere celebrarlo contigo.');
    $email_cuerpo    = get_option('ps_email_cuerpo', 'Como regalo de cumpleaños tenemos un descuento exclusivo del 10% en todas tus compras, tanto en la tienda online como en nuestra tienda física:');
    $email_cierre    = get_option('ps_email_cierre', "Con todo nuestro cariño,\nEl equipo de {{tienda}}");

    $nombre_tienda_js  = json_encode(get_bloginfo('name'));
    $imagen_actual_js  = json_encode($imagen_actual);
    ?>
    <div class="wrap">
        <h1>Ajustes del popup de cumpleaños</h1>
        <form method="post">
            <?php wp_nonce_field('ps_guardar_ajustes', 'ps_ajustes_nonce'); ?>

            <h2>Imagen del popup</h2>
            <table class="form-table">
                <tr>
                    <th><label>Imagen del popup</label></th>
                    <td>
                        <div style="margin-bottom:12px;">
                            <?php if ($imagen_actual): ?>
                                <img id="ps-preview-imagen" src="<?php echo esc_url($imagen_actual); ?>"
                                    style="max-width:300px; max-height:200px; display:block; margin-bottom:8px; border:1px solid #ddd;">
                            <?php else: ?>
                                <img id="ps-preview-imagen" src=""
                                    style="max-width:300px; max-height:200px; display:none; margin-bottom:8px; border:1px solid #ddd;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="ps_popup_imagen" id="ps-popup-imagen-url"
                            value="<?php echo esc_url($imagen_actual); ?>">
                        <button type="button" class="button button-secondary" id="ps-seleccionar-imagen">
                            Seleccionar imagen
                        </button>
                        <?php if ($imagen_actual): ?>
                            <button type="button" class="button" id="ps-eliminar-imagen" style="margin-left:8px; color:red;">
                                Eliminar imagen
                            </button>
                        <?php endif; ?>
                        <p class="description" style="margin-top:8px;">
                            Elige la imagen que aparecerá en el lado izquierdo del popup de registro (versión escritorio). Tamaño óptimo: <strong>800 × 1000 px</strong>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label>Imagen para móvil</label></th>
                    <td>
                        <div style="margin-bottom:12px;">
                            <?php if ($imagen_movil_actual): ?>
                                <img id="ps-preview-imagen-movil" src="<?php echo esc_url($imagen_movil_actual); ?>"
                                    style="max-width:300px; max-height:120px; display:block; margin-bottom:8px; border:1px solid #ddd;">
                            <?php else: ?>
                                <img id="ps-preview-imagen-movil" src=""
                                    style="max-width:300px; max-height:120px; display:none; margin-bottom:8px; border:1px solid #ddd;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="ps_popup_imagen_movil" id="ps-popup-imagen-movil-url"
                            value="<?php echo esc_url($imagen_movil_actual); ?>">
                        <button type="button" class="button button-secondary" id="ps-seleccionar-imagen-movil">
                            Seleccionar imagen para móvil
                        </button>
                        <?php if ($imagen_movil_actual): ?>
                            <button type="button" class="button" id="ps-eliminar-imagen-movil" style="margin-left:8px; color:red;">
                                Eliminar imagen
                            </button>
                        <?php endif; ?>
                        <p class="description" style="margin-top:8px;">
                            Imagen horizontal que aparece <strong>encima del formulario</strong> en dispositivos móviles. Tamaño óptimo: <strong>800 × 300 px</strong>.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Textos del popup de registro</h2>
            <table class="form-table">
                <tr>
                    <th><label for="ps_popup_titulo">Título</label></th>
                    <td>
                        <textarea name="ps_popup_titulo" id="ps_popup_titulo" rows="3" class="large-text" style="font-size:14px; line-height:1.6;"><?php echo esc_textarea($popup_titulo); ?></textarea>
                        <p class="description">Puedes dividir el título en dos líneas usando Intro/Enter. Cada línea se centrará en el popup.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps_popup_subtitulo">Subtítulo</label></th>
                    <td>
                        <textarea name="ps_popup_subtitulo" id="ps_popup_subtitulo" rows="2" class="large-text"><?php echo esc_textarea($popup_subtitulo); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <button type="button" class="button button-secondary" onclick="psPreviewPopup('registro')">
                            Ver popup de registro
                        </button>
                    </td>
                </tr>
            </table>

            <h2>Mensaje de confirmación</h2>
            <table class="form-table">
                <tr>
                    <th><label for="ps_exito_saludo">Texto de saludo <em style="font-weight:400;">(primer párrafo)</em></label></th>
                    <td>
                        <textarea name="ps_exito_saludo" id="ps_exito_saludo" rows="2" class="large-text"><?php echo esc_textarea($exito_saludo); ?></textarea>
                        <p class="description">Usa <code>{{nombre}}</code> para el nombre de pila.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps_exito_promesa">Texto de promesa <em style="font-weight:400;">(segundo párrafo)</em></label></th>
                    <td>
                        <textarea name="ps_exito_promesa" id="ps_exito_promesa" rows="2" class="large-text"><?php echo esc_textarea($exito_promesa); ?></textarea>
                        <p class="description">Usa <code>{{nombre}}</code> si quieres personalizar también esta parte.</p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <button type="button" class="button button-secondary" onclick="psPreviewPopup('exito')">
                            Ver mensaje de confirmación
                        </button>
                    </td>
                </tr>
            </table>

            <h2>Email de cumpleaños</h2>
            <table class="form-table">
                <tr>
                    <th><label for="ps_email_asunto">Asunto</label></th>
                    <td>
                        <input type="text" name="ps_email_asunto" id="ps_email_asunto"
                            value="<?php echo esc_attr($email_asunto); ?>" class="regular-text" style="width:100%;max-width:600px;">
                        <p class="description">Usa <code>{{nombre}}</code> para el nombre del cliente.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps_email_intro">Párrafo de introducción</label></th>
                    <td>
                        <textarea name="ps_email_intro" id="ps_email_intro" rows="2" class="large-text"><?php echo esc_textarea($email_intro); ?></textarea>
                        <p class="description">Usa <code>{{nombre}}</code> y <code>{{tienda}}</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps_email_cuerpo">Párrafo del descuento</label></th>
                    <td>
                        <textarea name="ps_email_cuerpo" id="ps_email_cuerpo" rows="2" class="large-text"><?php echo esc_textarea($email_cuerpo); ?></textarea>
                        <p class="description">Usa <code>{{tienda}}</code> si necesitas mencionar la tienda.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ps_email_cierre">Despedida / firma</label></th>
                    <td>
                        <textarea name="ps_email_cierre" id="ps_email_cierre" rows="2" class="large-text"><?php echo esc_textarea($email_cierre); ?></textarea>
                        <p class="description">Usa <code>{{tienda}}</code>. Los saltos de línea se respetan en el email.</p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <button type="button" class="button button-secondary" onclick="psPreviewEmail()">
                            Previsualizar email
                        </button>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Guardar cambios</button>
            </p>
        </form>
    </div>

    <!-- Modal compartido para previsualizaciones -->
    <div id="ps-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999999; justify-content:center; align-items:center;">
        <div id="ps-modal-box" style="background:#fff; max-width:880px; width:92%; max-height:90vh; overflow-y:auto; position:relative; border-radius:2px;">
            <button id="ps-modal-close" onclick="psCloseModal()" style="position:absolute; top:10px; right:14px; font-size:22px; background:none; border:none; cursor:pointer; color:#999; z-index:1; line-height:1;">&times;</button>
            <div id="ps-modal-content" style="padding:0;"></div>
        </div>
    </div>

    <script>
    (function($) {
        // ── Media uploader ────────────────────────────────────────────────
        var mediaUploader;

        $('#ps-seleccionar-imagen').on('click', function(e) {
            e.preventDefault();
            if (mediaUploader) { mediaUploader.open(); return; }
            mediaUploader = wp.media({
                title: 'Seleccionar imagen del popup',
                button: { text: 'Usar esta imagen' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var att = mediaUploader.state().get('selection').first().toJSON();
                $('#ps-popup-imagen-url').val(att.url);
                $('#ps-preview-imagen').attr('src', att.url).show();
                psImagen = att.url;
            });
            mediaUploader.open();
        });

        $('#ps-eliminar-imagen').on('click', function(e) {
            e.preventDefault();
            $('#ps-popup-imagen-url').val('');
            $('#ps-preview-imagen').attr('src', '').hide();
            psImagen = '';
        });

        // ── Media uploader imagen móvil ───────────────────────────────────
        var mediaUploaderMovil;

        $('#ps-seleccionar-imagen-movil').on('click', function(e) {
            e.preventDefault();
            if (mediaUploaderMovil) { mediaUploaderMovil.open(); return; }
            mediaUploaderMovil = wp.media({
                title: 'Seleccionar imagen para móvil',
                button: { text: 'Usar esta imagen' },
                multiple: false
            });
            mediaUploaderMovil.on('select', function() {
                var att = mediaUploaderMovil.state().get('selection').first().toJSON();
                $('#ps-popup-imagen-movil-url').val(att.url);
                $('#ps-preview-imagen-movil').attr('src', att.url).show();
                psMobileImagen = att.url;
            });
            mediaUploaderMovil.open();
        });

        $('#ps-eliminar-imagen-movil').on('click', function(e) {
            e.preventDefault();
            $('#ps-popup-imagen-movil-url').val('');
            $('#ps-preview-imagen-movil').attr('src', '').hide();
            psMobileImagen = '';
        });
    })(jQuery);

    // ── Variables desde PHP ───────────────────────────────────────────────
    var psTienda       = <?php echo $nombre_tienda_js; ?>;
    var psImagen       = <?php echo $imagen_actual_js; ?>;
    var psMobileImagen = <?php echo json_encode($imagen_movil_actual); ?>;

    // ── Reemplaza placeholders ────────────────────────────────────────────
    function psReplace(text) {
        var anio = new Date().getFullYear();
        var d = new Date(); d.setDate(d.getDate() + 10);
        var caducidad = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
        return text
            .replace(/\{\{nombre\}\}/g, 'María García')
            .replace(/\{\{tienda\}\}/g, psTienda)
            .replace(/\{\{codigo\}\}/g, 'CUMPLE-MARIAGAR-' + anio + '-1')
            .replace(/\{\{caducidad\}\}/g, caducidad);
    }

    // ── Escape HTML ───────────────────────────────────────────────────────
    function psEscHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // ── Modal ─────────────────────────────────────────────────────────────
    function psOpenModal(html) {
        document.getElementById('ps-modal-content').innerHTML = html;
        document.getElementById('ps-modal-overlay').style.display = 'flex';
    }

    function psCloseModal() {
        document.getElementById('ps-modal-overlay').style.display = 'none';
    }

    document.getElementById('ps-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) psCloseModal();
    });

    // ── Previsualizar popup ───────────────────────────────────────────────
    function psPreviewPopup(estado) {
        var titulo        = document.getElementById('ps_popup_titulo').value;
        var subtitulo     = document.getElementById('ps_popup_subtitulo').value;
        var exitoSaludo   = document.getElementById('ps_exito_saludo').value;
        var exitoPromesa  = document.getElementById('ps_exito_promesa').value;

        var html;
        if (estado === 'registro') {
            var imgHtml = psImagen
                ? '<div style="width:45%;min-height:420px;background-image:url(\'' + psImagen.replace(/'/g, '%27') + '\');background-size:cover;background-position:center;flex-shrink:0;"></div>'
                : '<div style="width:45%;min-height:420px;background:#e8e0d8;flex-shrink:0;"></div>';

            html = '<div style="display:flex;font-family:Lora,serif;font-weight:200;letter-spacing:0.5px;color:#333;min-height:420px;">'
                + imgHtml
                + '<div style="padding:50px 40px;flex:1;display:flex;flex-direction:column;justify-content:center;">'
                + '<h2 style="font-size:18px;font-weight:200;letter-spacing:1px;text-transform:uppercase;margin:0 0 8px;color:#333;">' + psEscHtml(psReplace(titulo)).replace(/\n/g, '<br>') + '</h2>'
                + '<p style="font-size:13px;color:#888;margin:0 0 24px;font-style:italic;line-height:1.6;">' + psEscHtml(psReplace(subtitulo)) + '</p>'
                + '<div style="pointer-events:none;opacity:0.75;">'
                + '<p style="margin-bottom:16px;"><label style="display:block;font-size:13px;font-weight:200;margin-bottom:4px;">Nombre y apellidos *</label><input disabled style="width:100%;padding:10px 12px;border:1px solid rgba(32,7,7,0.8);border-radius:4px;font-family:Lora,serif;font-size:14px;background:#fff;box-sizing:border-box;" type="text"></p>'
                + '<p style="margin-bottom:16px;"><label style="display:block;font-size:13px;font-weight:200;margin-bottom:4px;">Teléfono *</label><input disabled style="width:100%;padding:10px 12px;border:1px solid rgba(32,7,7,0.8);border-radius:4px;font-family:Lora,serif;font-size:14px;background:#fff;box-sizing:border-box;" type="text"></p>'
                + '<p style="margin-bottom:16px;"><label style="display:block;font-size:13px;font-weight:200;margin-bottom:4px;">Email *</label><input disabled style="width:100%;padding:10px 12px;border:1px solid rgba(32,7,7,0.8);border-radius:4px;font-family:Lora,serif;font-size:14px;background:#fff;box-sizing:border-box;" type="text"></p>'
                + '<p style="margin-bottom:16px;"><label style="display:block;font-size:13px;font-weight:200;margin-bottom:4px;">Fecha de nacimiento *</label><input disabled style="width:100%;padding:10px 12px;border:1px solid rgba(32,7,7,0.8);border-radius:4px;font-family:Lora,serif;font-size:14px;background:#fff;box-sizing:border-box;" type="text"></p>'
                + '<p style="text-align:center;"><button disabled style="background:#333;color:#fff;border:none;padding:12px 32px;font-family:Lora,serif;font-size:13px;font-weight:200;letter-spacing:1px;text-transform:uppercase;cursor:not-allowed;">Registrarme</button></p>'
                + '</div>'
                + '<span style="display:block;text-align:center;margin-top:16px;font-size:12px;color:#bbb;letter-spacing:0.5px;text-decoration:underline;cursor:default;">Ahora no, gracias</span>'
                + '</div></div>';
        } else {
            html = '<div style="font-family:Lora,serif;font-weight:200;letter-spacing:0.5px;color:#333;padding:50px 40px;text-align:center;">'
                + '<img src="' . get_option('ps_popup_imagen', '') . '" alt="Personal Showroom" style="width:100%;max-width:400px;height:auto;display:block;margin:0 auto 28px;">'
                + '<p style="font-style:italic;color:#333;font-size:15px;line-height:1.8;margin:0 0 16px;">' + psEscHtml(psReplace(exitoSaludo)) + '</p>'
                + '<p style="font-style:italic;color:#333;font-size:15px;line-height:1.8;margin:0;">' + psEscHtml(psReplace(exitoPromesa)) + '</p>'
                + '</div>';
        }
        psOpenModal(html);
    }

    // ── Previsualizar email ───────────────────────────────────────────────
    function psPreviewEmail() {
        var asunto = document.getElementById('ps_email_asunto').value;
        var intro  = document.getElementById('ps_email_intro').value;
        var cuerpo = document.getElementById('ps_email_cuerpo').value;
        var cierre = document.getElementById('ps_email_cierre').value;

        var anio = new Date().getFullYear();
        var d = new Date(); d.setDate(d.getDate() + 10);
        var caducidad = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
        var codigo = 'CUMPLE-MARIAGAR-' + anio + '-1';

        var introHtml  = psEscHtml(psReplace(intro)).replace(/\n/g, '<br>');
        var cuerpoHtml = psEscHtml(psReplace(cuerpo)).replace(/\n/g, '<br>');
        var cierreHtml = psEscHtml(psReplace(cierre)).replace(/\n/g, '<br>');

        var html = '<div style="background:#eee;padding:12px 16px;font-family:sans-serif;font-size:13px;color:#555;border-bottom:1px solid #ddd;">'
            + '<strong>Asunto:</strong> ' + psEscHtml(psReplace(asunto))
            + '</div>'
            + '<div style="font-family:Lora,serif;color:#333;max-width:600px;margin:0 auto;font-weight:200;letter-spacing:0.5px;">'
            + '<div style="text-align:center;padding:40px 0 20px;">'
            + '<h1 style="color:#333;font-weight:200;letter-spacing:1px;font-size:24px;">¡Feliz Cumpleaños, María García!</h1>'
            + '</div>'
            + '<div style="padding:30px;background:#f9f9f9;">'
            + '<p style="line-height:2;">' + introHtml + '</p>'
            + '<p style="line-height:2;">' + cuerpoHtml + '</p>'
            + '<div style="text-align:center;margin:28px 0;">'
            + '<div style="display:inline-block;border:1px solid #333;padding:20px 40px;">'
            + '<p style="margin:0;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#999;">Tu código de descuento</p>'
            + '<p style="margin:10px 0 0;font-size:24px;letter-spacing:4px;font-weight:400;color:#333;">' + psEscHtml(codigo) + '</p>'
            + '</div>'
            + '<p style="margin:12px 0 0;font-size:12px;color:#aaa;letter-spacing:0.5px;">Válido hasta el ' + psEscHtml(caducidad) + ' &middot; Un solo uso &middot; Solo para este email</p>'
            + '</div>'
            + '<div style="text-align:center;margin:30px 0;">'
            + '<a href="#" style="background:#333;color:#fff;padding:12px 32px;text-decoration:none;font-family:Lora,serif;font-size:13px;font-weight:200;letter-spacing:1px;text-transform:uppercase;">Ver la tienda</a>'
            + '</div>'
            + '<p style="color:#999;font-size:13px;line-height:2;margin-top:30px;">' + cierreHtml + '</p>'
            + '</div></div>';

        psOpenModal(html);
    }
    </script>
    <?php
}

function ps_pagina_admin() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'cumpleanos';

    // Borrar contacto
    if (isset($_GET['borrar_id']) && current_user_can('manage_options')) {
        $id = intval($_GET['borrar_id']);
        if (isset($_GET['ps_delete_nonce']) && wp_verify_nonce($_GET['ps_delete_nonce'], 'ps_borrar_' . $id)) {
            $wpdb->delete($tabla, ['id' => $id]);
            echo '<div class="notice notice-success"><p>Contacto eliminado correctamente.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error de seguridad al intentar borrar.</p></div>';
        }
    }

    // Filtro de busqueda
    $busqueda = isset($_GET['buscar']) ? sanitize_text_field($_GET['buscar']) : '';
    if ($busqueda) {
        $contactos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tabla WHERE nombre LIKE %s OR email LIKE %s OR telefono LIKE %s ORDER BY fecha_nacimiento ASC",
            '%' . $wpdb->esc_like($busqueda) . '%',
            '%' . $wpdb->esc_like($busqueda) . '%',
            '%' . $wpdb->esc_like($busqueda) . '%'
        ));
    } else {
        $contactos = $wpdb->get_results("SELECT * FROM $tabla ORDER BY fecha_nacimiento ASC");
    }
    ?>
    <div class="wrap">
        <h1>Contactos registrados</h1>
        <p>Total: <strong><?php echo count($contactos); ?></strong> contactos</p>

        <?php if (isset($_GET['importados'])): ?>
            <div class="notice notice-success"><p>
                Importación completada: <strong><?php echo intval($_GET['importados']); ?></strong> contactos importados,
                <strong><?php echo intval($_GET['omitidos']); ?></strong> omitidos (ya existían o datos incompletos).
            </p></div>
        <?php endif; ?>
        <?php if (isset($_GET['import_error'])): ?>
            <div class="notice notice-error"><p>Error al importar. Asegúrate de subir un archivo CSV válido.</p></div>
        <?php endif; ?>

        <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; align-items:flex-start;">

            <div>
                <a href="<?php echo wp_nonce_url(add_query_arg('ps_exportar', '1'), 'ps_exportar', 'ps_export_nonce'); ?>"
                   class="button button-primary">
                    ⬇ Exportar contactos a CSV
                </a>
            </div>

            <div style="border:1px solid #ccc; padding:12px 16px; border-radius:4px; background:#f9f9f9;">
                <strong style="display:block; margin-bottom:8px; font-size:13px;">⬆ Importar desde CSV</strong>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
                    <?php wp_nonce_field('ps_importar', 'ps_import_nonce'); ?>
                    <input type="hidden" name="action" value="ps_importar_csv">
                    <input type="file" name="ps_csv" accept=".csv" required style="font-size:13px;">
                    <button type="submit" class="button button-secondary">Importar</button>
                </form>
                <p style="margin:8px 0 0; font-size:11px; color:#888;">
                    El CSV debe tener columnas: Nombre ; Telefono ; Email ; Fecha de nacimiento (YYYY-MM-DD) ; Fecha de registro
                </p>
            </div>

        </div>

        <form method="get" style="margin-bottom:16px; display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="page" value="ps-cumpleanos">
            <input type="text" name="buscar" value="<?php echo esc_attr($busqueda); ?>"
                placeholder="Buscar por nombre, email o telefono..."
                style="width:300px; padding:6px 10px; border:1px solid #ccc; border-radius:4px;">
            <button type="submit" class="button button-primary">Buscar</button>
            <?php if ($busqueda): ?>
                <a href="?page=ps-cumpleanos" class="button button-secondary">Limpiar</a>
            <?php endif; ?>
        </form>

        <?php if (isset($_GET['test_email']) && current_user_can('manage_options')) {
            ps_enviar_emails_cumpleanos();
            echo '<div class="notice notice-success"><p>Revisión de cumpleaños ejecutada manualmente.</p></div>';
        } ?>

        <a href="?page=ps-cumpleanos&test_email=1" class="button button-secondary" style="margin-bottom:16px;">
            Ejecutar revisión de cumpleaños ahora (para pruebas)
        </a>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Telefono</th>
                    <th>Fecha de nacimiento</th>
                    <th>Registrado el</th>
                    <th>Accion</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contactos)): ?>
                    <tr><td colspan="6">Aún no hay contactos registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($contactos as $c): ?>
                        <?php $nonce = wp_create_nonce('ps_borrar_' . $c->id); ?>
                        <tr>
                            <td><?php echo esc_html($c->nombre); ?></td>
                            <td><?php echo esc_html($c->email); ?></td>
                            <td><?php echo esc_html($c->telefono); ?></td>
                            <td><?php echo esc_html(date('d/m', strtotime($c->fecha_nacimiento))); ?></td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($c->fecha_registro))); ?></td>
                            <td>
                                <a href="?page=ps-cumpleanos&borrar_id=<?php echo $c->id; ?>&ps_delete_nonce=<?php echo $nonce; ?>"
                                   style="color:red;"
                                   onclick="return confirm('Seguro que quieres eliminar a <?php echo esc_js($c->nombre); ?>?')">
                                    Borrar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}


// ============================================================
// 6. EXPORTAR E IMPORTAR CONTACTOS
// ============================================================

// Exportar CSV
add_action('admin_init', 'ps_exportar_csv');
function ps_exportar_csv() {
    if (!isset($_GET['ps_exportar']) || !current_user_can('manage_options')) return;
    if (!isset($_GET['ps_export_nonce']) || !wp_verify_nonce($_GET['ps_export_nonce'], 'ps_exportar')) return;

    global $wpdb;
    $tabla = $wpdb->prefix . 'cumpleanos';
    $contactos = $wpdb->get_results("SELECT nombre, telefono, email, fecha_nacimiento, fecha_registro FROM $tabla ORDER BY fecha_nacimiento ASC", ARRAY_A);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=contactos-cumpleanos-' . date('Y-m-d') . '.csv');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para que Excel lo abra bien
    fputcsv($output, ['Nombre', 'Telefono', 'Email', 'Fecha de nacimiento', 'Fecha de registro'], ';');
    foreach ($contactos as $row) {
        fputcsv($output, $row, ';');
    }
    fclose($output);
    exit;
}

// Importar CSV
add_action('admin_post_ps_importar_csv', 'ps_importar_csv');
function ps_importar_csv() {
    if (!current_user_can('manage_options')) wp_die('Sin permiso');
    if (!isset($_POST['ps_import_nonce']) || !wp_verify_nonce($_POST['ps_import_nonce'], 'ps_importar')) wp_die('Error de seguridad');

    if (empty($_FILES['ps_csv']['tmp_name'])) {
        wp_redirect(add_query_arg(['page' => 'ps-cumpleanos', 'import_error' => '1'], admin_url('admin.php')));
        exit;
    }

    global $wpdb;
    $tabla = $wpdb->prefix . 'cumpleanos';
    $importados = 0;
    $omitidos = 0;

    $handle = fopen($_FILES['ps_csv']['tmp_name'], 'r');
    $primera_fila = true;
    while (($row = fgetcsv($handle, 1000, ';')) !== false) {
        if ($primera_fila) { $primera_fila = false; continue; } // saltar cabecera
        if (count($row) < 4) continue;

        $nombre   = sanitize_text_field($row[0]);
        $telefono = sanitize_text_field($row[1]);
        $email    = sanitize_email($row[2]);
        $fecha_raw = sanitize_text_field($row[3]);

        // Detectar formato de fecha y convertir a YYYY-MM-DD
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $fecha_raw, $m)) {
            $fecha = $m[3] . '-' . $m[2] . '-' . $m[1]; // DD/MM/YYYY -> YYYY-MM-DD
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
            $fecha = $fecha_raw; // Ya esta en formato correcto
        } else {
            $fecha = '';
        }

        if (empty($nombre) || empty($email) || empty($fecha)) { $omitidos++; continue; }

        $existe = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla WHERE email = %s", $email));
        if ($existe) { $omitidos++; continue; }

        $wpdb->insert($tabla, [
            'nombre'           => $nombre,
            'telefono'         => $telefono,
            'email'            => $email,
            'fecha_nacimiento' => $fecha,
        ]);
        $importados++;
    }
    fclose($handle);

    wp_redirect(add_query_arg(['page' => 'ps-cumpleanos', 'importados' => $importados, 'omitidos' => $omitidos], admin_url('admin.php')));
    exit;
}

// ============================================================
// 7. POPUP AUTOMATICO AL CARGAR LA PAGINA
// ============================================================
add_action('wp_footer', 'ps_popup_cumpleanos');

function ps_popup_cumpleanos() {
    // No mostrar en el panel de admin
    if (is_admin()) return;
    ?>
    <style>
        #ps-popup-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 99999;
            justify-content: center;
            align-items: center;
        }
        #ps-popup-overlay.ps-visible {
            display: flex;
        }
        #ps-popup-box {
            background: #fff;
            max-width: 860px;
            width: 90%;
            max-height: 92vh;
            overflow: hidden;
            position: relative;
            font-family: 'Lora', serif;
            font-weight: 200;
            letter-spacing: 0.5px;
            color: #333;
            display: flex;
        }
        #ps-popup-image {
            width: 45%;
            min-height: 500px;
            background-size: cover;
            background-position: center;
            background-color: #e8e0d8;
            flex-shrink: 0;
        }
        #ps-popup-image-mobile {
            display: none;
            width: 100%;
            height: 150px;
            background-size: cover;
            background-position: center;
            background-color: #e8e0d8;
            flex-shrink: 0;
        }
        #ps-popup-content {
            padding: 50px 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
            box-sizing: border-box;
        }
        #ps-popup-close {
            position: absolute;
            top: 12px; right: 16px;
            font-size: 20px;
            cursor: pointer;
            color: #999;
            background: none;
            border: none;
            line-height: 1;
            font-family: sans-serif;
            z-index: 1;
        }
        #ps-popup-close:hover {
            color: #333;
        }
        #ps-popup-box h2 {
            font-size: 18px;
            font-weight: 200;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 0 0 8px;
            color: #333;
        }
        #ps-popup-box p.ps-popup-sub {
            font-size: 13px;
            color: #888;
            margin: 0 0 24px;
            font-style: italic;
            line-height: 1.6;
        }
        #ps-popup-box .ps-form-wrap {
            max-width: 100%;
        }
        .ps-popup-no-thanks {
            display: block;
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
            color: #bbb;
            cursor: pointer;
            letter-spacing: 0.5px;
            text-decoration: underline;
        }
        .ps-popup-no-thanks:hover {
            color: #888;
        }
        @media (max-height: 720px) {
            #ps-popup-image {
                min-height: 380px;
            }
            #ps-popup-content {
                padding: 32px 40px;
            }
        }
        @media (max-height: 600px) {
            #ps-popup-image {
                min-height: 0;
            }
            #ps-popup-content {
                padding: 24px 32px;
            }
        }
        @media (max-width: 600px) {
            #ps-popup-overlay {
                align-items: flex-start;
                padding: 16px;
                box-sizing: border-box;
            }
            #ps-popup-box {
                flex-direction: column;
                width: 100%;
                max-width: 100%;
                max-height: calc(100vh - 32px);
                overflow-y: auto;
                overflow-x: hidden;
            }
            #ps-popup-image {
                display: none;
            }
            #ps-popup-image-mobile.ps-has-image {
                display: block;
            }
            #ps-popup-content {
                padding: 24px 20px;
                overflow-y: visible;
                justify-content: flex-start;
            }
        }
    </style>

    <div id="ps-popup-overlay">
        <div id="ps-popup-box">
            <button id="ps-popup-close" aria-label="Cerrar">&times;</button>
            <?php
                $ps_img               = get_option('ps_popup_imagen', '');
                $ps_img_movil         = get_option('ps_popup_imagen_movil', '');
                $ps_img_movil_efectiva = $ps_img_movil ?: $ps_img; // fallback a la imagen de escritorio
            ?>
            <div id="ps-popup-image" <?php if ($ps_img): ?>style="background-image:url('<?php echo esc_url($ps_img); ?>')"<?php endif; ?>></div>
            <div id="ps-popup-image-mobile"<?php if ($ps_img_movil_efectiva): ?> class="ps-has-image" style="background-image:url('<?php echo esc_url($ps_img_movil_efectiva); ?>')"<?php endif; ?>></div>
            <div id="ps-popup-content">
                <h2><?php echo nl2br(esc_html(get_option('ps_popup_titulo', 'En tu cumpleaños te haremos un regalo'))); ?></h2>
                <p class="ps-popup-sub"><?php echo esc_html(get_option('ps_popup_subtitulo', 'Regístrate y te haremos un descuento en todas tus compras.')); ?></p>
                <?php echo ps_mostrar_formulario(); ?>
                <span class="ps-popup-no-thanks" id="ps-popup-nothanks">Ahora no, gracias</span>
            </div>
        </div>
    </div>

    <script>
    function openBirthdayPopup() {
        var overlay = document.getElementById('ps-popup-overlay');
        if (overlay) {
            overlay.classList.add('ps-visible');
            document.cookie = 'ps_popup_visto=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/';
        }
    }

    (function() {
        var COOKIE = 'ps_popup_visto';
        var DIAS   = 30;

        function getCookie(name) {
            var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? match[2] : null;
        }

        function setCookie(name, days) {
            var d = new Date();
            d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
            document.cookie = name + '=1; expires=' + d.toUTCString() + '; path=/';
        }

        function cerrarPopup() {
            setCookie(COOKIE, DIAS);
            document.getElementById('ps-popup-overlay').classList.remove('ps-visible');
        }

        // Si el registro fue exitoso, ocultar titulo, subtitulo, "no gracias" e imagen
        var msgOk = document.querySelector('#ps-popup-box .ps-msg-ok');
        if (msgOk) {
            var titulo = document.querySelector('#ps-popup-content h2');
            var sub = document.querySelector('#ps-popup-box .ps-popup-sub');
            var nothanks = document.getElementById('ps-popup-nothanks');
            var imagen = document.getElementById('ps-popup-image');
            var content = document.getElementById('ps-popup-content');
            if (titulo) titulo.style.display = 'none';
            if (sub) sub.style.display = 'none';
            if (nothanks) nothanks.style.display = 'none';
            if (imagen) imagen.style.display = 'none';
            if (content) {
                content.style.textAlign = 'center';
                content.style.padding = '50px 40px';
            }
        }

        // Mostrar solo si no ha visto el popup en los ultimos 30 dias
        if (!getCookie(COOKIE)) {
            setTimeout(function() {
                document.getElementById('ps-popup-overlay').classList.add('ps-visible');
            }, 2000); // aparece a los 2 segundos
        }

        document.getElementById('ps-popup-close').addEventListener('click', cerrarPopup);
        document.getElementById('ps-popup-nothanks').addEventListener('click', cerrarPopup);

        // Cerrar al hacer click fuera del box
        document.getElementById('ps-popup-overlay').addEventListener('click', function(e) {
            if (e.target === this) cerrarPopup();
        });
    })();
    </script>
    <?php
}
