<?php

namespace App\Http\Controllers;

use App\Models\EncryptedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * FileController
 * 
 * Handles encrypted file upload, download, and management.
 * Files are encrypted client-side before upload and decrypted client-side after download.
 */
class FileController extends Controller
{
    /**
     * Upload and encrypt a file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        // Validate the request
        // NOTE: File is already encrypted client-side, we just store it!
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // Max 10MB
            'algorithm' => 'required|in:RC4,A5/1',
            'original_filename' => 'required|string|max:255',
            'original_size' => 'integer', // Original unencrypted size
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Enforce per-user file limit
            $fileCount = EncryptedFile::where('user_id', auth()->id())->count();
            if ($fileCount >= 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'File limit reached. You can store up to 10 files. Please delete older files or upgrade your plan.',
                ], 403);
            }

            // CLIENT-SIDE ENCRYPTION: File is already encrypted by frontend!
            // We just store it as-is without touching it
            $file = $request->file('file');
            
            // Generate a unique encrypted filename
            $encryptedFilename = Str::random(40) . '.enc';
            
            // Store the encrypted file directly (NO server-side encryption!)
            $path = $file->storeAs('encrypted_files', $encryptedFilename);

            // Save file metadata to database (NO KEY STORED!)
            $encryptedFile = EncryptedFile::create([
                'user_id' => auth()->id(),
                'original_filename' => $request->original_filename,
                'encrypted_filename' => $encryptedFilename,
                'file_size' => $request->input('original_size', $file->getSize()),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'algorithm' => $request->algorithm,
                // NO encryption_key field - key never sent to server!
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded and encrypted successfully',
                'data' => [
                    'id' => $encryptedFile->id,
                    'original_filename' => $encryptedFile->original_filename,
                    'file_size' => $encryptedFile->file_size,
                    'mime_type' => $encryptedFile->mime_type,
                    'algorithm' => $encryptedFile->algorithm,
                    'uploaded_at' => $encryptedFile->created_at->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File upload failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Download encrypted file (client will decrypt)
     * 
     * @param int $id File ID
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function download($id)
    {
        try {
            // Find the file and verify ownership
            $file = EncryptedFile::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // Check if file exists in storage
            $encryptedPath = 'encrypted_files/' . $file->encrypted_filename;
            if (!Storage::exists($encryptedPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage'
                ], 404);
            }

            // Return encrypted file AS-IS
            // Client-side decryption will happen in the browser!
            return response()->streamDownload(function () use ($encryptedPath) {
                echo Storage::get($encryptedPath);
            }, $file->encrypted_filename, [
                'Content-Type' => 'application/octet-stream',
                // Send metadata in custom headers for frontend to use
                'X-Original-Filename' => $file->original_filename,
                'X-Algorithm' => $file->algorithm,
                'X-Original-Size' => $file->file_size,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found or access denied'
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
     * Get all files for the authenticated user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserFiles()
    {
        try {
            $files = EncryptedFile::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_filename' => $file->original_filename,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'algorithm' => $file->algorithm,
                        'is_shared' => $file->isShareValid(),
                        'share_expires_at' => $file->share_expires_at ? $file->share_expires_at->toISOString() : null,
                        'uploaded_at' => $file->created_at->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Files retrieved successfully',
                'data' => $files
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve files',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Delete a file
     * 
     * @param int $id File ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try {
            // Find the file and verify ownership
            $file = EncryptedFile::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // Delete the file from storage
            $encryptedPath = 'encrypted_files/' . $file->encrypted_filename;
            if (Storage::exists($encryptedPath)) {
                Storage::delete($encryptedPath);
            }

            // Delete the database record
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File deletion failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

}
