<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files Table Migration
 * Stores encrypted file metadata and paths
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('original_filename'); // Original filename (before encryption)
            $table->string('encrypted_filename'); // Stored filename (after encryption)
            $table->unsignedBigInteger('file_size'); // File size in bytes
            $table->string('mime_type')->nullable();
            $table->enum('algorithm', ['RC4', 'A5/1']); // Encryption algorithm
            // NOTE: encryption_key is NOT stored - client-side encryption only!
            $table->string('share_token')->nullable()->unique(); // For file sharing
            $table->timestamp('share_expires_at')->nullable(); // Share link expiration
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('user_id');
            $table->index('share_token');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
