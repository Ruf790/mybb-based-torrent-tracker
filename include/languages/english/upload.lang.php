<?php
/*
+--------------------------------------------------------------------------
|   TS Special Edition v.8.0
|   ========================================
|   by xam
|   (c) 2005 - 2020 Template Shares Services
|   https://templateshares.net
|   ========================================
|   Web: https://templateshares.net
|   Time: $_ts_date_
|   Signature Key: $_ts_signature_key_
|   Email: contact@templateshares.net
|   TS SE IS NOT FREE SOFTWARE!
+---------------------------------------------------------------------------
*/
/*
TS Special Edition English Language File
Translation by xam Version: 1.2
Added Since v7.1
*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// upload.php
$language['upload'] = array
(
	'title' => 'Upload Torrent',
	'title2' => 'If necessary (to avoid Invalid Torrent error), you will informed to download the uploaded torrent for seeding.<br />Your Announce URL is: {1}',
	
	
	'uploaderform' => 'If you want to become Uploader, please click <a href="uploaderform.php">here</a> to fill Uploader Form.',
	'torrentfile' => 'Select Torrent File',
	'nfofile' => 'Select NFO File (optional)',
	'UseNFOasDescr' => 'Please check this box, if you want to use the NFO file as description of this torrent!',
	'category' => 'Select Torrent Category',
	'isexternal' => 'Please check this box, if this is an external torrent!',
	'uploading' => 'The torrent is being uploaded.. Please wait..',
	'cover' => 'Torrent Cover Image',
	'cover1'=> 'Option 1 - Enter the URL to the Image on Another Website:',
	'cover2'=> 'Option 2 - Upload Image From Your Computer:',
	
	'cover5' => 'Torrent Cover Image2',
	'cover3'=> 'Option 1 - Enter the URL to the Image2 on Another Website:',
	'cover4'=> 'Option 2 - Upload Image2 From Your Computer:',
	
	
	't_link' => 'IMDB Link',
	't_link2' => '<i>Example: https://www.imdb.com/title/tt0913354/</i><br />',
	'isScene1' => 'Scene Release',
	'isScene2' => 'If this is a scene release, please enter Release Date of this content (leave -1 to remove Release Date). <small>Example: 2010-04-13 22:17:10</small>',//Updated in v7.2
	'offensive1' => 'Offensive Content',
	'offensive2' => 'Check this box if this torrent depicts nudity, may otherwise be potentially offensive or unsuitable for minors. ',
	'multiplier' => 'Multiplier Options',
	'free1' => 'Free Torrent',
	'free2' => 'Mark this torrent as FREE! Only Upload stats will be recorded!',
	'silver1' => 'Silver Torrent',
	'silver2' => 'Mark this torrent as SILVER! Only 50% Download stats will be recorded!',
	'doubleupload1' => 'x2 Torrent',
	'doubleupload2' => 'Mark this torrent as x2! Give Double Upload stats for this torrent.',
	'allowcomments1' => 'Disable Comments',
	'allowcomments2' => 'Check this box to disable comments on this Torrent!',
	'anonymous1' => 'Anonymous Upload',
	'anonymous2' => 'Check this box to hide your name and mark this torrent as Anonymouse Upload.',
	'error' => 'An error has occured while uploading the torrent. You must fix the following error(s) to continue this upload.',
	'error1' => '<b>The subject</b> you have entered is too short. Please lengthen your subject to at least <b>5</b> characters.',
	'error2' => '<b>The description</b> you have entered is too short. Please lengthen your description to at least <b>10</b> characters.',
	'error3' => 'Invalid <b>Torrent File</b>! You have to make sure that you have selected a correct Torrent File.',
	'error4' => 'Invalid <b>Torrent File Type</b>! Are you sure that you have created this torrent correctly?',
	'error5' => '<b>The size</b> of this torrent is too short!',
	'error6' => 'PHP Upload Error!',
	'error7' => 'PHP File Get Contents Error!',
	'error8' => 'PHP Can not create the torrent {File Put Contents}!',
	'error9' => 'Invalid Torrent Category!',
	'error10' => 'Invalid <b>NFO File</b>! You have to make sure that you have selected a correct NFO File.',
	'error11' => '<b>The size</b> of this NFO is too short!',
	'error12' => 'Invalid <b>Pieces</b>! Please re-create the torrent!',
	'error13' => 'MySQL Error: Upload Failed (Possible Reason: Duplicate Torrent)',
	'DefaultTorrentComment' => 'Please seed this torrent as long as possible!',
	'CreatedBy' => 'This torrent has been created by {1}',
	//'newtorrent' => 'There is a new torrent ({1}) has been uploaded by {2}.',
	//'editedtorrent' => 'Torrent ({1}) has been edited by {2}.',
	'newtorrent' => 'There is a new torrent {1} has been uploaded by {2}.',
	'editedtorrent' => 'Torrent {1} has been edited by {2}.',
	'mulmsg'=>'Thank you for uploading! Your upload will not be visible until a staff member has approved it for uploading.',
	
	'modmsgs' => 'A New Torrent has been uploaded and awaiting moderation!',
	
	'modmsgss'=>'Hi,

There is a new torrent ([b]{1}[/b]) has been uploaded by [b]{2}[/b] and awating moderation.

Please click on the below link to check torrent details.
---------------------------------------------------------------------------------------------------
{3}
---------------------------------------------------------------------------------------------------

If you think this torrent NOT against our rules, please approve it as soon as possible.

Have a good day.',

'emailbody'			=>'Hi,

A new torrent has been uploaded.

Name: {1}
Size: {2}
Category: {3}
Uploaded by: {4}

Description
-------------------------------------------------------------------------------
{5}
-------------------------------------------------------------------------------

You can use the URL below to download the torrent (you may have to login).

{6}/details.php?id={7}

Yours,
The {8} Team.',

	'emailsubject'		=>'{1} New torrent - {2}',

	'shoutbOT'          =>'Torrent [url={1}]{2}[/url] has just been uploaded by {3}.',//updated in v7.2

	'done' => 'Thank you, your torrent has been uploaded.<br /><br /><b>Note:</b> INFO_HASH has been changed therefore you must re-download the torrent for seeding (to avoid <b>Invalid Torrent Error</b>), it will not be visible until you do so.<br /><br />Please re-download the torrent and start seeding it!<br /><br />
	<i>Your download will begin in a moment. If it doesn\'t, click <a href="{1}">here</a> to try again.</i><br /><br />
	<i>Click <a href="{2}">here</a> to view Torrent Details.</i>',

	'moptions' => 'Moderator Options',

	'isrequest1' => 'Requested Torrent',
	'isrequest2' => 'Please check this box, if you are uploading a requested torrent.',
	
	'sticky1'=> 'Sticky Torrent',
	'sticky2' => 'Check this box to set this torrent as Sticky.',

	'nuked1' => 'Nuked Torrent',
	'nuked2' => 'Please check this box, if you want to Nuke this torrent.',
	
	'tinfo' => 'Extra Details',
	'video' => 'Video Info',
	'audio' => 'Audio Info',
	'codec' => 'Codec',
	'bitrate' => 'BitRate  (kbps)',
	'resulation' => 'Resulation',
	'length' => 'Length (minutes)',
	'quality' =>'Quality (1-10)',
	'frequency' => 'Frequency',
	'language' => 'Language',
	'tinfohelp' => 'if you do not know this information, use GSpot codec information appliance.',
	'sbu' => 'Search Before Upload',//Added in v7.2
	'sbu_wait' => 'Please wait while we check smilar torrents in our database..',//Added in v7.2
	'pretime' => 'Check this box to use Auto Pretime feature.',//Added in v7.2
	'tname'=>'Torrent Name (Taken from torrent filename if not specified)',//Added in v7.2
	'description' => 'Torrent Description',//Added in v7.2
	'nreason'=>'Reason:',//Added in v7.2
	'savechanges'=>'Save Changes',//Added in v7.2
);
?>