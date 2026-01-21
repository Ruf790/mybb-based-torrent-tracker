<?php
declare(strict_types=1);

/***********************************************/
/*   MYBONUS.PHP — PHP 8.4 — CLEAN BEAUTIFUL   */           
/***********************************************/

define("IN_MYBB", 1);

require_once 'global.php';
require_once INC_PATH . '/functions_pm.php';
require_once INC_PATH . '/datahandler.php';

// Загружаем настройки seedbonus из БД
$seedbonus_settings = [];
$res = $db->sql_query("SELECT setting_key, setting_value, setting_type FROM seedbonus_settings");
while ($row = $db->fetch_array($res)) {
    $value = $row['setting_value'];
    switch ($row['setting_type']) {
        case 'boolean':
            $value = ($value === 'yes' || $value === 'true' || $value === '1' || $value === 'on');
            break;
        case 'integer':
            $value = intval($value);
            break;
        case 'float':
            $value = floatval($value);
            break;
        case 'array':
            $value = json_decode($value, true) ?? [];
            break;
        default:
            $value = (string)$value;
    }
    $seedbonus_settings[$row['setting_key']] = $value;
}

// Настройки из таблицы
$BASE_BONUS = floatval($seedbonus_settings['base_bonus'] ?? 10.0);
$HOUR_CAP = floatval($seedbonus_settings['hour_cap'] ?? 500.0);
$MAX_DB_VALUE = floatval($seedbonus_settings['max_db_value'] ?? 9999999.9);
$ANNOUNCE_INTERVAL = ($seedbonus_settings['announce_interval'] ?? 15) * 60; // в секундах

// Множители
$TORRENT_MULTIPLIER_TYPE = $seedbonus_settings['torrent_multiplier_type'] ?? 'penalty';
$FLAT_MULTIPLIER = floatval($seedbonus_settings['flat_multiplier'] ?? 1.0);

$LEECH_NONE = floatval($seedbonus_settings['leech_none'] ?? 1.2);
$LEECH_FEW = floatval($seedbonus_settings['leech_few'] ?? 1.5);
$LEECH_MANY = floatval($seedbonus_settings['leech_many'] ?? 1.8);

$SIZE_SMALL = floatval($seedbonus_settings['size_small'] ?? 1.0);
$SIZE_MEDIUM = floatval($seedbonus_settings['size_medium'] ?? 1.2);
$SIZE_LARGE = floatval($seedbonus_settings['size_large'] ?? 1.5);
$SIZE_XLARGE = floatval($seedbonus_settings['size_xlarge'] ?? 1.8);
$SIZE_HUGE = floatval($seedbonus_settings['size_huge'] ?? 2.0);

$SEEDERS_MANY = floatval($seedbonus_settings['seeders_many'] ?? 0.9);
$SEEDERS_MEDIUM = floatval($seedbonus_settings['seeders_medium'] ?? 0.95);
$AGE_OLD = floatval($seedbonus_settings['age_old'] ?? 1.5);
$AGE_MEDIUM = floatval($seedbonus_settings['age_medium'] ?? 1.3);
$PROMO_FREE = floatval($seedbonus_settings['promo_free'] ?? 0.7);
$PROMO_SILVER = floatval($seedbonus_settings['promo_silver'] ?? 0.5);
$PROMO_DOUBLE = floatval($seedbonus_settings['promo_double'] ?? 0.5);

// Временные настройки
$CRON_INTERVAL = intval($seedbonus_settings['cron_interval'] ?? 15) * 60; // в секундах
$CRON_INTERVAL_HOURS = $CRON_INTERVAL / 3600; // в часах
$ENABLE_HEURISTIC = isset($seedbonus_settings['enable_heuristic']) && 
                   ($seedbonus_settings['enable_heuristic'] === true || 
                    $seedbonus_settings['enable_heuristic'] === 'yes' || 
                    $seedbonus_settings['enable_heuristic'] === 'true' || 
                    $seedbonus_settings['enable_heuristic'] === '1' || 
                    $seedbonus_settings['enable_heuristic'] === 'on');

// Эвристические настройки
$HEURISTIC_50 = intval($seedbonus_settings['heuristic_50'] ?? 24);
$HEURISTIC_40 = intval($seedbonus_settings['heuristic_40'] ?? 20);
$HEURISTIC_30 = intval($seedbonus_settings['heuristic_30'] ?? 16);
$HEURISTIC_20 = intval($seedbonus_settings['heuristic_20'] ?? 12);
$HEURISTIC_10 = intval($seedbonus_settings['heuristic_10'] ?? 8);
$HEURISTIC_5 = intval($seedbonus_settings['heuristic_5'] ?? 4);
$HEURISTIC_1 = intval($seedbonus_settings['heuristic_1'] ?? 2);

// Authorization check
if (!$CURUSER || ($CURUSER['id'] ?? 0) == 0) {
    print_no_permission();
}

$lang->load('mybonus');
$points = (int)($CURUSER['seedbonus'] ?? 0);
$userid = (int)$CURUSER['id'];

$errors = [];
$messages = [];

// === PURCHASE PROCESSING ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $query = $db->simple_select("bonus", "*", "id = " . (int)$id);
    $bonus = $db->fetch_array($query);

    if (!$bonus) {
        $errors[] = $lang->mybonus['error1'] ?? 'Bonus not found';
    } elseif ($points < $bonus['points']) {
        $errors[] = sprintf($lang->mybonus['error2'] ?? 'Not enough points: %d out of %d', $points, $bonus['points']);
    } else {
        $used = false;
        
        // Determine bonus type and process
        switch ($bonus['art']) {
            case 'traffic':
                purchase($db, $userid, "uploaded = uploaded + {$bonus['menge']}", $bonus, $used);
                break;
            case 'invite':
                purchase($db, $userid, "invites = invites + {$bonus['menge']}", $bonus, $used);
                break;
            case 'title':
                if (isset($_POST['update_title']) && $_POST['update_title'] === 'yes') {
                    handleTitle($db, $userid, $bonus, $used);
                } else {
                    showTitleForm($bonus);
                    exit;
                }
                break;
            case 'gift_1':
                if (isset($_POST['send_gift']) && $_POST['send_gift'] === 'yes') {
                    handleGift($db, $userid, $bonus, $used);
                } else {
                    showGiftForm($bonus);
                    exit;
                }
                break;
            case 'warning':
                purchase($db, $userid, "timeswarned = GREATEST(0, timeswarned - {$bonus['menge']})", $bonus, $used);
                break;
            case 'ratiofix':
                if (isset($_POST['ratiofix']) && $_POST['ratiofix'] === 'yes') {
                    handleRatioFix($db, $userid, $bonus, $used);
                } else {
                    showRatioFixForm($bonus);
                    exit;
                }
                break;
            default:
                $errors[] = "Unknown bonus";
        }

        if ($used && empty($errors)) {
            $messages[] = sprintf($lang->mybonus['message1'] ?? 'Purchased: %s', htmlspecialchars_uni($bonus['bonusname']));
            $points -= $bonus['points'];
            $CURUSER['seedbonus'] = $points;
        }
    }
}

// === ФУНКЦИИ ===
function getTorrentMultiplier($torrentsCount, $type, $flatValue = 1.0) {
    switch ($type) {
        case 'penalty':
            if ($torrentsCount <= 20) return 1.0;
            if ($torrentsCount <= 50) return 0.9;
            if ($torrentsCount <= 100) return 0.8;
            return 0.7;
        case 'neutral':
            return ($torrentsCount <= 100) ? 1.0 : 0.9;
        case 'reward':
            if ($torrentsCount >= 100) return 1.2;
            if ($torrentsCount >= 50) return 1.1;
            if ($torrentsCount >= 20) return 1.0;
            return 0.9;
        case 'flat':
            return $flatValue;
        default:
            return 1.0;
    }
}

function getHeuristicHours($torrentsCount, $settings) {
    if ($torrentsCount >= 50) return floatval($settings['heuristic_50'] ?? 24);
    if ($torrentsCount >= 40) return floatval($settings['heuristic_40'] ?? 20);
    if ($torrentsCount >= 30) return floatval($settings['heuristic_30'] ?? 16);
    if ($torrentsCount >= 20) return floatval($settings['heuristic_20'] ?? 12);
    if ($torrentsCount >= 10) return floatval($settings['heuristic_10'] ?? 8);
    if ($torrentsCount >= 5) return floatval($settings['heuristic_5'] ?? 4);
    return floatval($settings['heuristic_1'] ?? 2);
}

function purchase($db, $uid, $field, $b, &$used): void {
    $db->sql_query("UPDATE users SET $field, seedbonus = seedbonus - {$b['points']} WHERE id = " . (int)$uid);
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
    }
}

function logBonus($db, $uid, $b): void {
    $comment = TIMENOW . ' - ' . $b['bonusname'] . ' (-' . $b['points'] . " pts)\n";
    $db->sql_query("UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment, ''), '" . $db->escape_string($comment) . "') WHERE id = " . (int)$uid);
}

function handleTitle($db, $uid, $b, &$used): void {
    global $errors;
    
    $title = trim($_POST['title'] ?? '');
    if (strlen($title) < 2) {
        $errors[] = "Title too short!";
        return;
    }
    
    $db->sql_query("UPDATE users SET title = " . $db->sqlesc(htmlspecialchars_uni($title)) . ", seedbonus = seedbonus - {$b['points']} WHERE id = " . (int)$uid);
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
    }
}

function showTitleForm($b): void {
    global $CURUSER, $mybb;
    
    stdhead("Buy title");
    echo '<div class="container py-5"><div class="card shadow"><div class="card-body">
        <form method="post">
            <input type="hidden" name="id" value="'.$b['id'].'">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
            <input type="hidden" name="update_title" value="yes">
            <div class="mb-3">
                <label class="form-label fw-bold">New title:</label>
                <input type="text" name="title" class="form-control" value="'.htmlspecialchars_uni($CURUSER['title'] ?? '').'" required>
            </div>
            <button type="submit" class="btn btn-success">Buy for '.$b['points'].' points</button>
            <a href="mybonus.php" class="btn btn-secondary">Cancel</a>
        </form>
        </div></div></div>';
    stdfoot();
    exit;
}

function handleGift($db, $uid, $b, &$used): void {
    global $errors, $CURUSER, $BASEURL, $points, $lang;
    
    $gift = (int)($_POST['gift'] ?? 0);
    $to = trim($_POST['username'] ?? '');
    
    if ($gift < 1) {
        $errors[] = "Invalid gift amount!";
        return;
    }
    
    if ($to === $CURUSER['username']) {
        $errors[] = "Cannot gift to yourself!";
        return;
    }
    
    $query = $db->simple_select("users", "id, seedbonus, username", "username = " . $db->sqlesc($to));
    $target = $db->fetch_array($query);
    
    if (!$target) {
        $errors[] = "User not found!";
        return;
    }
    
    $total = $b['points'] + $gift;
    
    if ($points < $total) {
        $errors[] = "Not enough points! Needed: {$total}, you have: {$points}";
        return;
    }
    
    $db->sql_query("UPDATE users SET seedbonus = seedbonus + {$gift} WHERE id = " . (int)$target['id']);
    $db->sql_query("UPDATE users SET seedbonus = seedbonus - {$total} WHERE id = " . (int)$uid);
    
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
        
        $comment = "Gift: {$gift} points from " . $CURUSER['username'];
        $db->sql_query("UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment, ''), '" . $db->escape_string($comment) . "') WHERE id = " . (int)$target['id']);
        
        $profilelink = $BASEURL . '/'.get_profile_link((int)$uid).'';
        
        $pm = array(
            'subject' => sprintf($lang->mybonus['giftsubject']),
            'message' => sprintf(
                $lang->mybonus['giftmsg'],
                '[b]' . $target['username'] . '[/b]',
                '[URL='.$profilelink.'][b]' . $CURUSER['username'] . '[/b][/URL]',
                $gift
            ),
            'touid' => (int)$target['id']
        );
        
        $pm['sender']['uid'] = -1;

        if (function_exists('send_pm')) {
            send_pm($pm, -1, true);
        }
    }
}

function showGiftForm($b): void {
    global $mybb, $points;
    
    stdhead("Gift points");
    echo '<div class="container py-5"><div class="card shadow"><div class="card-body">
        <form method="post">
            <input type="hidden" name="id" value="'.$b['id'].'">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
            <input type="hidden" name="send_gift" value="yes">
            
            <div class="alert alert-info">
                <strong>Information:</strong> You have <strong>'.$points.'</strong> points available<br>
                Transfer fee: <strong>'.$b['points'].'</strong> points<br>
                Maximum you can gift: <strong>'.($points - $b['points']).'</strong> points
            </div>
            
            <div class="mb-3">
                <label class="form-label">To (username):</label>
                <input type="text" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">How many points to gift:</label>
                <input type="number" name="gift" class="form-control" min="1" max="'.($points - $b['points']).'" required>
            </div>
            
            <button type="submit" class="btn btn-success">Gift points</button>
            <a href="mybonus.php" class="btn btn-secondary">Cancel</a>
        </form>
        </div></div></div>';
    stdfoot();
    exit;
}

function handleRatioFix($db, $uid, $b, &$used): void {
    global $errors;
    
    $tid = (int)($_POST['torrentid'] ?? 0);
    
    if ($tid <= 0) {
        $errors[] = "Invalid torrent ID!";
        return;
    }
    
    $query = $db->simple_select("snatched", "uploaded, downloaded, seedtime", "torrentid = '{$tid}' AND userid = '{$uid}' AND finished = 'yes'");
    $snatch = $db->fetch_array($query);
    
    if (!$snatch) {
        $errors[] = "Torrent not found!";
        return;
    }
    
    $db->sql_query("UPDATE snatched SET uploaded = downloaded, seedtime = GREATEST(seedtime, 3600*24) WHERE torrentid = '{$tid}' AND userid = '{$uid}'");
    $db->sql_query("UPDATE users SET seedbonus = seedbonus - {$b['points']} WHERE id = '{$uid}'");
    
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
    }
}

function showRatioFixForm($b): void {
    global $mybb;
    
    stdhead("Fix ratio");
    echo '<div class="container py-5"><div class="card shadow"><div class="card-body">
        <form method="post">
            <input type="hidden" name="id" value="'.$b['id'].'">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
            <input type="hidden" name="ratiofix" value="yes">
            <div class="mb-3">
                <label class="form-label">Torrent ID:</label>
                <input type="number" name="torrentid" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">Fix</button>
            <a href="mybonus.php" class="btn btn-secondary">Cancel</a>
        </form>
        </div></div></div>';
    stdfoot();
    exit;
}

// === CARD OUTPUT ===
$query = $db->simple_select("bonus", "*", "", ["order_by" => "points"]);
$cards = '';

while ($b = $db->fetch_array($query)) {
    $disabled = $points < $b['points'];
    $bg = match($b['art']) {
        'traffic' => 'success',
        'invite'  => 'info',
        'title'   => 'warning',
        'gift_1'  => 'danger',
        'warning' => 'secondary',
        'ratiofix'=> 'dark',
        default   => 'primary'
    };
    
    $cards .= '
    <div class="col-md-6 col-xl-4 mb-4">
        <div class="card h-100 shadow-sm border-0 hover-lift '.($disabled?'opacity-75':'').'">
            <div class="card-header bg-'.$bg.' text-white">
                <h5 class="mb-0"><i class="fas fa-gem me-2"></i> '.htmlspecialchars_uni($b['bonusname']).'</h5>
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted small flex-grow-1">'.nl2br(htmlspecialchars_uni($b['description'])).'</p>
                <div class="mt-auto">
                    <span class="badge bg-dark fs-6 px-3 py-2">'.$b['points'].' points</span>
                    <form method="post" class="d-inline float-end">
                        <input type="hidden" name="id" value="'.$b['id'].'">
                        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">';
    
    $cards .= '<button type="submit" class="btn btn-outline-'.$bg.' btn-sm" '.($disabled?'disabled':'').'>
                            Buy
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>';
}

// === HTML ===
stdhead("My Bonuses — {$points} points");



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .hover-lift { transition: all .3s; }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,.15)!important; }
        .card-header { border-bottom: none; }
        .stats-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        .stats-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .badge-small {
            font-size: 0.7rem;
            padding: 0.15em 0.4em;
        }
        .formula-box {
            border-left: 4px solid var(--bs-primary);
        }
        .formula-item {
            padding: 10px;
            margin-bottom: 10px;
            background: linear-gradient(to right, #f8f9fa, #fff);
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .very-small { font-size: 0.75rem; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary">
            <i class="fas fa-coins me-3"></i>My Bonuses
        </h1>
        <p class="lead">You have: <strong class="text-success fs-3"><?php echo $points; ?> points</strong></p>
        <p class="text-muted small">
            Current system settings: Base bonus: <?php echo $BASE_BONUS; ?>/h, Hour cap: <?php echo $HOUR_CAP; ?>/h,
            Torrent multiplier: <?php echo $TORRENT_MULTIPLIER_TYPE; ?>
        </p>
    </div>

    <!-- СЕКЦИЯ: РАСЧЕТ БОНУСОВ -->
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
                    <!-- РАСЧЕТ ДЛЯ ТЕКУЩЕГО ЮЗЕРА -->
                    <?php
                    // Получаем текущую статистику пользователя
                    $user_stats_query = "
                        SELECT 
                            COUNT(DISTINCT p.torrent) as torrents_count,
                            AVG(
                                GREATEST(0.25, LEAST(
                                    (UNIX_TIMESTAMP() - GREATEST(
                                        p.last_action,
                                        UNIX_TIMESTAMP() - 2700
                                    )) / 3600,
                                    " . $CRON_INTERVAL_HOURS . "
                                ))
                            ) as avg_hours_seeded,
                            SUM(
                                /* Leecher множители */
                                CASE 
                                    WHEN t.leechers = 0 THEN " . $LEECH_NONE . "
                                    WHEN t.leechers <= 2 THEN " . $LEECH_FEW . "
                                    ELSE " . $LEECH_MANY . "
                                END *
                                
                                /* Size множители */
                                CASE 
                                    WHEN t.size < 536870912 THEN " . $SIZE_SMALL . "
                                    WHEN t.size < 2147483648 THEN " . $SIZE_MEDIUM . "
                                    WHEN t.size < 8589934592 THEN " . $SIZE_LARGE . "
                                    WHEN t.size < 21474836480 THEN " . $SIZE_XLARGE . "
                                    ELSE " . $SIZE_HUGE . "
                                END *
                                
                                /* Seeders штрафы */
                                CASE 
                                    WHEN t.seeders > 100 THEN " . $SEEDERS_MANY . "
                                    WHEN t.seeders > 50 THEN " . $SEEDERS_MEDIUM . "
                                    ELSE 1.0
                                END *
                                
                                /* Age factor бонусы */
                                CASE 
                                    WHEN (UNIX_TIMESTAMP() - t.added) > 15552000 THEN " . $AGE_OLD . "
                                    WHEN (UNIX_TIMESTAMP() - t.added) > 5184000 THEN " . $AGE_MEDIUM . "
                                    ELSE 1.0
                                END *
                                
                                /* Promotion multipliers */
                                (1.0 + 
                                    (t.free = 'yes') * " . $PROMO_FREE . " +
                                    (t.silver = 'yes') * " . $PROMO_SILVER . " +
                                    (t.doubleupload = 'yes') * " . $PROMO_DOUBLE . "
                                )
                            ) as raw_bonus_sum
                        FROM peers p
                        INNER JOIN torrents t ON t.id = p.torrent
                        WHERE p.seeder = 'yes'
                          AND p.userid = '{$userid}'
                          AND t.visible = 'yes'
                          AND t.banned = 'no'
                          AND t.isnuked = 'no'
                          AND p.last_action >= UNIX_TIMESTAMP() - 2700
                        GROUP BY p.userid
                    ";
                    
                    $user_stats_res = $db->sql_query($user_stats_query);
                    $user_stats = $db->fetch_array($user_stats_res);
                    
                    if ($user_stats && $user_stats['torrents_count'] > 0) {
                        $user_torrents = (int)$user_stats['torrents_count'];
                        $user_avg_hours = (float)$user_stats['avg_hours_seeded'];
                        $user_raw_bonus = (float)$user_stats['raw_bonus_sum'];
                        
                        // Множитель торрентов
                        $user_cap_mul = getTorrentMultiplier($user_torrents, $TORRENT_MULTIPLIER_TYPE, $FLAT_MULTIPLIER);
                        
                        // Теоретический часовой бонус
                        $user_hourly_theoretical = round($user_raw_bonus * $BASE_BONUS * $user_cap_mul, 1);
                        
                        // Часовой кап
                        $user_hourly_capped = min($user_hourly_theoretical, $HOUR_CAP);
                        
                        // Применяем эвристику если включена
                        if ($ENABLE_HEURISTIC) {
                            $heuristicHoursPerDay = getHeuristicHours($user_torrents, [
                                'heuristic_50' => $HEURISTIC_50,
                                'heuristic_40' => $HEURISTIC_40,
                                'heuristic_30' => $HEURISTIC_30,
                                'heuristic_20' => $HEURISTIC_20,
                                'heuristic_10' => $HEURISTIC_10,
                                'heuristic_5' => $HEURISTIC_5,
                                'heuristic_1' => $HEURISTIC_1,
                            ]);
                            
                            $heuristicForInterval = $heuristicHoursPerDay * ($CRON_INTERVAL_HOURS / 24);
                            $user_avg_hours = max($user_avg_hours, $heuristicForInterval);
                        }
                        
                        // Бонус за один запуск крона
                        $user_per_run_bonus = round($user_hourly_capped * $user_avg_hours, 1);
                        
                        // Реальный бонус в час (4 запуска по 15 минут)
                        $user_real_hourly = round($user_per_run_bonus * 4, 1);
                        
                        // Суточная проекция
                        $user_daily_bonus = round($user_real_hourly * 24, 0);
                        
                        // Процент от общего лимита
                        $percent_of_total_cap = ($user_hourly_theoretical / $HOUR_CAP) * 100;
                        if ($percent_of_total_cap > 100) $percent_of_total_cap = 100;
                        
                        // Проверка на лимит
                        $is_capped = $user_hourly_theoretical > $HOUR_CAP;
                    } else {
                        $user_torrents = 0;
                        $user_avg_hours = 0;
                        $user_raw_bonus = 0;
                        $user_cap_mul = 1.0;
                        $user_hourly_theoretical = 0;
                        $user_hourly_capped = 0;
                        $user_per_run_bonus = 0;
                        $user_real_hourly = 0;
                        $user_daily_bonus = 0;
                        $percent_of_total_cap = 0;
                        $is_capped = false;
                    }
                    ?>
                    
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Your current bonus calculation</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($user_torrents > 0): ?>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted">Active torrents</div>
                                        <div class="display-6 text-primary fw-bold"><?php echo $user_torrents; ?></div>
                                        <div class="small text-muted">
                                            Multiplier: ×<?php echo number_format($user_cap_mul, 1); ?>
                                            (<?php echo $TORRENT_MULTIPLIER_TYPE; ?>)
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted">Raw bonus sum</div>
                                        <div class="display-6 text-success fw-bold"><?php echo number_format($user_raw_bonus, 1); ?></div>
                                        <div class="small text-muted">Total of all multipliers</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted">Avg seeding time</div>
                                        <div class="display-6 text-warning fw-bold"><?php echo number_format($user_avg_hours * 60, 1); ?> min</div>
                                        <div class="small text-muted">per calculation</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Hourly bonus calculation</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div class="small text-muted">Theoretical maximum</div>
                                                        <div class="fs-4 fw-bold text-primary">
                                                            <?php echo number_format($user_hourly_theoretical, 0); ?> pts/h
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="small text-muted">System cap</div>
                                                        <div class="fs-4 fw-bold text-warning">
                                                            <?php echo number_format($HOUR_CAP, 0); ?> pts/h
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- ДЕТАЛЬНЫЙ РАСЧЕТ -->
                                            <div class="border rounded p-3 bg-light mb-3">
                                                <div class="row mb-2">
                                                    <div class="col-8">
                                                        <span class="small text-muted">Raw bonus sum</span>
                                                    </div>
                                                    <div class="col-4 text-end">
                                                        <span class="fw-bold"><?php echo number_format($user_raw_bonus, 1); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mb-2">
                                                    <div class="col-8">
                                                        <span class="small">× Base rate (<?php echo $BASE_BONUS; ?>)</span>
                                                    </div>
                                                    <div class="col-4 text-end">
                                                        <span><?php echo number_format($user_raw_bonus * $BASE_BONUS, 1); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mb-2">
                                                    <div class="col-8">
                                                        <span class="small">× Torrents factor (×<?php echo number_format($user_cap_mul, 1); ?>)</span>
                                                    </div>
                                                    <div class="col-4 text-end">
                                                        <span class="fw-bold"><?php echo number_format($user_hourly_theoretical, 1); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <hr class="my-2">
                                                <div class="row">
                                                    <div class="col-8">
                                                        <span class="small text-muted">Theoretical hourly bonus</span>
                                                    </div>
                                                    <div class="col-4 text-end">
                                                        <span class="fw-bold text-primary"><?php echo number_format($user_hourly_theoretical, 1); ?> pts/h</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- ИТОГОВЫЙ ЧАСОВОЙ БОНУС -->
                                            <div class="text-center p-3 border rounded bg-success bg-opacity-10">
                                                <div class="small text-muted">Hourly bonus (after cap)</div>
                                                <div class="display-6 fw-bold text-success mb-2">
                                                    <?php echo number_format($user_hourly_capped, 0); ?> pts/h
                                                </div>
                                                <div class="small text-muted">
                                                    <?php if ($is_capped): ?>
                                                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Capped at system limit</span>
                                                    <?php else: ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> Not capped</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- ПРОГРЕСС-БАР ОТ ОБЩЕГО ЛИМИТА -->
                                            <div class="mt-4">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small>Theoretical vs total cap (<?php echo $HOUR_CAP; ?>/h)</small>
                                                    <small><?php echo number_format($percent_of_total_cap, 1); ?>%</small>
                                                </div>
                                                <div class="progress" style="height: 20px;">
                                                    <?php
                                                    $progress_color = 'success';
                                                    if ($percent_of_total_cap >= 80) {
                                                        $progress_color = 'danger';
                                                    } elseif ($percent_of_total_cap >= 50) {
                                                        $progress_color = 'warning';
                                                    }
                                                    ?>
                                                    <div class="progress-bar bg-<?php echo $progress_color; ?> progress-bar-striped" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $percent_of_total_cap; ?>%"
                                                         aria-valuenow="<?php echo $percent_of_total_cap; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo number_format($user_hourly_theoretical, 0); ?> / <?php echo $HOUR_CAP; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-coins me-2"></i>Your estimated earnings</h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- ОСНОВНОЙ ПОКАЗАТЕЛЬ -->
                                            <div class="text-center mb-4">
                                                <div class="display-3 text-success fw-bold mb-2">
                                                    <?php echo number_format($user_real_hourly, 0); ?>
                                                </div>
                                                <div class="text-muted fs-5">points per hour</div>
                                                <div class="small text-warning mt-2">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Based on current <?php echo number_format($user_avg_hours * 60, 0); ?> min avg seeding time
                                                </div>
                                            </div>
                                            
                                            <!-- КАРТОЧКИ С РАСЧЕТАМИ -->
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <div class="card border-primary h-100 stats-card">
                                                        <div class="card-body text-center p-3">
                                                            <div class="small text-muted mb-1">Per cron run</div>
                                                            <div class="fs-3 fw-bold text-primary"><?php echo number_format($user_per_run_bonus, 1); ?></div>
                                                            <div class="small text-muted">every <?php echo ($CRON_INTERVAL/60); ?> min</div>
                                                        </div>
                                                    </div>
                                                </div>
												
												
												
												<div class="col-6">
                    <div class="card border-success h-100 stats-card">
                        <div class="card-body text-center p-3">
                            <div class="small text-muted mb-1">Per hour</div>
                            <div class="fs-3 fw-bold text-success"><?php echo number_format($user_real_hourly, 0); ?></div>
                            <div class="small text-muted">4 × per run</div>
                            <div class="mt-2 small">
                                <i class="fas fa-torrent text-info"></i>
                                <?php echo $user_torrents; ?> active
                            </div>
                        </div>
                    </div>
                </div>
												
												
												
												
												
												
                                                <div class="col-6">
                                                    <div class="card border-success h-100 stats-card">
                                                        <div class="card-body text-center p-3">
                                                            <div class="small text-muted mb-1">Per day (24h)</div>
                                                            <div class="fs-3 fw-bold text-success"><?php echo number_format($user_daily_bonus, 0); ?></div>
                                                            <div class="small text-muted">if active 24/7</div>
                                                        </div>
                                                    </div>
                                                </div>
												
												
												  <div class="col-6">
                    <div class="card border-danger h-100 stats-card">
                        <div class="card-body text-center p-3">
                            <div class="small text-muted mb-1">Per week</div>
                            <div class="fs-3 fw-bold text-danger"><?php echo number_format($user_daily_bonus * 7, 0); ?></div>
                            <div class="small text-muted">7 days constant</div>
                            <div class="mt-2 small text-muted">
                                <?php echo number_format($user_real_hourly, 0); ?>/h
                            </div>
                        </div>
                    </div>
                </div>
            </div>
			
			
			
			<!-- ПРОГРЕСС БАР АКТИВНОСТИ -->
            <?php if ($user_torrents > 0): ?>
            <div class="mt-3">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Activity level</small>
                    <small>
                        <?php 
                        $activity_percent = min(100, ($user_avg_hours / $CRON_INTERVAL_HOURS) * 100);
                        echo number_format($activity_percent, 0); ?>%
                    </small>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         style="width: <?php echo $activity_percent; ?>%"
                         aria-valuenow="<?php echo $activity_percent; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
                <div class="small text-center text-muted mt-1">
                    <?php echo number_format($user_avg_hours * 60, 0); ?> min / <?php echo ($CRON_INTERVAL/60); ?> min interval
                </div>
            </div>
            <?php endif; ?>
												
												
												
												
												
												
												
                                            
                                            
                                           <!-- СОВЕТ -->
            <div class="alert alert-dark mt-3 small">
                <div class="d-flex">
                    <i class="fas fa-lightbulb text-warning me-2 mt-1"></i>
                    <div>
                        <strong>Tip:</strong> To maximize earnings, seed torrents with leechers, 
                        large files (>20GB), and old torrents (>180 days). Keep seeding for 30+ minutes 
                        to reach maximum potential.
                        <?php if ($user_torrents > 0): ?>
                        <br><span class="text-info mt-1 d-block">
                            <i class="fas fa-chart-line"></i>
                            Your current rate: <?php echo number_format($user_real_hourly, 0); ?>/h, 
                            <?php echo number_format($user_daily_bonus, 0); ?>/day
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
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
                    
                    <!-- СИСТЕМНЫЕ НАСТРОЙКИ -->
                    <div class="card bg-light border mb-4">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Current System Settings</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-primary me-2">Base bonus</span>
                                        <span>Points per hour</span>
                                    </div>
                                    <h5 class="text-success"><?php echo $BASE_BONUS; ?></h5>
                                </div>
                                <div class="col-sm-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-primary me-2">Hour cap</span>
                                        <span>Max per hour</span>
                                    </div>
                                    <h5 class="text-warning"><?php echo $HOUR_CAP; ?></h5>
                                </div>
                                <div class="col-sm-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-primary me-2">Cron interval</span>
                                        <span>Calculation</span>
                                    </div>
                                    <h5 class="text-info"><?php echo ($CRON_INTERVAL/60); ?> min</h5>
                                </div>
                                <div class="col-sm-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-primary me-2">Torrent multiplier</span>
                                        <span>Type</span>
                                    </div>
                                    <h5 class="text-danger"><?php echo ucfirst($TORRENT_MULTIPLIER_TYPE); ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- МНОЖИТЕЛИ -->
                    <h6 class="mt-3 mb-2">Multipliers per torrent:</h6>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <div class="formula-item">
                                <span class="badge bg-info badge-small me-2">Leechers</span>
                                <div class="d-flex justify-content-between">
                                    <span>0 leechers:</span>
                                    <span class="text-success">×<?php echo $LEECH_NONE; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>1-2 leechers:</span>
                                    <span class="text-warning">×<?php echo $LEECH_FEW; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>3+ leechers:</span>
                                    <span class="text-danger fw-bold">×<?php echo $LEECH_MANY; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="formula-item">
                                <span class="badge bg-success badge-small me-2">Size</span>
                                <div class="d-flex justify-content-between">
                                    <span>&lt; 0.5 GB:</span>
                                    <span>×<?php echo $SIZE_SMALL; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>0.5-2 GB:</span>
                                    <span class="text-success">×<?php echo $SIZE_MEDIUM; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>2-8 GB:</span>
                                    <span class="text-warning">×<?php echo $SIZE_LARGE; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>8-20 GB:</span>
                                    <span class="text-danger">×<?php echo $SIZE_XLARGE; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>&gt; 20 GB:</span>
                                    <span class="text-danger fw-bold">×<?php echo $SIZE_HUGE; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="formula-item">
                                <span class="badge bg-warning badge-small me-2">Seeders</span>
                                <div class="d-flex justify-content-between">
                                    <span>≤ 50 seeders:</span>
                                    <span class="text-success">×1.0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>51-100 seeders:</span>
                                    <span>×<?php echo $SEEDERS_MEDIUM; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>&gt; 100 seeders:</span>
                                    <span class="text-muted">×<?php echo $SEEDERS_MANY; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="formula-item">
                                <span class="badge bg-secondary badge-small me-2">Age</span>
                                <div class="d-flex justify-content-between">
                                    <span>&lt; 60 days:</span>
                                    <span>×1.0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>60-180 days:</span>
                                    <span class="text-warning">×<?php echo $AGE_MEDIUM; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>&gt; 180 days:</span>
                                    <span class="text-danger fw-bold">×<?php echo $AGE_OLD; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                   
					
					
					
					
					
					                    <div class="formula-item mt-3 p-3 bg-warning bg-opacity-10 rounded">
                        <span class="badge bg-danger badge-small me-2">Promo bonuses</span>
                        <div class="row mt-2">
                            <div class="col-sm-4">
                                <div class="d-flex justify-content-between">
                                    <span>Freeleech:</span>
                                    <span class="text-success">+<?php echo $PROMO_FREE; ?></span>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="d-flex justify-content-between">
                                    <span>Silver:</span>
                                    <span class="text-success">+<?php echo $PROMO_SILVER; ?></span>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="d-flex justify-content-between">
                                    <span>Double Upload:</span>
                                    <span class="text-success">+<?php echo $PROMO_DOUBLE; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted mt-1">These values are added to 1.0 base</div>
                    </div>
                    
                   
				   
				   
<!-- ВСТАВЛЯЕМ ТРИ НОВЫХ БЛОКА ЗДЕСЬ -->
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
        <!-- РАСЧЕТНЫЙ ПЕРИОД -->
        <div class="card border-primary mb-3">
            <div class="card-header bg-primary text-white py-2">
                <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Calculation period</h6>
            </div>
            <div class="card-body py-2">
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Tracker announce:</span>
                        <span class="fw-bold"><?php echo $seedbonus_settings['announce_interval'] ?? 15; ?> min</span>
                    </div>
                    <small class="text-muted">($ANNOUNCE_INTERVAL = <?php echo ($seedbonus_settings['announce_interval'] ?? 15) * 60; ?>s)</small>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Bonus cron:</span>
                        <span class="fw-bold"><?php echo $seedbonus_settings['cron_interval'] ?? 15; ?> min</span>
                    </div>
                    <small class="text-muted">(Same as announce)</small>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Min counted:</span>
                        <span class="fw-bold"><?php echo $seedbonus_settings['announce_interval'] ?? 15; ?> min</span>
                    </div>
                    <small class="text-muted">
                        (<?php echo round(($seedbonus_settings['announce_interval'] ?? 15) / 60, 2); ?> hours minimum)
                    </small>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Max per run:</span>
                        <span class="fw-bold">
                            <?php echo ($seedbonus_settings['cron_interval'] ?? 15) * 2; ?> min
                        </span>
                    </div>
                    <small class="text-muted">
                        (<?php echo round((($seedbonus_settings['cron_interval'] ?? 15) * 2) / 60, 2); ?> hours maximum)
                    </small>
                </div>
                <hr class="my-2">
                <div class="text-center">
                    <small class="text-muted">
                        You need to seed at least 
                        <strong><?php echo $seedbonus_settings['announce_interval'] ?? 15; ?> minutes</strong> 
                        to get points
                    </small>
                </div>
            </div>
        </div>
        
        <!-- ПРИМЕР РАСЧЕТА -->
        <div class="card bg-success bg-opacity-10 border-success mb-3">
            <div class="card-header border-success py-2">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Example calculation</h6>
            </div>
            <div class="card-body py-2">
                <div class="small mb-2">
                    <strong>Torrent:</strong> 15 GB, 4 leechers, Freeleech, 200 days old, 30 seeders
                </div>
                <div class="bg-white p-2 rounded mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Leechers (4):</span>
                        <span>×<?php echo $LEECH_MANY; ?> (3+ leechers)</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Size (15 GB):</span>
                        <span>×<?php echo $SIZE_XLARGE; ?> (8-20 GB range)</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Seeders (30):</span>
                        <span>×1.0 (≤ 50 seeders)</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Age (200 days):</span>
                        <span>×<?php echo $AGE_OLD; ?> (>180 days)</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Promo (Freeleech):</span>
                        <span>×<?php echo number_format(1.0 + $PROMO_FREE, 1); ?> (+<?php echo $PROMO_FREE; ?>)</span>
                    </div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold">
                        <?php
                        // Расчет общего множителя с текущими настройками
                        $example_multiplier = $LEECH_MANY * $SIZE_XLARGE * 1.0 * $AGE_OLD * (1.0 + $PROMO_FREE);
                        ?>
                        <span>Total multiplier:</span>
                        <span>×<?php echo number_format($example_multiplier, 2); ?></span>
                    </div>
                </div>
                <div class="bg-light p-2 rounded">
                    <div class="d-flex justify-content-between">
                        <span>Base (<?php echo $BASE_BONUS; ?>):</span>
                        <span>×<?php echo $BASE_BONUS; ?></span>
                    </div>
                    <?php
                    $example_hourly = $example_multiplier * $BASE_BONUS;
                    $cron_interval_hours = ($seedbonus_settings['cron_interval'] ?? 15) / 60;
                    $example_per_cron = $example_hourly * $cron_interval_hours;
                    ?>
                    <div class="d-flex justify-content-between">
                        <span>Hourly bonus:</span>
                        <span class="fw-bold"><?php echo number_format($example_hourly, 1); ?>/h</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>For <?php echo $seedbonus_settings['cron_interval'] ?? 15; ?> min:</span>
                        <span class="text-success fw-bold"><?php echo number_format($example_per_cron, 1); ?> pts</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- СОВЕТЫ -->
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark py-2">
                <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Maximization tips</h6>
            </div>
            <div class="card-body py-2">
                <ul class="mb-0 small">
                    <li class="mb-1">
                        <i class="fas fa-fire text-danger me-1"></i>
                        <strong>Seed torrents with leechers</strong> - biggest bonus!
                        <span class="text-success">(×<?php echo $LEECH_MANY; ?> for 3+ leechers)</span>
                    </li>
                    <li class="mb-1">
                        <i class="fas fa-hdd text-success me-1"></i>
                        <strong>Large files (>20GB)</strong>
                        <span class="text-success">(×<?php echo $SIZE_HUGE; ?>)</span>
                    </li>
                    <li class="mb-1">
                        <i class="fas fa-history text-secondary me-1"></i>
                        <strong>Old torrents (>180 days)</strong>
                        <span class="text-success">(×<?php echo $AGE_OLD; ?>)</span>
                    </li>
                    <li class="mb-1">
                        <i class="fas fa-tag text-primary me-1"></i>
                        <strong>Promo torrents</strong> add extra multipliers:
                        <span class="text-success">
                            Freeleech +<?php echo $PROMO_FREE; ?>, 
                            Silver +<?php echo $PROMO_SILVER; ?>, 
                            Double +<?php echo $PROMO_DOUBLE; ?>
                        </span>
                    </li>
                    <li class="mb-1">
                        <i class="fas fa-bell text-info me-1"></i>
                        <strong>Stay active</strong> - send announce every 
                        <?php echo $seedbonus_settings['announce_interval'] ?? 15; ?> min
                    </li>
                    <li>
                        <i class="fas fa-chart-pie text-warning me-1"></i>
                        <?php if ($TORRENT_MULTIPLIER_TYPE === 'penalty'): ?>
                        <strong>1-20 torrents</strong> optimal (×1.0, no penalty)
                        <?php elseif ($TORRENT_MULTIPLIER_TYPE === 'reward'): ?>
                        <strong>50+ torrents</strong> optimal (×1.1+ bonus)
                        <?php elseif ($TORRENT_MULTIPLIER_TYPE === 'neutral'): ?>
                        <strong>1-100 torrents</strong> optimal (×1.0)
                        <?php elseif ($TORRENT_MULTIPLIER_TYPE === 'flat'): ?>
                        <strong>Any amount</strong> (always ×<?php echo $FLAT_MULTIPLIER; ?>)
                        <?php else: ?>
                        <strong>Keep seeding</strong> for maximum points
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div> <!-- Закрываем col-md-4 -->
</div> <!-- Закрываем row mt-4 -->				   
					
					
					
					
					
					
					
					
					
					<!-- ТОРРЕНТЫ МНОЖИТЕЛЬ -->
<div class="formula-item mt-3">
    <span class="badge bg-dark badge-small me-2">Torrents factor</span>
    <span class="badge bg-info badge-small">Type: <?php echo htmlspecialchars(ucfirst($TORRENT_MULTIPLIER_TYPE)); ?></span>
    
    <?php if ($TORRENT_MULTIPLIER_TYPE === 'flat'): ?>
    <!-- FLAT МНОЖИТЕЛЬ -->
    <div class="alert alert-info mt-2">
        <i class="fas fa-equals me-2"></i>
        <strong>Fixed multiplier:</strong> Always ×<?php echo number_format($FLAT_MULTIPLIER, 1); ?> (<?php echo number_format($FLAT_MULTIPLIER * 100, 0); ?>%)
    </div>
    
    <?php elseif ($TORRENT_MULTIPLIER_TYPE === 'penalty'): ?>
    <!-- PENALTY МНОЖИТЕЛЬ -->
    <div class="row mt-2">
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">1-20 torrents</div>
                <div class="fs-5 text-success">100%</div>
                <small>(×1.0)</small>
                <?php if ($user_torrents >= 1 && $user_torrents <= 20): ?>
                <div class="mt-1">
                    <span class="badge bg-success">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">21-50 torrents</div>
                <div class="fs-5 text-warning">90%</div>
                <small>(×0.9)</small>
                <?php if ($user_torrents >= 21 && $user_torrents <= 50): ?>
                <div class="mt-1">
                    <span class="badge bg-warning">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">51-100 torrents</div>
                <div class="fs-5 text-warning">80%</div>
                <small>(×0.8)</small>
                <?php if ($user_torrents >= 51 && $user_torrents <= 100): ?>
                <div class="mt-1">
                    <span class="badge bg-warning">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">100+ torrents</div>
                <div class="fs-5 text-muted">70%</div>
                <small>(×0.7)</small>
                <?php if ($user_torrents > 100): ?>
                <div class="mt-1">
                    <span class="badge bg-secondary">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="text-muted small mt-2">
        <i class="fas fa-info-circle me-1"></i>
        Penalty system: More torrents = lower multiplier. Controls bonus inflation.
    </div>
    
    <?php elseif ($TORRENT_MULTIPLIER_TYPE === 'reward'): ?>
    <!-- REWARD МНОЖИТЕЛЬ -->
    <div class="row mt-2">
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">1-19 torrents</div>
                <div class="fs-5 text-muted">90%</div>
                <small>(×0.9)</small>
                <?php if ($user_torrents >= 1 && $user_torrents <= 19): ?>
                <div class="mt-1">
                    <span class="badge bg-secondary">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">20-49 torrents</div>
                <div class="fs-5 text-success">100%</div>
                <small>(×1.0)</small>
                <?php if ($user_torrents >= 20 && $user_torrents <= 49): ?>
                <div class="mt-1">
                    <span class="badge bg-success">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">50-99 torrents</div>
                <div class="fs-5 text-warning">110%</div>
                <small>(×1.1)</small>
                <?php if ($user_torrents >= 50 && $user_torrents <= 99): ?>
                <div class="mt-1">
                    <span class="badge bg-warning">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">100+ torrents</div>
                <div class="fs-5 text-danger">120%</div>
                <small>(×1.2)</small>
                <?php if ($user_torrents >= 100): ?>
                <div class="mt-1">
                    <span class="badge bg-danger">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="text-muted small mt-2">
        <i class="fas fa-info-circle me-1"></i>
        Reward system: More torrents = higher multiplier. Encourages seeding many torrents.
    </div>
    
    <?php elseif ($TORRENT_MULTIPLIER_TYPE === 'neutral'): ?>
    <!-- NEUTRAL МНОЖИТЕЛЬ -->
    <div class="row mt-2">
        <div class="col-sm-6">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">1-100 torrents</div>
                <div class="fs-5 text-success">100%</div>
                <small>(×1.0)</small>
                <?php if ($user_torrents >= 1 && $user_torrents <= 100): ?>
                <div class="mt-1">
                    <span class="badge bg-success">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="text-center p-2 border rounded bg-light">
                <div class="small text-muted">101+ torrents</div>
                <div class="fs-5 text-warning">90%</div>
                <small>(×0.9)</small>
                <?php if ($user_torrents > 100): ?>
                <div class="mt-1">
                    <span class="badge bg-warning">Your range</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="text-muted small mt-2">
        <i class="fas fa-info-circle me-1"></i>
        Neutral system: Simple penalty only for very high torrent counts (>100).
    </div>
    
    <?php else: ?>
    <!-- UNKNOWN TYPE -->
    <div class="alert alert-warning mt-2">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Unknown multiplier type: <?php echo htmlspecialchars($TORRENT_MULTIPLIER_TYPE); ?>
    </div>
    <?php endif; ?>
    
    <!-- ТЕКУЩИЙ МНОЖИТЕЛЬ ПОЛЬЗОВАТЕЛЯ -->
    <?php if ($user_torrents > 0): ?>
    <div class="alert alert-primary mt-3">
        <div class="row">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user me-2"></i>
                    <div>
                        <strong>Your multiplier:</strong>
                        <span class="fs-4 ms-2">×<?php echo number_format($user_cap_mul, 1); ?></span>
                        <div class="small text-muted">
                            <?php echo $user_torrents; ?> torrents → 
                            <?php echo number_format($user_cap_mul * 100, 0); ?>%
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">
                    <?php if ($TORRENT_MULTIPLIER_TYPE === 'penalty' && $user_cap_mul < 1.0): ?>
                    <i class="fas fa-arrow-down text-warning me-1"></i>
                    Penalty applied for many torrents
                    <?php elseif ($TORRENT_MULTIPLIER_TYPE === 'reward' && $user_cap_mul > 1.0): ?>
                    <i class="fas fa-arrow-up text-success me-1"></i>
                    Bonus for many torrents
                    <?php elseif ($user_cap_mul == 1.0): ?>
                    <i class="fas fa-equals text-info me-1"></i>
                    Standard multiplier
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- СОВЕТ ПО УЛУЧШЕНИЮ -->
    <div class="alert alert-secondary small mt-2">
        <?php
        $next_threshold = null;
        $next_multiplier = null;
        $torrents_needed = null;
        
        if ($TORRENT_MULTIPLIER_TYPE === 'penalty') {
            if ($user_torrents <= 20) {
                $next_threshold = 21;
                $next_multiplier = 0.9;
                $torrents_needed = 21 - $user_torrents;
            } elseif ($user_torrents <= 50) {
                $next_threshold = 51;
                $next_multiplier = 0.8;
                $torrents_needed = 51 - $user_torrents;
            } elseif ($user_torrents <= 100) {
                $next_threshold = 101;
                $next_multiplier = 0.7;
                $torrents_needed = 101 - $user_torrents;
            }
        } elseif ($TORRENT_MULTIPLIER_TYPE === 'reward') {
            if ($user_torrents < 20) {
                $next_threshold = 20;
                $next_multiplier = 1.0;
                $torrents_needed = 20 - $user_torrents;
            } elseif ($user_torrents < 50) {
                $next_threshold = 50;
                $next_multiplier = 1.1;
                $torrents_needed = 50 - $user_torrents;
            } elseif ($user_torrents < 100) {
                $next_threshold = 100;
                $next_multiplier = 1.2;
                $torrents_needed = 100 - $user_torrents;
            }
        }
        ?>
        
        <?php if ($next_threshold): ?>
        <i class="fas fa-bullseye me-1"></i>
        <strong>Next threshold:</strong> 
        Reach <?php echo $next_threshold; ?> torrents for 
        ×<?php echo number_format($next_multiplier, 1); ?> multiplier 
        (need <?php echo $torrents_needed; ?> more torrents)
        <?php else: ?>
        <i class="fas fa-trophy me-1"></i>
        <strong>Maximum multiplier reached!</strong> You're at the highest tier.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
					
					
					
					
					
					
					
					
					
					
					
					
					
					
                </div>
            </div>
        </div>
    </div>

    <?php
    // Display errors and messages via toast
    if (!empty($errors) || !empty($messages)) {
        echo '
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
            <div id="toastContainer">';
        
        foreach ($errors as $e) {
            echo '
            <div class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000">
                <div class="toast-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong class="me-auto">Error</strong>
                    <small>just now</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    '.htmlspecialchars($e).'
                </div>
            </div>';
        }

        foreach ($messages as $m) {
            echo '
            <div class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="toast-header bg-success text-white">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong class="me-auto">Success</strong>
                    <small>just now</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    '.htmlspecialchars($m).'
                </div>
            </div>';
        }
        
        echo '</div>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toasts = document.querySelectorAll(".toast");
            toasts.forEach((toast, index) => {
                const bsToast = new bootstrap.Toast(toast);
                setTimeout(() => {
                    bsToast.show();
                }, index * 300);
            });
        });
        </script>';
    }
    ?>

    

    <!-- КАРТОЧКИ БОНУСОВ -->
    <div class="row g-4 mt-4">
        <?php echo $cards; ?>
    </div>
</div>

<script>
// Auto-refresh stats every 5 minutes
setTimeout(function() {
    window.location.reload();
}, 300000);

// Copy stats to clipboard
function copyStats() {
    const stats = {
        'Hourly Bonus': '<?php echo number_format($user_real_hourly, 0); ?> pts/h',
        'Active Torrents': '<?php echo $user_torrents; ?>',
        'Seeding Time': '<?php echo number_format($user_avg_hours * 60, 0); ?> min',
        'Daily Projection': '<?php echo number_format($user_daily_bonus, 0); ?> pts/day'
    };
    
    const text = Object.entries(stats).map(([key, value]) => `${key}: ${value}`).join('\n');
    
    navigator.clipboard.writeText(text).then(() => {
        alert('Bonus stats copied to clipboard!');
    });
}
</script>

</body>
</html>
<?php
stdfoot();