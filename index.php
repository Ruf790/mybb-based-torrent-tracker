<?php

declare(strict_types=1);

define("SCRIPTNAME", "index.php");
require_once 'global.php';
require_once INC_PATH . '/functions_icons.php';




if (!isset($CURUSER) || !$CURUSER) 
{
    header('Location: member.php?action=login');
    exit;
}

require_once INC_PATH . '/class_parser.php';
$parser = new postParser();

$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

stdhead('Dashboard with Online Users and News');

$is_mod = is_mod($usergroups);

////////// ONLINE USERS
$_dt2 = TIMENOW - $wolcutoffmins * 60;

$_wgo_query = $db->sql_query_prepared("
    SELECT s.uid as id, u.username, u.avatar, u.usergroup, u.enabled, u.invisible, 
           u.donor, u.leechwarn, u.warned, u.canupload, u.candownload, u.cancomment, u.lastactive
    FROM sessions s
    LEFT JOIN users u ON (s.uid = u.id)
    WHERE s.uid != '0' AND s.time > ?
    ORDER BY u.username, u.lastactive
", [$_dt2]);

$_usernames = [];
$_hidden_members = 0;
$_active_members = 0;

while ($_active_users = $db->fetch_array($_wgo_query)) {
    $is_hidden = ($_active_users['invisible'] == 1);
    
    if ($is_hidden && $_active_users['id'] != $CURUSER['id'] && !$is_mod) {
        $_hidden_members++;
        continue;
    }
    
    if ($is_hidden) {
        $_hidden_members++;
    } else {
        $_active_members++;
    }

    $username_html = '<a href="' . get_profile_link((int)$_active_users['id']) . '">' .
        format_name($_active_users['username'], $_active_users['usergroup']) . '</a>' .
        ($is_hidden ? ' <small class="text-muted">(+)</small>' : '') .
        ' ' . get_user_icons($_active_users);

    $_usernames[] = $username_html;
}

$_dt = TIMENOW - (24 * 60 * 60);

// Count guest sessions in the last 24 hours
$_qsquery = $db->sql_query_prepared(
    'SELECT 1 FROM sessions WHERE uid = 0 AND time > ?',
    [$_dt]
);
$_guests = ts_nf($db->num_rows($_qsquery));

$timeLimit = TIMENOW - (24 * 60 * 60);

// Users who logged in during the last 24 hours
$_wgo_query2 = $db->sql_query_prepared(
    'SELECT u.id, u.username, u.avatar, u.usergroup, u.enabled, u.invisible, 
            u.donor, u.leechwarn, u.warned, u.canupload, u.candownload, u.cancomment
     FROM users u 
     WHERE u.last_login > ?
     ORDER BY u.username, u.last_login',
    [$timeLimit]
);

$_most_ever = $db->num_rows($_wgo_query2) + (int)$_guests;

if (file_exists(TSDIR . '/cache/onlinestats.php')) {
    include_once TSDIR . '/cache/onlinestats.php';
}

$onlinestats['most_ever'] ??= 0;

$_hidden_members2 = $_active_members2 = 0;
$_usernames2 = [];

while ($_user = $db->fetch_array($_wgo_query2)) {
    if (!is_array($_user)) {
        continue;
    }
    
    $is_hidden2 = ($_user['invisible'] == 1);
    
    if ($is_hidden2 && isset($_user['id']) && $_user['id'] != $CURUSER['id'] && !$is_mod) {
        $_hidden_members2++;
        continue;
    }

    if ($is_hidden2) {
        $_hidden_members2++;
    } else {
        $_active_members2++;
    }

    $_usernames2[] = '<span style="white-space: nowrap;"><a href="' . get_profile_link((int)$_user['id']) . '">' . 
        format_name($_user['username'], $_user['usergroup']) . '</a>' . 
        ($is_hidden2 ? '+' : '') . get_user_icons($_user) . '</span>';
}

// Fetch the latest 5 news articles
$newsArticles = $cache->read('news');

// Query to get most popular torrents by hits
$sqlPopularTorrents = "SELECT name, hits FROM torrents WHERE visible = 'yes' AND banned = 'no' ORDER BY hits DESC LIMIT ?";
$resultPopularTorrents = $db->sql_query_prepared($sqlPopularTorrents, [10]);

$popularTorrentNames = [];
$popularTorrentHits = [];

while ($row = $db->fetch_array($resultPopularTorrents)) {
    $popularTorrentNames[] = $row['name'];
    $popularTorrentHits[] = (int)$row['hits'];
}

// Query to get most active torrents by completed times
$sqlActiveTorrents = "SELECT name, times_completed FROM torrents WHERE visible = 'yes' AND banned = 'no' ORDER BY times_completed DESC LIMIT ?";
$resultActiveTorrents = $db->sql_query_prepared($sqlActiveTorrents, [10]);

$activeTorrentNames = [];
$activeTorrentCompletions = [];

while ($row = $db->fetch_array($resultActiveTorrents)) {
    $activeTorrentNames[] = $row['name'];
    $activeTorrentCompletions[] = (int)$row['times_completed'];
}

// Query last 6 torrents visible and not banned
$sql = "SELECT id, name, descr, t_image, added FROM torrents WHERE visible = 'yes' AND banned = 'no' AND t_image != '' ORDER BY added DESC LIMIT ?";
$result = $db->sql_query_prepared($sql, [6]);

$torrents = [];
while ($row = $db->fetch_array($result)) {
    $torrents[] = $row;
}

// BEGIN Plugin: seedersneeded
$seedersneeded = '';

$Query = $db->sql_query_prepared("
    SELECT t.id, t.name, t.seeders, t.leechers, t.size, t.t_image, c.name AS category_name 
    FROM torrents t 
    LEFT JOIN categories c ON t.category = c.id 
    WHERE t.leechers > 0 AND t.seeders = 0 
    ORDER BY t.added DESC 
    LIMIT ?", [200]);

if ($db->num_rows($Query) > 0) {
    $seedersneeded .= '
    <style>
      .torrent-thumb { transition: transform 0.3s ease; }
      .torrent-thumb:hover { transform: scale(1.5); z-index: 10; position: relative; }
      .card.seedersneeded-card { border: 1px solid #dc3545; background-color: #fff5f5; }
      .card.seedersneeded-card .card-header { background-color: #dc3545; color: #fff; font-weight: 600; }
      .seedersneeded-card table thead { background-color: #f8d7da; color: #721c24; }
      .badge.seeders-badge { background-color: #dc3545; color: #fff; }
      .badge.seeders-badge-light { background-color: #f5c6cb; color: #721c24; border: 1px solid #dc3545; }
      .modal.seedersneeded-modal .modal-header { background-color: #dc3545; color: white; border-bottom: none; }
      .modal.seedersneeded-modal .modal-content { border-radius: 0.375rem; box-shadow: 0 0.5rem 1rem rgba(220, 53, 69, 0.25); }
      a.torrent-preview { color: #b02a37; }
      a.torrent-preview:hover { color: #dc3545; text-decoration: underline; }
    </style>

    <div class="card mt-3 shadow-sm seedersneeded-card">
      <div class="card-header d-flex align-items-center">
        <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
        <span class="fs-6 fw-semibold">Recently Uploaded Torrents Needing Seeders</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><i class="fa-solid fa-film me-1"></i> Torrent</th>
                <th scope="col" class="text-center"><i class="fa-solid fa-arrow-up"></i> Seeders</th>
                <th scope="col" class="text-center"><i class="fa-solid fa-arrow-down"></i> Leechers</th>
              </tr>
            </thead>
            <tbody>';

    while ($Torrent = $db->fetch_array($Query)) {
        $torrentId = (int)$Torrent['id'];
        $torrentName = cutename($Torrent['name']);
        $torrentSize = mksize($Torrent['size']);
        $category = htmlspecialchars($Torrent['category_name'] ?? '');
        $poster = $Torrent['t_image'] && file_exists($Torrent['t_image']) ? $Torrent['t_image'] : 'assets/img/no-poster.png';

        $seedersneeded .= '
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="' . htmlspecialchars($Torrent['t_image']) . '" alt="Poster" class="me-2 rounded torrent-thumb" style="width: 40px; height: 60px; object-fit: cover;">
                    <div>
                      <a href="#" class="torrent-preview text-decoration-none fw-semibold" data-id="' . $torrentId . '" data-bs-toggle="modal" data-bs-target="#torrentModal">
                        ' . $torrentName . '
                      </a>
                      <div class="mt-1">
                        <span class="badge seeders-badge-light me-1">
                          <i class="fa-solid fa-database me-1"></i>' . $torrentSize . '
                        </span>
                        <span class="badge seeders-badge-light">
                          <i class="fa-solid fa-tag me-1"></i>' . $category . '
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="text-center">
                  <span class="badge seeders-badge">
                    <i class="fa-solid fa-arrow-up me-1"></i> ' . $Torrent['seeders'] . '
                  </span>
                </td>
                <td class="text-center">
                  <span class="badge bg-danger text-white">
                    <i class="fa-solid fa-arrow-down me-1"></i> ' . ts_nf($Torrent['leechers']) . '
                  </span>
                </td>
              </tr>';
    }

    $seedersneeded .= '
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal fade seedersneeded-modal" id="torrentModal" tabindex="-1" aria-labelledby="torrentModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
          <div class="modal-header">
            <h5 class="modal-title" id="torrentModalLabel"><i class="fa-solid fa-circle-info me-1"></i> Torrent Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="torrentModalContent">
            <div class="text-center text-muted">
              <div class="spinner-border text-danger" role="status"></div>
              <p class="mt-2">Loading torrent details...</p>
            </div>
          </div>
        </div>
      </div>
    </div>';
} else {
    $seedersneeded .= '
    <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">
                <i class="fas fa-check-circle me-2"></i>All Torrents Have Seeders
            </div>
            <div class="card-body text-center text-success">
                <i class="fas fa-smile-beam fa-2x mb-2"></i>
                <p class="mb-0">Great job! All torrents are currently seeded.</p>
            </div>
        </div>';
}
// END Plugin: seedersneeded
?>
<!-- Highcharts.js -->
<script type="text/javascript" src="<?= $BASEURL ?>/scripts/highcharts.js"></script>
<script type="text/javascript" src="<?= $BASEURL ?>/scripts/exporting.js"></script>
<script type="text/javascript" src="<?= $BASEURL ?>/scripts/accessibility.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".torrent-preview").forEach(el => {
    el.addEventListener("click", function (e) {
      e.preventDefault();
      const torrentId = this.dataset.id;
      const modalContent = document.getElementById("torrentModalContent");
      modalContent.innerHTML = `
        <div class="text-center text-muted">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="mt-2">Loading torrent details...</p>
        </div>`;
      fetch("torrent_preview.php?id=" + torrentId)
        .then(response => response.text())
        .then(html => {
          modalContent.innerHTML = html;
        })
        .catch(err => {
          modalContent.innerHTML = "<p class='text-danger'>Failed to load torrent preview.</p>";
        });
    });
  });
});
</script>

<style>
  .card { transition: transform 0.3s ease-in-out; }
  .card:hover { transform: translateY(-5px); box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); }
  .card-body { text-align: center; }
  .table th, .table td { vertical-align: middle; }
  .table-striped tbody tr:nth-child(odd) { background-color: #f9f9f9; }
  .table-striped tbody tr:hover { background-color: #f1f1f1; }
  body { font-family: Arial; }
  h2 { text-align: center; }
  .chart-container { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
  .chart-box { width: 45%; min-width: 400px; height: 400px; }
  .user-list .user-card { background: #fff; border-radius: 0.5rem; padding: 0.75rem 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: 0.2s; white-space: nowrap; }
  .user-list .user-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
  .torrent-card { transition: transform 0.3s ease, box-shadow 0.3s ease; cursor: pointer; background: #fff; }
  .torrent-card:hover { transform: translateY(-8px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
  .img-container { max-width: 100%; max-height: 300px; overflow: auto; }
  .torrent-img { transition: transform 0.4s ease; object-fit: cover; height: 100%; width: 100%; }
  .img-container:hover .torrent-img { transform: scale(1.1); }
  .overlay { background: rgba(0,0,0,0.55); opacity: 0; transition: opacity 0.3s ease; font-size: 0.9rem; }
  .img-container:hover .overlay { opacity: 1; }
  .torrent-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .torrent-desc { height: 3.6em; overflow: hidden; }
  .badge { margin-right: 6px; font-size: 0.75rem; cursor: help; }
  .user-card { transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default; }
  .user-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
</style>

<div class="container py-4">
  <!-- News Block -->
  <div class="row mb-5">
    <div class="col-md-12">
      <div class="card shadow-sm">
        <div class="card-header">
          <h4 class="mb-0"><i class="fa-solid fa-newspaper me-2"></i>Latest News</h4>
        </div>
        <div class="card-body p-3">
          <?php if (!empty($newsArticles)): ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($newsArticles as $news): ?>
                <li class="news-item border-bottom pb-3 mb-3">
                  <h5 class="mb-1"><?= htmlspecialchars($news['title'] ?? '') ?></h5>
                  <small class="text-muted d-block mb-2">
                    Posted by <strong><?= htmlspecialchars($news['username'] ?? 'System') ?></strong> on 
                    <?= my_datee($dateformat, $news['added'] ?? 0) . my_datee($timeformat, $news['added'] ?? 0) ?>
                  </small>
                  <div class="news-body" style="line-height: 1.5;">
                    <?= $parser->parse_message($news['body'] ?? '', $parser_options) ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-center text-muted">No news found.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?= $seedersneeded ?>
  <br>





<!-- Latest Torrents Section -->
<div class="row mb-5">
  <div class="col-md-12">
   <h3 class="mb-4"><i class="fa-solid fa-cloud-download-alt me-2"></i>Latest Torrents</h3>
    <div class="d-flex flex-wrap gap-4 justify-content-start">

      <?php foreach ($torrents as $torrent): ?>
        <div class="torrent-card position-relative border rounded shadow-sm" style="width: 18rem; overflow: hidden;">
		
		
		

          <!-- Torrent Image -->
          <?php 
		  $seolink = get_torrent_link($torrent['id']);
		  $descrr = $parser->parse_message($torrent['descr'],$parser_options);
		  
         
          ?>
          <div>
            <img src="<?= htmlspecialchars_uni($torrent['t_image']) ?>" class="img-fluid w-100 torrent-img" />
            <div class="overlay position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center text-white p-3">
              <div>
               
				
				<i class="bi bi-hdd me-1"></i> <?= mksize($torrent['size'] ?? 0) ?>
				
              </div>
              <div>
                
				
				
				
			   <i class="bi bi-arrow-up-circle-fill text-success me-1"></i> <?= (int)($torrent['seeders'] ?? 0) ?>
               <i class="bi bi-arrow-down-circle-fill text-danger ms-3 me-1"></i> <?= (int)($torrent['leechers'] ?? 0) ?>

				
				
				
				
              </div>
            </div>
          </div>

          <!-- Torrent Body -->
          <div class="p-3">
            <h5 class="torrent-title text-truncate" title="<?= htmlspecialchars_uni($torrent['name']) ?>">
              <a href="<?= $seolink ?>" class="text-decoration-none stretched-link">
                <?= htmlspecialchars_uni($torrent['name']) ?>
              </a>
            </h5>	
			

            

            <!-- Badges -->
           <?php if (($torrent['free'] ?? '') === 'yes'): ?>
  <span class="badge bg-success" title="Free Torrent">Free</span>
<?php endif; ?>
<?php if (($torrent['silver'] ?? '') === 'yes'): ?>
  <span class="badge bg-secondary" title="Silver Torrent">Silver</span>
<?php endif; ?>
<?php if (($torrent['doubleupload'] ?? '') === 'yes'): ?>
  <span class="badge bg-warning text-dark" title="Double Upload">2x Upload</span>
<?php endif; ?>
			
		
			
			

            
			
			<small class="text-muted d-block">Added: <?= date('Y-m-d', (int)$torrent['added'] ?? time()) ?></small>

			
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </div>
</div>




  
  

  <!-- Charts Section -->
  <div class="row mb-5">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Most Popular Torrents</div>
        <div class="card-body">
          <div id="popularTorrentsChart" style="height: 400px;"></div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Most Active Torrents</div>
        <div class="card-body">
          <div id="activeTorrentsChart" style="height: 400px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Online Users Section -->
  <section class="mb-5">
    <h2 class="mb-4 text-primary fw-bold"><i class="fa-solid fa-users me-2"></i>Online Users</h2>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
      <span class="badge bg-success fs-6"><i class="fa-solid fa-user-check me-1"></i> Visible Members: <?= $_active_members ?></span>
      <span class="badge bg-secondary fs-6"><i class="fa-solid fa-user-secret me-1"></i> Hidden Members: <?= $_hidden_members ?></span>
      <span class="badge bg-info text-dark fs-6"><i class="fa-solid fa-users-line me-1"></i> Total Online: <?= $_active_members + $_hidden_members ?></span>
    </div>
    <div class="user-list d-flex flex-wrap gap-3">
      <?php foreach ($_usernames as $user_html): ?>
        <div class="user-card border rounded p-3 shadow-sm" style="min-width: 120px; max-width: 180px;"><?= $user_html ?></div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Last 24 Hours Active Users Section -->
  <section>
    <h2 class="mb-4 text-primary fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Last 24 Hours Active Users</h2>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
      <span class="badge bg-success fs-6"><i class="fa-solid fa-user-check me-1"></i> Visible Members: <?= $_active_members2 ?></span>
      <span class="badge bg-secondary fs-6"><i class="fa-solid fa-user-secret me-1"></i> Hidden Members: <?= $_hidden_members2 ?></span>
      <span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-users me-1"></i> Guests: <?= $_guests ?></span>
      <span class="badge bg-info text-dark fs-6"><i class="fa-solid fa-users-line me-1"></i> Total Users: <?= (int)$_active_members2 + (int)$_hidden_members2 + (int)$_guests ?></span>
    </div>
    <div class="user-list d-flex flex-wrap gap-3">
      <?php foreach ($_usernames2 as $user_html2): ?>
        <div class="user-card border rounded p-3 shadow-sm" style="min-width: 120px; max-width: 180px;"><?= $user_html2 ?></div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<script>
Highcharts.chart('popularTorrentsChart', {
  chart: { type: 'column' },
  title: { text: 'Most Popular Torrents' },
  xAxis: { 
    categories: <?= json_encode($popularTorrentNames) ?>,
    crosshair: true,
    title: { text: 'Torrent' }
  },
  yAxis: {
    min: 0,
    title: { text: 'Hits' }
  },
  series: [{
    name: 'Hits',
    data: <?= json_encode($popularTorrentHits) ?>,
    color: 'rgba(220, 53, 69, 0.7)'
  }],
  tooltip: { valueSuffix: ' hits' }
});

Highcharts.chart('activeTorrentsChart', {
  chart: { type: 'column' },
  title: { text: 'Most Active Torrents' },
  xAxis: { 
    categories: <?= json_encode($activeTorrentNames) ?>,
    crosshair: true,
    title: { text: 'Torrent' }
  },
  yAxis: {
    min: 0,
    title: { text: 'Completed Times' }
  },
  series: [{
    name: 'Completed',
    data: <?= json_encode($activeTorrentCompletions) ?>,
    color: 'rgba(23, 162, 184, 0.7)'
  }],
  tooltip: { valueSuffix: ' completions' }
});
</script>

<?php stdfoot(); ?>