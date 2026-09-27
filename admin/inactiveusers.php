<?php

declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

// --- Core settings and checks ---
if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

/**
 * Рассылка предупреждений выбранным пользователям.
 * Возвращает ['items' => [[username, email, ok], ...], 'count' => int]
 */
function send_warning_emails(array $selected_users): array
{
    global $db, $body, $subject;

    $items = [];
    $count = 0;

    foreach ($selected_users as $user_id) {
        $user_id = (int)$user_id;
        $query = $db->sql_query_prepared("SELECT id, username, email FROM users WHERE id = ?", [$user_id]);
        $user  = $query ? $db->fetch_array($query) : null;
        if (!$user) {
            continue;
        }

        $email         = htmlspecialchars_uni($user['email']);
        $safe_username = htmlspecialchars_uni($user['username']);
        $text_message  = '';

        $sendmail = my_mail($user['email'], $subject['inactive'], sprintf($body['inactive'], $safe_username), '', '', '', false, 'html', $text_message);

        if ($sendmail) {
            $db->sql_query_prepared('REPLACE INTO inactivity (userid, inactivitytag) VALUES (?, ?)', [$user['id'], TIMENOW]);
            $count++;
        }
        $items[] = [$safe_username, $email, (bool)$sendmail];
    }

    return ['items' => $items, 'count' => $count];
}

/**
 * Удаление выбранных пользователей.
 * Возвращает ['deleted' => int, 'skipped' => int]
 */
function delete_selected_users(array $selected_users): array
{
    global $db, $CURUSER;

    $deleted = 0;
    $skipped = 0;

    // ID групп, которые НИКОГДА нельзя удалить через эту панель
    // (админы/модераторы/супермодераторы и т.п.) — подставьте реальные ID
    // групп из вашей таблицы usergroups.
    $protected_usergroups = [1, 3, 4, 5, 6];

    if (!class_exists('UserDataHandler')) {
        require_once INC_PATH . '/datahandlers/user.php';
    }
    $userhandler = new UserDataHandler('delete');

    foreach ($selected_users as $user_id) {
        $user_id = (int)$user_id;

        // Не удаляем себя и пользователя с ID 1
        if ($user_id === (int)$CURUSER['id'] || $user_id === 1) {
            $skipped++;
            continue;
        }

        $query = $db->sql_query_prepared("SELECT id, username, usergroup FROM users WHERE id = ?", [$user_id]);
        $user  = $query ? $db->fetch_array($query) : null;
        if (!$user) {
            continue;
        }

        if (in_array((int)$user['usergroup'], $protected_usergroups, true)) {
            write_log('Attempt to delete protected/staff account (' . htmlspecialchars_uni($user['username']) . ') blocked, requested by ' . $CURUSER['username']);
            $skipped++;
            continue;
        }

        try {
            if ($userhandler->delete_user((int)$user['id'])) {
                $deleted++;
                write_log('Account (' . htmlspecialchars_uni($user['username']) . ') has been deleted due inactivity by ' . $CURUSER['username']);
            } else {
                write_log('Failed to delete account (' . htmlspecialchars_uni($user['username']) . ')');
            }
        } catch (Exception $e) {
            write_log('Error deleting user ' . $user_id . ': ' . $e->getMessage());
        }
    }

    return ['deleted' => $deleted, 'skipped' => $skipped];
}

define('IUM_VERSION', '0.9 by xam');
if (@file_exists('./include/inactiveusers_config.php')) {
    include_once './include/inactiveusers_config.php';
} else {
    $leechwarn_length2 = '3';
    // Срок до удаления после предупреждения — в ДНЯХ. Раньше здесь был
    // TIMENOW + недели*604800 (метка времени), а дальше значение умножалось на
    // 86400 как дни: «удалим через 56 лет», и такое же число уходило в письмо.
    $deleteafter   = (int)$leechwarn_length2 * 7;
    $show_per_page = 30;
    $postmaillimit = '20';
    $body = [
        'inactive' => '<p>Dear %s,</p><p>It has come to our attention that you have registered at <b>' . $SITENAME . '</b> more then <b>' . $maxdays . ' days ago</b>, but didn\'t login again since.</p><p>Did you forget about us?</p><p>We would be happy to see you around again!</p><p>If you don\'t login again within <b>' . $deleteafter . ' days</b> from now, we will <b><font color=red>delete</font></b> your account.</p><p>&nbsp;</p><p>Sincerely,</p><p>' . $SITENAME . ' Team</p><p><a href="' . $BASEURL . '">' . $BASEURL . '</a></p><p>&nbsp;</p><p><b>DO NOT REPLY TO THIS EMAIL!</b></p>',
        'deleted'  => '<p>Dear %s,</p><p>You have not logged in at <b>' . $SITENAME . '</b> for more then <b>' . $maxdays . ' days</b>.</p><p>You also didn\'t respond to our eMail we sent to you <b>' . $deleteafter . ' days ago</b>.</p><p>Therefor we have decided to <b><font color=red>delete</font></b> your Account, as it seems you are not interested in our site any longer.</p><p>We are sorry to see that you left us, feel free to come back at any time.</p><p>&nbsp;</p><p>Sincerely,</p><p>' . $SITENAME . ' Team</p><p><a href="' . $BASEURL . '">' . $BASEURL . '</a></p><p>&nbsp;</p><p><b>DO NOT REPLY TO THIS EMAIL!</b></p>',
    ];
    $subject = [
        'inactive' => $SITENAME . ' - Account Inactive!',
        'deleted'  => $SITENAME . ' - Account Deleted!',
    ];
}

// Защита, если в конфиге $deleteafter задан меткой времени, а не числом дней
$deleteafter = (int)$deleteafter;
if ($deleteafter > 100000) {
    $deleteafter = max(1, (int)round(($deleteafter - TIMENOW) / 86400));
}
$delete_secs = $deleteafter * 86400;

if (!isset($_this_script_)) {
    $_this_script_ = $_SERVER['PHP_SELF'];
}

// --- Process form actions ---
$action_alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF — обязательно перед массовой рассылкой / удалением
    verify_post_check($mybb->get_input('my_post_key'));

    $selected_users = [];
    if (!empty($_POST['selected_users_data'])) {
        $selected_users = array_values(array_filter(array_map('intval', explode(',', (string)$_POST['selected_users_data']))));
    }

    if (empty($selected_users)) {
        $action_alert = '<div class="alert alert-warning d-flex align-items-center gap-2"><i class="fas fa-circle-exclamation"></i>No users selected.</div>';
    } elseif ($_POST['action'] === 'send_warn_email') {
        $res  = send_warning_emails($selected_users);
        $rows = '';
        foreach ($res['items'] as [$name, $mail, $ok]) {
            $rows .= '<li class="d-flex align-items-center gap-2 py-1">'
                   . ($ok ? '<i class="fas fa-circle-check text-success"></i>' : '<i class="fas fa-circle-xmark text-danger"></i>')
                   . '<span class="fw-semibold">' . $name . '</span><span class="text-body-secondary small">' . $mail . '</span></li>';
        }
        $failed = count($res['items']) - $res['count'];
        $action_alert = '<div class="alert ' . ($failed ? 'alert-warning' : 'alert-success') . '">
            <div class="d-flex align-items-center gap-2 fw-semibold"><i class="fas fa-paper-plane"></i>Sent ' . $res['count'] . ' warning email(s)' . ($failed ? ', ' . $failed . ' failed' : '') . '.</div>
            ' . ($rows ? '<ul class="list-unstyled mb-0 mt-2 iu-result-list">' . $rows . '</ul>' : '') . '
        </div>';
    } elseif ($_POST['action'] === 'delete_selected_users') {
        $res = delete_selected_users($selected_users);
        $action_alert = '<div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-user-xmark"></i>'
            . $res['deleted'] . ' user account(s) deleted.'
            . ($res['skipped'] ? ' <span class="text-body-secondary">(' . $res['skipped'] . ' protected account(s) skipped)</span>' : '')
            . '</div>';
    }
}

// --- Stats & pagination ---
$dt = TIMENOW - ($maxdays * 86400);

$query = $db->sql_query_prepared("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(i.inactivitytag > 0), 0) AS warned,
           COALESCE(SUM(i.inactivitytag > 0 AND i.inactivitytag + ? < ?), 0) AS overdue
    FROM users u
    LEFT JOIN inactivity i ON (u.id = i.userid)
    WHERE u.lastactive < ? AND u.ustatus = 'confirmed' AND u.enabled = 'yes'
", [$delete_secs, TIMENOW, $dt]);
$stats       = $query ? $db->fetch_array($query) : [];
$threadcount = (int)($stats['total'] ?? 0);
$warned_cnt  = (int)($stats['warned'] ?? 0);
$overdue_cnt = (int)($stats['overdue'] ?? 0);

$perpage = 20;
$page    = $mybb->get_input('page', MyBB::INPUT_INT);
$pages   = max(1, (int)ceil($threadcount / $perpage));
if ($page < 1 || $page > $pages) {
    $page = 1;
}
$start = ($page - 1) * $perpage;

// Раньше: str_replace("{fid}", $fid, …) — $fid здесь не определён (warning)
$multipage = multipage($threadcount, $perpage, $page, $_this_script_);

$query_inactive = $db->sql_query_prepared(
    'SELECT u.id,u.username,u.usergroup,u.email,u.uploaded,u.downloaded,u.lastactive,u.lastvisit,u.added,u.avatar,u.avatardimensions,i.inactivitytag
     FROM users u LEFT JOIN inactivity i ON (u.id=i.userid)
     WHERE u.enabled = \'yes\' AND u.ustatus = \'confirmed\' AND u.lastactive < ?
     ORDER BY i.inactivitytag DESC, u.lastactive DESC LIMIT ?, ?',
    [$dt, $start, $perpage]
);

include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_mkprettytime.php';

stdhead('Inactive Users (' . (int)$maxdays . '+ days)');
?>

<link rel="stylesheet" href="<?php echo $BASEURL; ?>/admin/templates/inactiveusers.css?v=<?php echo (int)@filemtime(TSDIR . '/include/templates/default/style/inactiveusers.css'); ?>">

<div class="container mt-3 mb-4 iu">

    <!-- Заголовок -->
    <div class="iu-card mb-3">
        <div class="iu-head">
            <span class="iu-head-icon"><i class="fas fa-user-clock"></i></span>
            <div>
                <h1 class="iu-title">Inactive Users</h1>
                <div class="iu-sub">No activity for more than <b><?php echo (int)$maxdays; ?> days</b> · accounts are deleted <b><?php echo $deleteafter; ?> days</b> after the warning email</div>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-blue"><i class="fas fa-users"></i></span>
                <div><div class="iu-stat-label">Inactive</div><div class="iu-stat-value"><?php echo ts_nf($threadcount); ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-slate"><i class="fas fa-bed"></i></span>
                <div><div class="iu-stat-label">Not warned</div><div class="iu-stat-value"><?php echo ts_nf($threadcount - $warned_cnt); ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-amber"><i class="fas fa-envelope-circle-check"></i></span>
                <div><div class="iu-stat-label">Warned</div><div class="iu-stat-value"><?php echo ts_nf($warned_cnt - $overdue_cnt); ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-red"><i class="fas fa-hourglass-end"></i></span>
                <div><div class="iu-stat-label">Overdue</div><div class="iu-stat-value"><?php echo ts_nf($overdue_cnt); ?></div></div>
            </div>
        </div>
    </div>

    <?php echo $action_alert; ?>

    <form method="post" action="<?php echo htmlspecialchars($_this_script_); ?>" id="mainForm">
        <input type="hidden" name="selected_users_data" id="selectedUsersData" value="">
        <input type="hidden" name="action" id="formAction" value="">
        <input type="hidden" name="my_post_key" value="<?php echo htmlspecialchars_uni($mybb->post_code ?? ''); ?>">
    </form>

    <!-- Панель действий -->
    <div class="iu-card iu-toolbar mb-3">
        <div class="iu-selected"><i class="fas fa-square-check me-1"></i>Selected: <b id="iuSelCount">0</b> of <?php echo ts_nf($threadcount); ?></div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="iuSelectOverdue" title="Select users whose warning period has expired">
                <i class="fas fa-hourglass-end me-1"></i>Select overdue
            </button>
            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 iu-act" onclick="submitForm('send_warn_email')" disabled>
                <i class="fas fa-paper-plane me-1"></i>Send Warning
            </button>
            <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 iu-act" onclick="submitForm('delete_selected_users')" disabled>
                <i class="fas fa-user-xmark me-1"></i>Delete
            </button>
        </div>
    </div>

    <!-- Таблица -->
    <div class="iu-card overflow-hidden">
        <div class="table-responsive">
            <table class="table iu-table">
                <thead>
                    <tr>
                        <th style="width:48px">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="checkAll" aria-label="Select all">
                            </div>
                        </th>
                        <th><i class="fas fa-user"></i>User</th>
                        <th><i class="fas fa-envelope"></i>Email</th>
                        <th class="text-center"><i class="fas fa-scale-balanced"></i>Ratio</th>
                        <th><i class="fas fa-calendar-plus"></i>Joined</th>
                        <th><i class="fas fa-clock-rotate-left"></i>Last Access</th>
                        <th><i class="fas fa-circle-info"></i>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($query_inactive && $db->num_rows($query_inactive) > 0) {
                    while ($user = $db->fetch_array($query_inactive)) {
                        $uid       = (int)$user['id'];
                        $raw_name  = (string)$user['username'];
                        $safe_name = htmlspecialchars_uni($raw_name);
                        $initial   = htmlspecialchars_uni(mb_strtoupper(mb_substr($raw_name, 0, 1)));

                        // Аватар; если своего нет (заглушка) — кружок с первой буквой ника
                        $av = format_avatar($user['avatar'] ?? '', $user['avatardimensions'] ?? '');
                        // format_avatar() уже отдаёт экранированный URL (как в member.php / commenttable.php)
                        $avatar_html = (!empty($av['image']) && empty($av['is_placeholder']))
                            ? '<img class="iu-avatar" src="' . $av['image'] . '" alt="" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">'
                              . '<span class="iu-initial" hidden>' . $initial . '</span>'
                            : '<span class="iu-initial">' . $initial . '</span>';
                        $last_seen = max((int)$user['lastactive'], (int)$user['lastvisit']);
                        $last_active = $last_seen ? my_datee('relative', $last_seen) : 'Never';
                        $tag       = (int)($user['inactivitytag'] ?? 0);

                        if ($tag === 0) {
                            $state  = 'idle';
                            $status = '<span class="iu-badge iu-badge-idle"><i class="fas fa-moon"></i>Not warned</span>';
                        } else {
                            $delete_at = $tag + $delete_secs;
                            $left      = $delete_at - TIMENOW;
                            if ($left > 0) {
                                $state  = 'warned';
                                $status = '<span class="iu-badge iu-badge-warned"><i class="fas fa-envelope"></i>Warned ' . mkprettytime(TIMENOW - $tag) . ' ago</span>'
                                        . '<div class="iu-muted mt-1"><i class="fas fa-hourglass-half me-1"></i>Deletion in ' . mkprettytime($left) . ' · ' . get_date_time($delete_at) . '</div>';
                            } else {
                                // Раньше выводилось отрицательное «Will be deleted in -3 days»
                                $state  = 'overdue';
                                $status = '<span class="iu-badge iu-badge-overdue"><i class="fas fa-triangle-exclamation"></i>Overdue</span>'
                                        . '<div class="iu-muted mt-1">Warning expired ' . mkprettytime(-$left) . ' ago</div>';
                            }
                        }
                        ?>
                        <tr data-state="<?php echo $state; ?>">
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input user-checkbox" type="checkbox" role="switch"
                                           value="<?php echo $uid; ?>" id="user_<?php echo $uid; ?>"
                                           data-username="<?php echo $safe_name; ?>"
                                           aria-label="Select <?php echo $safe_name; ?>">
                                </div>
                            </td>
                            <td>
                                <div class="iu-user">
                                    <?php echo $avatar_html; ?>
                                    <div style="min-width:0">
                                        <a href="<?php echo $BASEURL . '/' . get_profile_link($uid); ?>" class="fw-semibold text-decoration-none"><?php echo format_name($safe_name, $user['usergroup']); ?></a>
                                        <div class="iu-uid">ID <?php echo $uid; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><a class="iu-email" href="mailto:<?php echo htmlspecialchars_uni($user['email']); ?>"><i class="fas fa-at me-1"></i><?php echo htmlspecialchars_uni($user['email']); ?></a></td>
                            <td class="text-center"><?php echo get_user_ratio($user['uploaded'], $user['downloaded']); ?></td>
                            <td class="iu-date">
                                <div><i class="fas fa-calendar-day"></i><?php echo my_datee($dateformat, $user['added']); ?></div>
                                <div class="iu-muted"><?php echo mkprettytime(TIMENOW - (int)$user['added']); ?> ago</div>
                            </td>
                            <td class="iu-date"><i class="fas fa-eye-slash"></i><?php echo $last_active; ?></td>
                            <td><?php echo $status; ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="7">
                            <div class="iu-empty">
                                <i class="fas fa-face-smile-beam"></i>
                                <div class="fw-semibold">No inactive users found.</div>
                                <div class="small">Everyone has been active within the last <?php echo (int)$maxdays; ?> days.</div>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($multipage) { ?>
        <div class="d-flex justify-content-center mt-3"><?php echo $multipage; ?></div>
    <?php } ?>
</div>

<!-- Modal: подтверждение удаления -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel"><i class="fas fa-triangle-exclamation me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-start mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger flex-shrink-0" style="width:44px;height:44px"><i class="fas fa-user-xmark"></i></span>
                    <div>
                        <div class="fw-semibold">Delete <span id="selectedUsersCount">0</span> account(s)?</div>
                        <div class="small text-body-secondary">This action cannot be undone. Staff and protected accounts are skipped automatically.</div>
                    </div>
                </div>
                <div id="selectedUsersList" class="iu-del-list p-2 rounded-3 bg-body-tertiary"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal"><i class="fas fa-xmark me-1"></i>Cancel</button>
                <button type="button" class="btn btn-danger rounded-pill px-3" id="confirmDeleteBtn"><i class="fas fa-trash me-1"></i>Delete Users</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $BASEURL; ?>/admin/scripts/inactiveusers.js?v=<?php echo (int)@filemtime(TSDIR . '/scripts/inactiveusers.js'); ?>"></script>

<?php
stdfoot();