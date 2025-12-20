<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('branches')->insert([
            'name' => 'AVJ Branch',
            'address' => '123 Main St, Cityville',
            'details' => 'The primary branch of the organization.',
            'public_ip' => '127.0.0.1',
            'is_main_branch' => true,
            'created_by' => 'Seeder',
            'updated_by' => 'Seeder',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'name' => 'East Branch',
            'address' => '456 East St, Cityville',
            'details' => 'The eastern branch of the organization.',
            'public_ip' => '120.29.77.240',
            'created_by' => 'Seeder',
            'updated_by' => 'Seeder',
            'is_main_branch' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'name' => 'West Branch',
            'address' => '789 West St, Cityville',
            'details' => 'The western branch of the organization.',
            'public_ip' => '120.29.77.240',
            'created_by' => 'Seeder',
            'updated_by' => 'Seeder',
            'is_main_branch' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
