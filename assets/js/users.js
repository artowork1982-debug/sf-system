console.log("users.js loaded");

/**
 * Käyttäjä-välilehden init
 * - toimii sekä normaalilla latauksella
 * - että settings-tabin AJAX-vaihdon jälkeen
 */
function sfInitUsersTab(root = document) {
    const page = root.querySelector(".sf-settings-page");
    const container = page?.querySelector(".sf-tabs-content");
    if (!container) return;

    // Estä tuplabindaukset, jos tab vaihdetaan monta kertaa
    if (container.dataset.sfUsersInit === "1") return;
    container.dataset.sfUsersInit = "1";

    const base =
        typeof SF_BASE_URL !== "undefined"
            ? SF_BASE_URL.replace(/\/+$/, "")
            : "";

    /* =========================
       MODAALIT & FORM
       ========================= */

    const userModal = document.getElementById("sfUserModal");
    const userForm = document.getElementById("sfUserForm");
    const userTitle = document.getElementById("sfUserModalTitle");

    const btnAdd = document.getElementById("sfUserAddBtn");
    const btnCancel = document.getElementById("sfUserCancel");

    const inputId = document.getElementById("sfUserId");
    const inputFirst = document.getElementById("sfUserFirst");
    const inputLast = document.getElementById("sfUserLast");
    const inputEmail = document.getElementById("sfUserEmail");
    const selectRole = document.getElementById("sfUserRole");
    const selectHomeWs = document.getElementById("sfUserHomeWorksite");
    const inputPass = document.getElementById("sfUserPassword");

    /* =========================
       LISÄÄ KÄYTTÄJÄ
       ========================= */
    if (btnAdd && userModal && userForm) {
        btnAdd.addEventListener("click", () => {
            userTitle.textContent = "Lisää käyttäjä";
            userForm.reset();
            inputId.value = "";
            if (selectHomeWs) selectHomeWs.value = "";
            inputPass.required = true;
            userModal.classList.remove("hidden");
        });
    }

    /* =========================
       PERUUTA
       ========================= */
    if (btnCancel && userModal) {
        btnCancel.addEventListener("click", () => {
            userModal.classList.add("hidden");
        });
    }

    /* ==================================================
       EVENT DELEGATION – EDIT / DELETE / RESET
       (TÄMÄ on se kriittinen osa AJAX-käytössä)
       ================================================== */
    container.addEventListener("click", (e) => {
        /* --- MUOKKAA --- */
        const editBtn = e.target.closest(".sf-edit-user");
        if (editBtn) {
            userTitle.textContent = "Muokkaa käyttäjää";

            inputId.value = editBtn.dataset.id || "";
            inputFirst.value = editBtn.dataset.first || "";
            inputLast.value = editBtn.dataset.last || "";
            inputEmail.value = editBtn.dataset.email || "";
            selectRole.value = editBtn.dataset.role || "";

            if (selectHomeWs) {
                const homeWs = editBtn.dataset.homeWorksite || "";
                selectHomeWs.value = homeWs === "0" ? "" : homeWs;
            }

            inputPass.value = "";
            inputPass.required = false;

            userModal.classList.remove("hidden");
            return;
        }

        /* --- POISTA --- */
        const delBtn = e.target.closest(".sf-delete-user");
        if (delBtn) {
            const deleteModal = document.getElementById("sfDeleteModal");
            const deleteName = document.getElementById("sfDeleteUserName");

            const nameCell = delBtn.closest("tr")?.querySelector("td");
            const name = nameCell ? nameCell.textContent.trim() : "käyttäjä";

            container.dataset.sfDeleteUserId = delBtn.dataset.id || "";
            if (deleteName) deleteName.textContent = name;
            if (deleteModal) deleteModal.classList.remove("hidden");
            return;
        }

        /* --- RESET SALASANA --- */
        const resetBtn = e.target.closest(".sf-reset-pass");
        if (resetBtn) {
            const resetModal = document.getElementById("sfResetModal");
            const resetName = document.getElementById("sfResetUserName");

            const nameCell = resetBtn.closest("tr")?.querySelector("td");
            const name = nameCell ? nameCell.textContent.trim() : "käyttäjä";

            container.dataset.sfResetUserId = resetBtn.dataset.id || "";
            if (resetName) resetName.textContent = name;
            if (resetModal) resetModal.classList.remove("hidden");
            return;
        }
    });

    /* =========================
       TALLENNUS (ADD / EDIT)
       ========================= */
    if (userForm) {
        userForm.addEventListener("submit", (e) => {
            e.preventDefault();

            const formData = new FormData(userForm);
            const isEdit = formData.get("id") !== "";

            const csrfInput = userForm.querySelector('input[name="csrf_token"]');
            if (csrfInput?.value) {
                formData.set("csrf_token", csrfInput.value);
            }

            if (formData.get("home_worksite_id") === "") {
                formData.set("home_worksite_id", "");
            }

            fetch(
                base +
                (isEdit
                    ? "/app/api/users_update.php"
                    : "/app/api/users_create.php"),
                {
                    method: "POST",
                    body: formData,
                }
            )
                .then((r) => r.json())
                .then((res) => {
                    if (res.ok) {
                        const notice = isEdit ? "user_updated" : "user_created";
                        window.location =
                            base +
                            "/index.php?page=settings&tab=users&notice=" +
                            notice;
                    } else {
                        alert(res.error || "Virhe tallennuksessa");
                    }
                })
                .catch(() => alert("Verkkovirhe."));
        });
    }

    /* =========================
       POISTO MODAALI
       ========================= */
    const deleteModal = document.getElementById("sfDeleteModal");
    const deleteCancel = document.getElementById("sfDeleteCancel");
    const deleteOk = document.getElementById("sfDeleteConfirm");

    if (deleteCancel && deleteModal) {
        deleteCancel.addEventListener("click", () => {
            container.dataset.sfDeleteUserId = "";
            deleteModal.classList.add("hidden");
        });
    }

    if (deleteOk && deleteModal) {
        deleteOk.addEventListener("click", () => {
            const id = container.dataset.sfDeleteUserId;
            if (!id) return;

            const body = new URLSearchParams();
            body.set("id", id);

            fetch(base + "/app/api/users_delete.php", {
                method: "POST",
                body,
            })
                .then((r) => r.json())
                .then((res) => {
                    if (res.ok) {
                        window.location =
                            base +
                            "/index.php?page=settings&tab=users&notice=user_deleted";
                    } else {
                        alert(res.error || "Virhe poistossa");
                    }
                })
                .catch(() => alert("Verkkovirhe."));
        });
    }

    /* =========================
       RESET MODAALI
       ========================= */
    const resetModal = document.getElementById("sfResetModal");
    const resetCancel = document.getElementById("sfResetCancel");
    const resetOk = document.getElementById("sfResetConfirm");

    if (resetCancel && resetModal) {
        resetCancel.addEventListener("click", () => {
            container.dataset.sfResetUserId = "";
            resetModal.classList.add("hidden");
        });
    }

    if (resetOk && resetModal) {
        resetOk.addEventListener("click", () => {
            const id = container.dataset.sfResetUserId;
            if (!id) return;

            const body = new URLSearchParams();
            body.set("id", id);

            fetch(base + "/app/api/users_reset_password.php", {
                method: "POST",
                body,
            })
                .then((r) => r.json())
                .then((res) => {
                    if (res.ok) {
                        resetModal.classList.add("hidden");
                        alert("Uusi salasana: " + res.password);
                        window.location =
                            base +
                            "/index.php?page=settings&tab=users&notice=user_pass_reset";
                    } else {
                        alert(res.error || "Virhe salasanan resetoinnissa");
                    }
                })
                .catch(() => alert("Verkkovirhe."));
        });
    }
}

/* ===== INIT ===== */

// Normaali sivulataus
document.addEventListener("DOMContentLoaded", () => {
    sfInitUsersTab(document);
});

// Settings-tab AJAX-vaihdon jälkeen
window.addEventListener("sf:content:updated", (e) => {
    if (e?.detail?.page === "settings" && e?.detail?.tab === "users") {
        sfInitUsersTab(document);
    }
});