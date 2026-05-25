<?php

declare(strict_types=1);


function notify_upload_subscribers(int $catid, int $torrent_id, string $torrent_name): void
{
    global $BASEURL, $SITENAME, $CURUSER, $db;

	
	$torrent_link = $BASEURL . '/' . get_torrent_link($torrent_id).'';
	
	
    $res = $db->sql_query("
        SELECT u.id, u.username, u.email, u.notifs
        FROM users u
        LEFT JOIN usergroups g ON (u.usergroup = g.gid)
        WHERE u.enabled = 'yes'
        AND u.ustatus = 'confirmed'
        AND u.notifs != ''
        AND u.notifs LIKE '%[cat{$catid}]%'
        AND u.id != '" . (int)$CURUSER['id'] . "'
    ");

    if (!$res || $db->num_rows($res) === 0) return;

    

	$row      = $db->fetch_array($db->simple_select('categories', 'name', "id='{$catid}'"));
    $cat_name = $row['name'] ?? 'Unknown Category';

    $subject = "New torrent in {$cat_name}: " . $torrent_name;
	
	require_once INC_PATH . '/functions_pm.php';
	


    $body_pm  = "A new torrent has been uploaded in your subscribed category:\n\n"
              . "[URL={$torrent_link}][b]{$torrent_name}[/b][/URL]\n\n"
              . "Manage subscriptions: [URL={$BASEURL}/usercp.php?action=options]User CP[/URL]";


    $body_email = "A new torrent has been uploaded in your subscribed category:\n\n"
                . $torrent_name . "\n"
                . $torrent_link . "\n\n"
                . "Manage subscriptions: {$BASEURL}/usercp.php?action=options\n\n"
                . "— " . $SITENAME;

    while ($user = $db->fetch_array($res)) {

        // PM уведомление
        if (strpos($user['notifs'], '[pm]') !== false) {
            
			
			
			$pm = [
                'subject' => $subject,
                'message' => $body_pm,
				'touid' => (int)$user['id']
                
            ];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
					
        }

        // Email уведомление
        if (strpos($user['notifs'], '[email]') !== false && !empty($user['email'])) {
            my_mail($user['email'], $subject, $body_email);
        }
    }
}