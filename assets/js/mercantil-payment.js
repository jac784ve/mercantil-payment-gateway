// assets/js/mercantil-payment.js

class MercantilPaymentDebug {
    static enableDebug = true;
    
    static log(message, data = null) {
        if (this.enableDebug && console) {
            console.log(`[Mercantil Payment] ${message}`, data || '');
        }
    }
    
    static error(message, error = null) {
        if (this.enableDebug && console) {
            console.error(`[Mercantil Payment ERROR] ${message}`, error || '');
        }
        
        this.showUserMessage('error', message);
    }
    
    static warn(message, data = null) {
        if (this.enableDebug && console) {
            console.warn(`[Mercantil Payment WARN] ${message}`, data || '');
        }
    }
    
    static showUserMessage(type, message) {
        const debugPanel = document.getElementById('mercantil-debug-panel');
        if (debugPanel && debugPanel.style.display !== 'none') {
            const messageElement = document.createElement('div');
            messageElement.className = `debug-message ${type}`;
            messageElement.innerHTML = `[${new Date().toLocaleTimeString()}] ${message}`;
            debugPanel.appendChild(messageElement);
        }
    }
}

// Función de procesamiento mejorada con logging
async function processMercantilPayment(paymentData) {
    MercantilPaymentDebug.log('Iniciando procesamiento de pago', paymentData);
    
    try {
        const response = await fetch(mercantil_ajax.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': mercantil_ajax.nonce
            },
            body: JSON.stringify({
                action: 'process_mercantil_payment',
                payment_data: paymentData,
                debug: MercantilPaymentDebug.enableDebug
            })
        });

        const result = await response.json();
        MercantilPaymentDebug.log('Respuesta del servidor', result);
        
        if (result.success) {
            MercantilPaymentDebug.log('Pago exitoso', result.data);
            return {
                success: true,
                transaction_id: result.data.transaction_id,
                message: result.data.message,
                debug_info: result.debug_info || null
            };
        } else {
            MercantilPaymentDebug.error('Error en el pago', {
                error_type: result.error_type,
                error_message: result.error_message,
                error_code: result.error_code,
                http_status: result.http_status
            });
            
            return {
                success: false,
                error: result.error_message,
                error_type: result.error_type,
                error_code: result.error_code,
                http_status: result.http_status,
                debug_info: result.debug_info || null
            };
        }
        
    } catch (error) {
        MercantilPaymentDebug.error('Error de conexión', error);
        return {
            success: false,
            error: 'Error de conexión con el servidor',
            error_type: 'network',
            error_code: 'NETWORK_ERROR'
        };
    }
}

// Formatear número de tarjeta con espacios
function formatCardNumber(input) {
    let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    let matches = value.match(/\d{4,16}/g);
    let match = matches ? matches[0] : '';
    let parts = [];
    
    for (let i = 0; i < match.length; i += 4) {
        parts.push(match.substring(i, i + 4));
    }
    
    if (parts.length) {
        input.value = parts.join(' ');
    } else {
        input.value = value;
    }
}

// Validar formulario antes de enviar
function validatePaymentForm(formData) {
    const errors = [];

    // Validar cédula (V/E + números)
    if (!/^[VE]\d+$/.test(formData.customer_id)) {
        errors.push('La cédula debe comenzar con V o E seguido de números');
    }

    // Validar tarjeta (solo números, 13-19 dígitos)
    const cleanCard = formData.card_number.replace(/\s/g, '');
    if (!/^\d{13,19}$/.test(cleanCard)) {
        errors.push('Número de tarjeta inválido');
    }

    // Validar fecha (MM/AAAA)
    if (!/^(0[1-9]|1[0-2])\/(20\d{2})$/.test(formData.expiration_date)) {
        errors.push('Fecha de expiración inválida. Use MM/AAAA');
    }

    // Validar CVV (3-4 dígitos)
    if (!/^\d{3,4}$/.test(formData.cvv)) {
        errors.push('CVV inválido');
    }

    // Validar monto
    if (!formData.amount || parseFloat(formData.amount) <= 0) {
        errors.push('Monto inválido');
    }

    return errors;
}

// Panel de debug en interfaz
function createDebugPanel() {
    if (!document.getElementById('mercantil-debug-panel')) {
        const debugPanel = document.createElement('div');
        debugPanel.id = 'mercantil-debug-panel';
        debugPanel.style.cssText = `
            position: fixed;
            bottom: 10px;
            right: 10px;
            width: 400px;
            height: 300px;
            background: #1e1e1e;
            color: #d4d4d4;
            border: 1px solid #007acc;
            padding: 10px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            z-index: 9999;
            display: none;
        `;
        
        const toggleBtn = document.createElement('button');
        toggleBtn.textContent = 'Debug Mercantil';
        toggleBtn.className = 'mercantil-debug-toggle';
        toggleBtn.onclick = () => {
            debugPanel.style.display = debugPanel.style.display === 'none' ? 'block' : 'none';
        };
        toggleBtn.style.cssText = `
            position: fixed;
            bottom: 320px;
            right: 10px;
            z-index: 10000;
            background: #007acc;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 3px;
        `;
        
        document.body.appendChild(toggleBtn);
        document.body.appendChild(debugPanel);
    }
}

// Manejar envío del formulario
document.addEventListener('DOMContentLoaded', function() {
    const paymentForm = document.getElementById('mercantil-payment-form');
    
    if (paymentForm) {
        paymentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Mostrar loading
            document.getElementById('payment-loading').style.display = 'block';
            document.getElementById('payment-error').style.display = 'none';
            document.getElementById('payment-success').style.display = 'none';
            
            const formData = new FormData(paymentForm);
            const paymentData = {
                customer_id: formData.get('customer_id'),
                card_number: formData.get('card_number').replace(/\s/g, ''),
                expiration_date: formData.get('expiration_date'),
                cvv: formData.get('cvv'),
                amount: formData.get('amount'),
                invoice_number: formData.get('invoice_number')
            };
            
            // Validaciones frontend
            const errors = validatePaymentForm(paymentData);
            if (errors.length > 0) {
                document.getElementById('payment-error').style.display = 'block';
                document.getElementById('error-message').textContent = errors.join(', ');
                document.getElementById('payment-loading').style.display = 'none';
                return;
            }
            
            const result = await processMercantilPayment(paymentData);
            
            if (result.success) {
                document.getElementById('payment-success').style.display = 'block';
                document.getElementById('transaction-id').textContent = 
                    'ID Transacción: ' + result.transaction_id;
                paymentForm.reset();
            } else {
                document.getElementById('payment-error').style.display = 'block';
                document.getElementById('error-message').textContent = result.error;
            }
            
            document.getElementById('payment-loading').style.display = 'none';
        });
    }
    
    // Formatear número de tarjeta en tiempo real
    const cardInput = document.getElementById('card_number');
    if (cardInput) {
        cardInput.addEventListener('input', function() {
            formatCardNumber(this);
        });
    }
    
    // Crear panel de debug
    createDebugPanel();
});