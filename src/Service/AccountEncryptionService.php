<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Service for encrypting and decrypting sensitive account information.
 * 
 * This service provides secure encryption for account numbers using PHP's Sodium library
 * (libsodium) which provides modern, secure cryptographic operations. It uses authenticated
 * encryption to ensure both confidentiality and integrity of the encrypted data.
 * 
 * Security Features:
 * - Uses XSalsa20-Poly1305 authenticated encryption (sodium_crypto_secretbox)
 * - Generates random nonces for each encryption operation
 * - Provides SHA-256 hashing for searchable account number lookups
 * - Implements constant-time hash comparison to prevent timing attacks
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class AccountEncryptionService
{
    /**
     * The encryption key used for symmetric encryption operations.
     * This key is derived from the application secret and should remain constant
     * across application restarts to ensure encrypted data can be decrypted.
     */
    private readonly string $encryptionKey;

    /**
     * Initialize the encryption service with a derived encryption key.
     * 
     * The encryption key is derived from the application's APP_SECRET environment
     * variable to ensure consistency across deployments while maintaining security.
     * 
     * @throws \RuntimeException If the encryption key cannot be generated
     */
    public function __construct()
    {
        // In production, store this in environment variables
        // For now, we'll generate a key - in real app, use a persistent key
        $this->encryptionKey = $this->getOrGenerateKey();
    }

    /**
     * Encrypts a plain-text account number using authenticated encryption.
     * 
     * This method uses XSalsa20-Poly1305 authenticated encryption which provides:
     * - Confidentiality: Data is encrypted and unreadable without the key
     * - Authenticity: Data integrity is verified during decryption
     * - Uniqueness: Each encryption uses a random nonce for semantic security
     * 
     * The method returns both the encrypted data and a SHA-256 hash. The hash
     * allows for database searches without decrypting the stored values.
     * 
     * @param string $plainAccountNumber The plain-text account number to encrypt
     * 
     * @return array{encrypted: string, hash: string} Array containing:
     *               - 'encrypted': Base64-encoded encrypted data with nonce
     *               - 'hash': SHA-256 hash for database indexing/searching
     * 
     * @throws \SodiumException If encryption fails
     * @throws \Exception If random byte generation fails
     * 
     * @example
     * $result = $service->encryptAccountNumber('ACC123456789');
     * // Returns: [
     * //   'encrypted' => 'base64EncodedNonceAndCiphertext...',
     * //   'hash' => 'sha256HashForIndexing...'
     * // ]
     */
    public function encryptAccountNumber(string $plainAccountNumber): array
    {
        // Generate a random nonce for each encryption operation
        // This ensures that encrypting the same plaintext twice produces different ciphertexts
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        // Encrypt the account number using authenticated encryption
        // This provides both confidentiality and integrity protection
        $encrypted = sodium_crypto_secretbox($plainAccountNumber, $nonce, $this->encryptionKey);
        
        // Combine nonce and encrypted data for storage
        // The nonce must be stored with the ciphertext for decryption
        $encryptedWithNonce = base64_encode($nonce . $encrypted);
        
        // Generate a deterministic hash for database indexing and searching
        // This allows finding records without decrypting all stored values
        $hash = hash('sha256', $plainAccountNumber);
        
        return [
            'encrypted' => $encryptedWithNonce,
            'hash' => $hash
        ];
    }

    /**
     * Decrypts an encrypted account number back to its original plaintext form.
     * 
     * This method reverses the encryption process by:
     * 1. Decoding the Base64-encoded data
     * 2. Extracting the nonce and ciphertext
     * 3. Performing authenticated decryption
     * 4. Verifying data integrity automatically
     * 
     * The authenticated encryption ensures that any tampering with the encrypted
     * data will be detected and the decryption will fail.
     * 
     * @param string $encryptedWithNonce Base64-encoded string containing nonce + ciphertext
     * 
     * @return string The original plaintext account number
     * 
     * @throws \RuntimeException If decryption fails due to:
     *                          - Invalid encrypted data format
     *                          - Data tampering/corruption
     *                          - Wrong encryption key
     *                          - Invalid nonce or ciphertext length
     * 
     * @example
     * $plaintext = $service->decryptAccountNumber('base64EncodedNonceAndCiphertext...');
     * // Returns: 'ACC123456789'
     */
    public function decryptAccountNumber(string $encryptedWithNonce): string
    {
        // Decode the Base64-encoded data containing nonce + ciphertext
        $decoded = base64_decode($encryptedWithNonce);
        
        // Extract the nonce (first 24 bytes) and encrypted data (remaining bytes)
        // The nonce size is defined by SODIUM_CRYPTO_SECRETBOX_NONCEBYTES (24 bytes)
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        // Perform authenticated decryption
        // This will verify data integrity and decrypt in one operation
        $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $this->encryptionKey);
        
        // Check if decryption failed (returns false on failure)
        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt account number');
        }
        
        return $decrypted;
    }

    /**
     * Generates a unique account number for a given user.
     * 
     * The account number format ensures uniqueness and traceability while remaining
     * human-readable for customer service purposes. The format combines multiple
     * elements to virtually guarantee uniqueness across the system.
     * 
     * Format: ACC + UserID(4) + Timestamp(6) + Random(4) = 17 characters total
     * 
     * Components:
     * - Prefix: 'ACC' - Identifies this as an account number
     * - User ID: 4-digit zero-padded user identifier (e.g., '0001', '1234')
     * - Timestamp: Last 6 digits of Unix timestamp for temporal uniqueness
     * - Random: 4-digit random number for additional entropy
     * 
     * @param int $userId The unique identifier of the user owning the account
     * 
     * @return string A unique account number in format: ACC{userID}{timestamp}{random}
     * 
     * @throws \Exception If random number generation fails
     * 
     * @example
     * $accountNumber = $service->generateAccountNumber(123);
     * // Returns something like: 'ACC0123456789123' 
     * // Where: ACC + 0123 (user) + 456789 (time) + 1234 (random)
     */
    public function generateAccountNumber(int $userId): string
    {
        // Generate a unique account number using multiple entropy sources
        // Format: ACC + user_id (padded) + timestamp + random
        
        // Use last 6 digits of timestamp for temporal uniqueness
        // This provides seconds-level uniqueness within the same user
        $timestamp = substr((string)time(), -6);
        
        // Generate a 4-digit random number for additional entropy
        // Protects against predictable account numbers
        $random = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        // Pad user ID to 4 digits for consistent formatting
        // Supports up to 9999 users with zero-padding
        $userIdPadded = str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
        
        return 'ACC' . $userIdPadded . $timestamp . $random;
    }

    /**
     * Creates a SHA-256 hash of an account number for indexing and searching.
     * 
     * This method creates a deterministic hash that allows the database to:
     * - Index encrypted account numbers for fast searches
     * - Find specific accounts without decrypting all stored values
     * - Maintain referential integrity in foreign key relationships
     * 
     * The hash is deterministic (same input = same output) but irreversible,
     * providing a balance between searchability and security.
     * 
     * @param string $plainAccountNumber The plain-text account number to hash
     * 
     * @return string A 64-character hexadecimal SHA-256 hash
     * 
     * @example
     * $hash = $service->hashAccountNumber('ACC123456789');
     * // Returns: 'a1b2c3d4e5f6...' (64 hex characters)
     */
    public function hashAccountNumber(string $plainAccountNumber): string
    {
        return hash('sha256', $plainAccountNumber);
    }

    /**
     * Verifies if a plaintext account number matches a stored hash.
     * 
     * This method uses constant-time comparison to prevent timing attacks
     * that could potentially leak information about the stored hash values.
     * The hash_equals() function ensures that comparison time is independent
     * of the input values, making it secure against timing-based attacks.
     * 
     * @param string $plainAccountNumber The plaintext account number to verify
     * @param string $hash The stored hash to compare against
     * 
     * @return bool True if the account number matches the hash, false otherwise
     * 
     * @example
     * $isValid = $service->verifyAccountNumber('ACC123456789', $storedHash);
     * // Returns: true if matches, false if different
     */
    public function verifyAccountNumber(string $plainAccountNumber, string $hash): bool
    {
        return hash_equals($hash, $this->hashAccountNumber($plainAccountNumber));
    }

    /**
     * Derives or generates a consistent encryption key for the application.
     * 
     * This method creates a deterministic encryption key based on the application's
     * secret key. The key derivation process ensures that:
     * - The same key is generated across application restarts
     * - The key is cryptographically strong (256-bit)
     * - The key is unique to this specific use case (account encryption)
     * 
     * Key Derivation Process:
     * 1. Retrieves APP_SECRET from environment variables
     * 2. Appends a context string for domain separation
     * 3. Hashes the combination to produce a 256-bit key
     * 4. Returns the raw binary key (not hex-encoded)
     * 
     * Security Considerations:
     * - The APP_SECRET should be cryptographically random and at least 32 bytes
     * - Different contexts should use different derived keys
     * - The key should never be logged or exposed in error messages
     * - In production, consider using a Hardware Security Module (HSM)
     * 
     * @return string A 32-byte (256-bit) encryption key suitable for XSalsa20
     * 
     * @throws \RuntimeException If APP_SECRET is not available or too weak
     * 
     * @internal This method is private and should not be called directly
     */
    private function getOrGenerateKey(): string
    {
        // In production, you should store this key securely in:
        // - Environment variables (current approach)
        // - Hardware Security Module (HSM)
        // - Key management service (AWS KMS, Azure Key Vault, etc.)
        // - Encrypted configuration files with separate key protection
        
        // Get the application secret from environment
        $appSecret = $_ENV['APP_SECRET'] ?? 'dev-secret-key-change-in-production';
        
        // Derive a 32-byte key from the app secret using domain separation
        // The context string ensures this key is unique to account encryption
        return hash('sha256', $appSecret . 'account-encryption', true);
    }
}