<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Slot waktu dari sebuah penugasan mengajar. Guru, rombel, mapel, dan
        // tahun ajaran dibaca lewat teaching_assignment agar tidak ada salinan.
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Senin, 2 = Selasa, 3 = Rabu, 4 = Kamis, 5 = Jumat, 6 = Sabtu
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 50)->nullable(); // Contoh: "Lab Komputer 1", "R.204"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['teaching_assignment_id', 'day_of_week']);
            $table->index(['day_of_week', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};

