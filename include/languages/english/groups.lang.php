<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// admin/groups.php — User Groups

$language['groups'] = array (

	// ── Breadcrumb / page titles ──
	'crumb_root'              => 'User Groups',
	'crumb_add'               => 'Add New Group',
	'title_add'               => 'Add New User Group',
	'title_edit'              => 'Edit User Group',
	'title_manage'            => 'Manage User Groups',

	// ── Hero ──
	'hero_add_sub'            => 'Create a group, then fine-tune its permissions on the next screen',
	'hero_edit_title'         => 'Edit group: {1}',
	'hero_gid'                => 'GID {1}',
	'hero_main_sub'           => 'Permissions, name styles and team-page order for every group',

	// ── Errors ──
	'err_heading'             => 'Please correct the following errors:',
	'err_no_title_new'        => 'You did not enter a title for this new user group',
	'err_no_title'            => 'You did not enter a title for this user group',
	'err_namestyle_username'  => 'The username style must contain {username}',
	'err_namestyle_tags'      => 'You can\'t use script, meta or base tags in the username style',
	'err_moderate_invite'     => 'A group can\'t be both "approval required" and "invite only"',

	// ── Flash / AJAX messages ──
	'flash_created'           => 'User group created successfully',
	'flash_invalid'           => 'You have selected an invalid user group',
	'flash_updated'           => 'The selected user group has been updated successfully',
	'flash_default_nodelete'  => 'Default groups cannot be deleted',
	'flash_order_updated'     => 'The user group display orders have been updated successfully',
	'msg_deleted'             => 'The selected Group has been deleted successfully',

	// ── Tabs ──
	'tab_general'             => 'General',
	'tab_forums'              => 'Forums & Posts',
	'tab_messaging'           => 'Messaging',
	'tab_moderation'          => 'Moderation',

	// ── Section headers ──
	'sec_identity'            => 'Identity',
	'sec_start_perms'         => 'Starting permissions',
	'sec_general'             => 'General options',
	'sec_joining'             => 'Joining',
	'sec_admin'               => 'Administration',
	'sec_viewing'             => 'Viewing',
	'sec_posting'             => 'Posting',
	'sec_polls'               => 'Polls',
	'sec_editing'             => 'Editing',
	'sec_attach'              => 'Attachments & screenshots',
	'sec_pms'                 => 'Private messages',
	'sec_limits'              => 'Limits',
	'sec_email'               => 'Email',
	'sec_approval'            => 'Require approval for',
	'sec_deletion'            => 'Deletion',

	// ── Identity fields: labels ──
	'lbl_title'               => 'Group title',
	'lbl_desc'                => 'Short description',
	'lbl_namestyle'           => 'Username style',
	'lbl_usertitle'           => 'Default user title',
	'lbl_image'               => 'Group image',
	'lbl_preview'             => 'Preview',
	'lbl_preview_name'        => 'Name',
	'lbl_preview_user'        => 'Username',
	'lbl_preview_image'       => 'Image',
	'lbl_copyfrom'            => 'Copy permissions from',

	// ── Identity fields: placeholders ──
	'ph_title'                => 'e.g. Power Users',
	'ph_desc'                 => 'Shown on the team page',
	'ph_usertitle'            => 'e.g. Power User',

	// ── Hints (output as-is, HTML allowed) ──
	'hint_namestyle'          => 'Must contain <code>{username}</code>, e.g. <code>&lt;b style="color:#e67e22"&gt;{username}&lt;/b&gt;</code>',
	'hint_image'              => 'Icon HTML (<code>&lt;i class="fa-solid fa-star"&gt;</code>) or an image path; <code>{lang}</code> = user language',
	'hint_copyfrom'           => 'All group permissions and forum permissions are copied from the selected group',
	'hint_red_switches'       => 'Red switches grant powerful rights — enable only for trusted staff groups.',
	'hint_savebar'            => 'Changes apply to every member of the group',
	'hint_order_footer'       => 'Order applies to groups shown on the team page',

	// ── Select options ──
	'opt_copy_default'        => 'Default permissions (don\'t copy)',

	// ── Buttons ──
	'btn_cancel'              => 'Cancel',
	'btn_create'              => 'Create Group',
	'btn_save'                => 'Save Group',
	'btn_add'                 => 'Add Group',
	'btn_save_order'          => 'Save order',

	// ── Permission switches ──
	'sw_showforumteam'        => 'Show this group on the team page',
	'sw_isbannedgroup'        => 'This is a banned group',
	'sw_canviewwolinvis'      => 'Can see invisible users',
	'sw_joinable'             => 'Users can join this group themselves',
	'sw_moderate'             => 'Join requests must be approved',
	'sw_invite'               => 'Invite only',
	'sw_issupermod'           => 'Users are super moderators',
	'sw_canstaffpanel'        => 'Can access the Staff Panel',
	'sw_cansettingspanel'     => 'Can access the Settings Panel',
	'sw_canview'              => 'Can view the board',
	'sw_canviewthreads'       => 'Can view threads',
	'sw_cansearch'            => 'Can search forums',
	'sw_candlattachments'     => 'Can download attachments',
	'sw_canviewboardclosed'   => 'Can view the board when closed',
	'sw_canpostthreads'       => 'Can post new threads',
	'sw_canpostreplys'        => 'Can reply to threads',
	'sw_canpostpolls'         => 'Can create polls',
	'sw_canvotepolls'         => 'Can vote in polls',
	'sw_canundovotes'         => 'Can undo own poll votes',
	'sw_caneditposts'         => 'Can edit own posts',
	'sw_candeleteposts'       => 'Can delete own posts',
	'sw_candeletethreads'     => 'Can delete own threads',
	'sw_caneditattachments'   => 'Can edit own attachments',
	'sw_canpostattachments'   => 'Can post attachments',
	'sw_canusepms'            => 'Can use private messaging',
	'sw_cansendpms'           => 'Can send messages',
	'sw_cantrackpms'          => 'Can track messages',
	'sw_candenypmreceipts'    => 'Can deny read receipts',
	'sw_canoverridepm'        => 'Can bypass PM limits',
	'sw_cansendemail'         => 'Can email other users',
	'sw_cansendemailoverride' => 'Can bypass email flood check',
	'sw_modposts'             => 'New posts',
	'sw_modthreads'           => 'New threads',
	'sw_mod_edit_posts'       => 'Edited posts',
	'sw_modattachments'       => 'New attachments',
	'sw_candeletetorrent'     => 'Can delete torrents',

	// ── Number fields ──
	'lbl_attachquota'         => 'Attachment quota',
	'lbl_max_screenshots'     => 'Screenshots per torrent',
	'lbl_pmquota'             => 'PM quota',
	'lbl_maxpmrecipients'     => 'Max PM recipients',
	'hint_unlimited'          => '0 = unlimited',
	'hint_not_allowed'        => '0 = not allowed',
	'hint_per_message'        => 'Per message',
	'unit_kb'                 => 'KB',
	'unit_messages'           => 'messages',
	'unit_users'              => 'users',

	// ── Group tags ──
	'tag_default'             => 'Default',
	'tag_custom'              => 'Custom',
	'tag_staff'               => 'Staff',
	'tag_banned'              => 'Banned',
	'tag_invite'              => 'Invite',
	'tag_request'             => 'Request',
	'tag_open'                => 'Open',
	'tag_team'                => 'Team page',

	// ── KPI tiles ──
	'kpi_groups'              => 'Groups',
	'kpi_custom'              => 'Custom',
	'kpi_staff'               => 'Staff groups',
	'kpi_users'               => 'Users',

	// ── Groups table ──
	'th_group'                => 'Group',
	'th_flags'                => 'Flags',
	'th_members'              => 'Members',
	'th_order'                => 'Team order',
	'th_actions'              => 'Actions',
	'lbl_additional'          => '{1} additional',
	'aria_order'              => 'Display order',
	'tip_order_na'            => 'Only groups shown on the team page are ordered',
	'tip_edit'                => 'Edit',
	'tip_list_users'          => 'List users',
	'tip_delete'              => 'Delete',
	'tip_default_locked'      => 'Default groups cannot be deleted',
	'empty_groups'            => 'No user groups found.',

	// ── JS: usergroups.js (preview) ──
	'js_preview_username'     => 'Username',
	'js_img_not_found'        => 'not found',

	// ── JS: deleteGroup.js (delete modal) ──
	'js_del_title'            => 'Delete Group',
	'js_del_confirm'          => 'Are you sure you want to delete this group? This action cannot be undone.',
	'js_btn_no'               => 'No',
	'js_btn_delete'           => 'Delete!',
	'js_deleting'             => 'Deleting...',
	'js_no_token'             => 'Security token not found on page. Please reload the page and try again.',
	'js_del_error'            => 'Error deleting group. Please try again.',
	'js_btn_ok'               => 'OK',
);
?>
