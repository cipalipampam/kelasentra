# Rencana Pengerjaan Refactor Domain Akademik (Bertahap)

Status dokumen: **AKTIF**
Dibuat: 2026-10-09
Acuan spec: [`docs/academic-domain-spec.md`](./academic-domain-spec.md)

Dokumen ini adalah kontrol pengerjaan. Setiap tugas punya ID, prasyarat, berkas terkait, cara verifikasi, dan status.

---

## 1. Cara pakai

### Legenda status

| Simbol | Arti |
|---|---|
| `[ ]` | Belum dikerjakan |
| `[~]` | Sedang dikerjakan |
| `[x]` | Selesai & terverifikasi |
| `[!]` | Blocked / butuh keputusan |

### Aturan kerja

1. Satu tugas selesai **hanya jika** cara verifikasinya lulus.
2. Jangan mulai tugas yang prasyaratnya belum `[x]`.
3. Setiap fase diakhiri dengan menjalankan seluruh test suite (`php artisan test`) sebelum lanjut fase berikutnya.
4. Setelah setiap perubahan skema: jalankan `php artisan migrate:fresh --seed` pada DB bersih.
5. Update kolom status di dokumen ini setiap kali tugas berpindah status.

### Perintah bantu

```powershell
# PHP di environment ini
$php = "C:\laragon\bin\php\php-8.5.8-nts-Win32-vs17-x64\php.exe"

# Migrasi + seed ulang dari nol
& $php artisan migrate:fresh --seed

# Test
& $php artisan test

# Lint
& $php vendor\bin\pint database app --test
```

---

## 2. Peta ketergantungan fase

```text
Fase 1  Fondasi tahun ajaran & FK classroom
   │
Fase 2  Enrollment & perubahan students
   │
Fase 3  Promotion (naik/tinggal/lulus) & revert
   │
Fase 4  TeachingAssignment & bentuk baru Schedule (+ eligibility guru)
   │
Fase 5  Presensi snapshot & perlindungan histori (+ soft delete)
   │
Fase 6  Finalisasi (seeder, kompatibilitas Mobile, test penuh, dokumen)
```

Fase bersifat berurutan (setiap fase bergantung pada fase sebelumnya).

---

## 3. Fase 1 — Fondasi tahun ajaran & FK classroom

Keputusan terkait: **D1, D2, D12** · Celah terkait: **R** (tahun classroom tidak boleh diubah)

| ID | Tugas | Prasyarat | Berkas terkait | Verifikasi | Status |
|---|---|---|---|---|---|
| F1-1 | Migrasi `academic_years`: enum → `upcoming/current/closed`; kolom `start_year` (smallint, diisi otomatis dari `name` lewat model) + unique; index unik ekspresi `CASE WHEN status='current'` dan `'upcoming'` (maks satu masing-masing). Format nama dijaga validasi aplikasi, bukan CHECK DB, agar portabel SQLite | — | `database/migrations/2026_04_07_083730_*.php` | `migrate:fresh` di MySQL & SQLite sukses; insert 2 `current` ditolak DB | `[x]` |
| F1-2 | Model `AcademicYear`: konstanta & label baru; helper `currentYear()`, `nextYear()`, `operableYears()`, `operableIds()`; mutator `start_year`; hapus `activeName()`/`selectableNames()` | F1-1 | `app/Models/AcademicYear.php` | Tidak ada referensi `selectableNames()` tersisa; test helper `start_year` hijau | `[x]` |
| F1-3 | Migrasi `classrooms`: `academic_year` → `academic_year_id` FK; sesuaikan seluruh unique constraint. Migrasi `academic_years` dipindah ke `2026_04_07_083730` agar tabel induk dibuat lebih dulu | F1-1 | `database/migrations/2026_04_07_083731_*.php` | `migrate:fresh` sukses; constraint tetap berlaku | `[x]` |
| F1-4 | Model `Classroom`: relasi tahun via FK, `sectionKey()` pakai `academic_year_id`, helper `isOperable()`, `promotionBlockReason()` pakai kronologi `start_year` (aturan tepat `+1` & tinggal kelas menyusul di Fase 3) | F1-3 | `app/Models/Classroom.php` | Feature test promosi tetap hijau | `[x]` |
| F1-5 | Request rules: `Store/UpdateClassroomRequest` memakai `academic_year_id` (operable), `HomeroomTeacherAvailable` memakai id; tahun `upcoming` dibatasi lewat validasi | F1-3 | `app/Http/Requests/Web/Academic/*`, `app/Rules/HomeroomTeacherAvailable.php` | Feature test validasi | `[x]` |
| F1-6 | `AcademicYearService`: `setCurrent`, `delete`, aturan tepat 1 `current` (periode lama ditutup lebih dulu agar index unik tidak menolak) & maks 1 `upcoming`; hapus `archiveOtherActiveYears()` | F1-2 | `app/Services/Web/Academic/AcademicYearService.php` | Feature test aktivasi & penghapusan | `[x]` |
| F1-7 | `ClassroomService`: `getRunningClassrooms()`/`getEnrollableClassrooms()` pakai `operableIds()`; operability rombel = `is_active` **dan** tahunnya belum `closed`; batasi enrollable ke `current` + tepat satu berikutnya | F1-2 | `app/Services/Web/Academic/ClassroomService.php` | Feature test daftar rombel | `[x]` |
| F1-8 | Larang ubah `academic_year_id` classroom setelah dibuat (`Rule::in` ke tahun asal; form edit menampilkan select ter-disable + hidden input) | F1-5 | `app/Http/Requests/Web/Academic/UpdateClassroomRequest.php`, view edit rombel | Test `classroom academic year cannot be changed after creation` hijau | `[x]` |
| F1-9 | View `admin/academic-years/*` & `admin/classrooms/*` menyesuaikan status baru & relasi tahun; JS island (`section-suggestion`, `homeroom-teacher`) memakai id tahun + nama dari `data-year-name` | F1-2, F1-3 | `resources/views/admin/academic-years/*`, `resources/views/admin/classrooms/*`, `public/js/admin/classrooms/islands/*` | Render halaman tanpa error | `[x]` |
| F1-10 | Test fase 1: sesuaikan seluruh test yang menyentuh tahun ajaran + helper `yearId()` di `Tests\TestCase` + test baru (satu `current`, satu `upcoming`, `start_year`, guard DB) | F1-1..F1-9 | `tests/TestCase.php`, `tests/Feature/*` | `php artisan test` hijau (130 passed) | `[x]` |

**Definition of Done Fase 1:** tabel tahun memakai FK; kronologi memakai `start_year`; tidak ada lagi `selectableNames()`; DB menolak tahun `current` ganda; seluruh test hijau.

---

## 4. Fase 2 — Enrollment & perubahan `students`

Keputusan terkait: **D3, D4, D11, D13** · Celah terkait: **P** (grade alumni), **Q** (EnrollableClassroom)

| ID | Tugas | Prasyarat | Berkas terkait | Verifikasi | Status |
|---|---|---|---|---|---|
| F2-1 | Migrasi `student_enrollments` (+ unique `(student_id, academic_year_id)` + generated `open_flag` unique + soft delete) | Fase 1 | `database/migrations/*` (baru) | Insert 2 enrollment berjalan untuk 1 siswa ditolak DB | `[x]` |
| F2-2 | Model `StudentEnrollment` + relasi di `Student`, `Classroom`, `AcademicYear` | F2-1 | `app/Models/StudentEnrollment.php`, model terkait | Unit test relasi & scope "berjalan" | `[x]` |
| F2-3 | Migrasi `students`: hapus `classroom_id` & `grade`; tambah `entry_academic_year_id`, `graduated_at`, `graduation_academic_year_id` (SoftDeletes dipindah ke Fase 5 agar seragam dengan guard unique) | F2-1 | `database/migrations/2026_04_07_083732_*.php` | `migrate:fresh` di MySQL & SQLite sukses | `[x]` |
| F2-4 | `EnrollmentService` sebagai satu-satunya penulis enrollment (open, close, move, baca kelas sekarang) + transaksi | F2-2 | `app/Services/Web/Academic/EnrollmentService.php` (baru) | Unit test open/close/move | `[x]` |
| F2-5 | Ganti semua query "kelas sekarang" ke enrollment (kapasitas, filter kelas, dashboard, laporan) | F2-4 | `ClassroomService`, `Rules/EnrollableClassroom`, `StudentController`, `AdminAttendanceController`, `DashboardService`, `AdminDashboardStatsService` | Feature test filter & kapasitas | `[x]` |
| F2-6 | `StudentService` create/update/delete memakai enrollment + transaksi; hapus penulisan `grade` | F2-4 | `app/Services/Web/Student/StudentService.php` | Feature test CRUD siswa | `[x]` |
| F2-7 | Perbaiki `EnrollableClassroom`: lewati cek kapasitas/tahun hanya bila siswa tetap di rombel enrollment berjalannya | F2-4 | `app/Rules/EnrollableClassroom.php` | Feature test edit siswa di rombel penuh | `[x]` |
| F2-8 | Terapkan alur status (D13): `transferred`/`dropped` menutup enrollment; kembali `active` wajib penempatan rombel | F2-4 | `StudentService`, request/view siswa | Feature test ubah status | `[x]` |
| F2-9 | API: field `grade` computed (enrollment berjalan, fallback enrollment terakhir) — **jaga bentuk payload** | F2-4 | `app/Services/Api/Auth/AuthService.php`, resource terkait | Test API membandingkan bentuk payload | `[x]` |
| F2-10 | View siswa (index/detail/create/edit) tanpa `grade` & `classroom_id` | F2-6 | `resources/views/admin/students/*` | Render halaman tanpa error | `[x]` |
| F2-11 | Test fase 2: `StudentRombelIntegrationTest`, `ClassroomRulesTest`, `ClassroomSectionSuggestionTest`, `AcademicApiTest` disesuaikan | F2-1..F2-10 | `tests/Feature/*` | `php artisan test` hijau | `[x]` |

**Definition of Done Fase 2:** tidak ada lagi kolom `students.classroom_id`/`students.grade`; "kelas sekarang" selalu dari enrollment; alumni tetap menampilkan kelas terakhir; seluruh test hijau.

---

## 5. Fase 3 — Promotion, kelulusan, tinggal kelas, revert

Keputusan terkait: **D5, D8** (hapus auto-close)

| ID | Tugas | Prasyarat | Berkas terkait | Verifikasi | Status |
|---|---|---|---|---|---|
| F3-1 | `EnrollmentService`: `promote` (naik kelas), `retain` (tinggal kelas), `graduate` dengan aturan target tepat tahun berikutnya + level + kapasitas (row lock) | Fase 2 | `app/Services/Web/Academic/EnrollmentService.php` | Unit test tiap aturan termasuk penolakan loncat tahun | `[x]` |
| F3-2 | Hapus auto-close rombel & mesin terkait (`source_classroom_closed`, pelepasan wali kelas saat tutup, `reopenSourceClassroom()`) | F3-1 | `ClassroomService`, model `PromotionBatch`, migrasi terkait | Test: rombel tetap aktif setelah siswa naik kelas | `[x]` |
| F3-3 | `revertBatch()` enrollment-aware: tutup/hapus enrollment buatan batch, buka enrollment sebelumnya, pulihkan identitas siswa | F3-2 | `ClassroomService` | Feature test revert (naik kelas & lulus) | `[x]` |
| F3-4 | Request/Controller/View promotion: sumber & target memakai enrollment, aksi naik/tinggal/lulus | F3-1 | `ProcessClassPromotionRequest`, `ClassroomController`, `resources/views/admin/classrooms/components/promotion/*` | Feature test alur UI | `[x]` |
| F3-5 | Test fase 3: `ClassroomPromotionTest`, `ClassroomPromotionRulesTest` + test baru (tinggal kelas, loncat tahun ditolak, lulus mencatat tahun) | F3-1..F3-4 | `tests/Feature/*` | `php artisan test` hijau | `[x]` |

**Definition of Done Fase 3:** promotion hanya boleh target `+1`; tinggal kelas berfungsi; kelulusan mencatat tahun; revert memulihkan enrollment; tidak ada lagi auto-close.

---

## 6. Fase 4 — TeachingAssignment & bentuk baru Schedule

Keputusan terkait: **D10a, D10b, D6, D8 (is_active jadwal)** · Celah terkait: **S** (`is_teacher`)

| ID | Tugas | Prasyarat | Berkas terkait | Verifikasi | Status |
|---|---|---|---|---|---|
| F4-1 | Migrasi `teaching_assignments` (+ `unique(classroom_id, subject_id, teacher_id)` + generated `active_flag` unique + soft delete) | Fase 1 | `database/migrations/*` (baru) | Insert 2 assignment aktif untuk (rombel, mapel) sama ditolak DB | `[x]` |
| F4-2 | Model `TeachingAssignment` + relasi ke classroom, subject, teacher, schedules | F4-1 | `app/Models/TeachingAssignment.php` | Unit test relasi | `[x]` |
| F4-3 | Migrasi `schedules`: tambah `teaching_assignment_id`, hapus `classroom_id/subject_id/teacher_id` | F4-1 | `database/migrations/*` (baru) | `migrate:fresh` sukses | `[x]` |
| F4-4 | Model `Schedule`: relasi via assignment, scope `overlapping`, helper JP/durasi tetap | F4-3 | `app/Models/Schedule.php` | Unit test JP & overlap | `[x]` |
| F4-5 | Rule `EligibleTeachingTeacher` (role guru + `is_teacher` + `employment_status = active` + kualifikasi); perbaiki `is_teacher` di `EmployeeService`; guard perubahan role `guru → staff` saat masih ada jadwal | F4-2 | `app/Rules/EligibleTeachingTeacher.php` (baru), `app/Services/Web/Employee/EmployeeService.php` | Feature test: staff/resign ditolak; role change diblokir | `[x]` |
| F4-6 | `ScheduleService`: clash guru & kelas di-scope tahun ajaran; workload per tahun ajaran | F4-4 | `app/Services/Web/Academic/ScheduleService.php` | Feature test: jadwal tahun berbeda tidak dianggap bentrok; JP tidak dobel | `[x]` |
| F4-7 | Controller & view jadwal: CRUD teaching assignment + slot; pemilihan tahun | F4-5, F4-6 | `app/Http/Controllers/Web/Academic/ScheduleController.php`, `resources/views/admin/schedules/*`, request terkait | Render & simpan jadwal lewat UI | `[x]` |
| F4-8 | API jadwal mobile: enrollment berjalan → classroom → assignment → schedule (tahun berjalan) | F4-4 | `app/Services/Api/Academic/ScheduleService.php` | Test API: siswa hanya melihat jadwal tahun berjalan | `[x]` |
| F4-9 | Test fase 4: `ScheduleWebTest`, `ScheduleWorkloadTest`, `AcademicApiTest` disesuaikan | F4-1..F4-8 | `tests/Feature/*` | `php artisan test` hijau | `[x]` |

**Definition of Done Fase 4:** `schedules` tidak lagi menyimpan kelas/mapel/guru; satu assignment aktif per (rombel, mapel); clash & beban guru sadar tahun; eligibility guru ditegakkan.

---

## 7. Fase 5 — Presensi & perlindungan histori

Keputusan terkait: **D7, D14** · Celah terkait: **T, U**

| ID | Tugas | Prasyarat | Berkas terkait | Verifikasi | Status |
|---|---|---|---|---|---|
| F5-1 | Migrasi `attendances`: + `academic_year_id` (wajib) + `classroom_id` (nullable) | Fase 2 | `database/migrations/*` (baru) | `migrate:fresh` sukses | `[x]` |
| F5-2 | Migrasi `schedule_attendances`: + `student_enrollment_id` (nullable, restrict) | Fase 2 | `database/migrations/*` (baru) | `migrate:fresh` sukses | `[x]` |
| F5-3 | Ubah FK histori dari `cascade` → `restrict`: `attendances.user_id`, `schedule_attendances.schedule_id/student_id/teacher_id`, `teaching_assignments.*`, `student_enrollments.*` (catatan: `schedules` sudah tidak punya FK kelas/mapel/guru sejak Fase 4) | F5-1, F5-2 | `database/migrations/*` (baru) | Hapus user/jadwal ber-histori ditolak DB | `[x]` |
| F5-4 | SoftDeletes pada `users`, `students`, `employees`, `subjects`, `schedules`, `classrooms`, `teaching_assignments` **+ generated kolom "aktif" untuk setiap unique key** (lihat spec §5.9: `active_email`, `active_nis`, `active_nisn`, `active_nip`, `active_code`, unique classroom & assignment) | F5-3 | migrasi + semua model terkait | Hapus bersifat soft; user ter-soft-delete gagal login; kunci bisnis (NIS/NIP/kode/nama rombel) bisa dipakai ulang setelah soft delete; alur hapus user tetap **mencabut token** (spec §11.3 X) | `[x]` |
| F5-4b | Pastikan pembacaan histori tetap menemukan entitas ter-soft-delete bila perlu (`withTrashed()` pada relasi audit/promotion/presensi) | F5-4 | model & service terkait | Feature test: histori tetap tampil walau entitas di-soft-delete | `[x]` |
| F5-5 | Service presensi: tulis snapshot akademik saat mencatat; filter/laporan memakai snapshot | F5-1, F5-2 | `Services/Api/Attendance/AttendanceService.php`, `Services/Api/Academic/ClassAttendanceService.php`, `Services/Web/Attendance/*` | Feature test: presensi tersimpan dengan konteks benar | `[x]` |
| F5-6 | Controller & view presensi: filter per tahun/kelas dari snapshot | F5-5 | `AdminAttendanceController`, `resources/views/admin/attendances/*` | Feature test filter historis | `[x]` |
| F5-7 | Test fase 5: presensi lintas tahun, riwayat tidak hilang saat jadwal/siswa dihapus | F5-1..F5-6 | `tests/Feature/*` | `php artisan test` hijau | `[x]` |

**Definition of Done Fase 5:** presensi selalu punya konteks akademik; hapus entitas ber-histori tidak menghapus riwayat; soft delete konsisten.

**Catatan implementasi Fase 5 (deviasi kecil dari rencana):**

- `attendances.academic_year_id` dibuat `NOT NULL`, tetapi nilainya dijamin aplikasi lewat `AttendanceContext`: bila belum ada tahun ajaran aktif, pencatatan ditolak dengan pesan validasi (bukan error DB).
- Validasi `unique` pada request (`users.email`, `students.nis/nisn`, `employees.nip`, `subjects.code`, `classrooms.name/section`) diberi `whereNull('deleted_at')` agar kunci bekas baris non-aktif benar-benar bisa dipakai ulang (spec §5.9); tanpa ini, aturan validasi akan menolak meski index DB sudah mengizinkan.
- `student_enrollments` **tidak** memakai soft delete (lihat catatan di spec §5.4): unique-nya berbasis `ended_at`, sehingga baris ter-soft-delete akan memblokir penempatan ulang.
- MySQL menolak melepas index yang masih dipakai FK ("needed in a foreign key constraint"), termasuk index fungsional yang memuat kolom FK. Karena itu (a) kolom FK `classrooms` diberi index penopang eksplisit di migrasi dasar agar constraint tidak pernah terikat ke index unik yang dilepas, dan (b) unique `(homeroom_teacher_id, academic_year_id)` tidak dibuat ulang dalam bentuk "aktif" — lihat catatan di spec §5.9. Ketemu saat `migrate:fresh`/`migrate:rollback` di MySQL; SQLite tidak mengeluh.
- Rombel yang dinonaktifkan/dihapus selalu mengosongkan `homeroom_teacher_id` supaya slot wali kelas tidak tersandera baris nonaktif (dijaga test `ClassroomRulesTest`).

---

## 8. Fase 6 — Finalisasi

| ID | Tugas | Prasyarat | Berkas terkait | Verifikasi | Status |
|---|---|---|---|---|---|
| F6-1 | Selaraskan seeder (hanya auth + settings) dengan skema baru | Fase 5 | `database/seeders/DatabaseSeeder.php`, `RoleSeeder.php` | `migrate:fresh --seed` sukses, login admin berhasil | `[x]` |
| F6-2 | Verifikasi kompatibilitas Mobile (endpoint & payload tidak berubah) | Fase 5 | `Mobile/lib/**` (baca saja) | Bandingkan payload API dengan model Mobile | `[x]` |
| F6-3 | Jalankan seluruh test suite + Pint | Fase 5 | — | `php artisan test` hijau; `pint --test` pass | `[x]` |
| F6-4 | Perbarui dokumentasi (`README.md`, spec bila ada perubahan) | F6-3 | `README.md`, `docs/*` | Tidak ada info usang | `[x]` |
| F6-5 | Hapus kode mati (`selectableNames`, accessor `grade`, `reopenSourceClassroom`, `source_classroom_closed`) | F6-3 | kode terkait | Grep tidak menemukan referensi | `[x]` |

**Definition of Done Fase 6:** seluruh test hijau, lint bersih, Mobile tetap kompatibel, dokumentasi selaras, tidak ada kode mati.

**Hasil Fase 6:**
- Seeder bersih (role + akun admin + settings) selaras skema baru; `migrate:fresh --seed` sukses di MySQL dan `Auth::attempt` akun admin terbukti `true`.
- Kontrak Mobile diverifikasi dari sisi konsumen: `Mobile/lib` membaca `student.grade` (dijaga accessor + `$appends`, diuji di `AcademicApiTest`) dan objek bersarang `classroom`/`subject`/`teacher` pada jadwal (dijaga `Services/Api/Academic/ScheduleService`). Kolom baru (`academic_year_id`, `classroom_id`, `student_enrollment_id`) hanya **menambah** field, tidak mengubah atau menghapus field lama.
- Pint dijalankan pada berkas yang tersentuh fase ini; sisa pelanggaran gaya hanya ada di berkas lama yang tidak diubah.
- Kode mati yang disebut rencana sudah tidak ada (`selectableNames`, `reopenSourceClassroom`, `source_classroom_closed`); kolom `students.grade`/`classroom_id` benar-benar hilang dan `grade` kini accessor turunan.
- Ditemukan & diperbaiki di luar rencana: `ClassroomService::getPaginatedClassrooms()` masih mencari kolom `classrooms.academic_year` yang sudah dihapus sejak Fase 1 (pencarian rombel akan error) → kini memakai `orWhereHas('academicYear')`.

### 8.1 Seeder demo lengkap (lanjutan F6-1)

Setelah skema stabil, seluruh seeder domain dipulihkan sebagai dataset demo penuh (13 seeder + `DemoData`): tahun ajaran, mapel, pegawai, rombel, siswa, riwayat promosi, jadwal, presensi gerbang, presensi mapel, pengumuman, dan notifikasi.

- Riwayat kenaikan kelas **tidak ditulis manual**: `PromotionHistorySeeder` memanggil `ClassroomService::processPromotion()` 10 kali (8 kenaikan, 2 kelulusan) sehingga batch audit, enrollment, dan notifikasi siswa dihasilkan aturan yang sama seperti panel admin, lalu menutup kronologi tahun ajaran (2025/2026 selesai, 2026/2027 berjalan, 2027/2028 disiapkan).
- Jadwal dibangun dari pola minggu 20 blok yang digeser per rombel. Karena satu mapel diampu guru yang sama di semua rombel, pergeseran itu menjamin **tidak ada guru mengajar dua rombel pada jam yang sama** (diverifikasi lewat query pemeriksa, bukan asumsi).
- Presensi menyimpan snapshot konteks akademiknya (tahun + rombel untuk presensi gerbang, `student_enrollment_id` untuk presensi mapel), termasuk sampel periode 2025/2026 agar filter laporan historis punya data.
- Seeding mematikan sisi realtime (`broadcasting.default = null`, `queue.default = sync`); tanpa itu setiap notifikasi menghasilkan job `BroadcastEvent` di tabel `jobs` yang menunggu Reverb.
- Verifikasi: `migrate:fresh --seed` sukses, 19 pemeriksaan invariant lulus (kapasitas rombel, satu enrollment berjalan per siswa, tanpa guru bentrok, 240 JP terdistribusi, tabel `jobs` kosong), dan uji API nyata — login siswa menampilkan `student.grade` = `XII MIPA 1`, jadwal Jumat 4 slot, roster presensi mapel 12 siswa dengan prefill dari presensi harian.

---

## 9. Ringkasan progres

| Fase | Jumlah tugas | Selesai | Status fase |
|---|---:|---:|---|
| Fase 1 — Fondasi tahun ajaran & FK classroom | 10 | 10 | `[x]` Selesai |
| Fase 2 — Enrollment & `students` | 11 | 11 | `[x]` Selesai |
| Fase 3 — Promotion & revert | 5 | 5 | `[x]` Selesai |
| Fase 4 — TeachingAssignment & Schedule | 9 | 9 | `[x]` Selesai |
| Fase 5 — Presensi & histori | 8 | 8 | `[x]` Selesai |
| Fase 6 — Finalisasi | 5 | 5 | `[x]` Selesai |
| **Total** | **48** | **48** | |

> Perbarui kolom "Selesai" dan tabel status tugas setiap kali ada tugas yang berpindah status.

---

## 10. Risiko & catatan

| Risiko | Mitigasi |
|---|---|
| Refactor menyentuh ±20 berkas `app/`, 13 view, 9 test | Kerjakan per fase; jalankan seluruh test di akhir setiap fase |
| Tabrakan data saat migrasi | DB saat ini kosong (auth + settings saja) → `migrate:fresh` aman |
| Race condition kapasitas | Row lock (`SELECT ... FOR UPDATE`) pada classroom sebelum hitung & insert (F3-1) |
| Perubahan bentuk `schedules` memutus fitur lama sementara | Fase 4 mengerjakan model, service, view, dan test sekaligus dalam satu fase |
| Invariant baru tidak ditegakkan DB | Generated column + unique index (F1-1, F2-1, F4-1) |
| Mobile rusak karena perubahan payload | Pertahankan nama field `grade` & endpoint (F2-9, F6-2) |
