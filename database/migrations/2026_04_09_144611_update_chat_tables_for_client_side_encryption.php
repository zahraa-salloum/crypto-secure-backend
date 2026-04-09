<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * UPDATE CHAT FOR CLIENT-SIDE ENCRYPTION:
     * 1. Add nonce field to messages (unique value for each message)
     * 2. Remove encryption_key from conversations (client-side keys only)
     */
    public function up(): void
    {
        // Add nonce to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->string('nonce', 64)->after('encrypted_content')->nullable();
            $table->index('nonce');
        });
        
        // Remove encryption_key from conversations (client-side encryption)
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'encryption_key')) {
                $table->dropColumn('encryption_key');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove nonce from messages
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['nonce']);
            $table->dropColumn('nonce');
        });
        
        // Restore encryption_key to conversations
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('encryption_key')->nullable();
        });
    }
};
