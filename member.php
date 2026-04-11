<?php
declare(strict_types=1);

/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/***********************************************/

define('IN_MYBB', 1);
define('IGNORE_CLEAN_VARS', 'sid');
define('THIS_SCRIPT', 'member.php');
define('SCRIPTNAME', 'member.php');
define('ALLOWABLE_PAGE', 'register,do_register,login,do_login,logout,lostpw,do_lostpw,activate,resendactivation,do_resendactivation,resetpassword,viewnotes');

$nosession['avatar'] = 1;

$templatelist  = 'maketable_torrents,torrents_completed,user_profile,torrent_stats,member_register,member_register_hiddencaptcha,member_register_agreement,member_register_customfield,member_register_requiredfields,member_profile_findthreads';
$templatelist .= ',member_loggedin_notice,member_profile_away,member_register_regimage,member_register_regimage_recaptcha_invisible,member_register_regimage_nocaptcha,post_captcha_hcaptcha_invisible';
$templatelist .= ',member_profile_email,member_profile_offline,member_profile_customfields_field,member_profile_customfields,member_profile_adminoptions_manageban,member_profile_adminoptions,member_profile';
$templatelist .= ',member_profile_signature,member_profile_avatar,member_profile_groupimage,member_referrals_link,member_profile_referrals,member_activate,member_lostpw,member_register_additionalfields';
$templatelist .= ',member_profile_modoptions_manageuser,member_profile_modoptions_editprofile,member_profile_modoptions_banuser,member_profile_modoptions_viewnotes,member_profile_modoptions_editnotes';
$templatelist .= ',usercp_profile_profilefields_select_option,usercp_profile_profilefields_multiselect,usercp_profile_profilefields_select,usercp_profile_profilefields_textarea,usercp_profile_profilefields_radio,member_viewnotes';
$templatelist .= ',usercp_options_timezone,usercp_options_timezone_option,usercp_options_language_option,member_profile_customfields_field_multi_item,member_profile_customfields_field_multi';
$templatelist .= ',member_profile_pm,member_profile_contact_details,member_profile_modoptions_manageban';
$templatelist .= ',member_profile_banned_remaining,member_profile_addremove,member_emailuser_guest,member_register_day,usercp_options_tppselect_option,postbit_warninglevel_formatted,member_profile_userstar,member_profile_findposts';
$templatelist .= ',usercp_options_tppselect,usercp_options_pppselect,member_resetpassword,member_login,member_profile_online,usercp_options_pppselect_option,postbit_reputation_formatted,member_emailuser,usercp_profile_profilefields_text';
$templatelist .= ',member_profile_modoptions_ipaddress,member_profile_modoptions,member_profile_banned,member_register_language,member_resendactivation,usercp_profile_profilefields_checkbox,member_register_password,torrent_stats';

require_once 'global.php';

define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once INC_PATH . '/tsf_functions.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_timezone.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/function_loginattemptcheck.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_modcp.php';
require_once INC_PATH . '/class_parser.php';

$parser = new postParser();

// ── Helpers ────────────────────────────────────────────────────────────────

if (!function_exists('hsafe')) {
    function hsafe(mixed $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        $is_ajax ? $json(['ok' => false, 'error' => 'Не авторизован'], 401) : exit('Error: вы не авторизованы.');
    }

    $user_uid = (int)($CURUSER['id'] ?? 0);
    if ($user_uid <= 0) {
        $is_ajax ? $json(['ok' => false, 'error' => 'Не авторизован'], 401) : exit('Error: вы не авторизованы.');
    }

    $uid = (int)($_POST['id'] ?? $_GET['id'] ?? $memprofile['id'] ?? 0);
    if ($uid <= 0) {
        $is_ajax ? $json(['ok' => false, 'error' => 'Не указан uid профиля'], 400) : exit('Error: не указан uid профиля.');
    }

    if ($user_uid !== $uid && !is_mod($usergroups)) {
        $is_ajax ? $json(['ok' => false, 'error' => 'Нет прав менять этот аватар'], 403) : exit('Error: нет прав менять этот аватар.');
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $is_ajax ? $json(['ok' => false, 'error' => 'Файл не загружен'], 400) : exit('Error: file is not uploaded.');
    }

    $max_size    = 22 * 1024 * 1024;
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    $file_name = $_FILES['avatar']['name'];
    $file_tmp  = $_FILES['avatar']['tmp_name'];
    $file_size = $_FILES['avatar']['size'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_ext, true)) {
        $is_ajax ? $json(['ok' => false, 'error' => 'Allowed JPG/JPEG/PNG/GIF/WebP'], 415) : exit('Error: Allowed JPG/JPEG/PNG/GIF/WebP.');
    }
    if ($file_size > $max_size) {
        $is_ajax ? $json(['ok' => false, 'error' => 'Файл слишком большой (макс. 22 MB)'], 413) : exit('Error: file is too big (max. 22 MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        $is_ajax ? $json(['ok' => false, 'error' => 'file is not image'], 415) : exit('Error: file is not image.');
    }

    $upload_dir = TSDIR . '/uploads/avatars/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $new_name  = "avatar_{$uid}.{$file_ext}";
    $dest_path = $upload_dir . $new_name;

    foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $e) {
        if ($e !== $file_ext) {
            $p = $upload_dir . "avatar_{$uid}.{$e}";
            if (is_file($p)) { @unlink($p); }
        }
    }

    if (!move_uploaded_file($file_tmp, $dest_path)) {
        $is_ajax ? $json(['ok' => false, 'error' => 'Не удалось сохранить файл'], 500) : exit('Ошибка: не удалось сохранить файл.');
    }

    $size = @getimagesize($dest_path);
    if (!$size) {
        @unlink($dest_path);
        $is_ajax ? $json(['ok' => false, 'error' => 'Файл повреждён или не изображение'], 415) : exit('Ошибка: файл повреждён или не изображение.');
    }

    [$width, $height] = $size;
    $avatar_dimensions = $width . '|' . $height;
    $avatar_url        = 'uploads/avatars/' . $new_name;

    $db->update_query('users', [
        'avatar'           => $avatar_url,
        'avatardimensions' => $avatar_dimensions,
        'avatartype'       => 'upload',
    ], "id='{$uid}'");

    if ($is_ajax) {
        $json(['ok' => true, 'url' => $avatar_url, 'width' => $width, 'height' => $height, 'message' => 'Аватар обновлён']);
    }

    header("Location: member.php?action=profile&uid={$uid}");
    exit;
}

// ── Ban times ──────────────────────────────────────────────────────────────
function fetch_ban_times(): array
{
    global $plugins;

    $ban_times = [
        '1-0-0'  => '1 Day',   '2-0-0'  => '2 Days',  '3-0-0'  => '3 Days',
        '4-0-0'  => '4 Days',  '5-0-0'  => '5 Days',  '6-0-0'  => '6 Days',
        '7-0-0'  => '1 Week',  '14-0-0' => '2 Weeks', '21-0-0' => '3 Weeks',
        '0-1-0'  => '1 Month', '0-2-0'  => '2 Months','0-3-0'  => '3 Months',
        '0-4-0'  => '4 Months','0-5-0'  => '5 Months','0-6-0'  => '6 Months',
        '0-0-1'  => '1 Year',  '0-0-2'  => '2 Years',
    ];

    $ban_times = $plugins->run_hooks('functions_fetch_ban_times', $ban_times);
    $ban_times['---'] = 'Permanent';
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

// ── Active swarm ───────────────────────────────────────────────────────────
function build_user_active_swarm(object $db, int $uid, int $limit = 10): array
{
    global $BASEURL, $dateformat, $timeformat;

    $uid   = max(0, $uid);
    $limit = max(1, $limit);

    $render = static function (string $seeder_val) use ($db, $uid, $limit, $BASEURL, $dateformat, $timeformat): array {
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
                    <div class="text-muted small">Nothing active right now</div>
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
                                <span class="text-muted">Progress</span>
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
    global $BASEURL, $dateformat;

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
                <div class="text-secondary fw-medium">No uploads yet.</div>
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
                                <a href="' . $BASEURL . '/download.php/' . $id . '.torrent" class="btn btn-primary btn-sm rounded-pill" title="Download">
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
    global $BASEURL, $dateformat, $timeformat;

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
                <div class="text-secondary fw-medium">No completed history.</div>
                <div class="small text-muted">Completed torrents will appear here</div>
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

$lang->load('member');

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
if (in_array($mybb->input['action'], ['register', 'do_register'], true) && $mybb->usergroup['cancp'] != 1) {
    if ($disableregs == 1) { stderr($lang->member['registrations_disabled']); }

    if ((int)$maxusers > 0) {
        $count = $db->num_rows($db->sql_query('SELECT id FROM users WHERE id > 0'));
        if ($maxusers <= $count) { stderr($lang->global['signuplimitreached']); }
    }

    if ($CURUSER['id'] != 0) { stderr($lang->member['error_alreadyregistered']); }

    if ($betweenregstime && $maxregsbetweentime) {
        $datecut = TIMENOW - (60 * 60 * $betweenregstime);
        $query   = $db->simple_select('users', '*', 'regip=' . $db->escape_binary($session->packedip) . " AND added > '{$datecut}'");
        $regcount = $db->num_rows($query);
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

    if ($mybb->settings['regtime'] > 0) {
        if (isset($mybb->input['regtime'])) {
            $timetook = TIMENOW - $mybb->get_input('regtime', MyBB::INPUT_INT);
            if ($timetook < $mybb->settings['regtime']) {
                error(sprintf($lang->error_spam_deny_time, $mybb->settings['regtime'], $timetook));
            }
        } else {
            error($lang->error_spam_deny);
        }
    }

    $password_length = (int)$minpasswordlength;
    if ($regtype === 'randompass') {
        if ($password_length < 8) { $password_length = min(8, (int)$maxpasswordlength); }
        $mybb->input['password']  = random_str($password_length, $requirecomplexpasswords);
        $mybb->input['password2'] = $mybb->input['password'];
    }

    $usergroup = in_array($regtype, ['verify', 'admin', 'both'], true) ? 5 : 2;

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
        'profile_fields' => $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY),
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

        if ($regtype !== 'randompass') {
           my_setcookie('mybbuser', $user_info['uid'] . '_' . $user_info['loginkey'], null, true, 'lax');
        }

         
		
		if ($regtype === 'verify') {
            $activationcode = random_str();
            $db->insert_query('awaitingactivation', ['uid' => $user_info['uid'], 'dateline' => TIMENOW, 'code' => $activationcode, 'type' => 'r']);
            $emailsubject = sprintf($lang->member['emailsubject_activateaccount'], $SITENAME);
            $emailmessage = sprintf($lang->member['email_activateaccount' . ($username_method ?: '')], $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
            my_mail($user_info['email'], $emailsubject, $emailmessage);
            $plugins->run_hooks('member_do_register_end');
            
			 
			stdok(
    message: sprintf($lang->member['redirect_registered_activation'], $SITENAME, htmlspecialchars_uni($user_info['username'])),
    title:   'Registration successful',
    subtitle: 'Your account has been created.'
); 
			 
			 

			
        } 
		elseif ($regtype === 'randompass') {
            $emailsubject = sprintf($lang->member['emailsubject_randompassword'], $SITENAME);
            $emailmessage = sprintf($lang->member['email_randompassword' . ($username_method ?: '')], $user['username'], $SITENAME, $user_info['username'], $mybb->get_input('password'));
            my_mail($user_info['email'], $emailsubject, $emailmessage);
            $db->update_query('users', ['ustatus' => 'confirmed'], "id='{$user_info['uid']}' AND ustatus='pending' AND enabled='yes'");
            require_once INC_PATH . '/functions_pm.php';
            $pm = ['subject' => sprintf($lang->member['welcomepmsubject'], $SITENAME), 'message' => sprintf($lang->member['welcomepmbody'], $user_info['username'], $SITENAME, $BASEURL), 'touid' => $user_info['uid']];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
            $plugins->run_hooks('member_do_register_end');
           
			stdok(
    message: sprintf($lang->member['redirect_registered_passwordsent']),
    title:   'Registration successful',
    subtitle: 'Your account has been created.'
);
			
			
        } elseif ($regtype === 'admin') {
            $plugins->run_hooks('member_do_register_end');
            
			stdok(
    message: sprintf($lang->member['redirect_registered_admin_activate'], $SITENAME, htmlspecialchars_uni($user_info['username'])),
    title:   'Registration successful',
    subtitle: 'Your account has been created.'
);
			
			
        } elseif ($regtype === 'both') {
            $activationcode = random_str();
            $db->insert_query('awaitingactivation', ['uid' => $user_info['uid'], 'dateline' => TIMENOW, 'code' => $activationcode, 'type' => 'b']);
            $emailmessage = sprintf($lang->email_activateaccount . ($username_method ?: ''), $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
            my_mail($user_info['email'], sprintf($lang->emailsubject_activateaccount, $SITENAME), $emailmessage);
            $plugins->run_hooks('member_do_register_end');
           
			stdok(
    message: sprintf($lang->member['redirect_registered_activation'], $SITENAME, htmlspecialchars_uni($user_info['username'])),
    title:   'Registration successful',
    subtitle: 'Your account has been created.'
);
			
			
			
        } else {
            $db->update_query('users', ['ustatus' => 'confirmed'], "id='{$user_info['uid']}' AND ustatus='pending' AND enabled='yes'");
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

        $tppselect = $pppselect = '';
        if ($usertppoptions) {
            $tppoptions = '';
            foreach (array_map('trim', explode(',', $usertppoptions)) as $val) {
                $tpp_option = sprintf($lang->member['tpp_option'], $val);
                eval("\$tppoptions .= \"".$templates->get('usercp_options_tppselect_option')."\";");
            }
            eval("\$tppselect = \"".$templates->get('usercp_options_tppselect')."\";");
        }
        if ($userpppoptions) {
            $pppoptions = '';
            foreach (array_map('trim', explode(',', $userpppoptions)) as $val) {
                $ppp_option = sprintf($lang->member['ppp_option'], $val);
                eval("\$pppoptions .= \"".$templates->get('usercp_options_pppselect_option')."\";");
            }
            eval("\$pppselect = \"".$templates->get('usercp_options_pppselect')."\";");
        }

        $mybb->input['profile_fields'] = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY);
        $altbg          = 'trow1';
        $requiredfields = $customfields = '';
        $usergroup = in_array($regtype, ['verify', 'admin', 'both'], true) ? 5 : 2;
        $pfcache        = $cache->read('profilefields');
        $jsvar_reqfields = [];

        if (is_array($pfcache)) {
            foreach ($pfcache as $profilefield) {
                if ($profilefield['required'] != 1 && $profilefield['registration'] != 1
                    || !is_member($profilefield['editableby'], ['usergroup' => $mybb->user['usergroup'], 'additionalgroups' => $usergroup])) {
                    continue;
                }

                $code = $select = $val = $options = $expoptions = $useropts = '';
                $seloptions = [];
                $profilefield['type']        = htmlspecialchars_uni($profilefield['type']);
                $profilefield['description'] = htmlspecialchars_uni($profilefield['description']);
                $profilefield['name']        = htmlspecialchars_uni($profilefield['name']);
                $thing   = explode("\n", $profilefield['type'], 2);
                $type    = trim($thing[0]);
                $options = $thing[1] ?? null;
                $field   = 'fid' . $profilefield['fid'];
                $userfield = (!empty($errors) && isset($mybb->input['profile_fields'][$field])) ? $mybb->input['profile_fields'][$field] : '';

                if (in_array($type, ['multiselect', 'checkbox'], true)) {
                    $useropts = !empty($errors) ? $userfield : explode("\n", $userfield);
                    if (is_array($useropts)) {
                        foreach ($useropts as $v) { $seloptions[$v] = $v; }
                    }
                }

                switch ($type) {
                    case 'multiselect':
                        foreach (explode("\n", (string)$options) as $val) {
                            $val = str_replace("\n", "\\n", trim($val));
                            $sel = (isset($seloptions[$val]) && $val == $seloptions[$val]) ? ' selected="selected"' : '';
                            eval("\$select .= \"".$templates->get('usercp_profile_profilefields_select_option')."\";");
                        }
                        if (!$profilefield['length']) { $profilefield['length'] = 3; }
                        eval("\$code = \"".$templates->get('usercp_profile_profilefields_multiselect')."\";");
                        break;
                    case 'select':
                        foreach (explode("\n", (string)$options) as $val) {
                            $val = str_replace("\n", "\\n", trim($val));
                            $sel = $val == $userfield ? ' selected="selected"' : '';
                            eval("\$select .= \"".$templates->get('usercp_profile_profilefields_select_option')."\";");
                        }
                        if (!$profilefield['length']) { $profilefield['length'] = 1; }
                        eval("\$code = \"".$templates->get('usercp_profile_profilefields_select')."\";");
                        break;
                    case 'radio':
                        foreach (explode("\n", (string)$options) as $val) {
                            $checked = $val == $userfield ? 'checked="checked"' : '';
                            eval("\$code .= \"".$templates->get('usercp_profile_profilefields_radio')."\";");
                        }
                        break;
                    case 'checkbox':
                        foreach (explode("\n", (string)$options) as $val) {
                            $checked = (isset($seloptions[$val]) && $val == $seloptions[$val]) ? 'checked="checked"' : '';
                            eval("\$code .= \"".$templates->get('usercp_profile_profilefields_checkbox')."\";");
                        }
                        break;
                    case 'textarea':
                        $value = htmlspecialchars_uni($userfield);
                        eval("\$code = \"".$templates->get('usercp_profile_profilefields_textarea')."\";");
                        break;
                    default:
                        $value     = htmlspecialchars_uni($userfield);
                        $maxlength = $profilefield['maxlength'] > 0 ? ' maxlength="' . $profilefield['maxlength'] . '"' : '';
                        eval("\$code = \"".$templates->get('usercp_profile_profilefields_text')."\";");
                        break;
                }

                if ($profilefield['required'] == 1) {
                    if ($type !== 'select') { $jsvar_reqfields[] = ['type' => $type, 'fid' => $field]; }
                    eval("\$requiredfields .= \"".$templates->get('member_register_customfield')."\";");
                } else {
                    eval("\$customfields .= \"".$templates->get('member_register_customfield')."\";");
                }
            }

            if ($requiredfields) { eval("\$requiredfields = \"".$templates->get('member_register_requiredfields')."\";"); }
            if ($customfields)   { eval("\$customfields = \"".$templates->get('member_register_additionalfields')."\";"); }
        }

        if (!$fromreg) {
            $allownoticescheck = 'checked="checked"';
            $hideemailcheck = $invisiblecheck = $pmnotifycheck = '';
            $receivepmscheck = $pmnoticecheck = 'checked="checked"';
            $no_auto_subscribe_selected = $instant_email_subscribe_selected = $instant_pm_subscribe_selected = $no_subscribe_selected = '';
            $dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';
            $username = $email = $email2 = $regerrors = '';
        }

        

        $passboxes = '';
        if ($regtype !== 'randompass') {
            $js_validator_password_length = sprintf($lang->member['js_validator_password_length'], $minpasswordlength);
            if ($requirecomplexpasswords == 1) {
                $lang->member['password'] = $lang->member['complex_password'] = sprintf($lang->member['complex_password'], $minpasswordlength);
            }
            eval("\$passboxes = \"".$templates->get('member_register_password')."\";");
        }

        $invitehash = htmlspecialchars_uni(
            $_POST['invitehash'] ?? 
            $_GET['invitehash'] ?? 
            $_GET['invite'] ?? 
            $_POST['invite'] ?? 
            ''
        );
		
        $showinvitecode = '';
        if ($regtype === 'invite') {
            $showinvitecode = '<div class="py-3">' . $lang->member['invitecode']
                . '<input type="text" class="form-control form-control-sm border" name="invitehash" id="invitehash" value="' . $invitehash . '" />'
                . '</div>';
        }

        $time            = TIMENOW;
        $jsvar_reqfields = json_encode($jsvar_reqfields);

        $validator_javascript = '<script type="text/javascript">
            var regsettings = {
                requiredfields: \'' . $jsvar_reqfields . '\',
                minnamelength: \'' . $minnamelength . '\',
                maxnamelength: \'' . $maxnamelength . '\',
                minpasswordlength: \'' . $minpasswordlength . '\',
                questionexists: \'' . $question_exists . '\',
                requirecomplexpasswords: \'' . $requirecomplexpasswords . '\',
                regtype: \'' . $regtype . '\'
            };
            lang.js_validator_no_username = \'' . $lang->member['js_validator_no_username'] . '\';
            lang.js_validator_username_length = \'' . $js_validator_username_length . '\';
            lang.js_validator_invalid_email = \'' . $lang->member['js_validator_invalid_email'] . '\';
            lang.js_validator_email_match = \'' . $lang->member['js_validator_email_match'] . '\';
            lang.js_validator_not_empty = \'' . $lang->member['js_validator_not_empty'] . '\';
            lang.js_validator_password_length = \'' . $lang->member['js_validator_password_length'] . '\';
            lang.js_validator_password_matches = \'' . $lang->member['js_validator_password_matches'] . '\';
            lang.js_validator_no_image_text = \'' . $lang->member['js_validator_no_image_text'] . '\';
            lang.js_validator_no_security_question = \'' . $lang->member['js_validator_no_security_question'] . '\';
            lang.js_validator_bad_password_security = \'' . $lang->member['js_validator_bad_password_security'] . '\';
        </script>' . "\n";

        $plugins->run_hooks('member_register_end');
        eval("\$registration = \"".$templates->get('member_register')."\";");
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
        $user = get_user_by_username($mybb->get_input('username'), ['username_method' => $username_method, 'fields' => '*']);
        if (!$user) { stderr('error_invalidpworusername'); }
        $uid = $user['id'];
    } else {
        $user = get_user($mybb->get_input('id', MyBB::INPUT_INT));
    }

    if (isset($mybb->input['code']) && $user) {
        $query      = $db->simple_select('awaitingactivation', '*', "uid='{$user['id']}' AND (type='r' OR type='e' OR type='b')");
        $activation = $db->fetch_array($query);

        if (!$activation)                                       { stderr('error_alreadyactivated'); }
        if ($activation['code'] !== $mybb->get_input('code'))  { stderr('error_badactivationcode'); }
        if ($activation['type'] === 'b' && $activation['validated'] == 1) { stderr('error_alreadyvalidated'); }

        $db->delete_query('awaitingactivation', "uid='{$user['id']}' AND (type='r' OR type='e')");

        if ($user['usergroup'] == 5 && !in_array($activation['type'], ['e', 'b'], true)) {
            $db->update_query('users', ['usergroup' => 2], "id='{$user['id']}'");
            $cache->update_awaitingactivation();
        }

        if ($activation['type'] === 'e') {
            $db->update_query('users', ['email' => $db->escape_string($activation['misc'])], "id='{$user['id']}'");
            $plugins->run_hooks('member_activate_emailupdated');
            redirect('usercp.php', $lang->member['redirect_emailupdated']);
        } elseif ($activation['type'] === 'b') {
            $db->update_query('awaitingactivation', ['validated' => 1], "uid='{$user['id']}' AND type='b'");
            $plugins->run_hooks('member_activate_emailactivated');
            redirect('index.php', $lang->member['redirect_accountactivated_admin'], '', true);
        } else {
            $plugins->run_hooks('member_activate_accountactivated');
            $db->update_query('users', ['ustatus' => 'confirmed'], "id='{$user['id']}' AND ustatus='pending' AND enabled='yes'");
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
        eval("\$activate = \"".$templates->get('member_activate')."\";");
        stdhead('title');
        echo $activate;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_resendactivation
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_resendactivation' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_resendactivation_start');

    if ($regtype === 'admin') { error($lang->error_activated_by_admin); }

    $query    = $db->query("SELECT u.id, u.username, u.usergroup, u.email, a.code, a.type, a.validated FROM users u LEFT JOIN awaitingactivation a ON (a.id=u.id AND (a.type='r' OR a.type='b')) WHERE u.email='" . $db->escape_string($mybb->get_input('email')) . "'");
    $numusers = $db->num_rows($query);

    if ($numusers < 1) {
        error($lang->error_invalidemail);
    } else {
        while ($user = $db->fetch_array($query)) {
            if ($user['type'] === 'b' && $user['validated'] == 1) { error($lang->error_activated_by_admin); }
            if ($user['usergroup'] == 5) {
                if (!$user['code']) {
                    $user['code'] = random_str();
                    $db->insert_query('awaitingactivation', ['uid' => $user['id'], 'dateline' => TIMENOW, 'code' => $user['code'], 'type' => $user['type']]);
                }
                $emailmessage = sprintf($lang->email_activateaccount . ($username_method ?: ''), $user['username'], $SITENAME, $BASEURL, $user['uid'], $user['code']);
                my_mail($user['email'], sprintf($lang->emailsubject_activateaccount, $SITENAME), $emailmessage);
            }
        }
        $plugins->run_hooks('member_do_resendactivation_end');
        redirect('index.php', $lang->redirect_activationresent);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: resendactivation
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'resendactivation') {
    $plugins->run_hooks('member_resendactivation');
    if ($regtype === 'admin') { error($lang->error_activated_by_admin); }
    if ($mybb->user['uid'] && $mybb->user['usergroup'] != 5) { error($lang->error_alreadyactivated); }

    $query      = $db->simple_select('awaitingactivation', '*', "uid='{$mybb->user['uid']}' AND type='b'");
    $activation = $db->fetch_array($query);
    if ($activation && $activation['validated'] == 1) { error($lang->error_activated_by_admin); }

    $errors = isset($errors) && count($errors) > 0 ? inline_error($errors) : '';
    $email  = $errors ? htmlspecialchars_uni($mybb->get_input('email')) : '';

    $plugins->run_hooks('member_resendactivation_end');
    eval("\$activate = \"".$templates->get('member_resendactivation')."\";");
    output_page($activate);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: do_lostpw
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'do_lostpw' && $mybb->request_method === 'post') {
    $plugins->run_hooks('member_do_lostpw_start');

    $query    = $db->simple_select('users', '*', "email='" . $db->escape_string($mybb->get_input('email')) . "'");
    $numusers = $db->num_rows($query);

    if ($numusers < 1) {
        stderr($lang->member['error_invalidemail']);
    } else {
        while ($user = $db->fetch_array($query)) {
            $db->delete_query('awaitingactivation', "uid='{$user['id']}' AND type='p'");
            $activationcode = random_str(30);
            $db->insert_query('awaitingactivation', ['uid' => $user['id'], 'dateline' => TIMENOW, 'code' => $activationcode, 'type' => 'p']);
            $emailmessage = sprintf($lang->member['email_lostpw' . ($username_method ?: '')], $user['username'], $SITENAME, $BASEURL, $user['id'], $activationcode);
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
    eval("\$lostpw = \"".$templates->get('member_lostpw')."\";");
    stdhead('title');
    echo $lostpw;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: resetpassword
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'resetpassword') {
    $plugins->run_hooks('member_resetpassword_start');

    $user = isset($mybb->input['username'])
        ? get_user_by_username($mybb->get_input('username'), ['username_method' => $username_method, 'fields' => '*'])
        : get_user($mybb->get_input('id', MyBB::INPUT_INT));

    if (!$user && isset($mybb->input['username'])) { stderr('error_invalidpworusername'); }

    if (isset($mybb->input['code']) && $user) {
        $query          = $db->simple_select('awaitingactivation', 'code', "uid='{$user['id']}' AND type='p'");
        $activationcode = $db->fetch_field($query, 'code');

        if (!$activationcode || $activationcode !== $mybb->get_input('code')) { stderr('error_badlostpwcode'); }

        $db->delete_query('awaitingactivation', "uid='{$user['id']}' AND type='p'");

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
        stdhead('title');
        eval("\$activate = \"".$templates->get('member_resetpassword')."\";");
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
    $user_loginattempts = get_user_by_username($user['username'], ['fields' => 'loginattempts', 'username_method' => (int)$username_method]);
    if (!empty($user_loginattempts)) { $user['loginattempts'] = (int)$user_loginattempts['loginattempts']; }

    $loginhandler->set_data($user);
    $validated = $loginhandler->validate_login();

    if (!$validated) {
        $mybb->input['action']   = 'login';
        $mybb->request_method    = 'get';
        $login_user_uid          = (int)($loginhandler->login_data['id'] ?? 0);
        $user['loginattempts']   = (int)($loginhandler->login_data['loginattempts'] ?? 0);

        login_attempt_check($login_user_uid);
        $db->update_query('users', ['loginattempts' => 'loginattempts+1'], "id='{$login_user_uid}'", '1', true);
        
		$username = $mybb->get_input('username');
        $password = $mybb->get_input('password');
		
		$ipaddress = get_ip();
		$md5pw = md5($password);
        $iphost = @gethostbyaddr(USERIPADDRESS);
		
		failedlogins('login', false, true, true, $login_user_uid);
        $errors = $loginhandler->get_friendly_errors();
    } else {
        
        $loginhandler->complete_login();
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
// ACTION: login
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'login') {
    $plugins->run_hooks('member_login');

    $member_loggedin_notice = '';
    if (isset($CURUSER) && is_array($CURUSER) && !empty($CURUSER['id'])) {
        $CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);
        $already_logged_in   = sprintf($lang->member['already_logged_in'], build_profile_link($CURUSER['username'], $CURUSER['id']));
        eval("\$member_loggedin_notice = \"".$templates->get('member_loggedin_notice')."\";");
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

    if ($username_method == 1)      { $lang->member['username'] = $lang->member['username1']; }
    elseif ($username_method == 2)  { $lang->member['username'] = $lang->member['username2']; }

    $plugins->run_hooks('member_login_end');
    eval("\$login = \"".$templates->get('member_login')."\";");
    stdhead();
    echo $login;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: logout
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'logout') {
    $plugins->run_hooks('member_logout_start');

    if (!$CURUSER['id']) { redirect('index.php', $lang->member['redirect_alreadyloggedout']); }

    if (isset($mybb->input['sid']) && $mybb->get_input('sid') !== $session->sid) { stderr($lang->member['error_notloggedout']); }

    $logoutkey = md5($CURUSER['loginkey']);
    if ($mybb->get_input('logoutkey') !== $logoutkey) { stderr($lang->member['error_notloggedout']); }

    my_unsetcookie('mybbuser');
    my_unsetcookie('sid');

    if ($CURUSER['id']) {
        $time = TIMENOW;
        $db->shutdown_query("UPDATE users SET lastvisit='{$time}', lastactive='{$time}' WHERE id='{$CURUSER['id']}'");
        $db->delete_query('sessions', "sid='{$session->sid}'");
    }

    $plugins->run_hooks('member_logout_end');
    redirect('member.php?action=login', $lang->member['redirect_loggedout']);
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: viewnotes
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'viewnotes') {
    $uid  = $mybb->get_input('id', MyBB::INPUT_INT);
    $user = get_user($uid);

    if (!$user) { error($lang->member['error_nomember']); }
    if ($mybb->user['id'] == 0 || $mybb->usergroup['canmodcp'] != 1) { print_no_permission(); }

    $user['username']  = htmlspecialchars_uni($user['username']);
    $lang->view_notes_for = sprintf($lang->view_notes_for, $user['username']);
    $user['usernotes'] = nl2br(htmlspecialchars_uni($user['usernotes']));

    $plugins->run_hooks('member_viewnotes');
    eval("\$viewnotes = \"".$templates->get('member_viewnotes', 1, 0)."\";");
    echo $viewnotes;
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// ACTION: profile
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->input['action'] === 'profile') {
    if (!isset($CURUSER) || $CURUSER['id'] == 0) { print_no_permission(); }

    gzip();
    maxsysop();

    $parser_options = ['allow_html' => 1, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'allow_videocode' => 1, 'filter_badwords' => 1];

    $uid = $mybb->get_input('id', MyBB::INPUT_INT);
    $memprofile = $uid ? get_user($uid) : ($CURUSER['id'] ? $CURUSER : false);

    if (!$memprofile) { stderr($lang->member['error_nomember']); }

    $uid       = $memprofile['id'];
    $SameUser  = ($uid === (int)$CURUSER['id']);
    $IsStaff   = is_mod($usergroups);

    if ($memprofile['invisible'] == 1 && !$SameUser && !$IsStaff) { stderr($lang->member['noperm']); }
    if ($memprofile['ustatus'] === 'pending') { stderr($lang->member['pendinguser']); }

    $plugins->run_hooks('member_profile_start');

    $me_username             = $memprofile['username'];
    $memprofile['username']  = htmlspecialchars_uni($memprofile['username']);

    stdhead(sprintf($lang->member['title'], $memprofile['username']));

    $memperms = user_permissions((int)$memprofile['id']);

    $memprofile['displaygroup'] = $memprofile['displaygroup'] ?: $memprofile['usergroup'];
    $displaygroup = usergroup_displaygroup((int)$memprofile['displaygroup']);
    if (is_array($displaygroup)) { $memperms = array_merge($memperms, $displaygroup); }

    add_breadcrumb(sprintf($lang->member['nav_profile'], $memprofile['username']));
    build_breadcrumb();

    $send_user_email    = sprintf($lang->member['send_user_email'], $memprofile['username']);
    $send_pms           = sprintf($lang->member['send_pm'], $memprofile['username']);
    $users_signature    = sprintf($lang->member['users_signature'], $memprofile['username']);
    $users_forum_info   = sprintf($lang->member['users_forum_info'], $memprofile['username']);
    $users_additional_info = sprintf($lang->member['users_additional_info'], $memprofile['username']);

    $useravatar = format_avatar($memprofile['avatar'], $memprofile['avatardimensions']);
    $avatar     = str_starts_with($useravatar['image'], '<')
        ? $useravatar['image']
        : '<img class="rounded img-fluid" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';

    $website = $sendemail = $sendpm = $contact_details = '';

    if ($usergroups['cansendemail'] == 1 && $uid != $CURUSER['id'] && $memprofile['hideemail'] != 1
        && (str_contains(',' . $memprofile['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') === false || $usergroups['cansendemailoverride'] != 0)) {
        $bgcolor = alt_trow();
        eval("\$sendemail = \"".$templates->get('member_profile_email')."\";");
    }

    if ($enablepms != 0 && $uid != $CURUSER['id'] && $usergroups['canusepms'] == 1
        && (($memprofile['receivepms'] != 0 && $memperms['canusepms'] != 0
            && str_contains(',' . $memprofile['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') === false)
            || $usergroups['canoverridepm'] == 1)) {
        $bgcolor = alt_trow();
        eval('$sendpm = "' . $templates->get('member_profile_pm') . '";');
    }

    $any_contact_field = false;
    if ($any_contact_field || $sendemail || $sendpm || $website) {
        eval('$contact_details = "' . $templates->get('member_profile_contact_details') . '";');
    }

    $signature = '';
    if ($memprofile['signature']) {
        $sig_parser = ['allow_html' => 1, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'me_username' => $me_username, 'filter_badwords' => 1];
        $memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
        eval("\$signature = \"".$templates->get('member_profile_signature')."\";");
    }

    // User data with permissions
    $Query = $db->sql_query_prepared("SELECT u.*, p.canupload, p.candownload, p.cancomment FROM users u LEFT JOIN users_perm p ON (u.id=p.userid) WHERE u.id=? LIMIT 1", [$uid]);
    if ($Query && $db->num_rows($Query) > 0) {
        $user = $db->fetch_array($Query);
    } else {
        stderr($lang->member['invaliduser']);
    }

    $usericons = get_user_icons($user);

    if ($memprofile['invited_by']) {
        $query = $db->simple_select('users', 'username, usergroup', "id='{$memprofile['invited_by']}'");
        if ($db->num_rows($query) > 0) {
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
        'ratio-ok'   => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Good</span>',
        'ratio-warn' => '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Fair</span>',
        default      => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Poor</span>',
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
    $memlocaldate = gmdate($dateformat, (int)(TIMENOW + ((float)$memprofile['timezone'] * 3600)));
    $memlocaltime = gmdate($timeformat, (int)(TIMENOW + ((float)$memprofile['timezone'] * 3600)));
    $localtime     = $memlocaldate . ' at ' . $memlocaltime;

    // Birthday
    $membday = $membdayage = '';
    if ($memprofile['birthday']) {
        $membday_arr = explode('-', $memprofile['birthday']);
        if ($memprofile['birthdayprivacy'] !== 'none') {
            if (!empty($membday_arr[0]) && !empty($membday_arr[1]) && !empty($membday_arr[2])) {
                $membdayage = sprintf('(' . get_age($memprofile['birthday']) . ' years old)');
                $bdayformat = fix_mktime($dateformat, $membday_arr[2]);
                $membday    = date($bdayformat, mktime(0, 0, 0, (int)$membday_arr[1], (int)$membday_arr[0], (int)$membday_arr[2]));
            } elseif (!empty($membday_arr[2])) {
                $membday = date('Y', mktime(0, 0, 0, 1, 1, (int)$membday_arr[2]));
            } else {
                $membday = date('F j', mktime(0, 0, 0, (int)$membday_arr[1], (int)$membday_arr[0], 0));
            }
        }
        if ($memprofile['birthdayprivacy'] === 'age')  { $membday = 'Hidden'; }
        if ($memprofile['birthdayprivacy'] === 'none') { $membday = 'Hidden'; $membdayage = ''; }
    } else {
        $membday    = 'Not Specified';
        $membdayage = '';
    }

    echo '<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userclass.css" type="text/css" media="screen" />';

    $usertitle = $memperms['image'] ?? '';

    // Online status
    $timesearch = TIMENOW - $wolcutoffmins * 60;
    $query      = $db->simple_select('sessions', 'location,nopermission', "uid='{$uid}' AND time>'{$timesearch}'", ['order_by' => 'time', 'order_dir' => 'DESC', 'limit' => 1]);
    $session    = $db->fetch_array($query);

    $timeonline      = 'None Registered';
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
                eval("\$online_status = \"".$templates->get('member_profile_online')."\";");
            }
        }
    }

    if (!isset($online_status)) {
        eval("\$online_status = \"".$templates->get('member_profile_offline')."\";");
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
    if ($memperms['isbannedgroup'] == 1 && $usergroups['canuserdetails'] == 1) {
        $query = $db->simple_select('banned b LEFT JOIN users a ON (b.admin=a.id)', 'b.*, a.username AS adminuser', "b.uid='{$uid}'", ['limit' => 1]);
        if ($db->num_rows($query)) {
            $memban = $db->fetch_array($query);
            $memban['reason'] = $memban['reason'] ? htmlspecialchars_uni($parser->parse_badwords($memban['reason'])) : $lang->na;

            if (in_array($memban['lifted'], ['perm', ''], true) || in_array($memban['bantime'], ['perm', '---'], true)) {
                $banlength     = 'permanent';
                $timeremaining = 'na';
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

            $timeremaining        = '<span class="' . $banned_class . '">(' . $timeremaining . ' remaining)</span>';
            $memban['adminuser']  = build_profile_link(htmlspecialchars_uni($memban['adminuser']), $memban['admin']);
            eval('$bannedbit = "' . $templates->get('member_profile_banned') . '";');
        }
    }

    $memprofile['regip']  = my_inet_ntop($db->unescape_binary($memprofile['regip']));
    $memprofile['lastip'] = my_inet_ntop($db->unescape_binary($memprofile['lastip']));
    eval("\$ipaddress = \"".$templates->get('member_profile_modoptions_ipaddress')."\";");

    // Stats
    $zaza  = $cache->read('indexstats');
    $stats = $zaza['totalposts'];
    $stats22 = $zaza['totalthreads'];
    $daysreg = max(1, (TIMENOW - $memprofile['added']) / 86400);

    $ppd = round(min($memprofile['postnum'], $memprofile['postnum'] / $daysreg), 2);
    $post_percent = $stats > 0 ? min(100, round($memprofile['postnum'] * 100 / $stats, 2)) : 0;

    $tpd = round(min($memprofile['threadnum'], $memprofile['threadnum'] / $daysreg), 2);
    $thread_percent = $stats22 > 0 ? min(100, round($memprofile['threadnum'] * 100 / $stats22, 2)) : 0;

    $ppd_percent_total = ts_nf($ppd) . ' posts per day | ' . $post_percent . ' percent of total posts';
    $tpd_percent_total = ts_nf($tpd) . ' threads per day | ' . $thread_percent . ' percent of total threads';

    // Mod options
    $modoptions = $viewnotes = $editnotes = $editprofile = $banuser = $manageban = $manageuser = '';
    $awaybit = $referrals = $groupimage = $userstars = $reputation = '';

    if ($memperms['isbannedgroup'] == 1 && $usergroups['canuserdetails'] == 1) {
        eval("\$manageban = \"".$templates->get('member_profile_modoptions_manageban')."\";");
    } else {
        eval("\$banuser = \"".$templates->get('member_profile_modoptions_banuser')."\";");
    }
    eval("\$editprofile = \"".$templates->get('member_profile_modoptions_editprofile')."\";");
    $manageuser = $editprofile . $banuser . $manageban;

    if ($IsStaff) {
        eval("\$modoptions = \"".$templates->get('member_profile_modoptions')."\";");
    }

    $findposts = $findthreads = '';
    if (!empty($memprofile['postnum']))  { eval("\$findposts   = \"".$templates->get('member_profile_findposts')."\";"); }
    if (!empty($memprofile['threadnum'])){ eval("\$findthreads = \"".$templates->get('member_profile_findthreads')."\";"); }

    // Buddy/ignore
    $add_remove_options = [];
    $buddy_options = $ignore_options = '';
    if ($CURUSER['id'] != $memprofile['id'] && $CURUSER['id'] != 0) {
        $buddy_list  = explode(',', $CURUSER['buddylist']);
        $ignore_list = explode(',', $CURUSER['ignorelist']);

        $add_remove_options = in_array($uid, $buddy_list)
            ? ['url' => "usercp.php?action=do_editlists&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_buddy_button', 'lang' => 'Remove from Buddy List']
            : ['url' => "usercp.php?action=do_editlists&amp;add_username=" . urlencode($memprofile['username']) . "&amp;my_post_key={$mybb->post_code}", 'class' => 'add_buddy_button', 'lang' => 'Add to Buddy List'];

        if (!in_array($uid, $ignore_list)) { eval("\$buddy_options = \"".$templates->get('member_profile_addremove')."\";"); }

        $add_remove_options = in_array($uid, $ignore_list)
            ? ['url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_ignore_button', 'lang' => 'Remove from Ignore List']
            : ['url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;add_username=" . urlencode($memprofile['username']) . "&amp;my_post_key={$mybb->post_code}", 'class' => 'add_ignore_button', 'lang' => 'Add to Ignore List'];

        if (!in_array($uid, $buddy_list)) { eval("\$ignore_options = \"".$templates->get('member_profile_addremove')."\";"); }
    }

    $plugins->run_hooks('member_profile_end');

    // Invited by
    $invs = '';
    if ($memprofile['invited_by']) {
        $invs = '<div class="py-2 border-bottom"><span class="text-muted">' . sprintf($lang->member['iby'], $memprofile['invited_by']) . '</span></div>';
    }

    // Swarm & completed
    $recent_user_torrents = build_recent_user_torrents($db, (int)$uid);
    $swarm          = build_user_active_swarm($db, (int)$uid, 1000);
    $active_seeds   = (string)$swarm['seed_count'];
    $active_leeches = (string)$swarm['leech_count'];
    $seeding_now    = $swarm['seed_html'];
    $leeching_now   = $swarm['leech_html'];

    $completed             = build_user_completed_torrents_from_snatched($db, (int)$uid, 10000);
    $completed_list        = $completed['html'];
    $times_completed_total = ts_nf($completed['count']);

    $is_own_profile   = ((int)$CURUSER['id'] === (int)$memprofile['id']);
    $is_mod_flag      = is_mod($usergroups);
    $can_change_avatar = ($is_own_profile || $is_mod_flag) ? 1 : 0;

    echo '<script src="' . $BASEURL . '/scripts/upload_avatar.js"></script>';

    // Report button
    $report_button = '';
    if ($CURUSER['id'] != $memprofile['id'] && $CURUSER['id'] != 0) {
        $report_button = '<button type="button" class="btn btn-sm btn-outline-danger" onclick="openReportUserModal(' . $memprofile['id'] . ', \'' . addslashes($memprofile['username']) . '\')" data-bs-toggle="tooltip" title="Report this user for violations"><i class="fa-solid fa-flag me-1"></i> Report User</button>';
    }

    $reasons_map = [
        'spam'          => ['text' => 'Spam Account',            'icon' => 'fa-user-slash',       'description' => 'User is posting spam content'],
        'harassment'    => ['text' => 'Harassment/Bullying',     'icon' => 'fa-ban',               'description' => 'User is harassing or bullying others'],
        'fake'          => ['text' => 'Fake Account',            'icon' => 'fa-mask',              'description' => 'User is pretending to be someone else'],
        'impersonation' => ['text' => 'Impersonation',           'icon' => 'fa-id-badge',          'description' => 'User is impersonating another user'],
        'inappropriate' => ['text' => 'Inappropriate Profile',   'icon' => 'fa-eye-slash',         'description' => 'User has inappropriate profile content'],
        'scam'          => ['text' => 'Scam/Fraud',              'icon' => 'fa-skull-crossbones',  'description' => 'User is involved in scams or fraud'],
        'copyright'     => ['text' => 'Copyright Infringement',  'icon' => 'fa-copyright',         'description' => 'User is sharing copyrighted content'],
        'malware'       => ['text' => 'Malware Distribution',    'icon' => 'fa-bug',               'description' => 'User is distributing malware/viruses'],
        'other'         => ['text' => 'Other Reason',            'icon' => 'fa-ellipsis',          'description' => 'Select for other reasons'],
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
            $torrent_name = htmlspecialchars_uni($comment['torrent_name'] ?? 'Unknown');

            $recent_comments_html .= '
            <div class="list-row">
                <div class="flex-grow-1 me-3" style="min-width:0;">
                    <div class="fw-semibold text-truncate">
                        <a href="' . $comment_link . '#pid' . $pid . '" class="text-decoration-none">
                            <i class="bi bi-collection-play me-1 text-muted"></i>' . $torrent_name . '
                        </a>
                    </div>
                    <div class="muted mt-1" style="font-size:0.85rem;">' . $comment_text . '</div>
                </div>
                <div class="text-end flex-shrink-0">
                    <div class="small text-muted">' . $comment_date . '</div>
                    <div class="small text-muted">' . $comment_time . '</div>
                </div>
            </div>';
        }
    } else {
        $recent_comments_html = '<div class="text-center py-4">
            <div class="d-inline-flex flex-column align-items-center gap-3">
                <div class="bg-light rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;">
                    <i class="bi bi-chat-square-dots fs-1 text-secondary opacity-50"></i>
                </div>
                <div class="text-secondary fw-medium">No comments yet.</div>
            </div>
        </div>';
    }

    // Signature (second pass if needed)
    if ($memprofile['signature'] && !$signature) {
        $sig_parser = ['allow_html' => 1, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'me_username' => $me_username, 'filter_badwords' => 1];
        $memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
        eval("\$signature = \"".$templates->get('member_profile_signature')."\";");
    }

    $formattedname = format_name($memprofile['username'], $memprofile['usergroup'], $memprofile['displaygroup']);

    eval("\$profile = \"".$templates->get('member_profile')."\";");
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
                        <h5 class="modal-title" id="reportUserModalLabel">
                            <i class="fa-solid fa-flag me-2"></i>Report User: <?= hsafe($memprofile['username']) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fa-solid fa-circle-exclamation me-2"></i>Select Report Reason</h6>
                            <div class="row g-3">
                                <?php foreach ($reasons_map as $key => $reason): ?>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="reason" id="reason_<?= $key ?>" value="<?= $key ?>" autocomplete="off">
                                    <label class="btn btn-outline-danger w-100 text-start py-3" for="reason_<?= $key ?>">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3"><i class="fa-solid <?= $reason['icon'] ?> fa-2x"></i></div>
                                            <div>
                                                <div class="fw-bold"><?= $reason['text'] ?></div>
                                                <div class="small text-muted mt-1"><?= $reason['description'] ?></div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fa-solid fa-file-alt me-2"></i>Report Details</h6>
                            <div class="mb-3">
                                <label for="reportDescription" class="form-label fw-bold">Detailed Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="reportDescription" name="description" rows="5" placeholder="Please provide as much detail as possible..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="additionalInfo" class="form-label">Additional Information (Optional)</label>
                                <textarea class="form-control" id="additionalInfo" name="additional_info" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="evidenceLinks" class="form-label"><i class="fa-solid fa-link me-1"></i>Evidence Links (Optional)</label>
                                <input type="text" class="form-control" id="evidenceLinks" name="evidence_links" placeholder="Paste URLs to screenshots or other evidence...">
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> False or malicious reports may result in action against your account.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa-solid fa-times me-1"></i> Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-paper-plane me-1"></i> Submit Report</button>
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

    if ($mybb->usergroup['maxemails'] > 0) {
        $user_check = $mybb->user['uid'] > 0 ? "fromuid='{$mybb->user['uid']}'" : 'ipaddress=' . $db->escape_binary($session->packedip);
        $sent_count = (int)$db->fetch_field($db->simple_select('maillogs', 'COUNT(*) AS sent_count', "{$user_check} AND dateline >= '" . (TIMENOW - 86400) . "'"), 'sent_count');
        if ($sent_count >= $mybb->usergroup['maxemails']) { error(sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails'])); }
    }

    $query   = $db->simple_select('users', 'id, username, email, hideemail', "id='" . $mybb->get_input('id', MyBB::INPUT_INT) . "'");
    $to_user = $db->fetch_array($query);

    if (!$to_user['username']) { stderr('error_invalidusername'); }
    if ($to_user['hideemail'] != 0) { stderr('error_hideemail'); }

    if ($CURUSER['id']) {
        $mybb->input['fromemail'] = $CURUSER['email'];
        $mybb->input['fromname']  = $CURUSER['username'];
    }

    if (!validate_email_format($mybb->input['fromemail']))  { $errors[] = 'error_invalidfromemail'; }
    if (empty($mybb->input['fromname']))                    { $errors[] = 'error_noname'; }
    if (empty($mybb->input['subject']))                     { $errors[] = 'error_no_email_subject'; }
    if (empty($mybb->input['message']))                     { $errors[] = $lang->error_no_email_message; }

    if (empty($errors)) {
        $from    = $mail_handler === 'smtp' ? $mybb->input['fromemail'] : "{$mybb->input['fromname']} <{$mybb->input['fromemail']}>";
        $message = sprintf($lang->member['email_emailuser'], $to_user['username'], $mybb->input['fromname'], $SITENAME, $BASEURL, $mybb->get_input('message'));
        my_mail($to_user['email'], $mybb->get_input('subject'), $message, '', '', '', false, 'text', '', $from);

        if ($mail_logging > 0) {
            $db->insert_query('maillogs', [
                'subject'   => $db->escape_string($mybb->get_input('subject')),
                'message'   => $db->escape_string($mybb->get_input('message')),
                'dateline'  => TIMENOW,
                'fromuid'   => $CURUSER['id'],
                'fromemail' => $db->escape_string($mybb->input['fromemail']),
                'touid'     => $to_user['id'],
                'toemail'   => $db->escape_string($to_user['email']),
                'tid'       => 0,
                'ipaddress' => $db->escape_binary($session->packedip),
                'type'      => 1,
            ]);
        }

        $plugins->run_hooks('member_do_emailuser_end');
        redirect(get_profile_link($to_user['id']), 'redirect_emailsent');
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

    $query   = $db->simple_select('users', 'id, username, email, hideemail, ignorelist', "id='" . $mybb->get_input('id', MyBB::INPUT_INT) . "'");
    $to_user = $db->fetch_array($query);
    $to_user['username'] = htmlspecialchars_uni($to_user['username']);
    $email_user = sprintf($lang->member['email_user'], $to_user['username']);

    if (!$to_user['id'])          { stderr('error_invaliduser'); }
    if ($to_user['hideemail'] != 0) { stderr('error_hideemail'); }
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
    if ($CURUSER['id'] == 0) { eval("\$from_email = \"".$templates->get('member_emailuser_guest')."\";"); }

    $plugins->run_hooks('member_emailuser_end');
    eval("\$emailuser = \"".$templates->get('member_emailuser')."\";");
    stdhead('title');
    echo $emailuser;
    stdfoot();
}

// ── Default redirect ───────────────────────────────────────────────────────
if (!$mybb->input['action']) {
    header('Location: index.php');
}