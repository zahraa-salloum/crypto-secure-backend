<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EncryptionController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminController;

/**
 * API Routes
 * Define all API endpoints with authentication guards
 */

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getDashboardStats']);
    
    // Encryption operations
    Route::prefix('encryption')->group(function () {
        Route::post('/encrypt', [EncryptionController::class, 'encryptText']);
        Route::post('/decrypt', [EncryptionController::class, 'decryptText']);
        Route::post('/generate-key', [EncryptionController::class, 'generateKey']);
        Route::post('/increment-count', [EncryptionController::class, 'incrementCount']);
    });
    
    // File operations
    Route::prefix('files')->group(function () {
        Route::get('/', [FileController::class, 'getUserFiles']);
        Route::post('/upload', [FileController::class, 'upload']);
        Route::get('/{id}/download', [FileController::class, 'download']);
        Route::delete('/{id}', [FileController::class, 'delete']);
        Route::post('/{id}/share', [FileController::class, 'share']);
    });
    
    // Chat/Conversations
    Route::prefix('chat')->group(function () {
        Route::get('/conversations', [ChatController::class, 'getConversations']);
        Route::post('/conversations', [ChatController::class, 'getOrCreateConversation']);
        Route::get('/conversations/{id}/messages', [ChatController::class, 'getMessages']);
        Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/conversations/{id}/mark-read', [ChatController::class, 'markAsRead']);
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);
    });
    
    // User profile and management
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::post('/photo', [UserController::class, 'uploadPhoto']);
        Route::put('/password', [UserController::class, 'changePassword']);
        Route::delete('/account', [UserController::class, 'deleteAccount']);
        Route::get('/search', [UserController::class, 'searchUsers']);
    });

    // Admin-only routes
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'listUsers']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::post('/users/{userId}/ban', [AdminController::class, 'banUser']);
        Route::post('/users/{userId}/unban', [AdminController::class, 'unbanUser']);
    });
});

// Public file sharing route (no auth required) - for shared file links
Route::get('/files/shared/{token}', [FileController::class, 'downloadShared']);

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toISOString(),
    ]);
});
