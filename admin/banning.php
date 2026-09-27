<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct access not allowed.</div>');
}

require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';

// DAY_IN_SECONDS is not defined in the admin panel context
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);

// ── fetch_ban_times ───────────────────────────────────────────────────────────
function fetch_ban_times(): array
{
    global $plugins;

    $ban_times = [
        '1-0-0'  => '1 Day',   '2-0-0'  => '2 Days',  '3-0-0'  => '3 Days',
        '4-0-0'  => '4 Days',  '5-0-0'  => '5 Days',  '6-0-0'  => '6 Days',
        '7-0-0'  => '1 Week',  '14-0-0' => '2 Weeks', '21-0-0' => '3 Weeks',
        '0-1-0'  => '1 Month', '0-2-0'  => '2 Months','0-3-0'  => '3 Months',
        '0-4-0'  => '4 Months','0-5-0'  => '5 Months','0-6-0'  => '6 Months',
        '0-0-1'  => '1 Year',  '0-0-2'  => '2 Years',
    ];

    $ban_times          = $plugins->run_hooks('functions_fetch_ban_times', $ban_times);
    $ban_times['---']   = 'Permanent';
    return $ban_times;
}

// ── ban_date2timestamp ────────────────────────────────────────────────────────
function ban_date2timestamp(string $date, int $stamp = 0): int
{
    if ($stamp === 0) $stamp = TIMENOW;

    [$days, $months, $years] = array_map('intval', explode('-', $date));

    return mktime(
        (int)date('G', $stamp),
        (int)date('i', $stamp),
        0,
        (int)date('n', $stamp) + $months,
        (int)date('j', $stamp) + $days,
        (int)date('Y', $stamp) + $years
    );
}

// ── Nav tabs ──────────────────────────────────────────────────────────────────
const BAN_NAV = [
    'ips' => [
        'title'       => 'Banned IPs',
        'link'        => 'index.php?act=banning',
        'description' => 'Manage IP addresses banned from accessing your board.',
        'icon'        => 'fa-solid fa-network-wired',
    ],
    'users' => [
        'title'       => 'Banned Accounts',
        'link'        => 'index.php?act=banning&type=users',
        'description' => 'Manage user accounts that are currently banned.',
        'icon'        => 'fa-solid fa-user-lock',
    ],
    'usernames' => [
        'title'       => 'Disallowed Usernames',
        'link'        => 'index.php?act=banning&type=usernames',
        'description' => 'Manage usernames that cannot be registered.',
        'icon'        => 'fa-solid fa-user-slash',
    ],
    'emails' => [
        'title'       => 'Disallowed Emails',
        'link'        => 'index.php?act=banning&type=emails',
        'description' => 'Manage email addresses that cannot be used for registration.',
        'icon'        => 'fa-solid fa-envelope',
    ],
];

// ── Route ─────────────────────────────────────────────────────────────────────
$ban_type = $mybb->get_input('type') ?: 'ips';

if ($ban_type === 'users') {
    (new BannedAccountsManager($mybb, $db, $cache, $plugins))->handleRequest();
} else {
    (new BanManager($mybb, $db, $cache, $plugins))->handleRequest();
}

// ═════════════════════════════════════════════════════════════════════════════
//  BanView — shared page chrome (header, KPI tiles, empty states, confirm page)
// ═════════════════════════════════════════════════════════════════════════════
final class BanView
{
    public const ASSET_VER = 1;

    private const TONES = [
        'ips'       => 'danger',
        'users'     => 'primary',
        'usernames' => 'warning',
        'emails'    => 'info',
    ];

    public static function assets(): void
    {
        global $BASEURL;
        $v = self::ASSET_VER;
        ?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/banning.css?ver=<?= $v ?>">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/banning.js?ver=<?= $v ?>" defer></script>
        <?php
    }

    /** Opens the page wrapper: assets, header card, nav tabs. Close with BanView::close(). */
    public static function open(string $section, string $postKey): void
    {
        $nav  = BAN_NAV[$section] ?? BAN_NAV['ips'];
        $tone = self::TONES[$section] ?? 'danger';
        self::assets();
        ?>
<div class="bn-page bn-t-<?= $tone ?>" data-post-key="<?= htmlspecialchars_uni($postKey) ?>">
  <div class="container py-4">
    <header class="bn-head">
      <div class="bn-head-icon" aria-hidden="true"><i class="<?= $nav['icon'] ?>"></i></div>
      <div class="bn-head-text">
        <h1 class="bn-title"><?= htmlspecialchars_uni($nav['title']) ?></h1>
        <p class="bn-sub"><?= htmlspecialchars_uni($nav['description']) ?></p>
      </div>
    </header>
        <?php
        output_nav_tabs(BAN_NAV, $section);
    }

    public static function close(): void
    {
        echo "\n  </div>\n</div>\n";
    }

    /**
     * @param list<array{icon:string,label:string,value:int|string,tone?:string,raw?:bool}> $tiles
     *        icon without the fa-solid prefix; raw=true means value is trusted HTML
     */
    public static function kpis(array $tiles): void
    {
        echo '<div class="bn-kpis">';
        foreach ($tiles as $t) {
            $tone  = !empty($t['tone']) ? ' bn-t-' . $t['tone'] : '';
            $isNum = is_int($t['value']);
            $val   = match (true) {
                $isNum          => number_format($t['value']),
                !empty($t['raw']) => (string)$t['value'],
                default         => htmlspecialchars_uni((string)$t['value']),
            };
            echo '<div class="bn-kpi' . $tone . '">'
               . '<span class="bn-kpi-icon" aria-hidden="true"><i class="fa-solid ' . $t['icon'] . '"></i></span>'
               . '<div class="bn-kpi-body">'
               . '<div class="bn-kpi-val' . ($isNum ? '' : ' is-text') . '">' . $val . '</div>'
               . '<div class="bn-kpi-label">' . htmlspecialchars_uni($t['label']) . '</div>'
               . '</div></div>';
        }
        echo '</div>';
    }

    public static function errors(array $errors): void
    {
        if (empty($errors)) return;
        echo '<div class="bn-alert" role="alert">'
           . '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><ul>';
        foreach ($errors as $e) echo '<li>' . htmlspecialchars_uni((string)$e) . '</li>';
        echo '</ul></div>';
    }

    public static function emptyState(string $icon, string $title, string $text): string
    {
        return '<div class="bn-empty">'
             . '<div class="bn-empty-icon" aria-hidden="true"><i class="fa-solid ' . $icon . '"></i></div>'
             . '<h3>' . htmlspecialchars_uni($title) . '</h3>'
             . '<p>' . htmlspecialchars_uni($text) . '</p>'
             . '</div>';
    }

    /** Ban filter with wildcards, CIDR suffix and @ highlighted. */
    public static function pattern(string $raw, int $type): string
    {
        $s = htmlspecialchars_uni($raw);
        if ($type === 1) {
            $s = preg_replace('~/(\d{1,3})$~', '<span class="bn-cidr">/$1</span>', $s) ?? $s;
        }
        $s = str_replace('*', '<span class="bn-wild">*</span>', $s);
        if ($type === 3) {
            $s = str_replace('@', '<span class="bn-at">@</span>', $s);
        }
        return '<span class="bn-pattern">' . $s . '</span>';
    }

    /**
     * No-JS fallback confirmation page (JS users get SweetAlert2 instead).
     * @param array<string,string> $subject label => trusted HTML
     */
    public static function confirmPage(
        string $postKey,
        string $tone,
        string $icon,
        string $title,
        string $message,
        array  $subject,
        string $actionUrl,
        string $cancelUrl,
        string $okLabel,
        string $okIcon
    ): void {
        self::assets();
        ?>
<div class="bn-page bn-t-<?= $tone ?>" data-cancel-url="<?= htmlspecialchars_uni($cancelUrl) ?>">
  <div class="bn-confirm-backdrop"></div>
  <div class="bn-confirm" role="dialog" aria-modal="true" aria-labelledby="bn-confirm-title">
    <div class="bn-confirm-icon" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></div>
    <h1 id="bn-confirm-title"><?= htmlspecialchars_uni($title) ?></h1>
    <p class="bn-confirm-text"><?= htmlspecialchars_uni($message) ?></p>
    <dl class="bn-confirm-subject">
      <?php foreach ($subject as $label => $html): ?>
      <div><dt><?= htmlspecialchars_uni($label) ?></dt><dd><?= $html ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <div class="bn-confirm-actions">
      <a href="<?= htmlspecialchars_uni($cancelUrl) ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="fa-solid fa-xmark me-2"></i>Cancel
      </a>
      <form action="<?= htmlspecialchars_uni($actionUrl) ?>" method="post" class="d-inline">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars_uni($postKey) ?>">
        <button type="submit" class="btn btn-<?= $tone ?> rounded-pill px-4" autofocus>
          <i class="fa-solid <?= $okIcon ?> me-2"></i><?= htmlspecialchars_uni($okLabel) ?>
        </button>
      </form>
    </div>
  </div>
</div>
        <?php
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  BanManager — IP / usernames / emails
// ═════════════════════════════════════════════════════════════════════════════
class BanManager
{
    private const BAN_TYPES = ['ips' => 1, 'usernames' => 2, 'emails' => 3];

    private const TYPE_CONFIGS = [
        1 => ['title' => 'Banned IP Addresses',        'redirect' => '',          'icon' => 'fa-network-wired',              'color' => 'danger'],
        2 => ['title' => 'Disallowed Usernames',        'redirect' => 'usernames', 'icon' => 'fa-user-slash',                 'color' => 'warning'],
        3 => ['title' => 'Disallowed Email Addresses',  'redirect' => 'emails',    'icon' => 'fa-envelope-circle-exclamation','color' => 'info'],
    ];

    private const FORM_CONFIGS = [
        1 => ['title' => 'Ban IP Address',         'label' => 'IP Address',     'description' => 'To ban a range use * (Ex: 127.0.0.*) or CIDR (Ex: 127.0.0.0/8)', 'button' => 'Ban IP Address',         'icon' => 'fa-ban',       'input_icon' => 'fa-location-crosshairs', 'placeholder' => 'Enter IP address or range...'],
        2 => ['title' => 'Disallow Username',       'label' => 'Username',       'description' => 'Use * for wildcard (Ex: admin*, *bot)',                             'button' => 'Disallow Username',      'icon' => 'fa-user-lock', 'input_icon' => 'fa-user',                'placeholder' => 'Enter username pattern...'],
        3 => ['title' => 'Disallow Email Address',  'label' => 'Email Address',  'description' => 'Use * for wildcard (Ex: *@spam.com)',                               'button' => 'Disallow Email Address', 'icon' => 'fa-envelope',  'input_icon' => 'fa-at',                  'placeholder' => 'Enter email pattern...'],
    ];

    private const PER_PAGE = 20;

    public function __construct(
        private readonly object $mybb,
        private readonly object $db,
        private readonly object $cache,
        private readonly object $plugins
    ) {}

    public function handleRequest(): void
    {
        match ($this->mybb->get_input('action')) {
            'add'    => $this->handleAdd(),
            'delete' => $this->handleDelete(),
            default  => $this->displayInterface(),
        };
    }

    private function handleAdd(): void
    {
        $this->plugins->run_hooks('admin_config_banning_add');

        if ($this->mybb->request_method !== 'post') {
            flash_message('Invalid request method', 'error');
            admin_redirect('index.php?act=banning');
        }

        if (!verify_post_check($this->mybb->get_input('my_post_key'))) {
            http_response_code(403);
            die('Invalid security token');
        }

        $filter = $this->mybb->get_input('filter');
        $type   = $this->mybb->get_input('type', MyBB::INPUT_INT);
        $errors = $this->validateAdd($filter, $type);

        if (empty($errors)) {
            $this->addBanFilter($filter, $type);
            $cfg = self::TYPE_CONFIGS[$type];
            flash_message('Ban added successfully', 'success');
            admin_redirect('index.php?act=banning' . ($cfg['redirect'] ? '&type=' . $cfg['redirect'] : ''));
        }

        // Errors are rendered inside the page (after stdhead), with the typed value kept
        $this->displayInterface($errors, $filter, $type);
    }

    private function handleDelete(): void
    {
        $fid    = $this->mybb->get_input('fid', MyBB::INPUT_INT);
        $filter = $this->getFilterById($fid);

        if (!$filter) {
            flash_message('The specified filter does not exist', 'error');
            admin_redirect('index.php?act=banning');
        }

        $this->plugins->run_hooks('admin_config_banning_delete');

        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning&type=' . $this->getTypeName((int)$filter['type']));
        }

        if ($this->mybb->request_method === 'post') {
            if (!verify_post_check($this->mybb->get_input('my_post_key'))) {
                http_response_code(403);
                die('Invalid security token');
            }

            $this->db->sql_query_prepared("DELETE FROM banfilters WHERE fid = ?", [$filter['fid']]);
            $this->plugins->run_hooks('admin_config_banning_delete_commit');
            $this->updateCaches((int)$filter['type']);
            log_admin_action((int)$filter['fid'], $filter['filter'], (int)$filter['type']);
            flash_message('Ban deleted successfully', 'success');
            admin_redirect('index.php?act=banning&type=' . $this->getTypeName((int)$filter['type']));
        } else {
            $this->showDeleteConfirmation($filter);
        }
    }

    private function displayInterface(array $errors = [], string $value = '', int $type = 0): void
    {
        $this->plugins->run_hooks('admin_config_banning_start');
        $typeConfig = $type > 0 && isset(self::TYPE_CONFIGS[$type])
            ? self::TYPE_CONFIGS[$type] + ['type' => $type, 'name' => $this->getTypeName($type)]
            : $this->getCurrentTypeConfig();
        $this->renderInterface($typeConfig, $errors, $value);
    }

    private function validateAdd(string $filter, int $type): array
    {
        $errors = [];
        if (!isset(self::TYPE_CONFIGS[$type]))                 return ['Invalid ban type'];
        if (empty(trim($filter)))                              $errors[] = 'Please enter a value to ban';
        if ($this->isDuplicateFilter($filter, $type))          $errors[] = 'This filter already exists';
        if ($type === 1 && !$this->isValidIPFilter($filter))   $errors[] = 'Please enter a valid IP address or range';
        if ($type === 3 && !$this->isValidEmailFilter($filter)) $errors[] = 'Please enter a valid email pattern';
        return $errors;
    }

    private function isValidIPFilter(string $f): bool
    {
        if (str_contains($f, '/')) {
            $p = explode('/', $f);
            return count($p) === 2 && filter_var($p[0], FILTER_VALIDATE_IP) && $p[1] >= 0 && $p[1] <= 128;
        }
        return (bool)preg_match('/^[0-9.*]+$/', $f);
    }

    private function isValidEmailFilter(string $f): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9.*_%+-]+@[a-zA-Z0-9.*-]+\.[a-zA-Z]{2,}$/', str_replace('*', 'wildcard', $f));
    }

    private function isDuplicateFilter(string $filter, int $type): bool
    {
        $q = $this->db->sql_query_prepared("SELECT fid FROM banfilters WHERE filter = ? AND type = ?", [$filter, $type]);
        return $q ? $this->db->num_rows($q) > 0 : false;
    }

    private function addBanFilter(string $filter, int $type): void
    {
        $this->db->sql_query_prepared(
            "INSERT INTO banfilters (`filter`,`type`,`dateline`,`lastuse`) VALUES (?,?,?,?)",
            [trim($filter), $type, TIMENOW, 0]
        );
        $fid = $this->db->insert_id();
        $this->plugins->run_hooks('admin_config_banning_add_commit');
        $this->updateCaches($type);
        log_admin_action((int)$fid, $filter, $type);
    }

    private function updateCaches(int $type): void
    {
        match ($type) {
            1 => $this->cache->update_bannedips(),
            3 => $this->cache->update_bannedemails(),
            default => null,
        };
    }

    private function getFilterById(int $fid): ?array
    {
        $q   = $this->db->sql_query_prepared("SELECT * FROM banfilters WHERE fid = ?", [$fid]);
        $row = $q ? $this->db->fetch_array($q) : null;
        if (!$row) return null;
        $row['fid']      = (int)$row['fid'];
        $row['type']     = (int)$row['type'];
        $row['dateline'] = (int)$row['dateline'];
        $row['lastuse']  = (int)$row['lastuse'];
        return $row;
    }

    private function getTypeName(int $type): string
    {
        return array_flip(self::BAN_TYPES)[$type] ?? 'ips';
    }

    private function getCurrentTypeConfig(): array
    {
        return match ($this->mybb->get_input('type')) {
            'emails'    => self::TYPE_CONFIGS[3] + ['type' => 3, 'name' => 'emails'],
            'usernames' => self::TYPE_CONFIGS[2] + ['type' => 2, 'name' => 'usernames'],
            default     => self::TYPE_CONFIGS[1] + ['type' => 1, 'name' => 'ips'],
        };
    }

    /** @return array{total:int,recent:int,triggered:int,lastuse:int} */
    private function getStats(int $type): array
    {
        $q = $this->db->sql_query_prepared(
            "SELECT COUNT(*)                     AS total,
                    COALESCE(SUM(dateline >= ?), 0) AS recent,
                    COALESCE(SUM(lastuse > 0), 0)   AS triggered,
                    COALESCE(MAX(lastuse), 0)       AS lastuse
             FROM banfilters WHERE type = ?",
            [TIMENOW - 7 * DAY_IN_SECONDS, $type]
        );
        $r = $q ? $this->db->fetch_array($q) : null;

        return [
            'total'     => (int)($r['total']     ?? 0),
            'recent'    => (int)($r['recent']    ?? 0),
            'triggered' => (int)($r['triggered'] ?? 0),
            'lastuse'   => (int)($r['lastuse']   ?? 0),
        ];
    }

    private function renderInterface(array $tc, array $errors = [], string $value = ''): void
    {
        $stats = $this->getStats($tc['type']);

        stdhead($tc['title']);
        BanView::open($tc['name'], (string)$this->mybb->post_code);

        BanView::kpis([
            ['icon' => $tc['icon'],            'label' => $tc['type'] === 1 ? 'Banned addresses' : 'Blocked patterns', 'value' => $stats['total']],
            ['icon' => 'fa-calendar-plus',     'label' => 'Added in the last 7 days', 'value' => $stats['recent'],    'tone' => 'success'],
            ['icon' => 'fa-bolt',              'label' => 'Triggered at least once',  'value' => $stats['triggered'], 'tone' => 'warning'],
            ['icon' => 'fa-clock-rotate-left', 'label' => $tc['type'] === 1 ? 'Last blocked access' : 'Last blocked attempt',
             'value' => $stats['lastuse'] > 0 ? my_datee('relative', $stats['lastuse']) : 'Never', 'raw' => true, 'tone' => 'secondary'],
        ]);

        BanView::errors($errors);
        $this->outputAddForm($tc, $value);
        $this->outputBanList($tc, $stats['total']);

        BanView::close();
        stdfoot();
    }

    private function outputAddForm(array $tc, string $value = ''): void
    {
        $cfg   = self::FORM_CONFIGS[$tc['type']];
        $color = $tc['color'];
        ?>
    <form action="index.php?act=banning&amp;action=add" method="post" class="bn-panel bn-t-<?= $color ?>" data-bn-form>
      <input type="hidden" name="my_post_key" value="<?= htmlspecialchars_uni((string)$this->mybb->post_code) ?>">
      <input type="hidden" name="type" value="<?= (int)$tc['type'] ?>">

      <div class="bn-panel-head">
        <span class="bn-chip-icon" aria-hidden="true"><i class="fa-solid <?= $cfg['icon'] ?>"></i></span>
        <div>
          <h2><?= htmlspecialchars_uni($cfg['title']) ?></h2>
          <p>Takes effect immediately after saving.</p>
        </div>
      </div>

      <div class="bn-panel-body">
        <label for="bn-filter" class="bn-label">
          <i class="fa-solid <?= $cfg['input_icon'] ?>" aria-hidden="true"></i><?= htmlspecialchars_uni($cfg['label']) ?>
          <span class="bn-req" aria-hidden="true">*</span>
        </label>
        <div class="input-group input-group-lg bn-input">
          <span class="input-group-text" aria-hidden="true"><i class="fa-solid <?= $cfg['input_icon'] ?>"></i></span>
          <input type="text" id="bn-filter" name="filter" class="form-control"
                 value="<?= htmlspecialchars_uni($value) ?>"
                 placeholder="<?= htmlspecialchars_uni($cfg['placeholder']) ?>"
                 aria-describedby="bn-filter-help" required autofocus autocomplete="off" spellcheck="false">
        </div>
        <div class="bn-help" id="bn-filter-help">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i><span><?= htmlspecialchars_uni($cfg['description']) ?></span>
        </div>
      </div>

      <div class="bn-actionbar">
        <button type="submit" class="btn btn-<?= $color ?> rounded-pill" data-bn-submit>
          <i class="fa-solid <?= $cfg['icon'] ?> me-2"></i><?= htmlspecialchars_uni($cfg['button']) ?>
        </button>
      </div>
    </form>
        <?php
    }

    private function outputBanList(array $tc, int $total): void
    {
        $page  = max(1, $this->mybb->get_input('page', MyBB::INPUT_INT));
        $start = ($page - 1) * self::PER_PAGE;
        $q     = $this->db->sql_query_prepared(
            "SELECT * FROM banfilters WHERE type = ? ORDER BY dateline DESC LIMIT ?, ?",
            [$tc['type'], $start, self::PER_PAGE]
        );
        $filters = [];
        while ($q && ($f = $this->db->fetch_array($q))) {
            $f['fid'] = (int)$f['fid']; $f['type'] = (int)$f['type'];
            $f['dateline'] = (int)$f['dateline']; $f['lastuse'] = (int)$f['lastuse'];
            $filters[] = $f;
        }

        [$colValue, $colDate, $colLast, $valueIcon] = match ($tc['type']) {
            2       => ['Username', 'Disallowed', 'Last attempt', 'fa-user'],
            3       => ['Email',    'Disallowed', 'Last attempt', 'fa-at'],
            default => ['IP address / range', 'Banned', 'Last access', 'fa-location-crosshairs'],
        };

        $deleteText = match ($tc['type']) {
            2       => '%s can be registered again.',
            3       => '%s can be used to register again.',
            default => '%s will be able to access the site again.',
        };

        $emptyText = match ($tc['type']) {
            2       => 'Add a username pattern above to stop it being registered.',
            3       => 'Add an email pattern above to stop it being used for sign-up.',
            default => 'Add an IP address or range above to block it.',
        };

        $postKey = (string)$this->mybb->post_code;
        ?>
    <section class="bn-panel bn-panel-table">
      <div class="bn-panel-head">
        <span class="bn-chip-icon" aria-hidden="true"><i class="fa-solid <?= $tc['icon'] ?>"></i></span>
        <div><h2><?= htmlspecialchars_uni($tc['title']) ?></h2></div>
        <span class="bn-count" title="Total"><?= number_format($total) ?></span>
      </div>

      <?php if (empty($filters)): ?>
        <?= BanView::emptyState($tc['icon'], 'Nothing blocked yet', $emptyText) ?>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table bn-table mb-0">
          <thead>
            <tr>
              <th scope="col"><i class="fa-solid <?= $valueIcon ?>" aria-hidden="true"></i><?= $colValue ?></th>
              <th scope="col"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i><?= $colDate ?></th>
              <th scope="col"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><?= $colLast ?></th>
              <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($filters as $f):
              $raw     = (string)$f['filter'];
              $date    = $f['dateline'] > 0 ? my_datee('relative', $f['dateline']) : '—';
              $isNew   = (TIMENOW - $f['dateline']) < DAY_IN_SECONDS;
              $delUrl  = "index.php?act=banning&action=delete&fid={$f['fid']}&my_post_key={$postKey}";
          ?>
            <tr>
              <td>
                <?= BanView::pattern($raw, $f['type']) ?>
                <?php if ($isNew): ?><span class="bn-tag bn-t-success ms-2"><i class="fa-solid fa-star" aria-hidden="true"></i>New</span><?php endif; ?>
              </td>
              <td class="bn-muted"><?= $date ?></td>
              <td class="bn-muted">
                <?php if ($f['lastuse'] > 0): ?>
                  <span class="bn-hit"><i class="fa-solid fa-bolt" aria-hidden="true"></i><?= my_datee('relative', $f['lastuse']) ?></span>
                <?php else: ?>
                  Never
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a href="<?= htmlspecialchars_uni($delUrl) ?>" class="bn-icon-btn bn-t-danger"
                   title="Delete" aria-label="Delete <?= htmlspecialchars_uni($raw) ?>"
                   data-bn-confirm data-tone="danger" data-ok="Delete"
                   data-title="Delete this ban?"
                   data-text="<?= htmlspecialchars_uni(sprintf($deleteText, '“' . $raw . '”')) ?>">
                  <i class="fa-solid fa-trash-can"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
        <?php
        if ($total > self::PER_PAGE) {
            echo '<nav class="bn-pager" aria-label="Pages">'
               . multipage($total, self::PER_PAGE, $page, "index.php?act=banning&type={$tc['name']}&page={page}")
               . '</nav>';
        }
    }

    private function showDeleteConfirmation(array $filter): void
    {
        $tc      = self::TYPE_CONFIGS[$filter['type']] ?? self::TYPE_CONFIGS[1];
        $postKey = (string)$this->mybb->post_code;
        $delUrl  = "index.php?act=banning&action=delete&fid={$filter['fid']}&my_post_key={$postKey}";
        $canUrl  = 'index.php?act=banning&type=' . $this->getTypeName((int)$filter['type']);

        stdhead('Confirm Deletion');
        BanView::confirmPage(
            $postKey, 'danger', 'fa-trash-can',
            'Delete this ban?',
            'The filter will stop blocking right away.',
            [
                'Filter' => BanView::pattern((string)$filter['filter'], (int)$filter['type']),
                'List'   => '<i class="fa-solid ' . $tc['icon'] . ' me-1"></i>' . htmlspecialchars_uni($tc['title']),
            ],
            $delUrl, $canUrl, 'Delete', 'fa-trash-can'
        );
        stdfoot();
        exit;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  BannedAccountsManager — banned user accounts
// ═════════════════════════════════════════════════════════════════════════════
class BannedAccountsManager
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly object $mybb,
        private readonly object $db,
        private readonly object $cache,
        private readonly object $plugins
    ) {}

    public function handleRequest(): void
    {
        match ($this->mybb->get_input('action')) {
            'prune'          => $this->handlePrune(),
            'lift'           => $this->handleLift(),
            'edit'           => $this->handleEdit(),
            'search_username' => $this->handleSearchUsername(),
            default          => $this->displayInterface(),
        };
    }

    private function handleSearchUsername(): void
    {
        $term = trim($this->mybb->get_input('q'));

        header('Content-Type: application/json');

        if (mb_strlen($term) < 2) {
            echo json_encode([]);
            exit;
        }

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $q = $this->db->sql_query_prepared(
            "SELECT id, username FROM users WHERE username LIKE ? ORDER BY username ASC LIMIT 10",
            ['%' . $like . '%']
        );

        $results = [];
        while ($q && ($row = $this->db->fetch_array($q))) {
            $results[] = ['id' => (int)$row['id'], 'username' => $row['username']];
        }

        echo json_encode($results);
        exit;
    }

    private function handlePrune(): void
    {
        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning&type=users');
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            flash_message('Invalid ban specified', 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        if (is_super_admin((int)$user['id']) && !$this->canModifySuperAdmin()) {
            flash_message('You cannot perform this action on a super administrator', 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        $this->plugins->run_hooks('admin_user_banning_prune');

        if ($this->mybb->request_method === 'post') {
            if (!verify_post_check($this->mybb->get_input('my_post_key'))) {
                http_response_code(403);
                die('Invalid security token');
            }

            require_once INC_PATH . '/class_moderation.php';
            $mod = new Moderation();
            $q   = $this->db->sql_query_prepared("SELECT tid FROM threads WHERE uid = ?", [$user['id']]);
            while ($q && ($r = $this->db->fetch_array($q))) $mod->delete_thread($r['tid']);
            $q = $this->db->sql_query_prepared("SELECT pid FROM posts WHERE uid = ?", [$user['id']]);
            while ($q && ($r = $this->db->fetch_array($q))) $mod->delete_post($r['pid']);
            $this->plugins->run_hooks('admin_user_banning_prune_commit');

            log_admin_action((int)$user['id'], $user['username']);
            flash_message('User content pruned successfully', 'success');
            admin_redirect('index.php?act=banning&type=users');
        } else {
            $this->showConfirmation($user,
                "index.php?act=banning&type=users&action=prune&uid={$user['id']}",
                'index.php?act=banning&type=users',
                'Prune all content?',
                'Every thread and post by this user will be deleted. This cannot be undone.',
                'danger', 'fa-broom', 'Prune content'
            );
        }
    }

    private function handleLift(): void
    {
        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning&type=users');
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            flash_message('Invalid ban specified', 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        if (is_super_admin((int)$ban['uid']) && !$this->canModifySuperAdmin()) {
            flash_message('You cannot perform this action on a super administrator', 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        $this->plugins->run_hooks('admin_user_banning_lift');

        if ($this->mybb->request_method === 'post') {
            if (!verify_post_check($this->mybb->get_input('my_post_key'))) {
                http_response_code(403);
                die('Invalid security token');
            }

            $this->db->sql_query_prepared("DELETE FROM banned WHERE uid = ?", [$ban['uid']]);
            $this->db->sql_query_prepared(
                "UPDATE users SET usergroup = ?, additionalgroups = ?, displaygroup = ? WHERE id = ?",
                [$ban['oldgroup'], $ban['oldadditionalgroups'], $ban['olddisplaygroup'], $ban['uid']]
            );
            $this->plugins->run_hooks('admin_user_banning_lift_commit');
            log_admin_action($ban['uid'], $user['username']);
            flash_message('Ban lifted successfully', 'success');
            admin_redirect('index.php?act=banning&type=users');
        } else {
            $this->showConfirmation($user,
                "index.php?act=banning&type=users&action=lift&uid={$ban['uid']}",
                'index.php?act=banning&type=users',
                'Lift this ban?',
                'The user will be moved back to their previous group.',
                'success', 'fa-lock-open', 'Lift ban'
            );
        }
    }

    private function handleEdit(): void
    {
        $uid  = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban  = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            flash_message('Invalid ban specified', 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        $bannedGroups = $this->getBannedGroups();
        $banTimes     = fetch_ban_times();
        $errors       = [];

        $this->plugins->run_hooks('admin_user_banning_edit');

        if ($this->mybb->request_method === 'post') {
            if (!verify_post_check($this->mybb->get_input('my_post_key'))) {
                http_response_code(403);
                die('Invalid security token');
            }

            if (empty($ban['uid'])) {
                $errors[] = 'Invalid user';
            } elseif (is_super_admin($ban['uid']) && !$this->canModifySuperAdmin()) {
                $errors[] = 'You do not have permission to edit this ban';
            }

            if (empty($errors)) {
                $bantime = $this->mybb->input['bantime'] ?? '---';
                $lifted  = $bantime === '---' ? 0 : ban_date2timestamp($bantime, $ban['dateline']);
                $reason  = my_substr($this->mybb->input['reason'] ?? '', 0, 255);

                if (count($bannedGroups) === 1) $this->mybb->input['usergroup'] = array_key_first($bannedGroups);

                $this->db->sql_query_prepared(
                    "UPDATE banned SET gid = ?, dateline = ?, bantime = ?, lifted = ?, reason = ? WHERE uid = ?",
                    [
                        $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
                        TIMENOW,
                        $bantime,
                        $lifted,
                        $reason,
                        $ban['uid'],
                    ]
                );

                $this->db->sql_query_prepared(
                    "UPDATE users SET usergroup = ?, displaygroup = 0, additionalgroups = '' WHERE id = ?",
                    [$this->mybb->get_input('usergroup', MyBB::INPUT_INT), $ban['uid']]
                );

                $this->plugins->run_hooks('admin_user_banning_edit_commit');
                log_admin_action($ban['uid'], $user['username']);
                flash_message('Ban updated successfully', 'success');
                admin_redirect('index.php?act=banning&type=users');
            }
        }

        $this->renderEditForm($ban, $user, $bannedGroups, $banTimes, $errors);
    }

    private function displayInterface(): void
    {
        $bannedGroups = $this->getBannedGroups();
        $banTimes     = fetch_ban_times();
        $errors       = [];

        $this->plugins->run_hooks('admin_user_banning_start');

        if ($this->mybb->request_method === 'post') {
            if (!verify_post_check($this->mybb->get_input('my_post_key'))) {
                http_response_code(403);
                die('Invalid security token');
            }

            $errors = $this->processBanAction($bannedGroups);
            if (empty($errors)) return;
        }

        $this->renderMainInterface($bannedGroups, $banTimes, $errors);
    }

    private function getBanByUserId(int $uid): ?array
    {
        $q   = $this->db->sql_query_prepared("SELECT * FROM banned WHERE uid = ?", [$uid]);
        $row = $q ? $this->db->fetch_array($q) : null;
        if (!$row) return null;
        foreach (['uid','gid','oldgroup','olddisplaygroup','admin','dateline','lifted'] as $k) {
            $row[$k] = (int)$row[$k];
        }
        return $row;
    }

    private function canModifySuperAdmin(): bool
    {
        global $CURUSER;
        return is_super_admin((int)$CURUSER['id']);
    }

    private function getCurrentUserId(): int
    {
        global $CURUSER;
        return (int)$CURUSER['id'];
    }

    private function getBannedGroups(): array
    {
        $q = $this->db->sql_query_prepared("SELECT gid,title FROM usergroups WHERE isbannedgroup=1 ORDER BY title");
        $g = [];
        while ($q && ($r = $this->db->fetch_array($q))) $g[(int)$r['gid']] = $r['title'];
        return $g;
    }

    private function processBanAction(array $bannedGroups): array
    {
        if (isset($this->mybb->input['search'])) return [];

        $user = get_user_by_username($this->mybb->get_input('username'), [
            'fields' => ['username','usergroup','additionalgroups','displaygroup'],
        ]);

        if (!$user) return ['The username you entered is invalid and does not exist'];

        $uid    = (int)$user['id'];
        $errors = [];

        if (is_super_admin($uid) && !$this->canModifySuperAdmin()) $errors[] = 'You do not have permission to ban this user';
        elseif ($this->isUserAlreadyBanned($uid))                   $errors[] = 'This user is already banned';
        elseif ($uid === $this->getCurrentUserId())                  $errors[] = 'You cannot ban yourself';

        if (empty($errors)) {
            $bantime = $this->mybb->input['bantime'] ?? '---';
            $lifted  = $bantime === '---' ? 0 : ban_date2timestamp($bantime);
            $reason  = my_substr($this->mybb->input['reason'] ?? '', 0, 255);

            if (count($bannedGroups) === 1) $this->mybb->input['usergroup'] = array_key_first($bannedGroups);

            $this->db->sql_query_prepared(
                "INSERT INTO banned (`uid`,`gid`,`oldgroup`,`oldadditionalgroups`,`olddisplaygroup`,`admin`,`dateline`,`bantime`,`lifted`,`reason`) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $uid,
                    $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
                    (int)$user['usergroup'],
                    $user['additionalgroups'],
                    (int)$user['displaygroup'],
                    $this->getCurrentUserId(),
                    TIMENOW,
                    $bantime,
                    $lifted,
                    $reason,
                ]
            );

            $this->db->sql_query_prepared(
                "UPDATE users SET usergroup = ?, displaygroup = 0, additionalgroups = '' WHERE id = ?",
                [$this->mybb->get_input('usergroup', MyBB::INPUT_INT), $uid]
            );
            $this->db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE uid = ?", [$uid]);
            $this->db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE uid = ?", [$uid]);

            $this->plugins->run_hooks('admin_user_banning_start_commit');
            log_admin_action($uid, $user['username'], $lifted);
            flash_message('User banned successfully', 'success');
            admin_redirect('index.php?act=banning&type=users');
        }

        return $errors;
    }

    private function isUserAlreadyBanned(int $uid): bool
    {
        $q = $this->db->sql_query_prepared("SELECT uid FROM banned WHERE uid = ?", [$uid]);
        if ($q && $this->db->fetch_field($q, 'uid')) return true;
        $usergroups = $this->cache->read('usergroups');
        $user       = get_user($uid);
        return !empty($usergroups[(int)($user['usergroup'] ?? 0)]['isbannedgroup']);
    }

    /** @return array{total:int,perm:int,soon:int,recent:int} */
    private function getStats(): array
    {
        $q = $this->db->sql_query_prepared(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(lifted = 0 OR bantime IN ('perm','---')), 0)                       AS perm,
                    COALESCE(SUM(lifted > 0 AND bantime NOT IN ('perm','---') AND lifted <= ?), 0)   AS soon,
                    COALESCE(SUM(dateline >= ?), 0)                                                   AS recent
             FROM banned",
            [TIMENOW + DAY_IN_SECONDS, TIMENOW - 7 * DAY_IN_SECONDS]
        );
        $r = $q ? $this->db->fetch_array($q) : null;

        return [
            'total'  => (int)($r['total']  ?? 0),
            'perm'   => (int)($r['perm']   ?? 0),
            'soon'   => (int)($r['soon']   ?? 0),
            'recent' => (int)($r['recent'] ?? 0),
        ];
    }

    private function renderEditForm(array $ban, array $user, array $bannedGroups, array $banTimes, array $errors): void
    {
        global $dateformat;

        $isPerm  = $ban['lifted'] === 0 || in_array($ban['bantime'], ['perm', '---'], true);
        $name    = htmlspecialchars_uni((string)$user['username']);
        $length  = $banTimes[$ban['bantime']] ?? ($isPerm ? 'Permanent' : (string)$ban['bantime']);

        stdhead('Edit Ban');
        BanView::open('users', (string)$this->mybb->post_code);
        BanView::errors($errors);
        ?>
    <form action="index.php?act=banning&amp;type=users&amp;action=edit&amp;uid=<?= $ban['uid'] ?>" method="post" class="bn-panel bn-t-warning" data-bn-form>
      <input type="hidden" name="my_post_key" value="<?= htmlspecialchars_uni((string)$this->mybb->post_code) ?>">

      <div class="bn-panel-head">
        <span class="bn-avatar" aria-hidden="true"><?= htmlspecialchars_uni(mb_strtoupper(mb_substr((string)$user['username'], 0, 1))) ?></span>
        <div>
          <h2>Edit ban for <?= $name ?></h2>
          <p>UID <?= (int)$user['id'] ?></p>
        </div>
      </div>

      <div class="bn-panel-body">
        <div class="bn-facts">
          <span class="bn-tag bn-t-secondary"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i>Banned <?= my_datee($dateformat, $ban['dateline']) ?></span>
          <span class="bn-tag bn-t-<?= $isPerm ? 'danger' : 'info' ?>">
            <i class="fa-solid <?= $isPerm ? 'fa-infinity' : 'fa-hourglass-half' ?>" aria-hidden="true"></i>
            <?= $isPerm ? 'Permanent' : 'Lifts ' . my_datee($dateformat, $ban['lifted']) ?>
          </span>
          <span class="bn-tag bn-t-secondary"><i class="fa-solid fa-ruler-horizontal" aria-hidden="true"></i><?= htmlspecialchars_uni($length) ?></span>
        </div>

        <div class="row g-3">
          <div class="<?= count($bannedGroups) > 1 ? 'col-md-6' : 'col-12' ?>">
            <label for="bn-bantime" class="bn-label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>Ban length</label>
            <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? $ban['bantime'] ?? '---', 'bn-bantime') ?>
          </div>
          <?php if (count($bannedGroups) > 1): ?>
          <div class="col-md-6">
            <label for="bn-usergroup" class="bn-label"><i class="fa-solid fa-users-rectangle" aria-hidden="true"></i>Banned group</label>
            <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? $ban['gid'], 'bn-usergroup') ?>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label for="bn-reason" class="bn-label"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i>Reason</label>
            <textarea id="bn-reason" name="reason" class="form-control" rows="4" maxlength="255"
                      data-bn-count="bn-reason-count"><?= htmlspecialchars_uni($this->mybb->input['reason'] ?? $ban['reason']) ?></textarea>
            <div class="bn-counter" id="bn-reason-count" aria-live="polite"></div>
          </div>
        </div>
      </div>

      <div class="bn-actionbar">
        <span class="bn-actionbar-note"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>The new length counts from the original ban date.</span>
        <a href="index.php?act=banning&amp;type=users" class="btn btn-outline-secondary rounded-pill"><i class="fa-solid fa-xmark me-2"></i>Cancel</a>
        <button type="submit" class="btn btn-warning rounded-pill" data-bn-submit><i class="fa-solid fa-floppy-disk me-2"></i>Update ban</button>
      </div>
    </form>
        <?php
        BanView::close();
        stdfoot();
    }

    private function renderMainInterface(array $bannedGroups, array $banTimes, array $errors): void
    {
        $stats = $this->getStats();

        stdhead('Banned Accounts');
        BanView::open('users', (string)$this->mybb->post_code);

        BanView::kpis([
            ['icon' => 'fa-user-lock',    'label' => 'Banned accounts',          'value' => $stats['total']],
            ['icon' => 'fa-infinity',     'label' => 'Permanent',                'value' => $stats['perm'],   'tone' => 'danger'],
            ['icon' => 'fa-hourglass-end','label' => 'Lifting within 24 hours',  'value' => $stats['soon'],   'tone' => 'warning'],
            ['icon' => 'fa-calendar-plus','label' => 'Banned in the last 7 days','value' => $stats['recent'], 'tone' => 'info'],
        ]);

        BanView::errors($errors);
        ?>
    <form action="index.php?act=banning&amp;type=users" method="post" class="bn-panel" data-bn-form>
      <input type="hidden" name="my_post_key" value="<?= htmlspecialchars_uni((string)$this->mybb->post_code) ?>">

      <div class="bn-panel-head">
        <span class="bn-chip-icon" aria-hidden="true"><i class="fa-solid fa-gavel"></i></span>
        <div>
          <h2>Ban a user</h2>
          <p>Moves the account into a banned group and clears its subscriptions.</p>
        </div>
      </div>

      <div class="bn-panel-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="username" class="bn-label"><i class="fa-solid fa-user" aria-hidden="true"></i>Username <span class="bn-req" aria-hidden="true">*</span></label>
            <div class="bn-suggest-wrap">
              <div class="input-group bn-input">
                <span class="input-group-text" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="username" id="username" class="form-control" autocomplete="off" spellcheck="false" required
                       role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="usernameSuggestions"
                       value="<?= htmlspecialchars_uni($this->mybb->get_input('username')) ?>"
                       placeholder="Start typing a username…">
              </div>
              <div id="usernameSuggestions" class="bn-suggest" role="listbox"></div>
            </div>
          </div>
          <div class="col-md-6">
            <label for="bn-bantime" class="bn-label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>Ban length</label>
            <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? '---', 'bn-bantime') ?>
          </div>
          <?php if (count($bannedGroups) > 1): ?>
          <div class="col-12">
            <label for="bn-usergroup" class="bn-label"><i class="fa-solid fa-users-rectangle" aria-hidden="true"></i>Banned group</label>
            <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? array_key_first($bannedGroups), 'bn-usergroup') ?>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label for="bn-reason" class="bn-label"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i>Reason</label>
            <textarea id="bn-reason" name="reason" class="form-control" rows="3" maxlength="255"
                      data-bn-count="bn-reason-count"
                      placeholder="Shown to the user on their ban notice"><?= htmlspecialchars_uni($this->mybb->get_input('reason')) ?></textarea>
            <div class="bn-counter" id="bn-reason-count" aria-live="polite"></div>
          </div>
        </div>
      </div>

      <div class="bn-actionbar">
        <button type="submit" name="ban" value="1" class="btn btn-danger rounded-pill" data-bn-submit><i class="fa-solid fa-ban me-2"></i>Ban user</button>
      </div>
    </form>
        <?php
        $this->outputBannedUsersList();
        BanView::close();
        stdfoot();
    }

    private function outputBannedUsersList(): void
    {
        global $dateformat;

        $username  = $this->mybb->get_input('username');
        $userWhere = '';
        $userWhereParams = [];

        if ($this->mybb->request_method === 'post' && isset($this->mybb->input['search']) && $username) {
            $user = get_user_by_username($username);
            if ($user) { $userWhere = "b.uid = ?"; $userWhereParams = [(int)$user['id']]; }
        }

        $count_sql = $userWhere ? "SELECT COUNT(*) AS cnt FROM banned b WHERE {$userWhere}" : "SELECT COUNT(*) AS cnt FROM banned";
        $count_q   = $this->db->sql_query_prepared($count_sql, $userWhereParams);
        $row      = $count_q ? $this->db->fetch_array($count_q) : null;
        $banCount = (int)($row['cnt'] ?? 0);
        $perPage  = self::PER_PAGE;
        $page     = max(1, $this->mybb->get_input('page', MyBB::INPUT_INT));
        $start    = ($page - 1) * $perPage;
        $postKey  = (string)$this->mybb->post_code;
        ?>
    <section class="bn-panel bn-panel-table">
      <div class="bn-panel-head">
        <span class="bn-chip-icon bn-t-danger" aria-hidden="true"><i class="fa-solid fa-users-slash"></i></span>
        <div><h2>Banned accounts</h2></div>
        <span class="bn-count bn-t-danger" title="Total"><?= number_format($banCount) ?></span>
      </div>

      <?php if ($banCount === 0): ?>
        <?= BanView::emptyState('fa-users-slash', 'No banned accounts', 'Use the form above to ban a user.') ?>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table bn-table mb-0">
          <thead>
            <tr>
              <th scope="col"><i class="fa-solid fa-user" aria-hidden="true"></i>User</th>
              <th scope="col"><i class="fa-solid fa-user-shield" aria-hidden="true"></i>Banned by</th>
              <th scope="col"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>Time left</th>
              <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
          <?php
          $where = $userWhere ? "WHERE {$userWhere}" : '';
          $q = $this->db->sql_query_prepared("
              SELECT b.*, a.username AS adminuser, u.username
              FROM banned b
              LEFT JOIN users u ON b.uid   = u.id
              LEFT JOIN users a ON b.admin = a.id
              {$where}
              ORDER BY b.dateline DESC
              LIMIT ?, ?
          ", [...$userWhereParams, $start, $perPage]);
          while ($q && ($ban = $this->db->fetch_array($q))):
              $ban['uid']      = (int)$ban['uid'];
              $ban['dateline'] = (int)$ban['dateline'];
              $ban['lifted']   = (int)$ban['lifted'];
              $rawName         = (string)($ban['username'] ?? '');
              $name            = htmlspecialchars_uni($rawName !== '' ? $rawName : 'Deleted user');
              $initial         = htmlspecialchars_uni(mb_strtoupper(mb_substr($rawName !== '' ? $rawName : '?', 0, 1)));
              $isPerm          = $ban['lifted'] === 0 || in_array($ban['bantime'], ['perm','---'], true);
              $remaining       = $ban['lifted'] - TIMENOW;

              // Progress of the ban from start to lift
              $span = max(1, $ban['lifted'] - $ban['dateline']);
              $pct  = $isPerm ? 100 : (int)round(min(100, max(0, (TIMENOW - $ban['dateline']) / $span * 100)));
              $tone = match (true) {
                  $isPerm             => 'danger',
                  $remaining <= 0     => 'secondary',
                  $remaining < 3600   => 'success',
                  $remaining < 86400  => 'info',
                  $remaining < 604800 => 'warning',
                  default             => 'danger',
              };

              $base    = "index.php?act=banning&type=users&uid={$ban['uid']}";
              $editUrl = "{$base}&action=edit";
              $liftUrl = "{$base}&action=lift&my_post_key={$postKey}";
              $pruneUrl= "{$base}&action=prune&my_post_key={$postKey}";
          ?>
            <tr>
              <td>
                <div class="bn-user">
                  <span class="bn-avatar" aria-hidden="true"><?= $initial ?></span>
                  <div class="bn-user-text">
                    <div class="bn-user-name"><?= build_profile_link($name, $ban['uid'], '_blank') ?></div>
                    <?php if (!empty($ban['reason'])): ?>
                    <div class="bn-reason"><i class="fa-solid fa-quote-left" aria-hidden="true"></i><span><?= htmlspecialchars_uni($ban['reason']) ?></span></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div class="bn-by"><?= !empty($ban['adminuser']) ? htmlspecialchars_uni($ban['adminuser']) : '<span class="bn-muted">System</span>' ?></div>
                <div class="bn-muted small"><?= my_datee($dateformat, $ban['dateline']) ?></div>
              </td>
              <td>
                <div class="bn-remain bn-t-<?= $tone ?>">
                  <?php if ($isPerm): ?>
                    <div class="bn-remain-top"><strong><i class="fa-solid fa-infinity me-1" aria-hidden="true"></i>Permanent</strong><span>Never lifts</span></div>
                  <?php elseif ($remaining <= 0): ?>
                    <div class="bn-remain-top"><strong><i class="fa-solid fa-hourglass-end me-1" aria-hidden="true"></i>Expired</strong><span>Awaiting lift</span></div>
                  <?php else: ?>
                    <div class="bn-remain-top"><strong><?= mkprettytime($remaining) ?></strong><span><?= my_datee($dateformat, $ban['lifted']) ?></span></div>
                  <?php endif; ?>
                  <div class="bn-bar<?= $isPerm ? ' is-perm' : '' ?>" role="progressbar" aria-label="Ban served" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>">
                    <span style="width:<?= $pct ?>%"></span>
                  </div>
                </div>
              </td>
              <td class="text-end">
                <div class="bn-actions">
                  <a href="<?= htmlspecialchars_uni($editUrl) ?>" class="bn-icon-btn bn-t-primary" title="Edit ban" aria-label="Edit ban for <?= $name ?>">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </a>
                  <a href="<?= htmlspecialchars_uni($liftUrl) ?>" class="bn-icon-btn bn-t-success" title="Lift ban" aria-label="Lift ban for <?= $name ?>"
                     data-bn-confirm data-tone="success" data-ok="Lift ban"
                     data-title="Lift ban for <?= $name ?>?"
                     data-text="The user will be moved back to their previous group.">
                    <i class="fa-solid fa-lock-open"></i>
                  </a>
                  <a href="<?= htmlspecialchars_uni($pruneUrl) ?>" class="bn-icon-btn bn-t-danger" title="Prune content" aria-label="Prune content of <?= $name ?>"
                     data-bn-confirm data-tone="danger" data-ok="Prune content"
                     data-title="Prune all content by <?= $name ?>?"
                     data-text="Every thread and post by this user will be deleted. This cannot be undone.">
                    <i class="fa-solid fa-broom"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
        <?php
        if ($banCount > $perPage) {
            echo '<nav class="bn-pager" aria-label="Pages">'
               . multipage($banCount, $perPage, $page, 'index.php?act=banning&type=users&page={page}')
               . '</nav>';
        }
    }

    private function showConfirmation(
        array  $user,
        string $actionUrl,
        string $cancelUrl,
        string $title,
        string $message,
        string $tone   = 'danger',
        string $icon   = 'fa-triangle-exclamation',
        string $okLabel = 'Yes, continue'
    ): void {
        stdhead('Confirm Action');
        BanView::confirmPage(
            (string)$this->mybb->post_code, $tone, $icon, $title, $message,
            [
                'User' => htmlspecialchars_uni((string)$user['username'])
                        . ' <span class="bn-muted">UID ' . (int)$user['id'] . '</span>',
            ],
            $actionUrl, $cancelUrl, $okLabel, $icon
        );
        stdfoot();
        exit;
    }

    private function prepareBanTimes(array $banTimes): array
    {
        global $timeformat;
        $list = [];
        foreach ($banTimes as $time => $period) {
            $list[$time] = $time !== '---'
                ? "{$period} (" . my_datee("D, jS M Y @ {$timeformat}", ban_date2timestamp($time)) . ')'
                : $period;
        }
        return $list;
    }

    private function selectBox(string $name, array $options, mixed $selected, string $id = '', string $class = 'form-select'): string
    {
        $idAttr = $id !== '' ? " id=\"{$id}\"" : '';
        $html   = "<select name=\"{$name}\"{$idAttr} class=\"{$class}\">";
        foreach ($options as $val => $label) {
            $sel   = $val == $selected ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars_uni((string)$val) . '"' . $sel . '>'
                   . htmlspecialchars_uni((string)$label) . '</option>';
        }
        return $html . '</select>';
    }
}