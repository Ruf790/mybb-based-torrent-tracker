<?php
declare(strict_types=1);

/**
 * Syndication (RSS/Atom feed)
 * Updated for PHP 8.x
 */

define('IN_MYBB',           1);
define('IGNORE_CLEAN_VARS', 'fid');
define('NO_ONLINE',         1);
define('THIS_SCRIPT',       'syndication.php');
define('SCRIPTNAME',        'syndication.php');
define('IN_FORUM',          true);

require_once 'global.php';

$lang->load('syndication');

require_once INC_PATH . '/class_feedgeneration.php';
$feedgenerator = new FeedGenerator();

require_once INC_PATH . '/class_parser.php';
$parser = new postParser;

// ── Thread limit ──────────────────────────────────────────────────────────────
$isPortal = $mybb->get_input('portal') && ($mybb->settings['portal'] ?? 0) != 0;

if ($isPortal) {
    $thread_limit = (int)($mybb->settings['portal_numannouncements'] ?? 15);
} else {
    $thread_limit = $mybb->get_input('limit', MyBB::INPUT_INT);
}

$thread_limit = match(true) {
    $thread_limit > 50       => 50,
    $thread_limit < 1        => 15,
    default                  => $thread_limit,
};

// ── Forum list ────────────────────────────────────────────────────────────────
$forumlist = [];

if ($isPortal && ($mybb->settings['portal_announcementsfid'] ?? '') !== '-1') {
    $forumlist = explode(',', $mybb->settings['portal_announcementsfid'] ?? '');
} elseif ($mybb->get_input('fid')) {
    $forumlist = explode(',', $mybb->get_input('fid'));
}

$inactiveforums = get_inactive_forums();
$unviewable     = '';

$plugins->run_hooks('syndication_start');

if ($unviewableforums ?? '') {
    $unviewable .= " AND fid NOT IN($unviewableforums)";
}
if ($inactiveforums) {
    $unviewable .= " AND fid NOT IN($inactiveforums)";
}

// ── Build forum SQL ───────────────────────────────────────────────────────────
$all_forums  = false;
$forumlistsql = '';

if (!empty($forumlist)) {
    $forum_ids = "'-1'";
    foreach ($forumlist as $fid) {
        $forum_ids .= ",'" . (int)$fid . "'";
    }
    $forumlistsql = "AND fid IN ($forum_ids) $unviewable";
} else {
    $forumlistsql = $unviewable;
    $all_forums   = true;
}

// ── Feed title ────────────────────────────────────────────────────────────────
$title       = $mybb->settings['bbname'] ?? $SITENAME;
$forumcache  = [];
$comma       = ' - ';

$query = $db->sql_query_prepared('SELECT name, fid FROM forums WHERE 1=1 ' . $forumlistsql);
while ($forum = $db->fetch_array($query)) {
    if (!$isPortal) {
        $title .= $comma . $forum['name'];
        $comma  = $lang->comma ?? ', ';
    }
    $forumcache[$forum['fid']] = $forum;
}

if ($isPortal) {
    $title .= $comma . ($lang->portal ?? 'Portal');
}

if ($all_forums) {
    $title = $isPortal
        ? $SITENAME . ' - ' . ($lang->portal    ?? 'Portal')
        : $SITENAME . ' - ' . ($lang->all_forums ?? 'All Forums');
}

// ── Feed setup ────────────────────────────────────────────────────────────────
$feedgenerator->set_feed_format($mybb->get_input('type'));
$feedgenerator->set_channel([
    'title'       => $title,
    'link'        => $BASEURL . '/',
    'date'        => TIMENOW,
    'description' => $SITENAME . ' - ' . $BASEURL,
]);

// ── Permissions ───────────────────────────────────────────────────────────────
$permsql    = '';
$onlyusfids = [];

foreach (forum_permissions() as $fid => $fp) {
    if ((int)($fp['canonlyviewownthreads'] ?? 0) === 1) {
        $onlyusfids[] = (int)$fid;
    }
}

if (!empty($onlyusfids)) {
    $fids     = implode(',', $onlyusfids);
    $uid      = (int)($CURUSER['id'] ?? 0);
    $permsql  = "AND ((fid IN($fids) AND uid='$uid') OR fid NOT IN($fids))";
}

// ── Threads ───────────────────────────────────────────────────────────────────
$items      = [];
$firstposts = [];

$query = $db->sql_query_prepared(
    "SELECT subject, tid, dateline, firstpost FROM threads
     WHERE visible='1' AND closed NOT LIKE 'moved|%' {$permsql} {$forumlistsql}
     ORDER BY dateline DESC
     LIMIT ?",
    [$thread_limit]
);

while ($thread = $db->fetch_array($query)) {
    $items[$thread['tid']] = [
        'title' => $parser->parse_badwords($thread['subject']),
        'link'  => $BASEURL . '/' . get_thread_link($thread['tid']),
        'date'  => $thread['dateline'],
    ];
    $firstposts[] = (int)$thread['firstpost'];
}

$plugins->run_hooks('syndication_get_posts');

// ── Posts + attachments ───────────────────────────────────────────────────────
if (!empty($firstposts)) {
    $firstpostlist = 'pid IN(' . implode(',', $firstposts) . ')';
    $attachments   = [];

    if (($enableattachments ?? 0) == 1) {
        $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE {$firstpostlist}");
        while ($attachment = $db->fetch_array($query)) {
            $attachments[$attachment['pid']][] = $attachment;
        }
    }

    $query = $db->sql_query_prepared(
        "SELECT message, edittime, tid, uid, username, fid, pid FROM posts
         WHERE {$firstpostlist}
         ORDER BY dateline DESC, pid DESC"
    );

    while ($post = $db->fetch_array($query)) {
        $fid            = $post['fid'];
        $forumSettings  = $forumcache[$fid] ?? [];

        $parser_options = [
            'allow_html'      => (int)($forumSettings['allowhtml']     ?? 0),
            'allow_mycode'    => (int)($forumSettings['allowmycode']   ?? 1),
            'allow_smilies'   => (int)($forumSettings['allowsmilies']  ?? 1),
            'allow_imgcode'   => (int)($forumSettings['allowimgcode']  ?? 1),
            'allow_videocode' => (int)($forumSettings['allowvideocode']?? 1),
            'filter_badwords' => 1,
            'filter_cdata'    => 1,
        ];

        $parsed_message = $parser->parse_message($post['message'], $parser_options);

        // Вложения
        foreach ($attachments[$post['pid']] ?? [] as $attachment) {
            $ext  = get_extension($attachment['filename']);
            $name = htmlspecialchars_uni($attachment['filename']);
            $size = mksize($attachment['filesize']);
            $icon = get_attachment_icon($ext);

            $attbit = '
<div class="row mt-2 g-1 text-muted">
    <div class="col-auto align-self-center">' . $icon . '</div>
    <div class="col align-self-center">
        <a href="attachment.php?aid=' . (int)$attachment['aid'] . '" target="_blank">' . $name . '</a>
        (Size: <span class="text-dark">' . $size . '</span>
        Downloads: <span class="text-dark">' . (int)$attachment['downloads'] . '</span>)
    </div>
</div>';

            $aid = (int)$attachment['aid'];
            if (stripos($parsed_message, "[attachment={$aid}]") !== false) {
                $parsed_message = preg_replace(
                    "#\[attachment={$aid}]#si",
                    $attbit,
                    $parsed_message
                );
            } else {
                $parsed_message .= '<br>' . $attbit;
            }
        }

        if (!isset($items[$post['tid']])) continue;

        $items[$post['tid']]['description'] = $parsed_message;
        $items[$post['tid']]['updated']     = $post['edittime'];
        $items[$post['tid']]['author']      = [
            'uid'  => (int)$post['uid'],
            'name' => $post['username'],
        ];
        $feedgenerator->add_item($items[$post['tid']]);
    }
}

$feedgenerator->output_feed();