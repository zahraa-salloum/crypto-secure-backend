<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * SECURITY UPDATE: Remove encryption_key column to enforce client-side encryption
     * The server should NEVER store encryption keys - this ensures zero-knowledge architecture
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Drop encryption_key column if it exists (security critical!)
            if (Schema::hasColumn('files', 'encryption_key')) {
                $table->dropColumn('encryption_key');
            }
            
            // Rename columns if they exist with old names
            if (Schema::hasColumn('files', 'filename') && !Schema::hasColumn('files', 'original_filename')) {
                $table->renameColumn('filename', 'original_filename');
            }
            
            if (Schema::hasColumn('files', 'size') && !Schema::hasColumn('files', 'file_size')) {
                $table->renameColumn('size', 'file_size');
            }
            
            // Drop path column if it exists (we use encrypted_filename instead)
            if (Schema::hasColumn('files', 'path')) {
                $table->dropColumn('path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // NOTE: We DO NOT restore encryption_key column for security reasons
            // If you need to rollback, you should migrate:fresh instead
            
            // Restore old column names if needed
            if (Schema::hasColumn('files', 'original_filename')) {
                $table->renameColumn('original_filename', 'filename');
            }
            
            if (Schema::hasColumn('files', 'file_size')) {
                $table->renameColumn('file_size', 'size');
            }
        });
    }
};
