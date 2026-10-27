<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20); // Contoh: "MAT-W", "BIO-P", "B-IND"
            $table->string('name', 100); // Contoh: "Matematika Wajib", "Biologi"
            $table->string('cluster', 50)->default('mipa'); // Rumpun mapel: mipa, ips, bahasa, umum
            $table->string('color_code', 10)->default('#2563eb'); // Warna aksen kartu di UI mobile (#2563eb, #059669, dll)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX subjects_active_code ON subjects ((CASE WHEN deleted_at IS NULL THEN code ELSE NULL END))');

        // Pivot table: Guru dan Mapel yang diampu (Kurikulum Merdeka linieritas serumpun)
        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false); // Mapel utama vs mapel serumpun/tambahan
            $table->timestamps();

            $table->unique(['user_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('subjects');
    }
};
