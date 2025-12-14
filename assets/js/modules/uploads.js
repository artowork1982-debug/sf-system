import { getters } from './state.js';
const { getEl } = getters;

function autoFitImageToFrame(img, frame) {
    if (!img || !frame) return;
    const doFit = () => {
        const frameRect = frame.getBoundingClientRect();
        const frameW = frameRect.width;
        const frameH = frameRect.height;
        const imgW = img.naturalWidth;
        const imgH = img.naturalHeight;
        if (!imgW || !imgH || !frameW || !frameH) return;
        const scale = Math.max(frameW / imgW, frameH / imgH);
        const offsetX = (frameW - imgW * scale) / 2;
        const offsetY = (frameH - imgH * scale) / 2;
        img.style.position = 'absolute';
        img.style.width = imgW + 'px';
        img.style.height = imgH + 'px';
        img.style.left = offsetX + 'px';
        img.style.top = offsetY + 'px';
        img.style.transform = `scale(${scale})`;
        img.style.transformOrigin = 'top left';
        img.style.maxWidth = 'none';
        img.style.maxHeight = 'none';
    };
    if (img.complete && img.naturalWidth) doFit(); else img.onload = doFit;
}

export function bindUploads() {
    [1, 2, 3].forEach(slot => {
        const fileInput = document.getElementById(`sf-image${slot}`);
        const previewImg = document.getElementById(`sfImageThumb${slot}`) || document.getElementById(`sf-upload-preview${slot}`);

        const getPlaceholder = (thumb) => thumb?.dataset?.placeholder || '/safetyflash-system/assets/img/camera-placeholder.png';

        if (previewImg && fileInput && !previewImg.dataset.sfUploadClickBound) {
            previewImg.dataset.sfUploadClickBound = '1';
            previewImg.style.cursor = 'pointer';
            previewImg.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                if (e.target.closest('.sf-image-remove-btn, .sf-upload-remove')) return;
                fileInput.click();
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const file = fileInput.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function (e) {
                    const thumb = document.getElementById(`sfImageThumb${slot}`) || document.getElementById(`sf-upload-preview${slot}`);
                    if (thumb) {
                        thumb.src = e.target.result;
                        thumb.dataset.hasRealImage = '1';
                        thumb.parentElement?.classList.add('has-image');
                    }
                    const removeBtn = document.querySelector(`.sf-image-remove-btn[data-slot="${slot}"]`) || document.querySelector(`.sf-upload-remove[data-slot="${slot}"]`);
                    if (removeBtn) removeBtn.classList.remove('hidden');

                    const libraryInput = document.getElementById(`sfLibraryImage${slot}`);
                    if (libraryInput) libraryInput.value = '';

                    const cardImg = document.getElementById(`sfPreviewImg${slot}`);
                    const cardImgGreen = document.getElementById(`sfPreviewImg${slot}Green`);

                    if (cardImg) {
                        cardImg.src = e.target.result;
                        cardImg.dataset.hasRealImage = '1';
                        const frame = cardImg.closest('.sf-preview-image-frame, .sf-preview-thumb-frame');
                        if (frame) cardImg.onload = () => autoFitImageToFrame(cardImg, frame);
                    }

                    if (cardImgGreen) {
                        cardImgGreen.src = e.target.result;
                        cardImgGreen.dataset.hasRealImage = '1';
                        const frameGreen = cardImgGreen.closest('.sf-preview-image-frame, .sf-preview-thumb-frame');
                        if (frameGreen) cardImgGreen.onload = () => autoFitImageToFrame(cardImgGreen, frameGreen);
                    }

                    window.Preview?.applyGridClass?.();
                    window.PreviewTutkinta?.applyGridClass?.();
                };
                reader.readAsDataURL(file);
            });
        }

        const removeBtn = document.querySelector(`.sf-image-remove-btn[data-slot="${slot}"]`) || document.querySelector(`.sf-upload-remove[data-slot="${slot}"]`);
        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                if (fileInput) fileInput.value = '';
                const libraryInput = document.getElementById(`sfLibraryImage${slot}`);
                if (libraryInput) libraryInput.value = '';
                const thumb = document.getElementById(`sfImageThumb${slot}`) || document.getElementById(`sf-upload-preview${slot}`);
                const placeholder = getPlaceholder(thumb);
                if (thumb) {
                    thumb.src = placeholder;
                    thumb.dataset.hasRealImage = '0';
                    thumb.parentElement?.classList.remove('has-image');
                }
                this.classList.add('hidden');
                const cardImg = document.getElementById(`sfPreviewImg${slot}`);
                if (cardImg) { cardImg.src = placeholder; cardImg.dataset.hasRealImage = '0'; }
                const cardImgGreen = document.getElementById(`sfPreviewImg${slot}Green`);
                if (cardImgGreen) { cardImgGreen.src = placeholder; cardImgGreen.dataset.hasRealImage = '0'; }
                if (window.Preview?.state) {
                    window.Preview.state[slot] = { x: 0, y: 0, scale: 1 };
                    const transformInput = document.getElementById(`sf-image${slot}-transform`);
                    if (transformInput) transformInput.value = '';
                    window.Preview.applyGridClass?.();
                }
                if (window.PreviewTutkinta?.state) {
                    window.PreviewTutkinta.state[slot] = { x: 0, y: 0, scale: 1 };
                    window.PreviewTutkinta.applyGridClass?.();
                }
            });
        }
    });
}