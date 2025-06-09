<?php
// database/migrations/2024_01_01_000003_create_students_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('student_id')->unique(); // NIS
            $table->string('nisn')->unique()->nullable();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->string('parent_name');
            $table->string('parent_phone');
            $table->string('parent_email')->nullable();
            $table->text('parent_address');
            $table->date('entry_date');
            $table->enum('status', ['active', 'inactive', 'graduated'])->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        // Drop foreign key constraints first
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['class_id']);
        });
        
        Schema::dropIfExists('students');
    }
};