# Review dan Rekomendasi Logic Academic Year, Classroom, Angkatan, dan Promotion

## Ringkasan

Dari hasil pembacaan alur `AcademicYear`, `Classroom`, `Student`, dan `Promotion`, ditemukan masalah konsep pada penggunaan status tahun ajaran.

Saat ini sistem memperlakukan satu `AcademicYear` berstatus `active` sebagai satu-satunya tahun ajaran operasional. Padahal status tahun ajaran sekolah, angkatan masuk siswa, status aktif siswa, dan riwayat enrollment adalah konsep yang berbeda.

Contoh normal:

| Tahun ajaran | Angkatan siswa | Tingkat | Status |
|---|---:|---:|---|
| 2022/2023 | 2022/2023 | X | active |
| 2023/2024 | 2022/2023 | XI | active |
| 2024/2025 | 2022/2023 | XII | active |
| setelah 2024/2025 | 2022/2023 | Alumni | graduated |

Ketika tahun ajaran sekolah berubah dari `2023/2024` ke `2024/2025`, angkatan `2022/2023` tetap aktif karena masih memiliki siswa di kelas XII.

---

## Masalah pada Logic Saat Ini

### 1. Hanya satu `AcademicYear` yang boleh berstatus `active`

`AcademicYearService` menjalankan logic berikut ketika sebuah tahun ajaran diaktifkan:

```php
private function archiveOtherActiveYears(int $exceptId): void
{
    AcademicYear::query()
        ->where('status', AcademicYear::STATUS_ACTIVE)
        ->whereKeyNot($exceptId)
        ->update(['status' => AcademicYear::STATUS_ARCHIVED]);
}
```

Secara kalender sekolah, hanya satu tahun ajaran yang sedang berjalan memang masuk akal. Namun masalah muncul karena status tersebut digunakan terlalu luas untuk menentukan:

- classroom yang boleh digunakan;
- siswa yang dianggap aktif;
- tahun ajaran yang boleh dipilih;
- data yang boleh masuk ke proses promotion;
- data yang dibuat oleh seeder;
- dan data yang dianggap masih valid secara operasional.

`active` pada `AcademicYear` seharusnya berarti **periode kalender sekolah yang sedang berjalan**, bukan berarti hanya siswa dari periode tersebut yang boleh tetap aktif.

### 2. Angkatan masuk siswa belum dimodelkan sebagai data tersendiri

Saat ini siswa hanya menyimpan satu `classroom_id`. Ketika promotion dijalankan, nilai tersebut diganti:

```php
$student->update([
    'classroom_id' => $targetClass->id,
]);
```

Akibatnya, posisi classroom sebelumnya tidak tersedia sebagai histori akademik permanen. `PromotionBatch` hanya menyimpan jejak operasi promotion, bukan riwayat enrollment siswa yang lengkap.

### 3. `StudentSeeder` bergantung pada satu tahun ajaran aktif

Seeder mengambil satu tahun ajaran:

```php
$activeYear = AcademicYear::query()
    ->where('status', AcademicYear::STATUS_ACTIVE)
    ->orderByDesc('name')
    ->first();
```

Kemudian siswa aktif hanya ditempatkan pada classroom tahun tersebut. Model ini tidak merepresentasikan beberapa angkatan yang aktif bersamaan:

```text
Angkatan 2022 → kelas XII pada 2024/2025
Angkatan 2023 → kelas XI pada 2024/2025
Angkatan 2024 → kelas X pada 2024/2025
```

Ketiga angkatan tersebut aktif pada tahun ajaran sekolah yang sama.

### 4. `AcademicYear::archived` berpotensi disalahartikan

Tahun ajaran yang sudah selesai seharusnya tidak dihapus dari konteks akademik. Data tersebut masih dibutuhkan untuk:

- histori siswa;
- histori classroom;
- histori presensi;
- histori jadwal;
- laporan akademik;
- laporan alumni;
- dan audit promotion.

`archived` atau `closed` seharusnya berarti tidak menerima transaksi operasional baru, bukan data dianggap tidak valid atau tidak boleh dirujuk.

### 5. `Classroom` belum memiliki lifecycle yang tegas

Saat ini `Classroom` memakai `is_active`. Namun status tersebut dapat berjalan tidak sinkron dengan status `AcademicYear`.

Contoh kondisi yang mungkin terjadi:

```text
Classroom.is_active = true
AcademicYear.status = archived
```

Selain itu, classroom bisa ditutup ketika seluruh siswa di dalamnya diproses promotion. Hal ini perlu dibedakan antara:

- classroom tidak lagi menerima transaksi baru;
- classroom kosong;
- classroom historis sudah selesai;
- dan classroom dihapus.

### 6. Promotion hanya memindahkan posisi terkini

Proses promotion saat ini mengubah `students.classroom_id`, membuat `PromotionBatch`, dan membuat `PromotionBatchItem`. Namun belum ada entitas enrollment yang secara eksplisit menyimpan:

```text
siswa berada di classroom apa
pada tahun ajaran apa
mulai kapan
selesai kapan
hasil perpindahannya apa
```

### 7. Siswa tinggal kelas belum dimodelkan secara eksplisit

Aturan saat ini cenderung mengharuskan:

```text
X → XI
XI → XII
```

Padahal alur akademik normal juga dapat memiliki:

```text
X 2023/2024 → X 2024/2025
XI 2023/2024 → XI 2024/2025
```

Siswa tetap naik tahun ajaran, tetapi tidak naik tingkat.

---

## Pemisahan Konsep yang Disarankan

Sistem sebaiknya membedakan konsep berikut:

```text
AcademicYear = periode kalender akademik sekolah
Cohort       = angkatan masuk siswa
Student      = identitas siswa sepanjang masa sekolah
Classroom    = rombel pada suatu tahun ajaran
Enrollment   = riwayat siswa berada di classroom pada suatu tahun ajaran
Promotion    = transaksi perpindahan enrollment
```

Relasi yang disarankan:

```text
AcademicYear
    └── Classrooms

Student
    ├── entry_academic_year_id / cohort
    └── StudentEnrollments
            ├── AcademicYear
            └── Classroom
```

Contoh data seorang siswa:

```text
Student:
  name: Ahmad
  entry_academic_year: 2022/2023
  academic_status: active

StudentEnrollments:
  2022/2023 → X-MIPA 1  → ended
  2023/2024 → XI-MIPA 1 → ended
  2024/2025 → XII-MIPA 1 → current
```

---

## Makna Status yang Disarankan

### Status AcademicYear

Status `AcademicYear` dapat tetap dipakai, tetapi maknanya harus dibatasi:

```text
upcoming = belum dimulai
current   = sedang berjalan secara kalender sekolah
closed    = sudah selesai dan tidak menerima transaksi baru
```

Contoh:

| Tahun ajaran | Status |
|---|---|
| 2022/2023 | closed |
| 2023/2024 | closed |
| 2024/2025 | current |
| 2025/2026 | upcoming |

Hanya satu tahun ajaran boleh `current` jika status tersebut berarti kalender sekolah yang sedang berjalan. Namun data siswa dari beberapa angkatan boleh tetap `active` di dalam tahun ajaran `current`.

### Status Student

Status siswa bersifat individual:

```text
active
graduated
transferred
dropped
```

Perubahan tahun ajaran tidak otomatis mengubah siswa menjadi tidak aktif.

### Status Classroom

Sebaiknya `is_active` diluruskan menjadi status yang lebih jelas:

```text
planned = disiapkan untuk tahun ajaran mendatang
active  = digunakan pada periode tersebut
closed  = tidak menerima transaksi baru, tetapi histori tetap tersedia
```

---

## Alur Akademik Normal

### 1. Membuat tahun ajaran baru

Admin membuat tahun ajaran baru sebagai `upcoming`:

```text
2025/2026 → upcoming
```

Sistem dapat menyiapkan classroom untuk kelas X, XI, dan XII.

### 2. Menyiapkan classroom

Contoh:

```text
X-MIPA 1   / 2025/2026
XI-MIPA 1  / 2025/2026
XII-MIPA 1 / 2025/2026
```

Classroom boleh belum memiliki siswa saat masih `planned`.

### 3. Menjalankan transisi tahun ajaran

Misalnya kondisi saat ini adalah `2024/2025`:

```text
Angkatan 2022:
  XII 2024/2025 → lulus

Angkatan 2023:
  XI 2024/2025 → XII 2025/2026

Angkatan 2024:
  X 2024/2025 → XI 2025/2026

Siswa baru:
  masuk ke X 2025/2026
```

### 4. Mengaktifkan tahun ajaran baru

Setelah proses transisi selesai:

```text
2025/2026 → current
2024/2025 → closed
```

Namun data berikut tetap tersedia:

```text
siswa dari angkatan lama
classroom lama
student enrollment lama
attendance lama
promotion batch lama
```

### 5. Menyelesaikan angkatan

Saat siswa menyelesaikan kelas XII:

```text
academic_status = graduated
graduated_at = tanggal kelulusan
graduation_academic_year_id = tahun ajaran kelulusan
```

Enrollment terakhir juga ditutup sehingga riwayat kelas XII tetap dapat diketahui.

---

## Alur Promotion yang Disarankan

### Sebelum promotion

```text
AcademicYear current: 2023/2024

Siswa: Ahmad
Angkatan masuk: 2022/2023
Current classroom: XI-MIPA 1
Current enrollment: 2023/2024
```

### Saat promotion

Admin memilih:

```text
source academic year: 2023/2024
source classroom: XI-MIPA 1
target academic year: 2024/2025
target classroom: XII-MIPA 1
```

Sistem melakukan:

1. Memastikan enrollment siswa pada source masih aktif.
2. Memastikan target classroom berasal dari tahun ajaran yang valid.
3. Menutup enrollment lama.
4. Membuat enrollment baru.
5. Memperbarui posisi terkini siswa.
6. Membuat `PromotionBatch`.
7. Membuat `PromotionBatchItem`.
8. Mengirim notifikasi.

Hasilnya:

```text
student_enrollments:
- 2023/2024 → XI-MIPA 1 → ended
- 2024/2025 → XII-MIPA 1 → current
```

### Aturan promotion

Untuk kenaikan kelas:

```text
target academic year = tahun ajaran berikutnya
 target level = source level + 1
```

Contoh valid:

```text
2022/2023 X  → 2023/2024 XI
2023/2024 XI → 2024/2025 XII
```

Untuk kelulusan:

```text
source level harus XII
student status menjadi graduated
tidak ada target classroom
```

Untuk tinggal kelas:

```text
2023/2024 X → 2024/2025 X
```

Siswa tetap aktif, tetapi level tidak berubah.

---

## Struktur Database yang Disarankan

### Perubahan minimal

Jika belum siap melakukan refactor besar, tambahkan pada `students`:

```text
entry_academic_year_id
graduation_academic_year_id
graduated_at
last_classroom_id
```

Kemudian:

- `AcademicYear` tetap boleh memiliki satu status `current` secara kalender.
- Tahun ajaran lama tetap bisa dirujuk untuk histori.
- Siswa tetap `active` lintas tahun ajaran sampai lulus.
- Promotion mengubah posisi terkini siswa tanpa menghapus informasi angkatan.

Pendekatan ini masih belum menyediakan histori enrollment lengkap.

### Struktur yang lebih sehat

Buat tabel `student_enrollments`:

```text
id
student_id
academic_year_id
classroom_id
level
status
started_at
ended_at
promotion_batch_id
created_at
updated_at
```

Constraint yang disarankan:

```text
unique(student_id, academic_year_id)
```

Tambahkan pada `students`:

```text
entry_academic_year_id
current_classroom_id atau current_enrollment_id
academic_status
graduated_at
graduation_academic_year_id
```

Dengan model tersebut:

```text
Student       = identitas permanen
Enrollment    = histori akademik
Classroom     = rombel per periode
AcademicYear  = periode kalender
Promotion     = transaksi perubahan enrollment
```

---

## Rekomendasi Perubahan Logic di Repository

### `AcademicYearService`

- Pertahankan satu `current` hanya jika itu memang kalender sekolah saat ini.
- Pertimbangkan mengganti nama `archived` menjadi `closed` agar maknanya lebih jelas.
- Jangan gunakan status `current` untuk menentukan apakah siswa masih aktif.
- Jangan menghapus atau menyembunyikan histori tahun ajaran lama.
- Pisahkan query untuk kebutuhan operasional dan histori.

`AcademicYear::selectableNames()` hanya digunakan untuk:

- membuat classroom baru;
- membuat enrollment baru;
- transaksi operasional yang memang membutuhkan periode aktif atau mendatang.

Jangan gunakan method tersebut untuk menentukan apakah histori atau siswa masih valid.

### `ClassroomService`

Pisahkan method berdasarkan kebutuhan:

```text
getOperationalClassrooms()
getHistoricalClassrooms()
getPromotionSourceClassrooms()
getPromotionTargetClassrooms()
```

Contoh aturan:

```text
promotion source:
  classroom pada current year yang memiliki enrollment aktif

promotion target:
  classroom pada academic year berikutnya yang planned atau active

history:
  semua classroom tanpa filter current year
```

### `StudentSeeder`

Seeder sebaiknya mampu membuat beberapa angkatan aktif secara bersamaan:

```text
angkatan 2022 → kelas XII pada 2024/2025
angkatan 2023 → kelas XI pada 2024/2025
angkatan 2024 → kelas X pada 2024/2025
```

Satu `active academic year` boleh tetap menjadi periode sekolah saat ini, tetapi bukan berarti hanya satu angkatan yang boleh aktif.

### `Promotion`

Promotion sebaiknya menyimpan referensi eksplisit:

```text
source_academic_year_id
target_academic_year_id
source_classroom_id
target_classroom_id
```

Perbandingan tahun ajaran sebaiknya menggunakan nilai numerik atau relasi, bukan hanya perbandingan string:

```text
source.start_year = 2023
target.start_year = 2024
target.start_year = source.start_year + 1
```

---

## Alur Target Akhir

```text
1. Buat semua AcademicYear:
   2022/2023, 2023/2024, 2024/2025, 2025/2026

2. Tandai satu periode kalender sebagai current:
   2024/2025 = current

3. Siapkan classroom untuk setiap tahun ajaran.

4. Simpan entry/cohort year pada setiap siswa.

5. Buat enrollment siswa pada setiap tahun ajaran:
   2022/2023 → X
   2023/2024 → XI
   2024/2025 → XII

6. Saat naik kelas:
   tutup enrollment lama;
   buat enrollment baru;
   simpan promotion batch.

7. Saat lulus:
   tutup enrollment terakhir;
   ubah status siswa menjadi graduated;
   simpan tahun kelulusan.

8. Saat tahun ajaran berganti:
   periode lama menjadi closed;
   periode baru menjadi current;
   histori dan angkatan tetap tersedia.
```

---

## Kesimpulan

Model yang benar adalah:

```text
Satu tahun ajaran boleh menjadi current secara kalender sekolah.
Banyak angkatan dan banyak siswa boleh tetap active di dalam sistem.
```

Kesalahan utama pada logic saat ini adalah penggunaan `AcademicYear::STATUS_ACTIVE` terlalu luas. Status tersebut seharusnya hanya menjawab pertanyaan:

> Tahun ajaran sekolah mana yang sedang berjalan sekarang?

Status tersebut tidak boleh menjawab pertanyaan:

> Apakah siswa dari angkatan tertentu masih aktif?

Domain akhir yang disarankan:

```text
AcademicYear = periode sekolah
Cohort       = angkatan masuk
Classroom    = rombel per periode
Student      = identitas siswa
Enrollment   = histori posisi siswa per periode
Promotion    = transaksi perpindahan siswa antar enrollment
```
