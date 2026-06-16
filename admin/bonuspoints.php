<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';



final class BonusPointsManager
{
    private const BS_VERSION    = 'v0.3 by xam';
    private const ALLOWED_ACTIONS = [
        'showlist', 'edituser', 'updateuser', 'updatebonussystem',
        'updatebonussystemsave', 'adminpanel', 'add', 'add_save', 'resetall', 'reset'
    ];

    private string $script;

    public function __construct(
        private object $db,
        private string $baseUrl,
        private int    $perPage = 20
    ) {
        $this->script = $_SERVER['SCRIPT_NAME'];
    }

    // ── Router ───────────────────────────────────────────────

    public function handleRequest(): void
    {
        $action = $this->getValidatedAction();
        match ($action) {
            'showlist'             => $this->showUserList(),
            'edituser'             => $this->editUser(),
            'updateuser'           => $this->updateUser(),
            'updatebonussystem'    => $this->updateBonusSystem(),
            'updatebonussystemsave'=> $this->updateBonusSystemSave(),
            'add'                  => $this->addBonus(),
            'add_save'             => $this->addBonusSave(),
            'resetall'             => $this->resetAllPoints(),
            'reset'                => $this->resetPointsForm(),
            default                => $this->showAdminPanel(),
        };
    }

    private function getValidatedAction(): string
    {
        $action = htmlspecialchars((string)($_POST['action'] ?? $_GET['action'] ?? 'adminpanel'));
        return in_array($action, self::ALLOWED_ACTIONS) ? $action : 'adminpanel';
    }
	
	

	

    // ── Shared helpers ───────────────────────────────────────

    private function getTotalUsersWithBonus(): int
    {
        $res = $this->db->sql_query("SELECT COUNT(*) as total FROM users WHERE seedbonus > 0");
        return (int)($this->db->fetch_array($res)['total'] ?? 0);
    }

    private function getPagination(int $total): string
    {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        return multipage($total, $this->perPage, $page, $this->script . '?act=bonuspoints&action=showlist&');
    }

    private function getValidatedUserId(): int
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        return is_valid_id($id) ? $id : 0;
    }

    private function getValidatedBonusId(): int
    {
        return max(0, (int)($_POST['id'] ?? $_GET['id'] ?? 0));
    }

    private function getValidatedUserGroup(): int
    {
        return max(0, (int)($_POST['usergroup'] ?? $_GET['usergroup'] ?? 0));
    }

    private function getUserById(int $id): ?array
    {
        $res = $this->db->sql_query('SELECT id, username, seedbonus FROM users WHERE id = ' . $this->db->sqlesc($id));
        return $this->db->num_rows($res) > 0 ? $this->db->fetch_array($res) : null;
    }

    private function getBonusById(int $id): ?array
    {
        $res = $this->db->sql_query('SELECT * FROM bonus WHERE id = ' . $this->db->sqlesc($id));
        return $this->db->num_rows($res) > 0 ? $this->db->fetch_array($res) : null;
    }

    private function renderHeader(string $title, string $icon): string
    {
        $total = $this->getTotalUsersWithBonus();
        return <<<HTML
<div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded">
    <h2 class="text-primary mb-0"><i class="bi {$icon} me-2"></i>{$title}</h2>
    <span class="badge bg-primary fs-6"><i class="bi bi-people-fill me-1"></i>{$total} Users</span>
</div>
HTML;
    }

    private function renderNavigation(): string
    {
        $s = $this->script;
        return <<<HTML
<div class="mt-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-3">
            <div class="btn-group btn-group-lg" role="group">
                <a href="{$s}?act=bonuspoints"                        class="btn btn-primary"><i class="bi bi-speedometer2 me-2"></i>Admin Panel</a>
                <a href="{$s}?act=bonuspoints&action=add"             class="btn btn-success"><i class="bi bi-plus-circle me-2"></i>Add Bonus</a>
                <a href="{$s}?act=bonuspoints&action=showlist"        class="btn btn-info"><i class="bi bi-people-fill me-2"></i>User List</a>
                <a href="{$s}?act=bonuspoints&action=reset"           class="btn btn-warning"><i class="bi bi-arrow-clockwise me-2"></i>Reset Points</a>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    // ── GB size reference (повторялось в 2 формах) ───────────

    private function renderGbReference(): string
    {
        return <<<HTML
<div class="form-text">
    <i class="bi bi-info-circle me-1"></i><strong>Common values:</strong><br>
    <i class="bi bi-arrow-right me-2"></i>1 GB = 1,073,741,824<br>
    <i class="bi bi-arrow-right me-2"></i>2.5 GB = 2,684,354,560<br>
    <i class="bi bi-arrow-right me-2"></i>5 GB = 5,368,709,120<br>
    <i class="bi bi-arrow-right me-2"></i>10 GB = 10,737,418,240
</div>
HTML;
    }

    // ── Unified bonus form (Add + Edit) ──────────────────────

    private function renderBonusForm(string $action_url, array $data = [], bool $show_delete = false): string
    {
        $name        = htmlspecialchars($data['bonusname']   ?? '');
        $points      = htmlspecialchars((string)($data['points']  ?? ''));
        $description = htmlspecialchars($data['description'] ?? '');
        $menge       = htmlspecialchars((string)($data['menge']   ?? ''));
        $hidden_id   = isset($data['id']) ? '<input type="hidden" name="id" value="' . (int)$data['id'] . '">' : '';
		$hidden_action = htmlspecialchars($data['action'] ?? '');
        $gb_ref      = $this->renderGbReference();

        $delete_block = $show_delete ? '
<div class="alert alert-warning border-warning">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="delete" value="1" id="deleteCheck">
        <label class="form-check-label fw-bold text-danger" for="deleteCheck">
            <i class="bi bi-trash-fill me-1"></i>Delete this bonus item permanently
        </label>
    </div>
</div>' : '';

        $btn_label = $show_delete ? 'Update Bonus' : 'Add Bonus';
        $btn_icon  = $show_delete ? 'bi-check-circle' : 'bi-plus-circle';

        return <<<HTML
<div class="container mt-3">
    <div class="card shadow-sm border-primary">
        <div class="card-body">
            <form method="post" action="{$action_url}">
                <input type="hidden" name="act" value="bonuspoints">
				<input type="hidden" name="action" value="{$hidden_action}">
                {$hidden_id}
				

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="bonusname" class="form-label fw-bold"><i class="bi bi-tag me-1"></i>Bonus Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white"><i class="bi bi-tag"></i></span>
                            <input type="text" class="form-control" id="bonusname" name="bonusname" value="{$name}" placeholder="Enter bonus name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="points" class="form-label fw-bold"><i class="bi bi-coin me-1"></i>Points</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white"><i class="bi bi-coin"></i></span>
                            <input type="number" class="form-control" id="points" name="points" value="{$points}" min="0" step="0.1" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label for="description" class="form-label fw-bold"><i class="bi bi-text-paragraph me-1"></i>Description</label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white"><i class="bi bi-chat-text"></i></span>
                        <textarea class="form-control" id="description" name="description" rows="4" required>{$description}</textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="menge" class="form-label fw-bold"><i class="bi bi-hdd me-1"></i>Amount (bytes)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white"><i class="bi bi-hdd"></i></span>
                        <input type="number" class="form-control" id="menge" name="menge" value="{$menge}" placeholder="e.g. 1073741824">
                    </div>
                    {$gb_ref}
                </div>

                {$delete_block}

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="bi {$btn_icon} me-2"></i>{$btn_label}
                    </button>
                    <a href="{$this->script}?act=bonuspoints" class="btn btn-secondary btn-lg px-4 ms-2">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;
    }

    // ── Actions ──────────────────────────────────────────────

    private function showUserList(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - User List');
        $total = $this->getTotalUsersWithBonus();
        $pager = $this->getPagination($total);
        echo $this->renderHeader('User List', 'bi-people-fill');
        echo $pager;
        echo $this->renderUserTable();
        echo $pager;
        echo $this->renderNavigation();
        stdfoot();
    }

    private function renderUserTable(): string
    {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $start = ($page - 1) * $this->perPage;

        $res  = $this->db->sql_query(
            "SELECT id, username, seedbonus, bonuscomment, uploaded
             FROM users WHERE seedbonus > 0
             ORDER BY seedbonus DESC LIMIT {$start}, {$this->perPage}"
        );

        $rows = '';
        while ($u = $this->db->fetch_array($res)) {
            $link   = $this->baseUrl . '/' . get_profile_link($u['id']);
            $size   = mksize($u['uploaded']);
            $edit   = $this->script . '?act=bonuspoints&action=edituser&id=' . $u['id'];
            $rows  .= <<<HTML
<tr>
    <td class="text-center"><a href="{$link}" class="text-decoration-none"><i class="bi bi-person-badge me-1 text-muted"></i>{$u['id']}</a></td>
    <td class="text-center"><a href="{$link}" class="text-decoration-none fw-bold"><i class="bi bi-person-circle me-1 text-primary"></i>{$u['username']}</a></td>
    <td class="text-center"><span class="badge bg-success fs-6"><i class="bi bi-coin me-1"></i>{$u['seedbonus']} points</span></td>
    <td><textarea class="form-control form-control-sm" rows="2" readonly>{$u['bonuscomment']}</textarea></td>
    <td class="text-end"><i class="bi bi-cloud-upload text-info me-1"></i>{$size}</td>
    <td class="text-center"><a href="{$edit}" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i>Edit</a></td>
</tr>
HTML;
        }

        return <<<HTML
<div class="container mt-3">
    <div class="card shadow-sm border-primary">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="card-title mb-0"><i class="bi bi-people-fill me-2"></i>Users with Bonus Points</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th class="text-center">User ID</th><th class="text-center">Username</th>
                            <th class="text-center">Bonus Points</th><th class="text-center">Comment</th>
                            <th class="text-center">Uploaded</th><th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function editUser(): void
    {
        $user = $this->getUserById($this->getValidatedUserId());
        if (!$user) { stderr('Error', 'User not found!'); return; }

        stdhead('Bonus Points ' . self::BS_VERSION . " - Edit User ({$user['username']})");
        echo $this->renderHeader("Edit User: {$user['username']}", 'bi-person-gear');
        echo $this->renderEditUserForm($user);
        echo $this->renderNavigation();
        stdfoot();
    }

    private function renderEditUserForm(array $user): string
    {
        $link = $this->baseUrl . '/userdetails.php?id=' . $user['id'];
        $s    = $this->script;
        return <<<HTML
<div class="container mt-3">
    <div class="card shadow-sm border-primary">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="card-title mb-0"><i class="bi bi-person-gear me-2"></i>Edit User Points</h5>
        </div>
        <div class="card-body">
            <form method="post" action="{$s}">
                <input type="hidden" name="act" value="bonuspoints">
                <input type="hidden" name="action" value="updateuser">
                <input type="hidden" name="id" value="{$user['id']}">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">User ID</label>
                        <div class="form-control"><a href="{$link}" class="fw-bold">{$user['id']}</a></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Username</label>
                        <div class="form-control"><a href="{$link}" class="fw-bold">{$user['username']}</a></div>
                    </div>
                    <div class="col-md-4">
                        <label for="seedbonus" class="form-label fw-bold"><i class="bi bi-coin me-1"></i>Bonus Points</label>
                        <input type="number" class="form-control form-control-lg" id="seedbonus" name="seedbonus"
                               value="{$user['seedbonus']}" min="0" step="0.1" required>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-check-circle me-2"></i>Update Points
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;
    }

    private function updateUser(): void
    {
        $id    = $this->getValidatedUserId();
        $bonus = (float)($_POST['seedbonus'] ?? 0);
        $ok    = $this->db->sql_query("UPDATE users SET seedbonus = " . $this->db->sqlesc($bonus) . " WHERE id = " . $this->db->sqlesc($id));
        $ok
            ? stdok('User ID: ' . $id . ' successfully updated', 'Success', 'success')
            : stdok('Unable to update user: ' . $id, 'Error', 'error');
    }

    private function showAdminPanel(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - Admin Panel');
        echo $this->renderHeader('Bonus System Configuration', 'bi-gear-fill');
        echo $this->renderBonusList();
        echo $this->renderNavigation();
        stdfoot();
    }

    private function renderBonusList(): string
    {
        $res  = $this->db->sql_query('SELECT * FROM bonus ORDER BY id ASC');
        $rows = '';
        $count = 0;

        while ($b = $this->db->fetch_array($res)) {
            $count++;
            $edit  = $this->script . '?act=bonuspoints&action=updatebonussystem&id=' . $b['id'];
            $rows .= <<<HTML
<tr>
    <td class="text-center fw-bold"><i class="bi bi-hash text-muted me-1"></i>{$b['id']}</td>
    <td class="fw-bold text-primary"><i class="bi bi-tag me-1"></i>{$b['bonusname']}</td>
    <td class="text-center"><span class="badge bg-success fs-6"><i class="bi bi-coin me-1"></i>{$b['points']}</span></td>
    <td><div class="alert alert-info mb-0 p-2 small"><i class="bi bi-info-circle me-1"></i>{$b['description']}</div></td>
    <td class="text-center"><a href="{$edit}" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i></a></td>
</tr>
HTML;
        }

        return <<<HTML
<div class="container mt-3">
    <div class="card shadow-sm border-primary">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="card-title mb-0">
                <i class="bi bi-list-check me-2"></i>Bonus Items
                <span class="badge bg-light text-primary ms-2 fs-6">{$count} Items</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th class="text-center">ID</th><th>Name</th>
                            <th class="text-center">Points</th><th>Description</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function updateBonusSystem(): void
    {
        $bonus = $this->getBonusById($this->getValidatedBonusId());
        if (!$bonus) { stdok('Bonus item not found!', 'Error', 'error'); return; }

        stdhead('Bonus Points ' . self::BS_VERSION . " - Update Bonus ({$bonus['bonusname']})");
        echo $this->renderHeader("Update Bonus: {$bonus['bonusname']}", 'bi-pencil-square');
        echo $this->renderBonusForm(
            $this->script,
            array_merge($bonus, ['action' => 'updatebonussystemsave']),
            true
        );
        echo $this->renderNavigation();
        stdfoot();
    }

    private function updateBonusSystemSave(): void
    {
        $id     = $this->getValidatedBonusId();
        $delete = isset($_POST['delete']) && $_POST['delete'] == '1';
        $sure   = (int)($_GET['sure'] ?? 0);

        if ($delete && !$sure) {
            $url = $this->script . '?act=bonuspoints&action=updatebonussystemsave&id=' . $id . '&sure=1';
            stdok(
                "<div class='text-center'>
                    <i class='bi bi-exclamation-triangle-fill text-warning display-4 mb-3'></i>
                    <h4>Confirm Deletion</h4>
                    <p class='lead'>You are about to delete this bonus item permanently.</p>
                    <div class='mt-4'>
                        <a href='{$url}' class='btn btn-danger btn-lg me-3'><i class='bi bi-trash-fill me-2'></i>Confirm Delete</a>
                        <a href='{$this->script}?act=bonuspoints' class='btn btn-secondary btn-lg'><i class='bi bi-x-circle me-2'></i>Cancel</a>
                    </div>
                </div>",
                'Delete Confirmation', 'confirm'
            );
            return;
        }

        if ($delete && $sure) {
            $ok = $this->db->sql_query('DELETE FROM bonus WHERE id = ' . $this->db->sqlesc($id));
            $ok
                ? stdok('Bonus item successfully deleted.' . $this->renderNavigation(), 'Success', 'success')
                : stdok('Unable to delete bonus item.', 'Error', 'error');
            return;
        }

        $ok = $this->db->sql_query(
            "UPDATE bonus SET
                bonusname   = " . $this->db->sqlesc($_POST['bonusname']   ?? '') . ",
                points      = " . $this->db->sqlesc((float)($_POST['points']      ?? 0)) . ",
                description = " . $this->db->sqlesc($_POST['description'] ?? '') . ",
                menge       = " . $this->db->sqlesc((int)($_POST['menge']         ?? 0)) . "
             WHERE id = " . $this->db->sqlesc($id)
        );

        $ok
            ? stdok('Bonus item successfully updated.' . $this->renderNavigation(), 'Success', 'success')
            : stdok('Unable to update bonus item.', 'Error', 'error');
    }

    private function addBonus(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - Add Bonus');
        echo $this->renderHeader('Add New Bonus Item', 'bi-plus-circle');
        echo $this->renderBonusForm($this->script, ['action' => 'add_save'], false);
        echo $this->renderNavigation();
        stdfoot();
    }

    private function addBonusSave(): void
    {
        $ok = $this->db->sql_query(
            "INSERT INTO bonus (bonusname, points, description, menge, art) VALUES (" .
            $this->db->sqlesc($_POST['bonusname']  ?? '') . ", " .
            $this->db->sqlesc((float)($_POST['points']     ?? 0)) . ", " .
            $this->db->sqlesc($_POST['description'] ?? '') . ", " .
            $this->db->sqlesc((int)($_POST['menge']       ?? 0)) . ", 'traffic')"
        );

        $ok
            ? stdok('New bonus successfully added.' . $this->renderNavigation(), 'Success', 'success')
            : stdok('Unable to add bonus!', 'Error', 'error');
    }

    private function resetPointsForm(): void
    {
        stdhead('Bonus Points ' . self::BS_VERSION . ' - Reset Points');
        echo $this->renderHeader('Reset User Points', 'bi-arrow-clockwise');
        echo $this->renderResetForm();
        echo $this->renderNavigation();
        stdfoot();
    }

    private function renderResetForm(): string
    {
        $groups = _selectbox_('Usergroup', 'usergroup');
        $s = $this->script;
        return <<<HTML
<div class="container mt-3">
    <div class="card shadow-sm border-primary">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="card-title mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Reset Bonus Points</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-danger border-danger">
                <h6 class="alert-heading"><i class="bi bi-exclamation-octagon-fill me-2"></i>Warning!</h6>
                <p class="mb-0">This action cannot be undone. All bonus points will be permanently reset.</p>
            </div>
            <form method="post" action="{$s}">
                <input type="hidden" name="act" value="bonuspoints">
                <input type="hidden" name="action" value="resetall">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold"><i class="bi bi-people-fill me-1"></i>Select User Group</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white"><i class="bi bi-funnel"></i></span>
                            {$groups}
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-danger w-100 py-2"
                                onclick="return confirm('Reset ALL bonus points? This cannot be undone!')">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset Points
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;
    }

    private function resetAllPoints(): void
    {
        $group = $this->getValidatedUserGroup();
        $sure  = (int)($_GET['sure'] ?? 0);

        if (!$sure) {
            $groupName  = $group ? '[' . get_user_class_name($group) . ']' : '[ALL User Groups]';
            $confirmUrl = $this->script . '?act=bonuspoints&action=resetall&sure=1' . ($group ? '&usergroup=' . $group : '');
            stdok(
                "<div class='text-center'>
                    <i class='bi bi-exclamation-triangle-fill text-warning display-4 mb-3'></i>
                    <h4>Reset Confirmation</h4>
                    <p class='lead'>Reset all bonus points for: <b>{$groupName}</b></p>
                    <div class='mt-4'>
                        <a href='{$confirmUrl}' class='btn btn-danger btn-lg me-3'><i class='bi bi-trash-fill me-2'></i>Confirm Reset</a>
                        <a href='{$this->script}?act=bonuspoints' class='btn btn-secondary btn-lg'><i class='bi bi-x-circle me-2'></i>Cancel</a>
                    </div>
                </div>",
                'Reset Confirmation', 'confirm'
            );
            return;
        }

        $sql = "UPDATE users SET seedbonus = 0.0 WHERE enabled = 'yes' AND ustatus = 'confirmed'";
        if ($group) $sql .= " AND usergroup = " . $this->db->sqlesc($group);

        $ok = $this->db->sql_query($sql);
        $ok
            ? stdok('All bonus points have been successfully reset.' . $this->renderNavigation(), 'Success', 'success')
            : stdok('Unable to reset bonus points.' . $this->renderNavigation(), 'Error', 'error');
    }
}

$bonusManager = new BonusPointsManager($db, $BASEURL);
$bonusManager->handleRequest();