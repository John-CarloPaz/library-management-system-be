<?php

namespace Database\Seeders;

use App\Models\Semester;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example academic year with two semesters
        Semester::query()->updateOrCreate(
            ['name' => '1st Semester 2025–2026'],
            [
                'start_date' => '2025-08-01',
                'end_date' => '2025-12-15',
            ]
        );

        Semester::query()->updateOrCreate(
            ['name' => '2nd Semester 2025–2026'],
            [
                'start_date' => '2026-01-10',
                'end_date' => '2026-05-30',
            ]
        );
    }
}
