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
Translation by xam Version: 0.3

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// login.php, takelogin.php
$language['login'] = array 
(
	'head'					=>'Login',
	'loginfirst'			=>'Unfortunately, the page you tried to view <b>can only be used when you\'re logged in</b>. You will be redirected after a successful login.',
	'error1'				=>'ERROR: Incorrect username or password! Please try again or recover your password by clicking <a href="recover.php">here</a>.<br />You have <b>{1}</b> remaining tries.',	
	'info'					=>'<p><b>Note</b>: You need cookies enabled to log in.<br /> [<b>{1}</b>] failed logins in a row will result in banning your ip!</p>',
	'username'				=>'Username:',
	'password'				=>'Password:',
	'logout15'				=>'Log me out after 15 minutes inactivity',
	'securelogin'			=>'Secure Login',
	'login'					=>'LOGIN',
	'reset'					=>'RESET',
	'footer'				=>'<center><br /><p>Don\'t have an account? Click <a href="signup.php"><b>HERE</b></a> to register your <a href="signup.php"><b>FREE</b></a> account!<br /><br />Forget your password? Recover your password <a href="recover.php"><b>via email</b></a> or <a href="recoverhint.php"><b>via question</b></a>.<br /><br />Haven\'t received the Activation Code? Click <a href="'.$_SERVER['SCRIPT_NAME'].'?do=activation_code"><b>here</b></a>.<br /><br />Have a Question? <a href="contactus.php"><b>Contact Us</b></a>.</p></center>',
	'banned'				=>'This account has been disabled.',
	'pending'				=>'Please activate your account first!',
	'logged'				=>'You have succesfully logged in...',
	'resend'	=>'Resend Activation Code', // Added v3.9
	'resend2'	 =>'Type the email address that corresponds to your {1} account.', // Added v3.9
	'resend3'	 =>'Resend', // Added v3.9
	'resend4'	 =>'The email you specified is invalid, because it was not found in our database.', // Added v3.9
);
?>
