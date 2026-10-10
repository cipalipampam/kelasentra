<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            // Riwayat presensi dilindungi: user tidak dapat dihapus permanen.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Konteks akademik saat presensi dicatat (snapshot), bukan posisi terkini.
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms');
            $table->datetime('recorded_at');
            $table->datetime('check_out_time')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status');
            $table->boolean('is_late')->default(false);
            $table->boolean('is_approved')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
