<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Semester;
use Illuminate\Console\Command;

class DeactivateStudentsForEndedSemesters extends Command
{
    protected $signature = 'students:deactivate-ended-semesters';

    protected $description = 'Set students to inactive when their semester has ended';

    public function handle(): int
    {
        $today = now()->toDateString();

        $endedSemesterIds = Semester::where('end_date', '<', $today)->pluck('id');

        if ($endedSemesterIds->isEmpty()) {
            $this->info('No ended semesters found.');
            return self::SUCCESS;
        }

        $affected = Student::whereIn('semester_id', $endedSemesterIds)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $this->info("Updated {$affected} students to inactive.");

        return self::SUCCESS;
    }
}
