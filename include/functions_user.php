<?php

declare(strict_types=1);

function validate_password_from_uid(int|string $uid, string $password, array $user = []): array|false
{
    global $db, $mybb, $CURUSER;

    if (isset($CURUSER['id']) && $CURUSER['id'] == $uid) {
        $user = $mybb->user;
    }

    if (!$user['password']) {
        $user = get_user((int)$uid);
    }

    if (empty($user['loginkey'])) 
	{
        $user['loginkey'] = generate_loginkey();

        if (!empty($user['id'])) 
		{
            $db->sql_query_prepared("UPDATE users SET loginkey = ? WHERE id = ?", [$user['loginkey'], $user['id']]);
        }
    }

    if (verify_user_password($user, $password)) {
        return $user;
    }

    return false;
}


function create_password(string $password, array|false $user = false): array
{
    global $plugins;

    $fields = null;

    $parameters = compact('password', 'user', 'fields');

    if (!defined('IN_INSTALL') && !defined('IN_UPGRADE')) {
        $plugins->run_hooks('create_password', $parameters);
    }

    if ($parameters['fields'] !== null) {
        $fields = $parameters['fields'];
    } 
	
	else 
	{
       
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $fields = [
            'password' => $hash,
        ];
    }

    return $fields;
}

function verify_user_password(array $user, string $password): bool
{
    global $plugins;

    $result = null;

    $parameters = compact('user', 'password', 'result');

    if (!defined('IN_INSTALL') && !defined('IN_UPGRADE')) {
        $plugins->run_hooks('verify_user_password', $parameters);
    }

    if ($parameters['result'] !== null) {
        return $parameters['result'];
    }

 
    return password_verify($password, $user['password'] ?? '');
}


function generate_loginkey(): string
{
    return random_str(50);
}


function update_loginkey(int|string $uid): string
{
    global $db;

    $loginkey = generate_loginkey();
    $db->sql_query_prepared("UPDATE users SET loginkey = ? WHERE id = ?", [$loginkey, $uid]);

    return $loginkey;
}


function add_subscribed_thread(int|string $tid, int|string $notification = 1, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $query = $db->sql_query_prepared("SELECT * FROM threadsubscriptions WHERE tid = ? AND uid = ?", [(int)$tid, (int)$uid]);
    $subscription = $query ? $db->fetch_array($query) : null;

    if (!$subscription) {
        $db->sql_query_prepared(
            "INSERT INTO threadsubscriptions (`uid`,`tid`,`notification`,`dateline`) VALUES (?,?,?,?)",
            [(int)$uid, (int)$tid, (int)$notification, TIMENOW]
        );
    } else {
        // Subscription exists - simply update notification
        $db->sql_query_prepared(
            "UPDATE threadsubscriptions SET notification = ? WHERE uid = ? AND tid = ?",
            [(int)$notification, $uid, $tid]
        );
    }

    return true;
}


function remove_subscribed_thread(int|string $tid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE tid = ? AND uid = ?", [$tid, $uid]);

    return true;
}


function add_subscribed_forum(int|string $fid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $fid = (int)$fid;
    $uid = (int)$uid;

    $query = $db->sql_query_prepared("SELECT * FROM forumsubscriptions WHERE fid = ? AND uid = ? LIMIT 1", [$fid, $uid]);
    $fsubscription = $query ? $db->fetch_array($query) : null;

    if (!$fsubscription) {
        $db->sql_query_prepared("INSERT INTO forumsubscriptions (`fid`,`uid`) VALUES (?,?)", [$fid, $uid]);
    }

    return true;
}


function remove_subscribed_forum(int|string $fid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE fid = ? AND uid = ?", [$fid, $uid]);

    return true;
}


function update_pm_count(int|string $uid = 0, int $count_to_update = 7): array
{
    global $db, $mybb, $CURUSER;

    // If no user id, assume that we mean the current logged in user.
    if ((int)$uid == 0) {
        $uid = (int)(is_array($CURUSER) ? ($CURUSER['id'] ?? 0) : 0);
    }

    $uid = (int)$uid;
    $pmcount = [];

    if ($uid == 0) {
        return $pmcount;
    }

    // Update total number of messages.
    if ($count_to_update & 1) {
        $query = $db->sql_query_prepared("SELECT COUNT(pmid) AS pms_total FROM privatemessages WHERE uid = ?", [$uid]);
        $total = $query ? $db->fetch_array($query) : null;
        $pmcount['totalpms'] = $total['pms_total'];
    }

    // Update number of unread messages.
    if ($count_to_update & 2 && $db->field_exists("unreadpms", "users") == true) {
        $query = $db->sql_query_prepared(
            "SELECT COUNT(pmid) AS pms_unread FROM privatemessages WHERE uid = ? AND status = '0' AND folder = '1'",
            [$uid]
        );
        $unread = $query ? $db->fetch_array($query) : null;
        $pmcount['unreadpms'] = $unread['pms_unread'];
    }

    if (!empty($pmcount)) {
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($pmcount)));
        $params = array_values($pmcount);
        $params[] = $uid;

        $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);
    }

    return $pmcount;
}


function get_pm_folder_name(int|string $fid, string $name = ''): string
{
    global $lang;

    // Built-in folders: name from the language file (usercpnav), English fallback
    $builtin = [
        0 => ['ucp_nav_folder_inbox',  'Inbox'],
        1 => ['ucp_nav_folder_unread', 'Unread'],
        2 => ['ucp_nav_folder_sent',   'Sent Items'],
        3 => ['ucp_nav_folder_drafts', 'Drafts'],
        4 => ['ucp_nav_folder_trash',  'Trash Can'],
    ];
    $fid = (int)$fid;

    // A user's own folder name is shown as is; an old saved English default is translated
    if ($name !== '' && (!isset($builtin[$fid]) || $name !== $builtin[$fid][1])) {
        return $name;
    }

    if (!isset($lang->usercpnav) || !is_array($lang->usercpnav)) {
        $lang->load('usercpnav');
    }
    [$key, $fallback] = $builtin[$fid] ?? ['ucp_nav_folder_untitled', 'Untitled folder'];
    return (string)($lang->usercpnav[$key] ?? $fallback);
}
