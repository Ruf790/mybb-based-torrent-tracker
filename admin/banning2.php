<?php
declare(strict_types=1);

define('IN_MYBB',                  1);
define('IN_ADMINCP',               1);
define('TSF_FORUMS_TSSEv56',       true);
define('TSF_FORUMS_GLOBAL_TSSEv56',true);
define('TSF_VERSION',              'v1.5 by xam');

if (!defined('IN_MYBB')) {
    http_response_code(403);
    die('<div class="alert alert-danger">Access Denied</div>');
}

require_once INC_PATH . '/functions_mkprettytime.php';

// ── Конфигурация ──────────────────────────────────────────

const BAN_TABS = [
    'ips' => [
        'title'       => 'Banned IPs',
        'link'        => 'index.php?act=banning',
        'description' => 'Manage IP addresses banned from accessing your board.',
        'icon'        => 'fa-solid fa-network-wired',
    ],
    'users' => [
        'title'       => 'Banned Accounts',
        'link'        => 'index.php?act=banning2',
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

// ── Менеджер банов ────────────────────────────────────────

class BannedAccountsManager
{
    public function __construct(
        private readonly object $mybb,
        private readonly object $db,
        private readonly object $cache,
        private readonly object $plugins
    ) {}

    // ── Маршрутизация ─────────────────────────────────────

    public function handleRequest(): void
    {
        match ($this->mybb->get_input('action')) {
            'prune'  => $this->handlePrune(),
            'lift'   => $this->handleLift(),
            'edit'   => $this->handleEdit(),
            default  => $this->displayInterface(),
        };
    }

    // ── Действия ──────────────────────────────────────────

    private function handlePrune(): void
    {
        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning2');
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            $this->flashError('Invalid ban specified');
            admin_redirect('index.php?act=banning2');
        }

        if (is_super_admin((int)$user['id']) && !$this->canModifySuperAdmin()) {
            $this->flashError('You cannot perform this action on a super administrator');
            admin_redirect('index.php?act=banning2');
        }

        $this->plugins->run_hooks('admin_user_banning_prune');

        if ($this->mybb->request_method === 'post') {
            $this->pruneUserContent((int)$user['id']);
            $this->flashSuccess('User content pruned successfully');
            admin_redirect('index.php?act=banning2');
        } else {
            $this->showConfirmation(
                $user,
                "index.php?act=banning2&action=prune&uid={$user['id']}",
                'index.php?act=banning2',
                'Confirm Pruning',
                'Are you sure you want to prune all threads and posts? This cannot be undone.'
            );
        }
    }

    private function handleLift(): void
    {
        if ($this->mybb->get_input('no')) {
            admin_redirect('index.php?act=banning2');
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            $this->flashError('Invalid ban specified');
            admin_redirect('index.php?act=banning2');
        }

        if (is_super_admin((int)$ban['uid']) && !$this->canModifySuperAdmin()) {
            $this->flashError('You cannot perform this action on a super administrator');
            admin_redirect('index.php?act=banning2');
        }

        $this->plugins->run_hooks('admin_user_banning_lift');

        if ($this->mybb->request_method === 'post') {
            $this->liftBan($ban, $user);
            $this->flashSuccess('Ban lifted successfully');
            admin_redirect('index.php?act=banning2');
        } else {
            $this->showConfirmation(
                $user,
                "index.php?act=banning2&action=lift&uid={$ban['uid']}",
                'index.php?act=banning2',
                'Confirm Lift Ban',
                'Are you sure you want to lift the ban? The user will be restored to their original group.'
            );
        }
    }

    private function handleEdit(): void
    {
        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban || !($user = get_user($ban['uid']))) {
            $this->flashError('Invalid ban specified');
            admin_redirect('index.php?act=banning2');
        }

        $bannedGroups = $this->getBannedGroups();
        $banTimes     = fetch_ban_times();

        $this->plugins->run_hooks('admin_user_banning_edit');

        $errors = [];
        if ($this->mybb->request_method === 'post') {
            $errors = $this->validateBanEdit($ban);
            if (empty($errors)) {
                $this->updateBan($ban, $user, $bannedGroups);
                $this->flashSuccess('Ban updated successfully');
                admin_redirect('index.php?act=banning2');
            }
        }

        $this->renderEditForm($ban, $user, $bannedGroups, $banTimes, $errors);
    }

    private function displayInterface(): void
    {
        $bannedGroups = $this->getBannedGroups();
        $banTimes     = fetch_ban_times();

        $this->plugins->run_hooks('admin_user_banning_start');

        $errors = [];
        if ($this->mybb->request_method === 'post') {
            $errors = $this->processBanAction($bannedGroups);
            if (empty($errors)) return; // редирект произошёл внутри
        }

        $this->renderMainInterface($bannedGroups, $banTimes, $errors);
    }

    // ── Бизнес-логика ─────────────────────────────────────

    private function getBanByUserId(int $uid): ?array
    {
        $q   = $this->db->simple_select('banned', '*', "uid='{$uid}'");
        $row = $this->db->fetch_array($q);
        if (!$row) return null;

        return array_merge($row, [
            'uid'            => (int)$row['uid'],
            'gid'            => (int)$row['gid'],
            'oldgroup'       => (int)$row['oldgroup'],
            'olddisplaygroup'=> (int)$row['olddisplaygroup'],
            'admin'          => (int)$row['admin'],
            'dateline'       => (int)$row['dateline'],
            'lifted'         => (int)$row['lifted'],
        ]);
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
        $q      = $this->db->simple_select('usergroups', 'gid,title', 'isbannedgroup=1', ['order_by' => 'title']);
        $groups = [];
        while ($g = $this->db->fetch_array($q)) {
            $groups[(int)$g['gid']] = $g['title'];
        }
        return $groups;
    }

    private function pruneUserContent(int $userId): void
    {
        require_once INC_PATH . '/class_moderation.php';
        $mod = new Moderation();

        $q = $this->db->simple_select('tsf_threads', 'tid', "uid='{$userId}'");
        while ($row = $this->db->fetch_array($q)) {
            $mod->delete_thread($row['tid']);
        }

        $q = $this->db->simple_select('tsf_posts', 'pid', "uid='{$userId}'");
        while ($row = $this->db->fetch_array($q)) {
            $mod->delete_post($row['pid']);
        }

        $this->plugins->run_hooks('admin_user_banning_prune_commit');
        $this->cache->update_reportedcontent();

        $user = get_user($userId);
        log_admin_action($userId, $user['username'] ?? 'Unknown');
    }

    private function liftBan(array $ban, array $user): void
    {
        $this->db->delete_query('banned', "uid='{$ban['uid']}'");
        $this->db->update_query('users', [
            'usergroup'        => $ban['oldgroup'],
            'additionalgroups' => $this->db->escape_string($ban['oldadditionalgroups']),
            'displaygroup'     => $ban['olddisplaygroup'],
        ], "id='{$ban['uid']}'");

        $this->plugins->run_hooks('admin_user_banning_lift_commit');
        log_admin_action($ban['uid'], $user['username']);
    }

    private function validateBanEdit(array $ban): array
    {
        $errors = [];
        if (empty($ban['uid'])) {
            $errors[] = 'The username is invalid and does not exist';
        } elseif (is_super_admin($ban['uid']) && !$this->canModifySuperAdmin()) {
            $errors[] = 'You do not have permission to ban this user';
        } elseif ($ban['uid'] === $this->getCurrentUserId()) {
            $errors[] = 'You cannot ban yourself';
        }
        return $errors;
    }

    private function updateBan(array $ban, array $user, array $bannedGroups): void
    {
        $bantime = $this->mybb->input['bantime'] ?? '---';
        $lifted  = $bantime === '---' ? 0 : ban_date2timestamp($bantime, $ban['dateline']);
        $reason  = my_substr($this->mybb->input['reason'] ?? '', 0, 255);

        if (count($bannedGroups) === 1) {
            $this->mybb->input['usergroup'] = array_key_first($bannedGroups);
        }

        $this->db->update_query('banned', [
            'gid'     => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'dateline'=> TIMENOW,
            'bantime' => $this->db->escape_string($bantime),
            'lifted'  => $this->db->escape_string($lifted),
            'reason'  => $this->db->escape_string($reason),
        ], "uid='{$ban['uid']}'");

        $this->db->update_query('users', [
            'usergroup'        => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'displaygroup'     => 0,
            'additionalgroups' => '',
        ], "id='{$ban['uid']}'");

        $this->plugins->run_hooks('admin_user_banning_edit_commit');
        log_admin_action($ban['uid'], $user['username']);
    }

    private function processBanAction(array $bannedGroups): array
    {
        if (isset($this->mybb->input['search'])) return [];

        $username = $this->mybb->get_input('username');
        $user     = get_user_by_username($username, [
            'fields' => ['username', 'usergroup', 'additionalgroups', 'displaygroup'],
        ]);

        if (!$user) return ['The username you entered is invalid and does not exist'];

        $userId = (int)$user['id'];
        $errors = [];

        if (is_super_admin($userId) && !$this->canModifySuperAdmin()) {
            $errors[] = 'You do not have permission to ban this user';
        } elseif ($this->isUserAlreadyBanned($userId)) {
            $errors[] = 'This user is already banned';
        } elseif ($userId === $this->getCurrentUserId()) {
            $errors[] = 'You cannot ban yourself';
        }

        if (empty($errors)) {
            $this->createNewBan($user, $bannedGroups);
            $this->flashSuccess('User banned successfully');
            admin_redirect('index.php?act=banning2');
        }

        return $errors;
    }

    private function isUserAlreadyBanned(int $userId): bool
    {
        $q = $this->db->simple_select('banned', 'uid', "uid='{$userId}'");
        if ($this->db->fetch_field($q, 'uid')) return true;

        $usergroups = $this->cache->read('usergroups');
        $user       = get_user($userId);
        if (!$user) return false;

        return !empty($usergroups[(int)$user['usergroup']]['isbannedgroup']);
    }

    private function createNewBan(array $user, array $bannedGroups): void
    {
        $bantime = $this->mybb->input['bantime'] ?? '---';
        $lifted  = $bantime === '---' ? 0 : ban_date2timestamp($bantime);
        $reason  = my_substr($this->mybb->input['reason'] ?? '', 0, 255);

        if (count($bannedGroups) === 1) {
            $this->mybb->input['usergroup'] = array_key_first($bannedGroups);
        }

        $userId = (int)$user['id'];

        $this->db->insert_query('banned', [
            'uid'                 => $userId,
            'gid'                 => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'oldgroup'            => (int)$user['usergroup'],
            'oldadditionalgroups' => $this->db->escape_string($user['additionalgroups']),
            'olddisplaygroup'     => (int)$user['displaygroup'],
            'admin'               => $this->getCurrentUserId(),
            'dateline'            => TIMENOW,
            'bantime'             => $this->db->escape_string($bantime),
            'lifted'              => $this->db->escape_string($lifted),
            'reason'              => $this->db->escape_string($reason),
        ]);

        $this->db->update_query('users', [
            'usergroup'        => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'displaygroup'     => 0,
            'additionalgroups' => '',
        ], "id='{$userId}'");

        $this->db->delete_query('tsf_forumsubscriptions',  "uid='{$userId}'");
        $this->db->delete_query('tsf_threadsubscriptions', "uid='{$userId}'");

        $this->plugins->run_hooks('admin_user_banning_start_commit');
        log_admin_action($userId, $user['username'], $lifted);
    }

    // ── Рендер ────────────────────────────────────────────

    private function renderEditForm(array $ban, array $user, array $bannedGroups, array $banTimes, array $errors = []): void
    {
        stdhead('Edit Ban');
        output_nav_tabs(BAN_TABS, 'users');
        ?>
        <form action="index.php?act=banning2&action=edit&uid=<?= (int)$ban['uid'] ?>" method="post">
          <input type="hidden" name="my_post_key" value="<?= $this->mybb->post_code ?>">
          <div class="container mt-4">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fa-solid fa-user-edit me-2"></i>Edit Ban — <?= htmlspecialchars_uni($user['username']) ?></h5>
              </div>
              <div class="card-body">
                <?php $this->outputErrors($errors); ?>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label fw-semibold">Username</label>
                    <div class="form-control bg-light"><?= htmlspecialchars_uni($user['username']) ?></div>
                  </div>
                  <div class="col-12">
                    <label for="reason" class="form-label fw-semibold">Ban Reason</label>
                    <textarea name="reason" id="reason" class="form-control" maxlength="255" rows="4"><?= htmlspecialchars_uni($this->mybb->input['reason'] ?? $ban['reason']) ?></textarea>
                  </div>
                  <?php if (count($bannedGroups) > 1): ?>
                  <div class="col-12">
                    <label for="usergroup" class="form-label fw-semibold">Banned Group</label>
                    <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? $ban['gid'], 'form-select', 'usergroup') ?>
                  </div>
                  <?php endif; ?>
                  <div class="col-12">
                    <label for="bantime" class="form-label fw-semibold">Ban Length</label>
                    <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? $ban['bantime'] ?? '---', 'form-select', 'bantime') ?>
                  </div>
                </div>
              </div>
              <div class="card-footer bg-light text-center py-3">
                <button type="submit" class="btn btn-warning btn-lg px-4">
                  <i class="fa-solid fa-save me-2"></i>Update Ban
                </button>
                <a href="index.php?act=banning2" class="btn btn-outline-secondary btn-lg ms-2">
                  <i class="fa-solid fa-times me-2"></i>Cancel
                </a>
              </div>
            </div>
          </div>
        </form>
        <?php
        stdfoot();
    }

    private function renderMainInterface(array $bannedGroups, array $banTimes, array $errors = []): void
    {
        stdhead('Banned Accounts');
        output_nav_tabs(BAN_TABS, 'users');
        ?>
        <form action="index.php?act=banning2" method="post">
          <input type="hidden" name="my_post_key" value="<?= $this->mybb->post_code ?>">
          <div class="container mt-4">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-user-lock me-2"></i>Ban a User</h5>
              </div>
              <div class="card-body">
                <?php $this->outputErrors($errors); ?>
                <div class="row g-3">
                  <div class="col-12">
                    <label for="username" class="form-label fw-semibold">Username</label>
                    <small class="form-text text-muted d-block mb-1">Auto-complete is enabled.</small>
                    <input type="text" name="username" id="username" class="form-control"
                           value="<?= htmlspecialchars_uni($this->mybb->get_input('username')) ?>"
                           placeholder="Enter username...">
                  </div>
                  <div class="col-12">
                    <label for="reason" class="form-label fw-semibold">Ban Reason</label>
                    <textarea name="reason" id="reason" class="form-control" maxlength="255" rows="4"
                              placeholder="Enter ban reason..."><?= htmlspecialchars_uni($this->mybb->get_input('reason')) ?></textarea>
                  </div>
                  <?php if (count($bannedGroups) > 1): ?>
                  <div class="col-12">
                    <label for="usergroup" class="form-label fw-semibold">Banned Group</label>
                    <?= $this->selectBox('usergroup', $bannedGroups, $this->mybb->input['usergroup'] ?? $this->getDefaultBannedGroup($bannedGroups), 'form-select', 'usergroup') ?>
                  </div>
                  <?php endif; ?>
                  <div class="col-12">
                    <label for="bantime" class="form-label fw-semibold">Ban Length</label>
                    <?= $this->selectBox('bantime', $this->prepareBanTimes($banTimes), $this->mybb->input['bantime'] ?? '---', 'form-select', 'bantime') ?>
                  </div>
                </div>
              </div>
              <div class="card-footer bg-light text-center py-3">
                <button type="submit" name="ban" value="1" class="btn btn-danger btn-lg px-4">
                  <i class="fa-solid fa-ban me-2"></i>Ban User
                </button>
                <button type="submit" name="search" value="1" class="btn btn-outline-primary btn-lg ms-2">
                  <i class="fa-solid fa-search me-2"></i>Search User
                </button>
              </div>
            </div>
          </div>
        </form>
        <?php
        $this->outputUserAutoComplete();
        $this->outputBannedUsersList();
        stdfoot();
    }

    // ── Таблица банов ─────────────────────────────────────

    private function outputBannedUsersList(): void
    {
        global $dateformat;

        $username  = $this->mybb->get_input('username');
        $userWhere = '';

        if ($this->mybb->request_method === 'post' && isset($this->mybb->input['search']) && $username) {
            $user = get_user_by_username($username);
            if ($user) {
                $uid       = (int)$user['id'];
                $userWhere = "WHERE b.uid='{$uid}'";
            }
        }

        $q        = $this->db->simple_select('banned', 'COUNT(*) AS cnt', ltrim(str_replace('WHERE', '', $userWhere)));
        $banCount = (int)$this->db->fetch_field($q, 'cnt');

        $perPage     = 20;
        $currentPage = max(1, $this->mybb->get_input('page', MyBB::INPUT_INT));
        $start       = ($currentPage - 1) * $perPage;
        ?>
        <div class="container mt-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
              <h5 class="mb-0">
                <i class="fa-solid fa-users-slash text-danger me-2"></i>
                Banned Accounts
                <span class="badge bg-danger ms-2"><?= $banCount ?></span>
              </h5>
            </div>
            <div class="card-body p-0">
            <?php if ($banCount === 0): ?>
              <div class="text-center py-5">
                <i class="fa-solid fa-users-slash fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No Banned Users</h5>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>User</th>
                      <th width="200">Ban Lifts On</th>
                      <th width="150">Time Left</th>
                      <th width="100" class="text-center">Edit</th>
                      <th width="100" class="text-center">Lift</th>
                      <th width="150" class="text-center">Moderation</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                  $q = $this->db->sql_query("
                      SELECT b.*, a.username AS adminuser, u.username
                      FROM banned b
                      LEFT JOIN users u ON b.uid   = u.id
                      LEFT JOIN users a ON b.admin = a.id
                      {$userWhere}
                      ORDER BY b.dateline DESC
                      LIMIT {$start}, {$perPage}
                  ");
                  while ($ban = $this->db->fetch_array($q)) {
                      $this->outputBanRow($ban);
                  }
                  ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if ($banCount > $perPage): ?>
        <div class="container mt-3 d-flex justify-content-center">
          <?= draw_admin_pagination($currentPage, $perPage, $banCount, 'index.php?act=banning2&page={page}') ?>
        </div>
        <?php endif;
    }

    private function outputBanRow(array $ban): void
    {
        global $dateformat;

        $ban['uid']      = (int)$ban['uid'];
        $ban['dateline'] = (int)$ban['dateline'];
        $ban['lifted']   = (int)$ban['lifted'];

        $profileLink   = build_profile_link(htmlspecialchars_uni($ban['username']), $ban['uid'], '_blank');
        $banDate       = my_datee($dateformat, $ban['dateline']);
        $adminUsername = $ban['adminuser'] ?: ($ban['admin'] == 0 ? 'System' : (string)$ban['admin']);

        $isPermanent = ($ban['lifted'] === 0 || in_array($ban['bantime'], ['perm', '---'], true));

        if ($isPermanent) {
            $liftsOn           = 'Never';
            $timeRemainingHtml = '<span class="badge bg-dark">Permanent</span>';
            $banPeriod         = 'Permanently';
        } else {
            $remaining = $ban['lifted'] - TIMENOW;
            $liftsOn   = my_datee($dateformat, $ban['lifted']);
            $banPeriod = 'for ' . (fetch_ban_times()[$ban['bantime']] ?? $ban['bantime']);

            $color             = match(true) {
                $remaining < 3600   => 'danger',
                $remaining < 86400  => 'warning',
                $remaining < 604800 => 'info',
                default             => 'success',
            };
            $timeRemainingHtml = '<span class="badge bg-' . $color . '">' . mkprettytime($remaining) . '</span>';
        }
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center">
              <i class="fa-solid fa-user-slash text-danger me-2"></i>
              <div>
                <strong><?= $profileLink ?></strong>
                <div class="small text-muted">
                  Banned by <?= htmlspecialchars_uni($adminUsername) ?> on <?= $banDate ?> <?= $banPeriod ?>
                </div>
                <?php if (!empty($ban['reason'])): ?>
                <div class="small text-muted fst-italic"><?= htmlspecialchars_uni($ban['reason']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td><span class="text-muted small"><?= $liftsOn ?></span></td>
          <td><?= $timeRemainingHtml ?></td>
          <td class="text-center">
            <a href="index.php?act=banning2&action=edit&uid=<?= $ban['uid'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Ban">
              <i class="fa-solid fa-pen-to-square"></i>
            </a>
          </td>
          <td class="text-center">
            <a href="index.php?act=banning2&action=lift&uid=<?= $ban['uid'] ?>&my_post_key=<?= $this->mybb->post_code ?>"
               onclick="return AdminCP.deleteConfirmation(this, 'Are you sure you want to lift this ban?');"
               class="btn btn-sm btn-outline-success" title="Lift Ban">
              <i class="fa-solid fa-lock-open"></i>
            </a>
          </td>
          <td class="text-center">
            <a href="index.php?act=banning2&action=prune&uid=<?= $ban['uid'] ?>&my_post_key=<?= $this->mybb->post_code ?>"
               onclick="return AdminCP.deleteConfirmation(this, 'Are you sure you want to prune all content? This cannot be undone.');"
               class="btn btn-sm btn-outline-danger" title="Prune Content">
              <i class="fa-solid fa-trash"></i>
            </a>
          </td>
        </tr>
        <?php
    }

    // ── Диалог подтверждения ──────────────────────────────

    private function showConfirmation(array $user, string $actionUrl, string $cancelUrl, string $title, string $message): void
    {
        stdhead('Confirm Action');
        ?>
        <div class="modal-backdrop-custom"></div>
        <div class="confirmation-modal-custom card shadow-lg">
          <div class="card-header bg-danger text-white text-center py-4">
            <i class="fas fa-exclamation-triangle fa-3x mb-2"></i>
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
        <style>
        .modal-backdrop-custom {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.6);
            backdrop-filter: blur(4px);
            z-index: 1040;
        }
        .confirmation-modal-custom {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            z-index: 1050; width: 100%; max-width: 480px;
            border-radius: 16px; overflow: hidden;
        }
        </style>
        <script>
        document.querySelector('.modal-backdrop-custom').addEventListener('click', () => {
            location.href = <?= json_encode($cancelUrl) ?>;
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') location.href = <?= json_encode($cancelUrl) ?>;
        });
        </script>
        <?php
        stdfoot();
        exit;
    }

    // ── Автодополнение ────────────────────────────────────

    private function outputUserAutoComplete(): void
    {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById('username');
            if (!input) return;

            const dropdown = Object.assign(document.createElement('div'), {
                className: 'ac-dropdown list-group shadow-sm',
            });
            Object.assign(dropdown.style, {
                position: 'absolute', zIndex: 1000,
                display: 'none', width: input.offsetWidth + 'px',
                maxHeight: '200px', overflowY: 'auto',
            });
            input.parentNode.style.position = 'relative';
            input.parentNode.appendChild(dropdown);

            let timer;

            const search = q => {
                if (q.length < 2) { dropdown.style.display = 'none'; return; }
                fetch('../xmlhttp.php?action=get_users&query=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(users => {
                        dropdown.innerHTML = '';
                        if (!users?.length) { dropdown.style.display = 'none'; return; }
                        users.forEach(u => {
                            const name = u.username || u.text || '';
                            const item = Object.assign(document.createElement('button'), {
                                type: 'button',
                                className: 'list-group-item list-group-item-action',
                                textContent: name,
                            });
                            item.addEventListener('click', () => {
                                input.value = name;
                                dropdown.style.display = 'none';
                            });
                            dropdown.appendChild(item);
                        });
                        dropdown.style.display = 'block';
                    })
                    .catch(() => { dropdown.style.display = 'none'; });
            };

            input.addEventListener('input', e => {
                clearTimeout(timer);
                timer = setTimeout(() => search(e.target.value), 300);
            });

            input.addEventListener('keydown', e => {
                const items = [...dropdown.querySelectorAll('.list-group-item')];
                const idx   = items.findIndex(i => i === document.activeElement);
                if (e.key === 'ArrowDown') { e.preventDefault(); items[idx + 1]?.focus(); }
                if (e.key === 'ArrowUp')   { e.preventDefault(); items[idx - 1]?.focus() ?? input.focus(); }
                if (e.key === 'Escape')    { dropdown.style.display = 'none'; }
            });

            document.addEventListener('click', e => {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });

            window.addEventListener('resize', () => {
                dropdown.style.width = input.offsetWidth + 'px';
            });
        });
        </script>
        <?php
    }

    // ── Вспомогательные ───────────────────────────────────

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

    private function getDefaultBannedGroup(array $bannedGroups): int
    {
        if (!empty($this->mybb->settings['purgespammerbangroup'])) {
            return (int)$this->mybb->settings['purgespammerbangroup'];
        }
        return !empty($bannedGroups) ? (int)array_key_first($bannedGroups) : 0;
    }

    private function selectBox(string $name, array $options, mixed $selected, string $class = 'form-select', string $id = ''): string
    {
        $idAttr = $id ? " id=\"{$id}\"" : '';
        $html   = "<select name=\"{$name}\" class=\"{$class}\"{$idAttr}>";
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
        ?>
        <div class="alert alert-danger">
          <i class="fa-solid fa-exclamation-triangle me-2"></i>
          <strong>Please correct the following errors:</strong>
          <ul class="mb-0 mt-2">
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars_uni($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php
    }

    private function flashSuccess(string $msg): void { flash_message($msg, 'success'); }
    private function flashError(string $msg): void   { flash_message($msg, 'error'); }
}

// ── Запуск ────────────────────────────────────────────────

try {
    (new BannedAccountsManager($mybb, $db, $cache, $plugins))->handleRequest();
} catch (Throwable $e) {
    error_log('BannedAccountsManager: ' . $e->getMessage());
    flash_message('An error occurred while processing your request', 'error');
    admin_redirect('index.php?act=banning2');
}