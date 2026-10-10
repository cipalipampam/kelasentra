<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entitas ber-histori memakai soft delete, dan kunci bisnisnya hanya
     * berlaku untuk baris aktif agar kunci bekas baris terhapus bisa dipakai ulang.
     */
    private const SOFT_DELETE_TABLES = ['users', 'students', 'employees', 'subjects', 'schedules', 'classrooms'];

    /**
     * @var list<array{0: string, 1: string}> pasangan [tabel, nama index unik lama]
     */
    private const BUSINESS_UNIQUE_INDEXES = [
        ['users', 'users_email_unique'],
        ['students', 'students_nis_unique'],
        ['students', 'students_nisn_unique'],
        ['employees', 'employees_nip_unique'],
        ['subjects', 'subjects_code_unique'],
        ['classrooms', 'classrooms_name_academic_year_unique'],
        ['classrooms', 'classrooms_session_unique'],
    ];

    /**
     * @var list<array{0: string, 1: string}> pasangan [tabel, nama index unik "aktif"]
     */
    private const ACTIVE_UNIQUE_INDEXES = [
        ['users', 'users_active_email'],
        ['students', 'students_active_nis'],
        ['students', 'students_active_nisn'],
        ['employees', 'employees_active_nip'],
        ['subjects', 'subjects_active_code'],
        ['classrooms', 'classrooms_active_name'],
        ['classrooms', 'classrooms_active_session'],
    ];

    /**
     * @var array<string, list<string>> kolom tiap index unik lama, untuk rollback
     */
    private const UNIQUE_COLUMNS = [
        'users_email_unique' => ['email'],
        'students_nis_unique' => ['nis'],
        'students_nisn_unique' => ['nisn'],
        'employees_nip_unique' => ['nip'],
        'subjects_code_unique' => ['code'],
        'classrooms_name_academic_year_unique' => ['name', 'academic_year_id'],
        'classrooms_session_unique' => ['level', 'major', 'section', 'academic_year_id'],
    ];

    public function up(): void
    {
        foreach (self::SOFT_DELETE_TABLES as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->softDeletes();
                });
            }
        }

        foreach (self::BUSINESS_UNIQUE_INDEXES as [$table, $index]) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropUnique($index);
                });
            }
        }

        $active = fn (string $column) => '(CASE WHEN deleted_at IS NULL THEN '.$column.' ELSE NULL END)';

        DB::statement('CREATE UNIQUE INDEX users_active_email ON users ('.$active('email').')');
        DB::statement('CREATE UNIQUE INDEX students_active_nis ON students ('.$active('nis').')');
        DB::statement('CREATE UNIQUE INDEX students_active_nisn ON students ('.$active('nisn').')');
        DB::statement('CREATE UNIQUE INDEX employees_active_nip ON employees ('.$active('nip').')');
        DB::statement('CREATE UNIQUE INDEX subjects_active_code ON subjects ('.$active('code').')');
        DB::statement('CREATE UNIQUE INDEX classrooms_active_name ON classrooms (academic_year_id, '.$active('name').')');
        DB::statement('CREATE UNIQUE INDEX classrooms_active_session ON classrooms (level, section, academic_year_id, '.$active('major').')');
    }

    public function down(): void
    {
        foreach (self::BUSINESS_UNIQUE_INDEXES as [$table, $index]) {
            if (! Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique(self::UNIQUE_COLUMNS[$index], $index));
            }
        }

        foreach (self::ACTIVE_UNIQUE_INDEXES as [$table, $index]) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropUnique($index);
                });
            }
        }

        foreach (self::SOFT_DELETE_TABLES as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropSoftDeletes();
                });
            }
        }
    }
};
