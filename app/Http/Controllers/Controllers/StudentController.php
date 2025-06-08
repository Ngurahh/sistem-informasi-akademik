<?php
// app/Http/Controllers/StudentController.php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index()
    {
        $students = Student::with(['user', 'classRoom'])
                          ->when(request('search'), function($query, $search) {
                              return $query->whereHas('user', function($q) use ($search) {
                                  $q->where('name', 'like', "%{$search}%");
                              })->orWhere('student_id', 'like', "%{$search}%");
                          })
                          ->when(request('class'), function($query, $class) {
                              return $query->where('class_id', $class);
                          })
                          ->paginate(20);

        $classes = ClassRoom::active()->get();

        return view('students.index', compact('students', 'classes'));
    }

    public function create()
    {
        $classes = ClassRoom::active()->get();
        return view('students.create', compact('classes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'student_id' => 'required|string|unique:students',
            'nisn' => 'nullable|string|unique:students',
            'class_id' => 'required|exists:classes,id',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gender' => 'required|in:L,P',
            'birth_date' => 'required|date',
            'parent_name' => 'required|string|max:255',
            'parent_phone' => 'required|string|max:20',
            'parent_email' => 'nullable|email',
            'parent_address' => 'required|string',
            'entry_date' => 'required|date'
        ]);

        DB::transaction(function () use ($request) {
            // Create User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make('password123'), // Default password
                'phone' => $request->phone,
                'address' => $request->address,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
            ]);

            // Assign role
            $user->assignRole('student');

            // Create Student
            Student::create([
                'user_id' => $user->id,
                'student_id' => $request->student_id,
                'nisn' => $request->nisn,
                'class_id' => $request->class_id,
                'parent_name' => $request->parent_name,
                'parent_phone' => $request->parent_phone,
                'parent_email' => $request->parent_email,
                'parent_address' => $request->parent_address,
                'entry_date' => $request->entry_date,
            ]);
        });

        return redirect()->route('students.index')
                        ->with('success', 'Data siswa berhasil ditambahkan!');
    }

    public function show(Student $student)
    {
        $student->load(['user', 'classRoom.teacher', 'grades.subject']);
        
        return view('students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $classes = ClassRoom::active()->get();
        $student->load('user');
        
        return view('students.edit', compact('student', 'classes'));
    }

    public function update(Request $request, Student $student)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 
                       Rule::unique('users')->ignore($student->user_id)],
            'student_id' => ['required', 'string', 
                            Rule::unique('students')->ignore($student->id)],
            'nisn' => ['nullable', 'string', 
                      Rule::unique('students')->ignore($student->id)],
            'class_id' => 'required|exists:classes,id',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gender' => 'required|in:L,P',
            'birth_date' => 'required|date',
            'parent_name' => 'required|string|max:255',
            'parent_phone' => 'required|string|max:20',
            'parent_email' => 'nullable|email',
            'parent_address' => 'required|string',
            'entry_date' => 'required|date'
        ]);

        DB::transaction(function () use ($request, $student) {
            // Update User
            $student->user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
            ]);

            // Update Student
            $student->update([
                'student_id' => $request->student_id,
                'nisn' => $request->nisn,
                'class_id' => $request->class_id,
                'parent_name' => $request->parent_name,
                'parent_phone' => $request->parent_phone,
                'parent_email' => $request->parent_email,
                'parent_address' => $request->parent_address,
                'entry_date' => $request->entry_date,
            ]);
        });

        return redirect()->route('students.index')
                        ->with('success', 'Data siswa berhasil diperbarui!');
    }

    public function destroy(Student $student)
    {
        DB::transaction(function () use ($student) {
            $student->user->delete(); // Will cascade delete student
        });

        return redirect()->route('students.index')
                        ->with('success', 'Data siswa berhasil dihapus!');
    }
}