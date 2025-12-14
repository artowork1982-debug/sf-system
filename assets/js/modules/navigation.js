import { state, getters } from './state.js';
import { updateUIForStep, updatePreview, handleConditionalFields } from './preview-update.js';

const { getEl, qsa } = getters;

export function showStep(stepNumber, skipScroll = false) {
    state.currentStep = stepNumber;

    qsa('.sf-step-content').forEach(stepEl => {
        const isActive = parseInt(stepEl.dataset.step, 10) === stepNumber;
        stepEl.classList.toggle('active', isActive);

        if (isActive) {
            const prevBtn = stepEl.querySelector('.sf-prev-btn');
            if (prevBtn) prevBtn.style.display = (stepNumber === 1) ? 'none' : '';
        }
    });

    updateUIForStep(stepNumber);

    if (!skipScroll) {
        setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 50);
    }
}
// Lisää tämä tiedoston loppuun
export function bindStepButtons() {
    const { maxSteps } = state;

    document.querySelectorAll('.sf-next-btn, #sfNext').forEach(btn => {
        if (btn.dataset.sfNavBound) return;
        btn.dataset.sfNavBound = '1';
        btn.addEventListener('click', () => {
            const next = Math.min(state.currentStep + 1, maxSteps);
            showStep(next);
        });
    });

    document.querySelectorAll('.sf-prev-btn').forEach(btn => {
        if (btn.dataset.sfNavBound) return;
        btn.dataset.sfNavBound = '1';
        btn.addEventListener('click', () => {
            const prev = Math.max(state.currentStep - 1, 1);
            showStep(prev);
        });
    });
}