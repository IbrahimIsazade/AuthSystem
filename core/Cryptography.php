<?php

class Cryptography {
    private const ALGO = 'aes-256-cbc';

    public static function generateMasterKey(): string {
        return bin2hex(random_bytes(16));
    }
    
    public static function encrypt(string $plaintext, string $encryptionKey): string {
        $ivLength = openssl_cipher_iv_length(self::ALGO);
        $iv = openssl_random_pseudo_bytes($ivLength);

        // Hash the provided key to ensure it's exactly 256 bits (32 bytes)
        $secureKey = hash('sha256', $encryptionKey, true);

        $ciphertext = openssl_encrypt($plaintext, self::ALGO, $secureKey, OPENSSL_RAW_DATA, $iv);

        // Prepend IV to ciphertext for storage
        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypts AES-256-CBC encrypted data.
     */
    public static function decrypt(string $payload, string $decryptionKey): ?string {
        $data = base64_decode($payload);
        $ivLength = openssl_cipher_iv_length(self::ALGO);

        if (strlen($data) < $ivLength) {
            return null; // Invalid payload
        }

        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);

        $secureKey = hash('sha256', $decryptionKey, true);

        $plaintext = openssl_decrypt($ciphertext, self::ALGO, $secureKey, OPENSSL_RAW_DATA, $iv);

        return $plaintext !== false ? $plaintext : null;
    }
}