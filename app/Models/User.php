<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles; // ← PASTIKAN HasRoles ADA DI SINI

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'address', 
        'gender', 'birth_date', 'avatar', 'is_active'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'password' => 'hashed', // Laravel 10+
    ];

    // Relationships
    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function teacherClasses()
    {
        return $this->hasMany(ClassRoom::class, 'teacher_id');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class, 'teacher_id');
    }

    // Helper methods untuk role checking
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isTeacher()
    {
        return $this->hasRole('teacher');
    }

    public function isStudent()
    {
        return $this->hasRole('student');
    }

    public function isParent()
    {
        return $this->hasRole('parent');
    }
}