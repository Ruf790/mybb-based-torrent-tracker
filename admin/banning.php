<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct access not allowed.</div>');
}

require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';

global $lang; // no-op at file scope, needed if admin/index.php includes us from a function

$lang->load('banning');

// DAY_IN_SECONDS is not defined in the admin panel context
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);

// ── ags_fmt: {1}, {2}… placeholders ($lang->load() turns them into %1$s) ─────
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

// ── JS strings: js_* keys without the prefix ──────────────────────────────────
function ban_js_lang(): array
{
    global $lang;
    $out = [];
    foreach ($lang->banning as $key => $val) {
        if (str_starts_with((string)$key, 'js_')) {
            $out[substr((string)$key, 3)] = (string)$val;
        }
    }
    return $out;
}

// ── fetch_ban_times ───────────────────────────────────────────────────────────
function fetch_ban_times(): array
{
    global $plugins, $lang;
    $l = $lang->banning;

    $ban_times = [
        '1-0-0'  => $l['opt_bt_1d'],  '2-0-0'  => $l['opt_bt_2d'],  '3-0-0'  => $l['opt_bt_3d'],
        '4-0-0'  => $l['opt_bt_4d'],  '5-0-0'  => $l['opt_bt_5d'],  '6-0-0'  => $l['opt_bt_6d'],
        '7-0-0'  => $l['opt_bt_1w'],  '14-0-0' => $l['opt_bt_2w'],  '21-0-0' => $l['opt_bt_3w'],
        '0-1-0'  => $l['opt_bt_1m'],  '0-2-0'  => $l['opt_bt_2m'],  '0-3-0'  => $l['opt_bt_3m'],
        '0-4-0'  => $l['opt_bt_4m'],  '0-5-0'  => $l['opt_bt_5m'],  '0-6-0'  => $l['opt_bt_6m'],
        '0-0-1'  => $l['opt_bt_1y'],  '0-0-2'  => $l['opt_bt_2y'],
    ];

    $ban_times          = $plugins->run_hooks('functions_fetch_ban_times', $ban_times);
    $ban_times['---']   = $l['opt_bt_perm'];
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

// ── CSRF: silent check + flash/redirect instead of a bare 403 page ─────────────
function ban_require_post_key(object $mybb, string $back): void
{
    global $lang;
    if (!verify_post_check((string)$mybb->get_input('my_post_key'), true)) {
        flash_message($lang->banning['flash_csrf'], 'error');
        admin_redirect($back);
        exit;
    }
}

// ── Does an IP ban filter (wildcard or CIDR) match this address? ─────────────
function ban_ip_matches(string $filter, string $ip): bool
{
    if ($ip === '') return false;

    if (str_contains($filter, '/')) {
        [$net, $bits] = explode('/', $filter, 2);
        $a = @inet_pton($ip);
        $b = @inet_pton($net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;

        $bits  = (int)$bits;
        $bytes = intdiv($bits, 8);
        $rem   = $bits % 8;
        if ($bytes > 0 && strncmp($a, $b, $bytes) !== 0) return false;
        if ($rem === 0) return true;

        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        return ($a[$bytes] & $mask) === ($b[$bytes] & $mask);
    }

    $re = '~^' . str_replace('\\*', '.*', preg_quote($filter, '~')) . '$~';
    return (bool)preg_match($re, $ip);
}

// ── Nav tabs ──────────────────────────────────────────────────────────────────
function ban_nav(): array
{
    global $lang;
    $l = $lang->banning;
    return [
        'ips' => [
            'title'       => $l['nav_ips'],
            'link'        => 'index.php?act=banning',
            'description' => $l['nav_ips_desc'],
            'icon'        => 'fa-solid fa-network-wired',
        ],
        'users' => [
            'title'       => $l['nav_users'],
            'link'        => 'index.php?act=banning&type=users',
            'description' => $l['nav_users_desc'],
            'icon'        => 'fa-solid fa-user-lock',
        ],
        'usernames' => [
            'title'       => $l['nav_usernames'],
            'link'        => 'index.php?act=banning&type=usernames',
            'description' => $l['nav_usernames_desc'],
            'icon'        => 'fa-solid fa-user-slash',
        ],
        'emails' => [
            'title'       => $l['nav_emails'],
            'link'        => 'index.php?act=banning&type=emails',
            'description' => $l['nav_emails_desc'],
            'icon'        => 'fa-solid fa-envelope',
        ],
    ];
}

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
    public const ASSET_VER = 2;

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
<script>const AGS_LANG = <?= json_encode(ban_js_lang(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/admin/scripts/banning.js?ver=<?= $v ?>" defer></script>
        <?php
    }

    /** Opens the page wrapper: assets, header card, nav tabs. Close with BanView::close(). */
    public static function open(string $section, string $postKey): void
    {
        $navs = ban_nav();
        $nav  = $navs[$section] ?? $navs['ips'];
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
        output_nav_tabs($navs, $section);
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
        global $lang;
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
        <i class="fa-solid fa-xmark me-2"></i><?= htmlspecialchars_uni($lang->banning['btn_cancel']) ?>
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

    private static function typeConfigs(): array
    {
        global $lang;
        $l = $lang->banning;
        return [
            1 => ['title' => $l['sec_list_ips'],       'redirect' => '',          'icon' => 'fa-network-wired',              'color' => 'danger'],
            2 => ['title' => $l['sec_list_usernames'], 'redirect' => 'usernames', 'icon' => 'fa-user-slash',                 'color' => 'warning'],
            3 => ['title' => $l['sec_list_emails'],    'redirect' => 'emails',    'icon' => 'fa-envelope-circle-exclamation','color' => 'info'],
        ];
    }

    private static function formConfigs(): array
    {
        global $lang;
        $l = $lang->banning;
        return [
            1 => ['title' => $l['sec_form_ips'],       'label' => $l['lbl_ip'],       'description' => $l['hint_ip'],       'button' => $l['btn_ban_ip'],            'icon' => 'fa-ban',       'input_icon' => 'fa-location-crosshairs', 'placeholder' => $l['ph_ip']],
            2 => ['title' => $l['sec_form_usernames'], 'label' => $l['lbl_username'], 'description' => $l['hint_username'], 'button' => $l['btn_disallow_username'], 'icon' => 'fa-user-lock', 'input_icon' => 'fa-user',                'placeholder' => $l['ph_username_pattern']],
            3 => ['title' => $l['sec_form_emails'],    'label' => $l['lbl_email'],    'description' => $l['hint_email'],    'button' => $l['btn_disallow_email'],    'icon' => 'fa-envelope',  'input_icon' => 'fa-at',                  'placeholder' => $l['ph_email_pattern']],
        ];
    }

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
        global $lang;
        $this->plugins->run_hooks('admin_config_banning_add');

        if ($this->mybb->request_method !== 'post') {
            flash_message($lang->banning['flash_bad_method'], 'error');
            admin_redirect('index.php?act=banning');
        }

        ban_require_post_key($this->mybb, 'index.php?act=banning&type=' . $this->getTypeName($this->mybb->get_input('type', MyBB::INPUT_INT)));

        $filter = trim($this->mybb->get_input('filter'));
        $type   = $this->mybb->get_input('type', MyBB::INPUT_INT);
        $errors = $this->validateAdd($filter, $type);

        if (empty($errors)) {
            $this->addBanFilter($filter, $type);
            $cfg = self::typeConfigs()[$type];
            flash_message($lang->banning['flash_ban_added'], 'success');
            admin_redirect('index.php?act=banning' . ($cfg['redirect'] ? '&type=' . $cfg['redirect'] : ''));
        }

        // Errors are rendered inside the page (after stdhead), with the typed value kept
        $this->displayInterface($errors, $filter, $type);
    }

    private function handleDelete(): void
    {
        global $lang;
        $fid    = $this->mybb->get_input('fid', MyBB::INPUT_INT);
        $filter = $this->getFilterById($fid);

        if (!$filter) {
            flash_message($lang->banning['flash_filter_missing'], 'error');
            admin_redirect('index.php?act=banning');
        }

        $this->plugins->run_hooks('admin_config_banning_delete');

        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning&type=' . $this->getTypeName((int)$filter['type']));
        }

        if ($this->mybb->request_method === 'post') {
            ban_require_post_key($this->mybb, 'index.php?act=banning&type=' . $this->getTypeName((int)$filter['type']));

            $this->db->sql_query_prepared("DELETE FROM banfilters WHERE fid = ?", [$filter['fid']]);
            $this->plugins->run_hooks('admin_config_banning_delete_commit');
            $this->updateCaches((int)$filter['type']);
            log_admin_action((int)$filter['fid'], $filter['filter'], (int)$filter['type']);
            write_log("Removed ban filter '{$filter['filter']}' (type {$filter['type']}) by " . $GLOBALS['CURUSER']['username']);
            flash_message($lang->banning['flash_ban_deleted'], 'success');
            admin_redirect('index.php?act=banning&type=' . $this->getTypeName((int)$filter['type']));
        } else {
            $this->showDeleteConfirmation($filter);
        }
    }

    private function displayInterface(array $errors = [], string $value = '', int $type = 0): void
    {
        $this->plugins->run_hooks('admin_config_banning_start');
        $typeConfig = $type > 0 && isset(self::typeConfigs()[$type])
            ? self::typeConfigs()[$type] + ['type' => $type, 'name' => $this->getTypeName($type)]
            : $this->getCurrentTypeConfig();
        $this->renderInterface($typeConfig, $errors, $value);
    }

    private function validateAdd(string $filter, int $type): array
    {
        global $lang;
        $l      = $lang->banning;
        $errors = [];
        if (!isset(self::typeConfigs()[$type]))                 return [$l['err_bad_type']];
        if (empty(trim($filter)))                              $errors[] = $l['err_empty_value'];
        if ($this->isDuplicateFilter($filter, $type))          $errors[] = $l['err_duplicate'];
        if ($type === 1 && !$this->isValidIPFilter($filter))   $errors[] = $l['err_bad_ip'];
        if ($type === 3 && !$this->isValidEmailFilter($filter)) $errors[] = $l['err_bad_email'];

        // "*", "*.*.*.*", "*@*" and the like would block everyone
        if ($filter !== '' && preg_match('~^[*.:@]+$~', $filter)) {
            $errors[] = $l['err_matches_all'];
        }

        // Never let staff lock themselves out
        if ($type === 1 && empty($errors)) {
            $myIp = (string)get_ip();
            if (ban_ip_matches($filter, $myIp)) {
                $errors[] = ags_fmt($l['err_own_ip'], $myIp);
            }
        }
        return $errors;
    }

    private function isValidIPFilter(string $f): bool
    {
        if (str_contains($f, '/')) {
            $p = explode('/', $f);
            if (count($p) !== 2 || !ctype_digit($p[1])) return false;
            $bits = (int)$p[1];
            if (filter_var($p[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $bits >= 8 && $bits <= 32;
            if (filter_var($p[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return $bits >= 16 && $bits <= 128;
            return false;
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
        write_log("Added ban filter '{$filter}' (type {$type}) by " . $GLOBALS['CURUSER']['username']);
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
            'emails'    => self::typeConfigs()[3] + ['type' => 3, 'name' => 'emails'],
            'usernames' => self::typeConfigs()[2] + ['type' => 2, 'name' => 'usernames'],
            default     => self::typeConfigs()[1] + ['type' => 1, 'name' => 'ips'],
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
        global $lang;
        $l     = $lang->banning;
        $stats = $this->getStats($tc['type']);

        stdhead($tc['title']);
        BanView::open($tc['name'], (string)$this->mybb->post_code);

        BanView::kpis([
            ['icon' => $tc['icon'],            'label' => $tc['type'] === 1 ? $l['kpi_banned_addresses'] : $l['kpi_blocked_patterns'], 'value' => $stats['total']],
            ['icon' => 'fa-calendar-plus',     'label' => $l['kpi_added_7d'], 'value' => $stats['recent'],    'tone' => 'success'],
            ['icon' => 'fa-bolt',              'label' => $l['kpi_triggered'], 'value' => $stats['triggered'], 'tone' => 'warning'],
            ['icon' => 'fa-clock-rotate-left', 'label' => $tc['type'] === 1 ? $l['kpi_last_access'] : $l['kpi_last_attempt'],
             'value' => $stats['lastuse'] > 0 ? my_datee('relative', $stats['lastuse']) : htmlspecialchars_uni($l['lbl_never']), 'raw' => true, 'tone' => 'secondary'],
        ]);

        BanView::errors($errors);
        $this->outputAddForm($tc, $value);
        $this->outputBanList($tc, $stats['total']);

        BanView::close();
        stdfoot();
    }

    private function outputAddForm(array $tc, string $value = ''): void
    {
        global $lang;
        $cfg   = self::formConfigs()[$tc['type']];
        $color = $tc['color'];
        ?>
    <form action="index.php?act=banning&amp;action=add" method="post" class="bn-panel bn-t-<?= $color ?>" data-bn-form>
      <input type="hidden" name="my_post_key" value="<?= htmlspecialchars_uni((string)$this->mybb->post_code) ?>">
      <input type="hidden" name="type" value="<?= (int)$tc['type'] ?>">

      <div class="bn-panel-head">
        <span class="bn-chip-icon" aria-hidden="true"><i class="fa-solid <?= $cfg['icon'] ?>"></i></span>
        <div>
          <h2><?= htmlspecialchars_uni($cfg['title']) ?></h2>
          <p><?= htmlspecialchars_uni($lang->banning['sec_form_hint']) ?></p>
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
        global $lang;
        $l     = $lang->banning;
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
            2       => [$l['col_username'], $l['col_disallowed'], $l['col_last_attempt'], 'fa-user'],
            3       => [$l['col_email'],    $l['col_disallowed'], $l['col_last_attempt'], 'fa-at'],
            default => [$l['col_ip'],       $l['col_banned'],     $l['col_last_access'],  'fa-location-crosshairs'],
        };

        $deleteText = match ($tc['type']) {
            2       => $l['cf_del_username'],
            3       => $l['cf_del_email'],
            default => $l['cf_del_ip'],
        };

        $emptyText = match ($tc['type']) {
            2       => $l['empty_usernames'],
            3       => $l['empty_emails'],
            default => $l['empty_ips'],
        };

        ?>
    <section class="bn-panel bn-panel-table">
      <div class="bn-panel-head">
        <span class="bn-chip-icon" aria-hidden="true"><i class="fa-solid <?= $tc['icon'] ?>"></i></span>
        <div><h2><?= htmlspecialchars_uni($tc['title']) ?></h2></div>
        <span class="bn-count" title="<?= htmlspecialchars_uni($l['lbl_total']) ?>"><?= number_format($total) ?></span>
      </div>

      <?php if (empty($filters)): ?>
        <?= BanView::emptyState($tc['icon'], $l['empty_filters_title'], $emptyText) ?>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table bn-table mb-0">
          <thead>
            <tr>
              <th scope="col"><i class="fa-solid <?= $valueIcon ?>" aria-hidden="true"></i><?= htmlspecialchars_uni($colValue) ?></th>
              <th scope="col"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i><?= htmlspecialchars_uni($colDate) ?></th>
              <th scope="col"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><?= htmlspecialchars_uni($colLast) ?></th>
              <th scope="col" class="text-end"><span class="visually-hidden"><?= htmlspecialchars_uni($l['col_actions']) ?></span></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($filters as $f):
              $raw     = (string)$f['filter'];
              $date    = $f['dateline'] > 0 ? my_datee('relative', $f['dateline']) : '—';
              $isNew   = (TIMENOW - $f['dateline']) < DAY_IN_SECONDS;
              $delUrl  = "index.php?act=banning&action=delete&fid={$f['fid']}";
          ?>
            <tr>
              <td>
                <?= BanView::pattern($raw, $f['type']) ?>
                <?php if ($isNew): ?><span class="bn-tag bn-t-success ms-2"><i class="fa-solid fa-star" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_new']) ?></span><?php endif; ?>
              </td>
              <td class="bn-muted"><?= $date ?></td>
              <td class="bn-muted">
                <?php if ($f['lastuse'] > 0): ?>
                  <span class="bn-hit"><i class="fa-solid fa-bolt" aria-hidden="true"></i><?= my_datee('relative', $f['lastuse']) ?></span>
                <?php else: ?>
                  <?= htmlspecialchars_uni($l['lbl_never']) ?>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a href="<?= htmlspecialchars_uni($delUrl) ?>" class="bn-icon-btn bn-t-danger"
                   title="<?= htmlspecialchars_uni($l['btn_delete']) ?>" aria-label="<?= htmlspecialchars_uni(ags_fmt($l['aria_delete'], $raw)) ?>"
                   data-bn-confirm data-tone="danger" data-ok="<?= htmlspecialchars_uni($l['btn_delete']) ?>"
                   data-title="<?= htmlspecialchars_uni($l['cf_delete_title']) ?>"
                   data-text="<?= htmlspecialchars_uni(ags_fmt($deleteText, $raw)) ?>">
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
            echo '<nav class="bn-pager" aria-label="' . htmlspecialchars_uni($l['lbl_pages']) . '">'
               . multipage($total, self::PER_PAGE, $page, "index.php?act=banning&type={$tc['name']}&page={page}")
               . '</nav>';
        }
    }

    private function showDeleteConfirmation(array $filter): void
    {
        global $lang;
        $l       = $lang->banning;
        $tc      = self::typeConfigs()[$filter['type']] ?? self::typeConfigs()[1];
        $postKey = (string)$this->mybb->post_code;
        $delUrl  = "index.php?act=banning&action=delete&fid={$filter['fid']}";
        $canUrl  = 'index.php?act=banning&type=' . $this->getTypeName((int)$filter['type']);

        stdhead($l['title_confirm_delete']);
        BanView::confirmPage(
            $postKey, 'danger', 'fa-trash-can',
            $l['cf_delete_title'],
            $l['cf_delete_text'],
            [
                $l['lbl_filter'] => BanView::pattern((string)$filter['filter'], (int)$filter['type']),
                $l['lbl_list']   => '<i class="fa-solid ' . $tc['icon'] . ' me-1"></i>' . htmlspecialchars_uni($tc['title']),
            ],
            $delUrl, $canUrl, $l['btn_delete'], 'fa-trash-can'
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
        global $lang;
        $l = $lang->banning;
        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning&type=users');
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            flash_message($l['flash_invalid_ban'], 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        if (is_super_admin((int)$user['id']) && !$this->canModifySuperAdmin()) {
            flash_message($l['flash_super_admin'], 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        $this->plugins->run_hooks('admin_user_banning_prune');

        if ($this->mybb->request_method === 'post') {
            ban_require_post_key($this->mybb, 'index.php?act=banning&type=users');

            require_once INC_PATH . '/class_moderation.php';
            $mod = new Moderation();
            $q   = $this->db->sql_query_prepared("SELECT tid FROM threads WHERE uid = ?", [$user['id']]);
            while ($q && ($r = $this->db->fetch_array($q))) $mod->delete_thread($r['tid']);
            $q = $this->db->sql_query_prepared("SELECT pid FROM posts WHERE uid = ?", [$user['id']]);
            while ($q && ($r = $this->db->fetch_array($q))) $mod->delete_post($r['pid']);
            $this->plugins->run_hooks('admin_user_banning_prune_commit');

            log_admin_action((int)$user['id'], $user['username']);
            write_log("Pruned all content of {$user['username']} (UID {$user['id']}) by " . $GLOBALS['CURUSER']['username']);
            flash_message($l['flash_pruned'], 'success');
            admin_redirect('index.php?act=banning&type=users');
        } else {
            $this->showConfirmation($user,
                "index.php?act=banning&type=users&action=prune&uid={$user['id']}",
                'index.php?act=banning&type=users',
                $l['cf_prune_title'],
                $l['cf_prune_text'],
                'danger', 'fa-broom', $l['btn_prune']
            );
        }
    }

    private function handleLift(): void
    {
        global $lang;
        $l = $lang->banning;
        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning&type=users');
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            flash_message($l['flash_invalid_ban'], 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        if (is_super_admin((int)$ban['uid']) && !$this->canModifySuperAdmin()) {
            flash_message($l['flash_super_admin'], 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        $this->plugins->run_hooks('admin_user_banning_lift');

        if ($this->mybb->request_method === 'post') {
            ban_require_post_key($this->mybb, 'index.php?act=banning&type=users');

            $this->db->sql_query_prepared("DELETE FROM banned WHERE uid = ?", [$ban['uid']]);
            $this->db->sql_query_prepared(
                "UPDATE users SET usergroup = ?, additionalgroups = ?, displaygroup = ? WHERE id = ?",
                [$ban['oldgroup'], $ban['oldadditionalgroups'], $ban['olddisplaygroup'], $ban['uid']]
            );
            $this->plugins->run_hooks('admin_user_banning_lift_commit');
            log_admin_action($ban['uid'], $user['username']);
            write_log("Lifted ban of {$user['username']} (UID {$ban['uid']}) by " . $GLOBALS['CURUSER']['username']);
            flash_message($l['flash_lifted'], 'success');
            admin_redirect('index.php?act=banning&type=users');
        } else {
            $this->showConfirmation($user,
                "index.php?act=banning&type=users&action=lift&uid={$ban['uid']}",
                'index.php?act=banning&type=users',
                $l['cf_lift_title'],
                $l['cf_lift_text'],
                'success', 'fa-lock-open', $l['btn_lift_ban']
            );
        }
    }

    private function handleEdit(): void
    {
        global $lang;
        $l = $lang->banning;
        $uid  = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban  = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            flash_message($l['flash_invalid_ban'], 'error');
            admin_redirect('index.php?act=banning&type=users');
        }

        $bannedGroups = $this->getBannedGroups();
        $banTimes     = fetch_ban_times();
        $errors       = [];

        $this->plugins->run_hooks('admin_user_banning_edit');

        if ($this->mybb->request_method === 'post') {
            ban_require_post_key($this->mybb, 'index.php?act=banning&type=users');

            if (empty($ban['uid'])) {
                $errors[] = $l['err_invalid_user'];
            } elseif (is_super_admin($ban['uid']) && !$this->canModifySuperAdmin()) {
                $errors[] = $l['err_no_perm_edit'];
            }

            $bantime = $this->inputBanTime($banTimes);
            $gid     = $this->inputBannedGroup($bannedGroups);
            if ($bantime === null) $errors[] = $l['err_bad_length'];
            if ($gid === null)     $errors[] = $l['err_bad_group'];

            if (empty($errors)) {
                // Length counts from the ORIGINAL ban date, so dateline stays untouched
                $lifted  = $bantime === '---' ? 0 : ban_date2timestamp($bantime, $ban['dateline']);
                $reason  = my_substr($this->mybb->input['reason'] ?? '', 0, 255);

                $this->db->sql_query_prepared(
                    "UPDATE banned SET gid = ?, bantime = ?, lifted = ?, reason = ? WHERE uid = ?",
                    [$gid, $bantime, $lifted, $reason, $ban['uid']]
                );

                $this->db->sql_query_prepared(
                    "UPDATE users SET usergroup = ?, displaygroup = 0, additionalgroups = '' WHERE id = ?",
                    [$gid, $ban['uid']]
                );

                $this->plugins->run_hooks('admin_user_banning_edit_commit');
                log_admin_action($ban['uid'], $user['username']);
                write_log("Edited ban of {$user['username']} (UID {$ban['uid']}): {$bantime} by " . $GLOBALS['CURUSER']['username']);
                flash_message($l['flash_ban_updated'], 'success');
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
            ban_require_post_key($this->mybb, 'index.php?act=banning&type=users');

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

    /** Ban length from input, only if it is one of the offered options. */
    private function inputBanTime(array $banTimes): ?string
    {
        $t = (string)($this->mybb->input['bantime'] ?? '---');
        return isset($banTimes[$t]) ? $t : null;
    }

    /** Target group from input, only if it really is a banned group. */
    private function inputBannedGroup(array $bannedGroups): ?int
    {
        if (empty($bannedGroups)) return null;
        if (count($bannedGroups) === 1) return (int)array_key_first($bannedGroups);
        $g = $this->mybb->get_input('usergroup', MyBB::INPUT_INT);
        return isset($bannedGroups[$g]) ? $g : null;
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
        global $lang;
        $l = $lang->banning;

        if (isset($this->mybb->input['search'])) return [];

        $user = get_user_by_username($this->mybb->get_input('username'), [
            'fields' => ['username','usergroup','additionalgroups','displaygroup'],
        ]);

        if (!$user) return [$l['err_user_not_found']];

        $uid    = (int)$user['id'];
        $errors = [];

        if (is_super_admin($uid) && !$this->canModifySuperAdmin()) $errors[] = $l['err_no_perm_ban'];
        elseif ($this->isUserAlreadyBanned($uid))                   $errors[] = $l['err_already_banned'];
        elseif ($uid === $this->getCurrentUserId())                  $errors[] = $l['err_ban_self'];

        $bantime = $this->inputBanTime(fetch_ban_times());
        $gid     = $this->inputBannedGroup($bannedGroups);
        if ($bantime === null) $errors[] = $l['err_bad_length'];
        if ($gid === null)     $errors[] = $l['err_bad_group'];

        if (empty($errors)) {
            $lifted  = $bantime === '---' ? 0 : ban_date2timestamp($bantime);
            $reason  = my_substr($this->mybb->input['reason'] ?? '', 0, 255);

            $this->db->sql_query_prepared(
                "INSERT INTO banned (`uid`,`gid`,`oldgroup`,`oldadditionalgroups`,`olddisplaygroup`,`admin`,`dateline`,`bantime`,`lifted`,`reason`) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $uid,
                    $gid,
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
                [$gid, $uid]
            );
            $this->db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE uid = ?", [$uid]);
            $this->db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE uid = ?", [$uid]);

            $this->plugins->run_hooks('admin_user_banning_start_commit');
            log_admin_action($uid, $user['username'], $lifted);
            write_log("Banned {$user['username']} (UID {$uid}) for {$bantime} by " . $GLOBALS['CURUSER']['username']);
            flash_message($l['flash_user_banned'], 'success');
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
        global $dateformat, $lang;
        $l       = $lang->banning;

        $isPerm  = $ban['lifted'] === 0 || in_array($ban['bantime'], ['perm', '---'], true);
        $name    = htmlspecialchars_uni((string)$user['username']);
        $length  = $banTimes[$ban['bantime']] ?? ($isPerm ? $l['lbl_permanent'] : (string)$ban['bantime']);

        stdhead($l['title_edit_ban']);
        BanView::open('users', (string)$this->mybb->post_code);
        BanView::errors($errors);
        ?>
    <form action="index.php?act=banning&amp;type=users&amp;action=edit&amp;uid=<?= $ban['uid'] ?>" method="post" class="bn-panel bn-t-warning" data-bn-form>
      <input type="hidden" name="my_post_key" value="<?= htmlspecialchars_uni((string)$this->mybb->post_code) ?>">

      <div class="bn-panel-head">
        <span class="bn-avatar" aria-hidden="true"><?= htmlspecialchars_uni(mb_strtoupper(mb_substr((string)$user['username'], 0, 1))) ?></span>
        <div>
          <h2><?= htmlspecialchars_uni(ags_fmt($l['sec_edit_ban'], (string)$user['username'])) ?></h2>
          <p><?= htmlspecialchars_uni(ags_fmt($l['lbl_uid'], (int)$user['id'])) ?></p>
        </div>
      </div>

      <div class="bn-panel-body">
        <div class="bn-facts">
          <span class="bn-tag bn-t-secondary"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i><?= ags_fmt(htmlspecialchars_uni($l['lbl_banned_on']), my_datee($dateformat, $ban['dateline'])) ?></span>
          <span class="bn-tag bn-t-<?= $isPerm ? 'danger' : 'info' ?>">
            <i class="fa-solid <?= $isPerm ? 'fa-infinity' : 'fa-hourglass-half' ?>" aria-hidden="true"></i>
            <?= $isPerm ? htmlspecialchars_uni($l['lbl_permanent']) : ags_fmt(htmlspecialchars_uni($l['lbl_lifts_on']), my_datee($dateformat, $ban['lifted'])) ?>
          </span>
          <span class="bn-tag bn-t-secondary"><i class="fa-solid fa-ruler-horizontal" aria-hidden="true"></i><?= htmlspecialchars_uni($length) ?></span>
        </div>

        <div class="row g-3">
          <div class="<?= count($bannedGroups) > 1 ? 'col-md-6' : 'col-12' ?>">
            <label for="bn-bantime" class="bn-label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_ban_length']) ?></label>
            <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? ($isPerm ? '---' : $ban['bantime']), 'bn-bantime') ?>
          </div>
          <?php if (count($bannedGroups) > 1): ?>
          <div class="col-md-6">
            <label for="bn-usergroup" class="bn-label"><i class="fa-solid fa-users-rectangle" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_banned_group']) ?></label>
            <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? $ban['gid'], 'bn-usergroup') ?>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label for="bn-reason" class="bn-label"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_reason']) ?></label>
            <textarea id="bn-reason" name="reason" class="form-control" rows="4" maxlength="255"
                      data-bn-count="bn-reason-count"><?= htmlspecialchars_uni($this->mybb->input['reason'] ?? $ban['reason']) ?></textarea>
            <div class="bn-counter" id="bn-reason-count" aria-live="polite"></div>
          </div>
        </div>
      </div>

      <div class="bn-actionbar">
        <span class="bn-actionbar-note"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?= htmlspecialchars_uni($l['hint_edit_length']) ?></span>
        <a href="index.php?act=banning&amp;type=users" class="btn btn-outline-secondary rounded-pill"><i class="fa-solid fa-xmark me-2"></i><?= htmlspecialchars_uni($l['btn_cancel']) ?></a>
        <button type="submit" class="btn btn-warning rounded-pill" data-bn-submit><i class="fa-solid fa-floppy-disk me-2"></i><?= htmlspecialchars_uni($l['btn_update_ban']) ?></button>
      </div>
    </form>
        <?php
        BanView::close();
        stdfoot();
    }

    private function renderMainInterface(array $bannedGroups, array $banTimes, array $errors): void
    {
        global $lang;
        $l     = $lang->banning;
        $stats = $this->getStats();

        stdhead($l['nav_users']);
        BanView::open('users', (string)$this->mybb->post_code);

        BanView::kpis([
            ['icon' => 'fa-user-lock',    'label' => $l['kpi_banned_accounts'], 'value' => $stats['total']],
            ['icon' => 'fa-infinity',     'label' => $l['kpi_permanent'],       'value' => $stats['perm'],   'tone' => 'danger'],
            ['icon' => 'fa-hourglass-end','label' => $l['kpi_lifting_24h'],     'value' => $stats['soon'],   'tone' => 'warning'],
            ['icon' => 'fa-calendar-plus','label' => $l['kpi_banned_7d'],       'value' => $stats['recent'], 'tone' => 'info'],
        ]);

        BanView::errors($errors);
        ?>
    <form action="index.php?act=banning&amp;type=users" method="post" class="bn-panel" data-bn-form>
      <input type="hidden" name="my_post_key" value="<?= htmlspecialchars_uni((string)$this->mybb->post_code) ?>">

      <div class="bn-panel-head">
        <span class="bn-chip-icon" aria-hidden="true"><i class="fa-solid fa-gavel"></i></span>
        <div>
          <h2><?= htmlspecialchars_uni($l['sec_ban_user']) ?></h2>
          <p><?= htmlspecialchars_uni($l['sec_ban_user_hint']) ?></p>
        </div>
      </div>

      <div class="bn-panel-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="username" class="bn-label"><i class="fa-solid fa-user" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_username']) ?> <span class="bn-req" aria-hidden="true">*</span></label>
            <div class="bn-suggest-wrap">
              <div class="input-group bn-input">
                <span class="input-group-text" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="username" id="username" class="form-control" autocomplete="off" spellcheck="false" required
                       role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="usernameSuggestions"
                       value="<?= htmlspecialchars_uni($this->mybb->get_input('username')) ?>"
                       placeholder="<?= htmlspecialchars_uni($l['ph_username']) ?>">
              </div>
              <div id="usernameSuggestions" class="bn-suggest" role="listbox"></div>
            </div>
          </div>
          <div class="col-md-6">
            <label for="bn-bantime" class="bn-label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_ban_length']) ?></label>
            <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? '---', 'bn-bantime') ?>
          </div>
          <?php if (count($bannedGroups) > 1): ?>
          <div class="col-12">
            <label for="bn-usergroup" class="bn-label"><i class="fa-solid fa-users-rectangle" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_banned_group']) ?></label>
            <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? array_key_first($bannedGroups), 'bn-usergroup') ?>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <label for="bn-reason" class="bn-label"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_reason']) ?></label>
            <textarea id="bn-reason" name="reason" class="form-control" rows="3" maxlength="255"
                      data-bn-count="bn-reason-count"
                      placeholder="<?= htmlspecialchars_uni($l['ph_reason']) ?>"><?= htmlspecialchars_uni($this->mybb->get_input('reason')) ?></textarea>
            <div class="bn-counter" id="bn-reason-count" aria-live="polite"></div>
          </div>
        </div>
      </div>

      <div class="bn-actionbar">
        <button type="submit" name="ban" value="1" class="btn btn-danger rounded-pill" data-bn-submit><i class="fa-solid fa-ban me-2"></i><?= htmlspecialchars_uni($l['btn_ban_user']) ?></button>
      </div>
    </form>
        <?php
        $this->outputBannedUsersList();
        BanView::close();
        stdfoot();
    }

    private function outputBannedUsersList(): void
    {
        global $dateformat, $lang;
        $l         = $lang->banning;

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
        ?>
    <section class="bn-panel bn-panel-table">
      <div class="bn-panel-head">
        <span class="bn-chip-icon bn-t-danger" aria-hidden="true"><i class="fa-solid fa-users-slash"></i></span>
        <div><h2><?= htmlspecialchars_uni($l['sec_banned_accounts']) ?></h2></div>
        <span class="bn-count bn-t-danger" title="<?= htmlspecialchars_uni($l['lbl_total']) ?>"><?= number_format($banCount) ?></span>
      </div>

      <?php if ($banCount === 0): ?>
        <?= BanView::emptyState('fa-users-slash', $l['empty_accounts_title'], $l['empty_accounts']) ?>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table bn-table mb-0">
          <thead>
            <tr>
              <th scope="col"><i class="fa-solid fa-user" aria-hidden="true"></i><?= htmlspecialchars_uni($l['col_user']) ?></th>
              <th scope="col"><i class="fa-solid fa-user-shield" aria-hidden="true"></i><?= htmlspecialchars_uni($l['col_banned_by']) ?></th>
              <th scope="col"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><?= htmlspecialchars_uni($l['col_time_left']) ?></th>
              <th scope="col" class="text-end"><span class="visually-hidden"><?= htmlspecialchars_uni($l['col_actions']) ?></span></th>
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
              $plainName       = $rawName !== '' ? $rawName : $l['lbl_deleted_user'];
              $name            = htmlspecialchars_uni($plainName);
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
              $liftUrl = "{$base}&action=lift";
              $pruneUrl= "{$base}&action=prune";
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
                <div class="bn-by"><?= !empty($ban['adminuser']) ? htmlspecialchars_uni($ban['adminuser']) : '<span class="bn-muted">' . htmlspecialchars_uni($l['lbl_system']) . '</span>' ?></div>
                <div class="bn-muted small"><?= my_datee($dateformat, $ban['dateline']) ?></div>
              </td>
              <td>
                <div class="bn-remain bn-t-<?= $tone ?>">
                  <?php if ($isPerm): ?>
                    <div class="bn-remain-top"><strong><i class="fa-solid fa-infinity me-1" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_permanent']) ?></strong><span><?= htmlspecialchars_uni($l['lbl_never_lifts']) ?></span></div>
                  <?php elseif ($remaining <= 0): ?>
                    <div class="bn-remain-top"><strong><i class="fa-solid fa-hourglass-end me-1" aria-hidden="true"></i><?= htmlspecialchars_uni($l['lbl_expired']) ?></strong><span><?= htmlspecialchars_uni($l['lbl_awaiting_lift']) ?></span></div>
                  <?php else: ?>
                    <div class="bn-remain-top"><strong><?= mkprettytime($remaining) ?></strong><span><?= my_datee($dateformat, $ban['lifted']) ?></span></div>
                  <?php endif; ?>
                  <div class="bn-bar<?= $isPerm ? ' is-perm' : '' ?>" role="progressbar" aria-label="<?= htmlspecialchars_uni($l['lbl_ban_served']) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>">
                    <span style="width:<?= $pct ?>%"></span>
                  </div>
                </div>
              </td>
              <td class="text-end">
                <div class="bn-actions">
                  <a href="<?= htmlspecialchars_uni($editUrl) ?>" class="bn-icon-btn bn-t-primary" title="<?= htmlspecialchars_uni($l['act_edit_ban']) ?>" aria-label="<?= htmlspecialchars_uni(ags_fmt($l['aria_edit_ban'], $plainName)) ?>">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </a>
                  <a href="<?= htmlspecialchars_uni($liftUrl) ?>" class="bn-icon-btn bn-t-success" title="<?= htmlspecialchars_uni($l['btn_lift_ban']) ?>" aria-label="<?= htmlspecialchars_uni(ags_fmt($l['aria_lift_ban'], $plainName)) ?>"
                     data-bn-confirm data-tone="success" data-ok="<?= htmlspecialchars_uni($l['btn_lift_ban']) ?>"
                     data-title="<?= htmlspecialchars_uni(ags_fmt($l['cf_lift_user_title'], $plainName)) ?>"
                     data-text="<?= htmlspecialchars_uni($l['cf_lift_text']) ?>">
                    <i class="fa-solid fa-lock-open"></i>
                  </a>
                  <a href="<?= htmlspecialchars_uni($pruneUrl) ?>" class="bn-icon-btn bn-t-danger" title="<?= htmlspecialchars_uni($l['btn_prune']) ?>" aria-label="<?= htmlspecialchars_uni(ags_fmt($l['aria_prune'], $plainName)) ?>"
                     data-bn-confirm data-tone="danger" data-ok="<?= htmlspecialchars_uni($l['btn_prune']) ?>"
                     data-title="<?= htmlspecialchars_uni(ags_fmt($l['cf_prune_user_title'], $plainName)) ?>"
                     data-text="<?= htmlspecialchars_uni($l['cf_prune_text']) ?>">
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
            echo '<nav class="bn-pager" aria-label="' . htmlspecialchars_uni($l['lbl_pages']) . '">'
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
        string $okLabel = ''
    ): void {
        global $lang;
        $l = $lang->banning;
        if ($okLabel === '') $okLabel = $l['btn_yes_continue'];

        stdhead($l['title_confirm_action']);
        BanView::confirmPage(
            (string)$this->mybb->post_code, $tone, $icon, $title, $message,
            [
                $l['lbl_user'] => htmlspecialchars_uni((string)$user['username'])
                        . ' <span class="bn-muted">' . htmlspecialchars_uni(ags_fmt($l['lbl_uid'], (int)$user['id'])) . '</span>',
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