<?php

namespace App\Services;

/**
 * RC4 Stream Cipher Implementation
 * 
 * WARNING: RC4 is a deprecated algorithm with known security vulnerabilities.
 * This implementation is for EDUCATIONAL PURPOSES ONLY.
 * DO NOT use in production systems or for protecting sensitive data.
 * 
 * Known vulnerabilities:
 * - Biased keystream output
 * - Related-key attacks
 * - Weak key combinations
 * - Distinguishing attacks on the keystream
 * 
 * Usage: Educational demonstration of stream cipher concepts
 */
class RC4EncryptionService
{
    /**
     * Encrypt plaintext using RC4 algorithm
     * 
     * @param string $plaintext Text to encrypt
     * @param string $key Encryption key (40-2048 bits recommended)
     * @return string Base64 encoded ciphertext
     */
    public function encrypt(string $plaintext, string $key): string
    {
        // Validate key length
        $this->validateKey($key);
        
        // Generate keystream and perform XOR
        $keystream = $this->generateKeystream($key, strlen($plaintext));
        $ciphertext = $this->xorStrings($plaintext, $keystream);
        
        // Return base64 encoded result
        return base64_encode($ciphertext);
    }
    
    /**
     * Decrypt ciphertext using RC4 algorithm
     * 
     * @param string $ciphertext Base64 encoded ciphertext
     * @param string $key Decryption key
     * @return string Decrypted plaintext
     */
    public function decrypt(string $ciphertext, string $key): string
    {
        // Validate key length
        $this->validateKey($key);
        
        // Decode base64
        $ciphertext = base64_decode($ciphertext);
        
        // Generate keystream and perform XOR (same operation as encryption)
        $keystream = $this->generateKeystream($key, strlen($ciphertext));
        $plaintext = $this->xorStrings($ciphertext, $keystream);
        
        return $plaintext;
    }
    
    /**
     * Generate RC4 keystream
     * 
     * @param string $key Encryption key
     * @param int $length Required keystream length
     * @return string Keystream bytes
     */
    private function generateKeystream(string $key, int $length): string
    {
        // Initialize state array (S-box)
        $S = range(0, 255);
        
        // Key-scheduling algorithm (KSA)
        $keyLength = strlen($key);
        $j = 0;
        
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $S[$i] + ord($key[$i % $keyLength])) % 256;
            // Swap S[i] and S[j]
            $temp = $S[$i];
            $S[$i] = $S[$j];
            $S[$j] = $temp;
        }
        
        // Pseudo-random generation algorithm (PRGA)
        $keystream = '';
        $i = 0;
        $j = 0;
        
        for ($n = 0; $n < $length; $n++) {
            $i = ($i + 1) % 256;
            $j = ($j + $S[$i]) % 256;
            
            // Swap S[i] and S[j]
            $temp = $S[$i];
            $S[$i] = $S[$j];
            $S[$j] = $temp;
            
            // Generate keystream byte
            $K = $S[($S[$i] + $S[$j]) % 256];
            $keystream .= chr($K);
        }
        
        return $keystream;
    }
    
    /**
     * XOR two strings byte by byte
     * 
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return string XOR result
     */
    private function xorStrings(string $str1, string $str2): string
    {
        $result = '';
        $length = min(strlen($str1), strlen($str2));
        
        for ($i = 0; $i < $length; $i++) {
            $result .= chr(ord($str1[$i]) ^ ord($str2[$i]));
        }
        
        return $result;
    }
    
    /**
     * Validate encryption key
     * 
     * @param string $key Key to validate
     * @throws \InvalidArgumentException
     */
    private function validateKey(string $key): void
    {
        $keyLength = strlen($key);
        
        // RC4 accepts variable key sizes, but we enforce minimum/maximum for security
        if ($keyLength < 5) {
            throw new \InvalidArgumentException('RC4 key too short. Minimum 5 bytes required.');
        }
        
        if ($keyLength > 256) {
            throw new \InvalidArgumentException('RC4 key too long. Maximum 256 bytes allowed.');
        }
    }
    
    /**
     * Generate a random RC4 key
     * 
     * @param int $length Key length in bytes (default: 16)
     * @return string Random key
     */
    public function generateKey(int $length = 16): string
    {
        return bin2hex(random_bytes($length));
    }
}
