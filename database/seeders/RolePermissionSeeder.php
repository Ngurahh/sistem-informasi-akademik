<?php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage-users',
            'manage-students',
            'manage-teachers',
            'manage-classes',
            'manage-subjects',
            'manage-grades',
            'manage-attendance',
            'view-reports',
            'manage-announcements',
            'view-own-grades',
            'view-own-attendance',
            'view-child-grades',
            'view-child-attendance',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $teacherRole = Role::create(['name' => 'teacher']);
        $teacherRole->givePermissionTo([
            'manage-grades',
            'manage-attendance', 
            'view-reports',
            'manage-announcements'
        ]);

        $studentRole = Role::create(['name' => 'student']);
        $studentRole->givePermissionTo([
            'view-own-grades',
            'view-own-attendance'
        ]);

        $parentRole = Role::create(['name' => 'parent']);
        $parentRole->givePermissionTo([
            'view-child-grades',
            'view-child-attendance'
        ]);

        // Create default admin user
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@sekolah.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        // Create sample teacher
        $teacher = User::create([
            'name' => 'Guru Contoh',
            'email' => 'guru@sekolah.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');
    }
}