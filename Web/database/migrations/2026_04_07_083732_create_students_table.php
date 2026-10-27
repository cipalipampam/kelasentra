<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // Angkatan masuk siswa (cohort), terpisah dari kelas yang sedang ditempati.
            $table->foreignId('entry_academic_year_id')->nullable()->constrained('academic_years');
            $table->foreignId('graduation_academic_year_id')->nullable()->constrained('academic_years');
            $table->string('nis')->nullable();
            $table->string('nisn')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('religion')->nullable();
            $table->text('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('profile_picture')->nullable();
            $table->enum('academic_status', ['active', 'graduated', 'transferred', 'dropped'])->default('active');
            $table->timestamp('graduated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('academic_status');
        });

        DB::statement('CREATE UNIQUE INDEX students_active_nis ON students ((CASE WHEN deleted_at IS NULL THEN nis ELSE NULL END))');
        DB::statement('CREATE UNIQUE INDEX students_active_nisn ON students ((CASE WHEN deleted_at IS NULL THEN nisn ELSE NULL END))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
