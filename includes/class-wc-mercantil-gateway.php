<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Mercantil_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'mercantil_gateway';
        $this->has_fields = true;
        $this->method_title = 'Banco Mercantil';
        $this->method_description = 'Acepta pagos con tarjetas de crédito a través del Banco Mercantil';
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title', 'Tarjeta de Crédito (Mercantil)');
        $this->description = $this->get_option('description', 'Paga con tu tarjeta de crédito');
        $this->enabled = $this->get_option('enabled', 'no');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Habilitar/Deshabilitar',
                'label' => 'Habilitar Banco Mercantil',
                'type' => 'checkbox',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Título',
                'type' => 'text',
                'default' => 'Tarjeta de Crédito (Mercantil)'
            ),
            'description' => array(
                'title' => 'Descripción',
                'type' => 'textarea',
                'default' => 'Paga con tu tarjeta de crédito a través del Banco Mercantil'
            ),
            'merchant_id' => array(
                'title' => 'Merchant ID',
                'type' => 'text',
                'default' => '200284'
            ),
            'client_id' => array(
                'title' => 'Client ID',
                'type' => 'text',
                'default' => '81188330-c768-46fe-a378-ffaac9e88824'
            ),
            'secret_key' => array(
                'title' => 'Secret Key',
                'type' => 'password',
                'default' => 'A11103402525120190822HB01'
            ),
            'sandbox' => array(
                'title' => 'Modo Sandbox',
                'label' => 'Habilitar modo sandbox',
                'type' => 'checkbox',
                'default' => 'yes'
            )
        );
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        
        ?>
        <div class="mercantil-checkout-form">
            <p class="form-row form-row-wide">
                <label for="mercantil-card-number">Número de Tarjeta <span class="required">*</span></label>
                <input id="mercantil-card-number" name="mercantil_card_number" type="text" autocomplete="cc-number" placeholder="1234 5678 9012 3456" required />
            </p>
            
            <p class="form-row form-row-first">
                <label for="mercantil-card-expiry">Fecha Expiración (MM/AAAA) <span class="required">*</span></label>
                <input id="mercantil-card-expiry" name="mercantil_card_expiry" type="text" placeholder="MM/AAAA" required />
            </p>
            
            <p class="form-row form-row-last">
                <label for="mercantil-card-cvc">CVV <span class="required">*</span></label>
                <input id="mercantil-card-cvc" name="mercantil_card_cvc" type="text" placeholder="123" required />
            </p>
            
            <p class="form-row form-row-wide">
                <label for="mercantil-card-holder">Cédula (V/E + números) <span class="required">*</span></label>
                <input id="mercantil-card-holder" name="mercantil_card_holder" type="text" placeholder="V12345678" required />
            </p>
        </div>
        <?php
    }
    
    public function validate_fields() {
        $card_number = sanitize_text_field($_POST['mercantil_card_number'] ?? '');
        $card_expiry = sanitize_text_field($_POST['mercantil_card_expiry'] ?? '');
        $card_cvc = sanitize_text_field($_POST['mercantil_card_cvc'] ?? '');
        $card_holder = sanitize_text_field($_POST['mercantil_card_holder'] ?? '');
        
        if (empty($card_number)) {
            wc_add_notice('El número de tarjeta es requerido', 'error');
            return false;
        }
        
        if (empty($card_expiry) || !preg_match('/^(0[1-9]|1[0-2])\/20\d{2}$/', $card_expiry)) {
            wc_add_notice('Fecha de expiración inválida. Use MM/AAAA', 'error');
            return false;
        }
        
        if (empty($card_cvc) || !preg_match('/^\d{3,4}$/', $card_cvc)) {
            wc_add_notice('CVV inválido', 'error');
            return false;
        }
        
        if (empty($card_holder) || !preg_match('/^[VE]\d+$/i', $card_holder)) {
            wc_add_notice('Cédula inválida. Use formato V12345678', 'error');
            return false;
        }
        
        return true;
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        try {
            $card_number = str_replace(' ', '', sanitize_text_field($_POST['mercantil_card_number'] ?? ''));
            $card_expiry = sanitize_text_field($_POST['mercantil_card_expiry'] ?? '');
            $card_cvc = sanitize_text_field($_POST['mercantil_card_cvc'] ?? '');
            $card_holder = sanitize_text_field($_POST['mercantil_card_holder'] ?? '');
            
            $payment_data = [
                'card_number' => $card_number,
                'expiration_date' => $card_expiry,
                'cvv' => $card_cvc,
                'customer_id' => $card_holder,
                'amount' => $order->get_total(),
                'invoice_number' => $order->get_order_number()
            ];
            
            $config = [
                'merchant_id' => $this->get_option('merchant_id'),
                'client_id' => $this->get_option('client_id'),
                'secret_key' => $this->get_option('secret_key'),
                'sandbox' => $this->get_option('sandbox') === 'yes'
            ];
            
            $api = new Mercantil_Payment_API($config);
            $result = $api->process_payment($payment_data);
            
            if ($result['success']) {
                $order->payment_complete();
                $order->add_order_note('Pago procesado con Mercantil. ID: ' . ($result['transaction_id'] ?? 'N/A'));
                WC()->cart->empty_cart();
                
                return [
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                ];
            } else {
                $order->add_order_note('Error Mercantil: ' . $result['error_message']);
                wc_add_notice('Error en el pago: ' . $result['error_message'], 'error');
                return false;
            }
            
        } catch (Exception $e) {
            $order->add_order_note('Error procesando pago: ' . $e->getMessage());
            wc_add_notice('Error al procesar el pago.', 'error');
            return false;
        }
    }
}
?>