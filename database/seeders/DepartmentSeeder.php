<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('departments')->insert([
            'name' => 'College of Computing and Information Sciences',
            'short_name' => 'CCIS'
        ]);
        DB::table('departments')->insert([
            'name' => 'College of Engineering',
            'short_name' => 'COE'
        ]);
        DB::table('departments')->insert([
            'name' => 'College of Arts Social Sciences and Education',
            'short_name' => 'CASSED'
        ]);
        DB::table('departments')->insert([
            'name' => 'College of Nursing',
            'short_name' => 'CON'
        ]);
        DB::table('departments')->insert([
            'name' => 'College of Business',
            'short_name' => 'COB'
        ]);
        DB::table('departments')->insert([
            'name' => 'College of Criminology',
            'short_name' => 'COC'
        ]);
        DB::table('departments')->insert([
            'name' => 'Basic Education Department',
            'short_name' => 'BSED'
        ]);
    }
}
