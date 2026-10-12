<?php


if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['getrss'] = array
(
	// ── Page ──
	'title'					=> 'RSS Feeds',
	'sec_subtitle'			=> 'Your personal feed of new torrents for an RSS reader or a torrent client with auto-download.',

	// ── Result (after submit) ──
	'pane_result'			=> 'RSS Feed Generated',
	'hint_copy_link'		=> 'Copy the link below into your RSS reader.',
	'lbl_rss_link'			=> 'Your RSS link',
	'hint_passkey'			=> 'The link contains your personal passkey. Do not share it or post it publicly — anyone with it can download as you.',
	'btn_copy'				=> 'Copy',
	'btn_test'				=> 'Test RSS Feed',
	'btn_generate_another'	=> 'Generate Another',

	// ── Categories ──
	'pane_categories'		=> 'Categories',
	'hint_cat_click'		=> 'Click to select / deselect categories',
	'hint_categories'		=> 'Choose which categories go into the feed. Nothing selected means all categories.',

	// ── Quick selection (inside Categories) ──
	'btn_select_all'		=> 'Select all',
	'btn_deselect_all'		=> 'Clear selection',
	'lbl_showall'			=> 'Select All Categories (Include All Future Categories)',
	'hint_showall'			=> 'Categories added to the tracker later will show up in the feed automatically.',

	// ── Feed type ──
	'pane_feedtype'			=> 'Feed Type',
	'opt_feed_details'		=> 'Web Link',
	'opt_feed_download'		=> 'Download Link',
	'hint_feed_details'		=> 'Items open the torrent page on the site.',
	'hint_feed_download'	=> 'Items link straight to the .torrent file — for auto-download in your client.',

	// ── Settings ──
	'pane_settings'			=> 'Settings',
	'lbl_timezone'			=> 'Select Your Timezone:',
	'lbl_showrows'			=> 'Rows Per Page:',
	'hint_showrows'			=> 'How many of the latest torrents the feed returns.',
	'err_timezone'			=> 'Please select a valid timezone.',
	'err_showrows'			=> 'Please select number of rows to show.',
	'btn_submit'			=> 'Generate RSS Link',

	// ── Timezone options ──
	'opt_tz_m12'			=> '(GMT -12:00) Eniwetok, Kwajalein',
	'opt_tz_m11'			=> '(GMT -11:00) Midway Island, Samoa',
	'opt_tz_m10'			=> '(GMT -10:00) Hawaii',
	'opt_tz_m9'				=> '(GMT -9:00) Alaska',
	'opt_tz_m8'				=> '(GMT -8:00) Pacific Time (US & Canada)',
	'opt_tz_m7'				=> '(GMT -7:00) Mountain Time (US & Canada)',
	'opt_tz_m6'				=> '(GMT -6:00) Central Time (US & Canada), Mexico City',
	'opt_tz_m5'				=> '(GMT -5:00) Eastern Time (US & Canada), Bogota, Lima',
	'opt_tz_m4'				=> '(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz',
	'opt_tz_m3_5'			=> '(GMT -3:30) Newfoundland',
	'opt_tz_m3'				=> '(GMT -3:00) Brazil, Buenos Aires, Georgetown',
	'opt_tz_m2'				=> '(GMT -2:00) Mid-Atlantic',
	'opt_tz_m1'				=> '(GMT -1:00) Azores, Cape Verde Islands',
	'opt_tz_0'				=> '(GMT) Western Europe Time, London, Lisbon, Casablanca',
	'opt_tz_1'				=> '(GMT +1:00) Brussels, Copenhagen, Madrid, Paris',
	'opt_tz_2'				=> '(GMT +2:00) Kaliningrad, South Africa',
	'opt_tz_3'				=> '(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg',
	'opt_tz_3_5'			=> '(GMT +3:30) Tehran',
	'opt_tz_4'				=> '(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi',
	'opt_tz_4_5'			=> '(GMT +4:30) Kabul',
	'opt_tz_5'				=> '(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent',
	'opt_tz_5_5'			=> '(GMT +5:30) Mumbai, Kolkata, Chennai, New Delhi',
	'opt_tz_6'				=> '(GMT +6:00) Almaty, Dhaka, Colombo',
	'opt_tz_7'				=> '(GMT +7:00) Bangkok, Hanoi, Jakarta',
	'opt_tz_8'				=> '(GMT +8:00) Beijing, Perth, Singapore, Hong Kong',
	'opt_tz_9'				=> '(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk',
	'opt_tz_9_5'			=> '(GMT +9:30) Adelaide, Darwin',
	'opt_tz_10'				=> '(GMT +10:00) Eastern Australia, Guam, Vladivostok',
	'opt_tz_11'				=> '(GMT +11:00) Magadan, Solomon Islands, New Caledonia',
	'opt_tz_12'				=> '(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka',

	// ── JS strings (passed to AGS_LANG without the js_ prefix) ──
	'js_copied'				=> 'Copied!',
	'js_copy_failed'		=> 'Failed to copy link. Please copy it manually.',
	'js_cat_hint'			=> 'Click to select / deselect categories',
	'js_cats_selected_one'	=> '{1} category selected',
	'js_cats_selected_other'=> '{1} categories selected',
	'js_cats_all'			=> 'All categories selected',
	'js_cats_all_future'	=> 'All categories, including future ones',

	// ── Legacy (old TS SE keys, may still be used by rss.php) ──
	'done2'					=> 'Use the following url in your RSS reader:',
	'field1'				=> 'Categories to retrieve:',
	'field2'				=> 'ALL',
	'field3'				=> 'Feed Type:',
	'field4'				=> 'Web Link',
	'field5'				=> 'Download Link',
	'field6'				=> 'Select Your TimeZone:',
	'field7'				=> 'Rows Per Page:',
	'field8'				=> 'Generate RSS link',
);
?>
