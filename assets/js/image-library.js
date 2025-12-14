/**
 * assets/js/image-library.js
 * Kuvapankki-modaalin toiminnallisuus
 */
(function (global) {
    'use strict';

    const ImageLibrary = {
        initialized: false,
        currentSlot: null,
        selectedImage: null,
        images: [],
        baseUrl: '',

        init: function (baseUrl) {
            if (this.initialized) return this;

            this.baseUrl = baseUrl || '';
            this.bindEvents();
            this.initialized = true;

            return this;
        },

        bindEvents: function () {
            const self = this;

            // Kuvapankki-napit
            document.querySelectorAll('.sf-image-library-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const slot = parseInt(this.dataset.slot, 10);
                    self.openModal(slot);
                });
            });

            // Sulje modaali
            const closeBtn = document.getElementById('sfLibraryModalClose');
            const cancelBtn = document.getElementById('sfLibraryCancelBtn');
            const modal = document.getElementById('sfImageLibraryModal');

            if (closeBtn) {
                closeBtn.addEventListener('click', () => self.closeModal());
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => self.closeModal());
            }

            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) self.closeModal();
                });
            }

            // Valitse kuva
            const selectBtn = document.getElementById('sfLibrarySelectBtn');
            if (selectBtn) {
                selectBtn.addEventListener('click', () => self.confirmSelection());
            }

            // Kategoria-suodattimet
            document.querySelectorAll('.sf-library-cat-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.sf-library-cat-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    self.loadImages(this.dataset.category);
                });
            });

            // ESC sulkee
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    self.closeModal();
                }
            });

            // File upload ja poisto
            [1, 2, 3].forEach(function (slot) {
                const fileInput = document.getElementById('sf-image' + slot);
                if (fileInput) {
                    fileInput.addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (!file) return;

                        const reader = new FileReader();
                        reader.onload = function (ev) {
                            const thumb = document.getElementById('sfImageThumb' + slot);
                            if (thumb) thumb.src = ev.target.result;

                            const removeBtn = document.querySelector('.sf-image-remove-btn[data-slot="' + slot + '"]');
                            if (removeBtn) removeBtn.classList.remove('hidden');

                            const hiddenInput = document.getElementById('sfLibraryImage' + slot);
                            if (hiddenInput) hiddenInput.value = '';

                            self.updatePreview(slot, ev.target.result);
                        };

                        reader.readAsDataURL(file);
                    });
                }

                const removeBtn = document.querySelector('.sf-image-remove-btn[data-slot="' + slot + '"]');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const fileInput = document.getElementById('sf-image' + slot);
                        if (fileInput) fileInput.value = '';

                        const hiddenInput = document.getElementById('sfLibraryImage' + slot);
                        if (hiddenInput) hiddenInput.value = '';

                        const thumb = document.getElementById('sfImageThumb' + slot);
                        if (thumb) thumb.src = thumb.dataset.placeholder || '';

                        this.classList.add('hidden');

                        self.updatePreview(slot, thumb ? thumb.dataset.placeholder : '');
                    });
                }
            });
        },

        openModal: function (slot) {
            this.currentSlot = slot;
            this.selectedImage = null;

            const modal = document.getElementById('sfImageLibraryModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            this.updateSelectedInfo();
            this.loadImages('all');
        },

        closeModal: function () {
            const modal = document.getElementById('sfImageLibraryModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }

            this.currentSlot = null;
            this.selectedImage = null;
        },

        loadImages: function (category) {
            const self = this;
            const grid = document.getElementById('sfLibraryGrid');
            const loading = document.getElementById('sfLibraryLoading');
            const empty = document.getElementById('sfLibraryEmpty');

            if (!grid) return;

            grid.innerHTML = '';
            loading?.classList.remove('hidden');
            empty?.classList.add('hidden');

            const url = this.baseUrl + '/app/api/get_library_images.php?category=' + encodeURIComponent(category);

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    loading?.classList.add('hidden');

                    if (data.success && data.images.length > 0) {
                        self.images = data.images;
                        self.renderImages(data.images);
                    } else {
                        empty?.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    loading?.classList.add('hidden');
                    empty?.classList.remove('hidden');
                });
        },

        renderImages: function (images) {
            const self = this;
            const grid = document.getElementById('sfLibraryGrid');
            if (!grid) return;

            grid.innerHTML = '';

            images.forEach(function (img) {
                const item = document.createElement('div');
                item.className = 'sf-library-grid-item';
                item.dataset.id = img.id;
                item.dataset.filename = img.filename;
                item.dataset.title = img.title;
                item.dataset.url = img.url;

                item.innerHTML = `
                    <div class="sf-library-grid-thumb">
                        <img src="${self.baseUrl}${img.url}" alt="${self.escapeHtml(img.title)}" loading="lazy">
                    </div>
                    <div class="sf-library-grid-info">
                        <span class="sf-library-grid-title">${self.escapeHtml(img.title)}</span>
                        <span class="sf-library-grid-category">${self.escapeHtml(img.category)}</span>
                    </div>
                    <div class="sf-library-grid-check">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                `;

                item.addEventListener('click', () => self.selectImage(img, item));
                grid.appendChild(item);
            });
        },

        selectImage: function (image, element) {
            document.querySelectorAll('.sf-library-grid-item.selected')
                .forEach(el => el.classList.remove('selected'));

            element.classList.add('selected');
            this.selectedImage = image;

            this.updateSelectedInfo();
        },

        updateSelectedInfo: function () {
            const selectBtn = document.getElementById('sfLibrarySelectBtn');
            const selectedText = document.getElementById('sfLibrarySelectedText');
            const selectedThumb = document.getElementById('sfLibrarySelectedThumb');
            const selectedName = document.getElementById('sfLibrarySelectedName');

            if (this.selectedImage) {
                selectBtn.disabled = false;
                selectedText.classList.remove('hidden');
                selectedThumb.src = this.baseUrl + this.selectedImage.url;
                selectedName.textContent = this.selectedImage.title;
            } else {
                selectBtn.disabled = true;
                selectedText.classList.add('hidden');
            }
        },

        confirmSelection: function () {
            if (!this.selectedImage || !this.currentSlot) return;

            const slot = this.currentSlot;
            const image = this.selectedImage;

            const thumb = document.getElementById('sfImageThumb' + slot);
            if (thumb) thumb.src = this.baseUrl + image.url;

            const removeBtn = document.querySelector('.sf-image-remove-btn[data-slot="' + slot + '"]');
            if (removeBtn) removeBtn.classList.remove('hidden');

            const hiddenInput = document.getElementById('sfLibraryImage' + slot);
            if (hiddenInput) hiddenInput.value = image.filename;

            const fileInput = document.getElementById('sf-image' + slot);
            if (fileInput) fileInput.value = '';

            this.updatePreview(slot, this.baseUrl + image.url);
            this.closeModal();

            const card = document.querySelector('.sf-image-upload-card[data-slot="' + slot + '"]');
            if (card) {
                card.classList.add('sf-image-selected');
                setTimeout(() => card.classList.remove('sf-image-selected'), 600);
            }
        },

        updatePreview: function (slot, imageUrl) {
            const previewImg = document.getElementById('sfPreviewImg' + slot);
            if (previewImg) previewImg.src = imageUrl;

            const previewImgGreen = document.getElementById('sfPreviewImg' + slot + 'Green');
            if (previewImgGreen) previewImgGreen.src = imageUrl;

            const uploadPreview = document.getElementById('sf-upload-preview' + slot);
            if (uploadPreview) uploadPreview.src = imageUrl;

            if (global.Preview?.applyGridClass) Preview.applyGridClass();
            if (global.PreviewTutkinta?.applyGridClass) PreviewTutkinta.applyGridClass();
        },

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };

    global.ImageLibrary = ImageLibrary;

})(window);