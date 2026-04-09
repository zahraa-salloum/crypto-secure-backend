<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Admin User Seeder Migration
 * Creates the default admin account: admin@yopmail.com / admin
 * Running this as a migration guarantees it runs in the correct order
 * and is idempotent (uses updateOrInsert).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@yopmail.com'],
            [
                'name'         => 'Admin',
                'email'        => 'admin@yopmail.com',
                'password'     => Hash::make('admin'),
                'user_type_id' => 1, // admin
                'is_banned'    => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'admin@yopmail.com')->delete();
    }
};
