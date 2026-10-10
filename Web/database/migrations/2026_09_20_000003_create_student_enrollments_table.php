<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat penempatan siswa: satu baris per (siswa, tahun ajaran).
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            // Riwayat penempatan dilindungi: siswa tidak dapat dihapus permanen.
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('classroom_id')->constrained('classrooms');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->foreignId('promotion_batch_id')->nullable()->constrained('promotion_batches')->nullOnDelete();
            $table->timestamps();

            // Satu siswa hanya boleh punya satu enrollment per tahun ajaran.
            $table->unique(['student_id', 'academic_year_id'], 'student_enrollments_year_unique');

            $table->index('classroom_id');
        });

        // Maksimal satu enrollment berjalan (ended_at null) per siswa.
        DB::statement(
            'CREATE UNIQUE INDEX student_enrollments_one_open ON student_enrollments ((CASE WHEN ended_at IS NULL THEN student_id ELSE NULL END))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
