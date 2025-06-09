<?php
// app/Models/Subject.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code', 
        'description',
        'grade_level',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->code} - {$this->name}";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByGrade($query, $grade)
    {
        return $query->where('grade_level', $grade);
    }

    public function scopeForClass($query, $gradeLevel)
    {
        return $query->where('grade_level', $gradeLevel)->active();
    }

    // Methods
    public function getAverageGrade()
    {
        return $this->grades()
                   ->whereNotNull('final_grade')
                   ->avg('final_grade');
    }

    public function getTotalStudents()
    {
        return $this->grades()
                   ->distinct('student_id')
                   ->count('student_id');
    }
} 