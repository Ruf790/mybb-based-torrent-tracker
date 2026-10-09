<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');
$language['country'] = array (

    // ── Page titles / subtitles ──
    'pane_title_list'   => 'Countries',
    'pane_title_add'    => 'Add country',
    'pane_title_edit'   => 'Edit country',
    'sub_list'          => 'The list members pick from in their profile, with the flag shown next to their name.',
    'sub_add'           => 'The flag must already be in the flags folder on the server.',
    'sub_edit'          => 'Renaming changes it for every member who picked this country.',

    // ── Buttons ──
    'btn_add'           => 'Add country',
    'btn_back'          => 'All countries',
    'btn_cancel'        => 'Cancel',
    'btn_save'          => 'Save changes',

    // ── Statistics cards ──
    'kpi_countries'     => 'Countries',
    'kpi_flag_files'    => 'Flag files',
    'kpi_unused'        => 'Flags not used yet',
    'kpi_missing'       => 'Missing flag file',

    // ── Section headings ──
    'sec_all'           => 'All countries',
    'sec_unused'        => 'Flags not used yet',
    'sec_preview'       => 'How members see it',

    // ── Table ──
    'th_flag'           => 'Flag',
    'th_name'           => 'Name',
    'th_file'           => 'File',
    'th_id'             => 'ID',
    'th_actions'        => 'Actions',
    'tag_missing'       => 'Missing',
    'tip_flag_missing'  => 'Flag file missing',
    'tip_edit'          => 'Edit {1}',
    'tip_delete'        => 'Delete {1}',

    // ── Form labels ──
    'lbl_name'          => 'Country name',
    'lbl_flag'          => 'Flag',
    'lbl_no_flag_picked' => 'No flag picked',
    'lbl_country_id'    => 'Country ID {1}',
    'ph_name'           => 'Bulgaria',
    'ph_filter'         => 'Filter by name or file',
    'ph_find_flag'      => 'Find a flag file',
    'aria_filter'       => 'Filter countries',
    'aria_find_flag'    => 'Find a flag file',
    'aria_flag_group'   => 'Flag',

    // ── Hints ──
    'hint_unused'       => 'Click a flag to add a country with it.',

    // ── Empty states ──
    'empty_title'       => 'No countries yet',
    'empty_text'        => 'Add the first one and members can pick it in their profile.',
    'empty_no_match'    => 'No country matches that filter.',
    'empty_no_flag_match' => 'No flag file matches.',
    'empty_no_flag_files' => 'No flag images found in {1}. Upload the files first.',

    // ── Validation errors ──
    'err_name_empty'    => 'Enter the country name.',
    'err_name_long'     => 'The name is longer than {1} characters.',
    'err_flag_empty'    => 'Pick a flag.',
    'err_flag_missing'  => 'That flag file is not in the flags folder.',
    'err_duplicate'     => '“{1}” is already on the list.',

    // ── Flash messages ──
    'flash_security'    => 'Security check failed. Please try again.',
    'flash_unknown'     => 'Unknown action.',
    'flash_not_found'   => 'Country not found.',
    'flash_saved'       => '{1} saved.',
    'flash_added'       => '{1} added.',
    'flash_deleted'     => '{1} deleted.',

    // ── JS strings (exported to AGS_LANG without the js_ prefix) ──
    'js_confirm_title'  => 'Delete {1}?',
    'js_confirm_text'   => 'Members who picked this country will show no flag until they choose another.',
    'js_confirm_btn'    => 'Delete',
    'js_cancel_btn'     => 'Cancel',
    'js_default_name'   => 'this country',
    'js_preview_name'   => 'Country name',
);
?>
