<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['upload'] = [

    // ── Заголовки страницы ──────────────────────────────────────────────────
    'head'              => 'Upload Torrent',
    'head_edit'         => 'Edit Torrent',
    'title'             => 'Upload Torrent',
    'title2'            => 'If necessary (to avoid Invalid Torrent error), you will be informed to download the uploaded torrent for seeding.<br />Your Announce URL is: {1}',
    'subtitle'          => 'Share your content with the community',

    // ── Права / форма загрузчика ────────────────────────────────────────────
    'uploaderform'      => 'If you want to become an Uploader, please click <a href="uploaderform.php">here</a> to fill the Uploader Form.',
    'no_permission'     => 'You do not have permission to upload torrents.',

    // ── Торрент файл ────────────────────────────────────────────────────────
    'torrent_file'          => 'Torrent File',
    'torrent_file_required' => 'Torrent file is required for new uploads.',
    'torrent_file_invalid'  => 'Invalid or unsupported torrent file format.',
    'torrent_file_hint'     => 'Accepted format: .torrent',
    'torrent_file_hint_edit'=> 'Accepted format: .torrent (optional for edit)',
    'torrent_drop_title'    => 'Drag & Drop your .torrent file here',
    'torrent_drop_sub'      => 'or click to browse files',
    'torrent_browse'        => 'Browse File',
    'torrent_ready'         => 'Ready to upload',
    'torrent_validating'    => 'Validating torrent file...',
    'torrent_rename_error'  => 'Failed to rename the torrent file.',
    'torrent_not_found'     => 'Existing torrent file not found.',
    'torrent_info'          => 'Torrent Info',
    'torrent_size'          => 'Size',
    'torrent_files'         => 'Files',
    'torrent_hash'          => 'Hash',
    'torrent_file_list'     => 'File List',
    'torrent_show'          => 'Show',
    'torrent_hide'          => 'Hide',
	'error_wrong_passkey'   => 'This torrent contains a passkey that does not belong to your account',
	'error_no_passkey'      => 'Your account does not have a passkey. Please contact staff.',
	'no_permission_edit'    => "You do not have permission to edit this torrent.",
	'no_upload_permission'  => "You do not have permission to upload.",

    // ── Дубликат ────────────────────────────────────────────────────────────
    'duplicate_detected'    => 'Duplicate torrent detected!',
    'duplicate_exists'      => 'A torrent with the same info_hash already exists:',
    'duplicate_upload_anyway' => 'Upload Anyway',
    'duplicate_view'        => 'View Existing',
    'duplicate_ignored'     => 'Duplicate warning ignored. You can now upload.',
    'no_duplicates'         => 'Ready to upload — No duplicates found',

    // ── Название торрента ───────────────────────────────────────────────────
    'torrent_name'          => 'Torrent Name',
    'torrent_name_hint'     => 'Choose a descriptive name for your torrent',
    'torrent_name_invalid'  => 'Please enter a valid torrent name (3-255 characters)',
    'torrent_name_required' => 'Torrent name is required.',

    // ── NFO ─────────────────────────────────────────────────────────────────
    'nfofile'               => 'NFO File',
    'nfofile_optional'      => '(optional)',
    'nfofile_hint'          => 'Optional: Upload your .nfo file (stored in database)',
    'nfofile_preview'       => 'Preview',
    'nfofile_hide'          => 'Hide',
    'nfofile_uploaded'      => 'NFO already uploaded',
    'nfofile_replace'       => 'Upload a new file to replace it',

    // ── Описание ────────────────────────────────────────────────────────────
    'description'           => 'Description',
    'description_placeholder' => 'Enter Torrent Description...',
    'description_required'  => 'Description is required.',
    'description_invalid'   => 'Please enter a description.',

    // ── Категория ───────────────────────────────────────────────────────────
    'category'              => 'Select Category',
    'category_required'     => 'Category is required.',
    'category_invalid'      => 'Please select a category.',

    // ── Теги / жанры ────────────────────────────────────────────────────────
    'tags'                  => 'Tags',
    'tags_optional'         => '(optional)',
    'tags_placeholder'      => 'Action, Drama, Sci-Fi...',
    'tags_clear'            => 'Clear All',
    'tags_hint'             => 'Click on any genre button to add or remove it from tags.',
    'tags_added'            => 'added to tags',
    'tags_removed'          => 'removed from tags',
    'tags_cleared'          => 'All tags cleared',

    // ── IMDb ────────────────────────────────────────────────────────────────
    'imdb_link'             => 'IMDb Link',
    'imdb_fetch'            => 'Fetch',
    'imdb_hint'             => 'Paste IMDb URL and click Fetch to auto-fill poster and info',
    'imdb_placeholder'      => 'Example: https://www.imdb.com/title/tt1234567/',
    'imdb_fetching'         => 'Fetching IMDb data...',
    'imdb_add_description'  => 'Add to Description',
    'imdb_poster_main'      => 'Main',
    'imdb_poster_secondary' => '2nd',
    'imdb_set_main'         => 'Set as Main Image',
    'imdb_set_secondary'    => 'Set as Secondary Image',
    'imdb_poster_set_main'  => 'Poster set as Main Image!',
    'imdb_poster_set_2nd'   => 'Poster set as Secondary Image!',
    'imdb_no_poster'        => 'No poster available.',
    'imdb_added_description' => 'IMDb info added to description!',
    'imdb_error_empty'      => 'Please enter an IMDb URL',
    'imdb_error_invalid'    => 'Invalid IMDb URL — should contain /title/ttXXXXXXX/',
    'imdb_error_failed'     => 'Failed to fetch IMDb data',
    'imdb_error_proxies'    => 'All proxies failed',
    'imdb_error_parse'      => 'Could not parse movie data from IMDb page',

    // ── Секция Media & Images ────────────────────────────────────────────────
    'media_section'         => 'Media & Images',
    'image_main'            => 'Main Image',
    'image_secondary'       => 'Secondary Image',
    'image_url_label'       => 'Image URL',
    'image_url_placeholder' => 'https://example.com/image.jpg',
    'image_url_hint'        => 'Paste direct image URL (jpg, png, gif, webp)',
    'image_file_hint'       => 'JPG, PNG, GIF, WebP (max 10MB)',
    'image_click_upload'    => 'Click to upload image',
    'image_drag_drop'       => 'or drag & drop',
    'image_url_mode'        => 'URL',
    'image_file_mode'       => 'File',

    // ── Скриншоты ───────────────────────────────────────────────────────────
    'screenshots'               => 'Screenshots',
    'screenshots_upload_files'  => 'Upload Files',
    'screenshots_bulk_url'      => 'Bulk URL',
    'screenshots_drop'          => 'Drop screenshots here',
    'screenshots_multiple'      => 'Multiple files allowed',
    'screenshots_url_label'     => 'Screenshot URLs',
    'screenshots_url_hint'      => '(one URL per line)',
    'screenshots_url_supported' => 'Supported: jpg, png, gif, webp',
    'screenshots_load_preview'  => 'Load Previews',
    'screenshots_existing'      => 'Existing Screenshots',
    'screenshots_no_preview'    => 'No preview available',
    'screenshots_delete_title'  => 'Delete Screenshot?',
    'screenshots_delete_warning'=> 'This action cannot be undone!',
    'screenshots_delete_confirm'=> 'Yes, Delete',
    'screenshots_deleted'       => 'Screenshot deleted successfully!',
    'screenshots_order_saved'   => 'Screenshot order saved!',
    'screenshots_no_more'       => 'No screenshots yet',
    'screenshots_loaded'        => 'screenshot(s) loaded',
    'screenshots_failed'        => 'failed to load',

    // ── Секция настроек торрента ────────────────────────────────────────────
    'settings_section'      => 'Torrent Settings',

    'anonymous'             => 'Anonymous Upload',
    'anonymous_hint'        => 'Check this box if you want to upload this torrent anonymously',

    'request'               => 'Requested Torrent',
    'request_hint'          => 'Please check this box if you are uploading a requested torrent',

    'free'                  => 'Free Torrent',
    'free_hint'             => 'Mark this torrent as FREE! Only Upload stats will be recorded!',

    'silver'                => 'Silver Torrent',
    'silver_hint'           => 'Mark this torrent as SILVER! Only 50% Download stats will be recorded!',

    'doubleupload'          => 'x2 Torrent',
    'doubleupload_hint'     => 'Mark this torrent as x2! Give Double Upload stats for this torrent',

    'allowcomments'         => 'Disable Comments',
    'allowcomments_hint'    => 'Check this box to disable comments on this Torrent!',

    'sticky'                => 'Sticky Torrent',
    'sticky_hint'           => 'Check this box to set this torrent as Sticky',

    'isnuked'               => 'Nuked Torrent',
    'isnuked_hint'          => 'Please check this box if you want to Nuke this torrent',

    'nuke_reason'           => 'Nuke Reason',
    'nuke_reason_hint'      => 'Please provide a reason for nuking this torrent',
    'nuke_reason_placeholder' => 'Enter reason for nuking this torrent',

    'external'              => 'External Torrent',
    'external_hint'         => 'Torrent is linked from another tracker',
	
	'thirtypercent'         => "30% Leech",
    'thirtypercent_hint'    => "Only 30% of downloaded data is counted toward this user's download stats.",
	

    // ── Announce URL ────────────────────────────────────────────────────────
    'announce_important'    => 'Important!',
    'announce_copy'         => 'Copy',
    'announce_copied'       => 'Announce URL copied to clipboard!',
    'announce_copy_failed'  => 'Failed to copy: ',

    // ── Кнопки submit ───────────────────────────────────────────────────────
    'btn_upload'            => 'Upload Torrent',
    'btn_update'            => 'Update Torrent',

    // ── Upload модал / прогресс ─────────────────────────────────────────────
    'upload_processing'     => 'Processing Upload',
    'upload_wait'           => 'Uploading your content...',
    'upload_wait_sub'       => 'Please wait while we process your torrent and files',
    'upload_timer'          => 'seconds',
    'upload_size_note'      => 'This may take a few moments depending on file sizes',
    'upload_init'           => 'Initializing upload process...',
    'upload_torrent_file'   => 'Uploading torrent file...',
    'upload_metadata'       => 'Processing metadata...',
    'upload_screenshots'    => 'Uploading screenshots...',
    'upload_finalizing'     => 'Finalizing...',
    'upload_almost'         => 'Almost done!',

    // ── Успех ───────────────────────────────────────────────────────────────
    'upload_success_title'  => 'Upload Successful!',
    'upload_success_congrats' => 'Congratulations!',
    'upload_success_text'   => 'Your torrent has been successfully uploaded and is now live.',
    'upload_success_redirect' => 'You will be redirected shortly...',
    'upload_stay'           => 'Stay Here',
    'upload_view'           => 'View Torrent',
    'upload_completed'      => 'Upload Completed',
    'upload_private_text'   => 'Your torrent has been successfully uploaded and updated with a private flag.',
    'upload_redirecting'    => 'Redirecting in',
    'upload_download_btn'   => 'Download Torrent',
    'upload_view_details'   => 'View Details',

    // ── Ошибки ──────────────────────────────────────────────────────────────
    'error_title'           => 'Upload Error',
    'error_close'           => 'Close',
    'error_occurred'        => 'An error occurred: ',
    'error_upload_failed'   => 'Upload failed: ',
    'error_missing_id'      => 'Missing ID.',
    'error_image_save'      => 'Image from URL could not be saved.',
    'error_image_download'  => 'Could not download image from URL.',
    'error_image_invalid'   => 'Invalid image URL.',

    // ── Логи ────────────────────────────────────────────────────────────────
    'newtorrent'            => 'New torrent %1$s was uploaded by %2$s',
    'editedtorrent'         => 'Torrent %1$s was edited by %2$s',

    // ── Приватный трекер ────────────────────────────────────────────────────
    'DefaultTorrentComment' => 'Torrent downloaded from our tracker.',
    'CreatedBy'             => 'Uploaded by %s',
	
	
	
// ── Genres ──
'genre_action'      => 'Action',
'genre_adventure'   => 'Adventure',
'genre_animation'   => 'Animation',
'genre_biography'   => 'Biography',
'genre_comedy'      => 'Comedy',
'genre_crime'       => 'Crime',
'genre_documentary' => 'Documentary',
'genre_drama'       => 'Drama',
'genre_family'      => 'Family',
'genre_fantasy'     => 'Fantasy',
'genre_history'     => 'History',
'genre_horror'      => 'Horror',
'genre_music'       => 'Music',
'genre_mystery'     => 'Mystery',
'genre_romance'     => 'Romance',
'genre_sci_fi'      => 'Sci-Fi',
'genre_sport'       => 'Sport',
'genre_thriller'    => 'Thriller',
'genre_war'         => 'War',
'genre_western'     => 'Western',
	
'category_click_select' => 'Click a category to select',
'tags_optional'         => '(optional)',
'tags_hint_imdb'        => 'Tags will be automatically filled from IMDb when you click the {1} button.',
'js_tc_selected'        => 'Selected:',
'js_tc_tag_added'       => '{1} added to tags',
'js_tc_tag_removed'     => '{1} removed from tags',
'js_tc_tags_cleared'    => 'All tags cleared',	








    // ── Server errors (upload.php) ──
    'err_not_logged_in'       => 'You are not logged in.',
    'err_security'            => 'Invalid security token. Please refresh the page and try again.',
    'err_invalid_id'          => 'Invalid ID.',
    'err_access_denied'       => 'Access denied.',
    'err_screenshot_not_found'=> 'Screenshot not found.',
    'err_no_ids'              => 'No screenshots selected.',
    'err_no_screenshots'      => 'No screenshots found.',
    'err_no_order'            => 'No order provided.',
    'err_no_hash'             => 'No hash provided.',
    'err_torrent_too_large'   => 'Torrent file is too large (max {1} MB).',
    'err_screens_limit'       => 'Your group allows a maximum of {1} screenshots. You tried to upload {2}.',
    'err_screen_bad_file'     => 'Screenshot "{1}": could not be processed (corrupted or unsupported image).',
    'err_image_not_valid'     => 'Image {1}: the link does not point to a valid image.',
    'err_image_save'          => 'Image {1}: could not be saved.',
    'err_image_download'      => 'Image {1}: could not be downloaded.',
    'err_image_url'           => 'Image {1}: invalid link (it must end in .jpg, .png, .gif or .webp).',
    'err_shot_url_invalid'    => 'Screenshot link "{1}": not a valid URL.',
    'err_shot_url_ext'        => 'Screenshot link "{1}": must end in .jpg/.jpeg/.png/.gif/.webp (gallery page links are not supported — use the direct image link).',
    'err_shot_url_ext2'       => 'Screenshot link "{1}": unsupported file type.',
    'err_shot_url_download'   => 'Screenshot link "{1}": could not be downloaded.',
    'err_shot_url_not_image'  => 'Screenshot link "{1}": the downloaded file is not an image ({2}). If this is a gallery page link, use the direct image link.',
    'err_shot_url_db'         => 'Screenshot link "{1}": database error.',
    'imdb_error_not_found'    => 'This title was not found in the movie database.',

    // ── JS: upload_torrent.js ──
    'js_ut_modal_error'       => 'Could not open the dialog',
    'js_ut_delete_title'      => 'Delete screenshot?',
    'js_ut_no_selected'       => 'No screenshot selected for deletion',
    'js_ut_deleting'          => 'Deleting...',
    'js_ut_shot_deleted'      => 'Screenshot deleted!',
    'js_ut_no_shots'          => 'No screenshots yet',
    'js_ut_delete_failed'     => 'Could not delete the screenshot.',
    'js_ut_delete_error'      => 'An error occurred while deleting the screenshot.',
    'js_ut_selected_shots'    => 'Selected screenshots ({1})',
    'js_ut_delete_many_failed'=> 'Could not delete the screenshots.',
    'js_ut_deleted_many'      => '{1} screenshot(s) deleted.',
    'js_ut_skipped_denied'    => '{1} skipped (no permission).',
    'js_ut_network_delete'    => 'Network error while deleting screenshots.',
    'js_ut_item_missing'      => 'Screenshot not found on the page',
    'js_ut_missing_id'        => 'Cannot delete: screenshot ID is missing',
    'js_ut_image_type'        => 'Please choose an image file (JPG, PNG, GIF, WebP)',
    'js_ut_image_size'        => 'The image must be smaller than 10 MB',
    'js_ut_image_remove'      => 'Remove image',
    'js_ut_loading'           => 'Loading...',
    'js_ut_image_url_fail'    => 'Cannot load the image from this link',
    'js_ut_copied'            => 'Copied!',
    'js_ut_ready'             => 'Ready to upload',
    'js_ut_too_many'          => 'Too many! Max {1}, selected {2}.',
    'js_ut_limit_total'       => 'You already have {1} screenshot(s). Max {2} in total — you can add up to {3} more.',
    'js_ut_count'             => '{1} / {2} screenshots',
    'js_ut_select_category'   => 'Please select a category',
    'js_ut_st_torrent'        => 'Uploading torrent file...',
    'js_ut_st_meta'           => 'Processing metadata...',
    'js_ut_st_screens'        => 'Uploading screenshots...',
    'js_ut_st_final'          => 'Finalizing...',
    'js_ut_st_almost'         => 'Almost done!',
    'js_ut_server_error'      => 'Server error:',
    'js_ut_upload_failed'     => 'Upload failed (no details)',
    'js_ut_error_occurred'    => 'An error occurred: {1}',
    'js_ut_only_torrent'      => 'Only .torrent files are accepted!',
    'js_ut_torrent_too_big'   => 'File is too large (max {1} MB).',
    'js_ut_not_yours'         => 'This torrent does not belong to your account.',
    'js_ut_bad_passkey'       => 'Invalid passkey',
    'js_ut_no_hash'           => 'Could not read the torrent info_hash.',
    'js_ut_no_dupes'          => 'Ready to upload — no duplicates found',
    'js_ut_dupe'              => 'Duplicate detected!',
    'js_ut_dupe_anyway'       => 'Duplicate — uploading anyway',
    'js_ut_dupe_ignored'      => 'Duplicate warning ignored. You can now upload.',
    'js_ut_enter_url'         => 'Please enter at least one link.',
    'js_ut_skipped_urls'      => 'Skipped invalid links: {1}. Only jpg, png, gif, webp are supported.',
    'js_ut_no_valid_urls'     => 'No valid image links found.',
    'js_ut_loading_images'    => 'Loading {1} image(s)...',
    'js_ut_loaded_shots'      => 'Loaded {1} screenshot(s)',
    'js_ut_failed_shots'      => '{1} failed to load',
    'js_ut_remove'            => 'Remove',
    'js_ut_no_file_list'      => 'No file list available',
    'js_ut_order_saved'       => 'Screenshot order saved!',
    'js_ut_imdb_empty'        => 'Please enter an IMDb link first.',
    'js_ut_imdb_invalid'      => 'Invalid IMDb link. Example: https://www.imdb.com/title/tt1234567/',
    'js_ut_fetching'          => 'Fetching...',
    'js_ut_fetch'             => 'Fetch',
    'js_ut_imdb_failed'       => 'Could not get IMDb data',
    'js_ut_imdb_loaded'       => 'IMDb data loaded!',
    'js_ut_network_error'     => 'Network error: {1}',
    'js_ut_no_poster'         => 'No poster available.',
    'js_ut_poster_main'       => 'Poster set as the main image!',
    'js_ut_poster_2nd'        => 'Poster set as the secondary image!',
    'js_ut_imdb_added'        => 'IMDb info added to the description!',
    'js_ut_hide'              => 'Hide',
    'js_ut_preview'           => 'Preview',
    'js_ut_done_title'        => 'Upload completed',
    'js_ut_done_private'      => 'Your torrent has been uploaded and updated with the private flag.',
    'js_ut_saved_title'       => 'Changes saved',
    'js_ut_saved_text'        => 'Your torrent has been updated.',
    'js_ut_success'           => 'Success!',
    'js_ut_redirect_in'       => 'Redirecting in',
    'js_ut_seconds'           => 'seconds',
    'js_ut_download'          => 'Download torrent',
    'js_ut_view_details'      => 'View details',
    'js_ut_stay'              => 'Stay here',
    'js_ut_view_torrent'      => 'View torrent',
    'js_ut_image_preview'     => 'Image preview',
    'js_ut_dupe_title'        => 'Duplicate torrent detected!',
    'js_ut_dupe_exists'       => 'A torrent with the same info_hash already exists:',
    'js_ut_dupe_uploaded'     => 'uploaded {1}',
    'js_ut_dupe_upload_anyway'=> 'Upload anyway',
    'js_ut_dupe_view'         => 'View existing',
    'js_ut_th_file'           => 'File',
    'js_ut_th_size'           => 'Size',


    'error_details'     => 'Error details',
    'error_check_input' => 'Check your input and try again',
    'error_copy'        => 'Copy error',
	
	
	// ── New torrent in a subscribed category (functions_notify.php) ──
	'notify_upload_pm_subject'    => 'New torrent in {1}: {2}',
	'notify_upload_pm_message'    => "A new torrent has been uploaded in your subscribed category [b]{1}[/b]:\n\n[url={2}][b]{3}[/b][/url]\n\nManage subscriptions: [url={4}/usercp.php?action=options]User CP[/url]",
	'notify_upload_email_subject' => 'New torrent in {1}: {3}',
	'notify_upload_email_message' => "A new torrent has been uploaded in your subscribed category {1}:\n\n{3}\n{2}\n\nManage subscriptions: {4}/usercp.php?action=options\n\n— {5}",






	
	
	

];