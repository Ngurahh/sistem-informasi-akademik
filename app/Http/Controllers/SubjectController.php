<?php
// app/Http/Controllers/SubjectController.php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::when(request('search'), function($query, $search) {
                           return $query->where('name', 'like', "%{$search}%")
                                       ->orWhere('code', 'like', "%{$search}%");
                       })
                       ->when(request('grade'), function($query, $grade) {
                           return $query->where('grade_level', $grade);
                       })
                       ->orderBy('grade_level')
                       ->orderBy('name')
                       ->paginate(15);

        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        return view('subjects.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:subjects',
            'description' => 'nullable|string',
            'grade_level' => 'required|integer|between:1,6',
        ]);

        Subject::create($request->all());

        return redirect()->route('subjects.index')
                        ->with('success', 'Mata pelajaran berhasil ditambahkan!');
    }

    public function show(Subject $subject)
    {
        $subject->load(['grades.student.user', 'schedules.classRoom']);
        
        return view('subjects.show', compact('subject'));
    }

    public function edit(Subject $subject)
    {
        return view('subjects.edit', compact('subject'));
    }

    public function update(Request $request, Subject $subject)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:10', 
                      Rule::unique('subjects')->ignore($subject->id)],
            'description' => 'nullable|string',
            'grade_level' => 'required|integer|between:1,6',
        ]);

        $subject->update($request->all());

        return redirect()->route('subjects.index')
                        ->with('success', 'Mata pelajaran berhasil diperbarui!');
    }

    public function destroy(Subject $subject)
    {
        // Check if subject has grades
        if ($subject->grades()->count() > 0) {
            return redirect()->route('subjects.index')
                           ->with('error', 'Mata pelajaran tidak dapat dihapus karena sudah memiliki data nilai!');
        }

        $subject->delete();

        return redirect()->route('subjects.index')
                        ->with('success', 'Mata pelajaran berhasil dihapus!');
    }

    public function getByGrade($grade)
    {
        $subjects = Subject::where('grade_level', $grade)
                          ->where('is_active', true)
                          ->get();

        return response()->json($subjects);
    }
}