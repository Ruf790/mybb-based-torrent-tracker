<?php

declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

// --- Core settings and checks ---
if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$lang->load('inactiveusers');

global $iu_maxdays, $iu_deleteafter, $iu_protect_groups, $ts_perpage;

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… (и %1$s, %2$s… — в них $lang->load() превращает {N}).
     */
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

        $sendmail = my_mail($user['email'], $subject['inactive'], strtr($body['inactive'], ['%s' => $safe_username]), '', '', '', false, 'html', $text_message);

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
    global $db, $CURUSER, $iu_protect_groups;

    $deleted = 0;
    $skipped = 0;

    // Группы, которые НИКОГДА нельзя удалить через эту панель — настройка iu_protect_groups
    // (Admin → Settings → Cleanup → Inactive users). Раньше здесь был список [1,3,4,5,6] —
    // это ID групп из MyBB; у трекера они другие, и Administrator (7) / Sysop (8) не были защищены.
    // Модераторы, администраторы и сисопы защищены всегда, даже если их сняли в настройках.
    $protected_usergroups = array_map('intval', array_filter(
        explode(',', (string)($iu_protect_groups ?? '4,5,6,7,8')),
        fn($v) => (int)$v > 0
    ));
    $protected_usergroups = array_values(array_unique([...$protected_usergroups, UC_MODERATOR, UC_ADMINISTRATOR, UC_SYSOP]));

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

// Settings: Admin → Settings → Cleanup → Inactive users (were admin/include/inactiveusers_config.php).
// $maxdays     — days without login before a user counts as inactive
// $deleteafter — days after the warning e-mail before the account may be deleted
$maxdays     = max(1, (int)($iu_maxdays ?? 60));
$deleteafter = max(1, (int)($iu_deleteafter ?? 15));

// E-mail texts come from the inactiveusers lang. {1} stays as %s — the username is filled per user in send_warning_emails()
$mail_args = ['%s', (string)$SITENAME, $maxdays, $deleteafter, (string)$BASEURL];
$body = [
    'inactive' => ags_fmt($lang->inactiveusers['mail_body_inactive'], ...$mail_args),
    'deleted'  => ags_fmt($lang->inactiveusers['mail_body_deleted'], ...$mail_args),
];
$subject = [
    'inactive' => ags_fmt($lang->inactiveusers['mail_subject_inactive'], (string)$SITENAME),
    'deleted'  => ags_fmt($lang->inactiveusers['mail_subject_deleted'], (string)$SITENAME),
];
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
        $action_alert = '<div class="alert alert-warning d-flex align-items-center gap-2"><i class="fas fa-circle-exclamation"></i>' . $lang->inactiveusers['flash_no_selected'] . '</div>';
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
            <div class="d-flex align-items-center gap-2 fw-semibold"><i class="fas fa-paper-plane"></i>' . ($failed
                ? ags_fmt($lang->inactiveusers['flash_sent_partial'], $res['count'], $failed)
                : ags_fmt($lang->inactiveusers['flash_sent'], $res['count'])) . '</div>
            ' . ($rows ? '<ul class="list-unstyled mb-0 mt-2 iu-result-list">' . $rows . '</ul>' : '') . '
        </div>';
    } elseif ($_POST['action'] === 'delete_selected_users') {
        $res = delete_selected_users($selected_users);
        $action_alert = '<div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-user-xmark"></i>'
            . ags_fmt($lang->inactiveusers['flash_deleted'], $res['deleted'])
            . ($res['skipped'] ? ' <span class="text-body-secondary">' . ags_fmt($lang->inactiveusers['flash_skipped'], $res['skipped']) . '</span>' : '')
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

$perpage = max(1, (int)($ts_perpage ?? 20));
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

stdhead(ags_fmt($lang->inactiveusers['head_title'], (int)$maxdays));

// JS-строки: js_<key> → AGS_LANG.<key>
$js_lang = [];
foreach ($lang->inactiveusers as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $js_lang[substr((string)$k, 3)] = $v;
    }
}
?>

<link rel="stylesheet" href="<?php echo $BASEURL; ?>/admin/templates/inactiveusers.css?v=<?php echo (int)@filemtime(TSDIR . '/include/templates/default/style/inactiveusers.css'); ?>">

<div class="container mt-3 mb-4 iu">

    <!-- Заголовок -->
    <div class="iu-card mb-3">
        <div class="iu-head">
            <span class="iu-head-icon"><i class="fas fa-user-clock"></i></span>
            <div>
                <h1 class="iu-title"><?php echo $lang->inactiveusers['pane_title']; ?></h1>
                <div class="iu-sub"><?php echo ags_fmt($lang->inactiveusers['pane_sub'], (int)$maxdays, $deleteafter); ?></div>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-blue"><i class="fas fa-users"></i></span>
                <div><div class="iu-stat-label"><?php echo $lang->inactiveusers['stat_inactive']; ?></div><div class="iu-stat-value"><?php echo ts_nf($threadcount); ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-slate"><i class="fas fa-bed"></i></span>
                <div><div class="iu-stat-label"><?php echo $lang->inactiveusers['stat_not_warned']; ?></div><div class="iu-stat-value"><?php echo ts_nf($threadcount - $warned_cnt); ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-amber"><i class="fas fa-envelope-circle-check"></i></span>
                <div><div class="iu-stat-label"><?php echo $lang->inactiveusers['stat_warned']; ?></div><div class="iu-stat-value"><?php echo ts_nf($warned_cnt - $overdue_cnt); ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="iu-card iu-stat">
                <span class="iu-stat-icon ic-red"><i class="fas fa-hourglass-end"></i></span>
                <div><div class="iu-stat-label"><?php echo $lang->inactiveusers['stat_overdue']; ?></div><div class="iu-stat-value"><?php echo ts_nf($overdue_cnt); ?></div></div>
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
        <div class="iu-selected"><i class="fas fa-square-check me-1"></i><?php echo ags_fmt($lang->inactiveusers['lbl_selected'], '<b id="iuSelCount">0</b>', ts_nf($threadcount)); ?></div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="iuSelectOverdue" title="<?php echo htmlspecialchars_uni($lang->inactiveusers['tip_select_overdue']); ?>">
                <i class="fas fa-hourglass-end me-1"></i><?php echo $lang->inactiveusers['btn_select_overdue']; ?>
            </button>
            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 iu-act" onclick="submitForm('send_warn_email')" disabled>
                <i class="fas fa-paper-plane me-1"></i><?php echo $lang->inactiveusers['btn_send_warning']; ?>
            </button>
            <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 iu-act" onclick="submitForm('delete_selected_users')" disabled>
                <i class="fas fa-user-xmark me-1"></i><?php echo $lang->inactiveusers['btn_delete']; ?>
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
                                <input class="form-check-input" type="checkbox" role="switch" id="checkAll" aria-label="<?php echo htmlspecialchars_uni($lang->inactiveusers['aria_select_all']); ?>">
                            </div>
                        </th>
                        <th><i class="fas fa-user"></i><?php echo $lang->inactiveusers['th_user']; ?></th>
                        <th><i class="fas fa-envelope"></i><?php echo $lang->inactiveusers['th_email']; ?></th>
                        <th class="text-center"><i class="fas fa-scale-balanced"></i><?php echo $lang->inactiveusers['th_ratio']; ?></th>
                        <th><i class="fas fa-calendar-plus"></i><?php echo $lang->inactiveusers['th_joined']; ?></th>
                        <th><i class="fas fa-clock-rotate-left"></i><?php echo $lang->inactiveusers['th_last_access']; ?></th>
                        <th><i class="fas fa-circle-info"></i><?php echo $lang->inactiveusers['th_status']; ?></th>
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
                        $last_active = $last_seen ? my_datee('relative', $last_seen) : $lang->inactiveusers['lbl_never'];
                        $tag       = (int)($user['inactivitytag'] ?? 0);

                        if ($tag === 0) {
                            $state  = 'idle';
                            $status = '<span class="iu-badge iu-badge-idle"><i class="fas fa-moon"></i>' . $lang->inactiveusers['badge_not_warned'] . '</span>';
                        } else {
                            $delete_at = $tag + $delete_secs;
                            $left      = $delete_at - TIMENOW;
                            if ($left > 0) {
                                $state  = 'warned';
                                $status = '<span class="iu-badge iu-badge-warned"><i class="fas fa-envelope"></i>' . ags_fmt($lang->inactiveusers['badge_warned'], mkprettytime(TIMENOW - $tag)) . '</span>'
                                        . '<div class="iu-muted mt-1"><i class="fas fa-hourglass-half me-1"></i>' . ags_fmt($lang->inactiveusers['hint_deletion_in'], mkprettytime($left), get_date_time($delete_at)) . '</div>';
                            } else {
                                // Раньше выводилось отрицательное «Will be deleted in -3 days»
                                $state  = 'overdue';
                                $status = '<span class="iu-badge iu-badge-overdue"><i class="fas fa-triangle-exclamation"></i>' . $lang->inactiveusers['badge_overdue'] . '</span>'
                                        . '<div class="iu-muted mt-1">' . ags_fmt($lang->inactiveusers['hint_expired'], mkprettytime(-$left)) . '</div>';
                            }
                        }
                        ?>
                        <tr data-state="<?php echo $state; ?>">
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input user-checkbox" type="checkbox" role="switch"
                                           value="<?php echo $uid; ?>" id="user_<?php echo $uid; ?>"
                                           data-username="<?php echo $safe_name; ?>"
                                           aria-label="<?php echo htmlspecialchars_uni(ags_fmt($lang->inactiveusers['aria_select_user'], $raw_name)); ?>">
                                </div>
                            </td>
                            <td>
                                <div class="iu-user">
                                    <?php echo $avatar_html; ?>
                                    <div style="min-width:0">
                                        <a href="<?php echo $BASEURL . '/' . get_profile_link($uid); ?>" class="fw-semibold text-decoration-none"><?php echo format_name($safe_name, $user['usergroup']); ?></a>
                                        <div class="iu-uid"><?php echo ags_fmt($lang->inactiveusers['lbl_user_id'], $uid); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><a class="iu-email" href="mailto:<?php echo htmlspecialchars_uni($user['email']); ?>"><i class="fas fa-at me-1"></i><?php echo htmlspecialchars_uni($user['email']); ?></a></td>
                            <td class="text-center"><?php echo get_user_ratio($user['uploaded'], $user['downloaded']); ?></td>
                            <td class="iu-date">
                                <div><i class="fas fa-calendar-day"></i><?php echo my_datee($dateformat, $user['added']); ?></div>
                                <div class="iu-muted"><?php echo ags_fmt($lang->inactiveusers['lbl_ago'], mkprettytime(TIMENOW - (int)$user['added'])); ?></div>
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
                                <div class="fw-semibold"><?php echo $lang->inactiveusers['empty_title']; ?></div>
                                <div class="small"><?php echo ags_fmt($lang->inactiveusers['empty_text'], (int)$maxdays); ?></div>
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
                <h5 class="modal-title" id="deleteConfirmModalLabel"><i class="fas fa-triangle-exclamation me-2"></i><?php echo $lang->inactiveusers['modal_title']; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars_uni($lang->inactiveusers['aria_close']); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-start mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger flex-shrink-0" style="width:44px;height:44px"><i class="fas fa-user-xmark"></i></span>
                    <div>
                        <div class="fw-semibold"><?php echo ags_fmt($lang->inactiveusers['modal_question'], '<span id="selectedUsersCount">0</span>'); ?></div>
                        <div class="small text-body-secondary"><?php echo $lang->inactiveusers['modal_warning']; ?></div>
                    </div>
                </div>
                <div id="selectedUsersList" class="iu-del-list p-2 rounded-3 bg-body-tertiary"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal"><i class="fas fa-xmark me-1"></i><?php echo $lang->inactiveusers['btn_cancel']; ?></button>
                <button type="button" class="btn btn-danger rounded-pill px-3" id="confirmDeleteBtn"><i class="fas fa-trash me-1"></i><?php echo $lang->inactiveusers['btn_confirm_delete']; ?></button>
            </div>
        </div>
    </div>
</div>

<script>const AGS_LANG = <?= json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?php echo $BASEURL; ?>/admin/scripts/inactiveusers.js?ver=2"></script>

<?php
stdfoot();