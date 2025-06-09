<?php
// app/Models/Attendance.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'class_id',
        'date',
        'status',
        'notes',
        'recorded_by'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Constants for attendance status
    const STATUS_PRESENT = 'present';
    const STATUS_ABSENT = 'absent';
    const STATUS_LATE = 'late';
    const STATUS_SICK = 'sick';
    const STATUS_PERMIT = 'permit';

    public static function getStatusOptions()
    {
        return [
            self::STATUS_PRESENT => 'Hadir',
            self::STATUS_ABSENT => 'Tidak Hadir',
            self::STATUS_LATE => 'Terlambat',
            self::STATUS_SICK => 'Sakit',
            self::STATUS_PERMIT => 'Izin'
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            self::STATUS_PRESENT => 'success',
            self::STATUS_ABSENT => 'danger',
            self::STATUS_LATE => 'warning',
            self::STATUS_SICK => 'info',
            self::STATUS_PERMIT => 'secondary'
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    // Scopes
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeByMonth($query, $month, $year = null)
    {
        $year = $year ?? date('Y');
        return $query->whereMonth('date', $month)
                    ->whereYear('date', $year);
    }

    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopePresent($query)
    {
        return $query->where('status', self::STATUS_PRESENT);
    }

    public function scopeAbsent($query)
    {
        return $query->whereIn('status', [self::STATUS_ABSENT, self::STATUS_SICK, self::STATUS_PERMIT]);
    }

    // Static methods
    public static function getAttendancePercentage($studentId, $startDate = null, $endDate = null)
    {
        $query = self::where('student_id', $studentId);
        
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $total = $query->count();
        $present = $query->where('status', self::STATUS_PRESENT)->count();

        return $total > 0 ? round(($present / $total) * 100, 2) : 0;
    }
}