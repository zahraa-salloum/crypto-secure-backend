<?php

namespace App\Http\Controllers;

use App\Models\BannedEmail;
use App\Models\EncryptedFile;
use App\Models\Message;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * AdminController
 * Handles administration actions: statistics, user management, banning.
 * All routes protected by auth:sanctum + admin middleware.
 */
class AdminController extends Controller
{
    // -----------------------------------------------------------------------
    // Statistics
    // -----------------------------------------------------------------------

    /**
     * Return platform-wide statistics for the admin dashboard.
     */
    public function stats(): JsonResponse
    {
        try {
            $totalUsers         = User::where('user_type_id', UserType::USER)->count();
            $totalAdmins        = User::where('user_type_id', UserType::ADMIN)->count();
            $bannedUsers        = User::where('is_banned', true)->count();
            $totalFiles         = EncryptedFile::count();
            $totalMessages      = Message::count();
            $totalStorageBytes  = EncryptedFile::sum('file_size') ?? 0;
            $newUsersThisMonth  = User::where('user_type_id', UserType::USER)
                                      ->whereMonth('created_at', now()->month)
                                      ->whereYear('created_at', now()->year)
                                      ->count();

            return response()->json([
                'success' => true,
                'message' => 'Admin stats retrieved successfully',
                'data' => [
                    'total_users'           => $totalUsers,
                    'total_admins'          => $totalAdmins,
                    'banned_users'          => $bannedUsers,
                    'total_files'           => $totalFiles,
                    'total_messages'        => $totalMessages,
                    'total_storage_bytes'   => $totalStorageBytes,
                    'new_users_this_month'  => $newUsersThisMonth,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admin stats',
                'errors'  => [$e->getMessage()],
            ], 500);
        }
    }

    // -----------------------------------------------------------------------
    // User listing
    // -----------------------------------------------------------------------

    /**
     * List all users (paginated, searchable).
     */
    public function listUsers(Request $request): JsonResponse
    {
        try {
            $query = User::with('userType')
                ->select('id', 'name', 'email', 'user_type_id', 'is_banned', 'created_at');

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($typeId = $request->query('user_type_id')) {
                $query->where('user_type_id', (int) $typeId);
            }

            $users = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved',
                'data'    => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'errors'  => [$e->getMessage()],
            ], 500);
        }
    }

    // -----------------------------------------------------------------------
    // Create user / admin
    // -----------------------------------------------------------------------

    /**
     * Create a new user or admin account.
     */
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email|max:255',
            'password'      => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'user_type_id'  => ['required', Rule::in([UserType::ADMIN, UserType::USER])],
        ]);

        // Reject if email is banned
        if (BannedEmail::where('email', $validated['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email address has been banned and cannot be used.',
                'errors'  => ['email' => ['This email is banned.']],
            ], 422);
        }

        try {
            $user = User::create([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'password'     => Hash::make($validated['password']),
                'user_type_id' => (int) $validated['user_type_id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data'    => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'email'        => $user->email,
                    'user_type_id' => $user->user_type_id,
                    'created_at'   => $user->created_at->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account',
                'errors'  => [$e->getMessage()],
            ], 500);
        }
    }

    // -----------------------------------------------------------------------
    // Ban / Unban
    // -----------------------------------------------------------------------

    /**
     * Ban a user.
     * Sets is_banned=true on the user record and records the email in banned_emails.
     */
    public function banUser(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $admin  = $request->user();
        $target = User::findOrFail($userId);

        if ($target->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot ban your own account.',
                'errors'  => ['You cannot ban yourself.'],
            ], 422);
        }

        if ($target->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin accounts cannot be banned.',
                'errors'  => ['Cannot ban an admin.'],
            ], 422);
        }

        try {
            $target->update(['is_banned' => true]);

            // Soft-delete all active tokens so the user is logged out immediately
            $target->tokens()->delete();

            // Record the email so it cannot be re-registered
            BannedEmail::updateOrCreate(
                ['email' => $target->email],
                ['banned_by' => $admin->id, 'reason' => $validated['reason'] ?? null]
            );

            return response()->json([
                'success' => true,
                'message' => "User {$target->email} has been banned.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to ban user',
                'errors'  => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Unban a user.
     * Removes is_banned flag and removes the email from the banned list.
     */
    public function unbanUser(int $userId): JsonResponse
    {
        $target = User::findOrFail($userId);

        try {
            $target->update(['is_banned' => false]);
            BannedEmail::where('email', $target->email)->delete();

            return response()->json([
                'success' => true,
                'message' => "User {$target->email} has been unbanned.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unban user',
                'errors'  => [$e->getMessage()],
            ], 500);
        }
    }
}
