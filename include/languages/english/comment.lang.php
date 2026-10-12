<?php


if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// comment.php
$language['comment'] = array
(
	'nopost'				=>'You are not authorized to Post.  (<a href="messages.php">Read Inbox</a>).',
	'flood'					=>'Comment Flooding Not Allowed. Please wait {1} second(s) before posting another comment.',	
	'newcommentsub'			=>'New Comment on your torrent.',
	'newcommenttxt'			=>'You have received a comment on your torrent {1}',
	'addcomment'			=>'Add a new comment to: {1}',
	'order'					=>'<h2>Most recent comments, in reverse order</h2>',	
	'adit'					=>'Edit comment to: {1}',
	'delete'				=>'Delete comment',
	'confirm'				=>'You are about to delete a comment. Click {1}here</a> if you are sure.',
	'original'				=>'Original comment',
	'originalcontest'		=>'<h2>Original contents of comment #{1}</h2>',
	'back'					=>'Go back',
	'modnotice1'			=>'Moderator Message',
	'modnotice2'			=>'Activate the checkbox to remove this Moderator Message.',
	'insertcomment'			=>'Post a New Comment',
	'editcomment'			=>'Edit Comment',
	'floodcomment'	=>'comments',
	'closed'=>'Sorry this torrent has been closed for comment posting!',//Added v4.1
	
	'no_comment_permission' => 'You do not have permission to post comments. Contact staff if you believe this is a mistake.',
);
?>
