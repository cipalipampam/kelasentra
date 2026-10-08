{{-- Quick Action Approval Inbox --}}
<div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
        <div>
            <h6 class="fw-bold text-dark m-0">
                <i class="bi bi-inbox-fill text-primary me-2"></i>Inbox Pengajuan Izin / Sakit
            </h6>
            <span class="text-muted small" style="font-size: 0.78rem;">Tindakan persetujuan langsung admin</span>
        </div>
        <span class="badge-status badge-sick">{{ count($pendingRequests) }} Menunggu</span>
    </div>
    <div class="card-body p-0">
        @if($pendingRequests->isEmpty())
            <div class="text-center py-5">
                <div class="mb-2 text-success"><i class="bi bi-check-circle-fill fs-1"></i></div>
                <h6 class="fw-semibold text-dark mb-1">Semua Pengajuan Selesai</h6>
                <p class="text-muted small mb-0 px-4">Tidak ada pengajuan izin atau surat sakit yang menunggu verifikasi saat ini.</p>
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach($pendingRequests as $req)
                    <div class="list-group-item p-3 border-bottom d-flex align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3 overflow-hidden">
                            <div class="avatar-placeholder bg-light text-primary">
                                {{ strtoupper(substr($req->user->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="overflow-hidden">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold text-dark text-truncate" style="font-size: 0.88rem;">{{ $req->user->name }}</span>
                                    @if($req->user->student)
                                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.68rem;">{{ $req->user->student->class_name }}</span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    @if($req->status === 'sick')
                                        <span class="badge-status badge-sick" style="font-size: 0.68rem; padding: 2px 6px;">
                                            <i class="bi bi-bandaid"></i>Sakit
                                        </span>
                                    @else
                                        <span class="badge-status badge-permission" style="font-size: 0.68rem; padding: 2px 6px;">
                                            <i class="bi bi-file-text"></i>Izin
                                        </span>
                                    @endif
                                    <span class="text-muted small text-truncate" style="font-size: 0.78rem;" title="{{ $req->notes ?? '-' }}">
                                        {{ Str::limit($req->notes ?? 'Tidak ada keterangan', 32) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1 shrink-0">
                            {{-- Tombol Lihat Bukti --}}
                            @if($req->proof_url)
                                <button type="button" class="btn-action btn-action-view" title="Lihat Foto Bukti"
                                    data-proof-url="{{ $req->proof_url }}"
                                    data-proof-name="{{ $req->user->name }}"
                                    onclick="showProofModal('{{ $req->proof_url }}', '{{ addslashes($req->user->name) }}')">
                                    <i class="bi bi-image"></i>
                                </button>
                            @endif

                            {{-- Tombol Setujui --}}
                            <form action="{{ route('admin.attendances.approve', $req->id) }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-action btn-action-approve" title="Setujui Pengajuan" onclick="return confirm('Setujui pengajuan izin/sakit dari {{ addslashes($req->user->name) }}?')">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>

                            {{-- Tombol Tolak --}}
                            <form action="{{ route('admin.attendances.approve', $req->id) }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-action btn-action-delete" title="Tolak Pengajuan (Tercatat Alfa)" onclick="return confirm('Tolak permohonan ini? Status pengguna akan otomatis tercatat Alfa.')">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>

                            {{-- Link Detail Lengkap --}}
                            <a href="{{ route('admin.attendances.show', $req->id) }}" class="btn-action btn-action-view" title="Lihat Detail Halaman">
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

