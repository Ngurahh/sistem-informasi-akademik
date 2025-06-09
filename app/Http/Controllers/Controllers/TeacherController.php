<?php
// app/Http/Controllers/TeacherController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ClassRoom;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class TeacherController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin')->except(['show']);
    }

    public function index()
    {
        $teachers = User::role('teacher')
                       ->with(['teacherClasses'])
                       ->when(request('search'), function($query, $search) {
                           return $query->where('name', 'like', "%{$search}%")
                                       ->orWhere('email', 'like', "%{$search}%");
                       })
                       ->active()
                       ->paginate(20);

        return view('teachers.index', compact('teachers'));
    }

    public function create()
    {
        $subjects = Subject::active()->get();
        $classes = ClassRoom::active()->get();
        
        return view('teachers.create', compact('subjects', 'classes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gender' => 'required|in:L,P',
            'birth_date' => 'required|date',
            'password' => 'required|string|min:8|confirmed',
        ]);

        DB::transaction(function () use ($request) {
            // Create User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
            ]);

            // Assign teacher role
            $user->assignRole('teacher');
        });

        return redirect()->route('teachers.index')
                        ->with('success', 'Data guru berhasil ditambahkan!');
    }

    public function show(User $teacher)
    {
        // Pastikan user adalah teacher
        if (!$teacher->hasRole('teacher')) {
            abort(404, 'Data guru tidak ditemukan');
        }

        // Load relasi yang diperlukan
        $teacher->load([
            'teacherClasses.students', 
            'grades.student.user', 
            'grades.subject'
        ]);

        // Statistik guru
        $stats = [
            'total_classes' => $teacher->teacherClasses()->count(),
            'total_students' => $teacher->teacherClasses()
                                      ->withCount('students')
                                      ->get()
                                      ->sum('students_count'),
            'total_grades_given' => $teacher->grades()->count(),
            'average_grade' => $teacher->grades()
                                     ->whereNotNull('final_grade')
                                     ->avg('final_grade')
        ];

        return view('teachers.show', compact('teacher', 'stats'));
    }

    public function edit(User $teacher)
    {
        // Pastikan user adalah teacher
        if (!$teacher->hasRole('teacher')) {
            abort(404, 'Data guru tidak ditemukan');
        }

        $subjects = Subject::active()->get();
        $classes = ClassRoom::active()->get();
        
        return view('teachers.edit', compact('teacher', 'subjects', 'classes'));
    }

    public function update(Request $request, User $teacher)
    {
        // Pastikan user adalah teacher
        if (!$teacher->hasRole('teacher')) {
            abort(404, 'Data guru tidak ditemukan');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 
                       Rule::unique('users')->ignore($teacher->id)],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'gender' => 'required|in:L,P',
            'birth_date' => 'required|date',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        DB::transaction(function () use ($request, $teacher) {
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
            ];

            // Update password jika diisi
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $teacher->update($updateData);
        });

        return redirect()->route('teachers.index')
                        ->with('success', 'Data guru berhasil diperbarui!');
    }

    public function destroy(User $teacher)
    {
        // Pastikan user adalah teacher
        if (!$teacher->hasRole('teacher')) {
            abort(404, 'Data guru tidak ditemukan');
        }

        // Cek apakah guru masih mengajar kelas aktif
        if ($teacher->teacherClasses()->where('is_active', true)->exists()) {
            return redirect()->route('teachers.index')
                           ->with('error', 'Tidak dapat menghapus guru yang masih aktif mengajar!');
        }

        DB::transaction(function () use ($teacher) {
            // Set teacher_id ke null pada kelas yang diajar
            $teacher->teacherClasses()->update(['teacher_id' => null]);
            
            // Hapus user
            $teacher->delete();
        });

        return redirect()->route('teachers.index')
                        ->with('success', 'Data guru berhasil dihapus!');
    }

    public function assignClass(Request $request, User $teacher)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id'
        ]);

        $class = ClassRoom::findOrFail($request->class_id);
        
        // Cek apakah kelas sudah punya wali kelas
        if ($class->teacher_id && $class->teacher_id != $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas sudah memiliki wali kelas!'
            ]);
        }

        $class->update(['teacher_id' => $teacher->id]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menugaskan guru sebagai wali kelas!'
        ]);
    }

    public function removeFromClass(User $teacher, ClassRoom $class)
    {
        if ($class->teacher_id == $teacher->id) {
            $class->update(['teacher_id' => null]);
            
            return response()->json([
                'success' => true,
                'message' => 'Guru berhasil dilepas dari kelas!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Guru tidak terdaftar sebagai wali kelas ini!'
        ]);
    }
}