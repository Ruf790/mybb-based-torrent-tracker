<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('IGNORE_CLEAN_VARS', 'sid');
define('THIS_SCRIPT', 'member.php');
define('SCRIPTNAME', 'member.php');
define('ALLOWABLE_PAGE', 'verify_2fa,register,do_register,login,do_login,logout,lostpw,do_lostpw,activate,resendactivation,do_resendactivation,resetpassword,viewnotes');

$nosession['avatar'] = 1;

require_once 'global.php';

$lang->load('member');

define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once INC_PATH . '/functions_forum.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/functions_timezone.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/functions_loginattemptcheck.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_additions.php';

$parser = new postParser();

/* ═══════════════════════════════════════════════════════════════════════
 *  Helpers
 * ═══════════════════════════════════════════════════════════════════════ */

if (!function_exists('hsafe')) {
    function hsafe(mixed $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Substitutes {1}, {2}… (and %1$s, %2$s… which $lang->load() produces) in a lang string.
 */
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

/** Lang string → field label without the trailing colon ("Joined:" → "Joined"). */
function mr_lbl(?string $s): string
{
    return rtrim(trim((string)$s), ":： \t");
}

/** member.lang.php string with an English fallback. */
function mr_l(string $key, string $fallback = ''): string
{
    global $lang;
    $v = $lang->member[$key] ?? null;
    return (is_string($v) && $v !== '') ? $v : $fallback;
}

/** stdhead + page styles + page wrapper. $narrow: '' (full width), 'auth' or 'wide'. */
function mr_open(string $title, string $narrow = ''): void
{
    global $BASEURL;
    stdhead($title);
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=61">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/member.css?ver=1">';
    echo '<div class="container-md st-page mr-page">';
    if ($narrow !== '') {
        echo '<div class="mr-narrow' . ($narrow === 'wide' ? ' mr-narrow--wide' : '') . '">';
    }
}

function mr_close(bool $narrow = false): void
{
    if ($narrow) {
        echo '</div>';
    }
    echo '</div>';
    stdfoot();
}

/** Page header card. $title / $hint / $right are ready HTML (escape before passing). */
function mr_head(string $icon, string $soft, string $title, string $hint = '', string $right = ''): string
{
    return '<header class="st-card st-head mb-3">'
        . '<div class="st-head__icon st-soft-' . $soft . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></div>'
        . '<div class="flex-grow-1 min-w-0">'
        . '<h1 class="st-title">' . $title . '</h1>'
        . ($hint !== '' ? '<div class="st-meta mt-1">' . $hint . '</div>' : '')
        . '</div>'
        . $right
        . '</header>';
}

/** Error box. Messages are lang/datahandler strings (may contain simple markup). */
function mr_errors(array $errors, string $title = ''): string
{
    $errors = array_values(array_filter(array_map('strval', $errors), static fn(string $s): bool => $s !== ''));
    if (!$errors) {
        return '';
    }
    $html = '<div class="mr-error" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div class="min-w-0">';
    if ($title !== '') {
        $html .= '<div class="fw-semibold">' . $title . '</div>';
    }
    if (count($errors) === 1) {
        $html .= '<div>' . $errors[0] . '</div>';
    } else {
        $html .= '<ul class="mb-0 ps-3">';
        foreach ($errors as $err) {
            $html .= '<li>' . $err . '</li>';
        }
        $html .= '</ul>';
    }
    return $html . '</div></div>';
}

/** Text input with an icon in an input-group; .field-wrap is what regvalidator.js appends messages to. */
function mr_input(string $name, string $label, string $icon, string $value = '', string $type = 'text', string $attrs = '', string $hint = ''): string
{
    $id = $name;
    return '<div class="mr-field field-wrap">'
        . '<label class="form-label" for="' . $id . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i>' . $label . '</label>'
        . '<div class="input-group">'
        . '<span class="input-group-text"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></span>'
        . '<input type="' . $type . '" class="form-control" name="' . $name . '" id="' . $id . '" value="' . $value . '"' . ($attrs !== '' ? ' ' . $attrs : '') . '>'
        . '</div>'
        . ($hint !== '' ? '<div class="form-text">' . $hint . '</div>' : '')
        . '</div>';
}

/**
 * Login return URL: only addresses on this site; never member.php or javascript:.
 * Returns '' when the URL is not acceptable (caller falls back to index.php).
 */
function mr_safe_return_url(string $url): string
{
    global $BASEURL;

    $url = trim(str_replace('&amp;', '&', $url));
    if ($url === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $url)) {
        return '';
    }
    if (str_starts_with($url, $BASEURL . '/')) {
        $path = substr($url, strlen($BASEURL));
    } elseif (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $path = $url;
    } else {
        return '';
    }
    if (str_contains(basename((string)parse_url($path, PHP_URL_PATH)), 'member.php')) {
        return '';
    }
    if (preg_match('#(newthread|newreply)\.php#', $path)) {
        $path = str_replace('&processed=1', '', $path);
    }
    return $BASEURL . $path;
}

/** CSRF token from the form; silent check, the caller decides what to show. */
function mr_post_key_ok(): bool
{
    global $mybb;
    return verify_post_check((string)$mybb->get_input('my_post_key'), true);
}

// ── Avatar upload (AJAX from scripts/avatar_upload.js) ─────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'upload_avatar') {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $fail = static function (string $msg, int $code) use ($is_ajax): never {
        if ($is_ajax) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        exit(hsafe($msg));
    };

    $user_uid = (int)($CURUSER['id'] ?? 0);
    if ($user_uid <= 0) {
        $fail(mr_l('err_not_logged_in', 'You are not logged in.'), 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        $fail(mr_l('err_post_key', 'Security token mismatch. Please reload the page and try again.'), 403);
    }

    $uid = (int)($_POST['id'] ?? 0);
    if ($uid <= 0) {
        $fail(mr_l('err_no_profile_uid', 'Profile ID is not specified.'), 400);
    }
    if ($user_uid !== $uid && !is_mod($usergroups)) {
        $fail(mr_l('err_avatar_no_perm', "You don't have permission to change this avatar."), 403);
    }
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $fail(mr_l('err_avatar_no_file', 'No file was uploaded.'), 400);
    }

    // Validation (extension, real MIME via getimagesize(), avatarsize setting), saving and
    // GD re-encoding (polyglot protection) — upload_avatar(), the same function usercp.php uses.
    $avatarResult = upload_avatar($_FILES['avatar'], $uid);
    if (!empty($avatarResult['error'])) {
        $fail($is_ajax ? (string)$avatarResult['error'] : ags_fmt(mr_l('err_avatar_failed', 'Error: {1}'), (string)$avatarResult['error']), 415);
    }

    $width  = (int)$avatarResult['width'];
    $height = (int)$avatarResult['height'];
    $avatar_dimensions = ($width > 0 && $height > 0) ? $width . '|' . $height : '';
    $avatar_url        = (string)$avatarResult['avatar'];

    $db->sql_query_prepared(
        "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = ? WHERE id = ?",
        [$avatar_url, $avatar_dimensions, 'upload', $uid]
    );
    if ($user_uid !== $uid) {
        write_log('Avatar of user ID ' . $uid . ' changed by ' . (string)$CURUSER['username'] . ' from the profile page');
    }

    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'url' => $avatar_url, 'width' => $width, 'height' => $height, 'message' => mr_l('msg_avatar_updated', 'Avatar updated')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: member.php?action=profile&id=' . $uid);
    exit;
}

// ── Ban times ──────────────────────────────────────────────────────────────
function fetch_ban_times(): array
{
    global $plugins, $lang;

    $ban_times = [
        '1-0-0'  => $lang->member['opt_ban_1d'],  '2-0-0'  => $lang->member['opt_ban_2d'],  '3-0-0'  => $lang->member['opt_ban_3d'],
        '4-0-0'  => $lang->member['opt_ban_4d'],  '5-0-0'  => $lang->member['opt_ban_5d'],  '6-0-0'  => $lang->member['opt_ban_6d'],
        '7-0-0'  => $lang->member['opt_ban_1w'],  '14-0-0' => $lang->member['opt_ban_2w'],  '21-0-0' => $lang->member['opt_ban_3w'],
        '0-1-0'  => $lang->member['opt_ban_1m'],  '0-2-0'  => $lang->member['opt_ban_2m'],  '0-3-0'  => $lang->member['opt_ban_3m'],
        '0-4-0'  => $lang->member['opt_ban_4m'],  '0-5-0'  => $lang->member['opt_ban_5m'],  '0-6-0'  => $lang->member['opt_ban_6m'],
        '0-0-1'  => $lang->member['opt_ban_1y'],  '0-0-2'  => $lang->member['opt_ban_2y'],
    ];

    $ban_times = $plugins->run_hooks('functions_fetch_ban_times', $ban_times);
    $ban_times['---'] = $lang->member['permanent'];
    return $ban_times;
}

// ── Age / date helpers ─────────────────────────────────────────────────────
function get_age(string $birthday): ?int
{
    $bday = explode('-', $birthday);
    if (empty($bday[2])) { return null; }

    [$day, $month, $year] = explode('-', my_datee('j-n-Y', TIMENOW, '0', 0));

    $age = (int)$year - (int)$bday[2];
    if (((int)$month === (int)$bday[1] && (int)$day < (int)$bday[0]) || (int)$month < (int)$bday[1]) {
        $age--;
    }
    return $age;
}

function fix_mktime(string $format, string|int $year): string
{
    $format = str_replace('Y', (string)$year, $format);
    $format = str_replace('y', my_substr((string)$year, -2), $format);
    return $format;
}

// ── Torrent lists (profile) ────────────────────────────────────────────────

function mr_cat_icon_class(?string $raw): string
{
    return preg_replace('/[^a-z0-9\-\s]/i', '', trim((string)$raw)) ?: 'fa-solid fa-folder';
}

function mr_imdb_badge(?string $t_link): string
{
    if ($t_link && preg_match('#(\d+(?:\.\d+)?)/10#', $t_link, $m)) {
        return '<span class="st-badge st-soft-warning" title="IMDb"><i class="fa-solid fa-star" aria-hidden="true"></i>' . hsafe($m[1]) . '</span>';
    }
    return '';
}

/** [ratio string, st-soft-* colour] */
function mr_ratio(int $up, int $down): array
{
    if ($down > 0) {
        $r = $up / $down;
        return [number_format($r, 2), $r >= 1.0 ? 'success' : ($r >= 0.5 ? 'warning' : 'danger')];
    }
    return $up > 0 ? ['∞', 'success'] : ['0.00', 'muted'];
}

/**
 * One torrent row. $r: id, name, cat_name, cat_icon, t_image; $badges / $when / $side / $extra — ready HTML.
 */
function mr_tor_item(array $r, string $badges, string $when = '', string $side = '', string $extra = ''): string
{
    global $BASEURL;

    $id    = (int)($r['id'] ?? 0);
    $name  = (string)($r['name'] ?? '');
    $cat   = (string)($r['cat_name'] ?? '');
    $icls  = mr_cat_icon_class($r['cat_icon'] ?? '');
    $link  = $BASEURL . '/' . get_torrent_link($id);
    $img   = trim((string)($r['t_image'] ?? ''));

    $poster = $img !== ''
        ? '<img src="' . hsafe($img) . '" alt="" loading="lazy">'
        : '<i class="' . hsafe($icls) . '" aria-hidden="true"></i>';

    return '<div class="mr-tor">'
        . '<a class="mr-tor__poster" href="' . $link . '" data-fallback-icon="' . hsafe($icls) . '" tabindex="-1" aria-hidden="true">' . $poster . '</a>'
        . '<div class="mr-tor__body">'
        . '<div class="mr-tor__top"><span class="st-badge st-soft-muted"><i class="' . hsafe($icls) . '" aria-hidden="true"></i>' . hsafe($cat) . '</span>' . mr_imdb_badge($r['t_link'] ?? '') . '</div>'
        . '<a class="mr-tor__name" href="' . $link . '">' . htmlspecialchars_uni($name) . '</a>'
        . $extra
        . '<div class="mr-tor__meta">' . $badges . '</div>'
        . ($when !== '' ? '<div class="mr-tor__when">' . $when . '</div>' : '')
        . '</div>'
        . ($side !== '' ? '<div class="mr-tor__side">' . $side . '</div>' : '')
        . '</div>';
}

function mr_empty(string $icon, string $text, string $hint = ''): string
{
    return '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></div>'
        . '<div>' . hsafe($text) . '</div>'
        . ($hint !== '' ? '<div class="small mt-1">' . hsafe($hint) . '</div>' : '')
        . '</div>';
}

function mr_recent_uploads(object $db, int $uid, int $limit = 10): string
{
    global $BASEURL, $dateformat;

    $res = $db->sql_query_prepared("
        SELECT t.id, t.name, t.size, t.added, t.seeders, t.leechers, t.times_completed,
               t.t_image, t.t_link, c.name AS cat_name, c.icon AS cat_icon
        FROM torrents t
        LEFT JOIN categories c ON c.id = t.category
        WHERE t.owner = ?
          AND (t.visible = 'yes' OR t.visible = 1 OR t.visible IS NULL)
          AND (t.banned  = 'no'  OR t.banned  = 0  OR t.banned  IS NULL)
        ORDER BY t.added DESC
        LIMIT ?
    ", [$uid, $limit]);

    if (!$res || $db->num_rows($res) === 0) {
        return mr_empty('fa-cloud-arrow-up', mr_l('empty_uploads', 'No uploads yet.'));
    }

    $html = '<div class="mr-tor-list">';
    while ($r = $db->fetch_array($res)) {
        $id = (int)$r['id'];
        $badges = '<span class="st-badge st-soft-muted"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i>' . hsafe(mksize((int)$r['size'])) . '</span>'
            . '<span class="st-badge st-soft-success"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>' . ts_nf((int)$r['seeders']) . '</span>'
            . '<span class="st-badge st-soft-danger"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>' . ts_nf((int)$r['leechers']) . '</span>'
            . '<span class="st-badge st-soft-info"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>' . ts_nf((int)$r['times_completed']) . '</span>';
        $when = '<i class="fa-solid fa-calendar-days" aria-hidden="true"></i>' . hsafe(my_datee($dateformat, (int)$r['added']));
        $side = '<a href="' . $BASEURL . '/download.php/' . $id . '.torrent" class="btn btn-sm btn-outline-primary st-pill" title="' . hsafe(mr_l('tip_download', 'Download')) . '" aria-label="' . hsafe(mr_l('tip_download', 'Download')) . '"><i class="fa-solid fa-download" aria-hidden="true"></i></a>';
        $html .= mr_tor_item($r, $badges, $when, $side);
    }
    return $html . '</div>';
}

function mr_active_list(object $db, int $uid, bool $seeding, int $limit = 10): string
{
    global $dateformat, $timeformat;

    $res = $db->sql_query_prepared("
        SELECT t.id, t.name, t.size, t.t_image, t.t_link,
               c.name AS cat_name, c.icon AS cat_icon,
               p.uploaded, p.downloaded, p.to_go, p.last_action
        FROM peers p
        JOIN torrents t ON t.id = p.torrent
        LEFT JOIN categories c ON c.id = t.category
        WHERE p.userid = ? AND p.seeder = ?
        ORDER BY p.last_action DESC
        LIMIT ?
    ", [$uid, $seeding ? 'yes' : 'no', $limit]);

    if (!$res || $db->num_rows($res) === 0) {
        return mr_empty($seeding ? 'fa-cloud-arrow-up' : 'fa-cloud-arrow-down', mr_l('empty_active', 'Nothing active right now'));
    }

    $html = '<div class="mr-tor-list">';
    while ($r = $db->fetch_array($res)) {
        $size     = max(1, (int)($r['size'] ?? 1));
        $progress = $seeding ? 100 : min(100, max(0, (int)round((1 - (int)($r['to_go'] ?? 0) / $size) * 100)));
        $fill     = $seeding ? 'seed' : 'leech';
        $last     = (int)($r['last_action'] ?? 0);

        $extra = '<div class="mr-progress" role="progressbar" aria-label="' . hsafe(mr_l('lbl_progress', 'Progress')) . '" aria-valuenow="' . $progress . '" aria-valuemin="0" aria-valuemax="100">'
            . '<div class="mr-progress__fill--' . $fill . '" style="width:' . $progress . '%"></div></div>';
        $badges = '<span class="st-badge st-soft-' . ($seeding ? 'success' : 'info') . '">' . $progress . '%</span>'
            . '<span class="st-badge st-soft-success"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>' . hsafe(mksize((int)($r['uploaded'] ?? 0))) . '</span>'
            . '<span class="st-badge st-soft-danger"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>' . hsafe(mksize((int)($r['downloaded'] ?? 0))) . '</span>';
        $when = '<i class="fa-solid fa-clock" aria-hidden="true"></i>' . hsafe(my_datee($dateformat, $last) . ' ' . my_datee($timeformat, $last));
        $html .= mr_tor_item($r, $badges, $when, '', $extra);
    }
    return $html . '</div>';
}

function mr_completed_list(object $db, int $uid, int $limit = 10): string
{
    global $dateformat, $timeformat;

    $res = $db->sql_query_prepared("
        SELECT s.torrentid AS id, s.uploaded, s.downloaded, s.completedat,
               t.seeders, t.leechers, t.name, t.t_image, t.t_link,
               c.name AS cat_name, c.icon AS cat_icon
        FROM snatched s
        INNER JOIN torrents t ON t.id = s.torrentid
        INNER JOIN categories c ON c.id = t.category
        WHERE s.finished = 'yes' AND s.userid = ?
        ORDER BY s.completedat DESC, s.last_action DESC
        LIMIT ?
    ", [$uid, $limit]);

    if (!$res || $db->num_rows($res) === 0) {
        return mr_empty('fa-circle-check', mr_l('empty_completed', 'No completed history.'), mr_l('hint_empty_completed', 'Completed torrents will appear here'));
    }

    $html = '<div class="mr-tor-list">';
    while ($r = $db->fetch_array($res)) {
        $up   = (int)($r['uploaded'] ?? 0);
        $dn   = (int)($r['downloaded'] ?? 0);
        [$ratio, $rsoft] = mr_ratio($up, $dn);
        $seed = (int)($r['seeders'] ?? 0);
        $when_ts = (int)($r['completedat'] ?? 0);

        $badges = '<span class="st-badge st-soft-success"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>' . hsafe(mksize($up)) . '</span>'
            . '<span class="st-badge st-soft-danger"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>' . hsafe(mksize($dn)) . '</span>'
            . '<span class="st-badge st-soft-' . $rsoft . '"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>' . $ratio . '</span>'
            . '<span class="st-badge st-soft-' . ($seed > 0 ? 'success' : 'danger') . '"><i class="fa-solid ' . ($seed > 0 ? 'fa-wifi' : 'fa-plug-circle-xmark') . '" aria-hidden="true"></i>'
            . ts_nf($seed) . '/' . ts_nf((int)($r['leechers'] ?? 0)) . '</span>';
        $when = '<i class="fa-solid fa-calendar-check" aria-hidden="true"></i>' . hsafe(my_datee($dateformat, $when_ts) . ' ' . my_datee($timeformat, $when_ts));
        $html .= mr_tor_item($r, $badges, $when);
    }
    return $html . '</div>';
}

/* ═══════════════════════════════════════════════════════════════════════ */

$mybb->input['action'] = $mybb->get_input('action');

// Breadcrumb
$breadcrumb_map = [
    'register'         => $lang->member['nav_register'],
    'do_register'      => $lang->member['nav_register'],
    'activate'         => $lang->member['nav_activate'],
    'resendactivation' => $lang->member['nav_resendactivation'],
    'lostpw'           => $lang->member['nav_lostpw'],
    'resetpassword'    => $lang->member['nav_resetpassword'],
    'login'            => $lang->member['nav_login'],
    'verify_2fa'       => $lang->member['sec_2fa'],
    'emailuser'        => $lang->member['nav_emailuser'],
];
if (isset($breadcrumb_map[$mybb->input['action']])) {
    add_breadcrumb($breadcrumb_map[$mybb->input['action']]);
}

// ── Registration guard ─────────────────────────────────────────────────────
if (in_array($mybb->input['action'], ['register', 'do_register'], true)) {
    if ($disableregs == 1) { stderr($lang->member['registrations_disabled']); }

    if ((int)$maxusers > 0) {
        $count_q = $db->sql_query_prepared('SELECT COUNT(*) AS cnt FROM users WHERE id > 0');
        $count   = $count_q ? (int)$db->fetch_field($count_q, 'cnt') : 0;
        if ((int)$maxusers <= $count) { stderr($lang->global['signuplimitreached']); }
    }

    if ((int)($CURUSER['id'] ?? 0) !== 0) {
        stderr($lang->member['error_alreadyregistered']);
    }

    if ($betweenregstime && $maxregsbetweentime) {
        $datecut  = TIMENOW - (60 * 60 * (int)$betweenregstime);
        $query    = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM users WHERE regip = ? AND added > ?", [$session->packedip, $datecut]);
        $regcount = $query ? (int)$db->fetch_field($query, 'cnt') : 0;
        if ($regcount >= (int)$maxregsbetweentime) {
            stderr(sprintf($lang->member['error_alreadyregisteredtime'], $regcount, $betweenregstime));
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_register
// ══════════════════════════════════════════════════════════════════════════
$fromreg   = 0;
$regerrors = '';
if ($mybb->input['action'] === 'do_register' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_register_start');

    $usergroup = $regtype === 'verify' ? 5 : 2;

    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler('insert');

    $user = [
        'username'     => $mybb->get_input('username'),
        'password'     => $mybb->get_input('password'),
        'password2'    => $mybb->get_input('password2'),
        'invitehash'   => $mybb->get_input('invitehash'),
        'email'        => $mybb->get_input('email'),
        'email2'       => $mybb->get_input('email2'),
        'usergroup'    => $usergroup,
        'referrer'     => $mybb->get_input('referrername'),
        'timezone'     => $mybb->get_input('timezoneoffset'),
        'language'     => $mybb->get_input('language'),
        'regip'        => $session->packedip,
        'regcheck1'    => $mybb->get_input('regcheck1'),
        'regcheck2'    => $mybb->get_input('regcheck2'),
        'registration' => true,
    ];

    $user['options'] = [
        'allownotices'       => $mybb->get_input('allownotices', MyBB::INPUT_INT),
        'hideemail'          => $mybb->get_input('hideemail', MyBB::INPUT_INT),
        'subscriptionmethod' => $mybb->get_input('subscriptionmethod', MyBB::INPUT_INT),
        'receivepms'         => $mybb->get_input('receivepms', MyBB::INPUT_INT),
        'pmnotice'           => $mybb->get_input('pmnotice', MyBB::INPUT_INT),
        'pmnotify'           => $mybb->get_input('pmnotify', MyBB::INPUT_INT),
        'invisible'          => $mybb->get_input('invisible', MyBB::INPUT_INT),
        'dstcorrection'      => $mybb->get_input('dstcorrection'),
    ];

    $errors = [];
    if (!mr_post_key_ok()) {
        $errors[] = mr_l('err_post_key', 'Security token mismatch. Please reload the page and try again.');
    } else {
        $userhandler->set_data($user);
        if (!$userhandler->validate_user()) {
            $errors = $userhandler->get_friendly_errors();
        }
    }

    if (!empty($errors)) {
        $regerrors             = mr_errors($errors, hsafe(mr_l('registration_errors', 'The following errors occurred with your registration:')));
        $mybb->input['action'] = 'register';
        $fromreg               = 1;
    } else {
        $user_info = $userhandler->insert_user();

        // Language chosen in the form: same cookie as language.php, and switch this
        // request so the activation e-mail / welcome PM / success page use it too
        $reg_lang = (string)($user['language'] ?? '');
        if (in_array($reg_lang, ['english', 'russian'], true)) {
            setcookie('ts_language', $reg_lang, time() + 365 * 24 * 3600, '/');
            $_COOKIE['ts_language'] = $reg_lang;

            if ($reg_lang !== (string)($lang->language ?? '') && $lang->language_exists($reg_lang)) {
                $lang->set_language($reg_lang);
                $lang->load('member');
            }
        }

        my_setcookie('mybbuser', $user_info['uid'] . '_' . $user_info['loginkey'], null, true, 'lax');

        if ($regtype === 'verify') {
            $activationcode = random_str();
            $db->sql_query_prepared(
                "INSERT INTO awaitingactivation (`uid`,`dateline`,`code`,`type`) VALUES (?,?,?,?)",
                [$user_info['uid'], TIMENOW, $activationcode, 'r']
            );
            $emailsubject = sprintf($lang->member['emailsubject_activateaccount'], $SITENAME);
            $emailmessage = sprintf($lang->member['email_activateaccount'], $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
            my_mail($user_info['email'], $emailsubject, $emailmessage);
            $plugins->run_hooks('member_do_register_end');

            // stdok() escapes the text itself: raw username, no lang markup
            stdok(
                message:  trim(strip_tags(sprintf($lang->member['redirect_registered_activation'], $SITENAME, (string)$user_info['username']))),
                title:    $lang->member['sec_reg_success'],
                subtitle: $lang->member['hint_reg_success']
            );
        } else {
            $db->sql_query_prepared("UPDATE users SET ustatus = 'confirmed' WHERE id = ? AND ustatus='pending' AND enabled='yes'", [$user_info['uid']]);
            require_once INC_PATH . '/functions_pm.php';
            $pm = ['subject' => sprintf($lang->member['welcomepmsubject'], $SITENAME), 'message' => sprintf($lang->member['welcomepmbody'], $user_info['username'], $SITENAME, $BASEURL), 'touid' => $user_info['uid']];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
            $plugins->run_hooks('member_do_register_end');
            redirect('index.php', sprintf($lang->member['redirect_registered'], $SITENAME, htmlspecialchars_uni($user_info['username'])));
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: register
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'register') {
    $plugins->run_hooks('member_register_start');

    $js_validator_username_length = '';
    if ($maxnamelength > 0 && $minnamelength > 0) {
        $js_validator_username_length = sprintf($lang->member['js_validator_username_length'], $minnamelength, $maxnamelength);
    }

    // Re-posted values (form shown again after errors) or defaults
    $in = static fn(string $k): string => htmlspecialchars_uni((string)$mybb->get_input($k));
    $chk = static function (string $k, bool $default) use ($mybb, $fromreg): string {
        $on = $fromreg ? ($mybb->get_input($k, MyBB::INPUT_INT) === 1) : $default;
        return $on ? ' checked' : '';
    };
    $username = $fromreg ? $in('username') : '';
    $email    = $fromreg ? $in('email') : '';
    $email2   = $fromreg ? $in('email2') : '';
    $sub_sel  = $fromreg ? $mybb->get_input('subscriptionmethod', MyBB::INPUT_INT) : 0;
    $dst_sel  = $fromreg ? $mybb->get_input('dstcorrection', MyBB::INPUT_INT) : 2;

    // Time zone: own options (build_timezone_select() wraps them in usercp markup and needs the usercp lang)
    $tz_selected = str_replace('+', '', (string)($mybb->input['timezoneoffset'] ?? $timezoneoffset));
    $tzoptions = '';
    foreach (get_supported_timezones() as $tz => $tz_label) {
        $tzoptions .= '<option value="' . hsafe($tz) . '"' . ((string)$tz === $tz_selected ? ' selected' : '') . '>'
            . hsafe(_build_short_timezone_label((string)$tz, $timeformat)) . '</option>';
    }

    // Language: re-posted value → ts_language cookie → site default
    $reg_languages = ['english' => 'English', 'russian' => 'Русский'];
    $reg_lang = (string)($mybb->input['language'] ?? $_COOKIE['ts_language'] ?? $defaultlanguage ?? 'english');
    if (!isset($reg_languages[$reg_lang])) {
        $reg_lang = 'english';
    }
    $langoptions = '';
    foreach ($reg_languages as $lang_key => $lang_title) {
        if (!$lang->language_exists($lang_key)) {
            continue;
        }
        $langoptions .= '<option value="' . $lang_key . '"' . ($lang_key === $reg_lang ? ' selected' : '') . '>' . hsafe($lang_title) . '</option>';
    }

    $sel = static fn(int $cur, int $v): string => $cur === $v ? ' selected' : '';
    $suboptions = '<option value="0"' . $sel($sub_sel, 0) . '>' . hsafe($lang->member['no_auto_subscribe']) . '</option>'
        . '<option value="1"' . $sel($sub_sel, 1) . '>' . hsafe($lang->member['no_subscribe']) . '</option>'
        . '<option value="2"' . $sel($sub_sel, 2) . '>' . hsafe($lang->member['instant_email_subscribe']) . '</option>'
        . '<option value="3"' . $sel($sub_sel, 3) . '>' . hsafe($lang->member['instant_pm_subscribe']) . '</option>';
    $dstoptions = '<option value="2"' . $sel($dst_sel, 2) . '>' . hsafe($lang->member['dst_correction_auto']) . '</option>'
        . '<option value="1"' . $sel($dst_sel, 1) . '>' . hsafe($lang->member['dst_correction_enabled']) . '</option>'
        . '<option value="0"' . $sel($dst_sel, 0) . '>' . hsafe($lang->member['dst_correction_disabled']) . '</option>';

    $js_validator_password_length = sprintf($lang->member['js_validator_password_length'], $minpasswordlength);
    $password_label = mr_lbl($lang->member['password']);
    if ($requirecomplexpasswords == 1) {
        $password_label = mr_lbl(sprintf($lang->member['complex_password'], $minpasswordlength));
    }

    $invitehash = htmlspecialchars_uni((string)($_POST['invitehash'] ?? $_GET['invitehash'] ?? $_GET['invite'] ?? $_POST['invite'] ?? ''));

    $js_validator_strings = [
        'js_validator_no_username'           => $lang->member['js_validator_no_username'],
        'js_validator_username_length'       => $js_validator_username_length,
        'js_validator_invalid_email'         => $lang->member['js_validator_invalid_email'],
        'js_validator_email_match'           => $lang->member['js_validator_email_match'],
        'js_validator_not_empty'             => $lang->member['js_validator_not_empty'],
        'js_validator_password_length'       => $js_validator_password_length,
        'js_validator_password_matches'      => $lang->member['js_validator_password_matches'],
        'js_validator_no_image_text'         => $lang->member['js_validator_no_image_text'],
        'js_validator_no_security_question'  => $lang->member['js_validator_no_security_question'],
        'js_validator_bad_password_security' => $lang->member['js_validator_bad_password_security'],
    ];
    $js_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $regsettings = [
        'minnamelength'           => (string)$minnamelength,
        'maxnamelength'           => (string)$maxnamelength,
        'minpasswordlength'       => (string)$minpasswordlength,
        'maxpasswordlength'       => (string)$maxpasswordlength,
        'requirecomplexpasswords' => (string)$requirecomplexpasswords,
        'regtype'                 => (string)$regtype,
    ];

    $plugins->run_hooks('member_register_end');

    $switch = static function (string $name, string $icon, string $text, string $checked): string {
        return '<label class="st-check" for="' . $name . '">'
            . '<input class="form-check-input" type="checkbox" name="' . $name . '" id="' . $name . '" value="1"' . $checked . '>'
            . '<span><i class="fa-solid ' . $icon . ' me-1" aria-hidden="true"></i>' . $text . '</span></label>';
    };

    mr_open($lang->member['welcome_register'], 'wide');
    echo mr_head('fa-user-plus', 'primary', hsafe($lang->member['welcome_register']), hsafe(mr_l('hint_register', 'Create a new account — it only takes a minute')));
    echo $regerrors;
    ?>
    <form action="member.php" method="post" id="registration_form" class="mr-form" novalidate>
        <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">
        <input type="hidden" name="step" value="registration">
        <input type="hidden" name="action" value="do_register">
        <div class="mr-honeypot" aria-hidden="true">
            <input type="text" name="regcheck1" value="" tabindex="-1" autocomplete="off">
            <input type="text" name="regcheck2" value="true" tabindex="-1" autocomplete="off">
        </div>

        <section class="st-card mb-3">
            <div class="st-card__head">
                <span class="st-ico st-soft-primary"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
                <span><?= hsafe(mr_lbl($lang->member['account_details'])) ?></span>
            </div>
            <div class="st-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <?= mr_input('username', hsafe(mr_lbl($lang->member['username'])), 'fa-user', $username, 'text', 'autocomplete="username" placeholder="' . hsafe($lang->member['ph_username']) . '" required') ?>
                    </div>
                    <div class="col-md-6">
                        <?= $regtype === 'invite'
                            ? mr_input('invitehash', hsafe(mr_lbl($lang->member['invitecode'])), 'fa-ticket', $invitehash, 'text', 'autocomplete="off" required')
                            : '' ?>
                    </div>
                    <div class="col-md-6">
                        <?= mr_input('password', $password_label, 'fa-lock', '', 'password', 'autocomplete="new-password" required') ?>
                    </div>
                    <div class="col-md-6">
                        <?= mr_input('password2', hsafe(mr_lbl($lang->member['confirm_password'])), 'fa-lock', '', 'password', 'autocomplete="new-password" required') ?>
                    </div>
                    <div class="col-md-6">
                        <?= mr_input('email', hsafe(mr_lbl($lang->member['email'])), 'fa-envelope', $email, 'email', 'maxlength="50" autocomplete="email" placeholder="' . hsafe($lang->member['ph_email']) . '" required') ?>
                    </div>
                    <div class="col-md-6">
                        <?= mr_input('email2', hsafe(mr_lbl($lang->member['confirm_email'])), 'fa-envelope-circle-check', $email2, 'email', 'maxlength="50" autocomplete="email" placeholder="' . hsafe($lang->member['ph_email2']) . '" required') ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="st-card mb-3">
            <div class="st-card__head">
                <span class="st-ico st-soft-info"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
                <span><?= hsafe(mr_lbl($lang->member['account_prefs'])) ?></span>
            </div>
            <div class="st-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="language"><i class="fa-solid fa-language" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['lbl_language'])) ?></label>
                        <select name="language" id="language" class="form-select"><?= $langoptions ?></select>
                        <div class="form-text"><?= $lang->member['hint_language'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="subscriptionmethod"><i class="fa-solid fa-rss" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['subscription_method'])) ?></label>
                        <select name="subscriptionmethod" id="subscriptionmethod" class="form-select"><?= $suboptions ?></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="timezoneoffset"><i class="fa-solid fa-globe" aria-hidden="true"></i><span><?= mr_lbl($lang->member['time_offset']) ?></span></label>
                        <select name="timezoneoffset" id="timezoneoffset" class="form-select"><?= $tzoptions ?></select>
                        <div class="form-text"><?= $lang->member['time_offset_desc'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="dstcorrection"><i class="fa-solid fa-clock" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['dst_correction'])) ?></label>
                        <select name="dstcorrection" id="dstcorrection" class="form-select"><?= $dstoptions ?></select>
                    </div>
                    <div class="col-12">
                        <div class="form-label"><i class="fa-solid fa-bell" aria-hidden="true"></i><?= hsafe($lang->member['lbl_notifications']) ?></div>
                        <div class="mr-switches">
                            <?= $switch('allownotices', 'fa-bullhorn',     hsafe($lang->member['allow_notices']),      $chk('allownotices', true)) ?>
                            <?= $switch('hideemail',    'fa-eye-slash',    hsafe($lang->member['hide_email']),         $chk('hideemail', false)) ?>
                            <?= $switch('receivepms',   'fa-comments',     hsafe($lang->member['receive_pms']),        $chk('receivepms', true)) ?>
                            <?= $switch('pmnotice',     'fa-desktop',      hsafe($lang->member['pm_notice']),          $chk('pmnotice', true)) ?>
                            <?= $switch('pmnotify',     'fa-envelope',     hsafe($lang->member['email_notify_newpm']), $chk('pmnotify', false)) ?>
                            <?= $switch('invisible',    'fa-user-secret',  hsafe($lang->member['invisible_mode']),     $chk('invisible', false)) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mr-form-foot">
                <a href="member.php?action=login" class="mr-back"><i class="fa-solid fa-right-to-bracket me-1" aria-hidden="true"></i><?= hsafe($lang->member['login']) ?></a>
                <button type="submit" class="btn btn-primary st-pill mr-btn" name="regsubmit" value="1">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i><?= hsafe($lang->member['submit_registration']) ?>
                </button>
            </div>
        </section>
    </form>
    <script>
        var regsettings = <?= json_encode($regsettings, $js_flags) ?>;
        window.lang = window.lang || {};
        Object.assign(window.lang, <?= json_encode($js_validator_strings, $js_flags) ?>);
    </script>
    <script src="<?= $BASEURL ?>/scripts/regvalidator.js?ver=1823"></script>
    <?php
    mr_close(true);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: activate
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'activate') {
    $plugins->run_hooks('member_activate_start');

    if (isset($mybb->input['username'])) {
        $user = get_user_by_username($mybb->get_input('username'), ['fields' => '*']);
        if (!$user) { stderr($lang->member['error_invalidpworusername']); }
    } else {
        $user = get_user($mybb->get_input('id', MyBB::INPUT_INT));
    }

    if (isset($mybb->input['code']) && $user) {
        $query      = $db->sql_query_prepared("SELECT * FROM awaitingactivation WHERE uid = ? AND (type='r' OR type='e' OR type='b')", [$user['id']]);
        $activation = $query ? $db->fetch_array($query) : null;

        if (!$activation)                                                          { stderr($lang->member['error_alreadyactivated']); }
        if (!hash_equals((string)$activation['code'], (string)$mybb->get_input('code'))) { stderr($lang->member['error_badactivationcode']); }
        if ($activation['type'] === 'b' && (int)$activation['validated'] === 1)    { stderr($lang->member['error_alreadyvalidated']); }

        $db->sql_query_prepared("DELETE FROM awaitingactivation WHERE uid = ? AND (type='r' OR type='e')", [$user['id']]);

        if ((int)$user['usergroup'] === 2 && !in_array($activation['type'], ['e', 'b'], true)) {
            $db->sql_query_prepared("UPDATE users SET usergroup = ? WHERE id = ?", [2, $user['id']]);
            $cache->update_awaitingactivation();
        }

        if ($activation['type'] === 'e') {
            $db->sql_query_prepared("UPDATE users SET email = ? WHERE id = ?", [$activation['misc'], $user['id']]);
            write_log('Email of user ' . (string)$user['username'] . ' (ID ' . (int)$user['id'] . ') changed via confirmation link');
            $plugins->run_hooks('member_activate_emailupdated');
            redirect('usercp.php', $lang->member['redirect_emailupdated']);
        } elseif ($activation['type'] === 'b') {
            $db->sql_query_prepared("UPDATE awaitingactivation SET validated = 1 WHERE uid = ? AND type='b'", [$user['id']]);
            $db->sql_query_prepared("UPDATE users SET ustatus = 'confirmed' WHERE id = ? AND ustatus='pending' AND enabled='yes'", [$user['id']]);
            $cache->update_awaitingactivation();

            require_once INC_PATH . '/functions_pm.php';
            $pm = ['subject' => sprintf($lang->member['welcomepmsubject'], $SITENAME), 'message' => sprintf($lang->member['welcomepmbody'], $user['username'], $SITENAME, $BASEURL), 'touid' => $user['id']];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
            write_log('Account of user ' . (string)$user['username'] . ' (ID ' . (int)$user['id'] . ') email-validated');

            $plugins->run_hooks('member_activate_emailactivated');
            redirect('index.php', $lang->member['redirect_accountactivated_admin'], '', true);
        } else {
            $plugins->run_hooks('member_activate_accountactivated');
            $db->sql_query_prepared("UPDATE users SET ustatus = 'confirmed' WHERE id = ? AND ustatus='pending' AND enabled='yes'", [$user['id']]);
            require_once INC_PATH . '/functions_pm.php';
            $pm = ['subject' => sprintf($lang->member['welcomepmsubject'], $SITENAME), 'message' => sprintf($lang->member['welcomepmbody'], $user['username'], $SITENAME, $BASEURL), 'touid' => $user['id']];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
            write_log('Account of user ' . (string)$user['username'] . ' (ID ' . (int)$user['id'] . ') activated');
            redirect('index.php', $lang->member['redirect_accountactivated']);
        }
    }

    $plugins->run_hooks('member_activate_form');
    $code      = htmlspecialchars_uni((string)$mybb->get_input('code'));
    $user_name = htmlspecialchars_uni((string)($user['username'] ?? $mybb->get_input('username')));

    mr_open($lang->member['account_activation'], 'auth');
    echo mr_head('fa-user-check', 'success', hsafe($lang->member['account_activation']), hsafe(mr_l('hint_activate', 'Enter your username and the code from the activation email')));
    ?>
    <form action="member.php" method="post" class="st-card mr-form">
        <input type="hidden" name="action" value="activate">
        <div class="st-card__body">
            <?= mr_input('username', hsafe(mr_lbl($lang->member['username'])), 'fa-user', $user_name, 'text', 'autocomplete="username" required') ?>
            <?= mr_input('code', hsafe(mr_lbl($lang->member['activation_code'])), 'fa-key', $code, 'text', 'autocomplete="off" required') ?>
        </div>
        <div class="mr-form-foot">
            <a href="member.php?action=resendactivation" class="mr-back"><i class="fa-solid fa-envelope-circle-check me-1" aria-hidden="true"></i><?= hsafe($lang->member['resend_activation']) ?></a>
            <button type="submit" class="btn btn-success st-pill mr-btn"><i class="fa-solid fa-check" aria-hidden="true"></i><?= hsafe($lang->member['activate_account']) ?></button>
        </div>
    </form>
    <?php
    mr_close(true);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_resendactivation
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_resendactivation' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_resendactivation_start');

    if (!mr_post_key_ok()) {
        stderr(mr_l('err_post_key', 'Security token mismatch. Please reload the page and try again.'));
    }

    $query = $db->sql_query_prepared(
        "SELECT u.id, u.username, u.usergroup, u.ustatus, u.email, a.code, a.type, a.validated FROM users u LEFT JOIN awaitingactivation a ON (a.uid=u.id AND (a.type='r' OR a.type='b')) WHERE u.email = ?",
        [$mybb->get_input('email')]
    );
    $numusers = $query ? $db->num_rows($query) : 0;

    if ($numusers < 1) {
        error($lang->member['error_invalidemail']);
    }

    while ($user = $db->fetch_array($query)) {
        if ($user['type'] === 'b' && (int)$user['validated'] === 1) { error($lang->member['error_activated_by_admin']); }
        if ($user['ustatus'] === 'pending') {
            if (!$user['code']) {
                $user['code'] = random_str();
                $db->sql_query_prepared(
                    "INSERT INTO awaitingactivation (`uid`,`dateline`,`code`,`type`) VALUES (?,?,?,?)",
                    [$user['id'], TIMENOW, $user['code'], 'r']
                );
                $user['type'] = 'r';
            }
            $emailmessage = sprintf($lang->member['email_activateaccount'], $user['username'], $SITENAME, $BASEURL, $user['id'], $user['code']);
            my_mail($user['email'], sprintf($lang->member['emailsubject_activateaccount'], $SITENAME), $emailmessage);
        }
    }
    $plugins->run_hooks('member_do_resendactivation_end');
    redirect('index.php', $lang->member['redirect_activationresent']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: resendactivation
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'resendactivation') {
    $plugins->run_hooks('member_resendactivation');

    $cur_uid = (int)($CURUSER['id'] ?? 0);
    if ($cur_uid > 0) {
        if (($CURUSER['ustatus'] ?? '') !== 'pending') { error($lang->member['error_alreadyactivated']); }

        $query      = $db->sql_query_prepared("SELECT validated FROM awaitingactivation WHERE uid = ? AND type='b'", [$cur_uid]);
        $activation = $query ? $db->fetch_array($query) : null;
        if ($activation && (int)$activation['validated'] === 1) { error($lang->member['error_activated_by_admin']); }
    }

    $plugins->run_hooks('member_resendactivation_end');

    mr_open($lang->member['resend_activation'], 'auth');
    echo mr_head('fa-envelope-circle-check', 'primary', hsafe($lang->member['resend_activation']), hsafe($lang->member['hint_resend_activation']));
    ?>
    <form action="member.php" method="post" class="st-card mr-form">
        <input type="hidden" name="action" value="do_resendactivation">
        <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">
        <div class="st-card__body">
            <?= mr_input('email', hsafe(mr_lbl($lang->member['email_address'])), 'fa-envelope', '', 'email', 'autocomplete="email" placeholder="you@example.com" required autofocus') ?>
        </div>
        <div class="mr-form-foot">
            <a href="member.php?action=login" class="mr-back"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= hsafe($lang->member['lnk_back_to_login']) ?></a>
            <button type="submit" class="btn btn-primary st-pill mr-btn"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><?= hsafe($lang->member['request_activation']) ?></button>
        </div>
    </form>
    <?php
    mr_close(true);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_lostpw
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_lostpw' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_lostpw_start');

    if (!mr_post_key_ok()) {
        stderr(mr_l('err_post_key', 'Security token mismatch. Please reload the page and try again.'));
    }

    $query    = $db->sql_query_prepared("SELECT id, username, email FROM users WHERE email = ?", [$mybb->get_input('email')]);
    $numusers = $query ? $db->num_rows($query) : 0;

    if ($numusers < 1) {
        stderr($lang->member['error_invalidemail']);
    }

    while ($user = $db->fetch_array($query)) {
        $db->sql_query_prepared("DELETE FROM awaitingactivation WHERE uid = ? AND type='p'", [$user['id']]);
        $activationcode = random_str(30);
        $db->sql_query_prepared(
            "INSERT INTO awaitingactivation (`uid`,`dateline`,`code`,`type`) VALUES (?,?,?,?)",
            [$user['id'], TIMENOW, $activationcode, 'p']
        );
        $emailmessage = sprintf($lang->member['email_lostpw'], $user['username'], $SITENAME, $BASEURL, $user['id'], $activationcode);
        my_mail($user['email'], sprintf($lang->member['emailsubject_lostpw'], $SITENAME), $emailmessage);
    }
    $plugins->run_hooks('member_do_lostpw_end');
    redirect('index.php', $lang->member['redirect_lostpwsent'], '', true);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: lostpw
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'lostpw') {
    $plugins->run_hooks('member_lostpw');

    mr_open($lang->member['lost_pw'], 'auth');
    echo mr_head('fa-key', 'warning', hsafe($lang->member['lost_pw']), hsafe(mr_l('hint_lostpw', 'We will send a password reset link to your email')));
    ?>
    <form action="member.php" method="post" class="st-card mr-form">
        <input type="hidden" name="action" value="do_lostpw">
        <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">
        <div class="st-card__body">
            <?= mr_input('email', hsafe(mr_lbl($lang->member['email_address'])), 'fa-envelope', '', 'email', 'autocomplete="email" placeholder="you@example.com" required autofocus') ?>
        </div>
        <div class="mr-form-foot">
            <a href="member.php?action=login" class="mr-back"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= hsafe($lang->member['lnk_back_to_login']) ?></a>
            <button type="submit" class="btn btn-primary st-pill mr-btn"><i class="fa-solid fa-key" aria-hidden="true"></i><?= hsafe($lang->member['request_user_pass']) ?></button>
        </div>
    </form>
    <?php
    mr_close(true);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: resetpassword
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'resetpassword') {
    $plugins->run_hooks('member_resetpassword_start');

    $user = isset($mybb->input['username'])
        ? get_user_by_username($mybb->get_input('username'), ['fields' => '*'])
        : get_user($mybb->get_input('id', MyBB::INPUT_INT));

    if (!$user && isset($mybb->input['username'])) { stderr($lang->member['error_invalidpworusername']); }

    if (isset($mybb->input['code']) && $user) {
        $query          = $db->sql_query_prepared("SELECT code FROM awaitingactivation WHERE uid = ? AND type='p'", [$user['id']]);
        $activationcode = $query ? $db->fetch_field($query, 'code') : null;

        if (!$activationcode || !hash_equals((string)$activationcode, (string)$mybb->get_input('code'))) {
            stderr($lang->member['error_badlostpwcode']);
        }

        $db->sql_query_prepared("DELETE FROM awaitingactivation WHERE uid = ? AND type='p'", [$user['id']]);

        $password_length = max(8, min((int)$minpasswordlength, (int)$maxpasswordlength));

        require_once INC_PATH . '/datahandlers/user.php';
        $userhandler = new UserDataHandler('update');

        do {
            $password = random_str($password_length, $requirecomplexpasswords);
            $userhandler->set_data(['uid' => $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'password' => $password]);
            $userhandler->set_validated(true);
            $userhandler->errors = [];
        } while (!$userhandler->verify_password());

        $userhandler->update_user();
        write_log('Password of user ' . (string)$user['username'] . ' (ID ' . (int)$user['id'] . ') reset via lost password email');

        my_mail($user['email'], sprintf($lang->member['emailsubject_passwordreset'], $SITENAME), sprintf($lang->member['email_passwordreset'], $user['username'], $SITENAME, $password));
        $plugins->run_hooks('member_resetpassword_reset');
        stdok(
            message: $lang->member['redirect_passwordreset'],
            title:   $lang->member['reset_password']
        );
    }

    $plugins->run_hooks('member_resetpassword_form');
    $code           = htmlspecialchars_uni((string)$mybb->get_input('code'));
    $input_username = htmlspecialchars_uni((string)($user['username'] ?? $mybb->get_input('username')));

    mr_open($lang->member['reset_password'], 'auth');
    echo mr_head('fa-unlock-keyhole', 'warning', hsafe($lang->member['reset_password']), hsafe(mr_l('hint_resetpw', 'Enter your username and the code from the email — the new password will be emailed to you')));
    ?>
    <form action="member.php" method="post" class="st-card mr-form">
        <input type="hidden" name="action" value="resetpassword">
        <div class="st-card__body">
            <?= mr_input('username', hsafe(mr_lbl($lang->member['username'])), 'fa-user', $input_username, 'text', 'autocomplete="username" required') ?>
            <?= mr_input('code', hsafe(mr_lbl($lang->member['activation_code'])), 'fa-key', $code, 'text', 'autocomplete="off" required') ?>
        </div>
        <div class="mr-form-foot">
            <a href="member.php?action=login" class="mr-back"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= hsafe($lang->member['lnk_back_to_login']) ?></a>
            <button type="submit" class="btn btn-primary st-pill mr-btn"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><?= hsafe($lang->member['send_password']) ?></button>
        </div>
    </form>
    <?php
    mr_close(true);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_login
// ══════════════════════════════════════════════════════════════════════════
$errors = [];
if ($mybb->input['action'] === 'do_login' && $mybb->request_method === 'post') {
    // IP blocked after too many failures?
    failedloginscheck('Login');

    $plugins->run_hooks('member_do_login_start');

    if ($mybb->get_input('quick_password') && $mybb->get_input('quick_username')) {
        $mybb->input['password'] = $mybb->get_input('quick_password');
        $mybb->input['username'] = $mybb->get_input('quick_username');
        $mybb->input['remember'] = $mybb->get_input('quick_remember');
    }

    if (!mr_post_key_ok()) {
        $errors[]              = mr_l('err_post_key', 'Security token mismatch. Please reload the page and try again.');
        $mybb->input['action'] = 'login';
    } else {
        require_once INC_PATH . '/datahandlers/login.php';
        $loginhandler = new LoginDataHandler('get');

        $user = ['username' => $mybb->get_input('username'), 'password' => $mybb->get_input('password'), 'remember' => $mybb->get_input('remember')];
        $user_loginattempts = get_user_by_username($user['username'], ['fields' => 'loginattempts']);
        if (!empty($user_loginattempts)) { $user['loginattempts'] = (int)$user_loginattempts['loginattempts']; }

        $loginhandler->set_data($user);

        if (!$loginhandler->validate_login()) {
            $mybb->input['action'] = 'login';
            $mybb->request_method  = 'get';
            $login_user_uid        = (int)($loginhandler->login_data['id'] ?? 0);

            login_attempt_check($login_user_uid);
            if ($login_user_uid > 0) {
                $db->sql_query_prepared("UPDATE users SET loginattempts = loginattempts+1 WHERE id = ?", [$login_user_uid]);
            }
            failedlogins('login', false, true, true, $login_user_uid);
            $errors = $loginhandler->get_friendly_errors();
        } else {
            $login_uid = (int)($loginhandler->login_data['id'] ?? 0);

            require_once INC_PATH . '/functions_2fa.php';
            if (totp_is_enabled($login_uid)) {
                // Password OK but 2FA required — store pending and redirect
                totp_create_pending($login_uid, (string)$mybb->get_input('remember'), mr_safe_return_url((string)$mybb->get_input('url')));
                redirect('member.php?action=verify_2fa', '');
            }

            $loginhandler->complete_login();
            log_login($login_uid, 'success');

            $plugins->run_hooks('member_do_login_end');
            $url = mr_safe_return_url((string)$mybb->get_input('url'));
            redirect($url !== '' ? $url : 'index.php', $lang->member['redirect_loggedin']);
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: verify_2fa
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'verify_2fa') {
    require_once INC_PATH . '/functions_2fa.php';

    $pending = totp_get_pending();
    if ($pending === null) {
        redirect('member.php?action=login', '');
    }

    $uid       = (int)$pending['uid'];
    $max_fails = 5;
    $error     = '';

    if ($mybb->request_method === 'post') {
        if (!mr_post_key_ok()) {
            $error = mr_l('err_post_key', 'Security token mismatch. Please reload the page and try again.');
        } else {
            $code   = (string)preg_replace('/\D/', '', (string)$mybb->get_input('totp_code'));
            $secret = totp_get_secret($uid);

            if ($secret && totp_verify($secret, $code)) {
                totp_clear_pending((string)$pending['token']);

                require_once INC_PATH . '/datahandlers/login.php';
                $loginhandler             = new LoginDataHandler('get');
                $loginhandler->login_data = get_user($uid);
                $mybb->input['remember']  = $pending['remember'];
                $loginhandler->complete_login();

                log_login($uid, 'success');

                $url = mr_safe_return_url((string)$pending['url']);
                redirect($url !== '' ? $url : 'index.php', $lang->member['redirect_loggedin']);
            }

            log_login($uid, 'fail');

            // Warn account owner — password is correct but 2FA code is wrong
            require_once INC_PATH . '/functions_pm.php';
            send_pm([
                'subject' => $lang->member['pm_2fa_fail_subject'],
                'message' => ags_fmt($lang->member['pm_2fa_fail_body'], get_ip(), date('Y-m-d H:i:s')),
                'touid'   => $uid,
                'sender'  => ['uid' => -1],
            ], -1, true);

            // Brute-force guard: after N wrong codes for this pending login, the password must be entered again
            $fq    = $db->sql_query_prepared(
                "SELECT COUNT(*) AS cnt FROM login_log WHERE uid = ? AND status = 'fail' AND datetime >= ?",
                [$uid, (int)$pending['created_at']]
            );
            $fails = $fq ? (int)$db->fetch_field($fq, 'cnt') : 0;
            if ($fails >= $max_fails) {
                totp_clear_pending((string)$pending['token']);
                write_log('2FA login for user ID ' . $uid . ' cancelled after ' . $fails . ' invalid codes');
                stderr(mr_l('err_2fa_too_many', 'Too many invalid codes. Please log in again.'));
            }

            $error = mr_l('err_2fa_invalid', 'Invalid or expired code. Please try again.');
        }
    }

    mr_open($lang->member['sec_2fa'], 'auth');
    echo mr_head('fa-shield-halved', 'primary', hsafe($lang->member['sec_2fa']), hsafe($lang->member['hint_2fa']));
    echo $error !== '' ? mr_errors([hsafe($error)]) : '';
    ?>
    <form method="post" action="member.php?action=verify_2fa" class="st-card mr-form">
        <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">
        <div class="st-card__body">
            <label class="form-label" for="totp_code"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><?= hsafe($lang->member['lbl_2fa_code']) ?></label>
            <input type="text" name="totp_code" id="totp_code" class="form-control form-control-lg mr-code"
                   placeholder="000000" maxlength="6" autocomplete="one-time-code" autofocus
                   inputmode="numeric" pattern="[0-9]{6}" required>
        </div>
        <div class="mr-form-foot">
            <a href="member.php?action=login" class="mr-back"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= hsafe($lang->member['lnk_back_to_login']) ?></a>
            <button type="submit" class="btn btn-primary st-pill mr-btn"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><?= hsafe($lang->member['btn_2fa_verify']) ?></button>
        </div>
    </form>
    <?php
    mr_close(true);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: login
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'login') {
    $plugins->run_hooks('member_login');

    $member_loggedin_notice = '';
    if (!empty($CURUSER['id'])) {
        $member_loggedin_notice = '<div class="mr-notice"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div class="min-w-0">'
            . sprintf($lang->member['already_logged_in'], build_profile_link(htmlspecialchars_uni((string)$CURUSER['username']), (int)$CURUSER['id']))
            . '</div></div>';
    }
    login_attempt_check();

    // Return URL: what was posted (form shown again after an error) or the referer
    $redirect_url = (string)$mybb->get_input('url');
    if ($redirect_url === '' && isset($_SERVER['HTTP_REFERER']) && !str_contains((string)$_SERVER['HTTP_REFERER'], 'action=login')) {
        $redirect_url = (string)$_SERVER['HTTP_REFERER'];
    }
    $redirect_url = mr_safe_return_url($redirect_url);

    // do_login switches $mybb->request_method to 'get' on failure, so check the real method
    $username = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? htmlspecialchars_uni((string)$mybb->get_input('username')) : '';
    $plugins->run_hooks('member_login_end');

    mr_open($lang->member['login'], 'auth');
    echo mr_head('fa-right-to-bracket', 'primary', hsafe($lang->member['login']), hsafe(mr_l('hint_login', 'Sign in to your account')));
    echo $member_loggedin_notice;
    echo mr_errors($errors);
    ?>
    <form action="member.php" method="post" class="st-card mr-form">
        <input type="hidden" name="action" value="do_login">
        <input type="hidden" name="url" value="<?= hsafe($redirect_url) ?>">
        <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">
        <div class="st-card__body">
            <?= mr_input('username', hsafe(mr_lbl($lang->member['username'])), 'fa-user', $username, 'text', 'autocomplete="username" required' . ($username === '' ? ' autofocus' : '')) ?>
            <?= mr_input('password', hsafe(mr_lbl($lang->member['password'])), 'fa-lock', '', 'password', 'autocomplete="current-password" required' . ($username !== '' ? ' autofocus' : ''),
                '<a href="' . $BASEURL . '/member.php?action=lostpw">' . hsafe($lang->member['lostpw_note']) . '</a>') ?>
            <label class="st-check mt-3 mb-0" for="remember">
                <input class="form-check-input" type="checkbox" name="remember" value="yes" id="remember" checked>
                <span><?= hsafe($lang->member['remember_me']) ?></span>
            </label>
        </div>
        <div class="mr-form-foot">
            <a href="member.php?action=register" class="mr-back"><i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i><?= hsafe($lang->member['nav_register']) ?></a>
            <button type="submit" class="btn btn-primary st-pill mr-btn"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><?= hsafe($lang->member['login']) ?></button>
        </div>
    </form>
    <div class="mr-links mt-3"><?= $lang->member['footer'] ?></div>
    <?php
    mr_close(true);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: logout
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'logout') {
    $plugins->run_hooks('member_logout_start');

    if (empty($CURUSER['id'])) {
        redirect('index.php', $lang->member['redirect_alreadyloggedout']);
    }

    if (isset($mybb->input['sid']) && !hash_equals((string)$session->sid, (string)$mybb->get_input('sid'))) {
        stderr($lang->member['error_notloggedout']);
    }

    $logoutkey = $session->make_logoutkey((string)$CURUSER['loginkey']);
    if (!hash_equals($logoutkey, (string)$mybb->get_input('logoutkey'))) {
        stderr($lang->member['error_notloggedout']);
    }

    $uid  = (int)$CURUSER['id'];
    $time = TIMENOW;

    $db->sql_query_prepared("UPDATE users SET lastvisit = ?, lastactive = ? WHERE id = ?", [$time, $time, $uid]);

    // Removes the sessions row and the sid cookie
    $session->destroy_session();
    my_unsetcookie('mybbuser');

    // Clear the admin-panel 2FA gate so a future login (same browser/session,
    // possibly a different account) doesn't inherit a stale "verified" state.
    unset($_SESSION['admin_2fa_ok_' . $uid], $_SESSION['admin_2fa_fail_count']);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $plugins->run_hooks('member_logout_end');
    redirect('member.php?action=login', $lang->member['redirect_loggedout']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: profile
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'profile') {
    if (empty($CURUSER['id'])) { print_no_permission(); }

    gzip();
    maxsysop();

    $cur_uid    = (int)$CURUSER['id'];
    $req_uid    = $mybb->get_input('id', MyBB::INPUT_INT);
    $memprofile = $req_uid ? get_user($req_uid) : $CURUSER;

    if (!$memprofile) {
        stderr($lang->member['error_nomember'], $SITENAME . ' - ' . $lang->member['sec_member_not_found'], 404, 'user');
    }

    $uid      = (int)$memprofile['id'];
    $SameUser = ($uid === $cur_uid);
    $IsStaff  = is_mod($usergroups);

    if ((int)$memprofile['invisible'] === 1 && !$SameUser && !$IsStaff) {
        stderr($lang->member['noperm'], $SITENAME . ' - ' . $lang->member['sec_access_denied'], 403, '403');
    }
    if ($memprofile['ustatus'] === 'pending') {
        stderr($lang->member['pendinguser'], $SITENAME . ' - ' . $lang->member['sec_access_denied'], 403, '403');
    }

    $plugins->run_hooks('member_profile_start');

    $me_username = (string)$memprofile['username'];          // raw — for SQL / JS / urlencode
    $uname       = htmlspecialchars_uni($me_username);        // escaped — for HTML
    $memperms    = user_permissions($uid);

    add_breadcrumb(sprintf($lang->member['nav_profile'], $uname));

    // ── Numbers for the tiles and tab badges: one query ──────────────────
    $cq = $db->sql_query_prepared("
        SELECT
          (SELECT COUNT(*) FROM peers WHERE userid = ? AND seeder = 'yes') AS seeding,
          (SELECT COUNT(*) FROM peers WHERE userid = ? AND seeder = 'no')  AS leeching,
          (SELECT COUNT(*) FROM snatched s
              INNER JOIN torrents t ON t.id = s.torrentid
              INNER JOIN categories c ON c.id = t.category
            WHERE s.finished = 'yes' AND s.userid = ?) AS completed,
          (SELECT COUNT(*) FROM torrents t
            WHERE t.owner = ?
              AND (t.visible = 'yes' OR t.visible = 1 OR t.visible IS NULL)
              AND (t.banned  = 'no'  OR t.banned  = 0  OR t.banned  IS NULL)) AS uploads
    ", [$uid, $uid, $uid, $uid]);
    $cnt = $cq ? ($db->fetch_array($cq) ?: []) : [];
    $n_seed  = (int)($cnt['seeding'] ?? 0);
    $n_leech = (int)($cnt['leeching'] ?? 0);
    $n_done  = (int)($cnt['completed'] ?? 0);
    $n_up    = (int)($cnt['uploads'] ?? 0);

    // ── Traffic / ratio ──────────────────────────────────────────────────
    $up   = (int)($memprofile['uploaded'] ?? 0);
    $down = (int)($memprofile['downloaded'] ?? 0);
    [$ratio, $ratio_soft] = mr_ratio($up, $down);
    $ratio_word = match ($ratio_soft) {
        'success' => mr_l('lbl_ratio_good', 'Good'),
        'warning' => mr_l('lbl_ratio_fair', 'Fair'),
        default   => mr_l('lbl_ratio_poor', 'Poor'),
    };
    $total_traffic = max($up + $down, 1);
    $up_pct   = min(100, (int)round($up / $total_traffic * 100));
    $down_pct = min(100, (int)round($down / $total_traffic * 100));

    // ── Contact permissions ──────────────────────────────────────────────
    $on_ignore = str_contains(',' . (string)$memprofile['ignorelist'] . ',', ',' . $cur_uid . ',');
    $can_email = !$SameUser && (int)$usergroups['cansendemail'] === 1 && (int)$memprofile['hideemail'] !== 1
        && (!$on_ignore || (int)$usergroups['cansendemailoverride'] !== 0);
    $can_pm = !$SameUser && (int)$enablepms !== 0 && (int)$usergroups['canusepms'] === 1
        && (((int)$memprofile['receivepms'] !== 0 && (int)($memperms['canusepms'] ?? 0) !== 0 && !$on_ignore)
            || (int)$usergroups['canoverridepm'] === 1);

    // ── Buddy / ignore ───────────────────────────────────────────────────
    $buddy_btn = $ignore_btn = '';
    if (!$SameUser) {
        $buddy_list  = array_map('intval', explode(',', (string)$CURUSER['buddylist']));
        $ignore_list = array_map('intval', explode(',', (string)$CURUSER['ignorelist']));
        $pk = urlencode((string)$mybb->post_code);
        $un = urlencode($me_username);
        $is_buddy  = in_array($uid, $buddy_list, true);
        $is_ignore = in_array($uid, $ignore_list, true);

        if (!$is_ignore) {
            $buddy_btn = $is_buddy
                ? '<a href="usercp.php?action=do_editlists&amp;delete=' . $uid . '&amp;my_post_key=' . $pk . '" class="btn btn-sm btn-outline-secondary st-pill mr-btn"><i class="fa-solid fa-user-minus" aria-hidden="true"></i><span class="mr-hide-sm">' . hsafe($lang->member['remove_from_buddy_list']) . '</span></a>'
                : '<a href="usercp.php?action=do_editlists&amp;add_username=' . $un . '&amp;my_post_key=' . $pk . '" class="btn btn-sm btn-outline-secondary st-pill mr-btn" aria-label="' . hsafe($lang->member['aria_add_buddy']) . '"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span class="mr-hide-sm">' . hsafe($lang->member['btn_add_buddy']) . '</span></a>';
        }
        if (!$is_buddy) {
            $ignore_btn = $is_ignore
                ? '<a href="usercp.php?action=do_editlists&amp;manage=ignored&amp;delete=' . $uid . '&amp;my_post_key=' . $pk . '" class="btn btn-sm btn-outline-secondary st-pill mr-btn"><i class="fa-solid fa-eye" aria-hidden="true"></i><span class="mr-hide-sm">' . hsafe($lang->member['remove_from_ignore_list']) . '</span></a>'
                : '<a href="usercp.php?action=do_editlists&amp;manage=ignored&amp;add_username=' . $un . '&amp;my_post_key=' . $pk . '" class="btn btn-sm btn-outline-secondary st-pill mr-btn" data-ags-confirm="' . hsafe($lang->member['add_to_ignore_list']) . '?"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i><span class="mr-hide-sm">' . hsafe($lang->member['add_to_ignore_list']) . '</span></a>';
        }
    }

    // ── Avatar ───────────────────────────────────────────────────────────
    $useravatar        = format_avatar($memprofile['avatar'], $memprofile['avatardimensions']);
    $avatar_html       = !empty($useravatar['is_html'])
        ? (string)$useravatar['html']
        : '<img src="' . (string)$useravatar['image'] . '" alt="' . hsafe(mr_l('tip_avatar', 'Avatar')) . '">'; // format_avatar() returns the URL already escaped
    $can_change_avatar = ($SameUser || $IsStaff);

    // ── Dates ────────────────────────────────────────────────────────────
    $memprofile['timezone'] = (float)$memprofile['timezone'];
    if ((int)$memprofile['dst'] === 1) {
        $memprofile['timezone']++;
    }
    $memoffset    = (string)(float)$memprofile['timezone'];
    $memregdate   = my_datee($dateformat, $memprofile['added']);
    $localtime    = ags_fmt($lang->member['local_time_format'], my_datee($dateformat, TIMENOW, $memoffset, 0), my_datee($timeformat, TIMENOW, $memoffset, 0));

    // Birthday
    $membday = $membdayage = '';
    if ($memprofile['birthday']) {
        $membday_arr = explode('-', (string)$memprofile['birthday']);
        if ($memprofile['birthdayprivacy'] !== 'none') {
            if (!empty($membday_arr[0]) && !empty($membday_arr[1]) && !empty($membday_arr[2])) {
                $membdayage = ags_fmt($lang->member['membdayage'], (int)get_age((string)$memprofile['birthday']));
                $membday    = date(fix_mktime($dateformat, $membday_arr[2]), mktime(0, 0, 0, (int)$membday_arr[1], (int)$membday_arr[0], (int)$membday_arr[2]));
            } elseif (!empty($membday_arr[2])) {
                $membday = date('Y', mktime(0, 0, 0, 1, 1, (int)$membday_arr[2]));
            } else {
                $membday = date('F j', mktime(0, 0, 0, (int)$membday_arr[1], (int)$membday_arr[0], 0));
            }
        }
        if ($memprofile['birthdayprivacy'] === 'age')  { $membday = $lang->member['birthdayhidden']; }
        if ($memprofile['birthdayprivacy'] === 'none') { $membday = $lang->member['birthdayhidden']; $membdayage = ''; }
    } else {
        $membday = $lang->member['not_specified'];
    }

    // ── Online status ($wol, not $session: that global is the session object) ──
    $hidden_txt = strip_tags($lang->member['hidden']);
    $wq  = $db->sql_query_prepared("SELECT location, nopermission FROM sessions WHERE uid = ? AND time > ? ORDER BY time DESC LIMIT 1", [$uid, TIMENOW - (int)$wolcutoffmins * 60]);
    $wol = $wq ? $db->fetch_array($wq) : null;

    $last_seen    = max((int)$memprofile['lastactive'], (int)$memprofile['lastvisit']);
    $is_hidden    = (int)$memprofile['invisible'] === 1 && !$SameUser && !$IsStaff;
    $lastvisit    = $last_seen > 0 ? my_datee('relative', $last_seen) : $lang->member['lastvisit_never'];
    $timeonline   = (int)$memprofile['timeonline'] > 0 ? mkprettytime((int)$memprofile['timeonline']) : $lang->member['none_registered'];
    $online_html  = '<span class="mr-offline">' . hsafe($lang->global['postbit_status_offline']) . '</span>';
    $dot          = 'off';

    if ($is_hidden) {
        $lastvisit = $timeonline = $hidden_txt;
        $online_html = '<span class="mr-offline">' . hsafe($hidden_txt) . '</span>';
    } elseif (!empty($wol)) {
        $lang->load('online');
        require_once INC_PATH . '/functions_online.php';
        $activity    = fetch_wol_activity((string)$wol['location'], (bool)$wol['nopermission']);
        $location    = build_friendly_wol_location($activity);
        $online_html = '<a href="online.php" class="mr-online text-decoration-none">' . hsafe($lang->global['postbit_status_online']) . '</a>'
            . '<span class="mr-online-loc">' . $location . ' @ ' . hsafe(my_datee($timeformat, $last_seen)) . '</span>';
        $dot = 'on';
    } elseif ($last_seen > 0 && TIMENOW - $last_seen <= 1800) {
        $dot = 'away';
    }

    // ── Invited by ───────────────────────────────────────────────────────
    $invited_by = '';
    if ((int)$memprofile['invited_by'] > 0) {
        $iq = $db->sql_query_prepared("SELECT username, usergroup FROM users WHERE id = ?", [(int)$memprofile['invited_by']]);
        if ($iq && ($iu = $db->fetch_array($iq))) {
            $invited_by = '<a href="' . get_profile_link((int)$memprofile['invited_by']) . '">'
                . format_name(htmlspecialchars_uni((string)$iu['username']), (int)$iu['usergroup']) . '</a>';
        }
    }

    // ── Ban (staff only) ─────────────────────────────────────────────────
    $is_banned = (int)($memperms['isbannedgroup'] ?? 0) === 1;
    $bannedbit = '';
    if ($is_banned && (int)$usergroups['canstaffpanel'] === 1) {
        $bq = $db->sql_query_prepared("SELECT b.*, a.username AS adminuser FROM banned b LEFT JOIN users a ON (b.admin=a.id) WHERE b.uid = ? LIMIT 1", [$uid]);
        if ($bq && ($memban = $db->fetch_array($bq))) {
            $reason = $memban['reason'] ? htmlspecialchars_uni($parser->parse_badwords((string)$memban['reason'])) : hsafe($lang->member['not_available']);
            $left_cls = '';
            if (in_array($memban['lifted'], ['perm', '', 0, '0'], true) || in_array($memban['bantime'], ['perm', '---'], true)) {
                $banlength     = $lang->member['permanent'];
                $timeremaining = $lang->member['not_available'];
            } else {
                $bantimes      = fetch_ban_times();
                $banlength     = $bantimes[$memban['bantime']] ?? $memban['bantime'];
                $remaining     = (int)$memban['lifted'] - TIMENOW;
                $timeremaining = mkprettytime(max(0, $remaining));
                $left_cls      = $remaining < 3600 ? 'high' : ($remaining < 86400 ? 'moderate' : '');
            }
            $bannedbit = '<section class="st-card mr-ban mb-3">'
                . '<div class="st-card__head"><span class="st-ico st-soft-danger"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>'
                . '<span>' . hsafe($lang->member['ban_note']) . '</span>'
                . '<span class="st-badge st-soft-danger ms-auto"><i class="fa-solid fa-clock" aria-hidden="true"></i>' . hsafe($banlength) . '</span></div>'
                . '<div class="st-card__body">'
                . '<div class="st-meta mb-1">' . hsafe($lang->global['banned_warning2']) . '</div>'
                . '<div class="mr-ban__reason mb-3">' . $reason . '</div>'
                . '<div class="st-rows">'
                . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-user-shield" aria-hidden="true"></i>' . hsafe(mr_lbl($lang->member['ban_by'])) . '</span>'
                . '<span class="st-row__val">' . build_profile_link(htmlspecialchars_uni((string)$memban['adminuser']), (int)$memban['admin']) . '</span></div>'
                . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>' . hsafe(mr_lbl($lang->member['ban_length'])) . '</span>'
                . '<span class="st-row__val' . ($left_cls !== '' ? ' mr-ban__left--' . $left_cls : '') . '">' . hsafe($banlength) . ' · ' . hsafe(ags_fmt($lang->member['ban_time_remaining'], $timeremaining)) . '</span></div>'
                . '</div></div></section>';
        }
    }

    // ── Signature ────────────────────────────────────────────────────────
    $signature = '';
    if ($memprofile['signature']) {
        $signature = $parser->parse_message((string)$memprofile['signature'], [
            'allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1,
            'me_username' => $me_username, 'filter_badwords' => 1,
        ]);
    }

    // ── Forum stats ──────────────────────────────────────────────────────
    $stats_cache = $cache->read('stats');
    $tot_posts   = (int)($stats_cache['numposts'] ?? 0);
    $tot_threads = (int)($stats_cache['numthreads'] ?? 0);
    $postnum     = (int)$memprofile['postnum'];
    $threadnum   = (int)$memprofile['threadnum'];
    $daysreg     = max(1, (TIMENOW - (int)$memprofile['added']) / 86400);
    $ppd = round(min($postnum, $postnum / $daysreg), 2);
    $tpd = round(min($threadnum, $threadnum / $daysreg), 2);
    $ppd_line = ags_fmt($lang->member['ppd_percent_total'], ts_nf($ppd), $tot_posts > 0 ? min(100, round($postnum * 100 / $tot_posts, 2)) : 0);
    $tpd_line = ags_fmt($lang->member['tpd_percent_total'], ts_nf($tpd), $tot_threads > 0 ? min(100, round($threadnum * 100 / $tot_threads, 2)) : 0);

    // ── Recent comments ──────────────────────────────────────────────────
    $comments_html = '';
    $rcq = $db->sql_query_prepared("
        SELECT c.id, c.text, c.dateline, c.torrent AS torrentid, t.name AS torrent_name
        FROM comments c LEFT JOIN torrents t ON (c.torrent = t.id)
        WHERE c.user = ? ORDER BY c.dateline DESC LIMIT 10
    ", [$uid]);
    if ($rcq && $db->num_rows($rcq) > 0) {
        $comments_html = '<div class="st-rows">';
        while ($cm = $db->fetch_array($rcq)) {
            $plain = trim((string)preg_replace('/\[[^\]]{1,80}\]/u', ' ', strip_tags((string)$cm['text'])));
            $plain = mb_strimwidth((string)preg_replace('/\s+/u', ' ', $plain), 0, 140, '…');
            $c_link = $BASEURL . '/' . get_comment_link((int)$cm['id'], (int)$cm['torrentid']) . '#pid' . (int)$cm['id'];
            $c_ts   = (int)$cm['dateline'];
            $comments_html .= '<div class="st-row mr-comm">'
                . '<div class="min-w-0 flex-grow-1">'
                . '<a class="mr-comm__title" href="' . $c_link . '"><i class="fa-solid fa-film me-1 opacity-75" aria-hidden="true"></i>'
                . htmlspecialchars_uni((string)($cm['torrent_name'] ?? $lang->member['lbl_unknown_torrent'])) . '</a>'
                . '<div class="mr-comm__text">' . hsafe($plain) . '</div></div>'
                . '<div class="mr-comm__date">' . hsafe(my_datee($dateformat, $c_ts)) . '<br>' . hsafe(my_datee($timeformat, $c_ts)) . '</div>'
                . '</div>';
        }
        $comments_html .= '</div>';
    } else {
        $comments_html = mr_empty('fa-comments', mr_l('empty_comments', 'No comments yet.'));
    }

    // ── Torrent lists ────────────────────────────────────────────────────
    $list_uploads   = mr_recent_uploads($db, $uid, 10);
    $list_seeding   = mr_active_list($db, $uid, true, 10);
    $list_leeching  = mr_active_list($db, $uid, false, 10);
    $list_completed = mr_completed_list($db, $uid, 10);

    // Claims (NexusPHP: "Claims: N/max"); other users' claims are not public (claim.php checks it too)
    require_once INC_PATH . '/functions_claim.php';
    $claims_line = (CLAIM_ENABLED && ($SameUser || $IsStaff))
        ? '<a href="' . $BASEURL . '/claim.php?uid=' . $uid . '"><i class="fa-solid fa-heart-pulse me-1" aria-hidden="true"></i>'
          . hsafe(ags_fmt($lang->member['lbl_claims'], claim_count_user($uid), number_format(CLAIM_MAX_PER_USER))) . '</a>'
        : '';

    // ── Staff: IP + tools ────────────────────────────────────────────────
    $mod_card = '';
    if ($IsStaff) {
        $regip  = my_inet_ntop($db->unescape_binary($memprofile['regip']));
        $lastip = my_inet_ntop($db->unescape_binary($memprofile['lastip']));
        $adm    = $BASEURL . '/admin/';
        $links  = '<a class="btn btn-sm btn-outline-secondary st-pill mr-btn" href="' . $adm . 'edituser.php?action=edituser&amp;userid=' . $uid . '"><i class="fa-solid fa-user-pen" aria-hidden="true"></i>' . hsafe($lang->member['admin_edit_in_acp']) . '</a>';
        if ($is_banned) {
            $links .= '<a class="btn btn-sm btn-outline-secondary st-pill mr-btn" href="' . $adm . 'index.php?act=banning&amp;type=users&amp;action=edit&amp;uid=' . $uid . '"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>' . hsafe($lang->member['admin_edit_ban_in_acp']) . '</a>'
                . '<a class="btn btn-sm btn-outline-success st-pill mr-btn" href="' . $adm . 'index.php?act=banning&amp;type=users&amp;action=lift&amp;uid=' . $uid . '"><i class="fa-solid fa-unlock" aria-hidden="true"></i>' . hsafe($lang->member['admin_lift_ban_in_acp']) . '</a>';
        } elseif (!$SameUser) {
            $links .= '<a class="btn btn-sm btn-outline-danger st-pill mr-btn" href="' . $adm . 'index.php?act=banning&amp;type=users&amp;username=' . urlencode($me_username) . '"><i class="fa-solid fa-ban" aria-hidden="true"></i>' . hsafe($lang->member['admin_ban_in_acp']) . '</a>';
        }
        $mod_card = '<section class="st-card">'
            . '<div class="st-card__head"><span class="st-ico st-soft-warning"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><span>' . hsafe($lang->member['mod_options']) . '</span></div>'
            . '<div class="st-card__body">'
            . '<div class="st-rows mb-3">'
            . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>' . hsafe(mr_lbl($lang->member['registration_ip'])) . '</span><span class="st-row__val">' . hsafe($regip) . '</span></div>'
            . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-network-wired" aria-hidden="true"></i>' . hsafe(mr_lbl($lang->member['last_known_ip'])) . '</span><span class="st-row__val">' . hsafe($lastip) . '</span></div>'
            . '</div>'
            . '<div class="st-meta mb-2"><i class="fa-solid fa-screwdriver-wrench me-1" aria-hidden="true"></i>' . hsafe($lang->member['lbl_quick_actions']) . '</div>'
            . '<div class="mr-modlinks">' . $links . '</div>'
            . '</div></section>';
    }

    // ── Report (strings for report_user.js: js_report_* → AGS_LANG without js_) ──
    $ags_js_lang = [];
    foreach ($lang->member as $js_key => $js_val) {
        if (str_starts_with((string)$js_key, 'js_report_')) {
            $ags_js_lang[substr((string)$js_key, 3)] = (string)$js_val;
        }
    }
    $js_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    $reasons_map = [
        'spam'          => ['fa-user-slash',        'opt_reason_spam',          'hint_reason_spam'],
        'harassment'    => ['fa-ban',               'opt_reason_harassment',    'hint_reason_harassment'],
        'fake'          => ['fa-mask',              'opt_reason_fake',          'hint_reason_fake'],
        'impersonation' => ['fa-id-badge',          'opt_reason_impersonation', 'hint_reason_impersonation'],
        'inappropriate' => ['fa-eye-slash',         'opt_reason_inappropriate', 'hint_reason_inappropriate'],
        'scam'          => ['fa-skull-crossbones',  'opt_reason_scam',          'hint_reason_scam'],
        'copyright'     => ['fa-copyright',         'opt_reason_copyright',     'hint_reason_copyright'],
        'malware'       => ['fa-bug',               'opt_reason_malware',       'hint_reason_malware'],
        'other'         => ['fa-ellipsis',          'opt_reason_other',         'hint_reason_other'],
    ];

    $formattedname = format_name($uname, (int)($memprofile['usergroup'] ?? 1));
    $usericons     = get_user_icons($memprofile);
    $group_title   = (string)($memperms['title'] ?? '');

    $plugins->run_hooks('member_profile_end');

    // ═════════════════════════════ OUTPUT ═════════════════════════════
    mr_open(sprintf($lang->member['title'], $uname));
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css?ver=1">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';
    build_breadcrumb();
    ?>
    <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">

    <!-- Header -->
    <header class="st-card st-head mr-hero mb-3">
        <div class="mr-ava<?= $can_change_avatar ? ' mr-ava--edit' : '' ?>" id="avatar-container"
             data-uid="<?= $uid ?>" data-can-change="<?= $can_change_avatar ? 1 : 0 ?>"
             data-max-mb="<?= (int)round((int)$avatarsize / 1024) ?>"
             <?= $can_change_avatar ? 'role="button" tabindex="0" title="' . hsafe($lang->member['lbl_change_avatar']) . '"' : '' ?>>
            <div><?= $avatar_html ?></div>
            <span class="mr-dot mr-dot--<?= $dot ?>" aria-hidden="true"></span>
            <?php if ($can_change_avatar): ?>
                <span class="avatar-overlay"><?= hsafe($lang->member['lbl_change_avatar']) ?></span>
                <div id="avatar-progress" class="mr-ava__progress"><div id="avatar-progress-bar"></div></div>
            <?php endif; ?>
        </div>
        <?php if ($can_change_avatar): ?>
            <input type="file" id="avatar-input" accept="image/*" hidden>
        <?php endif; ?>

        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title mr-name"><span><?= $formattedname ?></span><?= $usericons !== '' ? '<span class="mr-name__icons">' . $usericons . '</span>' : '' ?></h1>
            <div class="mr-usertitle d-flex flex-wrap align-items-center gap-2">
                <?= (string)($memperms['image'] ?? '') ?>
                <?php if ($group_title !== ''): ?><span><?= hsafe($group_title) ?></span><?php endif; ?>
            </div>
            <div class="st-head__meta">
                <span><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['joined'])) ?>: <?= hsafe($memregdate) ?></span>
                <span><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['lastvisit'])) ?>: <?= hsafe(strip_tags($lastvisit)) ?></span>
                <span><i class="fa-solid fa-arrow-up" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['lbl_seeding'])) ?> <?= ts_nf($n_seed) ?></span>
                <span><i class="fa-solid fa-arrow-down" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['lbl_leeching'])) ?> <?= ts_nf($n_leech) ?></span>
            </div>
        </div>

        <div class="mr-actions">
            <?php if ($can_pm): ?>
                <a href="private.php?action=send&amp;uid=<?= $uid ?>" class="btn btn-sm btn-primary st-pill mr-btn" aria-label="<?= hsafe($lang->member['aria_send_pm']) ?>">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i><span><?= hsafe($lang->member['btn_send_pm']) ?></span>
                </a>
            <?php endif; ?>
            <?php if ($can_email): ?>
                <a href="member.php?action=emailuser&amp;id=<?= $uid ?>" class="btn btn-sm btn-outline-secondary st-pill mr-btn" title="<?= hsafe(sprintf($lang->member['send_user_email'], $me_username)) ?>">
                    <i class="fa-solid fa-at" aria-hidden="true"></i><span class="mr-hide-sm"><?= hsafe($lang->member['lbl_email']) ?></span>
                </a>
            <?php endif; ?>
            <?= $buddy_btn ?>
            <?= $ignore_btn ?>
            <?php if (!$SameUser): ?>
                <button type="button" class="btn btn-sm btn-outline-danger st-pill mr-btn" title="<?= hsafe($lang->member['tip_report_user']) ?>"
                        data-mr-report-uid="<?= $uid ?>" data-mr-report-name="<?= hsafe($me_username) ?>">
                    <i class="fa-solid fa-flag" aria-hidden="true"></i><span class="mr-hide-sm"><?= hsafe($lang->member['report_user']) ?></span>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- KPI -->
    <div class="mr-kpis mb-3">
        <div class="st-kpi st-kpi--muted mr-kpi">
            <i class="fa-solid fa-arrow-up st-kpi__bg" aria-hidden="true"></i>
            <div class="st-kpi__val"><?= hsafe(mksize($up)) ?></div>
            <div class="st-kpi__lbl"><?= hsafe($lang->member['lbl_uploaded']) ?></div>
            <div class="mr-kpi__sub"><?= hsafe(ags_fmt($lang->member['lbl_pct_of_total'], $up_pct)) ?></div>
        </div>
        <div class="st-kpi st-kpi--muted mr-kpi">
            <i class="fa-solid fa-arrow-down st-kpi__bg" aria-hidden="true"></i>
            <div class="st-kpi__val"><?= hsafe(mksize($down)) ?></div>
            <div class="st-kpi__lbl"><?= hsafe($lang->member['lbl_downloaded']) ?></div>
            <div class="mr-kpi__sub"><?= hsafe(ags_fmt($lang->member['lbl_pct_of_total'], $down_pct)) ?></div>
        </div>
        <div class="st-kpi st-kpi--muted mr-kpi">
            <i class="fa-solid fa-scale-balanced st-kpi__bg" aria-hidden="true"></i>
            <div class="st-kpi__val"><?= $ratio ?></div>
            <div class="st-kpi__lbl"><?= hsafe($lang->member['lbl_ratio']) ?></div>
            <div class="mr-kpi__sub"><span class="st-badge st-soft-<?= $ratio_soft ?>"><?= hsafe($ratio_word) ?></span></div>
        </div>
        <div class="st-kpi st-kpi--muted mr-kpi">
            <i class="fa-solid fa-circle-check st-kpi__bg" aria-hidden="true"></i>
            <div class="st-kpi__val"><?= ts_nf($n_done) ?></div>
            <div class="st-kpi__lbl"><?= hsafe($lang->member['lbl_snatched']) ?></div>
            <?php if ($claims_line !== ''): ?><div class="mr-kpi__sub"><?= $claims_line ?></div><?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="st-card">
                <div class="st-card__head mr-tabs-head">
                    <ul class="mr-tabs nav" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="mr-tabbtn-about" data-bs-toggle="pill" data-bs-target="#mr-tab-about" type="button" role="tab" aria-controls="mr-tab-about" aria-selected="true">
                                <i class="fa-solid fa-user" aria-hidden="true"></i><span><?= hsafe($lang->member['tab_about']) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="mr-tabbtn-forum" data-bs-toggle="pill" data-bs-target="#mr-tab-forum" type="button" role="tab" aria-controls="mr-tab-forum" aria-selected="false">
                                <i class="fa-solid fa-comments" aria-hidden="true"></i><span><?= hsafe($lang->member['tab_forum']) ?></span>
                                <span class="st-badge"><?= ts_nf($postnum) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="mr-tabbtn-torrents" data-bs-toggle="pill" data-bs-target="#mr-tab-torrents" type="button" role="tab" aria-controls="mr-tab-torrents" aria-selected="false">
                                <i class="fa-solid fa-film" aria-hidden="true"></i><span><?= hsafe($lang->member['tab_torrents']) ?></span>
                                <span class="st-badge"><?= ts_nf($n_up) ?></span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="st-card__body tab-content">
                    <!-- ABOUT -->
                    <div class="tab-pane fade show active" id="mr-tab-about" role="tabpanel" aria-labelledby="mr-tabbtn-about" tabindex="0">
                        <?= $bannedbit ?>
                        <div class="mr-sec">
                            <div class="st-rows">
                                <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['joined'])) ?></span><span class="st-row__val"><?= hsafe($memregdate) ?></span></div>
                                <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['lastvisit'])) ?></span><span class="st-row__val"><?= hsafe(strip_tags($lastvisit)) ?></span></div>
                                <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-cake-candles" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['date_of_birth'])) ?></span><span class="st-row__val"><?= hsafe(trim($membday . ' ' . $membdayage)) ?></span></div>
                                <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-earth-europe" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['local_time'])) ?></span><span class="st-row__val"><?= hsafe($localtime) ?></span></div>
                                <?php if ($invited_by !== ''): ?>
                                    <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i><?= hsafe(mr_lbl(ags_fmt($lang->member['iby'], ''))) ?></span><span class="st-row__val"><?= $invited_by ?></span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($signature !== ''): ?>
                            <div class="mr-sec">
                                <div class="mr-sec__head"><span class="st-ico st-soft-muted"><i class="fa-solid fa-signature" aria-hidden="true"></i></span><span><?= hsafe(sprintf($lang->member['users_signature'], $me_username)) ?></span></div>
                                <div class="mr-sig"><?= $signature ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- FORUM -->
                    <div class="tab-pane fade" id="mr-tab-forum" role="tabpanel" aria-labelledby="mr-tabbtn-forum" tabindex="0">
                        <div class="mr-sec">
                            <div class="mr-sec__head"><span class="st-ico st-soft-primary"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span><span><?= hsafe(sprintf($lang->member['users_forum_info'], $me_username)) ?></span></div>
                            <div class="mr-fstats">
                                <div class="mr-fstat">
                                    <span class="st-ico st-soft-primary"><i class="fa-solid fa-message" aria-hidden="true"></i></span>
                                    <div class="min-w-0">
                                        <div class="mr-fstat__val"><?= ts_nf($postnum) ?></div>
                                        <div class="mr-fstat__lbl"><?= hsafe(mr_lbl($lang->member['total_posts'])) ?></div>
                                        <div class="mr-fstat__sub"><?= hsafe($ppd_line) ?></div>
                                    </div>
                                    <?php if ($postnum > 0): ?>
                                        <a href="search.php?action=finduser&amp;uid=<?= $uid ?>" class="btn btn-sm btn-outline-primary st-pill mr-fstat__link" title="<?= hsafe($lang->member['find_posts']) ?>" aria-label="<?= hsafe($lang->member['find_posts']) ?>"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                </div>
                                <div class="mr-fstat">
                                    <span class="st-ico st-soft-success"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                                    <div class="min-w-0">
                                        <div class="mr-fstat__val"><?= ts_nf($threadnum) ?></div>
                                        <div class="mr-fstat__lbl"><?= hsafe(mr_lbl($lang->member['total_threads'])) ?></div>
                                        <div class="mr-fstat__sub"><?= hsafe($tpd_line) ?></div>
                                    </div>
                                    <?php if ($threadnum > 0): ?>
                                        <a href="search.php?action=finduserthreads&amp;uid=<?= $uid ?>" class="btn btn-sm btn-outline-success st-pill mr-fstat__link" title="<?= hsafe($lang->member['find_threads']) ?>" aria-label="<?= hsafe($lang->member['find_threads']) ?>"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mr-sec">
                            <div class="mr-sec__head"><span class="st-ico st-soft-info"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i></span><span><?= hsafe($lang->member['sec_recent_comments']) ?></span><span class="st-badge st-soft-muted"><?= ts_nf((int)($memprofile['comms'] ?? 0)) ?></span></div>
                            <?= $comments_html ?>
                        </div>
                    </div>

                    <!-- TORRENTS -->
                    <div class="tab-pane fade" id="mr-tab-torrents" role="tabpanel" aria-labelledby="mr-tabbtn-torrents" tabindex="0">
                        <div class="mr-sec">
                            <div class="mr-sec__head"><span class="st-ico st-soft-primary"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span><span><?= hsafe($lang->member['sec_uploaded_torrents']) ?></span><span class="st-badge st-soft-muted"><?= ts_nf($n_up) ?></span></div>
                            <?= $list_uploads ?>
                        </div>
                        <div class="mr-sec">
                            <div class="mr-sec__head"><span class="st-ico st-soft-success"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></span><span><?= hsafe($lang->member['sec_seeding_now']) ?></span><span class="st-badge st-soft-muted"><?= ts_nf($n_seed) ?></span></div>
                            <?= $list_seeding ?>
                        </div>
                        <div class="mr-sec">
                            <div class="mr-sec__head"><span class="st-ico st-soft-info"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></span><span><?= hsafe($lang->member['sec_leeching_now']) ?></span><span class="st-badge st-soft-muted"><?= ts_nf($n_leech) ?></span></div>
                            <?= $list_leeching ?>
                        </div>
                        <div class="mr-sec">
                            <div class="mr-sec__head"><span class="st-ico st-soft-muted"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span><?= hsafe($lang->member['sec_completed_recently']) ?></span><span class="st-badge st-soft-muted"><?= ts_nf($n_done) ?></span></div>
                            <?= $list_completed ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <aside class="mr-side">
                <section class="st-card">
                    <div class="st-card__head"><span class="st-ico st-soft-success"><i class="fa-solid fa-signal" aria-hidden="true"></i></span><span><?= hsafe($lang->member['sec_status']) ?></span></div>
                    <div class="st-card__body">
                        <div class="st-rows">
                            <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-circle" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->global['postbit_status'])) ?></span><span class="st-row__val"><?= $online_html ?></span></div>
                            <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['timeonline'])) ?></span><span class="st-row__val"><?= hsafe(strip_tags($timeonline)) ?></span></div>
                            <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['lbl_seeding'])) ?></span><span class="st-row__val"><?= ts_nf($n_seed) ?></span></div>
                            <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i><?= hsafe(mr_lbl($lang->member['lbl_leeching'])) ?></span><span class="st-row__val"><?= ts_nf($n_leech) ?></span></div>
                        </div>
                    </div>
                </section>
                <?= $mod_card ?>
            </aside>
        </div>
    </div>

    <?php if (!$SameUser): ?>
    <div class="modal fade mr-report" id="reportUserModal" tabindex="-1" aria-labelledby="reportUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="reportUserForm" method="POST" action="<?= $BASEURL ?>/takereport.php">
                    <input type="hidden" name="type" value="user">
                    <input type="hidden" name="reported_id" value="<?= $uid ?>">
                    <input type="hidden" name="reported_user_id" value="<?= $uid ?>">
                    <input type="hidden" name="addedby" value="<?= $cur_uid ?>">
                    <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reportUserModalLabel" data-username="<?= hsafe($me_username) ?>">
                            <i class="fa-solid fa-flag me-2" aria-hidden="true"></i><?= hsafe(ags_fmt($lang->member['sec_report_user'], $me_username)) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= hsafe($lang->member['cans']) ?>"></button>
                    </div>
                    <div class="modal-body">
                        <h6 class="mb-3"><i class="fa-solid fa-circle-exclamation me-2" aria-hidden="true"></i><?= hsafe($lang->member['sec_report_reason']) ?></h6>
                        <div class="row g-2 mb-4">
                            <?php foreach ($reasons_map as $key => [$r_icon, $r_text, $r_hint]): ?>
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="reason" id="reason_<?= $key ?>" value="<?= $key ?>" autocomplete="off">
                                <label class="btn btn-outline-danger w-100 mr-reason" for="reason_<?= $key ?>">
                                    <span class="d-flex align-items-center gap-2">
                                        <i class="fa-solid <?= $r_icon ?>" aria-hidden="true"></i>
                                        <span>
                                            <span class="fw-bold d-block"><?= hsafe($lang->member[$r_text]) ?></span>
                                            <span class="small text-muted d-block"><?= hsafe($lang->member[$r_hint]) ?></span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <h6 class="mb-3"><i class="fa-solid fa-file-lines me-2" aria-hidden="true"></i><?= hsafe($lang->member['sec_report_details']) ?></h6>
                        <div class="mb-3">
                            <label for="reportDescription" class="form-label fw-semibold"><?= hsafe($lang->member['lbl_report_description']) ?> <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reportDescription" name="description" rows="5" placeholder="<?= hsafe($lang->member['ph_report_description']) ?>" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="additionalInfo" class="form-label"><?= hsafe($lang->member['lbl_report_additional']) ?></label>
                            <textarea class="form-control" id="additionalInfo" name="additional_info" rows="3"></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="evidenceLinks" class="form-label"><i class="fa-solid fa-link me-1" aria-hidden="true"></i><?= hsafe($lang->member['lbl_report_evidence']) ?></label>
                            <input type="text" class="form-control" id="evidenceLinks" name="evidence_links" placeholder="<?= hsafe($lang->member['ph_report_evidence']) ?>">
                        </div>

                        <div class="d-flex align-items-center mb-2">
                            <h6 class="mb-0"><i class="fa-solid fa-shield-halved me-2" aria-hidden="true"></i><?= hsafe($lang->member['sec_security_check']) ?></h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary st-pill ms-auto" id="userReportRefreshCaptcha" title="<?= hsafe($lang->member['tip_captcha_refresh']) ?>" aria-label="<?= hsafe($lang->member['tip_captcha_refresh']) ?>">
                                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-6">
                                <img src="report_captcha.php" alt="<?= hsafe($lang->member['alt_captcha']) ?>" class="border rounded mr-captcha" id="userReportCaptchaDisplay" title="<?= hsafe($lang->member['tip_captcha_refresh']) ?>">
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control" id="userReportCaptchaInput" name="captcha_response" placeholder="<?= hsafe($lang->member['ph_captcha']) ?>" autocomplete="off">
                            </div>
                        </div>
                        <div class="alert alert-warning mb-0">
                            <i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>
                            <strong><?= hsafe($lang->member['lbl_important']) ?></strong> <?= hsafe($lang->member['hint_report_warning']) ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1" aria-hidden="true"></i><?= hsafe($lang->member['cans']) ?></button>
                        <button type="submit" class="btn btn-danger st-pill"><i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i><?= hsafe($lang->member['btn_report_submit']) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
    <?= ags_js_lang('global', ['dlg_ok' => 'js_dlg_ok', 'dlg_yes' => 'js_dlg_yes', 'dlg_cancel' => 'js_dlg_cancel']) ?>
    <script src="<?= $BASEURL ?>/scripts/ags-dialog.js?ver=1"></script>
    <script src="<?= $BASEURL ?>/scripts/toast.js"></script>
    <?= avatar_js_lang() ?>
    <script src="<?= $BASEURL ?>/scripts/avatar_upload.js?ver=5"></script>
    <?php if (!$SameUser): ?>
        <script>window.AGS_LANG = Object.assign(window.AGS_LANG || {}, <?= json_encode($ags_js_lang, $js_flags) ?>);</script>
        <script src="<?= $BASEURL ?>/scripts/report_user.js?ver=3"></script>
    <?php endif; ?>
    <script src="<?= $BASEURL ?>/scripts/member.js?ver=1"></script>
    <?php
    mr_close();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_emailuser
// ══════════════════════════════════════════════════════════════════════════
$errors = [];
if ($mybb->input['action'] === 'do_emailuser' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_emailuser_start');

    if ((int)$usergroups['cansendemail'] === 0) { print_no_permission(); }

    $query   = $db->sql_query_prepared("SELECT id, username, email, hideemail, ignorelist FROM users WHERE id = ?", [$mybb->get_input('id', MyBB::INPUT_INT)]);
    $to_user = $query ? $db->fetch_array($query) : null;

    if (empty($to_user['username']))      { stderr($lang->member['error_invalidusername']); }
    if ((int)$to_user['hideemail'] !== 0) { stderr($lang->member['error_hideemail']); }
    if ($to_user['ignorelist'] && str_contains(',' . $to_user['ignorelist'] . ',', ',' . (int)($CURUSER['id'] ?? 0) . ',')
        && (int)$usergroups['cansendemailoverride'] !== 1) {
        print_no_permission();
    }

    $fromemail = (string)$mybb->get_input('fromemail');
    $fromname  = (string)$mybb->get_input('fromname');
    if (!empty($CURUSER['id'])) {
        $fromemail = (string)$CURUSER['email'];
        $fromname  = (string)$CURUSER['username'];
    }
    $subject = (string)$mybb->get_input('subject');
    $message = (string)$mybb->get_input('message');

    if (!mr_post_key_ok())                  { $errors[] = mr_l('err_post_key', 'Security token mismatch. Please reload the page and try again.'); }
    if (!validate_email_format($fromemail)) { $errors[] = $lang->member['error_invalidfromemail']; }
    if (trim($fromname) === '')             { $errors[] = $lang->member['error_noname']; }
    if (trim($subject) === '')              { $errors[] = $lang->member['error_no_email_subject']; }
    if (trim($message) === '')              { $errors[] = $lang->member['error_no_email_message']; }

    if (empty($errors)) {
        $from     = $mail_handler === 'smtp' ? $fromemail : "{$fromname} <{$fromemail}>";
        $body     = sprintf($lang->member['email_emailuser'], $to_user['username'], $fromname, $SITENAME, $BASEURL, $message);
        my_mail($to_user['email'], $subject, $body, '', '', '', false, 'text', '', $from);

        if ($mail_logging > 0) {
            $db->sql_query_prepared(
                "INSERT INTO maillogs (`subject`,`message`,`dateline`,`fromuid`,`fromemail`,`touid`,`toemail`,`tid`,`ipaddress`,`type`) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$subject, $message, TIMENOW, (int)($CURUSER['id'] ?? 0), $fromemail, (int)$to_user['id'], $to_user['email'], 0, $session->packedip, 1]
            );
        }

        $plugins->run_hooks('member_do_emailuser_end');
        redirect(get_profile_link((int)$to_user['id']), $lang->member['redirect_emailsent']);
    }
    $mybb->input['action'] = 'emailuser';
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: emailuser
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'emailuser') {
    $plugins->run_hooks('member_emailuser_start');

    if ((int)$usergroups['cansendemail'] === 0) { print_no_permission(); }

    $query   = $db->sql_query_prepared("SELECT id, username, hideemail, ignorelist FROM users WHERE id = ?", [$mybb->get_input('id', MyBB::INPUT_INT)]);
    $to_user = $query ? $db->fetch_array($query) : null;

    if (empty($to_user['id']))            { stderr($lang->member['error_invaliduser']); }
    if ((int)$to_user['hideemail'] !== 0) { stderr($lang->member['error_hideemail']); }
    if ($to_user['ignorelist'] && str_contains(',' . $to_user['ignorelist'] . ',', ',' . (int)($CURUSER['id'] ?? 0) . ',')
        && (int)$usergroups['cansendemailoverride'] !== 1) {
        print_no_permission();
    }

    // Values are re-shown only after a failed submit; escaped once, here
    $fromname  = $errors ? htmlspecialchars_uni((string)$mybb->get_input('fromname')) : '';
    $fromemail = $errors ? htmlspecialchars_uni((string)$mybb->get_input('fromemail')) : '';
    $subject   = $errors ? htmlspecialchars_uni((string)$mybb->get_input('subject')) : '';
    $message   = $errors ? htmlspecialchars_uni((string)$mybb->get_input('message')) : '';
    $to_name   = htmlspecialchars_uni((string)$to_user['username']);

    $plugins->run_hooks('member_emailuser_end');

    mr_open($lang->member['nav_emailuser'], 'auth');
    echo mr_head('fa-envelope', 'primary', sprintf(hsafe($lang->member['email_user']), $to_name), hsafe(mr_l('hint_emailuser', 'The message will be sent from your account email')),
        '<a href="' . get_profile_link((int)$to_user['id']) . '" class="btn btn-outline-secondary st-pill mr-btn"><i class="fa-solid fa-user" aria-hidden="true"></i><span class="mr-hide-sm">' . hsafe(sprintf($lang->member['profile'], (string)$to_user['username'])) . '</span></a>');
    echo mr_errors($errors);
    ?>
    <form action="member.php" method="post" class="st-card mr-form">
        <input type="hidden" name="my_post_key" value="<?= hsafe($mybb->post_code) ?>">
        <input type="hidden" name="action" value="do_emailuser">
        <input type="hidden" name="id" value="<?= (int)$to_user['id'] ?>">
        <div class="st-card__body">
            <?php if (empty($CURUSER['id'])): ?>
                <?= mr_input('fromname', hsafe(mr_lbl($lang->member['your_name'])), 'fa-user', $fromname, 'text', 'required') ?>
                <?= mr_input('fromemail', hsafe(mr_lbl($lang->member['your_email'])), 'fa-at', $fromemail, 'email', 'required') ?>
            <?php endif; ?>
            <?= mr_input('subject', hsafe($lang->member['email_subject']), 'fa-heading', $subject, 'text', 'maxlength="200" required') ?>
            <div class="mr-field">
                <label class="form-label" for="message"><i class="fa-solid fa-align-left" aria-hidden="true"></i><?= hsafe($lang->member['email_message']) ?></label>
                <textarea class="form-control" name="message" id="message" rows="10" required><?= $message ?></textarea>
            </div>
        </div>
        <div class="mr-form-foot">
            <a href="<?= get_profile_link((int)$to_user['id']) ?>" class="mr-back"><i class="fa-solid fa-xmark me-1" aria-hidden="true"></i><?= hsafe($lang->member['cans']) ?></a>
            <button type="submit" class="btn btn-primary st-pill mr-btn"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><?= hsafe($lang->member['send_email']) ?></button>
        </div>
    </form>
    <?php
    mr_close(true);
    exit;
}

// ── Default redirect ───────────────────────────────────────────────────────
header('Location: index.php');
exit;
