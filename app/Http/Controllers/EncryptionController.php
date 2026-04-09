<?php

namespace App\Http\Controllers;

use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Encryption Controller
 * Handles text and basic encryption operations
 */
class EncryptionController extends Controller
{
    protected EncryptionService $encryptionService;
    
    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }
    
    /**
     * Encrypt text
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function encryptText(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'plaintext' => 'required|string|max:10000',
            'algorithm' => 'required|string|in:RC4,A5/1',
            'key' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }
        
        try {
            $result = $this->encryptionService->encryptText(
                $request->plaintext,
                $request->algorithm,
                $request->key
            );
            
            // Increment user's encryption count
            if (auth()->check()) {
                auth()->user()->increment('encryption_count');
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Text encrypted successfully',
                'data' => [
                    'ciphertext' => $result['ciphertext'],
                    'algorithm' => $request->algorithm,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Encryption failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
    
    /**
     * Decrypt text
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function decryptText(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'ciphertext' => 'required|string',
            'algorithm' => 'required|string|in:RC4,A5/1',
            'key' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }
        
        try {
            $result = $this->encryptionService->decryptText(
                $request->ciphertext,
                $request->algorithm,
                $request->key
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Text decrypted successfully',
                'data' => [
                    'plaintext' => $result['plaintext'],
                    'algorithm' => $request->algorithm,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Decryption failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
    
    /**
     * Generate random encryption key
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generateKey(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'algorithm' => 'required|string|in:RC4,A5/1',
            'length' => 'integer|min:8|max:256',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }
        
        try {
            // Set default length based on algorithm
            $length = $request->input('length', $request->algorithm === 'A5/1' ? 16 : 32);
            
            // Generate cryptographically secure random key
            $key = bin2hex(random_bytes($length / 2));
            
            return response()->json([
                'success' => true,
                'message' => 'Key generated successfully',
                'data' => [
                    'key' => $key,
                    'algorithm' => $request->algorithm,
                    'length' => strlen($key),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Key generation failed',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Increment user's encryption count
     * Called when user performs client-side encryption or decryption
     * 
     * @return JsonResponse
     */
    public function incrementCount(): JsonResponse
    {
        try {
            if (auth()->check()) {
                auth()->user()->increment('encryption_count');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Encryption count incremented',
                    'data' => [
                        'count' => auth()->user()->encryption_count
                    ]
                ], 200);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to increment count',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}
