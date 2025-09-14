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
$adminlang['ts_hit_and_run'] = 'Hi,

We have noticed your HIT & RUN on following torrent:
{torrentinfo}
Your current ratio on this torrent: {showratio}

You have 1 (one) week to fix your ratio on this torrent ('.$config['ts_hit_and_run']['min_share_ratio'].' ratio) otherwise you will get a second warning.

If you haven\'t this torrent on your computer, you can download it again by using below link:
{torrentdownloadinfo}

Please note: If you reach '.$ban_user_limit.' warn limit, your account will be banned.

Have a great day.';

$adminlang['massmail']['header'] = '<font color="red"><b>Message received from '.$SITENAME.' on ' . gmdate("Y-m-d H:i:s") . ' GMT.</b></font>';
$adminlang['massmail']['footer'] = '<b><font color="blue">Yours,<br />The <a href="'.$BASEURL.'">'.$SITENAME.'</a> Team.</font></b>';

$adminlang['ts_auto_optimize']['update_message'] = '<font face="verdana" size="2" color="darkred"><b>System Message:</b> Performance updates in progress... Please check back soon.</font>';

$manage_avatars['message']['subject'] = 'Your avatar has been deleted!';
$manage_avatars['message']['body'] = 'Hi,

For security reasons we deleted your avatar from our database.

Feel free to upload an another avatar.

[b]Note:[/b] Avatar deleted by '.$CURUSER['username'].'

Have a great day,
'.$SITENAME.' Team.';
$mass_reseed['message']['subject'] = 'Re-seed Request';
$mass_reseed['message']['body'] = 'Hi {username},

Please Re-seed the following torrent as soon as possible: {torrentname}

Have a great day,
'.$SITENAME.' Team.';
?>
