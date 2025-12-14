import { getters } from './state.js';
import { updatePreview } from './preview-update.js';
const { getEl } = getters;

export function bindRelatedFlash() {
    const relatedFlashSelect = getEl('sf-related-flash');
    if (!relatedFlashSelect) return;

    // Sulje-nappi alkuperäisen tiedotteen esikatselussa
    const closeBtn = getEl('sf-original-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            const preview = getEl('sf-original-flash-preview');
            if (preview) preview.classList.add('hidden');
        });
    }

    relatedFlashSelect.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const hiddenRelated = getEl('sf-related-flash-id');
        const originalPreview = getEl('sf-original-flash-preview');

        if (!selectedOption || !selectedOption.value) {
            if (hiddenRelated) hiddenRelated.value = '';
            if (originalPreview) originalPreview.classList.add('hidden');
            return;
        }

        if (hiddenRelated) hiddenRelated.value = selectedOption.value;

        const site = selectedOption.dataset.site || '';
        const siteDetail = selectedOption.dataset.siteDetail || '';
        const date = selectedOption.dataset.date || '';
        const title = selectedOption.dataset.title || '';
        const titleShort = selectedOption.dataset.titleShort || '';
        const description = selectedOption.dataset.description || '';
        const imageMain = selectedOption.dataset.imageMain || '';
        const image2 = selectedOption.dataset.image2 || '';
        const image3 = selectedOption.dataset.image3 || '';
        const originalType = selectedOption.closest('option')?.textContent?.includes('🔴') ? 'red' : 'yellow';

        // ============================================
        // NÄYTÄ ALKUPERÄINEN TIEDOTE (KOMPAKTI)
        // ============================================
        if (originalPreview) {
            originalPreview.classList.remove('hidden');

            // Päivitä tyyppiluokka ja ikoni
            originalPreview.classList.remove('type-red', 'type-yellow');
            originalPreview.classList.add('type-' + originalType);

            const icon = getEl('sf-original-icon');
            if (icon) {
                const card = getEl('sfPreviewCard') || getEl('sfPreviewCardGreen');
                const baseUrl = card?.dataset.baseUrl || '';
                icon.src = `${baseUrl}/assets/img/icon-${originalType}.png`;
            }

            // Päivitä otsikko
            const origTitle = getEl('sf-original-title');
            if (origTitle) origTitle.textContent = title || titleShort || '--';

            // Päivitä työmaa
            const origSite = getEl('sf-original-site');
            if (origSite) origSite.textContent = [site, siteDetail].filter(Boolean).join(' – ') || '--';

            // Päivitä päivämäärä
            const origDate = getEl('sf-original-date');
            if (origDate && date) {
                const dateObj = new Date(date);
                if (!isNaN(dateObj.getTime())) {
                    origDate.textContent = dateObj.toLocaleString('fi-FI', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } else {
                    origDate.textContent = '--';
                }
            }
        }
        // ============================================
        // KOPIOI KENTÄT SAMOIHIN KENTTIIN (EI ERILLISIIN)
        // ============================================

        // Työmaa - käytä samaa sf-worksite-kenttää
        const worksiteField = getEl('sf-worksite');
        if (worksiteField) {
            const options = worksiteField.options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === site) {
                    worksiteField.selectedIndex = i;
                    break;
                }
            }
        }

        // Site detail - käytä samaa sf-site-detail-kenttää
        const siteDetailField = getEl('sf-site-detail');
        if (siteDetailField) siteDetailField.value = siteDetail;

        // Päivämäärä - käytä samaa sf-date-kenttää
        const dateField = getEl('sf-date');
        if (dateField && date) {
            const dateObj = new Date(date);
            if (!isNaN(dateObj.getTime())) {
                dateField.value = dateObj.toISOString().slice(0, 16);
            }
        }

        // Otsikko ja kuvaus
        const titleField = getEl('sf-title');
        const shortTextField = getEl('sf-short-text');
        const descriptionField = getEl('sf-description');
        if (titleField) titleField.value = title;
        if (shortTextField) shortTextField.value = titleShort;
        if (descriptionField) descriptionField.value = description;

        // ============================================
        // KUVIEN KÄSITTELY
        // ============================================
        const card = getEl('sfPreviewCard') || getEl('sfPreviewCardGreen');
        const baseUrl = card?.dataset.baseUrl || '';
        const placeholder = `${baseUrl}/assets/img/camera-placeholder.png`;
        const getImageUrl = (filename) => filename ? `${baseUrl}/uploads/images/${filename}` : null;

        const updateImage = (slot, filename) => {
            const imgUrl = filename ? getImageUrl(filename) : placeholder;
            const uploadPreview = getEl(`sf-upload-preview${slot}`);
            if (uploadPreview) {
                uploadPreview.src = imgUrl;
                uploadPreview.parentElement?.classList.toggle('has-image', !!filename);
            }
            const cardImg = getEl(`sfPreviewImg${slot}`);
            if (cardImg) cardImg.src = imgUrl;
            const cardImgGreen = getEl(`sfPreviewImg${slot}Green`);
            if (cardImgGreen) cardImgGreen.src = imgUrl;

            if (window.Preview) {
                if (!window.Preview.state) window.Preview.state = {};
                window.Preview.state[slot] = { x: 0, y: 0, scale: 1 };
                const transformInput = getEl(`sf-image${slot}-transform`);
                if (transformInput) transformInput.value = '';
            }
            if (window.PreviewTutkinta) {
                if (!window.PreviewTutkinta.state) window.PreviewTutkinta.state = {};
                window.PreviewTutkinta.state[slot] = { x: 0, y: 0, scale: 1 };
            }
        };

        updateImage(1, imageMain);
        updateImage(2, image2);
        updateImage(3, image3);

        // Tallenna kuvien tiedostonimet hidden-kenttiin
        const setExistingImage = (slot, filename) => {
            let hiddenField = document.getElementById(`sf-existing-image-${slot}`);
            if (!hiddenField) {
                hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = `existing_image_${slot}`;
                hiddenField.id = `sf-existing-image-${slot}`;
                document.getElementById('sf-form')?.appendChild(hiddenField);
            }
            hiddenField.value = filename || '';

            const thumb = getEl(`sfImageThumb${slot}`);
            if (thumb && filename) {
                thumb.src = `${baseUrl}/uploads/images/${filename}`;
                thumb.dataset.hasRealImage = '1';
                const removeBtn = thumb.parentElement?.querySelector('.sf-image-remove-btn');
                if (removeBtn) removeBtn.classList.remove('hidden');
            }
        };

        setExistingImage(1, imageMain);
        setExistingImage(2, image2);
        setExistingImage(3, image3);

        // Päivitä previewit
        setTimeout(() => {
            updatePreview();
            window.Preview?.applyGridClass?.();
            window.PreviewTutkinta?.applyGridClass?.();
            window.PreviewTutkinta?.updatePreviewContent?.();
        }, 100);
    });
}