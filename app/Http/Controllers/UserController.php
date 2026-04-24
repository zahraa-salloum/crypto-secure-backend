<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * UserController
 * 
 * Handles user profile management operations including profile updates,
 * password changes, and account deletion. All operations require authentication.
 */
class UserController extends Controller
{
    /**
     * Get the authenticated user's profile
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile()
    {
        try {
            $user = auth()->user();

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'id'               => $user->id,
                    'name'             => $user->name,
                    'email'            => $user->email,
                    'avatar'           => $user->avatar,
                    'google_id'        => $user->google_id,
                    'user_type_id'     => $user->user_type_id,
                    'isAdmin'          => $user->isAdmin(),
                    'theme_preference' => $user->theme_preference ?? 'light',
                    'created_at'       => $user->created_at->toISOString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Update the authenticated user's profile
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name'             => 'sometimes|required|string|max:255',
            'avatar'           => 'sometimes|nullable|url|max:500',
            'theme_preference' => 'sometimes|in:light,dark',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();

            // Update only the provided fields (email is not editable)
            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('avatar')) {
                $updateData['avatar'] = $request->avatar;
            }
            if ($request->has('theme_preference')) {
                $updateData['theme_preference'] = $request->theme_preference;
            }

            // Update the user
            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'updated_at' => $user->updated_at->toISOString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Change the authenticated user's password
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();

            // Check if user has a password (might be Google OAuth user)
            if (!$user->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change password for OAuth accounts'
                ], 422);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => ['current_password' => ['Current password is incorrect']]
                ], 422);
            }

            // Check if new password is same as current
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password',
                    'errors' => ['new_password' => ['New password must be different from current password']]
                ], 422);
            }

            // Update the password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Optionally, revoke all existing tokens to force re-login on all devices
            // $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Delete the authenticated user's account
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAccount(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();

            // Verify password for security
            if ($user->password && !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect',
                    'errors' => ['password' => ['Password is incorrect']]
                ], 422);
            }

            // Revoke all tokens
            $user->tokens()->delete();

            // Permanently delete the user so the email can be re-registered
            $user->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account deletion failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Search for users (for chat functionality)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsers(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->query;
            $currentUserId = auth()->id();

            // Search for users by name or email (excluding current user)
            $users = \App\Models\User::where('id', '!=', $currentUserId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', '%' . $query . '%')
                      ->orWhere('email', 'like', '%' . $query . '%');
                })
                ->select('id', 'name', 'email', 'avatar')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Users found',
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Upload profile photo (stored as base64 in database)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPhoto(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|string', // base64 encoded image
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();
            $base64 = $request->avatar;

            // Validate it's actually a base64 image (data:image/...;base64,...)
            if (!preg_match('/^data:image\/(jpeg|png|jpg|gif|webp);base64,/', $base64)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image format'
                ], 422);
            }

            // Check base64 size (5MB max - base64 is ~33% larger than binary)
            $sizeInBytes = (strlen($base64) * 3) / 4;
            if ($sizeInBytes > 5 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image must be less than 5MB'
                ], 422);
            }

            // Save base64 string directly to database
            $user->update(['avatar' => $base64]);

            return response()->json([
                'success' => true,
                'message' => 'Photo updated successfully',
                'data' => [
                    'avatar' => $base64,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Photo upload failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }
}
