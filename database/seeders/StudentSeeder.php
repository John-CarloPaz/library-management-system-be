<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSemesterId = Semester::query()->orderBy('start_date')->value('id');

        if (!$defaultSemesterId) {
            return;
        }

        // 40 active students
        for ($i = 1; $i <= 5; $i++) {
            Student::create([
                'first_name' => 'Active ' . $i,
                'last_name' => 'Student',
                'middle_name' => null,
                'suffix' => null,
                'email' => 'active' . $i . '@example.com',
                'student_id' => (string) (1000 + $i),
                'program' => 'BS Computer Science',
                'year_level' => 1,
                'status' => 'active',
                'semester_id' => $defaultSemesterId,
                'qr_code' => null,
                'expiration_date' => null,
                'is_archived' => false,
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]);
        }

        // 10 inactive students
        for ($i = 1; $i <= 5; $i++) {
            Student::create([
                'first_name' => 'Inactive ' . $i,
                'last_name' => 'Student',
                'middle_name' => null,
                'suffix' => null,
                'email' => 'inactive' . $i . '@example.com',
                'student_id' => (string) (2000 + $i),
                'program' => 'BS Information Technology',
                'year_level' => 2,
                'status' => 'inactive',
                'semester_id' => $defaultSemesterId,
                'qr_code' => null,
                'expiration_date' => null,
                'is_archived' => false,
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]);
        }
    }
}
