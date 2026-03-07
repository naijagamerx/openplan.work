<?php
/**
 * Encryption Class - AES-256-GCM encryption/decryption
 */

class Encryption {
    private string $key;
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 16;
    private const TAG_LENGTH = 16;
    
    public function __construct(string $masterPassword) {
        $this->key = hash('sha256', $masterPassword, true);
    }
    
    /**
     * Encrypt data
     */
    public function encrypt(mixed $data): string {
        $plaintext = is_string($data) ? $data : json_encode($data);
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public function decrypt(string $encryptedData): mixed {
        $decoded = base64_decode($encryptedData);
        
        if ($decoded === false || strlen($decoded) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new Exception('Invalid encrypted data');
        }
        
        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $encrypted = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            throw new Exception('Decryption failed - invalid key or corrupted data');
        }
        
        $json = json_decode($decrypted, true);
        return $json !== null ? $json : $decrypted;
    }
    
    /**
     * Verify if a password matches the stored hash
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Hash a password
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
