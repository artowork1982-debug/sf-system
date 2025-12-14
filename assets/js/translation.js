(function () {
    'use strict';

    function getConfig() {
        return {
            baseUrl: window.SF_BASE_URL || '',
            flashData: window.SF_FLASH_DATA || {},
            supportedLangs: window.SF_SUPPORTED_LANGS || {}
        };
    }

    let currentTargetLang = '';

    window.sfAddTranslation = function (el) {
        if (!el) return;

        const config = getConfig();
        currentTargetLang = el.getAttribute('data-lang');
        if (!currentTargetLang) return;

        const langInput = document.getElementById('translationTargetLang');
        if (langInput) langInput.value = currentTargetLang;

        const langData = config.supportedLangs[currentTargetLang];
        const langDisplay = document.getElementById('translationLangDisplay');
        if (langDisplay && langData) {
            langDisplay.innerHTML =
                '<img src="' +
                config.baseUrl +
                '/assets/img/' +
                langData.icon +
                '" alt="' +
                langData.label +
                '">' +
                '<span>' +
                langData.label +
                '</span>';
        }

        const fields = [
            'translationTitleShort',
            'translationDescription',
            'translationRootCauses',
            'translationActions'
        ];

        fields.forEach(function (id) {
            const field = document.getElementById(id);
            if (field) field.value = '';
        });

        updateCharCount('translationTitleShort', 'titleCharCount');
        updateCharCount('translationDescription', 'descCharCount');

        const statusEl = document.getElementById('translationStatus');
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.className = 'sf-translation-status';
        }

        showStep(1);

        const modal = document.getElementById('modalTranslation');
        if (modal) modal.classList.remove('hidden');
    };

    function scalePreviewCard() {
        const container = document.getElementById('sfTranslationPreviewContainer');
        const card = document.getElementById('sfPreviewCard');

        if (!container || !card) return;

        requestAnimationFrame(function () {
            const containerRect = container.getBoundingClientRect();
            const containerWidth = containerRect.width;

            if (containerWidth <= 0) {
                setTimeout(scalePreviewCard, 100);
                return;
            }

            const cardWidth = 1920;
            const cardHeight = 1080;
            const scale = containerWidth / cardWidth;

            card.style.transform = 'scale(' + scale + ')';
            card.style.transformOrigin = 'top left';
            card.setAttribute('data-original-scale', scale); // LISÄTTY:  Tallenna skaalaus

            const scaledHeight = Math.round(cardHeight * scale);

            if (!CSS.supports('aspect-ratio', '16 / 9')) {
                container.style.height = scaledHeight + 'px';
            }
        });
    }

    function showStep(step) {
        const step1 = document.getElementById('translationStep1');
        const step2 = document.getElementById('translationStep2');

        if (step === 1) {
            if (step1) step1.classList.remove('hidden');
            if (step2) step2.classList.add('hidden');
        } else {
            if (step1) step1.classList.add('hidden');
            if (step2) step2.classList.remove('hidden');

            setTimeout(function () {
                scalePreviewCard();
            }, 50);
        }
    }

    function updateCharCount(inputId, countId) {
        const input = document.getElementById(inputId);
        const count = document.getElementById(countId);
        if (input && count) {
            count.textContent = input.value.length;
        }
    }

    // --- helpers: reliable preview capture (avoid scaled/clipped DOM in modal) ---
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function waitForFontsReady() {
        try {
            if (document.fonts && document.fonts.ready) {
                await document.fonts.ready;
            }
        } catch (e) {
            // ignore
        }
    }

    function waitForImages(rootEl, timeoutMs = 8000) {
        const imgs = Array.from(rootEl.querySelectorAll('img'));
        if (!imgs.length) return Promise.resolve();

        const waitOne = (img) => new Promise(resolve => {
            if (img.complete && img.naturalWidth > 0) return resolve();

            let done = false;
            const finish = () => {
                if (done) return;
                done = true;
                img.removeEventListener('load', finish);
                img.removeEventListener('error', finish);
                resolve();
            };

            const timer = setTimeout(finish, timeoutMs);

            img.addEventListener('load', () => { clearTimeout(timer); finish(); }, { once: true });
            img.addEventListener('error', () => { clearTimeout(timer); finish(); }, { once: true });
        });

        return Promise.all(imgs.map(waitOne));
    }

    async function capturePreviewCardAsJpeg(previewCard, width = 1920, height = 1080) {
        if (!previewCard || !window.html2canvas) return '';

        // Clone so we can capture at full resolution (no modal scaling / no clipping)
        const clone = previewCard.cloneNode(true);
        clone.style.position = 'fixed';
        clone.style.left = '-100000px';
        clone.style.top = '0';
        clone.style.zIndex = '-1';
        clone.style.transform = 'none';
        clone.style.transformOrigin = 'top left';
        clone.style.width = width + 'px';
        clone.style.height = height + 'px';

        document.body.appendChild(clone);

        try {
            await Promise.all([
                waitForFontsReady(),
                waitForImages(clone, 15000),
                sleep(50)
            ]);

            const canvas = await html2canvas(clone, {
                scale: 1,
                useCORS: true,
                allowTaint: false,
                backgroundColor: '#ffffff',
                imageTimeout: 15000,
                width: width,
                height: height,
                windowWidth: width,
                windowHeight: height
            });

            return canvas.toDataURL('image/jpeg', 0.92);
        } finally {
            clone.remove();
        }
    }

    function updatePreview() {
        const config = getConfig();
        const data = config.flashData;

        const titleEl = document.getElementById('sfPreviewTitle');
        const descEl = document.getElementById('sfPreviewDesc');
        const siteEl = document.getElementById('sfPreviewSite');
        const dateEl = document.getElementById('sfPreviewDate');
        const siteLabelEl = document.getElementById('sfPreviewSiteLabel');
        const dateLabelEl = document.getElementById('sfPreviewDateLabel');
        const bgEl = document.getElementById('sfPreviewBg');
        const card = document.getElementById('sfPreviewCard');

        const titleInput = document.getElementById('translationTitleShort');
        const descInput = document.getElementById('translationDescription');

        // Päivitä tekstit käännöslomakkeesta
        if (titleEl && titleInput) {
            titleEl.textContent = titleInput.value || 'Otsikko... ';
        }
        if (descEl && descInput) {
            descEl.textContent = descInput.value || 'Kuvaus... ';
        }

        // Työmaa ja päivämäärä alkuperäisestä
        if (siteEl) {
            const siteText = [data.site, data.site_detail].filter(Boolean).join(' – ');
            siteEl.textContent = siteText || '–';
        }

        if (dateEl && data.occurred_at) {
            // MySQL DATETIME => tee siitä selaimelle varma ISO‑muoto
            const raw = String(data.occurred_at);
            const isoGuess = raw.includes(' ') ? raw.replace(' ', 'T') : raw;
            const d = new Date(isoGuess);

            if (!isNaN(d.getTime())) {
                dateEl.textContent = d.toLocaleString('fi-FI', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }

        const flashType = data.type || 'yellow';

        // TÄRKEÄ: Päivitä taustakuva kohdekielen mukaan
        if (bgEl && currentTargetLang) {
            bgEl.src =
                config.baseUrl +
                '/assets/img/templates/SF_bg_' +
                flashType +
                '_' +
                currentTargetLang +
                '.jpg';
        }

        // Päivitä grid-tyyli - AUTOMAATTINEN kuvien määrän mukaan
        if (card) {
            card.setAttribute('data-type', flashType);
            card.setAttribute('data-lang', currentTargetLang);

            // Laske montako kuvaa on
            let imageCount = 0;
            if (data.image_main) imageCount++;
            if (data.image_2) imageCount++;
            if (data.image_3) imageCount++;

            // Valitse grid-tyyli kuvien määrän JA tallennetun tyylin mukaan
            let gridStyle;
            const savedStyle = data.grid_style || '';

            if (imageCount <= 1) {
                gridStyle = 'grid-main-only';
            } else if (imageCount === 2) {
                if (savedStyle === 'grid-2-stacked' || savedStyle === 'grid-2-overlay') {
                    gridStyle = savedStyle;
                } else {
                    gridStyle = 'grid-2-overlay';
                }
            } else {
                if (savedStyle === 'grid-3-main-top' || savedStyle === 'grid-3-overlay') {
                    gridStyle = savedStyle;
                } else {
                    gridStyle = 'grid-3-main-top';
                }
            }

            // Poista vanhat grid-luokat
            card.className = card.className.replace(/grid-\S+/g, '').trim();

            // Lisää oikeat luokat
            if (!card.classList.contains('sf-preview-card')) {
                card.classList.add('sf-preview-card');
            }
            card.classList.add(gridStyle);

            // Varmista kortin koko
            card.style.width = '1920px';
            card.style.height = '1080px';
        }

               // Labelit kohdekielen mukaan
        const labelTranslations = {
            fi: { site: 'Työmaa:', date: 'Milloin?' },
            en: { site: 'Worksite:', date: 'When?' },
            sv: { site: 'Arbetsplats:', date: 'När?' },
            el: { site: 'Εργοτάξιο:', date: 'Πότε;' },
            it: { site: 'Cantiere:', date: 'Quando?' }
        };

        const langLabels = labelTranslations[currentTargetLang] || labelTranslations.fi;
        if (siteLabelEl) siteLabelEl.textContent = langLabels.site;
        if (dateLabelEl) dateLabelEl.textContent = langLabels.date;

        // Kuvat - käytä PHP:n muodostamia URL:eja (sisältää oikeat polut)
        var slots = [1, 2, 3];
        var imageUrlFields = ['image_main_url', 'image_2_url', 'image_3_url'];
        var transformFields = ['image1_transform', 'image2_transform', 'image3_transform'];

        slots.forEach(function (slot, index) {
            var img = document.getElementById('sfPreviewImg' + slot);
            if (!img) return;

            // Käytä valmiita URL:eja (PHP on jo tarkistanut polut)
            var imageUrl = data[imageUrlFields[index]];

            if (imageUrl && imageUrl !== '') {
                img.src = imageUrl;
            }
            // Jos URL puuttuu, käytä PHP:n renderöimää oletuskuvaa (placeholder)

            // Käytä alkuperäisen kuvan muunnoksia
            var transformStr = data[transformFields[index]];
            if (transformStr && transformStr.trim() !== '') {
                try {
                    var t = JSON.parse(transformStr);
                    img.style.position = 'absolute';
                    img.style.top = '50%';
                    img.style.left = '50%';
                    img.style.transform =
                        'translate(calc(-50% + ' + (t.x || 0) + 'px), ' +
                        'calc(-50% + ' + (t.y || 0) + 'px)) ' +
                        'scale(' + (t.scale || 1) + ')';
                } catch (e) {
                    console.warn('Transform parse error:', e);
                }
            }
        });

        // Skaalaa kortti uudelleen
        setTimeout(scalePreviewCard, 10);
    }

    function debounce(func, wait) {
        let timeout;
        return function () {
            clearTimeout(timeout);
            timeout = setTimeout(func, wait);
        };
    }

    function init() {
        const titleInput = document.getElementById('translationTitleShort');
        const descInput = document.getElementById('translationDescription');

        if (titleInput) {
            titleInput.addEventListener('input', function () {
                updateCharCount('translationTitleShort', 'titleCharCount');
            });
        }
        if (descInput) {
            descInput.addEventListener('input', function () {
                updateCharCount('translationDescription', 'descCharCount');
            });
        }

        const btnToStep2 = document.getElementById('btnToStep2');
        if (btnToStep2) {
            btnToStep2.addEventListener('click', function () {
                const titleVal =
                    (document.getElementById('translationTitleShort').value || '').trim();
                const descVal =
                    (document.getElementById('translationDescription').value || '').trim();

                if (!titleVal || !descVal) {
                    alert('Täytä pakolliset kentät (otsikko ja kuvaus).');
                    return;
                }

                updatePreview();
                showStep(2);
            });
        }

        const btnBack = document.getElementById('btnBackToStep1');
        if (btnBack) {
            btnBack.addEventListener('click', function () {
                showStep(1);
            });
        }

        const debouncedScale = debounce(function () {
            const step2 = document.getElementById('translationStep2');
            if (step2 && !step2.classList.contains('hidden')) {
                scalePreviewCard();
            }
        }, 150);

        window.addEventListener('resize', debouncedScale);

        const saveBtn = document.getElementById('btnSaveTranslation');
        if (saveBtn) {
            saveBtn.addEventListener('click', async function () {
                const config = getConfig();
                const statusEl = document.getElementById('translationStatus');

                const titleShort =
                    (document.getElementById('translationTitleShort').value || '').trim();
                const description =
                    (document.getElementById('translationDescription').value || '').trim();
                const targetLang =
                    document.getElementById('translationTargetLang').value;
                const rootCausesEl = document.getElementById('translationRootCauses');
                const actionsEl = document.getElementById('translationActions');
                const rootCauses = rootCausesEl ? rootCausesEl.value : '';
                const actions = actionsEl ? actionsEl.value : '';

                saveBtn.disabled = true;
                if (statusEl) {
                    statusEl.textContent = 'Generoidaan kuvaa...';
                    statusEl.className = 'sf-translation-status loading';
                }

                try {
                    const previewCard = document.getElementById('sfPreviewCard');
                    let previewDataUrl = '';

                    if (previewCard && window.html2canvas) {
                        // Varmista että preview on ajan tasalla ennen kaappausta
                        updatePreview();

                        // TÄRKEÄ: Poista CSS-scaling ennen capture jotta fontit ja kuvat renderoituvät oikean kokoisiksi
                        const originalTransform = previewCard.style.transform;
                        const container = document.getElementById('sfTranslationPreviewContainer');
                        const originalContainerHeight = container ? container.style.height : '';
                        const originalContainerOverflow = container ? container.style.overflow : '';

                        // Poista skaalaus ja näytä full-size
                        previewCard.style.transform = 'none';
                        previewCard.style.width = '1920px';
                        previewCard.style.height = '1080px';

                        if (container) {
                            container.style.height = '1080px';
                            container.style.overflow = 'visible';
                        }

                        if (window.Preview && window.Preview.hideHandlesForCapture) {
                            window.Preview.hideHandlesForCapture();
                        }

                        try {
                            // Odota että DOM päivittyy
                            await new Promise(resolve => setTimeout(resolve, 100));

                            previewDataUrl = await capturePreviewCardAsJpeg(previewCard, 1920, 1080);
                        } finally {
                            // Palauta alkuperäinen skaalaus jotta modal näyttää oikeilta
                            previewCard.style.transform = originalTransform;

                            if (container) {
                                container.style.height = originalContainerHeight;
                                container.style.overflow = originalContainerOverflow;
                            }

                            // Skaalaa modal-preview uudelleen
                            setTimeout(scalePreviewCard, 10);
                        }

                        if (window.Preview && window.Preview.restoreHandlesForCapture) {
                            window.Preview.restoreHandlesForCapture();
                        }
                    }

                    if (statusEl) {
                        statusEl.textContent = 'Tallennetaan...';
                    }

                    const formData = new FormData();
                    formData.append('source_id', config.flashData.id);
                    formData.append('target_lang', targetLang);
                    formData.append('title_short', titleShort);
                    formData.append('description', description);
                    formData.append('root_causes', rootCauses);
                    formData.append('actions', actions);
                    formData.append('preview_image_data', previewDataUrl);

                    const response = await fetch(
                        config.baseUrl + '/app/api/create_language_version.php',
                        {
                            method: 'POST',
                            body: formData
                        }
                    );

                    const responseText = await response.text();
                    let result;

                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        console.error('Invalid JSON:', responseText);
                        throw new Error('Palvelinvirhe. Tarkista PHP-loki.');
                    }

                    if (result.success) {
                        if (statusEl) {
                            statusEl.textContent = 'Kieliversio luotu!';
                            statusEl.className = 'sf-translation-status success';
                        }
                        setTimeout(function () {
                            if (result.redirect) {
                                window.location.href = result.redirect;
                            } else {
                                window.location.reload();
                            }
                        }, 1500);
                    } else {
                        throw new Error(result.error || 'Tuntematon virhe');
                    }
                } catch (err) {
                    console.error('Save error:', err);
                    if (statusEl) {
                        statusEl.textContent = 'Virhe: ' + err.message;
                        statusEl.className = 'sf-translation-status error';
                    }
                    scalePreviewCard();
                } finally {
                    saveBtn.disabled = false;
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();