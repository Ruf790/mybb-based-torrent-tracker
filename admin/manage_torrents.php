<?php
declare(strict_types=1);


require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_category.php';
require_once INC_PATH . '/functions_bookmark.php';


class TorrentManager 
{
    private array $errors = [];
    
    public function showErrors(): void {
        global $lang;
        
        if (!empty($this->errors)) {
            $errors = implode('<br>', $this->errors);
            echo '
            <div class="alert-modern alert-modern-danger fade-in-up">
                <div class="alert-modern-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-modern-content">
                    <h5 class="alert-modern-title">' . $lang->global['error'] . '</h5>
                    <p class="alert-modern-message">' . $errors . '</p>
                </div>
                <button type="button" class="alert-modern-close" data-dismiss="alert">
                    <i class="fas fa-times"></i>
                </button>
            </div>';
        }
    }

    public function addError(string $error): void {
        $this->errors[] = $error;
    }

    public function getErrors(): array {
        return $this->errors;
    }



    public function handleUpdate(array $postData): void {
        $torrentIds = $postData['torrentid'] ?? [];
        $actionType = $postData['actiontype'] ?? '';
        $category = (int)($postData['category'] ?? 0);

        if (empty($actionType)) {
            $this->addError('Please select action type!');
            return;
        }

        if (!is_array($torrentIds) || count($torrentIds) < 1) {
            $this->addError('Please select at least one torrent!');
            return;
        }

        // Guard against accidental/malicious mass operations in one request.
        $maxBulkSize = 1000;
        if (count($torrentIds) > $maxBulkSize) {
            $this->addError('Too many torrents selected at once (max ' . $maxBulkSize . '). Narrow your selection and try again.');
            return;
        }

        $torrentIdsStr = implode(',', array_map('intval', $torrentIds));
        $affectedCount = count($torrentIds);

        $actions = [
            'move' => fn() => $this->moveTorrents($torrentIdsStr, $category),
            'delete' => fn() => $this->deleteTorrents($torrentIds),
            'sticky' => fn() => $this->toggleField($torrentIdsStr, 'sticky'),
            'free' => fn() => $this->toggleField($torrentIdsStr, 'free'),
            'silver' => fn() => $this->toggleField($torrentIdsStr, 'silver'),
			'thirtypercent' => fn() => $this->toggleField($torrentIdsStr, 'thirtypercent'),
            'visible' => fn() => $this->toggleField($torrentIdsStr, 'visible'),
            'anonymous' => fn() => $this->toggleField($torrentIdsStr, 'anonymous'),
            'banned' => fn() => $this->toggleField($torrentIdsStr, 'banned'),
            'nuke' => fn() => $this->toggleField($torrentIdsStr, 'isnuked'),
            'doubleupload' => fn() => $this->toggleField($torrentIdsStr, 'doubleupload'),
            'openclose' => fn() => $this->toggleField($torrentIdsStr, 'allowcomments'),
            'request' => fn() => $this->toggleField($torrentIdsStr, 'isrequest'),
            'resetrating' => fn() => $this->resetRatings($torrentIdsStr),
        ];

        // Extra privilege gate for irreversible/high-impact actions.
        // Defensive: only enforced if this codebase actually exposes an
        // is_sysop() helper - if it doesn't, we skip the extra check
        // rather than risk a fatal error on an undefined function.
        $dangerousActions = ['delete', 'banned', 'nuke', 'resetrating'];
        if (in_array($actionType, $dangerousActions, true) && function_exists('is_sysop')) {
            if (!is_sysop()) {
                $this->addError('This action ("' . $actionType . '") requires a higher staff level (sysop).');
                return;
            }
        }

        if (isset($actions[$actionType])) {
            $errorsBefore = count($this->errors);
            $detail = $actions[$actionType]();
            $hasNewError = count($this->errors) > $errorsBefore;

            if ($hasNewError) {
                // The action itself already queued a user-facing error
                // (e.g. "move" with no category picked) and did nothing -
                // don't log a bogus success or tell the mod it worked.
                return;
            }

            write_log(
                'Bulk action "' . $actionType . '" applied to torrent(s): ' . implode(', ', array_map('intval', $torrentIds))
                    . ($detail ? ' - ' . $detail : ''),
                'torrent',
                1
            );
            $_SESSION['action_success'] = 'Action completed successfully! (' . $affectedCount . ' torrent(s) affected)';
        } else {
            $this->addError('Unknown or not-yet-implemented action: ' . htmlspecialchars($actionType));
        }
    }

    private function moveTorrents(string $ids, int $category): ?string {
        global $db;
        if ($category <= 0) {
            $this->addError('Invalid category selected!');
            return null;
        }

        // Snapshot source categories and the target name purely for the
        // audit log - lets a later "who moved this and from where" question
        // be answered without cross-referencing anything else.
        $fromCategories = [];
        $catQuery = $db->sql_query_prepared(
            "SELECT c.name AS name, COUNT(*) AS cnt FROM torrents t LEFT JOIN categories c ON t.category = c.id WHERE t.id IN ($ids) GROUP BY t.category"
        );
        while ($catQuery && ($row = $db->fetch_array($catQuery))) {
            $fromCategories[] = ($row['name'] ?? 'unknown') . ' (' . $row['cnt'] . ')';
        }

        $targetName = null;
        $nameQuery = $db->sql_query_prepared("SELECT name FROM categories WHERE id = ?", [$category]);
        if ($nameQuery && $db->num_rows($nameQuery)) {
            $targetName = $db->fetch_field($nameQuery, 'name');
        }

        $db->sql_query_prepared("UPDATE torrents SET category = ? WHERE id IN ($ids)", [$category]);

        return sprintf(
            "moved from [%s] to '%s' (id %d)",
            $fromCategories ? implode(', ', $fromCategories) : 'unknown',
            $targetName ?? ('#' . $category),
            $category
        );
    }

    private function deleteTorrents(array $ids): ?string {
        global $db;
        require_once INC_PATH . '/functions_deletetorrent.php';

        // Names have to be read *before* deleting - grab a short preview
        // for the audit log, since "deleted ids 4, 91, 233" tells an admin
        // a lot less six months from now than the actual torrent names.
        $names = [];
        if (!empty($ids)) {
            $idsStr = implode(',', array_map('intval', $ids));
            $nameQuery = $db->sql_query_prepared("SELECT name FROM torrents WHERE id IN ($idsStr)");
            while ($nameQuery && ($row = $db->fetch_array($nameQuery))) {
                $names[] = $row['name'];
            }
        }

        foreach ($ids as $id) {
            deletetorrent((int)$id);
        }

        $maxNames = 10;
        $preview = implode(', ', array_slice($names, 0, $maxNames));
        $more = count($names) > $maxNames ? ' and ' . (count($names) - $maxNames) . ' more' : '';

        return 'deleted: ' . $preview . $more;
    }

    private function toggleField(string $ids, string $field): string {
        global $db;

        // Snapshot the before-state so the log can say what actually
        // changed ("2 turned on, 1 turned off") instead of just "toggled".
        $before = ['yes' => 0, 'no' => 0];
        $countQuery = $db->sql_query_prepared("SELECT $field AS val, COUNT(*) AS cnt FROM torrents WHERE id IN ($ids) GROUP BY $field");
        while ($countQuery && ($row = $db->fetch_array($countQuery))) {
            $key = ($row['val'] === 'yes') ? 'yes' : 'no';
            $before[$key] += (int)$row['cnt'];
        }

        $db->sql_query_prepared("UPDATE torrents SET $field = IF($field = 'yes', 'no', 'yes') WHERE id IN ($ids)");

        // Whatever was 'no' just flipped to 'yes', and vice versa.
        return sprintf(
            "'%s': %d turned on, %d turned off (was %d on / %d off)",
            $field,
            $before['no'],
            $before['yes'],
            $before['yes'],
            $before['no']
        );
    }

    private function resetRatings(string $ids): string {
        global $db;

        // rating_avg/rating_count не хранятся как колонки на torrents -
        // они вычисляются на лету из torrent_ratings (подтверждено
        // ошибкой "Unknown column 'rating_count'"). Значит достаточно
        // удалить строки из torrent_ratings - отображение само вернёт 0
        // при следующем подсчёте, обновлять torrents не нужно.
        $before = ['torrents' => 0, 'ratings' => 0];
        $beforeQuery = $db->sql_query_prepared(
            "SELECT torrent_id, COUNT(*) AS cnt FROM torrent_ratings WHERE torrent_id IN ($ids) GROUP BY torrent_id"
        );
        while ($beforeQuery && ($row = $db->fetch_array($beforeQuery))) {
            $before['torrents']++;
            $before['ratings'] += (int)$row['cnt'];
        }

        $db->sql_query_prepared("DELETE FROM torrent_ratings WHERE torrent_id IN ($ids)");

        return sprintf(
            "reset rating on %d torrent(s), removed %d individual rating(s)",
            $before['torrents'],
            $before['ratings']
        );
    }
}

// Initialize torrent manager
$torrentManager = new TorrentManager();

if (!defined('STAFF_PANEL')) {
    exit('
    <div class="alert-modern alert-modern-danger text-center">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

define('MT_VERSION', 'v1.0 by xam');

// Process form data
$do = $_POST['do'] ?? $_GET['do'] ?? '';
$browsecategory = (int)($_GET['browsecategory'] ?? $_POST['browsecategory'] ?? 0);
// Сырая строка — для SQL, экранированная — только для вывода.
// Раньше htmlspecialchars() применялся ДО поиска: «A&B» искалось как «A&amp;B»
$searchword_raw = trim((string)($_GET['searchword'] ?? $_POST['searchword'] ?? ''));
$searchword     = htmlspecialchars($searchword_raw);
$searchtype = $_GET['searchtype'] ?? $_POST['searchtype'] ?? '';

// Build query conditions
$queryBuilder = new class {
    public array $conditions = [];
    public array $params = [];
    public string $extralink = '';

    public function addCategoryCondition(int $category): void {
        global $db;
        
        if ($category > 0) {
            $query = $db->sql_query_prepared("SELECT type FROM categories WHERE id = ?", [$category]);
            if ($query && $db->num_rows($query)) {
                $result = $db->fetch_array($query);
                
                if ($result['type'] === 's') {
                    $this->conditions[] = "t.category = ?";
                    $this->params[] = $category;
                } else {
                    $subCats = [$category];
                    $subQuery = $db->sql_query_prepared("SELECT id FROM categories WHERE pid = ?", [$category]);
                    while ($subQuery && ($subCat = $db->fetch_array($subQuery))) {
                        $subCats[] = (int)$subCat['id'];
                    }
                    $ph = implode(',', array_fill(0, count($subCats), '?'));
                    $this->conditions[] = "t.category IN ({$ph})";
                    array_push($this->params, ...$subCats);
                }
                $this->extralink .= "browsecategory=$category&amp;";
            }
        }
    }

    public function addSearchCondition(string $searchword): void {
        if (!empty($searchword)) {
            $this->conditions[] = "t.name LIKE ?";
            // % и _ в запросе ищутся буквально
            $this->params[] = '%' . addcslashes($searchword, '%_\\') . '%';
            $this->extralink .= "searchword=" . urlencode($searchword) . "&amp;";
        }
    }

    public function addSearchTypeCondition(string $searchtype): void {
        $conditions = [
            'deadonly' => "(t.visible = 'no' OR (t.seeders=0 AND t.leechers=0))",
            'silver' => "t.silver = 'yes'",
			'thirtypercent' => "t.thirtypercent = 'yes'",
            'free' => "t.free = 'yes'",
            'recommend' => "t.sticky = 'yes'",
            'doubleuploads' => "t.doubleupload = 'yes'"
        ];

        if (isset($conditions[$searchtype])) {
            $this->conditions[] = $conditions[$searchtype];
            $this->extralink .= "searchtype=$searchtype&amp;";
        }
    }

    public function getWhereClause(): string {
        return $this->conditions ? 'WHERE ' . implode(' AND ', $this->conditions) : '';
    }

    public function getParams(): array {
        return $this->params;
    }

    public function getCountQuery(): string {
        return "SELECT COUNT(*) as total FROM torrents t " . $this->getWhereClause();
    }

    public function getMainQuery(string $orderBy, int $start, int $perPage): string {
        return "
            SELECT t.*, u.username, u.usergroup, u.avatar, c.name as category_name 
            FROM torrents t 
            LEFT JOIN users u ON t.owner = u.id 
            LEFT JOIN categories c ON t.category = c.id 
            " . $this->getWhereClause() . "
            ORDER BY $orderBy 
            LIMIT ?, ?
        ";
    }

    public function getMainQueryParams(int $start, int $perPage): array {
        return [...$this->params, $start, $perPage];
    }
};

$queryBuilder->addCategoryCondition($browsecategory);
$queryBuilder->addSearchCondition($searchword_raw);
$queryBuilder->addSearchTypeCondition($searchtype);

// Безопасен ли return_address для редиректа. Раньше принимались ТОЛЬКО
// относительные пути ("/browse.php?..."), а browse.php шлёт абсолютный URL
// ($BASEURL . '/browse.php?...'), поэтому редирект никогда не срабатывал и
// показывалась страница админки. Теперь разрешены относительные пути и
// абсолютные URL ТОЛЬКО на этот же хост (защита от open redirect сохранена).
if (!function_exists('mt_is_safe_return_address')) {
    function mt_is_safe_return_address(string $addr): bool
    {
        if ($addr === '' || preg_match('/[\x00-\x1F\x7F]/', $addr)) {
            return false;
        }

        // Относительный путь: ровно один "/" и не "//" и не "/\"
        if ($addr[0] === '/') {
            return !preg_match('#^/[/\\\\]#', $addr);
        }

        // Абсолютный URL: только http(s) и только наш хост
        $parts = parse_url($addr);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $allowedHosts = [];
        if (!empty($GLOBALS['BASEURL'])) {
            $h = parse_url((string)$GLOBALS['BASEURL'], PHP_URL_HOST);
            if ($h) {
                $allowedHosts[] = strtolower($h);
            }
        }
        if (!empty($_SERVER['HTTP_HOST'])) {
            $allowedHosts[] = strtolower(preg_replace('/:\d+$/', '', (string)$_SERVER['HTTP_HOST']));
        }

        return in_array(strtolower($parts['host']), $allowedHosts, true);
    }
}

// Handle form submission
if ($do === 'update') {
    $wantsReturn = ($_POST['return'] ?? '') === 'yes' && !empty($_POST['return_address']);
    $isAjax      = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        $torrentManager->addError('Security check failed. Please refresh the page and try again.');
    } else {
        $torrentManager->handleUpdate($_POST);
    }

    $errors = $torrentManager->getErrors();
    // Не полагаемся на $_SESSION между admin/index.php и browse.php (могут
    // быть разные cookie-scope) - результат отдаём в ответе / в query.
    $successMsg = $_SESSION['action_success'] ?? null;
    unset($_SESSION['action_success']);

    // AJAX (browse-moderation.js): отдаём JSON, страница не покидается.
    if ($isAjax) {
        // Буферы не снимаем (gzip() из global.php может держать ob_gzhandler),
        // только выбрасываем уже накопленный вывод, если он есть.
        if (ob_get_level() > 0 && ob_get_length()) {
            ob_clean();
        }
        http_response_code(empty($errors) ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => empty($errors),
            'message' => empty($errors)
                ? ($successMsg ?: 'Action completed successfully!')
                : strip_tags(implode('; ', $errors)),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Обычная отправка формы: редирект обратно на return_address.
    if ($wantsReturn) {
        $returnAddress = (string)$_POST['return_address'];

        if (mt_is_safe_return_address($returnAddress)) {
            // Раньше здесь было strpos($returnAddress, '') - пустая иголка
            // всегда "найдена", разделитель получался пустым, и параметр
            // приклеивался к URL ("...=nomod_success=..."). Исправлено.
            $lastChar  = substr($returnAddress, -1);
            $separator = ($lastChar === '?' || $lastChar === '&')
                ? ''
                : ((strpos($returnAddress, '?') !== false) ? '&' : '?');

            if (!empty($errors)) {
                $returnAddress .= $separator . 'mod_error=' . rawurlencode(strip_tags(implode('; ', $errors)));
            } elseif ($successMsg) {
                $returnAddress .= $separator . 'mod_success=' . rawurlencode($successMsg);
            }

            header('Location: ' . $returnAddress);
            exit;
        }
    }
}



// Handle quick_edit из модалки
if ($do === 'quick_edit') {
    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        $_SESSION['action_success'] = null;
        header('Location: ' . $_this_script_ . '');
        exit;
    }

    $tid  = (int)($_POST['torrent_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $cat  = (int)($_POST['category'] ?? 0);

    if ($tid > 0 && $name !== '' && $cat > 0) {
        $db->sql_query_prepared("UPDATE torrents SET name = ?, category = ? WHERE id = ?", [$name, $cat, $tid]);
        $_SESSION['action_success'] = 'Torrent #' . $tid . ' updated successfully!';
    }

    header('Location: ' . $_this_script_ . '');
    exit;
}






stdhead('Manage Torrents', true, 'supernote');

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/manage_torrents.css?ver=2">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/manage_torrents_ui.css?ver=1">';

$torrentManager->showErrors();

// Build ordering
$orderBy = 't.added DESC';
$allowedOrders = ['name', 'owner', 'category', 'added', 'size', 'seeders'];
$currentOrder = $_GET['orderby'] ?? '';
$currentWhat  = ($_GET['what'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$nextWhat     = $currentWhat === 'desc' ? 'asc' : 'desc';

if (in_array($currentOrder, $allowedOrders, true)) {
    $orderBy   = "t.{$currentOrder} " . strtoupper($currentWhat);
    $orderLink = "orderby={$currentOrder}&amp;what={$currentWhat}&amp;";
} else {
    $currentOrder = '';
}

// Count + pagination
$startTime     = microtime(true);
$countQuery    = $db->sql_query_prepared($queryBuilder->getCountQuery(), $queryBuilder->getParams());
$totalTorrents = $countQuery ? (int)$db->fetch_field($countQuery, 'total') : 0;
$queryTime     = round((microtime(true) - $startTime) * 1000, 2);

$torrentsPerPage = max(20, (int)($CURUSER['torrentsperpage'] ?? $ts_perpage ?? 50));
if ($queryTime > 1000 && $totalTorrents > 1000) {
    $torrentsPerPage = min($torrentsPerPage, 25);
}
$page  = max(1, (int)($_GET['page'] ?? 1));
$start = ($page - 1) * $torrentsPerPage;
$multipage = multipage($totalTorrents, $torrentsPerPage, $page, $_this_script_ . '' . ($orderLink ?? '') . $queryBuilder->extralink);

// Сводка по всему трекеру (один проход по таблице) — для плиток и счётчиков фильтров
$sq = $db->sql_query_prepared("
    SELECT COUNT(*) AS total, COALESCE(SUM(size),0) AS bytes,
           COALESCE(SUM(free = 'yes'),0) AS free, COALESCE(SUM(silver = 'yes'),0) AS silver,
           COALESCE(SUM(thirtypercent = 'yes'),0) AS thirtypercent, COALESCE(SUM(sticky = 'yes'),0) AS recommend,
           COALESCE(SUM(doubleupload = 'yes'),0) AS doubleuploads,
           COALESCE(SUM(visible = 'no' OR (seeders = 0 AND leechers = 0)),0) AS deadonly
    FROM torrents
");
$sum = $sq ? $db->fetch_array($sq) : [];

require_once INC_PATH . '/functions_category.php';
$categoryDropdown = ts_category_list('browsecategory', $browsecategory, '<option value="0">All categories</option>');

// База для ссылок сортировки. Раньше к $_this_script_ (уже с «?act=…»)
// дописывалось ещё одно «?act=manage_torrents» — получался «…??act=…».
$base = htmlspecialchars((string)$_SERVER['SCRIPT_NAME']) . '?act=manage_torrents&amp;';
$sortTh = static function (string $field, string $icon, string $label, string $cls = '') use ($base, $currentOrder, $currentWhat, $queryBuilder): string {
    $active = $currentOrder === $field;
    $next   = ($active && $currentWhat === 'desc') ? 'asc' : 'desc';
    $arrow  = $active ? ' <i class="fa-solid fa-arrow-' . ($currentWhat === 'desc' ? 'down-wide-short' : 'up-short-wide') . ' mt-sort-on"></i>' : '';
    return '<th class="' . $cls . '"><a class="mt-sort' . ($active ? ' is-active' : '') . '" href="' . $base . 'orderby=' . $field . '&amp;what=' . $next . '&amp;' . $queryBuilder->extralink . '"><i class="fa-solid ' . $icon . '"></i>' . $label . $arrow . '</a></th>';
};

$filters = [
    ''              => ['fa-layer-group',  'All',      (int)($sum['total'] ?? 0)],
    'free'          => ['fa-gift',         'Free',     (int)($sum['free'] ?? 0)],
    'silver'        => ['fa-star-half-stroke', 'Silver', (int)($sum['silver'] ?? 0)],
    'thirtypercent' => ['fa-percent',      '30%',      (int)($sum['thirtypercent'] ?? 0)],
    'doubleuploads' => ['fa-angles-up',    '2× Up',    (int)($sum['doubleuploads'] ?? 0)],
    'recommend'     => ['fa-thumbtack',    'Sticky',   (int)($sum['recommend'] ?? 0)],
    'deadonly'      => ['fa-skull',        'Dead',     (int)($sum['deadonly'] ?? 0)],
];
$filterLinkBase = $base . ($browsecategory ? 'browsecategory=' . $browsecategory . '&amp;' : '') . ($searchword !== '' ? 'searchword=' . urlencode($searchword_raw) . '&amp;' : '');
?>

<div class="container mt-3 mb-4 mt">

    <div class="mt-card mb-3"><div class="mt-head">
        <span class="mt-head-icon"><i class="fa-solid fa-magnet"></i></span>
        <div class="mt-head-text">
            <h1 class="mt-title">Manage Torrents</h1>
            <div class="mt-sub">Search, filter and apply bulk actions to torrents</div>
        </div>
        <span class="ms-auto mt-muted"><i class="fa-solid fa-gauge-high me-1"></i><?= $queryTime ?> ms</span>
    </div></div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-magnet',     'ic-blue',   'Torrents',    number_format((int)($sum['total'] ?? 0))],
            ['fa-hard-drive', 'ic-purple', 'Total size',  mksize((float)($sum['bytes'] ?? 0))],
            ['fa-gift',       'ic-green',  'Freeleech',   number_format((int)($sum['free'] ?? 0))],
            ['fa-skull',      'ic-red',    'Dead / hidden', number_format((int)($sum['deadonly'] ?? 0))],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="mt-card mt-kpi"><span class="mt-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="mt-kpi-label"><?= $label ?></div><div class="mt-kpi-value"><?= $val ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <!-- Поиск -->
    <div class="mt-card p-3 mb-3">
        <form method="get" action="<?= htmlspecialchars((string)$_SERVER['SCRIPT_NAME']) ?>" id="searchForm" class="row g-3 align-items-end">
            <input type="hidden" name="act" value="manage_torrents">
            <?php if ($searchtype !== ''): ?><input type="hidden" name="searchtype" value="<?= htmlspecialchars($searchtype) ?>"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label" for="torrent-search"><i class="fa-solid fa-magnifying-glass"></i>Name</label>
                <div class="input-group">
                    <input type="text" class="form-control mt-search-input" name="searchword" value="<?= $searchword ?>" placeholder="Part of the torrent name…" id="torrent-search">
                    <button type="button" class="btn btn-outline-secondary mt-search-clear" data-mt-call="clearSearch" data-mt-args="[]" title="Clear"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><i class="fa-solid fa-folder-tree"></i>Category</label>
                <?= $categoryDropdown ?>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" id="searchBtn"><i class="fa-solid fa-filter me-1"></i>Apply</button>
                <button type="button" class="btn btn-outline-secondary" data-mt-call="resetFilters" data-mt-args="[]" title="Reset"><i class="fa-solid fa-rotate-left"></i></button>
            </div>
            <div class="col-12">
                <!-- Раньше фильтр был выпадающим списком; теперь кнопки со счётчиками -->
                <div class="mt-filters">
                    <?php foreach ($filters as $k => [$ic, $lbl, $n]): ?>
                    <a href="<?= $filterLinkBase . ($k !== '' ? 'searchtype=' . $k : '') ?>" class="mt-filter<?= $searchtype === $k ? ' active' : '' ?>"><i class="fa-solid <?= $ic ?>"></i><?= $lbl ?><span class="n"><?= number_format($n) ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>

    <form method="post" action="<?= $_this_script_ ?>" name="update" id="torrentForm">
        <input type="hidden" name="do" value="update">
        <input type="hidden" name="page" value="<?= $page ?>">
        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">

        <div class="mt-card overflow-hidden">
            <div class="mt-toolbar">
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="selectAll" data-mt-call="toggleAllSelection">
                        <label class="form-check-label fw-semibold" for="selectAll">Select page</label>
                    </div>
                    <span class="mt-muted"><?= number_format($totalTorrents) ?> found · page <?= $page ?></span>
                </div>
                <div id="paginationTop"><?= $multipage ?></div>
            </div>

            <div class="table-responsive">
                <table class="mt-table">
                    <thead><tr>
                        <th class="mt-col-toggle"></th>
                        <?= $sortTh('name', 'fa-file-lines', 'Torrent') ?>
                        <th class="mobile-hidden"><i class="fa-solid fa-flag"></i>Status</th>
                        <?= $sortTh('owner', 'fa-user', 'Uploader') ?>
                        <?= $sortTh('category', 'fa-folder', 'Category', 'mobile-hidden') ?>
                        <?= $sortTh('added', 'fa-calendar', 'Added') ?>
                        <th class="text-center mt-col-check"><i class="fa-solid fa-square-check"></i></th>
                    </tr></thead>
                    <tbody id="torrentTableBody">
<?php
$queryStart = microtime(true);
$query = $db->sql_query_prepared(
    $queryBuilder->getMainQuery($orderBy, $start, $torrentsPerPage),
    $queryBuilder->getMainQueryParams($start, $torrentsPerPage)
);
$queryTime = round((microtime(true) - $queryStart) * 1000, 2);

$torrentCount = 0;
$totalSize = 0;
while ($query && ($torrent = $db->fetch_array($query))) {
    $torrentCount++;
    $totalSize += (float)$torrent['size'];
    $tid   = (int)$torrent['id'];
    $flags = GetTorrentTags($torrent);
    $av    = format_avatar($torrent['avatar'] ?? '', $torrent['avatardimensions'] ?? '');
    $avatar = (!empty($av['image']) && empty($av['is_placeholder']))
        ? '<img src="' . $av['image'] . '" class="mt-av" alt="" loading="lazy">'
        : '<span class="mt-av-ph"><i class="fa-solid fa-user"></i></span>';
    // Ник раньше передавался в format_name() без экранирования
    $owner = $torrent['username'] !== null
        ? '<a href="' . $BASEURL . '/' . get_profile_link((int)$torrent['owner']) . '" class="text-decoration-none fw-semibold">' . format_name(htmlspecialchars((string)$torrent['username']), (int)$torrent['usergroup']) . '</a>'
        : '<em class="text-body-secondary">deleted user</em>';
    ?>
                        <tr class="torrent-row" data-id="<?= $tid ?>" data-size="<?= (float)$torrent['size'] ?>">
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary torrent-btn" title="Manage"
                                        data-bs-toggle="modal" data-bs-target="#manageTorrentModal"
                                        data-id="<?= $tid ?>"
                                        data-name="<?= htmlspecialchars((string)$torrent['name']) ?>"
                                        data-image="<?= htmlspecialchars((string)($torrent['t_image'] ?? '')) ?>"
                                        data-infohash="<?= htmlspecialchars((string)$torrent['info_hash']) ?>"
                                        data-seeders="<?= (int)$torrent['seeders'] ?>"
                                        data-leechers="<?= (int)$torrent['leechers'] ?>"
                                        data-completed="<?= (int)$torrent['times_completed'] ?>">
                                    <i class="fa-solid fa-gear"></i>
                                </button>
                            </td>
                            <td data-label="Torrent">
                                <a href="<?= $BASEURL . '/' . get_torrent_link($tid) ?>" class="mt-tname"><?= htmlspecialchars((string)$torrent['name']) ?></a>
                                <div class="mt-meta">
                                    <span><i class="fa-solid fa-hard-drive me-1"></i><?= mksize((float)$torrent['size']) ?></span>
                                    <span class="s"><i class="fa-solid fa-arrow-up me-1"></i><?= (int)$torrent['seeders'] ?></span>
                                    <span class="l"><i class="fa-solid fa-arrow-down me-1"></i><?= (int)$torrent['leechers'] ?></span>
                                    <span><i class="fa-solid fa-circle-check me-1"></i><?= ts_nf($torrent['times_completed']) ?></span>
                                </div>
                                <div class="mt-flags d-md-none"><?= $flags ?></div>
                            </td>
                            <td class="mobile-hidden" data-label="Status"><div class="mt-flags mt-0"><?= $flags ?></div></td>
                            <td data-label="Uploader"><div class="d-flex align-items-center gap-2"><?= $avatar ?><?= $owner ?></div></td>
                            <td class="mobile-hidden" data-label="Category"><span class="mt-cat"><i class="fa-solid fa-tag"></i><?= htmlspecialchars((string)($torrent['category_name'] ?? '—')) ?></span></td>
                            <td data-label="Added" class="text-nowrap">
                                <div><?= my_datee('relative', (int)$torrent['added']) ?></div>
                                <div class="mt-muted mobile-hidden"><?= my_datee($dateformat, (int)$torrent['added']) ?></div>
                            </td>
                            <td class="text-center" data-label="Select">
                                <input type="checkbox" class="form-check-input torrent-checkbox" name="torrentid[]" value="<?= $tid ?>" data-mt-call="updateSelectionCounter" data-mt-args="[]" aria-label="Select">
                            </td>
                        </tr>
<?php
}

if ($torrentCount === 0) {
    // Раньше здесь печатался лишний </table> — таблица закрывалась дважды
    echo '<tr><td colspan="7"><div class="mt-empty"><i class="fa-solid fa-inbox"></i><div class="fw-semibold">No torrents found</div>'
       . '<div class="small mb-3">Try other filters</div>'
       . '<button type="button" class="btn btn-sm btn-outline-primary px-3" data-mt-call="resetFilters" data-mt-args="[]"><i class="fa-solid fa-rotate-left me-1"></i>Reset filters</button></div></td></tr>';
}
?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Массовые действия (id/имена — для manage_torrents.js) -->
        <div id="bulkActionsBar">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge rounded-pill text-bg-primary px-3 py-2" id="selectedCounter">0 selected</span>
                <select class="form-select form-select-sm w-auto" name="actiontype" id="actionType" data-mt-call="toggleMoveCategory">
                    <option value="">— Choose action —</option>
                    <optgroup label="Organise">
                        <option value="move">Move to category</option>
                        <option value="sticky">Toggle sticky</option>
                        <option value="visible">Toggle visible</option>
                        <option value="anonymous">Toggle anonymous</option>
                    </optgroup>
                    <optgroup label="Promotions">
                        <option value="free">Toggle freeleech</option>
                        <option value="silver">Toggle silver (50%)</option>
                        <option value="thirtypercent">Toggle 30% leech</option>
                        <option value="doubleupload">Toggle 2× upload</option>
                    </optgroup>
                    <optgroup label="Danger">
                        <option value="banned">Toggle banned</option>
                        <option value="delete">Delete torrents</option>
                    </optgroup>
                </select>
                <div id="moveCategory" style="display:none;"><?= ts_category_list('category', 0, '<option value="0">Choose category…</option>') ?></div>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="executeBtn" disabled><i class="fa-solid fa-play me-1"></i>Apply</button>
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-mt-call="clearSelection" data-mt-args="[]"><i class="fa-solid fa-xmark me-1"></i>Clear</button>
            </div>
            <div class="mt-muted text-end">
                <i class="fa-solid fa-list me-1"></i><?= number_format($torrentCount) ?> on page · <?= mksize($totalSize) ?>
                <span class="ms-2"><i class="fa-solid fa-gauge-high me-1"></i><?= $queryTime ?> ms</span>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-center mt-3"><?= $multipage ?></div>
</div>

<!-- Модалка управления торрентом (содержимое грузит manage_torrents.js) -->
<div class="modal fade mt-modal" id="manageTorrentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <span class="mt-mh-icon ic-blue"><i class="fa-solid fa-gear"></i></span>
                <h5 class="modal-title fw-bold">Manage torrent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="manageTorrentContent">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="mt-3 text-body-secondary">Loading torrent information…</p></div>
            </div>
        </div>
    </div>
</div>

<!-- Подтверждение опасных действий -->
<div class="modal fade mt-modal" id="bulkConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="mt-mh-icon ic-red"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <h5 class="modal-title fw-bold" id="bulkConfirmTitle">Are you sure?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><p id="bulkConfirmMessage" class="text-body-secondary mb-0">This action cannot be undone.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancel</button>
                <button type="button" class="btn btn-danger px-3" id="bulkConfirmBtn"><i class="fa-solid fa-check me-1"></i>Confirm</button>
            </div>
        </div>
    </div>
</div>

<?php
// Данные для JS — JSON, не код; флаги JSON_HEX_* не дают закрыть </script>
$mtConfig = [
    'baseUrl'       => (string)$BASEURL,
    'script'        => (string)$_this_script_,
    'torrentCount'  => $torrentCount,
    'totalTorrents' => $totalTorrents,
    'successMsg'    => !empty($_SESSION['action_success']) ? (string)$_SESSION['action_success'] : null,
];
unset($_SESSION['action_success']);
?>
<script type="application/json" id="mtConfig"><?= json_encode($mtConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= $BASEURL ?>/admin/scripts/manage_torrents_ui.js?ver=1"></script>
<script src="<?= $BASEURL ?>/admin/scripts/manage_torrents.js?ver=2"></script>

<?php
stdfoot();