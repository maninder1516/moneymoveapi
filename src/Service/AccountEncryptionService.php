<?php

declare(strict_types=1);

namespace App\Service;

class AccountEncryptionService
{
    private readonly string $encryptionKey;

    public function __construct()
    {
        // In production, store this in environment variables
        // For now, we'll generate a key - in real app, use a persistent key
        $this->encryptionKey = $this->getOrGenerateKey();
    }

    public function encryptAccountNumber(string $plainAccountNumber): array
    {
        // Generate a random nonce for each encryption
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        // Encrypt the account number
        $encrypted = sodium_crypto_secretbox($plainAccountNumber, $nonce, $this->encryptionKey);
        
        // Combine nonce and encrypted data for storage
        $encryptedWithNonce = base64_encode($nonce . $encrypted);
        
        // Generate hash for unique lookups
        $hash = hash('sha256', $plainAccountNumber);
        
        return [
            'encrypted' => $encryptedWithNonce,
            'hash' => $hash
        ];
    }

    public function decryptAccountNumber(string $encryptedWithNonce): string
    {
        $decoded = base64_decode($encryptedWithNonce);
        
        // Extract nonce and encrypted data
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        // Decrypt
        $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $this->encryptionKey);
        
        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt account number');
        }
        
        return $decrypted;
    }

    public function generateAccountNumber(int $userId): string
    {
        // Generate a unique account number
        // Format: ACC + user_id (padded) + timestamp + random
        $timestamp = substr((string)time(), -6); // Last 6 digits of timestamp
        $random = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $userIdPadded = str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
        
        return 'ACC' . $userIdPadded . $timestamp . $random;
    }

    public function hashAccountNumber(string $plainAccountNumber): string
    {
        return hash('sha256', $plainAccountNumber);
    }

    public function verifyAccountNumber(string $plainAccountNumber, string $hash): bool
    {
        return hash_equals($hash, $this->hashAccountNumber($plainAccountNumber));
    }

    private function getOrGenerateKey(): string
    {
        // In production, you should store this key securely
        // For development, we'll use a consistent key based on app secret
        $appSecret = $_ENV['APP_SECRET'] ?? 'dev-secret-key-change-in-production';
        
        // Derive a 32-byte key from the app secret
        return hash('sha256', $appSecret . 'account-encryption', true);
    }
}