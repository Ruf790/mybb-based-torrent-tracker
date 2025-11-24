<?php


declare(strict_types=1);

// Security constants
define("IN_MYBB", 1);
define("IN_ADMINCP", 1);
define('TSF_FORUMS_TSSEv56', true);
define('TSF_FORUMS_GLOBAL_TSSEv56', true);
define('TSF_VERSION', 'v1.5 by xam');

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    http_response_code(403);
    die("
        <div class='alert alert-danger text-center' style='max-width: 500px; margin: 100px auto;'>
            <i class='fa-solid fa-shield-exclamation fa-3x mb-3 text-warning'></i>
            <h4>Access Denied</h4>
            <p>Direct initialization of this file is not allowed.</p>
            <small>Please make sure IN_MYBB is defined.</small>
        </div>
    ");
}

require_once INC_PATH.'/functions_mkprettytime.php';

/**
 * Banned Accounts Management System
 */
class BannedAccountsManager
{
    private const BAN_TABS = [
    'ips' => [
        'title' => 'Banned IPs',
        'link' => "index.php?act=banning",
        'description' => 'Manage IP addresses banned from accessing your board. You can ban specific IPs, ranges using wildcards (*), or CIDR notation.',
        'icon' => 'fa-solid fa-network-wired'
    ],
    'users' => [ // Изменил 'bans' на 'users'
        'title' => 'Banned Accounts',
        'link' => "index.php?act=banning2",
        'description' => 'Manage user accounts that are currently banned from the forum.',
        'icon' => 'fa-solid fa-user-lock'
    ],
    'usernames' => [
        'title' => 'Disallowed Usernames',
        'link' => "index.php?act=banning&type=usernames",
        'description' => 'Manage usernames that cannot be registered or used. Useful for reserving names and preventing inappropriate usernames.',
        'icon' => 'fa-solid fa-user-slash'
    ],
	
    'emails' => [
    'title' => 'Disallowed Emails',
    'link' => "index.php?act=banning&type=emails",
    'description' => 'Manage email addresses and domains that cannot be used for registration. Use wildcards to block entire domains.',
    'icon' => 'fa-solid fa-mail-bulk' // Простая иконка письма
]

];

    public function __construct(
        private $mybb,
        private $db, 
        private $cache,
        private $plugins
    ) {}

    /**
     * Main request router
     */
    public function handleRequest(): void
    {
        $action = $this->mybb->get_input('action');
        
        match ($action) {
            'prune' => $this->handlePrune(),
            'lift' => $this->handleLift(),
            'edit' => $this->handleEdit(),
            default => $this->displayInterface()
        };
    }

    /**
     * Handle user pruning (delete threads/posts)
     */
    private function handlePrune(): void
    {
        if ($this->mybb->get_input('no')) {
            admin_redirect("index.php?act=banning2");
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban) {
            $this->flashError('Invalid ban specified');
            admin_redirect("index.php?act=banning2");
        }

        $user = get_user($ban['uid']);

        if (!$user || (is_super_admin((int)$user['id']) && !$this->canModifySuperAdmin())) {
            $this->flashError('You cannot perform this action on a super administrator');
            admin_redirect("index.php?act=banning2");
        }

        $this->plugins->run_hooks("admin_user_banning_prune");

        if ($this->mybb->request_method === "post") {
            $this->pruneUserContent((int)$user['id']);
            $this->flashSuccess('User content pruned successfully');
            admin_redirect("index.php?act=banning2");
        } else {
            $this->showPruneConfirmation($user);
        }
    }

    /**
     * Handle ban lifting
     */
    private function handleLift(): void
    {
        if ($this->mybb->get_input('no')) {
            admin_redirect("index.php?act=banning2");
        }

        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban) {
            $this->flashError('Invalid ban specified');
            admin_redirect("index.php?act=banning2");
        }

        $user = get_user($ban['uid']);

        if (!$user || (is_super_admin((int)$user['uid']) && !$this->canModifySuperAdmin())) {
            $this->flashError('You cannot perform this action on a super administrator');
            admin_redirect("index.php?act=banning2");
        }

        $this->plugins->run_hooks("admin_user_banning_lift");

        if ($this->mybb->request_method === "post") {
            $this->liftBan($ban, $user);
            $this->flashSuccess('Ban lifted successfully');
            admin_redirect("index.php?act=banning2");
        } else {
            $this->showLiftConfirmation($ban, $user);
        }
    }

    /**
     * Handle ban editing
     */
    private function handleEdit(): void
    {
        $uid = $this->mybb->get_input('uid', MyBB::INPUT_INT);
        $ban = $this->getBanByUserId($uid);

        if (!$ban) {
            $this->flashError('Invalid ban specified');
            admin_redirect("index.php?act=banning2");
        }

        $user = get_user($ban['uid']);
        $bannedGroups = $this->getBannedGroups();
        $banTimes = fetch_ban_times();

        $this->plugins->run_hooks("admin_user_banning_edit");

        if ($this->mybb->request_method === "post") {
            $errors = $this->validateBanEdit($ban, $user);
            
            if (empty($errors)) {
                $this->updateBan($ban, $user);
                $this->flashSuccess('Ban updated successfully');
                admin_redirect("index.php?act=banning2");
            }
            
            $this->renderEditForm($ban, $user, $bannedGroups, $banTimes, $errors);
        } else {
            $this->renderEditForm($ban, $user, $bannedGroups, $banTimes);
        }
    }

    /**
     * Display main management interface
     */
    private function displayInterface(): void
    {
        $this->plugins->run_hooks("admin_user_banning_start");

        $bannedGroups = $this->getBannedGroups();
        $banTimes = fetch_ban_times();

        if ($this->mybb->request_method === "post") {
            $errors = $this->processBanAction($bannedGroups);
            
            if (empty($errors)) {
                // Redirect happens in processBanAction if successful
                return;
            }
            
            $this->renderMainInterface($bannedGroups, $banTimes, $errors);
        } else {
            $this->renderMainInterface($bannedGroups, $banTimes);
        }
    }

    private function getBanByUserId(int $uid): ?array
    {
        $query = $this->db->simple_select("banned", "*", "uid='{$uid}'");
        $result = $this->db->fetch_array($query);
        
        if ($result) {
            // Ensure proper data types
            $result['uid'] = (int)$result['uid'];
            $result['gid'] = (int)$result['gid'];
            $result['oldgroup'] = (int)$result['oldgroup'];
            $result['olddisplaygroup'] = (int)$result['olddisplaygroup'];
            $result['admin'] = (int)$result['admin'];
            $result['dateline'] = (int)$result['dateline'];
            $result['lifted'] = (int)$result['lifted'];
        }
        
        return $result ?: null;
    }

    private function canModifySuperAdmin(): bool
    {
        global $CURUSER;
        return is_super_admin((int)$CURUSER['id']);
    }

    private function pruneUserContent(int $userId): void
    {
        require_once INC_PATH."/class_moderation.php";
        $moderation = new Moderation();

        // Delete user threads
        $query = $this->db->simple_select("tsf_threads", "tid", "uid='{$userId}'");
        while ($thread = $this->db->fetch_array($query)) {
            $moderation->delete_thread($thread['tid']);
        }

        // Delete user posts
        $query = $this->db->simple_select("tsf_posts", "pid", "uid='{$userId}'");
        while ($post = $this->db->fetch_array($query)) {
            $moderation->delete_post($post['pid']);
        }

        $this->plugins->run_hooks("admin_user_banning_prune_commit");
        $this->cache->update_reportedcontent();
        log_admin_action($userId, get_user($userId)['username'] ?? 'Unknown');
    }

    private function liftBan(array $ban, array $user): void
    {
        $updatedGroup = [
            'usergroup' => (int)$ban['oldgroup'],
            'additionalgroups' => $this->db->escape_string($ban['oldadditionalgroups']),
            'displaygroup' => (int)$ban['olddisplaygroup']
        ];
        
        $this->db->delete_query("banned", "uid='{$ban['uid']}'");
        $this->plugins->run_hooks("admin_user_banning_lift_commit");
        $this->db->update_query("users", $updatedGroup, "id='{$ban['uid']}'");
        
        log_admin_action($ban['uid'], $user['username']);
    }

    private function validateBanEdit(array $ban, array $user): array
    {
        $errors = [];

        if (empty($ban['uid'])) {
            $errors[] = 'The username you have entered is invalid and does not exist';
        } elseif (is_super_admin((int)$ban['uid']) && !$this->canModifySuperAdmin()) {
            $errors[] = 'You do not have permission to ban this user';
        } elseif ($ban['uid'] == $this->getCurrentUserId()) {
            $errors[] = 'You cannot ban yourself';
        }

        return $errors;
    }

    private function updateBan(array $ban, array $user): void
    {
        $lifted = ($this->mybb->input['bantime'] === '---') ? 0 : ban_date2timestamp($this->mybb->input['bantime'], $ban['dateline']);
        $reason = my_substr($this->mybb->input['reason'], 0, 255);

        $bannedGroups = $this->getBannedGroups();
        if (count($bannedGroups) === 1) {
            $group = array_keys($bannedGroups);
            $this->mybb->input['usergroup'] = $group[0];
        }

        $updateArray = [
            'gid' => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'dateline' => TIMENOW,
            'bantime' => $this->db->escape_string($this->mybb->input['bantime']),
            'lifted' => $this->db->escape_string($lifted),
            'reason' => $this->db->escape_string($reason)
        ];

        $this->db->update_query('banned', $updateArray, "uid='{$ban['uid']}'");

        $userUpdateArray = [
            'usergroup' => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'displaygroup' => 0,
            'additionalgroups' => '',
        ];
        $this->db->update_query('users', $userUpdateArray, "id = {$ban['uid']}");

        $this->plugins->run_hooks("admin_user_banning_edit_commit");
        log_admin_action($ban['uid'], $user['username']);
    }

    private function processBanAction(array $bannedGroups): array
    {
        $errors = [];
        $username = $this->mybb->get_input('username');

        if (isset($this->mybb->input['search'])) {
            // Search mode - handled in renderMainInterface
            return $errors;
        }

        $user = get_user_by_username($username, [
            'fields' => ['username', 'usergroup', 'additionalgroups', 'displaygroup']
        ]);

        if (!$user) {
            $errors[] = 'The username you have entered is invalid and does not exist';
            return $errors;
        }

        // Ensure user ID is integer
        $userId = (int)$user['id'];

        if (is_super_admin($userId) && !$this->canModifySuperAdmin()) {
            $errors[] = 'You do not have permission to ban this user';
        } elseif ($this->isUserAlreadyBanned($userId)) {
            $errors[] = 'This user already belongs to a banned group and cannot be added to a new one';
        } elseif ($userId == $this->getCurrentUserId()) {
            $errors[] = 'You cannot ban yourself';
        }

        if (empty($errors)) {
            $this->createNewBan($user, $bannedGroups);
            $this->flashSuccess('User banned successfully');
            admin_redirect("index.php?act=banning2");
        }

        return $errors;
    }

    private function isUserAlreadyBanned(int $userId): bool
    {
        $query = $this->db->simple_select("banned", "uid", "uid='{$userId}'");
        if ($this->db->fetch_field($query, "uid")) {
            return true;
        }

        $usergroups = $this->cache->read("usergroups");
        $user = get_user($userId);
        
        if (!$user) {
            return false;
        }

        $userGroupId = (int)$user['usergroup'];
        return !empty($usergroups[$userGroupId]) && $usergroups[$userGroupId]['isbannedgroup'] == 1;
    }

    private function createNewBan(array $user, array $bannedGroups): void
    {
        $lifted = ($this->mybb->input['bantime'] === '---') ? 0 : ban_date2timestamp($this->mybb->input['bantime']);
        $reason = my_substr($this->mybb->input['reason'], 0, 255);

        if (count($bannedGroups) === 1) {
            $group = array_keys($bannedGroups);
            $this->mybb->input['usergroup'] = $group[0];
        }

        $userId = (int)$user['id'];
        $userGroupId = (int)$user['usergroup'];
        $displayGroupId = (int)$user['displaygroup'];

        $insertArray = [
            'uid' => $userId,
            'gid' => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'oldgroup' => $userGroupId,
            'oldadditionalgroups' => $this->db->escape_string($user['additionalgroups']),
            'olddisplaygroup' => $displayGroupId,
            'admin' => $this->getCurrentUserId(),
            'dateline' => TIMENOW,
            'bantime' => $this->db->escape_string($this->mybb->input['bantime']),
            'lifted' => $this->db->escape_string($lifted),
            'reason' => $this->db->escape_string($reason)
        ];

        $this->db->insert_query('banned', $insertArray);

        $updateArray = [
            'usergroup' => $this->mybb->get_input('usergroup', MyBB::INPUT_INT),
            'displaygroup' => 0,
            'additionalgroups' => '',
        ];

        $this->db->delete_query("tsf_forumsubscriptions", "uid = '{$userId}'");
        $this->db->delete_query("tsf_threadsubscriptions", "uid = '{$userId}'");

        $this->plugins->run_hooks("admin_user_banning_start_commit");
        $this->db->update_query('users', $updateArray, "id = '{$userId}'");

        log_admin_action($userId, $user['username'], $lifted);
    }

    private function getBannedGroups(): array
    {
        $query = $this->db->simple_select("usergroups", "gid,title", "isbannedgroup=1", ['order_by' => 'title']);
        $groups = [];
        while ($group = $this->db->fetch_array($query)) {
            $groups[(int)$group['gid']] = $group['title'];
        }
        return $groups;
    }

    private function getCurrentUserId(): int
    {
        global $CURUSER;
        return (int)$CURUSER['id'];
    }

    private function renderEditForm(array $ban, array $user, array $bannedGroups, array $banTimes, array $errors = []): void
    {
        $this->outputHeader('Edit Ban');
        $this->outputNavigation('bans');

        $subTabs = [
            'edit' => [
                'title' => 'Edit Ban',
                'description' => 'Here you can edit the reason and length of currently banned users',
                'icon' => 'fa-edit'
            ]
        ];

        output_nav_tabs($subTabs, "edit");

        echo '
        <form action="index.php?act=banning2&action=edit&uid='.$ban['uid'].'" method="post">
            <input type="hidden" name="my_post_key" value="'.$this->mybb->post_code.'" />
            
            <div class="container mt-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-warning text-white rounded-top">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-user-edit me-2"></i>
                            Edit Ban - '.htmlspecialchars_uni($user['username']).'
                        </h5>
                    </div>
                    <div class="card-body">';

        if (!empty($errors)) {
            $this->outputErrors($errors);
        }

        echo '
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Username</label>
                                <div class="form-control bg-light">'.htmlspecialchars_uni($user['username']).'</div>
                            </div>
                            
                            <div class="col-12">
                                <label for="reason" class="form-label fw-semibold">Ban Reason</label>
                                <textarea name="reason" class="form-control" id="reason" maxlength="255" rows="4" placeholder="Enter ban reason...">'.htmlspecialchars_uni($this->mybb->input['reason'] ?? $ban['reason']).'</textarea>
                            </div>';

        if (count($bannedGroups) > 1) {
            $selectedGroup = $this->mybb->input['usergroup'] ?? $ban['gid'] ?? (int)$user['usergroup'] ?? 0;
            echo '
                            <div class="col-12">
                                <label for="usergroup" class="form-label fw-semibold">Banned Group</label>
                                <div class="form-text text-muted mb-2">In order for this user to be banned they must be moved to a banned group.</div>
                                '.$this->generateSelectBox('usergroup', $bannedGroups, $selectedGroup, ['id' => 'usergroup', 'class' => 'form-select']).'
                            </div>';
        }

        $lengthList = $this->prepareBanTimes($banTimes);
        $selectedTime = $this->mybb->input['bantime'] ?? $ban['bantime'] ?? '---';
        if (in_array($selectedTime, ['perm', '', '---'])) {
            $selectedTime = '---';
        }

        echo '
                            <div class="col-12">
                                <label for="bantime" class="form-label fw-semibold">Ban Length</label>
                                '.$this->generateSelectBox('bantime', $lengthList, $selectedTime, ['id' => 'bantime', 'class' => 'form-select']).'
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-center py-3">
                        <button type="submit" class="btn btn-warning btn-lg px-4">
                            <i class="fa-solid fa-save me-2"></i>
                            Update Ban
                        </button>
                        <a href="index.php?act=banning2" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fa-solid fa-times me-2"></i>
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>';

        $this->outputFooter();
    }

    private function renderMainInterface(array $bannedGroups, array $banTimes, array $errors = []): void
    {
        $this->outputHeader('Banned Accounts');
        $this->outputNavigation('bans');

        echo '
        <form action="index.php?act=banning2" method="post">
            <input type="hidden" name="my_post_key" value="'.$this->mybb->post_code.'" />
            
            <div class="container mt-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white rounded-top">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-user-lock me-2"></i>
                            Ban a User
                        </h5>
                    </div>
                    <div class="card-body">';

        if (!empty($errors)) {
            $this->outputErrors($errors);
        }

        $username = $this->mybb->get_input('username');
        $reason = $this->mybb->get_input('reason');

        echo '
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="username" class="form-label fw-semibold">Username</label>
                                <div class="form-text text-muted mb-2">Auto-complete is enabled in this field.</div>
                                '.$this->generateTextInput('username', $username, ['id' => 'username', 'class' => 'form-control', 'placeholder' => 'Enter username...']).'
                            </div>
                            
                            <div class="col-12">
                                <label for="reason" class="form-label fw-semibold">Ban Reason</label>
                                <textarea name="reason" class="form-control" id="reason" maxlength="255" rows="4" placeholder="Enter ban reason...">'.htmlspecialchars_uni($reason).'</textarea>
                            </div>';

        if (count($bannedGroups) > 1) {
            $selectedGroup = $this->mybb->input['usergroup'] ?? $this->getDefaultBannedGroup($bannedGroups);
            echo '
                            <div class="col-12">
                                <label for="usergroup" class="form-label fw-semibold">Banned Group</label>
                                <div class="form-text text-muted mb-2">In order for this user to be banned they must be moved to a banned group.</div>
                                '.$this->generateSelectBox('usergroup', $bannedGroups, $selectedGroup, ['id' => 'usergroup', 'class' => 'form-select']).'
                            </div>';
        }

        $lengthList = $this->prepareBanTimes($banTimes);
        $selectedTime = $this->mybb->input['bantime'] ?? '---';

        echo '
                            <div class="col-12">
                                <label for="bantime" class="form-label fw-semibold">Ban Length</label>
                                '.$this->generateSelectBox('bantime', $lengthList, $selectedTime, ['id' => 'bantime', 'class' => 'form-select']).'
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-center py-3">
                        <button type="submit" name="ban" value="1" class="btn btn-danger btn-lg px-4">
                            <i class="fa-solid fa-ban me-2"></i>
                            Ban User
                        </button>
                        <button type="submit" name="search" value="1" class="btn btn-outline-primary btn-lg ms-2">
                            <i class="fa-solid fa-search me-2"></i>
                            Search User
                        </button>
                    </div>
                </div>
            </div>
        </form>';

        $this->outputUserAutoComplete();
        $this->outputBannedUsersList();
        $this->outputFooter();
    }

    private function prepareBanTimes(array $banTimes): array
    {
        global $timeformat;
        $lengthList = [];
        
        foreach ($banTimes as $time => $period) {
            if ($time !== '---') {
                $friendlyTime = my_datee("D, jS M Y @ {$timeformat}", ban_date2timestamp($time));
                $lengthList[$time] = "{$period} ({$friendlyTime})";
            } else {
                $lengthList[$time] = $period;
            }
        }
        
        return $lengthList;
    }

    private function getDefaultBannedGroup(array $bannedGroups): int
    {
        if (!empty($this->mybb->settings['purgespammerbangroup'])) {
            return (int)$this->mybb->settings['purgespammerbangroup'];
        } elseif (count($bannedGroups)) {
            $group = array_keys($bannedGroups);
            return (int)$group[0];
        }
        
        return 0;
    }

    private function outputBannedUsersList(): void
    {
        $whereSql = '';
        $username = $this->mybb->get_input('username');
        
        if ($this->mybb->request_method === "post" && isset($this->mybb->input['search']) && $username) {
            $user = get_user_by_username($username);
            if ($user) {
                $whereSql = "WHERE b.uid='".(int)$user['id']."'";
            }
        }

        $query = $this->db->simple_select("banned", "COUNT(*) AS ban_count", str_replace('WHERE ', '', $whereSql));
        $banCount = (int)$this->db->fetch_field($query, "ban_count");

        $perPage = 20;
        $currentPage = $this->mybb->get_input('page', MyBB::INPUT_INT) ?: 1;
        $start = ($currentPage - 1) * $perPage;

        echo '
        <div class="container mt-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light rounded-top py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-users-slash text-danger me-2"></i>
                            Banned Accounts
                            <span class="badge bg-danger ms-2">'.$banCount.'</span>
                        </h5>
                        <div class="text-muted small">
                            <i class="fa-solid fa-clock me-1"></i>
                            Last updated: '.my_datee('relative', TIMENOW).'
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">';

        if ($banCount === 0) {
            echo '
                    <div class="text-center py-5">
                        <i class="fa-solid fa-users-slash fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Banned Users</h5>
                        <p class="text-muted">There are no banned users at this time.</p>
                    </div>';
        } else {
            $this->outputBannedUsersTable($whereSql, $start, $perPage);
        }

        echo '
                </div>
            </div>
        </div>';

        if ($banCount > $perPage) {
            echo '
            <div class="container mt-3">
                <div class="d-flex justify-content-center">
                    '.draw_admin_pagination($currentPage, $perPage, $banCount, "index.php?act=banning2&page={page}").'
                </div>
            </div>';
        }
    }

    private function outputBannedUsersTable(string $whereSql, int $start, int $perPage): void
    {
        $query = $this->db->sql_query("
            SELECT b.*, a.username AS adminuser, u.username
            FROM banned b
            LEFT JOIN users u ON (b.uid=u.id)
            LEFT JOIN users a ON (b.admin=a.id)
            {$whereSql}
            ORDER BY dateline DESC
            LIMIT {$start}, {$perPage}
        ");

        echo '
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
                <tbody>';

        while ($ban = $this->db->fetch_array($query)) {
            $this->outputBanRow($ban);
        }

        echo '
                </tbody>
            </table>
        </div>';
    }

    private function outputBanRow(array $ban): void
    {
        // Ensure proper data types
        $ban['uid'] = (int)$ban['uid'];
        $ban['dateline'] = (int)$ban['dateline'];
        $ban['lifted'] = (int)$ban['lifted'];

        $profileLink = build_profile_link(htmlspecialchars_uni($ban['username']), $ban['uid'], "_blank");
        $banDate = my_datee($dateformat, $ban['dateline']);
        $adminUsername = $ban['adminuser'] ?: ($ban['admin'] == 0 ? 'mybb_engine' : (string)$ban['admin']);

        if ($ban['lifted'] === 0 || $ban['bantime'] === 'perm' || $ban['bantime'] === '---') {
            $banPeriod = 'Permanently';
            $timeRemaining = $liftsOn = 'Never';
            $timeRemainingHtml = '<span class="badge bg-dark">Permanent</span>';
        } else {
            $banTimes = fetch_ban_times();
            $banPeriod = 'for '.$banTimes[$ban['bantime']];
            
            $remaining = $ban['lifted'] - TIMENOW;
            $timeRemaining = mkprettytime($remaining);
            $liftsOn = my_datee($dateformat, $ban['lifted']);

            if ($remaining < 3600) {
                $timeRemainingHtml = '<span class="badge bg-danger">'.$timeRemaining.'</span>';
            } elseif ($remaining < 86400) {
                $timeRemainingHtml = '<span class="badge bg-warning text-dark">'.$timeRemaining.'</span>';
            } elseif ($remaining < 604800) {
                $timeRemainingHtml = '<span class="badge bg-info">'.$timeRemaining.'</span>';
            } else {
                $timeRemainingHtml = '<span class="badge bg-success">'.$timeRemaining.'</span>';
            }
        }

        $userInfo = '
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-user-slash text-danger me-2"></i>
                </div>
                <div class="flex-grow-1 ms-2">
                    <strong>'.$profileLink.'</strong>
                    <div class="small text-muted">
                        Banned by '.htmlspecialchars_uni($adminUsername).' on '.$banDate.' '.$banPeriod.'
                    </div>
                </div>
            </div>';

        $editUrl = "index.php?act=banning2&action=edit&uid={$ban['uid']}";
        $liftUrl = "index.php?act=banning2&action=lift&uid={$ban['uid']}&my_post_key={$this->mybb->post_code}";
        $pruneUrl = "index.php?act=banning2&action=prune&uid={$ban['uid']}&my_post_key={$this->mybb->post_code}";

        echo '
        <tr>
            <td>'.$userInfo.'</td>
            <td><span class="text-muted small">'.$liftsOn.'</span></td>
            <td>'.$timeRemainingHtml.'</td>
            <td class="text-center">
                <a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary" title="Edit Ban">
                    <i class="fa-solid fa-pen-to-square"></i>
                </a>
            </td>
            <td class="text-center">
                <a href="'.$liftUrl.'" onclick="return AdminCP.deleteConfirmation(this, \'Are you sure you want to lift this ban?\');" class="btn btn-sm btn-outline-success" title="Lift Ban">
                    <i class="fa-solid fa-lock-open"></i>
                </a>
            </td>
            <td class="text-center">
                <a href="'.$pruneUrl.'" onclick="return AdminCP.deleteConfirmation(this, \'Are you sure you want to prune all threads and posts by this user? This action cannot be undone.\');" class="btn btn-sm btn-outline-danger" title="Prune Content">
                    <i class="fa-solid fa-trash"></i>
                </a>
            </td>
        </tr>';
    }








private function outputUserAutoComplete(): void
{
    echo '
    <script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        const usernameInput = document.getElementById("username");
        const usernameLabel = document.querySelector(\'[for="username"]\');
        let debounceTimer;

        if (!usernameInput) return;

        // Создаем контейнер для выпадающего списка
        const dropdown = document.createElement("div");
        dropdown.className = "autocomplete-dropdown";
        dropdown.style.cssText = `
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            width: ${usernameInput.offsetWidth}px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
        `;
        usernameInput.parentNode.style.position = "relative";
        usernameInput.parentNode.appendChild(dropdown);

        
        function searchUsers(query) {
            if (query.length < 2) {
                dropdown.style.display = "none";
                return;
            }

            fetch(`../xmlhttp.php?action=get_users&query=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.json();
                })
                .then(data => {
                    displayResults(data);
                })
                .catch(error => {
                    console.error("Error:", error);
                    dropdown.style.display = "none";
                });
        }

        
        function displayResults(users) {
            dropdown.innerHTML = "";
            
            if (!users || users.length === 0) {
                dropdown.style.display = "none";
                return;
            }

            users.forEach(user => {
                const item = document.createElement("div");
                item.style.cssText = `
                    padding: 8px 12px;
                    cursor: pointer;
                    border-bottom: 1px solid #f0f0f0;
                    transition: background-color 0.15s ease;
                `;
                
                // Обрабатываем разные форматы ответа
                const username = user.username || user.text || user.name || user.label || "";
                item.textContent = username;
                
                item.addEventListener("mouseenter", function() {
                    this.style.backgroundColor = "#f5f5f5";
                });
                
                item.addEventListener("mouseleave", function() {
                    this.style.backgroundColor = "";
                });
                
                item.addEventListener("click", function() {
                    usernameInput.value = username;
                    dropdown.style.display = "none";
                    usernameInput.focus();
                });
                
                dropdown.appendChild(item);
            });
            
            // Убираем границу у последнего элемента
            const lastItem = dropdown.lastElementChild;
            if (lastItem) {
                lastItem.style.borderBottom = "none";
            }
            
            dropdown.style.display = "block";
        }

        // Обработчик ввода с debounce
        usernameInput.addEventListener("input", function(e) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                searchUsers(e.target.value);
            }, 300);
        });

        // Обработчик фокуса
        usernameInput.addEventListener("focus", function() {
            if (this.value.length >= 2) {
                searchUsers(this.value);
            }
        });

        // Закрытие dropdown при клике вне
        document.addEventListener("click", function(e) {
            if (!usernameInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = "none";
            }
        });

        // Обработчик клавиш
        usernameInput.addEventListener("keydown", function(e) {
            const items = dropdown.querySelectorAll("div");
            const activeItem = dropdown.querySelector(".active");
            
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (items.length === 0) return;
                
                if (!activeItem) {
                    items[0].classList.add("active");
                    items[0].style.backgroundColor = "#007bff";
                    items[0].style.color = "white";
                } else {
                    const currentIndex = Array.from(items).indexOf(activeItem);
                    const nextIndex = (currentIndex + 1) % items.length;
                    
                    activeItem.classList.remove("active");
                    activeItem.style.backgroundColor = "";
                    activeItem.style.color = "";
                    
                    items[nextIndex].classList.add("active");
                    items[nextIndex].style.backgroundColor = "#007bff";
                    items[nextIndex].style.color = "white";
                }
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                if (items.length === 0) return;
                
                if (activeItem) {
                    const currentIndex = Array.from(items).indexOf(activeItem);
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                    
                    activeItem.classList.remove("active");
                    activeItem.style.backgroundColor = "";
                    activeItem.style.color = "";
                    
                    items[prevIndex].classList.add("active");
                    items[prevIndex].style.backgroundColor = "#007bff";
                    items[prevIndex].style.color = "white";
                }
            } else if (e.key === "Enter") {
                if (activeItem) {
                    e.preventDefault();
                    activeItem.click();
                }
            } else if (e.key === "Escape") {
                dropdown.style.display = "none";
                if (activeItem) {
                    activeItem.classList.remove("active");
                    activeItem.style.backgroundColor = "";
                    activeItem.style.color = "";
                }
            }
        });

        // Обработчик для лейбла (открытие фокуса)
        if (usernameLabel) {
            usernameLabel.style.cursor = "pointer";
            usernameLabel.addEventListener("click", function(e) {
                e.preventDefault();
                usernameInput.focus();
            });
        }

        // Обновление ширины при ресайзе окна
        window.addEventListener("resize", function() {
            dropdown.style.width = usernameInput.offsetWidth + "px";
        });
    });
    </script>

    <style>
    .autocomplete-dropdown div:hover {
        background-color: #f8f9fa !important;
    }
    
    .autocomplete-dropdown div.active {
        background-color: #007bff !important;
        color: white !important;
    }
    </style>';
}













    private function outputHeader(string $title): void
    {
        stdhead($title);
        $this->outputAssets();
    }

    private function outputAssets(): void
    {
        $version = '1813';
        
        echo "
        <!-- Banned Accounts Manager Assets -->
        
        
        <style>
            .ban-manager-card {
                border: none;
                box-shadow: 0 2px 15px rgba(0,0,0,0.08);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .ban-manager-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            }
            .table-hover tbody tr:hover {
                background-color: rgba(0,0,0,0.02);
            }
        </style>";
    }

    private function outputNavigation(string $currentTab): void
{
    // Если пришел 'bans', преобразуем в 'users'
    $activeTab = ($currentTab === 'bans') ? 'users' : $currentTab;
    output_nav_tabs(self::BAN_TABS, $activeTab);
}

    private function outputFooter(): void
    {
        stdfoot();
    }

    private function generateSelectBox(string $name, array $options, $selected = '', array $attributes = []): string
    {
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' '.$key.'="'.htmlspecialchars_uni($value).'"';
        }

        $html = '<select name="'.htmlspecialchars_uni($name).'"'.$attrString.'>';
        foreach ($options as $value => $label) {
            $isSelected = ($value == $selected) ? ' selected' : '';
            $html .= '<option value="'.htmlspecialchars_uni($value).'"'.$isSelected.'>'.htmlspecialchars_uni($label).'</option>';
        }
        $html .= '</select>';

        return $html;
    }

    private function generateTextInput(string $name, string $value = '', array $attributes = []): string
    {
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' '.$key.'="'.htmlspecialchars_uni($value).'"';
        }

        return '<input type="text" name="'.htmlspecialchars_uni($name).'" value="'.htmlspecialchars_uni($value).'"'.$attrString.' />';
    }
	
	
	
	
	/**
 * Custom confirmation dialog for ban actions
 */
private function outputCustomConfirmation(array $user, string $actionUrl, string $cancelUrl, string $title, string $message): void
{
    stdhead('Confirm Action');
    echo "
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1040;
            opacity: 0;
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        .confirmation-modal {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            max-width: 500px;
            width: 100%;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            z-index: 1050;
            opacity: 0;
            animation: modalSlideIn 0.4s ease-out 0.1s forwards;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 1.5rem 2rem;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            border: none;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            gap: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .btn-outline-secondary {
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.2);
            background: #6c757d;
            color: white;
        }
        
        .user-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid #dc3545;
        }
        
        .user-value {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            font-weight: 600;
            color: #dc3545;
            word-break: break-all;
        }
        
        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }
        
        @keyframes modalSlideIn {
            to {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }
        
        .warning-icon {
            font-size: 4rem;
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .user-badge {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }
        
        /* Responsive design */
        @media (max-width: 576px) {
            .confirmation-modal {
                margin: 1rem;
                max-width: calc(100% - 2rem);
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.9);
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin: 0.25rem 0;
                text-align: center;
            }
        }
    </style>

    <!-- Backdrop -->
    <div class='modal-backdrop'></div>
    
    <!-- Modal -->
    <div class='confirmation-modal'>
        <div class='modal-header text-center'>
            <h4 class='modal-title w-100 mb-0'>
                <i class='fas fa-exclamation-triangle me-2'></i>
                {$title}
            </h4>
        </div>
        
        <div class='modal-body text-center'>
            <div class='warning-icon'>
                <i class='fas fa-exclamation-circle'></i>
            </div>
            
            <h5 class='text-dark mb-3'>Are you sure you want to continue?</h5>
            <p class='text-muted mb-4'>{$message}</p>
            
            <div class='user-details text-start'>
                <div class='d-flex justify-content-between align-items-center mb-2'>
                    <span class='text-muted'>Username:</span>
                    <span class='user-badge'>
                        <i class='fas fa-user me-1'></i>
                        User Account
                    </span>
                </div>
                <div class='mb-2'>
                    <span class='text-muted'>User:</span>
                    <div class='user-value mt-1'>".htmlspecialchars_uni($user['username'])."</div>
                </div>
                <div class='small text-muted'>
                    <i class='fas fa-id-card me-1'></i>
                    User ID: ".(int)$user['id']."
                </div>
                <div class='small text-muted'>
                    <i class='fas fa-calendar me-1'></i>
                    Member since: ".($user['regdate'] > 0 ? my_datee('datetime', $user['regdate']) : 'N/A')."
                </div>
            </div>
        </div>
        
        <div class='modal-footer justify-content-center'>
            <form action='{$actionUrl}' method='post' class='d-inline'>
                <input type='hidden' name='my_post_key' value='{$this->mybb->post_code}' />
                <button type='submit' class='btn btn-danger btn-lg pulse'>
                    <i class='fas fa-check me-2'></i>
                    Yes, Continue
                </button>
            </form>
            <a href='{$cancelUrl}' class='btn btn-outline-secondary btn-lg'>
                <i class='fas fa-times me-2'></i>
                Cancel
            </a>
        </div>
    </div>

    <script>
        // Close modal on backdrop click
        document.querySelector('.modal-backdrop').addEventListener('click', function() {
            window.location.href = '{$cancelUrl}';
        });
        
        // Escape key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = '{$cancelUrl}';
            }
        });
        
        // Smooth focus management
        setTimeout(() => {
            document.querySelector('.btn-danger').focus();
        }, 100);
        
        // Add some interactive effects
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Prevent form submission animation issues
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('button');
            btn.innerHTML = '<i class=\"fas fa-spinner fa-spin me-2\"></i>Processing...';
            btn.disabled = true;
        });
    </script>
    ";
    stdfoot();
    exit;
}
	
	
	
	
	
	
	
	

    private function outputErrors(array $errors): void
    {
        echo '
        <div class="alert alert-danger">
            <i class="fa-solid fa-exclamation-triangle me-2"></i>
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-2">';
        foreach ($errors as $error) {
            echo '<li>'.htmlspecialchars_uni($error).'</li>';
        }
        echo '
            </ul>
        </div>';
    }

    
	
	
	private function showPruneConfirmation(array $user): void
{
    
        $this->outputCustomConfirmation(
            $user,
            "index.php?act=banning2&action=prune&uid={$user['id']}",
            "index.php?act=banning2",
            'Confirm Pruning',
            'Are you sure you want to prune all threads and posts by this user? This action cannot be undone and will permanently delete all content created by the user.'
        );
    
}
	
	
	
	
	private function showLiftConfirmation(array $ban, array $user): void
{
   
        $this->outputCustomConfirmation(
            $user,
            "index.php?act=banning2&action=lift&uid={$ban['uid']}",
            "index.php?act=banning2",
            'Confirm Lift Ban',
            'Are you sure you want to lift the ban for this user? The user will be restored to their original user group.'
        );
    
}
	
	



    private function flashSuccess(string $message): void
    {
        flash_message($message, 'success');
    }

    private function flashError(string $message): void
    {
        flash_message($message, 'error');
    }
}

// Initialize and execute the banned accounts manager
try {
    $bannedAccountsManager = new BannedAccountsManager($mybb, $db, $cache, $plugins);
    $bannedAccountsManager->handleRequest();
} catch (Exception $e) {
    error_log("Banned Accounts Manager Error: " . $e->getMessage());
    flash_message('An error occurred while processing your request', 'error');
    admin_redirect("index.php?act=banning2");
}