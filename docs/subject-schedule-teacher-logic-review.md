# Review Logic Mata Pelajaran, Jadwal Pelajaran, Guru, dan Karyawan

## Konteks

Logic mata pelajaran, jadwal, guru, dan karyawan harus mengikuti model akademik yang lebih besar:

```text
AcademicYear
    └── Classroom
            └── StudentEnrollment

Subject
    └── CurriculumSubject
            └── TeachingAssignment
                    └── Schedule
                            └── ScheduleAttendance

User
    └── Employee
            ├── TeacherSubjectQualification
            └── TeachingAssignment
```

Masalah utama pada implementasi saat ini adalah data operasional masih banyak diperlakukan sebagai data global dengan flag `is_active`, padahal seharusnya memiliki konteks tahun ajaran, classroom, enrollment, dan penugasan guru.

---

## Kondisi Logic Saat Ini

### Struktur mata pelajaran

Model `Subject` saat ini memiliki:

```text
code
name
cluster
color_code
is_active
```

Subject memiliki relasi ke:

```text
schedules
teachers melalui teacher_subjects
```

### Struktur jadwal

Model `Schedule` saat ini memiliki:

```text
classroom_id
subject_id
teacher_id
day_of_week
start_time
end_time
room
is_active
```

Tahun ajaran hanya diketahui secara tidak langsung melalui:

```text
schedule.classroom.academic_year
```

### Struktur guru dan karyawan

Seseorang direpresentasikan melalui:

```text
users
employees
roles
teacher_subjects
```

Role utama yang digunakan adalah:

```text
guru
staff
```

Status kepegawaian:

```text
active
leave
retired
resigned
```

### Pivot guru dan subject

Tabel `teacher_subjects` saat ini menyimpan:

```text
user_id
subject_id
is_primary
```

Relasi ini lebih cocok dimaknai sebagai kompetensi guru, bukan penugasan mengajar pada tahun ajaran tertentu.

---

## Temuan dan Potensi Masalah

## 1. Subject masih sepenuhnya global

Master subject berlaku untuk semua tahun ajaran, tingkat, dan jurusan. Padahal konfigurasi akademik subject dapat berbeda berdasarkan:

```text
academic year
level
major
curriculum
weekly JP
semester
required/elective status
```

Contoh:

| Tahun ajaran | Tingkat | Jurusan | Mata pelajaran | JP | Jenis |
|---|---:|---|---|---:|---|
| 2024/2025 | X | MIPA | Matematika | 4 | Wajib |
| 2024/2025 | XI | MIPA | Matematika | 4 | Wajib |
| 2024/2025 | XII | MIPA | Matematika | 3 | Wajib |

Saat ini belum ada entitas yang menyimpan konfigurasi tersebut.

### Rekomendasi

Pertahankan `Subject` sebagai master global, lalu tambahkan entitas konfigurasi kurikulum:

```text
CurriculumSubject
```

Dengan field seperti:

```text
id
academic_year_id
subject_id
level
major
is_required
weekly_jp
semester
is_active
created_at
updated_at
```

---

## 2. `Subject.is_active` terlalu global

Jika sebuah subject dinonaktifkan, efeknya berlaku untuk seluruh tahun ajaran. Padahal subject dapat:

```text
tidak digunakan pada tahun ajaran baru
masih memiliki jadwal lama
masih dibutuhkan untuk histori absensi
masih dibutuhkan untuk laporan akademik
```

### Rekomendasi

Bedakan antara:

```text
Subject.is_active
    = status master mata pelajaran

CurriculumSubject.is_active
    = status penggunaan subject pada tahun ajaran tertentu
```

Subject lama sebaiknya tidak langsung dihapus. Gunakan:

```text
soft delete
atau
status inactive
```

agar histori tetap aman.

---

## 3. Relasi guru dan subject masih bercampur dengan penugasan

`teacher_subjects` saat ini menunjukkan guru dapat mengajar subject tertentu. Namun hal itu berbeda dengan penugasan aktual.

### Kompetensi guru

```text
Guru A → Matematika
Guru A → Informatika
```

### Penugasan aktual

```text
2024/2025
Guru A
Matematika
X-MIPA 1
4 JP per minggu
```

### Rekomendasi

Pisahkan menjadi dua konsep:

```text
TeacherSubjectQualification
    = kompetensi atau linieritas guru

TeachingAssignment
    = penugasan guru pada academic year, classroom, dan subject tertentu
```

Struktur `TeachingAssignment` yang disarankan:

```text
id
academic_year_id
classroom_id
teacher_id
subject_id
curriculum_subject_id
weekly_jp
status
created_at
updated_at
```

Jika satu subject hanya boleh memiliki satu guru pada satu classroom:

```text
unique(academic_year_id, classroom_id, subject_id)
```

Jika satu subject boleh diajar beberapa guru:

```text
unique(academic_year_id, classroom_id, subject_id, teacher_id)
```

---

## 4. Validasi guru belum sepenuhnya konsisten

Validasi jadwal saat ini terutama memeriksa apakah guru memiliki subject pada pivot `teacher_subjects`.

Namun guru yang dapat membuat jadwal baru seharusnya memenuhi semua syarat berikut:

```text
memiliki role guru
memiliki data employee
employee.is_teacher = true
employment_status = active
memiliki qualification untuk subject terkait
```

Guru yang sedang:

```text
leave
retired
resigned
```

seharusnya tidak dapat menerima penugasan baru.

### Rekomendasi

Buat rule atau service khusus seperti:

```text
EligibleTeachingTeacher
```

Rule tersebut digunakan saat membuat `TeachingAssignment` dan `Schedule`.

---

## 5. Staff berpotensi masuk ke alur pengajaran

Saat ini sebagian query mengambil user dengan role `guru`, tetapi field `employee.is_teacher` belum selalu digunakan secara konsisten.

Hal ini dapat menyebabkan data yang tidak seharusnya menjadi pengajar tetap muncul sebagai kandidat.

### Aturan yang disarankan

```text
Guru:
  role = guru
  is_teacher = true
  employment_status = active

Staff:
  role = staff
  is_teacher = false
  tidak boleh memiliki teaching assignment
  tidak boleh menjadi wali kelas
```

---

## 6. Penghapusan guru dapat menghapus histori jadwal

Tabel `schedules` menggunakan foreign key guru dengan cascade delete:

```text
teacher_id → users.id → cascadeOnDelete
```

Jika akun guru dihapus, jadwal dan absensi yang terkait dapat ikut terhapus. Ini berbahaya untuk histori akademik.

### Rekomendasi

Guru yang sudah tidak bekerja sebaiknya tidak dihapus. Gunakan:

```text
employment_status = resigned
atau
employment_status = retired
```

Jadwal lama tetap disimpan. Assignment mendatang dapat ditutup atau dipindahkan ke guru pengganti.

Jika penghapusan tetap diperlukan, gunakan strategi histori yang aman, misalnya:

```text
restrictOnDelete
set null
soft delete
```

---

## 7. Perubahan role guru menjadi staff belum menangani jadwal aktif

`EmployeeService::updateEmployee()` melakukan sinkronisasi role:

```php
$user->syncRoles([$data['role']]);
```

Namun perubahan:

```text
guru → staff
```

seharusnya juga memproses:

- teaching assignment aktif;
- schedule mendatang;
- classroom yang memiliki guru tersebut;
- status wali kelas;
- subject qualification;
- kebutuhan guru pengganti;
- histori jadwal lama.

### Alur yang disarankan

Jika guru memiliki jadwal aktif, sistem sebaiknya:

1. menolak perubahan role sampai jadwal dipindahkan; atau
2. meminta admin memilih guru pengganti; atau
3. menutup jadwal mendatang dan menyimpan histori jadwal lama.

Jadwal masa lalu tidak boleh dihapus.

---

## 8. Schedule belum memiliki konteks AcademicYear secara eksplisit

Saat ini schedule terhubung ke academic year melalui classroom:

```text
Schedule → Classroom → academic_year
```

Pendekatan ini dapat bekerja sementara, tetapi membuat query histori dan validasi menjadi lebih sulit.

### Rekomendasi minimal

Semua query schedule operasional harus memfilter konteks tahun ajaran melalui classroom.

### Rekomendasi jangka panjang

Tambahkan `academic_year_id` secara eksplisit atau hubungkan schedule melalui `TeachingAssignment` yang sudah memiliki `academic_year_id`.

Struktur jangka panjang:

```text
Schedule
    └── teaching_assignment_id
            ├── academic_year_id
            ├── classroom_id
            ├── subject_id
            └── teacher_id
```

Dengan begitu schedule selalu memiliki konteks akademik yang jelas.

---

## 9. `is_active` tidak cukup untuk lifecycle jadwal

Schedule saat ini hanya memiliki:

```text
is_active = true/false
```

Padahal lifecycle jadwal biasanya memerlukan status:

```text
draft
published
active
closed
cancelled
```

Contoh:

```text
Jadwal 2025/2026 dibuat → draft
Jadwal disetujui → published
Tahun ajaran berjalan → active
Tahun ajaran berakhir → closed
Jadwal diganti → cancelled
```

### Rekomendasi

Ganti atau lengkapi `is_active` dengan status lifecycle yang lebih eksplisit:

```text
status
published_at
closed_at
cancelled_at
```

Aplikasi mobile hanya membaca schedule yang:

```text
status = published atau active
academic year = current
student enrollment = active
```

---

## 10. Workload guru masih mencampur tahun ajaran

`ScheduleService::calculateTeacherWeeklyWorkload()` saat ini mengambil seluruh schedule guru yang `is_active = true`.

Jika schedule dari beberapa tahun ajaran masih aktif, workload dapat tercampur:

```text
jadwal 2023/2024
jadwal 2024/2025
jadwal 2025/2026
```

### Rekomendasi

Workload wajib memiliki konteks academic year:

```php
calculateTeacherWeeklyWorkload(
    User $teacher,
    AcademicYear $academicYear,
): array
```

Atau:

```php
calculateTeacherWeeklyWorkload(
    User $teacher,
    int $academicYearId,
): array
```

Perhitungan JP hanya mengambil assignment atau schedule pada periode tersebut.

---

## 11. API jadwal siswa masih bergantung pada `students.classroom_id`

API schedule siswa saat ini mengambil jadwal berdasarkan:

```php
$user->student->classroom_id
```

dan:

```php
where('is_active', true)
```

Setelah `StudentEnrollment` diterapkan, API sebaiknya mengikuti alur:

```text
authenticated student
        ↓
current student enrollment
        ↓
current classroom
        ↓
current academic year
        ↓
published/active schedules
```

Ini mencegah siswa melihat jadwal classroom lama atau jadwal dari tahun ajaran yang salah.

---

## 12. ScheduleSeeder menghapus seluruh jadwal

Saat ini `ScheduleSeeder` melakukan:

```php
Schedule::query()->delete();
```

Ini berbahaya karena dapat menghapus:

```text
jadwal semua tahun ajaran
absensi mata pelajaran lama
histori pengajaran
```

### Rekomendasi

Seeder harus dibatasi ke academic year tertentu:

```text
hapus atau reset schedule untuk academic_year target saja
buat ulang schedule untuk academic_year tersebut
```

Lebih baik menggunakan `updateOrCreate()` berdasarkan identitas jadwal yang stabil, misalnya:

```text
academic_year_id
classroom_id
subject_id
teacher_id
day_of_week
start_time
```

Seeder tidak boleh menghapus histori tahun ajaran lain.

---

## 13. ScheduleAttendance belum terikat kuat dengan enrollment

`ScheduleAttendance` saat ini menyimpan:

```text
schedule_id
student_id
teacher_id
attendance_date
status
notes
recorded_at
```

Belum ada referensi eksplisit terhadap:

```text
academic_year_id
classroom_id saat absensi
student_enrollment_id
```

Jika siswa sudah berpindah classroom, sistem perlu memastikan absensi dibuat untuk enrollment yang benar pada tanggal tersebut.

### Rekomendasi

Tambahkan:

```text
student_enrollment_id
```

`student_id` dapat tetap disimpan sebagai referensi cepat atau snapshot, tetapi konteks enrollment menjadi sumber validasi utama.

---

## Logic Schedule yang Sudah Baik

Beberapa bagian implementasi saat ini sudah memiliki dasar yang baik:

### Anti-bentrok guru

Schedule memeriksa overlap berdasarkan:

```text
teacher_id
day_of_week
start_time
end_time
```

### Anti-bentrok classroom

Satu classroom tidak boleh memiliki dua jadwal yang beririsan.

### Validasi jam sekolah

Jadwal dibatasi pada rentang:

```text
07:00–16:00
```

### Validasi waktu istirahat

Sudah terdapat guard untuk:

```text
09:30–10:00
11:45–13:00
```

serta variasi Jumat.

### Validasi linieritas

Guru harus memiliki subject pada relasi `teacher_subjects`.

Namun validasi tersebut perlu ditambah dengan status employee, `is_teacher`, academic year, dan teaching assignment.

---

## Struktur Domain Target

Struktur domain yang disarankan:

```text
AcademicYear
    ├── Classrooms
    ├── CurriculumSubjects
    ├── TeachingAssignments
    └── Schedules

Subject
    └── CurriculumSubjects

User
    └── Employee
          ├── TeacherSubjectQualifications
          └── TeachingAssignments

Student
    └── StudentEnrollments
            └── Classroom
```

Makna setiap entitas:

```text
Subject
    = master mata pelajaran global

CurriculumSubject
    = subject yang berlaku pada academic year, level, jurusan,
      semester, dan jumlah JP tertentu

TeacherSubjectQualification
    = kompetensi atau linieritas guru

TeachingAssignment
    = penugasan guru pada classroom dan academic year tertentu

Schedule
    = slot waktu dari teaching assignment

ScheduleAttendance
    = absensi siswa berdasarkan schedule dan enrollment
```

---

## Alur Normal Mata Pelajaran dan Jadwal

### Tahap 1: Master subject

Admin membuat master:

```text
Matematika
Bahasa Indonesia
Fisika
Biologi
```

Master ini tidak terikat pada satu tahun ajaran.

### Tahap 2: Konfigurasi kurikulum

Admin menentukan:

```text
2025/2026
Kelas X
MIPA
Matematika
4 JP
Wajib
```

Data ini disimpan sebagai `CurriculumSubject`.

### Tahap 3: Kompetensi guru

Guru memiliki kompetensi:

```text
Guru A → Matematika
Guru B → Bahasa Indonesia
```

Ini tidak otomatis berarti guru sudah ditugaskan pada tahun berjalan.

### Tahap 4: Teaching assignment

Admin menetapkan:

```text
2025/2026
X-MIPA 1
Matematika
Guru A
4 JP per minggu
```

### Tahap 5: Schedule

Admin membuat slot:

```text
X-MIPA 1
Matematika
Guru A
Senin
08:00–09:30
```

Sistem memvalidasi:

```text
guru aktif
guru benar-benar pengajar
guru memiliki qualification subject
guru tidak bentrok
classroom tidak bentrok
ruangan tidak bentrok
jumlah JP tidak berlebihan
jadwal berada pada academic year yang sesuai
```

### Tahap 6: Publikasi

Lifecycle jadwal:

```text
draft → published → active → closed
```

Aplikasi mobile hanya membaca jadwal yang sudah dipublikasikan atau aktif.

### Tahap 7: Pergantian tahun ajaran

Saat tahun ajaran selesai:

```text
schedule 2024/2025 → closed
teaching assignment 2024/2025 → closed
academic year 2024/2025 → closed
```

Jadwal dan absensi lama tidak dihapus.

---

## Aturan Guru dan Karyawan

### Guru aktif

```text
role = guru
is_teacher = true
employment_status = active
memiliki subject qualification
```

### Staff

```text
role = staff
is_teacher = false
tidak boleh memiliki teaching assignment
 tidak boleh menjadi wali kelas
```

### Guru cuti

```text
tidak menerima jadwal baru
tidak menjadi wali kelas baru
jadwal lama tetap tersimpan
assignment mendatang perlu diganti atau ditutup
```

### Guru pensiun atau resign

```text
akun tidak dihapus
employment_status = retired/resigned
assignment baru ditolak
jadwal lama tetap tersedia
jadwal mendatang dipindahkan atau ditutup
```

---

## Contoh Alur Terintegrasi

Misalnya:

```text
AcademicYear current = 2024/2025
```

Siswa aktif:

```text
Angkatan 2022 → XII-MIPA 1
Angkatan 2023 → XI-MIPA 1
Angkatan 2024 → X-MIPA 1
```

Konfigurasi kurikulum:

```text
2024/2025 + X + MIPA + Matematika + 4 JP
2024/2025 + XI + MIPA + Matematika + 4 JP
2024/2025 + XII + MIPA + Matematika + 3 JP
```

Kompetensi guru:

```text
Guru A → Matematika
Guru B → Bahasa Indonesia
```

Teaching assignment:

```text
Guru A → Matematika → X-MIPA 1 → 2024/2025
Guru A → Matematika → XI-MIPA 1 → 2024/2025
Guru B → Bahasa Indonesia → XII-MIPA 1 → 2024/2025
```

Schedule:

```text
X-MIPA 1
Matematika
Guru A
2024/2025
Senin 08:00–09:30
```

Saat tahun ajaran berganti:

```text
2024/2025 schedule → closed
2025/2026 schedule → active
```

Namun data lama tetap tersedia untuk histori:

```text
siswa
classroom
teacher assignment
schedule
schedule attendance
```

---

## Prioritas Refactor

### Prioritas 1: Pisahkan current year dari status siswa

Pastikan `AcademicYear.current` hanya menentukan periode kalender sekolah. Siswa dari banyak angkatan tetap dapat berstatus `active`.

### Prioritas 2: Tambahkan konteks academic year pada schedule

Minimal, semua query schedule operasional harus memfilter melalui classroom dan academic year. Jangka panjangnya, schedule diarahkan melalui `TeachingAssignment`.

### Prioritas 3: Perbaiki ScheduleSeeder

Jangan gunakan:

```php
Schedule::query()->delete();
```

Seeder harus bekerja scoped pada academic year tertentu agar histori tahun lain tidak hilang.

### Prioritas 4: Scope workload guru

Workload harus dihitung untuk academic year tertentu, bukan seluruh schedule yang memiliki `is_active = true`.

### Prioritas 5: Validasi guru secara lengkap

Pastikan guru:

```text
role guru
employee.is_teacher = true
employment_status active
memiliki qualification
```

### Prioritas 6: Pisahkan qualification dan assignment

Gunakan:

```text
teacher_subjects = kompetensi guru
teaching_assignments = penugasan aktual per tahun ajaran
```

### Prioritas 7: Tambahkan konfigurasi kurikulum

Subject perlu dikaitkan dengan academic year, level, major, semester, dan jumlah JP.

### Prioritas 8: Lindungi histori

Jangan menghapus guru, subject, schedule, atau attendance yang sudah memiliki histori. Gunakan status closed, inactive, soft delete, atau restrict delete.

### Prioritas 9: Perbaiki API schedule

API siswa harus mengambil jadwal berdasarkan current enrollment, current academic year, dan schedule yang sudah published/active.

### Prioritas 10: Tambahkan test lintas tahun ajaran

Test minimal:

- schedule tahun lama tidak muncul sebagai jadwal current;
- workload guru tidak mencampur tahun ajaran;
- guru resign tidak dapat menerima assignment baru;
- staff tidak dapat menjadi pengajar;
- subject lama tidak menghapus histori;
- seeder tahun baru tidak menghapus schedule lama;
- siswa hanya melihat jadwal berdasarkan enrollment aktif;
- schedule attendance menolak siswa dari classroom berbeda.

---

## Kesimpulan

Masalah terbesar saat ini adalah data operasional masih banyak direpresentasikan sebagai:

```text
global + is_active
```

Padahal seharusnya memiliki konteks:

```text
academic year
classroom
enrollment
curriculum
teacher qualification
teaching assignment
schedule lifecycle
```

Model target:

```text
Subject
    = master mata pelajaran

CurriculumSubject
    = subject yang berlaku pada tahun ajaran, tingkat, jurusan,
      semester, dan jumlah JP tertentu

TeacherSubjectQualification
    = kompetensi guru

TeachingAssignment
    = penugasan guru pada classroom dan academic year tertentu

Schedule
    = slot waktu dari teaching assignment

ScheduleAttendance
    = absensi siswa berdasarkan schedule dan enrollment
```

Urutan refactor yang disarankan:

```text
1. Luruskan AcademicYear dan StudentEnrollment.
2. Pastikan Classroom memiliki konteks academic year yang jelas.
3. Tambahkan konfigurasi kurikulum per tahun ajaran.
4. Pisahkan qualification guru dan teaching assignment.
5. Tambahkan konteks academic year pada schedule.
6. Perbaiki validasi guru, subject, classroom, dan konflik jadwal.
7. Perbaiki API schedule berdasarkan current enrollment siswa.
8. Perbaiki seeder agar tidak menghapus histori.
9. Tambahkan lifecycle untuk assignment dan schedule.
10. Tambahkan test lintas tahun ajaran.
```
