<?php


define('IN_MYBB',    1);
define('THIS_SCRIPT', 'showteam.php');
define('IN_FORUM', true);

require_once 'global.php';

$lang->load('showteam');

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

add_breadcrumb($lang->showteam['nav_showteam']);
$plugins->run_hooks('showteam_start');

$timecut = TIMENOW - $wolcutoffmins;
$is_mod  = is_mod($usergroups);

$teamgroups = $moderators = [];

/* ── Groups to show ─────────────────────────────────────────────────── */
// Отдельный массив: $usergroups - это права текущего юзера, смешивать их
// с ключами-gid нельзя (раньше в цикл вывода попадали поля прав)
$query = $db->sql_query_prepared("SELECT gid, title FROM usergroups WHERE showforumteam=1 ORDER BY disporder");
while ($query && ($ug = $db->fetch_array($query))) {
    $teamgroups[(int)$ug['gid']] = $ug;
}

if (empty($teamgroups)) stderr($lang->showteam['error_noteamstoshow']);

/* ── Forum moderators (показываются в группе 6 «Moderators») ───────── */
if (isset($teamgroups[6])) {
    $query = $db->sql_query_prepared("
        SELECT m.*, f.name
        FROM moderators m
        LEFT JOIN users u ON (u.id = m.id)
        LEFT JOIN forums f ON (f.fid = m.fid)
        WHERE f.active = 1 AND m.isgroup = 0
        ORDER BY u.username
    ");
    while ($query && ($mod = $db->fetch_array($query))) {
        $moderators[(int)$mod['id']][] = $mod;
    }
}

$forum_permissions = forum_permissions();

/* ── Fetch users: основная группа из списка команды или модератор форума ── */
$group_ids = array_keys($teamgroups);
$where     = 'usergroup IN (' . implode(',', array_fill(0, count($group_ids), '?')) . ')';
$params    = $group_ids;

if ($moderators) {
    $mod_ids = array_keys($moderators);
    $where  .= ' OR id IN (' . implode(',', array_fill(0, count($mod_ids), '?')) . ')';
    $params  = array_merge($params, $mod_ids);
}

$query = $db->sql_query_prepared(
    "SELECT id, username, usergroup,
     ignorelist, hideemail, receivepms, lastactive, lastvisit, invisible, avatar, avatardimensions
     FROM users
     WHERE {$where}
     ORDER BY username",
    $params
);

while ($query && ($user = $db->fetch_array($query))) {
    $uid   = (int)$user['id'];
    $group = (int)$user['usergroup'];

    // Модератор форумов - в группу 6 со списком его форумов
    if (isset($moderators[$uid])) {
        $forumlist = '';
        foreach ($moderators[$uid] as $forum) {
            if (($forum_permissions[$forum['fid']]['canview'] ?? 0) == 1) {
                $forumlist .= '<a href="' . get_forum_link($forum['fid']) . '" class="badge bg-light text-dark border me-1 mb-1">'
                            . htmlspecialchars_uni($forum['name']) . '</a>';
            }
        }
        $user['forumlist'] = $forumlist;
        $teamgroups[6]['user_list'][$uid] = $user;
    }

    // Своя (основная) группа, если она в списке команды
    if (isset($teamgroups[$group]) && !isset($teamgroups[$group]['user_list'][$uid])) {
        $teamgroups[$group]['user_list'][$uid] = $user;
    }
}

/* ── Helper: build one member card ──────────────────────────────────── */
function build_member_card(array $user, string $scopeHtml, bool $showScope,
                            bool $online, string $emailcode, string $pmcode,
                            string $lastvisit): string
{
    global $lang;

    // Avatar
    $av = format_avatar($user['avatar'] ?? '', $user['avatardimensions'] ?? '');
    $avatar_html = str_starts_with($av['image'], '<svg')
        ? '<div class="team-avatar-svg">' . $av['image'] . '</div>'
        : '<img src="' . htmlspecialchars($av['image']) . '" alt="" class="team-avatar">';

    $lbl_online  = htmlspecialchars_uni($lang->showteam['lbl_online']);
    $lbl_offline = htmlspecialchars_uni($lang->showteam['lbl_offline']);
    $online_dot = $online
        ? '<span class="online-dot" title="' . $lbl_online . '" aria-label="' . $lbl_online . '"></span>'
        : '<span class="offline-dot" title="' . $lbl_offline . '" aria-label="' . $lbl_offline . '"></span>';

    $tip_lastvisit = htmlspecialchars_uni($lang->showteam['tip_lastvisit']);

    $scope_block = ($showScope && $scopeHtml)
        ? '<div class="scope-list mt-2">' . $scopeHtml . '</div>'
        : '';

    $actions = ($emailcode || $pmcode)
        ? '<div class="mt-3 d-flex gap-2 flex-wrap">' . $emailcode . $pmcode . '</div>'
        : '';

    return <<<HTML
<div class="col-xl-4 col-lg-6 col-md-6 mb-3">
    <div class="team-card h-100">
        <div class="team-card-inner">
            <div class="team-avatar-wrap">
                {$avatar_html}
                {$online_dot}
            </div>
            <div class="team-info">
                <div class="team-name"><a href="{$user['profilelink']}">{$user['username']}</a></div>
                {$scope_block}
                <div class="team-meta" title="{$tip_lastvisit}">
                    <i class="fas fa-clock me-1" aria-hidden="true"></i>{$lastvisit}
                </div>
                {$actions}
            </div>
        </div>
    </div>
</div>
HTML;
}

/* ── Build group sections ────────────────────────────────────────────── */
$grouplist = '';

foreach ($teamgroups as $usergroup) {
    if (!isset($usergroup['user_list'])) continue;

    $modrows = $usergrouprows = '';

    foreach ($usergroup['user_list'] as $user) {
        $plainname           = (string)$user['username'];
        $user['username']    = format_name(htmlspecialchars_uni($user['username']), (int)$user['usergroup']);
        $user['profilelink'] = get_profile_link($user['id']);

        // Online status
        $online = $user['lastactive'] > $timecut
               && $user['invisible'] == 0
               && $user['lastvisit'] != $user['lastactive'];

        // Last visit
        if ($user['invisible'] == 1 && !$is_mod && $user['id'] != $CURUSER['id']) {
            $lastvisit = $user['lastactive']
                ? $lang->global['lastvisit_hidden']
                : $lang->global['lastvisit_never'];
        } else {
            $lastvisit = my_datee('relative', $user['lastactive']);
        }

        // Email/PM buttons
        $emailcode = $pmcode = '';
        if (!$user['hideemail']) {
            $emailcode = '<a href="member.php?action=emailuser&amp;id=' . $user['id']
                . '" class="btn btn-sm btn-outline-secondary" title="'
                . htmlspecialchars_uni(ags_fmt($lang->showteam['tip_email'], $plainname))
                . '"><i class="fa-solid fa-at me-1" aria-hidden="true"></i>'
                . htmlspecialchars_uni($lang->showteam['btn_email']) . '</a>';
        }
        if ($user['receivepms'] && my_strpos(',' . (string)$user['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') === false) {
            $pmcode = '<a href="private.php?action=send&amp;uid=' . $user['id']
                . '" class="btn btn-sm btn-outline-secondary" title="'
                . htmlspecialchars_uni(ags_fmt($lang->showteam['tip_pm'], $plainname))
                . '"><i class="fa-solid fa-envelope me-1" aria-hidden="true"></i>'
                . htmlspecialchars_uni($lang->showteam['btn_pm']) . '</a>';
        }

        $plugins->run_hooks('showteam_user');

        // В группе модераторов у каждого показываем его форумы
        $isMod = (int)$usergroup['gid'] === 6 && !empty($user['forumlist']);

        if ($isMod) {
            $modrows .= build_member_card($user, $user['forumlist'], true, $online,
                                          $emailcode, $pmcode, $lastvisit);
        } else {
            $usergrouprows .= build_member_card($user, '', false, $online,
                                                $emailcode, $pmcode, $lastvisit);
        }
    }

    $rows = $modrows . $usergrouprows;
    if (!$rows) continue;

    $section_title = htmlspecialchars_uni((string)$usergroup['title']);

    $grouplist .= <<<HTML
<div class="team-section">
    <div class="team-section-title">{$section_title}</div>
    <div class="row g-3">{$rows}</div>
</div>
HTML;
}

if (empty($grouplist)) stderr($lang->showteam['error_noteamstoshow']);

$plugins->run_hooks('showteam_end');

/* ── Output ──────────────────────────────────────────────────────────── */
stdhead($lang->showteam['nav_showteam']);
build_breadcrumb();
?>
<style>
/* ── Team page styles ───────────────────────────────────────────────── */
.team-section {
    margin-bottom: 2.5rem;
}

.team-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ts-text, #1e293b);
    padding: .6rem 1rem;
    border-left: 4px solid var(--bs-primary, #0d6efd);
    background: rgba(13,110,253,.05);
    border-radius: 0 .5rem .5rem 0;
    margin-bottom: 1rem;
}

.team-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    transition: transform .2s ease, box-shadow .2s ease;
    overflow: hidden;
}

.team-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,.09);
    border-color: #c8d8ff;
}

.team-card-inner {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
}

/* Avatar */
.team-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.team-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
}

.team-avatar-svg {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid #e9ecef;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.team-avatar-svg svg {
    width: 40px;
    height: 40px;
}

.online-dot,
.offline-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.online-dot  { background: #22c55e; }
.offline-dot { background: #94a3b8; }

/* Info */
.team-info {
    flex: 1;
    min-width: 0;
}

.team-name {
    font-weight: 600;
    font-size: .95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: .15rem;
}

.team-name a {
    color: inherit;
    text-decoration: none;
}

.team-name a:hover { color: var(--bs-primary); }

.team-meta {
    font-size: .8rem;
    color: #64748b;
    margin-top: .25rem;
}

.scope-list {
    font-size: .8rem;
    line-height: 1.6;
}

.scope-list a {
    font-size: .75rem;
}

/* Buttons */
.team-card .btn-sm {
    font-size: .75rem;
    padding: .2rem .55rem;
}
</style>

<div class="container-md py-4">
    <?= $grouplist ?>
</div>

<?php
stdfoot();