<?php

declare(strict_types=1);


class InviteSystem
{
    
	
	

public static function checkAmount(int $uid): bool
{
    global $db;
    
    $query = $db->sql_query_prepared(
        "SELECT invites FROM users WHERE id = ?", 
        [$uid]
    );
    
    if ($db->num_rows($query) === 0) {
        return false;
    }

    $amount = $db->fetch_array($query);
    return (int)$amount['invites'] >= 1;
}

public static function getInviteAmount(int $uid): string
{
    global $db;
    
    $query = $db->sql_query_prepared(
        "SELECT invites FROM users WHERE id = ?", 
        [$uid]
    );
    
    $amount = $db->fetch_array($query);
    $invites = (int)$amount['invites'];
    
    $color = match (true) {
        $invites <= 2 => 'danger',
        $invites <= 5 => 'warning',
        default => 'success'
    };
    
    return "<span class='badge bg-$color'>$invites</span>";
}

public static function isEmailExists(string $email): bool
{
    global $db;
    
    // Check users table
    $query1 = $db->sql_query_prepared(
        "SELECT email FROM users WHERE email = ?", 
        [$email]
    );
    
    if ($db->num_rows($query1) >= 1) {
        return false;
    }

    // Check invites table
    $query2 = $db->sql_query_prepared(
        "SELECT invitee FROM invites WHERE invitee = ?", 
        [$email]
    );
    
    return $db->num_rows($query2) < 1;
}
	
	
	
	
	
	
	
	
	

    public static function sanitizeEmail(string $email): string
    {
        return str_replace(['<', '>', '\\\'', '\\"', '\\\\'], '', $email);
    }

    public static function generateInviteHash(): string
    {
        return substr(md5(uniqid((string)random_int(1, 1000000), true)), 0, 32);
    }
}

class InviteRenderer
{
    public static function renderHeader(): void
    {
        global $lang;
        
        echo '
        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-envelope-plus me-2"></i>' . $lang->invite['head'] . '
                </h1>
                <a href="invite.php?action=send" class="btn btn-primary btn-lg">
                    <i class="bi bi-plus-circle me-2"></i>' . $lang->invite['button'] . '
                </a>
            </div>
        </div>';
    }

    




public static function renderUserStats(int $inviterid): void
{
    global $lang, $db;
    
    // Прямой подсчет через подготовленный запрос
    $query = $db->sql_query_prepared(
        "SELECT COUNT(*) as count FROM users WHERE invited_by = ?", 
        [$inviterid]
    );
    
    $row = $db->fetch_array($query);
    $number = (int)$row['count'];
    
    echo '
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people-fill me-2"></i>' . $lang->invite['status'] . '
                    </h5>
                    <span class="badge bg-light text-primary fs-6">' . $number . '</span>
                </div>
            </div>
        </div>
    </div>';
}
	
	
	
	
	
	
	
	
public static function renderPendingInvites(int $inviterid): void
{
    global $db, $lang, $BASEURL, $pic_base_url, $dateformat, $timeformat;

    // Получаем ожидающие приглашения через подготовленный запрос
    $query = $db->sql_query_prepared(
        "SELECT id, invitee, hash, time_invited FROM invites WHERE inviter = ?", 
        [$inviterid]
    );
    
    $num1 = $db->num_rows($query);
    
    // Подсчет через подготовленный запрос
    $countQuery = $db->sql_query_prepared(
        "SELECT COUNT(*) as count FROM invites WHERE inviter = ?", 
        [$inviterid]
    );
    $countRow = $db->fetch_array($countQuery);
    $number1 = (int)$countRow['count'];

    echo '
    <div class="container mt-5">
        <div class="card border-0 mb-4">
            <div class="card-header bg-primary text-light py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>' . $lang->invite['status3'] . '
                    </h5>
                    <span class="badge bg-light text-primary fs-6">' . $number1 . '</span>
                </div>
            </div>
        </div>
    </div>';

    if ($num1 === 0) {
        echo '
        <div class="container">
            <div class="card border-0">
                <div class="card-body text-center py-5">
                    <i class="bi bi-envelope-x display-1 text-muted mb-3"></i>
                    <h4 class="text-muted">' . $lang->invite['nooutyet'] . '</h4>
                    <p class="text-muted">No pending invitations found.</p>
                </div>
            </div>
        </div>';
        return;
    }

    echo '
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
                    <input type="hidden" name="action" value="delete">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">' . $lang->invite['email'] . '</th>
                                    <th>' . $lang->invite['hash'] . '</th>
                                    <th>' . $lang->invite['senddate'] . '</th>
                                    <th>' . $lang->invite['invitedeadtime'] . '</th>
                                    <th class="pe-4 text-center">' . $lang->invite['action'] . '</th>
                                </tr>
                            </thead>
                            <tbody>';

    require_once INC_PATH . '/readconfig_cleanup.php';
    
    // ИСПРАВЛЕНИЕ: используем $query вместо $rer
    while ($arr1 = $db->fetch_array($query)) {
        $timeout = $arr1['time_invited'] + 172800;
        $timeoutDate = my_datee($dateformat, $timeout) . ' ' . my_datee($timeformat, $timeout);
        $sendDate = my_datee($dateformat, $arr1['time_invited']) . ' ' . my_datee($timeformat, $arr1['time_invited']);
        $manualLink = strip_tags(sprintf($lang->invite['manuellink'], $BASEURL, $arr1['hash']));

        echo '
                                <tr>
                                    <td class="ps-4">' . $arr1['invitee'] . '</td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <code class="text-muted">' . $arr1['hash'] . '</code>
                                            <a href="#" onclick="javascript:prompt(\'' . $manualLink . '\',\'' . $BASEURL . '/signup.php?invitehash=' . $arr1['hash'] . '&type=invite\'); return false;" 
                                               class="btn btn-sm btn-outline-primary ms-2" title="' . $lang->invite['hash'] . '">
                                                <i class="bi bi-link-45deg"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <td><small>' . $sendDate . '</small></td>
                                    <td><small class="text-danger">' . $timeoutDate . '</small></td>
                                    <td class="pe-4 text-center">
                                        <input type="checkbox" class="form-check-input" name="id[]" value="' . $arr1['id'] . '">
                                    </td>
                                </tr>';
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light py-3">
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash me-2"></i>' . $lang->invite['actionbutton'] . '
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>';
}
	
	
	



	
	

    public static function renderUsersTable(int $inviterid, bool $is_mod): void
    {
        global $db, $lang;
        
        require_once INC_PATH . '/functions_icons.php';
        require_once INC_PATH . '/functions_ratio.php';

        $ret = $db->sql_query('
            SELECT u.id, u.username, u.email, u.uploaded, u.lastactive, u.last_login, 
                   u.added, u.downloaded, u.ustatus, u.warned, u.leechwarn, u.enabled, 
                   u.donor, p.canupload, p.candownload, p.cancomment, g.namestyle 
            FROM users u 
            LEFT JOIN usergroups g ON (u.usergroup = g.gid) 
            LEFT JOIN ts_u_perm p ON (u.id = p.userid) 
            WHERE u.invited_by = ' . $db->sqlesc($inviterid)
        );

        if ($db->num_rows($ret) === 0) {
            echo '
            <div class="container mt-4">
                <div class="card border-0">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-people display-1 text-muted mb-3"></i>
                        <h4 class="text-muted">' . $lang->invite['noinvitesyet'] . '</h4>
                        <p class="text-muted">No users have been invited yet.</p>
                    </div>
                </div>
            </div>';
            return;
        }

        echo '
        <div class="container mt-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">' . $lang->invite['username'] . '</th>
                                    <th>' . $lang->invite['email'] . '</th>
                                    <th>' . $lang->invite['added'] . '</th>
                                    <th>' . $lang->invite['lastseen'] . '</th>
                                    <th>' . $lang->invite['uploaded'] . '</th>
                                    <th>' . $lang->invite['downloaded'] . '</th>
                                    <th>' . $lang->invite['ratio'] . '</th>
                                    <th class="pe-4">' . $lang->invite['status2'] . '</th>
                                </tr>
                            </thead>
                            <tbody>';

        while ($arr = $db->fetch_array($ret)) {
            echo self::renderUserRow($arr, $is_mod);
        }

        echo '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>';
    }

    private static function renderUserRow(array $user, bool $is_mod): string
    {
        global $lang, $dateformat, $timeformat;

        $username = get_user_color($user['username'], $user['namestyle']);
        $registered = my_datee($dateformat, $user['added']) . '<br><small class="text-muted">' . my_datee($timeformat, $user['added']) . '</small>';
        $lastSeen = self::getLastSeen($user, $is_mod);
        $ratio = self::calculateRatio((int)$user['uploaded'], (int)$user['downloaded']);
        $status = self::getUserStatus($user);

        return '
        <tr>
            <td class="ps-4">' . self::getUserLink($user, $username) . '</td>
            <td><a href="mailto:' . $user['email'] . '" class="text-decoration-none">' . $user['email'] . '</a></td>
            <td><small>' . $registered . '</small></td>
            <td><small>' . $lastSeen . '</small></td>
            <td><span class="text-success">' . mksize($user['uploaded']) . '</span></td>
            <td><span class="text-danger">' . mksize($user['downloaded']) . '</span></td>
            <td>' . $ratio . '</td>
            <td class="pe-4">' . $status . '</td>
        </tr>';
    }

    private static function getLastSeen(array $user, bool $is_mod): string
    {
        global $lang, $dateformat, $timeformat;

        $lastseen = $user['lastactive'];
        if (preg_match('#B1#is', $user['options'] ?? '') && !$is_mod) {
            $lastseen = $user['last_login'];
        }

        if ($lastseen === '0000-00-00 00:00:00' || $lastseen === '-') {
            return '<span class="badge bg-secondary">' . $lang->invite['never'] . '</span>';
        }

        return my_datee($dateformat, $lastseen) . '<br><small class="text-muted">' . my_datee($timeformat, $lastseen) . '</small>';
    }

    private static function getUserLink(array $user, string $username): string
    {
        if ($user['ustatus'] === 'pending') {
            return '<a href="checkuser.php?id=' . $user['id'] . '" class="text-decoration-none">' . $username . '</a>';
        }

        require_once INC_PATH . '/functions_icons.php';
        $icons = get_user_icons($user);
        return '<a href="' . get_profile_link($user['id']) . '" class="text-decoration-none fw-bold">' . $username . '</a>' . $icons;
    }

    private static function calculateRatio(int $uploaded, int $downloaded): string
    {
        if ($downloaded > 0) {
            $ratio = number_format($uploaded / $downloaded, 2);
            $color = get_ratio_color((float)$ratio);
            return '<span class="badge bg-' . ($ratio >= 1.0 ? 'success' : 'warning') . '">' . $ratio . '</span>';
        }

        return $uploaded > 0 ? '<span class="badge bg-info">Inf.</span>' : '<span class="badge bg-secondary">---</span>';
    }

    private static function getUserStatus(array $user): string
    {
        global $lang;

        if ($user['ustatus'] === 'confirmed') {
            return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>' . $lang->invite['confirmed'] . '</span>';
        }

        return '<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>' . $lang->invite['pending'] . '</span>';
    }
}

class InviteForms
{
    public static function renderTypeSelection(): void
    {
        global $lang;
        
        echo '
        <div class="container mt-3">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="card-title mb-0 text-center">
                                <i class="bi bi-send-check me-2"></i>' . $lang->invite['selecttype'] . '
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
                                <input type="hidden" name="action" value="send">
                                
                                <div class="mb-4">
                                    <label class="form-label fs-5 fw-semibold">' . $lang->invite['selecttype'] . '</label>
                                    <select name="type" class="form-select form-select-lg" required>
                                        <option value="">' . $lang->invite['selecttype'] . '</option>
                                        <option value="email">' . $lang->invite['type1'] . '</option>
                                        <option value="manual">' . $lang->invite['type2'] . '</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg py-2">
                                        <i class="bi bi-arrow-right-circle me-2"></i>' . $lang->invite['typebutton'] . '
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    public static function renderEmailForm(int $inviterid): void
    {
        global $lang;
        
        echo '
        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-success text-white py-3">
                            <h5 class="card-title mb-0 text-center">
                                <i class="bi bi-envelope-at me-2"></i>' . $lang->invite['type1'] . '
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '" name="sendinvite">
                                <input type="hidden" name="action" value="sendinvite">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">' . $lang->invite['field1'] . '</label>
                                    <input type="email" name="email" class="form-control form-control-lg" 
                                           placeholder="friend@example.com" required>
                                    <div class="form-text text-danger mt-2">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        <strong>' . $lang->invite['field2'] . '</strong>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">' . $lang->invite['field3'] . '</label>
                                    <textarea name="note" class="form-control" rows="6" 
                                              placeholder="' . $lang->invite['default_invite_msg'] . '">' . $lang->invite['default_invite_msg'] . '</textarea>
                                </div>
                                
                                <div class="alert alert-info">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2 fs-4"></i>
                                        <div>
                                            ' . sprintf($lang->invite['field4'], InviteSystem::getInviteAmount($inviterid)) . '
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-success btn-lg px-4">
                                        <i class="bi bi-send-check me-2"></i>' . $lang->invite['button2'] . '
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary btn-lg px-4">
                                        <i class="bi bi-arrow-clockwise me-2"></i>' . $lang->invite['button3'] . '
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    public static function renderManualInvite(string $hash): void
    {
        global $lang, $BASEURL;
        
        $inviteUrl = $BASEURL . '/signup.php?invitehash=' . $hash . '&type=invite';
        
        echo '
        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-warning text-dark py-3">
                            <h5 class="card-title mb-0 text-center">
                                <i class="bi bi-link-45deg me-2"></i>' . $lang->invite['type2'] . '
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <i class="bi bi-check-circle-fill text-success display-1"></i>
                                <h3 class="mt-3 text-success">Invite Created Successfully!</h3>
                                <p class="lead">Share this link with your friend to invite them to join.</p>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Invitation URL</label>
                                <div class="input-group input-group-lg">
                                    <input type="text" class="form-control" value="' . $inviteUrl . '" id="inviteUrl" readonly>
                                    <button class="btn btn-outline-primary" type="button" onclick="copyInviteUrl()">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text">Click the copy button to copy the invitation link to your clipboard.</div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-clock me-2 fs-4"></i>
                                    <div>
                                        <strong>Note:</strong> This invitation will expire in 48 hours.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <a href="invite.php" class="btn btn-primary btn-lg px-4">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Invites
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function copyInviteUrl() {
            const input = document.getElementById("inviteUrl");
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(() => {
                const btn = event.target;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = \'<i class="bi bi-check"></i>\';
                btn.classList.remove("btn-outline-primary");
                btn.classList.add("btn-success");
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove("btn-success");
                    btn.classList.add("btn-outline-primary");
                }, 2000);
            });
        }
        </script>';
    }

    public static function renderSystemAlert(): void
    {
        global $lang;
        
        echo '
        <div class="container mt-4">
            <div class="alert alert-warning border-0 shadow-sm">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-3 fs-2"></i>
                    <div>
                        <h5 class="alert-heading mb-2">Invite System Disabled</h5>
                        <p class="mb-0">' . $lang->invite['alert'] . '</p>
                    </div>
                </div>
            </div>
        </div>';
    }
}

// Main execution
define("IN_MYBB", 1);
require_once 'global.php';

if (!isset($CURUSER) || ($CURUSER["id"] ?? 0) === 0) {
    print_no_permission();
}

gzip();
maxsysop();
define('I_VERSION', '1.2');
require INC_PATH . '/readconfig.php';

$lang->load('invite');
$action = htmlspecialchars($_POST['action'] ?? $_GET['action'] ?? 'main');
$type = htmlspecialchars($_POST['type'] ?? $_GET['type'] ?? '');
$is_mod = is_mod($usergroups);

stdhead($lang->invite['head'], true, 'collapse');

// Determine inviter
if (isset($_GET['id']) && is_valid_id($_GET['id']) && ($is_mod || $usergroups['canuserdetails'] === 'yes')) {
    $inviterid = (int)$_GET['id'];
    $ra = $db->sql_query('SELECT username FROM users WHERE id = ' . $db->sqlesc($inviterid));
    $raa = $db->fetch_array($ra);
    $invitername = htmlspecialchars(trim($raa['username']));
} else {
    $inviterid = (int)$CURUSER['id'];
    $invitername = htmlspecialchars(trim($CURUSER['username']));
}

// Handle actions
match ($action) {
    'delete' => handleDeleteAction($inviterid),
    'send' => showSendPage($inviterid, $invitername, $is_mod, $type),
    'sendinvite' => handleSendInvite($inviterid, $invitername, $is_mod),
    default => showMainPage($inviterid, $invitername, $is_mod)
};

stdfoot();

// Action handlers
function handleDeleteAction(int $inviterid): void
{
    global $db;
    
    $deleteids = $_POST['id'] ?? [];
    if (!empty($deleteids) && is_array($deleteids)) {
        $validIds = array_filter($deleteids, 'is_valid_id');
        if (!empty($validIds)) {
            $ids = implode(',', $validIds);
            $db->sql_query('DELETE FROM invites WHERE id IN (' . $ids . ') AND inviter = ' . $db->sqlesc($inviterid));
        }
    }
    
    showMainPage($inviterid, '', true);
}

function showMainPage(int $inviterid, string $invitername, bool $is_mod): void
{
    InviteRenderer::renderHeader();
    InviteRenderer::renderUserStats($inviterid);
    InviteRenderer::renderUsersTable($inviterid, $is_mod);
	InviteRenderer::renderPendingInvites($inviterid); // Добавляем этот вызов
}

function showSendPage(int $inviterid, string $invitername, bool $is_mod, string $type): void
{
    global $invitesystem, $lang;
    
    // Check if user has invites
    if (!InviteSystem::checkAmount($inviterid)) {
        error($lang->invite['noinvitesleft']);
        return;
    }
    
    // Check if invite system is enabled
    if ($invitesystem === 'off' && !$is_mod) {
        error($lang->invite['invitesystemoff']);
        return;
    }
    
    // Show alert for moderators when system is off
    if ($invitesystem === 'off' && $is_mod) {
        InviteForms::renderSystemAlert();
    }
    
    // Render appropriate form based on type
    match ($type) {
        'email' => InviteForms::renderEmailForm($inviterid),
        'manual' => createManualInvite($inviterid),
        default => InviteForms::renderTypeSelection()
    };
	
	
	
}







function createManualInvite(int $inviterId): void
{
    global $db, $lang, $BASEURL;

    try {
       
		$hash = InviteSystem::generateInviteHash();
        $timeInvited = TIMENOW;

        $insertData = [
            'inviter' => $db->escape_string($inviterId),
            'invitee' => 'manual',
            'hash' => $db->escape_string($hash),
            'time_invited' => $db->escape_string($timeInvited)
        ];

        $db->insert_query('invites', $insertData);

        if ($db->affected_rows() !== 1) {
            throw new Exception($lang->invite['error']);
        }

        $db->sql_query('UPDATE users SET invites = invites - 1 WHERE id = ' . $db->sqlesc($inviterId));

        if ($db->affected_rows() !== 1) {
            throw new Exception($lang->invite['error']);
        }

        ?>
        <div class="container mt-4">
            <div class="alert alert-success">
                <?php echo sprintf($lang->invite['manuellink'], $BASEURL, htmlspecialchars($hash)); ?>
            </div>
        </div>
        <?php
        stdfoot();
        exit;
    } catch (Exception $e) {
        error($e->getMessage());
    }
}






function handleSendInvite(int $inviterid, string $invitername, bool $is_mod): void
{
    global $db, $lang, $SITENAME, $BASEURL, $invitesystem;

    if ($invitesystem === 'off' && !$is_mod) {
        error($lang->invite['invitesystemoff']);
        return;
    }

    if (!InviteSystem::checkAmount($inviterid)) {
        error($lang->invite['noinvitesleft']);
        return;
    }

    $email = htmlspecialchars_uni(InviteSystem::sanitizeEmail($_POST['email'] ?? ''));
    if (!check_email($email)) {
        error($lang->invite['invalidemail']);
        return;
    }

    if (!InviteSystem::isEmailExists($email)) {
        error($lang->invite['invalidemail2']);
        return;
    }

    $note = htmlspecialchars_uni($_POST['note'] ?? $lang->invite['nonote']);
    $subject = sprintf($lang->invite['subject'], $SITENAME);
    $time_invited = TIMENOW;
    $invitehash = InviteSystem::generateInviteHash();

    require_once INC_PATH . '/readconfig_cleanup.php';
    $message = sprintf($lang->invite['message'], $invitername, $SITENAME, $BASEURL, $invitehash, 2, $note);

    $insert_invite = [
        "inviter" => $db->escape_string($inviterid),
        "invitee" => $db->escape_string($email),
        "hash" => $db->escape_string($invitehash),
        "time_invited" => $db->escape_string($time_invited)
    ];

    $db->insert_query("invites", $insert_invite);
    
    if ($db->affected_rows() !== 1) {
        error($lang->invite['error']);
        return;
    }

    $db->sql_query('UPDATE users SET invites = invites - 1 WHERE id = ' . $db->sqlesc($inviterid));
    
    if ($db->affected_rows() !== 1) {
        error($lang->invite['error']);
        return;
    }

    my_mail($email, $subject, $message);
    
    // Show success message
    echo '
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white py-3">
                        <h5 class="card-title mb-0 text-center">
                            <i class="bi bi-check-circle me-2"></i>Success!
                        </h5>
                    </div>
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-envelope-check display-1 text-success mb-3"></i>
                        <h4 class="text-success">' . sprintf($lang->invite['sent'], $email) . '</h4>
                        <p class="text-muted">Your invitation has been sent successfully.</p>
                        <a href="invite.php" class="btn btn-primary btn-lg mt-3">
                            <i class="bi bi-arrow-left me-2"></i>Back to Invites
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    stdfoot();
    exit;
}

?>