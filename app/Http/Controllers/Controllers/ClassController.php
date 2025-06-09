<?php
// app/Http/Controllers/ClassController.php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{
    public function index()
    {
        $classes = ClassRoom::with(['teacher', 'students'])
                           ->withCount(['students as active_students_count' => function($query) {
                               $query->where('status', 'active');
                           }])
                           ->when(request('search'), function($query, $search) {
                               return $query->where('name', 'like', "%{$search}%");
                           })
                           ->when(request('grade'), function($query, $grade) {
                               return $query->where('grade', $grade);
                           })
                           ->orderBy('grade')
                           ->orderBy('name')
                           ->paginate(12);

        return view('classes.index', compact('classes'));
    }

    public function create()
    {
        $teachers = User::role('teacher')->active()->get();
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        $academicYear = $currentYear . '/' . $nextYear;

        return view('classes.create', compact('teachers', 'academicYear'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:10',
            'grade' => 'required|integer|between:1,6',
            'teacher_id' => 'nullable|exists:users,id',
            'max_students' => 'required|integer|min:10|max:50',
            'academic_year' => 'required|string',
        ]);

        // Check if teacher is already assigned to another class in same grade
        if ($request->teacher_id) {
            $existingClass = ClassRoom::where('teacher_id', $request->teacher_id)
                                    ->where('grade', $request->grade)
                                    ->where('academic_year', $request->academic_year)
                                    ->where('is_active', true)
                                    ->exists();

            if ($existingClass) {
                return back()->withErrors([
                    'teacher_id' => 'Guru sudah menjadi wali kelas untuk tingkat yang sama di tahun ajaran ini.'
                ])->withInput();
            }
        }

        ClassRoom::create($request->all());

        return redirect()->route('classes.index')
                        ->with('success', 'Kelas berhasil ditambahkan!');
    }

    public function show(ClassRoom $class)
    {
        $class->load([
            'teacher', 
            'students.user',
            'schedules.subject'
        ]);

        $studentsCount = $class->students()->where('status', 'active')->count();
        $averageGrades = $class->students()
                             ->whereHas('grades', function($query) {
                                 $query->whereNotNull('final_grade');
                             })
                             ->with(['grades' => function($query) {
                                 $query->whereNotNull('final_grade');
                             }])
                             ->get()
                             ->map(function($student) {
                                 return [
                                     'student' => $student,
                                     'average' => $student->grades->avg('final_grade')
                                 ];
                             })
                             ->sortByDesc('average');

        return view('classes.show', compact('class', 'studentsCount', 'averageGrades'));
    }

    public function edit(ClassRoom $class)
    {
        $teachers = User::role('teacher')->active()->get();
        
        return view('classes.edit', compact('class', 'teachers'));
    }

    public function update(Request $request, ClassRoom $class)
    {
        $request->validate([
            'name' => 'required|string|max:10',
            'grade' => 'required|integer|between:1,6',
            'teacher_id' => 'nullable|exists:users,id',
            'max_students' => 'required|integer|min:10|max:50',
            'academic_year' => 'required|string',
        ]);

        // Check if teacher is already assigned (except current class)
        if ($request->teacher_id) {
            $existingClass = ClassRoom::where('teacher_id', $request->teacher_id)
                                    ->where('grade', $request->grade)
                                    ->where('academic_year', $request->academic_year)
                                    ->where('is_active', true)
                                    ->where('id', '!=', $class->id)
                                    ->exists();

            if ($existingClass) {
                return back()->withErrors([
                    'teacher_id' => 'Guru sudah menjadi wali kelas untuk tingkat yang sama di tahun ajaran ini.'
                ])->withInput();
            }
        }

        $class->update($request->all());

        return redirect()->route('classes.index')
                        ->with('success', 'Data kelas berhasil diperbarui!');
    }

    public function destroy(ClassRoom $class)
    {
        // Check if class has active students
        $activeStudents = $class->students()->where('status', 'active')->count();
        
        if ($activeStudents > 0) {
            return redirect()->route('classes.index')
                           ->with('error', 'Kelas tidak dapat dihapus karena masih memiliki siswa aktif!');
        }

        $class->delete();

        return redirect()->route('classes.index')
                        ->with('success', 'Kelas berhasil dihapus!');
    }

    public function moveStudents(ClassRoom $class)
    {
        $students = $class->students()->where('status', 'active')->with('user')->get();
        $targetClasses = ClassRoom::where('grade', $class->grade)
                                ->where('id', '!=', $class->id)
                                ->where('is_active', true)
                                ->get();

        return view('classes.move-students', compact('class', 'students', 'targetClasses'));
    }

    public function processMoveStudents(Request $request, ClassRoom $class)
    {
        $request->validate([
            'target_class_id' => 'required|exists:classes,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id'
        ]);

        $targetClass = ClassRoom::findOrFail($request->target_class_id);
        $currentCount = $targetClass->students()->where('status', 'active')->count();
        $movingCount = count($request->student_ids);

        if (($currentCount + $movingCount) > $targetClass->max_students) {
            return back()->with('error', 'Kelas tujuan tidak memiliki kapasitas yang cukup!');
        }

        Student::whereIn('id', $request->student_ids)->update([
            'class_id' => $request->target_class_id
        ]);

        return redirect()->route('classes.show', $class)
                        ->with('success', "Berhasil memindahkan {$movingCount} siswa ke {$targetClass->name}!");
    }
}