{{-- Modal Quick Preview Bukti Dokumen / Surat Sakit --}}
<div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold text-dark" id="proofModalLabel">
                    <i class="bi bi-file-earmark-image text-primary me-2"></i>Bukti Dokumen / Surat
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 text-center bg-light">
                <div id="proofLoading" class="py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <img id="proofModalImage" src="" alt="Bukti Surat" class="img-fluid rounded-3 border shadow-sm d-none" style="max-height: 480px; object-fit: contain;">
                <p id="proofModalCaption" class="text-muted small mt-2 mb-0"></p>
            </div>
            <div class="modal-footer border-top py-2">
                <a id="proofDownloadBtn" href="" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Buka Tab Baru
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

