<?php
// database/migrations/2024_01_01_000002_create_classes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 1A, 1B, 2A, etc.
            $table->integer('grade'); // 1-6
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('max_students')->default(30);
            $table->string('academic_year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('classes');
    }
};