<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\EncryptedFile;
use Illuminate\Support\Facades\DB;

/**
 * DashboardController
 * 
 * Provides dashboard statistics and analytics for users
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats()
    {
        try {
            $userId = auth()->id();
            $user = auth()->user();

            // 1. Total Conversations (Encrypted Messages count)
            $totalConversations = Conversation::where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId)
                ->count();

            // 2. Total Files Uploaded
            $totalFiles = EncryptedFile::where('user_id', $userId)->count();

            // 3. Total Encryptions (from user's encryption_count field)
            $totalEncryptions = $user->encryption_count ?? 0;

            // 4. Storage Used (sum of all file sizes)
            $storageUsed = EncryptedFile::where('user_id', $userId)
                ->sum('file_size') ?? 0;

            return response()->json([
                'success' => true,
                'message' => 'Dashboard stats retrieved successfully',
                'data' => [
                    'total_messages' => $totalConversations,
                    'total_files' => $totalFiles,
                    'total_encryptions' => $totalEncryptions,
                    'storage_used' => $storageUsed,
                    'storage_limit' => 10485760, // 10MB in bytes
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard stats',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }
}
