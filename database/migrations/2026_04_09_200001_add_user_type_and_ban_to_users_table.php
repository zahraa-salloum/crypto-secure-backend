<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add user_type_id and is_banned columns to users table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Foreign key to user_types; default 2 = 'user'
            $table->foreignId('user_type_id')
                  ->default(2)
                  ->after('avatar')
                  ->constrained('user_types')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Whether the account / email is permanently banned
            $table->boolean('is_banned')->default(false)->after('user_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_type_id']);
            $table->dropColumn(['user_type_id', 'is_banned']);
        });
    }
};
