<?php
declare(strict_types=1);

define('IN_MYBB', 1);

require_once 'global.php';

// Языковой файл страницы: languages/<lang>/mybonus.lang.php
$lang->load('mybonus');

require_once INC_PATH . '/functions_pm.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_bonuslog.php';


// Подстановка {1}, {2}… (а также %1$s, %2$s…) в строки ланга
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        return preg_replace_callback(
            '/\{(\d+)\}|%(\d+)\$s/',
            static function (array $m) use ($args): string {
                $i = (int)($m[1] !== '' ? $m[1] : $m[2]) - 1;
                return array_key_exists($i, $args) ? (string)$args[$i] : $m[0];
            },
            $str
        ) ?? $str;
    }
}

// ── Авторизация ───────────────────────────────────────────
if (!$CURUSER || ($CURUSER['id'] ?? 0) == 0) {
    print_no_permission();
}

$is_mod = is_mod($usergroups);

// ── Доступ к магазину бонусов целиком ───────────────────────;
if (($bonus === 'disable' || $bonus === 'disablesave') && !$is_mod) {
    stderr($lang->global['error'], $lang->mybonus['disabled']);
}





// ── Загрузка настроек из БД ───────────────────────────────
function loadSeedbonusSettings(): array
{
    global $db;

    $res = $db->sql_query_prepared('SELECT setting_key, setting_value, setting_type FROM seedbonus_settings');
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
// max(1, ...): при cron_interval = 0 страница падала на делении на ноль
$CRON_INTERVAL_SEC     = max(1, (int)($cfg['cron_interval']    ?? 15)) * 60;
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
    $messages[] = $lang->mybonus['message1_success'];
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
    // Bonus log (table); the old text log in bonuscomment is kept as it was
    bonus_log($uid, -(int)$b['points'], 'shop', (string)$b['bonusname'], (int)($b['id'] ?? 0) ?: null);
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
    global $errors, $lang;
    // AND seedbonus >= ?: проверка и списание в одном запросе. Раньше баланс
    // проверялся в PHP, и два одновременных клика уводили его в минус.
    $db->sql_query_prepared(
        "UPDATE users SET {$field}, seedbonus = seedbonus - ? WHERE id = ? AND seedbonus >= ?",
        [(int)$b['points'], $uid, (int)$b['points']]
    );
    if ($db->affected_rows()) {
        logBonus($uid, $b);
        $used = true;
    } else {
        $errors[] = $lang->mybonus['flash_not_enough_points'];
    }
}

/** Списать очки, если их хватает. true - списано. */
function spendPoints(int $uid, int $cost): bool
{
    global $db;
    if ($cost < 0) return false;
    $db->sql_query_prepared(
        'UPDATE users SET seedbonus = seedbonus - ? WHERE id = ? AND seedbonus >= ?',
        [$cost, $uid, $cost]
    );
    return (int)$db->affected_rows() === 1;
}

// ── Обработчики покупок ───────────────────────────────────

function handleTitle(int $uid, array $b, bool &$used): void
{
    global $db, $errors, $lang;
    $title = trim((string)($_POST['title'] ?? ''));
    if (mb_strlen($title) < 2)  { $errors[] = $lang->mybonus['flash_title_short']; return; }
    if (mb_strlen($title) > 50) { $errors[] = ags_fmt($lang->mybonus['flash_title_long'], 50); return; }
    $db->sql_query_prepared(
        'UPDATE users SET usertitle = ?, seedbonus = seedbonus - ? WHERE id = ? AND seedbonus >= ?',
        [htmlspecialchars_uni($title), (int)$b['points'], $uid, (int)$b['points']]
    );
    if ($db->affected_rows()) { logBonus($uid, $b); $used = true; }
    else { $errors[] = $lang->mybonus['flash_not_enough_points']; }
}

function handleGift(int $uid, array $b, bool &$used): void
{
    global $db, $errors, $CURUSER, $BASEURL, $points, $lang;

    $gift = (int)($_POST['gift'] ?? 0);
    $to   = trim($_POST['username'] ?? '');

    if ($gift < 1) { $errors[] = $lang->mybonus['flash_gift_invalid']; return; }

    $res    = $db->sql_query_prepared("SELECT id, seedbonus, username FROM users WHERE username = ? AND enabled = 'yes'", [$to]);
    $target = $db->fetch_array($res);
    if (!$target)                          { $errors[] = $lang->mybonus['flash_user_not_found'];          return; }
    // По ID, а не по нику: сравнение ников зависело от регистра
    if ((int)$target['id'] === $uid)       { $errors[] = $lang->mybonus['flash_gift_self']; return; }

    $total = (int)$b['points'] + $gift;
    if ($points < $total) { $errors[] = ags_fmt($lang->mybonus['flash_gift_need'], $total, $points); return; }

    // Сначала списание (с проверкой баланса), и только потом начисление.
    // Раньше было наоборот: получатель получал очки, даже если списать
    // у отправителя не удалось, - очки появлялись из воздуха.
    if (!spendPoints($uid, $total)) { $errors[] = $lang->mybonus['flash_not_enough_points']; return; }
    $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?', [$gift, (int)$target['id']]);

    {
        // Sender: fee + gift in one row (logBonus would log only the fee)
        bonus_log($uid, -$total, 'gift', "Gift to {$target['username']}: " . number_format($gift) . ' + fee ' . number_format((int)$b['points']), (int)$target['id']);
        bonus_log((int)$target['id'], $gift, 'gift', "Gift from {$CURUSER['username']}", $uid, $uid);
        $db->sql_query_prepared(
            "UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment,''), ?) WHERE id = ?",
            [date('Y-m-d H:i:s') . ' — ' . $b['bonusname'] . " (-{$total} pts)\n", $uid]
        );
        $used = true;

        $db->sql_query_prepared(
            "UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment,''), ?) WHERE id = ?",
            ["Gift: {$gift} pts from {$CURUSER['username']}\n", (int)$target['id']]
        );

        $profilelink = $BASEURL . '/' . get_profile_link($uid);
        send_pm([
            'subject' => $lang->mybonus['giftsubject'],
            'message' => ags_fmt(
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
    global $db, $errors, $lang;
    $tid = (int)($_POST['torrentid'] ?? 0);
    if ($tid <= 0) { $errors[] = $lang->mybonus['flash_torrent_invalid']; return; }

    $res   = $db->sql_query_prepared(
        "SELECT uploaded FROM snatched WHERE torrentid = ? AND userid = ? AND finished = 'yes'",
        [(int)$tid, (int)$uid]
    );
    $snatch = $db->fetch_array($res);
    if (!$snatch) { $errors[] = $lang->mybonus['flash_torrent_not_found']; return; }

    // Сначала списание - раньше рейтинг исправлялся, даже если очков не хватило
    if (!spendPoints($uid, (int)$b['points'])) { $errors[] = $lang->mybonus['flash_not_enough_points']; return; }
    $db->sql_query_prepared(
        // GREATEST: если на раздаче уже отдано больше, чем скачано, "исправление"
        // раньше УМЕНЬШАЛО отданное до скачанного - пользователь платил за убыток
        "UPDATE snatched SET uploaded = GREATEST(uploaded, downloaded), seedtime = GREATEST(seedtime, 86400) WHERE torrentid = ? AND userid = ?",
        [(int)$tid, (int)$uid]
    );
    logBonus($uid, $b);
    $used = true;
}

// ── Покупка VIP (временный статус, с автовозвратом через cron_vip_expire.php) ──
function handleVip(int $uid, array $b, bool &$used): void
{
    global $db, $CURUSER;

    $vip_until = TIMENOW + 28 * 86400;
    $old_gid   = (int)$CURUSER['usergroup'];

    global $errors, $lang;

    // Сначала списание вместе со сменой группы; раньше VIP записывался в
    // auto_vip до списания и оставался, если очков не хватило.
    $db->sql_query_prepared(
        'UPDATE users SET usergroup = ?, seedbonus = seedbonus - ? WHERE id = ? AND seedbonus >= ?',
        [UC_VIP, (int)$b['points'], $uid, (int)$b['points']]
    );
    if (!$db->affected_rows()) { $errors[] = $lang->mybonus['flash_not_enough_points']; return; }

    $db->sql_query_prepared(
        'REPLACE INTO auto_vip (userid, vip_until, old_gid) VALUES (?, ?, ?)',
        [$uid, $vip_until, $old_gid]
    );
    logBonus($uid, $b);
    $used = true;
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
    global $CURUSER, $lang;
    $currentTitle = htmlspecialchars_uni($CURUSER['title'] ?? '');
    $lblNew       = $lang->mybonus['lbl_new_title'];
    $btnBuy       = ags_fmt($lang->mybonus['btn_buy_for'], (int)$b['points']);
    $btnCancel    = $lang->mybonus['btn_cancel'];
    showForm($lang->mybonus['sec_buy_title'], 'update_title', 'yes', <<<HTML
<input type="hidden" name="id" value="{$b['id']}">
<div class="mb-3">
    <label class="form-label fw-bold">{$lblNew}</label>
    <input type="text" name="title" class="form-control" value="{$currentTitle}" required>
</div>
<button type="submit" class="btn btn-success">{$btnBuy}</button>
<a href="mybonus.php" class="btn btn-secondary ms-2">{$btnCancel}</a>
HTML);
}

function showGiftForm(array $b): never
{
    global $points, $lang;
    $maxGift   = $points - (int)$b['points'];
    $info      = ags_fmt($lang->mybonus['hint_gift_info'], $points, (int)$b['points'], $maxGift);
    $lblTo     = $lang->mybonus['lbl_gift_to'];
    $phUser    = htmlspecialchars($lang->mybonus['ph_username'], ENT_QUOTES, 'UTF-8');
    $lblAmount = $lang->mybonus['lbl_gift_amount'];
    $btnGift   = $lang->mybonus['btn_gift'];
    $btnCancel = $lang->mybonus['btn_cancel'];
    showForm($lang->mybonus['sec_gift'], 'send_gift', 'yes', <<<HTML
<input type="hidden" name="id" value="{$b['id']}">
<div class="alert alert-info">
    {$info}
</div>
<div class="mb-3">
    <label class="form-label">{$lblTo}</label>
    <input type="text" name="username" class="form-control" placeholder="{$phUser}" required>
</div>
<div class="mb-3">
    <label class="form-label">{$lblAmount}</label>
    <input type="number" name="gift" class="form-control" min="1" max="{$maxGift}" required>
</div>
<button type="submit" class="btn btn-success">{$btnGift}</button>
<a href="mybonus.php" class="btn btn-secondary ms-2">{$btnCancel}</a>
HTML);
}

function showRatioFixForm(array $b): never
{
    global $lang;
    $lblTid    = $lang->mybonus['lbl_torrent_id'];
    $btnFix    = ags_fmt($lang->mybonus['btn_fix_ratio'], (int)$b['points']);
    $btnCancel = $lang->mybonus['btn_cancel'];
    showForm($lang->mybonus['sec_fix_ratio'], 'ratiofix', 'yes', <<<HTML
<input type="hidden" name="id" value="{$b['id']}">
<div class="mb-3">
    <label class="form-label">{$lblTid}</label>
    <input type="number" name="torrentid" class="form-control" required>
</div>
<button type="submit" class="btn btn-warning">{$btnFix}</button>
<a href="mybonus.php" class="btn btn-secondary ms-2">{$btnCancel}</a>
HTML);
}

// ── Обработка POST ────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Формы передавали my_post_key, но он нигде не проверялся: чужой сайт мог
    // отправить от имени пользователя, например, подарок очков на свой ник.
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        stderr($lang->global['error'] ?? 'Error', $lang->mybonus['flash_token_expired']);
    }

    $id    = (int)($_POST['id'] ?? 0);
    $res   = $db->sql_query_prepared('SELECT * FROM bonus WHERE id = ?', [$id]);
    $bonus = $db->fetch_array($res);

    if (!$bonus) {
        $errors[] = $lang->mybonus['error1'];
    } elseif ($points < $bonus['points']) {
        $errors[] = ags_fmt($lang->mybonus['error2'], $points, (int)$bonus['points']);
    } else {
        $used = false;

        switch ($bonus['art']) {
            case 'traffic':
                purchase($userid, 'uploaded = uploaded + ' . (int)$bonus['menge'], $bonus, $used);
                break;
            case 'invite':
                if (($kpsinvite ?? 'no') !== 'yes') {
                    $errors[] = $lang->mybonus['error3'];
                } else {
                    purchase($userid, 'invites = invites + ' . (int)$bonus['menge'], $bonus, $used);
                }
                break;
            case 'title':
                if (($kpstitle ?? 'no') !== 'yes') {
                    $errors[] = $lang->mybonus['error3'];
                } else {
                    isset($_POST['update_title']) ? handleTitle($userid, $bonus, $used) : showTitleForm($bonus);
                }
                break;
            case 'gift_1':
                if (($kpsgift ?? 'no') !== 'yes') {
                    $errors[] = $lang->mybonus['error3'];
                } else {
                    isset($_POST['send_gift']) ? handleGift($userid, $bonus, $used) : showGiftForm($bonus);
                }
                break;
            case 'warning':
                if (($kpswarning ?? 'no') !== 'yes') {
                    $errors[] = $lang->mybonus['error3'];
                } elseif (($CURUSER['timeswarned'] ?? 0) > 0) {
                    $menge = (int)$bonus['menge'];
                    purchase($userid, "timeswarned = IF(timeswarned >= {$menge}, timeswarned - {$menge}, 0)", $bonus, $used);
                } else {
                    // Раньше в этом случае страница просто перезагружалась без объяснений
                    $errors[] = $lang->mybonus['flash_no_warnings'];
                }
                break;
            case 'ratiofix':
                if (($kpsratiofix ?? 'no') !== 'yes') {
                    $errors[] = $lang->mybonus['error3'];
                } else {
                    isset($_POST['ratiofix']) ? handleRatioFix($userid, $bonus, $used) : showRatioFixForm($bonus);
                }
                break;
            case 'class':
                if (($kpsvip ?? 'no') !== 'yes') {
                    $errors[] = $lang->mybonus['error3'];
                } elseif ($is_mod || ($usergroups['isvipgroup'] ?? 'no') === 'yes') {
                    $errors[] = $lang->mybonus['error11'];
                } else {
                    handleVip($userid, $bonus, $used);
                }
                break;
            default:
                $errors[] = $lang->mybonus['flash_unknown_type'];
        }

        if ($used && empty($errors)) {
            $messages[] = ags_fmt($lang->mybonus['message1'], htmlspecialchars_uni((string)$bonus['bonusname']));
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
    $rare1       = (float)($cfg['rare_1']       ?? 1.0);
    $rare3       = (float)($cfg['rare_3']       ?? 1.0);
    $rare5       = (float)($cfg['rare_5']       ?? 1.0);
    $loyal30     = (float)($cfg['loyal_30']     ?? 1.0);
    $loyal90     = (float)($cfg['loyal_90']     ?? 1.0);
    $loyal180    = (float)($cfg['loyal_180']    ?? 1.0);
    $cronHours   = max(1, (int)($cfg['cron_interval'] ?? 15)) / 60;

    $sql = "
        SELECT
            COUNT(DISTINCT p.torrent) AS torrents_count,
            {$cronHours} AS avg_hours_seeded,
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
                     WHEN t.seeders <= 1  THEN {$rare1}
                     WHEN t.seeders <= 3  THEN {$rare3}
                     WHEN t.seeders <= 5  THEN {$rare5}
                     ELSE 1.0 END *
                CASE WHEN COALESCE(st.seedtime, 0) >= 15552000 THEN {$loyal180}
                     WHEN COALESCE(st.seedtime, 0) >= 7776000  THEN {$loyal90}
                     WHEN COALESCE(st.seedtime, 0) >= 2592000  THEN {$loyal30}
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
        LEFT JOIN LATERAL (
            SELECT MAX(s.seedtime) AS seedtime FROM snatched s
            WHERE s.userid = p.userid AND s.torrentid = p.torrent
        ) st ON TRUE
        WHERE p.seeder     = 'yes'
          AND p.userid     = ?
          AND t.visible    = 'yes'
          AND t.banned     = 'no'
          AND t.isnuked    = 'no'
          AND p.last_action >= UNIX_TIMESTAMP() - 2700
        GROUP BY p.userid";

    $res  = $db->sql_query_prepared($sql, [(int)$uid]);
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
    // Было "* 4": верно только при кроне раз в 15 минут
    $realHourly = round($perRun / max(1e-9, $CRON_INTERVAL_HOURS), 1);
    $daily      = round($realHourly * 24);
    // hour_cap = 0 (бонус выключен лимитом) раньше ронял страницу делением на ноль
    $capPct     = $HOUR_CAP > 0 ? min(($hourlyTh / $HOUR_CAP) * 100, 100) : 100;

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
    global $mybb, $lang;
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
    $name     = htmlspecialchars_uni($b['bonusname']);
    $desc     = nl2br(htmlspecialchars_uni($b['description']));
    $opClass  = $disabled ? 'opacity-75' : '';
    $dis      = $disabled ? 'disabled' : '';
    $ptsLabel = ags_fmt($lang->mybonus['val_pts'], (int)$b['points']);
    $btnBuy   = $lang->mybonus['btn_buy'];

    return <<<HTML
<div class="col-md-6 col-xl-4 mb-4">
    <div class="card h-100 shadow-sm border-0 hover-lift {$opClass}">
        <div class="card-header bg-{$bg} text-white">
            <h5 class="mb-0"><i class="fas fa-gem me-2"></i>{$name}</h5>
        </div>
        <div class="card-body d-flex flex-column">
            <p class="text-muted small flex-grow-1">{$desc}</p>
            <div class="mt-auto d-flex justify-content-between align-items-center">
                <span class="badge bg-dark fs-6 px-3 py-2">{$ptsLabel}</span>
                <form method="post" class="d-inline">
                    <input type="hidden" name="id" value="{$b['id']}">
                    <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
                    <button type="submit" class="btn btn-outline-{$bg} btn-sm" {$dis}>{$btnBuy}</button>
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
    global $lang;
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
        return '<div class="alert alert-info mt-2">' . ags_fmt($lang->mybonus['lbl_fixed_multiplier'], number_format($flatMul, 1)) . '</div>';
    }

    $cols = '';
    $colW = (int)(12 / max(1, count($ranges)));
    foreach ($ranges as [$range, $mul, $color, $isUser]) {
        $badge      = $isUser ? "<div class='mt-1'><span class='badge bg-{$color}'>" . $lang->mybonus['lbl_your_range'] . "</span></div>" : '';
        $rangeLabel = ags_fmt($lang->mybonus['lbl_range_torrents'], $range);
        $cols .= <<<HTML
<div class="col-sm-{$colW}">
    <div class="text-center p-2 border rounded bg-light">
        <div class="small text-muted">{$rangeLabel}</div>
        <div class="fs-5 text-{$color}">×{$mul}</div>
        {$badge}
    </div>
</div>
HTML;
    }

    $desc = match($type) {
        'penalty' => $lang->mybonus['tip_mulinfo_penalty'],
        'reward'  => $lang->mybonus['tip_mulinfo_reward'],
        'neutral' => $lang->mybonus['tip_mulinfo_neutral'],
        default   => '',
    };

    return '<div class="row mt-2">' . $cols . '</div>'
         . '<div class="text-muted small mt-2"><i class="fas fa-info-circle me-1"></i>' . $desc . '</div>';
}

/** Подпись типа множителя раздач для вывода (неизвестный тип - как есть, с заглавной). */
function mulTypeLabel(string $type): string
{
    global $lang;
    return match($type) {
        'penalty' => $lang->mybonus['opt_mul_penalty'],
        'reward'  => $lang->mybonus['opt_mul_reward'],
        'neutral' => $lang->mybonus['opt_mul_neutral'],
        'flat'    => $lang->mybonus['opt_mul_flat'],
        default   => ucfirst($type),
    };
}

// ── Сборка карточек ───────────────────────────────────────

// Карта: тип товара (art) → соответствующий kps*-флаг, включающий его
$art_gate_map = [
    'invite'   => $kpsinvite   ?? 'no',
    'title'    => $kpstitle    ?? 'no',
    'class'    => $kpsvip      ?? 'no',
    'gift_1'   => $kpsgift     ?? 'no',
    'warning'  => $kpswarning  ?? 'no',
    'ratiofix' => $kpsratiofix ?? 'no',
    // 'traffic' сюда не входит — у него нет отдельного kps*-флага, всегда доступен
];

$res   = $db->sql_query_prepared('SELECT * FROM bonus ORDER BY points');
$cards = '';
while ($b = $db->fetch_array($res)) {
    // Пропускаем товар, если для его типа есть gate-флаг и он выключен
    if (isset($art_gate_map[$b['art']]) && $art_gate_map[$b['art']] !== 'yes') {
        continue;
    }
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
stdhead(ags_fmt($lang->mybonus['sec_page_title'], $points));

// Подпись типа множителя (обычный текст - экранируем при выводе)
$mulTypeText = mulTypeLabel($TORRENT_MUL_TYPE);

// Строки для JS: ключи js_* из ланга → массив без префикса (AGS_LANG)
$jsLang = [];
foreach ((array)$lang->mybonus as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = (string)$v;
    }
}
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/mybonus.css" type="text/css" media="screen" />
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bonuslog.css?ver=1111">

<div class="container py-5">

    <!-- Заголовок -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary">
            <i class="fas fa-coins me-3"></i><?= $lang->mybonus['sec_my_bonuses'] ?>
        </h1>
        <p class="lead"><?= $lang->mybonus['lbl_you_have'] ?> <strong class="text-success fs-3"><?= ags_fmt($lang->mybonus['val_total_points'], $points) ?></strong></p>
        <p class="text-muted small">
            <?= ags_fmt($lang->mybonus['hint_header_stats'], $BASE_BONUS, $HOUR_CAP, htmlspecialchars($mulTypeText)) ?>
        </p>
        <a class="bl-btn" href="<?= $BASEURL ?>/bonuslog.php"><i class="fa-solid fa-clock-rotate-left"></i><?= $lang->mybonus['btn_bonus_history'] ?></a>
    </div>

    <?php $recent = bonus_log_fetch((int)$CURUSER['id'], null, 5); if ($recent): ?>
    <!-- Recent activity (bonus log) -->
    <div class="bl-card bl-recent">
        <div class="bl-recent__head">
            <h4><i class="fa-solid fa-clock-rotate-left me-2"></i><?= $lang->mybonus['sec_recent_activity'] ?></h4>
            <a href="<?= $BASEURL ?>/bonuslog.php"><?= $lang->mybonus['lbl_view_all'] ?></a>
        </div>
        <?php foreach ($recent as $r): $a = (float)$r['amount']; [, $icon] = BONUS_LOG_TYPES[$r['type']] ?? BONUS_LOG_TYPES['other']; ?>
        <div class="bl-recent__row">
            <span class="bl-sub"><?= date('d.m H:i', (int)$r['added']) ?></span>
            <i class="fa-solid <?= $icon ?> bl-sub"></i>
            <span class="bl-reason"><?= htmlspecialchars((string)$r['reason'], ENT_QUOTES, 'UTF-8') ?></span>
            <strong class="<?= $a >= 0 ? 'bl-plus' : 'bl-minus' ?>"><?= htmlspecialchars(bonus_log_amount($a), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Секция расчёта -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm formula-box">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0 text-primary">
                        <i class="fas fa-calculator me-2"></i><?= $lang->mybonus['sec_how_calculated'] ?>
                    </h4>
                    <small class="text-muted"><?= $lang->mybonus['hint_formula_sub'] ?></small>
                </div>
                <div class="card-body">

                    <!-- Статистика пользователя -->
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i><?= $lang->mybonus['sec_your_calc'] ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($ub['torrents'] > 0): ?>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted"><?= $lang->mybonus['lbl_active_torrents'] ?></div>
                                        <div class="display-6 text-primary fw-bold"><?= $ub['torrents'] ?></div>
                                        <div class="small text-muted">
                                            <?= ags_fmt($lang->mybonus['lbl_multiplier_line'], number_format($ub['capMul'], 1), htmlspecialchars($mulTypeText)) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted"><?= $lang->mybonus['lbl_raw_bonus_sum'] ?></div>
                                        <div class="display-6 text-success fw-bold"><?= number_format($ub['rawBonus'], 1) ?></div>
                                        <div class="small text-muted"><?= $lang->mybonus['hint_total_multipliers'] ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded bg-light mb-3 stats-card">
                                        <div class="small text-muted"><?= $lang->mybonus['lbl_avg_seed_time'] ?></div>
                                        <div class="display-6 text-warning fw-bold"><?= ags_fmt($lang->mybonus['val_minutes'], number_format($ub['avgHours'] * 60, 1)) ?></div>
                                        <div class="small text-muted"><?= $lang->mybonus['hint_per_calculation'] ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <!-- Левая колонка: расчёт -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i><?= $lang->mybonus['sec_hourly_calc'] ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <div class="small text-muted"><?= $lang->mybonus['lbl_theoretical_max'] ?></div>
                                                    <div class="fs-4 fw-bold text-primary"><?= ags_fmt($lang->mybonus['val_pts_h'], number_format($ub['hourlyTheoretical'], 0)) ?></div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="small text-muted"><?= $lang->mybonus['lbl_system_cap'] ?></div>
                                                    <div class="fs-4 fw-bold text-warning"><?= ags_fmt($lang->mybonus['val_pts_h'], number_format($HOUR_CAP, 0)) ?></div>
                                                </div>
                                            </div>

                                            <div class="border rounded p-3 bg-light mb-3">
                                                <?php
                                                $rows = [
                                                    [$lang->mybonus['lbl_raw_bonus_sum'], number_format($ub['rawBonus'], 1)],
                                                    [ags_fmt($lang->mybonus['lbl_row_base_rate'], $BASE_BONUS), number_format($ub['rawBonus'] * $BASE_BONUS, 1)],
                                                    [ags_fmt($lang->mybonus['lbl_row_torrents_factor'], number_format($ub['capMul'], 1)), number_format($ub['hourlyTheoretical'], 1)],
                                                ];
                                                foreach ($rows as [$label, $val]):
                                                ?>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="small"><?= htmlspecialchars($label) ?></span>
                                                    <span><?= $val ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between">
                                                    <span class="small text-muted"><?= $lang->mybonus['lbl_theoretical_hourly'] ?></span>
                                                    <span class="fw-bold text-primary"><?= ags_fmt($lang->mybonus['val_pts_h'], number_format($ub['hourlyTheoretical'], 1)) ?></span>
                                                </div>
                                            </div>

                                            <div class="text-center p-3 border rounded bg-success bg-opacity-10 mb-3">
                                                <div class="small text-muted"><?= $lang->mybonus['lbl_hourly_after_cap'] ?></div>
                                                <div class="display-6 fw-bold text-success mb-1">
                                                    <?= ags_fmt($lang->mybonus['val_pts_h'], number_format($ub['hourlyCapped'], 0)) ?>
                                                </div>
                                                <?php if ($ub['isCapped']): ?>
                                                <span class="text-warning small"><i class="fas fa-exclamation-triangle me-1"></i><?= $lang->mybonus['lbl_capped'] ?></span>
                                                <?php else: ?>
                                                <span class="text-success small"><i class="fas fa-check-circle me-1"></i><?= $lang->mybonus['lbl_not_capped'] ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <?php
                                            $pct   = $ub['capPct'];
                                            $pColor = progressColor($pct);
                                            ?>
                                            <div class="d-flex justify-content-between mb-1">
                                                <small><?= ags_fmt($lang->mybonus['lbl_theory_vs_cap'], $HOUR_CAP) ?></small>
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
                                            <h6 class="mb-0"><i class="fas fa-coins me-2"></i><?= $lang->mybonus['sec_est_earnings'] ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="text-center mb-4">
                                                <div class="display-3 text-success fw-bold"><?= number_format($ub['realHourly'], 0) ?></div>
                                                <div class="text-muted fs-5"><?= $lang->mybonus['lbl_points_per_hour'] ?></div>
                                                <div class="small text-warning mt-1">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= ags_fmt($lang->mybonus['hint_based_on'], number_format($ub['avgHours'] * 60, 0)) ?>
                                                </div>
                                            </div>

                                            <?php
                                            $earnCards = [
                                                ['primary', $lang->mybonus['lbl_per_run'],  number_format($ub['perRun'], 1),      ags_fmt($lang->mybonus['hint_per_run'], $cronMin)],
                                                ['success', $lang->mybonus['lbl_per_hour'], number_format($ub['realHourly'], 0),  $lang->mybonus['hint_per_hour']],
                                                ['success', $lang->mybonus['lbl_per_day'],  number_format($ub['daily'], 0),        $lang->mybonus['hint_per_day']],
                                                ['danger',  $lang->mybonus['lbl_per_week'], number_format($ub['daily'] * 7, 0),   $lang->mybonus['hint_per_week']],
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
                                                <small class="text-muted"><?= $lang->mybonus['lbl_activity_level'] ?></small>
                                                <small><?= number_format($actPct, 0) ?>%</small>
                                            </div>
                                            <div class="progress mb-1" style="height:8px">
                                                <div class="progress-bar bg-info progress-bar-striped progress-bar-animated"
                                                     style="width:<?= $actPct ?>%"></div>
                                            </div>
                                            <div class="small text-center text-muted">
                                                <?= ags_fmt($lang->mybonus['hint_activity'], number_format($ub['avgHours'] * 60, 0), $cronMin) ?>
                                            </div>

                                            <div class="alert alert-dark mt-3 small">
                                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                                <?= $lang->mybonus['hint_tip_html'] ?>
                                                <span class="text-info d-block mt-1">
                                                    <?= ags_fmt($lang->mybonus['lbl_current_rate'], number_format($ub['realHourly'], 0), number_format($ub['daily'], 0)) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= $lang->mybonus['hint_no_torrents'] ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Системные настройки -->
                    <div class="card bg-light border mb-4">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-cogs me-2"></i><?= $lang->mybonus['sec_system_settings'] ?></h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php
                                $sysSettings = [
                                    ['primary', $lang->mybonus['lbl_set_base'], $lang->mybonus['hint_set_base'], $BASE_BONUS, 'success'],
                                    ['primary', $lang->mybonus['lbl_set_cap'],  $lang->mybonus['hint_set_cap'],  $HOUR_CAP,   'warning'],
                                    ['primary', $lang->mybonus['lbl_set_cron'], $lang->mybonus['hint_set_cron'], ags_fmt($lang->mybonus['val_minutes'], $cronMin), 'info'],
                                    ['primary', $lang->mybonus['lbl_set_mul'],  $lang->mybonus['hint_set_mul'],  htmlspecialchars($mulTypeText), 'danger'],
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
                    <h6 class="mt-3 mb-2"><?= $lang->mybonus['sec_multipliers'] ?></h6>
                    <div class="row g-2">
                        <?php
                        $multiplierGroups = [
                            ['info',      $lang->mybonus['grp_leechers'], [
                                [$lang->mybonus['rng_leech_0'],    '×' . ($cfg['leech_none'] ?? 1.2), 'success'],
                                [$lang->mybonus['rng_leech_few'],  '×' . ($cfg['leech_few']  ?? 1.5), 'warning'],
                                [$lang->mybonus['rng_leech_many'], '×' . ($cfg['leech_many'] ?? 1.8), 'danger', true],
                            ]],
                            ['success',   $lang->mybonus['grp_size'], [
                                [$lang->mybonus['rng_size_small'],  '×' . ($cfg['size_small']  ?? 1.0), ''],
                                [$lang->mybonus['rng_size_medium'], '×' . ($cfg['size_medium'] ?? 1.2), 'success'],
                                [$lang->mybonus['rng_size_large'],  '×' . ($cfg['size_large']  ?? 1.5), 'warning'],
                                [$lang->mybonus['rng_size_xlarge'], '×' . ($cfg['size_xlarge'] ?? 1.8), 'danger'],
                                [$lang->mybonus['rng_size_huge'],   '×' . ($cfg['size_huge']   ?? 2.0), 'danger', true],
                            ]],
                            ['warning',   $lang->mybonus['grp_seeders'], [
                                ['≤ 50',    '×1.0', 'success'],
                                ['51-100',  '×' . ($cfg['seeders_medium'] ?? 0.95), ''],
                                ['> 100',   '×' . ($cfg['seeders_many']   ?? 0.9),  'muted'],
                            ]],
                            ['secondary', $lang->mybonus['grp_age'], [
                                [$lang->mybonus['rng_age_new'],    '×1.0', ''],
                                [$lang->mybonus['rng_age_medium'], '×' . ($cfg['age_medium'] ?? 1.3), 'warning'],
                                [$lang->mybonus['rng_age_old'],    '×' . ($cfg['age_old']    ?? 1.5), 'danger', true],
                            ]],
                        ];
                        // Редкость и верность - показываем, только если включены (не 1.0)
                        $rareOn  = max((float)($cfg['rare_1'] ?? 1), (float)($cfg['rare_3'] ?? 1), (float)($cfg['rare_5'] ?? 1)) > 1.0;
                        $loyalOn = max((float)($cfg['loyal_30'] ?? 1), (float)($cfg['loyal_90'] ?? 1), (float)($cfg['loyal_180'] ?? 1)) > 1.0;
                        if ($rareOn) $multiplierGroups[] = ['primary', $lang->mybonus['grp_rarity'], [
                                [$lang->mybonus['rng_rare_1'], '×' . ($cfg['rare_1'] ?? 1.0), 'danger', true],
                                [$lang->mybonus['rng_rare_3'], '×' . ($cfg['rare_3'] ?? 1.0), 'warning'],
                                [$lang->mybonus['rng_rare_5'], '×' . ($cfg['rare_5'] ?? 1.0), 'success'],
                            ]];
                        if ($loyalOn) $multiplierGroups[] = ['success', $lang->mybonus['grp_loyalty'], [
                                [$lang->mybonus['rng_loyal_30'],  '×' . ($cfg['loyal_30']  ?? 1.0), 'success'],
                                [$lang->mybonus['rng_loyal_90'],  '×' . ($cfg['loyal_90']  ?? 1.0), 'warning'],
                                [$lang->mybonus['rng_loyal_180'], '×' . ($cfg['loyal_180'] ?? 1.0), 'danger', true],
                            ]];
                        foreach ($multiplierGroups as [$color, $label, $rows]):
                        ?>
                        <div class="col-sm-6">
                            <div class="formula-item">
                                <span class="badge bg-<?= $color ?> badge-small me-2"><?= htmlspecialchars($label) ?></span>
                                <?php foreach ($rows as $row):
                                    [$rLabel, $rVal, $rColor] = $row;
                                    $fw = !empty($row[3]) ? ' fw-bold' : '';
                                    $tc = $rColor ? ' text-' . $rColor : '';
                                ?>
                                <div class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($rLabel) ?>:</span>
                                    <span class="<?= $tc . $fw ?>"><?= $rVal ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Промо-бонусы -->
                    <div class="formula-item mt-3 p-3 bg-warning bg-opacity-10 rounded">
                        <span class="badge bg-danger badge-small me-2"><?= $lang->mybonus['sec_promo'] ?></span>
                        <div class="row mt-2">
                            <?php
                            $promos = [
                                [$lang->mybonus['lbl_promo_free'],   $cfg['promo_free']   ?? 0.7],
                                [$lang->mybonus['lbl_promo_silver'], $cfg['promo_silver'] ?? 0.5],
                                [$lang->mybonus['lbl_promo_double'], $cfg['promo_double'] ?? 0.5],
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
                        <div class="small text-muted mt-1"><?= $lang->mybonus['hint_promo_base'] ?></div>
                    </div>

                    <!-- Базовая формула + пример + советы -->
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <h5 class="mb-3"><?= $lang->mybonus['sec_basic_formula'] ?></h5>
                            <div class="alert alert-success py-2 mb-3">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <strong><?= $lang->mybonus['lbl_formula_hourly'] ?></strong> <?= $lang->mybonus['txt_formula_hourly'] ?>
                                    </div>
                                    <div class="col-sm-6">
                                        <strong><?= $lang->mybonus['lbl_formula_final'] ?></strong> <?= $lang->mybonus['txt_formula_final'] ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">

                            <!-- Период расчёта -->
                            <div class="card border-primary mb-3">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i><?= $lang->mybonus['sec_calc_period'] ?></h6>
                                </div>
                                <div class="card-body py-2">
                                    <?php
                                    $announceMin = (int)($cfg['announce_interval'] ?? 15);
                                    $periodRows  = [
                                        [$lang->mybonus['lbl_period_announce'], ags_fmt($lang->mybonus['val_minutes'], $announceMin), null],
                                        [$lang->mybonus['lbl_period_cron'],     ags_fmt($lang->mybonus['val_minutes'], $cronMin),     null],
                                        [$lang->mybonus['lbl_period_min'],      ags_fmt($lang->mybonus['val_minutes'], $announceMin), ags_fmt($lang->mybonus['hint_hours_min'], round($announceMin / 60, 2))],
                                        [$lang->mybonus['lbl_period_max'],      ags_fmt($lang->mybonus['val_minutes'], $cronMin * 2), ags_fmt($lang->mybonus['hint_hours_max'], round(($cronMin * 2) / 60, 2))],
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
                                            <?= ags_fmt($lang->mybonus['hint_seed_at_least'], $announceMin) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Пример расчёта -->
                            <div class="card bg-success bg-opacity-10 border-success mb-3">
                                <div class="card-header border-success py-2">
                                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i><?= $lang->mybonus['sec_example'] ?></h6>
                                </div>
                                <div class="card-body py-2">
                                    <div class="small mb-2">
                                        <strong><?= $lang->mybonus['lbl_ex_torrent'] ?></strong> <?= $lang->mybonus['txt_ex_torrent'] ?>
                                    </div>
                                    <div class="bg-white p-2 rounded mb-2">
                                        <?php
                                        $exRows = [
                                            [$lang->mybonus['lbl_ex_leechers'], ags_fmt($lang->mybonus['txt_ex_leechers'], (string)($cfg['leech_many']  ?? 1.8))],
                                            [$lang->mybonus['lbl_ex_size'],     ags_fmt($lang->mybonus['txt_ex_size'],     (string)($cfg['size_xlarge'] ?? 1.8))],
                                            [$lang->mybonus['lbl_ex_seeders'],  ags_fmt($lang->mybonus['txt_ex_seeders'],  '1.0')],
                                            [$lang->mybonus['lbl_ex_age'],      ags_fmt($lang->mybonus['txt_ex_age'],      (string)($cfg['age_old']     ?? 1.5))],
                                            [$lang->mybonus['lbl_ex_promo'],    '×' . number_format(1.0 + (float)($cfg['promo_free'] ?? 0.7), 1) . ' (+' . ($cfg['promo_free'] ?? 0.7) . ')'],
                                        ];
                                        foreach ($exRows as [$el, $ev]):
                                        ?>
                                        <div class="d-flex justify-content-between">
                                            <span><?= htmlspecialchars($el) ?>:</span>
                                            <span><?= htmlspecialchars($ev) ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                        <hr class="my-1">
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span><?= $lang->mybonus['lbl_ex_total'] ?></span>
                                            <span>×<?= number_format($exampleMul, 2) ?></span>
                                        </div>
                                    </div>
                                    <div class="bg-light p-2 rounded">
                                        <div class="d-flex justify-content-between">
                                            <span><?= ags_fmt($lang->mybonus['lbl_ex_base'], $BASE_BONUS) ?></span>
                                            <span>×<?= $BASE_BONUS ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span><?= $lang->mybonus['lbl_ex_hourly'] ?></span>
                                            <span class="fw-bold"><?= ags_fmt($lang->mybonus['val_per_h'], number_format($exampleHourly, 1)) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span><?= ags_fmt($lang->mybonus['lbl_ex_for_period'], $cronMin) ?></span>
                                            <span class="text-success fw-bold"><?= ags_fmt($lang->mybonus['val_pts'], number_format($examplePerCron, 1)) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Советы -->
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark py-2">
                                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i><?= $lang->mybonus['sec_tips'] ?></h6>
                                </div>
                                <div class="card-body py-2">
                                    <ul class="mb-0 small">
                                        <?php
                                        $tips = [
                                            ['fa-fire text-danger',      $lang->mybonus['tip_leechers'], ags_fmt($lang->mybonus['tip_leechers_note'], (string)($cfg['leech_many'] ?? 1.8))],
                                            ['fa-hdd text-success',      $lang->mybonus['tip_large'],    '×' . ($cfg['size_huge']  ?? 2.0)],
                                            ['fa-history text-secondary',$lang->mybonus['tip_old'],      '×' . ($cfg['age_old']    ?? 1.5)],
                                            ['fa-tag text-primary',      $lang->mybonus['tip_promo'],
                                                ags_fmt($lang->mybonus['tip_promo_note'],
                                                    (string)($cfg['promo_free']   ?? 0.7),
                                                    (string)($cfg['promo_silver'] ?? 0.5),
                                                    (string)($cfg['promo_double'] ?? 0.5))],
                                            ['fa-bell text-info',        ags_fmt($lang->mybonus['tip_announce'], $announceMin), ''],
                                        ];
                                        if ((float)($cfg['rare_1'] ?? 1) > 1.0) {
                                            $tips[] = ['fa-gem text-primary', $lang->mybonus['tip_rare'], ags_fmt($lang->mybonus['tip_rare_note'], (string)$cfg['rare_1'])];
                                        }
                                        if ((float)($cfg['loyal_180'] ?? 1) > 1.0) {
                                            $tips[] = ['fa-heart text-danger', $lang->mybonus['tip_loyal'], ags_fmt($lang->mybonus['tip_loyal_note'], (string)$cfg['loyal_180'])];
                                        }
                                        foreach ($tips as [$ic, $text, $note]):
                                        ?>
                                        <li class="mb-1">
                                            <i class="fas <?= $ic ?> me-1"></i>
                                            <strong><?= htmlspecialchars($text) ?></strong>
                                            <?php if ($note): ?>
                                            <span class="text-success">(<?= htmlspecialchars($note) ?>)</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                        <li>
                                            <i class="fas fa-chart-pie text-warning me-1"></i>
                                            <?= match($TORRENT_MUL_TYPE) {
                                                'penalty' => $lang->mybonus['tip_opt_penalty'],
                                                'reward'  => $lang->mybonus['tip_opt_reward'],
                                                'neutral' => $lang->mybonus['tip_opt_neutral'],
                                                'flat'    => ags_fmt($lang->mybonus['tip_opt_flat'], (string)$FLAT_MULTIPLIER),
                                                default   => $lang->mybonus['tip_opt_default'],
                                            } ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Торрент-множитель -->
                    <div class="formula-item mt-3">
                        <span class="badge bg-dark badge-small me-2"><?= $lang->mybonus['lbl_torrents_factor'] ?></span>
                        <span class="badge bg-info badge-small"><?= ags_fmt($lang->mybonus['lbl_type'], htmlspecialchars($mulTypeText)) ?></span>
                        <?= renderTorrentMultiplierTable($TORRENT_MUL_TYPE, $ub['torrents'], $FLAT_MULTIPLIER) ?>

                        <?php if ($ub['torrents'] > 0): ?>
                        <div class="alert alert-primary mt-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <i class="fas fa-user me-2"></i>
                                    <strong><?= $lang->mybonus['lbl_your_multiplier'] ?></strong>
                                    <span class="fs-4 ms-2">×<?= number_format($ub['capMul'], 1) ?></span>
                                    <div class="small text-muted">
                                        <?= ags_fmt($lang->mybonus['hint_torrents_to_pct'], $ub['torrents'], number_format($ub['capMul'] * 100, 0)) ?>
                                    </div>
                                </div>
                                <div class="col-md-6 small text-muted">
                                    <?= match(true) {
                                        $TORRENT_MUL_TYPE === 'penalty' && $ub['capMul'] < 1.0 =>
                                            '<i class="fas fa-arrow-down text-warning me-1"></i>' . $lang->mybonus['txt_mul_penalty'],
                                        $TORRENT_MUL_TYPE === 'reward'  && $ub['capMul'] > 1.0 =>
                                            '<i class="fas fa-arrow-up text-success me-1"></i>' . $lang->mybonus['txt_mul_reward'],
                                        default =>
                                            '<i class="fas fa-equals text-info me-1"></i>' . $lang->mybonus['txt_mul_standard'],
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
                            <strong><?= $lang->mybonus['lbl_next_threshold'] ?></strong>
                            <?= ags_fmt($lang->mybonus['txt_next_threshold'], $nt, number_format($nm, 1), $need) ?>
                            <?php else: ?>
                            <i class="fas fa-trophy me-1"></i>
                            <strong><?= $lang->mybonus['txt_max_multiplier'] ?></strong>
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

<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script src="<?= $BASEURL ?>/scripts/mybonus.js?ver=2"></script>
