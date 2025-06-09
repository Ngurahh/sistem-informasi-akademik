<?php
// database/migrations/2024_01_01_000005_create_grades_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->enum('semester', ['1', '2']);
            $table->decimal('daily_score', 5, 2)->nullable(); // Nilai harian
            $table->decimal('midterm_score', 5, 2)->nullable(); // UTS
            $table->decimal('final_score', 5, 2)->nullable(); // UAS
            $table->decimal('final_grade', 5, 2)->nullable(); // Nilai akhir
            $table->string('grade_letter', 2)->nullable(); // A, B, C, D
            $table->text('notes')->nullable();
            $table->string('academic_year');
            $table->timestamps();
        });
    }

    public function down()
    {
        // Drop foreign key constraints first
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['teacher_id']);
        });
        
        Schema::dropIfExists('grades');
    }
};