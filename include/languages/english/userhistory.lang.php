<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// userhistory.php
$language['userhistory'] = array
(
	'chistory'         => 'Comment History for {1}',
	'invaliduser'      => 'Invalid User specified. If you followed a valid link, please notify the <a href="/contactstaff.php">administrator.</a>',

	'lbl_uh_total'     => 'Comments',
	'lbl_uh_torrents'  => 'Torrents commented',
	'lbl_uh_month'     => 'Last 30 days',
	'lbl_uh_last'      => 'Last comment',
	'txt_uh_empty'     => 'No comments found',
	'txt_uh_deleted'   => '[Deleted Torrent]',
	'tip_uh_permalink' => 'Permalink',

	'ph_uh_search'     => 'Search by comment text or torrent name',
	'lbl_uh_only_att'  => 'With attachments only',
	'btn_uh_reset'     => 'Reset',
);
?>