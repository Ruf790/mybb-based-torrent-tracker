<?php



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
	
	
	
	
	
	
'sent_title'         => 'Email Sent',
'sent_body'          => 'If an account with that username exists, a password reset link has been sent to the associated email address.',
'sent_hint'          => 'The link is valid for 60 minutes. Please check your spam folder if you did not receive the email.',

'invalid_token'      => 'The password reset link is invalid or has already been used.',
'token_expired'      => 'The reset link has expired. Please request a new one.',
'passwords_mismatch' => 'The passwords you entered do not match.',
'password_tooshort'  => 'Your password must be at least 8 characters long.',
'password_changed222222'   => 'Your password has been changed successfully. You can now log in with your new password.',

'password_changed' => 'Your password has been changed successfully. You are now logged in.',

'newpassword_title'  => 'Set New Password',
'newpassword_info'   => 'Setting a new password for account <strong>%s</strong>.',
'newpassword'        => 'New Password',
'confirmpassword'    => 'Confirm Password',
'go_login'           => 'Go to Login',

// Email template
'email_subject' => 'Password Reset Request',
'email_body'    =>
"Hi %s,

Someone (hopefully you) requested a password reset for your account on our site.

To set a new password, please follow the link below:
%s

This link is valid for %d minutes. If you did not request this, simply ignore this email.

Regards,
The Team",
	
	
	
	
	
	
	
);
?>
