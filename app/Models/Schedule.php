<?php
// app/Models/Schedule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'academic_year',
        'semester',
        'is_active'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];

    // Constants for days of week
    const DAYS = [
        1 => 'Senin',
        2 => 'Selasa', 
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];

    // Relationships
    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
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
    public function getDayNameAttribute()
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    public function getTimeRangeAttribute()
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }

    public function getDurationAttribute()
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByDay($query, $day)
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeBySemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    // Methods
    public function hasConflict($classId = null, $teacherId = null, $excludeId = null)
    {
        $query = self::where('day_of_week', $this->day_of_week)
                    ->where('academic_year', $this->academic_year)
                    ->where('semester', $this->semester)
                    ->where(function($q) {
                        $q->where(function($subQ) {
                            // Start time is between existing schedule
                            $subQ->where('start_time', '<=', $this->start_time)
                                 ->where('end_time', '>', $this->start_time);
                        })->orWhere(function($subQ) {
                            // End time is between existing schedule  
                            $subQ->where('start_time', '<', $this->end_time)
                                 ->where('end_time', '>=', $this->end_time);
                        })->orWhere(function($subQ) {
                            // New schedule encompasses existing schedule
                            $subQ->where('start_time', '>=', $this->start_time)
                                 ->where('end_time', '<=', $this->end_time);
                        });
                    });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($classId) {
            $query->where('class_id', $classId);
        }

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        return $query->exists();
    }

    public static function getDayOptions()
    {
        return self::DAYS;
    }
}