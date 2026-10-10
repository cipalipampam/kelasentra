<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penugasan guru: satu baris = satu guru mengampu satu mapel di satu rombel.
        // Tahun ajaran tidak disimpan ulang karena sudah dimiliki rombel.
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['classroom_id', 'subject_id', 'teacher_id'], 'teaching_assignments_triple_unique');
            $table->index('teacher_id');
        });

        // Tepat satu penugasan aktif (belum dihapus) per rombel + mapel.
        // Guru pengganti dibuat sebagai penugasan baru; yang lama di-soft-delete.
        DB::statement(
            'CREATE UNIQUE INDEX teaching_assignments_one_active ON teaching_assignments (classroom_id, subject_id, (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
