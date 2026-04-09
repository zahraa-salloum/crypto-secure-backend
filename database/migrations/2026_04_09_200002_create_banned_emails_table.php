<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banned Emails Table Migration
 * Stores permanently banned email addresses so they cannot be re-registered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banned_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('banned_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_emails');
    }
};
