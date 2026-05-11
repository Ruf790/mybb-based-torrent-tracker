<?php


define('IN_MYBB',    1);
define('THIS_SCRIPT', 'showteam.php');
define('TSF_FORUMS_TSSEv56',        true);
define('TSF_FORUMS_GLOBAL_TSSEv56', true);
define('TSF_VERSION', 'v1.5 by xam');
define('IN_FORUM', true);

require_once 'global.php';

$lang->load('showteam');
add_breadcrumb($lang->showteam['nav_showteam']);
$plugins->run_hooks('showteam_start');

$timecut = TIMENOW - $wolcutoffmins;
$is_mod  = is_mod($usergroups);

$user_groups_data = $moderators = [];

/* ── Groups to show ─────────────────────────────────────────────────── */
$query = $db->simple_select('usergroups', 'gid, title', 'showforumteam=1',
    ['order_by' => 'disporder']);
while ($ug = $db->fetch_array($query)) {
    $usergroups[$ug['gid']] = $ug;
}

if (empty($usergroups)) stderr('error_noteamstoshow');

/* ── Forum moderators ───────────────────────────────────────────────── */
if (!empty($usergroups[5]['gid'])) {
    $query = $db->sql_query("
        SELECT m.*, f.name
        FROM moderators m
        LEFT JOIN users u ON (u.id = m.id)
        LEFT JOIN forums f ON (f.fid = m.fid)
        WHERE f.active = 1 AND m.isgroup = 0
        ORDER BY u.username
    ");
    while ($mod = $db->fetch_array($query)) {
        $moderators[$mod['id']][] = $mod;
    }
}

/* ── Build query parts ──────────────────────────────────────────────── */
$visible_groups = array_keys($user_groups_data);
$groups_in      = implode(',', $visible_groups) ?: '0';
$users_in       = implode(',', array_keys($moderators)) ?: '0';
$forum_permissions = forum_permissions();
$query_part = '';

foreach ($visible_groups as $vg) {
    $query_part .= ($db->type === 'pgsql')
        ? "'$vg' = ANY (string_to_array(additionalgroups, ',')) OR "
        : "FIND_IN_SET('$vg', additionalgroups) OR ";
}


/* ── Fetch users ────────────────────────────────────────────────────── */
$query = $db->simple_select('users',
    'id, username, displaygroup, usergroup, additionalgroups,
     ignorelist, hideemail, receivepms, lastactive, lastvisit, invisible, avatar, avatardimensions',
    $query_part .
    "displaygroup IN ($groups_in) OR (displaygroup='0' AND usergroup IN ($groups_in)) OR id IN ($users_in)",
    ['order_by' => 'username']
);

while ($user = $db->fetch_array($query)) {
    if (isset($moderators[$user['id']])) {
        $forumlist = '';
        foreach ($moderators[$user['id']] as $forum) {
            if ($forum_permissions[$forum['fid']]['canview'] == 1) {
                $forumlist .= '<a href="' . get_forum_link($forum['fid']) . '" class="badge bg-light text-dark border me-1 mb-1">'
                            . htmlspecialchars_uni($forum['name']) . '</a>';
            }
        }
        $user['forumlist'] = $forumlist;
        $usergroups[6]['user_list'][$user['id']] = $user;
    }

    if (isset($usergroups[0]['user_list'])) {
        foreach ($usergroups[0]['user_list'] as $uid => $ud) {
            if ($user['id'] == $uid) {
                $user['leaded'] = $ud['leaded'];
                $usergroups[0]['user_list'][$uid] = $user;
            }
        }
    }

    if ($user['displaygroup'] == '6' || $user['usergroup'] == '6') {
        $usergroups[6]['user_list'][$user['id']] = $user;
    }

    $group = $user['displaygroup'] != 0 ? $user['displaygroup'] : $user['usergroup'];
    if (isset($usergroups[$group]) && $group != 6) {
        $usergroups[$group]['user_list'][$user['id']] = $user;
    }

    if ($user['additionalgroups'] !== '') {
        foreach (explode(',', $user['additionalgroups']) as $ag) {
            if (in_array($ag, $visible_groups)) {
                $usergroups[$ag]['user_list'][$user['id']] = $user;
            }
        }
    }
}

/* ── Helper: build one member card ──────────────────────────────────── */
function build_member_card(array $user, string $scopeHtml, bool $showScope,
                            bool $online, string $emailcode, string $pmcode,
                            string $lastvisit): string
{
    // Avatar
    $av = format_avatar($user['avatar'] ?? '', $user['avatardimensions'] ?? '');
    $avatar_html = str_starts_with($av['image'], '<svg')
        ? '<div class="team-avatar-svg">' . $av['image'] . '</div>'
        : '<img src="' . htmlspecialchars($av['image']) . '" alt="" class="team-avatar">';

    $online_dot = $online
        ? '<span class="online-dot" title="Online"></span>'
        : '<span class="offline-dot" title="Offline"></span>';

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
                <div class="team-meta">
                    <i class="fas fa-clock me-1"></i>{$lastvisit}
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

foreach ($usergroups as $usergroup) {
    if (!isset($usergroup['user_list'])) continue;

    $modrows = $usergrouprows = '';

    foreach ($usergroup['user_list'] as $user) {
        $user['username']    = format_name(htmlspecialchars_uni($user['username']),
                                           $user['usergroup'], $user['displaygroup']);
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
                . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-at me-1"></i>'
                . $lang->showteam['postbit_button_email'] . '</a>';
        }
        if ($user['receivepms'] && my_strpos(',' . $user['ignorelist'] . ',', ',' . $CURUSER['id'] . ',') === false) {
            $pmcode = '<a href="private.php?action=send&amp;uid=' . $user['id']
                . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-envelope me-1"></i>'
                . $lang->showteam['postbit_button_pm'] . '</a>';
        }

        $plugins->run_hooks('showteam_user');

        $isMod = ($usergroup['gid'] == 0 && !empty($user['leaded']))
              || ($usergroup['gid'] == 6 && !empty($user['forumlist']));

        if ($isMod) {
            $scope = $usergroup['gid'] == 0 ? $user['leaded'] : ($user['forumlist'] ?? '');
            $modrows .= build_member_card($user, $scope, true, $online,
                                          $emailcode, $pmcode, $lastvisit);
        } else {
            $usergrouprows .= build_member_card($user, '', false, $online,
                                                $emailcode, $pmcode, $lastvisit);
        }
    }

    $rows = $modrows ?: $usergrouprows;
    if (!$rows) continue;

    $grouplist .= <<<HTML
<div class="team-section">
    <div class="team-section-title">{$usergroup['title']}</div>
    <div class="row g-3">{$rows}</div>
</div>
HTML;
}

if (empty($grouplist)) stderr('error_noteamstoshow');

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