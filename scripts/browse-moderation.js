window.ModerationModal = (function () {
    'use strict';

    const settings = {
        totalTorrents: null,
        selectAllId: 'checkAllSwitch',
        checkboxName: 'torrentid[]',
        selectedCountId: 'selectedCountDisplay',
        totalCountId: 'totalTorrentsCount',
        modalSelectedCountId: 'modalSelectedCount',
        actionSelectId: 'actiontype',
        moveBlockId: 'movetorrent',
        actionInfoId: 'actionInfo',
        actionDescriptionId: 'actionDescription',
        applyBtnId: 'applyActionBtn',
        modalId: 'moderationModal',
        formSelector: 'form[name="manage_torrents"]',
        confirmModalId: 'modConfirmModal',
        confirmMessageId: 'modConfirmMessage',
        confirmAcceptBtnId: 'modConfirmAcceptBtn',
        selectedListId: 'selectedTorrentsList',
        posterContainerId: 'modPosterContainer',
        posterWrapperId: 'modPosterWrapper',
        posterPlaceholderId: 'modPosterPlaceholder',
        posterImageId: 'modPosterPreview',
        posterCountId: 'modPosterCount',
        summaryStatsId: 'modSummaryStats',
        summarySizeId: 'modSummarySize',
        summarySeedersId: 'modSummarySeeders',
        summaryLeechersId: 'modSummaryLeechers',
        summaryCategoriesId: 'modSummaryCategories',
        moveCategoryWarningId: 'modMoveCategoryWarning',
        moveCategoryWarningTextId: 'modMoveCategoryWarningText',
        confirmTorrentListId: 'modConfirmTorrentList',
		
		progressContainerId: 'modProgressContainer',
    progressBarId: 'selectionProgress',
    selectedCount2Id: 'selectedCount2',
    totalCount2Id: 'totalCount2',
    progressPercentageId: 'progressPercentage',
	restoreInfoId: 'modRestoreInfo',
    restoreMessageId: 'modRestoreMessage',
		
		
        storageKey: 'modSelectedTorrentIds'
    };

    let modInitialized = false;

    function checkboxSelector() {
        return 'input[name="' + settings.checkboxName + '"]';
    }

    /**
     * Cross-page selection persistence (client-side only). We only ever
     * restore checked state onto checkboxes that already exist on the
     * current page - the set of ids actually submitted is still exactly
     * what's visibly checked, so this can't cause an action to apply to
     * anything the page itself didn't render.
     */
    function saveSelectionToStorage() {
    try {
        let stored = [];
        try {
            const existing = sessionStorage.getItem(settings.storageKey);
            if (existing) stored = JSON.parse(existing);
            if (!Array.isArray(stored)) stored = [];
        } catch (err) {
            stored = [];
        }

        const idSet = new Set(stored);

        // Синхронизируем ТОЛЬКО те ID, чьи чекбоксы реально есть на этой
        // странице (добавляем отмеченные, убираем снятые) - ID с других
        // страниц, которых сейчас нет в DOM, остаются нетронутыми. Раньше
        // тут было sessionStorage.setItem(...) с чекбоксами ТОЛЬКО этой
        // страницы - это стирало выбор с других страниц при каждом клике.
        document.querySelectorAll(checkboxSelector()).forEach(cb => {
            if (cb.checked) {
                idSet.add(cb.value);
            } else {
                idSet.delete(cb.value);
            }
        });

        if (idSet.size > 0) {
            sessionStorage.setItem(settings.storageKey, JSON.stringify([...idSet]));
        } else {
            sessionStorage.removeItem(settings.storageKey);
        }
    } catch (err) {
        // Storage may be unavailable (private mode, quota, etc.) - non-fatal.
    }
}

    /**
     * Общее число выбранных торрентов по ВСЕМ страницам (не только
     * видимым сейчас чекбоксам) - источник истины для счётчика/badge.
     */
    function getStoredSelectionCount() {
        try {
            const stored = sessionStorage.getItem(settings.storageKey);
            if (!stored) return 0;
            const ids = JSON.parse(stored);
            return Array.isArray(ids) ? ids.length : 0;
        } catch (err) {
            return 0;
        }
    }

    /**
     * Полный список ID выбранных торрентов по всем страницам.
     */
    function getStoredSelectionIds() {
        try {
            const stored = sessionStorage.getItem(settings.storageKey);
            if (!stored) return [];
            const ids = JSON.parse(stored);
            return Array.isArray(ids) ? ids : [];
        } catch (err) {
            return [];
        }
    }

    /**
     * Drops the stored selection without touching the live checkboxes.
     * Used right before submitting - if we unchecked the boxes instead,
     * the form would submit with an empty torrentid[] list.
     */
    function clearSelectionStorage() {
    try {
        sessionStorage.removeItem(settings.storageKey);
        // Скрыть уведомление
        const restoreInfo = document.getElementById(settings.restoreInfoId);
        if (restoreInfo) {
            restoreInfo.style.display = 'none';
        }
    } catch (err) {
        // ignore
    }
}

    
	
	
	let restoredCountPending = 0;
	let restoreNotificationShown = false;

	function restoreSelectionFromStorage() {
    let ids;
    let restoredCount = 0;
    
    try {
        const stored = sessionStorage.getItem(settings.storageKey);
        if (!stored) return;
        ids = JSON.parse(stored);
    } catch (err) {
        ids = [];
    }
    
    if (!Array.isArray(ids) || ids.length === 0) return;

    const idSet = new Set(ids);
    const checkboxes = document.querySelectorAll(checkboxSelector());
    
    checkboxes.forEach(cb => {
        if (idSet.has(cb.value) && !cb.checked) {
            cb.checked = true;
            restoredCount++;
            const row = cb.closest('.torrent-row');
            if (row) {
                row.classList.add('selected');
                row.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
            }
        }
    });

    // Уведомление откладываем до первого открытия модалки (см.
    // show.bs.modal в initModal()) - раньше 5-секундный таймер стартовал
    // прямо при загрузке страницы, ДО того как пользователь физически
    // открывал модалку. Если открыть модалку через пару секунд после
    // загрузки, уведомление успевало "натикать" большую часть времени
    // ещё невидимым, и казалось, что оно исчезает почти сразу.
    restoredCountPending = restoredCount;
    
    return restoredCount;
}

/**
 * Show notification that selection was restored
 */
function showRestoreNotification(count) {
    const restoreInfo = document.getElementById(settings.restoreInfoId);
    const restoreMessage = document.getElementById(settings.restoreMessageId);
    
    if (!restoreInfo || !restoreMessage) return;
    
    const message = count === 1 
        ? '1 torrent selection restored from previous session'
        : count + ' torrents selection restored from previous session';
    
    restoreMessage.textContent = message;
    restoreInfo.style.display = 'block';

    // Остаётся видимым, пока пользователь сам не закроет крестиком -
    // раньше скрывалось автоматически через 5 секунд, что было слишком
    // мало времени, чтобы гарантированно успеть его заметить.
    const closeBtn = document.getElementById('modRestoreCloseBtn');
    if (closeBtn && !closeBtn.dataset.bound) {
        closeBtn.dataset.bound = '1';
        closeBtn.addEventListener('click', () => {
            restoreInfo.style.display = 'none';
        });
    }
}
	
	
	
	

    /**
     * Cross-page torrent metadata cache. For IDs selected on other pages
     * we only have the bare ID from sessionStorage - no title/poster is
     * available in the DOM. Fetched lazily via
     * xmlhttp.php?action=get_torrents_by_ids and cached in memory for the
     * life of the page, so the same IDs aren't re-fetched on every
     * selection change.
     */
    const crossPageCache = new Map();
    let crossPageFetchInFlight = false;

    function getVisibleCheckboxIds() {
        return new Set(
            Array.from(document.querySelectorAll(checkboxSelector())).map(cb => cb.value)
        );
    }

    function getMissingCrossPageIds() {
        const visibleIds = getVisibleCheckboxIds();
        return getStoredSelectionIds().filter(id => !visibleIds.has(id) && !crossPageCache.has(id));
    }

    function fetchCrossPageMetadata() {
        if (crossPageFetchInFlight) return;
        const missing = getMissingCrossPageIds();
        if (missing.length === 0) return;

        crossPageFetchInFlight = true;
        fetch('xmlhttp.php?action=get_torrents_by_ids&ids=' + encodeURIComponent(missing.join(',')))
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) {
                    data.forEach(t => {
                        crossPageCache.set(String(t.id), { name: t.name, image_url: t.image_url });
                    });
                }
            })
            .catch(() => {
                // Non-fatal - cross-page items just keep showing as "#id"
                // in the list until the next successful fetch attempt.
            })
            .finally(() => {
                crossPageFetchInFlight = false;
                // Данные подгружены - перерисовываем список и превью,
                // чтобы заменить "#id" на реальные названия/картинки.
                renderSelectedList();
                updatePosterPreview();
            });
    }

    /**
     * Render the mini-list of selected torrent titles inside the modal.
     */
    /**
     * Shared HTML builder for "here's what you selected" mini-lists -
     * used both inside the main modal and inside the danger-confirm modal.
     * Includes cross-page items (from crossPageCache/sessionStorage), not
     * just checkboxes visibly checked on the current page.
     */
    function buildSelectedListHtml(maxShown) {
        const visibleChecked = Array.from(document.querySelectorAll(checkboxSelector() + ':checked'));
        const visibleIds = new Set(visibleChecked.map(cb => cb.value));

        const visibleItems = visibleChecked.map(cb => ({
            id: cb.value,
            title: cb.dataset.title || ('#' + cb.value)
        }));

        const crossPageItems = getStoredSelectionIds()
            .filter(id => !visibleIds.has(id))
            .map(id => {
                const cached = crossPageCache.get(id);
                return { id, title: cached ? cached.name : ('#' + id) };
            });

        const allItems = visibleItems.concat(crossPageItems);
        if (allItems.length === 0) return '';

        const items = allItems.slice(0, maxShown).map(item => {
            return '<li>' + item.title.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</li>';
        }).join('');

        const more = allItems.length > maxShown
            ? '<li class="mod-selected-list-more">…and ' + (allItems.length - maxShown) + ' more</li>'
            : '';

        return '<ul>' + items + more + '</ul>';
    }

    function renderSelectedList() {
        const container = document.getElementById(settings.selectedListId);
        if (!container) return;

        const html = buildSelectedListHtml(20);

        if (!html) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        container.innerHTML = html;
        container.style.display = 'block';
    }

    /**
     * Same idea, rendered inside the danger-confirm modal so "delete /
     * ban / nuke N torrents?" shows exactly which ones, not just a count.
     */
    function renderConfirmTorrentList() {
        const container = document.getElementById(settings.confirmTorrentListId);
        if (!container) return;

        const html = buildSelectedListHtml(10);

        if (!html) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        container.innerHTML = html;
        container.style.display = 'block';
    }

    /**
     * Update poster preview from t_image column
     */
    function updatePosterPreview() {
        const container = document.getElementById(settings.posterContainerId);
        const wrapper = document.getElementById(settings.posterWrapperId);
        const placeholder = document.getElementById(settings.posterPlaceholderId);
        const posterImg = document.getElementById(settings.posterImageId);
        const posterCount = document.getElementById(settings.posterCountId);
        
        if (!container) return;
        
        const checked = document.querySelectorAll(checkboxSelector() + ':checked');
        const count = getStoredSelectionCount();
        
        if (count === 0) {
            if (wrapper) wrapper.style.display = 'none';
            if (placeholder) placeholder.style.display = 'block';
            container.classList.remove('has-poster');
            return;
        }

        // Собираем постеры за один проход: сначала data-image с чекбокса,
        // и только если его нет для конкретного торрента - fallback на
        // <img> в его строке таблицы. Раньше это были два полных прохода
        // по всем отмеченным элементам (второй запускался только если
        // *вообще ни один* чекбокс не дал data-image), из-за чего строки
        // без data-image молча выпадали из подсчёта, если хоть один другой
        // чекбокс это поле имел.
        //
        // Отображается всё равно максимум 12 миниатюр, поэтому дорогой
        // DOM-обход (closest + querySelector) для fallback ограничиваем
        // первыми FALLBACK_SCAN_LIMIT чекбоксами без data-image - при
        // выборе, близком к лимиту bulk-действий (см. manage_torrents.php,
        // 1000 шт.), незачем гонять сотни лишних обращений к DOM ради
        // счётчика "N posters".
        const FALLBACK_SCAN_LIMIT = 200;
        let fallbackScanned = 0;
        const posters = [];

        checked.forEach(cb => {
            const imageUrl = cb.dataset.image || '';
            if (imageUrl.trim() !== '') {
                posters.push({ url: imageUrl, title: cb.dataset.title || '', id: cb.value });
                return;
            }

            if (fallbackScanned >= FALLBACK_SCAN_LIMIT) return;
            fallbackScanned++;

            const row = cb.closest('.torrent-row');
            const posterImgEl = row ? row.querySelector('.torrent-poster') : null;
            if (posterImgEl && posterImgEl.src && !posterImgEl.src.includes('data:image')) {
                posters.push({ url: posterImgEl.src, title: cb.dataset.title || '', id: cb.value });
            }
        });

        // Постеры для торрентов с других страниц - берём из кэша, если
        // уже подгружен. Пусто до первого успешного fetchCrossPageMetadata().
        const visibleIds = getVisibleCheckboxIds();
        getStoredSelectionIds()
            .filter(id => !visibleIds.has(id))
            .forEach(id => {
                const cached = crossPageCache.get(id);
                if (cached && cached.image_url) {
                    posters.push({ url: cached.image_url, title: cached.name || '', id });
                }
            });

        if (posters.length === 0) {
            if (wrapper) wrapper.style.display = 'none';
            if (placeholder) {
                placeholder.style.display = 'block';
                const p = placeholder.querySelector('p');
                if (p) p.textContent = 'No posters available';
            }
            container.classList.remove('has-poster');
            return;
        }
        
        container.classList.add('has-poster');
        if (placeholder) placeholder.style.display = 'none';
        
        // Если один постер - показываем крупно
        if (posters.length === 1) {
            if (wrapper) {
                wrapper.style.display = 'flex';
                wrapper.style.alignItems = 'center';
                wrapper.style.justifyContent = 'center';
            }
            if (posterImg) {
                posterImg.src = posters[0].url;
                posterImg.alt = posters[0].title || 'Torrent poster';
                posterImg.title = posters[0].title || '';
                posterImg.style.display = 'block';
                posterImg.style.maxHeight = '350px';
                posterImg.style.maxWidth = '100%';
                posterImg.style.objectFit = 'contain';
                posterImg.onerror = function() {
                    this.style.display = 'none';
                    if (placeholder) {
                        placeholder.style.display = 'block';
                        const p = placeholder.querySelector('p');
                        if (p) p.textContent = 'Image not available';
                    }
                };
                posterImg.onload = function() {
                    if (placeholder) placeholder.style.display = 'none';
                };
            }
            if (posterCount) {
                posterCount.textContent = '';
                posterCount.style.display = 'none';
            }
            // Убираем сетку если была
            if (wrapper) {
                const grid = wrapper.querySelector('.mod-poster-grid');
                if (grid) grid.remove();
            }
            return;
        }
        
        // Много постеров - показываем сетку
        if (wrapper) {
            wrapper.style.display = 'flex';
            wrapper.style.alignItems = 'flex-start';
            wrapper.style.justifyContent = 'center';
            
            let grid = wrapper.querySelector('.mod-poster-grid');
            if (!grid) {
                grid = document.createElement('div');
                grid.className = 'mod-poster-grid';
                wrapper.appendChild(grid);
            }
            grid.innerHTML = '';
            
            // Показываем первые 12 постеров
            const maxShow = 12;
            const showPosters = posters.slice(0, maxShow);
            showPosters.forEach(poster => {
                const thumbWrap = document.createElement('div');
                thumbWrap.className = 'mod-poster-thumb-wrap';

                const thumb = document.createElement('img');
                thumb.src = poster.url;
                // .poster-link + data-zoom hooks into the same hover-zoom
                // overlay (#posterZoomOverlay) already used for the main
                // torrent list, so small grid thumbnails get a large
                // preview on hover for free.
                thumb.className = 'mod-poster-thumb poster-link';
                thumb.dataset.zoom = poster.url;
                thumb.alt = poster.title || 'Poster';
                thumb.title = poster.title || '';
                thumb.loading = 'lazy';
                thumb.onerror = function() {
                    thumbWrap.style.display = 'none';
                };
                thumbWrap.appendChild(thumb);

                if (poster.id) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'mod-poster-thumb-remove';
                    removeBtn.setAttribute('aria-label', 'Remove from selection');
                    removeBtn.title = 'Remove from selection';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const cb = document.querySelector(
                            checkboxSelector() + '[value="' + CSS.escape(poster.id) + '"]'
                        );
                        if (cb && cb.checked) {
                            cb.checked = false;
                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                    thumbWrap.appendChild(removeBtn);
                }

                grid.appendChild(thumbWrap);
            });
            
            if (posters.length > maxShow) {
                const more = document.createElement('div');
                more.className = 'mod-poster-thumb mod-poster-more';
                more.textContent = '+' + (posters.length - maxShow);
                more.style.cssText = `
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #0d6efd;
                    color: white;
                    font-weight: 700;
                    font-size: 1.2rem;
                    border-radius: 6px;
                    aspect-ratio: 1/1.4;
                    min-height: 80px;
                `;
                grid.appendChild(more);
            }
            
            if (posterImg) posterImg.style.display = 'none';
            if (posterCount) {
                posterCount.textContent = posters.length + ' posters';
                posterCount.style.display = 'block';
            }
        }
    }
	
	
	/**
 * Update progress bar
 */
function updateProgress() {
    const total = document.querySelectorAll(checkboxSelector()).length;
    const checked = document.querySelectorAll(checkboxSelector() + ':checked').length;
    const percentage = total > 0 ? Math.round((checked / total) * 100) : 0;
    
    const progressBar = document.getElementById(settings.progressBarId);
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
    }
    
    const container = document.getElementById(settings.progressContainerId);
    if (container) {
        container.style.display = total > 0 ? 'block' : 'none';
    }
    
    const count2 = document.getElementById(settings.selectedCount2Id);
    if (count2) count2.textContent = checked;
    
    const total2 = document.getElementById(settings.totalCount2Id);
    if (total2) total2.textContent = total;
    
    const percent = document.getElementById(settings.progressPercentageId);
    if (percent) percent.textContent = percentage + '%';
}

    /**
     * Human-readable file size, mirroring the site's mksize() helper
     * closely enough for a summary line (binary units, 2 decimals).
     */
    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
        const value = bytes / Math.pow(1024, i);
        return (i === 0 ? value.toFixed(0) : value.toFixed(2)) + ' ' + units[i];
    }

    /**
     * Aggregate size / seeders / leechers / distinct categories across the
     * current selection and render them next to the poster preview.
     */
    function updateSummaryStats() {
        const stats = document.getElementById(settings.summaryStatsId);
        if (!stats) return;

        const checked = document.querySelectorAll(checkboxSelector() + ':checked');

        if (checked.length === 0) {
            stats.style.display = 'none';
            return;
        }

        let totalSize = 0;
        let totalSeeders = 0;
        let totalLeechers = 0;
        const categories = new Set();

        checked.forEach(cb => {
            totalSize += parseInt(cb.dataset.size || '0', 10) || 0;
            totalSeeders += parseInt(cb.dataset.seeders || '0', 10) || 0;
            totalLeechers += parseInt(cb.dataset.leechers || '0', 10) || 0;
            if (cb.dataset.category) categories.add(cb.dataset.category);
        });

        const sizeEl = document.getElementById(settings.summarySizeId);
        const seedersEl = document.getElementById(settings.summarySeedersId);
        const leechersEl = document.getElementById(settings.summaryLeechersId);
        const categoriesEl = document.getElementById(settings.summaryCategoriesId);

        if (sizeEl) sizeEl.textContent = formatBytes(totalSize);
        if (seedersEl) seedersEl.textContent = totalSeeders.toLocaleString();
        if (leechersEl) leechersEl.textContent = totalLeechers.toLocaleString();
        if (categoriesEl) {
            categoriesEl.textContent = categories.size + (categories.size === 1 ? ' category' : ' categories');
        }

        stats.style.display = 'flex';
    }

    /**
     * When "Move" is selected, warn if the selected torrents span more
     * than one category - moving them all into a single target category
     * in one click is easy to trigger by accident on a mixed selection.
     */
    function updateMoveCategoryWarning() {
        const warning = document.getElementById(settings.moveCategoryWarningId);
        if (!warning) return;

        const actionSelect = document.getElementById(settings.actionSelectId);
        if (!actionSelect || actionSelect.value !== 'move') {
            warning.style.display = 'none';
            return;
        }

        const checked = document.querySelectorAll(checkboxSelector() + ':checked');
        const categoryNames = new Map();
        checked.forEach(cb => {
            if (cb.dataset.category) {
                categoryNames.set(cb.dataset.category, cb.dataset.catname || ('#' + cb.dataset.category));
            }
        });

        if (categoryNames.size <= 1) {
            warning.style.display = 'none';
            return;
        }

        const textEl = document.getElementById(settings.moveCategoryWarningTextId);
        if (textEl) {
            const names = Array.from(categoryNames.values()).slice(0, 5).join(', ');
            const extra = categoryNames.size > 5 ? ` and ${categoryNames.size - 5} more` : '';
            textEl.textContent = `Selected torrents come from ${categoryNames.size} different categories (${names}${extra}). They will all be moved to the category you pick below.`;
        }
        warning.style.display = 'block';
    }
	
	
	
	
	
	

    /**
     * Toggle all checkboxes
     */
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll(checkboxSelector());
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
            const row = checkbox.closest('.torrent-row');
            if (row) {
                if (checkbox.checked) {
                    row.classList.add('selected');
                    row.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
                } else {
                    row.classList.remove('selected');
                    row.style.backgroundColor = '';
                }
            }
        });
        updateTorrentSelectionCount();
    }

    /**
     * "Select all matching current filter" - по ВСЕМ страницам, не только
     * видимой. Запрашивает у сервера полный список ID (та же WHERE-логика,
     * что строит сам список на странице), объединяет их с уже сохранённым
     * выбором в sessionStorage, отмечает видимые чекбоксы и подгружает
     * названия/постеры для остальных через уже существующий
     * fetchCrossPageMetadata().
     */
    function selectAllFilteredAcrossPages(btn) {
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Selecting...';
        }

        fetch('xmlhttp.php?action=select_all_filtered&' + window.location.search.replace(/^\?/, ''))
            .then(r => r.json())
            .then(data => {
                if (!data.ids || !Array.isArray(data.ids)) {
                    showModerationToast('Could not select all - please try again', 'error');
                    return;
                }

                let stored = [];
                try {
                    const existing = sessionStorage.getItem(settings.storageKey);
                    if (existing) stored = JSON.parse(existing);
                    if (!Array.isArray(stored)) stored = [];
                } catch (err) {
                    stored = [];
                }

                const idSet = new Set(stored);
                data.ids.forEach(id => idSet.add(String(id)));
                sessionStorage.setItem(settings.storageKey, JSON.stringify([...idSet]));

                // Отмечаем видимые на этой странице чекбоксы, входящие в
                // результат - остальные (с других страниц) остаются в
                // sessionStorage и подтянутся через fetchCrossPageMetadata().
                document.querySelectorAll(checkboxSelector()).forEach(cb => {
                    if (idSet.has(cb.value)) {
                        cb.checked = true;
                        const row = cb.closest('.torrent-row');
                        if (row) {
                            row.classList.add('selected');
                            row.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
                        }
                    }
                });

                updateTorrentSelectionCount();

                const capNote = data.capped ? ` (capped at ${data.count})` : '';
                showModerationToast(`Selected ${data.count} torrent(s) matching current filter${capNote}`, 'success');
            })
            .catch(() => {
                showModerationToast('Network error while selecting all', 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            });
    }

    /**
     * Update master checkbox state
     */
    function updateMasterCheckbox() {
        const checkboxes = document.querySelectorAll(checkboxSelector());
        const masterCheckbox = document.getElementById(settings.selectAllId);

        if (checkboxes.length === 0) {
            if (masterCheckbox) {
                masterCheckbox.checked = false;
                masterCheckbox.indeterminate = false;
            }
            return;
        }

        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);

        if (masterCheckbox) {
            masterCheckbox.checked = allChecked;
            masterCheckbox.indeterminate = !allChecked && anyChecked;
        }
    }

    /**
     * Handle checkbox change
     */
    function handleCheckboxChange(e) {
        const checkbox = e.target;
        const row = checkbox.closest('.torrent-row');

        if (row) {
            if (checkbox.checked) {
                row.classList.add('selected');
                row.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
            } else {
                row.classList.remove('selected');
                row.style.backgroundColor = '';
            }
        }

        updateMasterCheckbox();
        updateTorrentSelectionCount();
    }

    /**
     * Show/hide the "move to category" block. Kept as a plain global
     * (window.check_it) since some markup calls it inline via
     * onchange="check_it(this)" — but it now reads settings.moveBlockId
     * instead of a hardcoded id, so it stays consistent with whatever
     * was passed to init().
     */
    function check_it(el) {
        const moveElement = document.getElementById(settings.moveBlockId);
        if (moveElement) {
            moveElement.style.display = el.value === 'move' ? 'block' : 'none';
        }
    }

    /**
     * Update selection counters
     */
    function updateTorrentSelectionCount() {
        // Сначала синхронизируем состояние ЭТОЙ страницы в хранилище,
        // потом считаем итог по ВСЕМ страницам - иначе счётчик отставал
        // бы на один клик от реального объединённого состояния.
        saveSelectionToStorage();
        const count = getStoredSelectionCount();
        fetchCrossPageMetadata();

        const counters = [settings.selectedCountId, settings.modalSelectedCountId, 'selectedCount'];
        counters.forEach(id => {
            if (!id) return;
            const el = document.getElementById(id);
            if (el) el.textContent = count;
        });

        const badge = document.getElementById('totalSelectedBadge');
        if (badge) {
            if (count > 0) {
                badge.className = 'badge bg-success rounded-pill px-3 py-2';
                badge.innerHTML = `<i class="fas fa-check-circle me-1"></i> ${count} selected`;
            } else {
                badge.className = 'badge bg-primary rounded-pill px-3 py-2';
                badge.innerHTML = `<i class="fas fa-check-circle me-1"></i> 0 selected`;
            }
        }

        const applyBtn = document.getElementById(settings.applyBtnId);
        if (applyBtn) {
            applyBtn.disabled = count === 0;
            applyBtn.innerHTML = count > 0
                ? `<i class="fas fa-play me-1"></i> Apply (${count})`
                : '<i class="fas fa-play me-1"></i> Apply';
        }

        updateMasterCheckbox();

        const selectionInfo = document.querySelector('.mod-selection-info');
        if (selectionInfo) {
            const strong = selectionInfo.querySelector('strong');
            if (strong) strong.textContent = count;
            selectionInfo.className = count > 0
                ? 'mod-selection-info alert alert-success alert-dismissible fade show'
                : 'mod-selection-info alert alert-info alert-dismissible fade show';
        }

        renderSelectedList();
        updatePosterPreview();
        updateSummaryStats();
        updateMoveCategoryWarning();
		updateProgress();
    }

    /**
     * Confirm dangerous actions.
     *
     * Returns a Promise<boolean> now (used to be a synchronous confirm()).
     * Uses the styled #modConfirmModal if it's present on the page, and
     * falls back to the native confirm() dialog if it isn't - so this
     * still works even on a page that hasn't added that markup.
     */
    function confirmDangerousAction(action, count) {
        const actionNames = { delete: 'delete', banned: 'ban/unban', nuke: 'mark as Nuked' };
        const actionName = actionNames[action] || action;

        // Уточняем в тексте, если часть выбранного - с других страниц
        // (не видна прямо сейчас), раз действие теперь применяется ко
        // всему накопленному выбору, а не только к видимому на экране.
        const visibleCount = document.querySelectorAll(checkboxSelector() + ':checked').length;
        const crossPageNote = count > visibleCount
            ? ` (${count - visibleCount} of these are on other pages)`
            : '';

        const message = `You are about to ${actionName} ${count} torrent(s)${crossPageNote}.` +
            (action === 'delete' ? ' This action is IRREVERSIBLE! All data will be permanently deleted.' : '') +
            ' Are you sure you want to continue?';

        const modal = document.getElementById(settings.confirmModalId);
        const msgEl = document.getElementById(settings.confirmMessageId);
        const acceptBtn = document.getElementById(settings.confirmAcceptBtnId);

        if (!modal || !msgEl || !acceptBtn || typeof bootstrap === 'undefined') {
            return Promise.resolve(window.confirm('⚠️ WARNING!\n\n' + message));
        }

        msgEl.textContent = message;
        renderConfirmTorrentList();

        return new Promise(resolve => {
            const modalInstance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
            let settled = false;

            function cleanup() {
                acceptBtn.removeEventListener('click', onAccept);
                modal.removeEventListener('hidden.bs.modal', onHidden);
            }

            function onAccept() {
                settled = true;
                cleanup();
                modalInstance.hide();
                resolve(true);
            }

            function onHidden() {
                cleanup();
                if (!settled) resolve(false);
            }

            acceptBtn.addEventListener('click', onAccept);
            modal.addEventListener('hidden.bs.modal', onHidden);

            modalInstance.show();
        });
    }

    /**
     * Show toast notification
     */
    function showModerationToast(message, type = 'success') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }

        const toastContainer = document.querySelector('.toast-container') || (() => {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        })();

        const toast = document.createElement('div');
        toast.className = `toast show bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} text-white border-0 shadow-lg`;
        toast.role = 'alert';
        toast.style.borderRadius = '12px';

        toast.innerHTML = `
            <div class="toast-header bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} text-white border-0">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                <strong class="me-auto">${type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Info'}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;

        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    /**
     * Clear all selection
     */
    function clearAllSelection() {
        const checkboxes = document.querySelectorAll(checkboxSelector());
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            const row = checkbox.closest('.torrent-row');
            if (row) {
                row.classList.remove('selected');
                row.style.backgroundColor = '';
            }
        });

        const masterCheckbox = document.getElementById(settings.selectAllId);
        if (masterCheckbox) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
        }

        // Полностью стираем сохранённый выбор, включая ID с других
        // страниц - иначе "Clear All" снимал бы галочки только на этой
        // странице, а выбор с остальных страниц незаметно оставался бы
        // в sessionStorage.
        clearSelectionStorage();

        updateTorrentSelectionCount();
    }

    /**
     * Handle Apply button click
     */
    async function handleApply(e) {
        e.preventDefault();

        const actionSelect = document.getElementById(settings.actionSelectId);
        const count = getStoredSelectionCount();
        const form = document.querySelector(settings.formSelector);

        if (!form) {
            showModerationToast('Form not found', 'error');
            return;
        }

        if (!actionSelect || actionSelect.value === '0' || actionSelect.value === '') {
            showModerationToast('Please select an action', 'warning');
            return;
        }

        if (count === 0) {
            showModerationToast('Please select at least one torrent', 'warning');
            return;
        }

        const dangerous = ['delete', 'banned', 'nuke'];
        if (dangerous.includes(actionSelect.value)) {
            const confirmed = await confirmDangerousAction(actionSelect.value, count);
            if (!confirmed) {
                return;
            }
        }

        if (actionSelect.value === 'move') {
            const moveBlock = document.getElementById(settings.moveBlockId);
            if (moveBlock) {
                const catSelect = moveBlock.querySelector('select');
                if (catSelect && (catSelect.value === '0' || catSelect.value === '')) {
                    showModerationToast('Please select a category to move to', 'warning');
                    return;
                }
            }
        }

        const applyBtn = document.getElementById(settings.applyBtnId);
        if (applyBtn) {
            applyBtn.disabled = true;
            applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
        }

        const modal = document.getElementById(settings.modalId);
        if (modal) {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        }

        // Добавляем скрытые поля для ID, выбранных на ДРУГИХ страницах -
        // их чекбоксов физически нет в DOM этой страницы, поэтому обычный
        // form.submit() их бы просто не отправил. Раньше Apply затрагивал
        // только то, что видно на экране прямо сейчас, даже если счётчик
        // показывал больше (выбор с других страниц).
        const visibleIds = new Set(
            Array.from(document.querySelectorAll(checkboxSelector())).map(cb => cb.value)
        );
        form.querySelectorAll('input[type="hidden"][data-cross-page-selection]').forEach(el => el.remove());
        getStoredSelectionIds().forEach(id => {
            if (!visibleIds.has(id)) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = settings.checkboxName;
                hidden.value = id;
                hidden.setAttribute('data-cross-page-selection', '1');
                form.appendChild(hidden);
            }
        });

        // The action is about to run server-side - don't let the old
        // selection come back when we land back on this page.
        clearSelectionStorage();

        setTimeout(function() {
            form.submit();
        }, 300);
    }

    /**
     * Bind the modal itself: show event, action-select change, apply button.
     */
    function initModal() {
        const modal = document.getElementById(settings.modalId);
        if (!modal) return;

        const form = document.querySelector(settings.formSelector);
        if (!form) return;

        new bootstrap.Modal(modal);

        modal.addEventListener('show.bs.modal', function() {
            updateTorrentSelectionCount();

            if (!restoreNotificationShown && restoredCountPending > 0) {
                restoreNotificationShown = true;
                showRestoreNotification(restoredCountPending);
            }

            const actionSelect = document.getElementById(settings.actionSelectId);
            if (actionSelect) {
                actionSelect.value = '0';
                actionSelect.dispatchEvent(new Event('change'));
            }
        });

        // Focus the action select once the modal has finished opening.
        modal.addEventListener('shown.bs.modal', function() {
            const actionSelect = document.getElementById(settings.actionSelectId);
            if (actionSelect) actionSelect.focus();
        });

        // Enter-to-apply: skip it while focus is on a form control that
        // already gives Enter a meaning of its own (choosing an option,
        // adding a newline, activating a button).
        modal.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            const tag = (e.target.tagName || '').toUpperCase();
            if (['SELECT', 'TEXTAREA', 'BUTTON', 'A'].includes(tag)) return;

            const applyBtn = document.getElementById(settings.applyBtnId);
            if (applyBtn && !applyBtn.disabled) {
                e.preventDefault();
                applyBtn.click();
            }
        });

        const actionSelect = document.getElementById(settings.actionSelectId);
        if (actionSelect) {
            actionSelect.addEventListener('change', function() {
                const value = this.value;
                const moveBlock = document.getElementById(settings.moveBlockId);
                const actionInfo = document.getElementById(settings.actionInfoId);
                const actionDesc = document.getElementById(settings.actionDescriptionId);

                updateMoveCategoryWarning();

                if (moveBlock) {
                    const isMove = value === 'move';
                    moveBlock.style.display = isMove ? 'block' : 'none';

                    if (isMove) {
                        moveBlock.style.animation = 'fadeInUp 0.3s ease';
                        setTimeout(() => {
                            const catSelect = moveBlock.querySelector('select');
                            if (catSelect) catSelect.focus();
                        }, 300);
                    }
                }

                const descriptions = {
                    move: 'Move selected torrents to another category',
                    delete: '⚠️ PERMANENTLY DELETE selected torrents and all associated data. This action is irreversible!',
                    sticky: 'Toggle "Sticky" status for selected torrents',
                    visible: 'Toggle visibility for selected torrents',
                    banned: '⚠️ Toggle ban status for selected torrents',
                    nuke: '⚠️ Mark selected torrents as "Nuked" (rule violation)',
                    openclose: 'Open/Close comments for selected torrents',
                    free: 'Toggle Freeleech for selected torrents',
                    silver: 'Toggle Silverleech for selected torrents',
                    doubleupload: 'Toggle Double Upload for selected torrents',
                    thirtypercent: 'Toggle 30% Leech for selected torrents',
                    anonymous: 'Make selected torrents anonymous or restore authorship',
                    request: 'Toggle "Request" status for selected torrents'
                };

                if (actionInfo && actionDesc) {
                    if (value && value !== '0' && descriptions[value]) {
                        const desc = descriptions[value];
                        actionDesc.textContent = desc;
                        actionInfo.style.display = 'block';
                        actionInfo.style.animation = 'fadeInUp 0.3s ease';

                        const dangerous = ['delete', 'banned', 'nuke'];
                        const alertEl = actionInfo.querySelector('.alert');
                        if (alertEl) {
                            if (dangerous.includes(value)) {
                                alertEl.className = 'alert alert-danger mt-3';
                                alertEl.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>
                                                    <span id="${settings.actionDescriptionId}">${desc}</span>`;
                            } else {
                                alertEl.className = 'alert alert-warning mt-3';
                                alertEl.innerHTML = `<i class="fas fa-info-circle me-2"></i>
                                                    <span id="${settings.actionDescriptionId}">${desc}</span>`;
                            }
                        }
                    } else {
                        actionInfo.style.display = 'none';
                    }
                }
            });
        }

        const applyBtn = document.getElementById(settings.applyBtnId);
        if (applyBtn) {
            applyBtn.addEventListener('click', handleApply);
        }

        const clearAllBtn = document.getElementById('clearAllSelectionsBtn');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', clearAllSelection);
        }
    }

    /**
     * Bind checkbox delegation, master checkbox, mutation observer and
     * highlight already-checked rows on load.
     */
    function bindSelectionEvents() {
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === settings.checkboxName) {
                handleCheckboxChange(e);
            }
        });

        const masterCheckbox = document.getElementById(settings.selectAllId);
        if (masterCheckbox) {
            masterCheckbox.addEventListener('change', function() {
                toggleAllCheckboxes(this);
            });
        }

        const tableBody = document.querySelector('#listtorrents tbody');
        if (tableBody) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        updateTorrentSelectionCount();
                    }
                });
            });
            observer.observe(tableBody, { childList: true, subtree: true });
        }

        // Bring back whatever was checked on a previous page this session,
        // then fall back to whatever the server already rendered as checked.
        restoreSelectionFromStorage();

        document.querySelectorAll(checkboxSelector() + ':checked').forEach(checkbox => {
            const row = checkbox.closest('.torrent-row');
            if (row) {
                row.classList.add('selected');
                row.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
            }
        });
    }

    /**
     * "Delete" key opens the moderation modal with "Delete selected"
     * pre-picked, as a fast path for a selection the mod already made in
     * the table. Guarded against firing while typing anywhere (inputs,
     * textareas, selects, contenteditable) and against an empty selection,
     * so it can't do anything the mod didn't already set up by hand.
     */
    function bindKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Delete') return;

            const targetTag = (e.target.tagName || '').toUpperCase();
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(targetTag) || e.target.isContentEditable) {
                return;
            }

            const count = document.querySelectorAll(checkboxSelector() + ':checked').length;
            if (count === 0) return;

            const modal = document.getElementById(settings.modalId);
            const actionSelect = document.getElementById(settings.actionSelectId);
            if (!modal || !actionSelect || typeof bootstrap === 'undefined') return;

            e.preventDefault();

            actionSelect.value = 'delete';
            actionSelect.dispatchEvent(new Event('change'));

            const modalInstance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
            modalInstance.show();
        });
    }

    /**
     * The single entry point. Merges any passed-in config over the
     * defaults *before* anything gets bound, then wires up the modal
     * and selection behaviour exactly once.
     */
    function init(userConfig) {
        Object.assign(settings, userConfig || {});

        if (settings.totalTorrents !== null && settings.totalCountId) {
            const totalEl = document.getElementById(settings.totalCountId);
            if (totalEl) totalEl.textContent = settings.totalTorrents;
        }

        if (modInitialized) {
            updateTorrentSelectionCount();
            return;
        }
        modInitialized = true;

        initModal();
        bindSelectionEvents(); // restores sessionStorage selection internally
        bindKeyboardShortcuts();
        updateTorrentSelectionCount(); // count/mini-list computed after restore
    }

    // check_it stays available as a plain global for any markup that
    // still calls it inline (onchange="check_it(this)").
    window.check_it = check_it;

    return {
        updateCount: updateTorrentSelectionCount,
        clearSelection: clearAllSelection,
        getCheckedCount: () => getStoredSelectionCount(),
        showToast: showModerationToast,
        confirmAction: confirmDangerousAction,
        apply: handleApply,
        selectAllFiltered: selectAllFilteredAcrossPages,
        init: init
    };
})();

// ── Auto-initialize with the page's config ──────────────────
// window.browseModTotalTorrents - единственное значение, которое реально
// приходит из PHP ($threadcount) - задаётся крошечным инлайн-скриптом в
// browse.php, всё остальное здесь статично.
document.addEventListener("DOMContentLoaded", function() {
    if (typeof ModerationModal !== "undefined" && ModerationModal.init) {
        ModerationModal.init({
            totalTorrents: window.browseModTotalTorrents || 0,
            selectAllId: "checkAllSwitch",
            checkboxName: "torrentid[]",
            selectedCountId: "selectedCountDisplay",
            totalCountId: "totalTorrentsCount",
            modalSelectedCountId: "modalSelectedCount",
            actionSelectId: "actiontype",
            moveBlockId: "movetorrent",
            actionInfoId: "actionInfo",
            actionDescriptionId: "actionDescription",
            confirmModalId: "modConfirmModal",
            confirmMessageId: "modConfirmMessage",
            confirmAcceptBtnId: "modConfirmAcceptBtn",
            selectedListId: "selectedTorrentsList",
            posterContainerId: "modPosterContainer",
            posterWrapperId: "modPosterWrapper",
            posterPlaceholderId: "modPosterPlaceholder",
            posterImageId: "modPosterPreview",
            posterCountId: "modPosterCount",
            summaryStatsId: "modSummaryStats",
            summarySizeId: "modSummarySize",
            summarySeedersId: "modSummarySeeders",
            summaryLeechersId: "modSummaryLeechers",
            summaryCategoriesId: "modSummaryCategories",
            moveCategoryWarningId: "modMoveCategoryWarning",
            moveCategoryWarningTextId: "modMoveCategoryWarningText",
            progressContainerId: "modProgressContainer",
            progressBarId: "selectionProgress",
            selectedCount2Id: "selectedCount2",
            totalCount2Id: "totalCount2",
            progressPercentageId: "progressPercentage",
            confirmTorrentListId: "modConfirmTorrentList",
            restoreInfoId: "modRestoreInfo",
            restoreMessageId: "modRestoreMessage"
        });
    } else {
        if (typeof updateTorrentSelectionCount === "function") {
            updateTorrentSelectionCount();
        }
        if (typeof initModerationModal === "function") {
            initModerationModal();
        }
    }
});