<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\User;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->hasRole('admin')) {
            return $this->adminDashboard();
        } elseif ($user->hasRole('teacher')) {
            return $this->teacherDashboard();
        } elseif ($user->hasRole('student')) {
            return $this->studentDashboard();
        } elseif ($user->hasRole('parent')) {
            return $this->parentDashboard();
        }

        return redirect('/login');
    }

    private function adminDashboard()
    {
        $data = [
            'total_students' => Student::active()->count(),
            'total_teachers' => User::role('teacher')->active()->count(),
            'total_classes' => ClassRoom::active()->count(),
            'total_subjects' => Subject::active()->count(),
            'recent_students' => Student::with(['user', 'classRoom'])
                                      ->latest()
                                      ->limit(5)
                                      ->get(),
            'class_statistics' => ClassRoom::withCount(['students' => function($query) {
                                        $query->active();
                                    }])
                                    ->active()
                                    ->get()
        ];

        return view('dashboard.admin', compact('data'));
    }

    private function teacherDashboard()
    {
        $teacher = Auth::user();
        
        $data = [
            'my_classes' => $teacher->teacherClasses()->with('students')->get(),
            'total_students' => Student::whereHas('classRoom', function($query) use ($teacher) {
                                    $query->where('teacher_id', $teacher->id);
                                })->count(),
            'recent_grades' => Grade::where('teacher_id', $teacher->id)
                                   ->with(['student.user', 'subject'])
                                   ->latest()
                                   ->limit(10)
                                   ->get()
        ];

        return view('dashboard.teacher', compact('data'));
    }

    private function studentDashboard()
    {
        $student = Auth::user()->student;
        
        $data = [
            'student' => $student->load(['classRoom.teacher', 'user']),
            'recent_grades' => $student->grades()
                                      ->with('subject')
                                      ->latest()
                                      ->limit(10)
                                      ->get(),
            'average_grade' => $student->grades()
                                      ->whereNotNull('final_grade')
                                      ->avg('final_grade')
        ];

        return view('dashboard.student', compact('data'));
    }

    private function parentDashboard()
    {
        // Logic untuk dashboard orang tua
        return view('dashboard.parent');
    }
}