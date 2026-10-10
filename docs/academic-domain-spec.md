# Spec Domain Akademik — Hasil Diskusi & Keputusan

Status: **FINAL (menunggu implementasi)**
Tanggal: 2026-10-09
Cakupan: `Web/` (Laravel). Mobile tidak berubah selama kontrak API dipertahankan.

Dokumen ini adalah hasil konsolidasi diskusi atas dua dokumen review:

- `docs/academic-year-cohort-logic-review.md`
- `docs/subject-schedule-teacher-logic-review.md`

Dokumen ini **menggantikan** bagian rekomendasi pada kedua dokumen tersebut bila terjadi perbedaan. Bagian audit konsistensi ada di §11.

---

## 1. Akar masalah

1. **Status tahun ajaran di-overload.** Satu kolom `status` menjawab tiga hal sekaligus: kalender sekolah yang berjalan, periode yang boleh dipakai membuat data, dan (implisit) keaktifan siswa.
2. **Pointer kelas tunggal.** `students.classroom_id` ditimpa saat promotion sehingga riwayat posisi siswa hilang; angkatan masuk juga tidak pernah dimodelkan.
3. **Relasi tahun memakai string.** `classrooms.academic_year` bukan foreign key; rename tahun memutus relasi secara senyap dan perbandingan kronologis bergantung pada string.
4. **Data operasional global + `is_active`.** Jadwal, beban guru, mapel, dan absensi tidak memiliki konteks tahun ajaran/enrollment/penugasan, sehingga begitu periode disiapkan bersamaan, hitungannya salah.

---

## 2. Domain target

```text
AcademicYear        = periode kalender sekolah (upcoming | current | closed)
Classroom           = rombel milik SATU tahun ajaran (academic_year_id)
Student             = identitas permanen (entry_academic_year_id, academic_status)
StudentEnrollment   = keanggotaan siswa pada satu tahun (ended_at null = berjalan)
Subject             = master mata pelajaran global
TeachingAssignment  = guru x rombel x mapel pada satu tahun (tahun via classroom)
Schedule            = slot waktu dari teaching assignment
ScheduleAttendance  = absensi siswa per schedule + konteks enrollment
Angkatan (cohort)   = grouping turunan dari entry_academic_year_id (bukan tabel)
```

Relasi:

```text
AcademicYear
    └── Classrooms
            ├── TeachingAssignments
            │       └── Schedules
            └── StudentEnrollments
                    └── Students

Subject ── TeachingAssignments
User ── Employee ── TeacherSubjectQualifications (tabel: teacher_subjects)
```

---

## 3. Keputusan (terkunci)

| ID | Topik | Keputusan | Alternatif yang ditolak |
|---|---|---|---|
| D1 | Status tahun ajaran | enum `upcoming \| current \| closed`; tepat satu `current`; maks satu `upcoming`; `selectableNames()` dipensiunkan | status turunan tanggal penuh (B); biarkan enum apa adanya (C) |
| D2 | Relasi tahun classroom | `academic_year` (string) → `academic_year_id` (FK) | tetap string + larang rename (B) |
| D3 | Angkatan | `students.entry_academic_year_id`; angkatan = grouping turunan | tabel `cohorts` tersendiri; turunkan dari enrollment |
| D4 | Kelas sekarang | hapus `students.classroom_id`; kelas sekarang = enrollment `ended_at is null`; maks satu enrollment berjalan | cache `classroom_id`; `current_enrollment_id` |
| D5 | Promotion | target = `start_year + 1`; tingkat `+1` (naik) atau sama (tinggal kelas); lulus mencatat `graduated_at` + `graduation_academic_year_id`; revert enrollment-aware | hanya naik kelas; target tahun bebas |
| D6 | Jadwal & beban | di-scope per tahun (via classroom); UI memilih tahun; tahun classroom tidak boleh diubah | tambah `schedules.academic_year_id`; biarkan global |
| D7 | Presensi | snapshot konteks akademik saat dicatat + lindungi histori dari cascade | hanya tambah `academic_year_id`; status quo |
| D8 | Lifecycle classroom | definisi ulang `is_active`; **hapus auto-close**; `planned` diturunkan dari tahun `upcoming` | enum `planned/active/closed` di classroom |
| D9 | Guardrail | unique generated column + row lock + transaksi | hanya di service; status quo |
| D10a | Bentuk Schedule | sepenuhnya via `teaching_assignment_id`; kolom `classroom_id/subject_id/teacher_id` dihapus | simpan kolom sebagai cache; assignment opsional |
| D10b | Unik assignment | **satu assignment aktif per (rombel, mapel)**; guru pengganti = assignment baru; assignment lama di-soft-delete | satu assignment per (rombel, mapel) selamanya (menimpa = merusak histori); boleh banyak tanpa jaminan |
| D10c | Lifecycle & ruangan | **defer** lifecycle jadwal (`draft/published/cancelled`) dan validasi bentrok `room` | — |
| D11 | `students.grade` | hapus kolom & accessor; field API `grade` tetap (computed) | simpan sebagai turunan |
| D12 | Kronologi tahun | generated `start_year` + `unique` + `CHECK` format | wajibkan `start_date` |
| D13 | Transfer/keluar | status non-aktif menutup enrollment berjalan; aktif lagi butuh penempatan rombel baru | hapus `transferred/dropped` |
| D14 | Kebijakan hapus | `cascade` → `restrict` + SoftDeletes | hanya restrict; status quo |

---

## 4. Invariant (aturan yang harus selalu benar)

1. Tepat satu tahun ajaran `current`; maksimal satu `upcoming` — dijaga DB (generated flag + unique).
2. Maksimal satu enrollment berjalan (`ended_at is null`) per siswa — dijaga DB.
3. Enrollment berjalan hanya boleh ada bila `students.academic_status = active`.
4. Enrollment baru hanya dibuat untuk tahun operable (`current` atau tepat satu tahun berikutnya).
5. Promotion: target tahun = `start_year + 1`; tingkat target = tingkat sumber `+1` (naik kelas) atau **sama** (tinggal kelas).
6. Kelulusan hanya dari tingkat XII: menutup enrollment terakhir, set `graduated_at` dan `graduation_academic_year_id`.
7. Tepat satu assignment **aktif** per (classroom, subject); guru pengganti = assignment baru (assignment lama soft-delete).
8. Guru pada assignment/schedule wajib eligible: `role = guru` + `employee.is_teacher = true` + `employment_status = active` + punya kualifikasi mapel.
9. Kapasitas rombel ≤ `config('classroom.student_capacity')`, ditegakkan dengan row lock saat mutasi.
10. Tahun ajaran sebuah classroom tidak boleh berubah setelah dibuat; assignment tidak menyimpan tahun (diturunkan dari classroom).
11. Setiap record presensi menyimpan konteks akademik saat dicatat (tidak bergantung pada posisi siswa sekarang).
12. Entitas ber-histori tidak boleh hard-delete: FK `restrict` + SoftDeletes.

---

## 5. Perubahan skema

### 5.1 `academic_years`

| Aksi | Detail |
|---|---|
| ubah | `status` enum → `upcoming \| current \| closed` |
| tambah | `start_year` SMALLINT — kolom biasa yang diisi otomatis dari `name` lewat model (`saving`), `unique` |
| tambah | index unik ekspresi: `(CASE WHEN status='current' THEN 1 ELSE NULL END)` dan varian `'upcoming'` → maksimal satu tiap status |
| — | Format nama `YYYY/YYYY` dijaga validasi aplikasi (`regex`), **bukan** `CHECK` DB |

> **Catatan implementasi (deviasi dari rencana awal):** rencana semula memakai kolom *generated stored* dan `CHECK ... REGEXP`. Diganti karena suite test berjalan di SQLite (`:memory:`) yang tidak mendukung `REGEXP` dan sintaks generated column berbeda antar driver. Perilaku yang dijaga tetap sama: kronologi dari `name`, dan DB menolak dua periode `current`/`upcoming` (index unik ekspresi sudah diuji di MySQL **dan** SQLite).

Model: ganti `activeName()/selectableNames()` dengan helper eksplisit `currentYear()`, `nextYear()`, `operableYears()`.

### 5.2 `classrooms`

| Aksi | Detail |
|---|---|
| ubah | `academic_year` varchar → `academic_year_id` FK `academic_years` |
| ubah | constraint unik memakai `academic_year_id` (nama, sesi, wali kelas) |
| ubah | makna `is_active` = "rombel operasional" (boleh dijadwalkan & menerima enrollment); hanya boleh `true` bila tahunnya belum `closed` |
| hapus | perilaku auto-close saat rombel kosong |
| tambah | SoftDeletes |

### 5.3 `students`

| Aksi | Detail |
|---|---|
| hapus | `classroom_id`, `grade` |
| tambah | `entry_academic_year_id` FK nullable, `graduated_at` nullable, `graduation_academic_year_id` FK nullable |
| tambah | SoftDeletes |

### 5.4 `student_enrollments` (baru)

```text
id, student_id FK, academic_year_id FK, classroom_id FK,
started_at date, ended_at date nullable, promotion_batch_id FK nullable,
timestamps, soft delete

unique(student_id, academic_year_id)
generated open_flag = IF(ended_at IS NULL, 1, NULL) + unique(student_id, open_flag)
```

Keputusan desain: **tanpa kolom `status`** (dipakai `ended_at`), **tanpa `level`** (diturunkan dari classroom).

> **Catatan implementasi (deviasi dari rancangan di atas):** `student_enrollments` **tidak** memakai soft delete. Rancangan awal menyebut "soft delete", tetapi dua unique-nya (`unique(student_id, academic_year_id)` dan `one_open`) tidak memakai `deleted_at`, sehingga baris enrollment yang di-soft-delete akan tetap menempati kunci dan memblokir penempatan ulang di tahun yang sama (masalah V yang sama seperti tabel lain). Karena enrollment yang dibatalkan memang harus benar-benar hilang — pembatalan batch promosi menghapus enrollment buatannya — baris enrollment dihapus permanen, dan `ended_at` (bukan `deleted_at`) yang menjadi penanda "tidak lagi berjalan". Guard yang tersisa: `student_id` FK `restrict`, `unique(student_id, academic_year_id)`, dan `student_enrollments_one_open`.

### 5.5 `teaching_assignments` (baru)

```text
id, classroom_id FK, subject_id FK, teacher_id FK, timestamps, deleted_at

unique(classroom_id, subject_id, teacher_id)
generated active_flag = IF(deleted_at IS NULL, 1, NULL)
  + unique(classroom_id, subject_id, active_flag)
```

### 5.6 `schedules`

| Aksi | Detail |
|---|---|
| tambah | `teaching_assignment_id` FK |
| hapus | `classroom_id`, `subject_id`, `teacher_id` |
| tetap | `day_of_week`, `start_time`, `end_time`, `room`, `is_active` |

### 5.7 `attendances`

| Aksi | Detail |
|---|---|
| tambah | `academic_year_id` FK (wajib), `classroom_id` FK (nullable, null untuk staff) |
| ubah | `user_id` FK `cascade` → `restrict` |

### 5.8 `schedule_attendances`

| Aksi | Detail |
|---|---|
| tambah | `student_enrollment_id` FK nullable (`restrict`) |
| ubah | `schedule_id`, `student_id`, `teacher_id` dari `cascade` → `restrict` |
| tetap | `student_id` (dibutuhkan untuk unique `schedule_id + student_id + attendance_date`) |

### 5.9 Soft delete (D14)

Soft delete berlaku pada: `users`, `students`, `employees`, `subjects`, `schedules`, `classrooms`, `teaching_assignments`.

**Konsekuensi penting:** baris yang ter-soft-delete **tetap menempati** unique constraint, sehingga kunci bisnisnya tidak bisa dipakai ulang. Karena itu setiap tabel soft-delete yang punya unique key wajib memakai generated column "aktif" (`IF(deleted_at IS NULL, <kolom>, NULL)`) + unique. Multiple NULL diperbolehkan MySQL, sehingga hanya baris aktif yang dibatasi.

| Tabel | Unique saat ini | Pengganti |
|---|---|---|
| `users` | `email` | `active_email` + unique |
| `students` | `nis`, `nisn` | `active_nis`, `active_nisn` + unique |
| `employees` | `nip` | `active_nip` + unique |
| `subjects` | `code` | `active_code` + unique |
| `classrooms` | `(name, academic_year_id)`, `(level, major, section, academic_year_id)`, `(homeroom_teacher_id, academic_year_id)` | tambahkan flag aktif pada tiap unique |
| `teaching_assignments` | `(classroom_id, subject_id, teacher_id)` | tambahkan flag aktif |
| `schedules` | — | tidak ada unique |

> **Catatan implementasi (deviasi pada `classrooms`):** hanya unique `name` dan `(level, major, section)` yang dibuat ulang dengan flag aktif. Unique `(homeroom_teacher_id, academic_year_id)` **dibiarkan apa adanya** karena MySQL menolak melepas index fungsional yang memuat kolom foreign key ("needed in a foreign key constraint") — versi "aktif"-nya akan membuat skema tidak bisa di-rollback. Sebagai gantinya, slot wali kelas dilepas aplikasi: menonaktifkan **atau** menghapus rombel mengosongkan `homeroom_teacher_id` (`ClassroomService`), dan aturan "satu wali kelas per tahun ajaran" ditegakkan `HomeroomTeacherAvailable` + unique lama yang tetap berlaku untuk baris nonaktif. Karena slotnya selalu dilepas saat rombel nonaktif, baris ter-soft-delete tidak pernah menyandera seorang guru.

Catatan: `academic_years` **tidak** di-soft-delete, sehingga `name` unique tetap aman tanpa perubahan.

### 5.10 Tidak berubah

- `teacher_subjects` tetap = kualifikasi (`is_primary`); tidak ada skema baru.
- `promotion_batches` tetap menyimpan snapshot nama (audit beku); boleh ditambah kolom `*_academic_year_id` bila perlu query.

---

## 6. Aturan turunan penting

### 6.1 Promotion

```text
NAIK KELAS   : target tahun = +1, target tingkat = +1
TINGGAL KELAS: target tahun = +1, target tingkat = sama
LULUS        : sumber tingkat XII, tutup enrollment, academic_status = graduated
PINDAH ROMBEL: bukan promotion; update classroom_id pada enrollment berjalan (cek kapasitas)
REVERT       : tutup/hapus enrollment yang dibuat batch, buka enrollment sebelumnya,
               pulihkan identitas siswa (status/graduated_at/tahun lulus)
```

Kronologis tahun dihitung dari `start_year`, bukan parsing string.

### 6.2 Kelas sekarang & "grade"

- Kelas sekarang siswa = `enrollment` dengan `ended_at is null`.
- Field API `grade` = nama classroom dari enrollment berjalan.
- **Alumni tidak punya enrollment berjalan**, jadi `grade` alumni = classroom dari enrollment terakhir (yang `ended_at` terisi). Ini wajib dijaga agar alumni tetap menampilkan kelas terakhirnya.

### 6.3 Kapasitas

Jumlah terisi = jumlah enrollment berjalan pada classroom tersebut. Saat mengedit siswa yang tetap di rombelnya, pengecekan kapasitas & tahun dilewati.

### 6.4 Eligibility guru (satu rule)

`role = guru` + `employee.is_teacher = true` + `employment_status = active` + punya kualifikasi mapel. Dipakai saat membuat teaching assignment dan schedule.

Bug yang ikut diperbaiki: `EmployeeService` tidak pernah menulis `is_teacher` sehingga selalu `default(true)`.

---

## 7. Penyederhanaan yang muncul

- **Auto-close rombel dihapus (D8)** → `promotion_batches.source_classroom_closed`, pewarisan `homeroom_teacher_id` saat penutupan, dan `reopenSourceClassroom()` **tidak diperlukan lagi**. "Rombel selesai" kini turunan dari tahun `closed`.
- **TeachingAssignment diadopsi (D10a)** → scoping tahun (D6) dibangun sekali; kualifikasi & eligibility guru punya rumah yang jelas.

---

## 8. Detail teknis yang diputuskan oleh spec ini (dapat dikoreksi)

| Hal | Keputusan |
|---|---|
| Konteks presensi siswa | `schedule_attendances.student_enrollment_id` (meng-encode tahun + kelas, imutabel) |
| Konteks presensi gerbang | `attendances.academic_year_id` + `classroom_id` (staff tanpa enrollment → classroom null) |
| `weekly_jp` / target JP | **defer**; JP aktual tetap dihitung dari durasi jadwal |
| Status assignment | tanpa kolom status; "aktif" = tidak soft-deleted |
| Snapshot guru di absensi mapel | `schedule_attendances.teacher_id` tetap disimpan |

---

## 9. Di luar cakupan (dicatat, tidak dikerjakan)

- Dukungan **semester**.
- `CurriculumSubject` + `weekly_jp` (konfigurasi kurikulum per tahun/tingkat/jurusan).
- Lifecycle jadwal `draft/published/cancelled`.
- Validasi bentrok `room`.
- Laporan per angkatan.

---

## 10. Kontrak kompatibilitas

- Endpoint API mobile tidak berubah.
- Field `grade` pada payload siswa tetap ada (nilai computed) → **Mobile tidak perlu diubah**.
- Nama kolom `teacher_subjects` tetap.

---

## 11. Audit konsistensi dokumentasi

Hasil pemeriksaan ulang atas kedua dokumen review dibanding keputusan final.

### 11.1 Kontradiksi antar-dokumen dengan spec final (sudah diselesaikan)

| # | Lokasi | Masalah | Resolusi di spec |
|---|---|---|---|
| A | `academic-year-cohort...` §"Perubahan minimal" | menyarankan `last_classroom_id` **sambil** mempertahankan `classroom_id` → dua pointer ke kelas yang sama (drift baru) | D4: tanpa pointer; `last_classroom_id` tidak dipakai |
| B | `academic-year-cohort...` §"Struktur yang lebih sehat" | `student_enrollments.level` redundan dengan `classroom.level` | D4/§5.4: tanpa `level` |
| C | `academic-year-cohort...` §Kesimpulan vs §"Pemisahan Konsep" | `Cohort` diperlakukan sebagai entitas, tapi relasi yang diusulkan hanya `entry_academic_year_id` | D3: angkatan = grouping turunan, tanpa tabel `cohorts` |
| D | `academic-year-cohort...` §"Struktur yang lebih sehat" | enrollment memakai kolom `status` → duplikasi dengan `ended_at` | §5.4: hanya `ended_at` |
| E | `subject-schedule-teacher...` §8 | menawarkan dua jalur: `schedules.academic_year_id` eksplisit **atau** via `TeachingAssignment` | D10a memilih jalur `TeachingAssignment` → ini **alternatif yang tidak dipilih**, bukan kontradiksi |
| F | `subject-schedule-teacher...` §9 | status jadwal memuat `active` → menumpuk makna dengan `current` tahun ajaran | D10c: lifecycle jadwal di-defer; jangan simpan `active` di jadwal |
| G | `subject-schedule-teacher...` §3 (`TeachingAssignment`) | `academic_year_id` + `classroom_id` sekaligus → duplikasi tahun | D10a: hanya `classroom_id` |
| H | `subject-schedule-teacher...` §3 | menyodorkan dua opsi `unique(...)` tanpa memutuskan | D10b: `unique(classroom, subject, teacher)` + satu aktif per (classroom, subject) |
| I | `subject-schedule-teacher...` §1 & §10 | `CurriculumSubject.weekly_jp` bersaing dengan JP yang dihitung dari durasi jadwal | §9: weekly JP di-defer; JP aktual tetap dari durasi |
| J | `subject-schedule-teacher...` §13 | `student_enrollment_id` pada absensi mapel | §8: diadopsi untuk `schedule_attendances`, sementara presensi gerbang pakai snapshot tahun+kelas (perbedaan disengaja: staff tanpa enrollment) |
| K | `subject-schedule-teacher...` §12 | `ScheduleSeeder` menghapus seluruh jadwal | **Kedaluwarsa** — seeder sudah dihapus pada pembersihan data |

### 11.2 Inkonsistensi internal di dokumen sumber (tidak memengaruhi spec)

| # | Lokasi | Catatan |
|---|---|---|
| L | `subject-schedule-teacher...` §"Struktur Domain Target" | diagram menaruh `Schedules` di bawah `AcademicYear`, padahal `Schedule` tidak memiliki `academic_year_id` (lewat assignment → classroom). Diagram perlu dibaca sebagai konseptual, bukan struktural |
| M | `subject-schedule-teacher...` §"Alur Normal" Tahap 5 | menyebut validasi "ruangan tidak bentrok", padahal §"Rekomendasi" tidak memuat item validasi ruangan | 
| N | `academic-year-cohort...` §Ringkasan vs §"Alur Promotion" | memakai dua skenario tahun berbeda sebagai contoh (`2024/2025` sebagai `current` vs `2023/2024 → 2024/2025`). Bukan konflik logic, hanya ilustrasi yang bisa membingungkan |
| O | `subject-schedule-teacher...` §"Prioritas Refactor" | mengurutkan "quick fix dulu" (konteks jadwal #2, validasi guru #5, kurikulum #7); spec ini mengurutkan menurut ketergantungan domain (enrollment → promotion → assignment → presensi). Tidak bertentangan, hanya urutan berbeda |

Catatan M: item "ruangan tidak bentrok" **tidak** ada di daftar Prioritas, sehingga terlewat; spec ini memasukkannya ke §9 (di luar cakupan).

### 11.3 Celah logic yang ditemukan saat audit spec sendiri

| # | Celah | Penanganan |
|---|---|---|
| P | Field `grade` untuk siswa dengan status non-`active` (alumni/pindah) tidak punya enrollment berjalan → bisa jadi null | §6.2: fallback ke enrollment terakhir |
| Q | Rule `EnrollableClassroom` melewati cek kapasitas bila siswa tetap di rombelnya; dengan D4 "rombel sekarang" harus dibaca dari enrollment berjalan | Dicatat sebagai tugas implementasi Fase 2 |
| R | `classrooms` mengizinkan ubah tahun saat ini (`UpdateClassroomRequest`) → melanggar invariant 10 | Dicatat sebagai tugas implementasi Fase 1 |
| S | `is_teacher` tidak pernah diset → eligibility guru tidak dapat diandalkan | Dicatat sebagai tugas implementasi Fase 4 |
| T | Snapshot `teacher_id` di `schedule_attendances` perlu tetap sinkron saat guru pengganti; nilai diambil saat pencatatan, bukan dari assignment terkini | §8: diambil saat pencatatan |
| U | Absensi gerbang untuk staff memakai `classroom_id` null, sehingga filter per kelas tidak berlaku | Disengaja; dicatat di §8 |
| V | **Soft delete bertabrakan dengan unique constraint**: baris ter-soft-delete tetap menempati kunci bisnis (`nis`, `nip`, `code`, `name+year`, dst) sehingga data baru dengan kunci sama ditolak | Diperbaiki di §5.9: tiap tabel soft-delete memakai generated column "aktif" untuk unique-nya |
| W | Soft delete `classrooms` membuat `Classroom::find()` mengabaikan rombel yang terhapus, sedangkan histori (assignment/enrollment/schedule) masih merujuknya | Dicatat sebagai tugas implementasi Fase 5 (baca histori dengan `withTrashed()` bila perlu) |
| X | Alur hapus user saat ini mencabut token sebelum menghapus; setelah beralih ke soft delete, pencabutan token bisa terlewat sehingga token mobile masih hidup | Dicatat sebagai tugas implementasi Fase 5: alur soft delete user tetap mencabut token |

---

## 12. Referensi kode utama

| Area | Berkas |
|---|---|
| Tahun ajaran | `app/Models/AcademicYear.php`, `app/Services/Web/Academic/AcademicYearService.php` |
| Classroom | `app/Models/Classroom.php`, `app/Services/Web/Academic/ClassroomService.php` |
| Siswa | `app/Models/Student.php`, `app/Services/Web/Student/StudentService.php` |
| Jadwal | `app/Models/Schedule.php`, `app/Services/Web/Academic/ScheduleService.php` |
| API jadwal & absensi | `app/Services/Api/Academic/ScheduleService.php`, `app/Services/Api/Academic/ClassAttendanceService.php` |
| Pegawai | `app/Models/Employee.php`, `app/Services/Web/Employee/EmployeeService.php` |
| Presensi | `app/Services/Web/Attendance/AdminAttendanceService.php`, `app/Http/Controllers/Web/Attendance/AdminAttendanceController.php` |
| Migrasi terkait | `database/migrations/2026_04_07_0837*.php`, `2026_09_20_*.php`, `2026_10_08_*.php` |
