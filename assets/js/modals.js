// assets/js/modals.js
// Varmatoiminen modaaliohjaus smooth/PJAX-sivunvaihdossa
// - event delegation (ei kuole DOM-vaihdoissa)
// - sulje: X-nappi / peruuta / overlay / Esc
// - avaa: [data-modal-open="#id"] tai href="#id" (jos käytät sitä)

(function () {
    "use strict";

    // APU: etsi lähin modal-elementti
    function findModalFromEl(el) {
        if (!el) return null;
        return el.closest(".sf-modal, .modal, [data-modal]") || null;
    }

    // APU: hae modal id:stä
    function getModalBySelector(sel) {
        if (!sel) return null;
        try {
            // hyväksyy "#myModal" tai "myModal"
            const id = sel.startsWith("#") ? sel : "#" + sel;
            return document.querySelector(id);
        } catch (e) {
            return null;
        }
    }

    function lockBodyScroll() {
        document.documentElement.classList.add("sf-modal-open");
        document.body.classList.add("sf-modal-open");
    }

    function unlockBodyScroll() {
        document.documentElement.classList.remove("sf-modal-open");
        document.body.classList.remove("sf-modal-open");
    }

    function show(modal) {
        if (!modal) return;

        // jos käytät hidden-luokkaa
        modal.classList.remove("hidden");
        modal.style.display = ""; // jos joku inline display:none oli

        modal.setAttribute("aria-hidden", "false");
        lockBodyScroll();

        // fokus ensimmäiseen inputtiin/buttoniin jos löytyy
        const focusable = modal.querySelector(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusable) {
            setTimeout(() => {
                try { focusable.focus(); } catch (_) { }
            }, 0);
        }
    }

    function hide(modal) {
        if (!modal) return;

        modal.classList.add("hidden");
        modal.setAttribute("aria-hidden", "true");

        // jos sinulla on useita modaaleja auki, älä avaa scrollia ennen kuin viimeinen suljettu
        const anyOpen = document.querySelector(".sf-modal:not(.hidden), .modal:not(.hidden), [data-modal]:not(.hidden)");
        if (!anyOpen) unlockBodyScroll();
    }

    // Sulje kaikki
    function hideAll() {
        document.querySelectorAll(".sf-modal, .modal, [data-modal]").forEach((m) => hide(m));
        unlockBodyScroll();
    }

    // Julkiset apufunktiot (jos joku muu koodi kutsuu näitä)
    window.sfOpenModal = function (idOrSelector) {
        const m = getModalBySelector(idOrSelector);
        show(m);
    };

    window.sfCloseModal = function (idOrSelector) {
        const m = idOrSelector ? getModalBySelector(idOrSelector) : null;
        if (m) hide(m);
        else {
            // sulje lähin näkyvä
            const open = document.querySelector(".sf-modal:not(.hidden), .modal:not(.hidden), [data-modal]:not(.hidden)");
            if (open) hide(open);
        }
    };

    // ====== CLICK DELEGATION ======
    document.addEventListener("click", (e) => {
        const t = e.target;

        // 1) Avaa modaalinappi: data-modal-open
        const openBtn = t.closest("[data-modal-open]");
        if (openBtn) {
            const sel = openBtn.getAttribute("data-modal-open");
            const modal = getModalBySelector(sel);
            if (modal) {
                e.preventDefault();
                e.stopPropagation();
                show(modal);
            }
            return;
        }

        // 2) Avaa jos linkki href="#modalId" ja linkillä on esim. .sf-open-modal
        const openLink = t.closest('a[href^="#"].sf-open-modal, a[href^="#"][data-open-modal]');
        if (openLink) {
            const href = openLink.getAttribute("href");
            const modal = getModalBySelector(href);
            if (modal) {
                e.preventDefault();
                e.stopPropagation();
                show(modal);
            }
            return;
        }

        // 3) Sulje-napit: data-modal-close / .sf-modal-close / .modal-close
        const closeBtn = t.closest("[data-modal-close], .sf-modal-close, .modal-close");
        if (closeBtn) {
            const modal = findModalFromEl(closeBtn) || getModalBySelector(closeBtn.getAttribute("data-modal-close"));
            e.preventDefault();
            e.stopPropagation();
            hide(modal);
            return;
        }

        // 4) Peruuta-nappi (yleinen)
        const cancelBtn = t.closest(".sf-btn-cancel, .btn-cancel, [data-cancel]");
        if (cancelBtn) {
            // jos nappi on modaalissa, sulje se
            const modal = findModalFromEl(cancelBtn);
            if (modal) {
                e.preventDefault();
                e.stopPropagation();
                hide(modal);
            }
            return;
        }

        // 5) Klikkaus overlay-taustaan sulkee
        // Tyypillinen rakenne: <div class="sf-modal"> <div class="sf-modal-content">...</div></div>
        const overlay = t.closest(".sf-modal, .modal, [data-modal]");
        if (overlay) {
            const content = t.closest(".sf-modal-content, .modal-content, [data-modal-content]");
            // jos klikattiin overlayhin (ei sisältöön), sulje
            if (!content) {
                // sulje vain jos overlay on oikeasti näkyvä
                if (!overlay.classList.contains("hidden")) {
                    e.preventDefault();
                    hide(overlay);
                }
            }
        }
    }, true); // capture=true varmistaa toiminnan vaikka joku muu stopPropagation()

    // ====== ESC sulkee ======
    document.addEventListener("keydown", (e) => {
        if (e.key !== "Escape") return;
        const open = document.querySelector(".sf-modal:not(.hidden), .modal:not(.hidden), [data-modal]:not(.hidden)");
        if (open) {
            e.preventDefault();
            hide(open);
        }
    });

    // ====== Smooth-navigaatio: varmistus ettei “jää auki” ======
    // Kun sivu vaihtuu, sulje modaalit ja vapauta scroll.
    window.addEventListener("sf:pagechange", () => {
        hideAll();
    });

    // iOS/Safari BFCache: varmistus
    window.addEventListener("pageshow", () => {
        // jos jokin jäi kummittelemaan, suljetaan
        hideAll();
    });
})();