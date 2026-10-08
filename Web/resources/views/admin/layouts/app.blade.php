<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Portal - Kelasentra</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body>
    <!-- ===================== SIDEBAR ===================== -->
    <aside class="sidebar d-flex flex-column" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo-full">
                <div class="sidebar-logo-icon">PS</div>
                <div class="d-flex flex-column overflow-hidden sidebar-logo-text">
                    <span class="fs-6 fw-bold text-white" style="letter-spacing: -0.3px;">Kelasentra</span>
                    <span class="text-white-50 small" style="font-size: 0.68rem; margin-top: -3px;">Enterprise EdTech</span>
                </div>
            </div>
            <button id="toggleSidebar" class="btn p-1 border-0 bg-transparent text-white-50 ms-auto" style="font-size:1.25rem;" title="Toggle sidebar">
                <i class="bi bi-layout-sidebar-reverse"></i>
            </button>
        </div>

        <div class="py-3 grow overflow-y-auto">

            {{-- ==================== UTAMA ==================== --}}
            <div class="sidebar-section">
                <p class="sidebar-section-header text-white-50">Utama</p>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}"
                            class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Dashboard">
                            <i class="bi bi-grid-1x2-fill"></i>
                            <span class="link-text">Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- ==================== AKADEMIK ==================== --}}
            <div class="sidebar-section">
                <p class="sidebar-section-header text-white-50">Akademik</p>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a href="{{ route('admin.classrooms.index') }}"
                            class="nav-link {{ request()->routeIs('admin.classrooms.*') ? 'active' : '' }}" title="Data Rombel & Kelas">
                            <i class="bi bi-diagram-3-fill"></i>
                            <span class="link-text">Rombel & Kelas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.subjects.index') }}"
                            class="nav-link {{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}" title="Mata Pelajaran">
                            <i class="bi bi-journal-bookmark-fill"></i>
                            <span class="link-text">Mata Pelajaran</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.schedules.index') }}"
                            class="nav-link {{ request()->routeIs('admin.schedules.*') ? 'active' : '' }}" title="Jadwal Pelajaran">
                            <i class="bi bi-calendar3"></i>
                            <span class="link-text">Jadwal Pelajaran</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- ============ KESISWAAN & KEPEGAWAIAN ============ --}}
            <div class="sidebar-section">
                <p class="sidebar-section-header text-white-50">Kesiswaan & Kepegawaian</p>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a href="{{ route('admin.students.index') }}"
                            class="nav-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" title="Data Siswa">
                            <i class="bi bi-mortarboard-fill"></i>
                            <span class="link-text">Data Siswa</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.employees.index') }}"
                            class="nav-link {{ request()->routeIs('admin.employees.*') ? 'active' : '' }}" title="Data Karyawan & Guru">
                            <i class="bi bi-person-badge-fill"></i>
                            <span class="link-text">Guru & Karyawan</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- ==================== PRESENSI ==================== --}}
            <div class="sidebar-section">
                <p class="sidebar-section-header text-white-50">Presensi</p>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a href="{{ route('admin.attendances.students') }}"
                            class="nav-link {{ request()->routeIs('admin.attendances.students') ? 'active' : '' }}" title="Presensi Siswa">
                            <i class="bi bi-calendar-check-fill"></i>
                            <span class="link-text">Presensi Siswa</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.attendances.employees') }}"
                            class="nav-link {{ request()->routeIs('admin.attendances.employees') ? 'active' : '' }}" title="Presensi Karyawan">
                            <i class="bi bi-calendar2-check-fill"></i>
                            <span class="link-text">Presensi Guru & Staff</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- ==================== KOMUNIKASI ==================== --}}
            <div class="sidebar-section">
                <p class="sidebar-section-header text-white-50">Komunikasi</p>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a href="{{ route('admin.announcements.index') }}"
                            class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" title="Pengumuman">
                            <i class="bi bi-megaphone-fill"></i>
                            <span class="link-text">Pengumuman</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- ==================== KONFIGURASI ==================== --}}
            <div class="sidebar-section">
                <p class="sidebar-section-header text-white-50">Konfigurasi</p>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a href="{{ route('admin.settings.index') }}"
                            class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" title="Pengaturan Sistem">
                            <i class="bi bi-gear-fill"></i>
                            <span class="link-text">Pengaturan Sistem</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Hidden logout form -->
        <form action="{{ route('logout') }}" method="POST" id="logout-form">
            @csrf
        </form>
    </aside>

    <!-- ===================== NAVBAR ===================== -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <!-- Breadcrumbs / Page context -->
            <div class="d-flex align-items-center gap-2 nav-breadcrumb">
                <a href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-house-door me-1"></i>Home
                </a>
                @if(!request()->routeIs('admin.dashboard'))
                    <span class="separator">/</span>
                    <span class="current">
                        @if(request()->routeIs('admin.students.*')) Data Siswa
                        @elseif(request()->routeIs('admin.employees.*')) Guru & Karyawan
                        @elseif(request()->routeIs('admin.classrooms.promotion*'))
                            <a href="{{ route('admin.classrooms.index') }}" class="text-decoration-none text-muted">Rombel & Kelas</a>
                            <span class="separator">/</span>
                            <span>Kenaikan Kelas</span>
                        @elseif(request()->routeIs('admin.classrooms.*')) Rombel & Kelas
                        @elseif(request()->routeIs('admin.subjects.*')) Mata Pelajaran
                        @elseif(request()->routeIs('admin.schedules.*')) Jadwal Pelajaran
                        @elseif(request()->routeIs('admin.attendances.students')) Presensi Siswa
                        @elseif(request()->routeIs('admin.attendances.employees')) Presensi Guru & Staff
                        @elseif(request()->routeIs('admin.attendances.*')) Presensi
                        @elseif(request()->routeIs('admin.announcements.*')) Pengumuman
                        @elseif(request()->routeIs('admin.settings.*')) Pengaturan
                        @else Admin Portal
                        @endif
                    </span>
                @else
                    <span class="separator">/</span>
                    <span class="current">Overview</span>
                @endif
            </div>

            <!-- Navbar Actions -->
            <div class="ms-auto d-flex align-items-center gap-3">
                <!-- Operational Status Indicator -->
                <div class="d-none d-md-flex">
                    <span class="nav-status-pill">
                        <span class="gps-dot"></span>
                        <span>Sistem Aktif</span>
                    </span>
                </div>

                <!-- Bell Notification -->
                <button class="btn btn-sm p-2 border-0 bg-transparent text-secondary position-relative hover-lift" title="Notifikasi" style="font-size:1.15rem;">
                    <i class="bi bi-bell"></i>
                </button>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 px-2 py-1 rounded-2" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false" style="background:#f8fafc; border:1px solid #e2e8f0;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                             style="width:32px;height:32px;font-size:0.8rem;background:linear-gradient(135deg,#2563eb,#1e40af)!important;">
                            {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="d-none d-md-block text-start pe-1">
                            <div class="fw-semibold text-dark" style="font-size:0.82rem;line-height:1.2;">{{ Auth::user()->name ?? 'Administrator' }}</div>
                            <div class="text-muted" style="font-size:0.7rem;">Super Admin</div>
                        </div>
                        <i class="bi bi-chevron-down text-muted ms-1" style="font-size:0.65rem;"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg py-2 mt-2 border-0" style="min-width:240px;">
                        <li class="px-3 py-2 border-bottom border-light">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                     style="width:40px;height:40px;font-size:1rem;background:linear-gradient(135deg,#2563eb,#1e40af);">
                                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark text-truncate" style="font-size:0.875rem;">{{ Auth::user()->name ?? 'Administrator' }}</div>
                                    <div class="text-muted small text-truncate">{{ Auth::user()->email ?? '' }}</div>
                                </div>
                            </div>
                        </li>
                        <li>
                            <a class="dropdown-item mt-1" href="{{ route('admin.settings.index') }}">
                                <i class="bi bi-gear me-2 text-secondary"></i>Pengaturan Sistem
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="submit" form="logout-form" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- ===================== CONTENT ===================== -->
    <main class="content-wrapper">
        <div class="container-fluid animate-fade-up">
            @yield('content')
        </div>
    </main>

    <!-- ===================== GLOBAL DELETE MODAL ===================== -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:46px;height:46px;background:#fff1f2;border:1px solid #fecdd3;">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark mb-0" id="deleteModalLabel">Konfirmasi Penghapusan</h6>
                            <p class="text-muted small mb-0">Tindakan ini bersifat permanen</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3 px-4">
                    <p class="text-secondary mb-0" style="font-size: 0.9rem;">
                        Apakah Anda yakin ingin menghapus data <strong class="text-dark" id="deleteTargetName">—</strong>? Data yang telah dihapus tidak dapat dipulihkan kembali.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <form id="deleteForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger px-4 fw-semibold shadow-sm">
                            <i class="bi bi-trash3 me-1"></i>Hapus Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== COPY TOAST ===================== -->
    <div id="copy-toast">
        <i class="bi bi-clipboard-check me-1"></i> Berhasil disalin ke clipboard!
    </div>

    @stack('modals')

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ---- Sidebar toggle ----
        const toggleBtn  = document.getElementById('toggleSidebar');
        const sidebar    = document.getElementById('sidebar');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
            });
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                sidebar.classList.add('collapsed');
            }
        }

        // ---- Global delete modal ----
        document.addEventListener('click', event => {
            const btn = event.target.closest('[data-delete-url]');
            if (!btn) return;

            event.preventDefault();
            document.getElementById('deleteForm').action = btn.dataset.deleteUrl;
            document.getElementById('deleteTargetName').textContent = btn.dataset.deleteName || 'data ini';
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });

        // ---- Copy to clipboard ----
        const copyToast = document.getElementById('copy-toast');
        let toastTimer;

        window.copyToClipboard = function(text) {
            navigator.clipboard.writeText(text).then(() => {
                clearTimeout(toastTimer);
                copyToast.classList.add('show');
                toastTimer = setTimeout(() => copyToast.classList.remove('show'), 2000);
            });
        };

        // ---- Tooltips ----
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

        // Live GET filters: replace only the results area without reloading the page.
        document.querySelectorAll('form.js-live-filter').forEach(form => {
            let filterTimer;
            let requestController;

            const loadResults = async () => {
                requestController?.abort();
                requestController = new AbortController();

                const params = new URLSearchParams(new FormData(form));
                const url = `${form.action}?${params.toString()}`;
                const currentResults = document.querySelector('.js-live-results');
                currentResults?.classList.add('is-loading');

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: requestController.signal,
                    });
                    if (!response.ok) throw new Error(`Filter request failed: ${response.status}`);

                    const html = await response.text();
                    const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                    const nextResults = nextDocument.querySelector('.js-live-results');

                    if (currentResults && nextResults) {
                        nextResults.classList.add('is-entering');
                        currentResults.replaceWith(nextResults);
                        window.history.replaceState({}, '', url);

                        // Sinkronisasi modal dinamis (editModal, viewModal) agar tetap di root body dan selalu up-to-date
                        const nextModals = nextDocument.querySelectorAll('.modal[id^="editModal"], .modal[id^="viewModal"]');
                        if (nextModals.length > 0) {
                            document.querySelectorAll('.modal[id^="editModal"], .modal[id^="viewModal"]').forEach(m => {
                                bootstrap.Modal.getInstance(m)?.dispose();
                                m.remove();
                            });
                            nextModals.forEach(m => document.body.appendChild(m));
                        }
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        currentResults?.classList.remove('is-loading');
                        console.error(error);
                    }
                }
            };

            const submitFilter = () => {
                clearTimeout(filterTimer);
                filterTimer = setTimeout(loadResults, 400);
            };

            form.addEventListener('submit', event => {
                event.preventDefault();
                loadResults();
            });

            form.addEventListener('input', event => {
                if (event.target.matches('input[type="text"], input[type="search"]')) {
                    submitFilter();
                }
            });

            form.addEventListener('change', event => {
                if (event.target.matches('select, input[type="date"]')) {
                    submitFilter();
                }
            });
        });

        // Reusable background refresh for index-page result blocks.
        window.refreshLiveResults = async function(selector = '.js-live-results') {
            const currentResults = document.querySelector(selector);
            if (!currentResults) return;

            try {
                const response = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error(`Live refresh failed: ${response.status}`);

                const html = await response.text();
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextResults = nextDocument.querySelector(selector);
                if (!nextResults) return;

                nextResults.classList.add('is-entering');
                currentResults.replaceWith(nextResults);

                // Sinkronisasi modal dinamis (editModal, viewModal)
                const nextModals = nextDocument.querySelectorAll('.modal[id^="editModal"], .modal[id^="viewModal"]');
                if (nextModals.length > 0) {
                    document.querySelectorAll('.modal[id^="editModal"], .modal[id^="viewModal"]').forEach(m => {
                        bootstrap.Modal.getInstance(m)?.dispose();
                        m.remove();
                    });
                    nextModals.forEach(m => document.body.appendChild(m));
                }
            } catch (error) {
                console.error('Realtime results refresh failed:', error);
            }
        };
    </script>
    @stack('scripts')
</body>

</html>
