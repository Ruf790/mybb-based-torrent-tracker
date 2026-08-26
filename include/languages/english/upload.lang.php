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
    'tags_hint'             => 'Click on any genre button to add or remove it from tags. Tags will be automatically filled from IMDb when you click the <strong>Fetch Movie Info</strong> button.',
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

];