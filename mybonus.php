<?php
declare(strict_types=1);

define('IN_MYBB', 1);

require_once 'global.php';
require_once INC_PATH . '/functions_pm.php';
require_once INC_PATH . '/datahandler.php';

// ── Авторизация ───────────────────────────────────────────
if (!$CURUSER || ($CURUSER['id'] ?? 0) == 0) {
    print_no_permission();
}

$lang->load('mybonus');

// ── Загрузка настроек из БД ───────────────────────────────
function loadSeedbonusSettings(): array
{
    global $db;

    $res = $db->sql_query('SELECT setting_key, setting_value, setting_type FROM seedbonus_settings');
    $cfg = [];

    while ($row = $db->fetch_array($res)) {
        $cfg[$row['setting_key']] = match($row['setting_type']) {
            'boolean' => in_array($row['setting_value'], ['yes','true','1','on'], true),
            'integer' => (int)$row['setting_value'],
            'float'   => (float)$row['setting_value'],
            'array'   => json_decode($row['setting_value'], true) ?? [],
            default   => (string)$row['setting_value'],
        };
    }
    return $cfg;
}

$cfg = loadSeedbonusSettings();

// Короткие алиасы для часто используемых значений
$BASE_BONUS            = (float)($cfg['base_bonus']            ?? 10.0);
$HOUR_CAP              = (float)($cfg['hour_cap']              ?? 500.0);
$CRON_INTERVAL_SEC     = (int)($cfg['cron_interval']           ?? 15) * 60;
$CRON_INTERVAL_HOURS   = $CRON_INTERVAL_SEC / 3600;
$TORRENT_MUL_TYPE      = (string)($cfg['torrent_multiplier_type'] ?? 'penalty');
$FLAT_MULTIPLIER       = (float)($cfg['flat_multiplier']       ?? 1.0);
$ENABLE_HEURISTIC      = (bool)($cfg['enable_heuristic']       ?? false);

$userid = (int)$CURUSER['id'];
$points = (int)($CURUSER['seedbonus'] ?? 0);
$errors = [];
$messages = [];

// Показываем тост после редиректа (PRG паттерн)
if (isset($_GET['purchased'])) {
    $messages[] = $lang->mybonus['message1_success'] ?? 'Purchase successful!';
}

// ── Вспомогательные функции ───────────────────────────────

function getTorrentMultiplier(int $count, string $type, float $flat = 1.0): float
{
    return match($type) {
        'penalty' => match(true) {
            $count <= 20  => 1.0,
            $count <= 50  => 0.9,
            $count <= 100 => 0.8,
            default       => 0.7,
        },
        'neutral' => $count <= 100 ? 1.0 : 0.9,
        'reward'  => match(true) {
            $count >= 100 => 1.2,
            $count >= 50  => 1.1,
            $count >= 20  => 1.0,
            default       => 0.9,
        },
        'flat'    => $flat,
        default   => 1.0,
    };
}

function getHeuristicHours(int $count, array $cfg): float
{
    return match(true) {
        $count >= 50 => (float)($cfg['heuristic_50'] ?? 24),
        $count >= 40 => (float)($cfg['heuristic_40'] ?? 20),
        $count >= 30 => (float)($cfg['heuristic_30'] ?? 16),
        $count >= 20 => (float)($cfg['heuristic_20'] ?? 12),
        $count >= 10 => (float)($cfg['heuristic_10'] ?? 8),
        $count >= 5  => (float)($cfg['heuristic_5']  ?? 4),
        default      => (float)($cfg['heuristic_1']  ?? 2),
    };
}

function progressColor(float $pct): string
{
    return match(true) {
        $pct >= 80 => 'danger',
        $pct >= 50 => 'warning',
        default    => 'success',
    };
}

function logBonus(int $uid, array $b): void
{
    global $db;
    $comment = date('Y-m-d H:i:s') . ' — ' . $b['bonusname'] . ' (-' . (int)$b['points'] . " pts)\n";
    $db->sql_query_prepared(
        "UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment,''), ?) WHERE id = ?",
        [$comment, $uid]
    );
}

function purchase(int $uid, string $field, array $b, bool &$used): void
{
    global $db;
    // $field валидируется в вызывающем коде через whitelist
    $db->sql_query_prepared(
        "UPDATE users SET {$field}, seedbonus = seedbonus - ? WHERE id = ?",
        [(int)$b['points'], $uid]
    );
    if ($db->affected_rows()) {
        logBonus($uid, $b);
        $used = true;
    }
}

// ── Обработчики покупок ───────────────────────────────────

function handleTitle(int $uid, array $b, bool &$used): void
{
    global $db, $errors;
    $title = trim($_POST['title'] ?? '');
    if (strlen($title) < 2) { $errors[] = 'Title too short!'; return; }
    $db->sql_query_prepared(
        'UPDATE users SET title = ?, seedbonus = seedbonus - ? WHERE id = ?',
        [htmlspecialchars_uni($title), (int)$b['points'], $uid]
    );
    if ($db->affected_rows()) { logBonus($uid, $b); $used = true; }
}

function handleGift(int $uid, array $b, bool &$used): void
{
    global $db, $errors, $CURUSER, $BASEURL, $points, $lang;

    $gift = (int)($_POST['gift'] ?? 0);
    $to   = trim($_POST['username'] ?? '');

    if ($gift < 1)                        { $errors[] = 'Invalid gift amount!';    return; }
    if ($to === $CURUSER['username'])      { $errors[] = 'Cannot gift to yourself!'; return; }

    $res    = $db->simple_select('users', 'id, seedbonus, username', 'username = ' . $db->sqlesc($to));
    $target = $db->fetch_array($res);
    if (!$target) { $errors[] = 'User not found!'; return; }

    $total = (int)$b['points'] + $gift;
    if ($points < $total) { $errors[] = "Not enough points! Need {$total}, have {$points}"; return; }

    $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?', [$gift, (int)$target['id']]);
    $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?', [$total, $uid]);

    if ($db->affected_rows()) {
        logBonus($uid, $b);
        $used = true;

        $db->sql_query_prepared(
            "UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment,''), ?) WHERE id = ?",
            ["Gift: {$gift} pts from {$CURUSER['username']}\n", (int)$target['id']]
        );

        $profilelink = $BASEURL . '/' . get_profile_link($uid);
        send_pm([
            'subject' => $lang->mybonus['giftsubject'],
            'message' => sprintf(
                $lang->mybonus['giftmsg'],
                '[b]' . $target['username'] . '[/b]',
                '[URL=' . $profilelink . '][b]' . $CURUSER['username'] . '[/b][/URL]',
                $gift
            ),
            'touid'  => (int)$target['id'],
            'sender' => ['uid' => -1],
        ], -1, true);
    }
}

function handleRatioFix(int $uid, array $b, bool &$used): void
{
    global $db, $errors;
    $tid = (int)($_POST['torrentid'] ?? 0);
    if ($tid <= 0) { $errors[] = 'Invalid torrent ID!'; return; }

    $res   = $db->simple_select('snatched', 'uploaded', "torrentid = '{$tid}' AND userid = '{$uid}' AND finished = 'yes'");
    $snatch = $db->fetch_array($res);
    if (!$snatch) { $errors[] = 'Torrent not found!'; return; }

    $db->sql_query("UPDATE snatched SET uploaded = downloaded, seedtime = GREATEST(seedtime, 86400) WHERE torrentid = '{$tid}' AND userid = '{$uid}'");
    $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?', [(int)$b['points'], $uid]);
    if ($db->affected_rows()) { logBonus($uid, $b); $used = true; }
}

// ── Формы ─────────────────────────────────────────────────

function showForm(string $title, string $hiddenName, string $hiddenValue, string $body): never
{
    global $mybb;
    stdhead($title);
    echo <<<HTML
<div class="container py-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white"><h5 class="mb-0">{$title}</h5></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
                <input type="hidden" name="{$hiddenName}" value="{$hiddenValue}">
                {$body}
            </form>
        </div>
    </div>
</div>
HTML;
    stdfoot();
    exit;
}

function showTitleForm(array $b): never
{
    global $CURUSER;
    $currentTitle = htmlspecialchars_uni($CURUSER['title'] ?? '');
    showForm('Buy title', 'update_title', 'yes', <<<HTML
<input type="hidden" name="id" value="{$b['id']}">
<div class="mb-3">
    <label class="form-label fw-bold">New title:</label>
    <input type="text" name="title" class="form-control" value="{$currentTitle}" required>
</div>
<button type="submit" class="btn btn-success">Buy for {$b['points']} points</button>
<a href="mybonus.php" class="btn btn-secondary ms-2">Cancel</a>
HTML);
}

function showGiftForm(array $b): never
{
    global $points;
    $maxGift = $points - (int)$b['points'];
    showForm('Gift points', 'send_gift', 'yes', <<<HTML
<input type="hidden" name="id" value="{$b['id']}">
<div class="alert alert-info">
    You have <strong>{$points}</strong> pts &bull;
    Fee: <strong>{$b['points']}</strong> pts &bull;
    Max gift: <strong>{$maxGift}</strong> pts
</div>
<div class="mb-3">
    <label class="form-label">To (username):</label>
    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
</div>
<div class="mb-3">
    <label class="form-label">How many points:</label>
    <input type="number" name="gift" class="form-control" min="1" max="{$maxGift}" required>
</div>
<button type="submit" class="btn btn-success">Gift points</button>
<a href="mybonus.php" class="btn btn-secondary ms-2">Cancel</a>
HTML);
}

function showRatioFixForm(array $b): never
{
    showForm('Fix ratio', 'ratiofix', 'yes', <<<HTML
<input type="hidden" name="id" value="{$b['id']}">
<div class="mb-3">
    <label class="form-label">Torrent ID:</label>
    <input type="number" name="torrentid" class="form-control" required>
</div>
<button type="submit" class="btn btn-warning">Fix ratio for {$b['points']} points</button>
<a href="mybonus.php" class="btn btn-secondary ms-2">Cancel</a>
HTML);
}

// ── Обработка POST ────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $res   = $db->simple_select('bonus', '*', 'id = ' . $id);
    $bonus = $db->fetch_array($res);

    if (!$bonus) {
        $errors[] = $lang->mybonus['error1'] ?? 'Bonus not found';
    } elseif ($points < $bonus['points']) {
        $errors[] = sprintf($lang->mybonus['error2'] ?? 'Not enough points: %d of %d', $points, $bonus['points']);
    } else {
        $used = false;

        switch ($bonus['art']) {
            case 'traffic':
                purchase($userid, 'uploaded = uploaded + ' . (int)$bonus['menge'], $bonus, $used);
                break;
            case 'invite':
                purchase($userid, 'invites = invites + ' . (int)$bonus['menge'], $bonus, $used);
                break;
            case 'title':
                isset($_POST['update_title']) ? handleTitle($userid, $bonus, $used) : showTitleForm($bonus);
                break;
            case 'gift_1':
                isset($_POST['send_gift'])    ? handleGift($userid, $bonus, $used)     : showGiftForm($bonus);
                break;
            case 'warning':
                if (($CURUSER['timeswarned'] ?? 0) > 0) {
                    $menge = (int)$bonus['menge'];
                    purchase($userid, "timeswarned = IF(timeswarned >= {$menge}, timeswarned - {$menge}, 0)", $bonus, $used);
                }
                break;
            case 'ratiofix':
                isset($_POST['ratiofix'])     ? handleRatioFix($userid, $bonus, $used) : showRatioFixForm($bonus);
                break;
            default:
                $errors[] = 'Unknown bonus type';
        }

        if ($used && empty($errors)) {
            $messages[] = sprintf($lang->mybonus['message1'] ?? 'Purchased: %s', htmlspecialchars_uni($bonus['bonusname']));
            $points -= (int)$bonus['points'];
            $CURUSER['seedbonus'] = $points;
            // PRG паттерн — редирект чтобы повторный F5 не отправлял POST
            header('Location: mybonus.php?purchased=1');
            exit;
        }
    }
}

// ── Расчёт бонуса пользователя ────────────────────────────

function getUserStats(int $uid, array $cfg): array
{
    global $db;

    $leechNone   = (float)($cfg['leech_none']   ?? 1.2);
    $leechFew    = (float)($cfg['leech_few']    ?? 1.5);
    $leechMany   = (float)($cfg['leech_many']   ?? 1.8);
    $sizeSmall   = (float)($cfg['size_small']   ?? 1.0);
    $sizeMedium  = (float)($cfg['size_medium']  ?? 1.2);
    $sizeLarge   = (float)($cfg['size_large']   ?? 1.5);
    $sizeXlarge  = (float)($cfg['size_xlarge']  ?? 1.8);
    $sizeHuge    = (float)($cfg['size_huge']    ?? 2.0);
    $seedersMany = (float)($cfg['seeders_many'] ?? 0.9);
    $seedersMed  = (float)($cfg['seeders_medium']?? 0.95);
    $ageOld      = (float)($cfg['age_old']      ?? 1.5);
    $ageMed      = (float)($cfg['age_medium']   ?? 1.3);
    $promoFree   = (float)($cfg['promo_free']   ?? 0.7);
    $promoSilver = (float)($cfg['promo_silver'] ?? 0.5);
    $promoDouble = (float)($cfg['promo_double'] ?? 0.5);
    $cronHours   = (int)($cfg['cron_interval'] ?? 15) / 60;

    $sql = "
        SELECT
            COUNT(DISTINCT p.torrent) AS torrents_count,
            AVG(GREATEST(0.25, LEAST(
                (UNIX_TIMESTAMP() - GREATEST(p.last_action, UNIX_TIMESTAMP() - 2700)) / 3600,
                {$cronHours}
            ))) AS avg_hours_seeded,
            SUM(
                CASE WHEN t.leechers = 0 THEN {$leechNone}
                     WHEN t.leechers <= 2 THEN {$leechFew}
                     ELSE {$leechMany} END *
                CASE WHEN t.size < 536870912   THEN {$sizeSmall}
                     WHEN t.size < 2147483648  THEN {$sizeMedium}
                     WHEN t.size < 8589934592  THEN {$sizeLarge}
                     WHEN t.size < 21474836480 THEN {$sizeXlarge}
                     ELSE {$sizeHuge} END *
                CASE WHEN t.seeders > 100 THEN {$seedersMany}
                     WHEN t.seeders > 50  THEN {$seedersMed}
                     ELSE 1.0 END *
                CASE WHEN (UNIX_TIMESTAMP() - t.added) > 15552000 THEN {$ageOld}
                     WHEN (UNIX_TIMESTAMP() - t.added) > 5184000  THEN {$ageMed}
                     ELSE 1.0 END *
                (1.0 + (t.free = 'yes') * {$promoFree}
                     + (t.silver = 'yes') * {$promoSilver}
                     + (t.doubleupload = 'yes') * {$promoDouble})
            ) AS raw_bonus_sum
        FROM peers p
        INNER JOIN torrents t ON t.id = p.torrent
        WHERE p.seeder     = 'yes'
          AND p.userid     = '{$uid}'
          AND t.visible    = 'yes'
          AND t.banned     = 'no'
          AND t.isnuked    = 'no'
          AND p.last_action >= UNIX_TIMESTAMP() - 2700
        GROUP BY p.userid";

    $res  = $db->sql_query($sql);
    $row  = $db->fetch_array($res);
    return $row ?: [];
}

function calcUserBonus(array $stats, array $cfg): array
{
    if (empty($stats) || !($stats['torrents_count'] ?? 0)) {
        return array_fill_keys([
            'torrents','avgHours','rawBonus','capMul',
            'hourlyTheoretical','hourlyCapped','perRun',
            'realHourly','daily','capPct','isCapped',
        ], 0);
    }

    global $TORRENT_MUL_TYPE, $FLAT_MULTIPLIER, $ENABLE_HEURISTIC, $BASE_BONUS, $HOUR_CAP, $CRON_INTERVAL_HOURS;

    $torrents  = (int)$stats['torrents_count'];
    $avgHours  = (float)$stats['avg_hours_seeded'];
    $rawBonus  = (float)$stats['raw_bonus_sum'];
    $capMul    = getTorrentMultiplier($torrents, $TORRENT_MUL_TYPE, $FLAT_MULTIPLIER);
    $hourlyTh  = round($rawBonus * $BASE_BONUS * $capMul, 1);
    $hourlyCap = min($hourlyTh, $HOUR_CAP);

    if ($ENABLE_HEURISTIC) {
        $hHours   = getHeuristicHours($torrents, $cfg) * ($CRON_INTERVAL_HOURS / 24);
        $avgHours = max($avgHours, $hHours);
    }

    $perRun     = round($hourlyCap * $avgHours, 1);
    $realHourly = round($perRun * 4, 1);
    $daily      = round($realHourly * 24);
    $capPct     = min(($hourlyTh / $HOUR_CAP) * 100, 100);

    return compact('torrents','avgHours','rawBonus','capMul','hourlyTh',
                   'hourlyCap','perRun','realHourly','daily','capPct') + [
        'isCapped'           => $hourlyTh > $HOUR_CAP,
        'hourlyTheoretical'  => $hourlyTh,
        'hourlyCapped'       => $hourlyCap,
    ];
}

$userStats = getUserStats($userid, $cfg);
$ub        = calcUserBonus($userStats, $cfg);  // $ub = userBonus

// ── Рендер бонусных карточек ──────────────────────────────

function renderBonusCard(array $b, int $points): string
{
    global $mybb;
    $disabled = $points < $b['points'];
    $bg = match($b['art']) {
        'traffic'  => 'success',
        'invite'   => 'info',
        'title'    => 'warning',
        'gift_1'   => 'danger',
        'warning'  => 'secondary',
        'ratiofix' => 'dark',
        default    => 'primary',
    };
    $name    = htmlspecialchars_uni($b['bonusname']);
    $desc    = nl2br(htmlspecialchars_uni($b['description']));
    $opClass = $disabled ? 'opacity-75' : '';
    $dis     = $disabled ? 'disabled' : '';

    return <<<HTML
<div class="col-md-6 col-xl-4 mb-4">
    <div class="card h-100 shadow-sm border-0 hover-lift {$opClass}">
        <div class="card-header bg-{$bg} text-white">
            <h5 class="mb-0"><i class="fas fa-gem me-2"></i>{$name}</h5>
        </div>
        <div class="card-body d-flex flex-column">
            <p class="text-muted small flex-grow-1">{$desc}</p>
            <div class="mt-auto d-flex justify-content-between align-items-center">
                <span class="badge bg-dark fs-6 px-3 py-2">{$b['points']} pts</span>
                <form method="post" class="d-inline">
                    <input type="hidden" name="id" value="{$b['id']}">
                    <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
                    <button type="submit" class="btn btn-outline-{$bg} btn-sm" {$dis}>Buy</button>
                </form>
            </div>
        </div>
    </div>
</div>
HTML;
}

function renderToasts(array $errors, array $messages): string
{
    if (empty($errors) && empty($messages)) return '';

    $calls  = '';
    $reload = '';
    foreach ($errors   as $e) $calls .= 'showToast(' . json_encode(htmlspecialchars($e)) . ', \'error\');' . PHP_EOL;
    foreach ($messages as $m) {
        $calls  .= 'showToast(' . json_encode(htmlspecialchars($m)) . ', \'success\');' . PHP_EOL;
        $reload  = ''; // редирект теперь через header() в PHP
    }

    return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    {$calls}
    {$reload}
});
</script>
HTML;
}

function renderTorrentMultiplierTable(string $type, int $userTorrents, float $flatMul): string
{
    $ranges = match($type) {
        'penalty' => [
            ['1–20',   '1.0', 'success', $userTorrents >= 1  && $userTorrents <= 20],
            ['21–50',  '0.9', 'warning', $userTorrents >= 21 && $userTorrents <= 50],
            ['51–100', '0.8', 'warning', $userTorrents >= 51 && $userTorrents <= 100],
            ['100+',   '0.7', 'secondary', $userTorrents > 100],
        ],
        'reward'  => [
            ['1–19',   '0.9', 'secondary', $userTorrents >= 1  && $userTorrents <= 19],
            ['20–49',  '1.0', 'success',   $userTorrents >= 20 && $userTorrents <= 49],
            ['50–99',  '1.1', 'warning',   $userTorrents >= 50 && $userTorrents <= 99],
            ['100+',   '1.2', 'danger',    $userTorrents >= 100],
        ],
        'neutral' => [
            ['1–100', '1.0', 'success',  $userTorrents >= 1 && $userTorrents <= 100],
            ['101+',  '0.9', 'warning',  $userTorrents > 100],
        ],
        'flat'    => [],
        default   => [],
    };

    if ($type === 'flat') {
        return '<div class="alert alert-info mt-2">Fixed multiplier: ×' . number_format($flatMul, 1) . '</div>';
    }

    $cols = '';
    $colW = (int)(12 / max(1, count($ranges)));
    foreach ($ranges as [$range, $mul, $color, $isUser]) {
        $badge = $isUser ? "<div class='mt-1'><span class='badge bg-{$color}'>Your range</span></div>" : '';
        $cols .= <<<HTML
<div class="col-sm-{$colW}">
    <div class="text-center p-2 border rounded bg-light">
        <div class="small text-muted">{$range} torrents</div>
        <div class="fs-5 text-{$color}">×{$mul}</div>
        {$badge}
    </div>
</div>
HTML;
    }

    $desc = match($type) {
        'penalty' => 'More torrents = lower multiplier. Controls bonus inflation.',
        'reward'  => 'More torrents = higher multiplier. Encourages seeding many.',
        'neutral' => 'Simple penalty only for very high torrent counts.',
        default   => '',
    };

    return '<div class="row mt-2">' . $cols . '</div>'
         . '<div class="text-muted small mt-2"><i class="fas fa-info-circle me-1"></i>' . $desc . '</div>';
}

// ── Сборка карточек ───────────────────────────────────────

$res   = $db->simple_select('bonus', '*', '', ['order_by' => 'points']);
$cards = '';
while ($b = $db->fetch_array($res)) {
    $cards .= renderBonusCard($b, $points);
}

// Пример для расчёта
$exampleMul     = (float)($cfg['leech_many'] ?? 1.8)
                * (float)($cfg['size_xlarge'] ?? 1.8)
                * 1.0
                * (float)($cfg['age_old'] ?? 1.5)
                * (1.0 + (float)($cfg['promo_free'] ?? 0.7));
$exampleHourly  = round($exampleMul * $BASE_BONUS, 1);
$cronMin        = (int)($cfg['cron_interval'] ?? 15);
$examplePerCron = round($exampleHourly * ($cronMin / 60), 1);

// ── HTML ──────────────────────────────────────────────────
stdhead("My Bonuses — {$points} points");
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/mybonus.css" type="text/css" media="screen" />

<div class="container py-5">

    <!-- Заголовок -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary">
            <i class="fas fa-coins me-3"></i>My Bonuses
        </h1>
        <p class="lead">You have: <strong class="text-success fs-3"><?= $points ?> points</strong></p>
        <p class="text-muted small">
            Base: <?= $BASE_BONUS ?>/h &bull;
            Cap: <?= $HOUR_CAP ?>/h &bull;
            Multiplier type: <?= htmlspecialchars($TORRENT_MUL_TYPE) ?>
        </p>
    </div>

    <!-- Секция расчёта -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm formula-box">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0 text-primary">
                        <i class="fas fa-calculator me-2"></i>How bonus points are calculated
                    </h4>
                    <small class="text-muted">Active seeding formula explained</small>
                </div>
                <div class="card-body">

                    <!-- Статистика пользователя -->
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Your current bonus calculation</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($ub['torrents'] > 0): ?>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted">Active torrents</div>
                                        <div class="display-6 text-primary fw-bold"><?= $ub['torrents'] ?></div>
                                        <div class="small text-muted">
                                            Multiplier: ×<?= number_format($ub['capMul'], 1) ?>
                                            (<?= htmlspecialchars($TORRENT_MUL_TYPE) ?>)
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted">Raw bonus sum</div>
                                        <div class="display-6 text-success fw-bold"><?= number_format($ub['rawBonus'], 1) ?></div>
                                        <div class="small text-muted">Total of all multipliers</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted">Avg seeding time</div>
                                        <div class="display-6 text-warning fw-bold"><?= number_format($ub['avgHours'] * 60, 1) ?> min</div>
                                        <div class="small text-muted">per calculation</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <!-- Левая колонка: расчёт -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Hourly bonus calculation</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <div class="small text-muted">Theoretical max</div>
                                                    <div class="fs-4 fw-bold text-primary"><?= number_format($ub['hourlyTheoretical'], 0) ?> pts/h</div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="small text-muted">System cap</div>
                                                    <div class="fs-4 fw-bold text-warning"><?= number_format($HOUR_CAP, 0) ?> pts/h</div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3 bg-light mb-3">
                                                <?php
                                                $rows = [
                                                    ['Raw bonus sum', number_format($ub['rawBonus'], 1)],
                                                    ['× Base rate (' . $BASE_BONUS . ')', number_format($ub['rawBonus'] * $BASE_BONUS, 1)],
                                                    ['× Torrents factor (×' . number_format($ub['capMul'], 1) . ')', number_format($ub['hourlyTheoretical'], 1)],
                                                ];
                                                foreach ($rows as [$label, $val]):
                                                ?>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="small"><?= $label ?></span>
                                                    <span><?= $val ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between">
                                                    <span class="small text-muted">Theoretical hourly</span>
                                                    <span class="fw-bold text-primary"><?= number_format($ub['hourlyTheoretical'], 1) ?> pts/h</span>
                                                </div>
                                            </div>

                                            <div class="text-center p-3 border rounded bg-success bg-opacity-10 mb-3">
                                                <div class="small text-muted">Hourly bonus (after cap)</div>
                                                <div class="display-6 fw-bold text-success mb-1">
                                                    <?= number_format($ub['hourlyCapped'], 0) ?> pts/h
                                                </div>
                                                <?php if ($ub['isCapped']): ?>
                                                <span class="text-warning small"><i class="fas fa-exclamation-triangle me-1"></i>Capped at system limit</span>
                                                <?php else: ?>
                                                <span class="text-success small"><i class="fas fa-check-circle me-1"></i>Not capped</span>
                                                <?php endif; ?>
                                            </div>

                                            <?php
                                            $pct   = $ub['capPct'];
                                            $pColor = progressColor($pct);
                                            ?>
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Theoretical vs cap (<?= $HOUR_CAP ?>/h)</small>
                                                <small><?= number_format($pct, 1) ?>%</small>
                                            </div>
                                            <div class="progress" style="height:20px">
                                                <div class="progress-bar bg-<?= $pColor ?> progress-bar-striped"
                                                     style="width:<?= $pct ?>%">
                                                    <?= number_format($ub['hourlyTheoretical'], 0) ?> / <?= $HOUR_CAP ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Правая колонка: заработок -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-coins me-2"></i>Your estimated earnings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="text-center mb-4">
                                                <div class="display-3 text-success fw-bold"><?= number_format($ub['realHourly'], 0) ?></div>
                                                <div class="text-muted fs-5">points per hour</div>
                                                <div class="small text-warning mt-1">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Based on <?= number_format($ub['avgHours'] * 60, 0) ?> min avg seeding time
                                                </div>
                                            </div>

                                            <?php
                                            $earnCards = [
                                                ['primary', 'Per cron run',  number_format($ub['perRun'], 1),      'every ' . $cronMin . ' min'],
                                                ['success', 'Per hour',      number_format($ub['realHourly'], 0),  '4 × per run'],
                                                ['success', 'Per day (24h)', number_format($ub['daily'], 0),        'if active 24/7'],
                                                ['danger',  'Per week',      number_format($ub['daily'] * 7, 0),   '7 days constant'],
                                            ];
                                            ?>
                                            <div class="row g-2 mb-3">
                                                <?php foreach ($earnCards as [$color, $label, $val, $sub]): ?>
                                                <div class="col-6">
                                                    <div class="card border-<?= $color ?> h-100 stats-card">
                                                        <div class="card-body text-center p-3">
                                                            <div class="small text-muted mb-1"><?= $label ?></div>
                                                            <div class="fs-3 fw-bold text-<?= $color ?>"><?= $val ?></div>
                                                            <div class="small text-muted"><?= $sub ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <?php
                                            $actPct   = min(100, ($ub['avgHours'] / $CRON_INTERVAL_HOURS) * 100);
                                            $actColor = progressColor($actPct);
                                            ?>
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">Activity level</small>
                                                <small><?= number_format($actPct, 0) ?>%</small>
                                            </div>
                                            <div class="progress mb-1" style="height:8px">
                                                <div class="progress-bar bg-info progress-bar-striped progress-bar-animated"
                                                     style="width:<?= $actPct ?>%"></div>
                                            </div>
                                            <div class="small text-center text-muted">
                                                <?= number_format($ub['avgHours'] * 60, 0) ?> min / <?= $cronMin ?> min interval
                                            </div>

                                            <div class="alert alert-dark mt-3 small">
                                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                                <strong>Tip:</strong> Seed torrents with leechers, large files (&gt;20 GB),
                                                and old torrents (&gt;180 days) for max earnings.
                                                <span class="text-info d-block mt-1">
                                                    Current rate: <?= number_format($ub['realHourly'], 0) ?>/h,
                                                    <?= number_format($ub['daily'], 0) ?>/day
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                You don't have any active torrents. Start seeding to earn bonus points!
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Системные настройки -->
                    <div class="card bg-light border mb-4">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Current System Settings</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php
                                $sysSettings = [
                                    ['primary', 'Base bonus',       'Points per hour', $BASE_BONUS, 'success'],
                                    ['primary', 'Hour cap',         'Max per hour',    $HOUR_CAP,   'warning'],
                                    ['primary', 'Cron interval',    'Calculation',     $cronMin . ' min', 'info'],
                                    ['primary', 'Torrent multiplier','Type',           ucfirst($TORRENT_MUL_TYPE), 'danger'],
                                ];
                                foreach ($sysSettings as [$bc, $label, $sub, $val, $vc]):
                                ?>
                                <div class="col-sm-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-<?= $bc ?> me-2"><?= $label ?></span>
                                        <span><?= $sub ?></span>
                                    </div>
                                    <h5 class="text-<?= $vc ?>"><?= $val ?></h5>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Множители -->
                    <h6 class="mt-3 mb-2">Multipliers per torrent:</h6>
                    <div class="row g-2">
                        <?php
                        $multiplierGroups = [
                            ['info',      'Leechers', [
                                ['0 leechers',   '×' . ($cfg['leech_none'] ?? 1.2), 'success'],
                                ['1-2 leechers', '×' . ($cfg['leech_few']  ?? 1.5), 'warning'],
                                ['3+ leechers',  '×' . ($cfg['leech_many'] ?? 1.8), 'danger', true],
                            ]],
                            ['success',   'Size', [
                                ['< 0.5 GB', '×' . ($cfg['size_small']  ?? 1.0), ''],
                                ['0.5-2 GB', '×' . ($cfg['size_medium'] ?? 1.2), 'success'],
                                ['2-8 GB',   '×' . ($cfg['size_large']  ?? 1.5), 'warning'],
                                ['8-20 GB',  '×' . ($cfg['size_xlarge'] ?? 1.8), 'danger'],
                                ['> 20 GB',  '×' . ($cfg['size_huge']   ?? 2.0), 'danger', true],
                            ]],
                            ['warning',   'Seeders', [
                                ['≤ 50',    '×1.0', 'success'],
                                ['51-100',  '×' . ($cfg['seeders_medium'] ?? 0.95), ''],
                                ['> 100',   '×' . ($cfg['seeders_many']   ?? 0.9),  'muted'],
                            ]],
                            ['secondary', 'Age', [
                                ['< 60 days',   '×1.0', ''],
                                ['60-180 days', '×' . ($cfg['age_medium'] ?? 1.3), 'warning'],
                                ['> 180 days',  '×' . ($cfg['age_old']    ?? 1.5), 'danger', true],
                            ]],
                        ];
                        foreach ($multiplierGroups as [$color, $label, $rows]):
                        ?>
                        <div class="col-sm-6">
                            <div class="formula-item">
                                <span class="badge bg-<?= $color ?> badge-small me-2"><?= $label ?></span>
                                <?php foreach ($rows as $row):
                                    [$rLabel, $rVal, $rColor] = $row;
                                    $fw = !empty($row[3]) ? ' fw-bold' : '';
                                    $tc = $rColor ? ' text-' . $rColor : '';
                                ?>
                                <div class="d-flex justify-content-between">
                                    <span><?= $rLabel ?>:</span>
                                    <span class="<?= $tc . $fw ?>"><?= $rVal ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Промо-бонусы -->
                    <div class="formula-item mt-3 p-3 bg-warning bg-opacity-10 rounded">
                        <span class="badge bg-danger badge-small me-2">Promo bonuses</span>
                        <div class="row mt-2">
                            <?php
                            $promos = [
                                ['Freeleech',      $cfg['promo_free']   ?? 0.7],
                                ['Silver',         $cfg['promo_silver'] ?? 0.5],
                                ['Double Upload',  $cfg['promo_double'] ?? 0.5],
                            ];
                            foreach ($promos as [$pl, $pv]):
                            ?>
                            <div class="col-sm-4">
                                <div class="d-flex justify-content-between">
                                    <span><?= $pl ?>:</span>
                                    <span class="text-success">+<?= $pv ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-muted mt-1">These values are added to 1.0 base</div>
                    </div>

                    <!-- Базовая формула + пример + советы -->
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <h5 class="mb-3">Basic formula:</h5>
                            <div class="alert alert-success py-2 mb-3">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <strong>Hourly bonus =</strong> Base × Multipliers × Torrents factor
                                    </div>
                                    <div class="col-sm-6">
                                        <strong>Final bonus =</strong> Hourly bonus × Seeding time
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">

                            <!-- Период расчёта -->
                            <div class="card border-primary mb-3">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Calculation period</h6>
                                </div>
                                <div class="card-body py-2">
                                    <?php
                                    $announceMin = (int)($cfg['announce_interval'] ?? 15);
                                    $periodRows  = [
                                        ['Tracker announce', $announceMin . ' min', null],
                                        ['Bonus cron',       $cronMin . ' min',     null],
                                        ['Min counted',      $announceMin . ' min', round($announceMin / 60, 2) . ' hours minimum'],
                                        ['Max per run',      ($cronMin * 2) . ' min', round(($cronMin * 2) / 60, 2) . ' hours maximum'],
                                    ];
                                    foreach ($periodRows as [$label, $val, $note]):
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span><?= $label ?>:</span>
                                            <span class="fw-bold"><?= $val ?></span>
                                        </div>
                                        <?php if ($note): ?>
                                        <small class="text-muted"><?= $note ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    <hr class="my-2">
                                    <div class="text-center">
                                        <small class="text-muted">
                                            Seed at least <strong><?= $announceMin ?> minutes</strong> to earn points
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Пример расчёта -->
                            <div class="card bg-success bg-opacity-10 border-success mb-3">
                                <div class="card-header border-success py-2">
                                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Example calculation</h6>
                                </div>
                                <div class="card-body py-2">
                                    <div class="small mb-2">
                                        <strong>Torrent:</strong> 15 GB, 4 leechers, Freeleech, 200 days old, 30 seeders
                                    </div>
                                    <div class="bg-white p-2 rounded mb-2">
                                        <?php
                                        $exRows = [
                                            ['Leechers (4)',    '×' . ($cfg['leech_many']   ?? 1.8) . ' (3+ leechers)'],
                                            ['Size (15 GB)',    '×' . ($cfg['size_xlarge']  ?? 1.8) . ' (8-20 GB)'],
                                            ['Seeders (30)',    '×1.0 (≤ 50 seeders)'],
                                            ['Age (200 days)',  '×' . ($cfg['age_old']      ?? 1.5) . ' (>180 days)'],
                                            ['Promo (Free)',    '×' . number_format(1.0 + (float)($cfg['promo_free'] ?? 0.7), 1) . ' (+' . ($cfg['promo_free'] ?? 0.7) . ')'],
                                        ];
                                        foreach ($exRows as [$el, $ev]):
                                        ?>
                                        <div class="d-flex justify-content-between">
                                            <span><?= $el ?>:</span>
                                            <span><?= $ev ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                        <hr class="my-1">
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span>Total multiplier:</span>
                                            <span>×<?= number_format($exampleMul, 2) ?></span>
                                        </div>
                                    </div>
                                    <div class="bg-light p-2 rounded">
                                        <div class="d-flex justify-content-between">
                                            <span>Base (<?= $BASE_BONUS ?>):</span>
                                            <span>×<?= $BASE_BONUS ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Hourly bonus:</span>
                                            <span class="fw-bold"><?= number_format($exampleHourly, 1) ?>/h</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>For <?= $cronMin ?> min:</span>
                                            <span class="text-success fw-bold"><?= number_format($examplePerCron, 1) ?> pts</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Советы -->
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark py-2">
                                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Maximization tips</h6>
                                </div>
                                <div class="card-body py-2">
                                    <ul class="mb-0 small">
                                        <?php
                                        $tips = [
                                            ['fa-fire text-danger',      'Seed torrents with leechers',  '×' . ($cfg['leech_many'] ?? 1.8) . ' for 3+ leechers'],
                                            ['fa-hdd text-success',      'Large files (>20 GB)',          '×' . ($cfg['size_huge']  ?? 2.0)],
                                            ['fa-history text-secondary','Old torrents (>180 days)',       '×' . ($cfg['age_old']    ?? 1.5)],
                                            ['fa-tag text-primary',      'Promo torrents',
                                                'Free +' . ($cfg['promo_free'] ?? 0.7) .
                                                ', Silver +' . ($cfg['promo_silver'] ?? 0.5) .
                                                ', Double +' . ($cfg['promo_double'] ?? 0.5)],
                                            ['fa-bell text-info',        'Send announce every ' . $announceMin . ' min', ''],
                                        ];
                                        foreach ($tips as [$ic, $text, $note]):
                                        ?>
                                        <li class="mb-1">
                                            <i class="fas <?= $ic ?> me-1"></i>
                                            <strong><?= $text ?></strong>
                                            <?php if ($note): ?>
                                            <span class="text-success">(<?= $note ?>)</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                        <li>
                                            <i class="fas fa-chart-pie text-warning me-1"></i>
                                            <?= match($TORRENT_MUL_TYPE) {
                                                'penalty' => '<strong>1-20 torrents</strong> optimal (×1.0)',
                                                'reward'  => '<strong>50+ torrents</strong> optimal (×1.1+)',
                                                'neutral' => '<strong>1-100 torrents</strong> optimal (×1.0)',
                                                'flat'    => '<strong>Any amount</strong> (always ×' . $FLAT_MULTIPLIER . ')',
                                                default   => '<strong>Keep seeding</strong> for max points',
                                            } ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Торрент-множитель -->
                    <div class="formula-item mt-3">
                        <span class="badge bg-dark badge-small me-2">Torrents factor</span>
                        <span class="badge bg-info badge-small">Type: <?= htmlspecialchars(ucfirst($TORRENT_MUL_TYPE)) ?></span>
                        <?= renderTorrentMultiplierTable($TORRENT_MUL_TYPE, $ub['torrents'], $FLAT_MULTIPLIER) ?>

                        <?php if ($ub['torrents'] > 0): ?>
                        <div class="alert alert-primary mt-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <i class="fas fa-user me-2"></i>
                                    <strong>Your multiplier:</strong>
                                    <span class="fs-4 ms-2">×<?= number_format($ub['capMul'], 1) ?></span>
                                    <div class="small text-muted">
                                        <?= $ub['torrents'] ?> torrents → <?= number_format($ub['capMul'] * 100, 0) ?>%
                                    </div>
                                </div>
                                <div class="col-md-6 small text-muted">
                                    <?= match(true) {
                                        $TORRENT_MUL_TYPE === 'penalty' && $ub['capMul'] < 1.0 =>
                                            '<i class="fas fa-arrow-down text-warning me-1"></i>Penalty for many torrents',
                                        $TORRENT_MUL_TYPE === 'reward'  && $ub['capMul'] > 1.0 =>
                                            '<i class="fas fa-arrow-up text-success me-1"></i>Bonus for many torrents',
                                        default =>
                                            '<i class="fas fa-equals text-info me-1"></i>Standard multiplier',
                                    } ?>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Совет по следующему порогу
                        $nextInfo = null;
                        if ($TORRENT_MUL_TYPE === 'penalty') {
                            $nextInfo = match(true) {
                                $ub['torrents'] <= 20  => [21,  0.9, 21  - $ub['torrents']],
                                $ub['torrents'] <= 50  => [51,  0.8, 51  - $ub['torrents']],
                                $ub['torrents'] <= 100 => [101, 0.7, 101 - $ub['torrents']],
                                default                => null,
                            };
                        } elseif ($TORRENT_MUL_TYPE === 'reward') {
                            $nextInfo = match(true) {
                                $ub['torrents'] < 20  => [20,  1.0, 20  - $ub['torrents']],
                                $ub['torrents'] < 50  => [50,  1.1, 50  - $ub['torrents']],
                                $ub['torrents'] < 100 => [100, 1.2, 100 - $ub['torrents']],
                                default               => null,
                            };
                        }
                        ?>
                        <div class="alert alert-secondary small mt-2">
                            <?php if ($nextInfo): [$nt, $nm, $need] = $nextInfo; ?>
                            <i class="fas fa-bullseye me-1"></i>
                            <strong>Next threshold:</strong>
                            Reach <?= $nt ?> torrents for ×<?= number_format($nm, 1) ?>
                            (need <?= $need ?> more)
                            <?php else: ?>
                            <i class="fas fa-trophy me-1"></i>
                            <strong>Maximum multiplier reached!</strong>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?= renderToasts($errors, $messages) ?>

    <!-- Бонусные карточки -->
    <div class="row g-4 mt-2"
         id="bonusStatsData"
         data-hourly="<?= number_format($ub['realHourly'], 0) ?>"
         data-torrents="<?= $ub['torrents'] ?>"
         data-seedtime="<?= number_format($ub['avgHours'] * 60, 0) ?>"
         data-daily="<?= number_format($ub['daily'], 0) ?>">
        <?= $cards ?>
    </div>

</div>

<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script src="<?= $BASEURL ?>/scripts/mybonus.js"></script>


<?php stdfoot(); ?>