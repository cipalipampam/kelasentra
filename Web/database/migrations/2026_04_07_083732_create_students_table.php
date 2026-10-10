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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // Angkatan masuk siswa (cohort), terpisah dari kelas yang sedang ditempati.
            $table->foreignId('entry_academic_year_id')->nullable()->constrained('academic_years');
            $table->foreignId('graduation_academic_year_id')->nullable()->constrained('academic_years');
            $table->string('nis')->unique()->nullable();
            $table->string('nisn')->unique()->nullable();
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

            $table->index('academic_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
