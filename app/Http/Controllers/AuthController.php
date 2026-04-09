<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\BannedEmail;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Authentication Controller
 * Handles user registration, login, logout, and password management
 */
class AuthController extends Controller
{
    /**
     * Register a new user
     * 
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Reject banned emails at registration time
        if (BannedEmail::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email address has been banned and cannot be used.',
                'errors'  => ['email' => ['This email is banned.']],
            ], 422);
        }

        try {
            // Create new user with hashed password (default role: user)
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),
                'user_type_id' => UserType::USER,
            ]);
            
            // Generate authentication token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => [
                        'id'           => $user->id,
                        'name'         => $user->name,
                        'email'        => $user->email,
                        'avatar'       => $user->avatar ?? null,
                        'userTypeId'   => $user->user_type_id,
                        'isAdmin'      => $user->isAdmin(),
                        'createdAt'    => $user->created_at->toISOString(),
                    ],
                    'token' => $token,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
    
    /**
     * Login user
     * 
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Attempt to authenticate user
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'errors' => ['Invalid email or password'],
                ], 401);
            }
            
            $user = Auth::user();

            // Reject banned accounts
            if ($user->is_banned) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been banned. Please contact support.',
                    'errors'  => ['Your account is banned.'],
                ], 403);
            }
            
            // Generate authentication token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id'         => $user->id,
                        'name'       => $user->name,
                        'email'      => $user->email,
                        'avatar'     => $user->avatar ?? null,
                        'userTypeId' => $user->user_type_id,
                        'isAdmin'    => $user->isAdmin(),
                        'createdAt'  => $user->created_at->toISOString(),
                    ],
                    'token' => $token,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
    
    /**
     * Logout user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke all tokens for the user
            $request->user()->tokens()->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Logout successful',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
    
    /**
     * Get current authenticated user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar'     => $user->avatar ?? null,
                'userTypeId' => $user->user_type_id,
                'isAdmin'    => $user->isAdmin(),
                'createdAt'  => $user->created_at->toISOString(),
            ],
        ], 200);
    }
    
    /**
     * Send password reset link
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to send reset link. Please try again later.',
            'errors'  => [__($status)],
        ], 429);
    }
    
    /**
     * Reset password with token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing tokens so old sessions are invalidated
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successful. You can now log in.',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired reset token.',
            'errors'  => [__($status)],
        ], 422);
    }
}
