<?php
/**
 * Plugin Name: Mercantil Payment Gateway
 * Description: Pasarela de pago para Banco Mercantil Venezuela - Integración con WooCommerce
 * Version: 1.0.3
 * Author: Tu Nombre
 * Text Domain: mercantil-payment
 * Requires Plugins: woocommerce
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar WooCommerce de manera segura
add_action('plugins_loaded', 'mercantil_check_woocommerce');

function mercantil_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'mercantil_woocommerce_missing_notice');
        return;
    }
    
    // Inicializar el plugin solo si WooCommerce está activo
    mercantil_payment_init();
}

function mercantil_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><strong>Mercantil Payment Gateway:</strong> Requiere que WooCommerce esté instalado y activado.</p>
    </div>
    <?php
}

// Constantes
define('MERCANTIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MERCANTIL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MERCANTIL_PLUGIN_VERSION', '1.0.3');

function mercantil_payment_init() {
    try {
        // Verificar que los archivos existan antes de incluirlos
        $required_files = [
            'includes/class-mercantil-encryption.php',
            'includes/class-mercantil-api.php', 
            'includes/class-wc-mercantil-gateway.php'
        ];
        
        foreach ($required_files as $file) {
            $file_path = MERCANTIL_PLUGIN_PATH . $file;
            if (!file_exists($file_path)) {
                throw new Exception("Archivo requerido no encontrado: $file");
            }
            require_once $file_path;
        }
        
        // Agregar gateway a WooCommerce
        add_filter('woocommerce_payment_gateways', 'mercantil_add_gateway');
        
        // Cargar scripts
        add_action('wp_enqueue_scripts', 'mercantil_enqueue_scripts');
        
        // Internacionalización
        add_action('init', 'mercantil_load_textdomain');
        
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="error">
                <p><strong>Error en Mercantil Payment Gateway:</strong> <?php echo esc_html($e->getMessage()); ?></p>
            </div>
            <?php
        });
        return;
    }
}

function mercantil_add_gateway($methods) {
    if (class_exists('WC_Mercantil_Gateway')) {
        $methods[] = 'WC_Mercantil_Gateway';
    }
    return $methods;
}

function mercantil_enqueue_scripts() {
    if (!is_checkout()) {
        return;
    }
    
    // Solo cargar si el gateway está habilitado
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    if (!isset($gateways['mercantil_gateway'])) {
        return;
    }
    
    wp_enqueue_script(
        'mercantil-checkout-js',
        MERCANTIL_PLUGIN_URL . 'assets/js/mercantil-checkout.js',
        array('jquery'),
        MERCANTIL_PLUGIN_VERSION,
        true
    );
    
    wp_enqueue_style(
        'mercantil-checkout-css',
        MERCANTIL_PLUGIN_URL . 'assets/css/mercantil-checkout.css',
        array(),
        MERCANTIL_PLUGIN_VERSION
    );
}

function mercantil_load_textdomain() {
    load_plugin_textdomain('mercantil-payment', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Enlaces de acción en la lista de plugins
function mercantil_plugin_action_links($links) {
    if (!class_exists('WooCommerce')) {
        return $links;
    }
    
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mercantil_gateway') . '">Configuración</a>',
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mercantil_plugin_action_links');

// Función de activación
register_activation_hook(__FILE__, 'mercantil_plugin_activation');

function mercantil_plugin_activation() {
    // Verificar dependencias al activar
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('El plugin Mercantil Payment Gateway requiere WooCommerce. Por favor instala y activa WooCommerce primero.', 'mercantil-payment'),
            __('Dependencia faltante', 'mercantil-payment'),
            array('back_link' => true)
        );
    }
}
?>