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





// Настройки и общие множители — одни на mybonus.php и details.php
require_once INC_PATH . '/functions_seedbonus_estimate.php';

$cfg = sbe_settings();

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
    global $mybb, $BASEURL, $points, $lang;
    stdhead($title);
    $pts = ags_fmt($lang->mybonus['val_total_points'], number_format((int)$points));
    echo <<<HTML
<link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/st-ui.css?ver=12">
<link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/mybonus.css?ver=3">
<div class="container-md st-page bn-page py-3">
    <form method="post" class="st-card bn-form">
        <div class="st-card__head"><i class="fa-solid fa-cart-shopping"></i>{$title}
            <span class="st-badge st-soft-warning ms-auto"><i class="fa-solid fa-coins"></i>{$pts}</span>
        </div>
        <div class="st-card__body">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
            <input type="hidden" name="{$hiddenName}" value="{$hiddenValue}">
            {$body}
        </div>
    </form>
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
<div class="d-flex flex-wrap gap-2 justify-content-end"><a href="mybonus.php" class="btn btn-outline-secondary st-pill">{$btnCancel}</a><button type="submit" class="btn btn-success st-pill">{$btnBuy}</button></div>
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
<div class="bn-note mb-3"><i class="fa-solid fa-circle-info"></i><div>{$info}</div></div>
<div class="mb-3">
    <label class="form-label">{$lblTo}</label>
    <input type="text" name="username" class="form-control" placeholder="{$phUser}" required>
</div>
<div class="mb-3">
    <label class="form-label">{$lblAmount}</label>
    <input type="number" name="gift" class="form-control" min="1" max="{$maxGift}" required>
</div>
<div class="d-flex flex-wrap gap-2 justify-content-end"><a href="mybonus.php" class="btn btn-outline-secondary st-pill">{$btnCancel}</a><button type="submit" class="btn btn-success st-pill">{$btnGift}</button></div>
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
<div class="d-flex flex-wrap gap-2 justify-content-end"><a href="mybonus.php" class="btn btn-outline-secondary st-pill">{$btnCancel}</a><button type="submit" class="btn btn-warning st-pill">{$btnFix}</button></div>
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
            $messages[] = ags_fmt($lang->mybonus['message1'], htmlspecialchars_uni(bonusItemText($bonus)[0]));
            $points -= (int)$bonus['points'];
            $CURUSER['seedbonus'] = $points;
            // PRG паттерн — редирект чтобы повторный F5 не отправлял POST
            header('Location: mybonus.php?purchased=1');
            exit;
        }
    }
}

// ── Расчёт бонуса пользователя ────────────────────────────

/**
 * Every torrent the user is seeding right now, with each multiplier as its own column.
 * The total (raw_bonus_sum) is summed in PHP from these rows, so the per-torrent table
 * and the totals above it always agree. Same formula as before, one query.
 */
function getUserSeeding(int $uid, array $cfg): array
{
    global $db;

    $f = static fn(string $k, float $d): float => (float)($cfg[$k] ?? $d);

    $leechNone   = $f('leech_none', 1.2);   $leechFew   = $f('leech_few', 1.5);    $leechMany  = $f('leech_many', 1.8);
    $sizeSmall   = $f('size_small', 1.0);   $sizeMedium = $f('size_medium', 1.2);  $sizeLarge  = $f('size_large', 1.5);
    $sizeXlarge  = $f('size_xlarge', 1.8);  $sizeHuge   = $f('size_huge', 2.0);
    $seedersMany = $f('seeders_many', 0.9); $seedersMed = $f('seeders_medium', 0.95);
    $rare1       = $f('rare_1', 1.0);       $rare3      = $f('rare_3', 1.0);       $rare5      = $f('rare_5', 1.0);
    $loyal30     = $f('loyal_30', 1.0);     $loyal90    = $f('loyal_90', 1.0);     $loyal180   = $f('loyal_180', 1.0);
    $ageOld      = $f('age_old', 1.5);      $ageMed     = $f('age_medium', 1.3);
    $promoFree   = $f('promo_free', 0.7);   $promoSilver = $f('promo_silver', 0.5); $promoDouble = $f('promo_double', 0.5);

    $sql = "
        SELECT
            t.id, t.name, t.size, t.seeders, t.leechers, t.added,
            t.free, t.silver, t.doubleupload,
            COALESCE(st.seedtime, 0) AS seedtime,
            CASE WHEN t.leechers = 0 THEN {$leechNone}
                 WHEN t.leechers <= 2 THEN {$leechFew}
                 ELSE {$leechMany} END AS f_leech,
            CASE WHEN t.size < 536870912   THEN {$sizeSmall}
                 WHEN t.size < 2147483648  THEN {$sizeMedium}
                 WHEN t.size < 8589934592  THEN {$sizeLarge}
                 WHEN t.size < 21474836480 THEN {$sizeXlarge}
                 ELSE {$sizeHuge} END AS f_size,
            CASE WHEN t.seeders > 100 THEN {$seedersMany}
                 WHEN t.seeders > 50  THEN {$seedersMed}
                 WHEN t.seeders <= 1  THEN {$rare1}
                 WHEN t.seeders <= 3  THEN {$rare3}
                 WHEN t.seeders <= 5  THEN {$rare5}
                 ELSE 1.0 END AS f_seed,
            CASE WHEN COALESCE(st.seedtime, 0) >= 15552000 THEN {$loyal180}
                 WHEN COALESCE(st.seedtime, 0) >= 7776000  THEN {$loyal90}
                 WHEN COALESCE(st.seedtime, 0) >= 2592000  THEN {$loyal30}
                 ELSE 1.0 END AS f_loyal,
            CASE WHEN (UNIX_TIMESTAMP() - t.added) > 15552000 THEN {$ageOld}
                 WHEN (UNIX_TIMESTAMP() - t.added) > 5184000  THEN {$ageMed}
                 ELSE 1.0 END AS f_age,
            (1.0 + (t.free = 'yes') * {$promoFree}
                 + (t.silver = 'yes') * {$promoSilver}
                 + (t.doubleupload = 'yes') * {$promoDouble}) AS f_promo
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
          AND p.last_action >= UNIX_TIMESTAMP() - 2700";

    $res  = $db->sql_query_prepared($sql, [$uid]);
    $rows = [];
    while ($res && ($r = $db->fetch_array($res))) {
        $tid = (int)$r['id'];
        if (isset($rows[$tid])) {
            continue;   // same torrent from two clients: counted once
        }
        $r['mul'] = (float)$r['f_leech'] * (float)$r['f_size'] * (float)$r['f_seed']
                  * (float)$r['f_loyal'] * (float)$r['f_age'] * (float)$r['f_promo'];
        $rows[$tid] = $r;
    }
    return array_values($rows);
}

/** Totals in the shape calcUserBonus() expects. */
function getUserStats(array $seeding, array $cfg): array
{
    if (!$seeding) {
        return [];
    }
    return [
        'torrents_count'   => count($seeding),
        'avg_hours_seeded' => max(1, (int)($cfg['cron_interval'] ?? 15)) / 60,
        'raw_bonus_sum'    => array_sum(array_column($seeding, 'mul')),
    ];
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
    $capMul    = sbe_count_multiplier($torrents, $TORRENT_MUL_TYPE, $FLAT_MULTIPLIER);
    $hourlyTh  = round($rawBonus * $BASE_BONUS * $capMul, 1);
    $hourlyCap = min($hourlyTh, $HOUR_CAP);

    if ($ENABLE_HEURISTIC) {
        $hHours   = sbe_heuristic_hours($torrents, $cfg) * ($CRON_INTERVAL_HOURS / 24);
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

$seeding   = getUserSeeding($userid, $cfg);
$userStats = getUserStats($seeding, $cfg);
$ub        = calcUserBonus($userStats, $cfg);  // $ub = userBonus

// Per-torrent share: each torrent gets (its multiplier / sum of all) of the real hourly rate,
// so the hour cap and the torrent-count factor are spread over the torrents proportionally
// and the column adds up exactly to the "points per hour" shown above.
if ($seeding && $ub['rawBonus'] > 0) {
    foreach ($seeding as &$t) {
        $t['share']  = $t['mul'] / $ub['rawBonus'];
        $t['hourly'] = $ub['realHourly'] * $t['share'];
    }
    unset($t);
    usort($seeding, fn($a, $b) => $b['hourly'] <=> $a['hourly']);
}

// ── Рендер бонусных карточек ──────────────────────────────

/**
 * Item name/description in the user's language. Items are stored in the DB in English,
 * so text comes from lang keys by type (art): item_<art> / item_<art>_desc, {1} = amount (menge).
 * No key for the type (or an empty one) -> the DB text as is.
 */
function bonusItemText(array $b): array
{
    global $lang;
    $art  = (string)($b['art'] ?? '');
    $L    = (array)$lang->mybonus;
    $amt  = (int)($b['menge'] ?? 0);
    $amtS = $art === 'traffic' ? mksize($amt) : number_format($amt);
    $name = !empty($L['item_' . $art])           ? ags_fmt($L['item_' . $art], $amtS)           : (string)$b['bonusname'];
    $desc = !empty($L['item_' . $art . '_desc']) ? ags_fmt($L['item_' . $art . '_desc'], $amtS) : (string)$b['description'];
    return [$name, $desc];
}

function renderBonusCard(array $b, int $points): string
{
    global $mybb, $lang;
    $price    = (int)$b['points'];
    $disabled = $points < $price;
    [$icon, $tone] = match($b['art']) {
        'traffic'  => ['fa-cloud-arrow-up',      'success'],
        'invite'   => ['fa-envelope-open-text',  'info'],
        'title'    => ['fa-signature',           'warning'],
        'gift_1'   => ['fa-gift',                'danger'],
        'warning'  => ['fa-shield-halved',       'muted'],
        'ratiofix' => ['fa-scale-balanced',      'primary'],
        'class'    => ['fa-crown',               'warning'],
        default    => ['fa-gem',                 'primary'],
    };
    [$name, $desc] = bonusItemText($b);
    $name     = htmlspecialchars_uni($name);
    $desc     = nl2br(htmlspecialchars_uni($desc));
    $ptsLabel = ags_fmt($lang->mybonus['val_pts'], number_format($price));
    $btnBuy   = $lang->mybonus['btn_buy'];
    $need     = $disabled
        ? '<div class="bn-need"><i class="fa-solid fa-lock"></i>' . htmlspecialchars(ags_fmt($lang->mybonus['lbl_need_more'] ?? 'Need {1} more', number_format($price - $points))) . '</div>'
        : '';
    $pct      = $price > 0 ? (int)min(100, floor($points / $price * 100)) : 100;
    $bar      = $disabled ? '<div class="bn-bar"><span style="width:' . $pct . '%"></span></div>' : '';
    $dis      = $disabled ? ' disabled' : '';
    $cls      = $disabled ? ' is-locked' : '';

    return <<<HTML
<div class="st-card bn-item bn-tone-{$tone}{$cls}">
    <div class="bn-item__head">
        <span class="st-ico st-soft-{$tone}"><i class="fa-solid {$icon}"></i></span>
        <h3 class="bn-item__title">{$name}</h3>
    </div>
    <div class="bn-item__desc">{$desc}</div>
    {$bar}
    <div class="bn-item__foot">
        <span class="bn-price"><i class="fa-solid fa-coins"></i>{$ptsLabel}</span>
        <form method="post" class="m-0">
            <input type="hidden" name="id" value="{$b['id']}">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
            <button type="submit" class="btn btn-sm btn-primary st-pill"{$dis}><i class="fa-solid fa-cart-shopping me-1"></i>{$btnBuy}</button>
        </form>
    </div>
    {$need}
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
        return '<div class="bn-note mt-2"><i class="fa-solid fa-circle-info"></i><div>' . ags_fmt($lang->mybonus['lbl_fixed_multiplier'], number_format($flatMul, 1)) . '</div></div>';
    }

    $cols = '';
    $colW = (int)(12 / max(1, count($ranges)));
    foreach ($ranges as [$range, $mul, $color, $isUser]) {
        $badge      = $isUser ? "<div class='mt-1'><span class='st-badge st-soft-primary'>" . $lang->mybonus['lbl_your_range'] . "</span></div>" : '';
        $cur        = $isUser ? ' is-current' : '';
        $rangeLabel = ags_fmt($lang->mybonus['lbl_range_torrents'], $range);
        $cols .= <<<HTML
<div class="col-6 col-sm-{$colW}">
    <div class="bn-range{$cur}">
        <div class="st-meta">{$rangeLabel}</div>
        <div class="bn-range__mul text-{$color}">×{$mul}</div>
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

    return '<div class="row g-2 mt-1">' . $cols . '</div>'
         . '<div class="st-meta mt-2"><i class="fa-solid fa-circle-info me-1"></i>' . $desc . '</div>';
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
$cardsN = 0;
while ($b = $db->fetch_array($res)) {
    // Пропускаем товар, если для его типа есть gate-флаг и он выключен
    if (isset($art_gate_map[$b['art']]) && $art_gate_map[$b['art']] !== 'yes') {
        continue;
    }
    $cards .= renderBonusCard($b, $points);
    $cardsN++;
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
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/st-ui.css?ver=12">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/mybonus.css?ver=3">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/scripts/swal-notify.js?ver=2"></script>

<?php
$e      = fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$bnL    = static fn(string $k, string $fb): string => (string)($lang->mybonus[$k] ?? $fb);
$recent = bonus_log_fetch((int)$CURUSER['id'], null, 10);
if (!function_exists('bonus_log_reason_text')) {
    // older functions_bonuslog.php without the reason translator
    function bonus_log_reason_text(string $reason, array $L): string { return $reason; }
}
$lang->load('bonuslog');
$reasonL = is_array($lang->bonuslog ?? null) ? $lang->bonuslog : [];

/** Gray KPI tile with a big icon in the corner */
$bn_kpi = static function (string $icon, string $tone, string $value, string $label) use ($e): string {
    return '<div class="st-kpi st-kpi--muted">'
         . '<span class="st-ico st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div class="min-w-0"><div class="st-kpi__val">' . $value . '</div><div class="st-kpi__lbl">' . $e($label) . '</div></div>'
         . '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i></div>';
};
?>

<div class="container-md st-page bn-page py-3">

    <!-- Header -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-warning"><i class="fa-solid fa-coins"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $lang->mybonus['sec_my_bonuses'] ?></h1>
            <div class="st-meta mt-1"><?= ags_fmt($lang->mybonus['hint_header_stats'], $BASE_BONUS, $HOUR_CAP, htmlspecialchars($mulTypeText)) ?></div>
        </div>
        <a class="btn btn-outline-secondary st-pill bn-head-btn" href="<?= $BASEURL ?>/bonuslog.php"><i class="fa-solid fa-clock-rotate-left me-1"></i><?= $lang->mybonus['btn_bonus_history'] ?></a>
    </header>

    <!-- KPI tiles -->
    <div class="bn-kpis mb-3"
         id="bonusStatsData"
         data-hourly="<?= number_format($ub['realHourly'], 0) ?>"
         data-torrents="<?= $ub['torrents'] ?>"
         data-seedtime="<?= number_format($ub['avgHours'] * 60, 0) ?>"
         data-daily="<?= number_format($ub['daily'], 0) ?>">
        <?= $bn_kpi('fa-wallet', 'warning', number_format($points), $bnL('kpi_balance', 'Balance')) ?>
        <?= $bn_kpi('fa-gauge-high', 'success', number_format($ub['realHourly'], 0), $lang->mybonus['lbl_points_per_hour']) ?>
        <?= $bn_kpi('fa-calendar-day', 'primary', number_format($ub['daily'], 0), $lang->mybonus['lbl_per_day']) ?>
        <?= $bn_kpi('fa-seedling', 'info', number_format((int)$ub['torrents']), $lang->mybonus['lbl_active_torrents']) ?>
    </div>

    <?= renderToasts($errors, $messages) ?>

    <!-- Tabs -->
    <nav class="bn-tabs mb-3" role="tablist" aria-label="<?= $e($lang->mybonus['sec_my_bonuses']) ?>">
        <button class="active" data-bs-toggle="pill" data-bs-target="#bn-shop" type="button" role="tab"><i class="fa-solid fa-store"></i><?= $e($bnL('tab_shop', 'Shop')) ?><span class="bn-tabs__n"><?= $cardsN ?></span></button>
        <button data-bs-toggle="pill" data-bs-target="#bn-earn" type="button" role="tab"><i class="fa-solid fa-chart-line"></i><?= $e($bnL('tab_earn', 'My earnings')) ?></button>
        <button data-bs-toggle="pill" data-bs-target="#bn-how" type="button" role="tab"><i class="fa-solid fa-calculator"></i><?= $e($bnL('tab_how', 'How it works')) ?></button>
        <?php if ($recent): ?>
        <button data-bs-toggle="pill" data-bs-target="#bn-recent" type="button" role="tab"><i class="fa-solid fa-clock-rotate-left"></i><?= $e($lang->mybonus['sec_recent_activity']) ?></button>
        <?php endif; ?>
    </nav>

    <div class="tab-content">

    <!-- ═════ Shop ═════ -->
    <div class="tab-pane fade show active" id="bn-shop" role="tabpanel">
        <?php if ($cards !== ''): ?>
            <div class="bn-shop"><?= $cards ?></div>
        <?php else: ?>
            <div class="st-card st-empty"><span class="st-ico st-soft-muted"><i class="fa-solid fa-store-slash"></i></span><div><?= $e($bnL('txt_shop_empty', 'The shop is empty right now.')) ?></div></div>
        <?php endif; ?>
    </div>

    <!-- ═════ My earnings ═════ -->
    <div class="tab-pane fade" id="bn-earn" role="tabpanel">
        <?php if ($ub['torrents'] > 0): ?>
        <div class="row g-3 mb-3">
            <!-- Hourly calculation -->
            <div class="col-lg-6">
                <section class="st-card h-100">
                    <div class="st-card__head"><i class="fa-solid fa-chart-bar"></i><?= $lang->mybonus['sec_hourly_calc'] ?></div>
                    <div class="st-card__body">
                        <div class="bn-pair mb-3">
                            <div><div class="st-meta"><?= $lang->mybonus['lbl_theoretical_max'] ?></div><div class="bn-big text-primary"><?= ags_fmt($lang->mybonus['val_pts_h'], number_format($ub['hourlyTheoretical'], 0)) ?></div></div>
                            <div class="text-end"><div class="st-meta"><?= $lang->mybonus['lbl_system_cap'] ?></div><div class="bn-big text-warning"><?= ags_fmt($lang->mybonus['val_pts_h'], number_format($HOUR_CAP, 0)) ?></div></div>
                        </div>

                        <div class="st-rows mb-3">
                            <?php
                            $rows = [
                                [$lang->mybonus['lbl_raw_bonus_sum'], number_format($ub['rawBonus'], 1)],
                                [ags_fmt($lang->mybonus['lbl_row_base_rate'], $BASE_BONUS), number_format($ub['rawBonus'] * $BASE_BONUS, 1)],
                                [ags_fmt($lang->mybonus['lbl_row_torrents_factor'], number_format($ub['capMul'], 1)), number_format($ub['hourlyTheoretical'], 1)],
                                [$lang->mybonus['lbl_theoretical_hourly'], ags_fmt($lang->mybonus['val_pts_h'], number_format($ub['hourlyTheoretical'], 1))],
                            ];
                            foreach ($rows as [$label, $val]): ?>
                                <div class="st-row"><span class="st-row__lbl"><?= htmlspecialchars($label) ?></span><span class="st-row__val"><?= $val ?></span></div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bn-result mb-3">
                            <div class="st-meta"><?= $lang->mybonus['lbl_hourly_after_cap'] ?></div>
                            <div class="bn-result__val"><?= ags_fmt($lang->mybonus['val_pts_h'], number_format($ub['hourlyCapped'], 0)) ?></div>
                            <?php if ($ub['isCapped']): ?>
                                <span class="st-badge st-soft-warning"><i class="fa-solid fa-triangle-exclamation"></i><?= $lang->mybonus['lbl_capped'] ?></span>
                            <?php else: ?>
                                <span class="st-badge st-soft-success"><i class="fa-solid fa-circle-check"></i><?= $lang->mybonus['lbl_not_capped'] ?></span>
                            <?php endif; ?>
                        </div>

                        <?php $pct = $ub['capPct']; $pColor = progressColor($pct); ?>
                        <div class="d-flex justify-content-between mb-1 st-meta">
                            <span><?= ags_fmt($lang->mybonus['lbl_theory_vs_cap'], $HOUR_CAP) ?></span>
                            <span><?= number_format($pct, 1) ?>%</span>
                        </div>
                        <div class="progress bn-progress"><div class="progress-bar bg-<?= $pColor ?>" style="width:<?= $pct ?>%"><?= number_format($ub['hourlyTheoretical'], 0) ?> / <?= $HOUR_CAP ?></div></div>
                    </div>
                </section>
            </div>

            <!-- Expected earnings -->
            <div class="col-lg-6">
                <section class="st-card h-100">
                    <div class="st-card__head"><i class="fa-solid fa-coins"></i><?= $lang->mybonus['sec_est_earnings'] ?></div>
                    <div class="st-card__body">
                        <div class="bn-earn-grid mb-3">
                            <?php
                            $earnCards = [
                                ['primary', 'fa-rotate',        $lang->mybonus['lbl_per_run'],  number_format($ub['perRun'], 1),     ags_fmt($lang->mybonus['hint_per_run'], $cronMin)],
                                ['success', 'fa-clock',         $lang->mybonus['lbl_per_hour'], number_format($ub['realHourly'], 0), $lang->mybonus['hint_per_hour']],
                                ['success', 'fa-calendar-day',  $lang->mybonus['lbl_per_day'],  number_format($ub['daily'], 0),      $lang->mybonus['hint_per_day']],
                                ['warning', 'fa-calendar-week', $lang->mybonus['lbl_per_week'], number_format($ub['daily'] * 7, 0),  $lang->mybonus['hint_per_week']],
                            ];
                            foreach ($earnCards as [$tone, $icon, $label, $val, $sub]): ?>
                                <div class="bn-earn">
                                    <span class="st-ico st-soft-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                                    <div class="min-w-0"><div class="st-meta"><?= $label ?></div><div class="bn-earn__val"><?= $val ?></div><div class="st-meta"><?= $sub ?></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php $actPct = min(100, ($ub['avgHours'] / $CRON_INTERVAL_HOURS) * 100); ?>
                        <div class="d-flex justify-content-between mb-1 st-meta">
                            <span><?= $lang->mybonus['lbl_activity_level'] ?></span>
                            <span><?= number_format($actPct, 0) ?>%</span>
                        </div>
                        <div class="progress bn-progress bn-progress--thin mb-1"><div class="progress-bar bg-info" style="width:<?= $actPct ?>%"></div></div>
                        <div class="st-meta text-center mb-3"><?= ags_fmt($lang->mybonus['hint_activity'], number_format($ub['avgHours'] * 60, 0), $cronMin) ?></div>

                        <div class="bn-note">
                            <i class="fa-solid fa-lightbulb text-warning"></i>
                            <div><?= $lang->mybonus['hint_tip_html'] ?>
                                <div class="mt-1 fw-semibold"><?= ags_fmt($lang->mybonus['lbl_current_rate'], number_format($ub['realHourly'], 0), number_format($ub['daily'], 0)) ?></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <?php
        // ── Per-torrent breakdown ──
        $maxH   = max(array_column($seeding, 'hourly') ?: [1]);
        $factorLabels = [
            'f_leech' => [$lang->mybonus['f_leech'] ?? 'Leechers',  'fa-users'],
            'f_size'  => [$lang->mybonus['f_size']  ?? 'Size',      'fa-hard-drive'],
            'f_seed'  => [$lang->mybonus['f_seed']  ?? 'Seeders',   'fa-seedling'],
            'f_age'   => [$lang->mybonus['f_age']   ?? 'Age',       'fa-hourglass-half'],
            'f_loyal' => [$lang->mybonus['f_loyal'] ?? 'Loyalty',   'fa-heart'],
            'f_promo' => [$lang->mybonus['f_promo'] ?? 'Promo',     'fa-tag'],
        ];
        ?>
        <section class="st-card bn-tor mb-3">
            <div class="st-card__head">
                <i class="fa-solid fa-list-ol"></i><?= $e($lang->mybonus['sec_per_torrent'] ?? 'Bonus per torrent') ?>
                <span class="st-badge st-soft-muted"><?= count($seeding) ?></span>
                <input type="search" class="form-control form-control-sm ms-auto bn-tor-filter" id="mbTorFilter"
                       placeholder="<?= $e($lang->mybonus['ph_filter_torrents'] ?? 'Filter by name…') ?>">
            </div>
            <div class="st-meta px-3 pt-2"><?= $e($lang->mybonus['hint_per_torrent'] ?? 'Torrents you are seeding right now. Each one gets its share of your hourly bonus by its total multiplier; the hour cap and the torrent-count factor are spread over all of them.') ?></div>
            <div class="table-responsive bn-tor-scroll">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= $e($lang->mybonus['col_torrent'] ?? 'Torrent') ?></th>
                            <th class="d-none d-lg-table-cell"><?= $e($lang->mybonus['col_factors'] ?? 'Multipliers') ?></th>
                            <th class="text-end"><?= $e($lang->mybonus['col_total_mul'] ?? 'Total') ?></th>
                            <th class="text-end"><?= $e($lang->mybonus['col_pts_hour'] ?? 'pts/h') ?></th>
                            <th class="text-end d-none d-sm-table-cell"><?= $e($lang->mybonus['col_pts_day'] ?? 'pts/day') ?></th>
                        </tr>
                    </thead>
                    <tbody id="mbTorBody">
                    <?php foreach ($seeding as $t):
                        $pctBar = $maxH > 0 ? round($t['hourly'] / $maxH * 100, 1) : 0;
                    ?>
                        <tr data-name="<?= $e(mb_strtolower((string)$t['name'])) ?>">
                            <td class="bn-tor-name">
                                <a href="<?= get_torrent_link((int)$t['id']) ?>" title="<?= $e($t['name']) ?>"><?= $e($t['name']) ?></a>
                                <div class="st-meta">
                                    <?= mksize($t['size']) ?>
                                    · <i class="fa-solid fa-arrow-up text-success"></i> <?= (int)$t['seeders'] ?>
                                    <i class="fa-solid fa-arrow-down text-danger ms-1"></i> <?= (int)$t['leechers'] ?>
                                    <?php if ($t['free'] === 'yes'): ?><span class="st-badge st-soft-success ms-1">Free</span><?php endif; ?>
                                    <?php if ($t['silver'] === 'yes'): ?><span class="st-badge st-soft-muted ms-1">Silver</span><?php endif; ?>
                                    <?php if ($t['doubleupload'] === 'yes'): ?><span class="st-badge st-soft-warning ms-1">x2</span><?php endif; ?>
                                </div>
                                <div class="bn-tor-bar"><span style="width: <?= $pctBar ?>%"></span></div>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <div class="bn-tor-factors">
                                <?php foreach ($factorLabels as $key => [$label, $icon]):
                                    $v = round((float)$t[$key], 2);
                                    $cls = $v > 1.0 ? 'is-up' : ($v < 1.0 ? 'is-down' : 'is-flat');
                                ?>
                                    <span class="bn-tor-f <?= $cls ?>" title="<?= $e($label) ?>"><i class="fa-solid <?= $icon ?>"></i>×<?= rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?></span>
                                <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="text-end fw-semibold">×<?= number_format($t['mul'], 2) ?></td>
                            <td class="text-end fw-bold text-success"><?= number_format($t['hourly'], 1) ?></td>
                            <td class="text-end d-none d-sm-table-cell"><?= number_format($t['hourly'] * 24, 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td><?= $e($lang->mybonus['lbl_total'] ?? 'Total') ?></td>
                            <td class="d-none d-lg-table-cell"></td>
                            <td class="text-end">×<?= number_format($ub['rawBonus'], 2) ?></td>
                            <td class="text-end text-success"><?= number_format($ub['realHourly'], 1) ?></td>
                            <td class="text-end d-none d-sm-table-cell"><?= number_format($ub['daily'], 0) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if ($ub['isCapped'] || $ub['capMul'] != 1.0): ?>
            <div class="st-meta px-3 py-2 border-top">
                <i class="fa-solid fa-circle-info me-1"></i>
                <?php if ($ub['capMul'] != 1.0): ?>
                <?= $e(ags_fmt($lang->mybonus['hint_scaled_mul'] ?? 'Already includes the torrent-count factor ×{1}.', number_format($ub['capMul'], 1))) ?>
                <?php endif; ?>
                <?php if ($ub['isCapped']): ?>
                <?= $e(ags_fmt($lang->mybonus['hint_scaled_cap'] ?? 'Your total is over the hour cap of {1} pts/h, so every torrent is scaled down by the same share.', number_format($HOUR_CAP, 0))) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <?php else: ?>
            <div class="st-card st-empty mb-3">
                <span class="st-ico st-soft-warning"><i class="fa-solid fa-seedling"></i></span>
                <div><?= $lang->mybonus['hint_no_torrents'] ?></div>
            </div>
        <?php endif; ?>

        <!-- Torrent-count multiplier -->
        <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-layer-group"></i><?= $lang->mybonus['lbl_torrents_factor'] ?>
                <span class="st-badge st-soft-info ms-auto"><?= ags_fmt($lang->mybonus['lbl_type'], htmlspecialchars($mulTypeText)) ?></span>
            </div>
            <div class="st-card__body">
                <?= renderTorrentMultiplierTable($TORRENT_MUL_TYPE, $ub['torrents'], $FLAT_MULTIPLIER) ?>

                <?php if ($ub['torrents'] > 0): ?>
                <div class="bn-note bn-note--primary mt-3">
                    <i class="fa-solid fa-user"></i>
                    <div class="flex-grow-1">
                        <strong><?= $lang->mybonus['lbl_your_multiplier'] ?></strong> <span class="bn-big ms-1">×<?= number_format($ub['capMul'], 1) ?></span>
                        <div class="st-meta"><?= ags_fmt($lang->mybonus['hint_torrents_to_pct'], $ub['torrents'], number_format($ub['capMul'] * 100, 0)) ?></div>
                        <div class="st-meta mt-1"><?= match(true) {
                            $TORRENT_MUL_TYPE === 'penalty' && $ub['capMul'] < 1.0 => '<i class="fa-solid fa-arrow-down text-warning me-1"></i>' . $lang->mybonus['txt_mul_penalty'],
                            $TORRENT_MUL_TYPE === 'reward'  && $ub['capMul'] > 1.0 => '<i class="fa-solid fa-arrow-up text-success me-1"></i>' . $lang->mybonus['txt_mul_reward'],
                            default => '<i class="fa-solid fa-equals text-info me-1"></i>' . $lang->mybonus['txt_mul_standard'],
                        } ?></div>
                    </div>
                </div>

                <?php
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
                <div class="bn-note mt-2">
                    <?php if ($nextInfo): [$nt, $nm, $need] = $nextInfo; ?>
                        <i class="fa-solid fa-bullseye"></i>
                        <div><strong><?= $lang->mybonus['lbl_next_threshold'] ?></strong> <?= ags_fmt($lang->mybonus['txt_next_threshold'], $nt, number_format($nm, 1), $need) ?></div>
                    <?php else: ?>
                        <i class="fa-solid fa-trophy text-warning"></i>
                        <div><strong><?= $lang->mybonus['txt_max_multiplier'] ?></strong></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- ═════ How it works ═════ -->
    <div class="tab-pane fade" id="bn-how" role="tabpanel">
        <!-- System settings -->
        <div class="bn-sys mb-3">
            <?php
            $sysSettings = [
                ['fa-coins',          'success', $lang->mybonus['lbl_set_base'], $lang->mybonus['hint_set_base'], (string)$BASE_BONUS],
                ['fa-gauge-high',     'warning', $lang->mybonus['lbl_set_cap'],  $lang->mybonus['hint_set_cap'],  (string)$HOUR_CAP],
                ['fa-clock',          'info',    $lang->mybonus['lbl_set_cron'], $lang->mybonus['hint_set_cron'], ags_fmt($lang->mybonus['val_minutes'], $cronMin)],
                ['fa-layer-group',    'danger',  $lang->mybonus['lbl_set_mul'],  $lang->mybonus['hint_set_mul'],  htmlspecialchars($mulTypeText)],
            ];
            foreach ($sysSettings as [$icon, $tone, $label, $sub, $val]): ?>
                <div class="st-kpi st-kpi--muted" title="<?= $e(strip_tags((string)$sub)) ?>">
                    <span class="st-ico st-soft-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <div class="min-w-0"><div class="st-kpi__val"><?= $val ?></div><div class="st-kpi__lbl"><?= $label ?></div></div>
                    <i class="fa-solid <?= $icon ?> st-kpi__bg" aria-hidden="true"></i>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Formula -->
        <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-square-root-variable"></i><?= $lang->mybonus['sec_basic_formula'] ?>
                <span class="st-meta ms-auto"><?= $lang->mybonus['hint_formula_sub'] ?></span>
            </div>
            <div class="st-card__body">
                <div class="bn-formula">
                    <div><strong><?= $lang->mybonus['lbl_formula_hourly'] ?></strong> <?= $lang->mybonus['txt_formula_hourly'] ?></div>
                    <div><strong><?= $lang->mybonus['lbl_formula_final'] ?></strong> <?= $lang->mybonus['txt_formula_final'] ?></div>
                </div>
            </div>
        </section>

        <!-- Multipliers -->
        <section class="st-card mb-3">
            <div class="st-card__head"><i class="fa-solid fa-xmark"></i><?= rtrim($lang->mybonus['sec_multipliers'], ':') ?></div>
            <div class="st-card__body">
                <div class="bn-mul-grid">
                <?php
                $multiplierGroups = [
                    ['fa-users',          'info',      $lang->mybonus['grp_leechers'], [
                        [$lang->mybonus['rng_leech_0'],    '×' . ($cfg['leech_none'] ?? 1.2), 'success'],
                        [$lang->mybonus['rng_leech_few'],  '×' . ($cfg['leech_few']  ?? 1.5), 'warning'],
                        [$lang->mybonus['rng_leech_many'], '×' . ($cfg['leech_many'] ?? 1.8), 'danger', true],
                    ]],
                    ['fa-hard-drive',     'success',   $lang->mybonus['grp_size'], [
                        [$lang->mybonus['rng_size_small'],  '×' . ($cfg['size_small']  ?? 1.0), ''],
                        [$lang->mybonus['rng_size_medium'], '×' . ($cfg['size_medium'] ?? 1.2), 'success'],
                        [$lang->mybonus['rng_size_large'],  '×' . ($cfg['size_large']  ?? 1.5), 'warning'],
                        [$lang->mybonus['rng_size_xlarge'], '×' . ($cfg['size_xlarge'] ?? 1.8), 'danger'],
                        [$lang->mybonus['rng_size_huge'],   '×' . ($cfg['size_huge']   ?? 2.0), 'danger', true],
                    ]],
                    ['fa-seedling',       'warning',   $lang->mybonus['grp_seeders'], [
                        ['≤ 50',    '×1.0', 'success'],
                        ['51-100',  '×' . ($cfg['seeders_medium'] ?? 0.95), ''],
                        ['> 100',   '×' . ($cfg['seeders_many']   ?? 0.9),  'muted'],
                    ]],
                    ['fa-hourglass-half', 'muted',     $lang->mybonus['grp_age'], [
                        [$lang->mybonus['rng_age_new'],    '×1.0', ''],
                        [$lang->mybonus['rng_age_medium'], '×' . ($cfg['age_medium'] ?? 1.3), 'warning'],
                        [$lang->mybonus['rng_age_old'],    '×' . ($cfg['age_old']    ?? 1.5), 'danger', true],
                    ]],
                ];
                // Rarity and loyalty - only when switched on (not 1.0)
                $rareOn  = max((float)($cfg['rare_1'] ?? 1), (float)($cfg['rare_3'] ?? 1), (float)($cfg['rare_5'] ?? 1)) > 1.0;
                $loyalOn = max((float)($cfg['loyal_30'] ?? 1), (float)($cfg['loyal_90'] ?? 1), (float)($cfg['loyal_180'] ?? 1)) > 1.0;
                if ($rareOn) $multiplierGroups[] = ['fa-gem', 'primary', $lang->mybonus['grp_rarity'], [
                        [$lang->mybonus['rng_rare_1'], '×' . ($cfg['rare_1'] ?? 1.0), 'danger', true],
                        [$lang->mybonus['rng_rare_3'], '×' . ($cfg['rare_3'] ?? 1.0), 'warning'],
                        [$lang->mybonus['rng_rare_5'], '×' . ($cfg['rare_5'] ?? 1.0), 'success'],
                    ]];
                if ($loyalOn) $multiplierGroups[] = ['fa-heart', 'danger', $lang->mybonus['grp_loyalty'], [
                        [$lang->mybonus['rng_loyal_30'],  '×' . ($cfg['loyal_30']  ?? 1.0), 'success'],
                        [$lang->mybonus['rng_loyal_90'],  '×' . ($cfg['loyal_90']  ?? 1.0), 'warning'],
                        [$lang->mybonus['rng_loyal_180'], '×' . ($cfg['loyal_180'] ?? 1.0), 'danger', true],
                    ]];
                foreach ($multiplierGroups as [$icon, $tone, $label, $rows]): ?>
                    <div class="bn-mul">
                        <div class="bn-mul__head"><span class="st-ico st-soft-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span><?= htmlspecialchars($label) ?></div>
                        <?php foreach ($rows as $row):
                            [$rLabel, $rVal, $rColor] = $row;
                            $fw = !empty($row[3]) ? ' fw-bold' : '';
                            $tc = $rColor ? ' text-' . ($rColor === 'muted' ? 'secondary' : $rColor) : '';
                        ?>
                            <div class="bn-mul__row"><span><?= htmlspecialchars($rLabel) ?></span><span class="<?= $tc . $fw ?>"><?= $rVal ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                    <div class="bn-mul bn-mul--promo">
                        <div class="bn-mul__head"><span class="st-ico st-soft-danger"><i class="fa-solid fa-tag"></i></span><?= $lang->mybonus['sec_promo'] ?></div>
                        <?php foreach ([
                            [$lang->mybonus['lbl_promo_free'],   $cfg['promo_free']   ?? 0.7],
                            [$lang->mybonus['lbl_promo_silver'], $cfg['promo_silver'] ?? 0.5],
                            [$lang->mybonus['lbl_promo_double'], $cfg['promo_double'] ?? 0.5],
                        ] as [$pl, $pv]): ?>
                            <div class="bn-mul__row"><span><?= $pl ?></span><span class="text-success fw-semibold">+<?= $pv ?></span></div>
                        <?php endforeach; ?>
                        <div class="st-meta mt-1"><?= $lang->mybonus['hint_promo_base'] ?></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-3 mb-3">
            <!-- Calculation period -->
            <div class="col-lg-4">
                <section class="st-card h-100">
                    <div class="st-card__head"><i class="fa-solid fa-clock"></i><?= $lang->mybonus['sec_calc_period'] ?></div>
                    <div class="st-card__body">
                        <?php
                        $announceMin = (int)($cfg['announce_interval'] ?? 15);
                        $periodRows  = [
                            [$lang->mybonus['lbl_period_announce'], ags_fmt($lang->mybonus['val_minutes'], $announceMin), null],
                            [$lang->mybonus['lbl_period_cron'],     ags_fmt($lang->mybonus['val_minutes'], $cronMin),     null],
                            [$lang->mybonus['lbl_period_min'],      ags_fmt($lang->mybonus['val_minutes'], $announceMin), ags_fmt($lang->mybonus['hint_hours_min'], round($announceMin / 60, 2))],
                            [$lang->mybonus['lbl_period_max'],      ags_fmt($lang->mybonus['val_minutes'], $cronMin * 2), ags_fmt($lang->mybonus['hint_hours_max'], round(($cronMin * 2) / 60, 2))],
                        ];
                        ?>
                        <div class="st-rows mb-2">
                            <?php foreach ($periodRows as [$label, $val, $note]): ?>
                                <div class="st-row"><span class="st-row__lbl"><?= $label ?><?= $note ? '<div class="st-meta">' . $note . '</div>' : '' ?></span><span class="st-row__val"><?= $val ?></span></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="st-meta text-center"><?= ags_fmt($lang->mybonus['hint_seed_at_least'], $announceMin) ?></div>
                    </div>
                </section>
            </div>

            <!-- Example -->
            <div class="col-lg-4">
                <section class="st-card h-100">
                    <div class="st-card__head"><i class="fa-solid fa-chart-line"></i><?= $lang->mybonus['sec_example'] ?></div>
                    <div class="st-card__body">
                        <div class="st-meta mb-2"><strong><?= $lang->mybonus['lbl_ex_torrent'] ?></strong> <?= $lang->mybonus['txt_ex_torrent'] ?></div>
                        <div class="st-rows mb-2">
                            <?php
                            $exRows = [
                                [$lang->mybonus['lbl_ex_leechers'], ags_fmt($lang->mybonus['txt_ex_leechers'], (string)($cfg['leech_many']  ?? 1.8))],
                                [$lang->mybonus['lbl_ex_size'],     ags_fmt($lang->mybonus['txt_ex_size'],     (string)($cfg['size_xlarge'] ?? 1.8))],
                                [$lang->mybonus['lbl_ex_seeders'],  ags_fmt($lang->mybonus['txt_ex_seeders'],  '1.0')],
                                [$lang->mybonus['lbl_ex_age'],      ags_fmt($lang->mybonus['txt_ex_age'],      (string)($cfg['age_old']     ?? 1.5))],
                                [$lang->mybonus['lbl_ex_promo'],    '×' . number_format(1.0 + (float)($cfg['promo_free'] ?? 0.7), 1) . ' (+' . ($cfg['promo_free'] ?? 0.7) . ')'],
                                [$lang->mybonus['lbl_ex_total'],    '×' . number_format($exampleMul, 2)],
                            ];
                            foreach ($exRows as [$el, $ev]): ?>
                                <div class="st-row"><span class="st-row__lbl"><?= htmlspecialchars($el) ?></span><span class="st-row__val"><?= htmlspecialchars($ev) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="bn-result">
                            <div class="st-meta"><?= ags_fmt($lang->mybonus['lbl_ex_base'], $BASE_BONUS) ?> → <?= $lang->mybonus['lbl_ex_hourly'] ?> <strong><?= ags_fmt($lang->mybonus['val_per_h'], number_format($exampleHourly, 1)) ?></strong></div>
                            <div class="bn-result__val"><?= ags_fmt($lang->mybonus['val_pts'], number_format($examplePerCron, 1)) ?></div>
                            <div class="st-meta"><?= ags_fmt($lang->mybonus['lbl_ex_for_period'], $cronMin) ?></div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Tips -->
            <div class="col-lg-4">
                <section class="st-card h-100">
                    <div class="st-card__head"><i class="fa-solid fa-lightbulb"></i><?= $lang->mybonus['sec_tips'] ?></div>
                    <div class="st-card__body">
                        <ul class="bn-tips">
                            <?php
                            $tips = [
                                ['fa-fire text-danger',          $lang->mybonus['tip_leechers'], ags_fmt($lang->mybonus['tip_leechers_note'], (string)($cfg['leech_many'] ?? 1.8))],
                                ['fa-hard-drive text-success',   $lang->mybonus['tip_large'],    '×' . ($cfg['size_huge']  ?? 2.0)],
                                ['fa-clock-rotate-left text-secondary', $lang->mybonus['tip_old'], '×' . ($cfg['age_old']    ?? 1.5)],
                                ['fa-tag text-primary',          $lang->mybonus['tip_promo'],
                                    ags_fmt($lang->mybonus['tip_promo_note'],
                                        (string)($cfg['promo_free']   ?? 0.7),
                                        (string)($cfg['promo_silver'] ?? 0.5),
                                        (string)($cfg['promo_double'] ?? 0.5))],
                                ['fa-bell text-info',            ags_fmt($lang->mybonus['tip_announce'], $announceMin), ''],
                            ];
                            if ((float)($cfg['rare_1'] ?? 1) > 1.0) {
                                $tips[] = ['fa-gem text-primary', $lang->mybonus['tip_rare'], ags_fmt($lang->mybonus['tip_rare_note'], (string)$cfg['rare_1'])];
                            }
                            if ((float)($cfg['loyal_180'] ?? 1) > 1.0) {
                                $tips[] = ['fa-heart text-danger', $lang->mybonus['tip_loyal'], ags_fmt($lang->mybonus['tip_loyal_note'], (string)$cfg['loyal_180'])];
                            }
                            foreach ($tips as [$ic, $text, $note]): ?>
                                <li><i class="fa-solid <?= $ic ?>"></i><span><strong><?= htmlspecialchars($text) ?></strong><?php if ($note): ?> <span class="text-success">(<?= htmlspecialchars($note) ?>)</span><?php endif; ?></span></li>
                            <?php endforeach; ?>
                            <li><i class="fa-solid fa-chart-pie text-warning"></i><span><?= match($TORRENT_MUL_TYPE) {
                                'penalty' => $lang->mybonus['tip_opt_penalty'],
                                'reward'  => $lang->mybonus['tip_opt_reward'],
                                'neutral' => $lang->mybonus['tip_opt_neutral'],
                                'flat'    => ags_fmt($lang->mybonus['tip_opt_flat'], (string)$FLAT_MULTIPLIER),
                                default   => $lang->mybonus['tip_opt_default'],
                            } ?></span></li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- ═════ Recent activity ═════ -->
    <?php if ($recent): ?>
    <div class="tab-pane fade" id="bn-recent" role="tabpanel">
        <section class="st-card">
            <div class="st-card__head"><i class="fa-solid fa-clock-rotate-left"></i><?= $lang->mybonus['sec_recent_activity'] ?>
                <a href="<?= $BASEURL ?>/bonuslog.php" class="ms-auto st-meta fw-semibold text-decoration-none"><?= $lang->mybonus['lbl_view_all'] ?> <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <?php foreach ($recent as $r):
                $a = (float)$r['amount'];
                [, $icon] = BONUS_LOG_TYPES[$r['type']] ?? BONUS_LOG_TYPES['other']; ?>
                <div class="bn-log">
                    <span class="st-ico <?= $a >= 0 ? 'st-soft-success' : 'st-soft-danger' ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="bn-log__reason"><?= $e(bonus_log_reason_text((string)$r['reason'], $reasonL)) ?></div>
                        <div class="st-meta"><?= date('d.m.Y H:i', (int)$r['added']) ?></div>
                    </div>
                    <span class="bn-log__amount <?= $a >= 0 ? 'text-success' : 'text-danger' ?>"><?= $e(bonus_log_amount($a)) ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
    <?php endif; ?>

    </div><!-- /.tab-content -->
</div>

<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/mybonus.js?ver=3"></script>

<?php
stdfoot();
