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
Translation by xam Version: 0.4

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// recover.php, recoverhint.php
$language['recover'] = array 
(
	'error'						=>'Please logout first.',
	'error2'					=>'Invalid email address!',
	'error3'					=>'The email address was not found in the database.',
	'body'						=>'Hi,

Someone, hopefully you, requested that the password for the account associated with this email address ({1}) be reset.

The request originated from {2}.

If you did not do this ignore this email. Please do not reply.

Should you wish to confirm this request, please follow this link:

{3}/recover.php?id={4}&secret={5}

(AOL Email users may need to copy and paste the link into your web browser).

After you do this, your password will be reset and emailed back to you.

------------------------------------------------
Not working?
------------------------------------------------

If you could not validate your password by clicking on the link, please
visit this page:

{3}/recover.php?act=manual

It will ask you for a user id number, and your secret key. These are shown
below:

User ID: {4}

Secret Key: {5}

Please copy and paste, or type those numbers into the corresponding fields in the form.

If you still cannot validate your account, it\'s possible that the account has been removed.
If this is the case, please contact an administrator to rectify the problem.

Thank you for registering and enjoy your stay!

Regards,

The {6} team.
{3}/index.php', // Updated v4.1
	'subject'					=>'{1} password reset confirmation!',
	'invalidcodeorid'			=>'The code/id you specified is invalid or not found in our database.',
	'invalidcode2'				=>'The code you specified is invalid, because it was not found in our database.',
	'invalidcode3'				=>'The code you specified is invalid, because it didn\'t match.',
	'body2'						=>'Hi,

As per your request we have generated a new password for your account.

Here is the information we now have on file for this account:

    User name: {1}
    Password:  {2}

You may login at {3}/login.php

Yours,
The {4} Team.',
	'subject2'					=>'{1} account details',
	'head'						=>'Recover Lost Username or Password',
	'errortype1'				=>'ERROR: Incorrect email address! Please try again. You have <b>{1}</b> remaining tries.',	
	'errortype3'				=>'ERROR: Incorrect username! Please try again. You have <b>{1}</b> remaining tries.',
	'info'						=>'<p align="center">Use the form below to have your password reset and your account details mailed back to you.</p> <p align="center">(You will have to reply to a confirmation email.)</p><p align="center"><b>Note: {1}</b> failed attempts in a row will result in banning your ip!</p> ',
	'fieldemail'				=>'Registered Email:',
	'info2'						=>'<p align="center"><b>Note:</b> Only users with secret answer and question are searched in the database!</p><p align="center"><b>{1}</b> failed attempts in a row will result in banning your ip!</p>',
	'fieldusername'				=>'Registered Username:',
	'denyaccessforstaff'		=>'Unfortunately, Staff Member have no permission to recover his account via Recover-Hint system.
	<br />Please recover your account via email or contact Staff Leader.',
	'info3'						=>'<p align="center">Please enter the correct answer to your password hint.<br /></p>',
	'sq'						=>'Secret Question:',
	'ha'						=>'Hint Answer:',
	'hr0'						=>'What is your name of first school?',
	'hr1'						=>'What is your pet\'s name?',
	'hr2'						=>'What is your mothers maiden name?',
	'invalidanswer'				=>'Invalid Answer!',
	'generated1'				=>'New Password Generated!',
	'generated2'				=>'Your new password is <input type="text" value="{1}"> (Proceed to <a href={2}/login.php>login</a>)',
	'msent'	=>'Your username and details about how to reset your password have been sent to you by email.',//Added v3.9.0
);
?>
