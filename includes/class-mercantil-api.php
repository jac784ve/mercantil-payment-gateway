<?php
if (!defined('ABSPATH')) {
    exit;
}

class Mercantil_Payment_API {
    
    private $config;
    
    public function __construct($config) {
        $this->config = wp_parse_args($config, [
            'merchant_id' => '',
            'integrator_id' => '1',
            'terminal_id' => '1', 
            'client_id' => '',
            'secret_key' => '',
            'sandbox' => true
        ]);
    }
    
    public function process_payment($payment_data) {
        try {
            // Validar configuración
            if (empty($this->config['merchant_id']) || empty($this->config['client_id']) || empty($this->config['secret_key'])) {
                throw new Exception('Configuración incompleta del gateway');
            }
            
            // Validar datos de pago
            $validation = $this->validate_payment_data($payment_data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error_message' => $validation['error'],
                    'error_code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Construir y enviar request
            $body = $this->build_request_body($payment_data);
            $response = $this->send_request($body);
            
            return $this->handle_response($response);
            
        } catch (Exception $e) {
            error_log('Mercantil API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error_message' => 'Error interno: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ];
        }
    }
    
    private function validate_payment_data($data) {
        $required_fields = ['card_number', 'customer_id', 'invoice_number', 'expiration_date', 'cvv', 'amount'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['valid' => false, 'error' => "Campo requerido faltante: $field"];
            }
        }
        
        return ['valid' => true];
    }
    
    private function build_request_body($payment_data) {
        // Formatear fecha
        $expiry_parts = explode('/', $payment_data['expiration_date']);
        $expiry_formatted = (count($expiry_parts) === 2) ? $expiry_parts[1] . '/' . $expiry_parts[0] : $payment_data['expiration_date'];
        
        // Encriptar CVV
        $encrypted_cvv = mercantil_encrypt_data($payment_data['cvv'], $this->config['secret_key']);
        
        return [
            'merchant_identify' => [
                'integratorId' => $this->config['integrator_id'],
                'merchantId' => $this->config['merchant_id'],
                'terminalId' => $this->config['terminal_id']
            ],
            'client_identify' => [
                'ipaddress' => $this->get_client_ip(),
                'browser_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'WordPress/WooCommerce',
                'mobile' => [
                    'manufacturer' => 'Generic',
                    'model' => 'Unknown',
                    'os_version' => 'Unknown'
                ]
            ],
            'transaction' => [
                'trx_type' => 'compra',
                'payment_method' => 'tdc',
                'card_number' => preg_replace('/\s+/', '', $payment_data['card_number']),
                'customer_id' => $payment_data['customer_id'],
                'invoice_number' => $payment_data['invoice_number'],
                'expiration_date' => $expiry_formatted,
                'cvv' => $encrypted_cvv,
                'currency' => 'ves',
                'amount' => number_format(floatval($payment_data['amount']), 2, '.', '')
            ]
        ];
    }
    
    private function send_request($body) {
        $endpoint = $this->config['sandbox'] 
            ? 'https://apimbu.mercantilbanco.com/mercantil-banco/sandbox/v1/payment/pay'
            : 'https://apimbu.mercantilbanco.com/mercantil-banco/production/v1/payment/pay';
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-IBM-Client-ID' => $this->config['client_id']
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ];
        
        return wp_remote_post($endpoint, $args);
    }
    
    private function handle_response($response) {
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error_message' => 'Error de conexión: ' . $response->get_error_message(),
                'error_code' => 'CONNECTION_ERROR'
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if ($response_code === 200 && isset($data['error_code']) && $data['error_code'] == 0) {
            return [
                'success' => true,
                'data' => $data,
                'transaction_id' => $data['transaction_id'] ?? uniqid()
            ];
        } else {
            $error_msg = $data['error_message'] ?? 'Error en el procesamiento del pago';
            return [
                'success' => false,
                'error_message' => $error_msg,
                'error_code' => $data['error_code'] ?? 'UNKNOWN_ERROR',
                'http_status' => $response_code
            ];
        }
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
}
?>