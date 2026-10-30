<?php
declare(strict_types=1);

/**
 * Who's Online (WOL) — location parsing and rendering for online.php and the
 * "last seen" block in member.php.
 *
 * sessions.location holds SCRIPT_NAME + query string (rewritten SEO URLs are
 * already resolved: /user-1.html is stored as /member.php?action=profile&id=1).
 */

if (!defined('IN_MYBB')) {
    exit('Direct access not allowed.');
}

require_once INC_PATH . '/functions_forum.php';

/**
 * Fill {1}, {2}… placeholders. $lang->load() turns {N} into %N$s,
 * so both forms are replaced (strtr, not sprintf: a literal % is safe).
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

/**
 * Parsed location of one session
 */
final class UserActivity
{
    public function __construct(
        public readonly string $key,            // what the visitor is doing, see wol_describe()
        public readonly string $file = '',      // script name without .php
        public readonly array $params = [],     // parsed query string
        public readonly string $location = '',  // raw sessions.location
        public readonly bool $nopermission = false,
    ) {}

    public function int(string $name): int
    {
        return (int)($this->params[$name] ?? 0);
    }

    public function str(string $name): string
    {
        $v = $this->params[$name] ?? '';
        return is_string($v) ? $v : '';
    }
}

/* ══════════════════════════════════════════════════════════════════════════
 *  Parsing
 * ══════════════════════════════════════════════════════════════════════════ */

/**
 * Pages that only need a fixed phrase: file => lang key
 */
function wol_static_pages(): array
{
    return [
        'index'          => 'viewing_index',
        'index2'         => 'wol_forum_index',
        'upload'         => 'wol_upload',
        'mybonus'        => 'wol_mybonus',
        'bonuslog'       => 'wol_bonuslog',
        'chat'           => 'wol_chat',
        'chat_archive'   => 'wol_chat_archive',
        'faq'            => 'wol_faq',
        'rules'          => 'wol_rules',
        'formats'        => 'wol_formats',
        'videoformats'   => 'wol_formats',
        'links'          => 'wol_links',
        'memberlist'     => 'viewing_memberlist',
        'showteam'       => 'wol_team',
        'stats'          => 'wol_stats',
        'topten'         => 'wol_topten',
        'requests'       => 'wol_requests',
        'offers'         => 'wol_offers',
        'invite'         => 'wol_invite',
        'notifications'  => 'wol_notifications',
        'bookmark'       => 'wol_bookmarks',
        'contact'        => 'viewing_contact_us',
        'contactstaff'   => 'wol_contactstaff',
        'claim'          => 'wol_claim',
        'task'           => 'wol_tasks',
        'getrss'         => 'wol_rss',
        'rss'            => 'wol_rss',
        'syndication'    => 'wol_rss',
        'language'       => 'wol_language',
        'moderation'     => 'wol_moderation',
        'recoverhint'    => 'member_lostpw',
        'unbaniprequest' => 'wol_unban',
        'takereport'     => 'reporting_post',
        'report_captcha' => 'reporting_post',
        'takereseed'     => 'wol_reseed',
        'polls'          => 'wol_polls',
        'error'          => 'wol_error',
    ];
}

/**
 * Background / AJAX endpoints: they overwrite the location of the page the
 * visitor actually has open, so we show a neutral "on the site" phrase.
 */
function wol_background_pages(): array
{
    return [
        'xmlhttp', 'ajax_imdb', 'ajax_torrent_preview', 'torrent_preview', 'notifications_ajax',
        'upload_image', 'upload_attachment', 'cron', 'ts_cron', 'clear_ann', 'takeflush',
        'announce', 'scrape',
    ];
}

/**
 * Parse URL and determine user activity
 */
function fetch_wol_activity(string $location, bool $nopermission = false): UserActivity
{
    $raw = $location;

    // Stored through htmlspecialchars_uni()
    $location = html_entity_decode($location, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $path  = (string)parse_url($location, PHP_URL_PATH);
    $query = (string)parse_url($location, PHP_URL_QUERY);
    $file  = basename($path, '.php');
    $p     = parse_query_string($query);

    if ($nopermission) {
        return new UserActivity('noperms', $file, $p, $raw, true);
    }

    // Staff panel lives in /admin/ and its pages share names with public ones (index.php)
    if (preg_match('#/admin/#i', $path)) {
        return new UserActivity('staffpanel', $file, $p, $raw);
    }

    $action = (string)($p['action'] ?? '');

    $key = match ($file) {
        'details'       => !empty($p['id']) ? 'torrent' : 'browse',
        'details_edit'  => 'torrent_edit',
        'download'      => 'download',
        'viewsnatches'  => 'snatches',
        'comment'       => 'torrent_comment',
        'browse'        => !empty($p['category']) ? 'browse_category' : (($p['search'] ?? '') !== '' ? 'browse_search' : 'browse'),
        'forumdisplay'  => 'forum',
        'showthread'    => 'thread',
        'printthread'   => 'thread_print',
        'newthread'     => 'thread_new',
        'newreply'      => 'thread_reply',
        'editpost'      => 'post_edit',
        'announcements' => 'announcement',
        'attachment'    => 'attachment',
        'search'        => 'search',
        'userhistory'   => 'userhistory',
        'online'        => $action === 'today' ? 'woltoday' : 'wol',
        'member'        => wol_member_key($action, $p),
        'usercp'        => wol_usercp_key($action),
        'private'       => wol_private_key($action),
        'modcp'         => 'modcp:' . $action,
        'misc'          => wol_misc_key($action),
        default         => null,
    };

    if ($key === null) {
        $static = wol_static_pages();
        if (isset($static[$file])) {
            $key = 'static:' . $static[$file];
        } elseif (in_array($file, wol_background_pages(), true)) {
            $key = 'background';
        } else {
            $key = 'unknown';
        }
    }

    return new UserActivity($key, $file, $p, $raw);
}

function wol_member_key(string $action, array $p): string
{
    return match ($action) {
        'profile'                                   => !empty($p['id']) || !empty($p['uid']) ? 'profile' : 'static:viewing_profile',
        'register', 'do_register'                   => 'static:registering',
        'login', 'do_login', 'verify_2fa'           => 'static:logging_in',
        'logout'                                    => 'static:logging_out',
        'lostpw', 'do_lostpw', 'resetpassword'      => 'static:member_lostpw',
        'activate'                                  => 'static:activating_account',
        'resendactivation', 'do_resendactivation'   => 'static:member_resendactivation',
        'emailuser', 'do_emailuser'                 => 'static:emailing_user',
        'viewnotes'                                 => 'static:wol_viewnotes',
        default                                     => 'static:wol_member',
    };
}

function wol_usercp_key(string $action): string
{
    return 'static:' . match ($action) {
        'profile', 'do_profile'     => 'updating_profile',
        'options', 'do_options'     => 'updating_options',
        'password', 'do_password'   => 'editing_password',
        'editsig', 'do_editsig'     => 'editing_signature',
        'avatar', 'do_avatar'       => 'changing_avatar',
        'editlists', 'do_editlists' => 'managing_buddyignorelist',
        'subscriptions'             => 'viewing_subscriptions',
        'addsubscription', 'do_addsubscription', 'removesubscription', 'removesubscriptions' => 'managing_subscriptions',
        'notepad', 'do_notepad'     => 'editing_pad',
        'sessions', 'do_sessions'   => 'wol_usercp_sessions',
        default                     => 'user_cp',
    };
}

function wol_private_key(string $action): string
{
    return 'static:' . match ($action) {
        'send', 'do_send'       => 'sending_pm',
        'read'                  => 'reading_pm',
        'folders', 'do_folders' => 'editing_pmfolders',
        default                 => 'using_pmsystem',
    };
}

function wol_misc_key(string $action): string
{
    return match ($action) {
        'whoposted'   => 'whoposted',
        'help'        => 'static:viewing_helpdocs',
        'smilies'     => 'static:viewing_smilies',
        'buddypopup'  => 'static:viewing_buddylist',
        'syndication' => 'static:viewing_syndication',
        'markread'    => 'static:wol_markread',
        default       => 'background',
    };
}

/* ══════════════════════════════════════════════════════════════════════════
 *  Names (users, torrents, threads…) with a per-request cache
 * ══════════════════════════════════════════════════════════════════════════ */

/**
 * Escaped display name of an object, or '' if it does not exist.
 */
function wol_name(string $type, int $id): string
{
    global $db;
    static $cache = [];

    if ($id <= 0) {
        return '';
    }
    if (isset($cache[$type][$id])) {
        return $cache[$type][$id];
    }

    $sql = match ($type) {
        'user'         => 'SELECT username AS n FROM users WHERE id = ?',
        'torrent'      => 'SELECT name AS n FROM torrents WHERE id = ?',
        'thread'       => 'SELECT subject AS n FROM threads WHERE tid = ?',
        'forum'        => 'SELECT name AS n FROM forums WHERE fid = ?',
        'category'     => 'SELECT name AS n FROM categories WHERE id = ?',
        'announcement' => 'SELECT subject AS n FROM announcements WHERE id = ?',
        default        => null,
    };

    $name = '';
    if ($sql !== null) {
        $q   = $db->sql_query_prepared($sql, [$id]);
        $row = $q ? $db->fetch_array($q) : null;
        if (is_array($row) && isset($row['n'])) {
            $name = htmlspecialchars_uni((string)$row['n']);
        }
    }

    return $cache[$type][$id] = $name;
}

/**
 * Thread id of a post (editpost.php?pid=, attachment → post).
 */
function wol_post_tid(int $pid): int
{
    global $db;
    static $cache = [];

    if ($pid <= 0) {
        return 0;
    }
    if (!isset($cache[$pid])) {
        $q   = $db->sql_query_prepared('SELECT tid FROM posts WHERE pid = ?', [$pid]);
        $row = $q ? $db->fetch_array($q) : null;
        $cache[$pid] = (int)($row['tid'] ?? 0);
    }
    return $cache[$pid];
}

function wol_attachment_tid(int $aid): int
{
    global $db;
    static $cache = [];

    if ($aid <= 0) {
        return 0;
    }
    if (!isset($cache[$aid])) {
        $q   = $db->sql_query_prepared('SELECT pid FROM attachments WHERE aid = ?', [$aid]);
        $row = $q ? $db->fetch_array($q) : null;
        $cache[$aid] = wol_post_tid((int)($row['pid'] ?? 0));
    }
    return $cache[$aid];
}

/* ══════════════════════════════════════════════════════════════════════════
 *  Rendering
 * ══════════════════════════════════════════════════════════════════════════ */

/**
 * Lang string with an English fallback.
 */
function wol_lang(string $key, string $fallback): string
{
    global $lang;
    $v = $lang->online[$key] ?? '';
    return is_string($v) && $v !== '' ? $v : $fallback;
}

/**
 * "<phrase with link to the object>", or the plain phrase if the object is gone.
 */
function wol_linked(string $key, string $fallback, string $plain_key, string $plain_fallback, string $url, string $name, string ...$extra): string
{
    if ($name === '') {
        return wol_lang($plain_key, $plain_fallback);
    }
    return ags_fmt(wol_lang($key, $fallback), htmlspecialchars_uni($url), $name, ...$extra);
}

function build_friendly_wol_location(UserActivity $a): string
{
    global $SITENAME, $usergroups;

    // Fixed phrases
    if (str_starts_with($a->key, 'static:')) {
        $k = substr($a->key, 7);
        $s = wol_lang($k, wol_default_phrase($k));
        return $k === 'viewing_index' ? ags_fmt($s, htmlspecialchars_uni((string)$SITENAME)) : ags_fmt($s, '');
    }

    if (str_starts_with($a->key, 'modcp:')) {
        return wol_modcp_phrase(substr($a->key, 6));
    }

    switch ($a->key) {
        case 'torrent':
            $id = $a->int('id');
            return wol_linked('viewing_torrent2', 'Viewing Torrent Details <a href="{1}">{2}</a>', 'viewing_torrent', 'Viewing Torrent',
                get_torrent_link($id), wol_name('torrent', $id));

        case 'torrent_edit':
            $id = $a->int('id');
            return wol_linked('wol_torrent_edit2', 'Editing torrent <a href="{1}">{2}</a>', 'wol_torrent_edit', 'Editing a torrent',
                get_torrent_link($id), wol_name('torrent', $id));

        case 'torrent_comment':
            $id = $a->int('id') ?: $a->int('tid');
            return wol_linked('wol_comment2', 'Commenting on torrent <a href="{1}">{2}</a>', 'wol_comment', 'Writing a comment',
                get_torrent_link($id), wol_name('torrent', $id));

        case 'snatches':
            $id = $a->int('id');
            return wol_linked('wol_snatches2', 'Viewing snatches of <a href="{1}">{2}</a>', 'wol_snatches', 'Viewing snatch list',
                get_torrent_link($id), wol_name('torrent', $id));

        case 'download':
            $id = $a->int('id');
            return wol_linked('download_torrent2', 'Download Torrent <a href="{1}">{2}</a>', 'download_torrent', 'Download Torrent',
                get_torrent_link($id), wol_name('torrent', $id));

        case 'browse':
            return wol_lang('wol_browse', 'Browsing <a href="browse.php">torrents</a>');

        case 'browse_category':
            $id = $a->int('category');
            return wol_linked('wol_browse_category', 'Browsing category <a href="{1}">{2}</a>', 'wol_browse', 'Browsing <a href="browse.php">torrents</a>',
                'browse.php?category=' . $id, wol_name('category', $id));

        case 'browse_search':
            return wol_lang('wol_browse_search', 'Searching <a href="browse.php">torrents</a>');

        case 'forum':
            $fid = $a->int('fid');
            return wol_linked('viewing_forum2', 'Viewing Forum <a href="{1}">{2}</a>', 'viewing_forum', 'Viewing Forum',
                get_forum_link($fid), wol_name('forum', $fid));

        case 'thread':
        case 'thread_print':
            $tid = $a->int('tid') ?: wol_post_tid($a->int('pid'));
            if ($a->key === 'thread_print') {
                return wol_linked('printing_thread2', 'Printing Thread <a href="{1}">{2}</a>', 'printing_thread', 'Printing a Thread',
                    get_thread_link($tid), wol_name('thread', $tid));
            }
            $page = $a->int('page');
            $page_txt = $page > 1 ? '(' . wol_lang('page', 'Page') . ' ' . $page . ')' : '';
            return wol_linked('reading_thread2', 'Reading Thread <a href="{1}">{2}</a> {3}', 'reading_thread', 'Reading Thread',
                get_thread_link($tid, $page > 1 ? $page : 0), wol_name('thread', $tid), $page_txt);

        case 'thread_new':
            $fid = $a->int('fid');
            return wol_linked('posting_thread2', 'Creating New Thread in <a href="{1}">{2}</a>', 'posting_thread', 'Creating New Thread',
                get_forum_link($fid), wol_name('forum', $fid));

        case 'thread_reply':
            $tid = $a->int('tid');
            return wol_linked('replying_thread2', 'Replying to <a href="{1}">{2}</a>', 'replying_thread', 'Replying to Thread',
                get_thread_link($tid), wol_name('thread', $tid));

        case 'post_edit':
            $tid = wol_post_tid($a->int('pid'));
            return wol_linked('wol_editpost2', 'Editing a post in <a href="{1}">{2}</a>', 'editing_post', 'Editing Post',
                get_thread_link($tid), wol_name('thread', $tid));

        case 'whoposted':
            $tid = $a->int('tid');
            return wol_linked('viewing_whoposted2', 'Viewing Who Posted in <a href="{1}">{2}</a>', 'viewing_whoposted', 'Viewing Who Posted',
                get_thread_link($tid), wol_name('thread', $tid));

        case 'announcement':
            $aid = $a->int('aid');
            return wol_linked('viewing_announcements', 'Viewing Announcement <a href="{1}">{2}</a>', 'viewing_announcements2', 'Viewing Announcement',
                get_announcement_link($aid), wol_name('announcement', $aid));

        case 'attachment':
            $aid = $a->int('aid');
            $tid = wol_attachment_tid($aid);
            $name = wol_name('thread', $tid);
            if ($name === '') {
                return wol_lang('viewing_attachment', 'Viewing Attachment');
            }
            return ags_fmt(wol_lang('viewing_attachment2', 'Viewing <a href="attachment.php?aid={1}" target="_blank">Attachment</a> in Thread <a href="{3}">{2}</a>'),
                (string)$aid, $name, htmlspecialchars_uni(get_thread_link($tid)));

        case 'profile':
            $uid = $a->int('id') ?: $a->int('uid');
            return wol_linked('viewing_profile2', 'Viewing Profile of <a href="{1}">{2}</a>', 'viewing_profile', 'Viewing Profile',
                get_profile_link($uid), wol_name('user', $uid));

        case 'userhistory':
            $uid = $a->int('id') ?: $a->int('uid');
            return wol_linked('wol_userhistory2', 'Viewing post history of <a href="{1}">{2}</a>', 'wol_userhistory', 'Viewing post history',
                get_profile_link($uid), wol_name('user', $uid));

        case 'search':
            return ags_fmt(wol_lang('searching_forum', '<a href="search.php">Searching</a> {1}'), '');

        case 'wol':
            return wol_lang('viewing_wol', 'Viewing <a href="online.php">Who\'s Online</a>');

        case 'woltoday':
            return wol_lang('viewing_woltoday', 'Viewing <a href="online.php?action=today">Who Was Online Today</a>');

        case 'staffpanel':
            // Non-staff do not need to know which admin page is open
            return (int)($usergroups['canstaffpanel'] ?? 0) === 1
                ? wol_lang('wol_staffpanel_link', '<a href="admin/index.php">Staff Panel</a>')
                : wol_lang('wol_staffpanel', 'Staff Panel');

        case 'background':
            return ags_fmt(wol_lang('wol_on_site', 'Browsing {1}'), htmlspecialchars_uni((string)$SITENAME));

        case 'noperms':
            return wol_lang('viewing_noperms', 'Viewing No Permission Page');
    }

    // Unknown: link to the page itself (escaped — this URL comes from the visitor)
    return ags_fmt(wol_lang('unknown_location', '<a href="{1}">Unknown Location</a>'),
        htmlspecialchars_uni($a->location !== '' ? html_entity_decode($a->location, ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'index.php'));
}

/**
 * English fallbacks for fixed phrases (used only if a lang key is missing)
 */
function wol_default_phrase(string $key): string
{
    return match ($key) {
        'viewing_index'      => '{1} <a href="index.php">Main Index</a>',
        'viewing_memberlist' => 'Viewing <a href="memberlist.php">Member List</a>',
        'viewing_contact_us' => 'Viewing <a href="contact.php">Contact Us</a>',
        default              => ucfirst(str_replace(['wol_', '_'], ['', ' '], $key)),
    };
}

function wol_modcp_phrase(string $action): string
{
    [$key, $fallback] = match ($action) {
        'modlogs'             => ['viewing_modlogs', 'Viewing Moderator Logs'],
        'announcements'       => ['managing_announcements', 'Managing Announcements'],
        'finduser'            => ['search_for_user', 'Searching for users'],
        'warninglogs'         => ['managing_warninglogs', 'Managing Warning Logs'],
        'ipsearch'            => ['searching_ips', 'Searching IPs'],
        'reports', 'report'   => ['viewing_reports', 'Viewing Reported Posts'],
        'new_announcement'    => ['adding_announcement', 'Adding an Announcement'],
        'delete_announcement' => ['deleting_announcement', 'Deleting an Announcement'],
        'edit_announcement'   => ['editing_announcement', 'Editing an Announcement'],
        'modqueue', 'mod_queue' => ['managing_modqueue', 'Managing Moderation Queue'],
        'editprofile'         => ['editing_user_profiles', 'Editing User Profiles'],
        'banning'             => ['managing_bans', 'Managing Bans'],
        default               => ['viewing_modcp', 'Viewing Moderator CP'],
    };
    return wol_lang($key, $fallback);
}

/**
 * One table row for online.php
 */
function build_wol_row(array $user): string
{
    global $db, $lang, $CURUSER, $usergroups;

    $uid       = (int)($user['uid'] ?? 0);
    $my_uid    = (int)($CURUSER['id'] ?? 0);
    $is_staff  = (int)($usergroups['canstaffpanel'] ?? 0) === 1;
    $invisible = $uid > 0 && (int)($user['invisible'] ?? 0) === 1;
    $is_bot    = isset($user['bot']);

    // Invisible members: only staff and the member themself
    if ($invisible && !$is_staff && $uid !== $my_uid) {
        return '';
    }

    // ── User cell ─────────────────────────────────────────────────────────
    if ($uid > 0) {
        $avatar   = format_avatar((string)($user['avatar'] ?? ''), (string)($user['avatardimensions'] ?? ''));
        $name     = format_name(htmlspecialchars_uni((string)($user['username'] ?? '')), (int)($user['usergroup'] ?? 1));
        $pic      = '<img class="wol-avatar" src="' . $avatar['image'] . '" alt="" loading="lazy">';
        $title    = build_profile_link($name, $uid);
        $sort_key = mb_strtolower((string)($user['username'] ?? ''));

        if ($invisible) {
            $title .= ' <span class="wol-badge wol-soft-muted" title="' . htmlspecialchars($lang->online['tip_invisible'], ENT_QUOTES) . '"><i class="fa-solid fa-eye-slash"></i></span>';
        }
        if ($uid === $my_uid) {
            $title .= ' <span class="wol-badge wol-soft-primary">' . $lang->online['txt_you'] . '</span>';
        }
        $meta = '<i class="fa-solid fa-id-badge me-1"></i>' . $uid;
    } elseif ($is_bot) {
        $pic      = '<span class="wol-avatar wol-avatar--icon wol-soft-warning"><i class="fa-solid fa-robot"></i></span>';
        $title    = '<span class="fw-semibold">' . htmlspecialchars_uni((string)$user['bot']) . '</span>';
        $sort_key = 'zz' . mb_strtolower((string)$user['bot']);
        $meta     = '<span class="wol-badge wol-soft-warning">' . $lang->online['type_bot'] . '</span>';
    } else {
        $pic      = '<span class="wol-avatar wol-avatar--icon wol-soft-muted"><i class="fa-solid fa-user-secret"></i></span>';
        $title    = '<span class="fw-semibold text-body-secondary">' . $lang->online['txt_guest'] . '</span>';
        $sort_key = 'zzz';
        $meta     = '<span class="wol-badge wol-soft-muted">' . $lang->online['type_guest'] . '</span>';
    }

    // ── Location, time, IP ────────────────────────────────────────────────
    $location = build_friendly_wol_location($user['activity']);
    $time     = (int)($user['time'] ?? 0);

    $ip_cell = '';
    if ($is_staff) {
        $ip = '';
        if (!empty($user['ip'])) {
            $ip = (string)my_inet_ntop($db->unescape_binary($user['ip']));
        }
        $ip_cell = '<td class="wol-ip">' . ($ip !== '' ? '<code>' . htmlspecialchars($ip) . '</code>' : '<span class="wol-meta">—</span>') . '</td>';
    }

    return '
    <tr>
        <td data-sort="' . htmlspecialchars($sort_key, ENT_QUOTES) . '">
            <div class="d-flex align-items-center gap-2 wol-user">
                ' . $pic . '
                <div class="lh-sm">
                    ' . $title . '
                    <div class="wol-meta">' . $meta . '</div>
                </div>
            </div>
        </td>
        <td class="wol-location">' . $location . '</td>
        ' . $ip_cell . '
        <td class="wol-date text-end" data-sort="' . $time . '">
            <span class="wol-badge wol-soft-muted"><i class="fa-regular fa-clock"></i>' . my_datee('relative', $time) . '</span>
        </td>
    </tr>';
}

/* ══════════════════════════════════════════════════════════════════════════
 *  Helpers
 * ══════════════════════════════════════════════════════════════════════════ */

function parse_query_string(string $query): array
{
    $query = str_replace('&amp;', '&', $query);

    $params = [];
    parse_str($query, $params);

    foreach (['uid', 'fid', 'tid', 'pid', 'aid', 'eid', 'id', 'page', 'category'] as $key) {
        if (isset($params[$key])) {
            $params[$key] = is_scalar($params[$key]) ? (int)$params[$key] : 0;
        }
    }

    return $params;
}
