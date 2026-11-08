<?php
declare(strict_types=1);

if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

/* ------------------------------------------------------------------ *
 *  Helpers (defined once, used everywhere below)
 * ------------------------------------------------------------------ */
$e  = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$hl = static fn(string $k, string $fb = ''): string => $e($lang->header[$k] ?? $fb);   // $lang->header, escaped
$gl = static fn(string $k, string $fb = ''): string => $e($lang->global[$k] ?? $fb);   // $lang->global, escaped

$base = $e($BASEURL ?? '');                                       // escaped base URL, computed once
$url  = static fn(string $path): string => $base . '/' . $path;   // returns an already-escaped URL

$uid        = (int)($CURUSER['id'] ?? 0);
$isLoggedIn = $uid > 0;

$ug      = is_array($usergroups ?? null) ? $usergroups : [];
$isAdmin = (int)($ug['canstaffpanel'] ?? 0) === 1
        && (int)($ug['cansettingspanel'] ?? 0) === 1
        && (int)($ug['issupermod'] ?? 0) === 1;

/** Prints a <link> for a stylesheet only the first time it is requested (no duplicates in the page). */
$cssOnce = static function (string $file) use ($base): string {
    static $done = [];
    if (isset($done[$file])) {
        return '';
    }
    $done[$file] = true;

    // ?v=<mtime>: the browser re-downloads the CSS as soon as the file changes on the server
    $ver  = '';
    $path = (defined('INC_PATH') ? INC_PATH : '') . '/templates/default/style/' . $file;
    if (defined('INC_PATH') && is_file($path)) {
        $ver = '?v=' . filemtime($path);
    }

    return '<link rel="stylesheet" href="' . $base . '/include/templates/default/style/' . $file . $ver . '">';
};

/** Safe embedding of values into inline <script>. */
$jsFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// Toast titles for toast.js (toastT() reads window.AGS_TOAST_LANG)
$ags_toast = [];
foreach (['success', 'error', 'warning', 'info', 'close'] as $k) {
    $ags_toast[$k] = (string)($lang->global['js_toast_' . $k] ?? '');
}

// Language switcher
$current_lang = $_COOKIE['ts_language'] ?? $defaultlanguage ?? 'english';
$languages = [
    'english' => ['flag' => '🇬🇧', 'code' => 'EN'],
    'russian' => ['flag' => '🇷🇺', 'code' => 'RU'],
];

/* ------------------------------------------------------------------ *
 *  Navigation data (rendered by $renderMenu — no copy-pasted <li> blocks)
 *  item = [href (escaped), fa-icon, label (escaped)] | null = divider
 * ------------------------------------------------------------------ */
$navMenus = [
    ['card' => true, 'icon' => 'fa-list', 'label' => $hl('nav_browse', 'Browse'), 'items' => [
        [$url('browse.php'), 'fa-table-list', $hl('nav_browse_torrents', 'Browse Torrents')],
        [$url('upload.php'), 'fa-upload', $hl('hdr_upload', 'Upload')],
        null,
        [$url('browse.php?special_search=mybookmarks'), 'fa-bookmark', $hl('nav_my_bookmarks', 'My Bookmarks')],
        [$url('browse.php?special_search=myreseeds'), 'fa-seedling', $hl('nav_my_reseeds', 'My Reseeds')],
        [$url('browse.php?special_search=weaktorrents'), 'fa-heart-crack', $hl('nav_weak_torrents', 'Weak Torrents')],
    ]],
    ['card' => true, 'icon' => 'fa-comments', 'label' => $hl('nav_forums', 'Forums'), 'items' => [
        [$url('index2.php'), 'fa-forumbee', $hl('nav_forums_home', 'Forums')],
        [$url('search.php?action=getnew'), 'fa-star', $hl('hdr_new_posts', 'New Posts')],
        [$url('search.php?action=getdaily'), 'fa-calendar-day', $hl('hdr_todays_posts', "Today's Posts")],
        [$url('search.php'), 'fa-search', $hl('nav_search', 'Search')],
        null,
        [$url('chat.php'), 'fa-comment-dots', $hl('hdr_chat', 'Chat')],
    ]],
    ['card' => true, 'icon' => 'fa-user', 'label' => $hl('hdr_user_cp', 'User CP'), 'items' => [
        [$url('usercp.php'), 'fa-cog', $hl('nav_usercp_home', 'User CP Home')],
        [$url('private.php'), 'fa-envelope', $hl('nav_private_messages', 'Private Messages')],
        [$url('browse.php?special_search=mytorrents'), 'fa-download', $hl('nav_your_torrents', 'Your Torrents')],
    ]],
    ['card' => true, 'icon' => 'fa-chart-line', 'label' => $hl('hdr_top10', 'Top 10'), 'items' => [
        [$url('topten.php?type=1'), 'fa-users', $hl('nav_top_users', 'Users')],
        [$url('topten.php?type=2'), 'fa-download', $hl('nav_top_torrents', 'Torrents')],
        [$url('topten.php?type=3'), 'fa-globe', $hl('nav_top_countries', 'Countries')],
        [$url('topten.php?type=4'), 'fa-network-wired', $hl('nav_top_peers', 'Peers')],
        [$url('topten.php?type=5'), 'fa-comments', $hl('nav_forums', 'Forums')],
    ]],
    ['card' => false, 'icon' => 'fa-ellipsis-h', 'label' => $gl('extra', 'Extra'), 'items' => [
        ['__PROFILE__', 'fa-user-circle', $hl('nav_your_profile', 'Your Profile')],
        [$url('memberlist.php'), 'fa-users', $hl('nav_members_list', 'Members List')],
        [$url('getrss.php'), 'fa-rss', $hl('nav_rss_feeds', 'RSS Feeds')],
        [$url('invite.php'), 'fa-user-plus', $hl('nav_invite_friend', 'Invite Friend')],
        [$url('mybonus.php'), 'fa-coins', $hl('nav_bonus_points', 'Bonus Points')],
        [$url('task.php'), 'fa-list-check', $hl('nav_tasks', 'Tasks')],
        null,
        [$url('requests.php'), 'fa-list-alt', $hl('nav_requests', 'Requests')],
        [$url('offers.php'), 'fa-gift', $hl('nav_offers', 'Offers')],
    ]],
    ['card' => false, 'icon' => 'fa-ellipsis-h', 'label' => $hl('nav_help', 'Help'), 'items' => [
        [$url('videoformats.php'), 'fa-video', $hl('nav_video_formats', 'Video Formats')],
        [$url('links.php'), 'fa-link', $hl('nav_torrent_links', 'Torrent Links')],
        null,
        [$url('faq.php'), 'fa-question-circle', $hl('nav_faq', 'FAQ')],
        [$url('rules.php'), 'fa-gavel', $hl('nav_rules', 'Rules')],
    ]],
    ['card' => false, 'icon' => 'fa-shield-alt', 'label' => $gl('staff', 'Staff'), 'items' => [
        [$url('showteam.php'), 'fa-users-cog', $hl('nav_staff_team', 'Staff Team')],
        [$url('contactstaff.php'), 'fa-envelope', $hl('nav_contact_staff', 'Contact Staff')],
    ]],
];

// Icon colors (CSS classes .ic-<name> live in the <style> block). Same icon = same color everywhere.
$iconColors = [
    // Browse
    'fa-list' => 'blue', 'fa-table-list' => 'blue', 'fa-upload' => 'green', 'fa-bookmark' => 'orange',
    'fa-seedling' => 'green', 'fa-heart-crack' => 'red',
    // Forums
    'fa-comments' => 'purple', 'fa-forumbee' => 'orange', 'fa-star' => 'yellow',
    'fa-calendar-day' => 'teal', 'fa-search' => 'blue', 'fa-comment-dots' => 'pink',
    // User CP
    'fa-user' => 'blue', 'fa-cog' => 'indigo', 'fa-envelope' => 'cyan', 'fa-download' => 'teal',
    // Top 10
    'fa-chart-line' => 'orange', 'fa-users' => 'indigo', 'fa-globe' => 'cyan', 'fa-network-wired' => 'purple',
    // Extra
    'fa-ellipsis-h' => 'gray', 'fa-user-circle' => 'blue', 'fa-rss' => 'orange', 'fa-user-plus' => 'green',
    'fa-coins' => 'yellow', 'fa-list-check' => 'teal', 'fa-list-alt' => 'indigo', 'fa-gift' => 'pink',
    // Help
    'fa-video' => 'red', 'fa-link' => 'cyan', 'fa-question-circle' => 'blue', 'fa-gavel' => 'orange',
    // Staff
    'fa-shield-alt' => 'green', 'fa-users-cog' => 'purple',
];

$renderMenu = static function (array $m, string $profileHref) use ($iconColors): string {
    $card = !empty($m['card']);
    $ic   = static fn(string $icon, string $space) => 'fas ' . $icon . ' ' . $space
          . (isset($iconColors[$icon]) ? ' ic-' . $iconColors[$icon] : '');
    $out  = '<li class="nav-item dropdown">'
          . '<a class="nav-link' . ($card ? ' nav-link-card' : '') . ' dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">'
          . '<i class="' . $ic($m['icon'], 'me-1') . '"></i> ' . $m['label'] . '</a>'
          . '<ul class="dropdown-menu' . ($card ? ' dropdown-menu-card' : '') . '">';

    foreach ($m['items'] as $it) {
        if ($it === null) {
            $out .= '<li><hr class="dropdown-divider"></li>';
            continue;
        }
        [$href, $icon, $label] = $it;
        if ($href === '__PROFILE__') {
            $href = $profileHref;
        }
        $out .= '<li><a class="dropdown-item' . ($card ? ' dropdown-item-card' : '') . '" href="' . $href . '">'
              . '<i class="' . $ic($icon, 'me-2') . '"></i> ' . $label . '</a></li>';
    }

    return $out . '</ul></li>';
};

?>
<!doctype html>
<html lang="<?= $hl('html_lang', 'en') ?>">
<head>
    <meta charset="<?= $e($charset ?? 'UTF-8') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title ?? 'ArtCore Gangsta') ?></title>
<?php if (!empty($metadesc)): ?>
    <meta name="description" content="<?= $e($metadesc) ?>">
<?php endif; ?>
<?php if (!empty($metakeywords)): ?>
    <meta name="keywords" content="<?= $e($metakeywords) ?>">
<?php endif; ?>
    <meta name="robots" content="index, follow">

    <link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?= $url('rss.php') ?>">
    <link rel="icon" href="<?= $url('favicon.ico') ?>" type="image/x-icon">

    <?= $includeCSS ?? '' ?>

    <link rel="stylesheet" href="<?= $url('include/templates/default/style/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= $url('include/templates/default/style/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= $url('include/templates/default/style/all.min.css') ?>">

    <style>
        header.sticky-top {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1020;
        }
        .header-fixed {
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0 15px;
        }
        .nav-link.dropdown-toggle::after { display: none; }

        /* Russian labels are longer: tighter menu items on desktop so the language
           switch and the theme toggle stay inside the 1100px header */
        @media (min-width: 992px) {
            .header-fixed .navbar .navbar-nav .nav-link { padding-left: .45rem; padding-right: .45rem; white-space: nowrap; }
            .header-fixed .navbar-brand { margin-right: .5rem; }
        }

        .nav-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 6px;
            border: 1px solid #ccc;
        }
        .user-menu   { width: 250px; }
        .search-menu { min-width: 300px; }
        #toastContainer { z-index: 2000; }

        /* Rectangular error/success cards (works even if errorss.css is stale) */
        .error-card, .error-card-ok, .error-card .card-body, .error-card-ok .card-body { border-radius: 0 !important; }

        /* Colored menu icons (Bootstrap 5.3 palette, works in light and dark theme) */
        .ic-blue   { color: var(--bs-blue); }
        .ic-green  { color: var(--bs-green); }
        .ic-red    { color: var(--bs-red); }
        .ic-orange { color: var(--bs-orange); }
        .ic-yellow { color: #f2b600; }
        .ic-purple { color: var(--bs-purple); }
        .ic-teal   { color: var(--bs-teal); }
        .ic-pink   { color: var(--bs-pink); }
        .ic-cyan   { color: var(--bs-cyan); }
        .ic-indigo { color: var(--bs-indigo); }
        .ic-gray   { color: var(--bs-secondary-color); }
        .dropdown-item i, .nav-link i { transition: transform .15s; }
        .dropdown-item:hover i { transform: scale(1.15); }

        /* Language switcher */
        .lang-switch {
            display: inline-flex;
            border: .5px solid rgba(128, 128, 128, .3);
            border-radius: 999px;
            overflow: hidden;
            background: rgba(128, 128, 128, .07);
        }
        .lang-switch a {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 400;
            text-decoration: none;
            color: inherit;
            white-space: nowrap;
            transition: background .2s, color .2s;
        }
        .lang-switch a.active { background: #378ADD; color: #fff; font-weight: 500; }
        .lang-switch .flag { font-size: 14px; line-height: 1; }
    </style>

    <!-- Apply the saved theme immediately (prevents a light/dark flash on load) -->
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('bsTheme'); } catch (e) {}
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>

    <!-- Globals expected by core.js and other scripts -->
    <script>
        var baseurl  = <?= json_encode((string)($BASEURL ?? ''), $jsFlags) ?>;
        var dimagedir = <?= json_encode(($BASEURL ?? '') . '/' . ($pic_base_url ?? ''), $jsFlags) ?>;
        var charset  = <?= json_encode((string)($charset ?? 'UTF-8'), $jsFlags) ?>;
        var userid   = <?= json_encode((string)$uid, $jsFlags) ?>;
        var use_xmlhttprequest = <?= json_encode((string)(int)($use_xmlhttprequest ?? 0), $jsFlags) ?>;
        var my_post_key = <?= json_encode((string)($mybb->post_code ?? ''), $jsFlags) ?>;
        window.AVATAR_NOT_FOUND_URL = <?= json_encode(avatar_not_found_svg_url(), $jsFlags) ?>;
        window.AGS_TOAST_LANG = <?= json_encode($ags_toast, $jsFlags) ?>;
    </script>

    <script src="<?= $url('scripts/core.js?ver=12') ?>"></script>
    <script src="<?= $url('scripts/bootstrap.bundle.min.js') ?>"></script>

    <script>
        // "Back to top" button (passive listener + rAF: no layout work on every scroll event)
        function scrollToTop() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        }
        (function () {
            var btn = null, ticking = false;
            function update() {
                ticking = false;
                btn = btn || document.getElementById('back-to-up');
                if (btn) btn.classList.toggle('d-none', (window.scrollY || document.documentElement.scrollTop) <= 20);
            }
            window.addEventListener('scroll', function () {
                if (!ticking) { ticking = true; requestAnimationFrame(update); }
            }, {passive: true});
        })();

        document.addEventListener('DOMContentLoaded', function () {
            // Theme switch
            var sw = document.getElementById('darkModeSwitch');
            if (sw) {
                sw.checked = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                sw.addEventListener('change', function () {
                    var theme = this.checked ? 'dark' : 'light';
                    document.documentElement.setAttribute('data-bs-theme', theme);
                    try { localStorage.setItem('bsTheme', theme); } catch (e) {}
                });
            }
            // Tooltips
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        });
    </script>
</head>
<body>
<div class="container md">
    <button class="btn btn-primary position-fixed bottom-0 end-0 translate-middle d-none" onclick="scrollToTop()" id="back-to-up" type="button" aria-label="<?= $hl('hdr_back_to_top', 'Back to top') ?>">
        <i class="fa fa-arrow-up" aria-hidden="true"></i>
    </button>

<?php
// ---------------------------------------------------------------------
//  Per-user data (only for logged-in users — guests skip the DB query)
// ---------------------------------------------------------------------
$admincplink = $usercplink = $avatar_mini = $avataar = $profilelink_link = $logoutkey = '';
$seedtorrentscount = $leechingtorrentscount = 0;

$pms_unread = ts_nf((int)($CURUSER['pms_unread'] ?? 0));
$pms_total  = ts_nf((int)($CURUSER['pms_total'] ?? 0));
$welcome_pms_usage = sprintf($lang->global['welcome_pms_usage'] ?? '', $pms_unread, $pms_total);

// Kept for compatibility with other templates (not used directly in this file)
$pmslink = '
<div class="d-none d-lg-block">
    <a href="' . $url('private.php') . '" title="' . $e($welcome_pms_usage) . '">
        <i class="fa-solid fa-envelope"></i> &nbsp;' . $gl('welcome_pms') . '
    </a>
</div>
<div class="d-block d-lg-none">
    <a href="' . $url('private.php') . '"><i class="fa-solid fa-envelope"></i></a>
</div>';

$usercplink = '
<a href="' . $url('usercp.php') . '" class="dropdown-item pb-2 ms-0 ps-0" style="border-bottom: 1px solid #b3b3b3">
    <i class="fa-solid fa-user-gear"></i> &nbsp;' . $hl('hdr_user_cp', 'User CP') . '
</a>';

if ($isAdmin) {
    $admincplink = '
    <a href="' . $url('admin/index.php') . '" class="dropdown-item ms-0 ps-0">
        <i class="fa-solid fa-gears"></i> &nbsp;' . $hl('hdr_admin_cp', 'Admin CP') . '
    </a>';
}

if ($isLoggedIn) {
    require_once INC_PATH . '/functions_notify.php';

    if (isset($CURUSER['loginkey']) && is_string($CURUSER['loginkey'])) {
        $logoutkey = md5($CURUSER['loginkey']);
    }
    $profilelink_link = get_profile_link($uid);

    // Seeding / leeching counters
    $q  = $db->sql_query_prepared("SELECT SUM(seeder='yes') AS seeding, SUM(seeder='no') AS leeching FROM peers WHERE userid = ?", [$uid]);
    $pr = $db->fetch_array($q);
    $seedtorrentscount     = (int)($pr['seeding']  ?? 0);
    $leechingtorrentscount = (int)($pr['leeching'] ?? 0);

    // Avatar
    $useravatar = format_avatar($CURUSER['avatar'] ?? '', $CURUSER['avatardimensions'] ?? '');
    if (str_starts_with($useravatar['image'] ?? '', '<')) {
        $avatar_mini = '
        <svg class="nav-avatar rounded border avatar-ring2" width="50" height="50" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
            <text x="50" y="55" text-anchor="middle" font-size="12" fill="#666">' . $hl('hdr_no_avatar', 'No Avatar') . '</text>
        </svg>';
        $avataar = $avatar_mini;
    } else {
        $avatar_mini = '<img class="nav-avatar" src="' . $e($useravatar['image'] ?? '') . '" alt="" ' . $e($useravatar['width_height'] ?? '') . ' loading="lazy">';
        $avataar     = '<img src="' . $e($useravatar['image'] ?? '') . '" style="width: 80px;" class="rounded border" alt="" loading="lazy">';
    }
}

// Kept for compatibility with other templates (not used directly in this file)
if ($seedtorrentscount > 0) {
    $seederOrLeecher = '<span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i> ' . $hl('hdr_seeder', 'Seeder') . '</span>';
} elseif ($leechingtorrentscount > 0) {
    $seederOrLeecher = '<span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i> ' . $hl('hdr_leecher', 'Leecher') . '</span>';
} else {
    $seederOrLeecher = '<span class="badge bg-secondary"><i class="fas fa-minus me-1"></i> ' . $hl('hdr_idle', 'Idle') . '</span>';
}

echo '<br><br>';

if ($isLoggedIn):
    $ratioRaw  = (string)($ratio ?? '0.00');
    // $ratio may already contain trusted markup (<font>/<span>) — print as is, escape otherwise
    $ratioHtml = (str_contains($ratioRaw, '<font') || str_contains($ratioRaw, '<span')) ? $ratioRaw : $e($ratioRaw);
?>
<!-- Header with card-style navbar -->
<header class="sticky-top header-fixed">
    <div class="container-fluid py-1 bg-light border-bottom">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Profile with avatar -->
            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= $avatar_mini ?>
                    <div class="text-dark fw-semibold">
                        <?= $gl('welcomeback') ?>
                        <span class="text-primary"><?= format_name($CURUSER['username'] ?? '', $CURUSER['usergroup'] ?? 0) ?></span>
                        <?= $warn ?? '' ?>
                        <?= $lwarn ?? '' ?>
                        <?= $medaldon ?? '' ?>
                    </div>
                    <i class="fa-solid fa-angle-down small ms-1"></i>
                </a>

                <div class="dropdown-menu dropdown-menu-end border rounded user-menu">
                    <div class="row p-2">
                        <div class="col align-self-center">
                            <?= $usercplink ?>
                            <?= $admincplink ?>
                            <button type="button" class="dropdown-item text-danger ps-0" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="fa-solid fa-right-from-bracket"></i> &nbsp;&nbsp;<?= $hl('hdr_logout', 'Log Out') ?>
                            </button>
                        </div>
                        <div class="col-auto align-self-center">
                            <?= $avataar ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="small text-dark d-flex flex-wrap justify-content-center gap-2 align-items-center">
                <span><i class="fa-solid fa-upload me-1 ic-green"></i><?= $hl('hdr_uploaded', 'Uploaded:') ?> <strong><?= mksize($uploaded ?? 0) ?></strong></span>
                <span><i class="fa-solid fa-download me-1 ic-red"></i><?= $hl('hdr_downloaded', 'Downloaded:') ?> <strong><?= mksize($downloaded ?? 0) ?></strong></span>
                <span><i class="fa-solid fa-chart-simple me-1 ic-blue"></i><?= $hl('hdr_ratio', 'Ratio:') ?> <strong><?= $ratioHtml ?></strong></span>
                <span><i class="fa-solid fa-coins me-1 ic-yellow"></i><?= $hl('hdr_bonus', 'Bonus:') ?> <strong><?= number_format((float)($CURUSER['seedbonus'] ?? 0), 1) ?></strong></span>
                <span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i><?= $seedtorrentscount ?></span>
                <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i><?= $leechingtorrentscount ?></span>

                <!-- Notification center -->
                <div class="text-dark"><?= notify_bell_html() ?></div>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= $url('index.php') ?>"><?= $gl('home', 'Home') ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="<?= $hl('hdr_toggle_nav', 'Toggle navigation') ?>">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php foreach ($navMenus as $menu) { echo $renderMenu($menu, $e($profilelink_link)); } ?>
                </ul>

                <!-- Search and Settings -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= $hl('hdr_search', 'Search') ?>">
                            <i class="fas fa-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end p-3 search-menu">
                            <form class="d-flex" role="search" method="post" action="<?= $url('browse.php?do=search&amp;search_type=t_both') ?>">
                                <input type="hidden" name="search_type" value="t_both">
                                <input type="hidden" name="do" value="search">
                                <div class="input-group">
                                    <input class="form-control" type="text" value="" name="keywords" placeholder="<?= $hl('nav_search_placeholder', 'Search torrents...') ?>" aria-label="<?= $hl('hdr_search', 'Search') ?>">
                                    <button class="btn btn-primary" type="submit" aria-label="<?= $hl('hdr_search', 'Search') ?>">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a class="btn btn-search pt-2 border-0 btn-sm text-muted ms-2" href="<?= $url('search.php') ?>" style="border-left: 0px!important" aria-label="<?= $hl('hdr_search', 'Search') ?>">
                            <i class="fa-solid fa-gear"></i>
                        </a>
                    </li>

                    <!-- Language Switcher -->
                    <li class="nav-item ms-2">
                        <div class="lang-switch">
                            <?php $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/'); ?>
                            <?php foreach ($languages as $key => $item): ?>
                                <a href="<?= $url('language.php?lang=' . $key . '&amp;redirect=' . $redirect) ?>"<?= $current_lang === $key ? ' class="active"' : '' ?>>
                                    <span class="flag"><?= $item['flag'] ?></span><?= $item['code'] ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </li>

                    <!-- Dark Mode Switch -->
                    <li class="nav-item">
                        <div class="form-check form-switch ms-2">
                            <input class="form-check-input" type="checkbox" id="darkModeSwitch" aria-label="<?= $hl('hdr_theme_switch', 'Switch between light and dark mode') ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $hl('hdr_theme_switch', 'Switch between light and dark mode') ?>">
                            <label class="form-check-label" for="darkModeSwitch"></label>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<br><br>

<!-- Modal Logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fa-solid fa-right-from-bracket me-2"></i> <?= $hl('nav_confirm_logout', 'Confirm Logout') ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $hl('hdr_close', 'Close') ?>"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-0 fs-6"><?= $hl('logout_confirm_text', 'Are you sure you want to log out?') ?></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> <?= $hl('hdr_cancel', 'Cancel') ?>
                </button>
                <a href="<?= $url('member.php?action=logout&amp;logoutkey=' . $e($logoutkey)) ?>" class="btn btn-danger px-4">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> <?= $hl('hdr_logout', 'Log Out') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<br><br><br>

<?php else: ?>
<!-- Guest navigation -->
<nav class="navbar navbar-expand-lg bg-white shadow-sm border-bottom py-3">
    <div class="container">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#guestNavbar" aria-controls="guestNavbar" aria-expanded="false" aria-label="<?= $hl('hdr_toggle_nav', 'Toggle navigation') ?>">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="guestNavbar">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-2">
                    <a class="btn btn-outline-primary rounded-pill px-4 fw-semibold shadow-sm hover-shadow" href="<?= $url('member.php?action=login') ?>">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> <?= $hl('nav_login', 'Login') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm hover-shadow" href="<?= $url('member.php?action=register') ?>">
                        <i class="fa-solid fa-user-plus me-1"></i> <?= $hl('nav_register', 'Register') ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<br><br>
<?php endif;

// ---------------------------------------------------------------------
//  Awaiting users activation (admins only)
// ---------------------------------------------------------------------
$awaitingusers = '';
if ($isAdmin) {
    $awaitingData = $cache->read('awaitingactivation');

    if (isset($awaitingData['time']) && $awaitingData['time'] + 86400 < TIMENOW) {
        $cache->update_awaitingactivation();
        $awaitingData = $cache->read('awaitingactivation');
    }

    $awaitingCount = max(0, (int)($awaitingData['users'] ?? 0));   // keep it an int: ts_nf() returns a formatted string

    if ($awaitingCount > 0) {
        $awaiting_message = $awaitingCount === 1
            ? ($lang->global['awaiting_message_single'] ?? '')
            : sprintf($lang->global['awaiting_message_plural'] ?? '', ts_nf($awaitingCount));

        if ($admincplink) {
            $awaiting_message .= sprintf($lang->global['awaiting_message_link'] ?? '', $BASEURL ?? '');
        }

        $awaitingusers = $cssOnce('errorss.css') . '
        <div class="container mt-3">
            <div class="card error-card222">
                <div class="card-header22">
                    <i class="bi bi-exclamation-triangle-fill error-icon2"></i>
                    <div>
                        <h2 class="mb-0">' . $hl('hdr_attention', 'Attention') . '</h2>
                        <p class="mb-0 opacity-75">' . $hl('hdr_awaiting_activation', 'Awaiting Activation') . '</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger" role="alert">' . $e($awaiting_message) . '</div>
                </div>
            </div>
        </div>';

        echo $awaitingusers;
    }
}





// ---------------------------------------------------------------------
//  Banned group warning
// ---------------------------------------------------------------------
if ((int)($ug['isbannedgroup'] ?? 0) === 1) {
    if (!empty($mybb->user['banned'])) {
        $banlift = !empty($mybb->user['banlifted'])
            ? my_datee('normal', (int)$mybb->user['banlifted'])
            : ($lang->global['banned_lifted_never'] ?? '');
    } else {
        $banlift = $lang->global['unknown2'] ?? '';
    }

    $reason = !empty($mybb->user['banreason'])
        ? htmlspecialchars_uni($mybb->user['banreason'])
        : ($lang->global['unknown2'] ?? '');
		
	
		

    $banLogoutKey = isset($CURUSER['loginkey']) && is_string($CURUSER['loginkey']) && is_object($session ?? null)
    ? $session->make_logoutkey($CURUSER['loginkey'])
    : '';
	
    $banCss       = $base . '/include/templates/default/style/';


?>
<link rel="stylesheet" href="<?= $banCss ?>errorss.css?ver=131">
    <div class="container">
        <div class="card error-card222 stderr-card">
            <div class="card-header22">
                <div class="stderr-icon-wrap">
                    <div class="stderr-icon-circle"><i class="bi bi-shield-lock-fill error-icon2"></i></div>
                    <span class="stderr-pulse"></span>
                </div>
                <div>
                    <h2 class="mb-0">403 &middot; <?= $gl('banned_warning') ?></h2>
                    <p class="mb-0 opacity-75"><i class="bi bi-info-circle me-1"></i><?= $hl('hdr_access_restricted', 'Access Restricted') ?></p>
                </div>
            </div>

            <div class="card-body">
                <div class="alert alert-danger d-flex align-items-start gap-3 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    <div>
                        <strong><?= $hl('hdr_account_suspended', 'ACCOUNT SUSPENDED') ?></strong>
                        <div class="small opacity-75 mt-1">
                            <?= $hl('hdr_ban_mistake', 'If you believe this is a mistake, please contact the administrator for assistance.') ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <div class="stderr-chip chip-wide">
                        <i class="bi bi-chat-quote-fill text-danger"></i>
                        <div>
                            <small class="text-muted d-block"><?= $gl('banned_warning2') ?></small>
                            <strong class="ban-value"><?= $reason ?></strong>
                        </div>
                    </div>
                    <div class="stderr-chip">
                        <i class="bi bi-calendar-event-fill text-danger"></i>
                        <div>
                            <small class="text-muted d-block"><?= $gl('banned_warning3') ?></small>
                            <strong class="ban-value"><?= $banlift ?></strong>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" onclick="history.back()" class="btn btn-outline-danger">
                        <i class="bi bi-arrow-left me-1"></i><?= $hl('hdr_go_back', 'Go Back') ?>
                    </button>
                    <a href="<?= $url('contactstaff.php') ?>" class="btn btn-danger">
                        <i class="bi bi-envelope me-1"></i><?= $hl('nav_contact_staff', 'Contact Staff') ?>
                    </a>
<?php if ($banLogoutKey !== ''): ?>
                    <a href="<?= $url('member.php?action=logout&amp;logoutkey=' . $e($banLogoutKey)) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-right me-1"></i><?= $hl('hdr_logout', 'Log Out') ?>
                    </a>
<?php endif; ?>
                </div>

                <small class="text-muted d-block pt-3 border-top">
                    <i class="bi bi-envelope me-1"></i><?= $hl('hdr_need_help', 'Need help? Contact') ?>
                    <a href="<?= $url('contactstaff.php') ?>"><?= $hl('hdr_support_team', 'Support Team') ?></a>
                </small>
            </div>
        </div>
    </div>
<?php


}



// ---------------------------------------------------------------------
//  Access control
// ---------------------------------------------------------------------
$output     = '';
$notallowed = false;
if (
    ($mybb->usergroup['canview'] ?? 0) != 1
    && !(basename(SCRIPTNAME) === 'contactstaff.php' && ($mybb->usergroup['isbannedgroup'] ?? 0) == 1)
) {
    if (defined('ALLOWABLE_PAGE')) {
        if (is_string(ALLOWABLE_PAGE)) {
            if (!in_array($mybb->get_input('action'), explode(',', ALLOWABLE_PAGE), true)) {
                $notallowed = true;
            }
        } elseif (ALLOWABLE_PAGE !== 1) {
            $notallowed = true;
        }
    } else {
        $notallowed = true;
    }

    if ($notallowed) {
        if (!$mybb->get_input('modal')) {
            error_no_permission(false, '', null);
        } else {
            $output = '<div class="modal p-0 m-0">
    <div class="card border" style="overflow-y: auto; max-height: 500px;">
        <div class="card-header text-19 fw-bold border-0 py-3">' . $e($SITENAME ?? '') . '</div>
        <div class="card-body">' . $hl('hdr_no_permission', 'You do not have permission to access this page') . '</div>
    </div>
</div>';
            echo $output;
            exit;
        }
    }
}

// ---------------------------------------------------------------------
//  Banned IP addresses
// ---------------------------------------------------------------------
if (basename(SCRIPTNAME) !== 'unbaniprequest.php' && is_banned_ip($session->ipaddress ?? '', true)) {
    if ($isLoggedIn) {
        $db->sql_query_prepared("DELETE FROM sessions WHERE ip = ? OR uid = ?", [$session->packedip ?? '', $uid]);
    } else {
        $db->sql_query_prepared("DELETE FROM sessions WHERE ip = ?", [$session->packedip ?? '']);
    }
    print_no_permission(false, true, $lang->header['hdr_banned_ip'] ?? "I'm sorry, but you are banned. You may not post, read threads, or access the Tracker. Please contact your forum administrator should you have any questions");
}

// ---------------------------------------------------------------------
//  Warning / info messages
// ---------------------------------------------------------------------
$warnmessages = [];
$infomessages = [];

// Tracker offline
if ($offlinemsg ?? false) {
    $warnmessages[] = $cssOnce('errorss.css') . '
    <div class="card error-card">
        <div class="card-header22">
            <i class="bi bi-exclamation-triangle-fill error-icon2"></i>
            <div>
                <h2 class="mb-0">' . $hl('hdr_offline_title', 'The tracker is currently offline!') . '</h2>
                <p class="mb-0 opacity-75"></p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger" role="alert">
                <strong>' . $hl('hdr_danger', 'Danger!') . '</strong> ' . sprintf($lang->header['trackeroffline'] ?? '', $BASEURL ?? '') . '
            </div>
        </div>
    </div>';
}

// Leech warning
if ($isLoggedIn && ($CURUSER['downloaded'] ?? 0) > 0 && ($CURUSER['leechwarn'] ?? '') === 'yes' && ($CURUSER['leechwarnuntil'] ?? 0) > TIMENOW) {
    require_once INC_PATH . '/functions_mkprettytime.php';

    $warnmessages[] = $cssOnce('errorss.css') . '
    <div class="card error-card222">
        <div class="card-header22">
            <i class="bi bi-exclamation-triangle-fill error-icon2"></i>
            <div>
                <h2 class="mb-0">' . ($lang->header['low_raio_warns'] ?? '') . '</h2>
                <p class="mb-0 opacity-75"></p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger" role="alert">
                <strong>' . $hl('hdr_danger', 'Danger!') . '</strong> ' . sprintf($lang->header['warned'] ?? '', $leechwarn_remove_ratio ?? '', mkprettytime(($CURUSER['leechwarnuntil'] ?? 0) - TIMENOW)) . '
            </div>
        </div>
    </div>';
}

// Announcement (floating button + modal)
if (isset($CURUSER) && ($CURUSER['announce_read'] ?? '') === 'no') {
    $res = $db->sql_query_prepared("SELECT a.id, a.subject, a.message, a.added, COALESCE(u.username, 'Admin') AS `by`
        FROM announcements a
        LEFT JOIN users u ON u.id = a.uid
        WHERE a.type = 'tracker'
          AND a.minclassread IN (0, ?)
        ORDER BY a.added DESC LIMIT 1", [(int)($CURUSER['usergroup'] ?? 0)]);

    if ($db->num_rows($res) > 0) {
        $arr = $db->fetch_array($res);
        $announcement_id = (int)$arr['id'];

        require_once INC_PATH . '/class_parser.php';
        $parser = new postParser();

        $parser_options = [
            'allow_html'      => 1,
            'allow_mycode'    => 1,
            'allow_smilies'   => 1,
            'allow_imgcode'   => 1,
            'allow_videocode' => 1,
            'filter_badwords' => 1,
        ];

        $infomessages[] = $cssOnce('announcement.css') . '
        <!-- Floating Announcement Button -->
        <div class="announcement-floating">
            <button type="button" class="announcement-btn" data-bs-toggle="modal" data-bs-target="#announcementModal">
                <div class="btn-content">
                    <div class="btn-icon"><i class="fas fa-bullhorn"></i></div>
                    <div class="btn-text">
                        <span class="badge">' . $hl('hdr_new', 'New') . '</span>
                        ' . $hl('hdr_announcement', 'Announcement') . '
                    </div>
                </div>
            </button>
        </div>

        <!-- Announcement Modal -->
        <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg">
                    <div class="modal-header bg-gradient-primary text-white rounded-top-4 border-0 py-4">
                        <div class="d-flex align-items-center w-100">
                            <div class="header-icon me-3"><i class="fas fa-bullhorn"></i></div>
                            <div class="flex-grow-1">
                                <h4 class="modal-title mb-1 fw-bold" id="announcementModalLabel">' . htmlspecialchars_uni($arr['subject'] ?? '') . '</h4>
                                <div class="announcement-meta">
                                    <span class="opacity-90">
                                        <i class="fas fa-user me-1"></i>' . htmlspecialchars_uni($arr['by'] ?? '') . '
                                        <i class="fas fa-clock ms-3 me-1"></i>' . my_datee($dateformat ?? '', $arr['added'] ?? 0) . ', ' . my_datee($timeformat ?? '', $arr['added'] ?? 0) . '
                                    </span>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="' . $hl('hdr_close', 'Close') . '"></button>
                        </div>
                    </div>

                    <div class="modal-body p-0">
                        <div class="announcement-content p-5">
                            <div class="content-wrapper">' . $parser->parse_message($arr['message'] ?? '', $parser_options) . '</div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 bg-light rounded-bottom-4 py-3">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div class="text-muted small">
                                <i class="fas fa-info-circle me-1"></i> ' . $hl('hdr_ann_note', 'This announcement will be marked as read') . '
                            </div>
                            <div class="btn-group">
                                <a href="' . $base . '/clear_ann.php?id=' . $announcement_id . '" class="btn btn-success px-4">
                                    <i class="fas fa-check me-2"></i> ' . $hl('hdr_mark_read', 'Mark as Read') . '
                                </a>
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i> ' . $hl('hdr_close', 'Close') . '
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }
}

// Private message notice
$current_page = my_strtolower(basename(defined('SCRIPTNAME') ? (string)SCRIPTNAME : ''));

if ((int)($CURUSER['pmnotice'] ?? 0) === 2
    && (int)($CURUSER['pms_unread'] ?? 0) > 0
    && ($current_page !== 'private.php' || (string)$mybb->get_input('action') !== 'read')
) {
    $query = $db->sql_query_prepared("
        SELECT pm.subject, pm.pmid, fu.username AS fromusername, fu.id AS fromuid
        FROM privatemessages pm
        LEFT JOIN users fu ON (fu.id = pm.fromid)
        WHERE pm.folder = '1' AND pm.uid = ? AND pm.status = '0'
        ORDER BY pm.dateline DESC
        LIMIT 1
    ", [$uid]);

    $pm = $db->fetch_array($query) ?: [];

    if ($pm) {
        $pmSubject = htmlspecialchars_uni($pm['subject'] ?? '');   // escaped once (was escaped twice before)
        $fromUid   = (int)($pm['fromuid'] ?? 0);                    // DB returns strings: === 0 never matched before

        if ($fromUid === 0) {
            $user_text = 'ArtCore Gangsta Engine';
        } else {
            $user_text = build_profile_link(htmlspecialchars_uni($pm['fromusername'] ?? ''), $fromUid);
        }

        if ((int)($CURUSER['pms_unread'] ?? 0) === 1) {            // was `=== 1` on a string: always false
            $privatemessage_text = sprintf(
                $lang->global['newpm_notice_one'] ?? '',
                $user_text, $BASEURL ?? '', (int)$pm['pmid'], $pmSubject
            );
        } else {
            $privatemessage_text = sprintf(
                $lang->global['newpm_notice_multiple'] ?? '',
                (int)$CURUSER['pms_unread'], $user_text, $BASEURL ?? '', (int)$pm['pmid'], $pmSubject
            );
        }

        $infomessages[] = $cssOnce('messagess.css') . '
        <script src="' . $base . '/scripts/dismisspm.js" defer></script>

        <div class="card error-card4 fade show" id="pm_notice">
            <div class="card-header4">
                <i class="bi bi-exclamation-triangle-fill error-icon4"></i>
                <div><h2 class="mb-0">' . $hl('hdr_new_pm', 'You have a new private message') . '</h2></div>
                <div class="float-end ms-auto">
                    <a href="' . $base . '/private.php?action=dismiss_notice&amp;my_post_key=' . $e($mybb->post_code ?? '') . '"
                       title="' . $hl('dismiss_notice') . '"
                       onclick="return dismissPMNotice(\'' . $base . '/\')">
                        <i class="btn-close"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert4 alert-info" role="alert">' . $privatemessage_text . '</div>
            </div>
        </div>';
    }
}

// Staff messages
if ((int)($nummessages ?? 0) > 0) {
    $infomessages[] = '
    <div class="container mt-3">
        <div class="alert alert-primary alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fa-solid fa-inbox me-1"></i><a href="' . $base . '/admin/index.php?act=staffbox">' . sprintf($lang->header['staffmess'] ?? '', (int)$nummessages) . '</a>
        </div>
    </div>';
}

// Reports
if ((int)($numreports ?? 0) > 0) {
    $infomessages[] = '<a href="' . $base . '/admin/index.php?act=reports">' . sprintf($lang->header['newreport'] ?? '', (int)$numreports) . '</a>';
}

// Exam / task in progress (as msgalert in NexusPHP)
if ($isLoggedIn && ($msgalert ?? true) && (!defined('THIS_SCRIPT') || THIS_SCRIPT !== 'task.php')) {
    require_once INC_PATH . '/functions_exam_header.php';
    $examNotice = exam_render_header_notice($uid);
    if ($examNotice !== '') {
        $warnmessages[] = $examNotice;
    }
}

// Output
if ($warnmessages) {
    echo implode('<br />', $warnmessages);
}
if ($infomessages) {
    echo implode('<br />', $infomessages);
}
unset($warnmessages, $infomessages);
?>

</div>
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
</body>
</html>