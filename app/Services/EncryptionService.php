<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Main Encryption Service
 * Facade for accessing different encryption algorithms
 * Handles encryption/decryption routing and validation
 */
class EncryptionService
{
    private RC4EncryptionService $rc4Service;
    private A51EncryptionService $a51Service;
    
    public function __construct(
        RC4EncryptionService $rc4Service,
        A51EncryptionService $a51Service
    ) {
        $this->rc4Service = $rc4Service;
        $this->a51Service = $a51Service;
    }
    
    /**
     * Encrypt text using specified algorithm
     * 
     * @param string $plaintext Text to encrypt
     * @param string $algorithm Algorithm to use (RC4 or A5/1)
     * @param string $key Encryption key
     * @return array Result containing ciphertext and metadata
     */
    public function encryptText(string $plaintext, string $algorithm, string $key): array
    {
        $this->validateAlgorithm($algorithm);
        
        try {
            $ciphertext = match ($algorithm) {
                'RC4' => $this->rc4Service->encrypt($plaintext, $key),
                'A5/1' => $this->a51Service->encrypt($plaintext, $key),
                default => throw new \InvalidArgumentException("Unsupported algorithm: {$algorithm}")
            };
            
            // Log encryption activity (without sensitive data)
            Log::info('Text encrypted', [
                'algorithm' => $algorithm,
                'plaintext_length' => strlen($plaintext),
                'timestamp' => now()
            ]);
            
            return [
                'success' => true,
                'ciphertext' => $ciphertext,
                'algorithm' => $algorithm,
                'timestamp' => now()->toIso8601String()
            ];
            
        } catch (\Exception $e) {
            Log::error('Encryption failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Decrypt text using specified algorithm
     * 
     * @param string $ciphertext Encrypted text
     * @param string $algorithm Algorithm used for encryption
     * @param string $key Decryption key
     * @return array Result containing plaintext and metadata
     */
    public function decryptText(string $ciphertext, string $algorithm, string $key): array
    {
        $this->validateAlgorithm($algorithm);
        
        try {
            $plaintext = match ($algorithm) {
                'RC4' => $this->rc4Service->decrypt($ciphertext, $key),
                'A5/1' => $this->a51Service->decrypt($ciphertext, $key),
                default => throw new \InvalidArgumentException("Unsupported algorithm: {$algorithm}")
            };
            
            // Log decryption activity
            Log::info('Text decrypted', [
                'algorithm' => $algorithm,
                'timestamp' => now()
            ]);
            
            return [
                'success' => true,
                'plaintext' => $plaintext,
                'algorithm' => $algorithm,
                'timestamp' => now()->toIso8601String()
            ];
            
        } catch (\Exception $e) {
            Log::error('Decryption failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Generate random key for specified algorithm
     * 
     * @param string $algorithm Algorithm to generate key for
     * @return string Generated key
     */
    public function generateKey(string $algorithm): string
    {
        $this->validateAlgorithm($algorithm);
        
        return match ($algorithm) {
            'RC4' => $this->rc4Service->generateKey(),
            'A5/1' => $this->a51Service->generateKey(),
            default => throw new \InvalidArgumentException("Unsupported algorithm: {$algorithm}")
        };
    }
    
    /**
     * Validate algorithm name
     * 
     * @param string $algorithm Algorithm to validate
     * @throws \InvalidArgumentException
     */
    private function validateAlgorithm(string $algorithm): void
    {
        $supportedAlgorithms = ['RC4', 'A5/1'];
        
        if (!in_array($algorithm, $supportedAlgorithms)) {
            throw new \InvalidArgumentException(
                "Unsupported algorithm: {$algorithm}. Supported: " . implode(', ', $supportedAlgorithms)
            );
        }
    }
    
    /**
     * Get information about an algorithm
     * 
     * @param string $algorithm Algorithm name
     * @return array Algorithm information
     */
    public function getAlgorithmInfo(string $algorithm): array
    {
        $this->validateAlgorithm($algorithm);
        
        $info = [
            'RC4' => [
                'name' => 'RC4 (Rivest Cipher 4)',
                'type' => 'Stream Cipher',
                'key_size' => 'Variable (40-2048 bits recommended)',
                'status' => 'Deprecated',
                'vulnerabilities' => [
                    'Biased keystream output',
                    'Related-key attacks',
                    'Weak key combinations',
                    'Distinguishing attacks'
                ],
                'historical_uses' => ['SSL/TLS', 'WEP', 'WPA'],
                'warning' => 'Not secure for production use. Educational purposes only.'
            ],
            'A5/1' => [
                'name' => 'A5/1',
                'type' => 'Stream Cipher (LFSR-based)',
                'key_size' => '64 bits',
                'status' => 'Deprecated',
                'vulnerabilities' => [
                    'Known-plaintext attacks',
                    'Vulnerable to passive attacks',
                    'Can be cracked with moderate resources',
                    'Rainbow table attacks possible'
                ],
                'historical_uses' => ['GSM mobile phone encryption'],
                'warning' => 'Broken algorithm. Do not use for actual security.'
            ]
        ];
        
        return $info[$algorithm];
    }
}
