{{-- Log Presensi Hari Ini (Live Attendance Table) --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
        <div>
            <h6 class="fw-bold text-dark m-0">
                <i class="bi bi-clock-history text-primary me-2"></i>Presensi Terkini Hari Ini
            </h6>
            <span class="text-muted small" style="font-size: 0.78rem;">Aktivitas check-in dan check-out terbaru secara langsung</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.attendances.index') }}" class="btn btn-sm btn-outline-primary py-1 px-3" style="font-size: 0.82rem;">
                Kelola Semua Presensi →
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        @if ($todayPresensi->isEmpty())
            <div class="text-center py-5">
                <div class="mb-3 text-muted"><i class="bi bi-calendar-x fs-1"></i></div>
                <h6 class="text-dark fw-medium">Belum Ada Presensi Hari Ini</h6>
                <p class="text-muted small mb-0">Catatan presensi masuk akan otomatis tampil di sini secara real-time.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Nama Pengguna</th>
                            <th>Peran / Kelas</th>
                            <th>Status Kehadiran</th>
                            <th>Waktu Masuk</th>
                            <th>Waktu Pulang</th>
                            <th>Lokasi Presensi</th>
                            <th class="pe-4 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="ws-attendance-tbody">
                        @foreach ($todayPresensi as $presensi)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-placeholder">
                                            {{ strtoupper(substr($presensi->user->name ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark" style="font-size: 0.88rem;">{{ $presensi->user->name ?? 'Unknown' }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">{{ $presensi->user->email ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($presensi->user->student)
                                        <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-person-badge me-1"></i>{{ $presensi->user->student->class_name }}
                                        </span>
                                    @elseif($presensi->user->employee)
                                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-briefcase me-1"></i>{{ $presensi->user->employee->position ?? 'Pegawai' }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.72rem;">Administrator</span>
                                    @endif
                                </td>
                                <td>
                                    @if($presensi->status == 'present')
                                        @if($presensi->is_late)
                                            <span class="badge-status badge-late"><i class="bi bi-clock-history"></i>Terlambat</span>
                                        @else
                                            <span class="badge-status badge-present"><i class="bi bi-check2"></i>Hadir</span>
                                        @endif
                                    @elseif($presensi->status == 'permission')
                                        <span class="badge-status badge-permission">
                                            <i class="bi bi-file-text"></i>Izin
                                            @if($presensi->is_approved === true)
                                                <i class="bi bi-check-all ms-1 text-success"></i>
                                            @elseif($presensi->is_approved === false)
                                                <i class="bi bi-x ms-1 text-danger"></i>
                                            @else
                                                <span class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;">Review</span>
                                            @endif
                                        </span>
                                    @elseif($presensi->status == 'sick')
                                        <span class="badge-status badge-sick">
                                            <i class="bi bi-bandaid"></i>Sakit
                                            @if($presensi->is_approved === true)
                                                <i class="bi bi-check-all ms-1 text-success"></i>
                                            @elseif($presensi->is_approved === false)
                                                <i class="bi bi-x ms-1 text-danger"></i>
                                            @else
                                                <span class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;">Review</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="badge-status badge-absent"><i class="bi bi-x-circle"></i>Alfa</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-medium text-dark" style="font-size: 0.85rem;">
                                        {{ \Carbon\Carbon::parse($presensi->recorded_at)->format('H:i') }} WIB
                                    </span>
                                </td>
                                <td>
                                    @if($presensi->check_out_time)
                                        <span class="fw-medium text-dark" style="font-size: 0.85rem;">
                                            {{ \Carbon\Carbon::parse($presensi->check_out_time)->format('H:i') }} WIB
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($presensi->latitude && $presensi->longitude)
                                        <a href="https://www.google.com/maps?q={{ $presensi->latitude }},{{ $presensi->longitude }}" target="_blank" class="text-decoration-none small d-inline-flex align-items-center text-primary" style="font-size: 0.78rem;">
                                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>GPS Terkunci
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        @if($presensi->proof_url)
                                            <button type="button" class="btn-action btn-action-view" title="Lihat Foto Bukti"
                                                data-proof-url="{{ $presensi->proof_url }}"
                                                data-proof-name="{{ $presensi->user->name }}"
                                                onclick="showProofModal('{{ $presensi->proof_url }}', '{{ addslashes($presensi->user->name) }}')">
                                                <i class="bi bi-image"></i>
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.attendances.show', $presensi->id) }}" class="btn-action btn-action-view" title="Detail Presensi Lengkap">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($todayPresensi instanceof \Illuminate\Pagination\LengthAwarePaginator && $todayPresensi->hasPages())
            <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Menampilkan <strong class="text-dark">{{ $todayPresensi->firstItem() }}-{{ $todayPresensi->lastItem() }}</strong> dari <strong class="text-dark">{{ $todayPresensi->total() }}</strong> catatan
                </div>
                <div class="pagination-modern">
                    {{ $todayPresensi->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
        @endif
    </div>
</div>

