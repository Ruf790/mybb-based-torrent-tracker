<?php

declare(strict_types=1);

/*******************************************************************************
 * Login Security Manager v3.2
 * Failed login attempts + login history (login_log)
 * PHP 8.5+ · AJAX · live search · filters · SweetAlert2
 *
 * Assets (no inline CSS/JS in this file):
 *   /admin/templates/maxlogin.css   — all page styles
 *   /admin/scripts/maxlogin-ui.js   — config bootstrap, copy IP, empty-state buttons
 *   /admin/scripts/maxlogin.js      — AJAX table logic (both tabs)
 ******************************************************************************/

// Security check
if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert"><b>Access Denied:</b> Direct access not permitted.</div>');
}

/**
 * One maxlogin.js serves both tabs (attempts + log). Previously the attempts
 * tab loaded /admin/scripts/maxlogin.js and the log tab /scripts/maxlogin.js,
 * so one of them always returned 404.
 */
const MAXLOGIN_JS = '/admin/scripts/maxlogin.js';
const MAXLOGIN_UI_JS = '/admin/scripts/maxlogin-ui.js';
const MAXLOGIN_CSS = '/admin/templates/maxlogin.css';
const MAXLOGIN_ASSET_VER = '3.2';

/**
 * Public URL of a local asset with a cache-busting ?v= (file mtime,
 * falls back to MAXLOGIN_ASSET_VER if the file can't be found on disk).
 */
function maxlogin_asset_url(string $path): string
{
    global $BASEURL;

    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $file = $root . $path;
    $ver = ($root !== '' && is_file($file)) ? (string) filemtime($file) : MAXLOGIN_ASSET_VER;

    return htmlspecialchars($BASEURL . $path . '?v=' . $ver, ENT_QUOTES);
}

/**
 * Page config for maxlogin.js as a non-executable JSON block
 * (read by maxlogin-ui.js into window[...]). JSON_HEX_* keep "</script>"
 * and quotes from breaking out of the block.
 */
function maxlogin_config_tag(string $elementId, array $config): string
{
    $json = json_encode(
        $config,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    return "<script type=\"application/json\" id=\"{$elementId}\">{$json}</script>";
}

// ══════════════════════════════════════════════════════════════════════════════
// SHARED UI HELPERS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Page stylesheet (scoped under .ml-wrap), printed once per page.
 * The CSS itself lives in /admin/templates/maxlogin.css.
 */
function maxlogin_styles(): string
{
    static $printed = false;
    if ($printed) {
        return '';
    }
    $printed = true;

    $href = maxlogin_asset_url(MAXLOGIN_CSS);

    return "<link rel=\"stylesheet\" href=\"{$href}\">\n";
}

/**
 * Loads SweetAlert2 + the post key once per page (previously it was loaded
 * twice: sweetalert2.all.min.js in the router and sweetalert2.min.js here).
 */
function maxlogin_assets(): string
{
    static $printed = false;
    if ($printed) {
        return '';
    }
    $printed = true;

    global $mybb, $BASEURL;
    $postKey = htmlspecialchars((string) $mybb->post_code, ENT_QUOTES);

    return <<<HTML
    <link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/sweetalert2.min.css">
    <script src="{$BASEURL}/scripts/sweetalert2.min.js"></script>
    <input type="hidden" id="maxloginPostKey" value="{$postKey}">
    HTML;
}

/** Sort header link with an active-state arrow. */
function maxlogin_sort_link(string $class, string $key, string $label, string $current, string $dir): string
{
    $isActive = $key === $current;
    $icon = $isActive ? ($dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
    $active = $isActive ? ' is-active' : '';

    // maxlogin.js rewrites every <i> inside a sort link (and takes the first one
    // as the arrow), so decorative label icons are rendered as <span>.
    $label = str_replace(['<i ', '</i>'], ['<span ', '</span>'], $label);

    return "<a href=\"#\" class=\"ml-sort {$class}{$active}\" data-order=\"{$key}\">{$label} <i class=\"fa-solid {$icon}\"></i></a>";
}

/** Pill pagination; $linkClass keeps the class the external JS listens to. */
function maxlogin_pager(int $current, int $total, int $totalRows, string $linkClass): string
{
    $fmt = number_format($totalRows);

    if ($total <= 1) {
        return "<div class=\"ml-pager\"><span class=\"ml-muted\"><i class=\"fa-solid fa-list-ol me-2\"></i>{$fmt} records</span></div>";
    }

    $pages = '';
    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current ? ' active' : '';
        $aria   = $i === $current ? ' aria-current="page"' : '';
        $pages .= "<li class=\"page-item{$active}\"><a class=\"page-link {$linkClass}\" href=\"#\" data-page=\"{$i}\"{$aria}>{$i}</a></li>";
    }

    $prevDis = $current === 1 ? ' disabled' : '';
    $nextDis = $current === $total ? ' disabled' : '';
    $prev    = max(1, $current - 1);
    $next    = min($total, $current + 1);

    return <<<HTML
    <div class="ml-pager">
        <span class="ml-muted"><i class="fa-solid fa-book-open me-2"></i>Page {$current} of {$total}, {$fmt} records</span>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item{$prevDis}">
                    <a class="page-link {$linkClass}" href="#" data-page="{$prev}" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></a>
                </li>
                {$pages}
                <li class="page-item{$nextDis}">
                    <a class="page-link {$linkClass}" href="#" data-page="{$next}" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    HTML;
}

/** Four KPI tiles: [ [icon, tone, value, label], ... ] */
function maxlogin_kpis(array $tiles): string
{
    $html = '<div class="ml-kpis">';
    foreach ($tiles as [$icon, $tone, $value, $label]) {
        $val = number_format((int) $value);
        $html .= <<<HTML
        <div class="ml-kpi">
            <span class="ml-ico ml-soft-{$tone}"><i class="fa-solid {$icon}"></i></span>
            <div>
                <div class="ml-kpi-val">{$val}</div>
                <div class="ml-kpi-lbl">{$label}</div>
            </div>
        </div>
        HTML;
    }
    return $html . '</div>';
}

/** Page header card: icon in a soft square + title + subtitle. */
function maxlogin_page_header(string $icon, string $tone, string $title, string $subtitle, string $side = ''): string
{
    return <<<HTML
    <div class="ml-card">
        <div class="ml-head">
            <span class="ml-ico ml-soft-{$tone}"><i class="fa-solid {$icon}"></i></span>
            <div class="ml-head-main">
                <h4 class="ml-title">{$title}</h4>
                <p class="ml-sub">{$subtitle}</p>
            </div>
            <div class="ml-head-side">{$side}</div>
        </div>
    </div>
    HTML;
}

// ══════════════════════════════════════════════════════════════════════════════
// FAILED LOGIN ATTEMPTS — loginattempts table
// ══════════════════════════════════════════════════════════════════════════════

class LoginAttemptsManager
{
    private const VERSION = '3.1';
    private const PER_PAGE = 20;

    private string $action;
    private ?int $id;
    private ?string $update;
    private int $page;
    private string $orderBy;
    private string $orderType;
    private ?string $filterBanned;
    private ?string $filterType;
    private ?string $searchIp;

    public function __construct()
    {
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->action = $this->getRequest('action', 'showlist');
        $this->id = $this->getRequestInt('id');
        $this->update = $this->getRequest('update');
        $this->page = $this->getRequestInt('page', 1);
        $this->filterBanned = $this->getRequest('filter_banned');
        $this->filterType = $this->getRequest('filter_type');
        $this->searchIp = $this->getRequest('search_ip');

        $this->orderBy = $this->normalizeOrder($this->getRequest('order', 'added'));
        $this->orderType = $this->getRequest('otype') === 'DESC' ? 'ASC' : 'DESC';
    }

    /** ORDER BY whitelist — used for every source of the sort column. */
    private function normalizeOrder(string $order): string
    {
        return match ($order) {
            'id', 'ip', 'added', 'attempts', 'type' => $order,
            'status', 'banned' => 'banned',
            default => 'added'
        };
    }

    /** Totals for KPI tiles and the tab counter. */
    public function getStats(): array
    {
        global $db;

        $result = $db->sql_query_prepared(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(banned = 'yes'), 0) AS banned,
                    COALESCE(SUM(type = 'recover'), 0) AS recover,
                    COALESCE(SUM(attempts), 0) AS attempts
             FROM loginattempts"
        );
        $row = $result ? $db->fetch_array($result) : null;

        return [
            'total'    => (int) ($row['total'] ?? 0),
            'banned'   => (int) ($row['banned'] ?? 0),
            'recover'  => (int) ($row['recover'] ?? 0),
            'attempts' => (int) ($row['attempts'] ?? 0),
        ];
    }

    public function renderKpis(array $s): string
    {
        return maxlogin_kpis([
            ['fa-network-wired', 'primary', $s['total'],    'Tracked IPs'],
            ['fa-ban',           'danger',  $s['banned'],   'Banned IPs'],
            ['fa-key',           'warning', $s['recover'],  'Recovery attempts'],
            ['fa-repeat',        'info',    $s['attempts'], 'Failed attempts total'],
        ]);
    }

    /**
     * Render only the inner content (card with filters + table) without stdhead/stdfoot.
     * Used by the tabbed layout.
     */
    public function executeInner(): void
    {
        echo $this->includeJavaScriptLibraries();
        echo '<div class="ml-card">';
        echo $this->renderHeader();
        echo $this->renderFiltersAndSearch();
        echo '<div id="attempts-table-container">';
        echo $this->renderTableContent();
        echo '</div>';
        echo '</div>';
    }

    public function execute(): void
    {
        // AJAX handlers
        if ($this->action === 'ajax_ban' || $this->action === 'ajax_unban') {
            $this->handleAjaxToggleBan();
            return;
        }

        if ($this->action === 'ajax_delete') {
            $this->handleAjaxDelete();
            return;
        }

        if ($this->action === 'ajax_search') {
            $this->handleAjaxSearch();
            return;
        }

        if ($this->action === 'ajax_get_page') {
            $this->handleAjaxGetPage();
            return;
        }

        if ($this->action === 'ajax_get_count') {
            $this->handleAjaxGetCount();
            return;
        }

        // Regular handlers
        match ($this->action) {
            'showlist' => $this->showList(),
            'ban' => $this->ban(),
            'unban' => $this->unban(),
            'delete' => $this->delete(),
            'edit' => $this->edit(),
            'save' => $this->save(),
            'searchip' => $this->searchIp(),
            default => $this->showError('Invalid Action')
        };
    }

    private function showList(): void
    {
        stdhead('Login Attempts Manager - View List');

        echo maxlogin_styles();
        echo maxlogin_assets();
        echo '<div class="ml-wrap container-xl py-3">';
        echo maxlogin_page_header('fa-shield-halved', 'primary', 'Failed login attempts', 'Track and manage suspicious login activity');

        if ($this->update) {
            echo $this->renderSuccessMessage($this->update);
        }

        echo $this->renderKpis($this->getStats());
        $this->executeInner();
        echo '</div>';

        stdfoot();
    }

    private function renderTableContent(): string
    {
        global $db, $dateformat, $timeformat, $BASEURL;

        [$whereClause, $whereParams] = $this->buildWhereClause();
        $totalRows = $this->getTotalRows($whereClause, $whereParams);

        if ($totalRows === 0) {
            return $this->renderEmptyState();
        }

        $pagination = $this->getPagination($totalRows);
        $query = $this->buildQuery($whereClause, $pagination['offset']);

        $result = $db->sql_query_prepared($query, [...$whereParams, $pagination['offset'], self::PER_PAGE]);

        if (!$result) {
            return $this->renderEmptyState();
        }

        $output = $this->renderTable($result, $dateformat, $timeformat, $BASEURL);
        $output .= $this->renderPagination($pagination, $totalRows);

        return $output;
    }

    private function statusBadge(bool $isBanned): string
    {
        return $isBanned
            ? '<span class="ml-chip ml-soft-danger"><i class="fa-solid fa-ban"></i>Banned</span>'
            : '<span class="ml-chip ml-soft-success"><i class="fa-solid fa-circle-check"></i>Active</span>';
    }

    private function handleAjaxToggleBan(): void
    {
        global $db;

        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $action = $_POST['ajax_action'] ?? '';

            if (!$id || !is_valid_id($id)) {
                throw new Exception('Invalid ID');
            }

            $newStatus = $action === 'ban' ? 'yes' : 'no';
            $message = $action === 'ban' ? 'Ban' : 'Unban';

            $db->sql_query_prepared("UPDATE loginattempts SET banned = ? WHERE id = ?", [$newStatus, $id]);

            $result = $db->sql_query_prepared("SELECT * FROM loginattempts WHERE id = ?", [$id]);
            $row = $result ? $db->fetch_array($result) : null;
            if (!$row) {
                throw new Exception('Record not found after update');
            }

            echo json_encode([
                'success' => true,
                'message' => "IP {$message}ned successfully!",
                'data' => [
                    'id' => $row['id'],
                    'ip' => $row['ip'],
                    'banned' => $row['banned'],
                    'status_badge' => $this->statusBadge($row['banned'] === 'yes'),
                    'ban_button' => $this->renderBanButton($row),
                    'is_banned' => $row['banned'] === 'yes'
                ]
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    private function handleAjaxDelete(): void
    {
        global $db;

        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);

            if (!$id || !is_valid_id($id)) {
                throw new Exception('Invalid ID');
            }

            $result = $db->sql_query_prepared("SELECT ip FROM loginattempts WHERE id = ?", [$id]);
            $row = $result ? $db->fetch_array($result) : null;
            $ip = htmlspecialchars((string) ($row['ip'] ?? ''), ENT_QUOTES);

            $db->sql_query_prepared("DELETE FROM loginattempts WHERE id = ?", [$id]);

            echo json_encode([
                'success' => true,
                'message' => "Attempt from IP {$ip} deleted successfully!",
                'id' => $id
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    private function handleAjaxSearch(): void
    {
        global $db, $dateformat, $timeformat, $BASEURL;

        header('Content-Type: application/json');

        try {
            $searchTerm = trim($_POST['search'] ?? '');
            $filterBanned = $_POST['filter_banned'] ?? '';
            $filterType = $_POST['filter_type'] ?? '';

            $whereParts = [];
            $params = [];

            if (!empty($searchTerm)) {
                $whereParts[] = "ip LIKE ?";
                $params[] = '%' . $this->likeEscape($searchTerm) . '%';
            }

            if (!empty($filterBanned) && $filterBanned !== 'all') {
                $whereParts[] = "banned = ?";
                $params[] = $filterBanned;
            }

            if (!empty($filterType) && $filterType !== 'all') {
                $whereParts[] = "type = ?";
                $params[] = $filterType;
            }

            $whereClause = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
            $query = sprintf(
                "SELECT * FROM loginattempts %s ORDER BY %s %s LIMIT 50",
                $whereClause,
                $this->orderBy,
                $this->orderType
            );

            $result = $db->sql_query_prepared($query, $params);
            $count = $result ? $db->num_rows($result) : 0;

            if ($count === 0) {
                $html = $this->renderEmptySearch($searchTerm);
            } else {
                $rows = '';
                while ($row = $db->fetch_array($result)) {
                    $rows .= $this->renderTableRow($row, $dateformat, $timeformat, $BASEURL);
                }
                $html = $this->wrapTable($rows);
            }

            echo json_encode([
                'success' => true,
                'html' => $html,
                'count' => $count
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    private function handleAjaxGetPage(): void
    {
        header('Content-Type: application/json');

        try {
            $this->page = (int) ($_POST['page'] ?? 1);
            $this->filterBanned = (string) ($_POST['filter_banned'] ?? '');
            $this->filterType = (string) ($_POST['filter_type'] ?? '');
            $this->searchIp = (string) ($_POST['search_ip'] ?? '');

            // SECURITY: these two go straight into ORDER BY — whitelist them.
            // Previously any POSTed value was concatenated into the SQL.
            $this->orderBy = $this->normalizeOrder((string) ($_POST['order'] ?? $this->orderBy));
            $this->orderType = strtoupper((string) ($_POST['otype'] ?? $this->orderType)) === 'ASC' ? 'ASC' : 'DESC';

            echo json_encode([
                'success' => true,
                'html' => $this->renderTableContent(),
                'page' => $this->page
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    private function handleAjaxGetCount(): void
    {
        global $db;

        header('Content-Type: application/json');

        try {
            $filterBanned = $this->getRequest('filter_banned', 'all');
            $filterType = $this->getRequest('filter_type', 'all');
            $searchIp = $this->getRequest('search_ip', '');

            $whereParts = [];
            $params = [];

            if (!empty($searchIp)) {
                $whereParts[] = "ip LIKE ?";
                $params[] = '%' . $this->likeEscape($searchIp) . '%';
            }

            if (!empty($filterBanned) && $filterBanned !== 'all') {
                $whereParts[] = "banned = ?";
                $params[] = $filterBanned;
            }

            if (!empty($filterType) && $filterType !== 'all') {
                $whereParts[] = "type = ?";
                $params[] = $filterType;
            }

            $whereClause = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
            $query = "SELECT COUNT(*) as count FROM loginattempts " . $whereClause;

            $result = $db->sql_query_prepared($query, $params);
            $row = $result ? $db->fetch_array($result) : null;

            echo json_encode([
                'success' => true,
                'count' => (int) ($row['count'] ?? 0)
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    private function buildWhereClause(): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($this->searchIp)) {
            $whereParts[] = "ip LIKE ?";
            $params[] = '%' . $this->likeEscape($this->searchIp) . '%';
        }

        if (!empty($this->filterBanned) && $this->filterBanned !== 'all') {
            $whereParts[] = "banned = ?";
            $params[] = $this->filterBanned;
        }

        if (!empty($this->filterType) && $this->filterType !== 'all') {
            $whereParts[] = "type = ?";
            $params[] = $this->filterType;
        }

        $sql = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
        return [$sql, $params];
    }

    private function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function buildQuery(string $whereClause, int $offset): string
    {
        return sprintf(
            "SELECT * FROM loginattempts %s ORDER BY %s %s LIMIT ?, ?",
            $whereClause,
            $this->orderBy,
            $this->orderType
        );
    }

    private function getTotalRows(string $whereClause, array $whereParams = []): int
    {
        global $db;

        $query = "SELECT COUNT(*) as count FROM loginattempts " . $whereClause;
        $result = $db->sql_query_prepared($query, $whereParams);
        $row = $result ? $db->fetch_array($result) : null;

        return (int) ($row['count'] ?? 0);
    }

    private function getPagination(int $totalRows): array
    {
        $totalPages = (int) ceil($totalRows / self::PER_PAGE);
        $currentPage = max(1, min($this->page, $totalPages));
        $offset = ($currentPage - 1) * self::PER_PAGE;

        return [
            'current' => $currentPage,
            'total' => $totalPages,
            'offset' => $offset,
            'per_page' => self::PER_PAGE,
            'total_rows' => $totalRows
        ];
    }

    /** Card head of the attempts section (keeps #loading-spinner / #total-count for JS). */
    private function renderHeader(): string
    {
        return <<<HTML
        <div class="ml-head">
            <span class="ml-ico ml-ico-sm ml-soft-danger"><i class="fa-solid fa-user-lock"></i></span>
            <div class="ml-head-main">
                <h5 class="ml-title">Failed attempts</h5>
                <p class="ml-sub">IPs that failed to log in or recover a password</p>
            </div>
            <div class="ml-head-side">
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-spinner" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ml-chip ml-soft-primary"><i class="fa-solid fa-database"></i><span id="total-count">Loading...</span></span>
            </div>
        </div>
        HTML;
    }

    private function renderFiltersAndSearch(): string
    {
        $bannedSelected = htmlspecialchars($this->filterBanned ?? 'all');
        $typeSelected = htmlspecialchars($this->filterType ?? 'all');
        $searchValue = htmlspecialchars($this->searchIp ?? '');

        return <<<HTML
        <div class="ml-toolbar">
            <div class="row g-2 align-items-start">
                <div class="col-lg-6">
                    <div class="input-group ml-pill-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text"
                               class="form-control"
                               id="live-search"
                               placeholder="Search by IP address"
                               value="{$searchValue}"
                               autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="clear-search" title="Clear search">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="ml-hint"><i class="fa-solid fa-bolt me-1"></i>Results update as you type</div>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <div class="input-group ml-pill-group">
                        <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                        <select class="form-select" id="filter-banned" aria-label="Status filter">
                            <option value="all" {$this->selected($bannedSelected === 'all')}>All status</option>
                            <option value="yes" {$this->selected($bannedSelected === 'yes')}>Banned</option>
                            <option value="no" {$this->selected($bannedSelected === 'no')}>Active</option>
                        </select>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <div class="input-group ml-pill-group">
                        <span class="input-group-text"><i class="fa-solid fa-tag"></i></span>
                        <select class="form-select" id="filter-type" aria-label="Type filter">
                            <option value="all" {$this->selected($typeSelected === 'all')}>All types</option>
                            <option value="login" {$this->selected($typeSelected === 'login')}>Login</option>
                            <option value="recover" {$this->selected($typeSelected === 'recover')}>Recovery</option>
                        </select>
                    </div>
                </div>

                <div class="col-lg-2 d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary ml-btn flex-fill" id="refresh-btn" title="Refresh">
                        <i class="fa-solid fa-rotate"></i><span class="d-lg-none d-xl-inline">Refresh</span>
                    </button>
                    <button type="button" class="btn btn-outline-danger ml-btn" id="clear-filters" title="Clear filters">
                        <i class="fa-solid fa-filter-circle-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="ml-muted small mt-2"><span id="filter-info"></span></div>
        </div>
        HTML;
    }

    private function wrapTable(string $rows): string
    {
        $o = $this->orderBy;
        $d = $this->orderType;
        $s = fn(string $k, string $l) => maxlogin_sort_link('sort-header', $k, $l, $o === 'banned' ? 'status' : $o, $d);

        return <<<HTML
        <div class="table-responsive">
            <table class="table table-hover align-middle ml-table">
                <thead>
                    <tr>
                        <th class="ml-w-6">{$s('id', 'ID')}</th>
                        <th class="ml-w-22">{$s('ip', '<i class="fa-solid fa-network-wired"></i> IP address')}</th>
                        <th class="ml-w-18">{$s('added', '<i class="fa-regular fa-clock"></i> Last attempt')}</th>
                        <th class="ml-w-10">{$s('attempts', '<i class="fa-solid fa-repeat"></i> Attempts')}</th>
                        <th class="ml-w-14">{$s('type', '<i class="fa-solid fa-tag"></i> Type')}</th>
                        <th class="ml-w-14">{$s('status', '<i class="fa-solid fa-shield-halved"></i> Status')}</th>
                        <th class="ml-w-16 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    private function renderTable(object $result, string $dateformat, string $timeformat, string $baseUrl): string
    {
        global $db;

        $rows = '';
        while ($row = $db->fetch_array($result)) {
            $rows .= $this->renderTableRow($row, $dateformat, $timeformat, $baseUrl);
        }

        return $this->wrapTable($rows);
    }

    private function renderTableRow(array $row, string $dateformat, string $timeformat, string $baseUrl): string
    {
        $id = (int) $row['id'];
        $ip = htmlspecialchars((string) $row['ip'], ENT_QUOTES);
        $ipUrl = urlencode((string) $row['ip']);
        $date = my_datee($dateformat, $row['added']);
        $time = my_datee($timeformat, $row['added']);
        $attempts = (int) $row['attempts'];

        $isRecover = $row['type'] === 'recover';
        $typeChip = $isRecover
            ? '<span class="ml-chip ml-soft-warning attempt-type"><i class="fa-solid fa-key"></i>Recover password</span>'
            : '<span class="ml-chip ml-soft-primary attempt-type"><i class="fa-solid fa-right-to-bracket"></i>Login</span>';

        // Severity color for the attempts counter
        $tone = match (true) {
            $attempts >= 5 => 'danger',
            $attempts >= 3 => 'warning',
            default => 'secondary'
        };

        $statusBadge = $this->statusBadge($row['banned'] === 'yes');

        return <<<HTML
        <tr id="row-{$id}">
            <td class="ml-id">#{$id}</td>
            <td>
                <div class="ml-ipcell">
                    <code class="ml-ip ip-address">{$ip}</code>
                    <button type="button" class="btn ml-btn-icon ml-mini ml-soft-secondary ml-copy" data-copy="{$ip}" title="Copy IP">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                    <a href="{$baseUrl}/admin/index.php?act=ipsearch&amp;do=1&amp;ip={$ipUrl}"
                       target="_blank" rel="noopener"
                       class="btn ml-btn-icon ml-mini ml-soft-info"
                       title="Search this IP in the database">
                        <i class="fa-solid fa-magnifying-glass-location"></i>
                    </a>
                </div>
            </td>
            <td>
                <div class="ml-when">
                    <i class="fa-regular fa-calendar"></i>{$date}<br>
                    <i class="fa-regular fa-clock"></i>{$time}
                </div>
            </td>
            <td>
                <span class="ml-chip ml-num ml-soft-{$tone} attempts-count">{$attempts}</span>
            </td>
            <td>{$typeChip}</td>
            <td class="status-cell">{$statusBadge}</td>
            <td class="text-end">
                <div class="ml-actions" role="group">
                    {$this->renderBanButton($row)}
                    <a href="?act=maxlogin&amp;action=edit&amp;id={$id}"
                       class="btn ml-btn-icon ml-soft-primary"
                       title="Edit">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <button type="button" class="btn ml-btn-icon ml-soft-danger delete-btn"
                            data-id="{$id}"
                            data-ip="{$ip}"
                            title="Delete">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </td>
        </tr>
        HTML;
    }

    private function renderBanButton(array $row): string
    {
        $id = (int) $row['id'];
        $ip = htmlspecialchars((string) $row['ip'], ENT_QUOTES); // was unescaped in the attribute
        $isBanned = $row['banned'] === 'yes';
        $banText = $isBanned ? 'Unban' : 'Ban';
        $banTone = $isBanned ? 'success' : 'warning';
        $banIcon = $isBanned ? 'fa-lock-open' : 'fa-lock';
        $action = $isBanned ? 'unban' : 'ban';

        return <<<HTML
        <button type="button" class="btn ml-btn-icon ml-soft-{$banTone} ban-btn"
                data-id="{$id}"
                data-action="{$action}"
                data-ip="{$ip}"
                title="{$banText} IP">
            <i class="fa-solid {$banIcon}"></i>
        </button>
        HTML;
    }

    private function renderPagination(array $pagination, int $totalRows): string
    {
        return maxlogin_pager($pagination['current'], $pagination['total'], $totalRows, 'pagination-page');
    }

    private function includeJavaScriptLibraries(): string
    {
        $config = maxlogin_config_tag('maxlogin-config', [
            'orderBy'      => $this->orderBy,
            'orderType'    => $this->orderType,
            'filterBanned' => $this->filterBanned ?? 'all',
            'filterType'   => $this->filterType ?? 'all',
            'searchTerm'   => $this->searchIp ?? '',
        ]);

        $assets = maxlogin_assets();
        $uiJs = maxlogin_ui_js();
        $js = maxlogin_asset_url(MAXLOGIN_JS);

        // Order matters: config block -> maxlogin-ui.js (reads it) -> maxlogin.js
        return <<<HTML
        {$assets}
        {$config}
        {$uiJs}
        <script src="{$js}"></script>
        HTML;
    }

    private function renderEmptyState(): string
    {
        $hasFilters = !empty($this->filterBanned) && $this->filterBanned !== 'all'
            || !empty($this->filterType) && $this->filterType !== 'all'
            || !empty($this->searchIp);

        if ($hasFilters) {
            return <<<HTML
            <div class="ml-empty">
                <span class="ml-ico ml-soft-warning"><i class="fa-solid fa-filter"></i></span>
                <h4>No matching records</h4>
                <p class="ml-muted">Nothing matches the current filters. Clear them to see every record.</p>
                <button type="button" class="btn btn-outline-primary ml-btn mt-2" id="clear-filters-btn" data-ml-trigger="#clear-filters">
                    <i class="fa-solid fa-filter-circle-xmark"></i> Clear filters
                </button>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="ml-empty">
            <span class="ml-ico ml-soft-success"><i class="fa-solid fa-shield-heart"></i></span>
            <h4>No failed attempts</h4>
            <p class="ml-muted">Nobody has failed to log in. New failures will appear here.</p>
        </div>
        HTML;
    }

    private function renderEmptySearch(string $searchTerm): string
    {
        $searchTermHtml = htmlspecialchars($searchTerm);

        return <<<HTML
        <div class="ml-empty">
            <span class="ml-ico ml-soft-warning"><i class="fa-solid fa-magnifying-glass"></i></span>
            <h4>No results</h4>
            <p class="ml-muted">No attempts found for <code class="ml-ip">{$searchTermHtml}</code></p>
            <button type="button" class="btn btn-outline-primary ml-btn mt-2" data-ml-trigger="#clear-search">
                <i class="fa-solid fa-xmark"></i> Clear search
            </button>
        </div>
        HTML;
    }

    // ── Non-AJAX actions ──────────────────────────────────────────────────────

    private function ban(): void
    {
        $this->validateId();
        $this->updateRecord('banned', 'yes', 'Ban');
    }

    private function unban(): void
    {
        $this->validateId();
        $this->updateRecord('banned', 'no', 'Unban');
    }

    private function delete(): void
    {
        $this->validateId();
        $this->deleteRecord(isset($_GET['return']));
    }

    private function edit(): void
    {
        global $db;

        $this->validateId();

        $result = $db->sql_query_prepared("SELECT * FROM loginattempts WHERE id = ?", [$this->id]);
        $attempt = $result ? $db->fetch_array($result) : null;

        if (!$attempt) {
            stderr('Error', 'Login attempt not found');
        }

        stdhead('Login Attempts - Edit');
        echo maxlogin_styles();
        echo $this->renderEditForm($attempt);
        stdfoot();
    }

    private function save(): void
    {
        global $db;

        $id = (int) $_POST['id'];
        $attempts = (int) $_POST['attempts'];
        $type = trim($_POST['type'] ?? '');
        $banned = trim($_POST['banned'] ?? '');

        $this->validateId($id);
        $this->validateAttempts($attempts);

        // Only allow the enum values the table actually uses
        $type = $type === 'recover' ? 'recover' : 'login';
        $banned = $banned === 'yes' ? 'yes' : 'no';

        $db->sql_query_prepared(
            "UPDATE loginattempts SET attempts = ?, type = ?, banned = ? WHERE id = ? LIMIT 1",
            [$attempts, $type, $banned, $id]
        );

        if (!empty($_POST['returnto'])) {
            redirect($_POST['returnto']);
        }

        redirect($_SERVER['PHP_SELF'] . '?act=maxlogin&update=Edit');
    }

    private function searchIp(): void
    {
        global $db, $dateformat, $timeformat, $BASEURL;

        $ip = trim($_POST['ip'] ?? '');
        stdhead('Login Attempts - Search Results');

        echo maxlogin_styles();
        echo maxlogin_assets();
        echo '<div class="ml-wrap container-xl py-3">';
        echo maxlogin_page_header('fa-magnifying-glass-location', 'info', 'IP search', 'Failed attempts matching <code class="ml-ip">' . htmlspecialchars($ip) . '</code>');
        echo '<div class="ml-card mt-3">';

        $result = $db->sql_query_prepared("SELECT * FROM loginattempts WHERE ip LIKE ?", ['%' . $this->likeEscape($ip) . '%']);

        if (!$result || $db->num_rows($result) === 0) {
            echo $this->renderEmptySearch($ip);
        } else {
            echo $this->renderTable($result, $dateformat, $timeformat, $BASEURL);
        }

        echo '</div>';
        echo $this->renderSearchForm();
        echo '</div>';
        echo maxlogin_ui_js();
        stdfoot();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateId(?int $id = null): void
    {
        $id = $id ?? $this->id;

        if (!$id || !is_valid_id($id)) {
            stderr('Error', 'Invalid ID');
        }
    }

    private function validateAttempts(int $attempts): void
    {
        if ($attempts < 0 || $attempts > 1000) {
            stderr('Error', 'Invalid attempts value');
        }
    }

    private function updateRecord(string $field, string $value, string $message): void
    {
        global $db;

        $db->sql_query_prepared("UPDATE loginattempts SET {$field} = ? WHERE id = ?", [$value, $this->id]);
        redirect($_SERVER['PHP_SELF'] . "?act=maxlogin&update=$message");
    }

    private function deleteRecord(bool $returnToRequests = false): void
    {
        global $db;

        $db->sql_query_prepared("DELETE FROM loginattempts WHERE id = ?", [$this->id]);

        if ($returnToRequests) {
            redirect('admin.php?act=viewunbaniprequest');
        }

        redirect($_SERVER['PHP_SELF'] . '?act=maxlogin&update=Delete');
    }

    private function renderEditForm(array $attempt): string
    {
        global $mybb;

        $id = (int) $attempt['id'];
        $ip = htmlspecialchars((string) $attempt['ip'], ENT_QUOTES); // was printed raw
        $attempts = (int) $attempt['attempts'];
        $added = my_datee('relative', $attempt['added']);
        $fromRequests = isset($_GET['return']) && $_GET['return'] === 'yes';
        $returnHidden = $fromRequests
            ? '<input type="hidden" name="returnto" value="admin.php?act=viewunbaniprequest">'
            : '';
        $cancelUrl = $fromRequests ? 'admin.php?act=viewunbaniprequest' : '?act=maxlogin';
        $postKey = htmlspecialchars((string) $mybb->post_code, ENT_QUOTES);
        $status = $this->statusBadge($attempt['banned'] === 'yes');

        $header = maxlogin_page_header(
            'fa-pen-to-square',
            'primary',
            "Edit login attempt #{$id}",
            'Change the counter, the attempt type or the ban status',
            $status
        );

        return <<<HTML
        <div class="ml-wrap container-lg py-3">
            {$header}

            <form method="post" action="?act=maxlogin&amp;action=save" class="ml-form">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="{$id}">
                <input type="hidden" name="ip" value="{$ip}">
                <input type="hidden" name="my_post_key" value="{$postKey}">
                {$returnHidden}

                <div class="ml-card mt-3">
                    <div class="p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="ml-info">
                                    <span class="ml-ico ml-ico-sm ml-soft-info"><i class="fa-solid fa-network-wired"></i></span>
                                    <div>
                                        <div class="ml-info-lbl">IP address</div>
                                        <code class="ml-ip fs-6">{$ip}</code>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ml-info">
                                    <span class="ml-ico ml-ico-sm ml-soft-secondary"><i class="fa-regular fa-clock"></i></span>
                                    <div>
                                        <div class="ml-info-lbl">Last attempt</div>
                                        <div class="fw-semibold">{$added}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="attempts" class="form-label">
                                    <i class="fa-solid fa-repeat"></i> Attempts
                                </label>
                                <input type="number" class="form-control" id="attempts" name="attempts"
                                       value="{$attempts}" min="0" max="1000" required>
                                <div class="form-text">From 0 to 1000</div>
                            </div>

                            <div class="col-md-4">
                                <label for="type" class="form-label">
                                    <i class="fa-solid fa-tag"></i> Type
                                </label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="login" {$this->selected($attempt['type'] === 'login')}>Login</option>
                                    <option value="recover" {$this->selected($attempt['type'] === 'recover')}>Password recovery</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="banned" class="form-label">
                                    <i class="fa-solid fa-shield-halved"></i> Status
                                </label>
                                <select class="form-select" id="banned" name="banned" required>
                                    <option value="yes" {$this->selected($attempt['banned'] === 'yes')}>Banned</option>
                                    <option value="no" {$this->selected($attempt['banned'] === 'no')}>Active</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ml-actionbar">
                    <span class="ml-sub"><i class="fa-solid fa-circle-info me-1"></i>Changes apply immediately after saving</span>
                    <a href="{$cancelUrl}" class="btn btn-outline-secondary ml-btn">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary ml-btn px-4">
                        <i class="fa-solid fa-floppy-disk"></i> Save changes
                    </button>
                </div>
            </form>
        </div>
        HTML;
    }

    private function renderSearchForm(): string
    {
        return <<<HTML
        <div class="ml-card mt-3">
            <div class="ml-head">
                <span class="ml-ico ml-ico-sm ml-soft-info"><i class="fa-solid fa-magnifying-glass"></i></span>
                <div class="ml-head-main">
                    <h5 class="ml-title">Search another IP</h5>
                </div>
            </div>
            <div class="px-4 pb-4">
                <form method="post" action="?act=maxlogin&amp;action=searchip" class="row g-2 align-items-center">
                    <input type="hidden" name="action" value="searchip">
                    <div class="col-md-9">
                        <label for="searchIp" class="visually-hidden">IP address</label>
                        <div class="input-group ml-pill-group">
                            <span class="input-group-text"><i class="fa-solid fa-network-wired"></i></span>
                            <input type="text" class="form-control" id="searchIp" name="ip"
                                   placeholder="For example 192.168.1.1" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-info ml-btn w-100">
                            <i class="fa-solid fa-magnifying-glass"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
        HTML;
    }

    private function renderSuccessMessage(string $action): string
    {
        return <<<HTML
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mt-3 mb-0 ml-alert" role="alert">
            <i class="fa-solid fa-circle-check"></i>
            <div><strong>Done.</strong> "{$action}" completed.</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        HTML;
    }

    private function showError(string $message): void
    {
        stderr('Error', $message);
    }

    private function getRequest(string $key, string $default = ''): string
    {
        return htmlspecialchars((string) ($_REQUEST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
    }

    private function getRequestInt(string $key, int $default = 0): int
    {
        $value = $_REQUEST[$key] ?? $default;
        return (int) filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default]
        ]);
    }

    private function selected(bool $condition): string
    {
        return $condition ? 'selected' : '';
    }
}

/**
 * maxlogin-ui.js (config bootstrap, copy IP, empty-state buttons), once per page.
 * Must come after the config block and before maxlogin.js.
 */
function maxlogin_ui_js(): string
{
    static $printed = false;
    if ($printed) {
        return '';
    }
    $printed = true;

    $src = maxlogin_asset_url(MAXLOGIN_UI_JS);

    return "<script src=\"{$src}\"></script>";
}

// ══════════════════════════════════════════════════════════════════════════════
// LOGIN LOG MANAGER — login_log table viewer
// ══════════════════════════════════════════════════════════════════════════════

class LoginLogManager
{
    private const PER_PAGE = 25;

    private int    $page;
    private string $filterStatus;
    private string $filterSuspicious;
    private string $searchIp;
    private string $orderBy;
    private string $orderType;
    private string $action;

    public function __construct()
    {
        $this->action           = $this->req('action', 'showlist');
        $this->page             = max(1, (int)($_REQUEST['lpage'] ?? 1));
        $this->filterStatus     = $this->req('filter_status', 'all');
        $this->filterSuspicious = $this->req('filter_suspicious', 'all');
        $this->searchIp         = $this->req('search_log_ip', '');
        $this->orderBy          = $this->normalizeOrder($this->req('lorder', 'datetime'));
        $this->orderType        = $this->req('lotype') === 'ASC' ? 'ASC' : 'DESC';
    }

    private function normalizeOrder(string $order): string
    {
        return match ($order) {
            'id', 'uid', 'ip', 'country', 'city', 'datetime', 'status', 'suspicious', 'banned', 'type' => $order,
            default => 'datetime'
        };
    }

    private function req(string $key, string $default = ''): string
    {
        return htmlspecialchars((string) ($_REQUEST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
    }

    /** Totals for KPI tiles and the tab counter. */
    public function getStats(): array
    {
        global $db;

        $result = $db->sql_query_prepared(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'success'), 0) AS success,
                    COALESCE(SUM(status = 'fail'), 0) AS fail,
                    COALESCE(SUM(suspicious = 'yes'), 0) AS suspicious
             FROM login_log"
        );
        $row = $result ? $db->fetch_array($result) : null;

        return [
            'total'      => (int) ($row['total'] ?? 0),
            'success'    => (int) ($row['success'] ?? 0),
            'fail'       => (int) ($row['fail'] ?? 0),
            'suspicious' => (int) ($row['suspicious'] ?? 0),
        ];
    }

    public function renderKpis(array $s): string
    {
        return maxlogin_kpis([
            ['fa-clock-rotate-left',  'primary', $s['total'],      'Log entries'],
            ['fa-circle-check',       'success', $s['success'],    'Successful logins'],
            ['fa-circle-xmark',       'danger',  $s['fail'],       'Failed logins'],
            ['fa-triangle-exclamation','warning', $s['suspicious'], 'Suspicious'],
        ]);
    }

    public function renderTab(): void
    {
        if (str_starts_with($this->action, 'log_ajax_')) {
            $this->handleAjax();
            return;
        }
        if ($this->action === 'log_delete') {
            $this->handleDelete();
            return;
        }
        $this->showList();
    }

    // ── AJAX ──────────────────────────────────────────────────────────────────

    private function handleAjax(): void
    {
        header('Content-Type: application/json');
        try {
            match ($this->action) {
                'log_ajax_get_page'   => $this->ajaxGetPage(),
                'log_ajax_get_count'  => $this->ajaxGetCount(),
                'log_ajax_delete'     => $this->ajaxDelete(),
                'log_ajax_delete_all' => $this->ajaxDeleteAll(),
                default               => throw new Exception('Unknown action')
            };
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function ajaxGetPage(): void
    {
        $this->page             = max(1, (int)($_POST['lpage'] ?? 1));
        $this->filterStatus     = htmlspecialchars((string) ($_POST['filter_status']     ?? 'all'), ENT_QUOTES, 'UTF-8');
        $this->filterSuspicious = htmlspecialchars((string) ($_POST['filter_suspicious'] ?? 'all'), ENT_QUOTES, 'UTF-8');
        $this->searchIp         = htmlspecialchars((string) ($_POST['search_log_ip']     ?? ''),    ENT_QUOTES, 'UTF-8');
        $this->orderBy          = $this->normalizeOrder((string) ($_POST['lorder'] ?? 'datetime'));
        $this->orderType        = ($_POST['lotype'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        echo json_encode(['success' => true, 'html' => $this->renderTableContent()]);
    }

    private function ajaxGetCount(): void
    {
        global $db;
        [$where, $whereParams] = $this->buildWhere();
        $result = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM login_log $where", $whereParams);
        $row = $result ? $db->fetch_array($result) : null;
        echo json_encode(['success' => true, 'count' => (int)($row['c'] ?? 0)]);
    }

    private function ajaxDelete(): void
    {
        global $db;
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid ID');
        $result = $db->sql_query_prepared("SELECT ip FROM login_log WHERE id=?", [$id]);
        $row = $result ? $db->fetch_array($result) : null;
        if (!$row) throw new Exception('Log entry not found');
        $db->sql_query_prepared("DELETE FROM login_log WHERE id=?", [$id]);
        $ip = htmlspecialchars((string) $row['ip'], ENT_QUOTES);
        echo json_encode(['success' => true, 'message' => "Log entry #{$id} ({$ip}) deleted.", 'id' => $id]);
    }

    private function ajaxDeleteAll(): void
    {
        global $db;
        $scope = $_POST['scope'] ?? 'all';

        $where = match ($scope) {
            'fail'       => "WHERE status = 'fail'",
            'success'    => "WHERE status = 'success'",
            'suspicious' => "WHERE suspicious = 'yes'",
            default      => '',
        };

        $countResult = $db->sql_query_prepared("SELECT COUNT(*) as c FROM login_log $where");
        $count = $countResult ? (int)$db->fetch_field($countResult, 'c') : 0;
        $db->sql_query_prepared("DELETE FROM login_log $where");

        echo json_encode(['success' => true, 'message' => "{$count} records deleted.", 'count' => $count]);
    }

    private function handleDelete(): void
    {
        global $db;
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) $db->sql_query_prepared("DELETE FROM login_log WHERE id=?", [$id]);
        redirect($_SERVER['PHP_SELF'] . '?act=maxlogin&tab=log&update=Delete');
    }

    // ── Main render ───────────────────────────────────────────────────────────

    private function showList(): void
    {
        echo $this->renderLogJS();
        echo '<div class="ml-card">';
        echo $this->renderLogHeader();
        echo $this->renderLogFilters();
        echo '<div id="log-table-container">';
        echo $this->renderTableContent();
        echo '</div>';
        echo '</div>';
    }

    private function renderTableContent(): string
    {
        global $db;

        [$where, $whereParams] = $this->buildWhere();
        $totalResult = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM login_log $where", $whereParams);
        $total = $totalResult ? (int)$db->fetch_field($totalResult, 'c') : 0;

        if ($total === 0) {
            return $this->renderEmpty();
        }

        $totalPages  = (int)ceil($total / self::PER_PAGE);
        $currentPage = max(1, min($this->page, $totalPages));
        $offset      = ($currentPage - 1) * self::PER_PAGE;

        $result = $db->sql_query_prepared(
            "SELECT l.*, u.username FROM login_log l
             LEFT JOIN users u ON u.id = l.uid
             $where
             ORDER BY l.{$this->orderBy} {$this->orderType}
             LIMIT ?, ?",
            [...$whereParams, $offset, self::PER_PAGE]
        );

        $rows = '';
        while ($result && ($row = $db->fetch_array($result))) {
            $rows .= $this->renderRow($row);
        }

        return $this->renderTable($rows) . maxlogin_pager($currentPage, $totalPages, $total, 'log-page');
    }

    private function buildWhere(): array
    {
        $parts = [];
        $params = [];

        if (!empty($this->searchIp)) {
            $parts[] = "ip LIKE ?";
            $params[] = '%' . $this->likeEscape($this->searchIp) . '%';
        }
        if ($this->filterStatus !== 'all' && $this->filterStatus !== '') {
            $parts[] = "status = ?";
            $params[] = $this->filterStatus;
        }
        if ($this->filterSuspicious !== 'all' && $this->filterSuspicious !== '') {
            $parts[] = "suspicious = ?";
            $params[] = $this->filterSuspicious;
        }

        $sql = empty($parts) ? '' : 'WHERE ' . implode(' AND ', $parts);
        return [$sql, $params];
    }

    private function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function renderTable(string $rows): string
    {
        $o = $this->orderBy;
        $d = $this->orderType;
        $s = fn(string $k, string $l) => maxlogin_sort_link('log-sort', $k, $l, $o, $d);

        return <<<HTML
        <div class="table-responsive">
            <table class="table table-hover align-middle ml-table">
                <thead>
                    <tr>
                        <th class="ml-w-5">{$s('id', 'ID')}</th>
                        <th class="ml-w-11">{$s('uid', '<i class="fa-solid fa-user"></i> User')}</th>
                        <th class="ml-w-14">{$s('ip', '<i class="fa-solid fa-network-wired"></i> IP')}</th>
                        <th class="ml-w-13">{$s('country', '<i class="fa-solid fa-earth-europe"></i> Location')}</th>
                        <th class="ml-w-15"><i class="fa-solid fa-display me-1"></i> Device</th>
                        <th class="ml-w-12">{$s('datetime', '<i class="fa-regular fa-clock"></i> Time')}</th>
                        <th class="ml-w-8">{$s('type', 'Type')}</th>
                        <th class="ml-w-8">{$s('status', 'Result')}</th>
                        <th class="ml-w-9">{$s('suspicious', 'Suspicious')}</th>
                        <th class="ml-w-5 text-end"></th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    /** Browser + OS icons from the user agent string. */
    private function parseUserAgent(string $ua): array
    {
        $browser = match (true) {
            $ua === ''                                        => ['fa-solid fa-circle-question', 'Unknown'],
            (bool) preg_match('~bot|crawl|spider|curl|wget|python|httpclient~i', $ua) => ['fa-solid fa-robot', 'Bot / script'],
            str_contains($ua, 'Edg/')                          => ['fa-brands fa-edge', 'Edge'],
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => ['fa-brands fa-opera', 'Opera'],
            str_contains($ua, 'YaBrowser')                     => ['fa-brands fa-yandex-international', 'Yandex'],
            str_contains($ua, 'Firefox/')                      => ['fa-brands fa-firefox-browser', 'Firefox'],
            str_contains($ua, 'Chrome/')                       => ['fa-brands fa-chrome', 'Chrome'],
            str_contains($ua, 'Safari/')                       => ['fa-brands fa-safari', 'Safari'],
            default                                           => ['fa-solid fa-globe', 'Other'],
        };

        $os = match (true) {
            str_contains($ua, 'Windows')                        => ['fa-brands fa-windows', 'Windows'],
            str_contains($ua, 'Android')                        => ['fa-brands fa-android', 'Android'],
            (bool) preg_match('~iPhone|iPad|Macintosh|Mac OS~', $ua) => ['fa-brands fa-apple', 'Apple'],
            str_contains($ua, 'Linux')                          => ['fa-brands fa-linux', 'Linux'],
            default                                            => ['fa-solid fa-desktop', ''],
        };

        return [$browser, $os];
    }

    private function renderRow(array $r): string
    {
        global $dateformat, $timeformat, $BASEURL;

        $id       = (int) $r['id'];
        $ip       = htmlspecialchars((string) $r['ip'], ENT_QUOTES);
        $ipUrl    = urlencode((string) $r['ip']);
        $country  = htmlspecialchars((string) ($r['country'] ?? ''), ENT_QUOTES);
        $city     = htmlspecialchars((string) ($r['city'] ?? ''), ENT_QUOTES);
        $uaRaw    = (string) ($r['user_agent'] ?? '');
        $uaFull   = htmlspecialchars($uaRaw, ENT_QUOTES);
        $date     = my_datee($dateformat, $r['datetime']);
        $time     = my_datee($timeformat, $r['datetime']);
        $uid      = (int) $r['uid'];

        [[$bIcon, $bName], [$oIcon, $oName]] = $this->parseUserAgent($uaRaw);
        $device = $oName !== '' ? "{$bName} on {$oName}" : $bName;

        $statusBadge = $r['status'] === 'success'
            ? '<span class="ml-chip ml-soft-success"><i class="fa-solid fa-check"></i>Success</span>'
            : '<span class="ml-chip ml-soft-danger"><i class="fa-solid fa-xmark"></i>Fail</span>';

        $suspBadge = $r['suspicious'] === 'yes'
            ? '<span class="ml-chip ml-soft-warning"><i class="fa-solid fa-triangle-exclamation"></i>Yes</span>'
            : '<span class="ml-chip ml-soft-secondary"><i class="fa-solid fa-minus"></i>No</span>';

        $typeBadge = $r['type'] === 'recover'
            ? '<span class="ml-chip ml-soft-info" title="Password recovery"><i class="fa-solid fa-key"></i>Recover</span>'
            : '<span class="ml-chip ml-soft-primary"><i class="fa-solid fa-right-to-bracket"></i>Login</span>';

        $location = $country
            ? "<i class=\"fa-solid fa-location-dot me-1 ml-muted\"></i>{$country}" . ($city ? "<div class=\"ml-muted small\">{$city}</div>" : '')
            : '<span class="ml-muted">—</span>';

        if ($uid > 0 && !empty($r['username'])) {
            $username = htmlspecialchars((string) $r['username'], ENT_QUOTES);
            $userCell = "<div class=\"ml-user\"><span class=\"ml-ico ml-mini ml-soft-primary\"><i class=\"fa-solid fa-user\"></i></span>"
                      . "<a href=\"{$BASEURL}/member.php?action=profile&amp;uid={$uid}\">{$username}</a></div>";
        } else {
            $userCell = "<div class=\"ml-user ml-muted\"><span class=\"ml-ico ml-mini ml-soft-secondary\"><i class=\"fa-solid fa-user-secret\"></i></span>Unknown</div>";
        }

        return <<<HTML
        <tr id="log-row-{$id}">
            <td class="ml-id">#{$id}</td>
            <td>{$userCell}</td>
            <td>
                <div class="ml-ipcell">
                    <code class="ml-ip">{$ip}</code>
                    <button type="button" class="btn ml-btn-icon ml-mini ml-soft-secondary ml-copy" data-copy="{$ip}" title="Copy IP">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                    <a href="{$BASEURL}/admin/index.php?act=ipsearch&amp;do=1&amp;ip={$ipUrl}" target="_blank" rel="noopener"
                       class="btn ml-btn-icon ml-mini ml-soft-info" title="Search this IP in the database">
                        <i class="fa-solid fa-magnifying-glass-location"></i>
                    </a>
                </div>
            </td>
            <td class="small">{$location}</td>
            <td>
                <div class="ml-ua" title="{$uaFull}">
                    <i class="{$bIcon}"></i><i class="{$oIcon}"></i><span>{$device}</span>
                </div>
            </td>
            <td>
                <div class="ml-when">
                    <i class="fa-regular fa-calendar"></i>{$date}<br>
                    <i class="fa-regular fa-clock"></i>{$time}
                </div>
            </td>
            <td>{$typeBadge}</td>
            <td>{$statusBadge}</td>
            <td>{$suspBadge}</td>
            <td class="text-end">
                <button type="button" class="btn ml-btn-icon ml-soft-danger log-delete-btn"
                        data-id="{$id}" data-ip="{$ip}" title="Delete">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        </tr>
        HTML;
    }

    private function renderEmpty(): string
    {
        return <<<HTML
        <div class="ml-empty">
            <span class="ml-ico ml-soft-secondary"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <h4>No login history</h4>
            <p class="ml-muted">Entries appear here after users log in.</p>
        </div>
        HTML;
    }

    /** Card head of the log section (keeps #log-spinner / #log-total-count for JS). */
    private function renderLogHeader(): string
    {
        return <<<HTML
        <div class="ml-head">
            <span class="ml-ico ml-ico-sm ml-soft-primary"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <div class="ml-head-main">
                <h5 class="ml-title">Login history</h5>
                <p class="ml-sub">Every login, successful or failed, with location and device</p>
            </div>
            <div class="ml-head-side">
                <div class="spinner-border spinner-border-sm text-primary d-none" id="log-spinner" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ml-chip ml-soft-primary"><i class="fa-solid fa-database"></i><span id="log-total-count">Loading…</span></span>
            </div>
        </div>
        HTML;
    }

    private function renderLogFilters(): string
    {
        $statusSel = $this->filterStatus;
        $suspSel   = $this->filterSuspicious;
        $searchVal = $this->searchIp;

        $sel = fn(string $v, string $c) => $v === $c ? 'selected' : '';

        return <<<HTML
        <div class="ml-toolbar">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5">
                    <div class="input-group ml-pill-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" id="log-search" placeholder="Search by IP address"
                               value="{$searchVal}" autocomplete="off">
                        <button class="btn btn-outline-secondary" id="log-clear-search" type="button" title="Clear search">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="input-group ml-pill-group">
                        <span class="input-group-text"><i class="fa-solid fa-circle-half-stroke"></i></span>
                        <select class="form-select" id="log-filter-status" aria-label="Result filter">
                            <option value="all"     {$sel($statusSel, 'all')}>All results</option>
                            <option value="success" {$sel($statusSel, 'success')}>Success</option>
                            <option value="fail"    {$sel($statusSel, 'fail')}>Failed</option>
                        </select>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="input-group ml-pill-group">
                        <span class="input-group-text"><i class="fa-solid fa-triangle-exclamation"></i></span>
                        <select class="form-select" id="log-filter-suspicious" aria-label="Suspicious filter">
                            <option value="all" {$sel($suspSel, 'all')}>All entries</option>
                            <option value="yes" {$sel($suspSel, 'yes')}>Suspicious</option>
                            <option value="no"  {$sel($suspSel, 'no')}>Normal</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary ml-btn" id="log-refresh" title="Refresh">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary ml-btn" id="log-clear-filters" title="Clear filters">
                        <i class="fa-solid fa-filter-circle-xmark"></i>
                    </button>
                    <div class="dropdown flex-fill">
                        <button class="btn btn-danger ml-btn w-100 dropdown-toggle" type="button" id="log-delete-all-btn" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-trash-can"></i> Delete
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow ml-dropdown">
                            <li><a class="dropdown-item log-delete-all" href="#" data-scope="all"><i class="fa-solid fa-dumpster me-2 text-danger"></i>All records</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item log-delete-all" href="#" data-scope="fail"><i class="fa-solid fa-circle-xmark me-2 text-danger"></i>Failed only</a></li>
                            <li><a class="dropdown-item log-delete-all" href="#" data-scope="success"><i class="fa-solid fa-circle-check me-2 text-success"></i>Successful only</a></li>
                            <li><a class="dropdown-item log-delete-all" href="#" data-scope="suspicious"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Suspicious only</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }

    private function renderLogJS(): string
    {
        $config = maxlogin_config_tag('maxlogin-log-config', [
            'orderBy'   => $this->orderBy,
            'orderType' => $this->orderType,
        ]);

        $assets = maxlogin_assets();
        $uiJs   = maxlogin_ui_js();
        $js     = maxlogin_asset_url(MAXLOGIN_JS);

        // Order matters: config block -> maxlogin-ui.js (reads it) -> maxlogin.js
        return <<<HTML
        {$assets}
        {$config}
        {$uiJs}
        <script src="{$js}"></script>
        HTML;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// TABS ROUTER
// ══════════════════════════════════════════════════════════════════════════════

function maxlogin_render_tabs(): void
{
    $tab = ($_REQUEST['tab'] ?? 'attempts') === 'log' ? 'log' : 'attempts';
    $update = htmlspecialchars((string) ($_REQUEST['update'] ?? ''), ENT_QUOTES, 'UTF-8');

    $attemptsMgr = new LoginAttemptsManager();
    $logMgr      = new LoginLogManager();
    $attStats    = $attemptsMgr->getStats();
    $logStats    = $logMgr->getStats();

    stdhead('Login Security Manager');

    echo maxlogin_styles();
    echo maxlogin_assets();

    $attCount = number_format($attStats['total']);
    $logCount = number_format($logStats['total']);
    $tabAttempts = $tab === 'attempts' ? 'active' : '';
    $tabLog      = $tab === 'log' ? 'active' : '';

    echo '<div class="ml-wrap container-xl py-3">';
    echo maxlogin_page_header(
        'fa-shield-halved',
        'primary',
        'Login security',
        'Failed attempts, IP bans and the full login history'
    );

    if ($update) {
        echo '<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mt-3 mb-0 ml-alert" role="alert">'
           . '<i class="fa-solid fa-circle-check"></i><div><strong>Done.</strong> "' . $update . '" completed.</div>'
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }

    echo <<<HTML
    <nav class="ml-tabs" id="loginTabs">
        <a class="{$tabAttempts}" href="?act=maxlogin&amp;tab=attempts">
            <i class="fa-solid fa-user-lock"></i> Failed attempts <span class="ml-count">{$attCount}</span>
        </a>
        <a class="{$tabLog}" href="?act=maxlogin&amp;tab=log">
            <i class="fa-solid fa-clock-rotate-left"></i> Login history <span class="ml-count">{$logCount}</span>
        </a>
    </nav>
    HTML;

    if ($tab === 'log') {
        echo $logMgr->renderKpis($logStats);
        $logMgr->renderTab();
    } else {
        echo $attemptsMgr->renderKpis($attStats);
        $attemptsMgr->executeInner();
    }

    echo '</div>'; // .ml-wrap

    stdfoot();
}

// Initialize and execute the manager
try {
    $rawAction = $_REQUEST['action'] ?? '';

    // ── CSRF protection for every mutating action ─────────────────────────
    $mutatingActions = [
        'ajax_ban', 'ajax_unban', 'ajax_delete',
        'ban', 'unban', 'delete', 'save',
        'log_ajax_delete', 'log_ajax_delete_all',
    ];
    if (in_array($rawAction, $mutatingActions, true)) {
        global $mybb;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            if (str_starts_with($rawAction, 'ajax_') || str_starts_with($rawAction, 'log_ajax_')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid security token or request method']);
            } else {
                stderr('Error', 'Invalid security token or request method. Please try again from the page.');
            }
            exit;
        }
    }

    // AJAX for the log tab — bypass the tab wrapper
    if (str_starts_with($rawAction, 'log_ajax_')) {
        $logMgr = new LoginLogManager();
        $logMgr->renderTab();
        exit;
    }

    // LoginAttemptsManager AJAX
    $ajaxActions = ['ajax_ban', 'ajax_unban', 'ajax_delete', 'ajax_search', 'ajax_get_page', 'ajax_get_count'];
    if (in_array($rawAction, $ajaxActions, true)) {
        $manager = new LoginAttemptsManager();
        $manager->execute();
        exit;
    }

    // Non-list actions (edit/save/ban/unban/delete/searchip for attempts)
    $nonListActions = ['ban', 'unban', 'delete', 'edit', 'save', 'searchip'];
    if (in_array($rawAction, $nonListActions, true)) {
        $manager = new LoginAttemptsManager();
        $manager->execute();
        exit;
    }

    // Full tabbed page
    maxlogin_render_tabs();

} catch (Exception $e) {
    stderr('System Error', 'An unexpected error occurred: ' . htmlspecialchars($e->getMessage()));
}