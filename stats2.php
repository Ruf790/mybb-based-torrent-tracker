<?php
require_once 'global.php';
gzip();

maxsysop();
$lang->load('stats2');
define('S_VERSION', '0.4');

function mysqli_result($result, $iRow, $field = 0)
{
    if (!mysqli_data_seek($result, $iRow)) return false;
    if (!($row = mysqli_fetch_array($result))) return false;
    if (!array_key_exists($field, $row)) return false;
    return $row[$field];
}

$query = $db->sql_query('SELECT COUNT(id) as totaltorrents, SUM(times_completed) as totalcompleted FROM torrents');
$totaltorrents = ts_nf($db->fetch_field($query, "totaltorrents"));
$totalcompleted = ts_nf($db->fetch_field($query, "totalcompleted"));

$query = $db->sql_query('SELECT COUNT(id) as totaldeadtorrents FROM torrents WHERE visible = \'no\' OR (leechers=0 AND seeders=0)');
$totaldeadtorrents = ts_nf(mysqli_result($query, 0, 'totaldeadtorrents'));

$query = $db->sql_query('SELECT COUNT(id) as totalextorrents FROM torrents WHERE ts_external=\'yes\'');
$totalextorrents = ts_nf($db->fetch_field($query, 'totalextorrents'));

$totalinternaltorrents = $totaltorrents - $totalextorrents;

$query = $db->sql_query('SELECT COUNT(id) as totalratiounder1 FROM users WHERE uploaded / downloaded < 1.0');
$totalratiounder1 = ts_nf(mysqli_result($query, 0, 'totalratiounder1'));

include_once INC_PATH . '/functions_ratio.php';
$yourratio = get_user_ratio($CURUSER['uploaded'], $CURUSER['downloaded']);

$query = $db->sql_query('SELECT COUNT(id) as yourtorrentratio FROM snatched WHERE uploaded / downloaded < 1.0 AND userid = ' . $CURUSER['id']);
$yourtorrentratio = ts_nf(mysqli_result($query, 0, 'yourtorrentratio'));

$query = $db->sql_query('SELECT count(id) as totalseeders FROM peers WHERE seeder = \'yes\'');
$totalseeders = ts_nf($db->fetch_field($query, "totalseeders"));

$query = $db->sql_query('SELECT count(id) as totalleechers FROM peers WHERE seeder = \'no\'');
$totalleechers = ts_nf(mysqli_result($query, 0, 'totalleechers'));

$query = $db->sql_query('SELECT SUM(downloaded) AS totaldl, SUM(uploaded) AS totalul FROM users');
$row = mysqli_fetch_assoc($query);
$totaldownloaded = mksize($row['totaldl']);
$totaluploaded = mksize($row['totalul']);

$ts_e_query = $db->sql_query('SELECT SUM(leechers) as leechers, SUM(seeders) as seeders FROM torrents WHERE ts_external = \'yes\'');
$ts_e_query_r = mysqli_fetch_row($ts_e_query);

$leechers = ts_nf($ts_e_query_r[0]);
$seeders = ts_nf($ts_e_query_r[1]);





// 1. Top 5 Uploaders by Uploaded Data
$query = $db->sql_query("
    SELECT username, uploaded 
    FROM users 
    ORDER BY uploaded DESC 
    LIMIT 5
");
$topUploaders = [];
while ($row = $db->fetch_array($query)) {
    $topUploaders[] = ['username' => $row['username'], 'uploaded' => $row['uploaded']];
}

// 2. Top 5 Downloaders by Downloaded Data
$query = $db->sql_query("
    SELECT username, downloaded 
    FROM users 
    ORDER BY downloaded DESC 
    LIMIT 5
");
$topDownloaders = [];
while ($row = $db->fetch_array($query)) {
    $topDownloaders[] = ['username' => $row['username'], 'downloaded' => $row['downloaded']];
}

// 3. Average User Ratio
$query = $db->sql_query("
    SELECT AVG(CASE WHEN downloaded > 0 THEN uploaded / downloaded ELSE NULL END) as avg_ratio
    FROM users
    WHERE downloaded > 0
");
$avgRatio = $db->fetch_array($query)['avg_ratio'];
$avgRatio = round(floatval($avgRatio), 2);

// 4. Total Registered Users
$query = $db->sql_query("SELECT COUNT(id) as totalusers FROM users");
$totalusers = ts_nf(mysqli_result($query, 0, 'totalusers'));

// 5. Torrents Uploaded in Last 30 Days
$query = $db->sql_query("
    SELECT COUNT(id) as recenttorrents 
    FROM torrents 
    WHERE added >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$recentTorrents = ts_nf(mysqli_result($query, 0, 'recenttorrents'));

// 6. Torrents by Category (Assuming 'category' column exists)
//$query = $db->sql_query("
   // SELECT category, COUNT(id) as count 
   // FROM torrents 
   // GROUP BY category
//");
//$categoryStats = [];
//while ($row = $db->fetch_array($query)) {
//   $categoryStats[] = ['category' => $row['category'], 'count' => (int)$row['count']];
//}


//6. Torrents by Category (Assuming 'category' column exists)
$query = $db->sql_query("
    SELECT c.name AS category_name, COUNT(t.id) AS count
    FROM torrents t
    LEFT JOIN categories c ON t.category = c.id
    GROUP BY c.id, c.name
    ORDER BY count DESC
");

$categoryStats = [];
while ($row = $db->fetch_array($query)) {
    $categoryStats[] = [
        'category' => $row['category_name'] ?? 'Unknown',
        'count' => (int)$row['count']
    ];
}






stdhead($lang->stats['head']);
?>

<div class="container mt-3">
  <div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold"><?= $lang->stats2['head'] ?></div>
    <div class="card-body">

      <div id="torrent-stats" style="height: 400px; margin-bottom: 30px;"></div>
      <div id="peer-stats" style="height: 400px; margin-bottom: 30px;"></div>
      <div id="ratio-stats" style="height: 400px;"></div>
	  
	  
	  
	  <div id="user-uploaders" style="height: 400px; margin-bottom: 30px;"></div>
<div id="user-downloaders" style="height: 400px; margin-bottom: 30px;"></div>
<div id="misc-stats" style="height: 300px; margin-bottom: 30px;"></div>
<div id="category-stats" style="height: 400px;"></div>
	  
	  
	  
	  
	  
	  

    </div>
  </div>
</div>



<script type="text/javascript" src="<?php echo $BASEURL; ?>/scripts/highcharts.js"></script>





<script>
  const totaltorrents = <?= json_encode((int)$totaltorrents) ?>;
  const totalinternaltorrents = <?= json_encode((int)$totalinternaltorrents) ?>;
  const totalextorrents = <?= json_encode((int)$totalextorrents) ?>;
  const totaldeadtorrents = <?= json_encode((int)$totaldeadtorrents) ?>;

  const totalseeders = <?= json_encode((int)$totalseeders) ?>;
  const totalleechers = <?= json_encode((int)$totalleechers) ?>;
  const seedersExternal = <?= json_encode((int)$seeders) ?>;
  const leechersExternal = <?= json_encode((int)$leechers) ?>;

  const totalratiounder1 = <?= json_encode((int)$totalratiounder1) ?>;
  const yourratio = <?= json_encode(floatval($yourratio)) ?>;
  const yourtorrentratio = <?= json_encode((int)$yourtorrentratio) ?>;

  Highcharts.chart('torrent-stats', {
    chart: { type: 'column' },
    title: { text: 'Torrent Counts' },
    xAxis: { categories: ['All Torrents', 'Internal', 'External', 'Dead Torrents'] },
    yAxis: {
      min: 0,
      title: { text: 'Number of Torrents' }
    },
    series: [{
      name: 'Torrents',
      data: [totaltorrents, totalinternaltorrents, totalextorrents, totaldeadtorrents],
      colorByPoint: true
    }]
  });

  Highcharts.chart('peer-stats', {
    chart: { type: 'bar' },
    title: { text: 'Peers Statistics' },
    xAxis: {
      categories: ['Seeders (Internal)', 'Leechers (Internal)', 'Seeders (External)', 'Leechers (External)'],
      title: { text: null }
    },
    yAxis: {
      min: 0,
      title: { text: 'Number of Peers' }
    },
    series: [{
      name: 'Peers',
      data: [totalseeders, totalleechers, seedersExternal, leechersExternal],
      colorByPoint: true
    }]
  });

  Highcharts.chart('ratio-stats', {
    chart: { type: 'pie' },
    title: { text: 'User Ratio Stats' },
    tooltip: { pointFormat: '{series.name}: <b>{point.y}</b>' },
    accessibility: { point: { valueSuffix: '%' } },
    plotOptions: {
      pie: {
        allowPointSelect: true,
        cursor: 'pointer',
        dataLabels: { enabled: true, format: '<b>{point.name}</b>: {point.y}' }
      }
    },
    series: [{
      name: 'Users',
      colorByPoint: true,
      data: [
        { name: 'Users with ratio < 1.0', y: totalratiounder1 },
        { name: 'Your Torrent Ratio < 1.0', y: yourtorrentratio },
        { name: 'Your Ratio', y: yourratio }
      ]
    }]
  });
  
  
 // Top 5 Uploaders chart
Highcharts.chart('user-uploaders', {
    chart: { type: 'bar' },
    title: { text: 'Top 5 Uploaders' },
    xAxis: {
        categories: <?= json_encode(array_column($topUploaders, 'username')) ?>,
        title: { text: 'Usernames' }
    },
    yAxis: {
        title: { text: 'Uploaded Data (GB)' }
    },
    series: [{
        name: 'Uploaded',
        data: <?= json_encode(array_map(function($u) {
    return round($u['uploaded'] / 1073741824, 2);
}, $topUploaders)) ?>,
        color: '#28a745'
    }]
});

// Top 5 Downloaders chart
Highcharts.chart('user-downloaders', {
    chart: { type: 'bar' },
    title: { text: 'Top 5 Downloaders' },
    xAxis: {
        categories: <?= json_encode(array_column($topDownloaders, 'username')) ?>,
        title: { text: 'Usernames' }
    },
    yAxis: {
        title: { text: 'Downloaded Data (GB)' }
    },
    series: [{
        name: 'Downloaded',
        data: <?= json_encode(array_map(function($d) {
    return round($d['downloaded'] / 1073741824, 2);
}, $topDownloaders)) ?>,
        color: '#dc3545'
    }]
});

// Misc Stats (Average ratio and total users, recent torrents)
Highcharts.chart('misc-stats', {
    chart: { type: 'column' },
    title: { text: 'Miscellaneous Stats' },
    xAxis: {
        categories: ['Average User Ratio', 'Total Users', 'Torrents Last 30 Days']
    },
    yAxis: {
        min: 0,
        title: { text: 'Value' }
    },
    series: [{
        name: 'Stats',
        data: [<?= $avgRatio ?>, <?= (int)$totalusers ?>, <?= (int)$recentTorrents ?>],
        colorByPoint: true
    }]
});

// Torrents by Category Pie chart
Highcharts.chart('category-stats', {
    chart: { type: 'pie' },
    title: { text: 'Torrents by Category' },
    tooltip: { pointFormat: '{series.name}: <b>{point.y}</b>' },
    accessibility: { point: { valueSuffix: '%' } },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: { enabled: true, format: '<b>{point.name}</b>: {point.y}' }
        }
    },
    series: [{
        name: 'Torrents',
        colorByPoint: true,
        data: <?= json_encode(array_map(function($c){ return ['name' => $c['category'], 'y' => $c['count']]; }, $categoryStats)) ?>
    }]
});

  
  
  
  
  
  
  
</script>

<?php
stdfoot();
?>
