<?php
// app/Http/Controllers/GradeController.php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        
        // Check user role menggunakan method yang benar
        if ($user->hasRole('admin')) {
            $grades = Grade::with(['student.user', 'subject', 'teacher'])
                          ->when(request('search'), function($query, $search) {
                              return $query->whereHas('student.user', function($q) use ($search) {
                                  $q->where('name', 'like', "%{$search}%");
                              });
                          })
                          ->when(request('class'), function($query, $class) {
                              return $query->whereHas('student', function($q) use ($class) {
                                  $q->where('class_id', $class);
                              });
                          })
                          ->when(request('subject'), function($query, $subject) {
                              return $query->where('subject_id', $subject);
                          })
                          ->paginate(20);
        } 
        elseif ($user->hasRole('teacher')) {
            $grades = Grade::with(['student.user', 'subject'])
                          ->where('teacher_id', $user->id)
                          ->when(request('search'), function($query, $search) {
                              return $query->whereHas('student.user', function($q) use ($search) {
                                  $q->where('name', 'like', "%{$search}%");
                              });
                          })
                          ->paginate(20);
        }
        elseif ($user->hasRole('student')) {
            $grades = Grade::with(['subject', 'teacher'])
                          ->whereHas('student', function($query) use ($user) {
                              $query->where('user_id', $user->id);
                          })
                          ->paginate(20);
        }
        else {
            abort(403, 'Unauthorized action.');
        }

        $classes = ClassRoom::active()->with('teacher')->get();
        $subjects = Subject::active()->get();

        return view('grades.index', compact('grades', 'classes', 'subjects'));
    }

    public function create()
    {
        $user = Auth::user();
        
        // Hanya admin dan teacher yang bisa menambah nilai
        if (!$user->hasRole(['admin', 'teacher'])) {
            abort(403, 'Unauthorized action.');
        }

        if ($user->hasRole('admin')) {
            $students = Student::with(['user', 'classRoom'])->active()->get();
        } else {
            // Teacher hanya bisa input nilai untuk siswa di kelasnya
            $students = Student::with(['user', 'classRoom'])
                              ->whereHas('classRoom', function($query) use ($user) {
                                  $query->where('teacher_id', $user->id);
                              })
                              ->active()
                              ->get();
        }

        $subjects = Subject::active()->get();
        $classes = ClassRoom::active()->get();

        return view('grades.create', compact('students', 'subjects', 'classes'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'teacher'])) {
            abort(403, 'Unauthorized action.');
        }

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

        // Formula: 30% daily + 30% midterm + 40% final
        $finalGrade = ($dailyScore * 0.3) + ($midtermScore * 0.3) + ($finalScore * 0.4);
        
        // Determine grade letter
        $gradeLetter = $this->calculateGradeLetter($finalGrade);

        Grade::create([
            'student_id' => $request->student_id,
            'subject_id' => $request->subject_id,
            'teacher_id' => $user->id,
            'semester' => $request->semester,
            'daily_score' => $request->daily_score,
            'midterm_score' => $request->midterm_score,
            'final_score' => $request->final_score,
            'final_grade' => $finalGrade,
            'grade_letter' => $gradeLetter,
            'academic_year' => $request->academic_year,
            'notes' => $request->notes
        ]);

        return redirect()->route('grades.index')
                        ->with('success', 'Nilai berhasil ditambahkan!');
    }

    public function show(Grade $grade)
    {
        $grade->load(['student.user', 'subject', 'teacher']);
        
        $user = Auth::user();
        
        // Check authorization
        if ($user->hasRole('student')) {
            if ($grade->student->user_id !== $user->id) {
                abort(403, 'Unauthorized action.');
            }
        } elseif ($user->hasRole('teacher')) {
            if ($grade->teacher_id !== $user->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        return view('grades.show', compact('grade'));
    }

    public function edit(Grade $grade)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'teacher'])) {
            abort(403, 'Unauthorized action.');
        }

        // Teacher hanya bisa edit nilai yang dia input
        if ($user->hasRole('teacher') && $grade->teacher_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $grade->load(['student.user', 'subject']);
        $subjects = Subject::active()->get();

        return view('grades.edit', compact('grade', 'subjects'));
    }

    public function update(Request $request, Grade $grade)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'teacher'])) {
            abort(403, 'Unauthorized action.');
        }

        if ($user->hasRole('teacher') && $grade->teacher_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'daily_score' => 'nullable|numeric|min:0|max:100',
            'midterm_score' => 'nullable|numeric|min:0|max:100',
            'final_score' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string'
        ]);

        // Calculate final grade
        $dailyScore = $request->daily_score ?? 0;
        $midtermScore = $request->midterm_score ?? 0;
        $finalScore = $request->final_score ?? 0;

        $finalGrade = ($dailyScore * 0.3) + ($midtermScore * 0.3) + ($finalScore * 0.4);
        $gradeLetter = $this->calculateGradeLetter($finalGrade);

        $grade->update([
            'daily_score' => $request->daily_score,
            'midterm_score' => $request->midterm_score,
            'final_score' => $request->final_score,
            'final_grade' => $finalGrade,
            'grade_letter' => $gradeLetter,
            'notes' => $request->notes
        ]);

        return redirect()->route('grades.index')
                        ->with('success', 'Nilai berhasil diperbarui!');
    }

    public function destroy(Grade $grade)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'teacher'])) {
            abort(403, 'Unauthorized action.');
        }

        if ($user->hasRole('teacher') && $grade->teacher_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $grade->delete();

        return redirect()->route('grades.index')
                        ->with('success', 'Nilai berhasil dihapus!');
    }

    // Helper method untuk menghitung grade letter
    private function calculateGradeLetter($score)
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'E';
    }

    // Method untuk mendapatkan students berdasarkan kelas (AJAX)
    public function getStudentsByClass($classId)
    {
        $students = Student::with('user')
                          ->where('class_id', $classId)
                          ->active()
                          ->get()
                          ->map(function($student) {
                              return [
                                  'id' => $student->id,
                                  'name' => $student->user->name,
                                  'student_id' => $student->student_id
                              ];
                          });

        return response()->json($students);
    }
}