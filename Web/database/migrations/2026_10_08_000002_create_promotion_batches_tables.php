<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak audit kenaikan kelas / kelulusan massal agar dapat ditelusuri dan dibatalkan.
        Schema::create('promotion_batches', function (Blueprint $table) {
            $table->id();
            $table->enum('action', ['promote', 'graduate']);
            $table->foreignId('source_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('source_classroom_name', 50);
            $table->string('source_academic_year', 20);
            $table->foreignId('target_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('target_classroom_name', 50)->nullable();
            $table->string('target_academic_year', 20)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name', 100);
            $table->unsignedSmallInteger('student_count')->default(0);
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reverted_by_name', 100)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('promotion_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_name', 100);
            $table->string('student_nis', 30)->nullable();
            $table->foreignId('from_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('from_classroom_name', 50)->nullable();
            $table->string('from_grade', 50)->nullable();
            $table->string('from_academic_status', 30);
            $table->foreignId('to_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('to_academic_status', 30);
            $table->timestamps();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_batch_items');
        Schema::dropIfExists('promotion_batches');
    }
};
