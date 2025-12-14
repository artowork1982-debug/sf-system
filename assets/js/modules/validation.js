import { getters, state } from './state.js';
const { qs, getEl } = getters;

export function validateStep(stepNumber) {
    const errors = [];
    const currentType = qs('input[name="type"]:checked')?.value;

    if (stepNumber === 1) {
        if (!qs('input[name="lang"]:checked')) errors.push('Valitse kieli');
        if (!qs('input[name="type"]:checked')) errors.push('Valitse tiedotteen tyyppi');
    }
    if (stepNumber === 2) {
        if (currentType === 'green') {
            const worksite = getEl('sf-worksite-investigation')?.value?.trim();
            const relatedFlash = getEl('sf-related-flash')?.value;
            if (!worksite && !relatedFlash) errors.push('Valitse pohjatiedote tai syötä työmaa');
        } else {
            const worksite = getEl('sf-worksite')?.value?.trim();
            const eventDate = getEl('sf-date')?.value;
            if (!worksite) errors.push('Valitse työmaa');
            if (!eventDate) errors.push('Syötä tapahtuma-aika');
        }
    }
    if (stepNumber === 3) {
        const title = getEl('sf-title')?.value?.trim();
        const shortText = getEl('sf-short-text')?.value?.trim();
        const description = getEl('sf-description')?.value?.trim();
        if (!title) errors.push('Syötä sisäinen otsikko');
        if (!shortText) errors.push('Syötä lyhyt kuvaus');
        else if (shortText.length > 85) errors.push('Lyhyt kuvaus on liian pitkä (max 125 merkkiä)');
        if (!description) errors.push('Syötä tapahtuman kuvaus');
        else if (description.length > 950) errors.push('Kuvaus on liian pitkä (max 650 merkkiä)');
        if (currentType === 'green') {
            const rootCauses = getEl('sf-root-causes')?.value?.trim();
            const actions = getEl('sf-actions')?.value?.trim();
            if (rootCauses && rootCauses.length > 1500) errors.push('Juurisyyt-teksti on liian pitkä (max 1500 merkkiä)');
            if (actions && actions.length > 1500) errors.push('Toimenpiteet-teksti on liian pitkä (max 1500 merkkiä)');
        }
    }
    if (stepNumber === 4) {
        const fileInput1 = getEl('sf-image1');
        const libraryImage1 = getEl('sfLibraryImage1');
        const imageThumb1 = getEl('sfImageThumb1');
        const legacyPreview1 = getEl('sf-upload-preview1');
        const hasFileUpload = fileInput1?.files?.length > 0;
        const hasLibraryImage = libraryImage1 && libraryImage1.value.trim() !== '';
        const thumbEl = imageThumb1 || legacyPreview1;
        const isPlaceholder = (src) => !src || src.includes('camera-placeholder') || src.endsWith('/');
        const hasExistingImage = thumbEl && thumbEl.src && !isPlaceholder(thumbEl.src);
        if (!hasFileUpload && !hasLibraryImage && !hasExistingImage) {
            errors.push('Lisää vähintään yksi kuva');
        }
    }
    return errors;
}

export function showValidationErrors(errors) {
    if (errors.length === 0) return true;
    let errorBox = getEl('sf-validation-errors');
    if (!errorBox) {
        errorBox = document.createElement('div');
        errorBox.id = 'sf-validation-errors';
        errorBox.className = 'sf-validation-errors';
        const activeStep = qs('.sf-step-content.active');
        if (activeStep) activeStep.insertBefore(errorBox, activeStep.firstChild);
    }
    errorBox.innerHTML = `
    <div class="sf-validation-icon">⚠️</div>
    <div class="sf-validation-content">
      <strong>Täytä puuttuvat tiedot:</strong>
      <ul>${errors.map(e => `<li>${e}</li>`).join('')}</ul>
    </div>
    <button type="button" class="sf-validation-close" onclick="this.parentElement.remove()">×</button>
  `;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return false;
}