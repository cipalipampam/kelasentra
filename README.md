<div align="center">

# 🟢 Kelasentra

### Sistem Operasi Akademik Sekolah Berbasis Web & Mobile

<p>
    <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
    <img src="https://img.shields.io/badge/PHP-8.5%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.5+">
    <img src="https://img.shields.io/badge/Flutter-3.x-02569B?logo=flutter&logoColor=white" alt="Flutter">
    <img src="https://img.shields.io/badge/Dart-3.7%2B-0175C2?logo=dart&logoColor=white" alt="Dart">
    <img src="https://img.shields.io/badge/MySQL%20%7C%20SQLite-Database-4479A1?logo=mysql&logoColor=white" alt="Database">
    <img src="https://img.shields.io/badge/Sanctum-API%20Auth-FF2D20?logo=laravel&logoColor=white" alt="Laravel Sanctum">
    <img src="https://img.shields.io/badge/Reverb-WebSocket-6C63FF?logo=websocket&logoColor=white" alt="Laravel Reverb">
    <img src="https://img.shields.io/badge/Vite-6.x-646CFF?logo=vite&logoColor=white" alt="Vite">
</p>

<p><strong>Platform operasional akademik untuk mengelola data sekolah, jadwal, kehadiran, dan komunikasi secara terintegrasi.</strong></p>

<p>
    📍 <strong>Geofencing</strong> &nbsp;•&nbsp;
    📸 <strong>Bukti Foto</strong> &nbsp;•&nbsp;
    ⚡ <strong>Real-time</strong> &nbsp;•&nbsp;
    📊 <strong>Dashboard Admin</strong>
</p>

<p>
    <a href="#-fitur">✨ Fitur Utama</a> •
    <a href="#-teknologi">🛠️ Tech Stack</a> •
    <a href="#-arsitektur-direktori">🗂️ Arsitektur</a> •
    <a href="#-instalasi-dan-menjalankan-backend">⚙️ Backend</a> •
    <a href="#-instalasi-dan-menjalankan-mobile">📱 Mobile</a> •
    <a href="#-api-mobile">🔌 API</a> •
    <a href="#-pengujian">🧪 Testing</a>
</p>

</div>

---

## 📖 Gambaran Sistem

**Kelasentra** adalah sistem operasi akademik sekolah yang menghubungkan panel administrasi web dengan aplikasi mobile pengguna. Sistem ini menangani data siswa dan guru, rombel, mata pelajaran, jadwal, presensi harian maupun per sesi, pengajuan izin/sakit, laporan, pengumuman, dan pembaruan informasi melalui WebSocket.

> 💡 **Pusat operasional akademik sekolah.**

Kelasentra terdiri dari dua aplikasi yang memakai backend yang sama:

| Komponen | Peran | Teknologi |
| --- | --- | --- |
| `Web/` | REST API untuk mobile dan panel admin | Laravel 12, PHP 8.5+, Blade, Bootstrap, Vite |
| `Mobile/` | Aplikasi akademik dan presensi untuk siswa, guru, dan staff | Flutter, Dart, Provider |

Autentikasi mobile menggunakan Laravel Sanctum dengan bearer token. Panel admin memakai autentikasi session dan hanya dapat diakses oleh role `admin`. Data utama disimpan dalam database relasional, sementara file bukti presensi disimpan pada disk `public`.

## ✨ Fitur

### 🖥️ Panel Admin Web

- Dashboard statistik presensi.
- CRUD siswa beserta NIS, NISN, kelas, kontak, dan profil.
- CRUD guru dan staff beserta NIP, jabatan, kontak, dan profil.
- Melihat, menambah, mengubah, dan menghapus catatan presensi.
- Filter laporan berdasarkan nama, tanggal, bulan, tahun, role, dan kelas.
- Menyetujui atau menolak pengajuan izin/sakit.
- Ekspor laporan ke Excel (`.xlsx`), CSV, PDF, atau ZIP berisi seluruh format.
- Mengelola pengumuman yang diterima aplikasi mobile.
- Mengatur lokasi sekolah, radius presensi, jam masuk, toleransi keterlambatan, dan jam pulang.
- Pembaruan statistik dan data tertentu secara real-time melalui Laravel Reverb.

### 📱 Aplikasi Mobile

- Login, logout, dan pemulihan sesi lokal.
- Dashboard status presensi, jadwal, statistik pribadi, dan pengumuman.
- Check-in dengan koordinat GPS dan validasi radius sekolah.
- Penandaan terlambat berdasarkan jadwal dan toleransi yang dikonfigurasi admin.
- Check-out setelah check-in berhasil.
- Pengajuan izin atau sakit dengan keterangan dan foto bukti.
- Riwayat presensi berdasarkan bulan dan tahun.
- Notifikasi pembaruan pengajuan secara real-time pada channel pengguna.
- Profil dinamis untuk siswa, guru, dan staff.
- Antarmuka dark theme dengan komponen glassmorphism dan animasi.

## 🛠️ Teknologi

### 🧱 Backend dan Web

- Laravel `^12.0` dan PHP `^8.5`.
- Laravel Sanctum `^4.1` untuk token API.
- Laravel Reverb `^1.10` dan Pusher protocol untuk WebSocket.
- Spatie Laravel Permission `^7.2` untuk role dan permission.
- SQLite sebagai default development; MySQL, MariaDB, PostgreSQL, dan SQL Server tersedia pada konfigurasi database.
- Vite `^6.2.4`, Tailwind CSS `^4.0.0`, Bootstrap 5.3, dan Bootstrap Icons.
- DomPDF untuk PDF dan SimpleXLSXGen untuk Excel.

### 📲 Mobile

- Flutter dengan Dart SDK `^3.7.0`.
- Provider untuk state management.
- Dio untuk request HTTP dan interceptor autentikasi.
- Geolocator dan Flutter Map untuk lokasi.
- Image Picker untuk bukti foto.
- `dart_pusher_channels` untuk koneksi Reverb/WebSocket.
- Shared Preferences untuk menyimpan token dan data sesi.
- Flutter Animate, FL Chart, Intl, dan Cached Network Image untuk pengalaman aplikasi.

## 🗂️ Arsitektur Direktori

```text
Kelasentra/
├── README.md
├── Web/                         # Laravel API, admin portal, dan database
│   ├── app/
│   │   ├── Http/                # Controller, request, dan middleware
│   │   ├── Models/              # User, Student, Employee, Attendance, Setting
│   │   ├── Services/            # Logika bisnis API dan web
│   │   └── Events/              # Event broadcast real-time
│   ├── database/                # Migration, factory, dan seeder
│   ├── resources/views/         # Blade admin portal
│   ├── routes/api.php           # API mobile dengan prefix /api/v1
│   ├── routes/web.php           # Route panel admin
│   └── public/                  # Asset dan storage link
└── Mobile/                      # Aplikasi Flutter
    ├── lib/core/                # Konstanta, client API, WebSocket, widget
    └── lib/features/            # Auth, dashboard, attendance, profile
```

## 💻 Prasyarat

Pastikan perangkat pengembangan memiliki:

- PHP 8.5 atau versi yang kompatibel dengan `composer.json`.
- Composer dan ekstensi PHP database yang sesuai.
- Node.js dan npm.
- Flutter SDK dengan Dart 3.7 atau lebih baru.
- Android Studio/emulator atau perangkat Android fisik. Xcode diperlukan untuk build iOS di macOS.
- Database SQLite untuk setup paling sederhana, atau MySQL/PostgreSQL untuk environment bersama.
- Ekstensi PHP `zip` jika ingin memakai ekspor ZIP.

## ⚙️ Instalasi dan Menjalankan Backend

Jalankan perintah berikut dari direktori `Web/`.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Pada Windows PowerShell, gunakan perintah berikut untuk menyalin environment:

```powershell
Copy-Item .env.example .env
```

### 🗄️ Database

Konfigurasi yang digunakan pada environment ini adalah MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Buat database sesuai nama yang Anda pilih di MySQL. Jangan masukkan password database asli ke README atau repository. Jika ingin memakai SQLite, gunakan konfigurasi alternatif berikut:

```powershell
New-Item database/database.sqlite -ItemType File
```

Kemudian jalankan migration dan seeder:

```bash
php artisan migrate --seed
php artisan storage:link
```

Seeder menyiapkan dataset demo lengkap: peran, akun admin, pengaturan sekolah, kronologi tahun ajaran, rombel beserta wali kelas, mata pelajaran, pegawai, siswa dengan riwayat penempatan, jadwal, presensi, pengumuman, dan notifikasi. Seluruhnya dihasilkan seeder sehingga `migrate:fresh --seed` langsung menghasilkan aplikasi yang terisi. Pada akhir proses, seeder mencetak ringkasan jumlah data dan kredensial akun demo di terminal.

Untuk database lain, ubah `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `Web/.env` sebelum menjalankan migration.

### ▶️ Menjalankan Layanan

Buka terminal terpisah dari direktori `Web/`:

```bash
# Terminal 1: Laravel API dan panel admin
# Host dan port mengikuti SERVER_HOST/SERVER_PORT pada Web/.env.
php artisan serve

# Terminal 2: Vite untuk asset frontend
npm run dev

# Terminal 3: Laravel Reverb WebSocket
php artisan reverb:start

# Terminal 4: worker untuk broadcast dan pekerjaan latar belakang
php artisan queue:work
```

Untuk laptop lokal, panel admin tersedia di `http://127.0.0.1:8000/admin/login`. Dari emulator/perangkat eksternal, gunakan `http://YOUR_LAN_IP:8000/admin/login`.

Untuk workflow pengembangan Laravel yang sudah didefinisikan di `composer.json`, perintah berikut menjalankan server, queue listener, log viewer, dan Vite secara bersamaan:

```bash
composer run dev
```

## 📱 Instalasi dan Menjalankan Mobile

Jalankan dari direktori `Mobile/`:

```bash
flutter pub get
flutter doctor
flutter devices
flutter run
```

Konfigurasi development saat ini memakai `192.168.0.104` sebagai default `BACKEND_HOST`, sehingga perangkat fisik pada jaringan yang sama cukup menjalankan `flutter run`. Laravel dan Reverb memakai host/port dari `Web/.env`, sehingga cukup jalankan `php artisan serve` dan `php artisan reverb:start`.

Jika jaringan berubah, override sekali saat menjalankan Flutter tanpa mengubah source code:

```powershell
flutter run --dart-define=BACKEND_HOST=YOUR_LAN_IP
```

Gunakan nilai berikut sesuai target:

| Target | `baseUrl` host |
| --- | --- |
| Android Studio Emulator | `10.0.2.2` |
| Genymotion | `10.0.3.2` |
| Emulator eksternal/perangkat fisik | IPv4 komputer, misalnya `YOUR_LAN_IP` |
| iOS Simulator | `127.0.0.1` |
| Server production | Domain atau IP publik dengan HTTPS/WSS |

Windows Firewall harus mengizinkan port `8000` dan `8080`. Android development saat ini mengizinkan HTTP cleartext, tetapi production sebaiknya memakai HTTPS dan WSS.

## 👤 Data Demo

`DatabaseSeeder` membuat dataset demo lengkap agar setiap layar punya isi:

| Kelompok | Isi |
| --- | --- |
| Akun & peran | 4 role, 1 admin, 15 pegawai (13 guru, 2 staff), 98 siswa |
| Kronologi | 5 tahun ajaran (2023/2024 s.d. 2027/2028), 2026/2027 berjalan |
| Akademik | 11 mata pelajaran, 18 rombel + wali kelas, 194 riwayat penempatan |
| Mengajar | 66 penugasan, 120 slot jadwal (40 JP/minggu per rombel) |
| Presensi | ±1.200 presensi gerbang (termasuk periode lama) & 288 presensi mapel |
| Lain-lain | 10 batch riwayat kenaikan kelas/kelulusan, pengumuman, dan notifikasi |

Riwayat kenaikan kelas dibentuk lewat proses promosi asli, dan presensi menyimpan konteks akademik saat dicatat, sehingga data demo konsisten dengan aturan domain. Data ini untuk pengembangan; **jangan dipakai di deployment**. Kredensial akun demo tidak ditulis di dokumen ini, melainkan dicetak oleh seeder di terminal (akun admin, guru, staff, dan siswa dengan kata sandi bawaan masing-masing) — ganti semuanya sebelum dipakai di lingkungan nyata.

## 🔌 API Mobile

Base URL: `http://ALAMAT_BACKEND:8000/api/v1`

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| `POST` | `/login` | Tidak | Login dan memperoleh token Sanctum |
| `POST` | `/logout` | Sanctum | Menghapus sesi/token server |
| `GET` | `/user` | Sanctum | Mengambil profil pengguna aktif |
| `GET` | `/dashboard` | Sanctum | Jadwal, statistik, dan pengumuman |
| `GET` | `/settings/location` | Sanctum | Mengambil konfigurasi lokasi |
| `GET` | `/attendances` | Sanctum | Riwayat presensi, mendukung `month` dan `year` |
| `POST` | `/attendances/check-in` | Sanctum | Presensi masuk dengan latitude, longitude, dan bukti opsional |
| `POST` | `/attendances/check-out` | Sanctum | Presensi pulang |
| `POST` | `/attendances/permission` | Sanctum | Pengajuan izin/sakit dengan keterangan dan bukti |

Request yang memuat file dikirim sebagai `multipart/form-data`. Token dikirim melalui header:

```http
Authorization: Bearer <SANCTUM_TOKEN>
Accept: application/json
```

## 🔄 Alur Presensi

1. Pengguna login dan token disimpan secara lokal.
2. Aplikasi mengambil koordinat perangkat melalui GPS.
3. Backend membandingkan koordinat dengan `school_lat`, `school_long`, dan `school_radius` menggunakan perhitungan Haversine.
4. Backend memeriksa jadwal, toleransi keterlambatan, dan apakah pengguna sudah presensi hari itu.
5. Check-in disimpan dengan status `present`; check-out hanya dapat dilakukan setelah check-in.
6. Untuk izin/sakit, pengguna mengirim status, keterangan, dan foto bukti. Pengajuan menunggu persetujuan admin.
7. Perubahan presensi, pengumuman, pengaturan, dan data master dipancarkan melalui private channel sesuai hak akses. Payload WebSocket hanya berupa sinyal perubahan; aplikasi mengambil ulang data dari endpoint yang berizin.

Konfigurasi awal seeder menggunakan radius 100 meter, jam check-in `06:00`-`07:00`, toleransi terlambat 15 menit, dan jam check-out `15:00`-`17:00`. Nilai ini dapat diubah dari menu pengaturan admin.

## 🔧 Konfigurasi Penting

### 🌐 Environment Laravel

Nilai penting pada `Web/.env` meliputi:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-reverb-app-id
REVERB_APP_KEY=your-reverb-key
REVERB_APP_SECRET=your-reverb-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
VITE_REVERB_HOST=
QUEUE_CONNECTION=database
```

`REVERB_HOST=127.0.0.1` digunakan Laravel untuk mengirim broadcast ke Reverb di laptop, sedangkan `REVERB_SERVER_HOST=0.0.0.0` membuat server menerima koneksi dari jaringan. Aplikasi mobile sudah memakai key default `local-key`, sama dengan `.env` lokal. Gunakan `QUEUE_CONNECTION=database` dan jalankan `php artisan queue:work` agar broadcast tidak menambah waktu respons presensi. Simpan nilai asli hanya di file `.env` lokal atau secret manager.

### Realtime channel

| Channel private | Penerima | Perilaku |
| --- | --- | --- |
| `admin.attendance` | Admin | Daftar presensi refresh hanya untuk scope siswa atau employee yang relevan. |
| `admin.dashboard` | Admin | Statistik dashboard, termasuk jumlah izin/sakit yang menunggu keputusan, diperbarui. |
| `admin.directory` | Admin | Daftar siswa atau employee admin lain diperbarui. |
| `announcements` | Pengguna login | Dashboard mobile dan daftar pengumuman admin memuat ulang data. |
| `settings` | Pengguna login | Mobile memuat ulang lokasi/jam; admin lain menyegarkan settings bila form belum diubah. |
| `App.Models.User.{id}` | Pengguna terkait | Keputusan izin/sakit (beserta notifikasi saat aplikasi terbuka) serta invalidasi sesi akun. |

Saat development, jalankan pada terminal terpisah:

```powershell
php artisan serve
php artisan reverb:start
php artisan queue:work
```

### 📍 Permission Perangkat

Mobile memerlukan internet, lokasi, kamera, dan akses galeri untuk fitur terkait. Permission Android dan iOS sudah dideklarasikan pada manifest/platform configuration, tetapi pengguna tetap harus memberikan izin saat runtime.

### 📦 Storage

Foto bukti presensi baru disimpan pada disk privat dan diakses melalui URL bertanda tangan sementara, sehingga tidak memerlukan `storage:link`. File lama pada disk public tetap didukung sebagai kompatibilitas. Jangan menyimpan kredensial production atau secret Reverb di repository.

## 📚 Dokumentasi Domain

Aturan akademik (tahun ajaran, enrollment siswa, kenaikan kelas, penugasan mengajar, dan presensi) beserta invariant-nya didokumentasikan di:

- [`docs/academic-domain-spec.md`](docs/academic-domain-spec.md) — spesifikasi final: keputusan desain (D1–D14), daftar invariant, dan perubahan skema.
- [`docs/refactor-task-plan.md`](docs/refactor-task-plan.md) — rencana kerja bertahap beserta status tiap tugas.

Baca dokumen tersebut sebelum mengubah perilaku domain akademik.

## 🧪 Pengujian

### 🧰 Backend

```bash
cd Web
php artisan test
```

Perintah `composer test` juga tersedia dan menjalankan `php artisan test` setelah membersihkan konfigurasi.

### 📱 Mobile

```bash
cd Mobile
flutter analyze
flutter test
```

Widget test saat ini memverifikasi splash screen. Skenario login, geolocation, upload bukti, dan WebSocket tetap perlu diuji pada emulator/perangkat nyata.

## 🩹 Troubleshooting

### 📡 Mobile Tidak Dapat Terhubung ke Backend

- Jangan memakai `127.0.0.1` dari emulator eksternal atau perangkat fisik; gunakan IPv4 komputer.
- Android Studio Emulator memakai `10.0.2.2`; Genymotion memakai `10.0.3.2`.
- Jalankan Laravel dengan `php artisan serve`.
- Pastikan firewall mengizinkan port `8000`.
- Pastikan perangkat dan komputer berada pada jaringan yang sama.

### 🔌 WebSocket Tidak Tersambung

- Jalankan `php artisan reverb:start`.
- Pastikan `BROADCAST_CONNECTION` backend menggunakan `reverb`.
- Samakan `REVERB_APP_KEY` backend dengan default `local-key` aplikasi mobile, atau override saat diperlukan.
- Jika IP laptop berubah, override `BACKEND_HOST` saat menjalankan Flutter.
- Private channel broadcast mendukung session admin web serta token Sanctum mobile; login harus berhasil terlebih dahulu.
- Pastikan firewall mengizinkan port `8080`.
- Untuk production, gunakan konfigurasi TLS/WSS dan reverse proxy yang benar.

### 🖼️ Foto atau Bukti Tidak Tampil

- Jalankan `php artisan storage:link`.
- Pastikan `FILESYSTEM_DISK=public` atau disk upload yang dipakai sudah dikonfigurasi.
- Periksa permission kamera/galeri pada perangkat.

### 🔑 Token Dianggap Tidak Valid

Backend akan merespons `401` ketika token kedaluwarsa atau dicabut. Aplikasi mobile otomatis menghapus token lokal dan mengarahkan pengguna kembali ke login.

## 🔐 Catatan Keamanan

- Jangan gunakan akun demo dan secret development di production.
- Gunakan HTTPS untuk API serta WSS untuk WebSocket.
- Batasi CORS, aktifkan queue yang sesuai, dan gunakan cache/config hasil build untuk deployment.
- Validasi file upload, ukuran file, dan akses storage sebelum membuka aplikasi ke publik.
- Backup database dan file bukti secara berkala.
- Tinjau ulang role, permission, dan route admin sebelum deployment.

## 🤝 Lisensi dan Kontribusi

Kelasentra adalah project aplikasi pada repository ini. Aturan lisensi dan kontribusi dapat ditambahkan sesuai kebijakan pemilik project. Untuk pengembangan, pisahkan perubahan backend dan mobile dengan jelas, jalankan test yang relevan, lalu dokumentasikan perubahan konfigurasi atau endpoint baru.
