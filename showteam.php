<?php
declare(strict_types=1);

define('IN_MYBB',    1);
define('THIS_SCRIPT', 'showteam.php');
define('IN_FORUM', true);

require_once 'global.php';

$lang->load('showteam');

$L = $lang->showteam;
$e = static fn(string $s): string => htmlspecialchars_uni($s);

/* ── Lang helper: {1}/{2}… and %1$s/%2$s… placeholders ──────────────── */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}'] = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

add_breadcrumb($L['nav_showteam'] ?? 'Tracker Team');
$plugins->run_hooks('showteam_start');

// $wolcutoffmins — в минутах (раньше вычиталось как секунды: окно «в сети»
// было 15 секунд, и вся команда почти всегда показывалась офлайн)
$online_cut = TIMENOW - max(1, (int)($wolcutoffmins ?? 15)) * 60;
$day_cut    = TIMENOW - 86400;
$is_mod     = is_mod($usergroups);
$my_id      = (int)($CURUSER['id'] ?? 0);

/* ── Groups to show ─────────────────────────────────────────────────── */
// Отдельный массив: $usergroups - это права текущего юзера, смешивать их
// с ключами-gid нельзя (раньше в цикл вывода попадали поля прав)
$teamgroups = [];
$query = $db->sql_query_prepared("SELECT gid, title FROM usergroups WHERE showforumteam=1 ORDER BY disporder");
while ($query && ($ug = $db->fetch_array($query))) {
    $teamgroups[(int)$ug['gid']] = $ug + ['user_list' => []];
}

if (empty($teamgroups)) {
    stderr($L['error_noteamstoshow'] ?? 'There are no tracker staff to show.');
}

/* ── Fetch users: основная группа из списка команды ─────────────────────────
   Модераторы — это группа 6 (UC_MODERATOR) и показываются как обычная группа
   (таблицы moderators с модераторами отдельных форумов больше нет). */
$group_ids    = array_keys($teamgroups);
$placeholders = implode(',', array_fill(0, count($group_ids), '?'));

$query = $db->sql_query_prepared(
    "SELECT id, username, usergroup,
            ignorelist, hideemail, receivepms, lastactive, lastvisit, invisible, avatar, avatardimensions
     FROM users
     WHERE usergroup IN ({$placeholders})
     ORDER BY username",
    $group_ids
);

$kpi_total  = 0;
$kpi_online = 0;
$kpi_day    = 0;

while ($query && ($user = $db->fetch_array($query))) {
    $gid = (int)$user['usergroup'];
    if (!isset($teamgroups[$gid])) {
        continue;
    }
    $teamgroups[$gid]['user_list'][(int)$user['id']] = $user;

    // KPI из той же выборки — без отдельных запросов.
    // Невидимых считаем «в сети» только для модераторов, как и в карточках.
    $visible = (int)$user['invisible'] === 0 || $is_mod;
    $kpi_total++;
    if ($visible && (int)$user['lastactive'] >= $online_cut) $kpi_online++;
    if ($visible && (int)$user['lastactive'] >= $day_cut)    $kpi_day++;
}

// Только группы, в которых кто-то есть
$teamgroups = array_filter($teamgroups, static fn(array $g): bool => $g['user_list'] !== []);

if (empty($teamgroups)) {
    stderr($L['error_noteamstoshow'] ?? 'There are no tracker staff to show.');
}

/* ── Иконка и цвет группы ────────────────────────────────────────────── */
function tm_group_style(int $gid): array
{
    return match ($gid) {
        8       => ['fa-crown',         'st-soft-danger'],   // Sysop
        7       => ['fa-shield-halved', 'st-soft-warning'],  // Administrator
        6       => ['fa-gavel',         'st-soft-success'],  // Moderator
        5       => ['fa-upload',        'st-soft-info'],     // Uploader
        4       => ['fa-gem',           'st-soft-primary'],  // VIP
        default => ['fa-user-tie',      'st-soft-muted'],
    };
}

/* ── Карточка участника ──────────────────────────────────────────────── */
function tm_member_card(array $user, string $group_title): string
{
    global $L, $e, $lang, $is_mod, $my_id, $online_cut;

    $uid       = (int)$user['id'];
    $gid       = (int)$user['usergroup'];
    $plainname = (string)$user['username'];
    $profile   = get_profile_link($uid);
    $name_html = format_name($e($plainname), $gid);

    $av = format_avatar((string)($user['avatar'] ?? ''), (string)($user['avatardimensions'] ?? ''));

    // Статус и последний визит (невидимых видят только модераторы и они сами)
    $hidden    = (int)$user['invisible'] === 1 && !$is_mod && $uid !== $my_id;
    $last_seen = max((int)$user['lastactive'], (int)$user['lastvisit']);
    $online    = !$hidden && (int)$user['lastactive'] >= $online_cut;

    if ($hidden) {
        $lastvisit = '<i class="fa-solid fa-eye-slash" aria-hidden="true"></i>' . $e($lang->global['lastvisit_hidden'] ?? '(Hidden)');
    } elseif ($last_seen === 0) {
        $lastvisit = '<i class="fa-solid fa-clock" aria-hidden="true"></i>' . $e($lang->global['lastvisit_never'] ?? 'Never');
    } else {
        $lastvisit = '<i class="fa-solid fa-clock" aria-hidden="true"></i>' . my_datee('relative', $last_seen);
    }

    $lbl_status = $online ? ($L['lbl_online'] ?? 'Online') : ($L['lbl_offline'] ?? 'Offline');

    // Кнопки связи
    $actions = '';
    if ((int)$user['hideemail'] === 0) {
        $actions .= '<a href="member.php?action=emailuser&amp;id=' . $uid . '" class="btn btn-sm btn-outline-secondary st-pill tm-btn"'
            . ' title="' . $e(ags_fmt($L['tip_email'] ?? 'Send {1} an email', $plainname)) . '">'
            . '<i class="fa-solid fa-at" aria-hidden="true"></i>' . $e($L['btn_email'] ?? 'Email') . '</a>';
    }
    $ignored = my_strpos(',' . (string)($user['ignorelist'] ?? '') . ',', ',' . $my_id . ',') !== false;
    if ((int)$user['receivepms'] === 1 && !$ignored && $uid !== $my_id) {
        $actions .= '<a href="private.php?action=send&amp;uid=' . $uid . '" class="btn btn-sm btn-outline-primary st-pill tm-btn"'
            . ' title="' . $e(ags_fmt($L['tip_pm'] ?? 'Send {1} a private message', $plainname)) . '">'
            . '<i class="fa-solid fa-envelope" aria-hidden="true"></i>' . $e($L['btn_pm'] ?? 'PM') . '</a>';
    }

    return '<article class="st-card tm-user' . ($online ? ' tm-user--online' : '') . '">'
        . '<a href="' . $profile . '" class="tm-user__ava" tabindex="-1" aria-hidden="true">'
        .   '<img src="' . $e((string)$av['image']) . '" alt="" loading="lazy">'
        .   '<span class="tm-user__dot' . ($online ? ' is-online' : '') . '" title="' . $e($lbl_status) . '"></span>'
        . '</a>'
        . '<div class="tm-user__body">'
        .   '<div class="tm-user__name"><a href="' . $profile . '">' . $name_html . '</a></div>'
        .   '<div class="tm-user__title">' . $e($group_title) . '</div>'
        .   '<div class="tm-user__meta" title="' . $e($L['tip_lastvisit'] ?? 'Last visit') . '">' . $lastvisit . '</div>'
        .   ($actions !== '' ? '<div class="tm-user__actions">' . $actions . '</div>' : '')
        . '</div>'
        . '</article>';
}

/* ── Секции групп + вкладки ──────────────────────────────────────────── */
$sections = '';
$tabs     = '';

foreach ($teamgroups as $gid => $group) {
    [$icon, $soft] = tm_group_style((int)$gid);
    $title = (string)$group['title'];
    $count = count($group['user_list']);

    $cards = '';
    foreach ($group['user_list'] as $user) {
        $plugins->run_hooks('showteam_user');
        $cards .= tm_member_card($user, $title);
    }

    $sections .= '<section class="st-card tm-group mb-3" id="tm-group-' . (int)$gid . '" data-group="' . (int)$gid . '">'
        . '<div class="st-card__head">'
        .   '<span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
        .   '<span class="tm-group__title">' . $e($title) . '</span>'
        .   '<span class="st-badge st-soft-muted ms-auto">' . ts_nf($count) . '</span>'
        . '</div>'
        . '<div class="st-card__body"><div class="tm-grid">' . $cards . '</div></div>'
        . '</section>';

    $tabs .= '<li><a class="tm-tab" href="#tm-group-' . (int)$gid . '" data-group="' . (int)$gid . '">'
        . '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i>'
        . '<span class="tm-tab__label">' . $e($title) . '</span>'
        . '<span class="tm-tab__count">' . ts_nf($count) . '</span>'
        . '</a></li>';
}

$plugins->run_hooks('showteam_end');

/* ── Output ──────────────────────────────────────────────────────────── */
stdhead($L['nav_showteam'] ?? 'Tracker Team');
build_breadcrumb();

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/showteam.css?ver=1">';
?>
<div class="container-md st-page tm-page">

    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-user-shield"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $e($L['nav_showteam'] ?? 'Tracker Team') ?></h1>
            <div class="st-meta mt-1"><?= $e($L['page_subtitle'] ?? 'The people who run and moderate the tracker') ?></div>
        </div>
    </header>

    <?php
    $tiles = [
        ['fa-users',       'st-soft-primary', $kpi_total,         $L['lbl_kpi_members'] ?? 'Team members'],
        ['fa-signal',      'st-soft-success', $kpi_online,        $L['lbl_kpi_online'] ?? 'Online now'],
        ['fa-clock',       'st-soft-info',    $kpi_day,           $L['lbl_kpi_active'] ?? 'Active in 24h'],
        ['fa-layer-group', 'st-soft-warning', count($teamgroups), $L['lbl_kpi_groups'] ?? 'Groups'],
    ];
    ?>
    <div class="tm-kpis mb-3">
        <?php foreach ($tiles as [$icon, $soft, $val, $lbl]): ?>
            <div class="st-kpi st-kpi--muted">
                <span class="st-ico <?= $soft ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                <div class="min-w-0">
                    <div class="st-kpi__val"><?= ts_nf($val) ?></div>
                    <div class="st-kpi__lbl"><?= $e($lbl) ?></div>
                </div>
                <i class="fa-solid <?= $icon ?> st-kpi__bg" aria-hidden="true"></i>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($teamgroups) > 1): ?>
    <nav class="st-card tm-tabs-card mb-3">
        <ul class="tm-tabs">
            <li><a class="tm-tab active" href="#" data-group="all" aria-current="true">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span class="tm-tab__label"><?= $e($L['tab_all'] ?? 'All') ?></span>
                <span class="tm-tab__count"><?= ts_nf($kpi_total) ?></span>
            </a></li>
            <?= $tabs ?>
        </ul>
    </nav>
    <?php endif; ?>

    <div class="tm-groups">
        <?= $sections ?>
    </div>

</div>
<script src="<?= $BASEURL ?>/scripts/showteam.js?ver=1" defer></script>
<?php
stdfoot();
