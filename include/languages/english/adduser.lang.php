<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// admin/adduser.php ---*--- Note: This script using also signup.lang.php veritables.
$language['adduser'] = array 
(
	'title'				=>		'Create New Account',
	'username'		=>		'Username:',
	'email'				=>		'Email:',
	'password'		=>		'Password:',
	'password2'		=>		'Re-type Password:',
	'usergroup'		=>		'Usergroup:',
	'comment'		=>		'Mod-Comment:',
	'bonus'			=>		'Bonus Points:',
	'invites'			=>		'Invites:',
	'uploaded'		=>		'Uploaded:',
	'downloaded'	=>		'Downloaded:',
	'options'			=>		'Account Options:',
	'o1'				=>		'Request Confirmation E-Mail?',
	'invalidug'		=>		'Invalid Usergroup Selected!',
	
	'invalidemail'					=>'That doesn\'t look like a valid email address.',
	'invalidemail2'					=>'This email address banned! We do not accept Email from free email services such as Hotmail, Yahoo, Gmail etc.. (We ONLY accept registrations from non-free email addresses!)',	 // updated in v3.8
	'invalidemail3'					=>'The e-mail address is already in use.',
	
	'banned_email' => 'The email address you have entered is currently disallowed from being used. Please enter a different email address',
	
	'passe1'						=>'The passwords didn\'t match! Must\'ve typoed. Try again.',
	'passe2'						=>'Sorry, password is too short (min is 6 chars)',
	'passe3'						=>'Sorry, password is too long (max is 40 chars)',
	'passe4'						=>'Sorry, password cannot be same as user name.',
	
	'welcomepmsubject'				=>'Welcome to {1}!',
	'welcomepmbody'					=>'Congratulations {1},

	You are now a member of {2}, we would like to take this opportunity to say hello and welcome to {2}!
	
	Please be sure to read the Rules: ({3}/rules.php) and the Faq: ({3}/faq.php#dl8) and be sure to stop by the Forums: ({3}/index2.php) and say Hello!
	
	Enjoy your Stay.
	The Staff of {2}',
	

);
?>
