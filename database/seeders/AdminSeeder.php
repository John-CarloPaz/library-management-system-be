<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'employee_id' => 'SA001',
            'email' => 'superadmin@example.com',
            'username' => 'superadmin',
            'password' => Hash::make('password123'),
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'role' => 'super_admin',
            'branch_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
