<?php
declare(strict_types=1);

 

if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}


?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?= htmlspecialchars($charset ?? 'UTF-8', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" />
    <meta name="generator" content="<?= htmlspecialchars($title ?? 'ArtCore Gangsta', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" />
    <meta name="revisit-after" content="3 days" />
    <meta name="robots" content="index, follow" />
    <meta name="description" content="<?= htmlspecialchars($metadesc ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" />
    <meta name="keywords" content="<?= htmlspecialchars($metakeywords ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" />
    <meta http-equiv="Content-Script-Type" content="text/javascript" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
  
    <script type="text/javascript" src="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/scripts/general.js?ver=1827"></script>
    <script type="text/javascript" src="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/scripts/bootstrap.bundle.min.js"></script>
    
    <?= isset($includeCSS) ? $includeCSS : '' ?>
    
    <link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/rss.php" />
    <link rel="alternate" type="text/xml" title="RSS .92" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/rss.php" />
    <link rel="shortcut icon" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/favicon.ico" type="image/x-icon" />
    
    <script type="text/javascript">
        //<![CDATA[
        var baseurl = "<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var dimagedir = "<?= htmlspecialchars(($BASEURL ?? '') . '/' . ($pic_base_url ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var charset = "<?= htmlspecialchars($charset ?? 'UTF-8', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var userid = "<?= (int)($CURUSER['id'] ?? 0) ?>";
        //]]>
    </script>

    <script type="text/javascript">
        <!--
        lang.expcol_collapse = "[-]";
        lang.expcol_expand = "[+]";
		
        lang.select2_match = "<?= htmlspecialchars($lang->header['select2_match'] ?? 'One result is available, press enter to select it.', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_matches = "<?= htmlspecialchars($lang->header['select2_matches'] ?? '{1} results are available, use up and down arrow keys to navigate.', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_nomatches = "<?= htmlspecialchars($lang->header['select2_nomatches'] ?? 'No matches found', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_inputtooshort_single = "<?= htmlspecialchars($lang->header['select2_inputtooshort'] ?? 'Please enter one or more character', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_inputtooshort_plural = "<?= htmlspecialchars($lang->header['select2_inputtooshort_plural'] ?? 'Please enter {1} or more characters', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_inputtoolong_single = "<?= htmlspecialchars($lang->header['select2_inputtoolong'] ?? 'Please delete one character', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_inputtoolong_plural = "<?= htmlspecialchars($lang->header['select2_inputtoolong_plural'] ?? 'Please delete {1} characters', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_selectiontoobig_single = "<?= htmlspecialchars($lang->header['select2_toobig'] ?? 'You can only select one item', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_selectiontoobig_plural = "<?= htmlspecialchars($lang->header['select2_toobig_plural'] ?? 'You can only select {1} items', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_loadmore = "<?= htmlspecialchars($lang->header['select2_loadmore'] ?? 'Loading more results...', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        lang.select2_searching = "<?= htmlspecialchars($lang->header['select2_searching'] ?? 'Searching...', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";

        var use_xmlhttprequest = "<?= (int)($use_xmlhttprequest ?? 0) ?>";
        var my_post_key = "<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookieDomain = "<?= htmlspecialchars($cookiedomain ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookiePath = "<?= htmlspecialchars($cookiepath ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookiePrefix = "<?= htmlspecialchars($cookieprefix ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookieSecureFlag = "<?= (int)($cookiesecureflag ?? 0) ?>";
        
        var MyBBEditor = null;
        var spinner_image = "<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/pic/spinner.gif";
        var spinner = "<img src='" + spinner_image + "' alt='' />";
        var loading_text = 'Loading. <br />Please Wait&hellip;';
        var saving_changes = 'Saving changes&hellip;';
        // -->
    </script>

    <script type="text/javascript">
        window.onscroll = () => {
            toggleTopButton();
        }
        
        function scrollToTop() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function toggleTopButton() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                document.getElementById('back-to-up')?.classList.remove('d-none');
            } else {
                document.getElementById('back-to-up')?.classList.add('d-none');
            }
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const htmlElement = document.documentElement;
            const switchElement = document.getElementById('darkModeSwitch');
            const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)").matches;
            const currentTheme = localStorage.getItem('bsTheme') || (prefersDarkScheme ? 'dark' : 'light');

            htmlElement.setAttribute('data-bs-theme', currentTheme);
            if (switchElement) {
                switchElement.checked = currentTheme === 'dark';
            }

            switchElement?.addEventListener('change', function () {
                const newTheme = this.checked ? 'dark' : 'light';
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('bsTheme', newTheme);
            });

            // Tooltip activation
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    <title><?= htmlspecialchars($title ?? 'ArtCore Gangsta', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/include/templates/default/style/bootstrap.min.css" type="text/css" media="screen" />
	<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/include/templates/default/style/bootstrap-icons.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/include/templates/default/style/all.min.css" type="text/css" media="screen" />

    <style>    
        header.sticky-top {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1020;
        }
        
        .navbar-fixed {
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }
        
        .header-fixed {
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0 15px;
        }

        .nav-link.dropdown-toggle::after {
            display: none;
        }
        
        .nav-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 6px;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
<div class="container md">
    <button class="btn btn-primary position-fixed bottom-0 end-0 translate-middle d-none" onclick="scrollToTop()" id="back-to-up">
        <i class="fa fa-arrow-up" aria-hidden="true"></i>
    </button>

<?php
// Prepare the main templates for use
$admincplink = $usercplink = '';

$pms_unread = isset($CURUSER['pms_unread']) ? ts_nf((int)$CURUSER['pms_unread']) : ts_nf(0);
$pms_total = isset($CURUSER['pms_total']) ? ts_nf((int)$CURUSER['pms_total']) : ts_nf(0);
$welcome_pms_usage = sprintf($lang->global['welcome_pms_usage'] ?? '', $pms_unread, $pms_total);

$pmslink = '
<div class="d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">
    <a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/private.php" title="' . htmlspecialchars($welcome_pms_usage, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">
        <i class="fa-solid fa-envelope"></i> &nbsp;' . htmlspecialchars($lang->global['welcome_pms'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '
    </a>
</div>
<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">
    <a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/private.php">
        <i class="fa-solid fa-envelope"></i>
    </a>
</div>';

$usercplink = '
<a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/usercp.php" class="dropdown-item pb-2 ms-0 ps-0" style="border-bottom: 1px solid #b3b3b3">
    <i class="fa-solid fa-user-gear"></i> &nbsp;User CP
</a>';

if (is_array($usergroups) && 
    isset($usergroups['canstaffpanel'], $usergroups['cansettingspanel'], $usergroups['issupermod']) &&
    (int)($usergroups['canstaffpanel'] ?? 0) === 1 &&
    (int)($usergroups['cansettingspanel'] ?? 0) === 1 &&
    (int)($usergroups['issupermod'] ?? 0) === 1) {
    
    $admincplink = '
    <a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/admin/index.php" class="dropdown-item ms-0 ps-0">
        <i class="fa-solid fa-gears"></i> &nbsp;Admin CP
    </a>';

}

// Set the logout key for this user
$logoutkey = '';
if (isset($CURUSER['loginkey']) && is_string($CURUSER['loginkey'])) {
    $logoutkey = md5($CURUSER['loginkey']);
}

$profilelink_link = '';
if (!empty($CURUSER['id'])) {
    $profilelink_link = get_profile_link((int)$CURUSER['id']);
}

echo '<br><br>';

// Get user seeding/leeching information
$uid = (int)($CURUSER['id'] ?? 0);
$q = $db->sql_query_prepared("SELECT SUM(seeder='yes') AS seeding, SUM(seeder='no') AS leeching FROM peers WHERE userid = ?", [$uid]);
$pr = $db->fetch_array($q);
$seedtorrentscount     = (int)($pr['seeding']  ?? 0);
$leechingtorrentscount = (int)($pr['leeching'] ?? 0);

if ($seedtorrentscount > 0) {
    $seederOrLeecher = '<span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i> Seeder</span>';
} elseif ($leechingtorrentscount > 0) {
    $seederOrLeecher = '<span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i> Leecher</span>';
} else {
    $seederOrLeecher = '<span class="badge bg-secondary"><i class="fas fa-minus me-1"></i> Idle</span>';
}

// Avatar handling
$useravatar = format_avatar($CURUSER['avatar'] ?? '', $CURUSER['avatardimensions'] ?? '');
$avatar_mini = $avataar = '';

if (str_starts_with($useravatar['image'] ?? '', '<')) {
    $avatar_mini = '
    <svg class="nav-avatar rounded border avatar-ring2" width="50" height="50" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
        <text x="50" y="55" text-anchor="middle" font-size="12" fill="#666">No Avatar</text>
    </svg>';
    $avataar = $avatar_mini;
} else {
    $avatar_mini = '<img class="nav-avatar" src="' . htmlspecialchars($useravatar['image'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" alt="" ' . htmlspecialchars($useravatar['width_height'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' />';
    $avataar = '<img src="' . htmlspecialchars($useravatar['image'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" style="width: 80px;" class="rounded border" />';
}

if (!empty($CURUSER['id'])): ?>
<!-- Header with card-style navbar -->
<header class="sticky-top header-fixed">
    <div class="container-fluid py-1 bg-light border-bottom">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Profile with avatar -->
            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                    <?= $avatar_mini ?>
                    <div class="text-dark fw-semibold">
                        <?= htmlspecialchars($lang->global['welcomeback'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> 
                        <span class="text-primary"><?= format_name($CURUSER['username'] ?? '', $CURUSER['usergroup'] ?? 0) ?></span>
                        <?= $warn ?? '' ?> 
                        <?= $lwarn ?? '' ?> 
                        <?= $medaldon ?? '' ?> 
                    </div>
                    <i class="fa-solid fa-angle-down small ms-1"></i>
                </a>

                <div class="dropdown-menu dropdown-menu-end border rounded" style="width: 250px">
                    <div class="row p-2">
                        <div class="col align-self-center">
                            <?= $usercplink ?>
                            <?= $admincplink ?>
                            <button type="button" class="dropdown-item text-danger ps-0" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="fa-solid fa-right-from-bracket"></i> &nbsp;&nbsp;Log Out
                            </button>
                        </div>
                        <div class="col-auto align-self-center">
                            <?= $avataar ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <small class="text-dark d-flex flex-wrap justify-content-center gap-2 align-items-center">
                <span><i class="fa-solid fa-upload me-1"></i>Uploaded: <strong><?= mksize($uploaded ?? 0) ?></strong></span>
                <span><i class="fa-solid fa-download me-1"></i>Downloaded: <strong><?= mksize($downloaded ?? 0) ?></strong></span>
                <span><i class="fa-solid fa-chart-simple me-1"></i>Ratio: <strong>
    <?php 
    $ratio_display = $ratio ?? '0.00';
    // Если ratio содержит HTML тег, выводим как есть (без экранирования)
    if (str_contains($ratio_display, '<font') || str_contains($ratio_display, '<span')) {
        echo $ratio_display;
    } else {
        echo htmlspecialchars($ratio_display, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    ?>
</strong></span>
                <span><i class="fa-solid fa-coins me-1"></i>Bonus: <strong><?= number_format((float)($CURUSER['seedbonus'] ?? 0), 1) ?></strong></span>
                <span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i><?= (int)$seedtorrentscount ?></span>
                <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i><?= (int)$leechingtorrentscount ?></span>
            </small>
        </div>
    </div>
	
	


    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/index.php">
                <?= htmlspecialchars($lang->global['home'] ?? 'Home', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
           
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- Browse Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-list me-1"></i> <?= htmlspecialchars($lang->header['nav_browse'] ?? 'Browse', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php"><i class="fas fa-table-list me-2"></i> <?= htmlspecialchars($lang->header['nav_browse_torrents'] ?? 'Browse Torrents', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/upload.php"><i class="fas fa-upload me-2"></i> Upload</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=mybookmarks"><i class="fas fa-bookmark me-2"></i> <?= htmlspecialchars($lang->header['nav_my_bookmarks'] ?? 'My Bookmarks', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=myreseeds"><i class="fas fa-seedling me-2"></i> <?= htmlspecialchars($lang->header['nav_my_reseeds'] ?? 'My Reseeds', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=weaktorrents"><i class="fas fa-heart-crack me-2"></i> <?= htmlspecialchars($lang->header['nav_weak_torrents'] ?? 'Weak Torrents', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>	
						</ul>
                    </li>
                    
                    <!-- Forums Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-comments me-1"></i> <?= htmlspecialchars($lang->header['nav_forums'] ?? 'Forums', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/index2.php"><i class="fas fa-forumbee me-2"></i> <?= htmlspecialchars($lang->header['nav_forums_home'] ?? 'Forums', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/search.php?action=getnew"><i class="fas fa-star me-2"></i> New Posts</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/search.php?action=getdaily"><i class="fas fa-calendar-day me-2"></i> Today's Posts</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/search.php"><i class="fas fa-search me-2"></i> <?= htmlspecialchars($lang->header['nav_search'] ?? 'Search', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                        </ul>
                    </li>
                    
                    <!-- User CP Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> User CP
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
							<li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/usercp.php"><i class="fas fa-cog me-2"></i> <?= htmlspecialchars($lang->header['nav_usercp_home'] ?? 'User CP Home', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/private.php"><i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($lang->header['nav_private_messages'] ?? 'Private Messages', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=mytorrents"><i class="fas fa-download me-2"></i> <?= htmlspecialchars($lang->header['nav_your_torrents'] ?? 'Your Torrents', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>            
						</ul>
                    </li>
                    
                    <!-- Top 10 Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-line me-1"></i> Top 10
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=1"><i class="fas fa-users me-2"></i> <?= htmlspecialchars($lang->header['nav_top_users'] ?? 'Users', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=2"><i class="fas fa-download me-2"></i> <?= htmlspecialchars($lang->header['nav_top_torrents'] ?? 'Torrents', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=3"><i class="fas fa-globe me-2"></i> <?= htmlspecialchars($lang->header['nav_top_countries'] ?? 'Countries', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=4"><i class="fas fa-network-wired me-2"></i> <?= htmlspecialchars($lang->header['nav_top_peers'] ?? 'Peers', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
							<li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=5"><i class="fas fa-comments me-2"></i> <?= htmlspecialchars($lang->header['nav_forums'] ?? 'Forums', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></a></li>
                        </ul>
                    </li>
                    
                    <!-- Extra Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-h me-1"></i> <?= htmlspecialchars($lang->global['extra'] ?? 'Extra', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($profilelink_link, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                    <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($lang->header['nav_your_profile'] ?? 'Your Profile', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/memberlist.php">
                                    <i class="fas fa-users me-2"></i> <?= htmlspecialchars($lang->header['nav_members_list'] ?? 'Members List', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/getrss.php">
                                    <i class="fas fa-rss me-2"></i> <?= htmlspecialchars($lang->header['nav_rss_feeds'] ?? 'RSS Feeds', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/invite.php">
                                    <i class="fas fa-user-plus me-2"></i> <?= htmlspecialchars($lang->header['nav_invite_friend'] ?? 'Invite Friend', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/mybonus.php">
                                    <i class="fas fa-coins me-2"></i> <?= htmlspecialchars($lang->header['nav_bonus_points'] ?? 'Bonus Points', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
							
							<li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/requests.php">
                                    <i class="fas fa-list-alt me-2"></i> <?= htmlspecialchars($lang->header['nav_requests'] ?? 'Requests', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/offers.php">
                                    <i class="fas fa-gift me-2"></i> <?= htmlspecialchars($lang->header['nav_offers'] ?? 'Offers', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
							
							
                        </ul>
                    </li>
					
					
					
					
					
					<!--Help Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-h me-1"></i> <?= htmlspecialchars($lang->header['nav_help'] ?? 'Help', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu">
                            
							
							<li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/videoformats.php">
                <i class="fas fa-video me-2"></i> <?= htmlspecialchars($lang->header['nav_video_formats'] ?? 'Video Formats', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </a>
        </li>
        
        <!-- Links & Resources -->
        <li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/links.php">
                <i class="fas fa-link me-2"></i> <?= htmlspecialchars($lang->header['nav_torrent_links'] ?? 'Torrent Links', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </a>
        </li>
        
        <!-- Separator -->
        <li><hr class="dropdown-divider"></li>
        
        <!-- Other Useful Links (optional) -->
        <li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/faq.php">
                <i class="fas fa-question-circle me-2"></i> <?= htmlspecialchars($lang->header['nav_faq'] ?? 'FAQ', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/rules.php">
                <i class="fas fa-gavel me-2"></i> <?= htmlspecialchars($lang->header['nav_rules'] ?? 'Rules', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </a>
        </li>
							
							
							
							
                        </ul>
                    </li>
					
					
					
					
					
                    
                    <!-- Staff Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-shield-alt me-1"></i> <?= htmlspecialchars($lang->global['staff'] ?? 'Staff', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/showteam.php">
                                    <i class="fas fa-users-cog me-2"></i> <?= htmlspecialchars($lang->header['nav_staff_team'] ?? 'Staff Team', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/contactstaff.php">
                                    <i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($lang->header['nav_contact_staff'] ?? 'Contact Staff', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>

                <!-- Search and Settings -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 300px;">
                            <form class="d-flex" role="search" method="post" action="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?do=search&amp;search_type=t_both">
                                <input type="hidden" name="search_type" value="t_both" />
                                <input type="hidden" name="do" value="search" />
                                <div class="input-group">
                                    <input class="form-control" type="text" value="" name="keywords" placeholder="<?= htmlspecialchars($lang->header['nav_search_placeholder'] ?? 'Search torrents...', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" aria-label="Search">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </li>
                    
                    <li class="nav-item">
                        <a class="btn btn-search pt-2 border-0 btn-sm text-muted ms-2" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/search.php" style="border-left: 0px!important">
                            <i class="fa-solid fa-gear"></i>
                        </a>
                    </li>
					
					
					
					<!-- Language Switcher -->
<?php
$current_lang = $_COOKIE['ts_language'] ?? $defaultlanguage ?? 'english';
$languages = [
    'english' => ['flag' => '🇬🇧', 'code' => 'EN'],
    'russian' => ['flag' => '🇷🇺', 'code' => 'RU'],
];
?>
<li class="nav-item ms-2">
    <div style="display:inline-flex; border:0.5px solid rgba(128,128,128,0.3); border-radius:999px; overflow:hidden; background:rgba(128,128,128,0.07);">
        <?php foreach ($languages as $key => $langItem): ?>
        <a href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/language.php?lang=<?= $key ?>&amp;redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/') ?>"
           style="display:flex; align-items:center; gap:5px; padding:5px 11px; border-radius:999px; font-size:12px; font-weight:<?= $current_lang === $key ? '500' : '400' ?>; text-decoration:none;
                  background:<?= $current_lang === $key ? '#378ADD' : 'transparent' ?>;
                  color:<?= $current_lang === $key ? '#fff' : 'inherit' ?>; transition:all 0.2s; white-space:nowrap;">
            <span style="font-size:14px; line-height:1;"><?= $langItem['flag'] ?></span><?= $langItem['code'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</li>
					
					
					
					
					
					
					
					
					
                    
                    <!-- Dark Mode Switch -->
                    <li class="nav-item">
                        <div class="form-check form-switch ms-2">
                            <input class="form-check-input" type="checkbox" id="darkModeSwitch" checked aria-label="Switch between light and dark mode" data-bs-toggle="tooltip" data-bs-placement="top" title="Switch between light and dark mode">
                            <label class="form-check-label" for="darkModeSwitch"></label>
                        </div>
                    </li>
                </ul>
            </div>
        </div>    
    </nav>
</header>


	 </br>
</br>

<!-- Modal Logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fa-solid fa-right-from-bracket me-2"></i> <?= htmlspecialchars($lang->header['nav_confirm_logout'] ?? 'Confirm Logout', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-0 fs-6"><?= htmlspecialchars($lang->header['logout_confirm_text'] ?? 'Are you sure you want to log out?', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> Cancel
                </button>
                <a href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/member.php?action=logout&amp;logoutkey=<?= htmlspecialchars($logoutkey, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" class="btn btn-danger px-4">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Log Out
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
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#guestNavbar" aria-controls="guestNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="guestNavbar">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3">
                    <a class="nav-link text-secondary fw-semibold" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/faq.php">
                        <i class="fa-solid fa-circle-question me-1"></i> FAQ
                    </a>
                </li>
                <li class="nav-item me-3">
                    <a class="nav-link text-secondary fw-semibold" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/contact.php">
                        <i class="fa-solid fa-envelope me-1"></i> Contact
                    </a>
                </li>
                <li class="nav-item me-2">
                    <a class="btn btn-outline-primary rounded-pill px-4 fw-semibold shadow-sm hover-shadow" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/member.php?action=login">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> <?= htmlspecialchars($lang->header['nav_login'] ?? 'Login', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm hover-shadow" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/member.php?action=register">
                        <i class="fa-solid fa-user-plus me-1"></i> <?= htmlspecialchars($lang->header['nav_register'] ?? 'Register', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<br><br>
<?php endif;

// Awaiting users activation
$awaitingusers = '';
if (is_array($usergroups) &&
    isset($usergroups['canstaffpanel'], $usergroups['cansettingspanel'], $usergroups['issupermod']) &&
    (int)($usergroups['canstaffpanel'] ?? 0) === 1 &&
    (int)($usergroups['cansettingspanel'] ?? 0) === 1 &&
    (int)($usergroups['issupermod'] ?? 0) === 1) {
    
    $awaitingusers = $cache->read('awaitingactivation');

    if (isset($awaitingusers['time']) && $awaitingusers['time'] + 86400 < TIMENOW) {
        $cache->update_awaitingactivation();
        $awaitingusers = $cache->read('awaitingactivation');
    }

    $awaitingusers_count = (int)($awaitingusers['users'] ?? 0);
    $awaitingusers_count = $awaitingusers_count < 1 ? 0 : ts_nf($awaitingusers_count);

    if ($awaitingusers_count > 0) {
        if ($awaitingusers_count === 1) {
            $awaiting_message = $lang->global['awaiting_message_single'] ?? '';
        } else {
            $awaiting_message = sprintf($lang->global['awaiting_message_plural'] ?? '', $awaitingusers_count);
        }

        if ($admincplink) {
            $awaiting_message .= sprintf($lang->global['awaiting_message_link'] ?? '', $BASEURL ?? '');
        }

        $awaitingusers = '
        <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/errorss.css" rel="stylesheet">
        <div class="container mt-3">
            <div class="card error-card222">
                <div class="card-header22">
                    <i class="bi bi-exclamation-triangle-fill error-icon2"></i>
                    <div>
                        <h2 class="mb-0">Attention</h2>
                        <p class="mb-0 opacity-75">Awaiting Activation</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger" role="alert">
                        ' . htmlspecialchars($awaiting_message, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '
                    </div>
                </div>
            </div>
        </div>';

        echo $awaitingusers;
    }
}

// Banned warning
$bannedwarning = '';
if (is_array($usergroups) && isset($usergroups['isbannedgroup']) && (int)($usergroups['isbannedgroup'] ?? 0) === 1) {
    if (!empty($mybb->user['banned'])) {
        if (!empty($mybb->user['banlifted'])) {
            $banlift = my_datee('normal', (int)$mybb->user['banlifted']);
        } else {
            $banlift = $lang->global['banned_lifted_never'] ?? '';
        }
    } else {
        $banlift = $lang->global['unknown2'] ?? '';
    }

    $reason = !empty($mybb->user['banreason']) ? htmlspecialchars_uni($mybb->user['banreason']) : ($lang->global['unknown2'] ?? '');

    $bannedwarning = '<html>
<head>
    <title>'.$title.'</title>
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/banned.css">
    
</head>
<body>

    <div class="ban-container">
        <div class="ban-card">
            <!-- Анимированный фон -->
            <div class="ban-bg-pattern"></div>
            
            <!-- Неоновая линия -->
            <div class="ban-glow-line"></div>
            
            <!-- Хедер -->
            <div class="ban-header">
                <div class="ban-icon-wrapper">
                    <div class="ban-icon-circle">
                        <i class="bi bi-shield-lock-fill ban-icon"></i>
                    </div>
                    <div class="ban-header-text">
                        <h1>'.$lang->global['banned_warning'].'</h1>
                        <p>'.$lang->global['banned_warning'].' - Access Restricted</p>
                    </div>
                </div>
            </div>
            
            <!-- Тело карточки -->
            <div class="ban-body">
                <!-- Бейдж -->
                <div class="ban-badge">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ACCOUNT SUSPENDED
                </div>
                
                <!-- Сообщение о бане -->
                <div class="ban-message">
                    <div class="ban-message-content">
                        <div class="ban-message-icon">
                            <i class="bi bi-shield-exclamation"></i>
                        </div>
                        <div class="ban-message-text">
                            <strong>'.$lang->global['banned_warning'].'</strong>
                            <p class="mb-0 text-muted">'.$lang->global['banned_warning2'].':</p>
                        </div>
                    </div>
                </div>
                
                <!-- Детали бана -->
                <div class="ban-details">
                    <div class="ban-detail-item">
                        <i class="bi bi-chat-quote-fill"></i>
                        <h6>'.$lang->global['banned_warning2'].'</h6>
                        <div class="ban-value">'.$reason.'</div>
                    </div>
                    
                    <div class="ban-detail-item">
                        <i class="bi bi-calendar-event-fill"></i>
                        <h6>'.$lang->global['banned_warning3'].'</h6>
                        <div class="ban-value">'.$banlift.'</div>
                    </div>
                </div>
                
                <!-- Дополнительная информация -->
                <div class="alert alert-warning d-flex align-items-center" role="alert" style="border-radius: 15px; background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.2);">
                    <i class="bi bi-info-circle-fill me-3" style="color: #ffc107; font-size: 1.5rem;"></i>
                    <div>
                        <strong>Important:</strong> If you believe this is a mistake, please contact the administrator for assistance.
                    </div>
                </div>
                
                <!-- Кнопки действий -->
                <div class="ban-actions">
                    <button onclick="history.back()" class="ban-btn ban-btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Go Back
                    </button>
                    <a href="'.$BASEURL.'/" class="ban-btn ban-btn-primary">
                        <i class="bi bi-house-door"></i>
                        Home Page
                    </a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="ban-footer">
                <small>
                    <i class="bi bi-envelope me-1"></i>
                    Need help? Contact 
                    <a href="'.$BASEURL.'/contact.php">Support Team</a>
                </small>
            </div>
        </div>
    </div>

</body>
</html>';
	
	
}

echo $bannedwarning;

// Access control
$output = '';
$notallowed = false;
if (($mybb->usergroup['canview'] ?? 0) != 1) {
    if (defined('ALLOWABLE_PAGE')) {
        if (is_string(ALLOWABLE_PAGE)) {
            $allowable_actions = explode(',', ALLOWABLE_PAGE);
            if (!in_array($mybb->get_input('action'), $allowable_actions)) {
                $notallowed = true;
            }
        } else if (ALLOWABLE_PAGE !== 1) {
            $notallowed = true;
        }
    } else {
        $notallowed = true;
    }

    if ($notallowed) {
        if (!$mybb->get_input('modal')) {
            error_no_permission();
        } else {
            
			$output = '<div class="modal p-0 m-0">
		<div class="card border" style="overflow-y: auto; max-height: 500px;">
			<div class="card-header text-19 fw-bold border-0 py-3">'.$SITENAME.'</div>
			<div class="card-body">You do not have permission to access this page</div>
	</div>
</div>';
			
			
            echo $output;
            exit;
        }
    }
}

// Check banned IP addresses
if (is_banned_ip($session->ipaddress ?? '', true)) {
    if ($CURUSER['id'] ?? 0) {
        $db->delete_query('sessions', "ip = " . $db->escape_binary($session->packedip ?? '') . " OR uid='" . (int)($CURUSER['id'] ?? 0) . "'");
    } else {
        $db->delete_query('sessions', "ip = " . $db->escape_binary($session->packedip ?? ''));
    }
    error('I\'m sorry, but you are banned. You may not post, read threads, or access the Tracker. Please contact your forum administrator should you have any questions');
}

// Warning messages
$warnmessages = [];
$infomessages = [];

// Tracker offline message
if ($offlinemsg ?? false) {
    $warnmessages[] = '
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/errorss.css" rel="stylesheet">
    <div class="card error-card">
        <div class="card-header22">
            <i class="bi bi-exclamation-triangle-fill error-icon2"></i>
            <div>
                <h2 class="mb-0">The tracker is currently offline!</h2>
                <p class="mb-0 opacity-75"></p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger" role="alert">
                <strong>Danger!</strong> ' . sprintf($lang->header['trackeroffline'] ?? '', $BASEURL ?? '') . '
            </div>
        </div>
    </div>';
}

// Leech warning
if ((isset($CURUSER) && ($CURUSER['id'] ?? 0) > 0 && ($CURUSER['downloaded'] ?? 0) > 0 && ($CURUSER['leechwarn'] ?? '') === 'yes' && ($CURUSER['leechwarnuntil'] ?? 0) > TIMENOW)) {
    //include_once INC_PATH . '/readconfig_cleanup.php';
    require_once INC_PATH . '/functions_mkprettytime.php';
    
    $warnmessages[] = '
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/errorss.css" rel="stylesheet">
    <div class="card error-card222">
        <div class="card-header22">
            <i class="bi bi-exclamation-triangle-fill error-icon2"></i>
            <div>
                <h2 class="mb-0">You are now warned for having a low ratio!</h2>
                <p class="mb-0 opacity-75"></p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger" role="alert">
                <strong>Danger!</strong> ' . sprintf($lang->header['warned'] ?? '', $leechwarn_remove_ratio ?? '', mkprettytime(($CURUSER['leechwarnuntil'] ?? 0) - TIMENOW)) . '
            </div>
        </div>
    </div>';
}

// Announcements
if (isset($CURUSER) && ($CURUSER['announce_read'] ?? '') === 'no') 
{
   $res = $db->sql_query_prepared("SELECT a.id, a.subject, a.message, a.added, COALESCE(u.username, 'Admin') AS `by`
    FROM announcements a
    LEFT JOIN users u ON u.id = a.uid
    WHERE a.type = 'tracker'
      AND a.minclassread IN (0, ?)
    ORDER BY a.added DESC LIMIT 1", [(int)($CURUSER['usergroup'] ?? 0)]);
	
    if ($db->num_rows($res) > 0) 
	{
        $arr = $db->fetch_array($res);
		$announcement_id = (int)$arr['id']; // Вот здесь получаем ID
        
        require_once INC_PATH . '/class_parser.php';
        $parser = new postParser;
        
        $parser_options = [
            "allow_html" => 1,
            "allow_mycode" => 1,
            "allow_smilies" => 1,
            "allow_imgcode" => 1,
            "allow_videocode" => 1,
            "filter_badwords" => 1
        ];
        
        $zz = '
        <!-- Floating Announcement Button -->
        <div class="announcement-floating">
            <button type="button" class="announcement-btn" data-bs-toggle="modal" data-bs-target="#announcementModal">
                <div class="btn-content">
                    <div class="btn-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="btn-text">
                        <span class="badge">New</span>
                        Announcement
                    </div>
                </div>
            </button>
        </div>

        <!-- Announcement Modal -->
        <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg">
                    <!-- Header -->
                    <div class="modal-header bg-gradient-primary text-white rounded-top-4 border-0 py-4">
                        <div class="d-flex align-items-center w-100">
                            <div class="header-icon me-3">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h4 class="modal-title mb-1 fw-bold" id="announcementModalLabel">
                                    ' . htmlspecialchars_uni($arr['subject'] ?? '') . '
                                </h4>
                                <div class="announcement-meta">
                                    <span class="opacity-90">
                                        <i class="fas fa-user me-1"></i>' . htmlspecialchars_uni($arr['by'] ?? '') . '
                                        <i class="fas fa-clock ms-3 me-1"></i>' . my_datee($dateformat ?? '', $arr['added'] ?? 0) . ', ' . my_datee($timeformat ?? '', $arr['added'] ?? 0) . '
                                    </span>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="modal-body p-0">
                        <div class="announcement-content p-5">
                            <div class="content-wrapper">
                                ' . $parser->parse_message($arr['message'] ?? '', $parser_options) . '
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="modal-footer border-0 bg-light rounded-bottom-4 py-3">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div class="text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                This announcement will be marked as read
                            </div>
                            <div class="btn-group">
                                <a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/clear_ann.php?id=' . $announcement_id . '" class="btn btn-success px-4">
                                   <i class="fas fa-check me-2"></i>
                                   Mark as Read
                                </a>
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		
        <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/announcement.css">';
		
        $infomessages[] = $zz;
    }      
}

// Private message notice
$current_page = my_strtolower(basename(SCRIPTNAME ?? ''));
$pm_notice = '';

if (isset($CURUSER['pmnotice']) && (int)($CURUSER['pmnotice'] ?? 0) === 2 && ($CURUSER['pms_unread'] ?? 0) > 0 && ($current_page !== "private.php" || ($mybb->get_input('action') ?? '') !== "read")) {
    $query = $db->sql_query_prepared("
        SELECT pm.subject, pm.pmid, fu.username AS fromusername, fu.id AS fromuid
        FROM privatemessages pm
        LEFT JOIN users fu on (fu.id=pm.fromid)
        WHERE pm.folder = '1' AND pm.uid = ? AND pm.status = '0'
        ORDER BY pm.dateline DESC
        LIMIT 1
    ", [(int)($CURUSER['id'] ?? 0)]);

    $pm = $db->fetch_array($query);
    $pm['subject'] = htmlspecialchars_uni($pm['subject'] ?? '');

    if (($pm['fromuid'] ?? 0) === 0) {
        $pm['fromusername'] = 'ArtCore Gangsta Engine';
        $user_text = $pm['fromusername'];
    } else {
        $pm['fromusername'] = htmlspecialchars_uni($pm['fromusername'] ?? '');
        $user_text = build_profile_link($pm['fromusername'], (int)($pm['fromuid'] ?? 0));
    }

    
	



if (($CURUSER['pms_unread'] ?? 0) === 1)
{
    $privatemessage_text = sprintf(
        $lang->global['newpm_notice_one'] ?? '',
        $user_text ?? '',
        $BASEURL ?? '',
        (int)($pm['pmid'] ?? 0),
        htmlspecialchars($pm['subject'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );
}
else
{
    $privatemessage_text = sprintf(
        $lang->global['newpm_notice_multiple'] ?? '',
        (int)($CURUSER['pms_unread'] ?? 0),
        $user_text ?? '',
        $BASEURL ?? '',
        (int)($pm['pmid'] ?? 0),
        htmlspecialchars($pm['subject'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );
}



	

    $pm_notice = '
    <script type="text/javascript" src="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/scripts/dismisspm.js"></script>
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/messagess.css" rel="stylesheet">

    <div class="card error-card4 fade show" id="pm_notice">
        <div class="card-header4">
            <i class="bi bi-exclamation-triangle-fill error-icon4"></i>
            <div><h2 class="mb-0">You have a private Message</h2></div>
            <div class="float-end ms-auto">
                <a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/private.php?action=dismiss_notice&amp;my_post_key=' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"
                    title="' . htmlspecialchars($lang->header['dismiss_notice'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"
                    onclick="return dismissPMNotice(\'' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/\')">
                    <i class="btn-close"></i>
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="alert4 alert-info" role="alert">
                ' . $privatemessage_text . '
            </div>
        </div>
    </div>';

    $infomessages[] = $pm_notice;
}

// Staff messages
if (isset($nummessages) && (int)$nummessages > 0) {
    $infomessages[] = '
    <div class="container mt-3">
        <div class="alert alert-primary alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <strong>Primary!</strong><a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/admin/index.php?act=staffbox">' . sprintf($lang->header['staffmess'] ?? '', (int)$nummessages) . '</a>
        </div>
    </div>';
}

// Reports
if (isset($numreports) && (int)$numreports > 0) {
    $infomessages[] = '<a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/admin/index.php?act=reports">' . sprintf($lang->header['newreport'] ?? '', (int)$numreports) . '</a>';
}

// Display warning and info messages
if (isset($warnmessages) && !empty($warnmessages)) {
    echo implode('<br />', $warnmessages);
    unset($warnmessages);
}

if (isset($infomessages) && !empty($infomessages)) {
    echo implode('<br />', $infomessages);
    unset($infomessages);
}
?>

</div>
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 2000;"></div>
</body>
</html>