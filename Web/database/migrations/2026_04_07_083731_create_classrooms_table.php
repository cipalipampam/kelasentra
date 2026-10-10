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
            $table->foreignId('academic_year_id')->constrained('academic_years');
            // Index penopang FK dideklarasikan eksplisit sebelum FK-nya agar MySQL
            // mengikat constraint ke index ini — bukan ke index unik/fungsional
            // yang suatu saat harus bisa dilepas (MySQL menolak melepas index yang
            // masih dipakai FK, termasuk index fungsional).
            $table->index('academic_year_id');
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index('homeroom_teacher_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['level', 'major', 'academic_year_id']);

            // Nama rombel unik per tahun ajaran.
            $table->unique(['name', 'academic_year_id'], 'classrooms_name_academic_year_unique');

            // Satu nomor sesi hanya boleh dipakai sekali per tingkat + jurusan + tahun ajaran,
            // agar penomoran sesi otomatis tetap dapat diandalkan.
            $table->unique(['level', 'major', 'section', 'academic_year_id'], 'classrooms_session_unique');

            // Satu guru hanya boleh menjadi wali kelas pada satu rombel per tahun ajaran.
            $table->unique(['homeroom_teacher_id', 'academic_year_id'], 'classrooms_homeroom_academic_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
