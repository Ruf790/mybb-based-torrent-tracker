<?php

declare(strict_types=1);

/*
 * admin/edituser.php — редактирование пользователя (staff).
 * Новый стиль: общий st-ui.css + admin/templates/edituser.css, всё под .eu-page.
 * JS страницы: admin/scripts/edituser.js (вкладки, действия, графики, аудит).
 *
 * Действия:
 *   ?action=edituser&userid=N        — страница
 *   POST action=updateuser           — сохранение формы и все кнопки-действия
 *                                      (бан, сессии, блокировка входа, ЛС, аватар, аудит)
 *   ?action=resetpasskey&userid=N    — подтверждение (без JS), POST sure=1 — сброс
 *   ?action=deleteaccount&userid=N   — подтверждение (без JS), POST sure=1 — удаление
 *   ?action=upload_avatar            — AJAX-загрузка аватара (avatar_upload.js)
 */

// ── i18n helper: подстановка {1}, {2}… (и %1$s… — $lang->load() переводит {N} в %N$s) ──
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =============================================================================
   Общие функции
   ============================================================================= */

/**
 * Сроки бана: ключ "дни-месяцы-годы". Подписи на английском — они пишутся в modcomment;
 * в селекте показываем перевод (opt_ban_*).
 */
function fetch_ban_times(): array
{
    return [
        '1-0-0'  => '1 Day',    '2-0-0'  => '2 Days',   '3-0-0'  => '3 Days',
        '4-0-0'  => '4 Days',   '5-0-0'  => '5 Days',   '6-0-0'  => '6 Days',
        '7-0-0'  => '1 Week',   '14-0-0' => '2 Weeks',  '21-0-0' => '3 Weeks',
        '0-1-0'  => '1 Month',  '0-2-0'  => '2 Months', '0-3-0'  => '3 Months',
        '0-4-0'  => '4 Months', '0-5-0'  => '5 Months', '0-6-0'  => '6 Months',
        '0-0-1'  => '1 Year',   '0-0-2'  => '2 Years',
        '---'    => 'Permanent',
    ];
}

function ban_date2timestamp(string $date, int $stamp = 0): int
{
    if ($stamp === 0) {
        $stamp = TIMENOW;
    }
    $d = array_map('intval', explode('-', $date)) + [0, 0, 0];

    return (int)mktime(
        (int)date('G', $stamp),
        (int)date('i', $stamp),
        0,
        (int)date('n', $stamp) + $d[1],
        (int)date('j', $stamp) + $d[0],
        (int)date('Y', $stamp) + $d[2]
    );
}

/** Рейтинг как число (null — нет скачанного) */
function eu_ratio_value(array $userdata): ?float
{
    $dl = (float)$userdata['downloaded'];
    return $dl > 0 ? (float)$userdata['uploaded'] / $dl : null;
}

function get_ratio(array $userdata): string
{
    $r = eu_ratio_value($userdata);
    if ($r === null) {
        return (float)$userdata['uploaded'] > 0 ? '∞' : '---';
    }
    return ts_nf($r, 2);
}

if (!function_exists('get_user_class_name')) {
    function get_user_class_name($class = ''): string
    {
        global $lang;
        if ($class === 'all') {
            return (string)$lang->edituser['all_usergroups'];
        }
        require TSDIR . '/cache/usergroups.php';
        foreach ($usergroups as $arr) {
            if ((string)$arr['gid'] === (string)$class) {
                return (string)$arr['title'];
            }
        }
        return (string)$lang->edituser['all_usergroups'];
    }
}

/** Строка в начало modcomment: "Y-m-d H:i - <action> by <staff>" (формат читает вкладка аудита) */
function modcomment(string $what = 'Unknown Action taken'): string
{
    global $modcomment, $CURUSER;

    $action     = str_replace(["'", "\r", "\n"], ['`', ' ', ' '], $what);
    $username   = $CURUSER['username'] ?? 'System';
    $modcomment = date('Y-m-d H:i') . " - {$action} by {$username}\n" . ($modcomment ?? '');
    return $modcomment;
}

/**
 * Flash-сообщения: с аргументами — кладём в сессию, без аргументов — выводим тосты.
 * Вывод — только после stdhead().
 */
function flash_message(?string $message = null, string $type = 'info'): void
{
    if ($message !== null) {
        $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
        return;
    }
    if (empty($_SESSION['flash'])) {
        return;
    }

    $L          = $GLOBALS['lang']->edituser ?? [];
    $toastTitle = htmlspecialchars((string)($L['toast_title'] ?? 'Message'));
    $toastClose = htmlspecialchars((string)($L['toast_close'] ?? 'Close'));

    echo '<div class="toast-container position-fixed top-0 end-0 p-3 eu-toasts" aria-live="polite" aria-atomic="true">';
    foreach ($_SESSION['flash'] as $flash) {
        // Цвета как раньше: залитая шапка (bg-* text-white / text-dark для warning)
        [$bg, $icon, $close] = match ($flash['type']) {
            'success'         => ['bg-success text-white', 'fa-circle-check',         ' btn-close-white'],
            'error', 'danger' => ['bg-danger text-white',  'fa-circle-exclamation',   ' btn-close-white'],
            'warning'         => ['bg-warning text-dark',  'fa-triangle-exclamation', ''],
            default           => ['bg-info text-white',    'fa-circle-info',          ' btn-close-white'],
        };
        echo '<div class="toast eu-toast border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">'
           .   '<div class="toast-header ' . $bg . '">'
           .     '<i class="fa-solid ' . $icon . ' me-2"></i><strong class="me-auto">' . $toastTitle . '</strong>'
           .     '<button type="button" class="btn-close' . $close . '" data-bs-dismiss="toast" aria-label="' . $toastClose . '"></button>'
           .   '</div>'
           .   '<div class="toast-body">' . htmlspecialchars((string)$flash['message']) . '</div>'
           . '</div>';
    }
    echo '</div>';
    unset($_SESSION['flash']);
}

function admin_redirect(string $url, ?string $message = null): void
{
    if ($message) {
        flash_message($message, 'success');
    }
    $url = str_replace('&amp;', '&', $url);
    if (!headers_sent()) {
        header("Location: {$url}");
    } else {
        echo '<script>window.location.href=' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
    }
    exit;
}

/** Срок предупреждения в неделях */
function weeks(): string
{
    global $lang;
    $L   = $lang->edituser;
    $out = '<option value="0">' . $L['opt_dur_select'] . '</option>';
    for ($w = 1; $w <= 12; $w++) {
        $out .= '<option value="' . $w . '">' . $L['opt_dur_w' . $w] . '</option>';
    }
    return $out . '<option value="255">' . $L['opt_dur_unlimited'] . '</option>';
}

/** Нельзя редактировать себя и группы выше своей */
function permission_check(): void
{
    global $userdata, $usergroups, $CURUSER, $lang;

    if (
        ($userdata['cansettingspanel'] == '1' && $usergroups['cansettingspanel'] != '1') ||
        ($userdata['issupermod'] == '1' && $usergroups['issupermod'] != '1') ||
        ($userdata['canstaffpanel'] == '1' && $usergroups['canstaffpanel'] != '1') ||
        (int)$CURUSER['id'] === (int)$userdata['id']
    ) {
        print_no_permission(false, true, $lang->edituser['err_protected_group']);
    }
}

/** Права на трафик/бонусы/инвайты */
function eu_can_edit_stats(): bool
{
    global $usergroups;
    return $usergroups['cansettingspanel'] == '1' || $usergroups['issupermod'] == '1';
}

/** Права на ручную правку и очистку истории модерации */
function eu_can_edit_modcomment(): bool
{
    global $usergroups;
    return $usergroups['cansettingspanel'] == '1';
}

/** Права на удаление аккаунта */
function eu_can_delete(): bool
{
    return eu_can_edit_stats();
}

/** Данные пользователя + агрегаты одним запросом */
function get_user_data(): void
{
    global $userid, $db, $lang;

    if (!empty($GLOBALS['userdata']['id']) && (int)$GLOBALS['userdata']['id'] === (int)$userid) {
        return;
    }

    $res = $db->sql_query_prepared('
        SELECT u.*,
               g.cansettingspanel, g.canstaffpanel, g.issupermod,
               COALESCE(pc.cnt, 0)           AS post_count,
               COALESCE(tc.cnt, 0)           AS thread_count,
               COALESCE(sn.total, 0)         AS snatch_total,
               COALESCE(sn.done, 0)          AS snatch_done,
               COALESCE(sn.seedtime, 0)      AS snatch_seedtime,
               COALESCE(sn.leechtime, 0)     AS snatch_leechtime,
               COALESCE(pr.peers_total, 0)   AS peers_total,
               COALESCE(pr.peers_seeding, 0) AS peers_seeding,
               COALESCE(rc.cnt, 0)           AS report_count,
               av.vip_until                  AS vip_until
        FROM users u
        LEFT JOIN usergroups g ON g.gid = u.usergroup
        LEFT JOIN auto_vip av ON av.userid = u.id
        LEFT JOIN (SELECT uid, COUNT(*) AS cnt FROM posts   WHERE uid = ? GROUP BY uid) pc ON pc.uid = u.id
        LEFT JOIN (SELECT uid, COUNT(*) AS cnt FROM threads WHERE uid = ? GROUP BY uid) tc ON tc.uid = u.id
        LEFT JOIN (SELECT addedby, COUNT(*) AS cnt FROM reports WHERE addedby = ? GROUP BY addedby) rc ON rc.addedby = u.id
        LEFT JOIN (
            SELECT userid, COUNT(*) AS total, SUM(finished = "yes") AS done,
                   SUM(seedtime) AS seedtime, SUM(leechtime) AS leechtime
            FROM snatched WHERE userid = ? GROUP BY userid
        ) sn ON sn.userid = u.id
        LEFT JOIN (
            SELECT userid, COUNT(*) AS peers_total, SUM(seeder = "yes") AS peers_seeding
            FROM peers WHERE userid = ? GROUP BY userid
        ) pr ON pr.userid = u.id
        WHERE u.id = ?
    ', [$userid, $userid, $userid, $userid, $userid, $userid]);

    $arr = $res ? $db->fetch_array($res) : null;
    if (!$arr) {
        stderr((string)$lang->edituser['err_user_not_found_text'], (string)$lang->edituser['err_user_not_found_title'], 404);
        exit;
    }

    $GLOBALS['userdata'] = $arr;
}

/** Имя занято другим пользователем (без учёта регистра) */
function eu_username_taken(string $username, int $exceptUid): bool
{
    global $db;
    $q = $db->sql_query_prepared('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id <> ? LIMIT 1', [$username, $exceptUid]);
    return $q && $db->num_rows($q) > 0;
}

/** Email занят другим пользователем */
function eu_email_taken(string $email, int $exceptUid): bool
{
    global $db;
    $q = $db->sql_query_prepared('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $exceptUid]);
    return $q && $db->num_rows($q) > 0;
}

/** Группа бана: UC_BANNED, если это бан-группа, иначе первая группа с isbannedgroup = 1 */
function eu_banned_gid(): int
{
    global $db;
    $pref = defined('UC_BANNED') ? (int)UC_BANNED : 9;
    $q = $db->sql_query_prepared('SELECT gid FROM usergroups WHERE isbannedgroup = 1 ORDER BY (gid = ?) DESC, gid ASC LIMIT 1', [$pref]);
    $row = $q ? $db->fetch_array($q) : null;
    return $row ? (int)$row['gid'] : 0;
}

/* =============================================================================
   Инициализация
   ============================================================================= */

$rootpath = './../';
include $rootpath . '/global.php';

$lang->load('edituser');

define('IN_MYBB', 1);
define('IN_ADMINCP', 1);
define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once INC_PATH . '/functions_forum.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/functions_additions.php';

gzip();
maxsysop();

/* =============================================================================
   AJAX: загрузка аватара (avatar_upload.js)
   ============================================================================= */

if (isset($_GET['action']) && $_GET['action'] === 'upload_avatar') {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $json = function (array $arr, int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    };
    $fail = function (string $msg, int $code) use ($is_ajax, $json, $lang): never {
        if ($is_ajax) {
            $json(['success' => false, 'error' => $msg], $code);
        }
        exit(htmlspecialchars(ags_fmt((string)$lang->edituser['err_prefix'], $msg)));
    };

    $user_uid = (int)($CURUSER['id'] ?? 0);
    if ($user_uid <= 0) {
        $fail((string)$lang->edituser['ajax_not_authorized'], 401);
    }

    $uid = (int)($_POST['id'] ?? $_GET['id'] ?? $_GET['userid'] ?? 0);
    if ($uid <= 0) {
        $fail((string)$lang->edituser['ajax_no_profile'], 400);
    }

    // Права: свой профиль или модератор
    if ($user_uid !== $uid && !is_mod($usergroups ?? [])) {
        $fail((string)$lang->edituser['ajax_no_permission_long'], 403);
    }

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        $fail((string)$lang->edituser['ajax_csrf'], 403);
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $fail((string)$lang->edituser['ajax_no_file'], 400);
    }

    // Валидация, перекодирование через GD и ресайз — в upload_avatar() (как в usercp/member)
    $avatarResult = upload_avatar($_FILES['avatar'], $uid);
    if (!empty($avatarResult['error'])) {
        $fail((string)$avatarResult['error'], 415);
    }

    $width  = (int)$avatarResult['width'];
    $height = (int)$avatarResult['height'];
    $dims   = ($width > 0 && $height > 0) ? "{$width}|{$height}" : '';
    $url    = (string)$avatarResult['avatar'];

    $q  = $db->sql_query_prepared('SELECT username, modcomment FROM users WHERE id = ? LIMIT 1', [$uid]);
    $tu = $q ? $db->fetch_array($q) : null;
    $mc = date('Y-m-d H:i') . ' - Avatar updated by ' . $CURUSER['username'] . "\n" . rtrim((string)($tu['modcomment'] ?? ''));

    $db->sql_query_prepared(
        "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = 'upload', modcomment = ? WHERE id = ?",
        [$url, $dims, $mc, $uid]
    );
    if ($user_uid !== $uid) {
        write_log('Avatar of user ' . ($tu['username'] ?? '#' . $uid) . " ({$uid}) uploaded by {$CURUSER['username']}");
    }

    if ($is_ajax) {
        $json(['success' => true, 'url' => $url, 'width' => $width, 'height' => $height,
               'message' => (string)$lang->edituser['ajax_avatar_updated']]);
    }
    header("Location: edituser.php?action=edituser&userid={$uid}");
    exit;
}

/* =============================================================================
   Аватар по URL / локальный путь (поле в форме)
   ============================================================================= */

function is_valid_avatar_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    $ext = strtolower(get_extension($url));
    if (in_array($ext, ['gif', 'jpg', 'jpeg', 'png', 'bmp', 'webp', 'avif'], true)) {
        return true;
    }
    foreach (['/avatar', '/avatars/', '/image/', '/images/', '/img/', '/imgs/', '/profile/',
              '/user/', '/users/', '/photo/', '/picture/', '/pic/', 'gravatar.com/avatar/', 'i.imgur.com/'] as $ind) {
        if (stripos($url, $ind) !== false) {
            return true;
        }
    }
    return false;
}

/** '' — успех, иначе английский текст ошибки (он же пишется в modcomment) */
function process_avatar_url(string $avatar_url, int $user_id): string
{
    global $db, $userdata;

    if ($user_id <= 0) {
        return 'Invalid user ID';
    }
    if (!is_valid_avatar_url($avatar_url)) {
        return 'URL does not point to a valid image file';
    }
    if (strlen($avatar_url) > 200) {
        return 'Avatar URL too long';
    }

    remove_avatars($user_id);

    $dimensions = '';
    $temp_file  = tempnam(sys_get_temp_dir(), 'avatar_');
    require_once INC_PATH . '/functions_remote_connect.php';
    try {
        $content = fetch_remote_file($avatar_url);
        if ($content && $temp_file !== false) {
            file_put_contents($temp_file, $content);
            $info = @getimagesize($temp_file);
            if ($info !== false) {
                $dimensions = $info[0] . '|' . $info[1];
            }
        }
    } catch (Throwable) {
        // размеры не обязательны
    }
    if ($temp_file !== false) {
        @unlink($temp_file);
    }

    $db->sql_query_prepared(
        "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = 'remote' WHERE id = ?",
        [$avatar_url, $dimensions, $user_id]
    );
    $userdata['avatar'] = $avatar_url;
    return '';
}

function is_valid_local_avatar(string $avatar): bool
{
    $normalized = ltrim(str_replace('\\', '/', trim($avatar)), './');
    return str_starts_with($normalized, 'uploads/avatars/')
        && (bool)preg_match('#\.(gif|jpg|jpeg|png|bmp|webp|avif)$#i', $normalized);
}

/** Поле «URL аватара» из основной формы. Вызывается из handleUpdateUser() (CSRF уже проверен). */
function handle_avatar_update(): void
{
    global $userdata, $db, $lang;

    if (!isset($_POST['avatar']) || ($_POST['avatar_changed'] ?? '0') !== '1') {
        return;
    }

    $new_avatar = trim((string)$_POST['avatar']);
    $uid        = (int)$userdata['id'];

    $normalize = fn(string $s): string =>
        (string)preg_replace('/\?dateline=\d+$/', '', strtolower(ltrim(str_replace('\\', '/', trim($s)), './')));

    if ($normalize($new_avatar) === $normalize((string)$userdata['avatar'])) {
        return;
    }

    if ($new_avatar === '') {
        remove_avatars($uid);
        $db->sql_query_prepared("UPDATE users SET avatar = '', avatardimensions = '', avatartype = '' WHERE id = ?", [$uid]);
        $userdata['avatar'] = '';
        modcomment('Avatar removed');
        flash_message((string)$lang->edituser['flash_avatar_removed'], 'success');
        return;
    }

    if (filter_var($new_avatar, FILTER_VALIDATE_URL)) {
        $error = process_avatar_url($new_avatar, $uid);
        if ($error === '') {
            modcomment('Avatar updated via URL');
            flash_message((string)$lang->edituser['flash_avatar_updated'], 'success');
        } else {
            modcomment("Avatar update error: {$error}");
            $errText = match ($error) {
                'Invalid user ID'                          => $lang->edituser['avatar_err_invalid_uid'],
                'URL does not point to a valid image file' => $lang->edituser['avatar_err_not_image'],
                'Avatar URL too long'                      => $lang->edituser['avatar_err_too_long'],
                default                                    => $error,
            };
            flash_message(ags_fmt((string)$lang->edituser['err_prefix'], (string)$errText), 'error');
        }
        return;
    }

    if (is_valid_local_avatar($new_avatar)) {
        $db->sql_query_prepared("UPDATE users SET avatar = ?, avatardimensions = '', avatartype = 'upload' WHERE id = ?", [$new_avatar, $uid]);
        modcomment('Avatar updated (local)');
        flash_message((string)$lang->edituser['flash_avatar_updated'], 'success');
        return;
    }

    flash_message((string)$lang->edituser['flash_avatar_invalid'], 'error');
}

/* =============================================================================
   Маршрутизация
   ============================================================================= */

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$userid = (int)($_POST['userid'] ?? $_GET['userid'] ?? 0);

if ($usergroups['canstaffpanel'] != 1) {
    print_no_permission(true);
}
if ($action === '' || !is_valid_id($userid)) {
    print_no_permission(true);
}

require_once INC_PATH . '/functions_mkprettytime.php';

match ($action) {
    'edituser'      => handleEditUser(),
    'updateuser'    => handleUpdateUser(),
    'deleteaccount' => handleDeleteAccount(),
    'resetpasskey'  => handleResetPasskey(),
    default         => print_no_permission(true),
};

/* =============================================================================
   Рендер: мелкие помощники (классы st-* из st-ui.css, eu-* из edituser.css)
   ============================================================================= */

function eu_card(string $icon, string $soft, string $title, string $body, string $extra = '', string $bodyClass = 'st-card__body', string $class = ''): string
{
    return '<section class="st-card eu-card' . ($class !== '' ? ' ' . $class : '') . '">'
         .   '<div class="st-card__head">'
         .     '<span class="st-ico st-soft-' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
         .     '<span class="min-w-0">' . $title . '</span>'
         .     ($extra !== '' ? '<div class="ms-auto d-flex flex-wrap align-items-center gap-2">' . $extra . '</div>' : '')
         .   '</div>'
         .   '<div class="' . $bodyClass . '">' . $body . '</div>'
         . '</section>';
}

function eu_kpi(string $icon, string $val, string $lbl): string
{
    return '<div class="st-kpi st-kpi--muted">'
         .   '<div class="min-w-0"><div class="st-kpi__val">' . $val . '</div><div class="st-kpi__lbl">' . $lbl . '</div></div>'
         .   '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i>'
         . '</div>';
}

function eu_badge(string $soft, string $icon, string $text): string
{
    return '<span class="st-badge st-soft-' . $soft . '">' . ($icon !== '' ? '<i class="fa-solid ' . $icon . '"></i>' : '') . $text . '</span>';
}

function eu_empty(string $icon, string $text, string $title = ''): string
{
    return '<div class="st-empty">'
         .   '<div class="st-ico st-soft-muted"><i class="fa-solid ' . $icon . '"></i></div>'
         .   ($title !== '' ? '<div class="eu-empty__title">' . $title . '</div>' : '')
         .   '<div>' . $text . '</div>'
         . '</div>';
}

/** Поле формы: подпись + контрол + подсказка */
function eu_field(string $label, string $control, string $hint = '', string $icon = '', bool $required = false): string
{
    return '<div class="eu-field">'
         .   '<label class="eu-lbl">' . ($icon !== '' ? '<i class="fa-solid ' . $icon . '"></i>' : '') . $label
         .     ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
         .   $control
         .   ($hint !== '' ? '<div class="eu-hint">' . $hint . '</div>' : '')
         . '</div>';
}

/** Переключатель: скрытое поле с «выкл» + чекбокс с «вкл» (PHP берёт последнее значение) */
function eu_switch(string $label, string $name, bool $checked, string $icon, string $on = '1', string $off = '0', string $hint = ''): string
{
    $id = 'eu_sw_' . $name;
    return '<label class="eu-switch" for="' . $id . '">'
         .   '<span class="eu-switch__text min-w-0">'
         .     '<span class="eu-switch__lbl"><i class="fa-solid ' . $icon . '"></i>' . $label . '</span>'
         .     ($hint !== '' ? '<span class="eu-hint">' . $hint . '</span>' : '')
         .   '</span>'
         .   '<input type="hidden" name="' . $name . '" value="' . $off . '">'
         .   '<span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . $name . '" value="' . $on . '"' . ($checked ? ' checked' : '') . '></span>'
         . '</label>';
}

/** Строка «метка — значение» (st-rows) */
function eu_row(string $icon, string $label, string $value): string
{
    return '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid ' . $icon . '"></i>' . $label . '</span><span class="st-row__val">' . $value . '</span></div>';
}

function eu_table(string $head, string $rows): string
{
    return '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 eu-table">'
         . '<thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function eu_dt(int $ts, string $fmt = 'relative'): string
{
    return $ts > 0 ? my_datee($fmt, $ts) : '—';
}

/** Мини-вкладки внутри карточки: навигация (в шапку карточки). Панели — eu_subpanes(). */
function eu_subtabs(string $id, array $tabs): string
{
    $nav   = '<ul class="nav eu-subtabs" id="' . $id . '" role="tablist">';
    $first = true;
    foreach ($tabs as $pane => [$icon, $label, $count]) {
        $nav .= '<li class="nav-item" role="presentation">'
              .   '<button class="eu-subtab' . ($first ? ' active' : '') . '" data-bs-toggle="pill" data-bs-target="#' . $pane . '" type="button" role="tab"'
              .     ' aria-controls="' . $pane . '" aria-selected="' . ($first ? 'true' : 'false') . '">'
              .     '<i class="fa-solid ' . $icon . '"></i><span>' . $label . '</span>'
              .     ($count !== '' ? '<span class="eu-count">' . $count . '</span>' : '')
              .   '</button>'
              . '</li>';
        $first = false;
    }
    return $nav . '</ul>';
}

function eu_subpanes(array $panes): string
{
    $out   = '<div class="tab-content">';
    $first = true;
    foreach ($panes as $id => $html) {
        $out .= '<div class="tab-pane fade' . ($first ? ' show active' : '') . '" id="' . $id . '" role="tabpanel">' . $html . '</div>';
        $first = false;
    }
    return $out . '</div>';
}

/**
 * Временный VIP: [бейдж, подсказка] или null.
 * Только группа VIP + запись в auto_vip с vip_until > 0 (0 = бессрочно — не показываем).
 */
function eu_vip_until(array $userdata): ?array
{
    global $lang, $dateformat;
    $L = $lang->edituser;

    $until = (int)($userdata['vip_until'] ?? 0);
    if ($until <= 0 || (int)$userdata['usergroup'] !== UC_VIP) {
        return null;
    }

    $left = $until - TIMENOW;
    $date = my_datee((string)$dateformat, $until);

    if ($left <= 0) {
        // Срок вышел, cron ещё не снял группу
        $badge = eu_badge('danger', 'fa-circle-exclamation', ags_fmt($L['badge_vip_expired'] ?? 'VIP expired {1}', $date));
        $hint  = ags_fmt($L['hint_vip_expired'] ?? 'Temporary VIP expired {1} — the group will be removed by the next cron run.', $date);
    } else {
        $soft  = $left <= 7 * 86400 ? 'warning' : 'info';
        $badge = '<span title="' . htmlspecialchars(ags_fmt($L['tip_vip_left'] ?? 'Left: {1}', mkprettytime($left))) . '">'
               . eu_badge($soft, 'fa-crown', ags_fmt($L['badge_vip_until'] ?? 'VIP until {1}', $date)) . '</span>';
        $hint  = ags_fmt($L['hint_vip_until'] ?? 'Temporary VIP until {1} (left: {2}). After that the user returns to the previous group.', $date, mkprettytime($left));
    }

    return [$badge, $hint];
}

/** Бейдж с датой окончания; оставшееся время — во всплывающей подсказке */
function eu_badge_until(string $soft, string $icon, string $tpl, int $ts): string
{
    global $lang, $dateformat;
    $L    = $lang->edituser;
    $left = $ts - TIMENOW;
    $tip  = $left > 0 ? ags_fmt($L['tip_vip_left'] ?? 'Left: {1}', mkprettytime($left)) : '';
    return '<span' . ($tip !== '' ? ' title="' . htmlspecialchars($tip) . '"' : '') . '>'
         . eu_badge($soft, $icon, ags_fmt($tpl, my_datee((string)$dateformat, $ts))) . '</span>';
}

/** Статус-бейджи в шапке */
function get_user_status_badges(array $userdata): string
{
    global $lang;
    $L = $lang->edituser;
    $b = '';
    if ($userdata['enabled'] === 'no')  $b .= eu_badge('danger',  'fa-ban',                  $L['badge_banned']);
    if ($userdata['warned'] === 'yes') {
        $wu = (int)($userdata['warneduntil'] ?? 0);
        $b .= $wu > 0
            ? eu_badge_until('warning', 'fa-triangle-exclamation', $L['badge_warned_until'] ?? 'Warned until {1}', $wu)
            : eu_badge('warning', 'fa-triangle-exclamation', $L['badge_warned']);
    }
    if (($userdata['leechwarn'] ?? 'no') === 'yes') {
        $lu = (int)($userdata['leechwarnuntil'] ?? 0);
        $b .= $lu > 0
            ? eu_badge_until('danger', 'fa-skull-crossbones', $L['badge_leechwarn_until'] ?? 'Low ratio: ban {1}', $lu)
            : eu_badge('danger', 'fa-skull-crossbones', $L['badge_leechwarn'] ?? 'Low ratio');
    }
    if ($userdata['donor'] === 'yes')   $b .= eu_badge('success', 'fa-heart',                $L['badge_donor']);
    if ((int)$userdata['moderateposts'] === 1) $b .= eu_badge('info', 'fa-eye',             $L['badge_moderated']);
    if ($vip = eu_vip_until($userdata))  $b .= $vip[0];
    return $b;
}

/* =============================================================================
   Вкладки
   ============================================================================= */

/** Основное: аккаунт, аватар, подпись */
function renderBasicInfoTab(): string
{
    global $userdata, $usergroups, $db, $lang, $avatarsize, $CURUSER;
    $L = $lang->edituser;

    // Группы: только те, что текущий сотрудник вправе назначить
    $groupOpts = '';
    $gq = $db->sql_query_prepared('SELECT gid, title, cansettingspanel, issupermod, canstaffpanel FROM usergroups ORDER BY title');
    while ($gq && ($g = $db->fetch_array($gq))) {
        $gid = (int)$g['gid'];
        if (
            ($g['cansettingspanel'] == '1' && $usergroups['cansettingspanel'] != '1') ||
            ($g['issupermod'] == '1' && $usergroups['issupermod'] != '1') ||
            ($g['canstaffpanel'] == '1' && $usergroups['canstaffpanel'] != '1') ||
            (($gid === UC_ADMINISTRATOR || $gid === UC_SYSOP) && $usergroups['cansettingspanel'] != '1')
        ) {
            if ($gid !== (int)$userdata['usergroup']) {
                continue;
            }
        }
        $groupOpts .= '<option value="' . $gid . '"' . ($gid === (int)$userdata['usergroup'] ? ' selected' : '') . '>'
                    . htmlspecialchars_uni((string)$g['title']) . '</option>';
    }

    $account =
        '<div class="row g-3">'
      .   '<div class="col-md-6">' . eu_field($L['lbl_username'],
              '<input type="text" class="form-control" name="username" maxlength="255" required value="' . htmlspecialchars_uni((string)$userdata['username']) . '">',
              '', 'fa-user', true) . '</div>'
      .   '<div class="col-md-6">' . eu_field($L['lbl_email'],
              '<input type="email" class="form-control" name="email" maxlength="255" required value="' . htmlspecialchars_uni((string)$userdata['email']) . '">',
              '', 'fa-envelope', true) . '</div>'
      .   '<div class="col-md-6">' . eu_field($L['lbl_new_password'],
              '<input type="password" class="form-control" name="password" maxlength="255" autocomplete="new-password">',
              $L['hint_password_keep'], 'fa-lock') . '</div>'
      .   '<div class="col-md-6">' . eu_field($L['lbl_usertitle'],
              '<input type="text" class="form-control" name="usertitle" maxlength="255" value="' . htmlspecialchars_uni((string)$userdata['usertitle']) . '">',
              '', 'fa-award') . '</div>'
      .   '<div class="col-12">' . eu_field($L['lbl_usergroup'],
              '<select name="usergroup" class="form-select">' . $groupOpts . '</select>',
              ($vip = eu_vip_until($userdata)) ? '<i class="fa-solid fa-crown me-1"></i>' . $vip[1] : '', 'fa-users') . '</div>'
      . '</div>';

    // Аватар (разметку avatar-container / avatar-input использует /scripts/avatar_upload.js)
    $ua        = format_avatar((string)$userdata['avatar'], (string)$userdata['avatardimensions']);
    $canChange = ((int)$CURUSER['id'] === (int)$userdata['id'] || is_mod($usergroups)) ? '1' : '0';

    $avatar =
        '<div class="eu-ava-wrap">'
      .   '<div class="eu-ava' . ($canChange === '1' ? ' eu-ava--edit' : '') . '" id="avatar-container" tabindex="0" role="button"'
      .     ' data-uid="' . (int)$userdata['id'] . '" data-can-change="' . $canChange . '"'
      .     ' data-max-mb="' . round((int)$avatarsize / 1024, 1) . '"'
      .     ' data-upload-url="edituser.php?action=upload_avatar&amp;userid=' . (int)$userdata['id'] . '"'
      .     ' title="' . htmlspecialchars((string)$L['hdr_avatar_title']) . '">'
      .     '<div><img src="' . $ua['image'] . '" alt="' . htmlspecialchars_uni((string)$userdata['username']) . '" loading="lazy"></div>'
      .     '<span class="avatar-overlay"><i class="fa-solid fa-camera me-1"></i>' . $L['hdr_avatar_change'] . '</span>'
      .     '<div id="avatar-progress" class="eu-ava__progress"><div id="avatar-progress-bar"></div></div>'
      .   '</div>'
      .   '<input type="file" id="avatar-input" accept="image/*" hidden>'
      . '</div>'
      . eu_field($L['lbl_avatar_url'],
            '<input type="text" class="form-control" name="avatar" id="euAvatarUrl" maxlength="500" autocomplete="off" value="' . htmlspecialchars_uni((string)$userdata['avatar']) . '">'
          . '<input type="hidden" name="avatar_changed" id="avatar_changed" value="0">',
            $L['hint_avatar_url'], 'fa-link')
      . '<div class="d-flex flex-wrap gap-2">'
      .   '<button type="button" class="btn btn-sm btn-outline-secondary st-pill" id="euAvatarTest"><i class="fa-solid fa-rotate me-1"></i>' . $L['btn_avatar_test'] . '</button>'
      .   ((string)$userdata['avatar'] !== ''
            ? '<button type="button" class="btn btn-sm btn-outline-danger st-pill" data-eu-action="remove_avatar" data-eu-confirm="' . htmlspecialchars((string)$L['js_confirm']) . '"><i class="fa-solid fa-trash-can me-1"></i>' . $L['btn_avatar_remove'] . '</button>'
            : '')
      . '</div>';

    // Подпись: редактор + превью через парсер (как на member.php)
    $sigPreview = '<em class="text-body-secondary">' . $L['sig_none'] . '</em>';
    if ((string)$userdata['signature'] !== '') {
        require_once INC_PATH . '/class_parser.php';
        $parser     = new postParser();
        $sigPreview = $parser->parse_message((string)$userdata['signature'], [
            'allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'filter_badwords' => 1,
        ]);
    }

    $signature =
        eu_field($L['lbl_signature'],
            '<textarea name="signature" class="form-control" rows="4" placeholder="' . htmlspecialchars((string)$L['ph_signature']) . '">' . htmlspecialchars_uni((string)$userdata['signature']) . '</textarea>')
      . '<div class="eu-lbl"><i class="fa-solid fa-eye"></i>' . $L['lbl_sig_preview'] . '</div>'
      . '<div class="eu-sig">' . $sigPreview . '</div>';

    return '<div class="row g-3">'
         .   '<div class="col-lg-8 d-flex flex-column gap-3">'
         .     eu_card('fa-id-card', 'primary', $L['tab_basic'], $account)
         .     eu_card('fa-signature', 'info', $L['lbl_signature'], $signature)
         .   '</div>'
         .   '<div class="col-lg-4">' . eu_card('fa-image', 'success', $L['hdr_avatar_title'], $avatar) . '</div>'
         . '</div>';
}

/** Права и статус */
function renderPermissionsTab(): string
{
    global $userdata, $lang;
    $L = $lang->edituser;

    $perms = '<div class="eu-switches">'
        . eu_switch($L['lbl_enabled'],       'enabled',       $userdata['enabled'] === 'yes',               'fa-user-check', 'yes', 'no')
        . eu_switch($L['lbl_cancomment'],    'cancomment',    (int)($userdata['cancomment']  ?? 1) === 1,   'fa-comment',    'yes', 'no')
        . eu_switch($L['lbl_canupload'],     'canupload',     (int)($userdata['canupload']   ?? 1) === 1,   'fa-upload',     'yes', 'no')
        . eu_switch($L['lbl_candownload'],   'candownload',   (int)($userdata['candownload'] ?? 1) === 1,   'fa-download',   'yes', 'no')
        . eu_switch($L['lbl_moderateposts'], 'moderateposts', (int)$userdata['moderateposts'] === 1,        'fa-user-gear')
        . '</div>';

    $special = '<div class="eu-switches mb-3">'
        . eu_switch($L['lbl_donor'], 'donor', $userdata['donor'] === 'yes', 'fa-heart', 'yes', 'no')
        . '</div>'
        . eu_field($L['lbl_ipban'],
            '<select name="banip" class="form-select">'
          .   '<option value="no">' . $L['opt_ipban_no'] . '</option>'
          .   '<option value="yes">' . $L['opt_ipban_yes'] . '</option>'
          . '</select>',
            $L['hint_ipban'], 'fa-ban');

    if ($userdata['enabled'] === 'no') {
        $special .= '<div class="eu-note eu-note--danger mt-3">'
                  .   '<i class="fa-solid fa-triangle-exclamation"></i>'
                  .   '<div><strong>' . $L['alert_disabled_title'] . '</strong><div>'
                  .     htmlspecialchars_uni((string)(($userdata['notifs'] ?? '') !== '' ? $userdata['notifs'] : $L['alert_disabled_noreason'])) . '</div></div>'
                  . '</div>';
    }

    return '<div class="row g-3">'
         .   '<div class="col-lg-6">' . eu_card('fa-shield-halved', 'primary', $L['sec_account_perms'], $perms, '', 'eu-flush') . '</div>'
         .   '<div class="col-lg-6">' . eu_card('fa-gem', 'warning', $L['sec_special'], $special) . '</div>'
         . '</div>';
}

/** Предупреждения */
function renderWarningsTab(): string
{
    global $userdata, $ban_user_limit, $dateformat, $lang;
    $L = $lang->edituser;

    $warned    = $userdata['warned'] === 'yes';
    $leechwarn = ($userdata['leechwarn'] ?? 'no') === 'yes';

    $until = function (int $ts) use ($dateformat, $L): array {
        if ($ts <= 0) {
            return ['<strong>' . $L['never'] . '</strong>', ''];
        }
        $left = max(0, $ts - TIMENOW);
        return [my_datee($dateformat, $ts), ags_fmt($L['warn_remaining'], mkprettytime($left))];
    };

    $notes = '';
    if ($warned) {
        [$exp, $left] = $until((int)$userdata['warneduntil']);
        $notes .= '<div class="eu-note eu-note--warning mb-3"><i class="fa-solid fa-triangle-exclamation"></i><div>'
                . '<strong>' . $L['warn_active_title'] . '</strong>'
                . '<div>' . ags_fmt($L['warn_expires'], $exp) . '</div>'
                . ($left !== '' ? '<div class="eu-hint">' . $left . '</div>' : '')
                . '</div></div>';
    }
    if ($leechwarn) {
        [$exp, $left] = $until((int)($userdata['leechwarnuntil'] ?? 0));
        $notes .= '<div class="eu-note eu-note--danger mb-3"><i class="fa-solid fa-skull-crossbones"></i><div>'
                . '<strong>' . $L['leechwarn_title'] . '</strong>'
                . '<div>' . ags_fmt($L['leechwarn_expires'], $exp) . '</div>'
                . ($left !== '' ? '<div class="eu-hint">' . $left . '</div>' : '')
                . '</div></div>';
    }

    if ($warned) {
        // Снять предупреждение: выключить переключатель
        $controls = '<div class="eu-switches">'
                  . eu_switch($L['opt_warned'], 'warned', true, 'fa-triangle-exclamation', 'yes', 'no', $L['lbl_warn_status'])
                  . '</div>';
    } else {
        $controls = eu_field($L['lbl_warn_duration'], '<select name="warnlength" class="form-select">' . weeks() . '</select>', '', 'fa-hourglass-half')
                  . eu_field($L['lbl_warn_reason'],
                        '<input type="text" class="form-control" name="warnpm" maxlength="255" placeholder="' . htmlspecialchars((string)$L['ph_warn_reason']) . '">',
                        $L['hint_warn_reason'] ?? 'The reason is sent to the user in a PM.', 'fa-comment-dots');
    }

    $limit    = (int)($ban_user_limit ?? 0);
    $times    = (int)$userdata['timeswarned'];
    $timesSoft = ($limit > 0 && $times >= $limit) ? 'danger' : ($times > 0 ? 'warning' : 'success');

    $stats = '<div class="st-rows mb-3">'
           . eu_row('fa-hashtag', $L['lbl_times_warned'], eu_badge($timesSoft, '', (string)$times))
           . ($limit > 0 ? eu_row('fa-gavel', ags_fmt($L['hint_warn_max'], $limit), '') : '')
           . '</div>';

    if ($times > 0) {
        $stats .= '<div class="eu-switches mb-3">'
                . eu_switch($L['lbl_reset_warn_counter'], 'reset_timeswarned', false, 'fa-rotate-left', 'yes', '')
                . '</div>';
    }
    $stats .= get_last_warning_info();

    return '<div class="row g-3">'
         .   '<div class="col-lg-6">' . eu_card('fa-circle-exclamation', 'warning', $L['sec_warn_controls'], $notes . $controls) . '</div>'
         .   '<div class="col-lg-6">' . eu_card('fa-chart-column', 'info', $L['sec_warn_stats'], $stats) . '</div>'
         . '</div>';
}

function get_last_warning_info(): string
{
    global $userdata, $db, $BASEURL, $dateformat, $lang;
    $L = $lang->edituser;

    if ((int)$userdata['timeswarned'] === 0 || empty($userdata['warnedby'])) {
        return '<div class="eu-note"><i class="fa-solid fa-circle-info"></i><div>' . $L['warn_never'] . '</div></div>';
    }

    $elapsed = mkprettytime(max(0, TIMENOW - (int)$userdata['lastwarned']));
    $by      = ags_fmt($L['last_warn_by_system'], $elapsed);

    if ($userdata['warnedby'] !== 'System') {
        $q   = $db->sql_query_prepared('SELECT id, username, usergroup FROM users WHERE id = ? LIMIT 1', [(int)$userdata['warnedby']]);
        $arr = $q ? $db->fetch_array($q) : null;
        if ($arr) {
            $by = ags_fmt($L['last_warn_by_user'], $elapsed,
                '<a href="' . $BASEURL . '/' . get_profile_link((int)$arr['id']) . '">'
                . format_name(htmlspecialchars_uni((string)$arr['username']), (int)$arr['usergroup']) . '</a>');
        }
    }

    return '<div class="eu-note"><i class="fa-solid fa-clock-rotate-left"></i><div>'
         . '<strong>' . $L['sec_last_warning'] . '</strong><div>' . $by . '</div>'
         . '<div class="eu-hint">' . ags_fmt($L['last_warn_date'], my_datee($dateformat, (int)$userdata['lastwarned'])) . '</div>'
         . '</div></div>';
}

/** Статистика: трафик, бонусы, графики */
function renderStatisticsTab(): string
{
    global $userdata, $lang;
    $L = $lang->edituser;

    $can = eu_can_edit_stats();
    $dis = $can ? '' : ' disabled';

    $num = fn(string $name, string $val, string $label, string $hint, string $icon): string =>
        eu_field($label, '<input type="number" class="form-control" name="' . $name . '" value="' . htmlspecialchars($val) . '" min="0" step="any"' . $dis . '>', $hint, $icon);

    $traffic = $num('uploaded', (string)(int)$userdata['uploaded'], $L['lbl_uploaded_amount'], ags_fmt($L['hint_current'], mksize($userdata['uploaded'])), 'fa-arrow-up')
             . $num('downloaded', (string)(int)$userdata['downloaded'], $L['lbl_downloaded_amount'], ags_fmt($L['hint_current'], mksize($userdata['downloaded'])), 'fa-arrow-down');

    $bonus = $num('seedbonus', (string)(float)$userdata['seedbonus'], $L['stat_bonus'], ags_fmt($L['hint_current_points'], ts_nf((float)$userdata['seedbonus'])), 'fa-coins')
           . $num('invites', (string)(int)$userdata['invites'], $L['lbl_invite_count'], $L['hint_invites_available'], 'fa-ticket');

    $notice = $can ? '' :
        '<div class="eu-note eu-note--info mb-3"><i class="fa-solid fa-lock"></i><div><strong>' . $L['perm_notice_title'] . '</strong> ' . $L['perm_notice_text'] . '</div></div>';

    $charts = eu_card('fa-chart-column', 'primary',
                $L['chart_traffic'] . ' <span class="eu-hint">' . $L['chart_traffic_note'] . '</span>',
                '<div class="eu-chart"><canvas id="trafficChart"></canvas></div>')
            . eu_card('fa-chart-simple', 'success',
                $L['chart_activity'] . ' <span class="eu-hint">' . $L['chart_activity_note'] . '</span>',
                '<div class="eu-chart eu-chart--sm"><canvas id="activityChart"></canvas></div>');

    return $notice
         . '<div class="row g-3 mb-3">'
         .   '<div class="col-lg-6">' . eu_card('fa-arrow-right-arrow-left', 'success', $L['sec_traffic'], $traffic) . '</div>'
         .   '<div class="col-lg-6">' . eu_card('fa-gift', 'warning', $L['sec_bonus_invites'], $bonus) . '</div>'
         . '</div>'
         . '<div class="d-flex flex-column gap-3">' . $charts . '</div>';
}

/** Модерация: заметки, системная информация */
function renderAdvancedTab(): string
{
    global $userdata, $lang;
    $L = $lang->edituser;

    $canEdit = eu_can_edit_modcomment();

    $notes = eu_field($L['lbl_addcomment'],
                '<textarea name="addcomment" class="form-control" rows="2" maxlength="500" placeholder="' . htmlspecialchars((string)$L['ph_addcomment']) . '"></textarea>',
                $L['hint_addcomment'], 'fa-pen')
           . eu_field($L['lbl_modcomment'],
                '<textarea name="modcomment" class="form-control eu-mono" rows="14"' . ($canEdit ? '' : ' readonly') . ' placeholder="' . htmlspecialchars((string)$L['ph_modcomment']) . '">'
              . htmlspecialchars_uni((string)$userdata['modcomment']) . '</textarea>',
                $canEdit ? '' : $L['hint_readonly'], 'fa-clipboard-list');

    $system = '<div class="st-rows mb-3">'
            . eu_row('fa-calendar-days', $L['info_registered'],  eu_dt((int)$userdata['added'], 'd M Y'))
            . eu_row('fa-clock',         $L['info_last_active'], eu_dt((int)$userdata['lastactive'], 'd M Y H:i'))
            . eu_row('fa-clock-rotate-left', $L['info_last_visit'], eu_dt((int)$userdata['lastvisit'], 'd M Y H:i'))
            . eu_row('fa-comments',      $L['info_forum_posts'], ts_nf((int)$userdata['post_count']))
            . '</div>'
            . eu_field($L['lbl_karma_log'] . ' <span class="eu-hint">' . $L['lbl_karma_log_note'] . '</span>',
                '<textarea class="form-control eu-mono" rows="4" readonly>' . htmlspecialchars_uni((string)$userdata['bonuscomment']) . '</textarea>',
                $L['hint_karma_log'], 'fa-seedling');

    $bonusBtn = '<a class="btn btn-sm btn-outline-primary st-pill" title="' . htmlspecialchars((string)$L['hint_bonus_history']) . '"'
              . ' href="index.php?act=bonuspoints&amp;action=log&amp;user=%23' . (int)$userdata['id'] . '&amp;period=all">'
              . '<i class="fa-solid fa-clock-rotate-left me-1"></i>' . $L['btn_bonus_history'] . '</a>';

    return '<div class="row g-3">'
         .   '<div class="col-lg-7">' . eu_card('fa-gears', 'primary', $L['sec_mod_comments'], $notes) . '</div>'
         .   '<div class="col-lg-5">' . eu_card('fa-database', 'muted', $L['sec_system'], $system, $bonusBtn) . '</div>'
         . '</div>';
}

/** Настройки аккаунта пользователя */
function renderAccountSettingsTab(): string
{
    global $userdata, $lang;
    $L = $lang->edituser;
    $on = fn(string $f): bool => (int)$userdata[$f] === 1;

    $privacy = '<div class="eu-switches">'
        . eu_switch($L['lbl_invisible'],        'invisible',        $on('invisible'),        'fa-user-secret')
        . eu_switch($L['lbl_allownotices'],     'allownotices',     $on('allownotices'),     'fa-envelope')
        . eu_switch($L['lbl_hideemail'],        'hideemail',        $on('hideemail'),        'fa-eye-slash')
        . eu_switch($L['lbl_receivepms'],       'receivepms',       $on('receivepms'),       'fa-comments')
        . eu_switch($L['lbl_receivefrombuddy'], 'receivefrombuddy', $on('receivefrombuddy'), 'fa-user-group')
        . '</div>';

    $sm = (int)$userdata['subscriptionmethod'];
    $subOpts = '';
    foreach ([0 => 'opt_sub_none', 1 => 'opt_sub_nonotify', 2 => 'opt_sub_email', 3 => 'opt_sub_pm'] as $v => $k) {
        $subOpts .= '<option value="' . $v . '"' . ($sm === $v ? ' selected' : '') . '>' . $L[$k] . '</option>';
    }

    $notify = '<div class="eu-switches mb-3">'
        . eu_switch($L['lbl_pmnotice'],          'pmnotice',          $on('pmnotice'),          'fa-bell')
        . eu_switch($L['lbl_pmnotify'],          'pmnotify',          $on('pmnotify'),          'fa-envelope-open-text')
        . eu_switch($L['lbl_buddyrequestspm'],   'buddyrequestspm',   $on('buddyrequestspm'),   'fa-user-plus')
        . eu_switch($L['lbl_buddyrequestsauto'], 'buddyrequestsauto', $on('buddyrequestsauto'), 'fa-circle-check')
        . '</div>'
        . eu_field($L['lbl_subscription'], '<select name="subscriptionmethod" class="form-select">' . $subOpts . '</select>', '', 'fa-rss');

    return '<div class="row g-3">'
         .   '<div class="col-lg-6">' . eu_card('fa-user-shield', 'success', $L['sec_email_privacy'], $privacy, '', 'eu-flush') . '</div>'
         .   '<div class="col-lg-6">' . eu_card('fa-bell', 'warning', $L['sec_notifications'], $notify) . '</div>'
         . '</div>'
         . '<div class="eu-note eu-note--info mt-3"><i class="fa-solid fa-circle-info"></i><div><strong>' . $L['note_title'] . '</strong> ' . $L['note_buddy_text'] . '</div></div>';
}

/** Активность: последние раздачи, скачивания, комментарии */
function renderActivityTab(): string
{
    global $userdata, $db, $BASEURL, $dateformat, $lang;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $item = fn(string $main, string $sub, string $side): string =>
        '<li class="eu-list__item"><div class="min-w-0"><div class="eu-list__main">' . $main . '</div><div class="eu-list__sub">' . $sub . '</div></div>'
        . ($side !== '' ? '<div class="eu-list__side">' . $side . '</div>' : '') . '</li>';

    $uploads = '';
    $q = $db->sql_query_prepared('SELECT id, name, added, seeders FROM torrents WHERE owner = ? ORDER BY added DESC LIMIT 10', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $uploads .= $item(
            '<a href="' . $BASEURL . '/details.php?id=' . (int)$r['id'] . '">' . htmlspecialchars_uni((string)$r['name']) . '</a>',
            my_datee($dateformat, (int)$r['added']),
            (int)$r['seeders'] > 0 ? eu_badge('success', 'fa-arrow-up', ags_fmt($L['badge_seeders'], (int)$r['seeders'])) : eu_badge('danger', 'fa-skull', $L['act_dead'])
        );
    }

    $downloads = '';
    $q = $db->sql_query_prepared('SELECT s.torrentid, t.name, s.completedat, s.finished FROM snatched s LEFT JOIN torrents t ON t.id = s.torrentid WHERE s.userid = ? ORDER BY s.completedat DESC LIMIT 10', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $name = $r['name'] !== null
            ? '<a href="' . $BASEURL . '/details.php?id=' . (int)$r['torrentid'] . '">' . htmlspecialchars_uni((string)$r['name']) . '</a>'
            : '<em class="text-body-secondary">' . $L['deleted_torrent'] . '</em>';
        $downloads .= $item(
            $name,
            eu_dt((int)$r['completedat'], (string)$dateformat),
            $r['finished'] === 'yes' ? eu_badge('success', 'fa-check', $L['act_finished']) : eu_badge('warning', 'fa-hourglass-half', $L['act_incomplete'])
        );
    }

    $comments = '';
    $q = $db->sql_query_prepared('SELECT c.id, c.torrent, c.dateline, c.text, t.name FROM comments c LEFT JOIN torrents t ON t.id = c.torrent WHERE c.user = ? ORDER BY c.dateline DESC LIMIT 5', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $txt  = trim(strip_tags((string)$r['text']));
        $cut  = mb_strimwidth($txt, 0, 120, '…');
        $torr = $r['name'] !== null ? htmlspecialchars_uni((string)$r['name']) : '#' . (int)$r['torrent'];
        $comments .= $item(
            '<a href="' . $BASEURL . '/details.php?id=' . (int)$r['torrent'] . '#comments">' . $torr . '</a>',
            htmlspecialchars($cut, ENT_QUOTES, 'UTF-8'),
            '<span class="eu-hint">' . my_datee($dateformat, (int)$r['dateline']) . '</span>'
        );
    }

    $list = fn(string $html, string $icon, string $empty): string =>
        $html !== '' ? '<ul class="eu-list eu-list--scroll">' . $html . '</ul>' : eu_empty($icon, $empty);

    $seed  = (int)$userdata['snatch_seedtime']  > 0 ? mkprettytime((int)$userdata['snatch_seedtime'])  : '—';
    $leech = (int)$userdata['snatch_leechtime'] > 0 ? mkprettytime((int)$userdata['snatch_leechtime']) : '—';

    $summary = '<div class="eu-chips mb-3">'
             . '<span class="eu-chip"><i class="fa-solid fa-download"></i><b>' . ts_nf((int)$userdata['snatch_total']) . '</b>' . $L['act_snatches'] . '</span>'
             . '<span class="eu-chip"><i class="fa-solid fa-check"></i><b>' . ts_nf((int)$userdata['snatch_done']) . '</b>' . $L['act_completed'] . '</span>'
             . '<span class="eu-chip"><i class="fa-solid fa-seedling"></i><b>' . $seed . '</b>' . $L['act_seed_time'] . '</span>'
             . '<span class="eu-chip"><i class="fa-solid fa-hourglass-half"></i><b>' . $leech . '</b>' . $L['act_leech_time'] . '</span>'
             . '<span class="eu-chip"><i class="fa-solid fa-satellite-dish"></i><b>' . (int)$userdata['peers_seeding'] . ' / ' . (int)$userdata['peers_total'] . '</b>' . $L['act_active_peers'] . '</span>'
             . '</div>';

    return $summary
         . '<div class="row g-3">'
         .   '<div class="col-lg-6">' . eu_card('fa-upload', 'primary', $L['act_last_uploads'], $list($uploads, 'fa-upload', $L['act_no_uploads']), '', 'eu-flush') . '</div>'
         .   '<div class="col-lg-6">' . eu_card('fa-download', 'success', $L['act_last_downloads'], $list($downloads, 'fa-download', $L['act_no_downloads']), '', 'eu-flush') . '</div>'
         .   '<div class="col-12">' . eu_card('fa-comment', 'warning', $L['act_last_comments'], $list($comments, 'fa-comment-slash', $L['act_no_comments']), '', 'eu-flush') . '</div>'
         . '</div>';
}

/** Безопасность: IP, вход, пасскей, сессии */
function renderSecurityTab(): string
{
    global $userdata, $db, $dateformat, $lang, $BASEURL;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $ip = function ($raw) use ($db): string {
        if ($raw === null || $raw === '') {
            return '—';
        }
        $s = my_inet_ntop($db->unescape_binary($raw));
        return $s !== false && $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : '—';
    };
    $regip  = $ip($userdata['regip'] ?? '');
    $lastip = $ip($userdata['lastip'] ?? '');

    $ipLink = fn(string $v): string => $v === '—' ? $v
        : '<a href="' . $BASEURL . '/admin/index.php?act=ipsearch&amp;ip=' . rawurlencode(html_entity_decode($v)) . '"><code>' . $v . '</code></a>';

    $ipBody = '<div class="st-rows">'
            . eu_row('fa-user-plus', $L['sec_reg_ip'], $ipLink($regip))
            . eu_row('fa-location-dot', $L['sec_last_ip'], $ipLink($lastip))
            . '</div>'
            . (($regip !== $lastip && $regip !== '—' && $lastip !== '—')
                ? '<div class="eu-note eu-note--warning mt-3"><i class="fa-solid fa-triangle-exclamation"></i><div>' . $L['sec_ip_differs'] . '</div></div>'
                : '');

    $attempts = (int)$userdata['loginattempts'];
    $lockout  = (int)$userdata['loginlockoutexpiry'];
    $status   = $lockout > TIMENOW
        ? eu_badge('danger', 'fa-lock', ags_fmt($L['sec_locked_until'], my_datee($dateformat, $lockout)))
        : ($attempts > 0 ? eu_badge('warning', 'fa-triangle-exclamation', ags_fmt($L['sec_failed_attempts'], $attempts)) : eu_badge('success', 'fa-check', $L['sec_ok']));

    $loginBody = '<div class="st-rows">'
               . eu_row('fa-shield-halved', $L['sec_login_status'], $status)
               . eu_row('fa-right-to-bracket', $L['sec_last_login'], eu_dt((int)($userdata['last_login'] ?? 0), (string)$dateformat))
               . eu_row('fa-calendar-days', $L['info_registered'], eu_dt((int)$userdata['added'], (string)$dateformat))
               . '</div>';
    $lockBtn = ($attempts > 0 || $lockout > 0)
        ? '<button type="button" class="btn btn-sm btn-outline-warning st-pill" data-eu-action="reset_loginattempts" data-eu-confirm="' . htmlspecialchars((string)$L['js_confirm']) . '">'
          . '<i class="fa-solid fa-unlock me-1"></i>' . $L['btn_reset_lockout'] . '</button>'
        : '';

    $passkey = htmlspecialchars((string)$userdata['passkey'], ENT_QUOTES, 'UTF-8');
    $pkBody  = '<div class="eu-passkey">'
             . '<code id="passkeyText" class="eu-passkey__val is-hidden">' . $passkey . '</code>'
             . '<button type="button" class="st-icon-btn border-0" id="passkeyToggle" title="' . htmlspecialchars((string)$L['sec_passkey']) . '"><i class="fa-solid fa-eye"></i></button>'
             . '<button type="button" class="st-icon-btn border-0" id="passkeyCopy" data-copy="' . $passkey . '"><i class="fa-solid fa-copy"></i></button>'
             . '</div>';

    $sessions = '';
    $q = $db->sql_query_prepared('SELECT ip, time, useragent FROM sessions WHERE uid = ? ORDER BY time DESC LIMIT 10', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $sessions .= '<li class="eu-list__item"><div class="min-w-0">'
                   . '<div class="eu-list__main"><code>' . $ip($r['ip']) . '</code></div>'
                   . '<div class="eu-list__sub text-truncate">' . htmlspecialchars(mb_strimwidth((string)$r['useragent'], 0, 90, '…'), ENT_QUOTES, 'UTF-8') . '</div>'
                   . '</div><div class="eu-list__side eu-hint">' . my_datee('relative', (int)$r['time']) . '</div></li>';
    }
    $killBtn = $sessions !== ''
        ? '<button type="button" class="btn btn-sm btn-outline-danger st-pill" data-eu-action="kill_sessions" data-eu-danger="1" data-eu-confirm="' . htmlspecialchars((string)$L['js_confirm']) . '">'
          . '<i class="fa-solid fa-right-from-bracket me-1"></i>' . $L['btn_kill_sessions'] . '</button>'
        : '';

    return '<div class="row g-3">'
         .   '<div class="col-lg-6">' . eu_card('fa-network-wired', 'primary', $L['sec_ip_info'], $ipBody) . '</div>'
         .   '<div class="col-lg-6">' . eu_card('fa-lock', 'warning', $L['sec_login_security'], $loginBody, $lockBtn) . '</div>'
         .   '<div class="col-12">' . eu_card('fa-key', 'success', $L['sec_passkey'], $pkBody) . '</div>'
         .   '<div class="col-12">' . eu_card('fa-desktop', 'info', $L['sec_active_sessions'],
                $sessions !== '' ? '<ul class="eu-list eu-list--scroll">' . $sessions . '</ul>' : eu_empty('fa-desktop', $L['sec_no_sessions']),
                $killBtn, 'eu-flush') . '</div>'
         . '</div>';
}

/** Hit & Run (порог — настройка hr_min_seed_hours, как в cron weekly_cleanups) */
function renderHitRunTab(): string
{
    global $userdata, $db, $BASEURL, $dateformat, $lang, $hr_min_seed_hours;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];
    $min = max(1, (int)($hr_min_seed_hours ?? 24));

    $finished = (int)$userdata['snatch_done'];

    $rows = '';
    $hnr  = 0;
    $q = $db->sql_query_prepared("
        SELECT s.torrentid, t.name, s.downloaded, s.seedtime, s.completedat
        FROM snatched s
        LEFT JOIN torrents t ON t.id = s.torrentid
        WHERE s.userid = ? AND s.finished = 'yes' AND s.seedtime < ?
        ORDER BY s.completedat DESC
    ", [$uid, $min * 3600]);
    while ($q && ($r = $db->fetch_array($q))) {
        $hnr++;
        if ($hnr > 50) {
            continue; // считаем все, показываем 50
        }
        $seedH   = round((int)$r['seedtime'] / 3600, 1);
        $missing = round($min - $seedH, 1);
        $pct     = (int)min(100, round($seedH / $min * 100));
        $soft    = $seedH <= 0 ? 'danger' : ($missing > $min / 2 ? 'warning' : 'info');
        $name    = $r['name'] !== null
            ? '<a href="' . $BASEURL . '/details.php?id=' . (int)$r['torrentid'] . '">' . htmlspecialchars_uni((string)$r['name']) . '</a>'
            : '<em class="text-body-secondary">' . $L['deleted_torrent'] . '</em>';

        $rows .= '<tr class="st-row--bad">'
               . '<td class="eu-td-name">' . $name . '<div class="eu-hint"><i class="fa-solid fa-clock me-1"></i>' . ags_fmt($L['hnr_missing'], ags_fmt($L['unit_hours'], $missing)) . '</div></td>'
               . '<td class="eu-td-progress"><progress class="eu-progress eu-progress--' . $soft . '" value="' . $pct . '" max="100"></progress>'
               .   '<div class="eu-hint text-center">' . ags_fmt($L['unit_hours'], $seedH) . ' / ' . ags_fmt($L['unit_hours'], $min) . '</div></td>'
               . '<td class="text-end text-nowrap">' . mksize((int)$r['downloaded']) . '</td>'
               . '<td class="text-end text-nowrap eu-hint">' . eu_dt((int)$r['completedat'], (string)$dateformat) . '</td>'
               . '</tr>';
    }

    $ratio  = $finished > 0 ? round($hnr / $finished * 100, 1) : 0.0;
    $soft   = $ratio < 10 ? 'success' : ($ratio < 30 ? 'warning' : 'danger');
    $status = $ratio < 10 ? $L['hnr_good'] : ($ratio < 30 ? $L['hnr_warning'] : $L['hnr_critical']);

    $kpis = '<div class="eu-kpis mb-3">'
          . eu_kpi('fa-download',             ts_nf((int)$userdata['snatch_total']), $L['hnr_total'])
          . eu_kpi('fa-circle-check',         ts_nf($finished), $L['act_completed'])
          . eu_kpi('fa-person-running',       ts_nf($hnr), $L['hnr_count'] . ' <span class="eu-hint">&lt;' . ags_fmt($L['hnr_under'], ags_fmt($L['unit_hours'], $min)) . '</span>')
          . eu_kpi('fa-percent',              $ratio . '% ' . eu_badge($soft, '', $status), $L['hnr_ratio'])
          . '</div>';

    if ($ratio >= 10) {
        // Кнопки ведут на реальные инструменты страницы: вкладки «Предупреждения» и «Права»
        $alert = '<div class="eu-note eu-note--' . $soft . ' mb-3"><i class="fa-solid fa-triangle-exclamation"></i>'
               . '<div class="flex-grow-1"><strong>' . ($ratio >= 30 ? $L['hnr_alert_critical'] : $L['hnr_alert_moderate']) . '</strong>'
               . '<div>' . ($ratio >= 30 ? $L['hnr_alert_critical_text'] : $L['hnr_alert_moderate_text']) . '</div></div>'
               . '<div class="d-flex flex-wrap gap-2">'
               .   '<button type="button" class="btn btn-sm btn-warning st-pill" data-eu-goto="#warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>' . $L['btn_issue_warning'] . '</button>'
               .   '<button type="button" class="btn btn-sm btn-outline-danger st-pill" data-eu-goto="#permissions"><i class="fa-solid fa-ban me-1"></i>' . $L['btn_restrict_dl'] . '</button>'
               . '</div></div>';
    } else {
        $alert = '<div class="eu-note eu-note--success mb-3"><i class="fa-solid fa-thumbs-up"></i><div><strong>' . $L['hnr_good_title'] . '</strong><div>'
               . htmlspecialchars((string)$L['hnr_good_text']) . '</div></div></div>';
    }

    $table = $rows !== ''
        ? eu_table(
            '<th>' . $L['th_torrent_name'] . '</th><th class="text-center">' . $L['th_seed_required'] . '</th>'
          . '<th class="text-end">' . $L['stat_downloaded'] . '</th><th class="text-end">' . $L['act_completed'] . '</th>',
            $rows)
        : eu_empty('fa-circle-check', $L['hnr_none']);

    return $kpis . $alert
         . eu_card('fa-list', 'danger', $L['hnr_details'], $table, eu_badge('muted', '', ags_fmt($L['hnr_records'], $hnr)), 'eu-flush');
}

/** Форум */
function renderForumTab(): string
{
    global $userdata, $db, $BASEURL, $dateformat, $lang;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $posts = '';
    $q = $db->sql_query_prepared('
        SELECT p.pid, p.subject, p.dateline, p.message, t.subject AS thread_subject, f.name AS forum_name
        FROM posts p
        LEFT JOIN threads t ON t.tid = p.tid
        LEFT JOIN forums  f ON f.fid = p.fid
        WHERE p.uid = ?
        ORDER BY p.dateline DESC
        LIMIT 10', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $txt = trim(strip_tags((string)$r['message']));
        $posts .= '<li class="eu-list__item"><div class="min-w-0">'
                . '<div class="eu-list__main"><a href="' . $BASEURL . '/' . get_post_link((int)$r['pid']) . '#pid' . (int)$r['pid'] . '">'
                .   htmlspecialchars_uni((string)($r['thread_subject'] ?: $r['subject'])) . '</a></div>'
                . '<div class="eu-list__sub"><i class="fa-solid fa-folder me-1"></i>' . htmlspecialchars_uni((string)$r['forum_name']) . '</div>'
                . ($txt !== '' ? '<div class="eu-list__sub">' . htmlspecialchars(mb_strimwidth($txt, 0, 120, '…'), ENT_QUOTES, 'UTF-8') . '</div>' : '')
                . '</div><div class="eu-list__side eu-hint">' . my_datee($dateformat, (int)$r['dateline']) . '</div></li>';
    }

    $threads = '';
    $q = $db->sql_query_prepared('
        SELECT t.tid, t.subject, t.dateline, t.replies, t.views, f.name AS forum_name
        FROM threads t
        LEFT JOIN forums f ON f.fid = t.fid
        WHERE t.uid = ?
        ORDER BY t.dateline DESC
        LIMIT 10', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $threads .= '<li class="eu-list__item"><div class="min-w-0">'
                  . '<div class="eu-list__main"><a href="' . $BASEURL . '/' . get_thread_link((int)$r['tid']) . '">' . htmlspecialchars_uni((string)$r['subject']) . '</a></div>'
                  . '<div class="eu-list__sub"><i class="fa-solid fa-folder me-1"></i>' . htmlspecialchars_uni((string)$r['forum_name'])
                  .   '<span class="ms-2"><i class="fa-solid fa-reply me-1"></i>' . ags_fmt($L['forum_replies'], (int)$r['replies']) . '</span>'
                  .   '<span class="ms-2"><i class="fa-solid fa-eye me-1"></i>' . (int)$r['views'] . '</span></div>'
                  . '</div><div class="eu-list__side eu-hint">' . my_datee($dateformat, (int)$r['dateline']) . '</div></li>';
    }

    $chips = '<div class="eu-chips mb-3">'
           . '<span class="eu-chip"><i class="fa-solid fa-comment"></i><b>' . ts_nf((int)$userdata['post_count']) . '</b>' . $L['forum_posts'] . '</span>'
           . '<span class="eu-chip"><i class="fa-solid fa-file-lines"></i><b>' . ts_nf((int)$userdata['thread_count']) . '</b>' . $L['forum_threads'] . '</span>'
           . '<span class="eu-chip"><i class="fa-solid fa-flag"></i><b>' . ts_nf((int)$userdata['report_count']) . '</b>' . $L['forum_reports_filed'] . '</span>'
           . '</div>';

    $allPosts   = '<a href="' . $BASEURL . '/search.php?action=finduser&amp;uid=' . $uid . '" class="btn btn-sm btn-outline-primary st-pill"><i class="fa-solid fa-magnifying-glass me-1"></i>' . $L['btn_all_posts'] . '</a>';
    $allThreads = '<a href="' . $BASEURL . '/search.php?action=finduserthreads&amp;uid=' . $uid . '" class="btn btn-sm btn-outline-success st-pill"><i class="fa-solid fa-list me-1"></i>' . $L['btn_all_threads'] . '</a>';

    return $chips
         . '<div class="row g-3">'
         .   '<div class="col-lg-6">' . eu_card('fa-comment', 'primary', $L['forum_last_posts'],
                $posts !== '' ? '<ul class="eu-list eu-list--scroll">' . $posts . '</ul>' : eu_empty('fa-comment-slash', $L['forum_no_posts']), $allPosts, 'eu-flush') . '</div>'
         .   '<div class="col-lg-6">' . eu_card('fa-file-lines', 'success', $L['forum_last_threads'],
                $threads !== '' ? '<ul class="eu-list eu-list--scroll">' . $threads . '</ul>' : eu_empty('fa-file-circle-xmark', $L['forum_no_threads']), $allThreads, 'eu-flush') . '</div>'
         . '</div>';
}

/** Данные графиков вкладки «Статистика» (пропущенные месяцы/дни дополняются нулями) */
function buildChartData(int $uid): array
{
    global $db;

    $q = $db->sql_query_prepared("
        SELECT DATE_FORMAT(FROM_UNIXTIME(completedat), '%Y-%m') AS m, SUM(downloaded) AS dl, SUM(uploaded) AS ul
        FROM snatched
        WHERE userid = ? AND finished = 'yes' AND completedat >= ?
        GROUP BY m", [$uid, (int)strtotime('first day of -11 months midnight', TIMENOW)]);
    $byMonth = [];
    while ($q && ($r = $db->fetch_array($q))) {
        $byMonth[(string)$r['m']] = [round((float)$r['dl'] / 1073741824, 2), round((float)$r['ul'] / 1073741824, 2)];
    }
    $months = $dl = $ul = [];
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', (int)strtotime("first day of -{$i} months", TIMENOW));
        $months[] = $m;
        $dl[] = $byMonth[$m][0] ?? 0;
        $ul[] = $byMonth[$m][1] ?? 0;
    }

    $q = $db->sql_query_prepared('
        SELECT DATE(FROM_UNIXTIME(completedat)) AS d, COUNT(*) AS c
        FROM snatched
        WHERE userid = ? AND completedat >= ?
        GROUP BY d', [$uid, TIMENOW - 30 * 86400]);
    $byDay = [];
    while ($q && ($r = $db->fetch_array($q))) {
        $byDay[(string)$r['d']] = (int)$r['c'];
    }
    $days = $dayData = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', TIMENOW - $i * 86400);
        $days[]    = $d;
        $dayData[] = $byDay[$d] ?? 0;
    }

    return ['months' => $months, 'dl' => $dl, 'ul' => $ul, 'days' => $days, 'day' => $dayData];
}

/** Модальное окно бана (обычная POST-форма, вне основной формы) */
function renderQuickBanModal(bool $is_banned): string
{
    global $userdata, $db, $lang;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $current = null;
    if ($is_banned) {
        $q = $db->sql_query_prepared('SELECT bantime, reason FROM banned WHERE uid = ? LIMIT 1', [$uid]);
        $current = ($q ? $db->fetch_array($q) : null) ?: null;
    }

    $keys = [
        '1-0-0' => 'opt_ban_1d', '2-0-0' => 'opt_ban_2d', '3-0-0' => 'opt_ban_3d', '4-0-0' => 'opt_ban_4d',
        '5-0-0' => 'opt_ban_5d', '6-0-0' => 'opt_ban_6d', '7-0-0' => 'opt_ban_1w', '14-0-0' => 'opt_ban_2w',
        '21-0-0' => 'opt_ban_3w', '0-1-0' => 'opt_ban_1m', '0-2-0' => 'opt_ban_2m', '0-3-0' => 'opt_ban_3m',
        '0-4-0' => 'opt_ban_4m', '0-5-0' => 'opt_ban_5m', '0-6-0' => 'opt_ban_6m', '0-0-1' => 'opt_ban_1y',
        '0-0-2' => 'opt_ban_2y', '---' => 'opt_ban_perm',
    ];
    $options = '';
    foreach (fetch_ban_times() as $val => $label) {
        $val = (string)$val;
        $sel = ($current && (string)$current['bantime'] === $val) || (!$current && $val === '---');
        $options .= '<option value="' . htmlspecialchars($val) . '"' . ($sel ? ' selected' : '') . '>'
                  . htmlspecialchars((string)(isset($keys[$val]) ? $L[$keys[$val]] : $label)) . '</option>';
    }

    $username = htmlspecialchars_uni((string)$userdata['username']);
    $title    = $is_banned ? ags_fmt($L['ban_title_edit'], $username) : ags_fmt($L['ban_title_new'], $username);

    return '
<div class="modal fade eu-modal" id="quickBanModal" tabindex="-1" aria-labelledby="quickBanTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="edituser.php">
      <input type="hidden" name="action" value="updateuser">
      <input type="hidden" name="userid" value="' . $uid . '">
      <input type="hidden" name="my_post_key" value="' . generate_post_check() . '">
      <input type="hidden" name="quick_ban" value="1">
      <div class="modal-header ' . ($is_banned ? 'bg-warning text-dark' : 'bg-danger text-white') . '">
        <h5 class="modal-title" id="quickBanTitle"><i class="fa-solid ' . ($is_banned ? 'fa-pen-to-square' : 'fa-ban') . ' me-2"></i>' . $title . '</h5>
        <button type="button" class="btn-close' . ($is_banned ? '' : ' btn-close-white') . '" data-bs-dismiss="modal" aria-label="' . htmlspecialchars((string)$L['cancel']) . '"></button>
      </div>
      <div class="modal-body">
        ' . eu_field($L['ban_duration'], '<select name="ban_duration" class="form-select">' . $options . '</select>', '', 'fa-hourglass-half') . '
        ' . eu_field($L['ban_reason'],
              '<textarea name="ban_reason" class="form-control" rows="3" maxlength="500" required placeholder="' . htmlspecialchars((string)$L['ph_ban_reason']) . '">'
              . htmlspecialchars_uni((string)($current['reason'] ?? '')) . '</textarea>', '', 'fa-comment', true) . '
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="ban_send_pm" value="1" id="banSendPM"' . ($is_banned ? '' : ' checked') . '>
          <label class="form-check-label" for="banSendPM">' . $L['ban_send_pm'] . '</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>' . $L['cancel'] . '</button>
        <button type="submit" class="btn ' . ($is_banned ? 'btn-warning' : 'btn-danger') . ' st-pill"><i class="fa-solid ' . ($is_banned ? 'fa-pen-to-square' : 'fa-ban') . ' me-1"></i>'
          . ($is_banned ? $L['ban_btn_update'] : $L['ban_btn_ban']) . '</button>
      </div>
    </form>
  </div>
</div>';
}

/** Модальное окно ЛС (BBCode-кнопки и превью — /scripts/edit_delete_comment.js) */
function renderSendPMModal(): string
{
    global $userdata, $lang;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $bb = '';
    foreach ([
        ['[b]', '[/b]', '<b>B</b>'], ['[i]', '[/i]', '<i>I</i>'], ['[u]', '[/u]', '<u>U</u>'], ['[s]', '[/s]', '<s>S</s>'],
        ['[left]', '[/left]', $L['bb_left']], ['[center]', '[/center]', $L['bb_center']], ['[right]', '[/right]', $L['bb_right']],
        ['[color=red]', '[/color]', $L['bb_red']], ['[size=18]', '[/size]', $L['bb_size']],
        ['[url]', '[/url]', 'URL'], ['[img]', '[/img]', 'IMG'], ['[video]', '[/video]', $L['bb_video']], ['[youtube]', '[/youtube]', 'YouTube'],
        ['[quote]', '[/quote]', $L['bb_quote']], ['[code]', '[/code]', $L['bb_code']],
        ["[list]\n[*]", "\n[/list]", $L['bb_list']], ["[list=1]\n[*]", "\n[/list]", $L['bb_olist']],
    ] as [$open, $close, $label]) {
        $bb .= '<button class="btn btn-sm btn-outline-secondary" type="button" data-bb-open="' . htmlspecialchars($open) . '" data-bb-close="' . htmlspecialchars($close) . '">' . $label . '</button>';
    }

    return '
<div class="modal fade eu-modal" id="sendPMModal" tabindex="-1" aria-labelledby="sendPMTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content" method="post" action="edituser.php">
      <input type="hidden" name="action" value="updateuser">
      <input type="hidden" name="userid" value="' . $uid . '">
      <input type="hidden" name="my_post_key" value="' . generate_post_check() . '">
      <input type="hidden" name="send_pm" value="1">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="sendPMTitle"><i class="fa-solid fa-paper-plane me-2"></i>' . ags_fmt($L['pm_title'], htmlspecialchars_uni((string)$userdata['username'])) . '</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="' . htmlspecialchars((string)$L['cancel']) . '"></button>
      </div>
      <div class="modal-body bb-editor">
        ' . eu_field($L['pm_subject'], '<input type="text" name="pm_subject" class="form-control" maxlength="120" required placeholder="' . htmlspecialchars((string)$L['ph_pm_subject']) . '">', '', 'fa-heading', true) . '
        <div class="eu-bbbar">' . $bb . '</div>
        <textarea name="pm_message" class="form-control mb-3" rows="6" required placeholder="' . htmlspecialchars((string)$L['ph_pm_message']) . '"></textarea>
        <div class="eu-lbl"><i class="fa-solid fa-eye"></i>' . $L['pm_preview'] . '</div>
        <div data-bb-preview class="eu-sig eu-sig--preview"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal">' . $L['cancel'] . '</button>
        <button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-paper-plane me-1"></i>' . $L['btn_send_pm'] . '</button>
      </div>
    </form>
  </div>
</div>';
}

/** Журнал аудита — таймлайн из modcomment */
function renderAuditTab(): string
{
    global $userdata, $lang;
    $L = $lang->edituser;

    $events = [];
    foreach (array_filter(array_map('trim', explode("\n", (string)$userdata['modcomment']))) as $line) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2}(?:\s+\d{2}:\d{2})?)\s+-\s+(.+?)\s+(?:by|-)\s+(\S.*)$/u', $line, $m)) {
            $events[] = ['date' => $m[1], 'action' => trim($m[2]), 'by' => trim($m[3])];
        } else {
            $events[] = ['date' => '', 'action' => $line, 'by' => ''];
        }
    }

    $classify = function (string $action) use ($L): array {
        $a = mb_strtolower($action);
        return match (true) {
            (bool)preg_match('/unlock|unbann|lift|warning removed|removed|reset|cleared/u', $a) => ['success',  'fa-unlock',              $L['audit_cat_resolved']],
            (bool)preg_match('/warn|ban|restrict|demot/u', $a)                                => ['danger',   'fa-triangle-exclamation', $L['audit_cat_ban']],
            (bool)preg_match('/promot|upgrad/u', $a)                                          => ['success',  'fa-arrow-trend-up',       $L['audit_cat_promotion']],
            (bool)preg_match('/usergroup|group/u', $a)                                        => ['primary',  'fa-users',                $L['audit_cat_group']],
            (bool)preg_match('/avatar/u', $a)                                                 => ['info',     'fa-image',                $L['audit_cat_avatar']],
            (bool)preg_match('/password|passkey/u', $a)                                       => ['warning',  'fa-key',                  $L['audit_cat_password']],
            (bool)preg_match('/session|login|lockout/u', $a)                                  => ['warning',  'fa-desktop',              $L['audit_cat_session']],
            (bool)preg_match('/pm sent|message/u', $a)                                        => ['info',     'fa-envelope',             $L['audit_cat_pm']],
            (bool)preg_match('/can(upload|download|comment)|enabled|moderateposts/u', $a)     => ['muted',    'fa-toggle-off',           $L['audit_cat_permission']],
            (bool)preg_match('/email|username|usertitle|signature/u', $a)                     => ['primary',  'fa-pen-to-square',        $L['audit_cat_profile']],
            default                                                                           => ['muted',    'fa-circle',               $L['audit_cat_change']],
        };
    };

    if (!$events) {
        $body = eu_empty('fa-inbox', $L['audit_empty']);
    } else {
        $body = '<ol class="eu-timeline">';
        foreach ($events as $ev) {
            [$soft, $icon, $label] = $classify($ev['action']);
            $body .= '<li class="eu-timeline__item" data-cat="' . $soft . '">'
                   . '<span class="st-ico st-soft-' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
                   . '<div class="eu-timeline__body min-w-0">'
                   .   '<div class="eu-timeline__top">'
                   .     eu_badge($soft, '', $label)
                   .     '<span class="eu-timeline__act">' . htmlspecialchars($ev['action'], ENT_QUOTES, 'UTF-8') . '</span>'
                   .     ($ev['date'] !== '' ? '<span class="eu-hint ms-auto text-nowrap"><i class="fa-solid fa-clock me-1"></i>' . htmlspecialchars($ev['date']) . '</span>' : '')
                   .   '</div>'
                   .   ($ev['by'] !== '' ? '<div class="eu-hint"><i class="fa-solid fa-user me-1"></i>' . ags_fmt($L['audit_by'], '<strong>' . htmlspecialchars($ev['by'], ENT_QUOTES, 'UTF-8') . '</strong>') . '</div>' : '')
                   . '</div></li>';
        }
        $body .= '</ol>';
    }

    $extra = '<select id="auditFilter" class="form-select form-select-sm eu-select-auto" aria-label="' . htmlspecialchars((string)$L['tab_audit']) . '">'
           . '<option value="all">' . $L['opt_audit_all'] . '</option>'
           . '<option value="danger">' . $L['audit_cat_ban'] . '</option>'
           . '<option value="success">' . $L['audit_cat_resolved'] . '</option>'
           . '<option value="primary">' . $L['opt_audit_profile_group'] . '</option>'
           . '<option value="warning">' . $L['opt_audit_password_session'] . '</option>'
           . '<option value="info">' . $L['opt_audit_avatar_pm'] . '</option>'
           . '<option value="muted">' . $L['tab_permissions'] . '</option>'
           . '</select>';
    if ($events && eu_can_edit_modcomment()) {
        $extra .= '<button type="button" class="btn btn-sm btn-outline-danger st-pill" data-eu-action="clear_modcomment" data-eu-danger="1"'
                . ' data-eu-confirm="' . htmlspecialchars((string)$L['js_audit_clear_confirm']) . '">'
                . '<i class="fa-solid fa-trash-can me-1"></i>' . $L['audit_clear'] . '</button>';
    }

    return eu_card('fa-clipboard-list', 'primary',
        $L['tab_audit'] . ' <span class="eu-hint">' . ags_fmt($L['audit_count'], count($events)) . '</span>',
        $body, $extra);
}

/** Торрент-активность: пиры, история скачиваний, раздачи */
function renderTorrentActivityTab(): string
{
    global $userdata, $db, $BASEURL, $lang;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $tlink = function (int $tid, ?string $name, string $icon, string $sub = '') use ($BASEURL, $L): string {
        $label = $name !== null ? htmlspecialchars_uni($name) : '<em>' . $L['deleted_torrent'] . '</em>';
        return '<div class="eu-td-name"><i class="fa-solid ' . $icon . ' me-1 text-body-secondary"></i>'
             . ($name !== null && $tid > 0 ? '<a href="' . $BASEURL . '/details.php?id=' . $tid . '" title="' . htmlspecialchars_uni($name) . '">' . $label . '</a>' : $label)
             . ($sub !== '' ? '<div class="eu-hint text-truncate"><i class="fa-solid fa-microchip me-1"></i>' . $sub . '</div>' : '')
             . '</div>';
    };

    // ── 1. Пиры
    $peerRows = '';
    $peerCount = 0;
    $q = $db->sql_query_prepared('
        SELECT p.uploaded, p.downloaded, p.to_go, p.seeder, p.last_action, p.agent, p.connectable,
               t.name, t.size, t.id AS tid
        FROM peers p
        LEFT JOIN torrents t ON t.id = p.torrent
        WHERE p.userid = ?
        ORDER BY p.last_action DESC
        LIMIT 50', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $peerCount++;
        $seeder = $r['seeder'] === 'yes';
        $size   = (float)($r['size'] ?? 0);
        $pct    = $size > 0 ? (int)max(0, min(100, round((1 - (float)$r['to_go'] / $size) * 100))) : ($seeder ? 100 : 0);
        $peerRows .= '<tr>'
            . '<td>' . $tlink((int)$r['tid'], $r['name'], 'fa-magnet', htmlspecialchars((string)$r['agent'], ENT_QUOTES, 'UTF-8')) . '</td>'
            . '<td class="text-center">' . ($seeder ? eu_badge('success', 'fa-arrow-up', $L['ta_seeding']) : eu_badge('warning', 'fa-arrow-down', $L['ta_leeching'])) . '</td>'
            . '<td class="eu-td-progress"><progress class="eu-progress eu-progress--' . ($pct >= 100 ? 'success' : ($pct >= 50 ? 'info' : 'warning')) . '" value="' . $pct . '" max="100"></progress><div class="eu-hint text-center">' . $pct . '%</div></td>'
            . '<td class="text-end text-nowrap text-success">' . mksize($r['uploaded']) . '</td>'
            . '<td class="text-end text-nowrap text-danger">' . mksize($r['downloaded']) . '</td>'
            . '<td class="text-end text-nowrap">' . mksize($r['to_go']) . '</td>'
            . '<td class="text-end text-nowrap eu-hint">' . eu_dt((int)$r['last_action']) . '</td>'
            . '<td class="text-center"><i class="fa-solid fa-plug ' . ($r['connectable'] === 'yes' ? 'text-success' : 'text-danger')
            .   '" title="' . htmlspecialchars((string)($r['connectable'] === 'yes' ? $L['ta_connectable'] : $L['ta_not_connectable'])) . '"></i></td>'
            . '</tr>';
    }
    $peers = $peerCount
        ? eu_table('<th>' . $L['th_torrent'] . '</th><th class="text-center">' . $L['th_status'] . '</th><th class="text-center">' . $L['th_progress'] . '</th>'
                 . '<th class="text-end">' . $L['stat_uploaded'] . '</th><th class="text-end">' . $L['stat_downloaded'] . '</th>'
                 . '<th class="text-end">' . $L['th_remaining'] . '</th><th class="text-end">' . $L['th_last_seen'] . '</th><th class="text-center">' . $L['th_conn'] . '</th>', $peerRows)
        : eu_empty('fa-satellite-dish', $L['ta_no_conn_text'], $L['ta_no_conn_title']);

    // ── 2. История скачиваний
    $snRows = '';
    $snCount = 0;
    $totUl = $totDl = 0.0;
    $q = $db->sql_query_prepared('
        SELECT s.uploaded, s.downloaded, s.seeder, s.last_action, s.startdat, s.completedat,
               s.seedtime, s.finished, s.agent, t.name, t.id AS tid
        FROM snatched s
        LEFT JOIN torrents t ON t.id = s.torrentid
        WHERE s.userid = ?
        ORDER BY s.last_action DESC
        LIMIT 100', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $snCount++;
        $ul = (float)$r['uploaded'];
        $dl = (float)$r['downloaded'];
        $totUl += $ul;
        $totDl += $dl;
        $ratio = $dl > 0 ? ts_nf($ul / $dl, 2) : ($ul > 0 ? '∞' : '—');
        $snRows .= '<tr>'
            . '<td>' . $tlink((int)$r['tid'], $r['name'], 'fa-download', htmlspecialchars((string)$r['agent'], ENT_QUOTES, 'UTF-8')) . '</td>'
            . '<td class="text-nowrap">' . ($r['finished'] === 'yes' ? eu_badge('success', 'fa-circle-check', $L['ta_complete']) : eu_badge('muted', 'fa-hourglass-half', $L['act_incomplete']))
            .   ($r['seeder'] === 'yes' ? ' ' . eu_badge('primary', 'fa-arrow-up', $L['ta_seeding']) : '') . '</td>'
            . '<td class="text-end text-nowrap text-success">' . mksize($ul) . '</td>'
            . '<td class="text-end text-nowrap text-danger">' . mksize($dl) . '</td>'
            . '<td class="text-center">' . eu_badge('info', '', $ratio) . '</td>'
            . '<td class="text-end text-nowrap eu-hint">' . ((int)$r['seedtime'] > 0 ? mkprettytime((int)$r['seedtime']) : '—') . '</td>'
            . '<td class="text-end text-nowrap eu-hint">' . eu_dt((int)$r['startdat']) . '</td>'
            . '<td class="text-end text-nowrap eu-hint">' . eu_dt((int)$r['completedat']) . '</td>'
            . '<td class="text-end text-nowrap eu-hint">' . eu_dt((int)$r['last_action']) . '</td>'
            . '</tr>';
    }
    if ($snCount) {
        $snatched = '<div class="eu-chips p-3 pb-0">'
            . '<span class="eu-chip"><i class="fa-solid fa-list"></i>' . ags_fmt($L['ta_sum_snatches'], $snCount) . '</span>'
            . '<span class="eu-chip"><i class="fa-solid fa-arrow-up"></i>' . ags_fmt($L['ta_sum_uploaded'], mksize($totUl)) . '</span>'
            . '<span class="eu-chip"><i class="fa-solid fa-arrow-down"></i>' . ags_fmt($L['ta_sum_downloaded'], mksize($totDl)) . '</span>'
            . '<span class="eu-chip"><i class="fa-solid fa-scale-balanced"></i>' . ags_fmt($L['ta_sum_ratio'], $totDl > 0 ? ts_nf($totUl / $totDl, 2) : '∞') . '</span>'
            . '</div>'
            . eu_table('<th>' . $L['th_torrent'] . '</th><th>' . $L['th_status'] . '</th><th class="text-end">' . $L['stat_uploaded'] . '</th>'
                     . '<th class="text-end">' . $L['stat_downloaded'] . '</th><th class="text-center">' . $L['stat_ratio'] . '</th>'
                     . '<th class="text-end">' . $L['act_seed_time'] . '</th><th class="text-end">' . $L['th_started'] . '</th>'
                     . '<th class="text-end">' . $L['act_completed'] . '</th><th class="text-end">' . $L['info_last_active'] . '</th>', $snRows);
    } else {
        $snatched = eu_empty('fa-clock-rotate-left', $L['ta_no_snatch_text'], $L['ta_no_snatch_title']);
    }

    // ── 3. Раздачи
    $upRows = '';
    $upCount = 0;
    $q = $db->sql_query_prepared('
        SELECT id, name, size, added, seeders, leechers, times_completed, visible, banned, free, isnuked
        FROM torrents WHERE owner = ? ORDER BY added DESC LIMIT 100', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $upCount++;
        $badges = ($r['visible'] === 'yes' ? eu_badge('success', 'fa-eye', $L['ta_visible']) : eu_badge('muted', 'fa-eye-slash', $L['ta_hidden']))
                . ($r['isnuked'] === 'yes' ? ' ' . eu_badge('danger', 'fa-skull', $L['ta_nuked']) : '')
                . ($r['free'] === 'yes' ? ' ' . eu_badge('info', 'fa-gift', $L['ta_free']) : '')
                . ($r['banned'] === 'yes' ? ' ' . eu_badge('danger', 'fa-ban', $L['ta_banned']) : '');
        $upRows .= '<tr>'
            . '<td>' . $tlink((int)$r['id'], (string)$r['name'], 'fa-upload') . '</td>'
            . '<td class="text-end text-nowrap">' . mksize($r['size']) . '</td>'
            . '<td>' . $badges . '</td>'
            . '<td class="text-center text-nowrap"><span class="text-success me-2"><i class="fa-solid fa-arrow-up"></i> ' . (int)$r['seeders'] . '</span>'
            .   '<span class="text-danger"><i class="fa-solid fa-arrow-down"></i> ' . (int)$r['leechers'] . '</span></td>'
            . '<td class="text-center">' . (int)$r['times_completed'] . '</td>'
            . '<td class="text-end text-nowrap eu-hint">' . eu_dt((int)$r['added']) . '</td>'
            . '</tr>';
    }
    $uploads = $upCount
        ? eu_table('<th>' . $L['th_torrent'] . '</th><th class="text-end">' . $L['th_size'] . '</th><th>' . $L['th_status'] . '</th>'
                 . '<th class="text-center">' . $L['th_peers'] . '</th><th class="text-center">' . $L['th_grabs'] . '</th><th class="text-end">' . $L['th_added'] . '</th>', $upRows)
        : eu_empty('fa-upload', $L['ta_no_uploads_text'], $L['ta_no_uploads_title']);

    return '<section class="st-card eu-card"><div class="st-card__head">'
         . eu_subtabs('torrentActivityTabs', [
               'ta-peers'    => ['fa-satellite-dish',    $L['ta_tab_active'],   (string)$peerCount],
               'ta-snatched' => ['fa-clock-rotate-left', $L['ta_tab_snatched'], (string)$snCount],
               'ta-uploads'  => ['fa-upload',            $L['btn_uploads'],     (string)$upCount],
           ])
         . '</div>'
         . eu_subpanes(['ta-peers' => $peers, 'ta-snatched' => $snatched, 'ta-uploads' => $uploads])
         . '</section>';
}

/** Жалобы: на пользователя и от пользователя */
function renderReportsTab(): string
{
    global $userdata, $db, $BASEURL, $lang;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $sql = "
        SELECT r.*, u_by.username AS reporter_username, u_rep.username AS reported_username,
               u_dealt.username AS dealt_username, t.name AS torrent_name
        FROM reports r
        LEFT JOIN users    u_by    ON u_by.id    = r.addedby
        LEFT JOIN users    u_rep   ON u_rep.id   = r.reported_user_id
        LEFT JOIN users    u_dealt ON u_dealt.id = r.dealtby
        LEFT JOIN torrents t       ON t.id       = r.reported_id AND r.type = 'torrent'
        WHERE r.%s = ?
        ORDER BY r.added DESC
        LIMIT 200";

    $ulink = fn(int $id, ?string $name): string =>
        $id > 0 && $name !== null && $name !== ''
            ? '<a href="' . $BASEURL . '/' . get_profile_link($id) . '">' . htmlspecialchars_uni($name) . '</a>'
            : '—';

    $build = function (string $col, bool $incoming) use ($db, $sql, $uid, $L, $BASEURL, $ulink): array {
        $q = $db->sql_query_prepared(sprintf($sql, $col), [$uid]);
        $rows = '';
        $total = $open = 0;
        while ($q && ($r = $db->fetch_array($q))) {
            $total++;
            $dealt = (int)$r['dealtwith'] === 1;
            if (!$dealt) {
                $open++;
            }
            [$soft, $icon, $tlabel] = match ((string)$r['type']) {
                'torrent'   => ['primary', 'fa-magnet',   $L['rep_type_torrent']],
                'user'      => ['warning', 'fa-user',     $L['rep_type_user']],
                'comment'   => ['info',    'fa-comment',  $L['rep_type_comment']],
                'forumpost' => ['muted',   'fa-comments', $L['rep_type_forumpost']],
                default     => ['muted',   'fa-flag',     htmlspecialchars(ucfirst((string)$r['type']))],
            };

            $subject = '—';
            if ($r['type'] === 'torrent' && (int)$r['reported_id'] > 0) {
                $tid = (int)$r['reported_id'];
                $subject = $r['torrent_name'] !== null
                    ? '<a href="' . $BASEURL . '/details.php?id=' . $tid . '">' . htmlspecialchars_uni((string)$r['torrent_name']) . '</a>'
                    : ags_fmt($L['rep_torrent_n'], $tid);
            } elseif (in_array($r['type'], ['user', 'comment'], true)) {
                $subject = $ulink((int)$r['reported_user_id'], $r['reported_username']);
            } elseif ($r['type'] === 'forumpost' && (int)($r['thread_id'] ?? 0) > 0) {
                $tid2 = (int)$r['thread_id'];
                $subject = '<a href="' . $BASEURL . '/' . get_thread_link($tid2) . '">' . ags_fmt($L['rep_thread_n'], $tid2) . '</a>';
            }

            $who  = $incoming ? $ulink((int)$r['addedby'], $r['reporter_username']) : $ulink((int)$r['reported_user_id'], $r['reported_username']);
            $rule = trim((string)($r['rule_violation'] ?? ''));
            $desc = trim((string)($r['description'] ?? ''));

            $rows .= '<tr' . ($dealt ? '' : ' class="st-row--bad"') . '>'
                . '<td class="text-nowrap">' . eu_badge($soft, $icon, $tlabel)
                .   ($rule !== '' ? ' <span class="st-badge st-soft-muted" title="' . htmlspecialchars((string)$L['rep_rule_violation']) . '">' . htmlspecialchars($rule) . '</span>' : '') . '</td>'
                . '<td>' . $subject . '</td>'
                . '<td><div class="fw-semibold">' . htmlspecialchars((string)$r['reason'], ENT_QUOTES, 'UTF-8') . '</div>'
                .   ($desc !== '' ? '<div class="eu-hint">' . htmlspecialchars(mb_strimwidth($desc, 0, 120, '…'), ENT_QUOTES, 'UTF-8') . '</div>' : '') . '</td>'
                . '<td>' . $who . '</td>'
                . '<td>' . ($dealt ? eu_badge('success', 'fa-check', $L['rep_resolved']) : eu_badge('danger', 'fa-clock', $L['rep_open'])) . '</td>'
                . '<td class="eu-hint">' . ($dealt && !empty($r['dealt_username']) ? htmlspecialchars_uni((string)$r['dealt_username']) : '—') . '</td>'
                . '<td class="text-nowrap eu-hint">' . eu_dt((int)$r['added']) . '</td>'
                . '<td class="text-nowrap eu-hint">' . eu_dt((int)($r['updated_at'] ?? 0)) . '</td>'
                . '</tr>';
        }

        $html = $rows !== ''
            ? eu_table('<th>' . $L['rep_th_type'] . '</th><th>' . $L['rep_th_subject'] . '</th><th>' . $L['rep_th_reason'] . '</th>'
                     . '<th>' . ($incoming ? $L['rep_th_reported_by'] : $L['rep_th_reported_user']) . '</th><th>' . $L['th_status'] . '</th>'
                     . '<th>' . $L['rep_th_dealt_by'] . '</th><th>' . $L['rep_th_reported'] . '</th><th>' . $L['rep_th_updated'] . '</th>', $rows)
            : eu_empty($incoming ? 'fa-shield-halved' : 'fa-flag', $incoming ? $L['rep_none_against'] : $L['rep_none_by']);

        return [$html, $total, $open];
    };

    [$inHtml, $inTotal, $inOpen]    = $build('reported_user_id', true);
    [$outHtml, $outTotal, $outOpen] = $build('addedby', false);

    $count = fn(int $t, int $o): string => $t . ($o > 0 ? ' · ' . ags_fmt($L['rep_open_count'], $o) : '');

    return '<section class="st-card eu-card"><div class="st-card__head">'
         . eu_subtabs('reportsSubTabs', [
               'rep-incoming' => ['fa-shield-halved', $L['rep_tab_against'], $count($inTotal, $inOpen)],
               'rep-outgoing' => ['fa-flag',          $L['rep_tab_by'],      $count($outTotal, $outOpen)],
           ])
         . '</div>'
         . eu_subpanes(['rep-incoming' => $inHtml, 'rep-outgoing' => $outHtml])
         . '</section>';
}

/** Дерево приглашённых (до 3 уровней, по запросу на уровень) */
function buildInviteTree(int $rootUid): string
{
    global $db, $BASEURL, $lang;
    $L = $lang->edituser;

    $MAX_DEPTH  = 3;
    $LIMIT_EACH = 50;

    $level      = [$rootUid];
    $childrenOf = [];
    $seen       = [$rootUid => true];

    for ($depth = 0; $depth < $MAX_DEPTH && $level; $depth++) {
        $ph = implode(',', array_fill(0, count($level), '?'));
        $q  = $db->sql_query_prepared("
            SELECT u.id, u.username, u.usergroup, u.enabled, u.warned, u.donor, u.uploaded, u.downloaded, u.added, i.inviter_id
            FROM invites i
            JOIN users u ON u.id = i.invitee_id
            WHERE i.inviter_id IN ({$ph}) AND i.status = 'used'
            ORDER BY i.inviter_id, u.added ASC
            LIMIT ?", [...$level, $LIMIT_EACH * count($level)]);

        $next = [];
        while ($q && ($r = $db->fetch_array($q))) {
            $id = (int)$r['id'];
            if (isset($seen[$id])) {
                continue; // защита от циклов
            }
            $seen[$id] = true;
            $childrenOf[(int)$r['inviter_id']][] = $r;
            $next[] = $id;
        }
        $level = $next;
    }

    $render = function (int $parent, int $depth) use (&$render, &$childrenOf, $BASEURL, $L): string {
        if (empty($childrenOf[$parent])) {
            return '';
        }
        $html = '<ul class="eu-tree' . ($depth === 0 ? ' eu-tree--root' : '') . '">';
        foreach ($childrenOf[$parent] as $r) {
            $id   = (int)$r['id'];
            $kids = $render($id, $depth + 1);
            $html .= '<li class="eu-tree__node">'
                   . '<div class="eu-tree__row">'
                   .   ($kids !== ''
                        ? '<button type="button" class="eu-tree__toggle" data-eu-tree aria-expanded="true"><i class="fa-solid fa-chevron-down"></i></button>'
                        : '<span class="eu-tree__toggle eu-tree__toggle--leaf"><i class="fa-solid fa-circle"></i></span>')
                   .   '<a href="' . $BASEURL . '/' . get_profile_link($id) . '" class="fw-semibold">'
                   .     format_name(htmlspecialchars_uni((string)$r['username']), (int)$r['usergroup']) . '</a>'
                   .   ($r['enabled'] === 'no' ? eu_badge('danger', '', $L['badge_banned']) : '')
                   .   ($r['warned'] === 'yes' ? eu_badge('warning', '', $L['badge_warned']) : '')
                   .   ($r['donor'] === 'yes' ? eu_badge('info', '', $L['badge_donor']) : '')
                   .   '<span class="eu-hint ms-auto text-nowrap">' . ags_fmt($L['inv_joined'], eu_dt((int)$r['added']))
                   .     ' · <span class="text-success"><i class="fa-solid fa-arrow-up"></i> ' . mksize($r['uploaded']) . '</span>'
                   .     ' <span class="text-danger"><i class="fa-solid fa-arrow-down"></i> ' . mksize($r['downloaded']) . '</span></span>'
                   . '</div>'
                   . $kids
                   . '</li>';
        }
        return $html . '</ul>';
    };

    return $render($rootUid, 0);
}

/** Приглашения */
function renderInvitesTab(): string
{
    global $userdata, $db, $BASEURL, $lang;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $q = $db->sql_query_prepared("
        SELECT COUNT(*) AS total, SUM(status = 'used') AS used, SUM(status = 'pending') AS pending,
               SUM(status IN ('expired', 'revoked')) AS dead
        FROM invites WHERE inviter_id = ?", [$uid]);
    $s = ($q ? $db->fetch_array($q) : null) ?: [];

    $kpis = '<div class="eu-kpis mb-3">'
          . eu_kpi('fa-paper-plane',    ts_nf((int)($s['total'] ?? 0)),   $L['inv_total'])
          . eu_kpi('fa-user-check',     ts_nf((int)($s['used'] ?? 0)),    $L['inv_used'])
          . eu_kpi('fa-clock',          ts_nf((int)($s['pending'] ?? 0)), $L['inv_pending'])
          . eu_kpi('fa-hourglass-end',  ts_nf((int)($s['dead'] ?? 0)),    $L['inv_expired_revoked'])
          . '</div>';

    // Кто пригласил этого пользователя
    $invitedBy = (int)($userdata['invited_by'] ?? 0);
    $inviter   = '<div class="eu-note"><i class="fa-solid fa-user-plus"></i><div>' . $L['inv_no_inviter'] . '</div></div>';
    if ($invitedBy > 0) {
        $iq = $db->sql_query_prepared('SELECT id, username, usergroup, enabled, donor FROM users WHERE id = ? LIMIT 1', [$invitedBy]);
        if ($iq && ($ir = $db->fetch_array($iq))) {
            $inviter = '<div class="eu-note"><i class="fa-solid fa-user-plus"></i><div><strong>' . $L['inv_invited_by'] . '</strong> '
                     . '<a href="' . $BASEURL . '/' . get_profile_link((int)$ir['id']) . '">' . format_name(htmlspecialchars_uni((string)$ir['username']), (int)$ir['usergroup']) . '</a> '
                     . ($ir['enabled'] === 'no' ? eu_badge('danger', '', $L['badge_banned']) : '')
                     . ($ir['donor'] === 'yes' ? eu_badge('info', '', $L['badge_donor']) : '')
                     . '</div></div>';
        }
    }

    // Отправленные приглашения
    $rows = '';
    $sent = 0;
    $q = $db->sql_query_prepared('
        SELECT i.*, u.username AS invitee_name, u.usergroup AS invitee_group, u.enabled AS invitee_enabled,
               u.added AS invitee_joined, u.uploaded AS invitee_ul, u.downloaded AS invitee_dl,
               u.warned AS invitee_warned, u.donor AS invitee_donor
        FROM invites i
        LEFT JOIN users u ON u.id = i.invitee_id
        WHERE i.inviter_id = ?
        ORDER BY i.created_at DESC
        LIMIT 200', [$uid]);
    while ($q && ($r = $db->fetch_array($q))) {
        $sent++;
        [$soft, $icon, $label] = match ((string)$r['status']) {
            'used'    => ['success', 'fa-check',         $L['inv_st_used']],
            'pending' => ['warning', 'fa-clock',         $L['inv_st_pending']],
            'expired' => ['muted',   'fa-hourglass-end', $L['inv_st_expired']],
            'revoked' => ['danger',  'fa-ban',           $L['inv_st_revoked']],
            default   => ['muted',   'fa-question',      htmlspecialchars((string)$r['status'])],
        };

        if ((int)$r['invitee_id'] > 0 && $r['invitee_name'] !== null) {
            $invitee = '<a href="' . $BASEURL . '/' . get_profile_link((int)$r['invitee_id']) . '" class="fw-semibold">'
                     . format_name(htmlspecialchars_uni((string)$r['invitee_name']), (int)$r['invitee_group']) . '</a> '
                     . ($r['invitee_warned'] === 'yes' ? eu_badge('warning', '', $L['badge_warned']) : '')
                     . ($r['invitee_donor'] === 'yes' ? eu_badge('info', '', $L['badge_donor']) : '')
                     . ($r['invitee_enabled'] === 'no' ? eu_badge('danger', '', $L['badge_banned']) : '')
                     . '<div class="eu-hint">' . ags_fmt($L['inv_joined'], eu_dt((int)$r['invitee_joined']))
                     . ' · <span class="text-success"><i class="fa-solid fa-arrow-up"></i> ' . mksize($r['invitee_ul'] ?? 0) . '</span>'
                     . ' <span class="text-danger"><i class="fa-solid fa-arrow-down"></i> ' . mksize($r['invitee_dl'] ?? 0) . '</span></div>';
        } elseif (!empty($r['email'])) {
            $invitee = '<span class="text-body-secondary"><i class="fa-solid fa-envelope me-1"></i>' . htmlspecialchars((string)$r['email'], ENT_QUOTES, 'UTF-8') . '</span>'
                     . '<div class="eu-hint">' . $L['inv_not_registered'] . '</div>';
        } else {
            $invitee = '—';
        }

        $ips = '';
        foreach (['ip_created' => 'fa-paper-plane', 'ip_used' => 'fa-right-to-bracket'] as $col => $ico) {
            if (!empty($r[$col])) {
                $ips .= '<div class="eu-hint text-nowrap"><i class="fa-solid ' . $ico . ' me-1"></i>' . htmlspecialchars((string)$r[$col], ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        $rows .= '<tr>'
            . '<td>' . $invitee . '</td>'
            . '<td>' . eu_badge($soft, $icon, $label) . '</td>'
            . '<td class="text-nowrap eu-hint">' . eu_dt((int)$r['created_at']) . '</td>'
            . '<td class="text-nowrap eu-hint">' . eu_dt((int)($r['used_at'] ?? 0)) . '</td>'
            . '<td class="text-nowrap eu-hint">' . eu_dt((int)($r['expires_at'] ?? 0)) . '</td>'
            . '<td>' . ($ips !== '' ? $ips : '—') . '</td>'
            . '<td class="eu-hint">' . (!empty($r['note']) ? htmlspecialchars((string)$r['note'], ENT_QUOTES, 'UTF-8') : '—') . '</td>'
            . '</tr>';
    }

    $sentHtml = $rows !== ''
        ? eu_table('<th>' . $L['inv_th_invitee'] . '</th><th>' . $L['th_status'] . '</th><th>' . $L['inv_th_sent'] . '</th>'
                 . '<th>' . $L['inv_th_used_at'] . '</th><th>' . $L['inv_th_expires'] . '</th><th>' . $L['inv_th_ip'] . '</th><th>' . $L['inv_th_note'] . '</th>', $rows)
        : eu_empty('fa-ticket', $L['inv_none_sent']);

    $tree     = buildInviteTree($uid);
    $treeHtml = $tree !== '' ? '<div class="p-3">' . $tree . '</div>' : eu_empty('fa-sitemap', $L['inv_no_tree']);

    return $kpis
         . '<div class="d-flex flex-wrap align-items-center gap-2 mb-3">'
         .   $inviter
         .   '<span class="eu-chip"><i class="fa-solid fa-ticket"></i>' . $L['inv_balance'] . ' <b>' . (int)$userdata['invites'] . '</b></span>'
         . '</div>'
         . '<section class="st-card eu-card"><div class="st-card__head">'
         . eu_subtabs('inviteSubTabs', [
               'inv-sent' => ['fa-paper-plane', $L['inv_tab_sent'], (string)$sent],
               'inv-tree' => ['fa-sitemap',     $L['inv_tab_tree'], ''],
           ])
         . '</div>'
         . eu_subpanes(['inv-sent' => $sentHtml, 'inv-tree' => $treeHtml])
         . '</section>';
}

/* =============================================================================
   Страница
   ============================================================================= */

function handleEditUser(): void
{
    global $userdata, $BASEURL, $usergroups, $db, $lang, $dateformat;
    $L = $lang->edituser;

    get_user_data();
    permission_check();

    $uid      = (int)$userdata['id'];
    $username = htmlspecialchars_uni((string)$userdata['username']);

    stdhead(ags_fmt($L['page_title_edit'], $username, $uid));

    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/edituser.css?ver=3">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';

    flash_message();

    $q = $db->sql_query_prepared('SELECT uid FROM banned WHERE uid = ? LIMIT 1', [$uid]);
    $is_banned = $q && $db->num_rows($q) > 0;

    $profile = $BASEURL . '/' . get_profile_link($uid);
    $postKey = generate_post_check();

    // ── Плитки: из get_user_data() (один запрос)
    $ratio      = eu_ratio_value($userdata);
    $ratioClass = $ratio === null ? '' : ($ratio >= 1 ? 'text-success' : ($ratio >= 0.5 ? 'text-warning' : 'text-danger'));

    echo '<div class="container-md st-page eu-page">';

    // ── Шапка
    echo '<section class="st-card st-head mb-3">'
       .   '<span class="st-head__icon st-soft-primary"><i class="fa-solid fa-user-pen"></i></span>'
       .   '<div class="min-w-0 flex-grow-1">'
       .     '<div class="d-flex flex-wrap align-items-center gap-2">'
       .       '<h1 class="st-title">' . format_name($username, (int)$userdata['usergroup']) . '</h1>'
       .       get_user_status_badges($userdata)
       .     '</div>'
       .     '<div class="st-head__meta">'
       .       '<span><i class="fa-solid fa-id-card"></i>' . ags_fmt($L['hdr_uid'], $uid) . '</span>'
       .       '<span><i class="fa-solid fa-user-group"></i>' . htmlspecialchars_uni(get_user_class_name((string)$userdata['usergroup'])) . '</span>'
       .       '<span class="text-truncate"><i class="fa-solid fa-envelope"></i>' . htmlspecialchars_uni((string)$userdata['email']) . '</span>'
       .       '<span><i class="fa-solid fa-calendar-plus"></i>' . $L['stat_joined'] . ': ' . eu_dt((int)$userdata['added']) . '</span>'
       .       '<span><i class="fa-solid fa-clock"></i>' . $L['info_last_active'] . ': ' . eu_dt((int)$userdata['lastactive']) . '</span>'
       .     '</div>'
       .   '</div>'
       .   '<div class="eu-head__actions">'
       .     '<a href="' . $profile . '" class="btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-arrow-left me-1"></i>' . $L['btn_back_profile'] . '</a>'
       .     '<button type="button" class="btn btn-sm btn-outline-primary st-pill" data-bs-toggle="modal" data-bs-target="#sendPMModal"><i class="fa-solid fa-paper-plane me-1"></i>' . $L['btn_send_pm'] . '</button>'
       .     '<button type="button" class="btn btn-sm btn-danger st-pill" data-bs-toggle="modal" data-bs-target="#quickBanModal"><i class="fa-solid fa-ban me-1"></i>'
       .       ($is_banned ? $L['ban_btn_update'] : $L['btn_quick_ban']) . '</button>'
       .   '</div>'
       . '</section>';

    // ── KPI
    echo '<div class="eu-kpis mb-3">'
       . eu_kpi('fa-scale-balanced', '<span class="' . $ratioClass . '">' . get_ratio($userdata) . '</span>', $L['stat_ratio'])
       . eu_kpi('fa-arrow-up',       mksize($userdata['uploaded']),   $L['stat_uploaded'])
       . eu_kpi('fa-arrow-down',     mksize($userdata['downloaded']), $L['stat_downloaded'])
       . eu_kpi('fa-coins',          ts_nf((float)$userdata['seedbonus']), $L['stat_bonus'])
       . '</div>';

    // ── Инструменты (ссылки + опасные действия через POST)
    $lastip = '';
    if (!empty($userdata['lastip'])) {
        $s = my_inet_ntop($db->unescape_binary($userdata['lastip']));
        $lastip = $s !== false ? $s : '';
    }
    echo '<section class="st-card st-toolbar mb-3 eu-tools">'
       .   '<div class="d-flex flex-wrap gap-2">'
       .     '<a class="btn btn-sm btn-outline-secondary st-pill" href="' . $BASEURL . '/search.php?action=finduserthreads&amp;uid=' . $uid . '"><i class="fa-solid fa-file-lines me-1"></i>' . $L['btn_user_threads'] . '</a>'
       .     '<a class="btn btn-sm btn-outline-secondary st-pill" href="' . $BASEURL . '/search.php?action=finduser&amp;uid=' . $uid . '"><i class="fa-solid fa-comments me-1"></i>' . $L['btn_user_posts'] . '</a>'
       .     '<a class="btn btn-sm btn-outline-secondary st-pill" href="' . $BASEURL . '/userhistory.php?id=' . $uid . '"><i class="fa-solid fa-comment-dots me-1"></i>' . $L['btn_comments'] . '</a>'
       .     '<a class="btn btn-sm btn-outline-secondary st-pill" href="' . $BASEURL . '/browse.php?search_type=t_uploader&amp;include_dead_torrents=yes&amp;keywords=' . rawurlencode((string)$userdata['username']) . '"><i class="fa-solid fa-upload me-1"></i>' . $L['btn_uploads'] . '</a>'
       .     ($lastip !== '' ? '<a class="btn btn-sm btn-outline-secondary st-pill" href="' . $BASEURL . '/admin/index.php?act=ipsearch&amp;ip=' . rawurlencode($lastip) . '"><i class="fa-solid fa-network-wired me-1"></i>' . $L['btn_ip_info'] . '</a>' : '')
       .   '</div>'
       .   '<div class="d-flex flex-wrap gap-2">'
       .     '<form method="post" action="edituser.php?action=resetpasskey&amp;userid=' . $uid . '" class="d-inline">'
       .       '<input type="hidden" name="userid" value="' . $uid . '"><input type="hidden" name="sure" value="1"><input type="hidden" name="my_post_key" value="' . $postKey . '">'
       .       '<button type="submit" class="btn btn-sm btn-outline-warning st-pill" data-ags-confirm="' . htmlspecialchars((string)$L['pk_question']) . '"><i class="fa-solid fa-key me-1"></i>' . $L['btn_reset_passkey'] . '</button>'
       .     '</form>'
       .     (eu_can_delete()
            ? '<form method="post" action="edituser.php?action=deleteaccount&amp;userid=' . $uid . '" class="d-inline">'
            .   '<input type="hidden" name="userid" value="' . $uid . '"><input type="hidden" name="sure" value="1"><input type="hidden" name="my_post_key" value="' . $postKey . '">'
            .   '<button type="submit" class="btn btn-sm btn-outline-danger st-pill" data-ags-danger="1" data-ags-confirm="' . htmlspecialchars($L['del_question'] . ' ' . $L['del_warning']) . '"><i class="fa-solid fa-trash-can me-1"></i>' . $L['btn_delete_account'] . '</button>'
            . '</form>'
            : '')
       .   '</div>'
       . '</section>';

    // ── Вкладки
    $tabs = [
        'basic'       => ['fa-user',                  $L['tab_basic'],       true],
        'permissions' => ['fa-shield-halved',         $L['tab_permissions'], true],
        'warning'     => ['fa-triangle-exclamation',  $L['tab_warnings'],    true],
        'stats'       => ['fa-chart-column',          $L['tab_stats'],       true],
        'account'     => ['fa-gear',                  $L['tab_account'],     true],
        'advanced'    => ['fa-gears',                 $L['tab_advanced'],    true],
        'activity'    => ['fa-clock-rotate-left',     $L['tab_activity'],    false],
        'security'    => ['fa-lock',                  $L['tab_security'],    false],
        'hnr'         => ['fa-person-running',        $L['tab_hnr'],         false],
        'forum'       => ['fa-comments',              $L['tab_forum'],       false],
        'audit'       => ['fa-clipboard-list',        $L['tab_audit'],       false],
        'torrents'    => ['fa-right-left',            $L['tab_torrents'],    false],
        'reports'     => ['fa-flag',                  $L['tab_reports'],     false],
        'invites'     => ['fa-ticket',                $L['tab_invites'],     false],
    ];

    echo '<nav class="st-card eu-tabs-card mb-3"><ul class="nav eu-tabs" id="userTabs" role="tablist">';
    $first = true;
    foreach ($tabs as $id => [$icon, $label, $editable]) {
        echo '<li class="nav-item" role="presentation">'
           . '<button class="eu-tab' . ($first ? ' active' : '') . '" id="' . $id . '-tab" data-bs-toggle="tab" data-bs-target="#' . $id . '"'
           .   ' type="button" role="tab" aria-controls="' . $id . '" aria-selected="' . ($first ? 'true' : 'false') . '"'
           .   ' data-eu-editable="' . ($editable ? '1' : '0') . '" title="' . htmlspecialchars((string)$label) . '">'
           .   '<i class="fa-solid ' . $icon . '"></i><span>' . $label . '</span>'
           . '</button></li>';
        $first = false;
    }
    echo '</ul></nav>';

    // ── Основная форма (вкладки-просмотры тоже внутри — вложенных форм нет)
    echo '<form method="post" action="edituser.php" name="updateuser" id="userEditForm">'
       . '<input type="hidden" name="userid" value="' . $uid . '">'
       . '<input type="hidden" name="action" value="updateuser">'
       . '<input type="hidden" name="my_post_key" value="' . $postKey . '">'
       . '<div class="tab-content" id="userTabsContent">';

    $render = [
        'basic' => 'renderBasicInfoTab', 'permissions' => 'renderPermissionsTab', 'warning' => 'renderWarningsTab',
        'stats' => 'renderStatisticsTab', 'account' => 'renderAccountSettingsTab', 'advanced' => 'renderAdvancedTab',
        'activity' => 'renderActivityTab', 'security' => 'renderSecurityTab', 'hnr' => 'renderHitRunTab',
        'forum' => 'renderForumTab', 'audit' => 'renderAuditTab', 'torrents' => 'renderTorrentActivityTab',
        'reports' => 'renderReportsTab', 'invites' => 'renderInvitesTab',
    ];
    $first = true;
    foreach ($render as $id => $fn) {
        echo '<div class="tab-pane fade' . ($first ? ' show active' : '') . '" id="' . $id . '" role="tabpanel" aria-labelledby="' . $id . '-tab">' . $fn() . '</div>';
        $first = false;
    }

    echo '</div>'
       . '<div class="st-submitbar" id="euSaveBar">'
       .   '<span class="eu-hint d-none d-sm-inline"><i class="fa-solid fa-circle-info me-1"></i>' . ags_fmt($L['page_title_edit'], $username, $uid) . '</span>'
       .   '<div class="d-flex flex-wrap gap-2">'
       .     '<button type="reset" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-rotate-left me-1"></i>' . $L['btn_reset'] . '</button>'
       .     '<button type="submit" class="btn btn-success st-pill" name="updateuser" value="1"><i class="fa-solid fa-floppy-disk me-1"></i>' . $L['btn_save'] . '</button>'
       .   '</div>'
       . '</div>'
       . '</form>';

    // ── Форма кнопок-действий (сессии, блокировка, аватар, аудит) — заполняет edituser.js
    echo '<form method="post" action="edituser.php" id="euActionForm" class="d-none">'
       . '<input type="hidden" name="action" value="updateuser">'
       . '<input type="hidden" name="userid" value="' . $uid . '">'
       . '<input type="hidden" name="my_post_key" value="' . $postKey . '">'
       . '<input type="hidden" name="" value="1" id="euActionField">'
       . '</form>';

    echo renderSendPMModal();
    echo renderQuickBanModal($is_banned);

    echo '</div>'; // .eu-page

    // ── JS: язык, диалоги, аватар, BBCode-превью, графики, страница
    $ags_js_lang = [];
    foreach ($L as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $ags_js_lang[substr((string)$k, 3)] = (string)$v;
        }
    }
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    echo ags_js_lang('global', ['dlg_ok' => 'js_dlg_ok', 'dlg_yes' => 'js_dlg_yes', 'dlg_cancel' => 'js_dlg_cancel'])
       . avatar_js_lang()
       . '<script>window.AGS_LANG = Object.assign(window.AGS_LANG || {}, ' . json_encode($ags_js_lang, $jsonFlags) . ');'
       . 'window.EU_CHART = ' . json_encode(buildChartData($uid), $jsonFlags) . ';</script>'
       . '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>'
       . '<script src="' . $BASEURL . '/scripts/ags-dialog.js?ver=1"></script>'
       . '<script src="' . $BASEURL . '/scripts/toast.js"></script>'
       . '<script src="' . $BASEURL . '/scripts/avatar_upload.js?ver=5"></script>'
       . '<script src="' . $BASEURL . '/scripts/edit_delete_comment.js"></script>'
       . '<script src="' . $BASEURL . '/admin/scripts/chart.umd.min.js"></script>'
       . '<script src="' . $BASEURL . '/admin/scripts/edituser.js?ver=24"></script>';

    stdfoot();
}

/* =============================================================================
   Сохранение
   ============================================================================= */

function handleUpdateUser(): void
{
    global $userdata, $db, $CURUSER, $lang, $update_array, $modcomment, $mybb;

    if (!verify_post_check((string)$mybb->get_input('my_post_key'), true)) {
        print_no_permission(true);
    }

    get_user_data();
    permission_check();

    $uid      = (int)$userdata['id'];
    $origNote = (string)$userdata['modcomment'];

    // Ручная правка истории (только cansettingspanel): берём текст из формы как основу
    $modcomment = rtrim($origNote);
    if (eu_can_edit_modcomment() && isset($_POST['modcomment']) && is_string($_POST['modcomment'])) {
        $posted = rtrim(str_replace("\r\n", "\n", $_POST['modcomment']));
        if ($posted !== rtrim(str_replace("\r\n", "\n", $origNote))) {
            $modcomment = $posted;
            write_log("Moderation notes of user {$userdata['username']} ({$uid}) edited manually by {$CURUSER['username']}");
        }
    }

    $update_array = [];
    handle_avatar_update();
    processAvatarUpdate();

    $updateData = processUserUpdateData();
    if (!empty($update_array)) {
        $updateData = array_merge($updateData, $update_array);
    }
    unset($updateData['modcomment']); // пишется одним запросом в конце

    if ($updateData) {
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($updateData)));
        $params = array_values($updateData);
        $params[] = $uid;
        $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);

        $fields = array_diff(array_keys($updateData), ['password', 'salt', 'loginkey']);
        if (array_intersect(['password'], array_keys($updateData))) {
            $fields[] = 'password';
        }
        write_log("User {$userdata['username']} ({$uid}) has been edited by {$CURUSER['username']}: " . implode(', ', array_unique($fields)));

        if (array_key_exists('seedbonus', $updateData)) {
            require_once INC_PATH . '/functions_bonuslog.php';
            $old = (float)$userdata['seedbonus'];
            $new = (float)$updateData['seedbonus'];
            bonus_log($uid, $new - $old, 'staff', 'Balance set by staff: ' . ts_nf($old) . ' → ' . ts_nf($new), null, (int)$CURUSER['id']);
        }
    }

    updateUserPermissions();

    $modcomment = rtrim((string)$modcomment);
    if ($modcomment !== rtrim($origNote)) {
        $db->sql_query_prepared('UPDATE users SET modcomment = ? WHERE id = ?', [$modcomment, $uid]);
    }

    // Общее «сохранено», если для этого изменения ещё нет своего сообщения об успехе
    $hasSuccess = false;
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $hasSuccess = $hasSuccess || $f['type'] === 'success';
    }
    if (!$hasSuccess && ($updateData || $modcomment !== rtrim($origNote) || empty($_SESSION['flash']))) {
        flash_message(ags_fmt((string)$lang->edituser['flash_user_updated'], (string)$userdata['username']), 'success');
    }
    admin_redirect("edituser.php?action=edituser&userid={$uid}");
}

/** Кнопка «Удалить аватар» (euActionForm) */
function processAvatarUpdate(): void
{
    global $userdata, $update_array, $mybb, $CURUSER, $lang;

    if (!$mybb->get_input('remove_avatar')) {
        return;
    }
    $update_array['avatar']           = '';
    $update_array['avatardimensions'] = '';
    $update_array['avatartype']       = '';
    remove_avatars((int)$userdata['id']);
    modcomment('Avatar removed');
    write_log("Avatar of user {$userdata['username']} ({$userdata['id']}) removed by {$CURUSER['username']}");
    flash_message((string)$lang->edituser['flash_avatar_removed'], 'success');
}

/** Данные из формы и кнопок-действий → поля для UPDATE users */
function processUserUpdateData(): array
{
    global $userdata, $CURUSER, $db, $modcomment, $cache, $lang, $dateformat;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    $updateData = [];
    $post = fn(string $k): ?string => isset($_POST[$k]) && is_string($_POST[$k]) ? $_POST[$k] : null;

    // ── Текстовые поля
    $title = $post('usertitle');
    if ($title !== null) {
        $title = mb_substr(trim($title), 0, 255);
        if ($title !== (string)$userdata['usertitle']) {
            $updateData['usertitle'] = $title;
            modcomment("Usertitle changed from '{$userdata['usertitle']}' to '{$title}'");
        }
    }
    $sig = $post('signature');
    if ($sig !== null) {
        $sig = str_replace("\r\n", "\n", $sig);
        if ($sig !== str_replace("\r\n", "\n", (string)$userdata['signature'])) {
            $updateData['signature'] = $sig;
            modcomment('Signature changed');
        }
    }

    // ── yes/no
    foreach (['donor', 'enabled'] as $f) {
        $v = $post($f);
        if (($v === 'yes' || $v === 'no') && $v !== (string)$userdata[$f]) {
            $updateData[$f] = $v;
            modcomment(ucfirst($f) . " changed from '{$userdata[$f]}' to '{$v}'");
        }
    }

    // ── 0/1
    foreach (['moderateposts', 'allownotices', 'hideemail', 'receivepms', 'receivefrombuddy',
              'pmnotice', 'pmnotify', 'buddyrequestspm', 'buddyrequestsauto', 'invisible'] as $f) {
        $v = $post($f);
        if ($v === null) {
            continue;
        }
        $v = $v === '1' ? 1 : 0;
        if ($v !== (int)$userdata[$f]) {
            $updateData[$f] = $v;
            modcomment(ucfirst($f) . " changed from '" . (int)$userdata[$f] . "' to '{$v}'");
        }
    }
    $sm = $post('subscriptionmethod');
    if ($sm !== null) {
        $sm = max(0, min(3, (int)$sm));
        if ($sm !== (int)$userdata['subscriptionmethod']) {
            $updateData['subscriptionmethod'] = $sm;
            modcomment("Subscriptionmethod changed from '" . (int)$userdata['subscriptionmethod'] . "' to '{$sm}'");
        }
    }

    // ── Трафик/бонусы/инвайты — только с правами (проверка и на сервере, не только disabled в форме)
    if (eu_can_edit_stats()) {
        foreach (['seedbonus' => 'float', 'invites' => 'int', 'uploaded' => 'int', 'downloaded' => 'int'] as $f => $type) {
            $v = $post($f);
            if ($v === null || trim($v) === '' || !is_numeric($v)) {
                continue;
            }
            $new = $type === 'float' ? max(0.0, round((float)$v, 2)) : max(0, (int)$v);
            $old = $type === 'float' ? round((float)$userdata[$f], 2) : (int)$userdata[$f];
            if ($new !== $old) {
                $updateData[$f] = $new;
                modcomment(ucfirst($f) . " changed from '{$old}' to '{$new}'");
            }
        }
    }

    // ── Email: формат + уникальность
    $email = $post('email');
    if ($email !== null && ($email = trim($email)) !== (string)$userdata['email']) {
        if (!validate_email_format($email)) {
            flash_message($L['err_email_invalid'] ?? 'Invalid email address — not saved.', 'error');
        } elseif (eu_email_taken($email, $uid)) {
            flash_message($L['err_email_taken'] ?? 'This email is already used by another account — not saved.', 'error');
        } else {
            $updateData['email'] = $email;
            modcomment("Email changed from '{$userdata['email']}' to '{$email}'");
        }
    }

    // ── Имя: валидация как в UserDataHandler + уникальность
    $name = $post('username');
    if ($name !== null && ($name = trim_blank_chrs($name)) !== (string)$userdata['username']) {
        $bad = $name === '' || mb_strlen($name) > 255 || preg_match('/[<>&\\\\;,"\']/', $name) || is_banned_username($name, true);
        if ($bad) {
            flash_message($L['err_username_invalid'] ?? 'Invalid username — not saved.', 'error');
        } elseif (eu_username_taken($name, $uid)) {
            flash_message($L['err_username_taken'] ?? 'This username is already taken — not saved.', 'error');
        } else {
            $updateData['username'] = $name;
            modcomment("Username changed from '{$userdata['username']}' to '{$name}'");
        }
    }

    // ── Группа: staff-группы назначает только супер-админ
    $gid = $post('usergroup');
    if ($gid !== null && (int)$gid > 0 && (int)$gid !== (int)$userdata['usergroup']) {
        $newGid = (int)$gid;
        $gq = $db->sql_query_prepared('SELECT gid, cansettingspanel, canstaffpanel, issupermod FROM usergroups WHERE gid = ? LIMIT 1', [$newGid]);
        $g  = $gq ? $db->fetch_array($gq) : null;
        if ($g) {
            $elevated = $g['cansettingspanel'] == '1' || $g['canstaffpanel'] == '1' || $g['issupermod'] == '1';
            if ($elevated && !is_super_admin((int)$CURUSER['id'])) {
                write_log("SECURITY: {$CURUSER['username']} attempted to assign elevated group {$newGid} to user {$uid} without super admin rights", 'security', 2);
            } else {
                $updateData['usergroup'] = $newGid;
                modcomment("Usergroup changed from '{$userdata['usergroup']}' to '{$newGid}'");
                // Ушёл из VIP вручную — иначе vipexpire.php по старой записи вернёт его в old_gid
                if ((int)$userdata['usergroup'] === UC_VIP) {
                    $db->sql_query_prepared('DELETE FROM auto_vip WHERE userid = ?', [$uid]);
                }
            }
        }
    }

    // ── Пароль
    $pw = $post('password');
    if ($pw !== null && $pw !== '') {
        $user = ['loginkey' => generate_loginkey()];
        $updateData = array_merge($updateData, create_password($pw, $user));
        modcomment('Password updated');
    }

    // ── Отключение аккаунта + бан IP (поле banip)
    if (($updateData['enabled'] ?? '') === 'no' && $post('banip') === 'yes') {
        $ipRaw = $userdata['lastip'] ?? '';
        $ip    = $ipRaw !== '' && $ipRaw !== null ? my_inet_ntop($db->unescape_binary($ipRaw)) : false;
        if ($ip !== false && $ip !== '') {
            $dq = $db->sql_query_prepared('SELECT fid FROM banfilters WHERE filter = ? AND type = 1 LIMIT 1', [$ip]);
            if (!($dq && $db->num_rows($dq) > 0)) {
                $db->sql_query_prepared('INSERT INTO banfilters (`filter`,`type`,`dateline`,`lastuse`) VALUES (?,?,?,?)', [$ip, 1, TIMENOW, 0]);
                $cache->update_bannedips();
            }
            modcomment("IP {$ip} banned");
            write_log("IP {$ip} of user {$userdata['username']} ({$uid}) banned by {$CURUSER['username']}");
        }
    }

    // ── Предупреждение
    if ($post('warned') === 'no' && $userdata['warned'] === 'yes') {
        $updateData['warned']      = 'no';
        $updateData['warneduntil'] = 0;
        modcomment('Warning removed');
    } elseif ($userdata['warned'] !== 'yes' && (int)($post('warnlength') ?? 0) > 0) {
        $weeks  = (int)$post('warnlength');
        $weeks  = ($weeks === 255 || ($weeks >= 1 && $weeks <= 12)) ? $weeks : 1;
        $reason = mb_substr(trim((string)$post('warnpm')), 0, 255);
        $until  = $weeks === 255 ? 0 : TIMENOW + $weeks * 604800;

        $updateData['warned']      = 'yes';
        $updateData['warneduntil'] = $until;
        $updateData['timeswarned'] = (int)$userdata['timeswarned'] + 1;
        $updateData['lastwarned']  = TIMENOW;
        $updateData['warnedby']    = (int)$CURUSER['id'];
        modcomment('User warned ' . ($weeks === 255 ? 'permanently' : "for {$weeks} weeks") . '. Reason: ' . ($reason !== '' ? $reason : 'No reason given.'));
        write_log("User {$userdata['username']} ({$uid}) warned by {$CURUSER['username']}");

        require_once INC_PATH . '/functions_pm.php';
        $reasonPm = $reason !== '' ? str_replace(['[', ']'], ['(', ')'], $reason) : '—';
        send_pm([
            'subject'       => ['msg_warn_pm_subject'],
            'message'       => $until > 0 ? ['msg_warn_pm_body', my_datee((string)$dateformat, $until), $reasonPm] : ['msg_warn_pm_body_perm', $reasonPm],
            'touid'         => $uid,
            'language'      => $userdata['language'] ?? null,
            'language_file' => 'edituser',
            'sender'        => ['uid' => -1],
        ], -1, true);
    }
    if ($post('reset_timeswarned') === 'yes' && (int)$userdata['timeswarned'] > 0) {
        $updateData['timeswarned'] = 0;
        modcomment('Warning count reset');
    }

    // ── Заметка модератора
    $note = $post('addcomment');
    if ($note !== null && ($note = trim(str_replace(["\r", "\n"], ' ', $note))) !== '') {
        modcomment('Note: ' . mb_substr($note, 0, 500));
    }

    // ── Бан (модальное окно)
    if ($post('quick_ban') === '1') {
        $times    = fetch_ban_times();
        $duration = (string)$post('ban_duration');
        if (!isset($times[$duration])) {
            $duration = '---';
        }
        $reason = mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)$post('ban_reason'))), 0, 500);
        if ($reason === '') {
            $reason = 'No reason given.';
        }
        $bannedGid = eu_banned_gid();

        if ($bannedGid > 0) {
            $lifted = $duration === '---' ? 0 : ban_date2timestamp($duration);
            $label  = $duration === '---' ? 'Permanently' : $times[$duration];

            $eq = $db->sql_query_prepared('SELECT uid FROM banned WHERE uid = ? LIMIT 1', [$uid]);
            if ($eq && $db->num_rows($eq) > 0) {
                $db->sql_query_prepared('UPDATE banned SET bantime = ?, lifted = ?, reason = ?, admin = ?, dateline = ? WHERE uid = ?',
                    [$duration, (string)$lifted, $reason, (int)$CURUSER['id'], TIMENOW, $uid]);
                modcomment("Ban updated ({$label}). Reason: {$reason}");
                write_log("Ban of user {$userdata['username']} ({$uid}) updated by {$CURUSER['username']} ({$label})");
                flash_message($L['msg_ban_updated'] ?? 'Ban updated.', 'success');
            } else {
                $ug = $cache->read('usergroups') ?: [];
                $oldgroup = (int)$userdata['usergroup'];
                if ($oldgroup <= 0 || !empty($ug[$oldgroup]['isbannedgroup'])) {
                    $oldgroup = defined('UC_USER') ? (int)UC_USER : 2;
                }
                $db->sql_query_prepared(
                    'INSERT INTO banned (`uid`,`gid`,`oldgroup`,`admin`,`dateline`,`bantime`,`lifted`,`reason`) VALUES (?,?,?,?,?,?,?,?)',
                    [$uid, $bannedGid, $oldgroup, (int)$CURUSER['id'], TIMENOW, $duration, (string)$lifted, $reason]);
                $updateData['usergroup'] = $bannedGid;
                modcomment("Banned ({$label}). Reason: {$reason}");
                write_log("User {$userdata['username']} ({$uid}) banned by {$CURUSER['username']} ({$label})");
                flash_message($L['msg_user_banned'] ?? 'User banned.', 'success');
            }

            if ($post('ban_send_pm') === '1') {
                require_once INC_PATH . '/functions_pm.php';
                $reasonPm = str_replace(['[', ']'], ['(', ')'], $reason);
                send_pm([
                    'subject'       => ['msg_ban_pm_subject'],
                    'message'       => $lifted > 0 ? ['msg_ban_pm_body', my_datee((string)$dateformat, $lifted), $reasonPm] : ['msg_ban_pm_body_perm', $reasonPm],
                    'touid'         => $uid,
                    'language'      => $userdata['language'] ?? null,
                    'language_file' => 'edituser',
                    'sender'        => ['uid' => -1],
                ], -1, true);
            }
        }
    }

    // ── Сессии
    if ($post('kill_sessions') === '1') {
        $db->sql_query_prepared('DELETE FROM sessions WHERE uid = ?', [$uid]);
        modcomment('All sessions killed');
        write_log("All sessions for user {$userdata['username']} ({$uid}) killed by {$CURUSER['username']}");
        flash_message($L['msg_sessions_killed'] ?? 'All sessions have been terminated.', 'success');
    }

    // ── Блокировка входа
    if ($post('reset_loginattempts') === '1') {
        $updateData['loginattempts']      = 0;
        $updateData['loginlockoutexpiry'] = 0;
        modcomment('Login lockout reset');
        write_log("Login lockout of user {$userdata['username']} ({$uid}) reset by {$CURUSER['username']}");
        flash_message($L['msg_lockout_reset'] ?? 'Login lockout has been reset.', 'success');
    }

    // ── Очистка истории модерации
    if ($post('clear_modcomment') === '1' && eu_can_edit_modcomment()) {
        $modcomment = '';
        modcomment('Audit history cleared');
        write_log("Moderation history of user {$userdata['username']} ({$uid}) cleared by {$CURUSER['username']}");
        flash_message($L['msg_audit_cleared'] ?? 'Audit history cleared.', 'success');
    }

    // ── ЛС от сотрудника
    $pmSubject = trim((string)$post('pm_subject'));
    $pmMessage = trim((string)$post('pm_message'));
    if ($post('send_pm') === '1' && $pmSubject !== '' && $pmMessage !== '') {
        require_once INC_PATH . '/functions_pm.php';
        if (send_pm(['touid' => $uid, 'subject' => mb_substr($pmSubject, 0, 120), 'message' => $pmMessage], (int)$CURUSER['id'], true)) {
            modcomment('PM sent: ' . mb_substr($pmSubject, 0, 60));
            write_log("PM sent to user {$userdata['username']} ({$uid}) by {$CURUSER['username']}");
            flash_message($L['msg_pm_sent'] ?? 'Private message sent.', 'success');
        }
    }

    return $updateData;
}

/** cancomment / canupload / candownload */
function updateUserPermissions(): void
{
    global $db, $userdata, $CURUSER;

    if (!isset($_POST['cancomment']) && !isset($_POST['canupload']) && !isset($_POST['candownload'])) {
        return;
    }

    $changed = [];
    foreach (['cancomment', 'canupload', 'candownload'] as $f) {
        if (!isset($_POST[$f])) {
            continue;
        }
        $new = ($_POST[$f] === 'yes') ? 1 : 0;
        $old = (int)($userdata[$f] ?? 1);
        if ($new !== $old) {
            $changed[$f] = $new;
            modcomment(ucfirst($f) . " changed from '{$old}' to '{$new}'");
        }
    }
    if (!$changed) {
        return;
    }

    $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($changed)));
    $params = array_values($changed);
    $params[] = (int)$userdata['id'];
    $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);
    write_log("Permissions of user {$userdata['username']} ({$userdata['id']}) changed by {$CURUSER['username']}: " . implode(', ', array_keys($changed)));
}

/* =============================================================================
   Удаление аккаунта / сброс пасскея (страницы подтверждения — запасной вариант без JS)
   ============================================================================= */

function eu_confirm_page(string $title, string $icon, string $soft, string $question, string $note, string $action, string $btnClass, string $btnLabel): void
{
    global $userdata, $lang, $BASEURL;
    $L   = $lang->edituser;
    $uid = (int)$userdata['id'];

    stdhead($title);
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/edituser.css?ver=3">'
       . '<div class="container-md st-page eu-page eu-page--narrow">'
       . '<section class="st-card st-head mb-3">'
       .   '<span class="st-head__icon st-soft-' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
       .   '<div class="min-w-0"><h1 class="st-title">' . $title . '</h1>'
       .   '<div class="st-head__meta"><span><i class="fa-solid fa-user"></i>' . htmlspecialchars_uni((string)$userdata['username']) . '</span>'
       .   '<span><i class="fa-solid fa-id-card"></i>' . ags_fmt($L['hdr_uid'], $uid) . '</span></div></div>'
       . '</section>'
       . '<section class="st-card"><div class="st-card__body">'
       .   '<p class="fw-semibold mb-2">' . $question . '</p>'
       .   '<p class="eu-hint mb-0">' . $note . '</p>'
       . '</div>'
       . '<form method="post" action="edituser.php?action=' . $action . '&amp;userid=' . $uid . '" class="st-toolbar st-toolbar--bottom">'
       .   '<input type="hidden" name="userid" value="' . $uid . '"><input type="hidden" name="sure" value="1">'
       .   '<input type="hidden" name="my_post_key" value="' . generate_post_check() . '">'
       .   '<a href="edituser.php?action=edituser&amp;userid=' . $uid . '" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-xmark me-1"></i>' . $L['cancel'] . '</a>'
       .   '<button type="submit" class="btn ' . $btnClass . ' st-pill"><i class="fa-solid ' . $icon . ' me-1"></i>' . $btnLabel . '</button>'
       . '</form></section>'
       . '</div>';
    stdfoot();
}

function handleDeleteAccount(): void
{
    global $userdata, $CURUSER, $lang;
    $L = $lang->edituser;

    if (!eu_can_delete()) {
        print_no_permission();
    }

    get_user_data();
    permission_check();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ($_POST['sure'] ?? '') !== '1') {
        eu_confirm_page($L['del_title'], 'fa-trash-can', 'danger', $L['del_question'], $L['del_warning'], 'deleteaccount', 'btn-danger', $L['del_btn_yes']);
        return;
    }

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        print_no_permission(true);
    }

    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler('delete');
    if (!$userhandler->delete_user([(int)$userdata['id']])) {
        stderr((string)$L['err_delete_failed'], (string)$L['err_title']);
        return;
    }

    write_log('Account: ' . $userdata['username'] . ' (' . $userdata['id'] . ') has been deleted by ' . $CURUSER['username']);

    stdok(
        message:  ags_fmt($L['del_done_msg'], '<strong>' . htmlspecialchars_uni((string)$userdata['username']) . '</strong>'),
        title:    $L['del_done_title'],
        subtitle: $L['del_done_subtitle']
    );
}

function handleResetPasskey(): void
{
    global $userdata, $db, $CURUSER, $lang;
    $L = $lang->edituser;

    get_user_data();
    permission_check();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ($_POST['sure'] ?? '') !== '1') {
        eu_confirm_page(ags_fmt($L['pk_page_title'], htmlspecialchars_uni((string)$userdata['username'])), 'fa-key', 'warning',
            $L['pk_question'], $L['pk_note'], 'resetpasskey', 'btn-warning', $L['pk_btn_yes']);
        return;
    }

    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        print_no_permission(true);
    }

    $passkey    = (string)generate_passkey((string)$userdata['username'], (string)$userdata['loginkey']);
    $modcomment = date('Y-m-d H:i') . ' - Passkey reset by ' . $CURUSER['username'] . "\n" . rtrim((string)$userdata['modcomment']);

    $db->sql_query_prepared('UPDATE users SET passkey = ?, modcomment = ? WHERE id = ?', [$passkey, $modcomment, (int)$userdata['id']]);

    require_once INC_PATH . '/functions_pm.php';
    send_pm([
        'subject'       => ['passkeysubject'],
        'message'       => ['passkeymsg', (string)$CURUSER['username']],
        'touid'         => (int)$userdata['id'],
        'language'      => $userdata['language'] ?? null,
        'language_file' => 'edituser',
        'sender'        => ['uid' => -1],
    ], -1, true);

    write_log('Passkey for user: ' . $userdata['username'] . ' (' . $userdata['id'] . ') has been reset by ' . $CURUSER['username']);

    flash_message((string)$L['flash_passkey_reset'], 'success');
    admin_redirect('edituser.php?action=edituser&userid=' . (int)$userdata['id']);
}
