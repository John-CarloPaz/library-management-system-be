<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Borrow;
use Carbon\Carbon;

class BorrowSeeder extends Seeder
{
    public function run()
    {
        // Book 2: Not overdue
        Borrow::create([
            'student_id' => 1,
            'book_id' => 2,
            'borrow_date' => Carbon::now()->subDays(2),
            'due_date' => Carbon::now()->addDays(5),
            'status' => 'borrowed',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Book 3: Overdue
        Borrow::create([
            'student_id' => 1,
            'book_id' => 3,
            'borrow_date' => Carbon::now()->subDays(10),
            'due_date' => Carbon::now()->subDays(3),
            'status' => 'overdue',
            'is_penalized' => true,
            'penalty_amount' => 24, // 3 days overdue * 8
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Book 4: Overdue
        Borrow::create([
            'student_id' => 1,
            'book_id' => 4,
            'borrow_date' => Carbon::now()->subDays(15),
            'due_date' => Carbon::now()->subDays(5),
            'status' => 'overdue',
            'is_penalized' => true,
            'penalty_amount' => 40, // 5 days overdue * 8
            'created_by' => 1,
            'updated_by' => 1,
        ]);
    }
}
