<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['recount_rebuild'] = array (

// ── Page header ──
'page_title'          => 'Recount & Rebuild',
'page_subtitle'       => 'Fix counters and caches that drifted out of sync. Big jobs run in batches with a progress bar.',
'lbl_tools'           => '{1} tools',

// ── Sections ──
'sec_forums'          => 'Forums & threads',
'sec_users'           => 'Users',
'sec_media'           => 'Attachments',
'sec_site'            => 'Torrents & statistics',

// ── Tasks: titles ──
'title_forum_counters'           => 'Forum counters',
'title_thread_counters'          => 'Thread counters',
'title_poll_counters'            => 'Poll counters',
'title_thread_ratings'           => 'Thread ratings',
'title_user_posts'               => 'User post counts',
'title_user_threads'             => 'User thread counts',
'title_user_comments'            => 'User comment counts',
'title_private_messages'         => 'Private messages',
'title_attachment_thumbs'        => 'Attachment thumbnails',
'title_comment_attachment_thumbs'=> 'Comment attachment thumbnails',
'title_torrent_comments'         => 'Torrent comment counts',
'title_stats'                    => 'Board statistics',
'title_recount'                  => 'Recount',

// ── Tasks: descriptions ──
'desc_forum_counters'            => 'Post/thread counters and last post of every forum.',
'desc_thread_counters'           => 'Reply/view counters and last post of every thread.',
'desc_poll_counters'             => 'Vote counters and totals of every poll.',
'desc_thread_ratings'            => 'Average rating and vote count cached on each thread.',
'desc_user_posts'                => 'Post count of each user from the posts in the database.',
'desc_user_threads'              => 'Thread count of each user from the threads in the database.',
'desc_user_comments'             => 'Torrent comment count of each user.',
'desc_private_messages'          => 'Private message counters of each user.',
'desc_attachment_thumbs'         => 'Regenerate forum attachment thumbnails at the current size.',
'desc_comment_attachment_thumbs' => 'Regenerate comment attachment thumbnails at the current size.',
'desc_torrent_comments'          => 'Comment count cached on each torrent.',
'desc_stats'                     => 'Totals on the forum index and statistics pages. Runs in one step.',

// ── Task card controls ──
'lbl_per_step'        => '/ step',
'lbl_single_step'     => 'single step',
'tip_per_batch'       => 'Items per batch',
'btn_run'             => 'Run',
'hint_footer'         => 'Smaller batches are slower but safer on a busy server; thumbnails are the heaviest job.',

// ── Progress page ──
'lbl_processing'      => 'Processing batch {1} · {2} per step',
'btn_stop'            => 'Stop',
'btn_continuing'      => 'Continuing…',
'hint_keep_open'      => 'Keep this tab open — the next batch starts automatically.',

// ── Flash messages ──
'flash_done'                     => 'The recount has been completed successfully.',
'flash_forum_counters'           => 'The forum counters have been rebuilt successfully.',
'flash_thread_counters'          => 'The thread counters have been rebuilt successfully.',
'flash_poll_counters'            => 'The poll counters have been rebuilt successfully.',
'flash_thread_ratings'           => 'Thread ratings have been recounted successfully.',
'flash_torrent_comments'         => 'Torrent comment counts have been recounted successfully.',
'flash_user_posts'               => 'User post counts have been recounted successfully.',
'flash_user_threads'             => 'User thread counts have been recounted successfully.',
'flash_private_messages'         => 'User private message counts have been recounted successfully.',
'flash_user_comments'            => 'User comment counts have been recounted successfully.',
'flash_attachment_thumbs'        => 'The attachment thumbnails have been rebuilt successfully.',
'flash_comment_attachment_thumbs'=> 'The comment attachment thumbnails have been rebuilt successfully.',
'flash_stats'                    => 'The forum statistics have been rebuilt successfully.',

// ── Errors ──
'err_security_title'  => 'Security Error',
'err_security_token'  => 'Invalid security token. Please refresh the page and try again.',

// ── JS strings ──
'js_starting'         => 'Starting…',

);
?>
