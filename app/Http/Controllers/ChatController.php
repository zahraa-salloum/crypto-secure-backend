<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\EncryptedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ChatController - CLIENT-SIDE ENCRYPTION
 * 
 * Handles encrypted real-time messaging between users.
 * Messages are encrypted CLIENT-SIDE using RC4 or A5/1.
 * Server NEVER sees encryption keys - zero-knowledge architecture.
 * Each message has a unique nonce for security.
 */
class ChatController extends Controller
{
    // No EncryptionService needed - client-side encryption only!

    /**
     * Get all conversations for the authenticated user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversations()
    {
        try {
            $userId = auth()->id();

            // Get all conversations where user is a participant
            $conversations = Conversation::where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId)
                ->with(['userOne:id,name,email,avatar', 'userTwo:id,name,email,avatar'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($conversation) use ($userId) {
                    // Get the other participant
                    $otherUser = $conversation->getOtherParticipant($userId);

                    // Skip conversations where the other user has been deleted
                    if (!$otherUser) {
                        return null;
                    }

                    // Get last message
                    $lastMessage = $conversation->messages()->latest()->first();
                    
                    return [
                        'id' => $conversation->id,
                        'other_user' => [
                            'id' => $otherUser->id,
                            'name' => $otherUser->name,
                            'email' => $otherUser->email,
                            'avatar' => $otherUser->avatar,
                        ],
                        'algorithm' => $conversation->algorithm,
                        'last_message' => $lastMessage ? [
                            'content' => '🔒 Encrypted message', // Don't show encrypted content in list
                            'created_at' => $lastMessage->created_at->toISOString(),
                        ] : null,
                        'unread_count' => $conversation->getUnreadCount($userId),
                        'updated_at' => $conversation->updated_at->toISOString(),
                    ];
                })
                ->filter() // Remove null entries (deleted participants)
                ->values(); // Re-index the array

            return response()->json([
                'success' => true,
                'message' => 'Conversations retrieved successfully',
                'data' => $conversations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get or create a conversation with a specific user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrCreateConversation(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'algorithm' => 'required|in:rc4,a5/1,RC4,A5/1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = auth()->id();
        $userEmail = auth()->user()->email;

        // Check if trying to chat with self
        if ($userEmail === $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot create conversation with yourself'
            ], 422);
        }

        // Find the other user by email
        $otherUser = User::where('email', $request->email)->first();

        if (!$otherUser) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist',
                'errors' => ['email' => ['No user found with this email address']]
            ], 404);
        }

        $otherUserId = $otherUser->id;

        try {
            // Find existing conversation (either direction)
            $conversation = Conversation::where(function ($query) use ($userId, $otherUserId) {
                $query->where('user_one_id', $userId)
                      ->where('user_two_id', $otherUserId);
            })->orWhere(function ($query) use ($userId, $otherUserId) {
                $query->where('user_one_id', $otherUserId)
                      ->where('user_two_id', $userId);
            })->first();

            // Return error if conversation already exists
            if ($conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'A conversation with this user already exists',
                    'errors' => ['email' => ['You already have an active conversation with this user']]
                ], 409);
            }

            // CLIENT-SIDE ENCRYPTION: No server-side key generation!
            $conversation = Conversation::create([
                'user_one_id' => $userId,
                'user_two_id' => $otherUserId,
                'algorithm' => strtoupper($request->algorithm),
                // NO encryption_key - client handles all encryption!
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation created successfully',
                'data' => [
                    'id' => $conversation->id,
                    'algorithm' => $conversation->algorithm,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'email' => $otherUser->email,
                        'avatar' => $otherUser->avatar,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get or create conversation',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get messages for a specific conversation
     * CLIENT-SIDE ENCRYPTION: Returns encrypted messages as-is!
     * 
     * @param int $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages($conversationId)
    {
        try {
            $userId = auth()->id();

            // Find the conversation and verify user is a participant
            $conversation = Conversation::where('id', $conversationId)
                ->where(function ($query) use ($userId) {
                    $query->where('user_one_id', $userId)
                          ->orWhere('user_two_id', $userId);
                })
                ->firstOrFail();

            // CLIENT-SIDE: Return encrypted content without decryption!
            $messages = $conversation->messages()
                ->with(['sender:id,name,avatar'])
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'sender_id' => $message->sender_id,
                        'sender_name' => $message->sender->name,
                        'sender_avatar' => $message->sender->avatar,
                        'encrypted_content' => $message->encrypted_content, // Return encrypted!
                        'nonce' => $message->nonce, // For client-side decryption
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at->toISOString(),
                    ];
                });

            // Mark messages as read for the current user
            $conversation->messages()
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Messages retrieved successfully',
                'data' => $messages
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Send a new message in a conversation
     * CLIENT-SIDE ENCRYPTION: Accepts pre-encrypted content + nonce!
     * 
     * @param Request $request
     * @param int $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request, $conversationId)
    {
        // Validate the request - encrypted content + nonce from client
        $validator = Validator::make($request->all(), [
            'encrypted_content' => 'required|string',
            'nonce' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->id();

            // Find the conversation and verify user is a participant
            $conversation = Conversation::where('id', $conversationId)
                ->where(function ($query) use ($userId) {
                    $query->where('user_one_id', $userId)
                          ->orWhere('user_two_id', $userId);
                })
                ->firstOrFail();

            // Determine the receiver
            $receiverId = $conversation->user_one_id == $userId 
                ? $conversation->user_two_id 
                : $conversation->user_one_id;

            // CLIENT-SIDE ENCRYPTION: Store encrypted content as-is!
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $userId,
                'receiver_id' => $receiverId,
                'encrypted_content' => $request->encrypted_content, // Already encrypted
                'nonce' => $request->nonce, // Unique nonce from client
                'is_read' => false,
            ]);

            // Update conversation timestamp
            $conversation->touch();
            $conversation->update(['last_message_at' => now()]);

            // Load sender information for response
            $message->load('sender:id,name,avatar');

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->name,
                    'sender_avatar' => $message->sender->avatar,
                    'encrypted_content' => $message->encrypted_content,
                    'nonce' => $message->nonce,
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->toISOString(),
                ]
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Mark messages as read in a conversation
     * 
     * @param int $conversationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($conversationId)
    {
        try {
            $userId = auth()->id();

            // Find the conversation and verify user is a participant
            $conversation = Conversation::where('id', $conversationId)
                ->where(function ($query) use ($userId) {
                    $query->where('user_one_id', $userId)
                          ->orWhere('user_two_id', $userId);
                })
                ->firstOrFail();

            // Mark all unread messages for this user as read
            $updatedCount = $conversation->messages()
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
                'data' => [
                    'marked_count' => $updatedCount
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get total unread message count for the authenticated user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnreadCount()
    {
        try {
            $userId = auth()->id();

            // Count all unread messages where user is the receiver
            $unreadCount = Message::where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Unread count retrieved successfully',
                'data' => [
                    'unread_count' => $unreadCount
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve unread count',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Download a file that was shared in a chat conversation.
     * Authorization: requester must share a conversation with the file owner.
     * The file is returned as-is (still encrypted); client decrypts with the
     * key embedded in the chat message.
     *
     * @param int $fileId
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function downloadSharedFile($fileId)
    {
        try {
            $userId = auth()->id();

            // Find the file (may belong to another user)
            $file = EncryptedFile::findOrFail($fileId);

            // If the requester owns the file, allow directly
            if ($file->user_id !== $userId) {
                // Otherwise verify they share a conversation with the file owner
                $sharedConversation = Conversation::where(function ($q) use ($userId, $file) {
                    $q->where('user_one_id', $userId)->where('user_two_id', $file->user_id);
                })->orWhere(function ($q) use ($userId, $file) {
                    $q->where('user_one_id', $file->user_id)->where('user_two_id', $userId);
                })->exists();

                if (!$sharedConversation) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied: no shared conversation with file owner'
                    ], 403);
                }
            }

            $encryptedPath = 'encrypted_files/' . $file->encrypted_filename;
            if (!Storage::exists($encryptedPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage'
                ], 404);
            }

            return response()->streamDownload(function () use ($encryptedPath) {
                echo Storage::get($encryptedPath);
            }, $file->encrypted_filename, [
                'Content-Type' => 'application/octet-stream',
                'X-Original-Filename' => $file->original_filename,
                'X-Algorithm' => $file->algorithm,
                'X-Original-Size' => $file->file_size,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File download failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Batch-check which of the given file IDs still exist and are accessible.
     * Returns the subset of IDs that are gone (deleted from DB or storage).
     */
    public function checkFilesExist(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return response()->json(['deleted' => []]);
        }

        // Clamp to a reasonable limit to prevent abuse
        $ids = array_slice(array_map('intval', $ids), 0, 100);

        $userId = auth()->id();
        $existing = EncryptedFile::whereIn('id', $ids)->pluck('id')->toArray();
        $deleted = array_values(array_diff($ids, $existing));

        return response()->json(['deleted' => $deleted]);
    }
}
