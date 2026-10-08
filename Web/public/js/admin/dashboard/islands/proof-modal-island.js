/**
 * Proof Modal Island
 * Mengelola pop-up modal pratinjau bukti dokumen / surat sakit.
 */
export function initProofModal() {
    const modalEl = document.getElementById('proofModal');
    if (!modalEl) return;

    const imgEl = document.getElementById('proofModalImage');
    const loadingEl = document.getElementById('proofLoading');
    const captionEl = document.getElementById('proofModalCaption');
    const downloadBtn = document.getElementById('proofDownloadBtn');

    function openModal(url, userName) {
        if (!url) return;

        if (loadingEl) loadingEl.classList.remove('d-none');
        if (imgEl) {
            imgEl.classList.add('d-none');
            imgEl.onload = function () {
                if (loadingEl) loadingEl.classList.add('d-none');
                imgEl.classList.remove('d-none');
            };
            imgEl.onerror = function () {
                if (loadingEl) loadingEl.classList.add('d-none');
                if (captionEl) captionEl.textContent = 'Gagal memuat gambar bukti.';
            };
            imgEl.src = url;
        }

        if (captionEl) captionEl.textContent = `Surat/Bukti dari: ${userName || 'Pengguna'}`;
        if (downloadBtn) downloadBtn.href = url;

        if (window.bootstrap?.Modal) {
            const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    // Expose ke window untuk backward-compatibility onclick inline
    window.showProofModal = openModal;

    // Delegasi event listener untuk data-attributes modern
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-proof-url]');
        if (btn) {
            e.preventDefault();
            const url = btn.getAttribute('data-proof-url');
            const name = btn.getAttribute('data-proof-name') || '';
            openModal(url, name);
        }
    });
}

