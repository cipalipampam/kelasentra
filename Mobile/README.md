# Kelasentra Mobile

Aplikasi mobile Kelasentra untuk siswa, guru, dan staff sekolah. Aplikasi ini terhubung ke backend Laravel pada direktori `../Web` untuk akses jadwal, presensi, notifikasi, profil, dan informasi akademik.

## Menjalankan aplikasi

```bash
flutter pub get
flutter run
```

Untuk perangkat fisik atau emulator eksternal, atur alamat backend dengan `BACKEND_HOST`:

```bash
flutter run --dart-define=BACKEND_HOST=YOUR_LAN_IP
```

## Konfigurasi Transport & Build Release

Aplikasi menggunakan HTTPS dan WSS secara otomatis pada mode release untuk melindungi token autentikasi Sanctum dan data presensi.

### Daftar Environment Defines (`--dart-define`)

| Variable | Default (Debug) | Default (Release) | Deskripsi |
|---|---|---|---|
| `BACKEND_HOST` | `192.168.0.104` | `api.kelasentra.sch.id` | Hostname backend API & Reverb |
| `API_BASE_URL` | *(kosong / turunan)* | *(kosong / turunan)* | Override penuh URL base API (`https://.../api/v1`) |
| `USE_HTTPS` | `false` | `true` | Paksa scheme `https` & `wss` |
| `API_PORT` | `8000` | `443` | Port HTTP backend |
| `REVERB_APP_KEY` | `local-key` | `local-key` | App key Reverb WebSocket |
| `REVERB_HOST` | `$BACKEND_HOST` | `$BACKEND_HOST` | Host WebSocket |
| `REVERB_SCHEME` | `ws` | `wss` | Scheme WebSocket (`ws` atau `wss`) |
| `REVERB_PORT` | `8080` | `443` | Port WebSocket |

### Perintah Build Release

```bash
flutter build apk --release \
  --dart-define=BACKEND_HOST=api.sekolah.sch.id \
  --dart-define=REVERB_APP_KEY=your-production-reverb-key
```

Lihat README di root repository untuk konfigurasi backend dan detail arsitektur.
