{{-- Modal Konfirmasi Protektif Eksekusi Massal --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-shield-check fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="confirmModalLabel">
                            Konfirmasi Eksekusi Massal
                        </h6>
                        <p class="text-muted small mb-0">Periksa kembali data sebelum diproses</p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3 px-4">
                {{-- Indikator loading verifikasi preview server-side --}}
                <div id="modalPreviewLoading" class="py-4 text-center text-muted small d-none">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <div>Memverifikasi kelayakan data ke server...</div>
                </div>

                <div id="modalPreviewContent">
                    <p class="text-secondary small mb-3">
                        Mohon pastikan rincian hasil verifikasi berikut sudah tepat sebelum melanjutkan:
                    </p>

                    <div class="alert alert-danger border-0 p-2.5 px-3 small d-none mb-3" id="modalErrorAlert" style="border-radius: 8px;">
                        <i class="bi bi-x-circle-fill me-1"></i>
                        <span id="modalErrorMessage"></span>
                    </div>

                    <div class="alert alert-warning border-0 p-2.5 px-3 small d-none mb-3" id="modalWarningAlert" style="border-radius: 8px;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="modalWarningMessage"></span>
                    </div>

                    <div class="bg-light p-3 rounded-3 mb-3 small">
                        <div class="d-flex justify-content-between py-1.5 border-bottom">
                            <span class="text-muted">Kelas Asal:</span>
                            <span class="fw-bold text-dark" id="modalSourceClass">-</span>
                        </div>
                        <div class="d-flex justify-content-between py-1.5 border-bottom">
                            <span class="text-muted">Tipe Aksi:</span>
                            <span class="fw-bold text-primary" id="modalActionType">Kenaikan Kelas</span>
                        </div>
                        <div class="d-flex justify-content-between py-1.5 border-bottom">
                            <span class="text-muted">Tujuan / Status Baru:</span>
                            <span class="fw-bold text-success" id="modalTargetClass">-</span>
                        </div>
                        <div class="d-flex justify-content-between py-1.5 border-bottom d-none" id="modalCapacityRow">
                            <span class="text-muted">Kapasitas Tujuan:</span>
                            <span class="fw-bold text-dark" id="modalTargetCapacity">-</span>
                        </div>
                        <div class="d-flex justify-content-between py-1.5">
                            <span class="text-muted">Jumlah Siswa Terpilih:</span>
                            <span class="fw-bold text-dark" id="modalStudentCount">0 Siswa</span>
                        </div>
                    </div>
                    <div class="alert alert-light border p-2 px-3 small d-flex align-items-center gap-2 mb-0 text-muted" style="border-radius: 8px;">
                        <i class="bi bi-info-circle text-primary shrink-0"></i>
                        <span>Siswa yang tidak dicentang akan tetap berada di kelas asalnya.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="promotionForm" class="btn btn-sm btn-primary px-4 fw-semibold shadow-xs" id="btnSubmitFinal">
                    <i class="bi bi-check2-circle me-1"></i>Ya, Jalankan Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

