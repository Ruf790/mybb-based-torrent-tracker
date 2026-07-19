<?php
declare(strict_types=1);



define('IN_MYBB', 1);
define('IGNORE_CLEAN_VARS', 'sid');
define('THIS_SCRIPT', 'member.php');
define('SCRIPTNAME', 'member.php');
define('ALLOWABLE_PAGE', 'verify_2fa,register,do_register,login,do_login,logout,lostpw,do_lostpw,activate,resendactivation,do_resendactivation,resetpassword,viewnotes');

$nosession['avatar'] = 1;


require_once 'global.php';

define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once INC_PATH . '/functions_forum.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_timezone.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/function_loginattemptcheck.php';
require_once INC_PATH . '/functions_user.php';
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
    //finfo_close($finfo);

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
//if (in_array($mybb->input['action'], ['register', 'do_register'], true) && $mybb->usergroup['cancp'] != 1) {
if (in_array($mybb->input['action'], ['register', 'do_register'], true)) {	
	
    if ($disableregs == 1) { stderr($lang->member['registrations_disabled']); }
	

    if ((int)$maxusers > 0) {
        $count = $db->num_rows($db->sql_query('SELECT id FROM users WHERE id > 0'));
        if ($maxusers <= $count) { stderr($lang->global['signuplimitreached']); }
    }

    if (($CURUSER['id'] ?? 0) != 0) {
        stderr($lang->member['error_alreadyregistered']);
    }

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
        $mybb->input['password']  = random_str($password_length, (bool)$requirecomplexpasswords);
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
           	
			
			
			$emailsubject = sprintf($lang->member['emailsubject_activateaccount'], $SITENAME);

$template = match((int)($username_method ?? 0)) {
    1 => $lang->member['email_activateaccount1'],
    2 => $lang->member['email_activateaccount2'],
    default => $lang->member['email_activateaccount'],
};

$emailmessage = sprintf($template, $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);

my_mail($user_info['email'], $emailsubject, $emailmessage);
			
			
			
			
			
			
            
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
                
				$tppoptions .= '<option value="'.$val.'"'.$selected.'>'.$tpp_option.'</option>';
            }
            
			$tppselect = '<div class="mb-2 pb-3">
	<label for="tpp">'.$lang->usercp['tpp'].'</label>
<select name="tpp" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->usercp['use_default'].'</option>
'.$tppoptions.'
</select>
</div>';
			
			
        }
        if ($userpppoptions) {
            $pppoptions = '';
            foreach (array_map('trim', explode(',', $userpppoptions)) as $val) {
                $ppp_option = sprintf($lang->member['ppp_option'], $val);
                
				$pppoptions .= '<option value="'.$val.'"'.$selected.'>'.$ppp_option.'</option>';
				
            }
            
			$pppselect = '<div class="mb-2 pb-3">
	<label for="ppp">'.$lang->usercp['post_per_page'].'</label>
<select name="ppp" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->usercp['use_default'].'</option>
'.$pppoptions.'
</select>
</div>';

        }

       
        $altbg          = 'trow1';
        $usergroup = in_array($regtype, ['verify', 'admin', 'both'], true) ? 5 : 2;
       
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
        if ($regtype !== 'randompass') {
            $js_validator_password_length = sprintf($lang->member['js_validator_password_length'], $minpasswordlength);
            if ($requirecomplexpasswords == 1) {
                $lang->member['password'] = $lang->member['complex_password'] = sprintf($lang->member['complex_password'], $minpasswordlength);
            }
            
			$passboxes = '
			
			<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="password">'.$lang->member['password'].'</label>
		<input type="password" class="form-control form-control-sm border" name="password" id="password" />
		</div>
		<div class="col-lg-6">
		<label for="password2">'.$lang->member['confirm_password'].'</label>
		<input type="password" class="form-control form-control-sm border" name="password2" id="password2" style="width: 100%" />			
		</div>
		<div class="col" style="display: none" id="password_status">&nbsp;</div>
	</div>
</div>';
			
			
			
			
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
       

        $validator_javascript = '<script type="text/javascript">
            var regsettings = {
                minnamelength: \'' . $minnamelength . '\',
                maxnamelength: \'' . $maxnamelength . '\',
                minpasswordlength: \'' . $minpasswordlength . '\',
                maxpasswordlength:       \'' . $maxpasswordlength . '\',
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
        
		
		$registration = '
		
		<!DOCTYPE html>
<html lang="en">
<head>
    <title>'.$SITENAME.' - '.$lang->member['welcome_register'].'</title>
    
</head>
<body>


<div class="container mt-3">
    '.$inline_errors.'
    '.$member_loggedin_notice.'
    
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i> '.$lang->member['welcome_register'].'</h4>
                </div>
                
                <div class="card-body">
                    '.$regerrors.'
                    
                    <form action="member.php" method="post" id="registration_form">
                        <input type="text" style="display: none;" value="" name="regcheck1" />
                        <input type="text" style="display: none;" value="true" name="regcheck2" />
                        
                        <!-- Секция данных аккаунта -->
                        <div class="card-section">
                            <h5 class="section-title mb-4">
                                <i class="fas fa-user-circle me-2"></i> '.$lang->member['account_details'].'
                            </h5>
                            
                            <div class="row">
                                <!-- Имя пользователя -->
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">'.$lang->member['username'].'</label>
                                    <div class="position-relative">
                                        <i class="fas fa-user form-icon"></i>
                                        <input type="text" class="form-control input-with-icon" name="username" id="username" value="'.$username.'" placeholder="Enter username" />
                                    </div>
                                </div>
                                
                                <!-- Поля пароля -->
                                '.$passboxes.'
                                
                                <!-- Email -->
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">'.$lang->member['email'].'</label>
                                    <div class="position-relative">
                                        <i class="fas fa-envelope form-icon"></i>
                                        <input type="email" class="form-control input-with-icon" name="email" id="email" maxlength="50" value="'.$email.'" placeholder="Your email" />
                                    </div>
                                </div>
                                
                                <!-- Подтверждение email -->
                                <div class="col-md-6 mb-3">
                                    <label for="email2" class="form-label">'.$lang->member['confirm_email'].'</label>
                                    <div class="position-relative">
                                        <i class="fas fa-envelope-circle-check form-icon"></i>
                                        <input type="email" class="form-control input-with-icon" name="email2" id="email2" maxlength="50" value="'.$email2.'" placeholder="Confirm email" />
                                    </div>
                                    <div style="display: none;" id="email_status">&nbsp;</div>
                                </div>
                                
                               
                                
                                <!-- Код приглашения -->
                                <div class="col-md-6 mb-3">
                                    '.$showinvitecode.'
                                </div>
                            </div>
                        </div>
                        
                        <!-- Секция настроек аккаунта -->
                        <div class="card-section">
                            <h5 class="section-title mb-4">
                                <i class="fas fa-sliders-h me-2"></i> '.$lang->member['account_prefs'].'
                            </h5>
                            
                            <div class="row">
                                <!-- Часовой пояс -->
                                <div class="col-md-6 mb-3">
                                    <label for="timezone" class="form-label">'.$lang->member['time_offset'].'</label>
                                    <div class="position-relative">
                                        <i class="fas fa-globe form-icon"></i>
                                        '.$tzselect.'
                                    </div>
                                </div>
                                
                                <!-- Коррекция летнего времени -->
                                <div class="col-md-6 mb-3">
                                    <label for="dstcorrection" class="form-label">'.$lang->member['dst_correction'].'</label>
                                    <div class="position-relative">
                                        <i class="fas fa-clock form-icon"></i>
                                        <select name="dstcorrection" class="form-select input-with-icon">
                                            <option value="2" '.$dst_auto_selected.'>'.$lang->member['dst_correction_auto'].'</option>
                                            <option value="1" '.$dst_enabled_selected.'>'.$lang->member['dst_correction_enabled'].'</option>
                                            <option value="0" '.$dst_disabled_selected.'>'.$lang->member['dst_correction_disabled'].'</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Настройки уведомлений -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">Настройки уведомлений</label>
                                    <div class="border rounded p-3">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="allownotices" id="allownotices" value="1" '.$allownoticescheck.'>
                                            <label class="form-check-label" for="allownotices">
                                                <i class="fas fa-bell me-1"></i> '.$lang->member['allow_notices'].'
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="hideemail" id="hideemail" value="1" '.$hideemailcheck.'>
                                            <label class="form-check-label" for="hideemail">
                                                <i class="fas fa-eye-slash me-1"></i> '.$lang->member['hide_email'].'
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="receivepms" id="receivepms" value="1" '.$receivepmscheck.'>
                                            <label class="form-check-label" for="receivepms">
                                                <i class="fas fa-comments me-1"></i> '.$lang->member['receive_pms'].'
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="pmnotice" id="pmnotice" value="1" '.$pmnoticecheck.'>
                                            <label class="form-check-label" for="pmnotice">
                                                <i class="fas fa-desktop me-1"></i> '.$lang->member['pm_notice'].'
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="pmnotify" id="pmnotify" value="1" '.$pmnotifycheck.'>
                                            <label class="form-check-label" for="pmnotify">
                                                <i class="fas fa-envelope me-1"></i> '.$lang->member['email_notify_newpm'].'
                                            </label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="invisible" id="invisible" value="1" '.$invisiblecheck.'>
                                            <label class="form-check-label" for="invisible">
                                                <i class="fas fa-user-secret me-1"></i> '.$lang->member['invisible_mode'].'
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Метод подписки -->
                                <div class="col-md-6 mb-3">
                                    <label for="subscriptionmethod" class="form-label">'.$lang->member['subscription_method'].'</label>
                                    <div class="position-relative">
                                        <i class="fas fa-rss form-icon"></i>
                                        <select name="subscriptionmethod" id="subscriptionmethod" class="form-select input-with-icon">
                                            <option value="0" '.$no_auto_subscribe_selected.'>'.$lang->member['no_auto_subscribe'].'</option>
                                            <option value="1" '.$no_subscribe_selected.'>'.$lang->member['no_subscribe'].'</option>
                                            <option value="2" '.$instant_email_subscribe_selected.'>'.$lang->member['instant_email_subscribe'].'</option>
                                            <option value="3" '.$instant_pm_subscribe_selected.'>'.$lang->member['instant_pm_subscribe'].'</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Дополнительные поля -->
                       
                        <!-- Кнопка отправки -->
                        <div class="d-grid gap-2 mt-4">
                            <input type="hidden" name="regtime" value="'.$time.'" />
                            <input type="hidden" name="step" value="registration" />
                            <input type="hidden" name="action" value="do_register" />
                            <button type="submit" class="btn btn-primary btn-lg" name="regsubmit" value="'.$lang->member['submit_registration'].'">
                                <i class="fas fa-user-plus me-2"></i> '.$lang->member['submit_registration'].'
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

'.$validator_javascript.'
<script type="text/javascript" src="'.$BASEURL.'/scripts/regvalidator.js?ver=1823"></script>
    

</body>
</html>';
		
		
		
		
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
        if ($activation['type'] === 'b' && $activation['validated'] == 1) { stderr($lang->member['error_alreadyvalidated']); }

        $db->delete_query('awaitingactivation', "uid='{$user['id']}' AND (type='r' OR type='e')");

        if ($user['usergroup'] == 2 && !in_array($activation['type'], ['e', 'b'], true)) {
            $db->update_query('users', ['usergroup' => 2], "id='{$user['id']}'");
            $cache->update_awaitingactivation();
        }

        if ($activation['type'] === 'e') {
			$db->update_query('users', ['email' => $db->escape_string($activation['misc'])], "id='{$user['id']}'");
            $plugins->run_hooks('member_activate_emailupdated');
            redirect('usercp.php', $lang->member['redirect_emailupdated']);
        } 
		elseif ($activation['type'] === 'b') {
            $db->update_query('awaitingactivation', ['validated' => 1], "uid='{$user['id']}' AND type='b'");
			$db->update_query('users', ['ustatus' => 'confirmed'], "id='{$user['id']}' AND ustatus='pending' AND enabled='yes'");
			$cache->update_awaitingactivation();
            
			require_once INC_PATH . '/functions_pm.php';
            $pm = ['subject' => sprintf($lang->member['welcomepmsubject'], $SITENAME), 'message' => sprintf($lang->member['welcomepmbody'], $user['username'], $SITENAME, $BASEURL), 'touid' => $user['id']];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
			
			
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
        
		$activate = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->member['account_activation'].'</title>

</head>
<body>

	<div class="container-md">

<form action="member.php" method="post">
   <div class="card">
<div class="card-body">
	
	
	<div class="pb-4 border-bottom">
                 <label for="username">'.$lang->member['username'].'</label>
                <input type="text" class="form-control border form-control-sm" name="username" value="'.$user['username'].'" />
	</div>
	
	<div class="mt-3">
                 <label for="email" class="form-label">'.$lang->member['activation_code'].'</label>
                <input type="text" class="form-control border form-control-sm" name="code" value="'.$code.'" />
	</div>
	   </div>
	
	<div class="card-footer text-center">

<button type="submit" class="btn btn-primary" name="regsubmit" value="'.$lang->member['activate_account'].'"><i class="fa-solid fa-check"></i> &nbsp;'.$lang->member['activate_account'].'</button>
</div>

	
	   </div>
	</div>

</form>
</body>
</html>';
		
		
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
    
	$activate = '<html>
<head>
<title>'.$SITENAME.' - resend_activation</title>

</head>
<body>

	<div class="container-md">
'.$errors.'
<form action="member.php" method="post">
<div class="card shadow-sm border-0 align-center">
<div class="card-body p-2 p-sm-2 p-md-2 p-lg-3 p-xl-3 p-xxl-3 border-0 text-start">
	
	<div class="legend mb-4">'.$lang->resend_activation.'</div>
	
		<div class="mb-3 ps-3 pe-3">
                 <label for="email" class="form-label">'.$lang->email_address.'</label>
                <input type="text" class="form-control border form-control-sm" name="email" value="'.$email.'" />
	</div>


</table>

<div class="ps-3 text-start"><input type="submit" class="btn btn-primary mt-2" style="padding-left 40px; padding-right: 40px" name="submit" value="'.$lang->request_activation.'" /></div>
<input type="hidden" name="action" value="do_resendactivation" />
</form>
	</div>
	</div>
	</div>

</body>
</html>';
	
	
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
    
	$lostpw = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->member['request_user_pass'].'</title>

</head>
<body>

	
	
	
	<div class="container-md">

	
<form action="member.php" method="post">
    <div class="card">
		<div class="card-body">
	
	'.$errors.'
           
                 <label for="email" class="form-label fw-bold">'.$lang->member['email_address'].'</label>
                <input class="form-control border form-control-sm" type="text" id="email" name="email" value="'.$email.'" placeholder=""/>
            
            <div class="row mt-3">
                <div class="col align-self-center">
                   
                </div>
            </div>
					
					
		</div>
		<div class="card-footer">
                         <button name="submit" type="submit" class="btn btn-primary" value="'.$lang->member['request_user_pass'].'" /><i class="fa-solid fa-key"></i> &nbsp;'.$lang->member['request_user_pass'].'</button>
		</div>

<input type="hidden" name="action" value="do_lostpw" />
		
		
	</div>
     
    
	</div>
</form>

</body>
</html>';
	
	
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
        
		$activate = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->member['reset_password'].'</title>

</head>
<body>

<div class="container-md">
<form action="member.php" method="post">
<div class="card shadow-sm border-0 align-center">
<div class="card-body p-2 p-sm-2 p-md-2 p-lg-3 p-xl-3 p-xxl-3 border-0 text-start">
	
	<div class="legend mb-4">'.$lang->member['reset_password'].'</div>

	<div class="mb-3 ps-3 pe-3">
                 <label for="email" class="form-label">'.$lang->username.'</label>
                <input type="text" class="form-control border form-control-sm" name="username" value="'.$input_username.'" />
	</div>
	
	<div class="mb-3 ps-3 pe-3">
                 <label for="email" class="form-label">'.$lang->member['activation_code'].'</label>
                <input type="text" class="form-control border form-control-sm" name="code" value="'.$code.'" />
	</div>


<div class="ps-3 text-start"><input type="hidden" name="action" value="resetpassword" /><input type="submit" class="btn btn-primary mt-2" style="padding-left 40px; padding-right: 40px" name="regsubmit" value="'.$lang->member['send_password'].'" /></div>
	</div>
	</div>
	</div>
</form>

</body>
</html>';
		
		
		
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
              'subject' => '⚠️ Failed two-factor authentication attempt',
              'message' => "Someone entered your correct password but failed the 2FA code check.\n\n"
                   . "IP: " . get_ip() . "\n"
                   . "Time: " . date('Y-m-d H:i:s') . "\n\n"
                   . "If this wasn't you, your password may be compromised. "
                   . "Consider changing it immediately.",
              'touid'  => $uid,
              'sender' => ['uid' => -1],
            ];
            send_pm($pm, -1, true);
			
			
			$error = '<div class="alert alert-danger mt-3">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                Invalid or expired code. Please try again.
            </div>';
        }
    }

    stdhead($SITENAME . ' - Two-Factor Authentication');
    echo '
    <div class="container-md mt-5" style="max-width:420px">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white text-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-shield-halved me-2"></i>
                    Two-Factor Authentication
                </h5>
            </div>
            <div class="card-body">
                ' . $error . '
                <p class="text-muted small mb-3">
                    Enter the 6-digit code from your authenticator app.
                </p>
                <form method="post" action="member.php?action=verify_2fa">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Authentication Code</label>
                        <input type="text" name="totp_code"
                               class="form-control form-control-lg text-center fw-bold letter-spacing-3"
                               placeholder="000 000" maxlength="6"
                               autocomplete="one-time-code" autofocus
                               inputmode="numeric" pattern="[0-9]{6}">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>Verify & Login
                        </button>
                    </div>
                    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
                </form>
            </div>
            <div class="card-footer text-center">
                <a href="member.php?action=login" class="small text-muted">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to login
                </a>
            </div>
        </div>
    </div>';
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
        
		$member_loggedin_notice = '<div class="rounded p-2 mt-3 mb-3 bg-nav">'.$already_logged_in.'</div>';
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
   
    $login = '
	
	<html>
<head>
<title>'.$SITENAME.' - '.$lang->member['login'].'</title>
</head>
<body>
<div class="container-md">
<form action="member.php" method="post">
	
<div class="card">
		<div class="card-body">			
				'.$inline_errors.'
'.$member_loggedin_notice.'
			<div class="pb-3 border-bottom">
  <label for="username" class="form-label fw-bold">'.$lang->member['username'].'</label>
  <input class="form-control form-control-sm border" name="username" type="text" id="username" value="'.$username.'"/>
</div>
			
			<div class="py-3 border-bottom">
  <label for="password" class="form-label fw-bold">'.$lang->member['password'].'<br /><a href="'.$BASEURL.'/member.php?action=lostpw" class="fw-normal small">'.$lang->member['lostpw_note'].'</a></label>
  <input class="form-control form-control-sm border" name="password"  type="password" id="password" value="'.$password.'"/>
</div>
            
	
					<div class="form-check pt-3">
  <input class="form-check-input" type="checkbox" name="remember" value="yes" id="flexCheckDefault" checked>
  <label class="form-check-label" for="flexCheckDefault">
    '.$lang->member['remember_me'].'
  </label>
	</div>
					
						
	'.$lang->member['footer'].'			
   
	</div>
<div class="card-footer text-center">
<button type="submit" class="btn btn-primary" name="submit" value="'.$lang->member['login'].'"><i class="fa-solid fa-right-to-bracket"></i> &nbsp;'.$lang->member['login'].'</button>
	</div>
		
		
<input type="hidden" name="action" value="do_login" />
<input type="hidden" name="url" value="'.$redirect_url.'" />
<input name="my_post_key" type="hidden" value="'.$mybb->post_code.'" />
</form>
	</div>
	</div>
</body>
</html>';
	
	
	
	
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

        // Clear the admin-panel 2FA gate so a future login (same browser/session,
        // possibly a different account) doesn't inherit a stale "verified" state.
        unset($_SESSION['admin_2fa_ok_' . (int)$CURUSER['id']]);
        unset($_SESSION['admin_2fa_fail_count']);
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

    $parser_options = ['allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'allow_videocode' => 1, 'filter_badwords' => 1];

    $uid = $mybb->get_input('id', MyBB::INPUT_INT);
    $memprofile = $uid ? get_user($uid) : ($CURUSER['id'] ? $CURUSER : false);

    
	if (!$memprofile) 
	{ 
        stderr($lang->member['error_nomember'], $SITENAME . ' - Member Not Found', 404, '404'); 
	}

    $uid       = $memprofile['id'];
    $SameUser  = ($uid === (int)$CURUSER['id']);
    $IsStaff   = is_mod($usergroups);

    
	
	if ($memprofile['invisible'] == 1 && !$SameUser && !$IsStaff) 
	{ 
        stderr($lang->member['noperm'], $SITENAME . ' - Access Denied', 403, '403'); 
	}
	
   
	
	if ($memprofile['ustatus'] === 'pending') 
	{    
        stderr($lang->member['pendinguser'], $SITENAME . ' - Access Denied', 403, '403'); 
	}
	
	
	

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
        
		$sendemail = '<div class="py-2 border-bottom"><span class="text-muted">'.$lang->member['email'].'</span> <a href="member.php?action=emailuser&amp;id='.$memprofile['id'].'">'.$send_user_email.'</a></div>';
		
		
    }

    if ($enablepms != 0 && $uid != $CURUSER['id'] && $usergroups['canusepms'] == 1
        && (($memprofile['receivepms'] != 0 && $memperms['canusepms'] != 0
            && str_contains(',' . $memprofile['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') === false)
            || $usergroups['canoverridepm'] == 1)) {
        $bgcolor = alt_trow();
        
		$sendpm = '<div class="py-2 border-bottom"><span class="text-muted">'.$lang->member['pm'].'</span> <a href="private.php?action=send&amp;uid='.$memprofile['id'].'">'.$send_pms.'</a></div>';
    }

    $any_contact_field = false;
    if ($any_contact_field || $sendemail || $sendpm || $website) {
        
		$contact_details = '<div class="card-clean mb-4 hov-soft">
  <div class="p-3 border-bottom text-19 fw-bold d-flex align-items-center gap-2">
    <i class="bi bi-person-lines-fill"></i>
    <span>Contact Details</span>
  </div>

  <div class="p-3">
    <div class="list-row">
      <span class="muted d-flex align-items-center gap-2">
        <i class="bi bi-envelope-fill"></i>
        <span>Private Message</span>
      </span>
      <span>'.$sendpm.'</span>
    </div>

    <div class="list-row">
      <span class="muted d-flex align-items-center gap-2">
        <i class="bi bi-at"></i>
        <span>Email</span>
      </span>
      <span>'.$sendemail.'</span>
    </div>

  </div>
</div>';
		
		
		
    }

    $signature = '';
    if ($memprofile['signature']) {
        $sig_parser = ['allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'me_username' => $me_username, 'filter_badwords' => 1];
        $memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
        
		$signature = '<div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$users_signature.'
	</div>
	<div class="card-body border-bottom pb-3">
		'.$memprofile['signature'].'
	</div>
</div>';
		
		
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
                
				$online_status = '<a href="online.php"><span class="online" style="font-weight: bold; text-success">'.$lang->global['postbit_status_online'].'</span></a> ('.$location.' @ '.$location_time.')';
            }
        }
    }

    if (!isset($online_status)) {
        
		$online_status = '<span class="offline" style="font-weight: bold;">'.$lang->global['postbit_status_offline'].'</span>';
		
		
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
                $banlength     = $lang->member['permanent'];
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
            
			$bannedbit = '<div class="card-clean p-3 mb-4" style="border-left:4px solid #dc3545;">
  <div class="d-flex align-items-start gap-3">
    <div class="text-danger" style="font-size:1.5rem;">
      <i class="fa-solid fa-ban" aria-hidden="true"></i>
    </div>

    <div class="flex-grow-1">
      <div class="d-flex justify-content-between flex-wrap gap-2">
        <h5 class="mb-1 text-danger fw-semibold">'.$lang->member['ban_note'].'</h5>
        <span class="badge-soft" style="background:#ffe6e6;border-color:#ffc9c9;color:#b02a37;">
          <i class="fa-regular fa-clock me-1"></i> '.$banlength.'
        </span>
      </div>

      <div class="mt-2 small muted">
        <strong>'.$lang->global['banned_warning2'].'</strong>
      </div>

      <!-- Причина -->
      <div class="mt-2 p-2 rounded small" style="background:#fff5f5;border:1px solid #f8d7da;">
        '.$memban['reason'].'
      </div>

      <!-- Метаданные -->
      <div class="mt-2 small muted">
        <span class="me-3">
          <i class="fa-solid fa-user-shield me-1"></i>
          <strong>'.$lang->member['ban_by'].'</strong>
          <span class="links">'.$memban['adminuser'].'</span>
        </span>
        <span>
          <i class="fa-regular fa-hourglass-half me-1"></i>
          <strong>'.$lang->member['ban_length'].'</strong> '.$banlength.'
          <span class="ms-1">'.$timeremaining.'</span>
        </span>
      </div>
    </div>
  </div>
</div>';
        
		}
    }

    $memprofile['regip']  = my_inet_ntop($db->unescape_binary($memprofile['regip']));
    $memprofile['lastip'] = my_inet_ntop($db->unescape_binary($memprofile['lastip']));
    
	
	$ipaddress = '<div class="py-2 border-bottom">
						<span class="text-muted">Registration IP:</span> '.$memprofile['regip'].'
					</div>
					<div class="py-2 border-bottom">
						<span class="text-muted">Last Known IP:</span> '.$memprofile['lastip'].'
					</div>';
	
	
	

    // Stats
  
	$zaza = $cache->read("stats");
	
    $stats = $zaza['numposts'];
    $stats22 = $zaza['numthreads'];
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
        
		$manageban = '<li><a href="'.$BASEURL.'/modcp.php?action=banuser&amp;uid='.$uid.'">'.$lang->member['edit_ban_in_mcp'].'</a></li>
        <li><a href="'.$BASEURL.'/modcp.php?action=liftban&amp;uid='.$uid.'&amp;my_post_key='.$mybb->post_code.'">'.$lang->member['lift_ban_in_mcp'].'</a></li>';
		
		
    } else {
        
		$banuser = '<li><a href="'.$BASEURL.'/modcp.php?action=banuser&amp;uid='.$uid.'">'.$lang->member['ban_in_mcp'].'</a></li>';
		
		
    }
    
	$editprofile = '<li><a href="'.$BASEURL.'/admin/edituser.php?action=edituser&userid='.$uid.'">'.$lang->member['edit_in_mcp'].'</a></li>';
	
    $manageuser = $editprofile . $banuser . $manageban;

    if ($IsStaff) {
        

$modoptions = '<!-- Moderator Options (compact) -->
<div class="card-clean mb-4 hov-soft">
  <div class="p-3 border-bottom text-19 fw-bold d-flex align-items-center gap-2">
    <i class="bi bi-shield-check"></i><span>Moderator Options</span>
  </div>

  <div class="p-3">
    <div class="list-row">
      <span class="muted d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt-fill"></i><span>IP Address</span>
      </span>
      <span>'.$ipaddress.'</span>
    </div>

    <div class="mt-3">
      <div class="muted mb-2 d-flex align-items-center gap-2">
        <i class="bi bi-tools"></i><span>Quick actions</span>
      </div>

      
      <ul id="mod-actions" class="icon-grid m-0 list-unstyled">
        '.$manageuser.'
      </ul>
    </div>
  </div>
</div>

<style>
  .icon-grid{display:flex;flex-wrap:wrap;gap:.5rem}
  .icon-grid a{
    display:inline-flex;align-items:center;justify-content:center;
    width:36px;height:36px;border-radius:10px;
    border:1px solid #e5e7eb;background:#f9fafb;text-decoration:none;
  }
  .icon-grid a:hover{transform:translateY(-2px)}
  @media (prefers-color-scheme: dark){
    .icon-grid a{border-color:#1f2a38;background:#0f1720}
  }
</style>
<script type="text/javascript" src="' . $BASEURL . '/scripts/mod-actions.js"></script>';

		
		
    }

    $findposts = $findthreads = '';
    if (!empty($memprofile['postnum']))  { 
	
	$findposts   = '<a href="search.php?action=finduser&amp;uid='.$uid.'" class="text-decoration-none text-primary">
    <i class="fa-solid fa-file-lines me-1"></i> '.$lang->member['find_posts'].'
</a>'; 
	
	}
    if (!empty($memprofile['threadnum']))
	{ 
        $findthreads = '<a href="search.php?action=finduserthreads&amp;uid='.$uid.'" class="text-decoration-none text-success">
                       <i class="fa-solid fa-comments me-1"></i> '.$lang->member['find_threads'].'
                       </a>'; 
		
	}

    // Buddy/ignore
    $add_remove_options = [];
    $buddy_options = $ignore_options = '';
    if ($CURUSER['id'] != $memprofile['id'] && $CURUSER['id'] != 0) {
        $buddy_list  = explode(',', $CURUSER['buddylist']);
        $ignore_list = explode(',', $CURUSER['ignorelist']);

        $add_remove_options = in_array($uid, $buddy_list)
            ? ['url' => "usercp.php?action=do_editlists&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_buddy_button', 'lang' => 'Remove from Buddy List']
            : ['url' => "usercp.php?action=do_editlists&amp;add_username=" . urlencode($memprofile['username']) . "&amp;my_post_key={$mybb->post_code}", 'class' => 'add_buddy_button', 'lang' => 'Add to Buddy List'];

        if (!in_array($uid, $ignore_list)) 
		{ 
	        
			
			$buddy_options = '<li><a href="'.$add_remove_options['url'].'" class="links">'.$add_remove_options['lang'].'</a></li>'; 
			
		}

        $add_remove_options = in_array($uid, $ignore_list)
            ? ['url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_ignore_button', 'lang' => 'Remove from Ignore List']
            : ['url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;add_username=" . urlencode($memprofile['username']) . "&amp;my_post_key={$mybb->post_code}", 'class' => 'add_ignore_button', 'lang' => 'Add to Ignore List'];

        if (!in_array($uid, $buddy_list)) 
		{ 
	          $ignore_options = '<li><a href="'.$add_remove_options['url'].'" class="links">'.$add_remove_options['lang'].'</a></li>'; 
			  
	    }
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
        $sig_parser = ['allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'me_username' => $me_username, 'filter_badwords' => 1];
        $memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
        
		$signature = '<div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$users_signature.'
	</div>
	<div class="card-body border-bottom pb-3">
		'.$memprofile['signature'].'
	</div>
</div>';
		
		
    }

    $formattedname = format_name($memprofile['username'], $memprofile['usergroup'], $memprofile['displaygroup']);

    $profile = '
	
	<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>'.$SITENAME.' - '.$lang->member['profile'].'</title>
  
  <script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js"></script>
  <script type="text/javascript" src="'.$BASEURL.'/scripts/report_user.js"></script>
  
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  
  <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/member_profile.css">

</head>
<body>
<div class="container-md my-4">
  <!-- HERO -->
  <div class="profile-hero p-3 p-md-4 mb-4 hov-soft">
    <div class="hero-inner">
      <div class="row g-3 g-lg-4 align-items-center">
        
		
	
<div class="col-auto">
  <div class="avatar-ring position-relative hov-soft"
       id="avatar-container"
       data-uid="'.$memprofile['id'].'"
       data-can-change="'.$can_change_avatar.'"
       title="Avatar">
    <div>
      '.$avatar.'
      '.$status_dot_html.'
      <span class="avatar-overlay">Change</span>
    </div>

    <div id="avatar-progress"><div id="avatar-progress-bar"></div></div>
  </div>
</div>

<input type="file" id="avatar-input" accept="image/*" style="display:none;">
		
		


        <div class="col">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <h2 class="mb-0 me-1">'.$formattedname.'</h2> '.$usericons.'
          </div>
          <div class="muted mt-1">'.$usertitle.'</div>
          <div class="mt-2">'.$groupimage.'</div>
          <div class="mt-2">'.$userstars.'</div>

          <div class="mt-3 d-flex flex-wrap gap-2">
    <span class="chip"><i class="bi bi-upload me-1 text-success"></i> Seeding: <strong>'.$active_seeds.'</strong></span>
    <span class="chip"><i class="bi bi-download me-1 text-danger"></i> Leeching: <strong>'.$active_leeches.'</strong></span>
    <span class="chip"><i class="bi bi-check2-circle me-1 text-primary"></i> Completed: <strong>'.$times_completed_total.'</strong></span>
    <span class="chip"><i class="bi bi-calendar-check me-1 text-muted"></i> Joined: <strong>'.$memregdate.'</strong></span>
</div>
        </div>

        <div class="col-12 col-lg-3">
          <div class="d-grid gap-2">
            <a href="private.php?action=send&uid='.$memprofile['id'].'" class="btn btn-primary btn-sm" aria-label="Send private message">
              <i class="bi bi-envelope me-1"></i> Send PM
            </a>
            <a href="misc.php?action=buddy&add='.$memprofile['id'].'" class="btn btn-outline-secondary btn-sm" aria-label="Add to buddy list">
              <i class="bi bi-person-plus me-1"></i> Add to Buddy
            </a>
            '.$report_button.'
          </div>
		  
		   <!-- Мини-статистика под кнопками -->
    <div class="mt-3 p-3 card-clean text-center">
        <div class="row g-2">
            <div class="col-6">
                <div class="muted" style="font-size:0.7rem;">POSTS</div>
                <div class="fw-bold">'.$memprofile['postnum'].'</div>
            </div>
            <div class="col-6">
                <div class="muted" style="font-size:0.7rem;">THREADS</div>
                <div class="fw-bold">'.$memprofile['threadnum'].'</div>
            </div>
            <div class="col-6">
                <div class="muted" style="font-size:0.7rem;">SEEDS</div>
                <div class="fw-bold text-success">'.$active_seeds.'</div>
            </div>
            <div class="col-6">
                <div class="muted" style="font-size:0.7rem;">LEECHES</div>
                <div class="fw-bold text-danger">'.$active_leeches.'</div>
            </div>
        </div>
    </div>
		  
        </div>
      </div>
    </div>
  </div>


<!-- METRICS — замените весь блок -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="metric text-center hov-soft">
            <div class="label">Ratio</div>
            <div class="value {$ratio_class}" style="font-size:1.35rem;">'.$ratio.'</div>
            <div class="mt-2">
                
                   '.$ratio_label.'
				  
                
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric text-center hov-soft">
            <div class="label"><i class="bi bi-check2-circle me-1"></i>Snatched</div>
            <div class="value" style="font-size:1.35rem;">'.$times_completed_total.'</div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="metric hov-soft">
            <div class="d-flex justify-content-between align-items-center">
                <div class="label"><i class="bi bi-arrow-up-right text-success me-1"></i>Uploaded</div>
                <div class="value text-success">'.$uploaded.'</div>
            </div>
            <div class="progress mt-2">
                <div class="progress-bar" style="width:{$uploaded_percent}%;background:linear-gradient(90deg,#4ade80,#22c55e)"></div>
            </div>
            <div class="d-flex justify-content-between mt-1">
                <small class="muted">'.$uploaded_percent.'% of total</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="metric hov-soft">
            <div class="d-flex justify-content-between align-items-center">
                <div class="label"><i class="bi bi-arrow-down-right text-danger me-1"></i>Downloaded</div>
                <div class="value text-danger">'.$downloaded.'</div>
            </div>
            <div class="progress mt-2">
                <div class="progress-bar" style="width:'.$downloaded_percent.'%;background:linear-gradient(90deg,#f87171,#ef4444)"></div>
            </div>
            <div class="d-flex justify-content-between mt-1">
                <small class="muted">'.$downloaded_percent.'% of total</small>
            </div>
        </div>
    </div>
</div>

  <div class="row g-4">
    <!-- LEFT -->
    <div class="col-12 col-lg-8">
      <!-- Tabs -->
      
	  
	 
	  
	  
	  
	  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-about" type="button">
            <i class="bi bi-person me-1"></i>About
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-forum" type="button">
            <i class="bi bi-chat-dots me-1"></i>Forum
            <span class="badge-soft ms-1" style="font-size:0.7rem;">'.$memprofile['postnum'].'</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-torrents" type="button">
            <i class="bi bi-collection-play me-1"></i>Torrents
            <span class="badge-soft ms-1" style="font-size:0.7rem;">'.$times_completed_total.'</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-contact" type="button">
            <i class="bi bi-envelope me-1"></i>Contact
        </button>
    </li>
</ul>
	  
	  
	  
	  
	  
	  

      <div class="tab-content">
        <!-- ABOUT -->
        <div class="tab-pane fade show active" id="tab-about">
          
          <!-- красивый бан-блок: сформируй в PHP в переменную новым стилем -->
          '.$bannedbit.'
          '.$signature.'

          

          <div class="card-clean p-3 mt-3 hov-soft">
            <div class="row row-cols-1 row-cols-md-2 g-3">
              <div class="col">
                <div class="list-row">
                  <span class="muted"><i class="bi bi-calendar-event me-1"></i>'.$lang->member['joined'].'</span>
                  <span>'.$memregdate.'</span>
                </div>
                <div class="list-row">
                  <span class="muted"><i class="bi bi-clock-history me-1"></i>'.$lang->member['lastvisit'].'</span>
                  <span>'.$memlastvisitdate.'</span>
                </div>
                <div class="list-row">
                  <span class="muted"><i class="bi bi-cake me-1"></i>'.$lang->member['date_of_birth'].'</span>
                  <span>'.$membday.' '.$membdayage.'</span>
                </div>
                <div class="list-row">
                  <span class="muted"><i class="bi bi-globe2 me-1"></i>'.$lang->member['local_time'].'</span>
                  <span>'.$localtime.'</span>
                </div>
              </div>
              <div class="col">
                '.$invs.'
                <!-- соцсети / доп. факты сюда -->
              </div>
            </div>
          </div>

          
		  
		<!-- Status & time -->
              <div class="card-clean p-0 mt-3 hov-soft">
                <div class="p-3 border-bottom d-flex align-items-center gap-2">
                  <i class="bi bi-activity"></i>
                  <strong>Status</strong>
                </div>
                <div class="p-3">
                  <div class="list-row">
                    <span class="muted d-flex align-items-center gap-2">
                      <i class="bi bi-circle-fill" style="font-size:.6rem"></i>
                      '.$lang->global['postbit_status'].'
                    </span>
                    <span>'.$online_status.'</span>
                  </div>
                  <div class="list-row">
                    <span class="muted d-flex align-items-center gap-2">
                      <i class="bi bi-clock-history"></i>
                      '.$lang->member['timeonline'].'
                    </span>
                    <span>'.$timeonline.'</span>
                  </div>
                </div>
              </div>
		  
		  
		  
		  
        </div>





<!-- FORUM -->
<div class="tab-pane fade" id="tab-forum">

    <!-- KPIs постов/тредов -->
    <div class="card-clean mb-3 hov-soft">
        <div class="p-3 border-bottom text-19 fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-chat-dots-fill"></i>
            <span>'.$users_forum_info.'</span>
        </div>
        <div class="p-3">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="metric hov-soft d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-light d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                            <i class="bi bi-chat-left-text-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="label d-flex justify-content-between align-items-center">
                                <span>'.$lang->member['total_posts'].'</span>
                                <span class="badge-soft">'.$ppd_percent_total.'</span>
                            </div>
                            <div class="value mt-1">'.$memprofile['postnum'].'</div>
                        </div>
                        <div class="ms-2 d-none d-md-block">'.$findposts.'</div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="metric hov-soft d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-light d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="label d-flex justify-content-between align-items-center">
                                <span>'.$lang->member['total_threads'].'</span>
                                <span class="badge-soft">'.$tpd_percent_total.'</span>
                            </div>
                            <div class="value mt-1">'.$memprofile['threadnum'].'</div>
                        </div>
                        <div class="ms-2 d-none d-md-block">'.$findthreads.'</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Последние комментарии -->
    <div class="card-clean p-0 hov-soft">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <span class="text-19 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-chat-left-text-fill"></i>
                Recent Comments
            </span>
            <span class="badge-soft">'.$memprofile['comms'].'</span>
        </div>
        <div class="p-3">
            '.$recent_comments_html.'
        </div>
    </div>

</div>
		
		

        
		
		
		
		
		<!-- TORRENTS -->
<div class="tab-pane fade" id="tab-torrents">
    <div class="card-clean p-0 hov-soft mb-3">
        <div class="p-3 border-bottom text-19 fw-bold">Uploaded torrents</div>
        <div class="p-3">'.$recent_user_torrents.'</div>
    </div>

    <!-- Seeding & Leeching в одну колонку -->
    <div class="card-clean p-0 hov-soft mb-3">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <span class="text-19 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-cloud-upload"></i> Seeding now
            </span>
            <span class="badge-soft">'.$active_seeds.'</span>
        </div>
        <div class="p-3">
            <!-- Здесь каждый элемент seeding будет на всю ширину -->
            '.$seeding_now.'
        </div>
    </div>

    <div class="card-clean p-0 hov-soft mb-3">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <span class="text-19 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-cloud-download"></i> Leeching now
            </span>
            <span class="badge-soft">'.$active_leeches.'</span>
        </div>
        <div class="p-3">
            <!-- Здесь каждый элемент leeching будет на всю ширину -->
            '.$leeching_now.'
        </div>
    </div>

    <div class="card-clean p-0 hov-soft">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <span class="text-19 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i> Completed recently
            </span>
            <span class="badge-soft">'.$times_completed_total.'</span>
        </div>
        <div class="p-3">'.$completed_list.'</div>
    </div>
</div>
		
		
		
		
		

        <!-- CONTACT -->
        <div class="tab-pane fade" id="tab-contact">'.$contact_details.'</div>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="col-12 col-lg-4">
      <div class="sticky-col">
        '.$modoptions.'
      </div>
    </div>
  </div>
</div>


</body>
</html>';

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
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
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Security Check</h6>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                                        id="userReportRefreshCaptcha">
                                    <i class="fa-solid fa-arrows-rotate"></i>
                                </button>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-6">
                                    <img src="report_captcha.php" alt="Security code" class="border rounded"
                                         id="userReportCaptchaDisplay" style="cursor:pointer;height:56px;width:100%;object-fit:cover;"
                                         title="Click to refresh">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control"
                                           id="userReportCaptchaInput" name="captcha_response" placeholder="Enter code" autocomplete="off">
                                </div>
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
    if ($CURUSER['id'] == 0) 
	{ 
         $from_email = '<div class="pb-4 border-bottom mb-4">
	<label for="fromname">'.$lang->member['your_name'].'</label>
		<input type="text" class="form-control border form-control-sm" size="50" name="fromname" value="'.$fromname.'" />
	</div>

     <div class="pb-4 border-bottom mb-4">
			<label for="fromemail">'.$lang->member['your_email'].'</label>
		<input type="text" class="form-control border form-control-sm" size="50" name="fromemail"  value="'.$fromemail.'" />
	</div>'; 
		 
	}

    $plugins->run_hooks('member_emailuser_end');
	

$emailuser = '
<div class="container-md mt-4">
    ' . $errors . '
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0">
                <i class="fas fa-envelope me-2"></i>' . $lang->member['email_user'] . '
            </h5>
        </div>
        <form action="member.php" method="post">
            <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
            <input type="hidden" name="action" value="do_emailuser">
            <input type="hidden" name="id" value="' . (int)$to_user['id'] . '">
            <div class="card-body">
                ' . $from_email . '
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-heading me-1 text-muted"></i>' . $lang->member['email_subject'] . '
                    </label>
                    <input type="text" class="form-control" name="subject" value="' . htmlspecialchars_uni($subject) . '">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-align-left me-1 text-muted"></i>' . $lang->member['email_message'] . '
                    </label>
                    <textarea class="form-control" name="message" rows="10" style="resize:vertical">' . htmlspecialchars_uni($message) . '</textarea>
                </div>
            </div>
            <div class="card-footer bg-light text-center py-3">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-paper-plane me-2"></i>' . $lang->member['send_email'] . '
                </button>
                <a href="javascript:history.back()" class="btn btn-outline-secondary ms-2 px-4">
                    <i class="fas fa-times me-2"></i>' . $lang->member['cans'] . '
                </a>
            </div>
        </form>
    </div>
</div>';



	
    stdhead('title');
    echo $emailuser;
    stdfoot();
}

// ── Default redirect ───────────────────────────────────────────────────────
if (!$mybb->input['action']) {
    header('Location: index.php');
}