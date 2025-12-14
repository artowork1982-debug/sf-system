console.log("users.js loaded");

(function () {
    var base = typeof SF_BASE_URL !== "undefined"
        ? SF_BASE_URL.replace(/\/+$/, "")
        : "";

    document.addEventListener("click", function (e) {
        var settingsPage = document.querySelector(".sf-settings-page");
        if (!settingsPage) return;

        if (e.target.closest("#sfUserAddBtn")) {
            var userModal = document.getElementById("sfUserModal");
            var userForm = document.getElementById("sfUserForm");
            var userTitle = document.getElementById("sfUserModalTitle");
            var inputId = document.getElementById("sfUserId");
            var inputPass = document.getElementById("sfUserPassword");
            var selectHomeWs = document.getElementById("sfUserHomeWorksite");

            if (userModal && userForm) {
                userTitle.textContent = "Lisää käyttäjä";
                userForm.reset();
                inputId.value = "";
                if (selectHomeWs) selectHomeWs.value = "";
                if (inputPass) inputPass.required = true;
                userModal.classList.remove("hidden");
            }
            return;
        }

        var editBtn = e.target.closest(".sf-edit-user");
        if (editBtn) {
            var userModal = document.getElementById("sfUserModal");
            var userTitle = document.getElementById("sfUserModalTitle");
            var inputId = document.getElementById("sfUserId");
            var inputFirst = document.getElementById("sfUserFirst");
            var inputLast = document.getElementById("sfUserLast");
            var inputEmail = document.getElementById("sfUserEmail");
            var selectRole = document.getElementById("sfUserRole");
            var selectHomeWs = document.getElementById("sfUserHomeWorksite");
            var inputPass = document.getElementById("sfUserPassword");

            if (userModal) {
                userTitle.textContent = "Muokkaa käyttäjää";
                inputId.value = editBtn.dataset.id || "";
                inputFirst.value = editBtn.dataset.first || "";
                inputLast.value = editBtn.dataset.last || "";
                inputEmail.value = editBtn.dataset.email || "";
                selectRole.value = editBtn.dataset.role || "";

                if (selectHomeWs) {
                    var homeWs = editBtn.dataset.homeWorksite || "";
                    selectHomeWs.value = homeWs === "0" ? "" : homeWs;
                }

                inputPass.value = "";
                inputPass.required = false;
                userModal.classList.remove("hidden");
            }
            return;
        }

        var delBtn = e.target.closest(".sf-delete-user");
        if (delBtn) {
            var deleteModal = document.getElementById("sfDeleteModal");
            var deleteName = document.getElementById("sfDeleteUserName");

            var row = delBtn.closest("tr");
            var card = delBtn.closest(".sf-user-card");
            var name = "käyttäjä";

            if (row) {
                var nameCell = row.querySelector("td");
                name = nameCell ? nameCell.textContent.trim() : name;
            } else if (card) {
                var nameEl = card.querySelector(".sf-user-card-name");
                name = nameEl ? nameEl.textContent.trim() : name;
            }

            if (deleteModal) {
                deleteModal.dataset.userId = delBtn.dataset.id || "";
                if (deleteName) deleteName.textContent = name;
                deleteModal.classList.remove("hidden");
            }
            return;
        }

        var resetBtn = e.target.closest(".sf-reset-pass");
        if (resetBtn) {
            var resetModal = document.getElementById("sfResetModal");
            var resetName = document.getElementById("sfResetUserName");

            var row = resetBtn.closest("tr");
            var card = resetBtn.closest(".sf-user-card");
            var email = "";

            if (row) {
                var emailCell = row.querySelector("td:nth-child(2)");
                email = emailCell ? emailCell.textContent.trim() : "";
            } else if (card) {
                var emailEl = card.querySelector(".sf-user-card-email");
                email = emailEl ? emailEl.textContent.trim() : "";
            }

            if (resetModal) {
                resetModal.dataset.userId = resetBtn.dataset.id || "";
                if (resetName) resetName.textContent = email;
                resetModal.classList.remove("hidden");
            }
            return;
        }

        if (e.target.closest("#sfUserCancel")) {
            var modal = document.getElementById("sfUserModal");
            if (modal) modal.classList.add("hidden");
            return;
        }

        if (e.target.closest("#sfDeleteCancel")) {
            var modal = document.getElementById("sfDeleteModal");
            if (modal) modal.classList.add("hidden");
            return;
        }

        if (e.target.closest("#sfResetCancel")) {
            var modal = document.getElementById("sfResetModal");
            if (modal) modal.classList.add("hidden");
            return;
        }

        if (e.target.closest("#sfDeleteConfirm")) {
            var modal = document.getElementById("sfDeleteModal");
            var userId = modal ? modal.dataset.userId : null;

            if (userId) {
                var body = new URLSearchParams();
                body.set("id", userId);

                fetch(base + "/app/api/users_delete.php", {
                    method: "POST",
                    body: body
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.ok) {
                            window.location = base + "/index.php?page=settings&tab=users&notice=user_deleted";
                        } else {
                            alert(res.error || "Virhe poistossa");
                        }
                    })
                    .catch(function () { alert("Verkkovirhe."); });
            }
            return;
        }

        if (e.target.closest("#sfResetConfirm")) {
            var modal = document.getElementById("sfResetModal");
            var userId = modal ? modal.dataset.userId : null;

            if (userId) {
                var body = new URLSearchParams();
                body.set("id", userId);

                fetch(base + "/app/api/users_reset_password.php", {
                    method: "POST",
                    body: body
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.ok) {
                            modal.classList.add("hidden");
                            alert("Uusi salasana: " + res.password);
                            window.location = base + "/index.php?page=settings&tab=users&notice=user_pass_reset";
                        } else {
                            alert(res.error || "Virhe salasanan resetoinnissa");
                        }
                    })
                    .catch(function () { alert("Verkkovirhe."); });
            }
            return;
        }
    });

    document.addEventListener("submit", function (e) {
        var userForm = e.target.closest("#sfUserForm");
        if (!userForm) return;

        e.preventDefault();

        var formData = new FormData(userForm);
        var inputId = document.getElementById("sfUserId");
        var isEdit = inputId && inputId.value !== "";

        var csrfInput = userForm.querySelector('input[name="csrf_token"]');
        if (csrfInput && csrfInput.value) {
            formData.set("csrf_token", csrfInput.value);
        }

        if (formData.get("home_worksite_id") === "") {
            formData.set("home_worksite_id", "");
        }

        var url = base + (isEdit ? "/app/api/users_update.php" : "/app/api/users_create.php");

        fetch(url, {
            method: "POST",
            body: formData
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    var notice = isEdit ? "user_updated" : "user_created";
                    window.location = base + "/index.php?page=settings&tab=users&notice=" + notice;
                } else {
                    alert(res.error || "Virhe tallennuksessa");
                }
            })
            .catch(function () { alert("Verkkovirhe."); });
    });
})();