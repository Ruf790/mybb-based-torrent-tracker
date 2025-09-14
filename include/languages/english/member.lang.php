<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// ts_watch_list.php
$language['member'] = array 
(


'redirect_loggedin' => "You have successfully been logged in.<br />You will now be taken back to where you came from.",

'invitecode'=>'Invite Code:',

'error_nomember' => "The member you specified is either invalid or doesn't exist.",

'invaliduser' => 'Invalid User specified. If you followed a valid link, please notify the <a href="/contactstaff.php">administrator.</a>',

'pendinguser' => 'This user pending approval from administrator.',

'noperm' =>	'Sorry, this users profile is protected as they have chosen to be anonymous!',

'title'	=> 'View Profile: {1}',

'lastvisit_never' => 'Never',

'hidden' =>	'<i>Hidden</i>',

'kps' => '<b>Bonus Points:</b>',

'iby' => 'Invited By: {1}',


'torrentstats'	=> 'Torrent Statistics',
'torrentstats1'	=>	 'Uploaded Torrents',
'torrentstats2'	=>	 'Completed Torrents',
'torrentstats3'	=>	 'Current Leechs',
'torrentstats4'	=>	 'Current Seeds',
'torrentstats5'	=>	 'Recently Snatched',








'banned_warning' => "Your forum account is currently banned.",
'banned_warning2' => "Ban Reason",
'banned_warning3' => "Ban will be lifted",
'banned_lifted_never' => "Never",



'error_invalidemail' => "You did not enter a valid email address.",

'emailsubject_lostpw' => "Password Reset at {1}",

'emailsubject_passwordreset' => "New password at {1}",



'email_passwordreset' => "{1},

Your password at {2} has been reset.

Your new password is: {3}

You will need this password to login to the forums, once you login you should change it by going to your User Control Panel.

Thank you,
{2} Staff",





'email_lostpw' => "{1},

To complete the phase of resetting your account password at {2}, you will need to go to the URL below in your web browser.

{3}/member.php?action=resetpassword&id={4}&code={5}

If the above link does not work correctly, go to

{3}/member.php?action=resetpassword

You will need to enter the following:
Username: {1}
Activation Code: {5}

Thank you,
{2} Staff",



'email_lostpw1' => "{1},

To complete the phase of resetting your account password at {2}, you will need to go to the URL below in your web browser.

{3}/member.php?action=resetpassword&uid={4}&code={5}

If the above link does not work correctly, go to

{3}/member.php?action=resetpassword

You will need to enter the following:
Your email address
Activation Code: {5}

Thank you,
{2} Staff",


'email_lostpw2' => "{1},

To complete the phase of resetting your account password at {2}, you will need to go to the URL below in your web browser.

{3}/member.php?action=resetpassword&uid={4}&code={5}

If the above link does not work correctly, go to

{3}/member.php?action=resetpassword

You will need to enter the following:
Username: {1} (Or your email address)
Activation Code: {5}

Thank you,
{2} Staff",






'emailsubject_subscription' => "New Reply to {1}",



'email_subscription' => "{1},

{2} has just replied to a thread which you have subscribed to at {3}. This thread is titled {4}.

Here is an excerpt of the message:
------------------------------------------
{5}
------------------------------------------

To view the thread, you can go to the following URL:
{6}/{7}

There may also be other replies to this thread but you will not receive anymore notifications until you visit the board again.

Thank you,
{3} Staff

------------------------------------------
Unsubscription Information:

If you would not like to receive any more notifications of replies to this thread, visit the following URL in your browser:
{6}/usercp.php?action=removesubscription&tid={8}

------------------------------------------",



'email_emailuser' => "{1},

{2} from {3} has sent you the following message:
------------------------------------------
{5}
------------------------------------------

Thank you,
{3} Staff
{4}

------------------------------------------
Don't want to receive email messages from other members?

If you don't want other members to be able to email you please visit your User Control Panel and enable the option 'Hide your email from other members':
{4}/usercp.php?action=options

------------------------------------------",










'emailsubject_randompassword' => "Your Password for {1}",


'emailsubject_activateaccount' => "Account Activation at {1}",

'email_activateaccount' => "{1},

To complete the registration process on {2}, you will need to go to the URL below in your web browser.

{3}/member.php?action=activate&id={4}&code={5}

If the above link does not work correctly, go to

{3}/member.php?action=activate

You will need to enter the following:
Username: {1}
Activation Code: {5}

Thank you,
{2} Staff",


'email_activateaccount1' => "{1},

To complete the registration process on {2}, you will need to go to the URL below in your web browser.

{3}/member.php?action=activate&id={4}&code={5}

If the above link does not work correctly, go to

{3}/member.php?action=activate

You will need to enter the following:
Your email address
Activation Code: {5}

Thank you,
{2} Staff",



'email_activateaccount2' => "{1},

To complete the registration process on {2}, you will need to go to the URL below in your web browser.

{3}/member.php?action=activate&id={4}&code={5}

If the above link does not work correctly, go to

{3}/member.php?action=activate

You will need to enter the following:
Username: {1} (Or your email address)
Activation Code: {5}

Thank you,
{2} Staff",





'email_randompassword' => "{1},

Thank you for registering on {2}. Below is your username and the randomly generated password. To login to {2}, you will need these details.

Username: {3}
Password: {4}

It is recommended you change your password immediately after you login. You can do this by going to your User CP then clicking Change Password on the left menu.

Thank you,
{2} Staff",

'email_randompassword1' => "{1},

Thank you for registering on {2}. Below is your username and the randomly generated password. To login to {2}, you will need these details.

Your email address
Password: {4}

It is recommended you change your password immediately after you login. You can do this by going to your User CP then clicking Change Password on the left menu.

Thank you,
{2} Staff",

'email_randompassword2' => "{1},

Thank you for registering on {2}. Below is your username and the randomly generated password. To login to {2}, you will need these details.

Username: {3} (Or your email address)
Password: {4}

It is recommended you change your password immediately after you login. You can do this by going to your User CP then clicking Change Password on the left menu.

Thank you,
{2} Staff",



'email_changeemail_noactivation' => "{1},

We have received a request on {2} to change your email address (see details below).

Old Email Address: {3}
New Email Address: {4}

This change has been automatically processed. If you did not request this change, please get in touch with an Administrator.

Thank you,
{2} Staff
{5}",
















'invitecode'=>'Invite Code:',

'validinvitecode'=>'The invite code you specified is invalid!',

'footer' => '<center><br /><p>Don\'t have an account? Click <a href="member.php?action=register"><b>HERE</b></a> to register your <a href="member.php?action=register"><b>FREE</b></a> account!<br /><br />Forget your password? Recover your password <a href="member.php?action=lostpw"><b>via email</b></a> or <a href="recoverhint.php"><b>via question</b></a>.<br /><br />Haven\'t received the Activation Code? Click <a href="'.$_SERVER['SCRIPT_NAME'].'?do=activation_code"><b>here</b></a>.<br /><br />Have a Question? <a href="contactus.php"><b>Contact Us</b></a>.</p></center>',
	

'hr0' => 'What is your name of first school?',
'hr1' => 'What is your pet\'s name?',
'hr2' => 'What is your mothers maiden name?',

'sq' => 'Secret Question:',
'sqa' => 'Answer:',


'month_1' => "January",
'month_2' => "February",
'month_3' => "March",
'month_4' => "April",
'month_5' => "May",
'month_6' => "June",
'month_7' => "July",
'month_8' => "August",
'month_9' => "September",
'month_10' => "October",
'month_11' => "November",
'month_12' => "December",



'welcomepmsubject'				=>'Welcome to {1}!',
'welcomepmbody'					=>'Congratulations {1},

	You are now a member of {2}, we would like to take this opportunity to say hello and welcome to {2}!
	
	Please be sure to read the Rules: ({3}/rules.php) and the Faq: ({3}/faq.php#dl8) and be sure to stop by the Forums: ({3}/index2.php) and say Hello!
	
	Enjoy your Stay.
	The Staff of {2}',


'username1' => "Email:",
'username2' => "Username/Email:",


'username' => "Username:",
'password' => "Password:",

'remember_me' => "Remember me",


'failed_login_wait' => "You have failed to login within the required number of attempts. You must now wait {1}h {2}m {3}s before you can login again.",

'failed_login_again' => "<br />You have <strong>{1}</strong> more login attempts.",



'invaliduser'		=>	 'Invalid User specified. If you followed a valid link, please notify the <a href="/contactstaff.php">administrator.</a>',

'torrentstats'	=> 'Torrent Statistics',
'torrentstats1'	=>	 'Uploaded Torrents',
'torrentstats2'	=>	 'Completed Torrents',
'torrentstats3'	=>	 'Current Leechs',
'torrentstats4'	=>	 'Current Seeds',
'torrentstats5'	=>	 'Recently Snatched',


'nav_register' => "Register",
'nav_activate' => "Activate",
'nav_resendactivation' => "Resend Activation Email",
'nav_lostpw' => "Lost Password Recovery",
'nav_resetpassword' => "Reset Password",
'nav_login' => "Login",
'nav_emailuser' => "Email User",
'nav_referrals' => "Users Referred by {1}",
'nav_profile' => "Profile of {1}",

'referrals' => 'Referrals',
'referral_date' => 'Referral Date:',
'referrals_no_user_specified' => 'No user specified.',
'referrals_invalid_user' => 'Invalid user specified.',
'member_no_referrals' => 'No referrals for this user.',

'tpp_option' => "Show {1} threads per page",
'ppp_option' => "Show {1} posts per page",
'account_activation' => "Account Activation",
'activate_account' => "Activate Account",
'activation_code' => "Activation Code:",

'email_user' => "Send {1} an Email",
'email_subject' => "Email Subject",
'email_message' => "Email Message",
'send_email' => "Send Email",
'error_hideemail' => "The recipient has chosen to hide their email address and as a result you cannot email them.",
'error_no_email_subject' => "You need to enter a subject for your email",
'error_no_email_message' => "You need to enter a message for your email",


'your_email' => "Your Email:",
'your_name' => "Your Name:",


'login' => "Login",
'pw_note' => "Please note that passwords are case sensitive.",
'lostpw_note' => "Lost your password?",
'lost_pw' => "Lost Account Password",
'lost_pw_form' => "Lost Password Recovery Form",
'email_address' => "Email Address:",
'request_user_pass' => "Request Login Credentials",
'profile' => "Profile of {1}",
'registration_date' => "Registration Date:",
'date_of_birth' => "Date of Birth:",
'birthdayhidden' => "Hidden",
'birthday' => "Birthday:",
'local_time' => "Local Time:",
'local_time_format' => "{1} at {2}",
'users_forum_info' => "{1}'s Tracker and Forum Info",
'joined' => "Joined:",
'lastvisit' => "Last Visit:",
'total_posts' => "Total Posts:",
'ppd_percent_total' => "{1} posts per day | {2} percent of total posts",
'total_threads' => "Total Threads:",
'tpd_percent_total' => "{1} threads per day | {2} percent of total threads",
'find_posts' => "Find All Posts",
'find_threads' => "Find All Threads",
'members_referred' => "Members Referred:",
'rating' => "Rating:",
'users_contact_details' => "{1}'s Contact Details",
'homepage' => "Homepage:",
'pm' => "Private Message:",
'send_pm' => "Send {1} a private message.",
'icq_number' => "ICQ Number:",
'skype_id' => "Skype ID:",
'google_id' => "Google Hangouts ID:",
'avatar'  => "Avatar:",
'warning_level' => "Warning Level:",
'warn' => "Warn",
'away_note' => "{1} is currently away.",
'away_reason' => "Reason:",
'away_since' => "Away Since:",
'away_returns' => "Returns on:",
'away_no_reason' => "Not specified.",
'ban_note' => "This forum account is currently banned.",
'ban_by' => "Banned By",
'ban_length' => "Ban Length",
'ban_remaining' => "remaining",

'users_additional_info' => "Additional Info About {1}",
'email' => "Email:",
'send_user_email' => "Send {1} an email.",
'users_signature' => "{1}'s Signature",
'agreement' => "Registration Agreement",
'agreement_1' => "Whilst we attempt to edit or remove any messages containing inappropriate, sexually orientated, abusive, hateful, slanderous, or threatening material that could be considered invasive of a person's privacy, or which otherwise violate any kind of law, it is impossible for us to review every message posted on this discussion system. For this reason you acknowledge that all messages posted on this discussion system express the views and opinions of the original message author and not necessarily the views of this bulletin board. Therefore we take no responsibility and cannot be held liable for any messages posted. We do not vouch for or warrant the accuracy and completeness of every message.",
'agreement_2' => "By registering on this discussion system you agree that you will not post any material which is knowingly false, inaccurate, abusive, hateful, harassing, sexually orientated, threatening or invasive of a person's privacy, or any other material which may violate any applicable laws.",
'agreement_3' => "Failure to comply with these rules may result in the termination of your account, account suspension, or permanent ban of access to these forums. Your IP Address is recorded with each post you make on this discussion system and is retrievable by the forum staff if need-be. You agree that we have the ability and right to remove, edit, or lock any account or message at any time should it be seen fit. You also agree that any information you enter on this discussion system is stored in a database, and that \"cookies\" are stored on your computer to save your login information.",
'agreement_4' => "Any information you provide on these forums will not be disclosed to any third party without your complete consent, although the staff cannot be held liable for any hacking attempt in which your data is compromised.",
'agreement_5' => "By continuing with the registration process you agree to the above rules and any others that the Administrator specifies.",
'registration' => "Registration",
'required_fields' => "Required Fields",
'complex_password' => "<abbr title= 'A password that is at least {1} characters long and contains an upper case letter, a lower case letter and a number.'>Complex</abbr> Password:",
'confirm_email' => "Confirm Email:",
'optional_fields' => "Optional Fields",
'website_url' => "Your Website URL:",
'birthdate' => "Birthdate:",
'additional_info' => "Additional Information",
'required_info' => "Required Information",
'i_agree' => "I Agree",
'account_details' => "Account Details",
'account_prefs' => "Account Preferences:",
'invisible_mode' => "Hide me from the Who's Online list.",
'allow_notices' => "Receive emails from the Administrators.",
'hide_email' => "Hide your email from other members.",
'email_notify' => "Automatically subscribe to threads you post in.",
'receive_pms' => "Receive private messages from other users.",
'pm_notice' => "Alert me with a notice when I receive a Private Message.",
'email_notify_newpm' => "Notify me by email when I receive a new Private Message.",
'time_offset' => 'Time Zone (<abbr title="Daylight Saving Time">DST</abbr> correction excluded):',
'time_offset_desc' => "If you live in a time zone which differs to what this board is set at, you can select it from the list below.",
'dst_correction' => "Daylight Saving Time correction:",
'dst_correction_auto' => "Automatically detect DST settings",
'dst_correction_enabled' => "Always use DST correction",
'dst_correction_disabled' => "Never use DST correction",
'redirect_registered_coppa_activate' => "Thank you for registering on {1}, {2}. Your account has successfully been created, however, as the owner of this account is under the age of 13, parental permission needs to be sought before this account can be used.<br /><br />A parent or legal guardian will need to download, fill in and submit to us a completed copy of our <a href=>\"member.php?action=>coppa_form\">COPPA Compliance &amp, Permission form</a>.<br /><br />Once we receive a completed copy of this form, the account will be activated.",
'coppa_compliance' => "COPPA Compliance",


'coppa_desc' => "In order to register on these forums, we require you to verify your age to comply with 
<a href=\"https://www.ftc.gov/enforcement/rules/rulemaking-regulatory-reform-proceedings/childrens-online-privacy-protection-rule\" title=\"Children's Online Privacy Protection Act\" target=\"_blank\" rel=\"noopener\">COPPA</a>. Please enter your date of birth below.<br /><br />If you are under the age of 13, parental permission must be obtained prior to registration. A parent or legal guardian will need to download, fill in and submit to us a completed copy of our <a href=\"member.php?action=>coppa_form\" target=\"_blank\" rel=\"noopener\">COPPA Compliance &amp, Permission form</a>.",


'coppa_desc_for_deny' => "In order to register on these forums, we require you to verify your age to comply with 
<a href=\"https://www.ftc.gov/enforcement/rules/rulemaking-regulatory-reform-proceedings/childrens-online-privacy-protection-rule\" 
title=\"Children's Online Privacy Protection Act\" target=\"_blank\" rel=\"noopener\">COPPA</a>. Please enter your date of birth below.",




'hide_dob' => "You can choose to hide your date of birth and age by editing your profile after registering.",
'signature' => "Signature:",
'continue_registration' => "Continue with Registration",
'birthdayprivacy' => "Date of Birth Privacy:",
'birthdayprivacyall' => "Display Age and Date of Birth",
'birthdayprivacynone' => "Hide Age and Date of Birth",
'birthdayprivacyage' => "Display Only Age",
'leave_this_field_empty' => "Leave this field empty:",
'error_need_to_be_thirteen' => "You need to be of thirteen years or older to register on this forum.",
'coppa_registration' => "COPPA Registration Form",
'coppa_form_instructions' => "Please print this form, fill it in and either fax it to the number below or mail it to the provided mailing address.",
'fax_number' => "Fax Number:",
'mailing_address' => "Mailing Address:",
'account_information' => "Account Information",
'parent_details' => "Parent / Guardian Details",
'full_name' => "Full Name:",
'relation' => "Relation:",
'phone_no' => "Phone #:",
'coppa_parent_agreement' => "I understand that the information I have provided is truthful, that any information may be changed in the future by entering the supplied password and this user account can be removed by request.",

'coppa_agreement_1' => "Users under the age of 13 must receive permission from their parent or legal guardian in order to register on {1}.",
'coppa_agreement_2' => "A parent or legal guardian will need to download, fill in and submit to us a completed copy of our <a href=>\"member.php?action=>coppa_form\" target=>\"_blank\" rel=>\"noopener\">COPPA Compliance &amp, Permission form</a> before membership will be granted.",
'coppa_agreement_3' => "If you'd like to, you can begin the registration process now, however the account will be inaccessible until the above compliance form is received.",

'error_invalid_birthday' => 'The birthday you entered is invalid. Please enter a valid birthday.',
'error_awaitingcoppa' => "You cannot login using this account as it is still awaiting COPPA validation from a parent or guardian.<br /><br />A parent or legal guardian will need to download, fill in and submit to us a completed copy of our <a href=>\"member.php?action=>coppa_form\">COPPA Compliance &amp, Permission form</a>.<br /><br />Once we receive a completed copy of this form, the account will be activated.",

'lang_select' => "Language Settings:",
'lang_select_desc' => "If you live in a country that speaks a language other than the forums default, you may be able to select an installed, read-able language pack below.",
'lang_select_default' => "Use Default",

'submit_registration' => "Submit Registration!",
'confirm_password' => "Confirm Password:",
'referrer' => "Referrer:",
'referrer_desc' => "If you were referred by another member you can enter their username below. If not, simply leave this field blank.",
'resend_activation' => "Resend Account Activation",
'request_activation' => "Request Activation Code",
'ppp' => "Posts Per Page:",
'ppp_desc' => "Allows you to select the amount of posts to be shown per page in a thread.",
'tpp' => "Threads Per Page:",
'tpp_desc' => "Allows you to select the amount of threads to be shown per page in the thread listing.",
'reset_password' => "Reset Password",
'send_password' => "Send New Password!",
'registration_errors' => "The following errors occurred with your registration:",
'timeonline' => "Time Spent Online:",
'timeonline_hidden' => "(Hidden)",
'registrations_disabled' => "Sorry, but you cannot register at this time because the administrator has disabled new account registrations.",
'error_username_length' => "Your username is invalid. Usernames have to be within {1} to {2} characters.",
'error_stop_forum_spam_spammer' => 'Sorry, your {1} matches that of a known spammer. If you feel this is a mistake, please contact an administrator.',
'error_stop_forum_spam_fetching' => 'Sorry, something went wrong verifying your account against a spammer database. Most likely the database couldn\'t be accessed. Please try again later.',

'none_registered' => "None Registered",
'not_specified' => "Not Specified",
'membdayage' => "({1} years old)",
'mod_options' => "Moderator Options",
'edit_in_mcp' => "Edit this user in Mod CP",
'ban_in_mcp' => "Ban this user in Mod CP",
'edit_ban_in_mcp' => "Edit ban in Mod CP",
'lift_ban_in_mcp' => "Lift ban in Mod CP",
'purgespammer' => "Purge Spammer",
'edit_usernotes' => "Edit user notes in Mod CP",
'no_usernotes' => "There are currently no notes on this user",
'view_all_notes' => "View all notes",
'view_notes_for' => "View notes for {1}",
'registration_ip' => "Registration IP:",
'last_known_ip' => "Last Known IP:",
'reputation' => "Reputation:",
'reputation_vote' => "Rate",
'reputation_details' => "Details",
'already_logged_in' => "Notice: You are already currently logged in as {1}.",
'admin_edit_in_acp' => "Edit this user in Admin CP",
'admin_ban_in_acp' => "Ban this user in Admin CP",
'admin_lift_ban_in_acp' => "Lift ban in Admin CP",
'admin_edit_ban_in_acp' => "Edit ban in Admin CP",
'admin_options' => "Administrator Options",


'emailsubject_changeemail' => "Change of Email at {1}",

'redirect_registered_activation' => "Thank you for registering on {1}, {2}.<p>To complete your registration, please check your email for the account activation instructions. You may not be able to post until your account has been activated.",
'redirect_emailupdated' => "Your email has been successfully changed.<br />You will now be taken back to the forums index.",
'redirect_accountactivated' => "Your account has successfully been activated.<br />You will now be taken back to the forums index.",
'redirect_accountactivated_admin' => "Your email has successfully validated.<br />Your registration must be approved by an administrator. Until then, you may not be able to post on these forums.<br />You will now be taken back to the forum index.",
'redirect_registered' => "Thank you for registering on {1}, {2}.<br />You will now be taken back to the forums index.",
'redirect_registered_admin_activate' => "Thank you for registering on {1}, {2}.<br />Your registration must be approved by an administrator. Until then, you may not be able to post on these forums.",
'redirect_loggedout' => "You have successfully been logged out.<br />You will now be taken back to the forum index.",
'redirect_alreadyloggedout' => "You were already logged out or have not logged in yet.<br />You will now be taken back to the forum index.",
'redirect_lostpwsent' => "Thank you, all accounts pertaining to that email address have now been sent an email with details on how to reset their passwords.<br /><br />You will now be taken to the forum index.",
'redirect_activationresent' => "Your activation email has been resent to the email address provided.",
'redirect_passwordreset' => "Thank you, the password for your account has been reset. The new randomly generated password has been emailed to the email address associated with your account.",
'redirect_memberrated' => "The member has been rated successfully.",
'redirect_registered_passwordsent' => "A random password has been generated and sent to your email address. Before you can login, you will need to check your email for this password.",
'redirect_validated' => "Thank you, your account has been validated.<br />You will now be taken to the forum index.",

'error_activated_by_admin' => "You are unable to resend your account activation email as all registrations must be approved by an Administrator.",
'error_alreadyregistered' => "Sorry, but our system shows that you have already registered and the registration of multiple accounts has been disabled.",
'error_alreadyregisteredtime' => "We cannot process your registration because there has already been {1} new registration(s) from your ip address in the past {2} hours. Please try again later.",
'error_badlostpwcode' => "You have entered an invalid password reset code. Please re-read the email you were sent or contact the forum administrators for more help.",
'error_badactivationcode' => "You have entered an invalid account activation code. To resend all activation emails to the email address on file, please click <a href=>\"member.php?action=>resendactivation\">here</a>.",
'error_alreadyactivated' => "Your account has already been activated or does not require email validation.",
'error_alreadyvalidated' => "Your email has already been validated.",
'error_nothreadurl' => "Your message does not contain the URL of the thread. Please use the \"send to friend\" feature for it's intended purpose.",
'error_bannedusername' => "You have entered a username that is banned from registration.  Please choose another username.",
'error_notloggedout' => "Your user ID could not be verified to proceed with the log out process. This could be due to malicious Javascript that was attempting to log you out automatically.  If you intended to log out, please click the Log Out button at the top menu.",
'error_regimageinvalid' => "The image verification code that you entered was incorrect. Please enter the code exactly how it appears in the image.",
'error_regimagerequired' => "Please fill out the image verification code to continue the login process. Please enter the code exactly how it appears in the image.",
'error_spam_deny' => "Our systems detect that you may be a spammer and therefore you have been denied registration. If this is a mistake, please contact the Administrator.",
'error_spam_deny_time' => "Our systems detect that you may be a spammer and therefore you have been denied registration. Registration must take a minimum time of {1} seconds to prevent automated signups, you registered in {2} seconds. If this is a mistake, please contact the Administrator.",

'js_validator_no_username' => "You need to enter a username.",
'js_validator_invalid_email' => "You need to enter a valid email address.",
'js_validator_email_match' => "You need to enter the same email address again.",
'js_validator_no_image_text' => "You need to enter the text in the image above.",
'js_validator_no_security_question' => "You need to enter the answer to the security question.",
'js_validator_password_matches' => "The passwords you enter must match.",
'js_validator_password_length' => "Your password must be {1} or more characters long.",
'js_validator_bad_password_security' => "The password you entered is similar to either your username or email address. Please enter a stronger password.",
'js_validator_not_empty' => "You must select or enter a value for this field.",
'js_validator_username_length' => "Usernames must be between {1} and {2} characters long.",

'security_question' => "Security Question",
'question_note' => "Please answer the question provided. This process is used to prevent automated processes.",
'error_question_wrong' => "The answer you provided for the security question is wrong. Please try again.",

'subscription_method' => "Default Thread Subscription Mode:",
'no_auto_subscribe' => "Do not subscribe",
'no_subscribe' => "No notification",
'instant_email_subscribe' => "Instant email notification",
'instant_pm_subscribe' => "Instant PM notification",

'remove_from_buddy_list' => "Remove from Buddy List",
'add_to_buddy_list' => "Add to Buddy List",
'remove_from_ignore_list' => "Remove from Ignore List",
'add_to_ignore_list' => "Add to Ignore List",
'report_user' => "Report User",

'newregistration_subject' => "New registration at {1}",
'newregistration_message' => "{1},

There is a new user at {2} who is pending admin activation.

Username: {3}

Thank you,
{2} Staff",


);
