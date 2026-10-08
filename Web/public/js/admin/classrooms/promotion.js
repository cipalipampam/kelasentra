/**
 * Class Promotion Orchestrator (ESM)
 * Mengelola interaktivitas halaman Kenaikan Kelas & Kelulusan Massal.
 */
import { initPromotionIsland } from './islands/promotion-island.js';
import { initConfirmSubmitIsland } from '../shared/islands/confirm-submit-island.js';

function bootstrapPromotion() {
    initPromotionIsland();
    initConfirmSubmitIsland();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapPromotion);
} else {
    bootstrapPromotion();
}

