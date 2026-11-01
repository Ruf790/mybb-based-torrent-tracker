<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'memberlist.php');
define('SCRIPTNAME', 'memberlist.php');
define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);

require_once 'global.php';
require_once INC_PATH . '/functions_multipage.php';

$lang->load('memberlist');
$plugins->run_hooks('memberlist_start');

/* ═══════════════════════════════════════════════════════════════════
 *  Helpers
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Экранирует только LIKE-wildcard'ы (%, _, \) — для bind-параметров.
 * Кавычки экранирует сам биндинг.
 */
function ml_escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

/** Первая буква заглавная (мультибайтово) — для старых ключей вида «begins with». */
function ml_ucfirst(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

/** Шапка страницы: stdhead + стили + открытие обёртки. */
function ml_open(string $title): void
{
    global $BASEURL;
    stdhead($title);
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/memberlist.css?ver=1">';
    echo '<div class="container-md st-page ml-page">';
}

function ml_close(): void
{
    echo '</div>';
    stdfoot();
}

$L = $lang->memberlist;
$e = static fn($v): string => htmlspecialchars_uni((string)$v);

/* ═══════════════════════════════════════════════════════════════════
 *  Входные параметры (сырые значения — для SQL, $e() — для вывода)
 * ═══════════════════════════════════════════════════════════════════ */

$sort_options = [
    'username'  => 'u.username',
    'added'     => 'u.added',
    'lastvisit' => 'u.lastactive',
    'postnum'   => 'u.postnum',
    'threadnum' => 'u.threadnum',
];

$sort_input = strtolower((string)($mybb->get_input('sort') ?: 'username'));
if (!isset($sort_options[$sort_input])) {
    $sort_input = 'username';
}
$sort_field = $sort_options[$sort_input];

$order_input = strtolower((string)($mybb->get_input('order') ?: ($sort_input === 'username' ? 'ascending' : 'descending')));
if ($order_input !== 'descending') {
    $order_input = 'ascending';
}
$sort_order = $order_input === 'descending' ? 'DESC' : 'ASC';

$per_page = $mybb->get_input('perpage', MyBB::INPUT_INT);
if ($per_page < 5 || $per_page > 500) {
    $per_page = 20;
}

$search_username = trim((string)$mybb->get_input('username'));
$search_website  = trim((string)$mybb->get_input('website'));
$match_input     = (string)$mybb->get_input('username_match');
if (!in_array($match_input, ['exact', 'begins', 'contains'], true)) {
    $match_input = 'exact';
}

$letter_input = '';
if (isset($mybb->input['letter'])) {
    $l = (string)$mybb->get_input('letter');
    if ($l === '-1' || preg_match('/^[a-z]$/i', $l)) {
        $letter_input = strtolower($l);
    }
}

$match_opts = [
    'exact'    => ml_ucfirst($L['username_exact'] ?? 'exactly'),
    'begins'   => ml_ucfirst($L['begins_with'] ?? 'begins with'),
    'contains' => ml_ucfirst($L['username_contains'] ?? 'contains'),
];

/* ═══════════════════════════════════════════════════════════════════
 *  ACTION: расширенный поиск
 * ═══════════════════════════════════════════════════════════════════ */

if ($mybb->get_input('action') === 'search') {
    $plugins->run_hooks('memberlist_search');

    ml_open($L['search_member_list'] ?? 'Search Member List');
    ?>
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-magnifying-glass"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $e($L['search_member_list'] ?? 'Search Member List') ?></h1>
            <div class="st-meta mt-1"><?= $e($L['search_criteria'] ?? 'Search Criteria') ?></div>
        </div>
        <a href="memberlist.php" class="btn btn-outline-secondary st-pill ml-btn">
            <i class="fa-solid fa-users"></i><span class="ml-hide-sm"><?= $e($L['member_list'] ?? 'Member List') ?></span>
        </a>
    </header>

    <form method="get" action="memberlist.php" class="st-card ml-search">
        <div class="st-card__head">
            <span class="st-ico st-soft-primary"><i class="fa-solid fa-user"></i></span>
            <span><?= $e($L['search_criteria'] ?? 'Search Criteria') ?></span>
        </div>
        <div class="st-card__body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label ml-lbl" for="ml_s_user"><i class="fa-solid fa-user"></i><?= $e($L['username'] ?? 'Username') ?></label>
                    <input type="text" class="form-control" id="ml_s_user" name="username" placeholder="<?= $e($L['ph_username'] ?? 'Enter username...') ?>" autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label ml-lbl" for="ml_s_web"><i class="fa-solid fa-globe"></i><?= $e($L['search_website'] ?? 'Website URL') ?></label>
                    <input type="text" class="form-control" id="ml_s_web" name="website" placeholder="<?= $e($L['ph_website'] ?? 'Website...') ?>">
                </div>
                <div class="col-12">
                    <div class="ml-lbl mb-2"><i class="fa-solid fa-filter"></i><?= $e($L['lbl_match'] ?? 'Match') ?></div>
                    <div class="ml-seg">
                        <?php foreach ($match_opts as $k => $label): ?>
                            <input type="radio" class="btn-check" name="username_match" id="ml_s_m_<?= $k ?>" value="<?= $k ?>"<?= $k === 'exact' ? ' checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-secondary st-pill" for="ml_s_m_<?= $k ?>"><?= $e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="st-card__head ml-search__opts">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-sliders"></i></span>
            <span><?= $e($L['search_options'] ?? 'Search Options') ?></span>
        </div>
        <div class="st-card__body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label ml-lbl" for="ml_s_sort"><i class="fa-solid fa-arrow-down-wide-short"></i><?= $e($L['sort_by'] ?? 'Sort by') ?></label>
                    <select name="sort" id="ml_s_sort" class="form-select">
                        <option value="username"><?= $e($L['sort_by_username'] ?? 'Sort by: Username') ?></option>
                        <option value="added"><?= $e($L['sort_by_regdate'] ?? 'Sort by: Registration date') ?></option>
                        <option value="lastvisit"><?= $e($L['sort_by_lastvisit'] ?? 'Sort by: Last visit') ?></option>
                        <option value="postnum"><?= $e($L['sort_by_posts'] ?? 'Sort by: Post count') ?></option>
                        <option value="threadnum"><?= $e($L['sort_by_threads'] ?? 'Sort by: Thread count') ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="ml-lbl mb-2"><i class="fa-solid fa-arrow-up-short-wide"></i><?= $e($L['lbl_order'] ?? 'Order') ?></div>
                    <div class="ml-seg">
                        <input type="radio" class="btn-check" name="order" id="ml_s_asc" value="ascending" checked>
                        <label class="btn btn-sm btn-outline-secondary st-pill" for="ml_s_asc"><i class="fa-solid fa-arrow-up"></i> <?= $e(ml_ucfirst($L['order_asc'] ?? 'ascending order')) ?></label>
                        <input type="radio" class="btn-check" name="order" id="ml_s_desc" value="descending">
                        <label class="btn btn-sm btn-outline-secondary st-pill" for="ml_s_desc"><i class="fa-solid fa-arrow-down"></i> <?= $e(ml_ucfirst($L['order_desc'] ?? 'descending order')) ?></label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label ml-lbl" for="ml_s_pp"><i class="fa-solid fa-list-ol"></i><?= $e($L['per_page'] ?? 'Results Per Page') ?></label>
                    <input type="number" class="form-control" id="ml_s_pp" name="perpage" value="20" min="5" max="500">
                </div>
            </div>
        </div>
        <div class="st-toolbar st-toolbar--bottom">
            <a href="memberlist.php" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-xmark"></i> <?= $e($L['btn_reset'] ?? 'Reset') ?></a>
            <button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-magnifying-glass"></i> <?= $e($L['search'] ?? 'Search') ?></button>
        </div>
    </form>
    <?php
    ml_close();
    exit;
}

/* ═══════════════════════════════════════════════════════════════════
 *  Условия поиска
 * ═══════════════════════════════════════════════════════════════════ */

$search_conditions = ['1=1'];
$search_params     = [];
$url_params        = [];   // сырые значения, кодируются http_build_query()

if ($letter_input === '-1') {
    $search_conditions[] = "u.username NOT REGEXP '^[a-zA-Z]'";
    $url_params['letter'] = '-1';
} elseif ($letter_input !== '') {
    $search_conditions[] = 'u.username LIKE ?';
    $search_params[]     = ml_escape_like($letter_input) . '%';
    $url_params['letter'] = $letter_input;
}

if ($search_username !== '') {
    $url_params['username'] = $search_username;
    if ($match_input === 'begins') {
        $search_conditions[] = 'u.username LIKE ?';
        $search_params[]     = ml_escape_like($search_username) . '%';
    } elseif ($match_input === 'contains') {
        $search_conditions[] = 'u.username LIKE ?';
        $search_params[]     = '%' . ml_escape_like($search_username) . '%';
    } else {
        $search_conditions[] = 'LOWER(u.username) = ?';
        $search_params[]     = my_strtolower($search_username);
    }
    if ($match_input !== 'exact') {
        $url_params['username_match'] = $match_input;
    }
}

if ($search_website !== '') {
    $search_conditions[] = 'u.website LIKE ?';
    $search_params[]     = '%' . ml_escape_like($search_website) . '%';
    $url_params['website'] = $search_website;
}

$is_filtered  = $url_params !== [];
$search_query = implode(' AND ', $search_conditions);

/* ═══════════════════════════════════════════════════════════════════
 *  KPI (один запрос)
 * ═══════════════════════════════════════════════════════════════════ */

$online_cut = TIMENOW - max(1, (int)($wolcutoffmins ?? 15)) * 60;
$week_cut   = TIMENOW - 7 * 86400;

$kpi = $db->fetch_array($db->sql_query_prepared(
    "SELECT COUNT(*)                                                 AS total,
            COALESCE(SUM(added >= ?), 0)                             AS new_week,
            COALESCE(SUM(lastactive >= ? AND invisible = 0), 0)      AS online
     FROM users",
    [$week_cut, $online_cut]
)) ?: [];
$kpi_total  = (int)($kpi['total'] ?? 0);
$kpi_week   = (int)($kpi['new_week'] ?? 0);
$kpi_online = (int)($kpi['online'] ?? 0);

/* ═══════════════════════════════════════════════════════════════════
 *  Пагинация
 * ═══════════════════════════════════════════════════════════════════ */

if ($is_filtered) {
    $count_query = $db->sql_query_prepared("SELECT COUNT(*) AS users FROM users u WHERE {$search_query}", $search_params);
    $num_users   = $count_query ? (int)$db->fetch_field($count_query, 'users') : 0;
} else {
    $num_users = $kpi_total;   // без фильтра счёт уже есть
}

$page  = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
$pages = max(1, (int)ceil($num_users / $per_page));
if ($page > $pages) {
    $page = 1;
}
$start = ($page - 1) * $per_page;

/** URL списка с текущими параметрами; $over — что заменить. */
$ml_url = static function (array $over = []) use ($url_params, $sort_input, $order_input, $per_page): string {
    $q = array_merge(['sort' => $sort_input, 'order' => $order_input], $per_page !== 20 ? ['perpage' => $per_page] : [], $url_params, $over);
    $q = array_filter($q, static fn($v) => $v !== null && $v !== '');
    return 'memberlist.php' . ($q ? '?' . http_build_query($q) : '');
};

$multipage = multipage($num_users, $per_page, $page, htmlspecialchars_uni($ml_url()));

/* ═══════════════════════════════════════════════════════════════════
 *  Список
 * ═══════════════════════════════════════════════════════════════════ */

$can_see_invis = (int)($usergroups['canviewwolinvis'] ?? 0) === 1;
$my_id         = (int)($CURUSER['id'] ?? 0);

$users_html = '';
$query = $db->sql_query_prepared("
    SELECT u.*
    FROM users u
    WHERE {$search_query}
    ORDER BY {$sort_field} {$sort_order}, u.id ASC
    LIMIT ?, ?
", [...$search_params, $start, $per_page]);

while ($query && ($user = $db->fetch_array($query))) {
    $user = $plugins->run_hooks('memberlist_user', $user);

    $uid            = (int)$user['id'];
    $user_gid       = (int)($user['usergroup'] ?? 1) ?: 1;
    $username_plain = htmlspecialchars_uni((string)$user['username']);
    $profile_url    = get_profile_link($uid);
    $profile_link   = build_profile_link(format_name($username_plain, $user_gid), $uid);

    $group     = usergroup_permissions($user_gid);
    $usertitle = htmlspecialchars_uni((string)($group['title'] ?? ($L['member'] ?? 'Member')));

    $av  = format_avatar((string)($user['avatar'] ?? ''), (string)($user['avatardimensions'] ?? ''));

    // Последний визит и статус «в сети»
    $hidden    = (int)$user['invisible'] === 1 && !$can_see_invis && $uid !== $my_id;
    $last_seen = max((int)$user['lastactive'], (int)$user['lastvisit']);
    $is_online = !$hidden && (int)$user['lastactive'] >= $online_cut;

    if ($hidden) {
        $lastvisit = '<i class="fa-solid fa-eye-slash"></i>' . $e($L['lastvisit_hidden'] ?? '(Hidden)');
    } elseif (!$last_seen) {
        $lastvisit = '<i class="fa-solid fa-clock"></i>' . $e($L['lastvisit_never'] ?? 'Never');
    } else {
        $lastvisit = '<i class="fa-solid fa-clock"></i>' . my_datee('relative', $last_seen);
    }

    $joined = (int)$user['added'] > 0
        ? my_datee('relative', (int)$user['added'])
        : $e($L['lastvisit_never'] ?? 'Never');

    $users_html .= '<article class="st-card ml-user' . ($is_online ? ' ml-user--online' : '') . '">'
        . '<a href="' . $profile_url . '" class="ml-user__ava" tabindex="-1" aria-hidden="true">'
        .   '<img src="' . $av['image'] . '" alt="" loading="lazy">'
        .   ($is_online ? '<span class="ml-user__dot" title="' . $e($L['lbl_online'] ?? 'Online') . '"></span>' : '')
        . '</a>'
        . '<div class="ml-user__body">'
        .   '<div class="ml-user__name">' . $profile_link . '</div>'
        .   '<div class="ml-user__title">' . $usertitle . '</div>'
        .   '<div class="ml-user__stats">'
        .     '<span class="st-badge st-soft-primary" title="' . $e($L['posts'] ?? 'Post Count') . '"><i class="fa-solid fa-comment"></i>' . ts_nf((int)$user['postnum']) . '</span>'
        .     '<span class="st-badge st-soft-info" title="' . $e($L['threads'] ?? 'Thread Count') . '"><i class="fa-solid fa-file-lines"></i>' . ts_nf((int)$user['threadnum']) . '</span>'
        .   '</div>'
        .   '<div class="ml-user__meta">'
        .     '<span title="' . $e($L['joined'] ?? 'Joined') . '"><i class="fa-solid fa-calendar-plus"></i>' . $joined . '</span>'
        .     '<span title="' . $e($L['lastvisit'] ?? 'Last Visit') . '">' . $lastvisit . '</span>'
        .   '</div>'
        . '</div>'
        . '</article>';
}

$plugins->run_hooks('memberlist_end');

/* ═══════════════════════════════════════════════════════════════════
 *  Вывод
 * ═══════════════════════════════════════════════════════════════════ */

ml_open($L['member_list'] ?? 'Member List');

// ── Шапка ──
?>
<header class="st-card st-head mb-3">
    <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-users"></i></div>
    <div class="flex-grow-1 min-w-0">
        <h1 class="st-title"><?= $e($L['member_list'] ?? 'Member List') ?></h1>
        <div class="st-meta mt-1"><?= $e($L['page_subtitle'] ?? 'All registered members of the tracker') ?></div>
    </div>
    <a href="memberlist.php?action=search" class="btn btn-outline-secondary st-pill ml-btn">
        <i class="fa-solid fa-sliders"></i><span class="ml-hide-sm"><?= $e($L['advanced_search'] ?? 'Advanced Search') ?></span>
    </a>
</header>

<?php
// ── KPI-плитки ──
$tiles = [
    ['fa-users',       'st-soft-primary', ts_nf($kpi_total),  $L['lbl_kpi_total'] ?? 'Members',       'memberlist.php'],
    ['fa-user-plus',   'st-soft-success', ts_nf($kpi_week),   $L['lbl_kpi_new_week'] ?? 'New this week', 'memberlist.php?sort=added&amp;order=descending'],
    ['fa-signal',      'st-soft-info',    ts_nf($kpi_online), $L['lbl_kpi_online'] ?? 'Online now',    'memberlist.php?sort=lastvisit&amp;order=descending'],
    ['fa-filter',      'st-soft-warning', ts_nf($num_users),  $L['lbl_kpi_found'] ?? 'Found',          ''],
];
echo '<div class="ml-kpis mb-3">';
foreach ($tiles as [$icon, $soft, $val, $lbl, $href]) {
    $tag = $href !== '' ? 'a' : 'div';
    echo '<' . $tag . ($href !== '' ? ' href="' . $href . '"' : '') . ' class="st-kpi st-kpi--muted">'
       .   '<span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
       .   '<div class="min-w-0"><div class="st-kpi__val">' . $val . '</div><div class="st-kpi__lbl">' . $e($lbl) . '</div></div>'
       .   '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i>'
       . '</' . $tag . '>';
}
echo '</div>';

// ── Фильтр: буквы + быстрый поиск ──
?>
<section class="st-card ml-filter mb-3">
    <div class="st-card__head">
        <span class="st-ico st-soft-primary"><i class="fa-solid fa-magnifying-glass"></i></span>
        <span><?= $e($L['search_members'] ?? 'Search Member List') ?></span>
        <?php if ($is_filtered): ?>
            <a href="memberlist.php" class="ms-auto btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-xmark"></i> <?= $e($L['btn_reset'] ?? 'Reset') ?></a>
        <?php endif; ?>
    </div>
    <div class="st-card__body">
        <div class="ml-letters" aria-label="<?= $e($L['lbl_browse_letter'] ?? 'Browse by letter') ?>">
            <a href="<?= $e($ml_url(['letter' => '-1', 'username' => null, 'username_match' => null, 'page' => null])) ?>"
               class="ml-letter<?= $letter_input === '-1' ? ' active' : '' ?>" title="<?= $e($L['tip_letter_other'] ?? 'Other characters') ?>"><i class="fa-solid fa-hashtag"></i></a>
            <?php foreach (range('a', 'z') as $ch): ?>
                <a href="<?= $e($ml_url(['letter' => $ch, 'username' => null, 'username_match' => null, 'page' => null])) ?>"
                   class="ml-letter<?= $letter_input === $ch ? ' active' : '' ?>"<?= $letter_input === $ch ? ' aria-current="true"' : '' ?>><?= strtoupper($ch) ?></a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="memberlist.php" class="ml-quick">
            <input type="hidden" name="sort" value="<?= $e($sort_input) ?>">
            <input type="hidden" name="order" value="<?= $e($order_input) ?>">
            <div class="input-group ml-quick__user">
                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                <input type="text" class="form-control" name="username" value="<?= $e($search_username) ?>"
                       placeholder="<?= $e($L['ph_username'] ?? 'Enter username...') ?>" aria-label="<?= $e($L['username'] ?? 'Username') ?>">
            </div>
            <select name="username_match" class="form-select ml-quick__match" aria-label="<?= $e($L['lbl_match'] ?? 'Match') ?>">
                <?php foreach ($match_opts as $k => $label): ?>
                    <option value="<?= $k ?>"<?= $match_input === $k ? ' selected' : '' ?>><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="perpage" class="form-select ml-quick__pp" aria-label="<?= $e($L['per_page'] ?? 'Results Per Page') ?>">
                <?php foreach (array_unique([20, 50, 100, $per_page]) as $pp): ?>
                    <option value="<?= (int)$pp ?>"<?= $pp === $per_page ? ' selected' : '' ?>><?= (int)$pp ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-magnifying-glass"></i> <?= $e($L['search'] ?? 'Search') ?></button>
        </form>
    </div>
</section>

<?php
// ── Сортировка (пилюли) ──
$sort_pills = [
    'username'  => $L['username'] ?? 'Username',
    'added'     => $L['joined'] ?? 'Joined',
    'lastvisit' => $L['lastvisit'] ?? 'Last Visit',
    'postnum'   => $L['posts'] ?? 'Post Count',
    'threadnum' => $L['threads'] ?? 'Thread Count',
];
echo '<div class="st-card ml-sortbar mb-3"><div class="st-toolbar">'
   . '<span class="ml-sortbar__lbl"><i class="fa-solid fa-arrow-down-wide-short"></i>' . $e($L['sort_by'] ?? 'Sort by') . '</span>'
   . '<div class="ml-sortbar__pills">';
foreach ($sort_pills as $key => $label) {
    $active = $sort_input === $key;
    $next   = $active
        ? ($order_input === 'ascending' ? 'descending' : 'ascending')
        : ($key === 'username' ? 'ascending' : 'descending');
    $arrow  = $active ? ' <i class="fa-solid ' . ($order_input === 'ascending' ? 'fa-arrow-up' : 'fa-arrow-down') . '"></i>' : '';
    echo '<a href="' . $e($ml_url(['sort' => $key, 'order' => $next, 'page' => null])) . '" class="btn btn-sm st-pill '
       . ($active ? 'btn-primary' : 'btn-outline-secondary') . '"' . ($active ? ' aria-current="true"' : '') . '>' . $e($label) . $arrow . '</a>';
}
echo '</div></div></div>';

// ── Пагинация + сетка ──
if ($multipage) {
    echo '<div class="st-pager mb-3">' . $multipage . '</div>';
}

if ($users_html !== '') {
    echo '<div class="ml-grid mb-3">' . $users_html . '</div>';
} else {
    echo '<div class="st-card mb-3"><div class="st-empty">'
       .   '<div class="st-ico st-soft-muted"><i class="fa-solid fa-user-slash"></i></div>'
       .   '<div class="ml-empty__title">' . $e($L['msg_no_members_title'] ?? 'No members found') . '</div>'
       .   '<div class="ml-empty__hint">' . ($L['error_no_members'] ?? '') . '</div>'
       . '</div></div>';
}

if ($multipage) {
    echo '<div class="st-pager mb-3">' . $multipage . '</div>';
}

ml_close();
