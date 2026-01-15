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
            'public_ip' => '49.151.163.149',
            'public_ipv6' => '2001:4455:90b6:3100:69b6:a82f:e292:fde1',
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
            'public_ip' => '120.29.72.76',
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
            'public_ip' => '103.168.10.67',
            'created_by' => 'Seeder',
            'updated_by' => 'Seeder',
            'is_main_branch' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'name' => 'North Branch',
            'address' => '789 West St, Cityville',
            'details' => 'The western branch of the organization.',
            'public_ip' => '120.29.111.224',
            'created_by' => 'Seeder',
            'updated_by' => 'Seeder',
            'is_main_branch' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
