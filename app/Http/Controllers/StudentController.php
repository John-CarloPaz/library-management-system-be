<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Events\GenericActionEvent;

class StudentController extends Controller
{
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
        $student = Student::where('student_id', $studentNumber)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        return response()->json($student);
    }

    public function getAllStudents()
    {
        $students = Student::where('is_archived', false)->get();
        return response()->json($students);
    }

    public function getArchivedStudents()
    {
        $students = Student::where('is_archived', true)->get();
        return response()->json($students);
    }

    public function listStudentUnarchived()
    {
        $students = Student::where('is_archived', false)->get();
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
            'suffix' => 'nullable|string|max:50',
            'email' => 'nullable|email|unique:students,email,' . $student->id,
            'student_id' => 'nullable|integer|unique:students,student_id,' . $student->id,
            'program' => 'nullable|string|max:255',
            'year_level' => 'nullable|integer|min:1|max:5',
            'status' => 'nullable|in:active,inactive,suspended',
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

        if($student->with('borrows')->where('status', 'borrowed')->exists()) {
            return response()->json(['message' => 'Cannot archive student with active borrows'], 400);
        }

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
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
