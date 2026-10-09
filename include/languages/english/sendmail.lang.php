<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['sendmail'] = array (
	// ── Page ──
	'page_title'          => 'Send Mail',
	'head_title'          => 'Send email',
	'head_subtitle'       => 'Send a one-off message from {1} to any address.',

	// ── Flash messages ──
	'flash_sent_title'    => 'Email sent.',
	'flash_sent_text'     => 'The message was handed to the mail server.',
	'flash_fail_title'    => 'The email was not sent.',

	// ── Errors ──
	'err_token'           => 'Your security token has expired. Reload the page and send again.',
	'err_email'           => 'Enter a valid recipient address.',
	'err_subject'         => 'Enter a subject.',
	'err_message_short'   => 'The message must be at least {1} characters long.',
	'err_mail_server'     => 'The mail server did not accept the message. Check the mail log and try again.',

	// ── Panes ──
	'pane_compose'        => 'Compose',
	'pane_preview'        => 'Inbox preview',

	// ── Labels ──
	'lbl_recipient'       => 'Recipient',
	'lbl_subject'         => 'Subject',
	'lbl_message'         => 'Message',
	'lbl_from'            => 'From',
	'lbl_to'              => 'To',
	'lbl_counter_suffix'  => 'characters',

	// ── Placeholders ──
	'ph_recipient'        => 'recipient@example.com',
	'ph_subject'          => 'What is this email about?',
	'ph_message'          => 'Write your message. HTML is allowed.',

	// ── Validation feedback ──
	'inv_email'           => 'Enter a valid email address.',
	'inv_subject'         => 'Enter a subject.',
	'inv_message'         => 'Write at least {1} characters.',

	// ── Toolbar ──
	'aria_toolbar'        => 'HTML formatting',
	'tip_bold'            => 'Bold (Ctrl+B)',
	'tip_italic'          => 'Italic (Ctrl+I)',
	'tip_underline'       => 'Underline (Ctrl+U)',
	'tip_heading'         => 'Heading',
	'tip_paragraph'       => 'Paragraph',
	'tip_list'            => 'Bulleted list',
	'tip_link'            => 'Link (Ctrl+K)',
	'tip_image'           => 'Image',
	'tip_hr'              => 'Divider',
	'tip_br'              => 'Line break',

	// ── Hints ──
	'hint_html'           => 'The message is sent as HTML. The preview shows how it will look in the inbox.',
	// Output as-is (HTML allowed)
	'hint_send_html'      => '<kbd>Ctrl</kbd> + <kbd>Enter</kbd> sends',

	// ── Preview / action bar / overlay ──
	'title_preview_frame' => 'Email preview',
	'btn_reset'           => 'Reset',
	'btn_send'            => 'Send email',
	'loading_sending'     => 'Sending email…',

	// ── JS strings ──
	'js_no_recipient'        => 'No recipient yet',
	'js_no_subject'          => 'No subject yet',
	'js_preview_placeholder' => 'Your message will appear here.',
	'js_confirm_send_title'  => 'Send this email?',
	'js_confirm_send_text'   => 'It will be delivered to {1}.',
	'js_btn_send'            => 'Send email',
	'js_btn_keep_editing'    => 'Keep editing',
	'js_confirm_reset_title' => 'Discard your changes?',
	'js_confirm_reset_text'  => 'The form will go back to how it was when the page loaded.',
	'js_btn_discard'         => 'Discard',
);
?>
