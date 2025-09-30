<?php
if (!defined('ABSPATH')) {
    exit;
}

class Mercantil_Encryption {
    
    const CIPHER_NAME = "aes-128-ecb";
    const KEY_LENGTH = 16;
    
    /**
     * Crear hash de la clave
     */
    public static function create_key_hash($key) {
        if (empty($key)) {
            throw new Exception('La clave secreta no puede estar vacía');
        }
        
        $key_hash = hash("sha256", $key, true);        
        return substr($key_hash, 0, self::KEY_LENGTH);
    }
    
    /**
     * Ajustar la longitud de la clave
     */
    private static function fix_key_length($key) {
        $key_length = strlen($key);
        
        if ($key_length < self::KEY_LENGTH) {
            return str_pad($key, self::KEY_LENGTH, "0");
        }
        
        if ($key_length > self::KEY_LENGTH) {
            return substr($key, 0, self::KEY_LENGTH);
        }

        return $key;
    }
    
    /**
     * Encriptar datos
     */
    public static function encrypt($key, $data) {
        if (empty($data)) {
            throw new Exception('Los datos a encriptar no pueden estar vacíos');
        }
        
        $fixed_key = self::fix_key_length($key);
        $encrypted = openssl_encrypt($data, self::CIPHER_NAME, $fixed_key, OPENSSL_RAW_DATA);
        
        if ($encrypted === false) {
            throw new Exception('Error en la encriptación: ' . openssl_error_string());
        }
        
        return base64_encode($encrypted);
    }
    
    /**
     * Desencriptar datos
     */
    public static function decrypt($key, $encrypted_data) {
        if (empty($encrypted_data)) {
            throw new Exception('Los datos encriptados no pueden estar vacíos');
        }
        
        $fixed_key = self::fix_key_length($key);
        $decrypted = openssl_decrypt(base64_decode($encrypted_data), self::CIPHER_NAME, $fixed_key, OPENSSL_RAW_DATA);
        
        if ($decrypted === false) {
            throw new Exception('Error en la desencriptación: ' . openssl_error_string());
        }
        
        return $decrypted;
    }
}

// Funciones helper
function mercantil_encrypt_data($data, $secret_key) {
    try {
        $key_hash = Mercantil_Encryption::create_key_hash($secret_key);
        return Mercantil_Encryption::encrypt($key_hash, $data);
    } catch (Exception $e) {
        error_log('Mercantil Encryption Error: ' . $e->getMessage());
        return '';
    }
}

function mercantil_decrypt_data($encrypted_data, $secret_key) {
    try {
        $key_hash = Mercantil_Encryption::create_key_hash($secret_key);
        return Mercantil_Encryption::decrypt($key_hash, $encrypted_data);
    } catch (Exception $e) {
        error_log('Mercantil Decryption Error: ' . $e->getMessage());
        return '';
    }
}
?>