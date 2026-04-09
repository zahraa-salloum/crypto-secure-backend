<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations and Messages Tables Migration
 * Stores encrypted chat conversations and messages
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_two_id')->constrained('users')->onDelete('cascade');
            $table->enum('algorithm', ['RC4', 'A5/1']); // Encryption algorithm
            $table->text('encryption_key')->nullable(); // Encrypted storage of shared key
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('user_one_id');
            $table->index('user_two_id');
            $table->index('last_message_at');
            
            // Ensure unique conversation between two users
            $table->unique(['user_one_id', 'user_two_id']);
        });
        
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->text('encrypted_content'); // Encrypted message content
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('conversation_id');
            $table->index('sender_id');
            $table->index('receiver_id');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
