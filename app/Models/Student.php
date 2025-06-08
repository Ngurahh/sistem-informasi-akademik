<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'student_id', 'nisn', 'class_id',
        'parent_name', 'parent_phone', 'parent_email', 
        'parent_address', 'entry_date', 'status'
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
        return $query->whereHas('classRoom', function($q) use ($grade) {
            $q->where('grade', $grade);
        });
    }
}