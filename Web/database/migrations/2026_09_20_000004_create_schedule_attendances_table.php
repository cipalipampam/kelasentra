<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_attendances', function (Blueprint $table) {
            $table->id();
            // Riwayat absensi mapel tidak boleh hilang karena penghapusan entitas.
            $table->foreignId('schedule_id')->constrained('schedules')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            // Enrollment yang berlaku saat absensi dicatat, sumber validasi konteks.
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->restrictOnDelete();
            $table->date('attendance_date');
            $table->enum('status', ['present', 'late', 'sick', 'permission', 'absent']);
            $table->string('notes', 255)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Menjamin 1 siswa tidak bisa diabsen ganda pada jadwal & tanggal yang sama
            $table->unique(['schedule_id', 'student_id', 'attendance_date'], 'uq_schedule_student_date');
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_attendances');
    }
};

