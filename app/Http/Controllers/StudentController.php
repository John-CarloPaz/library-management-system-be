<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Student;
use App\Services\ListQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StudentController extends Controller
{
    public function __construct(private ListQueryService $lists)
    {
    }
    /**
     * Create a new student with QR code generation.
     */
    public function createStudent(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'email' => 'required|email|unique:students,email',
            'student_id' => 'required|integer|unique:students,student_id',
            'program' => 'required|string|max:255',
            'year_level' => 'required|integer|min:1|max:5',
            'status' => 'required|in:active,inactive,suspended',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        // Generate QR code
        $qrCodePath = $this->generateStudentQrCode($validated['student_id']);

        $student = Student::create(array_merge($validated, [
            'qr_code' => $qrCodePath,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
        ]));

        GenericActionEvent::dispatch([
            'resource_type' => 'student',
            'action' => 'create',
            'resource_id' => $student->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json(['message' => 'Student created successfully', 'student' => $student], 201);
    }

    /**
     * Generate a QR code for a student.
     */
    private function generateStudentQrCode(string $studentNumber): string
    {
        $qrCodeImage = QrCode::format('png')->size(300)->generate($studentNumber);
        $qrCodePath = "students_qr/{$studentNumber}.png";
        Storage::disk('public')->put($qrCodePath, $qrCodeImage);

        return $qrCodePath;
    }

    /**
     * Retrieve a student by student number.
     */
    public function getStudentByStudentNumber($studentNumber)
    {
        $student = Student::where('student_id', $studentNumber)
            ->with('semester')
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        return response()->json($student);
    }

    public function listStudents(Request $request)
    {
        // By default, only show non-archived students unless explicitly overridden
        $baseQuery = Student::with('semester');
        
        if (!$request->has('archived') && !$request->has('active')) {
            $baseQuery->where('is_archived', false);
        }

        $students = $this->lists->build(
            $request,
            $baseQuery,
            [
                'status_field' => 'status',
                'archived_field' => 'is_archived',
                'search_fields' => ['first_name', 'last_name', 'student_id', 'email'],
            ]
        );

        return response()->json($students);
    }

    /**
     * Update a student record.
     */
    public function updateStudent(Request $request, $studentNumber)
    {
        $student = Student::where('student_id', $studentNumber)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'email' => 'nullable|email|unique:students,email,' . $student->id,
            'student_id' => 'nullable|integer|unique:students,student_id,' . $student->id,
            'program' => 'nullable|string|max:255',
            'year_level' => 'nullable|integer|min:1|max:5',
            'status' => 'nullable|in:active,inactive,suspended',
            'semester_id' => 'sometimes|required|exists:semesters,id',
        ]);

        $student->update(array_merge($validated, [
            'updated_by' => auth()->user()->id,
        ]));

        GenericActionEvent::dispatch([
            'resource_type' => 'student',
            'action' => 'update',
            'resource_id' => $student->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json(['message' => 'Student updated successfully', 'student' => $student]);
    }

    /**
     * Archive a student record.
     */
    public function archiveStudent($studentNumber)
    {
        $student = Student::where('student_id', $studentNumber)->first();
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        // Business rule: cannot archive an active student (must be inactive or suspended first)
        if ($student->status === 'active') {
            return response()->json([
                'message' => 'Cannot archive an active student. Please set the status to inactive or suspended first.',
            ], 400);
        }

        // Business rule: student cannot be archived when there are active borrows
        if ($student->borrows()->where('status', 'borrowed')->exists()) {
            return response()->json([
                'message' => 'Cannot archive student with active borrows',
            ], 400);
        }

        $student->update([
            'is_archived' => true,
            'updated_by' => auth()->user()->id,
        ]);

        GenericActionEvent::dispatch([
            'resource_type' => 'student',
            'action' => 'archive',
            'resource_id' => $student->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json(['message' => 'Student archived successfully']);
    }

    /**
     * Restore an archived student record.
     */
    public function restoreStudent($studentNumber)
    {
        $student = Student::where('student_id', $studentNumber)->where('is_archived', true)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or not archived'], 404);
        }

        $student->update([
            'is_archived' => false,
            'updated_by' => auth()->user()->id,
        ]);

        GenericActionEvent::dispatch([
            'resource_type' => 'student',
            'action' => 'restore',
            'resource_id' => $student->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json(['message' => 'Student restored successfully']);
    }
}
