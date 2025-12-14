// assets/js/settings.js
console.log("settings.js loaded");

(function () {
    let controller = null;

    function isSettingsPage() {
        return (
            document.body?.dataset?.page === "settings" ||
            !!document.querySelector(".sf-settings-page")
        );
    }

    function getTabFromUrl(url) {
        try {
            const u = new URL(url, window.location.origin);
            return u.searchParams.get("tab") || "users";
        } catch {
            return "users";
        }
    }

    function setActiveTab(tabsEl, tabName) {
        if (!tabsEl) return;
        tabsEl.querySelectorAll(".sf-tab").forEach((a) => {
            const href = a.getAttribute("href") || "";
            const isActive = href.includes("tab=" + encodeURIComponent(tabName));
            a.classList.toggle("active", isActive);
        });
    }

    function forceClearLoadingState() {
        const pageRoot = document.querySelector(".sf-settings-page");
        const contentEl = pageRoot?.querySelector(".sf-tabs-content");
        if (contentEl) contentEl.classList.remove("sf-tab-loading");
        document.body.classList.remove("sf-loading", "sf-loading-long");
    }

    // --- Korjattu toast helper ---
    function showToast(message, type = "success") {
        // if you already have a global toast helper, use it
        if (typeof window.sfToast === "function") {
            window.sfToast(type, message);
            return;
        }

        // Poista mahdollinen olemassa oleva toast ensin
        const existingToast = document.getElementById("toastNotice");
        if (existingToast) {
            existingToast.remove();
        }

        // Luo uusi toast-elementti
        const t = document.createElement("div");
        t.id = "toastNotice";

        // Määritä oikea CSS-luokka tyypin mukaan
        const typeClass = type === "error" ? "sf-toast-danger" : "sf-toast-" + type;
        t.className = "sf-toast " + typeClass;

        // Luo ikoni tyypin mukaan
        let iconSvg = "";
        if (type === "success") {
            iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>`;
        } else if (type === "error") {
            iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>`;
        } else {
            iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>`;
        }

        t.innerHTML = `
            <div class="sf-toast-icon">${iconSvg}</div>
            <span class="sf-toast-text">${escapeHtml(message)}</span>
            <button class="sf-toast-close" type="button" onclick="this.parentElement.remove();">×</button>
        `;

        document.body.appendChild(t);

        // Piilota toast automaattisesti 4 sekunnin jälkeen
        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(() => {
            if (t && t.parentElement) {
                t.classList.add("sf-toast-hide");
                setTimeout(() => {
                    if (t && t.parentElement) t.remove();
                }, 300);
            }
        }, 4000);
    }

    // Helper: escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    // Map notice codes -> readable messages if header terms are not rendered
    // (You can extend these if you want; works even without terms system here.)
    function noticeToMessage(code) {
        const map = {
            worksite_added: "Työmaa lisätty.",
            worksite_enabled: "Työmaa aktivoitu.",
            worksite_disabled: "Työmaa passivoitu.",
            image_added: "Kuva lisätty kuvapankkiin.",
            image_deleted: "Kuva poistettu kuvapankista.",
            image_toggled: "Kuvan näkyvyys muutettu.",
            error: "Toiminto epäonnistui.",
        };
        return map[code] || "";
    }

    function replaceUrlPreserveTab(url) {
        // Keep current tab in URL (so refresh/back works nicely)
        try {
            const u = new URL(url, window.location.origin);
            const current = new URL(window.location.href);
            if (!u.searchParams.get("tab") && current.searchParams.get("tab")) {
                u.searchParams.set("tab", current.searchParams.get("tab"));
            }
            history.replaceState({ sfSettings: true }, "", u.toString());
            return u.toString();
        } catch {
            return url;
        }
    }

    async function loadSettingsTab(url, { pushState = true } = {}) {
        const pageRoot = document.querySelector(".sf-settings-page");
        if (!pageRoot) return;

        const tabsEl = pageRoot.querySelector(".sf-tabs");
        const contentEl = pageRoot.querySelector(".sf-tabs-content");
        if (!tabsEl || !contentEl) return;

        if (controller) controller.abort();
        controller = new AbortController();

        const tabName = getTabFromUrl(url);
        setActiveTab(tabsEl, tabName);
        contentEl.classList.add("sf-tab-loading");

        try {
            const res = await fetch(url, {
                method: "GET",
                credentials: "same-origin",
                signal: controller.signal,
                headers: {
                    "X-Requested-With": "fetch",
                    Accept: "text/html",
                },
            });

            if (!res.ok) throw new Error("HTTP " + res.status);
            const html = await res.text();

            const doc = new DOMParser().parseFromString(html, "text/html");
            const newContent = doc.querySelector(".sf-settings-page .sf-tabs-content");

            if (!newContent) {
                window.location.href = url;
                return;
            }

            contentEl.innerHTML = newContent.innerHTML;

            // Suorita uuden sisällön inline-scriptit
            contentEl.querySelectorAll("script").forEach(function (oldScript) {
                var newScript = document.createElement("script");
                if (oldScript.src) {
                    newScript.src = oldScript.src;
                } else {
                    newScript.textContent = oldScript.textContent;
                }
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });

            if (pushState) {
                history.pushState({ sfSettings: true }, "", url);
            }

            window.dispatchEvent(
                new CustomEvent("sf:content:updated", {
                    detail: { page: "settings", tab: tabName, root: pageRoot },
                })
            );
        } catch (err) {
            if (err && err.name === "AbortError") return;
            console.error("Settings tab load failed:", err);
            window.location.href = url;
        } finally {
            contentEl.classList.remove("sf-tab-loading");
            forceClearLoadingState();
        }
    }

    // Detect whether we should AJAX-handle a form
    function shouldAjaxHandleForm(form) {
        if (!(form instanceof HTMLFormElement)) return false;

        // allow opt-out
        if (form.getAttribute("data-sf-ajax") === "0") return false;

        // must have action and be same-origin
        // HUOM: Käytä getAttribute() koska input name="action" ylikirjoittaa form.action propertyn
        const action = form.getAttribute("action") || "";
        if (!action) return false;

        let u;
        try {
            u = new URL(action, window.location.origin);
        } catch {
            return false;
        }
        if (u.origin !== window.location.origin) return false;

        // only handle POST-like actions inside settings (actions endpoints)
        // this prevents breaking unrelated forms
        // HUOM: Käytä getAttribute() koska input name="method" ylikirjoittaisi form.method propertyn
        const formMethod = form.getAttribute("method") || "POST";
        const isPostish = formMethod.toUpperCase() !== "GET";
        const isActionEndpoint =
            u.pathname.includes("/app/actions/") || u.pathname.includes("/app/api/");

        return isPostish && isActionEndpoint;
    }

    // AJAX-submit settingsin sisällä (worksites, users, image library, etc.)
    async function handleSettingsAjaxSubmit(form) {
        const pageRoot = document.querySelector(".sf-settings-page");
        if (!pageRoot) return;

        const contentEl = pageRoot.querySelector(".sf-tabs-content");
        if (contentEl) contentEl.classList.add("sf-tab-loading");

        try {
            // HUOM: Käytä getAttribute() koska input name="action" ylikirjoittaa form.action propertyn
            // ja input name="method" ylikirjoittaisi form.method propertyn
            const formAction = form.getAttribute("action") || "";
            const formMethod = form.getAttribute("method") || "POST";
            const actionUrl = new URL(formAction, window.location.origin).toString();

            const res = await fetch(actionUrl, {
                method: formMethod.toUpperCase(),
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "fetch",
                    // prefer json, but accept html too
                    Accept: "application/json,text/html,*/*",
                },
                body: new FormData(form),
                redirect: "follow",
            });

            const ct = (res.headers.get("content-type") || "").toLowerCase();
            let notice = "";
            let message = "";
            let type = "success";

            if (!res.ok) {
                if (ct.includes("application/json")) {
                    const data = await res.json().catch(() => null);
                    message = (data && (data.error || data.message)) || "Toiminto epäonnistui.";
                } else {
                    message = "Toiminto epäonnistui.";
                }
                type = "error";
                showToast(message, type);
                await loadSettingsTab(window.location.href, { pushState: false });
                return;
            }

            // Success path:
            if (ct.includes("application/json")) {
                const data = await res.json().catch(() => null);

                if (data && data.ok === false) {
                    message = data.error || "Toiminto epäonnistui.";
                    type = "error";
                    showToast(message, type);
                    await loadSettingsTab(window.location.href, { pushState: false });
                    return;
                }

                notice = (data && data.notice) || "";
                message = noticeToMessage(notice) || (data && data.message) || "";
            } else {
                // HTML/redirect case:
                try {
                    const finalUrl = new URL(res.url, window.location.origin);
                    notice = finalUrl.searchParams.get("notice") || "";
                    message = noticeToMessage(notice) || "";
                } catch {
                    // ignore
                }
            }

            if (message) showToast(message, "success");

            const urlWithTab = replaceUrlPreserveTab(window.location.href);
            await loadSettingsTab(urlWithTab, { pushState: false });
        } catch (e) {
            console.error("Settings AJAX submit failed:", e);
            // fallback: allow normal submit to go through
            // HUOM: Käytä HTMLFormElement.prototype.submit. call() koska input name="submit" 
            // ylikirjoittaisi form.submit() metodin
            try {
                HTMLFormElement.prototype.submit.call(form);
            } catch { }
        } finally {
            if (contentEl) contentEl.classList.remove("sf-tab-loading");
            forceClearLoadingState();
        }
    }

    function bindSettingsOnce() {
        if (!isSettingsPage()) return;

        const pageRoot = document.querySelector(".sf-settings-page");
        if (!pageRoot) return;

        // bind once
        if (pageRoot.dataset.sfSettingsBound === "1") {
            forceClearLoadingState();
            return;
        }
        pageRoot.dataset.sfSettingsBound = "1";

        const tabsEl = pageRoot.querySelector(".sf-tabs");
        if (tabsEl) {
            tabsEl.addEventListener("click", (e) => {
                const a = e.target.closest("a.sf-tab");
                if (!a) return;

                const href = a.getAttribute("href");
                if (!href) return;

                const u = new URL(href, window.location.origin);
                if (u.origin !== window.location.origin) return;

                e.preventDefault();
                loadSettingsTab(u.toString(), { pushState: true });
            });
        }

        // Delegoitu submit: toimii vaikka .sf-tabs-content vaihtuu innerHTML:llä
        pageRoot.addEventListener(
            "submit",
            (e) => {
                const form = e.target.closest("form");
                if (!form) return;

                if (!shouldAjaxHandleForm(form)) return;

                e.preventDefault();
                handleSettingsAjaxSubmit(form);
            },
            true
        );

        window.addEventListener("popstate", () => {
            if (!isSettingsPage()) return;
            loadSettingsTab(window.location.href, { pushState: false });
        });

        forceClearLoadingState();
    }

    document.addEventListener("DOMContentLoaded", bindSettingsOnce);
    window.addEventListener("sf:pagechange", bindSettingsOnce);

    window.addEventListener("pageshow", () => {
        if (!isSettingsPage()) return;
        forceClearLoadingState();
        bindSettingsOnce();
    });
})();