<?php
// database/migrations/2024_01_01_000006_create_attendances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'sick', 'permission']);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Unique constraint untuk mencegah duplikasi absensi
            $table->unique(['student_id', 'subject_id', 'date']);
        });
    }

    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['teacher_id']);
        });
        
        Schema::dropIfExists('attendances');
    }
};