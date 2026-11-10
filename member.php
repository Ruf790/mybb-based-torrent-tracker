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
require_once INC_PATH . '/functions_profile_extra.php';
require_once INC_PATH . '/functions_seedbonus_estimate.php';
require_once INC_PATH . '/functions_loginattemptcheck.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_additions.php';

$parser = new postParser();

// ── Helpers ────────────────────────────────────────────────────────────────

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

function rt_cat_fa(?string $iconRaw, string $title = ''): string
{
    $cls = preg_replace('/[^a-z0-9\-\s]/i', '', (string)$iconRaw) ?: 'fa-solid fa-question';
    return '<i class="' . hsafe($cls) . '" title="' . hsafe($title) . '" aria-hidden="true"></i>';
}

// ── Avatar upload ──────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'upload_avatar') {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $json = static function (array $arr, int $code = 200) use ($is_ajax): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (!$CURUSER) {
        $is_ajax ? $json(['ok' => false, 'error' => $lang->member['err_not_logged_in']], 401) : exit(hsafe($lang->member['err_not_logged_in']));
    }

    $user_uid = (int)($CURUSER['id'] ?? 0);
    if ($user_uid <= 0) {
        $is_ajax ? $json(['ok' => false, 'error' => $lang->member['err_not_logged_in']], 401) : exit(hsafe($lang->member['err_not_logged_in']));
    }

    $uid = (int)($_POST['id'] ?? $_GET['id'] ?? $memprofile['id'] ?? 0);
    if ($uid <= 0) {
        $is_ajax ? $json(['ok' => false, 'error' => $lang->member['err_no_profile_uid']], 400) : exit(hsafe($lang->member['err_no_profile_uid']));
    }

    if ($user_uid !== $uid && !is_mod($usergroups)) {
        $is_ajax ? $json(['ok' => false, 'error' => $lang->member['err_avatar_no_perm']], 403) : exit(hsafe($lang->member['err_avatar_no_perm']));
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $is_ajax ? $json(['ok' => false, 'error' => $lang->member['err_avatar_no_file']], 400) : exit(hsafe($lang->member['err_avatar_no_file']));
    }

    // Вся валидация (расширение, реальный MIME через getimagesize(),
    // размер по настройке avatarsize), сохранение и перекодирование через
    // GD (защита от "полиглот"-файлов) теперь делает upload_avatar() —
    // та же функция, что использует usercp.php. Раньше здесь была
    // отдельная копия той же логики без перекодирования.
    $avatarResult = upload_avatar($_FILES['avatar'], $uid);

    if (!empty($avatarResult['error'])) {
        $is_ajax
            ? $json(['ok' => false, 'error' => $avatarResult['error']], 415)
            : exit(hsafe(ags_fmt($lang->member['err_avatar_failed'], (string)$avatarResult['error'])));
    }

    $width  = (int)$avatarResult['width'];
    $height = (int)$avatarResult['height'];
    $avatar_dimensions = ($width > 0 && $height > 0) ? $width . '|' . $height : '';
    $avatar_url        = $avatarResult['avatar'];

    $db->sql_query_prepared(
        "UPDATE users SET avatar = ?, avatardimensions = ?, avatartype = ? WHERE id = ?",
        [$avatar_url, $avatar_dimensions, 'upload', (int)$uid]
    );

    if ($is_ajax) {
        $json(['ok' => true, 'url' => $avatar_url, 'width' => $width, 'height' => $height, 'message' => $lang->member['msg_avatar_updated']]);
    }

    header("Location: member.php?action=profile&uid={$uid}");
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

// ── Auth pages UI (login, register, activation, lost / reset password, 2FA) ──
// st-ui.css + auth.css (.au-page). Labels from the lang file may end with ':' and may hold HTML (abbr).

/** Centered card: gradient head with an icon, body, optional footer. $wide = registration form */
function au_page(string $icon, string $tone, string $title, string $sub, string $body, string $foot = '', bool $wide = false): string
{
    global $BASEURL;
    return '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=12">'
        . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/auth.css?ver=2">'
        . '<div class="container-md st-page au-page' . ($wide ? ' au-page--wide' : '') . ' py-4">'
        .   '<div class="st-card au-card">'
        .     '<div class="au-head">'
        .       '<span class="au-head__ico st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
        .       '<div class="min-w-0"><h1 class="st-title">' . $title . '</h1>' . ($sub !== '' ? '<div class="st-meta mt-1">' . $sub . '</div>' : '') . '</div>'
        .     '</div>'
        .     '<div class="au-body">' . $body . '</div>'
        .     ($foot !== '' ? '<div class="au-foot">' . $foot . '</div>' : '')
        .   '</div>'
        . '</div>';
}

/** Label text without the trailing colon (lang strings are "Username:") */
function au_label(string $text): string
{
    return rtrim(trim($text), ':');
}

/** Input with an icon inside. .field-wrap: regvalidator.js puts its messages there */
function au_field(string $id, string $name, string $label, string $icon, string $type = 'text', string $value = '', string $attrs = '', string $extra = ''): string
{
    return '<div class="au-field field-wrap">'
        . '<label class="st-label" for="' . $id . '">' . au_label($label) . '</label>'
        . '<div class="au-input"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i>'
        . '<input class="form-control" type="' . $type . '" id="' . $id . '" name="' . $name . '" value="' . $value . '" ' . $attrs . '></div>'
        . $extra
        . '</div>';
}

/** <select> with an icon inside; $options = ready <option> HTML */
function au_select(string $id, string $name, string $label, string $icon, string $options, string $hint = ''): string
{
    return '<div class="au-field">'
        . '<label class="st-label" for="' . $id . '">' . au_label($label) . '</label>'
        . '<div class="au-input"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i>'
        . '<select class="form-select" id="' . $id . '" name="' . $name . '">' . $options . '</select></div>'
        . ($hint !== '' ? '<div class="st-meta mt-1">' . $hint . '</div>' : '')
        . '</div>';
}

/** Big submit button */
function au_submit(string $icon, string $label, string $name = 'submit'): string
{
    return '<button type="submit" class="btn btn-primary st-pill au-submit" name="' . $name . '" value="' . hsafe(strip_tags($label)) . '">'
        . '<i class="fa-solid ' . $icon . ' me-2"></i>' . $label . '</button>';
}

/** Footer links of the auth pages: [[href, icon, text], ...] */
function au_links(array $links): string
{
    $out = '<div class="au-links">';
    foreach ($links as [$href, $icon, $text]) {
        $out .= '<a href="' . $href . '"><i class="fa-solid ' . $icon . '"></i>' . $text . '</a>';
    }
    return $out . '</div>';
}

// ── Active swarm ───────────────────────────────────────────────────────────
function build_user_active_swarm(object $db, int $uid, int $limit = 10): array
{
    global $BASEURL, $dateformat, $timeformat, $lang;

    $uid   = max(0, $uid);
    $limit = max(1, $limit);

    $render = static function (string $seeder_val) use ($db, $uid, $limit, $BASEURL, $dateformat, $timeformat, $lang): array {
        $sql = "
            SELECT t.id, t.name, t.size, t.category, t.t_image, t.t_link,
                   c.name AS cat_name, c.icon AS cat_icon,
                   p.uploaded, p.downloaded, p.to_go, p.last_action, p.seeder
            FROM peers p
            JOIN torrents t ON t.id = p.torrent
            LEFT JOIN categories c ON c.id = t.category
            WHERE p.userid = ? AND p.seeder = ?
            ORDER BY p.last_action DESC
            LIMIT ?
        ";
        $res = $db->sql_query_prepared($sql, [$uid, $seeder_val, $limit]);

        if (!$res || $db->num_rows($res) === 0) {
            $icon = $seeder_val === 'yes' ? 'cloud-upload' : 'cloud-download';
            return ['html' => '<div class="text-center py-4">
                <div class="d-inline-flex flex-column align-items-center gap-2">
                    <i class="bi bi-' . $icon . ' fs-1 text-secondary opacity-25"></i>
                    <div class="text-muted small">' . hsafe($lang->member['empty_active']) . '</div>
                </div>
            </div>', 'count' => 0];
        }

        $html = '<div class="d-flex flex-column gap-2">';
        $cnt  = 0;

        while ($r = $db->fetch_array($res)) {
            $cnt++;
            $id   = (int)$r['id'];
            $name = (string)($r['name'] ?? '');
            $cat  = (string)($r['cat_name'] ?? '');
            $icon = rt_cat_fa($r['cat_icon'] ?? '', $cat);
            $link = $BASEURL . '/torrent-' . $id . '.html';
            $up   = mksize((int)($r['uploaded']   ?? 0));
            $dn   = mksize((int)($r['downloaded'] ?? 0));
            $seen = my_datee($dateformat, (int)($r['last_action'] ?? 0))
                  . ' ' . my_datee($timeformat, (int)($r['last_action'] ?? 0));

            $size     = max(1, (int)($r['size'] ?? 1));
            $to_go    = (int)($r['to_go'] ?? 0);
            $progress = $seeder_val === 'yes'
                ? 100
                : min(100, max(0, (int)round((1 - $to_go / $size) * 100)));
            $progress_color = $seeder_val === 'yes' ? 'success' : 'info';

            $upspeed   = (int)($r['upspeed']   ?? 0);
            $downspeed = (int)($r['downspeed'] ?? 0);
            $speed_html = '';
            if ($upspeed > 0 || $downspeed > 0) {
                $speed_html = '<div class="d-flex gap-2 mt-1">'
                    . ($upspeed > 0 ? '<span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size:0.65rem;"><i class="bi bi-arrow-up me-1"></i>' . mksize($upspeed) . '/s</span>' : '')
                    . ($downspeed > 0 ? '<span class="badge bg-info bg-opacity-10 text-info rounded-pill" style="font-size:0.65rem;"><i class="bi bi-arrow-down me-1"></i>' . mksize($downspeed) . '/s</span>' : '')
                    . '</div>';
            }

            $imdb_badge = '';
            if (!empty($r['t_link']) && preg_match('#(\d+\.?\d*)/10#', $r['t_link'], $m)) {
                $imdb_badge = '<span class="badge ms-1" style="background:#f5c518;color:#000;font-size:0.6rem;"><i class="bi bi-star-fill me-1"></i>' . $m[1] . '</span>';
            }

            $poster = !empty($r['t_image']) ? htmlspecialchars($r['t_image']) : '';

            $eta_html = '';
            if ($seeder_val === 'no' && $to_go > 0 && $downspeed > 0) {
                $eta_secs = (int)($to_go / $downspeed);
                $eta_html = '<span class="badge bg-warning bg-opacity-10 text-warning rounded-pill" style="font-size:0.65rem;"><i class="bi bi-stopwatch me-1"></i>' . mkprettytime($eta_secs) . '</span>';
            }

            $poster_html = $poster
                ? '<a href="' . $link . '" class="flex-shrink-0"><img src="' . $poster . '" style="width:70px;min-height:85px;object-fit:cover;background:#111;" alt="' . hsafe($name) . '" onerror="this.parentElement.style.display=\'none\'"></a>'
                : '<a href="' . $link . '" class="flex-shrink-0 d-flex align-items-center justify-content-center bg-light" style="width:70px;min-height:85px;"><span class="fs-3">' . $icon . '</span></a>';

            $html .= '
            <div class="card border-0 shadow-sm overflow-hidden hov-scale">
                <div class="d-flex">
                    ' . $poster_html . '
                    <div class="card-body p-2 flex-grow-1 min-width-0">
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <span class="badge bg-light text-dark border" style="font-size:0.6rem;">' . $icon . ' ' . hsafe($cat) . '</span>
                            ' . $imdb_badge . '
                        </div>
                        <a href="' . $link . '" class="text-decoration-none">
                            <div class="fw-semibold text-dark clamp-2" style="font-size:0.82rem;">' . htmlspecialchars_uni($name) . '</div>
                        </a>
                        <div class="mt-2 mb-1">
                            <div class="d-flex justify-content-between mb-1" style="font-size:0.7rem;">
                                <span class="text-muted">' . hsafe($lang->member['lbl_progress']) . '</span>
                                <span class="fw-bold text-' . $progress_color . '">' . $progress . '%</span>
                            </div>
                            <div class="progress" style="height:4px;">
                                <div class="progress-bar bg-' . $progress_color . '" style="width:' . $progress . '%"></div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size:0.65rem;"><i class="bi bi-arrow-up-circle-fill me-1"></i>' . $up . '</span>
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill" style="font-size:0.65rem;"><i class="bi bi-arrow-down-circle-fill me-1"></i>' . $dn . '</span>
                            ' . $eta_html . '
                        </div>
                        ' . $speed_html . '
                        <div class="text-muted mt-1" style="font-size:0.68rem;"><i class="bi bi-clock me-1"></i>' . $seen . '</div>
                    </div>
                </div>
            </div>';
        }

        $html .= '</div>';
        return ['html' => $html, 'count' => $cnt];
    };

    $seed  = $render('yes');
    $leech = $render('no');

    return [
        'seed_html'   => $seed['html'],
        'leech_html'  => $leech['html'],
        'seed_count'  => $seed['count'],
        'leech_count' => $leech['count'],
    ];
}

// ── Recent uploads ─────────────────────────────────────────────────────────
function build_recent_user_torrents(object $db, int $uid): string
{
    global $BASEURL, $dateformat, $lang;

    $uid = max(0, $uid);
    $sql = "
        SELECT t.id, t.name, t.size, t.added, t.seeders, t.leechers, t.times_completed,
               t.t_image, t.t_link, t.category, c.name AS cat_name, c.icon AS cat_icon
        FROM torrents t
        LEFT JOIN categories c ON c.id = t.category
        WHERE t.owner = ?
          AND (t.visible = 'yes' OR t.visible = 1 OR t.visible IS NULL)
          AND (t.banned  = 'no'  OR t.banned  = 0  OR t.banned  IS NULL)
        ORDER BY t.added DESC
        LIMIT 12
    ";

    $res = $db->sql_query_prepared($sql, [$uid]);
    if (!$res || $db->num_rows($res) === 0) {
        return '<div class="text-center p-4">
            <div class="d-inline-flex flex-column align-items-center gap-3">
                <div class="bg-light rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;">
                    <i class="bi bi-cloud-arrow-up fs-1 text-secondary"></i>
                </div>
                <div class="text-secondary fw-medium">' . hsafe($lang->member['empty_uploads']) . '</div>
            </div>
        </div>';
    }

    $html = '<div class="row g-3">';
    while ($r = $db->fetch_array($res)) {
        $id    = (int)$r['id'];
        $name  = (string)($r['name'] ?? '');
        $size  = mksize((int)$r['size']);
        $added = my_datee($dateformat, (int)$r['added']);
        $seed  = ts_nf((int)$r['seeders']);
        $leech = ts_nf((int)$r['leechers']);
        $done  = ts_nf((int)$r['times_completed']);
        $link  = $BASEURL . '/torrent-' . $id . '.html';

        $catName   = (string)($r['cat_name'] ?? '');
        $iconClass = preg_replace('/[^a-z0-9\-\s]/i', '', trim((string)($r['cat_icon'] ?? ''))) ?: 'fa-solid fa-question';
        $catIcon   = '<i class="' . hsafe($iconClass) . '" title="' . hsafe($catName) . '" aria-hidden="true"></i>';
        $poster    = !empty($r['t_image']) ? htmlspecialchars($r['t_image']) : '';

        $imdb_badge = '';
        if (!empty($r['t_link']) && preg_match('#(\d+\.?\d*)/10#', $r['t_link'], $m)) {
            $imdb_badge = '<span class="badge ms-1" style="background:#f5c518;color:#000;font-size:0.7rem;"><i class="bi bi-star-fill me-1"></i>' . $m[1] . '</span>';
        }

        $poster_html = $poster
            ? '<a href="' . $link . '" class="flex-shrink-0"><img src="' . $poster . '" style="width:75px;height:90%;object-fit:cover;min-height:90px;background:#111;" alt="' . hsafe($name) . '" onerror="this.parentElement.style.display=\'none\'"></a>'
            : '';

        $html .= '
        <div class="col-12">
            <div class="card border-0 shadow-sm hov-scale overflow-hidden">
                <div class="d-flex">
                    ' . $poster_html . '
                    <div class="card-body p-3 flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-width-0">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-light text-dark border" style="font-size:0.7rem;">' . $catIcon . ' ' . hsafe($catName) . '</span>
                                    ' . $imdb_badge . '
                                </div>
                                <a href="' . $link . '" class="text-decoration-none">
                                    <h6 class="fw-semibold text-dark mb-1 clamp-2">' . htmlspecialchars_uni($name) . '</h6>
                                </a>
                                <div class="small text-muted d-flex flex-wrap gap-2 mb-2">
                                    <span><i class="bi bi-hdd me-1"></i>' . hsafe($size) . '</span>
                                    <span><i class="bi bi-calendar3 me-1"></i>' . hsafe($added) . '</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill"><i class="bi bi-arrow-up-circle-fill me-1"></i>' . $seed . '</span>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill"><i class="bi bi-arrow-down-circle-fill me-1"></i>' . $leech . '</span>
                                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill"><i class="bi bi-check2-circle me-1"></i>' . $done . '</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="' . $BASEURL . '/download.php/' . $id . '.torrent" class="btn btn-primary btn-sm rounded-pill" title="' . hsafe($lang->member['tip_download']) . '">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    $html .= '</div>';
    return $html;
}

// ── Completed torrents ─────────────────────────────────────────────────────
function build_user_completed_torrents_from_snatched(object $db, int $uid, int $limit = 10): array
{
    global $BASEURL, $dateformat, $timeformat, $lang;

    $uid   = max(0, $uid);
    $limit = max(1, $limit);

    $rc    = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM snatched s LEFT JOIN torrents t ON (s.torrentid=t.id) INNER JOIN categories c ON (t.category=c.id) WHERE s.finished='yes' AND s.userid=?", [(int)$uid]);
    $rowc  = $rc ? $db->fetch_array($rc) : null;
    $total = (int)($rowc['cnt'] ?? 0);

    $sql = "
        SELECT s.torrentid AS id, s.uploaded, s.downloaded, s.completedat, s.last_action,
               t.seeders, t.leechers, t.name, t.category, t.t_image, t.t_link,
               c.name AS categoryname, c.icon AS caticon
        FROM snatched s
        LEFT JOIN torrents t ON (s.torrentid=t.id)
        INNER JOIN categories c ON (t.category=c.id)
        WHERE s.finished='yes' AND s.userid=?
        ORDER BY s.completedat DESC, s.last_action DESC
        LIMIT ?
    ";
    $res = $db->sql_query_prepared($sql, [(int)$uid, (int)$limit]);

    if (!$res || $db->num_rows($res) === 0) {
        return ['html' => '
        <div class="text-center py-4">
            <div class="d-inline-flex flex-column align-items-center gap-3">
                <div class="bg-light rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;">
                    <i class="bi bi-check2-circle fs-1 text-secondary opacity-50"></i>
                </div>
                <div class="text-secondary fw-medium">' . hsafe($lang->member['empty_completed']) . '</div>
                <div class="small text-muted">' . hsafe($lang->member['hint_empty_completed']) . '</div>
            </div>
        </div>', 'count' => $total];
    }

    $html = '<div class="d-flex flex-column gap-2">';
    while ($r = $db->fetch_array($res)) {
        $id   = (int)($r['id']   ?? 0);
        $name = (string)($r['name'] ?? '');
        $cat  = (string)($r['categoryname'] ?? '');
        $icon = rt_cat_fa($r['caticon'] ?? '', $cat);
        $link = $BASEURL . '/torrent-' . $id . '.html';
        $when = my_datee($dateformat, $r['completedat']) . ' ' . my_datee($timeformat, $r['completedat']);
        $up   = mksize((int)($r['uploaded']   ?? 0));
        $dn   = mksize((int)($r['downloaded'] ?? 0));
        $seed = ts_nf((int)($r['seeders']  ?? 0));
        $lee  = ts_nf((int)($r['leechers'] ?? 0));

        $dl = (int)($r['downloaded'] ?? 0);
        $ul = (int)($r['uploaded']   ?? 0);
        if ($dl > 0) {
            $ratio_val   = $ul / $dl;
            $ratio_str   = number_format($ratio_val, 2);
            $ratio_color = $ratio_val >= 1.0 ? 'success' : ($ratio_val >= 0.5 ? 'warning' : 'danger');
        } else {
            $ratio_str   = $ul > 0 ? '∞' : '0.00';
            $ratio_color = $ul > 0 ? 'success' : 'secondary';
        }

        $imdb_badge = '';
        if (!empty($r['t_link']) && preg_match('#(\d+\.?\d*)/10#', $r['t_link'], $m)) {
            $imdb_badge = '<span class="badge ms-1" style="background:#f5c518;color:#000;font-size:0.65rem;"><i class="bi bi-star-fill me-1"></i>' . $m[1] . '</span>';
        }

        $poster       = !empty($r['t_image']) ? htmlspecialchars($r['t_image']) : '';
        $health_color = (int)$r['seeders'] > 0 ? 'success' : 'danger';
        $health_icon  = (int)$r['seeders'] > 0 ? 'bi-wifi' : 'bi-wifi-off';

        $poster_html = $poster
            ? '<a href="' . $link . '" class="flex-shrink-0"><img src="' . $poster . '" style="width:75px;min-height:90px;object-fit:cover;background:#111;" alt="' . hsafe($name) . '" onerror="this.parentElement.style.display=\'none\'"></a>'
            : '<a href="' . $link . '" class="flex-shrink-0 d-flex align-items-center justify-content-center bg-light" style="width:75px;min-height:90px;"><span class="fs-3">' . $icon . '</span></a>';

        $html .= '
        <div class="card border-0 shadow-sm overflow-hidden hov-scale">
            <div class="d-flex">
                ' . $poster_html . '
                <div class="card-body p-2 flex-grow-1 min-width-0">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge bg-light text-dark border" style="font-size:0.65rem;">' . $icon . ' ' . hsafe($cat) . '</span>
                        ' . $imdb_badge . '
                        <span class="badge bg-' . $health_color . ' bg-opacity-10 text-' . $health_color . ' ms-auto" style="font-size:0.65rem;"><i class="bi ' . $health_icon . ' me-1"></i>' . $seed . '/' . $lee . '</span>
                    </div>
                    <a href="' . $link . '" class="text-decoration-none">
                        <div class="fw-semibold text-dark clamp-2" style="font-size:0.85rem;">' . htmlspecialchars_uni($name) . '</div>
                    </a>
                    <div class="text-muted mt-1" style="font-size:0.72rem;"><i class="bi bi-calendar-check me-1"></i>' . $when . '</div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size:0.65rem;"><i class="bi bi-arrow-up-circle-fill me-1"></i>' . $up . '</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill" style="font-size:0.65rem;"><i class="bi bi-arrow-down-circle-fill me-1"></i>' . $dn . '</span>
                        <span class="badge bg-' . $ratio_color . ' bg-opacity-10 text-' . $ratio_color . ' rounded-pill" style="font-size:0.65rem;"><i class="bi bi-percent me-1"></i>' . $ratio_str . '</span>
                    </div>
                </div>
            </div>
        </div>';
    }

    $html .= '</div>';
    return ['html' => $html, 'count' => $total];
}

// ══════════════════════════════════════════════════════════════════════════

$mybb->input['action'] = $mybb->get_input('action');

// Breadcrumb
$breadcrumb_map = [
    'register'        => $lang->member['nav_register'],
    'do_register'     => $lang->member['nav_register'],
    'activate'        => $lang->member['nav_activate'],
    'resendactivation'=> $lang->member['nav_resendactivation'],
    'lostpw'          => $lang->member['nav_lostpw'],
    'resetpassword'   => $lang->member['nav_resetpassword'],
    'login'           => $lang->member['nav_login'],
    'emailuser'       => $lang->member['nav_emailuser'],
];
if (isset($breadcrumb_map[$mybb->input['action']])) {
    add_breadcrumb($breadcrumb_map[$mybb->input['action']]);
}

// ── Registration guard ─────────────────────────────────────────────────────
//if (in_array($mybb->input['action'], ['register', 'do_register'], true) && $mybb->usergroup['cancp'] != 1) {
if (in_array($mybb->input['action'], ['register', 'do_register'], true)) {	
	
    if ($disableregs == 1) { stderr($lang->member['registrations_disabled']); }
	

    if ((int)$maxusers > 0) {
        $count_q = $db->sql_query_prepared('SELECT id FROM users WHERE id > 0');
        $count   = $count_q ? $db->num_rows($count_q) : 0;
        if ($maxusers <= $count) { stderr($lang->global['signuplimitreached']); }
    }

    if (($CURUSER['id'] ?? 0) != 0) {
        stderr($lang->member['error_alreadyregistered']);
    }

    if ($betweenregstime && $maxregsbetweentime) {
        $datecut = TIMENOW - (60 * 60 * $betweenregstime);
        $query   = $db->sql_query_prepared("SELECT * FROM users WHERE regip = ? AND added > ?", [$session->packedip, $datecut]);
        $regcount = $query ? $db->num_rows($query) : 0;
        if ($regcount >= $maxregsbetweentime) {
            stderr(sprintf($lang->member['error_alreadyregisteredtime'], $regcount, $betweenregstime));
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_register
// ══════════════════════════════════════════════════════════════════════════
$fromreg = 0;
if ($mybb->input['action'] === 'do_register' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_register_start');

    $usergroup = $regtype === 'verify' ? 5 : 2;

    require_once INC_PATH . '/datahandlers/user.php';
    $userhandler = new UserDataHandler('insert');

    

    $user = [
        'username'       => $mybb->get_input('username'),
        'password'       => $mybb->get_input('password'),
        'password2'      => $mybb->get_input('password2'),
        'invitehash'     => $mybb->get_input('invitehash'),
        'email'          => $mybb->get_input('email'),
        'email2'         => $mybb->get_input('email2'),
        'usergroup'      => $usergroup,
        'referrer'       => $mybb->get_input('referrername'),
        'timezone'       => $mybb->get_input('timezoneoffset'),
        'language'       => $mybb->get_input('language'),
        'regip'          => $session->packedip,
        'regcheck1'      => $mybb->get_input('regcheck1'),
        'regcheck2'      => $mybb->get_input('regcheck2'),
        'registration'   => true,
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

    $userhandler->set_data($user);
    $errors = [];

    if (!$userhandler->validate_user()) {
        $errors = $userhandler->get_friendly_errors();
    }

    $regerrors = '';
    if (!empty($errors)) {
        $username     = htmlspecialchars_uni($mybb->get_input('username'));
        $email        = htmlspecialchars_uni($mybb->get_input('email'));
        $email2       = htmlspecialchars_uni($mybb->get_input('email2'));
        $referrername = htmlspecialchars_uni($mybb->get_input('referrername'));

        $allownoticescheck = $hideemailcheck = $receivepmscheck = $pmnoticecheck = $pmnotifycheck = $invisiblecheck = '';
        $no_auto_subscribe_selected = $instant_email_subscribe_selected = $instant_pm_subscribe_selected = $no_subscribe_selected = '';
        $dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';

        if ($mybb->get_input('allownotices', MyBB::INPUT_INT) == 1)     { $allownoticescheck = 'checked="checked"'; }
        if ($mybb->get_input('hideemail',    MyBB::INPUT_INT) == 1)     { $hideemailcheck    = 'checked="checked"'; }
        if ($mybb->get_input('receivepms',   MyBB::INPUT_INT) == 1)     { $receivepmscheck   = 'checked="checked"'; }
        if ($mybb->get_input('pmnotice',     MyBB::INPUT_INT) == 1)     { $pmnoticecheck     = ' checked="checked"'; }
        if ($mybb->get_input('pmnotify',     MyBB::INPUT_INT) == 1)     { $pmnotifycheck     = 'checked="checked"'; }
        if ($mybb->get_input('invisible',    MyBB::INPUT_INT) == 1)     { $invisiblecheck    = 'checked="checked"'; }

        match ($mybb->get_input('subscriptionmethod', MyBB::INPUT_INT)) {
            1       => $no_subscribe_selected            = 'selected="selected"',
            2       => $instant_email_subscribe_selected = 'selected="selected"',
            3       => $instant_pm_subscribe_selected    = 'selected="selected"',
            default => $no_auto_subscribe_selected       = 'selected="selected"',
        };

        match ($mybb->get_input('dstcorrection', MyBB::INPUT_INT)) {
            2       => $dst_auto_selected     = 'selected="selected"',
            1       => $dst_enabled_selected  = 'selected="selected"',
            default => $dst_disabled_selected = 'selected="selected"',
        };

        $regerrors              = inline_error($errors);
        $mybb->input['action']  = 'register';
        $fromreg                = 1;
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
            
			 
			stdok(
              message: sprintf($lang->member['redirect_registered_activation'], $SITENAME, htmlspecialchars_uni($user_info['username'])),
              title:   $lang->member['sec_reg_success'],
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
   

    if (false) 
	{
    // agreement removed
    } else {
		
		
        $plugins->run_hooks('member_register_start');

        $js_validator_username_length = '';
        if ($maxnamelength > 0 && $minnamelength > 0) {
            $js_validator_username_length = sprintf($lang->member['js_validator_username_length'], $minnamelength, $maxnamelength);
        }

        $timezoneoffset = $mybb->input['timezoneoffset'] ?? $timezoneoffset;
        $tzselect       = build_timezone_select('timezoneoffset', $timezoneoffset, true);

        // Language: re-posted value → ts_language cookie → site default
        $reg_languages = [
            'english' => 'English',
            'russian' => 'Русский',
        ];
        $reg_lang = (string)($mybb->input['language'] ?? $_COOKIE['ts_language'] ?? $defaultlanguage ?? 'english');
        if (!isset($reg_languages[$reg_lang])) {
            $reg_lang = 'english';
        }
        $langoptions = '';
        foreach ($reg_languages as $lang_key => $lang_title) {
            if (!$lang->language_exists($lang_key)) {
                continue;
            }
            $sel = $lang_key === $reg_lang ? ' selected="selected"' : '';
            $langoptions .= '<option value="' . $lang_key . '"' . $sel . '>' . hsafe($lang_title) . '</option>';
        }

        $tppselect = $pppselect = '';
        if ($usertppoptions) {
            $tppoptions = '';
            foreach (array_map('trim', explode(',', $usertppoptions)) as $val) {
                $tpp_option = sprintf($lang->member['tpp_option'], $val);
                
				//$tppoptions .= '<option value="'.$val.'"'.$selected.'>'.$tpp_option.'</option>';
				$tppoptions .= '<option value="'.$val.'">'.$tpp_option.'</option>';
            }
            
			$tppselect = '<div class="mb-2 pb-3">
	<label for="tpp">'.$lang->member['tpp'].'</label>
<select name="tpp" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->member['lang_select_default'].'</option>
'.$tppoptions.'
</select>
</div>';
			
			
        }
        if ($userpppoptions) {
            $pppoptions = '';
            foreach (array_map('trim', explode(',', $userpppoptions)) as $val) {
                $ppp_option = sprintf($lang->member['ppp_option'], $val);
                
				//$pppoptions .= '<option value="'.$val.'"'.$selected.'>'.$ppp_option.'</option>';
				$pppoptions .= '<option value="'.$val.'">'.$ppp_option.'</option>';
				
            }
            
			$pppselect = '<div class="mb-2 pb-3">
	<label for="ppp">'.$lang->member['ppp'].'</label>
<select name="ppp" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->member['lang_select_default'].'</option>
'.$pppoptions.'
</select>
</div>';

        }

       
        $altbg          = 'trow1';
        $usergroup = $regtype === 'verify' ? 5 : 2;
       
        $jsvar_reqfields = [];


        if (!$fromreg) {
            $allownoticescheck = 'checked="checked"';
            $hideemailcheck = $invisiblecheck = $pmnotifycheck = '';
            $receivepmscheck = $pmnoticecheck = 'checked="checked"';
            $no_auto_subscribe_selected = $instant_email_subscribe_selected = $instant_pm_subscribe_selected = $no_subscribe_selected = '';
            $dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';
            $username = $email = $email2 = $regerrors = '';
        }

        

        $passboxes = '';
        $js_validator_password_length = sprintf($lang->member['js_validator_password_length'], $minpasswordlength);
        if ($requirecomplexpasswords == 1) {
            $lang->member['password'] = $lang->member['complex_password'] = sprintf($lang->member['complex_password'], $minpasswordlength);
        }
        
			$passboxes = '<div class="au-grid">'
                . au_field('password', 'password', $lang->member['password'], 'fa-lock', 'password', '', 'autocomplete="new-password" required')
                . au_field('password2', 'password2', $lang->member['confirm_password'], 'fa-lock', 'password', '', 'autocomplete="new-password" required')
                . '</div><div style="display: none" id="password_status">&nbsp;</div>';

        $invitehash = htmlspecialchars_uni(
            $_POST['invitehash'] ?? 
            $_GET['invitehash'] ?? 
            $_GET['invite'] ?? 
            $_POST['invite'] ?? 
            ''
        );
		
        $showinvitecode = '';
        if ($regtype === 'invite') {
            $showinvitecode = au_field('invitehash', 'invitehash', $lang->member['invitecode'], 'fa-ticket', 'text', $invitehash, 'autocomplete="off"');
        }

        $time            = TIMENOW;
       

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
            'js_rv_username_ok'                  => $lang->member['js_rv_username_ok'] ?? 'Username is available',
            'js_rv_username_taken'               => $lang->member['js_rv_username_taken'] ?? 'This username is already taken',
            'js_rv_email_ok'                     => $lang->member['js_rv_email_ok'] ?? 'Email address is valid',
            'js_rv_email_taken'                  => $lang->member['js_rv_email_taken'] ?? 'This email is already registered',
            'js_rv_email2_ok'                    => $lang->member['js_rv_email2_ok'] ?? 'Email addresses match',
            'js_rv_password_ok'                  => $lang->member['js_rv_password_ok'] ?? 'Password looks good',
            'js_rv_password2_ok'                 => $lang->member['js_rv_password2_ok'] ?? 'Passwords match',
            'js_rv_server_error'                 => $lang->member['js_rv_server_error'] ?? 'Server error. Please try again.',
            'js_rv_checking'                     => $lang->member['js_rv_checking'] ?? 'Checking…',
            'js_rv_fix_errors'                   => $lang->member['js_rv_fix_errors'] ?? 'Please fix the highlighted fields',
            'js_rv_caps'                         => $lang->member['js_rv_caps'] ?? 'Caps Lock is on',
            'js_rv_show'                         => $lang->member['js_rv_show'] ?? 'Show password',
            'js_rv_hide'                         => $lang->member['js_rv_hide'] ?? 'Hide password',
            'js_rv_strength'                     => $lang->member['js_rv_strength'] ?? 'Strength',
            'js_rv_s1'                           => $lang->member['js_rv_s1'] ?? 'weak',
            'js_rv_s2'                           => $lang->member['js_rv_s2'] ?? 'fair',
            'js_rv_s3'                           => $lang->member['js_rv_s3'] ?? 'good',
            'js_rv_s4'                           => $lang->member['js_rv_s4'] ?? 'strong',
        ];
        $js_json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        $validator_javascript = '<script type="text/javascript">
            var regsettings = {
                minnamelength: \'' . $minnamelength . '\',
                maxnamelength: \'' . $maxnamelength . '\',
                minpasswordlength: \'' . $minpasswordlength . '\',
                maxpasswordlength:       \'' . $maxpasswordlength . '\',
                requirecomplexpasswords: \'' . $requirecomplexpasswords . '\',
                regtype: \'' . $regtype . '\'
            };' . "\n";
        foreach ($js_validator_strings as $js_key => $js_val) {
            $validator_javascript .= '            lang.' . $js_key . ' = ' . json_encode((string)$js_val, $js_json_flags) . ";\n";
        }
        $validator_javascript .= '        </script>' . "\n";

        $plugins->run_hooks('member_register_end');
        
		
        // Timezone: keep only the <select> of build_timezone_select() (it comes wrapped with a hint)
        $tz_only = preg_match('~<select\b.*?</select>~s', $tzselect, $m) ? $m[0] : $tzselect;
        $tz_only = preg_replace('~class="[^"]*"~', 'class="form-select"', $tz_only, 1);

        $sw = static fn(string $name, string $check, string $icon, string $label): string =>
            '<label class="st-check" for="' . $name . '"><input class="form-check-input" type="checkbox" name="' . $name . '" id="' . $name . '" value="1" ' . $check . '>'
            . '<span><i class="fa-solid ' . $icon . ' me-1"></i>' . $label . '</span></label>';

        $reg_body = ($inline_errors ?? '') . ($member_loggedin_notice ?? '') . $regerrors
            . '<form action="member.php" method="post" id="registration_form">'
            . '<input type="text" style="display: none;" value="" name="regcheck1" />'
            . '<input type="text" style="display: none;" value="true" name="regcheck2" />'

            . '<div class="au-sec"><i class="fa-solid fa-user-circle"></i>' . au_label($lang->member['account_details']) . '</div>'
            . '<div class="au-grid">'
            .   au_field('username', 'username', $lang->member['username'], 'fa-user', 'text', $username, 'placeholder="' . hsafe($lang->member['ph_username']) . '" autocomplete="username" required')
            .   ($showinvitecode !== '' ? $showinvitecode : '<div class="d-none d-md-block"></div>')
            . '</div>'
            . $passboxes
            . '<div class="au-grid">'
            .   au_field('email', 'email', $lang->member['email'], 'fa-envelope', 'email', $email, 'maxlength="50" placeholder="' . hsafe($lang->member['ph_email']) . '" autocomplete="email" required')
            .   au_field('email2', 'email2', $lang->member['confirm_email'], 'fa-envelope-circle-check', 'email', $email2, 'maxlength="50" placeholder="' . hsafe($lang->member['ph_email2']) . '" required',
                    '<div style="display: none;" id="email_status">&nbsp;</div>')
            . '</div>'

            . '<div class="au-sec"><i class="fa-solid fa-sliders"></i>' . au_label($lang->member['account_prefs']) . '</div>'
            . '<div class="au-grid">'
            .   au_select('language', 'language', $lang->member['lbl_language'], 'fa-language', $langoptions, $lang->member['hint_language'])
            .   '<div class="au-field"><label class="st-label" for="timezoneoffset">' . au_label($lang->member['time_offset']) . '</label>'
            .     '<div class="au-input"><i class="fa-solid fa-globe" aria-hidden="true"></i>' . $tz_only . '</div></div>'
            .   au_select('dstcorrection', 'dstcorrection', $lang->member['dst_correction'], 'fa-clock',
                    '<option value="2" ' . $dst_auto_selected . '>' . $lang->member['dst_correction_auto'] . '</option>'
                  . '<option value="1" ' . $dst_enabled_selected . '>' . $lang->member['dst_correction_enabled'] . '</option>'
                  . '<option value="0" ' . $dst_disabled_selected . '>' . $lang->member['dst_correction_disabled'] . '</option>')
            .   au_select('subscriptionmethod', 'subscriptionmethod', $lang->member['subscription_method'], 'fa-rss',
                    '<option value="0" ' . $no_auto_subscribe_selected . '>' . $lang->member['no_auto_subscribe'] . '</option>'
                  . '<option value="1" ' . $no_subscribe_selected . '>' . $lang->member['no_subscribe'] . '</option>'
                  . '<option value="2" ' . $instant_email_subscribe_selected . '>' . $lang->member['instant_email_subscribe'] . '</option>'
                  . '<option value="3" ' . $instant_pm_subscribe_selected . '>' . $lang->member['instant_pm_subscribe'] . '</option>')
            . '</div>'

            . '<div class="au-sec"><i class="fa-solid fa-bell"></i>' . hsafe($lang->member['lbl_notifications']) . '</div>'
            . '<div class="au-checks">'
            .   $sw('allownotices', $allownoticescheck, 'fa-bell', $lang->member['allow_notices'])
            .   $sw('hideemail', $hideemailcheck, 'fa-eye-slash', $lang->member['hide_email'])
            .   $sw('receivepms', $receivepmscheck, 'fa-comments', $lang->member['receive_pms'])
            .   $sw('pmnotice', $pmnoticecheck, 'fa-desktop', $lang->member['pm_notice'])
            .   $sw('pmnotify', $pmnotifycheck, 'fa-envelope', $lang->member['email_notify_newpm'])
            .   $sw('invisible', $invisiblecheck, 'fa-user-secret', $lang->member['invisible_mode'])
            . '</div>'

            . '<input type="hidden" name="step" value="registration" />'
            . '<input type="hidden" name="action" value="do_register" />'
            . au_submit('fa-user-plus', $lang->member['submit_registration'], 'regsubmit')
            . '</form>';

        $reg_foot = au_links([
            ['member.php?action=login', 'fa-right-to-bracket', hsafe((string)($lang->member['lnk_have_account'] ?? 'Already have an account? Sign in'))],
        ]);

        $registration = au_page('fa-user-plus', 'success', $lang->member['welcome_register'],
                hsafe((string)($lang->member['hint_register'] ?? 'Free account: takes a minute')), $reg_body, $reg_foot, true)
            . $validator_javascript
            . '<script type="text/javascript" src="' . $BASEURL . '/scripts/regvalidator.js?ver=1824"></script>';

        stdhead();
        build_breadcrumb();
        echo $registration;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: activate
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'activate') {
    $plugins->run_hooks('member_activate_start');

    if (isset($mybb->input['username'])) {
        $user = get_user_by_username($mybb->get_input('username'), ['fields' => '*']);
        if (!$user) { stderr($lang->member['error_invalidpworusername']); }
        $uid = $user['id'];
    } else {
        $user = get_user($mybb->get_input('id', MyBB::INPUT_INT));
    }

    if (isset($mybb->input['code']) && $user) {
        $query      = $db->sql_query_prepared("SELECT * FROM awaitingactivation WHERE uid = ? AND (type='r' OR type='e' OR type='b')", [$user['id']]);
        $activation = $query ? $db->fetch_array($query) : null;

        if (!$activation)                                       { stderr($lang->member['error_alreadyactivated']); }
        if ($activation['code'] !== $mybb->get_input('code'))  { stderr($lang->member['error_badactivationcode']); }
        if ($activation['type'] === 'b' && $activation['validated'] == 1) { stderr($lang->member['error_alreadyvalidated']); }

        $db->sql_query_prepared("DELETE FROM awaitingactivation WHERE uid = ? AND (type='r' OR type='e')", [$user['id']]);

        if ($user['usergroup'] == 2 && !in_array($activation['type'], ['e', 'b'], true)) {
            $db->sql_query_prepared("UPDATE users SET usergroup = ? WHERE id = ?", [2, $user['id']]);
            $cache->update_awaitingactivation();
        }

        if ($activation['type'] === 'e') {
			$db->sql_query_prepared("UPDATE users SET email = ? WHERE id = ?", [$activation['misc'], $user['id']]);
            $plugins->run_hooks('member_activate_emailupdated');
            redirect('usercp.php', $lang->member['redirect_emailupdated']);
        } 
		elseif ($activation['type'] === 'b') {
            $db->sql_query_prepared("UPDATE awaitingactivation SET validated = 1 WHERE uid = ? AND type='b'", [$user['id']]);
			$db->sql_query_prepared("UPDATE users SET ustatus = 'confirmed' WHERE id = ? AND ustatus='pending' AND enabled='yes'", [$user['id']]);
			$cache->update_awaitingactivation();
            
			require_once INC_PATH . '/functions_pm.php';
            $pm = ['subject' => sprintf($lang->member['welcomepmsubject'], $SITENAME), 'message' => sprintf($lang->member['welcomepmbody'], $user['username'], $SITENAME, $BASEURL), 'touid' => $user['id']];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
			
			
			$plugins->run_hooks('member_activate_emailactivated');
			
			
			
            redirect('index.php', $lang->member['redirect_accountactivated_admin'], '', true);
        } else {
            $plugins->run_hooks('member_activate_accountactivated');
            $db->sql_query_prepared("UPDATE users SET ustatus = 'confirmed' WHERE id = ? AND ustatus='pending' AND enabled='yes'", [$user['id']]);
            require_once INC_PATH . '/functions_pm.php';
            $pm = ['subject' => sprintf($lang->member['welcomepmsubject'], $SITENAME), 'message' => sprintf($lang->member['welcomepmbody'], $user['username'], $SITENAME, $BASEURL), 'touid' => $user['id']];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
            redirect('index.php', $lang->member['redirect_accountactivated']);
        }
    } else {
        $plugins->run_hooks('member_activate_form');
        $code = htmlspecialchars_uni($mybb->get_input('code'));
        $user['username'] = htmlspecialchars_uni($user['username'] ?? '');
        
		$activate = au_page('fa-user-check', 'success', $lang->member['account_activation'], hsafe((string)($lang->member['hint_activate'] ?? 'Enter your username and the code from the e-mail')),
            '<form action="member.php" method="post">'
            . au_field('username', 'username', $lang->member['username'], 'fa-user', 'text', $user['username'], 'autocomplete="username" required')
            . au_field('code', 'code', $lang->member['activation_code'], 'fa-key', 'text', $code, 'autocomplete="one-time-code" required')
            . '<input type="hidden" name="action" value="activate" />'
            . au_submit('fa-check', $lang->member['activate_account'], 'regsubmit')
            . '</form>',
            au_links([['member.php?action=resendactivation', 'fa-envelope-circle-check', hsafe((string)($lang->member['lnk_resend_activation'] ?? 'Resend activation code'))]]));

        stdhead($lang->member['account_activation']);
        echo $activate;
    }
}



// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_resendactivation
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_resendactivation' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_resendactivation_start');

    $query    = $db->sql_query_prepared(
        "SELECT u.id, u.username, u.usergroup, u.ustatus, u.email, a.code, a.type, a.validated FROM users u LEFT JOIN awaitingactivation a ON (a.uid=u.id AND (a.type='r' OR a.type='b')) WHERE u.email = ?",
        [$mybb->get_input('email')]
    );
    $numusers = $query ? $db->num_rows($query) : 0;

    if ($numusers < 1) {
        error($lang->member['error_invalidemail']);
    } else {
        while ($user = $db->fetch_array($query)) {
            if ($user['type'] === 'b' && $user['validated'] == 1) { error($lang->member['error_activated_by_admin']); }
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
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: resendactivation
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'resendactivation') {
    $plugins->run_hooks('member_resendactivation');
    if ($mybb->user['uid'] && ($mybb->user['ustatus'] ?? '') !== 'pending') { error($lang->member['error_alreadyactivated']); }

    $query      = $db->sql_query_prepared("SELECT * FROM awaitingactivation WHERE uid = ? AND type='b'", [$mybb->user['uid']]);
    $activation = $query ? $db->fetch_array($query) : null;
    if ($activation && $activation['validated'] == 1) { error($lang->member['error_activated_by_admin']); }

    $errors = isset($errors) && count($errors) > 0 ? inline_error($errors) : '';
    $email  = $errors ? htmlspecialchars_uni($mybb->get_input('email')) : '';

    $plugins->run_hooks('member_resendactivation_end');
    
    $activate = au_page('fa-envelope-circle-check', 'primary', $lang->member['resend_activation'], hsafe($lang->member['hint_resend_activation']),
        $errors
        . '<form action="member.php" method="post">'
        . '<input type="hidden" name="action" value="do_resendactivation" />'
        . au_field('email', 'email', $lang->member['email_address'], 'fa-envelope', 'email', $email, 'placeholder="you@example.com" autocomplete="email" required')
        . au_submit('fa-paper-plane', $lang->member['request_activation'])
        . '</form>',
        au_links([['member.php?action=login', 'fa-arrow-left', hsafe($lang->member['lnk_back_to_login'])]]));

	stdhead();
    echo $activate;
}




// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_lostpw
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_lostpw' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_lostpw_start');

    $query    = $db->sql_query_prepared("SELECT * FROM users WHERE email = ?", [$mybb->get_input('email')]);
    $numusers = $query ? $db->num_rows($query) : 0;

    if ($numusers < 1) {
        stderr($lang->member['error_invalidemail']);
    } else {
        while ($query && ($user = $db->fetch_array($query))) {
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
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: lostpw
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'lostpw') {
    $plugins->run_hooks('member_lostpw');
    $errors = (isset($errors) && count($errors) > 0) ? inline_error($errors) : '';
    $email  = $errors ? htmlspecialchars_uni($mybb->get_input('email')) : '';
    
	$lostpw = au_page('fa-key', 'warning', $lang->member['lost_pw'], hsafe((string)($lang->member['hint_lostpw'] ?? 'We will e-mail you a link to reset the password')),
        $errors
        . '<form action="member.php" method="post">'
        . au_field('email', 'email', $lang->member['email_address'], 'fa-envelope', 'email', $email, 'placeholder="you@example.com" autocomplete="email" required')
        . '<input type="hidden" name="action" value="do_lostpw" />'
        . au_submit('fa-paper-plane', $lang->member['request_user_pass'])
        . '</form>',
        au_links([
            ['member.php?action=login', 'fa-arrow-left', hsafe($lang->member['lnk_back_to_login'])],
            ['recoverhint.php', 'fa-circle-question', hsafe((string)($lang->member['lnk_recoverhint'] ?? 'Recover via secret question'))],
        ]));

    stdhead($lang->member['lost_pw']);
    echo $lostpw;
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

        if (!$activationcode || $activationcode !== $mybb->get_input('code')) { stderr($lang->member['error_badlostpwcode']); }

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

        my_mail($user['email'], sprintf($lang->member['emailsubject_passwordreset'], $SITENAME), sprintf($lang->member['email_passwordreset'], $user['username'], $SITENAME, $password));
        $plugins->run_hooks('member_resetpassword_reset');
        stderr($lang->member['redirect_passwordreset']);
    } else {
        $plugins->run_hooks('member_resetpassword_form');
        $code           = htmlspecialchars_uni($mybb->get_input('code'));
        $input_username = htmlspecialchars_uni($mybb->get_input('username'));
        stdhead($lang->member['reset_password']);
        
		$activate = au_page('fa-unlock-keyhole', 'warning', $lang->member['reset_password'], hsafe((string)($lang->member['hint_resetpw'] ?? 'A new password will be sent to your e-mail')),
            '<form action="member.php" method="post">'
            . au_field('username', 'username', $lang->member['username'], 'fa-user', 'text', $input_username, 'autocomplete="username" required')
            . au_field('code', 'code', $lang->member['activation_code'], 'fa-key', 'text', $code, 'autocomplete="one-time-code" required')
            . '<input type="hidden" name="action" value="resetpassword" />'
            . au_submit('fa-paper-plane', $lang->member['send_password'], 'regsubmit')
            . '</form>',
            au_links([['member.php?action=login', 'fa-arrow-left', hsafe($lang->member['lnk_back_to_login'])]]));

        echo $activate;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_login
// ══════════════════════════════════════════════════════════════════════════
$inline_errors = '';
if ($mybb->input['action'] === 'do_login' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
	
	// ── Сразу проверяем не заблокирован ли IP ────────────────
    failedloginscheck('Login');
	

    $errors = [];
    $plugins->run_hooks('member_do_login_start');

    require_once INC_PATH . '/datahandlers/login.php';
    $loginhandler = new LoginDataHandler('get');

    if ($mybb->get_input('quick_password') && $mybb->get_input('quick_username')) {
        $mybb->input['password'] = $mybb->get_input('quick_password');
        $mybb->input['username'] = $mybb->get_input('quick_username');
        $mybb->input['remember'] = $mybb->get_input('quick_remember');
    }

    $user = ['username' => $mybb->get_input('username'), 'password' => $mybb->get_input('password'), 'remember' => $mybb->get_input('remember')];
    $user_loginattempts = get_user_by_username($user['username'], ['fields' => 'loginattempts']);
    if (!empty($user_loginattempts)) { $user['loginattempts'] = (int)$user_loginattempts['loginattempts']; }

    $loginhandler->set_data($user);
    $validated = $loginhandler->validate_login();

    if (!$validated) {
        $mybb->input['action']   = 'login';
        $mybb->request_method    = 'get';
        $login_user_uid          = (int)($loginhandler->login_data['id'] ?? 0);
        $user['loginattempts']   = (int)($loginhandler->login_data['loginattempts'] ?? 0);

        login_attempt_check($login_user_uid);
		
        $db->sql_query_prepared("UPDATE users SET loginattempts = loginattempts+1 WHERE id = ?", [$login_user_uid]);
        
		$username = $mybb->get_input('username');
        $password = $mybb->get_input('password');
		
		$ipaddress = get_ip();
		$md5pw = md5($password);
        $iphost = @gethostbyaddr(USERIPADDRESS);
		
		failedlogins('login', false, true, true, $login_user_uid);
        $errors = $loginhandler->get_friendly_errors();
    } else {

        $login_uid = (int)($loginhandler->login_data['id'] ?? 0);

        require_once INC_PATH . '/functions_2fa.php';
        if (totp_is_enabled($login_uid)) {
            // Password OK but 2FA required — store pending and redirect
            $remember = $mybb->get_input('remember');
            $url      = $mybb->get_input('url');
            totp_create_pending($login_uid, $remember, $url);
            redirect('member.php?action=verify_2fa', '');
        }

        $loginhandler->complete_login();
		
		log_login((int)($loginhandler->login_data['id'] ?? 0), 'success');
       
        $plugins->run_hooks('member_do_login_end');
        $url = $mybb->get_input('url');
        if (!empty($url) && !str_contains(basename($url), 'member.php') && !preg_match('#^javascript:#i', $url)) {
            if ((str_contains(basename($url), 'newthread.php') || str_contains(basename($url), 'newreply.php')) && str_contains($url, '&processed=1')) {
                $url = str_replace('&processed=1', '', $url);
            }
            $url = str_replace('&amp;', '&', $url);
            if (!str_starts_with($url, $BASEURL . '/')) {
                $url = str_starts_with($url, '/') ? substr($url, 1) : end(explode('/', $url));
                $url = $BASEURL . '/' . $url;
            }
            redirect($url, $lang->member['redirect_loggedin']);
        } else {
            redirect('index.php', $lang->member['redirect_loggedin']);
        }
    }
    $plugins->run_hooks('member_do_login_end');
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

    $uid   = (int)$pending['uid'];
    $error = '';

    if ($mybb->request_method === 'post') {
        $code   = preg_replace('/\D/', '', $mybb->get_input('totp_code'));
        $secret = totp_get_secret($uid);

        if ($secret && totp_verify($secret, $code)) {
            totp_clear_pending($pending['token']);

            require_once INC_PATH . '/datahandlers/login.php';
            $loginhandler             = new LoginDataHandler('get');
            $loginhandler->login_data = get_user($uid);
            $mybb->input['remember'] = $pending['remember'];
            $loginhandler->complete_login();
			
			log_login($uid, 'success');
           

            $url = $pending['url'];
            if (!empty($url) && !str_contains(basename($url), 'member.php')) {
                redirect($url, $lang->member['redirect_loggedin']);
            } else {
                redirect('index.php', $lang->member['redirect_loggedin']);
            }
        } else {
            log_login($uid, 'fail');
			
			// Warn account owner — password is correct but 2FA code is wrong
            require_once INC_PATH . '/functions_pm.php';
            $user_data = get_user($uid);
            $pm = [
              'subject' => $lang->member['pm_2fa_fail_subject'],
              'message' => ags_fmt($lang->member['pm_2fa_fail_body'], get_ip(), date('Y-m-d H:i:s')),
              'touid'  => $uid,
              'sender' => ['uid' => -1],
            ];
            send_pm($pm, -1, true);
			
			
			$error = '<div class="st-notice st-soft-danger mb-3"><i class="fa-solid fa-triangle-exclamation"></i><div>' . hsafe($lang->member['err_2fa_invalid']) . '</div></div>';
        }
    }

    stdhead($SITENAME . ' - ' . $lang->member['sec_2fa']);
    echo au_page('fa-shield-halved', 'success', hsafe($lang->member['sec_2fa']), hsafe($lang->member['hint_2fa']),
        $error
        . '<form method="post" action="member.php?action=verify_2fa">'
        . '<div class="au-field"><label class="st-label" for="totp_code">' . hsafe($lang->member['lbl_2fa_code']) . '</label>'
        . '<input type="text" id="totp_code" name="totp_code" class="form-control au-otp" placeholder="000000" maxlength="6"'
        . ' autocomplete="one-time-code" autofocus inputmode="numeric" pattern="[0-9]{6}" required></div>'
        . au_submit('fa-right-to-bracket', hsafe($lang->member['btn_2fa_verify']))
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '</form>',
        au_links([['member.php?action=login', 'fa-arrow-left', hsafe($lang->member['lnk_back_to_login'])]]));
    stdfoot();
    exit;
}






// ══════════════════════════════════════════════════════════════════════════
// ACTION: login
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'login') {
    $plugins->run_hooks('member_login');
    $member_loggedin_notice = '';
    if (isset($CURUSER) && is_array($CURUSER) && !empty($CURUSER['id'])) {
        $CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);
        $already_logged_in   = sprintf($lang->member['already_logged_in'], build_profile_link($CURUSER['username'], $CURUSER['id']));
        
		$member_loggedin_notice = '<div class="st-notice st-soft-info mb-3"><i class="fa-solid fa-circle-info"></i><div>' . $already_logged_in . '</div></div>';
    }
    login_attempt_check();
    $redirect_url = '';
    if (isset($_SERVER['HTTP_REFERER']) && !str_contains($_SERVER['HTTP_REFERER'], 'action=login')) {
        $redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
    }
    $username = $password = '';
    if (isset($mybb->input['username']) && $mybb->request_method === 'post') { $username = htmlspecialchars_uni($mybb->get_input('username')); }
    if (isset($mybb->input['password']) && $mybb->request_method === 'post') { $password = htmlspecialchars_uni($mybb->get_input('password')); }
    if (!empty($errors)) { $inline_errors = inline_error($errors); }
    $plugins->run_hooks('member_login_end');
   
    $login_body = '<form action="member.php" method="post">'
        . $inline_errors . $member_loggedin_notice
        . au_field('username', 'username', $lang->member['username'], 'fa-user', 'text', $username, 'autocomplete="username" autofocus required')
        . au_field('password', 'password', $lang->member['password'], 'fa-lock', 'password', $password, 'autocomplete="current-password" required',
              '<a class="au-forgot" href="' . $BASEURL . '/member.php?action=lostpw">' . $lang->member['lostpw_note'] . '</a>')
        . '<label class="st-check au-remember" for="remember"><input class="form-check-input" type="checkbox" name="remember" value="yes" id="remember" checked><span>' . $lang->member['remember_me'] . '</span></label>'
        . au_submit('fa-right-to-bracket', $lang->member['login'])
        . '<input type="hidden" name="action" value="do_login" />'
        . '<input type="hidden" name="url" value="' . $redirect_url . '" />'
        . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . '</form>';

    $login_foot = au_links([
        ['member.php?action=register',         'fa-user-plus',          hsafe((string)($lang->member['lnk_register'] ?? 'Create an account'))],
        ['recoverhint.php',                    'fa-circle-question',    hsafe((string)($lang->member['lnk_recoverhint'] ?? 'Recover via secret question'))],
        ['member.php?action=resendactivation', 'fa-envelope-circle-check', hsafe((string)($lang->member['lnk_resend_activation'] ?? 'Resend activation code'))],
        ['contactus.php',                      'fa-headset',            hsafe((string)($lang->member['lnk_contact'] ?? 'Contact us'))],
    ]);

    $login = au_page('fa-right-to-bracket', 'primary', au_label($lang->member['nav_login'] ?? $lang->member['login']),
        hsafe((string)($lang->member['hint_login'] ?? 'Sign in to your account')), $login_body, $login_foot);

    stdhead();
    echo $login;
}




// ══════════════════════════════════════════════════════════════════════════
// ACTION: logout
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'logout') {
    $plugins->run_hooks('member_logout_start');

    if (empty($CURUSER['id'])) {
        redirect('index.php', $lang->member['redirect_alreadyloggedout']);
    }

    if (isset($mybb->input['sid']) && !hash_equals($session->sid, (string)$mybb->get_input('sid'))) {
        stderr($lang->member['error_notloggedout']);
    }

    $logoutkey = $session->make_logoutkey((string)$CURUSER['loginkey']);
    if (!hash_equals($logoutkey, (string)$mybb->get_input('logoutkey'))) {
        stderr($lang->member['error_notloggedout']);
    }

    $uid  = (int)$CURUSER['id'];
    $time = TIMENOW;

    $db->sql_query_prepared(
        "UPDATE users SET lastvisit = ?, lastactive = ? WHERE id = ?",
        [$time, $time, $uid]
    );

    // Удаляет строку из sessions и cookie sid
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
// ACTION: staff_activate (кнопка «Активировать» в профиле неактивированного пользователя)
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'staff_activate' && $mybb->request_method === 'post') {
    if (!is_mod($usergroups)) {
        print_no_permission(true);
    }
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        stderr($lang->member['err_invalid_request'] ?? 'Invalid security token. Please refresh the page and try again.');
    }

    $target = get_user($mybb->get_input('id', MyBB::INPUT_INT));
    if (!$target) {
        stderr($lang->member['error_nomember'], $SITENAME . ' - ' . $lang->member['sec_member_not_found'], 404, 'user');
    }
    $target_id = (int)$target['id'];

    $db->sql_query_prepared("UPDATE users SET ustatus = 'confirmed' WHERE id = ? AND ustatus = 'pending' AND enabled = 'yes'", [$target_id]);
    if ((int)$db->affected_rows() < 1) {
        stderr($lang->member['err_activate_failed'] ?? 'This account cannot be activated: it is already active or disabled.');
    }

    $db->sql_query_prepared("DELETE FROM awaitingactivation WHERE uid = ? AND (type = 'r' OR type = 'b')", [$target_id]);
    $cache->update_awaitingactivation();

    require_once INC_PATH . '/functions_pm.php';
    send_pm([
        'subject'       => ['welcomepmsubject', $SITENAME],
        'message'       => ['welcomepmbody', $target['username'], $SITENAME, $BASEURL],
        'touid'         => $target_id,
        'language'      => (string)($target['language'] ?? ''),
        'language_file' => 'member',
        'sender'        => ['uid' => -1],
    ], -1, true);

    write_log('User ' . $target['username'] . ' (' . $target_id . ') was activated by ' . $CURUSER['username']);

    redirect(get_profile_link($target_id), $lang->member['redirect_user_activated'] ?? 'The account has been activated.');
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: profile
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'profile') {
    if (!isset($CURUSER) || $CURUSER['id'] == 0) { print_no_permission(); }

    gzip();
    maxsysop();

    $parser_options = ['allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'allow_videocode' => 1, 'filter_badwords' => 1];

    $uid = $mybb->get_input('id', MyBB::INPUT_INT);
    $memprofile = $uid ? get_user($uid) : ($CURUSER['id'] ? $CURUSER : false);

    
	if (!$memprofile) 
	{     
		stderr($lang->member['error_nomember'], $SITENAME . ' - ' . $lang->member['sec_member_not_found'], 404, 'user');
	}

    $uid       = $memprofile['id'];
    $SameUser  = ($uid === (int)$CURUSER['id']);
    $IsStaff   = is_mod($usergroups);

    
	
	if ($memprofile['invisible'] == 1 && !$SameUser && !$IsStaff) 
	{ 
        stderr($lang->member['noperm'], $SITENAME . ' - ' . $lang->member['sec_access_denied'], 403, '403'); 
	}
	
   
	
	// Неактивированный профиль видит только стафф (для активации прямо отсюда)
	if ($memprofile['ustatus'] === 'pending' && !$IsStaff) 
	{    
        stderr($lang->member['pendinguser'], $SITENAME . ' - ' . $lang->member['sec_access_denied'], 403, '403'); 
	}
	
	
	

    $plugins->run_hooks('member_profile_start');

    $me_username             = $memprofile['username'];
    $memprofile['username']  = htmlspecialchars_uni($memprofile['username']);

    stdhead(sprintf($lang->member['title'], $memprofile['username']));

    $memperms = user_permissions((int)$memprofile['id']);

   

    add_breadcrumb(sprintf($lang->member['nav_profile'], $memprofile['username']));
    build_breadcrumb();

    $send_user_email    = sprintf($lang->member['send_user_email'], $memprofile['username']);
    $send_pms           = sprintf($lang->member['send_pm'], $memprofile['username']);
    $users_signature    = sprintf($lang->member['users_signature'], $memprofile['username']);
    $users_forum_info   = sprintf($lang->member['users_forum_info'], $memprofile['username']);
    $users_additional_info = sprintf($lang->member['users_additional_info'], $memprofile['username']);

    $useravatar = format_avatar($memprofile['avatar'], $memprofile['avatardimensions']);
   	
    // Avatar picture for the header; format_avatar() may return an HTML stub instead of a URL
    $pf_avatar_img = !empty($useravatar['is_html'])
        ? '<span class="pf-ava__none"><i class="fa-solid fa-user"></i></span>'
        : '<img class="' . (!empty($useravatar['is_placeholder']) ? 'is-placeholder' : '') . '" src="' . $useravatar['image'] . '" alt="">';	
	  


    $website = $sendemail = $sendpm = $contact_details = '';

    if ($usergroups['cansendemail'] == 1 && $uid != $CURUSER['id'] && $memprofile['hideemail'] != 1
        && (str_contains(',' . $memprofile['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') === false || $usergroups['cansendemailoverride'] != 0)) {
        
        
		$sendemail = '<div class="py-2 border-bottom"><span class="text-muted">'.$lang->member['email'].'</span> <a href="member.php?action=emailuser&amp;id='.$memprofile['id'].'">'.$send_user_email.'</a></div>';
		
		
    }

    if ($enablepms != 0 && $uid != $CURUSER['id'] && !empty($CURUSER['id'])
        && (($memprofile['receivepms'] != 0
            && str_contains(',' . $memprofile['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') === false)
            || $usergroups['canoverridepm'] == 1)) {
        
        
		$sendpm = '<div class="py-2 border-bottom"><span class="text-muted">'.$lang->member['pm'].'</span> <a href="private.php?action=send&amp;uid='.$memprofile['id'].'">'.$send_pms.'</a></div>';
    }

    // Contact tab: only rows the viewer can actually use
    $contact_details = '';
    if ($sendpm || $sendemail) {
        $contact_details = '<section class="st-card">'
            . '<div class="st-card__head"><i class="fa-solid fa-address-card"></i>' . hsafe($lang->member['sec_contact_details']) . '</div>'
            . '<div class="st-card__body"><div class="st-rows">'
            . ($sendpm ? '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-envelope"></i>' . hsafe($lang->member['lbl_private_message']) . '</span>'
                . '<span class="st-row__val"><a href="private.php?action=send&amp;uid=' . (int)$memprofile['id'] . '">' . $send_pms . '</a></span></div>' : '')
            . ($sendemail ? '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-at"></i>' . hsafe($lang->member['lbl_email']) . '</span>'
                . '<span class="st-row__val"><a href="member.php?action=emailuser&amp;id=' . (int)$memprofile['id'] . '">' . $send_user_email . '</a></span></div>' : '')
            . '</div></div></section>';
    }

    $signature = '';
    $pf_signature = static function (string $html) use ($users_signature): string {
        return '<section class="st-card mb-3">'
            . '<div class="st-card__head"><i class="fa-solid fa-signature"></i>' . $users_signature . '</div>'
            . '<div class="st-card__body pf-sig">' . $html . '</div></section>';
    };
    if ($memprofile['signature']) {
        $sig_parser = ['allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'me_username' => $me_username, 'filter_badwords' => 1];
        $memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
        $signature = $pf_signature($memprofile['signature']);
    }

    // User data with permissions
    $Query = $db->sql_query_prepared("SELECT * FROM users WHERE id = ? LIMIT 1", [$uid]);
    if ($Query && $db->num_rows($Query) > 0) {
        $user = $db->fetch_array($Query);
    } else {
        stderr($lang->member['invaliduser']);
    }

    $usericons = get_user_icons($user);

    if ($memprofile['invited_by']) {
        $query = $db->sql_query_prepared("SELECT username, usergroup FROM users WHERE id = ?", [$memprofile['invited_by']]);
        if ($query && $db->num_rows($query) > 0) {
            $IUser = $db->fetch_array($query);
            $memprofile['invited_by'] = '<a href="' . get_profile_link($memprofile['invited_by']) . '">' . format_name($IUser['username'], $IUser['usergroup']) . '</a>';
        }
    }

    $uploaded   = mksize($memprofile['uploaded']);
    $downloaded = mksize($memprofile['downloaded']);
    $kps        = ($SameUser || $IsStaff) ? sprintf($lang->member['kps']) : '';
    $sr         = '';

    // Ratio calculation
    $up   = (int)($memprofile['uploaded']   ?? 0);
    $down = (int)($memprofile['downloaded'] ?? 0);
    if ($down > 0) {
        $ratio_val = $up / $down;
        $ratio     = number_format($ratio_val, 2);
    } else {
        $ratio_val = $up > 0 ? 999 : 0;
        $ratio     = $up > 0 ? '∞' : '0.00';
    }
    $ratio_class = match(true) {
        $ratio_val >= 1.0 => 'ratio-ok',
        $ratio_val >= 0.5 => 'ratio-warn',
        default           => 'ratio-bad',
    };
    $total              = max($up + $down, 1);
    $uploaded_percent   = min(100, (int)round($up   / $total * 100));
    $downloaded_percent = min(100, (int)round($down / $total * 100));
    $ratio_label = match($ratio_class) {
        'ratio-ok'   => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>' . hsafe($lang->member['lbl_ratio_good']) . '</span>',
        'ratio-warn' => '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>' . hsafe($lang->member['lbl_ratio_fair']) . '</span>',
        default      => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>' . hsafe($lang->member['lbl_ratio_poor']) . '</span>',
    };

    // Timezone / dates
    $memprofile['timezone'] = (float)$memprofile['timezone'];
    if ($memprofile['dst'] == 1) {
        $memprofile['timezone']++;
        if (!str_starts_with((string)$memprofile['timezone'], '-')) {
            $memprofile['timezone'] = '+' . $memprofile['timezone'];
        }
    }
	
	


    $memregdate    = my_datee($dateformat, $memprofile['added']);
    $memoffset    = (string)(float)$memprofile['timezone'];
    $memlocaldate = my_datee($dateformat, TIMENOW, $memoffset, 0);
    $memlocaltime = my_datee($timeformat, TIMENOW, $memoffset, 0);
	
	
	
    $localtime     = ags_fmt($lang->member['local_time_format'], $memlocaldate, $memlocaltime);

    // Birthday
    $membday = $membdayage = '';
    if ($memprofile['birthday']) {
        $membday_arr = explode('-', $memprofile['birthday']);
        if ($memprofile['birthdayprivacy'] !== 'none') {
            if (!empty($membday_arr[0]) && !empty($membday_arr[1]) && !empty($membday_arr[2])) {
                $membdayage = ags_fmt($lang->member['membdayage'], (int)get_age($memprofile['birthday']));
                $bdayformat = fix_mktime($dateformat, $membday_arr[2]);
                $membday    = date($bdayformat, mktime(0, 0, 0, (int)$membday_arr[1], (int)$membday_arr[0], (int)$membday_arr[2]));
            } elseif (!empty($membday_arr[2])) {
                $membday = date('Y', mktime(0, 0, 0, 1, 1, (int)$membday_arr[2]));
            } else {
                $membday = date('F j', mktime(0, 0, 0, (int)$membday_arr[1], (int)$membday_arr[0], 0));
            }
        }
        if ($memprofile['birthdayprivacy'] === 'age')  { $membday = $lang->member['birthdayhidden']; }
        if ($memprofile['birthdayprivacy'] === 'none') { $membday = $lang->member['birthdayhidden']; $membdayage = ''; }
    } else {
        $membday    = $lang->member['not_specified'];
        $membdayage = '';
    }

    
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css" type="text/css" media="screen" />';

    $usertitle = $memperms['image'] ?? '';

    // Неактивированный пользователь (видит только стафф): метка в шапке + карточка с кнопкой «Активировать»
    $pending_banner = '';
    if ($memprofile['ustatus'] === 'pending') {
        $L_pending = $lang->member;
        $usertitle = '<span class="st-badge st-soft-warning"><i class="fa-solid fa-hourglass-half"></i>'
                   . hsafe($L_pending['lbl_pending_status'] ?? 'Awaiting activation') . '</span>';

        $pending_days = max(0, (int)floor((TIMENOW - (int)$memprofile['added']) / 86400));
        $pending_note = ags_fmt((string)($L_pending['lbl_pending_notice'] ?? 'This account has not been activated. Registered {1} day(s) ago.'), ts_nf($pending_days));
        $pending_js   = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        $pending_banner = '
  <section class="st-card pf-pending mb-3">
    <form method="post" action="member.php" id="pendingActivateForm" class="st-card__body d-flex flex-wrap align-items-center gap-3">
      <input type="hidden" name="action" value="staff_activate">
      <input type="hidden" name="id" value="' . (int)$uid . '">
      <input type="hidden" name="my_post_key" value="' . hsafe((string)$mybb->post_code) . '">
      <span class="st-ico st-soft-warning"><i class="fa-solid fa-user-clock"></i></span>
      <div class="flex-grow-1 min-w-0">
        <strong>' . hsafe($L_pending['lbl_pending_status'] ?? 'Awaiting activation') . '</strong>
        <div class="st-meta">' . hsafe($pending_note) . '</div>
      </div>
      <button type="submit" class="btn btn-success st-pill"><i class="fa-solid fa-user-check me-1"></i>' . hsafe($L_pending['btn_activate_user'] ?? 'Activate') . '</button>
    </form>
  </section>
  <script>
  document.getElementById("pendingActivateForm").addEventListener("submit", function (e) {
    if (this.dataset.confirmed) { return; }
    e.preventDefault();
    var form = this, text = ' . json_encode((string)($L_pending['confirm_activate_user'] ?? 'Activate this account?'), $pending_js) . ';
    var go = function () { form.dataset.confirmed = "1"; form.submit(); };
    if (window.Swal) {
      Swal.fire({ icon: "question", text: text, showCancelButton: true }).then(function (r) { if (r.isConfirmed) { go(); } });
    } else if (confirm(text)) {
      go();
    }
  });
  </script>';
    }

    // Online status
    $timesearch = TIMENOW - $wolcutoffmins * 60;
    $query      = $db->sql_query_prepared("SELECT location,nopermission FROM sessions WHERE uid = ? AND time > ? ORDER BY time DESC LIMIT 1", [$uid, $timesearch]);
    $session    = $query ? $db->fetch_array($query) : null;

    $timeonline      = $lang->member['none_registered'];
    $memlastvisitdate = $lang->member['lastvisit_never'];
    $last_seen        = max($memprofile['lastactive'], $memprofile['lastvisit']);

    if (!empty($last_seen)) {
        if ($memprofile['invisible'] == 1 && !$SameUser && !$IsStaff) {
            $memlastvisitdate = $lang->member['hidden'];
            $online_status    = $timeonline = $lang->member['hidden'];
        } else {
            $memlastvisitdate = my_datee('relative', $last_seen);
            if ($memprofile['timeonline'] > 0) { $timeonline = mkprettytime((int)$memprofile['timeonline']); }

            if (!empty($session)) {
                $lang->load('online');
                require_once INC_PATH . '/functions_online.php';
                $activity      = fetch_wol_activity($session['location'], (bool)$session['nopermission']);
                $location      = build_friendly_wol_location($activity);
                $location_time = my_datee($timeformat, $last_seen);
                
				$online_status = '<a href="online.php" class="pf-online"><span class="pf-online__dot"></span>' . $lang->global['postbit_status_online'] . '</a>'
                    . ' <span class="st-meta">' . $location . ' @ ' . $location_time . '</span>';
            }
        }
    }

    if (!isset($online_status)) {
        
		$online_status = '<span class="pf-offline">' . $lang->global['postbit_status_offline'] . '</span>';
		
		
    }

    // Status dot
    $status_dot_class = ' status-off';
    if (!empty($memprofile['invisible']) && !$SameUser && !$IsStaff) {
        $status_dot_class = ' status-off';
    } elseif (!empty($session)) {
        $status_dot_class = '';
    } else {
        $delta = (int)(TIMENOW - (int)$last_seen);
        $status_dot_class = ($delta > 0 && $delta <= 1800) ? ' status-away' : ' status-off';
    }
    $status_dot_html = '<span class="status-dot' . $status_dot_class . '" aria-hidden="true"></span>';

    // Ban info
    $bannedbit = '';
    if ($memperms['isbannedgroup'] == 1 && $usergroups['canstaffpanel'] == 1) {
        $query = $db->sql_query_prepared("SELECT b.*, a.username AS adminuser FROM banned b LEFT JOIN users a ON (b.admin=a.id) WHERE b.uid = ? LIMIT 1", [$uid]);
        if ($query && $db->num_rows($query)) {
            $memban = $db->fetch_array($query);
            $memban['reason'] = $memban['reason'] ? htmlspecialchars_uni($parser->parse_badwords($memban['reason'])) : $lang->member['not_available'];

            if (in_array($memban['lifted'], ['perm', ''], true) || in_array($memban['bantime'], ['perm', '---'], true)) {
                $banlength     = $lang->member['permanent'];
                $timeremaining = $lang->member['not_available'];
                $banned_class  = 'normal_banned';
            } else {
                $bantimes  = fetch_ban_times();
                $banlength = $bantimes[$memban['bantime']];
                $remaining = $memban['lifted'] - TIMENOW;
                $timeremaining = mkprettytime($remaining);
                $banned_class = match(true) {
                    $remaining < 3600  => 'high_banned',
                    $remaining < 86400 => 'moderate_banned',
                    $remaining < 604800=> 'low_banned',
                    default            => 'normal_banned',
                };
            }

            $timeremaining        = '<span class="' . $banned_class . '">' . hsafe(ags_fmt($lang->member['ban_time_remaining'], $timeremaining)) . '</span>';
            $memban['adminuser']  = build_profile_link(htmlspecialchars_uni($memban['adminuser']), $memban['admin']);
            
			$bannedbit = '<section class="st-card pf-ban mb-3">'
                . '<div class="st-card__body d-flex gap-3">'
                .   '<span class="st-ico st-soft-danger"><i class="fa-solid fa-ban"></i></span>'
                .   '<div class="flex-grow-1 min-w-0">'
                .     '<div class="d-flex flex-wrap justify-content-between gap-2"><strong class="text-danger">' . $lang->member['ban_note'] . '</strong>'
                .       '<span class="st-badge st-soft-danger"><i class="fa-regular fa-clock"></i>' . $banlength . '</span></div>'
                .     '<div class="st-meta mt-1">' . $lang->global['banned_warning2'] . '</div>'
                .     '<div class="pf-ban__reason mt-2">' . $memban['reason'] . '</div>'
                .     '<div class="st-meta mt-2"><i class="fa-solid fa-user-shield me-1"></i>' . $lang->member['ban_by'] . ' ' . $memban['adminuser']
                .       ' &middot; <i class="fa-regular fa-hourglass-half me-1"></i>' . $lang->member['ban_length'] . ' ' . $banlength . ' ' . $timeremaining . '</div>'
                .   '</div>'
                . '</div></section>';        
		}
    }

    $memprofile['regip']  = my_inet_ntop($db->unescape_binary($memprofile['regip']));
    $memprofile['lastip'] = my_inet_ntop($db->unescape_binary($memprofile['lastip']));
    
	
	$ipaddress = '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-user-plus"></i>' . $lang->member['registration_ip'] . '</span><span class="st-row__val"><code>' . hsafe((string)$memprofile['regip']) . '</code></span></div>'
               . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-location-dot"></i>' . $lang->member['last_known_ip'] . '</span><span class="st-row__val"><code>' . hsafe((string)$memprofile['lastip']) . '</code></span></div>';	
	
	

    // Stats
  
	$zaza = $cache->read("stats");
	
    $stats = $zaza['numposts'];
    $stats22 = $zaza['numthreads'];
    $daysreg = max(1, (TIMENOW - $memprofile['added']) / 86400);

    $ppd = round(min($memprofile['postnum'], $memprofile['postnum'] / $daysreg), 2);
    $post_percent = $stats > 0 ? min(100, round($memprofile['postnum'] * 100 / $stats, 2)) : 0;

    $tpd = round(min($memprofile['threadnum'], $memprofile['threadnum'] / $daysreg), 2);
    $thread_percent = $stats22 > 0 ? min(100, round($memprofile['threadnum'] * 100 / $stats22, 2)) : 0;

    $ppd_percent_total = ags_fmt($lang->member['ppd_percent_total'], ts_nf($ppd), $post_percent);
    $tpd_percent_total = ags_fmt($lang->member['tpd_percent_total'], ts_nf($tpd), $thread_percent);

    // Mod options
    $modoptions = $viewnotes = $editnotes = $editprofile = $banuser = $manageban = $manageuser = '';
    $awaybit = $referrals = $groupimage = $userstars = $reputation = '';

    if ($memperms['isbannedgroup'] == 1 && $usergroups['canstaffpanel'] == 1) {
        
		$manageban = '<a class="pf-act" href="'.$BASEURL.'/modcp.php?action=banuser&amp;uid='.$uid.'"><i class="fa-solid fa-pen-to-square"></i>'.$lang->member['edit_ban_in_mcp'].'</a>'
                   . '<a class="pf-act" href="'.$BASEURL.'/modcp.php?action=liftban&amp;uid='.$uid.'&amp;my_post_key='.$mybb->post_code.'"><i class="fa-solid fa-shield-heart"></i>'.$lang->member['lift_ban_in_mcp'].'</a>';
    } else {
		$banuser = '<a class="pf-act pf-act--danger" href="'.$BASEURL.'/modcp.php?action=banuser&amp;uid='.$uid.'"><i class="fa-solid fa-ban"></i>'.$lang->member['ban_in_mcp'].'</a>';
    }
	$editprofile = '<a class="pf-act" href="'.$BASEURL.'/admin/edituser.php?action=edituser&amp;userid='.$uid.'"><i class="fa-solid fa-user-pen"></i>'.$lang->member['edit_in_mcp'].'</a>';
    $manageuser = $editprofile . $banuser . $manageban;

    if ($IsStaff) {
        

$modoptions = '<section class="st-card mb-3">'
    . '<div class="st-card__head"><i class="fa-solid fa-shield-halved"></i>' . hsafe($lang->member['mod_options']) . '</div>'
    . '<div class="st-card__body">'
    .   '<div class="st-rows mb-3">' . $ipaddress . '</div>'
    .   '<div class="st-meta mb-2"><i class="fa-solid fa-screwdriver-wrench me-1"></i>' . hsafe($lang->member['lbl_quick_actions']) . '</div>'
    .   '<div class="pf-acts">' . $manageuser . '</div>'
    . '</div></section>';

		
		
    }

    $findposts = $findthreads = '';
    if (!empty($memprofile['postnum']))  { 
	
	$findposts   = '<a href="search.php?action=finduser&amp;uid='.$uid.'" class="btn btn-sm btn-outline-primary st-pill"><i class="fa-solid fa-magnifying-glass me-1"></i>'.$lang->member['find_posts'].'</a>';	
	}
    if (!empty($memprofile['threadnum']))
	{ 
        $findthreads = '<a href="search.php?action=finduserthreads&amp;uid='.$uid.'" class="btn btn-sm btn-outline-success st-pill"><i class="fa-solid fa-magnifying-glass me-1"></i>'.$lang->member['find_threads'].'</a>';		
	}

    // Buddy/ignore
    $add_remove_options = [];
    $buddy_options = $ignore_options = '';
    if ($CURUSER['id'] != $memprofile['id'] && $CURUSER['id'] != 0) {
        $buddy_list  = explode(',', $CURUSER['buddylist']);
        $ignore_list = explode(',', $CURUSER['ignorelist']);

        $add_remove_options = in_array($uid, $buddy_list)
            ? ['url' => "usercp.php?action=do_editlists&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_buddy_button', 'lang' => $lang->member['remove_from_buddy_list']]
            : ['url' => "usercp.php?action=do_editlists&amp;add_username=" . urlencode($memprofile['username']) . "&amp;my_post_key={$mybb->post_code}", 'class' => 'add_buddy_button', 'lang' => $lang->member['add_to_buddy_list']];

        if (!in_array($uid, $ignore_list)) 
		{ 
	        
			
			$isBuddy = $add_remove_options['class'] === 'remove_buddy_button';
			$buddy_options = '<a href="'.$add_remove_options['url'].'" class="btn btn-sm st-pill '.($isBuddy ? 'btn-outline-danger' : 'btn-outline-secondary').'">'
				. '<i class="fa-solid '.($isBuddy ? 'fa-user-minus' : 'fa-user-plus').' me-1"></i>'.$add_remove_options['lang'].'</a>';
			
		}

        $add_remove_options = in_array($uid, $ignore_list)
            ? ['url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_ignore_button', 'lang' => $lang->member['remove_from_ignore_list']]
            : ['url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;add_username=" . urlencode($memprofile['username']) . "&amp;my_post_key={$mybb->post_code}", 'class' => 'add_ignore_button', 'lang' => $lang->member['add_to_ignore_list']];

        if (!in_array($uid, $buddy_list)) 
		{ 
	          $isIgnored = $add_remove_options['class'] === 'remove_ignore_button';
	          $ignore_options = '<a href="'.$add_remove_options['url'].'" class="btn btn-sm st-pill btn-outline-secondary pf-icon-btn" title="'.hsafe(strip_tags($add_remove_options['lang'])).'" aria-label="'.hsafe(strip_tags($add_remove_options['lang'])).'">'
	              . '<i class="fa-solid '.($isIgnored ? 'fa-eye' : 'fa-eye-slash').'"></i></a>';
			  
	    }
    }

    $plugins->run_hooks('member_profile_end');

    // Invited by
    $invs = '';
    if ($memprofile['invited_by']) {
        $invs = $memprofile['invited_by'];
    }

    // Swarm & completed
    $recent_user_torrents = build_recent_user_torrents($db, (int)$uid);
    $swarm          = build_user_active_swarm($db, (int)$uid, 1000);
    $active_seeds   = (string)$swarm['seed_count'];
    $active_leeches = (string)$swarm['leech_count'];
    $seeding_now    = $swarm['seed_html'];
    $leeching_now   = $swarm['leech_html'];

    $completed             = build_user_completed_torrents_from_snatched($db, (int)$uid, 10000);

    // Claims (NexusPHP: "Claims: N/max" in userdetails)
    require_once INC_PATH . '/functions_claim.php';
    // Own profile or staff: other users' claims are not public (claim.php checks it too)
    $claims_line = claim_enabled() && ((int)$uid === (int)$CURUSER['id'] || (isset($usergroups) && is_mod($usergroups)))
        ? '<a href="' . $BASEURL . '/claim.php?uid=' . (int)$uid . '" class="pf-kpi-sub">'
          . '<i class="fa-solid fa-heart-pulse me-1"></i>' . hsafe(ags_fmt($lang->member['lbl_claims'], claim_count_user((int)$uid), number_format(claim_cfg('max_per_user')))) . '</a>'
        : '';
    $completed_list        = $completed['html'];
    $times_completed_total = ts_nf($completed['count']);

    $is_own_profile   = ((int)$CURUSER['id'] === (int)$memprofile['id']);
    $is_mod_flag      = is_mod($usergroups);
    $can_change_avatar = ($is_own_profile || $is_mod_flag) ? 1 : 0;

    // SweetAlert2 + ags-dialog.js (кнопки ОК/Да/Отмена — из global.lang.php)
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">'
    . '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>'
    . ags_js_lang('global', ['dlg_ok' => 'js_dlg_ok', 'dlg_yes' => 'js_dlg_yes', 'dlg_cancel' => 'js_dlg_cancel'])
    . '<script src="' . $BASEURL . '/scripts/ags-dialog.js?ver=1"></script>';
    echo avatar_js_lang();
    echo '<script src="' . $BASEURL . '/scripts/avatar_upload.js?ver=5"></script>';

    // Report button
    $report_button = '';
    if ($CURUSER['id'] != $memprofile['id'] && $CURUSER['id'] != 0) {
        $report_button = '<button type="button" class="btn btn-sm btn-outline-danger st-pill pf-icon-btn" onclick="openReportUserModal(' . $memprofile['id'] . ', \'' . addslashes($memprofile['username']) . '\')" title="' . hsafe($lang->member['tip_report_user']) . '" aria-label="' . hsafe($lang->member['report_user']) . '"><i class="fa-solid fa-flag"></i></button>';
    }

    $reasons_map = [
        'spam'          => ['text' => $lang->member['opt_reason_spam'], 'icon' => 'fa-user-slash', 'description' => $lang->member['hint_reason_spam']],
        'harassment'    => ['text' => $lang->member['opt_reason_harassment'], 'icon' => 'fa-ban', 'description' => $lang->member['hint_reason_harassment']],
        'fake'          => ['text' => $lang->member['opt_reason_fake'], 'icon' => 'fa-mask', 'description' => $lang->member['hint_reason_fake']],
        'impersonation' => ['text' => $lang->member['opt_reason_impersonation'], 'icon' => 'fa-id-badge', 'description' => $lang->member['hint_reason_impersonation']],
        'inappropriate' => ['text' => $lang->member['opt_reason_inappropriate'], 'icon' => 'fa-eye-slash', 'description' => $lang->member['hint_reason_inappropriate']],
        'scam'          => ['text' => $lang->member['opt_reason_scam'], 'icon' => 'fa-skull-crossbones', 'description' => $lang->member['hint_reason_scam']],
        'copyright'     => ['text' => $lang->member['opt_reason_copyright'], 'icon' => 'fa-copyright', 'description' => $lang->member['hint_reason_copyright']],
        'malware'       => ['text' => $lang->member['opt_reason_malware'], 'icon' => 'fa-bug', 'description' => $lang->member['hint_reason_malware']],
        'other'         => ['text' => $lang->member['opt_reason_other'], 'icon' => 'fa-ellipsis', 'description' => $lang->member['hint_reason_other']],
    ];

    // Recent comments
    $recent_comments_html  = '';
    $recent_comments_query = $db->sql_query_prepared("
        SELECT c.id, c.text, c.dateline, c.torrent AS torrentid, t.name AS torrent_name
        FROM comments c LEFT JOIN torrents t ON (c.torrent=t.id)
        WHERE c.user=? ORDER BY c.dateline DESC LIMIT 10
    ", [$uid]);

    if ($recent_comments_query && $db->num_rows($recent_comments_query) > 0) {
        while ($comment = $db->fetch_array($recent_comments_query)) {
            $torrent_link = get_torrent_link($comment['torrentid']);
            $comment_date = my_datee($dateformat, $comment['dateline']);
            $comment_time = my_datee($timeformat, $comment['dateline']);
            $comment_text = mb_strimwidth(strip_tags($comment['text']), 0, 120, '...');
            $pid          = $comment['id'];
            $tid          = $comment['torrentid'];
            $comment_link = $BASEURL . '/' . get_comment_link($pid, $tid);
            $torrent_name = htmlspecialchars_uni($comment['torrent_name'] ?? $lang->member['lbl_unknown_torrent']);

            $recent_comments_html .= '<a class="pf-cmt" href="' . $comment_link . '#pid' . $pid . '">'
                . '<span class="st-ico st-soft-primary"><i class="fa-solid fa-comment"></i></span>'
                . '<span class="flex-grow-1 min-w-0">'
                .   '<span class="pf-cmt__title">' . $torrent_name . '</span>'
                .   '<span class="pf-cmt__text">' . htmlspecialchars_uni($comment_text) . '</span>'
                . '</span>'
                . '<span class="pf-cmt__date">' . $comment_date . '<br>' . $comment_time . '</span>'
                . '</a>';
        }
    } else {
        $recent_comments_html = '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-regular fa-comments"></i></div>' . hsafe($lang->member['empty_comments']) . '</div>';
    }

    // Signature (second pass if needed)
    if ($memprofile['signature'] && !$signature) {
        $sig_parser = ['allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'me_username' => $me_username, 'filter_badwords' => 1];
        $memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
        $signature = $pf_signature($memprofile['signature']);
    }

    // Strings for report_user.js: js_report_* lang keys → AGS_LANG without the js_ prefix
    $ags_js_lang = [];
    foreach ($lang->member as $js_key => $js_val) {
        if (str_starts_with((string)$js_key, 'js_report_')) {
            $ags_js_lang[substr((string)$js_key, 3)] = (string)$js_val;
        }
    }
    // Object.assign, not const: avatar_js_lang() and the SweetAlert button labels add their keys to the same object
    $ags_lang_script = '<script>window.AGS_LANG = Object.assign(window.AGS_LANG || {}, '
        . json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ');</script>';

    $formattedname = format_name($memprofile['username'], (int)($memprofile['usergroup'] ?? 1));
    $pfL = static fn(string $k, string $fb): string => hsafe((string)($lang->member[$k] ?? $fb));

    /** Gray KPI tile with a big icon in the corner (same as requests / offers / mybonus) */
    $pf_kpi = static function (string $icon, string $tone, string $value, string $label, string $extra = ''): string {
        return '<div class="st-kpi st-kpi--muted">'
             . '<span class="st-ico st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
             . '<div class="min-w-0 flex-grow-1"><div class="st-kpi__val">' . $value . '</div><div class="st-kpi__lbl">' . $label . '</div>' . $extra . '</div>'
             . '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i></div>';
    };
    $pf_bar = static fn(int $pct, string $tone): string => ($up + $down) > 0
        ? '<div class="pf-bar"><span class="bg-' . $tone . '" style="width:' . $pct . '%"></span></div>'
        : '';

    $ratio_tone = match ($ratio_class) { 'ratio-ok' => 'success', 'ratio-warn' => 'warning', default => 'danger' };
    $ratio_text = match ($ratio_class) { 'ratio-ok' => $lang->member['lbl_ratio_good'], 'ratio-warn' => $lang->member['lbl_ratio_fair'], default => $lang->member['lbl_ratio_poor'] };

    // Header buttons
    $head_btns = '';
    if ($is_own_profile) {
        $head_btns .= '<a href="usercp.php?action=profile" class="btn btn-sm btn-primary st-pill"><i class="fa-solid fa-user-pen me-1"></i>' . $pfL('btn_edit_profile', 'Edit profile') . '</a>';
    } else {
        if ($sendpm) {
            $head_btns .= '<a href="private.php?action=send&amp;uid=' . (int)$memprofile['id'] . '" class="btn btn-sm btn-primary st-pill" aria-label="' . hsafe($lang->member['aria_send_pm']) . '"><i class="fa-solid fa-envelope me-1"></i>' . hsafe($lang->member['btn_send_pm']) . '</a>';
        }
        $head_btns .= $buddy_options . $ignore_options . $report_button;
    }

    $tab_contact_btn = $contact_details !== ''
        ? '<button data-bs-toggle="pill" data-bs-target="#pf-contact" type="button" role="tab"><i class="fa-solid fa-address-card"></i>' . hsafe($lang->member['tab_contact']) . '</button>'
        : '';

    $about_rows = '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-calendar-plus"></i>' . rtrim($lang->member['joined'], ':') . '</span><span class="st-row__val">' . $memregdate . '</span></div>'
        . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-clock-rotate-left"></i>' . rtrim($lang->member['lastvisit'], ':') . '</span><span class="st-row__val">' . $memlastvisitdate . '</span></div>'
        . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-cake-candles"></i>' . rtrim($lang->member['date_of_birth'], ':') . '</span><span class="st-row__val">' . $membday . ' ' . $membdayage . '</span></div>'
        . '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-earth-europe"></i>' . rtrim($lang->member['local_time'], ':') . '</span><span class="st-row__val">' . $localtime . '</span></div>'
        . ($invs !== '' ? '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-ticket"></i>' . trim(str_replace(['{1}', '%1$s'], '', $lang->member['iby']), ' :') . '</span><span class="st-row__val">' . $invs . '</span></div>' : '');

    // Cover colour, activity map, posters, feed, BP/h
    $pfx_anon   = $is_own_profile || $IsStaff;
    $pf_color   = pfx_group_color((string)($memperms['namestyle'] ?? ''));
    $pf_group   = trim(strip_tags((string)($memperms['title'] ?? '')));
    $pf_heatmap = pfx_heatmap_html((int)$uid, $pfx_anon);
    $pf_posters = pfx_posters_html((int)$uid, $pfx_anon, 6);
    $pf_feed    = pfx_feed_html((int)$uid, $pfx_anon, 8);
    $pf_bp_chip = '';
    $pf_bp      = sbe_user_hourly((int)$uid);
    if ($pf_bp !== null && $pf_bp['torrents'] > 0) {
        $bp_num = number_format($pf_bp['hourly'], 1, '.', ' ');
        $bp_tip = ags_fmt($pfL('bp_hour_tip', 'About {1} BP a day from {2} seeding torrents'), number_format($pf_bp['daily'], 0, '.', ' '), $pf_bp['torrents']);
        $bp_in  = '<i class="fa-solid fa-coins text-warning"></i>' . ags_fmt($pfL('bp_hour', 'Earning ≈ {1} BP/h'), '<strong>' . $bp_num . '</strong>');
        $pf_bp_chip = $is_own_profile
            ? '<a class="pf-chip pf-chip--bp" href="' . $BASEURL . '/mybonus.php" title="' . $bp_tip . '">' . $bp_in . '</a>'
            : '<span class="pf-chip pf-chip--bp" title="' . $bp_tip . '">' . $bp_in . '</span>';
    }

    $profile = '
<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=12">
<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/profile.css?ver=4">
<script src="' . $BASEURL . '/scripts/toast.js"></script>
' . $ags_lang_script . '
<script src="' . $BASEURL . '/scripts/report_user.js?ver=3"></script>

<div class="container-md st-page pf-page py-3" style="--pf-c:' . hsafe($pf_color) . '">

  <!-- Header -->
  <header class="st-card st-head pf-head mb-3" style="--pf-c:' . hsafe($pf_color) . '">
    <div class="pf-cover" aria-hidden="true">' . ($pf_group !== '' ? '<span class="pf-cover__grp">' . hsafe($pf_group) . '</span>' : '') . '</div>
    <div class="pf-ava-wrap">
      <div class="pf-ava" id="avatar-container" data-uid="' . (int)$memprofile['id'] . '" data-can-change="' . $can_change_avatar . '"
           data-max-mb="' . round($avatarsize / 1024) . '" title="' . hsafe($lang->member['tip_avatar']) . '">
        <div>
          ' . $pf_avatar_img . '
          ' . $status_dot_html . '
          <span class="avatar-overlay"><i class="fa-solid fa-camera me-1"></i>' . hsafe($lang->member['lbl_change_avatar']) . '</span>
        </div>
        <div id="avatar-progress"><div id="avatar-progress-bar"></div></div>
      </div>
      <input type="file" id="avatar-input" accept="image/*" hidden>
    </div>

    <div class="flex-grow-1 min-w-0">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <h1 class="st-title mb-0">' . $formattedname . '</h1>' . $usericons . '
      </div>
      ' . ($usertitle !== '' ? '<div class="pf-utitle mt-1">' . $usertitle . '</div>' : '') . '
      ' . ($groupimage !== '' ? '<div class="mt-1">' . $groupimage . '</div>' : '') . '
      <div class="pf-chips mt-2">
        <span class="pf-chip">' . $online_status . '</span>
        <span class="pf-chip"><i class="fa-solid fa-arrow-up text-success"></i>' . hsafe(rtrim($lang->member['lbl_seeding'], ':')) . ' <strong>' . $active_seeds . '</strong></span>
        <span class="pf-chip"><i class="fa-solid fa-arrow-down text-danger"></i>' . hsafe(rtrim($lang->member['lbl_leeching'], ':')) . ' <strong>' . $active_leeches . '</strong></span>
        <span class="pf-chip"><i class="fa-regular fa-calendar"></i>' . hsafe(rtrim($lang->member['joined'], ':')) . ' <strong>' . $memregdate . '</strong></span>
        ' . $pf_bp_chip . '
      </div>
    </div>

    ' . ($head_btns !== '' ? '<div class="pf-head__btns">' . $head_btns . '</div>' : '') . '
  </header>

  ' . $pending_banner . '

  <!-- KPI tiles -->
  <div class="pf-kpis mb-3">
    ' . $pf_kpi('fa-scale-balanced', $ratio_tone, '<span class="text-' . $ratio_tone . '">' . $ratio . '</span>', hsafe($lang->member['lbl_ratio']),
            '<span class="st-badge st-soft-' . $ratio_tone . ' mt-1">' . hsafe($ratio_text) . '</span>') . '
    ' . $pf_kpi('fa-cloud-arrow-up', 'success', $uploaded, hsafe($lang->member['lbl_uploaded']), $pf_bar($uploaded_percent, 'success')) . '
    ' . $pf_kpi('fa-cloud-arrow-down', 'danger', $downloaded, hsafe($lang->member['lbl_downloaded']), $pf_bar($downloaded_percent, 'danger')) . '
    ' . $pf_kpi('fa-circle-check', 'primary', $times_completed_total, hsafe($lang->member['lbl_snatched']), $claims_line) . '
  </div>

  <div class="row g-3">
    <div class="col-lg-8 min-w-0">

      <nav class="pf-tabs mb-3" role="tablist">
        <button class="active" data-bs-toggle="pill" data-bs-target="#pf-about" type="button" role="tab"><i class="fa-solid fa-user"></i>' . hsafe($lang->member['tab_about']) . '</button>
        <button data-bs-toggle="pill" data-bs-target="#pf-forum" type="button" role="tab"><i class="fa-solid fa-comments"></i>' . hsafe($lang->member['tab_forum']) . '<span class="pf-tabs__n">' . ts_nf((int)$memprofile['postnum']) . '</span></button>
        <button data-bs-toggle="pill" data-bs-target="#pf-torrents" type="button" role="tab"><i class="fa-solid fa-layer-group"></i>' . hsafe($lang->member['tab_torrents']) . '<span class="pf-tabs__n">' . $times_completed_total . '</span></button>
        ' . $tab_contact_btn . '
      </nav>

      <div class="tab-content">

        <!-- About -->
        <div class="tab-pane fade show active" id="pf-about" role="tabpanel">
          ' . $bannedbit . '
          ' . $pf_heatmap . '
          ' . $pf_posters . '
          <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-bolt"></i>' . $pfL('sec_feed', 'Recent activity') . '</div>
            ' . $pf_feed . '
          </section>
          <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-id-card"></i>' . hsafe($lang->member['tab_about']) . '</div>
            <div class="st-card__body"><div class="st-rows">' . $about_rows . '</div></div>
          </section>
          ' . $signature . '
        </div>

        <!-- Forum -->
        <div class="tab-pane fade" id="pf-forum" role="tabpanel">
          <div class="pf-forum mb-3">
            <div class="st-kpi st-kpi--muted">
              <span class="st-ico st-soft-primary"><i class="fa-solid fa-message"></i></span>
              <div class="min-w-0 flex-grow-1"><div class="st-kpi__val">' . ts_nf((int)$memprofile['postnum']) . '</div><div class="st-kpi__lbl">' . rtrim($lang->member['total_posts'], ':') . ' &middot; ' . $ppd_percent_total . '</div></div>
              ' . ($findposts !== '' ? '<div class="pf-forum__btn">' . $findposts . '</div>' : '') . '
              <i class="fa-solid fa-message st-kpi__bg" aria-hidden="true"></i>
            </div>
            <div class="st-kpi st-kpi--muted">
              <span class="st-ico st-soft-success"><i class="fa-solid fa-layer-group"></i></span>
              <div class="min-w-0 flex-grow-1"><div class="st-kpi__val">' . ts_nf((int)$memprofile['threadnum']) . '</div><div class="st-kpi__lbl">' . rtrim($lang->member['total_threads'], ':') . ' &middot; ' . $tpd_percent_total . '</div></div>
              ' . ($findthreads !== '' ? '<div class="pf-forum__btn">' . $findthreads . '</div>' : '') . '
              <i class="fa-solid fa-layer-group st-kpi__bg" aria-hidden="true"></i>
            </div>
          </div>
          <section class="st-card">
            <div class="st-card__head"><i class="fa-solid fa-comment-dots"></i>' . hsafe($lang->member['sec_recent_comments']) . '<span class="st-badge st-soft-muted ms-auto">' . ts_nf((int)$memprofile['comms']) . '</span></div>
            <div class="pf-cmts">' . $recent_comments_html . '</div>
          </section>
        </div>

        <!-- Torrents -->
        <div class="tab-pane fade" id="pf-torrents" role="tabpanel">
          <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-cloud-arrow-up"></i>' . hsafe($lang->member['sec_uploaded_torrents']) . '</div>
            <div class="st-card__body pf-tlist">' . $recent_user_torrents . '</div>
          </section>
          <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-arrow-up"></i>' . hsafe($lang->member['sec_seeding_now']) . '<span class="st-badge st-soft-success ms-auto">' . $active_seeds . '</span></div>
            <div class="st-card__body pf-tlist">' . $seeding_now . '</div>
          </section>
          <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-arrow-down"></i>' . hsafe($lang->member['sec_leeching_now']) . '<span class="st-badge st-soft-danger ms-auto">' . $active_leeches . '</span></div>
            <div class="st-card__body pf-tlist">' . $leeching_now . '</div>
          </section>
          <section class="st-card">
            <div class="st-card__head"><i class="fa-solid fa-circle-check"></i>' . hsafe($lang->member['sec_completed_recently']) . '<span class="st-badge st-soft-primary ms-auto">' . $times_completed_total . '</span></div>
            <div class="st-card__body pf-tlist">' . $completed_list . '</div>
          </section>
        </div>

        ' . ($contact_details !== '' ? '<div class="tab-pane fade" id="pf-contact" role="tabpanel">' . $contact_details . '</div>' : '') . '
      </div>
    </div>

    <!-- Side -->
    <div class="col-lg-4">
      <div class="pf-side">
        <section class="st-card mb-3">
          <div class="st-card__head"><i class="fa-solid fa-signal"></i>' . hsafe($lang->member['sec_status']) . '</div>
          <div class="st-card__body"><div class="st-rows">
            <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-circle-dot"></i>' . rtrim($lang->global['postbit_status'], ': ') . '</span><span class="st-row__val">' . $online_status . '</span></div>
            <div class="st-row"><span class="st-row__lbl"><i class="fa-solid fa-hourglass-half"></i>' . rtrim($lang->member['timeonline'], ':') . '</span><span class="st-row__val">' . $timeonline . '</span></div>
          </div></div>
        </section>
        <div class="pf-mini mb-3">
          <div class="pf-mini__i"><span>' . ts_nf((int)$memprofile['postnum']) . '</span>' . hsafe($lang->member['stat_posts']) . '</div>
          <div class="pf-mini__i"><span>' . ts_nf((int)$memprofile['threadnum']) . '</span>' . hsafe($lang->member['stat_threads']) . '</div>
          <div class="pf-mini__i"><span class="text-success">' . $active_seeds . '</span>' . hsafe($lang->member['stat_seeds']) . '</div>
          <div class="pf-mini__i"><span class="text-danger">' . $active_leeches . '</span>' . hsafe($lang->member['stat_leeches']) . '</div>
        </div>
        ' . $modoptions . '
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  // remember the open tab in the URL hash (#pf-forum ...)
  var btns = document.querySelectorAll(".pf-tabs [data-bs-target]");
  if (!btns.length || !window.bootstrap) return;
  var h = location.hash && document.querySelector(".pf-tabs [data-bs-target=\"" + location.hash.replace(/"/g, "") + "\"]");
  if (h) bootstrap.Tab.getOrCreateInstance(h).show();
  document.querySelectorAll("[data-pf-tab]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      var b = document.querySelector(".pf-tabs [data-bs-target=\"" + a.getAttribute("data-pf-tab") + "\"]");
      if (!b) return;
      e.preventDefault();
      bootstrap.Tab.getOrCreateInstance(b).show();
      b.scrollIntoView({ block: "nearest", behavior: "smooth" });
    });
  });
  btns.forEach(function (b) { b.addEventListener("shown.bs.tab", function () { history.replaceState(null, "", b.getAttribute("data-bs-target")); }); });
});
</script>';

    echo $profile;
    
	?>

    <div class="modal fade" id="reportUserModal" tabindex="-1" aria-labelledby="reportUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="reportUserForm" method="POST" action="<?= $BASEURL ?>/takereport.php">
                    <input type="hidden" name="type" value="user">
                    <input type="hidden" name="reported_id" value="<?= (int)$memprofile['id'] ?>">
                    <input type="hidden" name="reported_user_id" value="<?= (int)$memprofile['id'] ?>">
                    <input type="hidden" name="addedby" value="<?= (int)$CURUSER['id'] ?>">
                    <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="reportUserModalLabel" data-username="<?= hsafe($me_username) ?>">
                            <i class="fa-solid fa-flag me-2"></i><?= hsafe(ags_fmt($lang->member['sec_report_user'], $me_username)) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= hsafe($lang->member['cans']) ?>"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fa-solid fa-circle-exclamation me-2"></i><?= hsafe($lang->member['sec_report_reason']) ?></h6>
                            <div class="row g-3">
                                <?php foreach ($reasons_map as $key => $reason): ?>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="reason" id="reason_<?= $key ?>" value="<?= $key ?>" autocomplete="off">
                                    <label class="btn btn-outline-danger w-100 text-start py-3" for="reason_<?= $key ?>">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3"><i class="fa-solid <?= $reason['icon'] ?> fa-2x"></i></div>
                                            <div>
                                                <div class="fw-bold"><?= hsafe($reason['text']) ?></div>
                                                <div class="small text-muted mt-1"><?= hsafe($reason['description']) ?></div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fa-solid fa-file-alt me-2"></i><?= hsafe($lang->member['sec_report_details']) ?></h6>
                            <div class="mb-3">
                                <label for="reportDescription" class="form-label fw-bold"><?= hsafe($lang->member['lbl_report_description']) ?> <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="reportDescription" name="description" rows="5" placeholder="<?= hsafe($lang->member['ph_report_description']) ?>" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="additionalInfo" class="form-label"><?= hsafe($lang->member['lbl_report_additional']) ?></label>
                                <textarea class="form-control" id="additionalInfo" name="additional_info" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="evidenceLinks" class="form-label"><i class="fa-solid fa-link me-1"></i><?= hsafe($lang->member['lbl_report_evidence']) ?></label>
                                <input type="text" class="form-control" id="evidenceLinks" name="evidence_links" placeholder="<?= hsafe($lang->member['ph_report_evidence']) ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0"><i class="fa-solid fa-shield-halved me-2"></i><?= hsafe($lang->member['sec_security_check']) ?></h6>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                                        id="userReportRefreshCaptcha" title="<?= hsafe($lang->member['tip_captcha_refresh']) ?>" aria-label="<?= hsafe($lang->member['tip_captcha_refresh']) ?>">
                                    <i class="fa-solid fa-arrows-rotate"></i>
                                </button>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-6">
                                    <img src="report_captcha.php" alt="<?= hsafe($lang->member['alt_captcha']) ?>" class="border rounded"
                                         id="userReportCaptchaDisplay" style="cursor:pointer;height:56px;width:100%;object-fit:cover;"
                                         title="<?= hsafe($lang->member['tip_captcha_refresh']) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control"
                                           id="userReportCaptchaInput" name="captcha_response" placeholder="<?= hsafe($lang->member['ph_captcha']) ?>" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <strong><?= hsafe($lang->member['lbl_important']) ?></strong> <?= hsafe($lang->member['hint_report_warning']) ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa-solid fa-times me-1"></i> <?= hsafe($lang->member['cans']) ?></button>
                        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-paper-plane me-1"></i> <?= hsafe($lang->member['btn_report_submit']) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
	
	
	
	
	
	

    stdfoot();
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_emailuser
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_emailuser' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('member_do_emailuser_start');

    if ($usergroups['cansendemail'] == 0) { print_no_permission(); }

    $errors = [];

    

    $query   = $db->sql_query_prepared("SELECT id, username, email, hideemail FROM users WHERE id = ?", [$mybb->get_input('id', MyBB::INPUT_INT)]);
    $to_user = $query ? $db->fetch_array($query) : null;

    if (!$to_user['username']) { stderr($lang->member['error_invalidusername']); }
    if ($to_user['hideemail'] != 0) { stderr($lang->member['error_hideemail']); }

    if ($CURUSER['id']) {
        $mybb->input['fromemail'] = $CURUSER['email'];
        $mybb->input['fromname']  = $CURUSER['username'];
    }

    if (!validate_email_format($mybb->input['fromemail']))  { $errors[] = $lang->member['error_invalidfromemail']; }
    if (empty($mybb->input['fromname']))                    { $errors[] = $lang->member['error_noname']; }
    if (empty($mybb->input['subject']))                     { $errors[] = $lang->member['error_no_email_subject']; }
    if (empty($mybb->input['message']))                     { $errors[] = $lang->member['error_no_email_message']; }

    if (empty($errors)) {
        $from    = $mail_handler === 'smtp' ? $mybb->input['fromemail'] : "{$mybb->input['fromname']} <{$mybb->input['fromemail']}>";
        $message = sprintf($lang->member['email_emailuser'], $to_user['username'], $mybb->input['fromname'], $SITENAME, $BASEURL, $mybb->get_input('message'));
        my_mail($to_user['email'], $mybb->get_input('subject'), $message, '', '', '', false, 'text', '', $from);

        if ($mail_logging > 0) {
            $db->sql_query_prepared(
                "INSERT INTO maillogs (`subject`,`message`,`dateline`,`fromuid`,`fromemail`,`touid`,`toemail`,`tid`,`ipaddress`,`type`) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $mybb->get_input('subject'),
                    $mybb->get_input('message'),
                    TIMENOW,
                    $CURUSER['id'],
                    $mybb->input['fromemail'],
                    $to_user['id'],
                    $to_user['email'],
                    0,
                    $session->packedip,
                    1,
                ]
            );
        }

        $plugins->run_hooks('member_do_emailuser_end');
        redirect(get_profile_link($to_user['id']), $lang->member['redirect_emailsent']);
    } else {
        $mybb->input['action'] = 'emailuser';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: emailuser
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'emailuser') {
    $plugins->run_hooks('member_emailuser_start');

    if ($usergroups['cansendemail'] == 0) { print_no_permission(); }

    $query   = $db->sql_query_prepared("SELECT id, username, email, hideemail, ignorelist FROM users WHERE id = ?", [$mybb->get_input('id', MyBB::INPUT_INT)]);
    $to_user = $query ? $db->fetch_array($query) : null;
    $to_user['username'] = htmlspecialchars_uni($to_user['username']);
    $email_user = sprintf($lang->member['email_user'], $to_user['username']);

    if (!$to_user['id'])          { stderr($lang->member['error_invaliduser']); }
    if ($to_user['hideemail'] != 0) { stderr($lang->member['error_hideemail']); }
    if ($to_user['ignorelist'] && str_contains(',' . $to_user['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') && $usergroups['cansendemailoverride'] != 1) {
        print_no_permission();
    }

    if (!empty($errors) && count($errors) > 0) {
        $errors    = inline_error($errors);
        $fromname  = htmlspecialchars_uni($mybb->get_input('fromname'));
        $fromemail = htmlspecialchars_uni($mybb->get_input('fromemail'));
        $subject   = htmlspecialchars_uni($mybb->get_input('subject'));
        $message   = htmlspecialchars_uni($mybb->get_input('message'));
    } else {
        $errors = $fromname = $fromemail = $subject = $message = '';
    }

    $from_email = '';
    if ($CURUSER['id'] == 0) 
	{ 
         $from_email = '<div class="au-grid">'
             . au_field('fromname', 'fromname', $lang->member['your_name'], 'fa-user', 'text', $fromname, 'required')
             . au_field('fromemail', 'fromemail', $lang->member['your_email'], 'fa-envelope', 'email', $fromemail, 'required')
             . '</div>';
		 
	}

    $plugins->run_hooks('member_emailuser_end');
	

$emailuser = au_page('fa-envelope', 'primary', $email_user, '',
    $errors
    . '<form action="member.php" method="post">'
    . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">'
    . '<input type="hidden" name="action" value="do_emailuser">'
    . '<input type="hidden" name="id" value="' . (int)$to_user['id'] . '">'
    . $from_email
    . au_field('subject', 'subject', $lang->member['email_subject'], 'fa-heading', 'text', $subject, 'required')
    . '<div class="au-field"><label class="st-label" for="message">' . au_label($lang->member['email_message']) . '</label>'
    . '<textarea class="form-control" id="message" name="message" rows="9" required>' . $message . '</textarea></div>'
    . '<div class="d-flex flex-wrap gap-2">'
    .   '<button type="submit" class="btn btn-primary st-pill flex-grow-1"><i class="fa-solid fa-paper-plane me-2"></i>' . $lang->member['send_email'] . '</button>'
    .   '<a href="javascript:history.back()" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-xmark me-1"></i>' . $lang->member['cans'] . '</a>'
    . '</div>'
    . '</form>', '', true);



	
    stdhead($lang->member['nav_emailuser']);
    echo $emailuser;
    stdfoot();
}

// ── Default redirect ───────────────────────────────────────────────────────
if (!$mybb->input['action']) {
    header('Location: index.php');
}