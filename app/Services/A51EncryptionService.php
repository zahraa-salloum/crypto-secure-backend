<?php

namespace App\Services;

/**
 * A5/1 Stream Cipher Implementation
 * 
 * WARNING: A5/1 is a deprecated algorithm with known security vulnerabilities.
 * This implementation is for EDUCATIONAL PURPOSES ONLY.
 * DO NOT use in production systems or for protecting sensitive data.
 * 
 * Known vulnerabilities:
 * - Vulnerable to known-plaintext attacks
 * - Can be broken with moderate computational resources
 * - Weak against passive attacks
 * - Rainbow table attacks possible
 * 
 * Historical context: Used in GSM mobile phone encryption
 * Current status: Deprecated and replaced by A5/3 (KASUMI)
 * 
 * Usage: Educational demonstration of LFSR-based stream ciphers
 */
class A51EncryptionService
{
    // LFSR lengths
    private const LFSR1_LENGTH = 19;
    private const LFSR2_LENGTH = 22;
    private const LFSR3_LENGTH = 23;
    
    // Clocking bits (middle bit of each LFSR)
    private const LFSR1_CLOCK_BIT = 8;
    private const LFSR2_CLOCK_BIT = 10;
    private const LFSR3_CLOCK_BIT = 10;
    
    // Tapping bits for feedback (0-indexed from LSB)
    private const LFSR1_TAPS = [13, 16, 17, 18]; // Taps at positions 13,16,17,18
    private const LFSR2_TAPS = [20, 21];         // Taps at positions 20,21
    private const LFSR3_TAPS = [7, 20, 21, 22];  // Taps at positions 7,20,21,22
    
    /**
     * Encrypt plaintext using A5/1 algorithm
     * 
     * @param string $plaintext Text to encrypt
     * @param string $key 64-bit key (16 hex characters)
     * @return string Base64 encoded ciphertext
     */
    public function encrypt(string $plaintext, string $key): string
    {
        // Validate key (must be 64 bits / 8 bytes / 16 hex chars)
        $this->validateKey($key);
        
        // Convert hex key to binary
        $keyBinary = hex2bin($key);
        
        // Generate keystream
        $keystream = $this->generateKeystream($keyBinary, strlen($plaintext));
        
        // XOR with plaintext
        $ciphertext = $this->xorStrings($plaintext, $keystream);
        
        // Return base64 encoded result
        return base64_encode($ciphertext);
    }
    
    /**
     * Decrypt ciphertext using A5/1 algorithm
     * 
     * @param string $ciphertext Base64 encoded ciphertext
     * @param string $key 64-bit key (16 hex characters)
     * @return string Decrypted plaintext
     */
    public function decrypt(string $ciphertext, string $key): string
    {
        // Validate key
        $this->validateKey($key);
        
        // Decode base64
        $ciphertext = base64_decode($ciphertext);
        
        // Convert hex key to binary
        $keyBinary = hex2bin($key);
        
        // Generate keystream (same as encryption)
        $keystream = $this->generateKeystream($keyBinary, strlen($ciphertext));
        
        // XOR with ciphertext (stream cipher property: encrypt = decrypt)
        $plaintext = $this->xorStrings($ciphertext, $keystream);
        
        return $plaintext;
    }
    
    /**
     * Generate A5/1 keystream
     * 
     * @param string $key Binary key (8 bytes)
     * @param int $length Required keystream length
     * @return string Keystream bytes
     */
    private function generateKeystream(string $key, int $length): string
    {
        // Initialize LFSRs with zeros
        $lfsr1 = 0;
        $lfsr2 = 0;
        $lfsr3 = 0;
        
        // Load key into LFSRs
        for ($i = 0; $i < 64; $i++) {
            $keyBit = ($key[$i >> 3] >> (7 - ($i & 7))) & 1;
            
            $lfsr1 = $this->clockLFSR($lfsr1, self::LFSR1_TAPS, self::LFSR1_LENGTH, $keyBit);
            $lfsr2 = $this->clockLFSR($lfsr2, self::LFSR2_TAPS, self::LFSR2_LENGTH, $keyBit);
            $lfsr3 = $this->clockLFSR($lfsr3, self::LFSR3_TAPS, self::LFSR3_LENGTH, $keyBit);
        }
        
        // Generate keystream
        $keystream = '';
        
        for ($i = 0; $i < $length; $i++) {
            $byte = 0;
            
            // Generate 8 bits for each byte
            for ($j = 0; $j < 8; $j++) {
                // Determine majority bit
                $majority = $this->getMajorityBit($lfsr1, $lfsr2, $lfsr3);
                
                // Clock LFSRs based on majority rule
                if ($this->getBit($lfsr1, self::LFSR1_CLOCK_BIT) == $majority) {
                    $lfsr1 = $this->clockLFSR($lfsr1, self::LFSR1_TAPS, self::LFSR1_LENGTH);
                }
                if ($this->getBit($lfsr2, self::LFSR2_CLOCK_BIT) == $majority) {
                    $lfsr2 = $this->clockLFSR($lfsr2, self::LFSR2_TAPS, self::LFSR2_LENGTH);
                }
                if ($this->getBit($lfsr3, self::LFSR3_CLOCK_BIT) == $majority) {
                    $lfsr3 = $this->clockLFSR($lfsr3, self::LFSR3_TAPS, self::LFSR3_LENGTH);
                }
                
                // Output bit is XOR of MSBs
                $outputBit = $this->getBit($lfsr1, self::LFSR1_LENGTH - 1) ^
                            $this->getBit($lfsr2, self::LFSR2_LENGTH - 1) ^
                            $this->getBit($lfsr3, self::LFSR3_LENGTH - 1);
                
                $byte = ($byte << 1) | $outputBit;
            }
            
            $keystream .= chr($byte);
        }
        
        return $keystream;
    }
    
    /**
     * Clock an LFSR forward one step
     * 
     * @param int $lfsr Current LFSR state
     * @param array $taps Tap positions for feedback
     * @param int $length LFSR length
     * @param int|null $inputBit Optional input bit for initialization
     * @return int New LFSR state
     */
    private function clockLFSR(int $lfsr, array $taps, int $length, ?int $inputBit = null): int
    {
        // Calculate feedback bit
        $feedback = 0;
        foreach ($taps as $tap) {
            $feedback ^= $this->getBit($lfsr, $tap);
        }
        
        // If input bit provided (during key loading), XOR it with feedback
        if ($inputBit !== null) {
            $feedback ^= $inputBit;
        }
        
        // Shift left and add feedback bit at LSB
        $lfsr = (($lfsr << 1) | $feedback) & ((1 << $length) - 1);
        
        return $lfsr;
    }
    
    /**
     * Get bit at specific position
     * 
     * @param int $value Value to extract bit from
     * @param int $position Bit position (0 = LSB)
     * @return int Bit value (0 or 1)
     */
    private function getBit(int $value, int $position): int
    {
        return ($value >> $position) & 1;
    }
    
    /**
     * Get majority bit from the three clocking bits
     * 
     * @param int $lfsr1 First LFSR
     * @param int $lfsr2 Second LFSR
     * @param int $lfsr3 Third LFSR
     * @return int Majority bit (0 or 1)
     */
    private function getMajorityBit(int $lfsr1, int $lfsr2, int $lfsr3): int
    {
        $bit1 = $this->getBit($lfsr1, self::LFSR1_CLOCK_BIT);
        $bit2 = $this->getBit($lfsr2, self::LFSR2_CLOCK_BIT);
        $bit3 = $this->getBit($lfsr3, self::LFSR3_CLOCK_BIT);
        
        // Return majority (at least 2 of 3 bits)
        return ($bit1 & $bit2) | ($bit2 & $bit3) | ($bit1 & $bit3);
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
     * @param string $key Key to validate (hex string)
     * @throws \InvalidArgumentException
     */
    private function validateKey(string $key): void
    {
        // A5/1 uses 64-bit key (16 hex characters)
        if (strlen($key) !== 16) {
            throw new \InvalidArgumentException('A5/1 key must be exactly 64 bits (16 hex characters).');
        }
        
        // Verify hex format
        if (!ctype_xdigit($key)) {
            throw new \InvalidArgumentException('A5/1 key must be a valid hexadecimal string.');
        }
    }
    
    /**
     * Generate a random A5/1 key
     * 
     * @return string 64-bit key as hex string
     */
    public function generateKey(): string
    {
        return bin2hex(random_bytes(8)); // 8 bytes = 64 bits
    }
}
