<?php


declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger text-center" style="font-family: system-ui, -apple-system, sans-serif; font-size: 1rem; color: #dc2626;">
            <strong>🚫 Access Denied!</strong> Direct access to this file is prohibited.
          </div>');
}

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger text-center" style="font-family: system-ui, -apple-system, sans-serif; font-size: 1rem; color: #dc2626;">
            <strong>🚫 Access Denied!</strong> You do not have permission to access this page.
          </div>');
}

const AB_VERSION = 'Enhanced Amountbonus Module v0.8.5';
const EOL = PHP_EOL;

// ── AJAX: карточка пользователя с текущим балансом ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = $db->sql_query_prepared(
        "SELECT u.id, u.username, u.usergroup, u.seedbonus, u.avatar, u.avatardimensions, g.title AS gtitle
         FROM users u LEFT JOIN usergroups g ON g.gid = u.usergroup WHERE u.username = ? LIMIT 1",
        [trim((string)$_GET['lookup'])]
    );
    $u = $q ? $db->fetch_array($q) : null;
    if (!$u) { echo json_encode(['found' => false]); exit; }
    $av = function_exists('format_avatar') ? format_avatar($u['avatar'] ?? '', $u['avatardimensions'] ?? '') : [];
    echo json_encode([
        'found'     => true,
        'id'        => (int)$u['id'],
        'username'  => (string)$u['username'],
        'name_html' => format_name(htmlspecialchars_uni($u['username']), (int)$u['usergroup']),
        'group'     => (string)($u['gtitle'] ?? ''),
        'bonus'     => (float)$u['seedbonus'],
        'avatar'    => (!empty($av['image']) && empty($av['is_placeholder'])) ? $av['image'] : '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Group IDs that must never be targeted by bulk distribution (Moderator, Administrator, Sysop — canstaffpanel=1)
const PROTECTED_BULK_GROUPS = [6, 7, 8];

global $mybb;

/**
 * Process bonus points distribution
 */
function processBonusDistribution(): bool
{
    global $db, $CURUSER, $BASEURL, $mybb;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    
    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        displayError('⚠️ Invalid security token. Please refresh the page and try again.');
        return false;
    }
    
    // Validate and sanitize input with PHP 8.x features
    $seedbonus = filter_input(INPUT_POST, 'seedbonus', FILTER_VALIDATE_INT, 
        ['options' => ['min_range' => 1, 'max_range' => 1000000]]
    );
    
    $username = trim($_POST['username'] ?? '');
    $toAll = $_POST['toall'] ?? '';
    $usergroup = filter_input(INPUT_POST, 'usergroup', FILTER_VALIDATE_INT) ?: null;
    
    if ($seedbonus === false || $seedbonus === null) {
        displayError('❌ Please enter a valid number between 1 and 1,000,000 for bonus points.');
        return false;
    }
    
    $timestamp = gmdate('Y-m-d H:i:s');
    $moderatorName = $CURUSER['username'] ?? 'System';
    
    // Prepare modcomment using sprintf
    $modcomment = sprintf(
        "%s - Received %d bonus points from %s (Amount Bonus Tool)" . EOL,
        $timestamp,
        $seedbonus,
        $moderatorName
    );
    
    try {
        if ($toAll === 'yes') {
            if ($usergroup !== null && $usergroup > 0 && in_array($usergroup, PROTECTED_BULK_GROUPS, true)) {
                displayError('🚫 Bulk distribution to staff or administrative groups is not allowed.');
                return false;
            }
            distributeToAll($seedbonus, $usergroup, $modcomment, $moderatorName);
        } elseif ($username !== '') {
            distributeToUser($seedbonus, $username, $modcomment, $moderatorName);
        } else {
            displayError('Please specify either a username or select "All Users".');
            return false;
        }
        return true;
    } catch (InvalidArgumentException | RuntimeException $e) {
        // Known, safe-to-display validation/business-logic errors
        displayError('❌ ' . $e->getMessage()); // экранируется в renderFlashMessage()
        return false;
    } catch (Throwable $e) {
        // Unexpected/internal errors — don't leak details to the browser
        displayError('⚠️ An unexpected error occurred. Please try again.');
        return false;
    }
}

/**
 * Distribute bonus points to all users or specific group
 */
function distributeToAll(int $points, ?int $group, string $comment, string $moderator): void
{
    global $db;
    
    if ($group !== null && $group > 0 && in_array($group, PROTECTED_BULK_GROUPS, true)) {
        throw new InvalidArgumentException('Bulk distribution to staff or administrative groups is not allowed.');
    }
    
    // Build WHERE clause
    $whereClause = "WHERE ustatus = 'confirmed'";
    $targetDescription = 'All confirmed users';
    $params = [$points, $comment];

    if ($group > 0) {
        $whereClause .= " AND usergroup = ?";
        $params[] = $group;
        // (string): get_user_class_name(string $class) — int давал TypeError в strict_types
        $targetDescription = (get_user_class_name((string)$group) ?: 'Group ' . $group) . ' group';
    }

    // Using prepared statement
    $query = "UPDATE users SET seedbonus = seedbonus + ?, modcomment = CONCAT(?, modcomment) $whereClause";

    if (!$db->sql_query_prepared($query, $params)) {
        throw new RuntimeException('Failed to update user records.');
    }
    
    logAction(
        message: "$points bonus points distributed to $targetDescription by $moderator",
        type: 'BULK_DISTRIBUTION'
    );
    
    displaySuccess("✅ $points bonus points have been successfully sent to $targetDescription.");
}

/**
 * Distribute bonus points to specific user
 */
function distributeToUser(int $points, string $username, string $comment, string $moderator): void
{
    global $db, $BASEURL;
    
    if ($username === '') {
        throw new InvalidArgumentException('Please enter a username.');
    }
    
    // Update user's bonus points - using prepared statement
    $updateQuery = "UPDATE users SET seedbonus = seedbonus + ?, modcomment = CONCAT(?, modcomment) WHERE username = ?";

    if (!$db->sql_query_prepared($updateQuery, [$points, $comment, $username])) {
        throw new RuntimeException('Failed to update user account.');
    }
    
    // Check if user was updated
    if ($db->affected_rows() === 0) {
        throw new RuntimeException("User '{$username}' not found.");
    }
    
    // Get user ID for redirection
    $selectQuery = "SELECT id, username FROM users WHERE username = ? LIMIT 1";

    $result = $db->sql_query_prepared($selectQuery, [$username]);
    $userData = $result ? $db->fetch_array($result) : null;
    
    if (!$userData) {
        throw new RuntimeException('Failed to retrieve user information.');
    }
    
    logAction(
        message: "$points bonus points sent to $username by $moderator",
        type: 'INDIVIDUAL_DISTRIBUTION'
    );
    
    // Redirect to user profile with modern header syntax
    header("Location: {$BASEURL}/" . get_profile_link($userData['id']));
    exit;
}

/**
 * Store a flash message to be rendered later, in the correct place in the page layout
 * (echoing directly here would run before stdhead() and break the page structure)
 */
function setFlashMessage(string $type, string $message): void
{
    $GLOBALS['_bonus_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Render the stored flash message (call this from within the page layout, after stdhead())
 */
function renderFlashMessage(): void
{
    $flash = $GLOBALS['_bonus_flash'] ?? null;
    if (!$flash) {
        return;
    }
    // Текст приходит из cookie после редиректа — всегда экранируем при выводе
    $flash['message'] = htmlspecialchars((string)$flash['message'], ENT_QUOTES);

    if ($flash['type'] === 'error') {
        echo <<<HTML
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                    <div class="flex-grow-1">
                        <strong>Error:</strong> {$flash['message']}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        HTML;
    } else {
        echo <<<HTML
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-3 fs-4"></i>
                    <div class="flex-grow-1">
                        <strong>Success!</strong> {$flash['message']}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        HTML;
    }
}

/**
 * Display error message
 */
function displayError(string $message): void
{
    setFlashMessage('error', $message);
}

/**
 * Display success message
 */
function displaySuccess(string $message): void
{
    setFlashMessage('success', $message);
}

/**
 * Log action to system log
 */
function logAction(string $message, string $type = 'INFO'): void
{
    // Using write_log function from your system
    if (function_exists('write_log')) {
        write_log($message);
    }
}

/**
 * Generate user group select box with modern PHP
 */
function generateGroupSelect(string $name = 'usergroup'): string
{
    $groups = [
        '' => '👥 All User Groups',
        2 => '👤 User',
        3 => '⚡ Power User',
        4 => '⭐ VIP',
        5 => '📤 Uploader',
        6 => '🛡️ Moderator',
        7 => '👑 Administrator',
        8 => '🔧 Sysop',
    ];
    
    $html = '<select name="' . htmlspecialchars($name) . '" class="form-select form-select-lg">';
    
    foreach ($groups as $value => $label) {
        $isProtected = $value !== '' && in_array($value, PROTECTED_BULK_GROUPS, true);
        
        $valueAttr = $value !== '' ? 'value="' . htmlspecialchars((string)$value) . '"' : '';
        $selected = $value === '' ? ' selected' : '';
        $disabledAttr = $isProtected ? ' disabled' : '';
        $displayLabel = $isProtected ? $label . ' (staff — protected)' : $label;
        
        $html .= sprintf(
            '<option %s%s%s>%s</option>',
            $valueAttr,
            $selected,
            $disabledAttr,
            htmlspecialchars($displayLabel)
        );
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Display usage statistics (new feature)
 */
function displayStatistics(): void
{
    global $db;
    
    $query = "SELECT COUNT(*) as total_users, SUM(seedbonus) as total_bonus, AVG(seedbonus) as avg_bonus FROM users WHERE ustatus = 'confirmed'";
    $result = $db->sql_query_prepared($query);
    $stats = $result ? $db->fetch_array($result) : null;
    
    if (!$stats) {
        return;
    }
    
    $totalUsers = number_format((int)($stats['total_users'] ?? 0));
    $totalBonus = number_format((float)($stats['total_bonus'] ?? 0));
    $avgBonus   = number_format((float)($stats['avg_bonus'] ?? 0));
    
    $cards = [
        ['icon' => 'fa-users',       'color' => '#3b82f6', 'label' => 'Confirmed Users',   'value' => $totalUsers],
        ['icon' => 'fa-coins',       'color' => '#f59e0b', 'label' => 'Total Bonus Points','value' => $totalBonus],
        ['icon' => 'fa-chart-line',  'color' => '#22c55e', 'label' => 'Avg. per User',     'value' => $avgBonus],
    ];
    
    echo '<div class="row g-3 mb-4">';
    foreach ($cards as $c) {
        echo <<<HTML
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                             style="width:48px;height:48px;background:{$c['color']}1a;">
                            <i class="fa-solid {$c['icon']}" style="color:{$c['color']};font-size:20px;"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold lh-1">{$c['value']}</div>
                            <div class="text-secondary" style="font-size:13px;">{$c['label']}</div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
    echo '</div>';
}

/**
 * Группы из базы (раньше — зашитый список 2…8, который мог разойтись с реальными группами).
 * Защищёнными считаются группы с доступом к стафф-панели / настройкам / супермодераторы.
 */
function loadBonusGroups(): array
{
    global $db;
    $groups = [];
    $q = $db->sql_query_prepared("
        SELECT g.gid, g.title, g.canstaffpanel, g.cansettingspanel, g.issupermod, g.isbannedgroup,
               (SELECT COUNT(*) FROM users u WHERE u.usergroup = g.gid AND u.ustatus = 'confirmed') AS members
        FROM usergroups g
        ORDER BY g.gid
    ");
    while ($q && ($g = $db->fetch_array($q))) {
        $gid = (int)$g['gid'];
        $groups[$gid] = [
            'title'     => (string)$g['title'],
            'members'   => (int)$g['members'],
            'protected' => in_array($gid, PROTECTED_BULK_GROUPS, true)
                        || (int)$g['canstaffpanel'] === 1 || (int)$g['cansettingspanel'] === 1 || (int)$g['issupermod'] === 1,
            'banned'    => (int)$g['isbannedgroup'] === 1,
        ];
    }
    return $groups;
}

/** Последние начисления этим инструментом (из site log) */
function loadRecentBonusLog(int $limit = 8): array
{
    global $db;
    $rows = [];
    $q = $db->sql_query_prepared(
        "SELECT txt, added FROM sitelog WHERE txt LIKE ? OR txt LIKE ? ORDER BY added DESC LIMIT " . (int)$limit,
        ['% bonus points sent to % by %', '% bonus points distributed to % by %']
    );
    while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;
    return $rows;
}

// ── Post/Redirect/Get ──────────────────────────────────────────────────
// После POST сохраняем flash в короткоживущую cookie и делаем 303 на GET,
// чтобы F5 не отправлял форму повторно (раньше бонусы начислялись снова).
const AB_FLASH_COOKIE = 'ab_flash';

function abCookieOptions(int $expires): array
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    return ['expires' => $expires, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'];
}

function abRedirectUrl(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri === '' || $uri[0] !== '/') {
        $uri = '/admin/index.php';
    }
    // act приходил скрытым полем POST — в GET он должен быть в строке запроса
    if (!preg_match('/[?&]act=/', $uri)) {
        $uri .= (str_contains($uri, '?') ? '&' : '?') . 'act=amountbonus';
    }
    return $uri;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        processBonusDistribution();
    } catch (Throwable $e) {
        displayError('A critical error occurred. Please contact the administrator.');
    }

    if (!headers_sent()) {
        $flash = $GLOBALS['_bonus_flash'] ?? null;
        if ($flash) {
            setcookie(AB_FLASH_COOKIE, json_encode($flash, JSON_UNESCAPED_UNICODE), abCookieOptions(time() + 60));
        }
        header('Location: ' . abRedirectUrl(), true, 303);
        exit;
    }
    // Если заголовки уже ушли — просто рендерим страницу с сообщением как раньше
} elseif (isset($_COOKIE[AB_FLASH_COOKIE])) {
    $flash = json_decode((string)$_COOKIE[AB_FLASH_COOKIE], true);
    if (is_array($flash) && in_array($flash['type'] ?? '', ['error', 'success'], true) && isset($flash['message'])) {
        setFlashMessage((string)$flash['type'], (string)$flash['message']);
    }
    if (!headers_sent()) {
        setcookie(AB_FLASH_COOKIE, '', abCookieOptions(time() - 3600)); // показать один раз
    }
}

$groups  = loadBonusGroups();
$recent  = loadRecentBonusLog();
$stq     = $db->sql_query_prepared("SELECT COUNT(*) AS total_users, COALESCE(SUM(seedbonus),0) AS total_bonus, COALESCE(AVG(seedbonus),0) AS avg_bonus, COALESCE(MAX(seedbonus),0) AS max_bonus FROM users WHERE ustatus = 'confirmed'");
$stats   = $stq ? $db->fetch_array($stq) : [];
$key     = htmlspecialchars((string)$mybb->post_code, ENT_QUOTES);
$self    = htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES);
$me      = htmlspecialchars((string)($CURUSER['username'] ?? ''), ENT_QUOTES);
$allCount = (int)($stats['total_users'] ?? 0);

stdhead('Bonus Points Distribution');
?>
<?php
// Ассеты страницы; ?v=filemtime — сброс кэша браузера при каждом изменении файла
$abAsset = static function (string $rel): string {
    $file = defined('TSDIR') ? TSDIR . $rel : '';
    $ver  = ($file !== '' && is_file($file)) ? (string)filemtime($file) : AB_VERSION;
    return htmlspecialchars(($GLOBALS['BASEURL'] ?? '') . $rel . '?v=' . rawurlencode($ver), ENT_QUOTES);
};
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/amountbonus.css?ver=336">

<div class="container mt-3 mb-4 ab" data-self="<?= $self ?>" data-me="<?= $me ?>">

    <div class="ab-card mb-3"><div class="ab-head">
        <span class="ab-head-icon"><i class="fa-solid fa-gift"></i></span>
        <div style="min-width:0">
            <h1 class="ab-title">Bonus Points Distribution</h1>
            <div class="ab-sub">Give seed bonus points to one member or to a whole group</div>
        </div>
        <span class="ab-ver ms-auto"><i class="fa-solid fa-code-branch me-1"></i><?= htmlspecialchars(AB_VERSION) ?></span>
    </div></div>

    <?php renderFlashMessage(); ?>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-users',      'ic-blue',   'Confirmed users', number_format($allCount)],
            ['fa-coins',      'ic-amber',  'Points in circulation', number_format((float)($stats['total_bonus'] ?? 0))],
            ['fa-chart-line', 'ic-green',  'Average per user', number_format((float)($stats['avg_bonus'] ?? 0))],
            ['fa-trophy',     'ic-purple', 'Richest balance', number_format((float)($stats['max_bonus'] ?? 0))],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="ab-card ab-kpi"><span class="ab-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="ab-kpi-label"><?= $label ?></div><div class="ab-kpi-value"><?= $val ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <!-- ── Один пользователь ─────────────────────────── -->
        <div class="col-lg-6">
            <form method="POST" action="<?= $self ?>" class="ab-card h-100 needs-validation" novalidate id="abSingle">
                <input type="hidden" name="act" value="amountbonus">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <div class="ab-sec-head">
                    <span class="ab-sec-icon ic-blue"><i class="fa-solid fa-user"></i></span>
                    <div><h2 class="ab-sec-title">Single user</h2><div class="ab-muted">Exact username</div></div>
                </div>
                <div class="ab-body">
                    <div class="mb-3">
                        <label class="form-label" for="abUser"><i class="fa-solid fa-user-tag"></i>Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                            <input type="text" class="form-control" id="abUser" name="username" placeholder="Exact username" maxlength="50" required autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="abMe" title="Myself" style="border-radius:0 .7rem .7rem 0"><i class="fa-solid fa-user-check"></i></button>
                        </div>
                        <div class="ab-user" id="abUserCard" hidden>
                            <span class="ab-avatar" id="abUserAv"><i class="fa-solid fa-user"></i></span>
                            <div class="flex-grow-1" style="min-width:0"><div class="fw-bold" id="abUserName"></div><div class="ab-muted" id="abUserMeta"></div></div>
                            <span class="fw-bold text-warning text-nowrap" id="abUserBonus"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="abAmount1"><i class="fa-solid fa-coins"></i>Points</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="abAmount1" name="seedbonus" min="1" max="1000000" placeholder="Amount" required>
                            <span class="input-group-text" style="border-radius:0 .7rem .7rem 0">points</span>
                        </div>
                        <div class="ab-chips" data-target="abAmount1">
                            <?php foreach ([100, 500, 1000, 5000, 10000] as $v): ?><button type="button" class="ab-chip" data-v="<?= $v ?>"><?= number_format($v) ?></button><?php endforeach; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="reset" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-eraser me-1"></i>Clear</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-paper-plane me-1"></i>Send points</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ── Группа ─────────────────────────────────────── -->
        <div class="col-lg-6">
            <form method="POST" action="<?= $self ?>" class="ab-card h-100 needs-validation" novalidate id="abBulk">
                <input type="hidden" name="act" value="amountbonus">
                <input type="hidden" name="toall" value="yes">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <div class="ab-sec-head">
                    <span class="ab-sec-icon ic-amber"><i class="fa-solid fa-users"></i></span>
                    <div><h2 class="ab-sec-title">Bulk distribution</h2><div class="ab-muted">Every confirmed member of a group</div></div>
                </div>
                <div class="ab-body">
                    <div class="mb-3">
                        <label class="form-label" for="abGroup"><i class="fa-solid fa-filter"></i>Target group</label>
                        <select name="usergroup" id="abGroup" class="form-select">
                            <option value="" data-n="<?= $allCount ?>">All confirmed users (<?= number_format($allCount) ?>)</option>
                            <?php foreach ($groups as $gid => $g): ?>
                            <option value="<?= $gid ?>" data-n="<?= $g['members'] ?>" <?= $g['protected'] ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($g['title']) ?> (<?= number_format($g['members']) ?>)<?= $g['protected'] ? ' — staff, protected' : ($g['banned'] ? ' — banned group' : '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="abAmount2"><i class="fa-solid fa-coins"></i>Points per user</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="abAmount2" name="seedbonus" min="1" max="1000000" placeholder="Amount" required>
                            <span class="input-group-text" style="border-radius:0 .7rem .7rem 0">points</span>
                        </div>
                        <div class="ab-chips" data-target="abAmount2">
                            <?php foreach ([100, 500, 1000, 5000] as $v): ?><button type="button" class="ab-chip" data-v="<?= $v ?>"><?= number_format($v) ?></button><?php endforeach; ?>
                        </div>
                    </div>
                    <div class="ab-impact mb-3">
                        <span class="ab-sec-icon ic-slate"><i class="fa-solid fa-calculator"></i></span>
                        <div><div class="ab-muted"><span id="abN">0</span> users × <span id="abPer">0</span> points</div><div class="ab-impact-v" id="abTotal">—</div></div>
                    </div>
                    <div class="ab-warn mb-3" id="abWarn"><i class="fa-solid fa-triangle-exclamation"></i><div id="abWarnText"></div></div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning px-4" id="abBulkBtn"><i class="fa-solid fa-tower-broadcast me-1"></i>Distribute</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ── Журнал ─────────────────────────────────────── -->
        <div class="col-12">
            <div class="ab-card overflow-hidden">
                <div class="ab-sec-head"><span class="ab-sec-icon ic-slate"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <div><h2 class="ab-sec-title">Recent distributions</h2><div class="ab-muted">From the site log</div></div></div>
                <?php if ($recent): ?>
                <ul class="ab-log">
                    <?php foreach ($recent as $r):
                        $bulk = str_contains((string)$r['txt'], 'distributed to'); ?>
                    <li><i class="fa-solid <?= $bulk ? 'fa-users text-warning' : 'fa-user text-primary' ?>"></i>
                        <span class="flex-grow-1"><?= htmlspecialchars((string)$r['txt']) ?></span>
                        <span class="ab-muted text-nowrap"><?= my_datee('relative', (int)$r['added']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="ab-muted px-4 py-3">Nothing distributed yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/amountbonus.js?ver=3"></script>

<?php
stdfoot();