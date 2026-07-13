<?php

declare(strict_types=1);


if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';

// Constants
const M_VIP_VERSION = 'v0.5';
const VIP_USERGROUP_ID = 4;
const DEFAULT_PER_PAGE = 20;
const ALLOWED_SORT_FIELDS = ['username', 'seedbonus', 'invites'];

// Helper functions
function getVipUserPopoverContent(array $user): string
{
    global $lang, $dateformat, $timeformat, $BASEURL, $mybb;
    
    $lastseen = my_datee($dateformat, $user['lastactive']) . ' ' . my_datee($timeformat, $user['lastactive']);
    $downloaded = mksize($user['downloaded']);
    $uploaded = mksize($user['uploaded']);
    $ratio = get_user_ratio($user['uploaded'], $user['downloaded']);
    
    $lang->load('tsf_forums');
    require_once INC_PATH . '/functions_mkprettytime.php';
    
    // Получаем аватар пользователя через вашу функцию
    $avatarData = format_avatar($user['avatar'] ?? null, '100|100', '100|100');
    $avatarHtml = $avatarData['html'] ?? '';
    
    // Формируем контент popover
    $content = '<div class="popover-content">';
    $content .= '<div class="popover-header bg-light border-bottom">';
    $content .= '<div class="d-flex align-items-center">';
    
    // Аватар
    $content .= '<div class="flex-shrink-0 me-3">';
    if ($avatarHtml && !$avatarData['is_html']) {
        // Если это обычное изображение
        $content .= str_replace('class="', 'class="rounded-circle ', $avatarHtml);
    } elseif ($avatarHtml) {
        // Если это SVG или HTML
        $content .= '<div style="width: 50px; height: 50px; overflow: hidden; border-radius: 50%;">' . $avatarHtml . '</div>';
    } else {
        // Дефолтный аватар
        $content .= '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-user text-white" style="font-size: 1.5rem;"></i>
                     </div>';
    }
    $content .= '</div>';
    
    // Информация о пользователе
    $content .= '<div class="flex-grow-1">';
    $content .= '<h6 class="mb-0 fw-bold">' . htmlspecialchars($user['username'] ?? '') . '</h6>';
    $content .= '<small class="text-muted">' . htmlspecialchars($user['title'] ?? 'VIP Member') . '</small>';
    
    // Статус онлайн/офлайн
    $isOnline = ($user['lastactive'] ?? 0) > (time() - 300); // 5 минут
    $content .= '<div class="mt-1">';
    $content .= '<span class="badge ' . ($isOnline ? 'bg-success' : 'bg-secondary') . ' badge-sm">';
    $content .= $isOnline ? '<i class="fas fa-circle me-1" style="font-size: 0.6em;"></i>Online' : 'Offline';
    $content .= '</span>';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';
    
    // Тело popover
    $content .= '<div class="popover-body">';
    
    // Основная информация
    $content .= '<div class="mb-3">';
    $content .= '<div class="row g-2">';
    
    // Дата регистрации
    $content .= '<div class="col-12">';
    $content .= '<small class="text-muted"><i class="fas fa-calendar-plus me-1"></i>Joined</small>';
    $content .= '<div class="fw-semibold">' . my_datee($dateformat, $user['added']) . '</div>';
    $content .= '</div>';
    
    // Последний вход
    $content .= '<div class="col-12">';
    $content .= '<small class="text-muted"><i class="fas fa-clock me-1"></i>Last seen</small>';
    $content .= '<div class="fw-semibold">' . $lastseen . '</div>';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';
    
    // Статистика
    $content .= '<div class="border-top pt-3">';
    $content .= '<h6 class="mb-2"><i class="fas fa-chart-bar me-1"></i>Statistics</h6>';
    $content .= '<div class="row g-2">';
    
    // Ratio
    $ratioClass = '';
    if ($ratio >= 1.0) {
        $ratioClass = 'text-success';
    } elseif ($ratio >= 0.5) {
        $ratioClass = 'text-warning';
    } else {
        $ratioClass = 'text-danger';
    }
    
    $content .= '<div class="col-6">';
    $content .= '<small class="text-muted">Ratio</small>';
    $content .= '<div class="fw-bold ' . $ratioClass . '">' . $ratio . '</div>';
    $content .= '</div>';
    
    // Загружено
    $content .= '<div class="col-6">';
    $content .= '<small class="text-muted"><i class="fas fa-upload me-1"></i>Uploaded</small>';
    $content .= '<div class="fw-semibold">' . $uploaded . '</div>';
    $content .= '</div>';
    
    // Скачано
    $content .= '<div class="col-6">';
    $content .= '<small class="text-muted"><i class="fas fa-download me-1"></i>Downloaded</small>';
    $content .= '<div class="fw-semibold">' . $downloaded . '</div>';
    $content .= '</div>';
    
    // Сидбонус
    $content .= '<div class="col-6">';
    $content .= '<small class="text-muted"><i class="fas fa-coins me-1"></i>Bonus Points</small>';
    $content .= '<div class="fw-semibold text-primary">' . ts_nf($user['seedbonus'] ?? 0) . '</div>';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';
    
    // Приглашения
    $content .= '<div class="border-top pt-3 mt-3">';
    $content .= '<div class="row g-2">';
    $content .= '<div class="col-12">';
    $content .= '<small class="text-muted"><i class="fas fa-envelope me-1"></i>Invites</small>';
    $content .= '<div class="fw-semibold">' . ts_nf($user['invites'] ?? 0) . ' available</div>';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';
    
    $content .= '</div>'; // popover-body
    
    
    $content .= '</div>'; // popover-content
    
    return $content;
}

function getAvatarForTable(array $user): string
{
    $avatarData = format_avatar($user['avatar'] ?? null, '32|32', '32|32');
    $avatarHtml = $avatarData['html'] ?? '';
    
    if ($avatarHtml && !$avatarData['is_html']) {
        // Если это обычное изображение
        return str_replace('class="', 'class="rounded-circle ', $avatarHtml);
    } elseif ($avatarHtml) {
        // Если это SVG или HTML - оборачиваем в контейнер
        return '<div style="width: 32px; height: 32px; overflow: hidden; border-radius: 50%;">' . $avatarHtml . '</div>';
    } else {
        // Дефолтный аватар
        return '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                    style="width: 32px; height: 32px;">
                   <i class="fas fa-user text-white" style="font-size: 0.9rem;"></i>
                </div>';
    }
}

function getVipUntilDisplay(?int $vipUntil): string
{
    if (empty($vipUntil)) {
        return '<span class="badge bg-success"><i class="fas fa-infinity me-1"></i>Unlimited</span>';
    }

    global $dateformat, $timeformat;

    $timeLeft = $vipUntil - TIMENOW;
    $daysLeft = floor($timeLeft / (60 * 60 * 24));

    // Определяем цвет в зависимости от оставшегося времени
    if ($daysLeft <= 0) {
        $badgeClass = 'bg-danger';
    } elseif ($daysLeft <= 7) {
        $badgeClass = 'bg-warning text-dark';
    } elseif ($daysLeft <= 30) {
        $badgeClass = 'bg-info';
    } else {
        $badgeClass = 'bg-primary';
    }

    return '<span class="badge ' . $badgeClass . '">
                <i class="fas fa-clock me-1"></i>' . 
                my_datee($dateformat, $vipUntil) . '<br>
                <small>' . ($daysLeft > 0 ? mkprettytime(max(0, $timeLeft)) . ' left' : 'Expired (pending cron)') . '</small>
            </span>';
}

// Main processing
$action = trim($_GET['do'] ?? $_POST['do'] ?? '');

// Handle update action
if ($action === 'update') {
    verify_post_check($mybb->get_input('my_post_key'));

    $addType = trim($_POST['add'] ?? '');
    $limit = (int) ($_POST['limit'] ?? 0);
    $userIds = array_filter($_POST['userids'] ?? [], 'is_numeric');
    $page = (int) ($_POST['page'] ?? 1);
    
    if (!empty($userIds) && ($limit > 0 || $addType === 'remove_vip')) {
        $userIds = array_map('intval', $userIds);

        switch ($addType) {
            case 'donoruntil':
                $extendSeconds = $limit * 7 * 86400;

                foreach ($userIds as $uid) {
                    $existing = $db->fetch_array($db->sql_query_prepared(
                        'SELECT vip_until, old_gid FROM auto_vip WHERE userid = ?',
                        [$uid]
                    ));

                    if ($existing) {
                        $newUntil = max((int)$existing['vip_until'], TIMENOW) + $extendSeconds;
                        $db->sql_query_prepared('UPDATE auto_vip SET vip_until = ? WHERE userid = ?', [$newUntil, $uid]);
                    } else {
                        $currentGroup = $db->fetch_array($db->sql_query_prepared('SELECT usergroup FROM users WHERE id = ?', [$uid]));
                        $oldGid = ((int)($currentGroup['usergroup'] ?? 0) === VIP_USERGROUP_ID)
                            ? UC_USER
                            : (int)($currentGroup['usergroup'] ?? UC_USER);

                        $db->sql_query_prepared(
                            'INSERT INTO auto_vip (userid, vip_until, old_gid) VALUES (?, ?, ?)',
                            [$uid, TIMENOW + $extendSeconds, $oldGid]
                        );
                    }

                    $db->sql_query_prepared('UPDATE users SET usergroup = ? WHERE id = ?', [VIP_USERGROUP_ID, $uid]);
                }

                write_log('VIP time extended by ' . $limit . ' week(s) for users: ' . implode(', ', $userIds), 'general', 1);
                break;
                
            case 'seedbonus':
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus + ? WHERE id IN ({$placeholders})", [$limit, ...$userIds]);
                write_log('Gave ' . $limit . ' bonus points to users: ' . implode(', ', $userIds), 'general', 1);
                break;
                
            case 'invites':
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $db->sql_query_prepared("UPDATE users SET invites = invites + ? WHERE id IN ({$placeholders})", [$limit, ...$userIds]);
                write_log('Gave ' . $limit . ' invite(s) to users: ' . implode(', ', $userIds), 'general', 1);
                break;

            case 'remove_vip':
                foreach ($userIds as $uid) {
                    $existing = $db->fetch_array($db->sql_query_prepared('SELECT old_gid FROM auto_vip WHERE userid = ?', [$uid]));
                    $newGid = ($existing && (int)$existing['old_gid'] > 0) ? (int)$existing['old_gid'] : UC_USER;

                    $db->sql_query_prepared('UPDATE users SET usergroup = ? WHERE id = ?', [$newGid, $uid]);
                    $db->sql_query_prepared('DELETE FROM auto_vip WHERE userid = ?', [$uid]);
                }

                write_log('VIP status manually removed by staff for users: ' . implode(', ', $userIds), 'general', 1);
                break;
        }
    }
}

// Build search conditions
$searchConditions = '';
$searchParams = [];
$username = trim($_GET['username'] ?? $_POST['username'] ?? '');

if ($action === 'search_user' && !empty($username)) {
    $searchConditions = " AND (u.username = ? OR u.username LIKE ?) ";
    $searchParams = [$username, "%{$username}%"];
    $linkParams = 'username=' . htmlspecialchars($username) . '&amp;do=search_user&amp;';
} else {
    $linkParams = '';
}

// Get total count using prepared statement
$sql = "SELECT COUNT(*) as total 
        FROM users u 
        LEFT JOIN usergroups g ON u.usergroup = g.gid 
        WHERE u.usergroup = ? 
        AND g.cansettingspanel = '0' 
        AND g.canstaffpanel = '0' 
        AND g.issupermod = '0' 
        {$searchConditions}";

$params = [VIP_USERGROUP_ID];
if (!empty($searchParams)) {
    $params = array_merge($params, $searchParams);
}

$countResult = $db->sql_query_prepared($sql, $params);
if ($countResult) {
    $countData = $db->fetch_array($countResult);
    $totalUsers = (int) ($countData['total'] ?? 0);
} else {
    $totalUsers = 0;
}

// Pagination
$perPage = $torrentsperpage ?? DEFAULT_PER_PAGE;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalUsers / $perPage));
$currentPage = min($currentPage, $totalPages);
$start = ($currentPage - 1) * $perPage;

// Sorting
$sortField = $_GET['sortby'] ?? 'username';
$sortField = in_array($sortField, ALLOWED_SORT_FIELDS, true) ? $sortField : 'username';
$sortOrder = ($_GET['type'] ?? 'ASC') === 'DESC' ? 'ASC' : 'DESC';
$sortIcon = $sortOrder === 'ASC' ? '↑' : '↓';

// Build main query using prepared statement
$mainQuery = "SELECT u.*, g.namestyle, g.title, av.vip_until, av.old_gid
              FROM users u 
              LEFT JOIN usergroups g ON u.usergroup = g.gid 
              LEFT JOIN auto_vip av ON av.userid = u.id
              WHERE u.usergroup = ? 
              AND g.cansettingspanel = '0' 
              AND g.canstaffpanel = '0' 
              AND g.issupermod = '0' 
              {$searchConditions} 
              ORDER BY u.{$sortField} {$sortOrder} 
              LIMIT ?, ?";

$params = [VIP_USERGROUP_ID];
if (!empty($searchParams)) {
    $params = array_merge($params, $searchParams);
}
$params[] = $start;
$params[] = $perPage;

$vipUsers = $db->sql_query_prepared($mainQuery, $params);
$vipUsersCount = $vipUsers ? $db->num_rows($vipUsers) : 0;

// Generate pagination
$pageUrl = $_this_script_ . '&amp;' . $linkParams;
$multipage = multipage($totalUsers, $perPage, $currentPage, $pageUrl);

// Output HTML
stdhead("Manage VIP Accounts (Total " . ts_nf($totalUsers) . " VIP Accounts found)");
?>

<div class="container mt-3">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white rounded-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">
                                <i class="fas fa-crown me-2"></i>Manage VIP Accounts
                            </h4>
                            <small class="opacity-75">VIP Members Management Panel</small>
                        </div>
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-users me-1"></i><?= ts_nf($totalUsers) ?> accounts
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="post" action="<?= $_this_script_ ?>" class="row g-3 align-items-end">
                        <input type="hidden" name="do" value="search_user">
                        
                        <div class="col-md-8">
                            <label for="username" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i>Search Username
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="<?= htmlspecialchars($username) ?>"
                                       placeholder="Enter username or part of username...">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <?php if (!empty($username)): ?>
                                <a href="<?= $_this_script_ ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear Search
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-info" data-bs-toggle="popover" 
                                    data-bs-placement="bottom" data-bs-html="true"
                                    data-bs-content="<small>Search for VIP users by username. You can search for exact matches or partial matches.</small>">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination Top -->
    <?php if ($totalPages > 1): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-center">
                <nav aria-label="VIP users pagination">
                    <?= $multipage ?>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- VIP Users Table -->
    <form method="post" action="<?= $_this_script_ ?>" name="update">
        <input type="hidden" name="do" value="update">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>">
        <input type="hidden" name="page" value="<?= $currentPage ?>">
        
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="20">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       checkall="group" 
                                                       onclick="select_deselectAll('update', this, 'group')">
                                            </div>
                                        </th>
                                        <th>
                                            <a href="<?= $_this_script_ ?>&amp;sortby=username&amp;type=<?= $sortField === 'username' ? $sortOrder : 'ASC' ?>"
                                               class="text-decoration-none text-dark d-flex align-items-center">
                                                <i class="fas fa-user me-1"></i>
                                                Username <?= $sortField === 'username' ? $sortIcon : '' ?>
                                            </a>
                                        </th>
                                        <th>VIP Status</th>
                                        <th>
                                            <a href="<?= $_this_script_ ?>&amp;sortby=seedbonus&amp;type=<?= $sortField === 'seedbonus' ? $sortOrder : 'ASC' ?>"
                                               class="text-decoration-none text-dark d-flex align-items-center">
                                                <i class="fas fa-coins me-1"></i>
                                                Points <?= $sortField === 'seedbonus' ? $sortIcon : '' ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="<?= $_this_script_ ?>&amp;sortby=invites&amp;type=<?= $sortField === 'invites' ? $sortOrder : 'ASC' ?>"
                                               class="text-decoration-none text-dark d-flex align-items-center">
                                                <i class="fas fa-envelope me-1"></i>
                                                Invites <?= $sortField === 'invites' ? $sortIcon : '' ?>
                                            </a>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($vipUsers && $vipUsersCount > 0): ?>
                                        <?php while ($vip = $db->fetch_array($vipUsers)): ?>
                                        <tr class="align-middle">
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" 
                                                           type="checkbox" 
                                                           name="userids[]" 
                                                           value="<?= (int) ($vip['id'] ?? 0) ?>" 
                                                           checkme="group">
                                                </div>
                                            </td>
                                            <td>
                                                <a href="<?= $BASEURL . '/' . get_profile_link($vip['id'] ?? 0) ?>" 
                                                   target="_blank"
                                                   class="text-decoration-none d-flex align-items-center user-popover-link"
                                                   data-bs-toggle="popover"
                                                   data-bs-custom-class="user-popover"
                                                   data-bs-html="true"
                                                   data-bs-content="<?= htmlspecialchars(getVipUserPopoverContent($vip), ENT_QUOTES) ?>"
                                                   data-bs-placement="auto"
                                                   data-bs-trigger="hover focus">
                                                    <div class="flex-shrink-0 me-2">
                                                        <?= getAvatarForTable($vip) ?>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-semibold"><?= format_name($vip['username'] ?? '', $vip['usergroup'] ?? 0) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($vip['title'] ?? 'VIP Member') ?></small>
                                                    </div>
                                                    <i class="fas fa-info-circle text-info ms-2" style="font-size: 0.8em;"></i>
                                                </a>
                                            </td>
                                            <td><?= getVipUntilDisplay(isset($vip['vip_until']) ? (int)$vip['vip_until'] : null) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-coins me-1"></i><?= ts_nf($vip['seedbonus'] ?? 0) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-envelope me-1"></i><?= ts_nf($vip['invites'] ?? 0) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-users fa-3x mb-3 opacity-50"></i>
                                                <h5 class="mb-2">No VIP users found</h5>
                                                <p class="mb-0">Try adjusting your search criteria</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <?php if ($totalUsers > 0): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>Bulk Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-3" id="limitFormGroup">
                                <label for="limit" class="form-label fw-semibold">
                                    <i class="fas fa-hashtag me-1"></i>Amount:
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="limit" 
                                       name="limit" 
                                       min="1" 
                                       placeholder="Enter amount">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="add" class="form-label fw-semibold">
                                    <i class="fas fa-cog me-1"></i>Action:
                                </label>
                                <select class="form-select" id="add" name="add" onchange="
                                    var isRemove = this.value === 'remove_vip';
                                    document.getElementById('limitFormGroup').style.display = isRemove ? 'none' : '';
                                    document.getElementById('limit').required = !isRemove;
                                ">
                                    <option value="donoruntil"><i class="fas fa-calendar-plus me-1"></i>Add Extra Donor Time (weeks)</option>
                                    <option value="seedbonus"><i class="fas fa-coins me-1"></i>Give Extra Karma Points</option>
                                    <option value="invites"><i class="fas fa-envelope me-1"></i>Give Extra Invites</option>
                                    <option value="remove_vip"><i class="fas fa-user-slash me-1"></i>Remove VIP Now</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold invisible">Submit</label>
                                <button type="submit" class="btn btn-primary w-100" onclick="
                                    if (document.getElementById('add').value === 'remove_vip') {
                                        return confirm('Remove VIP status from all selected users immediately? This cannot be undone.');
                                    }
                                ">
                                    <i class="fas fa-sync-alt me-1"></i>Update Selected Accounts
                                </button>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="card bg-light border">
                                    <div class="card-body text-center p-2">
                                        <small class="text-muted d-block">Selected</small>
                                        <span id="selectedCount" class="fw-bold text-primary fs-5">0</span>
                                        <small class="text-muted d-block">users</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </form>

    <!-- Pagination Bottom -->
    <?php if ($totalPages > 1): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-center">
                <nav aria-label="VIP users pagination bottom">
                    <?= $multipage ?>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>


<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/popover.js"></script>

<script>
// Update selected count
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="userids[]"]');
    const selectedCount = document.getElementById('selectedCount');
    
    function updateSelectedCount() {
        const checked = Array.from(checkboxes).filter(cb => cb.checked).length;
        selectedCount.textContent = checked;
        
        // Обновляем цвет в зависимости от количества
        if (checked > 0) {
            selectedCount.parentElement.classList.add('bg-selected');
        } else {
            selectedCount.parentElement.classList.remove('bg-selected');
        }
    }
    
    if (checkboxes.length > 0) {
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // Инициализация счетчика
        updateSelectedCount();
    }
    
   
});
</script>

<style>
.user-popover {
    max-width: 400px;
    min-width: 350px;
}
.user-popover .popover-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem;
}
.user-popover .popover-body {
    padding: 1.25rem;
}
.user-popover .popover-footer {
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 0.75rem;
}
.user-popover .border-top {
    border-top: 1px solid #dee2e6 !important;
}
.user-popover .border-bottom {
    border-bottom: 1px solid #dee2e6 !important;
}
.user-popover .badge-sm {
    font-size: 0.75em;
    padding: 0.25em 0.5em;
}
.user-popover .row {
    margin: 0 -0.5rem;
}
.user-popover .col-6, .user-popover .col-12 {
    padding: 0 0.5rem;
}
.bg-selected {
    background-color: #e7f3ff !important;
    border-color: #0d6efd !important;
}
.table tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}
.user-popover-link:hover {
    background-color: transparent !important;
}
.avatar-ring2 {
    width: 100%;
    height: 100%;
}
</style>

<?php
stdfoot();