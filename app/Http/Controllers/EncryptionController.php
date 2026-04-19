<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Encryption Controller
 * Tracks client-side encryption operations
 */
class EncryptionController extends Controller
{
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
