<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Services\ListQueryService;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function __construct(private ListQueryService $lists)
    {
    }

    public function index(Request $request)
    {
        $baseQuery = Semester::query();

        $semesters = $this->lists->build(
            $request,
            $baseQuery,
            [
                'archived_field' => 'is_archived',
                'search_fields' => ['name'],
            ]
        );

        return response()->json($semesters);
    }

    public function active(Request $request)
    {
        $baseQuery = Semester::query()->where('is_archived', false);

        $semesters = $this->lists->build(
            $request,
            $baseQuery,
            [
                'archived_field' => 'is_archived',
            ]
        );

        return response()->json($semesters);
    }

    public function archived(Request $request)
    {
        $baseQuery = Semester::query()->where('is_archived', true);

        $semesters = $this->lists->build(
            $request,
            $baseQuery,
            [
                'archived_field' => 'is_archived',
            ]
        );

        return response()->json($semesters);
    }

    public function show($id)
    {
        $semester = Semester::findOrFail($id);

        return response()->json($semester);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $semester = Semester::create($validated);

        return response()->json([
            'message' => 'Semester created successfully',
            'semester' => $semester,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $semester = Semester::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
        ]);

        $semester->update($validated);

        return response()->json([
            'message' => 'Semester updated successfully',
            'semester' => $semester,
        ]);
    }

    public function destroy($id)
    {
        $semester = Semester::findOrFail($id);
        $semester->update(['is_archived' => true]);

        return response()->json(['message' => 'Semester archived successfully']);
    }

    public function restore($id)
    {
        $semester = Semester::findOrFail($id);
        $semester->update(['is_archived' => false]);

        return response()->json(['message' => 'Semester restored successfully']);
    }
}
