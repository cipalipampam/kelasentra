/**
 * Confirm Submit Island
 * Meminta konfirmasi sebelum mengirim form yang ditandai `data-confirm-form`.
 * Dipasang di level document agar tetap bekerja setelah area hasil
 * live-filter diganti tanpa reload halaman.
 */
export function initConfirmSubmitIsland() {
    if (document.documentElement.dataset.confirmSubmitBound === 'true') return;
    document.documentElement.dataset.confirmSubmitBound = 'true';

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm-form]');
        if (!form) return;

        if (window.confirm(form.dataset.confirmForm)) return;

        event.preventDefault();
    });
}
