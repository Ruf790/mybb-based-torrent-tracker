/* batch_upload.js */
'use strict';

const VALID_IMAGE_TYPES = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];

// Тот же список жанров, что и в PHP-шаблоне первой строки (batch_upload.php,
// torrentItemHtml()) - держать оба списка синхронными вручную, раз это два
// независимых источника одной и той же разметки.
const BATCH_GENRES = [
    ['Action',      'fa-solid fa-bolt',              '#ff4757'],
    ['Adventure',   'fa-solid fa-compass',           '#ffa502'],
    ['Animation',   'fa-solid fa-film',              '#2ed573'],
    ['Biography',   'fa-solid fa-user-graduate',     '#70a1ff'],
    ['Comedy',      'fa-solid fa-face-laugh-squint', '#ff6b81'],
    ['Crime',       'fa-solid fa-gavel',             '#8395a7'],
    ['Documentary', 'fa-solid fa-video',             '#a4b0be'],
    ['Drama',       'fa-solid fa-masks-theater',     '#9b8ea9'],
    ['Family',      'fa-solid fa-people-roof',       '#ff7f50'],
    ['Fantasy',     'fa-solid fa-dragon',            '#a29bfe'],
    ['History',     'fa-solid fa-landmark',          '#cd84f1'],
    ['Horror',      'fa-solid fa-ghost',             '#ff4d4d'],
    ['Music',       'fa-solid fa-music',             '#1e90ff'],
    ['Mystery',     'fa-solid fa-magnifying-glass',  '#8e44ad'],
    ['Romance',     'fa-solid fa-heart',             '#ff6b6b'],
    ['Sci-Fi',      'fa-solid fa-rocket',            '#00cec9'],
    ['Sport',       'fa-solid fa-trophy',            '#e1b12c'],
    ['Thriller',    'fa-solid fa-skull',             '#e17055'],
    ['War',         'fa-solid fa-person-rifle',      '#7f8c8d'],
    ['Western',     'fa-solid fa-hat-cowboy',        '#f39c12'],
];

const FETCH_IMDB_LABEL = '<i class="fa-solid fa-wand-magic-sparkles me-1"></i>Fetch info';

let torrentCount = 1;

// ── Утилиты ──────────────────────────────────────────────

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text ?? '';
    return d.innerHTML;
}

function getCategoryHtml(name, idx) {
    const opts = BATCH_CONFIG.categories.map(c =>
        `<option value="${c.id}">${escapeHtml(c.name)}</option>`
    ).join('');
    return `<select class="form-select" name="${name}[${idx}]">${opts}</select>`;
}

function buildTorrentItemHtml(idx, fileName = '') {
    return `
    <button type="button" class="bu-remove-item" title="Remove this torrent" aria-label="Remove this torrent">
      <i class="fa-solid fa-trash-can"></i>
    </button>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-file-zipper bu-ic bu-ic-primary"></i>Torrent file <span class="bu-req">*</span></label>
        <input class="form-control" type="file" name="torrentFiles[]" accept=".torrent">
        <div class="torrent-name mt-1 small text-muted">${escapeHtml(fileName)}</div>
      </div>
      <div class="col-md-3">
        <label class="form-label"><i class="fa-solid fa-image bu-ic bu-ic-info"></i>Poster <span class="bu-opt">optional</span></label>
        <input class="form-control" type="file" name="posters[]" accept="image/*">
        <div class="image-preview mt-2" style="max-width:150px;display:none">
          <img src="" class="img-thumbnail" style="max-height:100px" alt="">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label"><i class="fa-solid fa-image bu-ic bu-ic-info"></i>Poster 2 <span class="bu-opt">optional</span></label>
        <input class="form-control" type="file" name="posters2[]" accept="image/*">
        <div class="image-preview mt-2" style="max-width:150px;display:none">
          <img src="" class="img-thumbnail" style="max-height:100px" alt="">
        </div>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-solid fa-images bu-ic bu-ic-warning"></i>Screenshots <span class="bu-opt">optional, up to ${BATCH_CONFIG.maxScreenshots}</span></label>
        <input class="form-control" type="file" name="screenshots_${idx}[]" accept="image/*" multiple>
        <div class="screenshots-preview mt-2 d-flex flex-wrap gap-2"></div>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-heading bu-ic bu-ic-primary"></i>Torrent name <span class="bu-opt">filename if empty</span></label>
        <input type="text" class="form-control torrent-name-input" name="torrent_names[]" placeholder="Leave empty to use the filename">
      </div>
      <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-folder-tree bu-ic bu-ic-success"></i>Category</label>
        ${getCategoryHtml('batch_categories', idx)}
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-solid fa-align-left bu-ic bu-ic-secondary"></i>Description <span class="bu-opt">BBCode supported</span></label>
        <textarea class="form-control batch-desc" name="descriptions[]" rows="5" placeholder="Description..."></textarea>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-solid fa-tags bu-ic bu-ic-danger"></i>Tags <span class="bu-opt">overridden by the CSV tags column</span></label>
        <div class="input-group mb-2">
          <span class="input-group-text bu-addon"><i class="fa-solid fa-tag"></i></span>
          <input type="text" class="form-control batch-tags-input" name="tags_manual[]" placeholder="Action, Comedy, Drama...">
          <button type="button" class="btn bu-btn-soft-secondary" onclick="clearBatchTags(this)">
            <i class="fa-solid fa-eraser me-1"></i>Clear
          </button>
        </div>
        <div class="d-flex flex-wrap gap-2 batch-genre-buttons">
          ${BATCH_GENRES.map(([label, icon, color]) => `
          <button type="button"
                  class="btn btn-sm batch-genre-tag-btn"
                  data-genre="${label}"
                  data-color="${color}"
                  onclick="toggleBatchGenreTag(this)"
                  style="border-color: ${color}80; color: ${color};">
              <i class="${icon} me-1"></i>${label}
          </button>`).join('')}
        </div>
      </div>
    </div>

    <div class="row g-3 mt-0">
      <div class="col-12">
        <label class="form-label"><i class="fa-brands fa-imdb bu-ic bu-ic-imdb"></i>IMDb URL <span class="bu-opt">optional</span></label>
        <div class="input-group">
          <span class="input-group-text bu-addon"><i class="fa-solid fa-link"></i></span>
          <input type="url" class="form-control imdb-url-input" name="imdb_urls[]"
                 placeholder="https://www.imdb.com/title/tt0000000/">
          <button type="button" class="btn bu-btn-imdb btn-fetch-imdb">${FETCH_IMDB_LABEL}</button>
        </div>
        <div class="imdb-preview mt-2" style="display:none;">
          <div class="bu-imdb-card">
            <div class="d-flex gap-3 align-items-start">
              <img class="imdb-poster" src="" alt="Poster"
                   style="width:56px;height:84px;object-fit:cover;border-radius:6px;display:none;">
              <div class="flex-grow-1 min-w-0">
                <div class="fw-bold imdb-title">—</div>
                <div class="d-flex gap-1 mt-1 flex-wrap">
                  <span class="badge bu-badge bu-soft-warning imdb-year" style="display:none;"></span>
                  <span class="badge bu-badge bu-soft-secondary imdb-genre" style="display:none;"></span>
                  <span class="badge bu-badge bu-soft-success imdb-rating" style="display:none;"></span>
                </div>
                <p class="small text-muted mt-2 mb-2 imdb-plot"></p>
                <button type="button" class="btn btn-sm rounded-pill bu-btn-soft-primary btn-imdb-apply-desc">
                  <i class="fa-solid fa-paste me-1"></i>Add to description
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>`;
}

function setFileToInput(input, file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

// Превью лежит в той же колонке, что и input (col-md-3 для обоих постеров)
function showImagePreview(posterInput, file) {
    const preview = posterInput.parentElement?.querySelector('.image-preview');
    if (!preview) return;
    const img = preview.querySelector('img');
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(file);
}

function hideImagePreview(posterInput) {
    const preview = posterInput.parentElement?.querySelector('.image-preview');
    if (!preview) return;
    preview.style.display = 'none';
    const img = preview.querySelector('img');
    if (img) img.src = '';
}

function validateImage(file) {
    if (!VALID_IMAGE_TYPES.includes(file.type)) {
        alert(`${file.name}: invalid image type`);
        return false;
    }
    if (file.size > BATCH_CONFIG.maxImageBytes) {
        alert(`${file.name}: too large (max ${Math.round(BATCH_CONFIG.maxImageBytes / 1048576)} MB)`);
        return false;
    }
    return true;
}

// ── DOM готов ─────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const form             = document.getElementById('batchUploadForm');
    const progressModalEl  = document.getElementById('batchProgressModal');
    const progressModal    = new bootstrap.Modal(progressModalEl);
    const modalIcon        = progressModalEl.querySelector('.modal-header i');
    const modalIconBox     = progressModalEl.querySelector('.modal-header .bu-square');
    const modalTitle       = progressModalEl.querySelector('.modal-title');
    const overallBar       = document.getElementById('overallProgressBar');
    const overallPercent   = document.getElementById('overallProgressPercent');
    const fileProgressCont = document.getElementById('fileProgressContainer');
    const resultsContainer = document.getElementById('resultsContainer');
    const resultsList      = document.getElementById('resultsList');
    const closeModalBtn    = document.getElementById('closeModalBtn');
    const viewTorrentsBtn  = document.getElementById('viewTorrentsBtn');
    const dropZone         = document.querySelector('.drop-zone');
    const dragDropInput    = document.getElementById('dragDropFiles');

    updateTorrentCount();

    // ── Drag & Drop ───────────────────────────────────────
    // Цвета - через класс .dragover из batch_upload.css (а не инлайн-стили),
    // чтобы тёмная тема работала. Счётчик нужен, потому что dragleave
    // срабатывает и при переходе курсора на дочерние элементы зоны.

    let dragDepth = 0;
    const endDrag = () => { dragDepth = 0; dropZone?.classList.remove('dragover'); };

    dropZone?.addEventListener('click', () => dragDropInput.click());
    dropZone?.addEventListener('dragenter', e => {
        e.preventDefault();
        dragDepth++;
        dropZone.classList.add('dragover');
    });
    dropZone?.addEventListener('dragover', e => e.preventDefault());
    dropZone?.addEventListener('dragleave', () => {
        if (--dragDepth <= 0) endDrag();
    });
    dropZone?.addEventListener('drop', e => {
        e.preventDefault();
        endDrag();
        handleDroppedFiles(e.dataTransfer.files);
    });
    dragDropInput?.addEventListener('change', function () {
        handleDroppedFiles(this.files);
        this.value = '';
    });

    // ── Кнопки ────────────────────────────────────────────

    document.getElementById('addMore')?.addEventListener('click', () => {
        if (torrentCount >= BATCH_CONFIG.maxTorrents) {
            alert(`Maximum ${BATCH_CONFIG.maxTorrents} torrents`);
            return;
        }
        addTorrentItem(null, torrentCount);
        updateTorrentCount();
    });

    // Предпросмотр постеров (делегирование)
    document.addEventListener('change', e => {
        if (!e.target.matches('input[name="posters[]"], input[name="posters2[]"]')) return;
        const file = e.target.files[0];
        if (!file) { hideImagePreview(e.target); return; }
        if (!validateImage(file)) { e.target.value = ''; hideImagePreview(e.target); return; }
        showImagePreview(e.target, file);
    });

    // ── Проверка дубликата при выборе торрента ───────────
    document.addEventListener('change', e => {
        if (!e.target.matches('input[name="torrentFiles[]"]')) return;
        const file = e.target.files[0];
        if (!file) return;
        const item = e.target.closest('.torrent-item');

        // Автозаполняем имя из имени файла если поле пустое
        const nameInput = item?.querySelector('input[name="torrent_names[]"]');
        if (nameInput && !nameInput.value.trim()) {
            nameInput.value = file.name.replace(/\.torrent$/i, '');
        }

        checkDuplicate(file, item);
    });

    function checkDuplicate(file, item) {
        const fd = new FormData();
        fd.append('torrentFile', file);
        fd.append('my_post_key', document.querySelector('[name="my_post_key"]').value);

        fetch(BATCH_CONFIG.scriptUrl + '&action=check_torrent_file', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(text => {
                const match = text.match(/\{[\s\S]*\}/);
                if (!match) return;
                showDuplicateWarning(item, JSON.parse(match[0]));
            })
            .catch(() => {});
    }

    function showDuplicateWarning(item, data) {
        item.querySelector('.duplicate-warning')?.remove();
        if (!data.exists) return;
        const warn = document.createElement('div');
        warn.className = 'duplicate-warning bu-alert bu-alert-warning mt-3';
        warn.innerHTML = '<div class="bu-alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>'
            + '<div><strong>Duplicate detected.</strong> A torrent with the same info_hash already exists: '
            + '<a href="' + escapeHtml(data.link) + '" target="_blank" rel="noopener">' + escapeHtml(data.name) + '</a>'
            + ', uploaded ' + escapeHtml(data.added) + '.</div>';
        item.appendChild(warn);
    }

    // ── IMDb Fetch ────────────────────────────────────────
    document.addEventListener('click', e => {
        const fetchBtn = e.target.closest('.btn-fetch-imdb');
        if (fetchBtn) { handleImdbFetch(fetchBtn); return; }

        const applyBtn = e.target.closest('.btn-imdb-apply-desc');
        if (applyBtn) { handleImdbApply(applyBtn); }
    });

    function handleImdbFetch(btn) {
        // .row, а не конкретный класс колонки - разметка колонок может меняться
        const block   = btn.closest('.row');
        const input   = block?.querySelector('.imdb-url-input');
        const preview = block?.querySelector('.imdb-preview');
        const url     = input?.value?.trim();
        if (!preview) return;

        if (!url) { alert('Please enter an IMDb URL first.'); return; }
        if (!/^https?:\/\/www\.imdb\.com\/title\/tt\d+/i.test(url)) {
            alert('Invalid IMDb URL. Example: https://www.imdb.com/title/tt0000000/');
            return;
        }

        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Fetching...';

        fetch(BATCH_CONFIG.scriptUrl + '&action=get_imdb_data&imdb_url=' + encodeURIComponent(url))
            .then(r => r.text())
            .then(text => {
                btn.disabled  = false;
                btn.innerHTML = FETCH_IMDB_LABEL;
                const match = text.match(/\{[\s\S]*\}/);
                if (!match) throw new Error('No JSON in response');
                const data = JSON.parse(match[0]);
                if (!data.success) { alert('IMDb Error: ' + (data.error || 'Unknown')); return; }

                preview.style.display = '';
                const poster = preview.querySelector('.imdb-poster');
                const title  = preview.querySelector('.imdb-title');
                const year   = preview.querySelector('.imdb-year');
                const genre  = preview.querySelector('.imdb-genre');
                const rating = preview.querySelector('.imdb-rating');
                const plot   = preview.querySelector('.imdb-plot');

                // Сброс значений от предыдущего запроса
                [year, genre, rating].forEach(b => { b.textContent = ''; b.style.display = 'none'; });
                poster.style.display = 'none';

                title.textContent = data.title || '—';
                if (data.poster) { poster.src = data.poster; poster.style.display = ''; }
                if (data.year)   { year.textContent   = data.year;          year.style.display   = ''; }
                if (data.genre)  { genre.textContent  = data.genre;         genre.style.display  = ''; }
                if (data.rating) { rating.textContent = '★ ' + data.rating; rating.style.display = ''; }
                plot.textContent = data.plot || '';
            })
            .catch(() => {
                btn.disabled  = false;
                btn.innerHTML = FETCH_IMDB_LABEL;
                alert('Failed to fetch IMDb data. Please try again.');
            });
    }

    function handleImdbApply(btn) {
        const torrentItem = btn.closest('.torrent-item');
        const textarea    = torrentItem?.querySelector('textarea[name="descriptions[]"]');
        const preview     = btn.closest('.imdb-preview');
        if (!textarea || !preview) return;

        const parts = [];
        const title  = preview.querySelector('.imdb-title')?.textContent;
        const year   = preview.querySelector('.imdb-year')?.textContent;
        const genre  = preview.querySelector('.imdb-genre')?.textContent;
        const rating = preview.querySelector('.imdb-rating')?.textContent;
        const plot   = preview.querySelector('.imdb-plot')?.textContent;

        if (title && title !== '—') parts.push('[b]' + title + '[/b]');
        if (year)   parts.push('Year: '         + year);
        if (genre)  parts.push('Genre: '        + genre);
        if (rating) parts.push('IMDb Rating: '  + rating);
        if (plot)   parts.push('\n' + plot);
        textarea.value = parts.join('\n');
        textarea.focus();
    }

    // Отправка формы
    form?.addEventListener('submit', e => {
        e.preventDefault();

        const hasFiles = [...document.querySelectorAll('input[name="torrentFiles[]"]')]
            .some(i => i.files.length > 0);
        if (!hasFiles) { alert('Please select at least one torrent file'); return; }

        const allValid = [...document.querySelectorAll('input[name="posters[]"], input[name="posters2[]"]')]
            .every(i => i.files.length === 0 || validateImage(i.files[0]));
        if (!allValid) return;

        resetUI();
        progressModal.show();
        createFileProgressItems();
        uploadFiles();
    });

    closeModalBtn?.addEventListener('click',  () => progressModal.hide());
    viewTorrentsBtn?.addEventListener('click', () => window.open('/browse.php', '_blank'));

    // ── Удаление раздачи из пачки ─────────────────────────

    document.addEventListener('click', e => {
        const btn = e.target.closest('.bu-remove-item');
        if (!btn) return;
        const item = btn.closest('.torrent-item');
        if (!item) return;

        const doRemove = () => removeTorrentItem(item);
        if (!itemHasData(item)) { doRemove(); return; }

        const isLast   = document.querySelectorAll('#torrentContainer .torrent-item').length <= 1;
        const fileName = item.querySelector('input[name="torrentFiles[]"]')?.files[0]?.name ?? '';
        const title    = isLast ? 'Clear this torrent?' : 'Remove this torrent?';
        const text     = (fileName ? fileName + ': ' : '')
            + (isLast ? 'all fields of this block will be cleared.' : 'the block and everything filled in it will be removed from the batch.');

        confirmAction(title, text, isLast ? 'Clear' : 'Remove').then(ok => { if (ok) doRemove(); });
    });

    // SweetAlert2 с запасным вариантом confirm(), если библиотека не загрузилась
    function confirmAction(title, text, confirmText) {
        if (typeof Swal === 'undefined') return Promise.resolve(confirm(title + '\n\n' + text));
        return Swal.fire({
            title,
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancel',
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim() || '#dc3545',
            reverseButtons: true,
            focusCancel: true,
        }).then(r => r.isConfirmed);
    }

    // Есть ли в блоке что-то, что жалко потерять (тогда спрашиваем подтверждение)
    function itemHasData(item) {
        const hasFile = [...item.querySelectorAll('input[type="file"]')].some(i => i.files.length > 0);
        const hasText = [...item.querySelectorAll('input[type="text"], input[type="url"], textarea')]
            .some(i => i.value.trim() !== '');
        return hasFile || hasText;
    }

    function removeTorrentItem(item) {
        const items = document.querySelectorAll('#torrentContainer .torrent-item');
        if (items.length <= 1) {
            clearTorrentItem(item);
        } else {
            item.remove();
            renumberTorrentItems();
        }
        updateTorrentCount();
    }

    // Последний блок не удаляем (форме нужен хотя бы один) - просто очищаем
    function clearTorrentItem(item) {
        item.querySelectorAll('input[type="file"], input[type="text"], input[type="url"], textarea')
            .forEach(i => { i.value = ''; });
        item.querySelectorAll('select').forEach(s => { s.selectedIndex = 0; });

        item.querySelectorAll('input[name="posters[]"], input[name="posters2[]"]')
            .forEach(posterInput => hideImagePreview(posterInput));

        const nameLabel = item.querySelector('.torrent-name');
        if (nameLabel) nameLabel.textContent = '';

        const shots = item.querySelector('.screenshots-preview');
        if (shots) shots.innerHTML = '';

        item.querySelector('.duplicate-warning')?.remove();

        item.querySelectorAll('.batch-genre-tag-btn').forEach(b => {
            b.classList.remove('batch-genre-active');
            b.style.background = 'transparent';
            b.style.color = b.dataset.color;
        });

        const imdb = item.querySelector('.imdb-preview');
        if (imdb) {
            imdb.style.display = 'none';
            imdb.querySelectorAll('.imdb-year, .imdb-genre, .imdb-rating').forEach(b => { b.textContent = ''; b.style.display = 'none'; });
            const poster = imdb.querySelector('.imdb-poster');
            if (poster) { poster.src = ''; poster.style.display = 'none'; }
            const title = imdb.querySelector('.imdb-title');
            if (title) title.textContent = '—';
            const plot = imdb.querySelector('.imdb-plot');
            if (plot) plot.textContent = '';
        }
    }

    // Сервер сопоставляет screenshots_N[] и batch_categories[N] с N-м файлом
    // в torrentFiles[] - после удаления блока номера надо привести к порядку
    // блоков, иначе скриншоты/категория уедут к соседней раздаче.
    function renumberTorrentItems() {
        document.querySelectorAll('#torrentContainer .torrent-item').forEach((item, i) => {
            const shots = item.querySelector('input[type="file"][name^="screenshots_"]');
            if (shots) shots.name = `screenshots_${i}[]`;

            const cat = item.querySelector('select[name^="batch_categories"]');
            if (cat) cat.name = `batch_categories[${i}]`;
        });
    }

    // ── Обработка файлов ──────────────────────────────────

    function handleDroppedFiles(files) {
        const container = document.getElementById('torrentContainer');
        const items     = container.querySelectorAll('.torrent-item');

        [...items].slice(1).forEach(i => i.remove());

        if (items[0]) {
            items[0].querySelector('input[name="torrentFiles[]"]').value = '';
            items[0].querySelector('.torrent-name').textContent          = '';
            items[0].querySelectorAll('input[name="posters[]"], input[name="posters2[]"]').forEach(posterInput => {
                posterInput.value = '';
                hideImagePreview(posterInput);
            });
            items[0].querySelector('textarea[name="descriptions[]"]').value = '';
            items[0].querySelector('.duplicate-warning')?.remove();
        }

        torrentCount = 0;

        const torrentFiles = [...files]
            .filter(f => f.name.toLowerCase().endsWith('.torrent'))
            .slice(0, BATCH_CONFIG.maxTorrents);

        torrentFiles.forEach((file, idx) => {
            if (idx === 0 && items[0]) {
                setFileToInput(items[0].querySelector('input[name="torrentFiles[]"]'), file);
                items[0].querySelector('.torrent-name').textContent = file.name;
            } else {
                addTorrentItem(file, idx);
            }
            torrentCount++;
        });

        updateTorrentCount();
    }

    function addTorrentItem(file, idx) {
        const container = document.getElementById('torrentContainer');
        const div = document.createElement('div');
        div.className = 'torrent-item mb-3';
        div.innerHTML = buildTorrentItemHtml(idx, file?.name ?? '');
        container.appendChild(div);
        renumberTorrentItems();
        if (file) setFileToInput(div.querySelector('input[name="torrentFiles[]"]'), file);
    }

    function updateTorrentCount() {
        torrentCount = document.querySelectorAll('.torrent-item').length;
        const btn = document.getElementById('batchUploadBtn');
        if (btn) btn.innerHTML = `<i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload ${torrentCount} torrent${torrentCount !== 1 ? 's' : ''}`;

        const addBtn = document.getElementById('addMore');
        if (addBtn) addBtn.disabled = torrentCount >= BATCH_CONFIG.maxTorrents;
    }

    function setModalState(state) {
        const map = {
            working: ['fa-solid fa-spinner fa-spin',     'bu-soft-primary', 'Processing upload'],
            done:    ['fa-solid fa-circle-check',        'bu-soft-success', 'Upload finished'],
            error:   ['fa-solid fa-circle-exclamation',  'bu-soft-danger',  'Upload failed'],
        };
        const [icon, soft, title] = map[state];
        if (modalIcon)    modalIcon.className = icon;
        if (modalIconBox) modalIconBox.className = `bu-square ${soft} me-2`;
        if (modalTitle)   modalTitle.textContent = title;
    }

    function resetUI() {
        setModalState('working');
        resultsContainer.style.display = 'none';
        resultsList.innerHTML          = '';
        fileProgressCont.innerHTML     = '';
        overallBar.style.width         = '0%';
        overallBar.className           = 'progress-bar progress-bar-striped progress-bar-animated';
        overallPercent.textContent     = '0%';
        closeModalBtn.style.display    = 'none';
        viewTorrentsBtn.style.display  = 'none';
    }

    // ── Прогресс ──────────────────────────────────────────

    function createFileProgressItems() {
        document.querySelectorAll('input[name="torrentFiles[]"]').forEach((input, idx) => {
            if (!input.files.length) return;
            const el = document.createElement('div');
            el.className = 'bu-file-progress';
            el.id        = `fileProgress_${idx}`;
            el.innerHTML = `
                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                  <span class="text-truncate">
                    <i class="fa-solid fa-file-zipper me-1 text-primary"></i>${escapeHtml(input.files[0].name)}
                  </span>
                  <span class="badge bu-badge bu-soft-secondary"><i class="fa-regular fa-clock me-1"></i>Waiting</span>
                </div>
                <div class="progress bu-progress-sm">
                  <div class="progress-bar" style="width:0%"></div>
                </div>`;
            fileProgressCont.appendChild(el);
        });
    }

    function updateFileProgress(idx, status, percent = 0) {
        const el = document.getElementById(`fileProgress_${idx}`);
        if (!el) return;
        const badge = el.querySelector('.badge');
        const bar   = el.querySelector('.progress-bar');
        bar.style.width = percent + '%';
        const map = {
            uploading:  ['bu-soft-info',    'fa-solid fa-arrow-up',        'Uploading',  'progress-bar bg-info progress-bar-striped progress-bar-animated'],
            processing: ['bu-soft-warning', 'fa-solid fa-gear fa-spin',    'Processing', 'progress-bar bg-warning progress-bar-striped progress-bar-animated'],
            success:    ['bu-soft-success', 'fa-solid fa-check',           'Done',       'progress-bar bg-success'],
            error:      ['bu-soft-danger',  'fa-solid fa-xmark',           'Error',      'progress-bar bg-danger'],
        };
        const [soft, icon, text, barClass] = map[status] ?? ['bu-soft-secondary', 'fa-solid fa-circle', status, 'progress-bar'];
        badge.className = `badge bu-badge ${soft}`;
        badge.innerHTML = `<i class="${icon} me-1"></i>${escapeHtml(text)}`;
        bar.className   = barClass;
    }

    // ── XHR загрузка ──────────────────────────────────────

    function uploadFiles() {
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', e => {
            if (!e.lengthComputable) return;
            const pct = Math.round(e.loaded / e.total * 100);
            overallBar.style.width     = pct + '%';
            overallPercent.textContent = pct + '%';
            document.querySelectorAll('input[name="torrentFiles[]"]').forEach((inp, idx) => {
                if (inp.files.length) updateFileProgress(idx, pct >= 100 ? 'processing' : 'uploading', pct);
            });
        });

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== XMLHttpRequest.DONE) return;
            try {
                if (!xhr.getResponseHeader('Content-Type')?.includes('application/json')) {
                    throw new Error('Non-JSON response from server');
                }
                const data = JSON.parse(xhr.responseText);
                data.success ? showResults(data) : showError(data.error ?? 'Server error');
            } catch (e) {
                showError('Response error: ' + e.message);
            }
        };

        xhr.onerror   = () => showError('Network error');
        xhr.timeout   = 300000;
        xhr.ontimeout = () => showError('Request timeout');

        xhr.open('POST', BATCH_CONFIG.scriptUrl);
        xhr.send(new FormData(form));
    }

    // ── Отображение результатов ───────────────────────────

    function showResults(data) {
        const allOk = (data.successful ?? 0) === (data.processed ?? 0) && !data.errors?.length;
        setModalState(data.successful > 0 ? 'done' : 'error');

        overallBar.className           = 'progress-bar ' + (data.successful > 0 ? 'bg-success' : 'bg-danger');
        overallBar.style.width         = '100%';
        overallPercent.textContent     = '100%';
        resultsContainer.style.display = 'block';

        // Прогресс по файлам. Сервер пишет фатальные ошибки как "'<имя файла>': ..."
        // (ошибки скриншотов - "'<имя>' screenshot ...", торрент при этом создан),
        // так что ищем именно префикс с двоеточием по исходному имени файла.
        const errors = data.errors ?? [];
        document.querySelectorAll('input[name="torrentFiles[]"]').forEach((inp, idx) => {
            if (!inp.files.length) return;
            const fname  = inp.files[0].name;
            const failed = errors.some(e => e.startsWith(`'${fname}': `) || e.startsWith(`File '${fname}':`));
            updateFileProgress(idx, failed ? 'error' : 'success', 100);
        });

        const s = data.stats ?? {};
        const summary = document.createElement('div');
        summary.className = `bu-alert ${allOk ? 'bu-alert-success' : 'bu-alert-warning'} mb-3`;
        summary.innerHTML = `
            <div class="bu-alert-icon"><i class="fa-solid ${allOk ? 'fa-circle-check' : 'fa-circle-info'}"></i></div>
            <div class="flex-grow-1">
              <strong>Uploaded ${data.successful} of ${data.processed} torrents</strong>
              <div class="bu-result-stats">
                <span><i class="fa-solid fa-image me-1"></i>${s.with_posters ?? 0} with posters</span>
                <span><i class="fa-solid fa-image me-1"></i>${s.with_posters2 ?? 0} with poster 2</span>
                <span><i class="fa-solid fa-images me-1"></i>${s.total_screenshots ?? 0} screenshots</span>
                <span><i class="fa-solid fa-file-csv me-1"></i>${s.csv_imported ?? 0} CSV records</span>
              </div>
            </div>`;
        resultsList.appendChild(summary);

        if (data.results?.length) {
            const list = document.createElement('div');
            list.className = 'bu-results mb-3';
            data.results.forEach(r => {
                // r.name уже экранирован на сервере (htmlspecialchars)
                const item = document.createElement('div');
                item.className = 'bu-result';
                item.innerHTML = `
                    <div class="bu-square bu-soft-success"><i class="fa-solid fa-check"></i></div>
                    <div class="bu-result-body">
                      <div class="bu-result-name">${r.name}</div>
                      <div class="bu-result-meta">
                        <span><i class="fa-solid fa-hashtag"></i>${r.id}</span>
                        <span><i class="fa-solid fa-copy"></i>${r.files} files</span>
                        <span><i class="fa-solid fa-hard-drive"></i>${escapeHtml(r.size)}</span>
                      </div>
                    </div>
                    <div class="bu-result-badges">
                      ${r.has_poster ? '<span class="badge bu-badge bu-soft-info" title="Poster"><i class="fa-solid fa-image"></i></span>' : ''}
                      ${r.has_poster2 ? '<span class="badge bu-badge bu-soft-info" title="Poster 2"><i class="fa-solid fa-image me-1"></i>2</span>' : ''}
                      ${r.screenshots_added ? `<span class="badge bu-badge bu-soft-warning" title="Screenshots"><i class="fa-solid fa-images me-1"></i>${r.screenshots_added}</span>` : ''}
                      ${r.has_imdb ? '<span class="badge bu-badge bu-soft-warning" title="IMDb"><i class="fa-brands fa-imdb"></i></span>' : ''}
                      <a href="${escapeHtml(r.link)}" target="_blank" rel="noopener" class="btn btn-sm rounded-pill bu-btn-soft-primary">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View
                      </a>
                    </div>`;
                list.appendChild(item);
            });
            resultsList.appendChild(list);
        }

        if (data.errors?.length) {
            const err = document.createElement('div');
            err.className = 'bu-alert bu-alert-danger';
            err.innerHTML = `
                <div class="bu-alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="flex-grow-1">
                  <strong>Errors (${data.errors.length})</strong>
                  <ul class="bu-error-list">${data.errors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}</ul>
                </div>`;
            resultsList.appendChild(err);
        }

        closeModalBtn.style.display   = '';
        viewTorrentsBtn.style.display = data.successful > 0 ? '' : 'none';
    }

    function showError(msg) {
        setModalState('error');
        resultsContainer.style.display = 'block';
        overallBar.className           = 'progress-bar bg-danger';
        const err = document.createElement('div');
        err.className = 'bu-alert bu-alert-danger';
        err.innerHTML = `<div class="bu-alert-icon"><i class="fa-solid fa-circle-exclamation"></i></div>`
            + `<div><strong>Error:</strong> ${escapeHtml(msg)}</div>`;
        resultsList.appendChild(err);
        closeModalBtn.style.display = '';
    }
});