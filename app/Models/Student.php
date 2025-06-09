<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'nisn',
        'class_id',
        'parent_name',
        'parent_phone',
        'parent_email',
        'parent_address',
        'entry_date',
        'status'
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->user->name;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByGrade($query, $grade)
    {
        return $query->whereHas('classRoom', function ($q) use ($grade) {
            $q->where('grade', $grade);
        });
    }

    public function getAttendancePercentage($month = null, $year = null)
    {
        $query = $this->attendances();

        if ($month && $year) {
            $query->whereMonth('date', $month)->whereYear('date', $year);
        } elseif ($year) {
            $query->whereYear('date', $year);
        }

        $total = $query->count();
        $present = $query->where('status', 'present')->count();

        return $total > 0 ? round(($present / $total) * 100, 2) : 0;
    }

    public function getAverageGrade($semester = null, $academicYear = null)
    {
        $query = $this->grades()->whereNotNull('final_grade');

        if ($semester) {
            $query->where('semester', $semester);
        }

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return round($query->avg('final_grade'), 2);
    }

    public function getTotalAbsences($month = null, $year = null)
    {
        $query = $this->attendances()->whereIn('status', ['absent', 'sick', 'permit']);

        if ($month && $year) {
            $query->whereMonth('date', $month)->whereYear('date', $year);
        } elseif ($year) {
            $query->whereYear('date', $year);
        }

        return $query->count();
    }
}
