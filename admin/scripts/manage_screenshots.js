// ── i18n ────────────────────────────────────────────────────────────────────
// AGS_LANG выводится PHP перед подключением скрипта (ключи js_* без префикса).
// t(key, fallback, ...args): перевод с английским fallback и подстановкой {1}, {2}…
// ($lang->load() превращает {N} в %N$s — поддерживаются оба формата)
function t(key, fallback, ...args) {
    const dict = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    const str = (typeof dict[key] === 'string') ? dict[key] : fallback;
    if (!args.length) return str;
    return str.replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => {
        const n = Number(a || b);
        return args[n - 1] !== undefined ? String(args[n - 1]) : m;
    });
}

// Множественное число через Intl.PluralRules: ключи <base>_one/_few/_many/_other
function tPlural(base, n, fallbackOne, fallbackOther) {
    let form = 'other';
    try { form = new Intl.PluralRules(t('locale', 'en-US')).select(n); } catch (e) { form = (n === 1 ? 'one' : 'other'); }
    const fb = (form === 'one') ? fallbackOne : fallbackOther;
    return t(base + '_' + form, fb, n);
}

// Иконка + текст без innerHTML (перевод вставляется как текст)
function setIconText(el, iconClass, text, spacer) {
    el.textContent = '';
    const i = document.createElement('i');
    i.className = iconClass;
    el.appendChild(i);
    el.appendChild(document.createTextNode((spacer || '') + text));
}

let deleteId = null;
let deleteImageSrc = null;

document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        
        deleteId = this.dataset.id;
        const filename = this.dataset.filename || t('this_screenshot', 'this screenshot');
        
        // Получаем src изображения из карточки
        const card = this.closest('.screenshot-card');
        const img = card?.querySelector('img');
        deleteImageSrc = img?.src || '';
        
        // Обновляем модалку
        setIconText(document.getElementById('singleDeleteModalLabel'), 'fas fa-exclamation-triangle me-2', t('confirm_deletion', 'Confirm Deletion'), ' ');
        document.getElementById('singleDeleteTitle').textContent = t('delete_q', 'Delete Screenshot?');
        const fnEl = document.getElementById('singleDeleteFilename');
        fnEl.textContent = '';
        const fnStrong = document.createElement('strong');
        fnStrong.textContent = t('quoted', '"{1}"', filename);
        fnEl.appendChild(fnStrong);
        document.getElementById('singleDeleteFileName').textContent = filename;
        
        // Получаем информацию о файле
        const fileInfoEl = document.getElementById('singleDeleteFileInfo');
        setIconText(fileInfoEl, 'fas fa-spinner fa-spin me-1', t('loading', 'Loading...'), ' ');
        
        // Имитация получения информации
        setTimeout(() => {
            const randomSize = Math.floor(Math.random() * 500 + 100);
            const today = new Date();
            const dateStr = today.toLocaleDateString(t('locale', 'en-US'), { year: 'numeric', month: 'short', day: 'numeric' });
            setIconText(fileInfoEl, 'fas fa-database me-1', t('kb', '{1} KB', randomSize) + ' • ', ' ');
            const calIcon = document.createElement('i');
            calIcon.className = 'fas fa-calendar me-1';
            fileInfoEl.appendChild(calIcon);
            fileInfoEl.appendChild(document.createTextNode(' ' + dateStr));
        }, 500);
        
        
const previewContainer = document.getElementById('singleDeletePreviewContainer');
const previewImg = document.getElementById('singleDeleteImage');

if (deleteImageSrc && deleteImageSrc !== '') {
    previewImg.src = deleteImageSrc;
    previewImg.style.display = 'block';
} else {
    previewImg.style.display = 'none';
}
previewContainer.style.display = 'block';
		
		
    });
});

document.getElementById('confirmSingleDeleteBtn').addEventListener('click', function () {
    if (!deleteId) return;

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = '';
    const spin = document.createElement('span');
    spin.className = 'spinner-border spinner-border-sm me-2';
    spin.setAttribute('role', 'status');
    btn.appendChild(spin);
    btn.appendChild(document.createTextNode(' ' + t('deleting', 'Deleting...')));

    fetch('index.php?act=manage_screenshots&action=delete&id=' + deleteId, {
        method: 'POST',
        body: new URLSearchParams({ my_post_key: my_post_key }),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('singleDeleteModal'));
        if (modal) modal.hide();

        if (data.status === 'success') {
            const card = document.querySelector('.screenshot-checkbox[value="' + deleteId + '"]')?.closest('.screenshot-card');
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8)';
                setTimeout(() => card.remove(), 300);
            }
            showToast(data.message || t('deleted_ok', 'Screenshot deleted successfully.'), 'success');
        } else {
            showToast(data.message || t('delete_failed', 'Failed to delete screenshot.'), 'error');;
        }

        deleteId = null;
        deleteImageSrc = null;
    })
    .catch(err => {
        console.error(err);
        showToast(t('delete_error', 'An error occurred while deleting the screenshot.'), 'error');
        deleteId = null;
        deleteImageSrc = null;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
});


// Очищаем превью при закрытии модалки
document.getElementById('singleDeleteModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('singleDeleteImage').src = '';
    setIconText(document.getElementById('singleDeleteFileInfo'), 'fas fa-spinner fa-spin me-1', t('loading', 'Loading...'), ' ');
    document.getElementById('singleDeleteFilename').textContent = '';
});



document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.screenshot-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const form = document.getElementById('massDeleteForm');
    const alertContainer = document.getElementById('alertContainer');
    const previewContainer = document.getElementById('massDeletePreview');
    const deleteCountEl = document.getElementById('deleteCount');
    const modalEl = document.getElementById('massDeleteModal');
    const previewList = document.getElementById('previewList');

    // Создаем экземпляр модалки
    let massDeleteModal;
    if (modalEl) {
        massDeleteModal = new bootstrap.Modal(modalEl);
    }

    function getSelectedCheckboxes() {
        return Array.from(document.querySelectorAll('.screenshot-checkbox:checked'));
    }

    function renderDeletePreview() {
        if (!previewList || !deleteCountEl || !previewContainer) return;
        
        previewList.innerHTML = '';
        const selected = getSelectedCheckboxes();
        deleteCountEl.textContent = selected.length;

        if (selected.length === 0) {
            previewContainer.style.display = 'none';
            return;
        }

        selected.forEach(cb => {
            let src = cb.dataset.imgSrc;
            if (!src) return;

            const item = document.createElement('div');
            item.className = 'preview-item';
            
            // Создаем изображение с обработчиком ошибок
            const img = new Image();
            img.src = src;
            img.alt = t('screenshot', 'Screenshot');
            img.style.cssText = 'width:100%; height:100%; object-fit:cover; display:block;';
            
            // Обработчик ошибки загрузки
            img.onerror = function() {
                this.onerror = null;
                const noImg = t('no_image', 'No image').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                this.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'56\' viewBox=\'0 0 100 56\'%3E%3Crect width=\'100\' height=\'56\' fill=\'%23e9ecef\'/%3E%3Ctext x=\'50\' y=\'28\' font-size=\'10\' text-anchor=\'middle\' fill=\'%236c757d\'%3E' + encodeURIComponent(noImg) + '%3C/text%3E%3C/svg%3E';
            };
            
            const imgDiv = document.createElement('div');
            imgDiv.className = 'preview-image';
            imgDiv.appendChild(img);
            
            item.appendChild(imgDiv);
            previewList.appendChild(item);
        });

        previewContainer.style.display = 'block';
    }

    // Открытие модалки через JS
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (this.hasAttribute('disabled')) {
                // Показываем предупреждение
                Swal.fire({
                    title: t('no_selection_title', 'No Selection'),
                    text: t('no_selection_text', 'Please select at least one screenshot to delete.'),
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            
            renderDeletePreview();
            if (massDeleteModal) {
                massDeleteModal.show();
            }
        });
    }

    // Обновление состояния кнопки
    function updateDeleteBtnState() {
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        
        if (anyChecked) {
            deleteBtn.removeAttribute('disabled');
            deleteBtn.classList.remove('btn-secondary');
            deleteBtn.classList.add('btn-danger');
        } else {
            deleteBtn.setAttribute('disabled', 'disabled');
            deleteBtn.classList.remove('btn-danger');
            deleteBtn.classList.add('btn-secondary');
        }
    }

    // Checkbox event listeners
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateDeleteBtnState);
    });

    // Select All/Deselect All functionality
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            if (allChecked) {
                setIconText(this, 'fas fa-check-square me-2', t('select_all', 'Select All'));
            } else {
                setIconText(this, 'fas fa-times-circle me-2', t('deselect_all', 'Deselect All'));
            }
            updateDeleteBtnState();
        });
    }

    // Confirm delete action
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            const selected = getSelectedCheckboxes();
            const selectedCount = selected.length;

            if (selectedCount === 0) return;

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (massDeleteModal) {
                    massDeleteModal.hide();
                }

                if (data.status === 'success' || data.status === 'partial') {
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast(t('mass_error', 'An error occurred while deleting screenshots'), 'error');
            });
        });
    }


    // Initial state
    updateDeleteBtnState();
});

// ── Upload modal (AJAX) ──────────────────────────────────────────────────────
(function() {
    const modal        = document.getElementById("uploadScreenshotModal");
    if (!modal) return;
    const torrentInput = document.getElementById("scrTorrentId");
    const fileInput     = document.getElementById("scrFileInput");
    const dropArea      = document.getElementById("scrDropArea");
    const placeholder    = document.getElementById("scrPlaceholder");
    const previewContainer = document.getElementById("scrPreviewContainer");
    const previewGrid    = document.getElementById("scrPreviewGrid");
    const fileCountBadge = document.getElementById("scrFileCountBadge");
    const fileCountEl    = document.getElementById("scrFileCount");
    const clearBtn       = document.getElementById("scrClearBtn");
    const startBtn       = document.getElementById("scrStartUploadBtn");
    const progressWrap   = document.getElementById("scrUploadProgress")?.parentElement?.parentElement;
    const progressBar    = document.getElementById("scrUploadProgress");

    let selected = [];

    function updateStartBtn() {
        startBtn.disabled = !(selected.length > 0 && torrentInput.value.trim() !== "");
    }

    function renderPreview() {
        previewGrid.innerHTML = "";
        if (!selected.length) {
            placeholder.style.display = "";
            previewContainer.style.display = "none";
            fileCountBadge.style.display = "none";
            clearBtn.classList.add("d-none");
            updateStartBtn();
            return;
        }
        placeholder.style.display = "none";
        previewContainer.style.display = "block";
        fileCountBadge.style.display = "block";
        fileCountEl.textContent = tPlural("files", selected.length, "{1} file", "{1} files");
        clearBtn.classList.remove("d-none");

        selected.forEach(file => {
            if (!file.type.match("image.*")) return;
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement("img");
                img.src = e.target.result;
                img.className = "rounded border";
                img.style.cssText = "max-height:100px;max-width:140px;object-fit:cover";
                previewGrid.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
        updateStartBtn();
    }

    fileInput.addEventListener("change", function() {
        selected = [...this.files];
        renderPreview();
    });

    torrentInput.addEventListener("input", updateStartBtn);

    clearBtn.addEventListener("click", function() {
        selected = [];
        fileInput.value = "";
        renderPreview();
    });

    ["dragenter", "dragover", "dragleave", "drop"].forEach(ev =>
        dropArea.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); })
    );
    ["dragenter", "dragover"].forEach(ev =>
        dropArea.addEventListener(ev, () => dropArea.classList.add("border-primary"))
    );
    ["dragleave", "drop"].forEach(ev =>
        dropArea.addEventListener(ev, () => dropArea.classList.remove("border-primary"))
    );
    dropArea.addEventListener("drop", e => {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event("change"));
    });

    modal.addEventListener("hidden.bs.modal", function() {
        selected = [];
        fileInput.value = "";
        torrentInput.value = "";
        renderPreview();
        if (progressWrap) progressWrap.style.display = "none";
    });

    startBtn.addEventListener("click", function() {
        if (!selected.length || !torrentInput.value.trim()) return;

        const formData = new FormData();
        selected.forEach(f => formData.append("screenshots[]", f));
        formData.append("torrent_id", torrentInput.value.trim());
        formData.append("my_post_key", my_post_key);

        startBtn.disabled = true;
        startBtn.querySelector(".upload-text").classList.add("d-none");
        startBtn.querySelector(".upload-loading").classList.remove("d-none");

        if (progressWrap) {
            progressWrap.style.display = "block";
            progressBar.style.width = "0%";
        }
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            if (progressBar && progress <= 90) progressBar.style.width = progress + "%";
        }, 200);

        fetch(scr_script + "&action=ajax_upload", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            clearInterval(progressInterval);
            if (progressBar) progressBar.style.width = "100%";

            if (data.status === "success" || data.status === "partial") {
                if (typeof showToast === "function") {
                    showToast(data.message, data.status === "success" ? "success" : "warning");
                }
                setTimeout(() => {
                    const inst = bootstrap.Modal.getInstance(modal);
                    if (inst) inst.hide();
                    location.reload();
                }, 1200);
            } else {
                if (typeof showToast === "function") {
                    showToast(data.message || t("upload_failed", "Upload failed."), "error");
                } else {
                    alert(data.message || t("upload_failed", "Upload failed."));
                }
                if (progressWrap) progressWrap.style.display = "none";
            }
        })
        .catch(err => {
            clearInterval(progressInterval);
            console.error("Upload error:", err);
            if (typeof showToast === "function") {
                showToast(t("server_down", "Server not responding."), "error");
            }
            if (progressWrap) progressWrap.style.display = "none";
        })
        .finally(() => {
            startBtn.disabled = false;
            startBtn.querySelector(".upload-text").classList.remove("d-none");
            startBtn.querySelector(".upload-loading").classList.add("d-none");
        });
    });
})();

// ── Edit modal (AJAX) ────────────────────────────────────────────────────────
(function() {
    const editModal   = document.getElementById("editScreenshotModal");
    if (!editModal) return;
    const idInput     = document.getElementById("editScreenshotId");
    const torrentInput = document.getElementById("editTorrentId");
    const fileInput    = document.getElementById("editScreenshotFile");
    const preview      = document.getElementById("editImagePreview");
    const currentInfo  = document.getElementById("editCurrentFilename");
    const saveBtn       = document.getElementById("editSaveBtn");

    editModal.addEventListener("show.bs.modal", function(event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        idInput.value = trigger.getAttribute("data-id") || "";
        torrentInput.value = trigger.getAttribute("data-torrent-id") || "";
        preview.src = trigger.getAttribute("data-img-src") || "";
        currentInfo.textContent = t("keep_file", "Leave empty to keep: {1}", trigger.getAttribute("data-filename") || "");
        fileInput.value = "";
    });

    fileInput.addEventListener("change", function() {
        const f = this.files[0];
        if (f && f.type.match("image.*")) {
            const r = new FileReader();
            r.onload = e => preview.src = e.target.result;
            r.readAsDataURL(f);
        }
    });

    saveBtn.addEventListener("click", function() {
        if (!torrentInput.value.trim()) {
            if (typeof showToast === "function") showToast(t("torrent_required", "Torrent ID is required"), "error");
            return;
        }

        const formData = new FormData();
        formData.append("id", idInput.value);
        formData.append("torrent_id", torrentInput.value.trim());
        formData.append("my_post_key", my_post_key);
        if (fileInput.files[0]) {
            formData.append("screenshot", fileInput.files[0]);
        }

        saveBtn.disabled = true;
        saveBtn.querySelector(".save-text").classList.add("d-none");
        saveBtn.querySelector(".save-loading").classList.remove("d-none");

        fetch(scr_script + "&action=ajax_edit", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === "success") {
                if (typeof showToast === "function") showToast(data.message, "success");
                setTimeout(() => {
                    const inst = bootstrap.Modal.getInstance(editModal);
                    if (inst) inst.hide();
                    location.reload();
                }, 1000);
            } else {
                if (typeof showToast === "function") {
                    showToast(data.message || t("update_failed", "Update failed."), "error");
                } else {
                    alert(data.message || t("update_failed", "Update failed."));
                }
            }
        })
        .catch(err => {
            console.error("Edit error:", err);
            if (typeof showToast === "function") showToast(t("server_down", "Server not responding."), "error");
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.querySelector(".save-text").classList.remove("d-none");
            saveBtn.querySelector(".save-loading").classList.add("d-none");
        });
    });
})();

// ── Upload page (full-page form, multi-file preview) ─────────────────────────
(function() {
    const fi = document.getElementById("screenshot");
    const grid = document.getElementById("previewGrid");
    const ph = document.getElementById("placeholderText");
    const da = document.getElementById("dropArea");
    if (!fi || !grid || !da) return;

    fi.addEventListener("change", function() {
        grid.innerHTML = "";
        const files = [...this.files];
        if (!files.length) { if (ph) ph.style.display = "block"; return; }
        if (ph) ph.style.display = "none";
        files.forEach(f => {
            if (!f.type.match("image.*")) return;
            const r = new FileReader();
            r.onload = e => {
                const img = document.createElement("img");
                img.src = e.target.result;
                img.className = "rounded border";
                img.style.cssText = "max-height:100px;max-width:140px;object-fit:cover";
                grid.appendChild(img);
            };
            r.readAsDataURL(f);
        });
    });

    ["dragenter", "dragover", "dragleave", "drop"].forEach(ev => da.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));
    ["dragenter", "dragover"].forEach(ev => da.addEventListener(ev, () => da.classList.add("border-primary")));
    ["dragleave", "drop"].forEach(ev => da.addEventListener(ev, () => da.classList.remove("border-primary")));
    da.addEventListener("drop", e => { fi.files = e.dataTransfer.files; fi.dispatchEvent(new Event("change")); });
})();

// ── Edit page (full-page form, single-file preview) ───────────────────────────
(function() {
    const fi = document.getElementById("screenshot");
    const imgPreview = document.getElementById("imagePreview");
    // Тот же id "screenshot", что и в блоке загрузки выше - но это
    // взаимоисключающие состояния страницы (либо форма загрузки, либо
    // форма редактирования рендерится за раз), коллизии ID на практике
    // не возникает. Отличаем по наличию #imagePreview (только на форме
    // редактирования) и по отсутствию #dropArea (только форма загрузки
    // его использует).
    if (!fi || !imgPreview || document.getElementById("dropArea")) return;

    fi.addEventListener("change", function() {
        const f = this.files[0];
        if (f && f.type.match("image.*")) {
            const r = new FileReader();
            r.onload = e => imgPreview.src = e.target.result;
            r.readAsDataURL(f);
        }
    });
})();