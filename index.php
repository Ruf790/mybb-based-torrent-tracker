<?php

declare(strict_types=1);

define("SCRIPTNAME", "index.php");
require_once 'global.php';

$lang->load('indexmain');

require_once INC_PATH . '/functions_icons.php';

if (!function_exists('ags_fmt')) {
    /**
     * Substitutes {1}, {2}… placeholders (and %1$s, %2$s… produced by $lang->load()).
     */
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

if (empty($CURUSER['id'])) {
    header('Location: member.php?action=login');
    exit;
}

require_once INC_PATH . '/class_parser.php';
$parser = new postParser();

$parser_options = [
    "allow_html"      => 0,
    "allow_mycode"    => 1,
    "allow_smilies"   => 1,
    "allow_imgcode"   => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1,
];

$L      = $lang->indexmain;
$G      = $lang->global;
$is_mod = is_mod($usergroups);
$myId   = (int)$CURUSER['id'];

/** Text from the page lang, escaped for HTML output. */
$e = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/** One user chip (online / last 24 h lists). */
$userChip = static function (array $u, bool $hidden): string {
    $icons = get_user_icons($u);
    return '<span class="ix-uchip' . ($hidden ? ' ix-uchip--hidden' : '') . '">'
         . ($hidden ? '<i class="fa-solid fa-eye-slash ix-uchip__hid" aria-hidden="true"></i>' : '')
         . '<a href="' . get_profile_link((int)$u['id']) . '">'
         . format_name(htmlspecialchars_uni((string)$u['username']), (int)$u['usergroup'])
         . '</a>'
         . ($icons !== '' ? '<span class="ix-uchip__icons">' . $icons . '</span>' : '')
         . '</span>';
};

// ───────────────────────────── KPI (one query) ──────────────────────────────
$kpi = ['total' => 0, 'seeders' => 0, 'leechers' => 0, 'dead' => 0];
$kq = $db->sql_query_prepared(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(seeders), 0)  AS seeders,
            COALESCE(SUM(leechers), 0) AS leechers,
            COALESCE(SUM(seeders = 0 AND leechers > 0), 0) AS dead
       FROM torrents
      WHERE visible = 'yes' AND banned = 'no'"
);
if ($kq && ($row = $db->fetch_array($kq))) {
    foreach ($kpi as $k => $_) {
        $kpi[$k] = (int)($row[$k] ?? 0);
    }
}

// ───────────────────────────── Online users ─────────────────────────────────
$_wgo_query = $db->sql_query_prepared("
    SELECT s.uid AS id, u.username, u.usergroup, u.enabled, u.invisible,
           u.donor, u.leechwarn, u.warned, u.canupload, u.candownload, u.cancomment
      FROM sessions s
      INNER JOIN users u ON (u.id = s.uid)
     WHERE s.uid != 0 AND s.time > ?
     ORDER BY u.username
", [TIMENOW - (int)$wolcutoffmins * 60]);

$onlineChips  = [];
$onlineHidden = 0;
$onlineShown  = 0;
$seen         = [];

while ($u = $db->fetch_array($_wgo_query)) {
    $uid = (int)$u['id'];
    if (isset($seen[$uid])) {
        continue;
    }
    $seen[$uid] = true;

    $hidden = ((int)$u['invisible'] === 1);
    if ($hidden) {
        $onlineHidden++;
        if ($uid !== $myId && !$is_mod) {
            continue;
        }
    } else {
        $onlineShown++;
    }
    $onlineChips[] = $userChip($u, $hidden);
}
$onlineTotal = $onlineShown + $onlineHidden;

// Record "most users ever online"
$onlinestats = $cache->read('onlinestats');
if (!is_array($onlinestats)) {
    $onlinestats = [];
}
$onlinestats['most_ever'] ??= 0;
if ($onlineTotal > (int)$onlinestats['most_ever']) {
    $onlinestats['most_ever']      = $onlineTotal;
    $onlinestats['most_ever_time'] = TIMENOW;
    $cache->update('onlinestats', $onlinestats);
}

// ───────────────────────────── Last 24 hours ────────────────────────────────
$q24 = $db->sql_query_prepared(
    "SELECT u.id, u.username, u.usergroup, u.enabled, u.invisible,
            u.donor, u.leechwarn, u.warned, u.canupload, u.candownload, u.cancomment
       FROM users u
      WHERE u.last_login > ?
      ORDER BY u.username",
    [TIMENOW - 86400]
);

$dayChips  = [];
$dayHidden = 0;
$dayShown  = 0;

while ($u = $db->fetch_array($q24)) {
    $hidden = ((int)$u['invisible'] === 1);
    if ($hidden) {
        $dayHidden++;
        if ((int)$u['id'] !== $myId && !$is_mod) {
            continue;
        }
    } else {
        $dayShown++;
    }
    $dayChips[] = $userChip($u, $hidden);
}

// ───────────────────────────── News ─────────────────────────────────────────
$news = null;
$nq = $db->sql_query_prepared(
    "SELECT n.id, n.userid, n.added, n.body, n.title, u.username, u.usergroup
       FROM news n
       LEFT JOIN users u ON u.id = n.userid
      ORDER BY n.added DESC
      LIMIT 1"
);
if ($nq && ($row = $db->fetch_array($nq))) {
    $news = $row;
}

// ───────────────────────────── Charts data ──────────────────────────────────
$chartPopular = ['names' => [], 'values' => []];
$cq = $db->sql_query_prepared(
    "SELECT name, hits FROM torrents
      WHERE visible = 'yes' AND banned = 'no' AND hits > 0
      ORDER BY hits DESC LIMIT 10"
);
while ($row = $db->fetch_array($cq)) {
    $chartPopular['names'][]  = (string)$row['name'];
    $chartPopular['values'][] = (int)$row['hits'];
}

$chartActive = ['names' => [], 'values' => []];
$cq = $db->sql_query_prepared(
    "SELECT name, times_completed FROM torrents
      WHERE visible = 'yes' AND banned = 'no' AND times_completed > 0
      ORDER BY times_completed DESC LIMIT 10"
);
while ($row = $db->fetch_array($cq)) {
    $chartActive['names'][]  = (string)$row['name'];
    $chartActive['values'][] = (int)$row['times_completed'];
}

// ───────────────────────────── Latest torrents ──────────────────────────────
$torrents = [];
$tq = $db->sql_query_prepared(
    "SELECT id, name, t_image, added, size, seeders, leechers,
            free, silver, doubleupload, thirtypercent
       FROM torrents
      WHERE visible = 'yes' AND banned = 'no' AND t_image != ''
      ORDER BY added DESC
      LIMIT 6"
);
while ($row = $db->fetch_array($tq)) {
    $torrents[] = $row;
}

// ───────────────────────────── Seeders needed ───────────────────────────────
$needSeed = [];
$sq = $db->sql_query_prepared(
    "SELECT t.id, t.name, t.seeders, t.leechers, t.size, t.t_image, c.name AS category_name
       FROM torrents t
       LEFT JOIN categories c ON c.id = t.category
      WHERE t.visible = 'yes' AND t.banned = 'no'
        AND t.seeders = 0 AND t.leechers > 0
      ORDER BY t.added DESC
      LIMIT 10"
);
while ($row = $db->fetch_array($sq)) {
    $needSeed[] = $row;
}

// ───────────────────────────── JS lang ──────────────────────────────────────
$agsJsLang = [];
foreach ($L as $agsKey => $agsVal) {
    if (str_starts_with((string)$agsKey, 'js_')) {
        $agsJsLang[substr((string)$agsKey, 3)] = $agsVal;
    }
}
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

/** Promotion badges for a torrent row. */
$promoBadges = static function (array $t) use ($L, $e): string {
    $map = [
        'free'          => ['st-soft-success', 'fa-gift',          'lbl_free',          'tip_free',          'Free',      'Free Torrent'],
        'silver'        => ['st-soft-muted',   'fa-medal',         'lbl_silver',        'tip_silver',        'Silver',    'Silver Torrent'],
        'thirtypercent' => ['st-soft-info',    'fa-percent',       'lbl_thirty',        'tip_thirty',        '30% Leech', '30% Leech Torrent'],
        'doubleupload'  => ['st-soft-warning', 'fa-angles-up',     'lbl_double_upload', 'tip_double_upload', '2x Upload', 'Double Upload'],
    ];
    $out = '';
    foreach ($map as $col => [$soft, $icon, $lk, $tk, $lf, $tf]) {
        if (($t[$col] ?? 'no') === 'yes') {
            $out .= '<span class="st-badge ' . $soft . '" title="' . $e($L[$tk] ?? $tf) . '">'
                  . '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i>' . $e($L[$lk] ?? $lf) . '</span>';
        }
    }
    return $out;
};

// ═════════════════════════════ OUTPUT ═══════════════════════════════════════
stdhead($L['page_title'] ?? 'Home');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/st-ui.css?ver=7">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/index.css?ver=1">

<div class="container-md st-page ix-page">

  <!-- Header -->
  <header class="st-card st-head mb-3">
    <span class="st-head__icon st-soft-primary"><i class="fa-solid fa-house"></i></span>
    <div class="min-w-0 flex-grow-1">
      <h1 class="st-title"><?= htmlspecialchars_uni(strip_tags((string)$SITENAME)) ?></h1>
      <div class="st-head__meta">
        <span><i class="fa-solid fa-signal"></i><?= $e(ags_fmt($L['lbl_total_online'] ?? 'Total Online: {1}', ts_nf($onlineTotal))) ?></span>
        <span><i class="fa-solid fa-clock-rotate-left"></i><?= $e(($L['sec_last24'] ?? 'Last 24 Hours Active Users') . ': ' . ts_nf($dayShown + $dayHidden)) ?></span>
      </div>
    </div>
    <div class="ix-head__actions">
      <a href="browse.php" class="btn btn-sm btn-outline-secondary st-pill ix-btn"><i class="fa-solid fa-list"></i><span><?= $e($G['browse'] ?? 'Browse') ?></span></a>
      <a href="upload.php" class="btn btn-sm btn-primary st-pill ix-btn"><i class="fa-solid fa-upload"></i><span><?= $e($G['upload'] ?? 'Upload') ?></span></a>
    </div>
  </header>

  <!-- KPI -->
  <?php
  $tiles = [
      ['fa-box-archive',  'st-soft-primary', ts_nf($kpi['total']),    $L['lbl_kpi_torrents'] ?? 'Torrents',     'browse.php'],
      ['fa-arrow-up',     'st-soft-success', ts_nf($kpi['seeders']),  $G['seeders']  ?? 'Seeders',           ''],
      ['fa-arrow-down',   'st-soft-danger',  ts_nf($kpi['leechers']), $G['leechers'] ?? 'Leechers',          ''],
      ['fa-user-check',   'st-soft-info',    ts_nf($onlineTotal),     $L['sec_online'] ?? 'Online Users',    'online.php'],
  ];
  ?>
  <div class="ix-kpis mb-3">
    <?php foreach ($tiles as [$icon, $soft, $val, $lbl, $href]):
        $tag = $href !== '' ? 'a' : 'div'; ?>
      <<?= $tag ?><?= $href !== '' ? ' href="' . $href . '"' : '' ?> class="st-kpi st-kpi--muted">
        <span class="st-ico <?= $soft ?>"><i class="fa-solid <?= $icon ?>"></i></span>
        <div class="min-w-0"><div class="st-kpi__val"><?= $val ?></div><div class="st-kpi__lbl"><?= $e($lbl) ?></div></div>
        <i class="fa-solid <?= $icon ?> st-kpi__bg" aria-hidden="true"></i>
      </<?= $tag ?>>
    <?php endforeach; ?>
  </div>

  <!-- News -->
  <section class="st-card mb-3">
    <div class="st-card__head">
      <span class="st-ico st-soft-info"><i class="fa-solid fa-newspaper"></i></span>
      <span><?= $e($L['sec_news'] ?? 'Latest News') ?></span>
    </div>
    <?php if ($news !== null): ?>
      <article class="st-card__body ix-news">
        <?php if (trim((string)$news['title']) !== ''): ?>
          <h2 class="ix-news__title"><?= htmlspecialchars_uni((string)$news['title']) ?></h2>
        <?php endif; ?>
        <div class="st-meta mb-2">
          <i class="fa-regular fa-clock me-1" aria-hidden="true"></i>
          <?php
          $author = !empty($news['username'])
              ? '<a href="' . get_profile_link((int)$news['userid']) . '">'
                  . format_name(htmlspecialchars_uni((string)$news['username']), (int)$news['usergroup']) . '</a>'
              : '<strong>' . $e($L['lbl_system'] ?? 'System') . '</strong>';
          echo ags_fmt($e($L['lbl_posted_by'] ?? 'Posted by {1} on {2}'), $author, my_datee('relative', (int)$news['added']));
          ?>
        </div>
        <div class="ix-news__body"><?= $parser->parse_message((string)$news['body'], $parser_options) ?></div>
      </article>
    <?php else: ?>
      <div class="st-empty">
        <span class="st-ico st-soft-muted"><i class="fa-solid fa-newspaper"></i></span>
        <div><?= $e($L['msg_no_news'] ?? 'No news found.') ?></div>
      </div>
    <?php endif; ?>
  </section>

  <!-- Latest torrents -->
  <?php if ($torrents): ?>
  <section class="st-card mb-3">
    <div class="st-card__head">
      <span class="st-ico st-soft-primary"><i class="fa-solid fa-cloud-arrow-down"></i></span>
      <span><?= $e($L['sec_latest_torrents'] ?? 'Latest Torrents') ?></span>
      <a href="browse.php" class="ms-auto btn btn-sm btn-outline-secondary st-pill ix-btn"><i class="fa-solid fa-arrow-right"></i><span><?= $e($L['btn_show_all'] ?? 'Show all') ?></span></a>
    </div>
    <div class="st-card__body">
      <div class="ix-tgrid">
        <?php foreach ($torrents as $t):
            $name = htmlspecialchars_uni((string)$t['name']); ?>
          <article class="ix-tcard">
            <div class="ix-tcard__poster">
              <img src="<?= htmlspecialchars_uni((string)$t['t_image']) ?>" alt="<?= $name ?>" loading="lazy">
              <div class="ix-tcard__stats">
                <span><i class="fa-solid fa-hard-drive" aria-hidden="true"></i><?= mksize($t['size'] ?? 0) ?></span>
                <span class="ix-up"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i><?= ts_nf((int)$t['seeders']) ?></span>
                <span class="ix-down"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i><?= ts_nf((int)$t['leechers']) ?></span>
              </div>
            </div>
            <div class="ix-tcard__body">
              <a href="<?= get_torrent_link((int)$t['id']) ?>" class="ix-tcard__name stretched-link" title="<?= $name ?>"><?= $name ?></a>
              <?php $badges = $promoBadges($t); if ($badges !== ''): ?>
                <div class="ix-tcard__badges"><?= $badges ?></div>
              <?php endif; ?>
              <div class="st-meta"><?= $e(ags_fmt($L['lbl_added'] ?? 'Added: {1}', my_datee($dateformat, (int)$t['added']))) ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Seeders needed -->
  <section class="st-card mb-3 ix-need">
    <div class="st-card__head">
      <?php if ($needSeed): ?>
        <span class="st-ico st-soft-danger"><i class="fa-solid fa-circle-exclamation"></i></span>
        <span><?= $e($L['sec_seeders_needed'] ?? 'Recently Uploaded Torrents Needing Seeders') ?></span>
        <span class="st-badge st-soft-danger"><?= ts_nf($kpi['dead']) ?></span>
        <?php if ($kpi['dead'] > count($needSeed)): ?>
          <a href="browse.php?no_seeders=1" class="ms-auto btn btn-sm btn-outline-secondary st-pill ix-btn"><i class="fa-solid fa-arrow-right"></i><span><?= $e($L['btn_show_all'] ?? 'Show all') ?></span></a>
        <?php endif; ?>
      <?php else: ?>
        <span class="st-ico st-soft-success"><i class="fa-solid fa-circle-check"></i></span>
        <span><?= $e($L['sec_all_seeded'] ?? 'All Torrents Have Seeders') ?></span>
      <?php endif; ?>
    </div>

    <?php if ($needSeed): ?>
      <ul class="ix-need__list">
        <?php foreach ($needSeed as $t):
            $tid = (int)$t['id'];
            $img = (string)$t['t_image']; ?>
          <li class="ix-need__row">
            <?php if ($img !== ''): ?>
              <img class="ix-need__thumb" src="<?= htmlspecialchars_uni($img) ?>" alt="<?= $e($L['alt_poster'] ?? 'Poster') ?>" loading="lazy">
            <?php else: ?>
              <span class="ix-need__thumb ix-need__thumb--none"><i class="fa-solid fa-film" aria-hidden="true"></i></span>
            <?php endif; ?>
            <div class="min-w-0 flex-grow-1">
              <a href="<?= get_torrent_link($tid) ?>" class="torrent-preview ix-need__name" data-id="<?= $tid ?>" data-bs-toggle="modal" data-bs-target="#torrentModal"><?= htmlspecialchars_uni((string)$t['name']) ?></a>
              <div class="ix-need__meta">
                <span><i class="fa-solid fa-database" aria-hidden="true"></i><?= mksize($t['size'] ?? 0) ?></span>
                <?php if (!empty($t['category_name'])): ?>
                  <span><i class="fa-solid fa-tag" aria-hidden="true"></i><?= htmlspecialchars_uni((string)$t['category_name']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="ix-need__peers">
              <span class="st-badge st-soft-muted" title="<?= $e($L['col_seeders'] ?? 'Seeders') ?>"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>0</span>
              <span class="st-badge st-soft-danger" title="<?= $e($L['col_leechers'] ?? 'Leechers') ?>"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i><?= ts_nf((int)$t['leechers']) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="st-empty">
        <span class="st-ico st-soft-success"><i class="fa-solid fa-face-smile-beam"></i></span>
        <div><?= $e($L['msg_all_seeded'] ?? 'Great job! All torrents are currently seeded.') ?></div>
      </div>
    <?php endif; ?>
  </section>

  <!-- Charts -->
  <div class="ix-charts mb-3">
    <?php foreach ([
        ['popularTorrentsChart', 'fa-fire',          'st-soft-warning', $L['sec_popular'] ?? 'Most Popular Torrents', $chartPopular],
        ['activeTorrentsChart',  'fa-arrow-trend-up', 'st-soft-info',   $L['sec_active']  ?? 'Most Active Torrents',  $chartActive],
    ] as [$cid, $icon, $soft, $title, $data]): ?>
      <section class="st-card">
        <div class="st-card__head">
          <span class="st-ico <?= $soft ?>"><i class="fa-solid <?= $icon ?>"></i></span>
          <span><?= $e($title) ?></span>
        </div>
        <?php if ($data['values']): ?>
          <div class="st-card__body"><div id="<?= $cid ?>" class="ix-chart"></div></div>
        <?php else: ?>
          <div class="st-empty">
            <span class="st-ico st-soft-muted"><i class="fa-solid fa-chart-column"></i></span>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>

  <!-- Online now -->
  <?php foreach ([
      ['fa-users',             'st-soft-success', $L['sec_online'] ?? 'Online Users',               $onlineShown, $onlineHidden, $onlineChips],
      ['fa-clock-rotate-left', 'st-soft-primary', $L['sec_last24'] ?? 'Last 24 Hours Active Users', $dayShown,    $dayHidden,    $dayChips],
  ] as [$icon, $soft, $title, $shown, $hidden, $chips]): ?>
    <section class="st-card mb-3">
      <div class="st-card__head">
        <span class="st-ico <?= $soft ?>"><i class="fa-solid <?= $icon ?>"></i></span>
        <span><?= $e($title) ?></span>
        <div class="ms-auto ix-counts">
          <span class="st-badge st-soft-success"><i class="fa-solid fa-user-check" aria-hidden="true"></i><?= $e(ags_fmt($L['lbl_visible'] ?? 'Visible Members: {1}', ts_nf($shown))) ?></span>
          <span class="st-badge st-soft-muted"><i class="fa-solid fa-user-secret" aria-hidden="true"></i><?= $e(ags_fmt($L['lbl_hidden'] ?? 'Hidden Members: {1}', ts_nf($hidden))) ?></span>
        </div>
      </div>
      <div class="st-card__body">
        <?php if ($chips): ?>
          <div class="ix-users"><?= implode('', $chips) ?></div>
        <?php else: ?>
          <div class="st-empty py-2"><span class="st-ico st-soft-muted"><i class="fa-solid fa-user-slash"></i></span></div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

</div>

<?php if ($needSeed): ?>
<div class="modal fade ix-modal" id="torrentModal" tabindex="-1" aria-labelledby="torrentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="torrentModalLabel"><i class="fa-solid fa-circle-info me-2"></i><?= $e($L['pane_torrent_details'] ?? 'Torrent Details') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $e($L['aria_close'] ?? 'Close') ?>"></button>
      </div>
      <div class="modal-body" id="torrentModalContent">
        <div class="text-center text-body-secondary py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="mt-2 mb-0"><?= $e($L['msg_loading_details'] ?? 'Loading torrent details...') ?></p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>const AGS_LANG = <?= json_encode($agsJsLang, $jsonFlags) ?>;</script>
<script type="application/json" id="ix-chart-data"><?= json_encode(['popular' => $chartPopular, 'active' => $chartActive], $jsonFlags) ?></script>
<?php if ($chartPopular['values'] || $chartActive['values']): ?>
<script src="<?= $BASEURL ?>/scripts/highcharts.js"></script>
<script src="<?= $BASEURL ?>/scripts/exporting.js"></script>
<script src="<?= $BASEURL ?>/scripts/accessibility.js"></script>
<?php endif; ?>
<script src="<?= $BASEURL ?>/scripts/index.js?ver=1"></script>
<?php
stdfoot();
