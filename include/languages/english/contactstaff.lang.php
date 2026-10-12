<?php


if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['contactstaff'] = array
(
	// ── Page ──────────────────────────────────────────────────────────────
	'title'                 => 'Contact Staff',
	'subtitle'              => 'A private message to the tracker administration',

	// ── Notice / tips ─────────────────────────────────────────────────────
	'hint_important'        => 'Please contact staff only about genuinely important matters. For everything else, use the FAQ and the forums.',
	'sec_before'            => 'Before you write',
	'tip_faq_title'         => 'Check the FAQ',
	'tip_faq_text'          => 'Most common questions are already answered there.',
	'tip_forum_title'       => 'Ask on the forums',
	'tip_forum_text'        => 'Technical help and general questions belong in the forums.',
	'tip_ignore_title'      => 'What gets ignored',
	'tip_ignore_text'       => 'Software support questions and anything already covered in the FAQ will be ignored.',
	'tip_private_title'     => 'Staff only',
	'tip_private_text'      => 'Only staff members can read your message.',
	'btn_open_faq'          => 'Open FAQ',
	'btn_open_forums'       => 'Go to forums',

	// ── Form ──────────────────────────────────────────────────────────────
	'sec_form'              => 'New message',
	'lbl_subject'           => 'Subject',
	'ph_subject'            => 'Briefly describe the topic',
	'hint_subject'          => 'Up to {1} characters',
	'lbl_message'           => 'Message',
	'ph_message'            => 'Describe your question in detail…',
	'btn_send'              => 'Send message',
	'btn_back'              => 'Back',
	'aria_close'            => 'Close',

	// ── Messages ──────────────────────────────────────────────────────────
	'flash_invalid_request' => 'Invalid request. Please reload the page and try again.',
	'flash_fields_blank'    => 'Please fill in both the subject and the message.',
	'flash_sent'            => 'Your message has been sent to staff. Thank you!',
	'txt_flood_what'        => 'staff messages',
	'txt_invalid_link'      => 'Broken link: {1}',

	// ── JS ────────────────────────────────────────────────────────────────
	'js_char_count'         => '{1} / {2}',
	'js_sending'            => 'Sending…',
	'js_submit_failed'      => 'Submission failed. Please try again.',
);
?>
