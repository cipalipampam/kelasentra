/**
 * Class Promotion Orchestrator (ESM)
 * Mengelola interaktivitas halaman Kenaikan Kelas & Kelulusan Massal.
 */
import { initPromotionIsland } from './islands/promotion-island.js';

function bootstrapPromotion() {
    initPromotionIsland();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapPromotion);
} else {
    bootstrapPromotion();
}

