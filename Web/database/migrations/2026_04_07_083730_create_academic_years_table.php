<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique(); // Format: 2026/2027
            $table->unsignedSmallInteger('start_year'); // Kronologi, diturunkan dari name.
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['upcoming', 'current', 'closed'])->default('upcoming');
            $table->timestamps();

            $table->unique('start_year');
            $table->index('status');
        });

        // Tepat satu tahun ajaran boleh current, dan maksimal satu upcoming.
        // Baris non-target menghasilkan NULL sehingga tidak ikut dibatasi.
        DB::statement(
            "CREATE UNIQUE INDEX academic_years_one_current ON academic_years ((CASE WHEN status = 'current' THEN 1 ELSE NULL END))"
        );
        DB::statement(
            "CREATE UNIQUE INDEX academic_years_one_upcoming ON academic_years ((CASE WHEN status = 'upcoming' THEN 1 ELSE NULL END))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
