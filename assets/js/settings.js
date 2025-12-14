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

    // --- Tiny toast helper (works even when header is not reloaded) ---
    function showToast(message, type = "success") {
        // if you already have a global toast helper, use it
        if (typeof window.sfToast === "function") {
            window.sfToast(type, message);
            return;
        }

        // fallback: create a simple toast
        let t = document.getElementById("toastNotice");
        if (!t) {
            t = document.createElement("div");
            t.id = "toastNotice";
            t.className = "sf-toast";
            document.body.appendChild(t);
        }

        t.classList.remove("hide");
        t.dataset.type = type;
        t.textContent = message;

        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(() => {
            t.classList.add("hide");
        }, 3500);
    }

    // Map notice codes -> readable messages if header terms are not rendered
    // (You can extend these if you want; works even without terms system here.)
    function noticeToMessage(code) {
        const map = {
            worksite_added: "Työmaa lisätty.",
            worksite_enabled: "Työmaa aktivoitu.",
            worksite_disabled: "Työmaa passivoitu.",
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
        const isPostish = ((form.method || "POST").toUpperCase() !== "GET");
        const isActionEndpoint =
            u.pathname.includes("/app/actions/") ||
            u.pathname.includes("/app/api/");

        return isPostish && isActionEndpoint;
    }

    // AJAX-submit settingsin sisällä (worksites, users, image library, etc.)
    async function handleSettingsAjaxSubmit(form) {
        const pageRoot = document.querySelector(".sf-settings-page");
        if (!pageRoot) return;

        const contentEl = pageRoot.querySelector(".sf-tabs-content");
        if (contentEl) contentEl.classList.add("sf-tab-loading");

        try {
            const actionUrl = new URL(form.action, window.location.origin).toString();

            const res = await fetch(actionUrl, {
                method: (form.method || "POST").toUpperCase(),
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "fetch",
                    // prefer json, but accept html too
                    Accept: "application/json,text/html,*/*",
                },
                body: new FormData(form),
                redirect: "follow",
            });

            // If the backend returns redirect, fetch will follow it and res.url will be the final URL.
            // But we still want to update the current tab content, not navigate away.

            const ct = (res.headers.get("content-type") || "").toLowerCase();
            let notice = "";
            let message = "";
            let type = "success";

            if (!res.ok) {
                // try to parse json error
                if (ct.includes("application/json")) {
                    const data = await res.json().catch(() => null);
                    message = (data && (data.error || data.message)) || "Toiminto epäonnistui.";
                } else {
                    message = "Toiminto epäonnistui.";
                }
                type = "error";
                showToast(message, type);
                // keep current tab as-is
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
                // If backend redirected to ...&notice=xyz, capture it from res.url
                try {
                    const finalUrl = new URL(res.url, window.location.origin);
                    notice = finalUrl.searchParams.get("notice") || "";
                    message = noticeToMessage(notice) || "";
                } catch {
                    // ignore
                }
            }

            // Show toast even though header isn't reloaded
            if (message) showToast(message, "success");

            // Ensure URL stays consistent (so refresh/back includes the correct tab)
            const urlWithTab = replaceUrlPreserveTab(window.location.href);

            // Reload current tab content so list/rows update immediately
            await loadSettingsTab(urlWithTab, { pushState: false });
        } catch (e) {
            console.error("Settings AJAX submit failed:", e);
            // fallback: allow normal submit to go through
            try {
                form.submit();
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

                // Only handle relevant POST forms (actions/api), and allow opt-out
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