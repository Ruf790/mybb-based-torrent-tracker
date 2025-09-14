<?php
/*
************************************************
*==========[TS Special Edition v.5.6]==========*
************************************************
*              Special Thanks To               *
*        DrNet - wWw.SpecialCoders.CoM         *
*          Vinson - wWw.Decode4u.CoM           *
*    MrDecoder - wWw.Fearless-Releases.CoM     *
*           Fynnon - wWw.BvList.CoM            *
*==============================================*
*   Note: Don't Modify Or Delete This Credit   *
*     Next Target: TS Special Edition v5.7     *
*     TS SE WILL BE ALWAYS FREE SOFTWARE !     *
************************************************
*/
/* 
TS Special Edition English Language File
Translation by xam Version: 1.1

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// details.php
$language['details'] = array 
(
	'infohash' =>'Info Hash',
	'smililartorrents' =>'Similar Torrents',
	'jdate'			=>'Joined: ',
	'totalcomments'		=>'Comments: ',
	'totalt'		=>'Threads: ',
	'insertcomment'				=>'Insert Comment',
	'report'					=>'Report',
	'bookmark'					=>'Bookmark',
	'removebookmark'			=>'Remove Bookmark',
	'viewsnatches'				=>'View Snatches',
	'editorrent'				=>'Edit This Torrent',
	'unknown'					=>'Unknown',
	'userip'					=>'USER/IP',
	'conn'						=>'CONN.',
	'up'						=>'UP',
	'urate'						=>'U.RATE',
	'down'						=>'DOWN',
	'drate'						=>'D.RATE',
	'ratio'						=>'RATIO',
	'done'						=>'DONE',
	'since'						=>'SINCE',
	'idle'						=>'IDLE',
	'client'					=>'CLIENT',
	'yes'						=>'Yes',
	'no'						=>'No',
	'inf'						=>'Inf.',
	'detailsfor'				=>'Details for torrent " {1} "',
	'uploaded'					=>'Successfully uploaded!',
	'uploadednote'				=>'<p>You can start seeding now. <b>Note</b> that the torrent won\'t be visible until you do that!</p>',
	'edited'					=>'Successfully edited!',
	'goback'					=>'<p><b>Go back to <a href="{1}">whence you came</a>.</b></p>',
	'singleresult'				=>'<div class=success>Your search for " {1} " gave a single result:</div>',
	'bookmarked'				=>'<div class=success>Bookmark added!</div>',
	'bookmarked2'				=>'<div class=error>No need to bookmark this torrent twice now do we?</div>',
	'bookmarked3'				=>'<div class=error>Bookmark deleted!</div>',	
	'download'					=>'Download',
	'hitrunwarning'				=>'<p><b><u>Download Privileges removed please restart a old torrent to improve your ratio!!</font></u></b></p><p>Your ratio is <b>{1}</b> - meaning that you have only uploaded <b>{2}</b> of the amount you downloaded.<p>It\'s important to maintain a good ratio because it helps to make downloads faster for all members.</p><p><b>Tip: </b></font>You can improve your ratio by leaving your torrent running after the download completes.<p>You must maintain a minimum ratio of <b>{3}</b></font> or your download privileges will be removed.</td></tr>',
	
	'hitrunwarning2'			=>
	'<font>
	<p><b><u>PAY ATTENTION TO YOUR RATIO!!</font></u></b></p>
	<p>Your ratio is <b><font>{1}</b></font> - meaning that you have only uploaded <b>
	<font>{2}</b></font> of the amount you downloaded.<p>It\'s important to maintain a good ratio because it helps to make downloads faster for all members.</p>
	<p><font><b>Tip: </b></font>You can improve your ratio by leaving your torrent running after the download completes.
	<p>You must maintain a minimum ratio of <b><font>{3}</b></font> or your download privileges will be removed.<p><a class="index" href="download.php?id={4}"><font>> Click here to continue with your download <</a></font></p></td></tr>',
	
	
	
	
	'downloadwarning' => '<p font-weight: bold; text-decoration: underline;">PAY ATTENTION TO YOUR RATIO!</p>
<p>Your ratio is <span font-weight: bold;"><b>{1}</b></span> - meaning that you have only uploaded <span font-weight: bold;"><b>{2}</b></span> of the amount you downloaded.<br />
It\'s important to maintain a good ratio because it helps to make downloads faster for all members.</p>
<p>You can improve your ratio by leaving your torrent running after the download completes.<br />You must maintain a minimum ratio of <spanfont-weight: bold;"><b>{3}</b></span> or your download privileges will be removed.</p>',
	
	
	
	
	
	
	
	'nodlpermission'			=>'You are not allowed to download.',
	'infohash'					=>'Info Hash',
	'description'				=>'Description',
	'viewnfo'					=>'View NFO',
	'visible'					=>'Visible',
	'visible2'					=>'NO (dead)',
	'banned'					=>'Banned',
	'sticky'					=>'Sticky',
	'type'						=>'Type',
	'type2'						=>'(none selected)',
	'lastactivity'				=>'Last activity',
	'activity'					=>'Activity',
	'size'						=>'Size',
	'bytes'						=>'bytes',
	'noneyet'					=>'none yet (needs at least {1} votes and has got ',
	'none'						=>'none',
	'only'						=>'only',
	'novotes'					=>'No votes yet',
	'invalid'					=>'invalid?',		
	'added'						=>'Added',
	'views'						=>'Views',
	'hits'						=>'Hits',
	'snatched'					=>'Snatched',
	'snatched2'					=>'time(s)',
	'snatched3'					=>'<--- Click Here to  all View Snatches',
	'progress'					=>'Progress',
	'uppedby'					=>'Uploader',
	'numfiles'					=>'Num files<br /><a href="details.php?id={1}&filelist=1{2}#filelist" class="sublink">[see list]</a>',
	'numfiles2'					=>'in {1} file(s)',
	'numfiles3'					=>'File Details',
	'path'						=>'Path',
	'filelist'					=>'File list</a><br /><a href="details.php?id={1}{2}" class="sublink">[Hide list]</a>',
	'askreseed'					=>'Reseed',
	'askreseed2'				=>'Click <a href=takereseed.php?reseedid={1}><b>here</b></a> to Ask for a reseed!',
	'peers'						=>'Peers<br /><a href="details.php?id={1}&dllist=1{2}#seeders" class="sublink">[see list]</a>',
	'peers2'					=>'{1} seeder(s), {2} leecher(s) = {3} peer(s) total',
	
	
	'peers22222222222222222'					=>'
	<div class="spoilerBox"><div style="cursor:pointer;" class="spoilerTitle" onclick="jQuery(this).next().slideToggle(); jQuery(this).children(\'.spoilerOpen\').toggle();jQuery(this).children(\'.spoilerClose\').toggle();">
 <span class="spoilerOpen">Show</span><span class="spoilerClose" style="display:none;">Hide</span>&nbsp;{1} seeder(s), {2} leecher(s) = {3} peer(s) total</div><div style="display: none;">{4}</div></div>',
 
 
	'peersb'					=>'Peers',
	'peers3'					=>'{1} seeder(s), {2} leecher(s) = {3} peer(s) total<br /><font color=red>Sorry, permission denied!</font>',
	'seeders'					=>'Seeders</a><br /><a href="details.php?id={1}{2}" class="sublink">[Hide list]</a>',
	'seeders2'					=>'Seeder(s)',
	'leechers'					=>'Leechers</a><br /><a href="details.php?id={1}{2}" class="sublink">[Hide list]</a>',
	'leechers2'					=>'Leecher(s)',
	'nothanksyet'				=>'no thanks added yet!',
	'thanksby'					=>'The following user users said thanks to the torrent uploader:',
	'torrentinfo'					=>'Torrent Info',
	'commentsfor'				=>'Comments for torrent "{1}"',
	'nocommentsyet'			=>'There are no comments yet. Be the First to Comment!',
	'quickcomment'			=>'<b>Quick Comment</b>',
	't_link'						=>'IMDB/Web Link', // Changed v3.6
	't_image'						=>'Torrent Image',
	'lastupdate'					=>'Last updated', // Added v3.7
	'warnexternal'				=>'Warning!!!\n----------------\nYou are about to download an external torrent which means download and upload stats aren\'t recorded for this torrent!\n\nClick \"OK\" to continue downloading!', // Added v3.9
	'close'=>'Close Comment',//Added v4.1
	'open'=>'Open Comment',//Added v4.1
	'dltorrent'=>'Download Torrent',//Added in v5.0
	'comments'=>'Comments',//Added in v5.0
	'na'=>'N/A',//Added in v5.0	
	'scene3'=>'Pre-Time',//Added in v5.2
	'newrating'=>'Thanks for voting.. You rated: ',//Added in v5.3
	'alreadyvotes'=>'You voted already!',//Added in v5.3
	'ratedetails'=>'{1} rating from {2} vote(s).',//Added in v5.3
	'bigfile'	=>	 '<b>Number of files ({1}) in this torrent too high to show file-list!</b>',//Added in v5.3
	"laction" => "(Last peer action: {1})" 
	);
?>
