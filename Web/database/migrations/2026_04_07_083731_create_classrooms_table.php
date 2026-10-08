<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Contoh: "X-MIPA 1", "XI-IPS 2", "XII-MIPA 1"
            $table->enum('level', ['10', '11', '12']);
            // Penjurusan baru dimulai setelah kelas X, sehingga kelas X boleh tanpa jurusan.
            $table->enum('major', ['MIPA', 'IPS', 'BAHASA'])->nullable();
            $table->string('section', 10); // 1, 2, 3
            $table->string('academic_year', 20); // 2026/2027
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['level', 'major', 'academic_year']);

            // Nama rombel unik per tahun ajaran.
            $table->unique(['name', 'academic_year'], 'classrooms_name_academic_year_unique');

            // Satu guru hanya boleh menjadi wali kelas pada satu rombel per tahun ajaran.
            $table->unique(['homeroom_teacher_id', 'academic_year'], 'classrooms_homeroom_academic_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};

