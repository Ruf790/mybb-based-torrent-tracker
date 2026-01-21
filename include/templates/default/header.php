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
    <meta name="generator" content="<?= htmlspecialchars($title ?? 'Ruff Tracker', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" />
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
        lang.select2_match = "One result is available, press enter to select it.";
        lang.select2_matches = "{1} results are available, use up and down arrow keys to navigate.";
        lang.select2_nomatches = "No matches found";
        lang.select2_inputtooshort_single = "Please enter one or more character";
        lang.select2_inputtooshort_plural = "Please enter {1} or more characters";
        lang.select2_inputtoolong_single = "Please delete one character";
        lang.select2_inputtoolong_plural = "Please delete {1} characters";
        lang.select2_selectiontoobig_single = "You can only select one item";
        lang.select2_selectiontoobig_plural = "You can only select {1} items";
        lang.select2_loadmore = "Loading more results&hellip;";
        lang.select2_searching = "Searching&hellip;";

        var use_xmlhttprequest = "<?= (int)($use_xmlhttprequest ?? 0) ?>";
        var my_post_key = "<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookieDomain = "<?= htmlspecialchars($cookiedomain ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookiePath = "<?= htmlspecialchars($cookiepath ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookiePrefix = "<?= htmlspecialchars($cookieprefix ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
        var cookieSecureFlag = "<?= (int)($cookiesecureflag ?? 0) ?>";
        
        var MyBBEditor = null;
        var spinner_image = "https://ruff-tracker.eu/pic/spinner.gif";
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

    <title><?= htmlspecialchars($title ?? 'Ruff Tracker', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/include/templates/default/style/bootstrap.min.css" type="text/css" media="screen" />
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
$admincplink = $modcplink = $usercplink = '';

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

    $modcplink = '
    <a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/modcp.php" class="dropdown-item ms-0 ps-0">
        <i class="fa-solid fa-screwdriver-wrench"></i> &nbsp;&nbsp;Mod CP
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
$seedtorrentscount = tsrowcount('id', 'peers', "seeder='yes' AND userid={$uid}");
$leechingtorrentscount = tsrowcount('id', 'peers', "seeder='no' AND userid={$uid}");

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

if (isset($CURUSER)): ?>
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
                            <?= $modcplink ?>
                            <?= $admincplink ?>
                            <button type="button" class="dropdown-item ms-0 ps-0" data-bs-toggle="modal" data-bs-target="#logoutModal">
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
                            <i class="fas fa-list me-1"></i> Browse
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php"><i class="fas fa-table-list me-2"></i> Browse Torrents</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/upload.php"><i class="fas fa-upload me-2"></i> Upload</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=mybookmarks"><i class="fas fa-bookmark me-2"></i> My Bookmarks</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=myreseeds"><i class="fas fa-seedling me-2"></i> My Reseeds</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=weaktorrents"><i class="fas fa-heart-crack me-2"></i> Weak Torrents</a></li>
                        </ul>
                    </li>
                    
                    <!-- Forums Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-comments me-1"></i> Forums
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/index2.php"><i class="fas fa-forumbee me-2"></i> Forums</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/search.php?action=getnew"><i class="fas fa-star me-2"></i> New Posts</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/search.php?action=getdaily"><i class="fas fa-calendar-day me-2"></i> Today's Posts</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/search.php"><i class="fas fa-search me-2"></i> Search</a></li>
                        </ul>
                    </li>
                    
                    <!-- User CP Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> User CP
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/usercp.php"><i class="fas fa-cog me-2"></i> User CP Home</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/private.php"><i class="fas fa-envelope me-2"></i> Private Messages</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/browse.php?special_search=mytorrents"><i class="fas fa-download me-2"></i> Your Torrents</a></li>
                        </ul>
                    </li>
                    
                    <!-- Top 10 Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-line me-1"></i> Top 10
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=1"><i class="fas fa-users me-2"></i> Users</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=2"><i class="fas fa-download me-2"></i> Torrents</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=3"><i class="fas fa-globe me-2"></i> Countries</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=4"><i class="fas fa-network-wired me-2"></i> Peers</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/topten.php?type=5"><i class="fas fa-comments me-2"></i> Forums</a></li>
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
                                    <i class="fas fa-user-circle me-2"></i> Your Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/memberlist.php">
                                    <i class="fas fa-users me-2"></i> Members List
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/getrss.php">
                                    <i class="fas fa-rss me-2"></i> RSS Feeds
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/invite.php">
                                    <i class="fas fa-user-plus me-2"></i> Invite Friend
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/mybonus.php">
                                    <i class="fas fa-coins me-2"></i> Bonus Points
                                </a>
                            </li>
                        </ul>
                    </li>
					
					
					
					
					
					
					<!--Help Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-h me-1"></i> <?= htmlspecialchars($lang->global['help'] ?? 'Help', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu">
                            
							
							<li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/videoformats.php">
                <i class="fas fa-video me-2"></i> Video Formats
            </a>
        </li>
        
        <!-- Links & Resources -->
        <li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/links.php">
                <i class="fas fa-link me-2"></i> Torrent Links
            </a>
        </li>
        
        <!-- Separator -->
        <li><hr class="dropdown-divider"></li>
        
        <!-- Other Useful Links (optional) -->
        <li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/faq.php">
                <i class="fas fa-question-circle me-2"></i> FAQ
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/rules.php">
                <i class="fas fa-gavel me-2"></i> Rules
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
                                    <i class="fas fa-users-cog me-2"></i> Staff Team
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/contactstaff.php">
                                    <i class="fas fa-envelope me-2"></i> Contact Staff
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
                                    <input class="form-control" type="text" value="" name="keywords" placeholder="Search torrents..." aria-label="Search">
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
                    <i class="fa-solid fa-right-from-bracket me-2"></i> Confirm Logout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-0 fs-6">Are you sure you want to log out?</p>
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
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm hover-shadow" href="<?= htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>/member.php?action=register">
                        <i class="fa-solid fa-user-plus me-1"></i> Register
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
        <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
        <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/errorss.css" rel="stylesheet">
        <div class="container mt-3">
            <div class="card error-card">
                <div class="card-header22">
                    <i class="bi bi-exclamation-triangle-fill error-icon"></i>
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

    eval('$bannedwarning = "' . $templates->get('global_bannedwarning') . '";');
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
            eval('$output = "' . $templates->get('global_no_permission_modal', 1, 0) . '";');
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
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/errorss.css" rel="stylesheet">
    <div class="card error-card">
        <div class="card-header22">
            <i class="bi bi-exclamation-triangle-fill error-icon"></i>
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
    include_once INC_PATH . '/readconfig_cleanup.php';
    require_once INC_PATH . '/functions_mkprettytime.php';
    
    $warnmessages[] = '
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/errorss.css" rel="stylesheet">
    <div class="card error-card">
        <div class="card-header22">
            <i class="bi bi-exclamation-triangle-fill error-icon"></i>
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
if (isset($CURUSER) && ($CURUSER['announce_read'] ?? '') === 'no') {
    $res = $db->sql_query('SELECT subject, message, added, `by` FROM announcements WHERE minclassread IN (0,' . (int)($CURUSER['usergroup'] ?? 0) . ') ORDER by added DESC LIMIT 1');
    if ($db->num_rows($res) > 0) {
        $arr = $db->fetch_array($res);
        
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
                                <a href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/clear_ann.php" class="btn btn-success px-4">
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

        <style>
        .announcement-floating {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1060;
        }
        
        .announcement-btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            border-radius: 16px;
            padding: 16px 20px;
            color: white;
            font-weight: 600;
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            animation: pulse 2s infinite;
        }
        
        .announcement-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.6);
        }
        
        .btn-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }
        
        .btn-text {
            text-align: left;
            line-height: 1.3;
        }
        
        .badge {
            background: #ef4444;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7em;
            font-weight: 700;
            margin-right: 6px;
            animation: blink 2s infinite;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .header-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
        }
        
        .modal-content {
            border: none;
            overflow: hidden;
        }
        
        .announcement-content {
            background: #ffffff;
            min-height: 300px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .content-wrapper {
            line-height: 1.6;
            font-size: 1.05em;
        }
        
        .content-wrapper img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }
        
        .announcement-meta {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4);
            }
            50% {
                box-shadow: 0 8px 30px rgba(59, 130, 246, 0.8);
            }
            100% {
                box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4);
            }
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Анимация появления модалки */
        .modal.fade .modal-dialog {
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }
        
        .modal.show .modal-dialog {
            transform: scale(1);
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            .announcement-floating {
                bottom: 20px;
                right: 20px;
                left: 20px;
            }
            
            .announcement-btn {
                width: 100%;
                padding: 14px 18px;
            }
            
            .btn-content {
                justify-content: center;
            }
            
            .modal-dialog {
                margin: 20px;
            }
            
            .modal-footer .d-flex {
                flex-direction: column;
                gap: 12px;
            }
            
            .btn-group {
                width: 100%;
            }
            
            .btn-group .btn {
                flex: 1;
            }
        }
        </style>';

        $infomessages[] = $zz;
    }      
}

// Private message notice
$current_page = my_strtolower(basename(SCRIPTNAME ?? ''));
$pm_notice = '';

if (isset($CURUSER['pmnotice']) && (int)($CURUSER['pmnotice'] ?? 0) === 2 && ($CURUSER['pms_unread'] ?? 0) > 0 && ($current_page !== "private.php" || ($mybb->get_input('action') ?? '') !== "read")) {
    $query = $db->sql_query("
        SELECT pm.subject, pm.pmid, fu.username AS fromusername, fu.id AS fromuid
        FROM privatemessages pm
        LEFT JOIN users fu on (fu.id=pm.fromid)
        WHERE pm.folder = '1' AND pm.uid = '" . (int)($CURUSER['id'] ?? 0) . "' AND pm.status = '0'
        ORDER BY pm.dateline DESC
        LIMIT 1
    ");

    $pm = $db->fetch_array($query);
    $pm['subject'] = htmlspecialchars_uni($pm['subject'] ?? '');

    if (($pm['fromuid'] ?? 0) === 0) {
        $pm['fromusername'] = 'Ruff Tracker Engine';
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
    <link href="' . htmlspecialchars($BASEURL ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
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