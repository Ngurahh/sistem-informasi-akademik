<?php
// app/Models/Grade.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'subject_id', 
        'teacher_id',
        'semester',
        'daily_score',
        'midterm_score',
        'final_score',
        'final_grade',
        'grade_letter',
        'notes',
        'academic_year'
    ];

    protected $casts = [
        'daily_score' => 'decimal:2',
        'midterm_score' => 'decimal:2', 
        'final_score' => 'decimal:2',
        'final_grade' => 'decimal:2',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    // Accessors
    public function getGradeLetterAttribute($value)
    {
        if ($value) return $value;
        
        return $this->calculateGradeLetter();
    }

    public function getFinalGradeAttribute($value)
    {
        if ($value) return $value;
        
        return $this->calculateFinalGrade();
    }

    // Methods
    public function calculateFinalGrade()
    {
        $daily = $this->daily_score ?? 0;
        $midterm = $this->midterm_score ?? 0;
        $final = $this->final_score ?? 0;

        // Formula: 30% daily + 35% midterm + 35% final
        return round(($daily * 0.3) + ($midterm * 0.35) + ($final * 0.35), 2);
    }

    public function calculateGradeLetter()
    {
        $finalGrade = $this->final_grade ?? $this->calculateFinalGrade();

        if ($finalGrade >= 90) return 'A';
        if ($finalGrade >= 80) return 'B';
        if ($finalGrade >= 70) return 'C';
        if ($finalGrade >= 60) return 'D';
        return 'E';
    }

    public function getGradeStatus()
    {
        $finalGrade = $this->final_grade ?? $this->calculateFinalGrade();
        return $finalGrade >= 70 ? 'Lulus' : 'Tidak Lulus';
    }

    // Scopes
    public function scopeBySemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeBySubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    // Boot method to auto-calculate grades
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($grade) {
            $grade->final_grade = $grade->calculateFinalGrade();
            $grade->grade_letter = $grade->calculateGradeLetter();
        });
    }
}