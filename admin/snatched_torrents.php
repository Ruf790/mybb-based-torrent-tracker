<?php

declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border m-3"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

define('ST_VERSION', '0.8');

$lang->load('snatched_torrents');

/**
 * Fill {1}, {2}… placeholders. $lang->load() turns {N} into %N$s,
 * so both forms are replaced (strtr, not sprintf: a literal % is safe).
 */
if (!function_exists('ags_fmt')) {
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

// ---------------------------------------------------------------------
// Search input
// ---------------------------------------------------------------------
$search_user       = isset($_GET['search_user']) ? trim((string)$_GET['search_user']) : '';
$search_torrent    = isset($_GET['search_torrent']) ? trim((string)$_GET['search_torrent']) : '';
$search_user_id    = isset($_GET['search_user_id']) ? (int)$_GET['search_user_id'] : 0;
$search_torrent_id = isset($_GET['search_torrent_id']) ? (int)$_GET['search_torrent_id'] : 0;

$where_conditions = [];
$where_params     = [];
$search_params    = []; // key => value, for pagination & filter chips

if ($search_user !== '') {
    $where_conditions[] = 'u.username LIKE ?';
    $where_params[]     = '%' . $db->escape_string_like($search_user) . '%';
    $search_params['search_user'] = $search_user;
}
if ($search_torrent !== '') {
    $where_conditions[] = 't.name LIKE ?';
    $where_params[]     = '%' . $db->escape_string_like($search_torrent) . '%';
    $search_params['search_torrent'] = $search_torrent;
}
if ($search_user_id > 0) {
    $where_conditions[] = 's.userid = ?';
    $where_params[]     = $search_user_id;
    $search_params['search_user_id'] = $search_user_id;
}
if ($search_torrent_id > 0) {
    $where_conditions[] = 's.torrentid = ?';
    $where_params[]     = $search_torrent_id;
    $search_params['search_torrent_id'] = $search_torrent_id;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$has_filter   = !empty($where_conditions);

// ---------------------------------------------------------------------
// Totals for KPI tiles (one query instead of a bare COUNT)
// ---------------------------------------------------------------------
$res_stats = $db->sql_query_prepared(
    "SELECT COUNT(*) AS cnt,
            COALESCE(SUM(s.completedat > 0), 0)  AS completed,
            COALESCE(SUM(s.seeder = 'yes'), 0)   AS seeding,
            COALESCE(SUM(s.uploaded), 0)         AS up_total,
            COALESCE(SUM(s.downloaded), 0)       AS down_total
       FROM snatched s
  LEFT JOIN torrents t ON (s.torrentid = t.id)
  LEFT JOIN users u    ON (s.userid = u.id)
     {$where_clause}",
    $where_params
);
$stats = $db->fetch_array($res_stats);

$count        = (int)$stats['cnt'];
$stat_done    = (int)$stats['completed'];
$stat_seeding = (int)$stats['seeding'];
$stat_up      = (int)$stats['up_total'];
$stat_down    = (int)$stats['down_total'];
$done_pct     = $count > 0 ? round($stat_done / $count * 100) : 0;
$seed_pct     = $count > 0 ? round($stat_seeding / $count * 100) : 0;

// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------
$perpage = (int)(!empty($CURUSER['torrentsperpage']) ? $CURUSER['torrentsperpage'] : $ts_perpage);
if ($perpage < 1) {
    $perpage = 20;
}

$page  = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
$pages = max(1, (int)ceil($count / $perpage));
if ($page > $pages) {
    $page = 1;
}
$start = ($page - 1) * $perpage;

$search_url = '';
foreach ($search_params as $k => $v) {
    $search_url .= '&' . $k . '=' . urlencode((string)$v);
}
$multipage = multipage($count, $perpage, $page, $_this_script_ . $search_url);

// URL of this page with one filter removed (for the chips)
$url_without = static function (string $drop) use ($search_params, $_this_script_): string {
    $url = $_this_script_;
    foreach ($search_params as $k => $v) {
        if ($k !== $drop) {
            $url .= '&' . $k . '=' . urlencode((string)$v);
        }
    }
    return htmlspecialchars($url, ENT_QUOTES);
};

$filter_labels = [
    'search_user'       => ['fa-user',          $lang->snatched_torrents['chip_user']],
    'search_user_id'    => ['fa-id-badge',      $lang->snatched_torrents['chip_user_id']],
    'search_torrent'    => ['fa-file-lines',    $lang->snatched_torrents['chip_torrent']],
    'search_torrent_id' => ['fa-hashtag',       $lang->snatched_torrents['chip_torrent_id']],
];

// ---------------------------------------------------------------------
// Main query
// ---------------------------------------------------------------------
$result = $db->sql_query_prepared(
    "SELECT s.*, t.name, t.size, t.added,
            u.username AS uname, u.id AS uid, u.usergroup, u.avatar, u.avatardimensions,
            u.donor, u.enabled, u.warned, u.leechwarn
       FROM snatched s
  LEFT JOIN torrents t ON (s.torrentid = t.id)
  LEFT JOIN users u    ON (s.userid = u.id)
     {$where_clause}
   ORDER BY s.to_go DESC
      LIMIT ?, ?",
    array_merge($where_params, [(int)$start, (int)$perpage])
);
$num_rows = (int)$db->num_rows($result);

stdhead($lang->snatched_torrents['page_title']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/snatched_torrents.css?v=' . ST_VERSION . '">';
echo '<script src="' . $BASEURL . '/scripts/popover.js"></script>';
echo '<script src="' . $BASEURL . '/admin/scripts/snatched_torrents.js?v=' . ST_VERSION . '" defer></script>';
?>

<div class="container mt-3 py-4 stn-page">

    <!-- Header -->
    <div class="stn-card stn-head mb-3">
        <div class="stn-head__icon"><i class="fa-solid fa-cloud-arrow-down"></i></div>
        <div class="flex-grow-1">
            <h1 class="stn-title"><?php echo $lang->snatched_torrents['sec_title']; ?></h1>
            <p><?php echo $lang->snatched_torrents['sec_subtitle']; ?></p>
        </div>
        <?php if ($has_filter): ?>
            <span class="stn-badge stn-soft-info d-none d-md-inline-flex"><i class="fa-solid fa-filter"></i><?php echo $lang->snatched_torrents['badge_filtered']; ?></span>
        <?php endif; ?>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-primary"><i class="fa-solid fa-database"></i></div>
                <div>
                    <div class="stn-kpi__value"><?php echo number_format($count); ?></div>
                    <div class="stn-kpi__label"><?php echo $lang->snatched_torrents['kpi_total']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-success"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="stn-kpi__value"><?php echo number_format($stat_done); ?></div>
                    <div class="stn-kpi__label"><?php echo $lang->snatched_torrents['kpi_completed']; ?> <span class="stn-kpi__sub">(<?php echo $done_pct; ?>%)</span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-info"><i class="fa-solid fa-seedling"></i></div>
                <div>
                    <div class="stn-kpi__value"><?php echo number_format($stat_seeding); ?></div>
                    <div class="stn-kpi__label"><?php echo $lang->snatched_torrents['kpi_seeding']; ?> <span class="stn-kpi__sub">(<?php echo $seed_pct; ?>%)</span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-warning"><i class="fa-solid fa-right-left"></i></div>
                <div>
                    <div class="stn-kpi__value stn-kpi__value--sm">
                        <i class="fa-solid fa-arrow-up text-success stn-ico-sm"></i> <?php echo mksize($stat_up); ?>
                    </div>
                    <div class="stn-kpi__sub">
                        <i class="fa-solid fa-arrow-down text-danger"></i> <?php echo ags_fmt($lang->snatched_torrents['kpi_downloaded'], mksize($stat_down)); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="stn-card stn-search mb-3">
        <form method="get" action="<?php echo $_this_script_; ?>" id="searchForm">
            <input type="hidden" name="act" value="snatched_torrents">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg">
                    <label class="form-label" for="stn_user"><i class="fa-solid fa-user me-1"></i><?php echo $lang->snatched_torrents['lbl_username']; ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                        <input type="text" class="form-control" id="stn_user" name="search_user"
                               value="<?php echo htmlspecialchars($search_user, ENT_QUOTES); ?>" placeholder="<?php echo htmlspecialchars($lang->snatched_torrents['ph_username'], ENT_QUOTES); ?>">
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <label class="form-label" for="stn_uid"><i class="fa-solid fa-id-badge me-1"></i><?php echo $lang->snatched_torrents['lbl_user_id']; ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-id-badge"></i></span>
                        <input type="number" min="1" class="form-control" id="stn_uid" name="search_user_id"
                               value="<?php echo $search_user_id ?: ''; ?>" placeholder="<?php echo htmlspecialchars($lang->snatched_torrents['ph_user_id'], ENT_QUOTES); ?>">
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <label class="form-label" for="stn_tname"><i class="fa-solid fa-file-lines me-1"></i><?php echo $lang->snatched_torrents['lbl_torrent_name']; ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" id="stn_tname" name="search_torrent"
                               value="<?php echo htmlspecialchars($search_torrent, ENT_QUOTES); ?>" placeholder="<?php echo htmlspecialchars($lang->snatched_torrents['ph_torrent_name'], ENT_QUOTES); ?>">
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <label class="form-label" for="stn_tid"><i class="fa-solid fa-hashtag me-1"></i><?php echo $lang->snatched_torrents['lbl_torrent_id']; ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                        <input type="number" min="1" class="form-control" id="stn_tid" name="search_torrent_id"
                               value="<?php echo $search_torrent_id ?: ''; ?>" placeholder="<?php echo htmlspecialchars($lang->snatched_torrents['ph_torrent_id'], ENT_QUOTES); ?>">
                    </div>
                </div>
                <div class="col-12 col-lg-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary stn-pill">
                        <i class="fa-solid fa-magnifying-glass me-1"></i><?php echo $lang->snatched_torrents['btn_search']; ?>
                    </button>
                    <a href="<?php echo $_this_script_; ?>" id="clearSearch" class="btn btn-outline-secondary stn-pill">
                        <i class="fa-solid fa-rotate-left me-1"></i><?php echo $lang->snatched_torrents['btn_reset']; ?>
                    </a>
                </div>
            </div>

            <?php if ($has_filter): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <span class="stn-meta"><i class="fa-solid fa-filter me-1"></i><?php echo $lang->snatched_torrents['lbl_active_filters']; ?></span>
                <?php foreach ($search_params as $key => $val):
                    [$ico, $lbl] = $filter_labels[$key]; ?>
                    <a class="stn-chip stn-soft-primary" href="<?php echo $url_without($key); ?>" title="<?php echo htmlspecialchars($lang->snatched_torrents['tip_remove_filter'], ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars($lang->snatched_torrents['tip_remove_filter'], ENT_QUOTES); ?>">
                        <i class="fa-solid <?php echo $ico; ?>"></i>
                        <?php echo htmlspecialchars($lbl, ENT_QUOTES); ?>: <b><?php echo htmlspecialchars((string)$val, ENT_QUOTES); ?></b>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="stn-card overflow-hidden">
    <?php if ($num_rows > 0): ?>

        <div class="stn-toolbar">
            <span><i class="fa-solid fa-list me-1"></i><?php echo ags_fmt($lang->snatched_torrents['txt_showing'], number_format($start + 1), number_format(min($start + $perpage, $count)), number_format($count)); ?></span>
            <?php if ($count > $perpage) { echo '<div>' . $multipage . '</div>'; } ?>
        </div>

        <div class="table-responsive">
            <table class="table stn-table">
                <thead>
                    <tr>
                        <th class="sortable" data-type="text"><i class="fa-solid fa-user"></i><?php echo $lang->snatched_torrents['col_user']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable" data-type="text"><i class="fa-solid fa-magnet"></i><?php echo $lang->snatched_torrents['col_torrent']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable text-end" data-type="num"><i class="fa-solid fa-arrow-up"></i><?php echo $lang->snatched_torrents['col_uploaded']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable text-end" data-type="num"><i class="fa-solid fa-arrow-down"></i><?php echo $lang->snatched_torrents['col_downloaded']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable text-center" data-type="num"><i class="fa-solid fa-scale-balanced"></i><?php echo $lang->snatched_torrents['col_ratio']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable" data-type="num"><i class="fa-solid fa-play"></i><?php echo $lang->snatched_torrents['col_started']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable" data-type="num"><i class="fa-solid fa-flag-checkered"></i><?php echo $lang->snatched_torrents['col_completed']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable text-center" data-type="num"><i class="fa-solid fa-seedling"></i><?php echo $lang->snatched_torrents['col_seeding']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                        <th class="sortable" data-type="num"><i class="fa-solid fa-bars-progress"></i><?php echo $lang->snatched_torrents['col_progress']; ?><i class="fa-solid fa-sort stn-sort-ico"></i></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                while ($row = $db->fetch_array($result)) {
                    $uploaded    = (int)($row['uploaded'] ?? 0);
                    $downloaded  = (int)($row['downloaded'] ?? 0);
                    $size        = (int)($row['size'] ?? 0);
                    $to_go       = (int)($row['to_go'] ?? 0);
                    $startdat    = (int)($row['startdat'] ?? 0);
                    $completedat = (int)($row['completedat'] ?? 0);
                    $is_seeder   = ($row['seeder'] ?? '') === 'yes';

                    // Progress: finished => 100, otherwise from bytes left (to_go)
                    if ($completedat > 0) {
                        $progress = 100;
                    } elseif ($size > 0) {
                        $progress = (int)max(0, min(100, round(($size - $to_go) / $size * 100)));
                    } else {
                        $progress = 0;
                    }
                    $progress_class = match (true) {
                        $progress >= 100 => 'bg-success',
                        $progress >= 50  => 'bg-info',
                        $progress > 0    => 'bg-warning',
                        default          => 'bg-secondary',
                    };

                    // Ratio
                    if ($downloaded > 0) {
                        $ratio_val  = $uploaded / $downloaded;
                        $ratio_txt  = number_format($ratio_val, 2);
                        $ratio_soft = $ratio_val >= 1 ? 'stn-soft-success' : ($ratio_val >= 0.5 ? 'stn-soft-warning' : 'stn-soft-danger');
                    } else {
                        $ratio_val  = $uploaded > 0 ? PHP_INT_MAX : 0;
                        $ratio_txt  = $uploaded > 0 ? '&infin;' : '—';
                        $ratio_soft = $uploaded > 0 ? 'stn-soft-success' : 'stn-soft-muted';
                    }

                    // User (may be deleted)
                    $user_exists = !empty($row['uid']);
                    $uname_safe  = htmlspecialchars_uni((string)($row['uname'] ?? ''));
                    $useravatar  = format_avatar((string)($row['avatar'] ?? ''), (string)($row['avatardimensions'] ?? ''));
                    $user_avatar = '<img class="nav-avatar" src="' . $useravatar['image'] . '" alt="" loading="lazy">';

                    // Torrent (may be deleted)
                    $torrent_exists = $row['name'] !== null;
                    $raw_name       = (string)($row['name'] ?? '');
                    $torrent_name   = htmlspecialchars_uni($raw_name);
                    $short_name     = htmlspecialchars_uni(cutename($raw_name));
                    $torrent_link   = $BASEURL . '/' . get_torrent_link((int)$row['torrentid']);
                    $torrent_added  = !empty($row['added']) ? date('Y-m-d H:i', (int)$row['added']) : htmlspecialchars($lang->snatched_torrents['txt_na'], ENT_QUOTES);

                    $popover_title   = htmlspecialchars('<i class="fa-solid fa-folder-open me-1"></i>' . htmlspecialchars_uni(cutename($raw_name, 30)), ENT_QUOTES);
                    $popover_content = htmlspecialchars(
                        '<div class="small text-break mb-2">' . $torrent_name . '</div>'
                        . '<div class="d-flex justify-content-between gap-3 border-top pt-2 small text-body-secondary">'
                        . '<span><i class="fa-solid fa-hard-drive me-1"></i>' . mksize($size) . '</span>'
                        . '<span><i class="fa-solid fa-calendar-plus me-1"></i>' . $torrent_added . '</span>'
                        . '</div>',
                        ENT_QUOTES
                    );

                    $progress_content = htmlspecialchars(
                        '<div class="small">'
                        . ($progress >= 100
                            ? '<i class="fa-solid fa-circle-check text-success me-1"></i>' . htmlspecialchars($lang->snatched_torrents['pop_completed'], ENT_QUOTES)
                            : '<i class="fa-solid fa-spinner text-warning me-1"></i>' . htmlspecialchars(ags_fmt($lang->snatched_torrents['pop_left'], $progress, mksize($to_go)), ENT_QUOTES))
                        . '</div>',
                        ENT_QUOTES
                    );
                ?>
                    <tr>
                        <!-- User -->
                        <td data-sort="<?php echo htmlspecialchars(mb_strtolower((string)($row['uname'] ?? '')), ENT_QUOTES); ?>">
                            <div class="d-flex align-items-center gap-2 stn-user">
                                <?php echo $user_avatar; ?>
                                <div class="lh-sm">
                                    <?php if ($user_exists): ?>
                                        <a href="<?php echo $BASEURL . '/' . get_profile_link((int)$row['uid']); ?>">
                                            <?php echo format_name($uname_safe, (int)$row['usergroup']); ?>
                                        </a>
                                        <?php echo get_user_icons($row); ?>
                                        <div class="stn-meta"><i class="fa-solid fa-id-badge me-1"></i><?php echo (int)$row['uid']; ?></div>
                                    <?php else: ?>
                                        <span class="stn-meta"><i class="fa-solid fa-user-slash me-1"></i><?php echo $lang->snatched_torrents['txt_deleted_user']; ?></span>
                                        <div class="stn-meta">#<?php echo (int)$row['userid']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Torrent -->
                        <td data-sort="<?php echo htmlspecialchars(mb_strtolower($raw_name), ENT_QUOTES); ?>">
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($torrent_exists): ?>
                                    <span class="stn-torrent-ico stn-soft-danger"><i class="fa-solid fa-magnet"></i></span>
                                    <div class="lh-sm">
                                        <a href="<?php echo $torrent_link; ?>" class="stn-torrent torrent-link"
                                           data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="hover focus"
                                           data-bs-html="true"
                                           data-bs-title="<?php echo $popover_title; ?>"
                                           data-bs-content="<?php echo $popover_content; ?>"><?php echo $short_name; ?></a>
                                        <div class="stn-meta">
                                            <i class="fa-solid fa-hashtag"></i><?php echo (int)$row['torrentid']; ?>
                                            <span class="ms-2"><i class="fa-solid fa-hard-drive me-1"></i><?php echo mksize($size); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="stn-torrent-ico stn-soft-muted"><i class="fa-solid fa-trash-can"></i></span>
                                    <div class="lh-sm">
                                        <span class="stn-meta"><?php echo $lang->snatched_torrents['txt_deleted_torrent']; ?></span>
                                        <div class="stn-meta"><i class="fa-solid fa-hashtag"></i><?php echo (int)$row['torrentid']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Traffic -->
                        <td class="text-end stn-traffic text-success" data-sort="<?php echo $uploaded; ?>">
                            <i class="fa-solid fa-arrow-up me-1 stn-ico-sm"></i><?php echo mksize($uploaded); ?>
                        </td>
                        <td class="text-end stn-traffic text-danger" data-sort="<?php echo $downloaded; ?>">
                            <i class="fa-solid fa-arrow-down me-1 stn-ico-sm"></i><?php echo mksize($downloaded); ?>
                        </td>

                        <!-- Ratio -->
                        <td class="text-center" data-sort="<?php echo $ratio_val; ?>">
                            <span class="stn-badge <?php echo $ratio_soft; ?>"><?php echo $ratio_txt; ?></span>
                        </td>

                        <!-- Started -->
                        <td class="stn-date" data-sort="<?php echo $startdat; ?>">
                            <?php if ($startdat > 0): ?>
                                <div><i class="fa-solid fa-calendar-day"></i><?php echo my_datee($dateformat, $startdat); ?></div>
                                <div class="stn-meta"><i class="fa-regular fa-clock"></i><?php echo my_datee($timeformat, $startdat); ?></div>
                            <?php else: ?>
                                <span class="stn-meta">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Completed -->
                        <td class="stn-date" data-sort="<?php echo $completedat; ?>">
                            <?php if ($completedat > 0): ?>
                                <span class="stn-badge stn-soft-success"><i class="fa-solid fa-flag-checkered"></i><?php echo my_datee($dateformat, $completedat); ?></span>
                                <div class="stn-meta mt-1 ms-1"><i class="fa-regular fa-clock"></i><?php echo my_datee($timeformat, $completedat); ?></div>
                            <?php elseif ($progress > 0): ?>
                                <span class="stn-badge stn-soft-warning"><i class="fa-solid fa-hourglass-half"></i><?php echo $lang->snatched_torrents['st_in_progress']; ?></span>
                            <?php else: ?>
                                <span class="stn-badge stn-soft-muted"><i class="fa-solid fa-circle-pause"></i><?php echo $lang->snatched_torrents['st_not_started']; ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Seeding -->
                        <td class="text-center" data-sort="<?php echo $is_seeder ? 1 : 0; ?>">
                            <?php if ($is_seeder): ?>
                                <span class="stn-badge stn-soft-success"><i class="fa-solid fa-seedling"></i><?php echo $lang->snatched_torrents['opt_yes']; ?></span>
                            <?php else: ?>
                                <span class="stn-badge stn-soft-muted"><i class="fa-solid fa-circle-minus"></i><?php echo $lang->snatched_torrents['opt_no']; ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Progress -->
                        <td data-sort="<?php echo $progress; ?>">
                            <div class="progress stn-progress"
                                 data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true"
                                 data-bs-title="<?php echo htmlspecialchars('<i class="fa-solid fa-bars-progress me-1"></i>' . htmlspecialchars($lang->snatched_torrents['pop_progress_title'], ENT_QUOTES), ENT_QUOTES); ?>"
                                 data-bs-content="<?php echo $progress_content; ?>">
                                <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar"
                                     style="width: <?php echo $progress; ?>%"
                                     aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="stn-meta text-center mt-1"><?php echo $progress; ?>%</div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($count > $perpage): ?>
        <div class="stn-toolbar stn-toolbar--bottom">
            <span><?php echo ags_fmt($lang->snatched_torrents['txt_page_of'], $page, $pages); ?></span>
            <div><?php echo $multipage; ?></div>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="stn-empty">
            <div class="stn-empty__icon <?php echo $has_filter ? 'stn-soft-warning' : 'stn-soft-muted'; ?>">
                <i class="fa-solid <?php echo $has_filter ? 'fa-magnifying-glass-minus' : 'fa-inbox'; ?>"></i>
            </div>
            <?php if ($has_filter): ?>
                <h4 class="stn-title"><?php echo $lang->snatched_torrents['empty_filtered_title']; ?></h4>
                <p class="text-body-secondary mb-3"><?php echo $lang->snatched_torrents['empty_filtered_text']; ?></p>
                <a href="<?php echo $_this_script_; ?>" class="btn btn-outline-secondary stn-pill"><i class="fa-solid fa-rotate-left me-1"></i><?php echo $lang->snatched_torrents['btn_reset_filters']; ?></a>
            <?php else: ?>
                <h4 class="stn-title"><?php echo $lang->snatched_torrents['empty_title']; ?></h4>
                <p class="text-body-secondary mb-0"><?php echo $lang->snatched_torrents['empty_text']; ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>
</div>

<?php
stdfoot();