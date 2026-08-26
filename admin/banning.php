<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3"><strong>Error!</strong> Direct access not allowed.</div>');
}

require_once INC_PATH . '/functions_mkprettytime.php';

require_once INC_PATH . '/functions_multipage.php';




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
        1 => ['title' => 'Ban IP Address',         'label' => 'IP Address',     'description' => 'To ban a range use * (Ex: 127.0.0.*) or CIDR (Ex: 127.0.0.0/8)', 'button' => 'Ban IP Address',         'icon' => 'fa-ban',       'placeholder' => 'Enter IP address or range...'],
        2 => ['title' => 'Disallow Username',       'label' => 'Username',       'description' => 'Use * for wildcard (Ex: admin*, *bot)',                             'button' => 'Disallow Username',      'icon' => 'fa-user-lock', 'placeholder' => 'Enter username pattern...'],
        3 => ['title' => 'Disallow Email Address',  'label' => 'Email Address',  'description' => 'Use * for wildcard (Ex: *@spam.com)',                               'button' => 'Disallow Email Address', 'icon' => 'fa-envelope',  'placeholder' => 'Enter email pattern...'],
    ];

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

        output_inline_error($errors);
        $this->displayInterface();
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

    private function displayInterface(): void
    {
        $this->plugins->run_hooks('admin_config_banning_start');
        $typeConfig = $this->getCurrentTypeConfig();
        $this->renderInterface($typeConfig);
    }

    private function validateAdd(string $filter, int $type): array
    {
        $errors = [];
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

    private function renderInterface(array $tc): void
    {
        stdhead($tc['title']);
        echo '<style>.ban-row:hover{background:#f8f9fa!important}.empty-state{padding:3rem 1rem;text-align:center;color:#6c757d}.empty-state i{font-size:3rem;opacity:.5}</style>';
        output_nav_tabs(BAN_NAV, $tc['name']);
        $this->outputAddForm($tc);
        $this->outputBanList($tc);
        stdfoot();
    }

    private function outputAddForm(array $tc): void
    {
        $cfg   = self::FORM_CONFIGS[$tc['type']];
        $color = $tc['color'];
        echo "
        <div class='container mt-4'>
          <form action='index.php?act=banning&action=add' method='post' class='card border-0 shadow-sm'>
            <input type='hidden' name='my_post_key' value='{$this->mybb->post_code}'>
            <input type='hidden' name='type' value='{$tc['type']}'>
            <div class='card-header bg-{$color} text-white'>
              <h5 class='mb-0'><i class='fa-solid {$cfg['icon']} me-2'></i>{$cfg['title']}</h5>
            </div>
            <div class='card-body'>
              <label class='form-label fw-semibold'>{$cfg['label']} <span class='text-danger'>*</span></label>
              <div class='form-text text-muted mb-2'><i class='fa-solid fa-circle-info me-1'></i>{$cfg['description']}</div>
              <input type='text' name='filter' class='form-control form-control-lg' placeholder='{$cfg['placeholder']}' required autofocus>
            </div>
            <div class='card-footer bg-light text-center py-3'>
              <button type='submit' class='btn btn-{$color} btn-lg px-4'>
                <i class='fa-solid {$cfg['icon']} me-2'></i>{$cfg['button']}
              </button>
            </div>
          </form>
        </div>";
    }

    private function outputBanList(array $tc): void
    {
        $total_q = $this->db->sql_query_prepared("SELECT COUNT(fid) AS c FROM banfilters WHERE type = ?", [$tc['type']]);
        $total   = $total_q ? (int)$this->db->fetch_field($total_q, 'c') : 0;
        $page    = max(1, $this->mybb->get_input('page', MyBB::INPUT_INT));
        $start   = ($page - 1) * 20;
        $q       = $this->db->sql_query_prepared(
            "SELECT * FROM banfilters WHERE type = ? ORDER BY dateline DESC LIMIT ?, ?",
            [$tc['type'], $start, 20]
        );
        $filters = [];
        while ($q && ($f = $this->db->fetch_array($q))) {
            $f['fid'] = (int)$f['fid']; $f['type'] = (int)$f['type'];
            $f['dateline'] = (int)$f['dateline']; $f['lastuse'] = (int)$f['lastuse'];
            $filters[] = $f;
        }

        $headers = match ($tc['type']) {
            2 => '<th>Username</th><th>Date Disallowed</th><th>Last Attempt</th>',
            3 => '<th>Email</th><th>Date Disallowed</th><th>Last Attempt</th>',
            default => '<th>IP Address</th><th>Ban Date</th><th>Last Access</th>',
        };

        echo "
        <div class='container mt-4'>
          <div class='card border-0 shadow-sm'>
            <div class='card-header bg-light py-3'>
              <h5 class='mb-0'>
                <i class='fa-solid {$tc['icon']} text-{$tc['color']} me-2'></i>
                {$tc['title']} <span class='badge bg-{$tc['color']} ms-2'>{$total}</span>
              </h5>
            </div>
            <div class='card-body p-0'>
              <div class='table-responsive'>
                <table class='table table-hover mb-0'>
                  <thead class='table-light'><tr>{$headers}<th width='80' class='text-center'>Del</th></tr></thead>
                  <tbody>";

        if (empty($filters)) {
            $icons = [1 => 'fa-network-wired', 2 => 'fa-user-slash', 3 => 'fa-envelope'];
            echo "<tr><td colspan='4' class='empty-state'><i class='fa-solid {$icons[$tc['type']]}'></i><h5 class='text-muted'>No bans found</h5></td></tr>";
        } else {
            foreach ($filters as $f) {
                $val     = htmlspecialchars_uni($f['filter']);
                $date    = $f['dateline'] > 0 ? my_datee('relative', $f['dateline']) : 'N/A';
                $lastuse = $f['lastuse']  > 0 ? my_datee('relative', $f['lastuse'])  : 'Never';
                $new     = (TIMENOW - $f['dateline']) < 86400 ? '<span class="badge bg-success ms-1">New</span>' : '';
                $delUrl  = "index.php?act=banning&action=delete&fid={$f['fid']}&my_post_key={$this->mybb->post_code}";
                echo "
                <tr class='ban-row align-middle'>
                  <td><code>{$val}</code>{$new}</td>
                  <td><small class='text-muted'>{$date}</small></td>
                  <td><small class='text-muted'>{$lastuse}</small></td>
                  <td class='text-center'>
                    <a href='{$delUrl}' onclick='return AdminCP.deleteConfirmation(this, \"Are you sure you want to delete this ban?\")' class='btn btn-sm btn-outline-danger' title='Delete'>
                      <i class='fa-solid fa-trash-can'></i>
                    </a>
                  </td>
                </tr>";
            }
        }

        echo "</tbody></table></div></div></div></div>";

        if ($total > 20) {
    echo "<div class='container mt-3 d-flex justify-content-center'>"
       . multipage($total, 20, $page, "index.php?act=banning&type={$tc['name']}&page={page}")
       . "</div>";
}

    }

    private function showDeleteConfirmation(array $filter): void
    {
        $tc      = self::TYPE_CONFIGS[$filter['type']];
        $val     = htmlspecialchars_uni($filter['filter']);
        $delUrl  = "index.php?act=banning&action=delete&fid={$filter['fid']}&my_post_key={$this->mybb->post_code}";
        $canUrl  = "index.php?act=banning&type=" . $this->getTypeName((int)$filter['type']);

        stdhead('Confirm Deletion');
        echo "
        <div class='modal-backdrop' style='position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1040'></div>
        <div style='position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1050;width:100%;max-width:460px'>
          <div class='card border-0 shadow-lg' style='border-radius:16px;overflow:hidden'>
            <div class='card-header bg-danger text-white text-center py-4'>
              <i class='fas fa-exclamation-triangle fa-3x mb-2'></i>
              <h4 class='mb-0'>Confirm Deletion</h4>
            </div>
            <div class='card-body text-center py-4'>
              <p class='text-muted'>Are you sure you want to delete this ban?</p>
              <div class='bg-light rounded p-3 mb-4 text-start'>
                <strong>Filter:</strong> <code>{$val}</code><br>
                <strong>Type:</strong> {$tc['title']}
              </div>
              <form action='{$delUrl}' method='post' class='d-inline'>
                <input type='hidden' name='my_post_key' value='{$this->mybb->post_code}'>
                <button type='submit' class='btn btn-danger btn-lg px-4 me-2'>
                  <i class='fas fa-trash me-2'></i>Yes, Delete
                </button>
              </form>
              <a href='{$canUrl}' class='btn btn-outline-secondary btn-lg px-4'>
                <i class='fas fa-times me-2'></i>Cancel
              </a>
            </div>
          </div>
        </div>
        <script>
        document.querySelector('.modal-backdrop').addEventListener('click', () => location.href='{$canUrl}');
        document.addEventListener('keydown', e => { if(e.key==='Escape') location.href='{$canUrl}'; });
        </script>";
        stdfoot();
        exit;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  BannedAccountsManager — banned user accounts
// ═════════════════════════════════════════════════════════════════════════════
class BannedAccountsManager
{
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
                'Confirm Pruning',
                'Are you sure you want to prune all threads and posts? This cannot be undone.'
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
                'Confirm Lift Ban',
                'Are you sure you want to lift the ban? The user will be restored to their original group.'
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

    private function renderEditForm(array $ban, array $user, array $bannedGroups, array $banTimes, array $errors): void
    {
        stdhead('Edit Ban');
        output_nav_tabs(BAN_NAV, 'users');
        $this->outputErrors($errors);
        ?>
        <form action="index.php?act=banning&type=users&action=edit&uid=<?= $ban['uid'] ?>" method="post">
          <input type="hidden" name="my_post_key" value="<?= $this->mybb->post_code ?>">
          <div class="container mt-4">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fa-solid fa-user-edit me-2"></i>Edit Ban — <?= htmlspecialchars_uni($user['username']) ?></h5>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label fw-semibold">Username</label>
                    <div class="form-control bg-light"><?= htmlspecialchars_uni($user['username']) ?></div>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Reason</label>
                    <textarea name="reason" class="form-control" rows="4" maxlength="255"><?= htmlspecialchars_uni($this->mybb->input['reason'] ?? $ban['reason']) ?></textarea>
                  </div>
                  <?php if (count($bannedGroups) > 1): ?>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Banned Group</label>
                    <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? $ban['gid']) ?>
                  </div>
                  <?php endif; ?>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Ban Length</label>
                    <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? $ban['bantime'] ?? '---') ?>
                  </div>
                </div>
              </div>
              <div class="card-footer bg-light text-center py-3">
                <button type="submit" class="btn btn-warning btn-lg px-4"><i class="fa-solid fa-save me-2"></i>Update Ban</button>
                <a href="index.php?act=banning&type=users" class="btn btn-outline-secondary btn-lg ms-2"><i class="fa-solid fa-times me-2"></i>Cancel</a>
              </div>
            </div>
          </div>
        </form>
        <?php
        stdfoot();
    }

    private function renderMainInterface(array $bannedGroups, array $banTimes, array $errors): void
    {
        stdhead('Banned Accounts');
        output_nav_tabs(BAN_NAV, 'users');
        $this->outputErrors($errors);
        ?>
        <form action="index.php?act=banning&type=users" method="post">
          <input type="hidden" name="my_post_key" value="<?= $this->mybb->post_code ?>">
          <div class="container mt-4">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-user-lock me-2"></i>Ban a User</h5>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-12 position-relative">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" name="username" id="username" class="form-control" autocomplete="off"
                           value="<?= htmlspecialchars_uni($this->mybb->get_input('username')) ?>"
                           placeholder="Enter username...">
                    <div id="usernameSuggestions" class="list-group position-absolute w-100 shadow-sm"
                         style="z-index:1050; display:none; max-height:220px; overflow-y:auto;"></div>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" maxlength="255"
                              placeholder="Enter ban reason..."><?= htmlspecialchars_uni($this->mybb->get_input('reason')) ?></textarea>
                  </div>
                  <?php if (count($bannedGroups) > 1): ?>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Banned Group</label>
                    <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? array_key_first($bannedGroups)) ?>
                  </div>
                  <?php endif; ?>
                  <div class="col-12">
                    <label class="form-label fw-semibold">Ban Length</label>
                    <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? '---') ?>
                  </div>
                </div>
              </div>
              <div class="card-footer bg-light text-center py-3">
                <button type="submit" name="ban" value="1" class="btn btn-danger btn-lg px-4"><i class="fa-solid fa-ban me-2"></i>Ban User</button>
              </div>
            </div>
          </div>
        </form>
        <script>
        (function () {
            const input = document.getElementById('username');
            const box   = document.getElementById('usernameSuggestions');
            if (!input || !box) return;

            let debounceTimer = null;
            let activeController = null;

            function hideBox() {
                box.style.display = 'none';
                box.innerHTML = '';
            }

            function escapeHtml(str) {
                return String(str ?? '').replace(/[&<>"']/g, function (ch) {
                    switch (ch) {
                        case '&': return '&amp;';
                        case '<': return '&lt;';
                        case '>': return '&gt;';
                        case '"': return '&quot;';
                        case "'": return '&#39;';
                    }
                });
            }

            input.addEventListener('input', function () {
                const term = input.value.trim();
                clearTimeout(debounceTimer);

                if (term.length < 2) {
                    hideBox();
                    return;
                }

                debounceTimer = setTimeout(function () {
                    if (activeController) activeController.abort();
                    activeController = new AbortController();

                    fetch('index.php?act=banning&type=users&action=search_username&q=' + encodeURIComponent(term), {
                        signal: activeController.signal
                    })
                        .then(r => r.json())
                        .then(users => {
                            if (!Array.isArray(users) || users.length === 0) {
                                hideBox();
                                return;
                            }
                            box.innerHTML = users.map(u =>
                                '<button type="button" class="list-group-item list-group-item-action py-2">'
                                + escapeHtml(u.username) + '</button>'
                            ).join('');
                            box.style.display = 'block';
                        })
                        .catch(() => {});
                }, 250);
            });

            box.addEventListener('click', function (e) {
                const btn = e.target.closest('button');
                if (!btn) return;
                input.value = btn.textContent;
                hideBox();
                input.focus();
            });

            document.addEventListener('click', function (e) {
                if (e.target !== input && !box.contains(e.target)) hideBox();
            });
        })();
        </script>
        <?php
        $this->outputBannedUsersList();
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
        $perPage  = 20;
        $page     = max(1, $this->mybb->get_input('page', MyBB::INPUT_INT));
        $start    = ($page - 1) * $perPage;
        ?>
        <div class="container mt-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-3">
              <h5 class="mb-0">
                <i class="fa-solid fa-users-slash text-danger me-2"></i>
                Banned Accounts <span class="badge bg-danger ms-2"><?= $banCount ?></span>
              </h5>
            </div>
            <div class="card-body p-0">
            <?php if ($banCount === 0): ?>
              <div class="text-center py-5">
                <i class="fa-solid fa-users-slash fa-4x text-muted mb-3 d-block"></i>
                <h5 class="text-muted">No Banned Users</h5>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>User</th>
                      <th width="200">Lifts On</th>
                      <th width="150">Time Left</th>
                      <th width="80" class="text-center">Edit</th>
                      <th width="80" class="text-center">Lift</th>
                      <th width="80" class="text-center">Prune</th>
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
                      $isPerm          = $ban['lifted'] === 0 || in_array($ban['bantime'], ['perm','---'], true);
                      $liftsOn         = $isPerm ? 'Never' : my_datee($dateformat, $ban['lifted']);
                      $remaining       = $ban['lifted'] - TIMENOW;
                      $timeBadge       = $isPerm
                          ? '<span class="badge bg-dark">Permanent</span>'
                          : '<span class="badge bg-' . match(true) {
                              $remaining < 3600   => 'danger',
                              $remaining < 86400  => 'warning',
                              $remaining < 604800 => 'info',
                              default             => 'success',
                          } . '">' . mkprettytime($remaining) . '</span>';
                  ?>
                  <tr>
                    <td>
                      <strong><?= build_profile_link(htmlspecialchars_uni($ban['username']), $ban['uid'], '_blank') ?></strong>
                      <?php if (!empty($ban['reason'])): ?>
                      <div class="small text-muted fst-italic"><?= htmlspecialchars_uni($ban['reason']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= $liftsOn ?></small></td>
                    <td><?= $timeBadge ?></td>
                    <td class="text-center">
                      <a href="index.php?act=banning&type=users&action=edit&uid=<?= $ban['uid'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </a>
                    </td>
                     
					 <td class="text-center">
            <a href="index.php?act=banning&type=users&action=lift&uid=<?= $ban['uid'] ?>&my_post_key=<?= $this->mybb->post_code ?>"
               onclick="return AdminCP.deleteConfirmation(this, 'Are you sure you want to lift this ban?');"
               class="btn btn-sm btn-outline-success" title="Lift Ban">
              <i class="fa-solid fa-lock-open"></i>
            </a>
          </td>
					
					
                    <td class="text-center">
                      <a href="index.php?act=banning&type=users&action=prune&uid=<?= $ban['uid'] ?>&my_post_key=<?= $this->mybb->post_code ?>"
                         onclick="return confirm('Prune all content? Cannot be undone!')" class="btn btn-sm btn-outline-danger">
                        <i class="fa-solid fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
       
	   if ($banCount > $perPage) {
    echo '<div class="container mt-3 d-flex justify-content-center">'
       . multipage($banCount, $perPage, $page, 'index.php?act=banning&type=users&page={page}')
       . '</div>';
}

    }

    private function showConfirmation(array $user, string $actionUrl, string $cancelUrl, string $title, string $message): void
    {
        stdhead('Confirm Action');
        ?>
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1040"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1050;width:100%;max-width:480px">
          <div class="card border-0 shadow-lg" style="border-radius:16px;overflow:hidden">
            <div class="card-header bg-danger text-white text-center py-4">
              <i class="fas fa-exclamation-triangle fa-3x mb-2 d-block"></i>
              <h4 class="mb-0"><?= htmlspecialchars($title) ?></h4>
            </div>
            <div class="card-body text-center py-4">
              <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
              <div class="bg-light rounded p-3 mb-4 text-start">
                <strong>User:</strong> <?= htmlspecialchars_uni($user['username']) ?>
                <span class="text-muted ms-2">UID: <?= (int)$user['id'] ?></span>
              </div>
              <form action="<?= htmlspecialchars($actionUrl) ?>" method="post" class="d-inline">
                <input type="hidden" name="my_post_key" value="<?= $this->mybb->post_code ?>">
                <button type="submit" class="btn btn-danger btn-lg px-4 me-2">
                  <i class="fas fa-check me-2"></i>Yes, Continue
                </button>
              </form>
              <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn btn-outline-secondary btn-lg px-4">
                <i class="fas fa-times me-2"></i>Cancel
              </a>
            </div>
          </div>
        </div>
        <script>
        document.addEventListener('keydown', e => { if(e.key==='Escape') location.href=<?= json_encode($cancelUrl) ?>; });
        </script>
        <?php
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

    private function selectBox(string $name, array $options, mixed $selected, string $class = 'form-select'): string
    {
        $html = "<select name=\"{$name}\" class=\"{$class}\">";
        foreach ($options as $val => $label) {
            $sel   = $val == $selected ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars_uni((string)$val) . '"' . $sel . '>'
                   . htmlspecialchars_uni((string)$label) . '</option>';
        }
        return $html . '</select>';
    }

    private function outputErrors(array $errors): void
    {
        if (empty($errors)) return;
        echo '<div class="container mt-3"><div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $e) echo '<li>' . htmlspecialchars_uni($e) . '</li>';
        echo '</ul></div></div>';
    }
}