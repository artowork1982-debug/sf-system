import { getters, state, setClickedSubmitButtonValue } from './state.js';
import { captureAllPreviews } from './capture.js';

const { getEl } = getters;

function createLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'sf-loading-overlay';
    overlay.innerHTML = `
        <div class="sf-loading-content">
            <div class="sf-loading-spinner"></div>
            <div class="sf-loading-text">Tallennetaan tiedotetta...</div>
            <div class="sf-loading-subtext">Generoidaan esikatselukuvaa</div>
        </div>
    `;
    document.body.appendChild(overlay);
    return overlay;
}

function showLoading(message, subtext) {
    let overlay = getEl('sf-loading-overlay');
    if (!overlay) overlay = createLoadingOverlay();

    const textEl = overlay.querySelector('.sf-loading-text');
    const subtextEl = overlay.querySelector('.sf-loading-subtext');

    if (textEl) textEl.textContent = message || 'Tallennetaan tiedotetta...';
    if (subtextEl) subtextEl.textContent = subtext || 'Generoidaan esikatselukuvaa';

    overlay.classList.add('visible');
}

function hideLoading() {
    const overlay = getEl('sf-loading-overlay');
    if (overlay) overlay.classList.remove('visible');
}

async function doSubmit(form, isDraft) {
    const draftBtn = getEl('sfSaveDraft');
    const reviewBtn = getEl('sfSubmitReview');

    showLoading(
        isDraft ? 'Tallennetaan luonnosta...' : 'Lähetetään tarkistettavaksi...',
        'Generoidaan esikatselukuvaa'
    );

    if (draftBtn) {
        draftBtn.disabled = true;
        draftBtn.classList.add('sf-btn-loading');
    }
    if (reviewBtn) {
        reviewBtn.disabled = true;
        reviewBtn.classList.add('sf-btn-loading');
    }

    try {
        await captureAllPreviews();
    } catch (err) {
        console.error('Error generating previews:', err);
        hideLoading();
        alert('Esikatselukuvan generointi epäonnistui.');
        if (draftBtn) {
            draftBtn.disabled = false;
            draftBtn.classList.remove('sf-btn-loading');
        }
        if (reviewBtn) {
            reviewBtn.disabled = false;
            reviewBtn.classList.remove('sf-btn-loading');
        }
        return;
    }

    const overlay = getEl('sf-loading-overlay');
    const sub = overlay ? overlay.querySelector('.sf-loading-subtext') : null;
    if (sub) sub.textContent = 'Tallennetaan tietokantaan...';

    const hiddenType = document.createElement('input');
    hiddenType.type = 'hidden';
    hiddenType.name = 'submission_type';
    hiddenType.value = isDraft ? 'draft' : 'review';
    form.appendChild(hiddenType);

    form.submit();
}

export function bindSubmit() {
    const form = getEl('sf-form');
    const draftBtn = getEl('sfSaveDraft');
    const reviewBtn = getEl('sfSubmitReview');
    const confirmModal = getEl('sfConfirmModal');
    const confirmSubmitBtn = getEl('sfConfirmSubmit');

    // Luonnos - lähetä suoraan
    if (draftBtn) {
        draftBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setClickedSubmitButtonValue('draft');
            doSubmit(form, true);
        });
    }

    // Tarkistettavaksi - näytä vahvistusmodaali
    if (reviewBtn) {
        reviewBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setClickedSubmitButtonValue('review');

            // Avaa vahvistusmodaali
            if (confirmModal) {
                confirmModal.classList.remove('hidden');
                document.body.classList.add('sf-modal-open');
            } else {
                // Jos modaalia ei ole, lähetä suoraan
                doSubmit(form, false);
            }
        });
    }

    // Modaalin "Kyllä, lähetä" -nappi
    if (confirmSubmitBtn) {
        confirmSubmitBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Sulje modaali
            if (confirmModal) {
                confirmModal.classList.add('hidden');
                document.body.classList.remove('sf-modal-open');
            }

            // Lähetä lomake
            doSubmit(form, false);
        });
    }

    // Estä lomakkeen normaali submit
    if (form) {
        form.addEventListener('submit', function (e) {
            // Jos on jo hidden input, anna mennä
            if (form.querySelector('input[name="submission_type"][type="hidden"]')) {
                return;
            }
            e.preventDefault();
        });
    }
}