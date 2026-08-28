<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('username', 'csc')
            ->update([
                'username' => 'kdu',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('username', 'kdu')
            ->update([
                'username' => 'csc',
                'updated_at' => now(),
            ]);
    }
};
