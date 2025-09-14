<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// contact.php
$language['contact'] = array 
(
	
'email_contact_subject' => "Contact: {1}",

'email_contact' => "E-mail: {1}
Forum profile: {2}
IP Address: {3}
Message:
{4}",
	
'contact' => 'Contact Us',
'contact_no_message' => 'You have not provided a message to send.',
'contact_no_subject' => 'You are required to enter a subject.',
'contact_no_email' => 'You must enter a valid email address.',
'contact_success_message' => 'Your message has been successfully sent to the Administrator.',
'contact_subject' => 'Subject',
'contact_subject_desc' => 'Brief the topic of your message.',
'contact_message' => 'Message',
'contact_message_desc' => 'Describe your concern in this area in detail.',
'contact_email' => 'Email',
'contact_email_desc' => 'Enter your email so we can contact you back.',
'contact_send' => 'Send',
'subject_too_long' => 'The subject is too long. Please enter a subject shorter than {1} characters (currently {2}).',
'message_too_short' => 'The message is too short. Please enter a message longer than {1} characters (currently {2}).',
'message_too_long' => 'The message is too long. Please enter a message shorter than {1} characters (currently {2}).',

'error_stop_forum_spam_spammer' => 'Sorry, {1} matches that of a known spammer and therefore your contact submission has been blocked.',
'error_stop_forum_spam_fetching' => 'Sorry, something went wrong verifying your message against a spammer database. Most likely the database couldn\'t be accessed. Please try again later.',


);

