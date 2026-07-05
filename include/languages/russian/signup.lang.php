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
Translation by xam Version: 1.1
*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

//  signup.php - Re-Coded since v6.2
$language['signup'] = array
(
	
	
	'banned_email' => 'The email address you have entered is currently disallowed from being used. Please enter a different email address',
	
	
	
	'welcomepmsubject'				=>'Welcome to {1}!',
	'welcomepmbody'					=>'Congratulations {1},

	You are now a member of {2}, we would like to take this opportunity to say hello and welcome to {2}!
	
	Please be sure to read the Rules: ({3}/rules.php) and the Faq: ({3}/faq.php#dl8) and be sure to stop by the Forums: ({3}/index2.php) and say Hello!
	
	Enjoy your Stay.
	The Staff of {2}',
	'title'	=>	'Sign Up For a Free {1} Account',
	'validusername'=>'<strong>Please enter a valid User Name!</strong><br />Allowed Characters: (a-z), (A-Z), (0-9)',
	'validusername2'=>'<strong>Please enter a valid User Name!</strong><br />Your User Name must be between 3 and 12 characters!',
	'username'=>'User Name:',
	'email' => 'Email Address:',
	'validemail'=>'Please enter a valid Email Address!',
	'password'=>'Create a Password:',
	'password2'=>'Retype Password:',
	'validpassword'=>'Please fill out both password fields.',
	'validpassword2'=>'The entered passwords do not match.',
	'validpassword3'=>'Your password must be between 6 and 40 characters.',
	'validpassword4'=>'Password can not be same as User Name!',
	'hr0'		=>'What is your name of first school?',
	'hr1'		=>'What is your pet\'s name?',
	'hr2'		=>'What is your mothers maiden name?',
	'sq'=>'Secret Question:',
	'sqa'=>'Answer:',
	'validhintanswer'=>'Please enter your Answer for Secret Question!',
	'validhintanswer2'=>'Your Answer must be between 6 and 32 characters!',
	'validhintanswer3'=>'Answer can not be same as User Name!',
	'validhintanswer4'=>'Please enter your Secret Question and Answer!',
	'country'=>'Country/Region:',
	'validcountry'=>'Please select your Country!',
	'validtimezone'=>'Please select your Time Zone settings!',
	'referrer'=>'Referrer (optional):',
	'timezone'=>'Timezone Settings:',
	'bday'=>'Date of Birth:',
	'validbday'=>'Please enter your Date of Birth!',
	'validgender'=>'Please select your Gender!',
	'gender'=>'Gender:',
	'male'=>'Male',
	'female'=>'Female',
	'weak'=>'<font color="red">Weak</font>',
	'medium'=>'<font color="blue">Medium</font>',
	'strong'=>'<font color="green">Strong</font>',
	'signup'=>'Sign Up',
	'usernamexists'=>'This User Name already exists!',
	'emailexists'=>'This Email Address already exists!',
	'emailbanned'=>'This Email Address is Banned!!',
	'invitecode'=>'Invite Code:',
	'validinvitecode'=>'The invite code you specified is invalid!',
	'checkbutton'=>'Check availability',
	'usernameokay'=>'You can Sign UP with this User Name!',
	'emailokay'=>'You can Sign UP with this Email!',
	'reset'=>'Reset Fields',
	'failed'=>'Registration Failed!',
	'verifiyemailsubject'			=>'{1} user registration confirmation',
	'verifiyemailbody'				=>'
Hello {1},
This email has been sent from {2}/index.php.

You have received this email because this email address
was used during registration for our tracker.
If you did not register at our tracker, please disregard this
email. You do not need to unsubscribe or take any further action.

------------------------------------------------
Activation Instructions
------------------------------------------------

Thank you for registering.
We require that you "validate" your registration to ensure that
the email address you entered was correct. This protects against
unwanted spam and malicious abuse.

To activate your account, simply click on the following link:

{2}/confirm.php?id={3}&secret={4}

(AOL Email users may need to copy and paste the link into your web browser).

------------------------------------------------
Not working?
------------------------------------------------

If you could not validate your registration by clicking on the link, please
visit this page:

{2}/confirm.php?act=manual

It will ask you for a user id number, and your secret key. These are shown
below:

User ID: {3}

Secret Key: {4}

Please copy and paste, or type those numbers into the corresponding fields in the form.

If you still cannot validate your account, it\'s possible that the account has been removed.
If this is the case, please contact an administrator to rectify the problem.

Thank you for registering and enjoy your stay!

Regards,

The {5} team.
{2}/index.php
',
	'agree1'=> 'User Agreement',
	'agree2'=>'In order to proceed, you must read & agree with the following User Agreement:',
	'agree3'=>'I have read and agree to the User Agreement',
	
	
	'une1'							=>'Sorry, Username is too short (min is 3 chars)',
	'une2'							=>'Sorry, username is too long (max is 12 chars)',
	'une3'							=>'Invalid username.',
	'une4'							=>'Username already exists!',
	'invalidemail'					=>'That doesn\'t look like a valid email address.',
	'invalidemail2'					=>'This email address banned! We do not accept Email from free email services such as Hotmail, Yahoo, Gmail etc.. (We ONLY accept registrations from non-free email addresses!)',	 // updated in v3.8
	'invalidemail3'					=>'The e-mail address is already in use.',
	'passe1'						=>'The passwords didn\'t match! Must\'ve typoed. Try again.',
	'passe2'						=>'Sorry, password is too short (min is 6 chars)',
	'passe3'						=>'Sorry, password is too long (max is 40 chars)',
	'passe4'						=>'Sorry, password cannot be same as user name.',
);
?>