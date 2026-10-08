{{-- Riwayat Eksekusi Massal & Pembatalan --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
            <i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Eksekusi Terakhir
        </h6>
        <span class="text-muted small" style="font-size: 0.78rem;">
            <i class="bi bi-shield-check me-1"></i>Batch yang salah dapat dibatalkan
        </span>
    </div>
    <div class="card-body p-0">
        @if($promotionHistory->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                <p class="text-muted small mb-0">Belum ada kenaikan kelas atau kelulusan yang diproses.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Waktu</th>
                            <th>Aksi</th>
                            <th>Dari</th>
                            <th>Ke</th>
                            <th class="text-center">Siswa</th>
                            <th>Diproses Oleh</th>
                            <th class="text-end pe-4" style="width: 150px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($promotionHistory as $batch)
                            <tr>
                                <td class="ps-4 small text-dark">{{ $batch->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($batch->action === \App\Models\PromotionBatch::ACTION_PROMOTE)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-arrow-repeat me-1"></i>Kenaikan
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-mortarboard me-1"></i>Kelulusan
                                        </span>
                                    @endif
                                </td>
                                <td class="small text-dark">
                                    {{ $batch->source_classroom_name }}
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">{{ $batch->source_academic_year }}</span>
                                    @if($batch->releasedHomeroomTeacherLabel())
                                        <span class="text-warning-emphasis d-block" style="font-size: 0.72rem;">
                                            <i class="bi bi-person-dash me-1"></i>{{ $batch->releasedHomeroomTeacherLabel() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="small text-dark">
                                    {{ $batch->targetLabel() }}
                                    @if($batch->target_academic_year)
                                        <span class="text-muted d-block" style="font-size: 0.72rem;">{{ $batch->target_academic_year }}</span>
                                    @endif
                                </td>
                                <td class="text-center small text-dark">{{ $batch->student_count }}</td>
                                <td class="small text-secondary">{{ $batch->performed_by_name }}</td>
                                <td class="text-end pe-4">
                                    @if($batch->isReverted())
                                        <span class="badge bg-light text-secondary border px-2 py-1"
                                              style="font-size: 0.72rem;"
                                              title="Dibatalkan {{ $batch->reverted_at->format('d M Y H:i') }} oleh {{ $batch->reverted_by_name }}">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Dibatalkan
                                        </span>
                                    @else
                                        <form action="{{ route('admin.classrooms.promotion.revert', $batch->id) }}" method="POST"
                                              data-confirm-form="Batalkan batch {{ $batch->actionLabel() }} dari {{ $batch->source_classroom_name }}? {{ $batch->student_count }} siswa akan dikembalikan ke posisi semula.">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.75rem;">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Batalkan
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
