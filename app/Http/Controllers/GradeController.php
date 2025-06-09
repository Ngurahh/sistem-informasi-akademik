<?php
// app/Http/Controllers/GradeController.php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        
        // Check if user has role using the correct method
        if ($user->hasRole('admin')) {
            $grades = Grade::with(['student.user', 'subject', 'teacher'])
                          ->latest()
                          ->paginate(20);
        } elseif ($user->hasRole('teacher')) {
            $grades = Grade::where('teacher_id', $user->id)
                          ->with(['student.user', 'subject'])
                          ->latest()
                          ->paginate(20);
        } else {
            // For students, show only their grades
            $student = $user->student;
            if ($student) {
                $grades = Grade::where('student_id', $student->id)
                              ->with(['subject', 'teacher'])
                              ->latest()
                              ->paginate(20);
            } else {
                $grades = collect();
            }
        }

        return view('grades.index', compact('grades'));
    }

    public function create()
    {
        $this->authorize('create grades');
        
        $students = Student::with('user', 'classRoom')->active()->get();
        $subjects = Subject::active()->get();
        
        return view('grades.create', compact('students', 'subjects'));
    }

    public function store(Request $request)
    {
        $this->authorize('create grades');
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'semester' => 'required|in:1,2',
            'daily_score' => 'nullable|numeric|min:0|max:100',
            'midterm_score' => 'nullable|numeric|min:0|max:100',
            'final_score' => 'nullable|numeric|min:0|max:100',
            'academic_year' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        // Calculate final grade
        $dailyScore = $request->daily_score ?? 0;
        $midtermScore = $request->midterm_score ?? 0;
        $finalScore = $request->final_score ?? 0;
        
        $finalGrade = ($dailyScore * 0.3) + ($midtermScore * 0.3) + ($finalScore * 0.4);
        
        // Determine grade letter
        $gradeLetter = 'D';
        if ($finalGrade >= 85) $gradeLetter = 'A';
        elseif ($finalGrade >= 75) $gradeLetter = 'B';
        elseif ($finalGrade >= 65) $gradeLetter = 'C';

        Grade::create([
            'student_id' => $request->student_id,
            'subject_id' => $request->subject_id,
            'teacher_id' => Auth::id(),
            'semester' => $request->semester,
            'daily_score' => $request->daily_score,
            'midterm_score' => $request->midterm_score,
            'final_score' => $request->final_score,
            'final_grade' => $finalGrade,
            'grade_letter' => $gradeLetter,
            'academic_year' => $request->academic_year,
            'notes' => $request->notes,
        ]);

        return redirect()->route('grades.index')
                        ->with('success', 'Nilai berhasil ditambahkan!');
    }
}