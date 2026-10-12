<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['batch_upload'] = array (

	// ── Page / header ──
	'page_title'              => 'Batch Torrent Upload',
	'hdr_subtitle'            => 'Upload up to {1} torrents at once with posters, screenshots, tags and IMDb info',
	'hdr_parser_ready'        => 'Torrent parser ready',

	// ── KPI tiles ──
	'kpi_per_batch'           => 'Torrents per batch',
	'kpi_max_image'           => 'Max image size',
	'kpi_mb'                  => '{1} MB',
	'kpi_screens'             => 'Screenshots per torrent',
	'kpi_categories'          => 'Categories',

	// ── Announce URL ──
	'lbl_announce'            => 'Your announce URL',
	'btn_copy'                => 'Copy',

	// ── Info cards (sec_ = headers, step_ = how-it-works list) ──
	'sec_how'                 => 'How it works',
	'step_1'                  => 'Drop or select .torrent files',
	'step_2'                  => 'Add a poster and screenshots to each',
	'step_3'                  => 'Set category, description and tags',
	'step_4'                  => 'Optionally fetch info from IMDb',
	'step_5_html'             => 'Press <b>Upload all</b>',
	'sec_global'              => 'Global settings',
	'lbl_anonymous'           => 'Anonymous upload',
	'hint_anonymous'          => 'Applies to every torrent in this batch',
	'sec_csv'                 => 'CSV metadata',
	'hint_csv_sub'            => 'Optional, overrides the form fields',
	'hint_csv_columns_html'   => 'Columns: <code>torrent_filename, name, category, description, tags</code> (tags optional)',
	'btn_csv_template'        => 'Download template',

	// ── Torrents card / drop zone ──
	'sec_torrents'            => 'Torrents',
	'hint_torrents_sub'       => 'Drop several files at once or add them one by one',
	'drop_aria'               => 'Select torrent files',
	'drop_title'              => 'Drag & drop .torrent files here',
	'drop_or'                 => 'or',
	'drop_browse'             => 'click to browse',
	'drop_limit'              => ', up to {1} files',
	'btn_add_more'            => 'Add another torrent',

	// ── Sticky action bar ──
	'lbl_in_batch'            => 'In batch:',
	'btn_back'                => 'Back',
	'btn_upload_all'          => 'Upload all',

	// ── Progress modal ──
	'sec_progress'            => 'Processing upload',
	'lbl_overall'             => 'Overall progress',
	'sec_results'             => 'Results',
	'btn_close'               => 'Close',
	'btn_view_torrents'       => 'View torrents',

	// ── BBCode toolbar (title / aria-label) ──
	'bb_aria'                 => 'BBCode',
	'bb_bold'                 => 'Bold',
	'bb_italic'               => 'Italic',
	'bb_underline'            => 'Underline',
	'bb_strike'               => 'Strikethrough',
	'bb_link'                 => 'Link',
	'bb_image'                => 'Image',
	'bb_youtube'              => 'YouTube',
	'bb_left'                 => 'Align left',
	'bb_center'               => 'Center',
	'bb_right'                => 'Align right',
	'bb_quote'                => 'Quote',
	'bb_code'                 => 'Code',
	'bb_spoiler'              => 'Spoiler',
	'bb_preview'              => 'Preview',
	'bb_hide'                 => 'Hide toolbar',

	// ── Preview modal ──
	'sec_preview'             => 'Description preview',
	'lbl_loading'             => 'Loading...',

	// ── Torrent block — shared by PHP and JS (passed to AGS_LANG as-is) ──
	'item_remove'             => 'Remove this torrent',
	'item_torrent_file'       => 'Torrent file',
	'item_optional'           => 'optional',
	'item_poster'             => 'Poster',
	'item_poster2'            => 'Poster 2',
	'item_screenshots'        => 'Screenshots',
	'item_screens_opt'        => 'optional, up to {1}',
	'item_name'               => 'Torrent name',
	'item_name_opt'           => 'filename if empty',
	'item_name_ph'            => 'Leave empty to use the filename',
	'item_category'           => 'Category',
	'item_descr'              => 'Description',
	'item_descr_opt'          => 'BBCode supported',
	'item_descr_ph'           => 'Description...',
	'item_tags'               => 'Tags',
	'item_tags_opt'           => 'overridden by the CSV tags column',
	'item_tags_ph'            => 'Action, Comedy, Drama...',
	'item_clear'              => 'Clear',
	'item_imdb_url'           => 'IMDb URL',
	'item_fetch'              => 'Fetch info',
	'item_add_descr'          => 'Add to description',

	// ── Genre button labels — shared by PHP and JS (data-genre stays English) ──
	'genre_action'            => 'Action',
	'genre_adventure'         => 'Adventure',
	'genre_animation'         => 'Animation',
	'genre_biography'         => 'Biography',
	'genre_comedy'            => 'Comedy',
	'genre_crime'             => 'Crime',
	'genre_documentary'       => 'Documentary',
	'genre_drama'             => 'Drama',
	'genre_family'            => 'Family',
	'genre_fantasy'           => 'Fantasy',
	'genre_history'           => 'History',
	'genre_horror'            => 'Horror',
	'genre_music'             => 'Music',
	'genre_mystery'           => 'Mystery',
	'genre_romance'           => 'Romance',
	'genre_sci_fi'            => 'Sci-Fi',
	'genre_sport'             => 'Sport',
	'genre_thriller'          => 'Thriller',
	'genre_war'               => 'War',
	'genre_western'           => 'Western',

	// ── Server errors (JSON responses) ──
	'err_csrf'                => 'Invalid CSRF token',
	'err_no_file'             => 'No file',
	'err_imdb_no_url'         => 'No URL provided',
	'err_imdb_invalid'        => 'Invalid IMDb URL',
	'err_internal'            => 'Internal server error',
	'err_mkdir'               => 'Cannot create directory: {1}',
	'err_not_writable'        => 'Directory not writable: {1}',
	'err_no_files'            => 'Please select at least one torrent file',
	'err_too_many'            => 'Maximum {1} files allowed, got {2}',
	'err_upload_code'         => 'upload error code {1}',
	'err_screenshot'          => 'screenshot {1}',
	'err_exists'              => 'Torrent already exists on the tracker',
	'err_db_insert'           => 'Database error while inserting torrent: {1}',
	'err_db_no_id'            => 'Failed to insert torrent into database',
	'err_copy_torrent'        => 'Failed to copy torrent file',
	'err_upload'              => 'Upload error',
	'err_file_type'           => 'Invalid file type: {1}',
	'err_save_file'           => 'Failed to save file',

	// ── Screenshot errors ({1} = file name) ──
	'lbl_shot_default'        => 'screenshot',
	'err_shot_limit'          => '{1}: skipped, max {2} screenshots per torrent reached',
	'err_shot_type'           => '{1}: unsupported file type',
	'err_shot_size'           => '{1}: file too large (max {2}MB)',
	'err_shot_mime'           => '{1}: content does not match an allowed image type',
	'err_shot_save'           => '{1}: failed to save file',
	'err_shot_recode'         => '{1}: corrupted or unsupported image (recode failed)',
	'err_shot_db'             => '{1}: database insert failed ({2})',

	// ── JS: announce copy ──
	'js_copy'                 => 'Copy',
	'js_copied'               => 'Copied!',

	// ── JS: validation / alerts ──
	'js_err_image_type'       => '{1}: invalid image type',
	'js_err_image_size'       => '{1}: too large (max {2} MB)',
	'js_max_torrents'         => 'Maximum {1} torrents',
	'js_no_files'             => 'Please select at least one torrent file',

	// ── JS: duplicate check ──
	'js_dup_title'            => 'Duplicate detected.',
	'js_dup_text'             => 'A torrent with the same info_hash already exists:',
	'js_dup_added'            => ', uploaded {1}.',

	// ── JS: IMDb ──
	'js_imdb_enter_url'       => 'Please enter an IMDb URL first.',
	'js_imdb_invalid'         => 'Invalid IMDb URL. Example: https://www.imdb.com/title/tt0000000/',
	'js_imdb_fetching'        => 'Fetching...',
	'js_imdb_error'           => 'IMDb Error: {1}',
	'js_unknown'              => 'Unknown',
	'js_imdb_failed'          => 'Failed to fetch IMDb data. Please try again.',
	'js_descr_year'           => 'Year: {1}',
	'js_descr_genre'          => 'Genre: {1}',
	'js_descr_rating'         => 'IMDb Rating: {1}',

	// ── JS: remove / clear confirmation ──
	'js_confirm_clear_title'  => 'Clear this torrent?',
	'js_confirm_remove_title' => 'Remove this torrent?',
	'js_confirm_clear_text'   => 'all fields of this block will be cleared.',
	'js_confirm_remove_text'  => 'the block and everything filled in it will be removed from the batch.',
	'js_confirm_clear'        => 'Clear',
	'js_confirm_remove'       => 'Remove',
	'js_cancel'               => 'Cancel',

	// ── JS: upload button / modal state ──
	'js_btn_upload_one'       => 'Upload {1} torrent',
	'js_btn_upload_many'      => 'Upload {1} torrents',
	'js_state_working'        => 'Processing upload',
	'js_state_done'           => 'Upload finished',
	'js_state_error'          => 'Upload failed',

	// ── JS: per-file status badges ──
	'js_st_waiting'           => 'Waiting',
	'js_st_uploading'         => 'Uploading',
	'js_st_processing'        => 'Processing',
	'js_st_done'              => 'Done',
	'js_st_error'             => 'Error',

	// ── JS: transport errors ──
	'js_err_non_json'         => 'Non-JSON response from server',
	'js_err_server'           => 'Server error',
	'js_err_response'         => 'Response error: {1}',
	'js_err_network'          => 'Network error',
	'js_err_timeout'          => 'Request timeout',
	'js_err_label'            => 'Error:',

	// ── JS: results ──
	'js_sum_uploaded'         => 'Uploaded {1} of {2} torrents',
	'js_sum_posters'          => '{1} with posters',
	'js_sum_posters2'         => '{1} with poster 2',
	'js_sum_screens'          => '{1} screenshots',
	'js_sum_csv'              => '{1} CSV records',
	'js_res_files'            => '{1} files',
	'js_res_view'             => 'View',
	'js_errors_title'         => 'Errors ({1})',

);
?>
