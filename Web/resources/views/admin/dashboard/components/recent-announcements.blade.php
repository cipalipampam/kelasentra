{{-- Pengumuman Terkini di Mobile --}}
<div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="fw-bold text-dark m-0">
            <i class="bi bi-megaphone text-primary me-2"></i>Pengumuman Terkini di Mobile
        </h6>
        <a href="{{ route('admin.announcements.index') }}" class="btn btn-sm btn-link text-decoration-none p-0 text-primary" style="font-size: 0.8rem;">
            Kelola Pengumuman →
        </a>
    </div>
    <div class="card-body p-3">
        @if($recentAnnouncements->isEmpty())
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-chat-left-dots fs-3 d-block mb-2"></i>
                Belum ada pengumuman aktif untuk aplikasi mobile siswa & pegawai.
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach($recentAnnouncements as $ann)
                    <div class="list-group-item px-2 py-2 border-0 border-bottom">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <strong class="text-dark small text-truncate" style="max-width: 70%;">{{ $ann->title }}</strong>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">Aktif</span>
                        </div>
                        <p class="text-muted small mb-0 text-truncate" style="font-size: 0.78rem;">{{ $ann->content }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

