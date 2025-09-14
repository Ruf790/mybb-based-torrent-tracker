<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

$ffcache = $cache->read('SIGNUP');
	  
$_d_usergroup = $ffcache['_d_usergroup'];
$r_verification = $ffcache['r_verification'];
//$r_referrer = $ffcache['r_referrer'];
$maxusers = $ffcache['maxusers'];
$invitesystem = $ffcache['invitesystem'];
$regtype = $ffcache['regtype'];
$requirecomplexpasswords = $ffcache['requirecomplexpasswords'];
$minpasswordlength = $ffcache['minpasswordlength'];
$maxpasswordlength = $ffcache['maxpasswordlength'];
$minnamelength = $ffcache['minnamelength'];
$maxnamelength = $ffcache['maxnamelength'];
$username_method = $ffcache['username_method'];
$disableregs = $ffcache['disableregs'];
$failedlogincount = $ffcache['failedlogincount']; 
$failedlogintext = $ffcache['failedlogintext'];
$coppa = $ffcache['coppa'];
$invite_count = $ffcache['invite_count'];
$autogigsignup = $ffcache['autogigsignup'];
$autosbsignup = $ffcache['autosbsignup'];  
$maxip = $ffcache['maxip'];
$illegalusernames = $ffcache['illegalusernames'];








$kpscache = $cache->read("KPS");
	 
$bonus = $kpscache['bonus'];
$kpsseed = $kpscache['kpsseed'];
$kpsupload = $kpscache['kpsupload'];
$kpscomment = $kpscache['kpscomment'];
$kpsthanks = $kpscache['kpsthanks'];
$kpsrate = $kpscache['kpsrate'];
$kpspoll = $kpscache['kpspoll'];
$kpsmaxpoint = $kpscache['kpsmaxpoint'];
$kpsinvite = $kpscache['kpsinvite'];
$kpstitle = $kpscache['kpstitle'];
$kpsvip = $kpscache['kpsvip'];
$kpsgift = $kpscache['kpsgift'];
$bdayreward = $kpscache['bdayreward'];
$bdayrewardtype = $kpscache['bdayrewardtype'];
$kpswarning = $kpscache['kpswarning'];
$kpsratiofix = $kpscache['kpsratiofix'];




$ffcache2 = $cache->read('forumcp');
  
$f_forum_online = $ffcache2['f_forum_online'];
$f_offlinemsg = $ffcache2['f_offlinemsg'];
$f_forumname = $ffcache2['f_forumname'];





  $clean2 = $cache->read('CLEANUP');
  
  $ai = $clean2['ai'];
  $max_dead_torrent_time = $clean2['max_dead_torrent_time'];
  $referrergift = $clean2['referrergift'];
  $autoinvitetime = $clean2['autoinvitetime'];
  $ban_user_limit = $clean2['ban_user_limit'];
  $promote_gig_limit = $clean2['promote_gig_limit'];
  $promote_min_ratio = $clean2['promote_min_ratio'];
  $promote_min_reg_days = $clean2['promote_min_reg_days'];
  $demote_min_ratio = $clean2['demote_min_ratio'];
  $leechwarn_remove_ratio = $clean2['leechwarn_remove_ratio'];
  $leechwarn_min_ratio = $clean2['leechwarn_min_ratio'];
  $leechwarn_gig_limit = $clean2['leechwarn_gig_limit'];
  $leechwarn_length = $clean2['leechwarn_length'];





$ann = $cache->read('ANNOUNCE');

$announce_actions = $ann['announce_actions'];
$aggressivecheat = $ann['aggressivecheat'];
$nc = $ann['nc'];
$announce_wait = $ann['announce_wait'];
$announce_interval = $ann['announce_interval'];
$max_rate = $ann['max_rate'];
$bannedclientdetect = $ann['bannedclientdetect'];
$allowed_clients = $ann['allowed_clients'];
$detectbrowsercheats = $ann['detectbrowsercheats'];
$checkconnectable = $ann['checkconnectable'];
$checkip = $ann['checkip'];

$mysql_host = $ann['mysql_host'];
$mysql_user = $ann['mysql_user'];
$mysql_pass = $ann['mysql_pass'];
$mysql_db = $ann['mysql_db'];


?>
