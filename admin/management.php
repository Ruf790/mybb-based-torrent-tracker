<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define("IN_ADMINCP", 1);
define ('TSF_FORUMS_TSSEv56', true);
define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
define ('TSF_VERSION', 'v1.5 by xam');


// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}



require_once $thispath . 'include/class_page.php';
require_once $thispath . 'include/class_form.php';
require_once $thispath . 'include/class_table.php';





// Include the layout generation class overrides for this style
if(file_exists($thispath . 'include/style.php'))
{
	require_once $thispath . 'include/style.php';
}

// Check if any of the layout generation classes we can override exist in the style file
$classes = array(
	"Page" => "DefaultPage",
	"SidebarItem" => "DefaultSidebarItem",
	"PopupMenu" => "DefaultPopupMenu",
	"Table" => "DefaultTable",
	"Form" => "DefaultForm",
	"FormContainer" => "DefaultFormContainer"
);
foreach($classes as $style_name => $default_name)
{
	// Style does not have this layout generation class, create it
	if(!class_exists($style_name))
	{
		eval("class {$style_name} extends {$default_name} { }");
	}
}

$page = new Page;
//$page->style = $cp_style;





foreach(array('action', 'do', 'module') as $input)
{
	if(!isset($mybb->input[$input]))
	{
		$mybb->input[$input] = '';
	}
}




$lang->load('forum_management');





$page->add_breadcrumb_item('Forum Management', "index.php?act=management");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || $mybb->input['action'] == "permissions" || !$mybb->input['action'])
{
	if(!empty($mybb->input['fid']) && ($mybb->input['action'] == "management" || $mybb->input['action'] == "edit" || $mybb->input['action'] == "copy" || !$mybb->input['action']))
	{
	
		
		$sub_tabs['view_forum'] = array(
			'title' =>'View Forum',
			'link' => "index.php?act=management&amp;fid=".$mybb->input['fid'],
			'description' => 'Here you can view sub forums, quickly edit permissions and add moderators to your forum'
		);

		$sub_tabs['add_child_forum'] = array(
			'title' => 'Add Child Forum',
			'link' => "index.php?act=management&amp;action=add&amp;pid=".$mybb->input['fid'],
			'description' => 'Here you can view sub forums, quickly edit permissions and add moderators to your forum'
		);

		$sub_tabs['edit_forum_settings'] = array(
			'title' => 'Edit Forum Settings',
			'link' => "index.php?act=management&action=edit&amp;fid=".$mybb->input['fid'],
			'description' => 'Here you can edit an existing forums settings and its permissions'
		);

		$sub_tabs['copy_forum'] = array(
			'title' => 'Copy Forum',
			'link' => "index.php?act=management&action=copy&amp;fid=".$mybb->input['fid'],
			'description' => 'Here you can copy forum settings or permissions from an existing forum to another or to a new forum'
		);
	}
	else
	{
		$sub_tabs['forum_management'] = array(
			'title' => 'Forum Management',
			'link' => "index.php?act=management",
			'description' => 'This section allows you to manage the categories and forums on your board. You can manage forum permissions and forum-specific moderators as well. If you change the display order for one or more forums or categories, make sure you submit the form at the bottom of the page'
		);

		$sub_tabs['add_forum'] = array(
			'title' => 'Add New Forum',
			'link' => "index.php?act=management&action=add",
			'description' => 'Here you can add a new forum or category to your board. You may also set initial permissions for this forum'
		);
	}
}

$plugins->run_hooks("admin_forum_management_begin");

if($mybb->input['action'] == "copy")
{
	$plugins->run_hooks("admin_forum_management_copy");

	if($mybb->request_method == "post")
	{
		$from = $mybb->get_input('from', MyBB::INPUT_INT);
		$to = $mybb->get_input('to', MyBB::INPUT_INT);

		// Find the source forum
		$query = $db->simple_select("tsf_forums", '*', "fid='{$from}'");
		$from_forum = $db->fetch_array($query);
		if(!$db->num_rows($query))
		{
			$errors[] ='error_invalid_source_forum';
		}

		if($to == -1)
		{
			// Create a new forum
			if(empty($mybb->input['title']))
			{
				$errors[] = 'You need to give your new forum a name';
			}

			if($mybb->input['pid'] == -1 && $mybb->input['type'] == 'f')
			{
				$errors[] = 'You must select a parent forum';
			}

			if(!$errors)
			{
				if($mybb->input['pid'] < 0)
				{
					$mybb->input['pid'] = 0;
				}
				$new_forum = $from_forum;
				unset($new_forum['fid'], $new_forum['threads'], $new_forum['posts'], $new_forum['lastpost'], $new_forum['lastposter'], $new_forum['lastposteruid'], $new_forum['lastposttid'], $new_forum['lastpostsubject'], $new_forum['unapprovedthreads'], $new_forum['unapprovedposts']);
				$new_forum['name'] = $mybb->input['title'];
				$new_forum['description'] = $mybb->input['description'];
				$new_forum['type'] = $mybb->input['type'];
				$new_forum['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
				$new_forum['parentlist'] = '';

				foreach($new_forum as $key => $value)
				{
					$new_forum[$key] = $db->escape_string($value);
				}

				$to = $db->insert_query("tsf_forums", $new_forum);

				// Generate parent list
				$parentlist = make_parent_list($to);
				$updatearray = array(
					'parentlist' => $parentlist
				);
				$db->update_query("tsf_forums", $updatearray, "fid='{$to}'");
			}
		}
		elseif($mybb->input['copyforumsettings'] == 1)
		{
			// Copy settings to existing forum
			$query = $db->simple_select("tsf_forums", '*', "fid='{$to}'");
			$to_forum = $db->fetch_array($query);
			if(!$db->num_rows($query))
			{
				$errors[] = 'Invalid destination forum';
			}

			if(!$errors)
			{
				$new_forum = $from_forum;
				unset($new_forum['fid'], $new_forum['threads'], $new_forum['posts'], $new_forum['lastpost'], $new_forum['lastposter'], $new_forum['lastposteruid'], $new_forum['lastposttid'], $new_forum['lastpostsubject'], $new_forum['unapprovedthreads'], $new_forum['unapprovedposts']);
				$new_forum['name'] = $to_forum['name'];
				$new_forum['description'] = $to_forum['description'];
				$new_forum['pid'] = $to_forum['pid'];
				$new_forum['parentlist'] = $to_forum['parentlist'];

				foreach($new_forum as $key => $value)
				{
					$new_forum[$key] = $db->escape_string($value);
				}

				$db->update_query("tsf_forums", $new_forum, "fid='{$to}'");
			}
		}
		else
		{
			$new_forum['name'] = null;
		}

		if(!$errors)
		{
			// Copy permissions
			if(isset($mybb->input['copygroups']) && is_array($mybb->input['copygroups']) && count($mybb->input['copygroups']) > 0)
			{
				foreach($mybb->input['copygroups'] as $gid)
				{
					$groups[] = (int)$gid;
				}
				$groups = implode(',', $groups);
				$query = $db->simple_select("forumpermissions", '*', "fid='{$from}' AND gid IN ({$groups})");
				$db->delete_query("forumpermissions", "fid='{$to}' AND gid IN ({$groups})", 1);
				while($permissions = $db->fetch_array($query))
				{
					unset($permissions['pid']);
					$permissions['fid'] = $to;

					$db->insert_query("forumpermissions", $permissions);
				}

				// Log admin action
				log_admin_action($from, $from_forum['name'], $to, $new_forum['name'], $groups);
			}
			else
			{
				// Log admin action (no group permissions)
				log_admin_action($from, $from_forum['name'], $to, $new_forum['name']);
			}

			$plugins->run_hooks("admin_forum_management_copy_commit");

			$cache->update_forums();
			$cache->update_forumpermissions();

			flash_message($lang->forum_management['success_forum_copied'], 'success');
			admin_redirect("index.php?act=management&action=edit&fid={$to}");
		}
	}

	$page->add_breadcrumb_item('copy_forum');
	//$page->output_header('copy_forum22222');
	
	
	stdhead('copy_forum22222');
	
	 echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";

		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
	
	
	
	

	
	
	
	
	output_nav_tabs($sub_tabs, 'Copy Forum');

	$form = new Form("index.php?act=management&action=copy", "post");

	$copy_data['type'] = "f";
	$copy_data['title'] = "";
	$copy_data['description'] = "";

	if(empty($mybb->input['pid']))
	{
		$copy_data['pid'] = "-1";
	}
	else
	{
		$copy_data['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
	}
	$copy_data['disporder'] = "1";
	$copy_data['from'] = $mybb->get_input('fid');
	$copy_data['to'] = -1;
	$copy_data['copyforumsettings'] = 0;
	$copy_data['copygroups'] = array();
	$copy_data['pid'] = 0;

	if($errors)
	{
		output_inline_error($errors);

		foreach($copy_data as $key => $value)
		{
			if(isset($mybb->input[$key]))
			{
				$copy_data[$key] = $mybb->input[$key];
			}
		}
	}

	$types = array(
		'f' => $lang->forum_management['forum'],
		'c' => $lang->forum_management['category']
	);

	$create_a_options_f = array(
		'id' => 'forum'
	);

	$create_a_options_c = array(
		'id' => 'category'
	);

	if($copy_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}

	$usergroupsZZ = array();

	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroupsZZ[$usergroup['gid']] = htmlspecialchars_uni($usergroup['title']);
	}

	
	
	
	$form_container = new FormContainer('Copy Forum');
	
	echo '<div class="container mt-3">';
	
	$form_container->output_row('Source forum'." <em>*</em>", 'Forum to copy settings and/or permissions from', $form->generate_forum_select('from', $copy_data['from'], array('id' => 'from')), 'from');
	$form_container->output_row('Destination forum'." <em>*</em>", 'Forum to copy settings and/or permissions to', $form->generate_forum_select('to', $copy_data['to'], array('id' => 'to', 'main_option' => 'Copy to new forum')), 'to');
	$form_container->output_row('Copy Forum Settings and Properties', 'Only applies if the destination forum exists', $form->generate_yes_no_radio('copyforumsettings', $copy_data['copyforumsettings']));
	$form_container->output_row('Copy User Group Permissions', 'Use CTRL to select multiple groups', generate_select_box('copygroups[]', $usergroupsZZ, $copy_data['copygroups'], array('id' => 'copygroups', 'multiple' => true, 'size' => 5)), 'copygroups');

	$form_container->end();

	$form_container = new FormContainer('New Forum Settings');
	$form_container->output_row('Forum Type', 'Select the type of forum you are creating - a forum you can post in, or a category, which contains other forums', $form->generate_radio_button('type', 'f', 'Forum', $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', 'Category', $create_a_options_c));
	$form_container->output_row('Title'." <em>*</em>", "", $form->generate_text_box('title', $copy_data['title'], array('id' => 'title')), 'title');
	$form_container->output_row('Description', "", $form->generate_text_area('description', $copy_data['description'], array('id' => 'description')), 'description');
	$form_container->output_row('Parent Forum'." <em>*</em>", 'The Forum that contains this forum. Categories do not have a parent forum - in this case, select None - however, categories can be specified to have a parent forum', $form->generate_forum_select('pid', $copy_data['pid'], array('id' => 'pid', 'main_option' => 'None')), 'pid');

	$form_container->end();

	//$buttons[] = $form->generate_submit_button('copy_forum');
	
	
	
	echo '<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
	<input type="submit" class="btn btn-primary" value="Copy Forum" />
	 
	
	</div>';
	
	
	
	
	//$form->output_submit_wrapper($buttons);
	$form->end();
	
	echo '</div>';

	stdfoot();
}

if($mybb->input['action'] == "editmod")
{
	$query = $db->simple_select("moderators", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$mod_data = $db->fetch_array($query);

	if(!$mod_data['id'])
	{
		flash_message($lang->forum_management['error_incorrect_moderator'], 'error');
		admin_redirect("index.php?act=management");
	}

	$plugins->run_hooks("admin_forum_management_editmod");

	if($mod_data['isgroup'])
	{
		$fieldname = "title";
	}
	else
	{
		$fieldname = "username";
	}

	if($mybb->request_method == "post")
	{
		$mid = $mybb->get_input('mid', MyBB::INPUT_INT);
		if(!$mid)
		{
			flash_message($lang->forum_management['error_incorrect_moderator'], 'error');
			admin_redirect("index.php?act=management");
		}

		if(!$errors)
		{
			$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
			$forum = get_forum($fid, 1);
			if($mod_data['isgroup'])
			{
				$mod = $groupscache[$mod_data['id']];
			}
			else
			{
				$mod = get_user($mod_data['id']);
			}
			$update_array = array(
				'fid' => (int)$fid,
				'caneditposts' => $mybb->get_input('caneditposts', MyBB::INPUT_INT),
				'cansoftdeleteposts' => $mybb->get_input('cansoftdeleteposts', MyBB::INPUT_INT),
				'canrestoreposts' => $mybb->get_input('canrestoreposts', MyBB::INPUT_INT),
				'candeleteposts' => $mybb->get_input('candeleteposts', MyBB::INPUT_INT),
				'cansoftdeletethreads' => $mybb->get_input('cansoftdeletethreads', MyBB::INPUT_INT),
				'canrestorethreads' => $mybb->get_input('canrestorethreads', MyBB::INPUT_INT),
				'candeletethreads' => $mybb->get_input('candeletethreads', MyBB::INPUT_INT),
				'canviewips' => $mybb->get_input('canviewips', MyBB::INPUT_INT),
				'canviewunapprove' => $mybb->get_input('canviewunapprove', MyBB::INPUT_INT),
				'canviewdeleted' => $mybb->get_input('canviewdeleted', MyBB::INPUT_INT),
				'canopenclosethreads' => $mybb->get_input('canopenclosethreads', MyBB::INPUT_INT),
				'canstickunstickthreads' => $mybb->get_input('canstickunstickthreads', MyBB::INPUT_INT),
				'canapproveunapprovethreads' => $mybb->get_input('canapproveunapprovethreads', MyBB::INPUT_INT),
				'canapproveunapproveposts' => $mybb->get_input('canapproveunapproveposts', MyBB::INPUT_INT),
				'canapproveunapproveattachs' => $mybb->get_input('canapproveunapproveattachs', MyBB::INPUT_INT),
				'canmanagethreads' => $mybb->get_input('canmanagethreads', MyBB::INPUT_INT),
				'canmanagepolls' => $mybb->get_input('canmanagepolls', MyBB::INPUT_INT),
				'canpostclosedthreads' => $mybb->get_input('canpostclosedthreads', MyBB::INPUT_INT),
				'canmovetononmodforum' => $mybb->get_input('canmovetononmodforum', MyBB::INPUT_INT),
				'canusecustomtools' => $mybb->get_input('canusecustomtools', MyBB::INPUT_INT),
				'canmanageannouncements' => $mybb->get_input('canmanageannouncements', MyBB::INPUT_INT),
				'canmanagereportedposts' => $mybb->get_input('canmanagereportedposts', MyBB::INPUT_INT),
				'canviewmodlog' => $mybb->get_input('canviewmodlog', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_forum_management_editmod_commit");

			$db->update_query("moderators", $update_array, "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");

			$cache->update_moderators();

			// Log admin action
			log_admin_action($fid, $forum['name'], $mid, $mod[$fieldname]);

			flash_message($lang->forum_management['success_moderator_updated'], 'success');
			admin_redirect("index.php?act=management&fid=".$mybb->get_input('fid', MyBB::INPUT_INT)."#tab_moderators");
		}
	}

	if($mod_data['isgroup'])
	{
		$query = $db->simple_select("usergroups", "title", "gid='{$mod_data['id']}'");
		$mod_data[$fieldname] = $db->fetch_field($query, 'title');
	}
	else
	{
		$query = $db->simple_select("users", "username", "id='{$mod_data['id']}'");
		$mod_data[$fieldname] = $db->fetch_field($query, 'username');
	}

	$sub_tabs = array();

	$sub_tabs['edit_mod'] = array(
		'title' => $lang->forum_management['edit_mod'],
		'link' => "index.php?act=management&action=editmod&amp;mid=".$mybb->input['mid'],
		'description' => $lang->forum_management['edit_mod_desc']
	);

	$page->add_breadcrumb_item('forum_moderators', "index.php?act=management&amp;fid={$mod_data['fid']}#tab_moderators");
	$page->add_breadcrumb_item('edit_forum');
	
	//$page->output_header('edit_mod');
	
	

	
	
	
	stdhead('edit_mod');
	
	
	

	
echo '<div class="container mt-3">';
	
output_nav_tabs($sub_tabs, 'edit_mod');

$form = new Form("index.php?act=management&action=editmod", "post", "editModForm");
echo $form->generate_hidden_field("mid", $mod_data['mid']);

if($errors)
{
    output_inline_error($errors);
    $mod_data = $mybb->input;
}

echo '<div class="card border-0 shadow-sm">';
    echo '<div class="card-header bg-primary text-white py-3">';
        echo '<h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>'.sprintf($lang->forum_management['edit_mod_for'], htmlspecialchars_uni($mod_data[$fieldname])).'</h5>';
    echo '</div>';
    echo '<div class="card-body">';

// Форма выбора форума
$form_container = new FormContainer('');
echo '<div class="mb-4">'; // Добавляем отступ вместо set_class
$form_container->output_row(
    $lang->forum_management['forum'], 
    $lang->forum_management['forum_desc'], 
    $form->generate_forum_select('fid', $mod_data['fid'], array('id' => 'fid', 'class' => 'form-select')), 
    'fid'
);
echo '</div>';

// Moderator Permissions
echo '<div class="row mb-4">';
    echo '<div class="col-12">';
        echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-shield-alt me-2 text-warning"></i>'.$lang->forum_management['moderator_permissions'].'</h6>';
        echo '<div class="row">';

$moderator_permissions = array(
    array('caneditposts', $lang->forum_management['can_edit_posts'], 'fa-edit', 'primary'),
    array('cansoftdeleteposts', $lang->forum_management['can_soft_delete_posts'], 'fa-trash-alt', 'secondary'),
    array('canrestoreposts', $lang->forum_management['can_restore_posts'], 'fa-undo', 'success'),
    array('candeleteposts', $lang->forum_management['can_delete_posts'], 'fa-trash', 'danger'),
    array('cansoftdeletethreads', $lang->forum_management['can_soft_delete_threads'], 'fa-trash-alt', 'secondary'),
    array('canrestorethreads', $lang->forum_management['can_restore_threads'], 'fa-undo', 'success'),
    array('candeletethreads', $lang->forum_management['can_delete_threads'], 'fa-trash', 'danger'),
    array('canviewips', $lang->forum_management['can_view_ips'], 'fa-search', 'info'),
    array('canviewunapprove', $lang->forum_management['can_view_unapprove'], 'fa-eye-slash', 'warning'),
    array('canviewdeleted', $lang->forum_management['can_view_deleted'], 'fa-eye', 'info'),
    array('canopenclosethreads', $lang->forum_management['can_open_close_threads'], 'fa-lock-open', 'success'),
    array('canstickunstickthreads', $lang->forum_management['can_stick_unstick_threads'], 'fa-thumbtack', 'warning'),
    array('canapproveunapprovethreads', $lang->forum_management['can_approve_unapprove_threads'], 'fa-check-circle', 'success'),
    array('canapproveunapproveposts', $lang->forum_management['can_approve_unapprove_posts'], 'fa-check-circle', 'success'),
    array('canapproveunapproveattachs', $lang->forum_management['can_approve_unapprove_attachments'], 'fa-check-circle', 'success'),
    array('canmanagethreads', $lang->forum_management['can_manage_threads'], 'fa-tasks', 'primary'),
    array('canmanagepolls', $lang->forum_management['can_manage_polls'], 'fa-chart-bar', 'info'),
    array('canpostclosedthreads', $lang->forum_management['can_post_closed_threads'], 'fa-comment', 'success'),
    array('canmovetononmodforum', $lang->forum_management['can_move_to_other_forums'], 'fa-exchange-alt', 'warning'),
    array('canusecustomtools', $lang->forum_management['can_use_custom_tools'], 'fa-tools', 'primary')
);

foreach(array_chunk($moderator_permissions, 2) as $chunk)
{
    echo '<div class="col-md-6">';
    foreach($chunk as $permission)
    {
        echo '<div class="form-check form-switch mb-3">';
        echo generate_check_box($permission[0], 1, '', array(
            'checked' => $mod_data[$permission[0]],
            'id' => $permission[0],
            'class' => 'form-check-input'
        ));
        echo '<label class="form-check-label" for="'.$permission[0].'">';
        echo '<i class="fas '.$permission[2].' me-2 text-'.$permission[3].'"></i>';
        echo htmlspecialchars_uni($permission[1]);
        echo '</label>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div></div></div>';

// Moderator CP Permissions
echo '<div class="row mb-4">';
    echo '<div class="col-12">';
        echo '<h6 class="border-bottom pb-2 mb-3"><i class="fas fa-cog me-2 text-purple"></i>'.$lang->forum_management['moderator_cp_permissions'].'</h6>';
        echo '<p class="text-muted mb-3">'.$lang->forum_management['moderator_cp_permissions_desc'].'</p>';
        echo '<div class="row">';

$moderator_cp_permissions = array(
    array('canmanageannouncements', $lang->forum_management['can_manage_announcements'], 'fa-bullhorn', 'warning'),
    array('canmanagereportedposts', $lang->forum_management['can_manage_reported_posts'], 'fa-flag', 'danger'),
    array('canviewmodlog', $lang->forum_management['can_view_mod_log'], 'fa-history', 'info')
);

foreach(array_chunk($moderator_cp_permissions, 2) as $chunk)
{
    echo '<div class="col-md-6">';
    foreach($chunk as $permission)
    {
        echo '<div class="form-check form-switch mb-3">';
        echo generate_check_box($permission[0], 1, '', array(
            'checked' => $mod_data[$permission[0]],
            'id' => $permission[0],
            'class' => 'form-check-input'
        ));
        echo '<label class="form-check-label" for="'.$permission[0].'">';
        echo '<i class="fas '.$permission[2].' me-2 text-'.$permission[3].'"></i>';
        echo htmlspecialchars_uni($permission[1]);
        echo '</label>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div></div></div>';

$form_container->end();

echo '</div>'; // .card-body
echo '<div class="card-footer bg-light">';
    $buttons = array();
    $buttons[] = $form->generate_submit_button($lang->forum_management['save_mod'], array('class' => 'btn btn-primary px-4'));
    $buttons[] = $form->generate_reset_button($lang->reset, array('class' => 'btn btn-outline-secondary ms-2'));
    echo '<div class="d-flex">';
    $form->output_submit_wrapper($buttons);
    echo '</div>';
echo '</div>';

echo '</div>'; // .card

$form->end();

// Добавляем стили для улучшения внешнего вида
echo '
<style>
.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
}
.form-switch .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.card-header {
    border-radius: 0.375rem 0.375rem 0 0 !important;
}
.text-purple {
    color: #6f42c1 !important;
}
</style>';


echo '</div>'; // .container mt-3
	
	
	
	
	
	
	
	
	
	
	
	

	stdfoot();
}

if($mybb->input['action'] == "clear_permission")
{
	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);

	// User clicked no
	if(!empty($mybb->input['no']))
	{
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_clear_permission");

	if($mybb->request_method == "post")
	{
		if((!$fid || !$gid) && $pid)
		{
			$query = $db->simple_select("forumpermissions", "fid, gid", "pid='{$pid}'");
			$result = $db->fetch_array($query);
			$fid = $result['fid'];
			$gid = $result['gid'];
		}

		if($pid)
		{
			$db->delete_query("forumpermissions", "pid='{$pid}'");
		}
		else
		{
			$db->delete_query("forumpermissions", "gid='{$gid}' AND fid='{$fid}'");
		}

		$plugins->run_hooks('admin_forum_management_clear_permission_commit');

		$cache->update_forumpermissions();

		flash_message($lang->forum_management['success_custom_permission_cleared'], 'success');
		admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
	}
	else
	{
		$page->output_confirm_action("index.php?act=management&action=clear_permission&amp;pid={$pid}&amp;gid={$gid}&amp;fid={$fid}", 'confirm_clear_custom_permission');
	}
}

if($mybb->input['action'] == "permissions")
{
	$plugins->run_hooks("admin_forum_management_permissions");

	if($mybb->request_method == "post")
	{
		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
		$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
		$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
		$forum = get_forum($fid, 1);

		if((!$fid || !$gid) && $pid)
		{
			$query = $db->simple_select("forumpermissions", "fid, gid", "pid='{$pid}'");
			$result = $db->fetch_array($query);
			$fid = $result['fid'];
			$gid = $result['gid'];
			$forum = get_forum($fid, 1);
		}

		$update_array = $field_list = array();
		$fields_array = $db->show_fields_from("forumpermissions");
		if(isset($mybb->input['permissions']))
		{
			// User has set permissions for this group...
			foreach($fields_array as $field)
			{
				if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
				{
					if(array_key_exists($field['Field'], $mybb->input['permissions']))
					{
						$update_array[$db->escape_string($field['Field'])] = (int)$mybb->input['permissions'][$field['Field']];
					}
					else
					{
						$update_array[$db->escape_string($field['Field'])] = 0;
					}
				}
			}
		}
		else
		{
			// Else, we assume that the group has no permissions...
			foreach($fields_array as $field)
			{
				if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
				{
					$update_array[$db->escape_string($field['Field'])] = 0;
				}
			}
		}

		if($fid && !$pid)
		{
			$update_array['fid'] = $fid;
			$update_array['gid'] = $mybb->get_input('gid', MyBB::INPUT_INT);
			$db->insert_query("forumpermissions", $update_array);
		}

		$plugins->run_hooks("admin_forum_management_permissions_commit");

		if(!($fid && !$pid))
		{
			$db->update_query("forumpermissions", $update_array, "pid='{$pid}'");
		}

		$cache->update_forumpermissions();

		// Log admin action
		log_admin_action($fid, $forum['name']);

		if($mybb->input['ajax'] == 1)
		{
			echo json_encode("<script type=\"text/javascript\">$('#row_{$gid}').html('".str_replace(array("'", "\t", "\n"), array("\\'", "", ""), retrieve_single_permissions_row($gid, $fid))."'); QuickPermEditor.init({$gid});</script>");
			die;
		}
		else
		{
			flash_message($lang->forum_management['success_forum_permissions_saved'], 'success');
			admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
		}
	}

	if($mybb->input['ajax'] != 1)
	{
		$sub_tabs = array();

		if($mybb->input['fid'] && $mybb->input['gid'])
		{
			$sub_tabs['edit_permissions'] = array(
				'title' => 'forum_permissions2',
				'link' => "index.php?act=management&action=permissions&amp;fid=".$mybb->input['fid']."&amp;gid=".$mybb->input['gid'],
				'description' => 'forum_permissions_desc'
			);

			$page->add_breadcrumb_item('forum_permissions2', "index.php?act=management&amp;fid=".$mybb->input['fid']."#tab_permissions");
		}
		else
		{
			$query = $db->simple_select("forumpermissions", "fid", "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'");
			$mybb->input['fid'] = $db->fetch_field($query, "fid");

			$sub_tabs['edit_permissions'] = array(
				'title' => 'forum_permissions33',
				'link' => "index.php?act=management&action=permissions&amp;pid=".$mybb->get_input('pid', MyBB::INPUT_INT),
				'description' => 'forum_permissions_desc'
			);

			$page->add_breadcrumb_item('forum_permissions2', "index.php?act=management&amp;fid=".$mybb->input['fid']."#tab_permissions");
		}

		$page->add_breadcrumb_item('forum_permissions444');
		//$page->output_header('forum_permissions');
		
		
		stdhead();
		
		
	
		
		
		echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";

		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
		
		
		
		

		
		
		output_nav_tabs($sub_tabs, 'edit_permissions');
	}
	
	
	
	
   else
   {
	   
	   
	    echo '
                    <script src="scripts/popup.js" type="text/javascript"></script>
					<script src="scripts/tabs.js" type="text/javascript"></script>
                    <script type="text/javascript">
                    $(function() {
                        // Save permissions handler
                        $("#modal_form").on("click", "#savePermissions", function(e) {
                            e.preventDefault();
                            var submitBtn = $(this);
                            var originalText = submitBtn.html();
                            
                            // Show loading state
                            submitBtn.prop("disabled", true).html(\'<i class="fas fa-spinner fa-spin me-2"></i>Saving...\');

                            var datastring = $("#modal_form").serialize();
                            $.ajax({
                                type: "POST",
                                url: $("#modal_form").attr("action"),
                                data: datastring,
                                dataType: "json",
                                success: function(data) {
                                    // Execute any scripts returned in the response
                                    $(data).filter("script").each(function() {
                                        try {
                                            eval($(this).text());
                                        } catch (e) {
                                            console.error("Error executing script: ", e);
                                        }
                                    });
                                    const modal = bootstrap.Modal.getInstance(document.getElementById("dynamicModal")); 
                                    if (modal) modal.hide();
                                    submitBtn.prop("disabled", false).html(originalText);
                                },
                                error: function(xhr, status, error) {
                                    alert("Failed to save permissions. Please try again.");
                                    submitBtn.prop("disabled", false).html(originalText);
                                }
                            });
                        });
                        
                        // Initialize Bootstrap tabs
                        $("#permissionTabs button").on("click", function(e) {
                            e.preventDefault();
                            $(this).tab("show");
                        });
                    });
                    </script>';
	   
	   
	   
	   
	   
	   
	   
	   
   }
	
	

	
	
	if(!empty($mybb->input['pid']) || (!empty($mybb->input['gid']) && !empty($mybb->input['fid'])))
	{
	
	echo '
    <div class="modal fade" id="dynamicModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-shield-alt me-2"></i>Forum Permissions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-4">
                        <div style="overflow-y: auto; max-height: 400px">';


    // Create form with sanitized inputs
    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
    $gid = $mybb->get_input('gid', MyBB::INPUT_INT);
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);

    $form = new Form(
        "index.php?act=management&action=permissions&ajax=1&pid=" . (int)$pid . "&gid=" . (int)$gid . "&fid=" . (int)$fid,
        "post",
        "modal_form"
    );
   
 
   
   
    echo $form->generate_hidden_field("usecustom", "1");

    if ($errors) {
        output_inline_error($errors);
        $permission_data = $mybb->input;

        $query = $db->simple_select("usergroups", "*", "gid = '" . $db->escape_string($permission_data['gid']) . "'");
        $usergroup = $db->fetch_array($query);

        $query = $db->simple_select("tsf_forums", "*", "fid = '" . $db->escape_string($permission_data['fid']) . "'");
        $forum = $db->fetch_array($query);
    } 
	else 
	{
        // Fetch permission data with proper sanitization
        if ($pid) 
		{
            $query = $db->simple_select("forumpermissions", "*", "pid = '" . $db->escape_string($pid) . "'");
        } 
		else 
		{
            $query = $db->simple_select(
                "forumpermissions",
                "*",
                "fid = '" . $db->escape_string($fid) . "' AND gid = '" . $db->escape_string($gid) . "'",
                array('limit' => 1)
            );
        }

        $permission_data = $db->fetch_array($query);

        if (is_array($permission_data)) 
		{
            $fid = $fid ?: $permission_data['fid'];
            $gid = $gid ?: $permission_data['gid'];
            $pid = $pid ?: $permission_data['pid'];
        }

        $query = $db->simple_select("usergroups", "*", "gid = '" . $db->escape_string($gid) . "'");
        $usergroup = $db->fetch_array($query);

        $query = $db->simple_select("tsf_forums", "*", "fid = '" . $db->escape_string($fid) . "'");
        $forum = $db->fetch_array($query);

        $sperms = $permission_data;

        $sql = build_parent_list($fid);
        $query = $db->simple_select(
            "forumpermissions",
            "*",
            $sql . " AND gid = '" . $db->escape_string($gid) . "'"
        );
        $customperms = $db->fetch_array($query);

        if (!empty($permission_data['pid'])) 
		{
            $permission_data['usecustom'] = 1;
            echo $form->generate_hidden_field("pid", (int)$pid);
        } 
		else 
		{
            echo $form->generate_hidden_field("fid", (int)$fid);
            echo $form->generate_hidden_field("gid", (int)$gid);
            $permission_data = empty($customperms['pid'])
                ? usergroup_permissions($gid)
                : forum_permissions($fid, 0, $gid);
        }
    }

    $groups = [
        'canviewthreads' => 'viewing',
        'canview' => 'viewing',
        'canonlyviewownthreads' => 'viewing',
        'candlattachments' => 'viewing',
        'canpostthreads' => 'posting_rating',
        'canpostreplys' => 'posting_rating',
        'canonlyreplyownthreads' => 'posting_rating',
        'canpostattachments' => 'posting_rating',
        'canratethreads' => 'posting_rating',
        'caneditposts' => 'editing',
        'candeleteposts' => 'editing',
        'candeletethreads' => 'editing',
        'caneditattachments' => 'editing',
        'canviewdeletionnotice' => 'editing',
        'modposts' => 'moderate',
        'modthreads' => 'moderate',
        'modattachments' => 'moderate',
        'mod_edit_posts' => 'moderate',
        'canpostpolls' => 'polls',
        'canvotepolls' => 'polls',
        'cansearch' => 'misc',
    ];

    $hidefields = ($usergroup['gid'] == 222)
        ? ['canonlyviewownthreads', 'canonlyreplyownthreads', 'caneditposts', 'candeleteposts', 'candeletethreads', 'caneditattachments', 'canviewdeletionnotice']
        : [];

    $groups = $plugins->run_hooks("admin_forum_management_permission_groups", $groups);

    foreach ($hidefields as $field) {
        unset($groups[$field]);
    }

    // Define tab colors, icons, and titles
    $tab_colors = [
        'viewing' => 'bg-primary',
        'posting_rating' => 'bg-success',
        'editing' => 'bg-info',
        'moderate' => 'bg-warning',
        'polls' => 'bg-purple',
        'misc' => 'bg-secondary'
    ];

    $tab_icons = [
        'viewing' => 'fa-eye',
        'posting_rating' => 'fa-comment',
        'editing' => 'fa-edit',
        'moderate' => 'fa-gavel',
        'polls' => 'fa-chart-bar',
        'misc' => 'fa-cog'
    ];

    $tab_titles = [
        'viewing' => 'Viewing',
        'posting_rating' => 'Posting & Rating',
        'editing' => 'Editing',
        'moderate' => 'Moderation',
        'polls' => 'Polls',
        'misc' => 'Misc'
    ];
	
	
	
	
	$l['viewing_field_canview'] = "Can view forum?";
$l['viewing_field_canviewthreads'] = "Can view threads within forum?";
$l['viewing_field_canonlyviewownthreads'] = "Can only view own threads?";
$l['viewing_field_candlattachments'] = "Can download attachments?";

$l['posting_rating_field_canpostthreads'] = "Can post threads?";
$l['posting_rating_field_canpostreplys'] = "Can post replies?";
$l['posting_rating_field_canonlyreplyownthreads'] = "Can only reply to own threads?";
$l['posting_rating_field_canpostattachments'] = "Can post attachments?";
$l['posting_rating_field_canratethreads'] = "Can rate threads?";

$l['editing_field_caneditposts'] = "Can edit own posts?";
$l['editing_field_candeleteposts'] = "Can delete own posts?";
$l['editing_field_candeletethreads'] = "Can delete own threads?";
$l['editing_field_caneditattachments'] = "Can update own attachments?";
$l['editing_field_canviewdeletionnotice'] = "Can view deletion notices?";

$l['moderate_field_modposts'] = "Moderate new posts?";
$l['moderate_field_modthreads'] = "Moderate new threads?";
$l['moderate_field_modattachments'] = "Moderate new attachments?";
$l['moderate_field_mod_edit_posts'] = "Moderate posts after they've been edited?";

$l['polls_field_canpostpolls'] = "Can post polls?";
$l['polls_field_canvotepolls'] = "Can vote in polls?";

$l['misc_field_cansearch'] = "Can search forum?";

	
	
	
	
	
	
	
	
	
	

    // Output navigation tabs
    echo '<div class="container-fluid px-0">
            <ul class="nav nav-tabs nav-justified mb-4" id="permissionTabs" role="tablist">';

    $first = true;
    foreach (array_unique(array_values($groups)) as $group) {
        echo '<li class="nav-item" role="presentation">
                <button class="nav-link' . ($first ? ' active' : '') . '" id="' . htmlspecialchars($group) . '-tab" data-bs-toggle="tab" data-bs-target="#tab_' . htmlspecialchars($group) . '" type="button" role="tab" aria-controls="tab_' . htmlspecialchars($group) . '" aria-selected="' . ($first ? 'true' : 'false') . '">
                    <i class="fas ' . htmlspecialchars($tab_icons[$group]) . ' me-1"></i>' . htmlspecialchars($tab_titles[$group]) . '
                </button>
            </li>';
        $first = false;
    }

    echo '</ul><div class="tab-content">';

    // Output tab content
    $first = true;
    foreach (array_unique(array_values($groups)) as $group) {
        echo '<div class="tab-pane fade' . ($first ? ' show active' : '') . '" id="tab_' . htmlspecialchars($group) . '" role="tabpanel" aria-labelledby="' . htmlspecialchars($group) . '-tab">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header ' . htmlspecialchars($tab_colors[$group]) . ' text-white py-3">
                        <h6 class="mb-0">
                            <i class="fas fa-user me-2"></i>"' . htmlspecialchars($usergroup['title']) . '" Custom Permissions for "' . htmlspecialchars($forum['name']) . '"
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">';

        foreach ($db->show_fields_from("forumpermissions") as $field) {
            if (!in_array($field['Field'], $hidefields) && 
                (strpos($field['Field'], 'can') === 0 || strpos($field['Field'], 'mod') === 0) &&
                isset($groups[$field['Field']]) && $groups[$field['Field']] == $group) {
                
                $checkbox = $form->generate_check_box(
                    "permissions[{$field['Field']}]",
                    1,
                    "",
                    [
                        'checked' => !empty($permission_data[$field['Field']]),
                        'id' => $field['Field'],
                        'class' => 'form-check-input'
                    ]
                );

	
				echo '<div class="col-md-6">
        <div class="form-check form-switch mb-3">
            ' . $checkbox . '
            <label class="form-check-label" for="' . htmlspecialchars($field['Field']) . '">' . $l[$group . '_field_' . $field['Field']] . '</label>
        </div>
    </div>';	
							
		
            }
        }

        echo '</div></div></div></div>';
        $first = false;
    }

    echo '</div></div>';

    // Output buttons
    echo '<div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-2"></i>Cancel
            </button>
           
		   
		   
		   <button type="submit" class="btn btn-primary" id="savePermissions"><i class="fas fa-save me-2"></i>
		   Save Permissions
		   </button>
		   
		   
        </div>';

    // End form
    $form->end();

    echo '</div></div></div></div>';

    // Output CSS styles
    echo '<style>
        .nav-tabs .nav-link { 
            border: none; 
            border-bottom: 3px solid transparent; 
            color: #6c757d; 
            padding: 12px 16px; 
            transition: all 0.3s ease; 
        }
        .nav-tabs .nav-link:hover { 
            border-color: #dee2e6; 
            color: #495057; 
        }
        .nav-tabs .nav-link.active { 
            border-color: #0d6efd; 
            color: #0d6efd; 
            background: transparent; 
            font-weight: 600; 
        }
        .form-check-input:checked { 
            background-color: #0d6efd; 
            border-color: #0d6efd; 
        }
        .card { 
            border-radius: 12px; 
        }
        .modal-content { 
            border-radius: 16px; 
            border: none; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
        }
        .bg-purple { 
            background-color: #6f42c1 !important; 
        }
        .btn-primary { 
            background-color: #0d6efd; 
            border-color: #0d6efd; 
            padding: 8px 16px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
    </style>';
	
	}
	
	
	
	
	
	
	
	
	

	if($mybb->input['ajax'] != 1)
	{
		stdfoot();
	}
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_forum_management_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = 'You must enter in a title';
		}

		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
		$type = $mybb->input['type'];

		if($pid <= 0 && $type == "f")
		{
			$errors[] = 'You must select a parent forum';
		}

		if(!$errors)
		{
			if($pid < 0)
			{
				$pid = 0;
			}
			$insert_array = array(
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"linkto" => $db->escape_string($mybb->input['linkto']),
				"type" => $db->escape_string($type),
				"pid" => $pid,
				"parentlist" => '',
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"active" => $mybb->get_input('active', MyBB::INPUT_INT),
				"open" => $mybb->get_input('open', MyBB::INPUT_INT),
				"usepostcounts" => $mybb->get_input('usepostcounts', MyBB::INPUT_INT),
				"usethreadcounts" => $mybb->get_input('usethreadcounts', MyBB::INPUT_INT),
				"requireprefix" => $mybb->get_input('requireprefix', MyBB::INPUT_INT),
				"password" => $db->escape_string($mybb->input['password']),
				"defaultdatecut" => $mybb->get_input('defaultdatecut', MyBB::INPUT_INT),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			);

			$plugins->run_hooks("admin_forum_management_add_start");

			$fid = $db->insert_query("tsf_forums", $insert_array);

			$parentlist = make_parent_list($fid);
			$db->update_query("tsf_forums", array("parentlist" => $parentlist), "fid='$fid'");

			$cache->update_forums();

			$inherit = $mybb->input['default_permissions'];

			foreach($mybb->input as $id => $permission)
			{
				if(strpos($id, 'fields_') === false)
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}

				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls','canpostattachments') as $name)
				{
					if(in_array($name, $permission) || !empty($permission[$name]))
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}
			}

			$canview = $permissions['canview'];
			$canpostthreads = $permissions['canpostthreads'];
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_add_commit");

			// Log admin action
			log_admin_action($fid, $insert_array['name']);

			flash_message($lang->forum_management['success_forum_added'], 'success');
			admin_redirect("index.php?act=management");
		}
	}

	$extra_header .=  "<script src=\"scripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	$page->add_breadcrumb_item('Add New Forum');
	
	//$page->output_header('add_forum');
	
	stdhead('Add New Forum');
	
	
	echo "		<div class=\"container mt-3\">\n";
	echo "		<div id=\"content\">\n";
	echo "			<div class=\"breadcrumb\">\n";
	echo $page->_generate_breadcrumb();
	echo "			</div>\n";
	echo "			</div>\n";
	
	
	
	
	
	
	echo $extra_header;
	
	
	  echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
	   echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/popup.js\"></script>\n";

		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
	
	
	

	
	
	
	
	
	
	output_nav_tabs($sub_tabs, 'Add New Forum');

	$form = new Form("index.php?act=management&action=add", "post");

	$forum_data['type'] = "f";
	$forum_data['title'] = "";
	$forum_data['description'] = "";

	if(empty($mybb->input['pid']))
	{
		$forum_data['pid'] = "-1";
	}
	else
	{
		$forum_data['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
	}
	$forum_data['disporder'] = "1";
	$forum_data['linkto'] = "";
	$forum_data['password'] = "";
	$forum_data['active'] = 1;
	$forum_data['open'] = 1;
	$forum_data['overridestyle'] = "";
	$forum_data['style'] = "";
	$forum_data['rulestype'] = "";
	$forum_data['rulestitle'] = "";
	$forum_data['rules'] = "";
	$forum_data['defaultdatecut'] = "";
	$forum_data['defaultsortby'] = "";
	$forum_data['defaultsortorder'] = "";
	$forum_data['allowhtml'] = "";
	$forum_data['allowmycode'] = 1;
	$forum_data['allowsmilies'] = 1;
	$forum_data['allowimgcode'] = 1;
	$forum_data['allowvideocode'] = 1;
	$forum_data['allowpicons'] = 1;
	$forum_data['allowtratings'] = 1;
	$forum_data['showinjump'] = 1;
	$forum_data['usepostcounts'] = 1;
	$forum_data['usethreadcounts'] = 1;
	$forum_data['requireprefix'] = 0;

	if($errors)
	{
		output_inline_error($errors);
		
		//inline_error($errors);
		

		foreach ($forum_data as $key => $value)
		{
			if (isset($mybb->input[$key]))
			{
				$forum_data[$key] = $mybb->input[$key];
			}
		}
	}

	$types = array(
		'f' => 'forum',
		'c' => 'category'
	);

	$create_a_options_f = array(
		'id' => 'forum'
	);

	$create_a_options_c = array(
		'id' => 'category'
	);

	if($forum_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}

	echo '<div class="container mt-3">';
	
	
	echo '<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">Add New Forum22</div>
    <div class="card-body">';
	
	
	
	
	echo '
	
	<tr class="first">
			<td class="first"><label>Forum Type</label>
<div class="description">Select the type of forum you are creating - a forum you can post in, or a category, which contains other forums</div>
<div class="form_row"><label for="forum"><input type="radio" name="type" value="f" class="form-check-input" id="forum" checked="checked" />Forum</label><br />
<label for="category"><input type="radio" name="type" value="c" class="form-check-input" id="category" />Category</label></div>
</td>
		</tr>
	
	
	';
	

		
		

echo '


<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="username">Title</label>
		<input type="text" class="form-control form-control-sm border" name="title"  value="" />
		</div>
		
	</div>
</div>




';
	
	
	echo '
	<tr>
	<td class="first"><label for="description">Description</label><textarea name="description" class="form-control form-control-sm border" id="description" rows="5" cols="45"></textarea></td>
	</tr>';
	
	
	
	$ff = generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => 'none'));
	

	echo '
	<tr>
			<td>Parent Forum <em>*</em></label>
<div>The Forum that contains this forum. Categories do not have a parent forum - in this case, select None - however, categories can be specified to have a parent forum.</div>
<div>


'.$ff.'



</div>
</td>
</tr>';
	
	
	
	
	echo '
	<tr>
			<td><label for="disporder">Display Order</label>
			<div class="form_row"><input type="number" name="disporder" value="1" min="0" class="form-control form-control-sm border" id="disporder" /></div></td>
	</tr>';

	echo '
	</div>
</div>
	';
	
	
	

	echo "<div id=\"additional_options_link\"><strong><a href=\"#\" onclick=\"$('#additional_options_link').toggle(); $('#additional_options').fadeToggle('fast'); return false;\">Show Additional Options</a></strong><br /><br /></div>";
	echo "<div id=\"additional_options\" style=\"display: none;\">";
	

		
	echo "
  

</br>
  <div class=\"card\">
    <div class=\"card-header rounded-bottom text-19 fw-bold\">
	
	<div class=\"float_right\" style=\"font-weight: normal;\">
	<a href=\"#\" onclick=\"$('#additional_options_link').toggle(); $('#additional_options').fadeToggle('fast'); return false;\">Hide Additional Options</a></div>Additional Forum Options
	
	
	</div>
    <div class=\"card-body\">";
	
	
		
	
	echo '
	
	
	<tr class="first">
			<td class="first"><label for="linkto">Forum Link</label>
<div class="description">To make a forum redirect to another location, enter the URL to the destination you wish to redirect to. Entering a URL in this field will remove the forum functionality; however, permissions can still be set for it.</div>
<div class="form_row">

<label>
<input type="text" name="linkto" value="" class="form-control" id="linkto" />
</label>

</div>
</td>


<tr class="alt_row">
			<td class="first"><label for="password">Forum Password</label>
<div class="description">To protect this forum further, you can choose a password that must be entered for access. Note: User groups still need permissions to access this forum.</div>
<div class="form_row">

<label>
<input type="text" name="password" value="" class="form-control" id="password" />
</label>


</div>
</td>
		</tr>
		
		
		
		
		<tr>
			<td class="first"><label>Access Options</label><div class="form_row"><div class="forum_settings_bit"><label for="active"><input type="checkbox" name="active" value="1" class="form-check-input" id="active" checked="checked" /> Forum is Active?<br />
<small>If unselected, this forum will not be shown to users and will not "exist".</small></label></div><div class="forum_settings_bit"><label for="open"><input type="checkbox" name="open" value="1" class="form-check-input" id="open" checked="checked" /> Forum is Open?<br />
<small>If unselected, users will not be able to post in this forum regardless of permissions.</small></label></div></div>
</td>
		</tr>
		
		
		
		
		
<tr class="alt_row">
			<td class="first"><label>Default View Options</label><div class="form_row"><div class="forum_settings_bit">Default Date Cut:<br />

<label>
<select class="form-select" name="defaultdatecut" id="defaultdatecut">
<option value="0">Board Default</option>
<option value="1">Last day</option>
<option value="5">Last 5 days</option>
<option value="10">Last 10 days</option>
<option value="20">Last 20 days</option>
<option value="50">Last 50 days</option>
<option value="75">Last 75 days</option>
<option value="100">Last 100 days</option>
<option value="365">Last year</option>
<option value="9999">The beginning</option>
</select>
</label>

</div><div class="forum_settings_bit">Default Sort By:<br />

<label>
<select class="form-select" name="defaultsortby" id="defaultsortby">
<option value="" selected="selected">Board Default</option>
<option value="subject">Thread subject</option>
<option value="lastpost">Last post time</option>
<option value="starter">Thread starter</option>
<option value="started">Thread creation time</option>
<option value="rating">Thread rating</option>
<option value="replies">Number of replies</option>
<option value="views">Number of views</option>
</select>
</label>

</div><div class="forum_settings_bit">Default Sort Order:<br />

<label>
<select class="form-select" name="defaultsortorder" id="defaultsortorder">
<option value="" selected="selected">Board Default</option>
<option value="asc">Ascending</option>
<option value="desc">Descending</option>
</select>
</label>

</div></div>
</td>
		</tr>
		
		
		
		
<tr class="last">
			<td class="first"><label>Miscellaneous Options</label>
			
			
			
			
			
			
			<div class="forum_settings_bit">
			<label for="usepostcounts">
			<input type="checkbox" name="usepostcounts" value="1" class="form-check-input" id="usepostcounts" checked="checked" /> Yes, posts in this forum should count towards user post counts</label>
			</div>
			
			<div class="forum_settings_bit">
			<label for="usethreadcounts">
			<input type="checkbox" name="usethreadcounts" value="1" class="form-check-input" id="usethreadcounts" checked="checked" /> Yes, threads in this forum should count towards user thread counts</label>
			</div>
		    
			<div class="forum_settings_bit">
			<label for="requireprefix">
			<input type="checkbox" name="requireprefix" value="1" class="form-check-input" id="requireprefix" /> Yes, require a thread prefix for all threads</label>
			</div>
			
			
</td>
		</tr>';			
		

	
	
	//$form_container->end();
	echo "</div></div></div>";

	$query = $db->simple_select("usergroups", "*", "", array("order" => "name"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroupsSS[$usergroup['gid']] = $usergroup;
	}

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => 'Can view?',
		'canpostthreads' => 'Can post threads?',
		'canpostreplys' => 'Can post replies?',
		'canpostpolls' => 'Can post polls?',
	);

	$field_list2 = array(
		'canview' => '&#149; View',
		'canpostthreads' => '&#149; Post Threads',
		'canpostreplys' => '&#149; Post Replies',
		'canpostpolls' => '&#149; Post Polls',
	);

	$ids = array();

	$form_container = new FormContainer('Permissions222222222');
	//$form_container->output_row_header('Group', array("class" => "align_center", 'style' => 'width: 40%'));
	//$form_container->output_row_header('Overview: Allowed Actions', array("class" => "align_center"));
	//$form_container->output_row_header('Overview: Disallowed Actions', array("class" => "align_center"));
	

    echo '
	
	
	
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  Permissions
	      </div>
	   </div>
	';


		
		echo '
	
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Group</th>
        <th>Overview: Allowed Actions</th>
        <th>Overview: Disallowed Actions</th>
     
      </tr>
    </thead>';
	
	
	
	
	
	
	
	

	if($mybb->request_method == "post")
	{
		foreach($usergroupsSS as $usergroup)
		{
			if(isset($mybb->input['fields_'.$usergroup['gid']]))
			{
				$input_permissions = $mybb->input['fields_'.$usergroup['gid']];
				if(!is_array($input_permissions))
				{
					// Convering the comma separated list from Javascript form into a variable
					$input_permissions = explode(',' , $input_permissions);
				}
				foreach($input_permissions as $input_permission)
				{
					$mybb->input['permissions'][$usergroup['gid']][$input_permission] = 1;
				}
			}
		}
	}

	foreach($usergroupsSS as $usergroup)
	{
		$perms = array();
		if(!empty($mybb->input['default_permissions'][$usergroup['gid']]))
		{
			if(isset($existing_permissions) && is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
			{
				$perms = $existing_permissions[$usergroup['gid']];
				$default_checked = false;
			}
			elseif(is_array($cached_forum_perms) && isset($forum_data['fid']) && !empty($cached_forum_perms[$forum_data['fid']][$usergroup['gid']]))
			{
				$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
				$default_checked = true;
			}
			else if(is_array($cached_forum_perms) && isset($forum_data['fid']) && !empty($cached_forum_perms[$forum_data['pid']][$usergroup['gid']]))
			{
				$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
				$default_checked = true;
			}
		}

		if(!$perms)
		{
			$perms = $usergroup;
			$default_checked = true;
		}

		foreach($field_list as $forum_permission => $forum_perm_title)
		{
			if(isset($mybb->input['permissions']))
			{
				if(!empty($mybb->input['default_permissions'][$usergroup['gid']]))
				{
					$default_checked = true;
				}
				else
				{
					$default_checked = false;
				}

				if(!empty($mybb->input['permissions'][$usergroup['gid']][$forum_permission]))
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
			else
			{
				if($perms[$forum_permission] == 1)
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
		}
		$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

		if($default_checked)
		{
			$inherited_text = 'inherited';
		}
		else
		{
			$inherited_text = 'Use custom permissions (below)';
		}

		echo "
		<tr>
		<td><strong>{$usergroup['title']}</strong><br />".generate_check_box("default_permissions[{$usergroup['gid']}]", 1, "", array("id" => "default_permissions_{$usergroup['gid']}", "checked" => $default_checked))." <small><label for=\"default_permissions_{$usergroup['gid']}\">Use Group Default</label></small></td>";









		$field_select = "
		
		<div class=\"quick_perm_fields\">\n";
		$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 1)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
		
		foreach($perms_checked as $perm => $value)
		{
			if($value == 0)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div></div>\n";
		$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_'.$usergroup['gid']));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);

		$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n";

		$field_selected = array();
		foreach($field_list as $forum_permission => $permission_title)
		{
			$field_options[$forum_permission] = $permission_title;
			if($perms_checked[$forum_permission])
			{
				$field_selected[] = $forum_permission;
			}
		}

		$field_select .= "<noscript>".generate_select_box('fields_'.$usergroup['gid'].'[]', $field_options, $field_selected, array('id' => 'fields_'.$usergroup['gid'].'[]', 'multiple' => true))."</noscript>\n";
		
		echo '<td>'.$field_select.'</td>';

		//$form_container->construct_row();

		$ids[] = $usergroup['gid'];
	}
	
	
	echo '</tr></table></div></div>';


	
	echo '<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Save Forum" class="btn btn-primary"> 
</td></tr>
</div>



';
	
	
	
	
	$form->end();

	// Write in our JS based field selector
	echo "<script type=\"text/javascript\">\n<!--\n";
	foreach($ids as $id)
	{
		echo "$(function() { QuickPermEditor.init(".$id.") });\n";
	}
	echo "// -->\n</script>\n";

	stdfoot();
}

if($mybb->input['action'] == "edit")
{
	if(!$mybb->input['fid'])
	{
		flash_message($lang->forum_management['error_invalid_fid'], 'error');
		admin_redirect("index.php?act=management");
	}

	$query = $db->simple_select("tsf_forums", "*", "fid='{$mybb->input['fid']}'");
	$forum_data = $db->fetch_array($query);
	if(!$forum_data)
	{
		flash_message($lang->forum_management['error_invalid_fid'], 'error');
		admin_redirect("index.php?act=management");
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$plugins->run_hooks("admin_forum_management_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = 'You must enter in a title';
		}

		$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

		if($pid == $mybb->input['fid'])
		{
			$errors[] = 'The forum parent cannot be the forum itself';
		}
		else
		{
			$query = $db->simple_select('tsf_forums', 'parentlist', "fid='{$pid}'");
			$parents = explode(',', $db->fetch_field($query, 'parentlist'));
			if(in_array($mybb->input['fid'], $parents))
			{
				$errors[] = 'You cant set the parent forum of this forum to one of its children';
			}
		}

		$type = $mybb->input['type'];

		if($pid <= 0 && $type == "f")
		{
			$errors[] = 'You must select a parent forum';
		}

		if($type == 'c' && $forum_data['type'] == 'f')
		{
			$query = $db->simple_select('tsf_threads', 'COUNT(tid) as num_threads', "fid = '{$fid}'");
			if($db->fetch_field($query, "num_threads") > 0)
			{
				$errors[] = 'Forums with threads cannot be converted to categories';
			}
		}

		if(!empty($mybb->input['linkto']) && empty($forum_data['linkto']))
		{
			$query = $db->simple_select('tsf_threads', 'COUNT(tid) as num_threads', "fid = '{$fid}'", array("limit" => 1));
			if($db->fetch_field($query, "num_threads") > 0)
			{
				$errors[] = 'Forums with threads cannot be redirected to another webpage';
			}
		}

		if(!$errors) {
			if ($pid < 0) {
				$pid = 0;
			}
			$update_array = array(
				"name" => $db->escape_string($mybb->input['title']),
				"description" => $db->escape_string($mybb->input['description']),
				"linkto" => $db->escape_string($mybb->input['linkto']),
				"type" => $db->escape_string($type),
				"pid" => $pid,
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"active" => $mybb->get_input('active', MyBB::INPUT_INT),
				"open" => $mybb->get_input('open', MyBB::INPUT_INT),
				"usepostcounts" => $mybb->get_input('usepostcounts', MyBB::INPUT_INT),
				"usethreadcounts" => $mybb->get_input('usethreadcounts', MyBB::INPUT_INT),
				"requireprefix" => $mybb->get_input('requireprefix', MyBB::INPUT_INT),
				"password" => $db->escape_string($mybb->input['password']),
				"defaultdatecut" => $mybb->get_input('defaultdatecut', MyBB::INPUT_INT),
				"defaultsortby" => $db->escape_string($mybb->input['defaultsortby']),
				"defaultsortorder" => $db->escape_string($mybb->input['defaultsortorder']),
			
			);
			$db->update_query("tsf_forums", $update_array, "fid='{$fid}'");
			if ($pid != $forum_data['pid']) {
				// Update the parentlist of this forum.
				$db->update_query("tsf_forums", array("parentlist" => make_parent_list($fid)), "fid='{$fid}'");

				// Rebuild the parentlist of all of the subforums of this forum
				switch ($db->type) {
					case "sqlite":
					case "pgsql":
						$query = $db->simple_select("tsf_forums", "fid", "','||parentlist||',' LIKE '%,$fid,%'");
						break;
					default:
						$query = $db->simple_select("tsf_forums", "fid", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
				}

				while ($child = $db->fetch_array($query)) {
					$db->update_query("tsf_forums", array("parentlist" => make_parent_list($child['fid'])), "fid='{$child['fid']}'");
				}
			}

			if(!empty($mybb->input['default_permissions']))
			{
				$inherit = $mybb->input['default_permissions'];
			}
			else
			{
				$inherit = array();
			}

			foreach($mybb->input as $id => $permission)
			{
				// Make sure we're only skipping inputs that don't start with "fields_" and aren't fields_default_ or fields_inherit_
				if(strpos($id, 'fields_') === false || (strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false))
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				if($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1)
				{
					$inherit[$gid] = 1;
					continue;
				}
				$inherit[$gid] = 0;

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}

				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls') as $name)
				{
					if(in_array($name, $permission) || !empty($permission[$name]))
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}
			}

			$cache->update_forums();

			if(isset($permissions['canview']))
			{
				$canview = $permissions['canview'];
			}
			if(isset($permissions['canpostthreads']))
			{
				$canpostthreads = $permissions['canpostthreads'];
			}
			if(isset($permissions['canpostpolls']))
			{
				$canpostpolls = $permissions['canpostpolls'];
			}
			if(isset($permissions['canpostattachments']))
			{
				$canpostattachments = $permissions['canpostattachments'];
			}
			if(isset($permissions['canpostreplys']))
			{
				$canpostreplies = $permissions['canpostreplys'];
			}

			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_edit_commit");

			// Log admin action
			log_admin_action($fid, $mybb->input['title']);

			flash_message('The forum settings have been updated successfully', 'success');
			admin_redirect("index.php?act=management&fid={$fid}");
		}
	}

	$extra_header .=  "<script src=\"scripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	$page->add_breadcrumb_item('Edit Forum');
	
	//$page->output_header('edit_forum');
	

	
	stdhead('Edit Forum');
	
	
	
	echo "		<div class=\"container mt-3\">\n";
	echo "		<div id=\"content\">\n";
	echo "			<div class=\"breadcrumb\">\n";
	echo $page->_generate_breadcrumb();
	echo "			</div>\n";
	echo "			</div>\n";
	
	
	
	
	
	
	echo $extra_header;
	
	  echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
	 echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/popup.js\"></script>\n";

		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
	
	
	
	
	
   output_nav_tabs($sub_tabs, 'Edit Forum Settings88888888');
	


	$form = new Form("index.php?act=management&action=edit", "post");
	echo $form->generate_hidden_field("fid", $fid);

	if($errors)
	{
		output_inline_error($errors);
		$forum_data = $mybb->input;
	}
	else
	{
		$forum_data['title'] = $forum_data['name'];
	}

	$query = $db->simple_select("usergroups", "*", "", array("order_dir" => "name"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroupsSSS[$usergroup['gid']] = $usergroup;
	}

	$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
	while($existing = $db->fetch_array($query))
	{
		$existing_permissions[$existing['gid']] = $existing;
	}

	$types = array(
		'f' => 'forum',
		'c' => 'category'
	);

	$create_a_options_f = array(
		'id' => 'forum'
	);

	$create_a_options_c = array(
		'id' => 'category'
	);

	if($forum_data['type'] == "f")
	{
		$create_a_options_f['checked'] = true;
	}
	else
	{
		$create_a_options_c['checked'] = true;
	}

	echo '<div class="container mt-3">';
	
	
	
	
	
	
	echo '<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">Edit ForumVVVVVV</div>
    <div class="card-body">';
	
	
	echo '
	
	<tr class="first">
			<td class="first"><label>Forum Type</label>
<div class="description">Select the type of forum you are creating - a forum you can post in, or a category, which contains other forums</div>

<div class="form_row">


'.generate_radio_button('type', 'f', 'Forum', $create_a_options_f)."<br />\n".generate_radio_button('type', 'c', 'Category', $create_a_options_c).'

</div>
</td>
		</tr>
	
	
	';
	
	
	
	
	//$form_container->output_row('Forum Type', 'Select the type of forum you are creating - a forum you can post in,
	///or a category, which contains other forums', 
	//$form->generate_radio_button('type', 'f', 'Forum', $create_a_options_f)."<br />\n".$form->generate_radio_button('type', 'c', 'Category', $create_a_options_c));
	
	//$form_container->output_row('Title222222222aaaaaaa'." <em>*</em>", "", $form->generate_text_box('title', $forum_data['title'], array('id' => 'title')), 'title');
	
	
	echo '


<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="username">Title</label>
		<input type="text" class="form-control form-control-sm border" name="title"  value="'.$forum_data['title'].'" />
		</div>
		
	</div>
</div>';
	
	
	
	
	echo '
	<tr>
	<td class="first"><label for="description">Description</label><textarea name="description" class="form-control form-control-sm border" id="description" rows="5" cols="45">'.$forum_data['description'].'</textarea></td>
	</tr>';
	
	
	
	$fff = generate_forum_select('pid', $forum_data['pid'], array('id' => 'pid', 'main_option' => 'none'));
	

	echo '
	<tr>
			<td>Parent Forum <em>*</em></label>
<div>The Forum that contains this forum. Categories do not have a parent forum - in this case, select None - however, categories can be specified to have a parent forum.</div>
<div>


'.$fff.'



</div>
</td>
</tr>';
	
	
	
	echo '
	<tr>
			<td><label for="disporder">Display Order</label>
			<div class="form_row"><input type="number" name="disporder" value="'.$forum_data['disporder'].'" min="0" class="form-control form-control-sm border" id="disporder" /></div></td>
	</tr>';

	echo '
	</div>
</div>
	';
	
echo '</br>';
	

	
	
	
	
	
	
	
	$form_container = new FormContainer('Additional Forum Options66666666666678677');
	
	
	
	$default_date_cut = array(
		0 => 'Board Default',
		1 => 'Last day',
		5 => 'Last 5 days',
		10 => 'Last 10 days',
		20 => 'Last 20 days',
		50 => 'Last 50 days',
		75 => 'Last 75 days',
		100 => 'Last 100 days',
		365 => 'Last year',
		9999 => 'The beginning',
	);

	$default_sort_by = array(
		"" => 'Board Default',
		"subject" => 'Thread subject',
		"lastpost" => 'Last post time',
		"starter" => 'Thread starter',
		"started" => 'Thread creation time',
		"rating" => 'Thread rating',
		"replies" => 'Number of replies',
		"views" => 'Number of views',
	);

	$default_sort_order = array(
		"" => 'Board Default',
		"asc" => 'Ascending',
		"desc" => 'Descending',
	);
	
	
	
	
	echo '<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">Additional Forum Options</div>
    <div class="card-body">';
	
	
	echo '
	
	
	<tr class="first">
			<td class="first"><label for="linkto">Forum Link</label>
<div class="description">To make a forum redirect to another location, enter the URL to the destination you wish to redirect to. Entering a URL in this field will remove the forum functionality; however, permissions can still be set for it.</div>
<div class="form_row">

<label>
'.generate_text_box('linkto', $forum_data['linkto'], array('id' => 'linkto')).'
</label>

</div>
</td>


<tr class="alt_row">
			<td class="first"><label for="password">Forum Password</label>
<div class="description">To protect this forum further, you can choose a password that must be entered for access. Note: User groups still need permissions to access this forum.</div>
<div class="form_row">

<label>
'.generate_text_box('password', $forum_data['password'], array('id' => 'password')).'
</label>


</div>
</td>
		</tr>
		
		
		
	<tr>
			<td class="first"><label>Access Options</label>
			<div class="form_row">
			<div class="forum_settings_bit">
			
			<label for="active">
			
			'.generate_check_box('active', 1, ' Forum is Active?'."<br />\n<small>If unselected, this forum will not be shown to users and will not exist.</small>", array('checked' => $forum_data['active'], 'id' => 'active')).'
			
            </label>
			</div>
			
			<div class="forum_settings_bit">
			<label for="open">
			
			'.generate_check_box('open', 1, ' Forum is Open?'."<br />\n<small>If unselected, users will not be able to post in this forum regardless of permissions.</small>", array('checked' => $forum_data['open'], 'id' => 'open')).'


</label></div></div>
</td>
		</tr>	
		
		
		
		
		
<tr class="alt_row">
			<td class="first"><label>Default View Options</label>
			
			<div class="form_row"><div class="forum_settings_bit">Default Date Cut:<br />

<label>


'.generate_select_box('defaultdatecut', $default_date_cut, $forum_data['defaultdatecut'], array('checked' => $forum_data['defaultdatecut'], 'id' => 'defaultdatecut')).'

</label>

</div><div class="forum_settings_bit">Default Sort By:<br />

<label>


'.generate_select_box('defaultsortby', $default_sort_by, $forum_data['defaultsortby'], array('checked' => $forum_data['defaultsortby'], 'id' => 'defaultsortby')).'

</label>

</div><div class="forum_settings_bit">Default Sort Order:<br />

<label>

'.generate_select_box('defaultsortorder', $default_sort_order, $forum_data['defaultsortorder'], array('checked' => $forum_data['defaultsortorder'], 'id' => 'defaultsortorder')).'


</label>

</div></div>
</td>
		</tr>
		
		
		
		
<tr class="last">
			<td class="first"><label>Miscellaneous Options</label>
			
			
			
			
			
			
			<div class="forum_settings_bit">
			<label for="usepostcounts">
			'.generate_check_box('usepostcounts', 1, 'Yes, posts in this forum should count towards user post counts', array('checked' => $forum_data['usepostcounts'], 'id' => 'usepostcounts')).'
			</div>
			
			<div class="forum_settings_bit">
			<label for="usethreadcounts">
			'.generate_check_box('usethreadcounts', 1,'Yes, threads in this forum should count towards user thread counts', array('checked' => $forum_data['usethreadcounts'], 'id' => 'usethreadcounts')).'
			</div>
		    
			<div class="forum_settings_bit">
			<label for="requireprefix">
			'.generate_check_box('requireprefix', 1, 'Yes, require a thread prefix for all threads', array('checked' => $forum_data['requireprefix'], 'id' => 'requireprefix')).'
			</div>
			
			
</td>
		</tr>';	
		
		
		echo "</div></div>";
		
		


	
	//$form_container->end();
	
	
	echo "</form>";
	
	echo "</br>";

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => 'Can view?',
		'canpostthreads' => 'Can post threads?',
		'canpostreplys' => 'Can post replies?',
		'canpostpolls' => 'Can post polls?',
	);

	$field_list2 = array(
		'canview' => '&#149; View',
		'canpostthreads' => '&#149; Post Threads',
		'canpostreplys' => '&#149; Post Replies',
		'canpostpolls' => '&#149; Post Polls',
	);

	$ids = array();

	//$form_container = new FormContainer(sprintf('Forum22222222 Permissions in '.$forum_data['name'].''));
	//$form_container->output_row_header('Group', array("class" => "align_center", 'style' => 'width: 30%'));
	//$form_container->output_row_header('Overview: Allowed Actions', array("class" => "align_center"));
	//$form_container->output_row_header('Overview: Disallowed Actions', array("class" => "align_center"));
	//$form_container->output_row_header('Controls', array("class" => "align_center", 'style' => 'width: 120px', 'colspan' => 2));
	
	
	
	
	
	echo '
	
	
	
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  Forum Permissions in '.$forum_data['name'].'
	      </div>
	   </div>
	';


		
		echo '
	
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Group</th>
        <th>Overview: Allowed Actions</th>
        <th>Overview: Disallowed Actions</th>
		<th>Controls</th>
     
      </tr>
    </thead>';
	
	
	
	
	
	

	if($mybb->request_method == "post")
	{
		foreach($usergroupsSSS as $usergroup)
		{
			if(isset($mybb->input['fields_'.$usergroup['gid']]))
			{
				$input_permissions = $mybb->input['fields_'.$usergroup['gid']];
				if(!is_array($input_permissions))
				{
					// Convering the comma separated list from Javascript form into a variable
					$input_permissions = explode(',' , $input_permissions);
				}
				foreach($input_permissions as $input_permission)
				{
					$mybb->input['permissions'][$usergroup['gid']][$input_permission] = 1;
				}
			}
		}
	}

	foreach($usergroupsSSS as $usergroup)
	{
		$perms = array();
		if(isset($mybb->input['default_permissions']))
		{
			if($mybb->input['default_permissions'][$usergroup['gid']])
			{
				if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
				{
					$perms = $existing_permissions[$usergroup['gid']];
					$default_checked = false;
				}
				elseif(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
					$default_checked = true;
				}
				else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
					$default_checked = true;
				}
			}

			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}
		else
		{
			if(isset($existing_permissions) && is_array($existing_permissions) && !empty($existing_permissions[$usergroup['gid']]))
			{
				$perms = $existing_permissions[$usergroup['gid']];
				$default_checked = false;
			}
			elseif(is_array($cached_forum_perms) && !empty($cached_forum_perms[$forum_data['fid']][$usergroup['gid']]))
			{
				$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
				$default_checked = true;
			}
			else if(is_array($cached_forum_perms) && !empty($cached_forum_perms[$forum_data['pid']][$usergroup['gid']]))
			{
				$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
				$default_checked = true;
			}

			if(!$perms)
			{
				$perms = $usergroup;
				$default_checked = true;
			}
		}

		foreach($field_list as $forum_permission => $forum_perm_title)
		{
			if(isset($mybb->input['permissions']))
			{
				if($mybb->input['permissions'][$usergroup['gid']][$forum_permission])
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
			else
			{
				if($perms[$forum_permission] == 1)
				{
					$perms_checked[$forum_permission] = 1;
				}
				else
				{
					$perms_checked[$forum_permission] = 0;
				}
			}
		}
		$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

		if($default_checked)
		{
			$inherited_text = 'inherited';
		}
		else
		{
			$inherited_text = 'custom';
		}

		//$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");
		
		echo '
			<tr>
			<td><strong>'.$usergroup['title'].'</strong> <small>('.$inherited_text.')</small></td>
			
			';
		
		

		$field_select = "<div class=\"quick_perm_fields\">\n";
		$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 1)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
		foreach($perms_checked as $perm => $value)
		{
			if($value == 0)
			{
				$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
			}
		}
		//$field_select .= "</ul></div></div>\n";
		$field_select .= "</ul></div></div></td>\n";
		$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_'.$usergroup['gid']));
		$field_select .= $form->generate_hidden_field("fields_inherit_".$usergroup['gid'], (int)$default_checked, array('id' => 'fields_inherit_'.$usergroup['gid']));
		$field_select .= $form->generate_hidden_field("fields_default_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_default_'.$usergroup['gid']));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);

		$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n";

		$field_selected = array();
		foreach($field_list as $forum_permission => $permission_title)
		{
			$field_options[$forum_permission] = $permission_title;
			if($perms_checked[$forum_permission])
			{
				$field_selected[] = $forum_permission;
			}
		}

		$field_select .= "<noscript>".$form->generate_select_box('fields_'.$usergroup['gid'].'[]', $field_options, $field_selected, array('id' => 'fields_'.$usergroup['gid'].'[]', 'multiple' => true))."</noscript>\n";
		//$form_container->output_cell($field_select, array('colspan' => 2));
		
		echo '<td>'.$field_select.'</td>';

		if(!$default_checked)
		{
			
			
			echo "<td align=right>
			<a href=\"index.php?act=management&action=permissions&amp;pid={$perms['pid']}\" onclick=\"popupWindow('index.php?act=management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">Edit Permissions3333333</a></td>";
			
			
			echo "<td align=right><a href=\"index.php?act=management&action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{confirm_clear_custom_permission}')\">Clear Custom Permissions</a></td>";
		}
		else
		{
			
			echo "
			<td align=right>
			<a href=\"index.php?act=management&action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\" onclick=\"popupWindow('index.php?act=management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">Set Custom Permissions</a>
			</td>
			</tr>";
		}

		$form_container->construct_row(array('id' => 'row_'.$usergroup['gid']));

		$ids[] = $usergroup['gid'];
	}
	//$form_container->end();
	
	//echo "</form>";

	//$buttons[] = $form->generate_submit_button('Save Forum');
	//$form->output_submit_wrapper($buttons);
	
	
	echo '</table></div>';
	
	
	
	
	
	echo '<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Save Forum" class="btn btn-primary"> 
</td></tr>
</div>';
	
	
	echo "</form>";

	// Write in our JS based field selector
	echo "<script type=\"text/javascript\">\n<!--\n";
	foreach($ids as $id)
	{
		echo "$(function() { QuickPermEditor.init(".$id."); });\n";
	}
	echo "// -->\n</script>\n";

	stdfoot();
}

if($mybb->input['action'] == "deletemod")
{
	$modid = $mybb->get_input('id', MyBB::INPUT_INT);
	$isgroup = $mybb->get_input('isgroup', MyBB::INPUT_INT);
	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$query = $db->simple_select("moderators", "*", "id='{$modid}' AND isgroup = '{$isgroup}' AND fid='{$fid}'");
	$mod = $db->fetch_array($query);

	// Does the forum not exist?
	if(!$mod)
	{
		flash_message($lang->forum_management['error_invalid_moderator'], 'error');
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	// User clicked no
	if(!empty($mybb->input['no']))
	{
		admin_redirect("index.php?act=management&fid={$fid}");
	}

	$plugins->run_hooks("admin_forum_management_deletemod");

	if($mybb->request_method == "post")
	{
		$mid = $mod['mid'];
		if($mybb->input['isgroup'])
		{
			$query = $db->sql_query("
				SELECT m.*, g.title
				FROM moderators m
				LEFT JOIN usergroups g ON (g.gid=m.id)
				WHERE m.mid='{$mid}'
			");
		}
		else
		{
			$query = $db->sql_query("
				SELECT m.*, u.username, u.usergroup
				FROM moderators m
				LEFT JOIN users u ON (u.id=m.id)
				WHERE m.mid='{$mid}'
			");
		}
		$mod = $db->fetch_array($query);

		$db->delete_query("moderators", "mid='{$mid}'");

		$plugins->run_hooks("admin_forum_management_deletemod_commit");

		$cache->update_moderators();

		$forum = get_forum($fid, 1);

		// Log admin action
		if($isgroup)
		{
			log_admin_action($mid, $mod['title'], $forum['fid'], $forum['name']);
		}
		else
		{
			log_admin_action($mid, $mod['username'], $forum['fid'], $forum['name']);
		}

		flash_message($lang->forum_management['success_moderator_deleted'], 'success');
		admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
	}
	else
	{
		$page->output_confirm_action("index.php?act=management&action=deletemod&amp;fid={$mod['fid']}&amp;uid={$mod['uid']}", $lang->forum_management['confirm_moderator_deletion']);
	}
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("tsf_forums", "*", "fid='{$mybb->input['fid']}'");
	$forum = $db->fetch_array($query);

	// Does the forum not exist?
	if(!$forum)
	{
		flash_message('Please select a valid forum', 'error');
		admin_redirect("index.php?act=management");
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?act=management");
	}

	$plugins->run_hooks("admin_forum_management_delete");

	if($mybb->request_method == "post")
	{
		$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
		$forum = get_forum($fid, 1);

		$delquery = "";
		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("tsf_forums", "*", "','|| parentlist|| ',' LIKE '%,$fid,%'");
				break;
			default:
				$query = $db->simple_select("tsf_forums", "*", "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'");
		}
		while($forum = $db->fetch_array($query))
		{
			$fids[$forum['fid']] = $fid;
			$delquery .= " OR fid='{$forum['fid']}'";
		}

		require_once INC_PATH.'/class_moderation.php';
		$moderation = new Moderation();

		// Start pagination. Limit results to 50
		$query = $db->simple_select("tsf_threads", "tid", "fid='{$fid}' {$delquery}", array("limit" => 50));

		while($tid = $db->fetch_field($query, 'tid'))
		{
			$moderation->delete_thread($tid);
		}

		// Check whether all threads have been deleted
		$query = $db->simple_select("tsf_threads", "tid", "fid='{$fid}' {$delquery}");

		if($db->num_rows($query) > 0)
		{
			//$page->output_header();
			
			
			stdhead('copy_forum22222');
	
	 echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";

		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
			
			
			
			
			
			
			

			$form = new Form("index.php?act=management", 'post');

			echo $form->generate_hidden_field("fid", $fid);
			echo $form->generate_hidden_field("action", "delete");
			//output_auto_redirect($form, 'confirm_proceed_deletion');

			$form->end();

			stdfoot();
			exit;
		}

		// End pagination

		// Delete the forum
		$db->delete_query("tsf_forums", "fid='$fid'");

		// Delete subforums
		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$db->delete_query("tsf_forums", "','||parentlist||',' LIKE '%,$fid,%'");
				break;
			default:
				$db->delete_query("tsf_forums", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
		}

		//$db->delete_query('moderators', "fid='{$fid}' {$delquery}");
		$db->delete_query('tsf_forumsubscriptions', "fid='{$fid}' {$delquery}");
		$db->delete_query('forumpermissions', "fid='{$fid}' {$delquery}");
		$db->delete_query('tsf_announcements', "fid='{$fid}' {$delquery}");
		$db->delete_query('tsf_forumsread', "fid='{$fid}' {$delquery}");

		$plugins->run_hooks("admin_forum_management_delete_commit");

		$cache->update_forums();
		$cache->update_moderators();
		$cache->update_forumpermissions();
		$cache->update_forumsdisplay();

		// Log admin action
		log_admin_action($forum_info['fid'], $forum_info['name']);

		flash_message($lang->forum_management['success_forum_deleted'], 'success');
	    admin_redirect("management.php");
		echo "The selected forum has been deleted successfully";
		
	}
	else
	{
		$page->output_confirm_action("index.php?act=management&action=delete&amp;fid={$forum['fid']}", 'confirm_forum_deletion2');
	}
}

if(!$mybb->input['action'])
{
	if(!isset($mybb->input['fid']))
	{
		$mybb->input['fid'] = 0;
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	if($fid)
	{
		$forum = get_forum($fid, 1);
	}

	$plugins->run_hooks("admin_forum_management_start");

	if($mybb->request_method == "post")
	{
		if($mybb->get_input('update') == "permissions")
		{
			$inherit = array();
			foreach($mybb->input as $id => $permission)
			{
				// Make sure we're only skipping inputs that don't start with "fields_" and aren't fields_default_ or fields_inherit_
				if(strpos($id, 'fields_') === false || (strpos($id, 'fields_default_') !== false || strpos($id, 'fields_inherit_') !== false))
				{
					continue;
				}

				list(, $gid) = explode('fields_', $id);

				if($mybb->input['fields_default_'.$gid] == $permission && $mybb->input['fields_inherit_'.$gid] == 1)
				{
					$inherit[$gid] = 1;
					continue;
				}
				$inherit[$gid] = 0;

				// If it isn't an array then it came from the javascript form
				if(!is_array($permission))
				{
					$permission = explode(',', $permission);
					$permission = array_flip($permission);
					foreach($permission as $name => $value)
					{
						$permission[$name] = 1;
					}
				}
				foreach(array('canview','canpostthreads','canpostreplys','canpostpolls') as $name)
				{
					if(!empty($permission[$name]))
					{
						$permissions[$name][$gid] = 1;
					}
					else
					{
						$permissions[$name][$gid] = 0;
					}
				}
			}

			if(isset($permissions['canview']))
			{
				$canview = $permissions['canview'];
			}
			if(isset($permissions['canpostthreads']))
			{
				$canpostthreads = $permissions['canpostthreads'];
			}
			if(isset($permissions['canpostpolls']))
			{
				$canpostpolls = $permissions['canpostpolls'];
			}
			if(isset($permissions['canpostattachments']))
			{
				$canpostattachments = $permissions['canpostattachments'];
			}
			if(isset($permissions['canpostreplys']))
			{
				$canpostreplies = $permissions['canpostreplys'];
			}

			save_quick_perms($fid);

			$plugins->run_hooks("admin_forum_management_start_permissions_commit");

			$cache->update_forums();

			// Log admin action
			log_admin_action('quickpermissions', $fid, $forum['name']);

			flash_message($lang->forum_management['success_forum_permissions_updated'], 'success');
			admin_redirect("index.php?act=management&fid={$fid}#tab_permissions");
		}
		elseif($mybb->get_input('add') == "moderators")
		{
			$forum = get_forum($fid, 1);
			if(!$forum)
			{
				flash_message($lang->forum_management['error_invalid_forum'], 'error');
				admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
			}
			if(!empty($mybb->input['usergroup']))
			{
				$isgroup = 1;
				$gid = $mybb->get_input('usergroup', MyBB::INPUT_INT);

				if(!$groupscache[$gid])
 				{
 					// Didn't select a valid moderator
 					flash_message($lang->forum_management['error_moderator_not_found'], 'error');
 					admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
 				}

				$newmod = array(
					"id" => $gid,
					"name" => $groupscache[$gid]['title']
				);
			}
			else
			{
				$options = array(
					'fields' => array('id AS id', 'username AS name', 'usergroup', 'additionalgroups')
				);
				$newmod = $newmoduser = get_user_by_username($mybb->input['username'], $options);

				if(empty($newmod['id']))
				{
					flash_message($lang->forum_management['error_moderator_not_found'], 'error');
					admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
				}

				$isgroup = 0;
			}

			if($newmod['id'])
			{
				$query = $db->simple_select("moderators", "id", "id='".$newmod['id']."' AND fid='".$fid."' AND isgroup='{$isgroup}'", array('limit' => 1));

				if(!$db->num_rows($query))
				{
					$new_mod = array(
						"fid" => $fid,
						"id" => $newmod['id'],
						"isgroup" => $isgroup,
						"caneditposts" => 1,
						"cansoftdeleteposts" => 1,
						"canrestoreposts" => 1,
						"candeleteposts" => 1,
						"cansoftdeletethreads" => 1,
						"canrestorethreads" => 1,
						"candeletethreads" => 1,
						"canviewips" => 1,
						"canviewunapprove" => 1,
						"canviewdeleted" => 1,
						"canopenclosethreads" => 1,
						"canstickunstickthreads" => 1,
						"canapproveunapprovethreads" => 1,
						"canapproveunapproveposts" => 1,
						"canapproveunapproveattachs" => 1,
						"canmanagethreads" => 1,
						"canmanagepolls" => 1,
						"canpostclosedthreads" => 1,
						"canmovetononmodforum" => 1,
						"canusecustomtools" => 1,
						"canmanageannouncements" => 1,
						"canmanagereportedposts" => 1,
						"canviewmodlog" => 1
					);

					$mid = $db->insert_query("moderators", $new_mod);

					if(!$isgroup)
					{
						$newmodgroups = $newmoduser['usergroup'];
						if(!empty($newmoduser['additionalgroups']))
						{
							$newmodgroups .= ','.$newmoduser['additionalgroups'];
						}
						$groupperms = usergroup_permissions($newmodgroups);

						// Check if new moderator already belongs to a moderators group
						if($groupperms['canmodcp'] != 1)
						{
							if($newmoduser['usergroup'] == 2 || $newmoduser['usergroup'] == 5)
							{
								// Primary group is default registered or awaiting activation group so change primary group to Moderators
								$db->update_query("users", array('usergroup' => 6), "id='{$newmoduser['id']}'");
							}
							else
							{
								// Primary group is another usergroup without canmodcp so add Moderators to additional groups
								join_usergroup($newmoduser['id'], 6);
							}
						}
					}

					$plugins->run_hooks("admin_forum_management_start_moderators_commit");

					$cache->update_moderators();

					// Log admin action
					log_admin_action('addmod', $mid, $newmod['name'], $fid, $forum['name']);

					flash_message($lang->forum_management['success_moderator_added'], 'success');
					admin_redirect("index.php?act=management&action=editmod&mid={$mid}");
				}
				else
				{
					flash_message($lang->forum_management['error_moderator_already_added'], 'error');
					admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
				}
			}
			else
			{
				flash_message($lang->forum_management['error_moderator_not_found'], 'error');
				admin_redirect("index.php?act=management&fid={$fid}#tab_moderators");
			}
		}
		else
		{
			if(!empty($mybb->input['disporder']) && is_array($mybb->input['disporder']))
			{
				foreach($mybb->input['disporder'] as $update_fid => $order)
				{
					$db->update_query("tsf_forums", array('disporder' => (int)$order), "fid='".(int)$update_fid."'");
				}

				$plugins->run_hooks("admin_forum_management_start_disporder_commit");

				$cache->update_forums();

				// Log admin action
				if(!empty($forum))
				{
					log_admin_action('orders', $forum['fid'], $forum['name']);
				}
				else
				{
					log_admin_action('orders', 0);
				}

				flash_message($lang->forum_management['success_forum_disporder_updated'], 'success');
				admin_redirect("index.php?act=management&fid=".$mybb->input['fid']);
			}
		}
	}

	$extra_header .=  "<script src=\"scripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

	if($fid)
	{
		$page->add_breadcrumb_item('View Forum', "index.php?act=management");
    }


	//$page->output_header('forum_management');
	
	stdhead('Forum Management222222222222aaaaaaa');
	
	
	
	echo "		<div class=\"container mt-3\">\n";
	echo "		<div id=\"content\">\n";
	echo "			<div class=\"breadcrumb\">\n";
	echo $page->_generate_breadcrumb();
	echo "			</div>\n";
	echo "			</div>\n";
	
	
	
	
	
	echo $extra_header;
	
	
	    echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/main.css?ver=1813\" type=\"text/css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/modal.css?ver=1813\" type=\"text/css\" />\n";
	    echo "	<script type=\"text/javascript\" src=\"scripts/admincp.js?ver=1821\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/tabs.js\"></script>\n";
		echo "	<script type=\"text/javascript\" src=\"scripts/popup.js\"></script>\n";

		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.structure.min.css\" />\n";
		echo "	<link rel=\"stylesheet\" href=\"templates/css/redmond/jquery-ui.theme.min.css\" />\n";
		echo "	<script src=\"scripts/jquery-ui.min.js?ver=1813\"></script>\n";

		// Stop JS elements showing while page is loading (JS supported browsers only)
		echo "  <style type=\"text/css\">.popup_button { display: none; } </style>\n";
		echo "  <script type=\"text/javascript\">\n".
				"//<![CDATA[\n".
				"	document.write('<style type=\"text/css\">.popup_button { display: inline; } .popup_menu { display: none; }<\/style>');\n".
                "//]]>\n".
                "</script>\n";
	
	
	
	
	

	if($fid)
	{
	
		output_nav_tabs($sub_tabs, 'View Forum');
		
	}
	else
	{
		output_nav_tabs($sub_tabs, 'Forum Management');
		
	}

	$form = new Form("index.php?act=management", "post", "management");
	echo $form->generate_hidden_field("fid", $mybb->input['fid']);
	

	
	
	
	

	if($fid)
	{
		$tabs = array(
			'subforums' => 'Sub Forums',
			'permissions' => 'Permissions',
			'moderators' => 'Moderators',
		);
		$tabs = $plugins->run_hooks("admin_forum_management_start_graph_tabs", $tabs);
		
		$page->output_tab_control($tabs);
		
		

		echo "<div id=\"tab_subforums\">\n";
		if(!isset($forum_cache) || !is_array($forum_cache))
		{
			cache_forums();
		}
		$form_container = new FormContainer(sprintf('Forums in '.$forum_cache[$fid]['name'].''));
		
		
		$manage_forums = '<div class="container mt-3">
            <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     '.sprintf('Forums in '.$forum_cache[$fid]['name'].'').'
	      </div>
	       </div>
	    </div>';
		
		
	}
	else
	{
		
		echo '<div class="container mt-3">';
		
		$form_container = new FormContainer('Manage Forums');
		
		
		$manage_forums = '<div class="container mt-3">
            <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     Manage Forums
	      </div>
	       </div>
	    </div>';
		
		
		
		
	}
	
	
	
	echo $manage_forums;
	
	
	echo '<div class="container mt-3">

  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Forum</th>
        <th>Order</th>
        <th>Controls</th>
      </tr>
    </thead>';
	

	build_admincp_forums_list($form_container, $form, $fid);
	
	
	//echo '</table></div></div>';
	
	

	$submit_options = array();

	$no_results = false;
	if($form_container->num_rows() == 0)
	{
		//$form_container->output_cell('There are no forums found', array('colspan' => 3));
		
		echo '
		<tr class="first">
			<td class="first" colspan="3">There are no forums found.</td>
		</tr>';
		
		
		//$form_container->construct_row();
		$no_results = true;
	}
	
	echo '</table></div></div>';

	echo "</form>";

	if(!$no_results)
	{
		//$buttons[] = $form->generate_submit_button('update_forum_orders', $submit_options);
		//$buttons[] = $form->generate_reset_button('reset');
		//$form->output_submit_wrapper($buttons);
		
		
		echo '
		
		
		<div class="container mt-3">
		<div class="card-footer text-center">
		<tr><td colspan=3 align=center>
<input type="submit" value="Save Forum Orders" class="btn btn-primary"> 
<input type="reset" value="Reset" class="btn btn-primary"> 
</td></tr>
</div>
</div>';
		
		
		
	
		
		
	}

	if(!$fid)
	{
		
		echo "</form>";
	}

	if($fid)
	{
		echo "</div>\n";
		echo "</form>";

		$query = $db->simple_select("usergroups", "*", "", array("order" => "name"));
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups22[$usergroup['gid']] = $usergroup;
		}

		$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
		while($existing = $db->fetch_array($query))
		{
			$existing_permissions[$existing['gid']] = $existing;
		}

		$cached_forum_perms = $cache->read("forumpermissions");
		$field_list = array(
			'canview' => 'Can view?',
			'canpostthreads' => 'Can post threads?',
			'canpostreplys' => 'Can post replies?',
			'canpostpolls' => 'Can post polls?',
		);

		$field_list2 = array(
			'canview' => '&#149; View',
			'canpostthreads' => '&#149; Post Threads',
			'canpostreplys' => '&#149; Post Replies',
			'canpostpolls' => '&#149; Post Polls',
		);

		$ids = array();

		$form = new Form("index.php?act=management", "post", "management");
		echo $form->generate_hidden_field("fid", $mybb->input['fid']);
		echo $form->generate_hidden_field("update", "permissions");

		echo "<div id=\"tab_permissions\">\n";

		
		echo '
		
	   <div class="container mt-3">
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		 Forum Permissions in '.$forum_cache[$fid]['name'].'
	</div>
	 </div>
		</div>';
	   
	   
	   
	   
	
	
	
		
		
		echo '
	
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Group</th>
        <th>Overview: Allowed Actions</th>
        <th>Overview: Disallowed Actions5455</th>
		<th>Controls</th>
     
      </tr>
    </thead>';
		
		
		
		
		
		
		
		
		foreach($usergroups22 as $usergroup)
		{
			$perms = array();
			if(isset($mybb->input['default_permissions']))
			{
				if($mybb->input['default_permissions'][$usergroup['gid']])
				{
					if(isset($existing_permissions) && is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
					{
						$perms = $existing_permissions[$usergroup['gid']];
						$default_checked = false;
					}
					elseif(is_array($cached_forum_perms) && isset($cached_forum_perms[$forum['fid']]) && $cached_forum_perms[$forum['fid']][$usergroup['gid']])
					{
						$perms = $cached_forum_perms[$forum['fid']][$usergroup['gid']];
						$default_checked = true;
					}
					else if(is_array($cached_forum_perms) && isset($cached_forum_perms[$forum['pid']]) && $cached_forum_perms[$forum['pid']][$usergroup['gid']])
					{
						$perms = $cached_forum_perms[$forum['pid']][$usergroup['gid']];
						$default_checked = true;
					}
				}

				if(!$perms)
				{
					$perms = $usergroup;
					$default_checked = true;
				}
			}
			else
			{
				if(isset($existing_permissions) && is_array($existing_permissions) && !empty($existing_permissions[$usergroup['gid']]))
				{
					$perms = $existing_permissions[$usergroup['gid']];
					$default_checked = false;
				}
				elseif(isset($forum['fid']) && is_array($cached_forum_perms) && isset($cached_forum_perms[$forum['fid']][$usergroup['gid']]) && $cached_forum_perms[$forum['fid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum['fid']][$usergroup['gid']];
					$default_checked = true;
				}
				else if(isset($forum['pid']) && is_array($cached_forum_perms) && isset($cached_forum_perms[$forum['pid']][$usergroup['gid']]) && $cached_forum_perms[$forum['pid']][$usergroup['gid']])
				{
					$perms = $cached_forum_perms[$forum['pid']][$usergroup['gid']];
					$default_checked = true;
				}

				if(!$perms)
				{
					$perms = $usergroup;
					$default_checked = true;
				}
			}
			foreach($field_list as $forum_permission => $forum_perm_title)
			{
				if(isset($mybb->input['permissions']))
				{
					if($mybb->input['permissions'][$usergroup['gid']][$forum_permission])
					{
						$perms_checked[$forum_permission] = 1;
					}
					else
					{
						$perms_checked[$forum_permission] = 0;
					}
				}
				else
				{
					if($perms[$forum_permission] == 1)
					{
						$perms_checked[$forum_permission] = 1;
					}
					else
					{
						$perms_checked[$forum_permission] = 0;
					}
				}
			}
			$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

			if($default_checked == 1)
			{
				$inherited_text = 'inherited';
			}
			else
			{
				$inherited_text = 'custom';
			}

			//$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");
			
			echo '
			<tr>
			<td><strong>'.$usergroup['title'].'</strong> <small>('.$inherited_text.')</small></td>
			
			';
			

			$field_select = "
			<div class=\"quick_perm_fields\">\n";
			$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
			
			
			foreach($perms_checked as $perm => $value)
			{
				if($value == 1)
				{
					$field_select .= "
				
					<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
				}
			}
			
			$field_select .= "</ul></div>\n";
			$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
			
	
			foreach($perms_checked as $perm => $value)
			{
				if($value == 0)
				{
					$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
				}
			}
			$field_select .= "</ul></div></div></td>\n";
			$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_'.$usergroup['gid']));
			$field_select .= $form->generate_hidden_field("fields_inherit_".$usergroup['gid'], (int)$default_checked, array('id' => 'fields_inherit_'.$usergroup['gid']));
			$field_select .= $form->generate_hidden_field("fields_default_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, '1')), array('id' => 'fields_default_'.$usergroup['gid']));
			$field_select = str_replace("'", "\\'", $field_select);
			$field_select = str_replace("\n", "", $field_select);

			$field_select = "
			
			
			<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n




";

			$field_selected = array();
			foreach($field_list as $forum_permission => $permission_title)
			{
				$field_options[$forum_permission] = $permission_title;
				if($perms_checked[$forum_permission])
				{
					$field_selected[] = $forum_permission;
				}
			}

			$field_select .= "<noscript>".generate_select_box('fields_'.$usergroup['gid'].'[]', $field_options, $field_selected, array('id' => 'fields_'.$usergroup['gid'].'[]', 'multiple' => true))."</noscript>\n";
			
			
			echo '<td>'.$field_select.'</td>';
			
			
			
			
			
			
			
			

			if(!$default_checked)
			{
				
				
				
				
				echo "<td align=right><a href=\"index.php?act=management&action=permissions&amp;pid={$perms['pid']}\" onclick=\"popupWindow('index.php?act=management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">Edit Permissions777777566</a></td>";
				
				
				
				
				
				
				
				echo "<td align=right><a href=\"index.php?act=management&action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, 'confirm_clear_custom_permission')\">Clear Custom Permissions33333</a></td>";
			}
			else
			{
				echo "
				<td align=right>
				<a href=\"index.php?act=management&action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\"  onclick=\"popupWindow('index.php?act=management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">
				Set Custom Permissions7777754</a>
				</td>
				</tr>
				";
			}
			$form_container->construct_row(array('id' => 'row_'.$usergroup['gid']));
			
			

			$ids[] = $usergroup['gid'];
		}
		//$form_container->end();
		
		echo "</form>";
		
		
		
	
		
		
		
		 echo '</table>
		 
		 
		 </br>
		<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Save Forum Permissions" class="btn btn-primary"> 
<input type="reset" value="Reset" class="btn btn-primary"> 
</td></tr>
</div>
		 
		 
		 
		 
		 
		 
		 
		 
		 </div></div>';


		
		
		
		
		
		
		
		
		
		
		
		
		

		// Write in our JS based field selector
		echo "<script type=\"text/javascript\">\n<!--\n";
		foreach($ids as $id)
		{
			echo "$(function() { QuickPermEditor.init(".$id.") });\n";
		}
		echo "// -->\n</script>\n";

		echo "</div>\n";
		$form->end();
		
		
		
		
		
		
		echo '<div class="container mt-3">';
echo "<div id=\"tab_moderators\">";

// Список текущих модераторов
echo '<div class="card border-0 shadow-sm mb-4">';
echo '<div class="card-header bg-primary text-white py-3">';
echo '<h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>'.sprintf($lang->forum_management['moderators_assigned_to'], htmlspecialchars_uni($forum_cache[$fid]['name'])).'</h5>';
echo '</div>';
echo '<div class="card-body p-0">';

$form_container = new FormContainer('');
$form_container->output_row_header($lang->forum_management['name'], array('class' => 'px-3 py-2 bg-light', 'width' => '75%'));
$form_container->output_row_header($lang->forum_management['controls'], array("class" => "text-center px-3 py-2 bg-light", 'style' => 'width: 200px', 'colspan' => 2));

$query = $db->query("
    SELECT m.mid, m.id, m.isgroup, u.username, g.title
    FROM moderators m
    LEFT JOIN users u ON (m.isgroup='0' AND m.id=u.id)
    LEFT JOIN usergroups g ON (m.isgroup='1' AND m.id=g.gid)
    WHERE m.fid='{$fid}'
    ORDER BY m.isgroup DESC, u.username ASC, g.title ASC
");

if($db->num_rows($query) == 0)
{
    $form_container->output_cell('<div class="text-center text-muted py-4">'.$lang->forum_management['no_moderators'].'</div>', array('colspan' => 3, 'class' => 'p-3'));
    $form_container->construct_row();
}
else
{
    while($moderator = $db->fetch_array($query))
    {
        if($moderator['isgroup'])
        {
            $icon = '<i class="fas fa-users me-2 text-primary"></i>';
            $name = '<a href="index.php?act=management&action=edit&amp;gid='.$moderator['id'].'" class="text-decoration-none fw-semibold">'.htmlspecialchars_uni($moderator['title']).' ('.$lang->usergroup.' '.$moderator['id'].')</a>';
            $edit = '<a href="index.php?act=management&action=editmod&amp;mid='.$moderator['mid'].'" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit me-1"></i>'.$lang->edit.'</a>';
            $delete = '<a href="index.php?act=management&action=deletemod&amp;id='.$moderator['id'].'&amp;isgroup=1&amp;fid='.$fid.'&amp;my_post_key='.$mybb->post_code.'" onclick="return AdminCP.deleteConfirmation(this, \''.$lang->forum_management['confirm_moderator_deletion'].'\')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>'.$lang->delete.'</a>';
        }
        else
        {
            $icon = '<i class="fas fa-user me-2 text-info"></i>';
            $name = '<a href="index.php?act=management&action=edit&amp;uid='.$moderator['id'].'" class="text-decoration-none fw-semibold">'.htmlspecialchars_uni($moderator['username']).'</a>';
            $edit = '<a href="index.php?act=management&action=editmod&amp;mid='.$moderator['mid'].'" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit me-1"></i>'.$lang->edit.'</a>';
            $delete = '<a href="index.php?act=management&action=deletemod&amp;id='.$moderator['id'].'&amp;isgroup=0&amp;fid='.$fid.'&amp;my_post_key='.$mybb->post_code.'" onclick="return AdminCP.deleteConfirmation(this, \''.$lang->forum_management['confirm_moderator_deletion'].'\')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>'.$lang->delete.'</a>';
        }

        $form_container->output_cell('<div class="px-3 py-2">'.$icon.$name.'</div>');
        $form_container->output_cell('<div class="px-3 py-2">'.$edit.'</div>', array("class" => "text-center"));
        $form_container->output_cell('<div class="px-3 py-2">'.$delete.'</div>', array("class" => "text-center"));
        $form_container->construct_row();
    }
}
$form_container->end();
echo '</div></div>';

// Формы добавления модераторов
echo '<div class="row">';
    echo '<div class="col-md-6">';
        // Добавление группы как модератора
        $form = new Form("index.php?act=management", "post", "addGroupMod");
        echo $form->generate_hidden_field("fid", $mybb->input['fid']);
        echo $form->generate_hidden_field("add", "moderators");

        if(!is_array($usergroups222))
        {
            $usergroups222 = $groupscache;
        }

        $modgroups = array();
        foreach($usergroups222 as $group)
        {
            if(is_array($group) && isset($group['gid'], $group['title']))
            {
                $modgroups[$group['gid']] = htmlspecialchars_uni($group['title']).' (ID: '.$group['gid'].')';
            }
        }

        echo '<div class="card border-0 shadow-sm">';
        echo '<div class="card-header bg-success text-white py-3">';
        echo '<h6 class="mb-0"><i class="fas fa-users me-2"></i>'.$lang->forum_management['add_usergroup_as_moderator'].'</h6>';
        echo '</div>';
        echo '<div class="card-body">';

        $form_container = new FormContainer('');
        $form_container->output_row(
            $lang->forum_management['usergroup'].' <span class="text-danger">*</span>', 
            $lang->forum_management['moderator_usergroup_desc'], 
            $form->generate_select_box('usergroup', $modgroups, $mybb->input['usergroup'], array('id' => 'usergroup', 'class' => 'form-select')), 
            'usergroup'
        );
        $form_container->end();

        echo '<div class="text-end">';
        $buttons = array();
        $buttons[] = $form->generate_submit_button($lang->forum_management['add_usergroup_moderator'], array('class' => 'btn btn-success'));
        $form->output_submit_wrapper($buttons);
        $form->end();
        echo '</div></div></div>';
    echo '</div>';

    echo '<div class="col-md-6">';
        // Добавление пользователя как модератора
        $form = new Form("index.php?act=management", "post", "addUserMod");
        echo $form->generate_hidden_field("fid", $mybb->input['fid']);
        echo $form->generate_hidden_field("add", "moderators");

        echo '<div class="card border-0 shadow-sm">';
        echo '<div class="card-header bg-info text-white py-3">';
        echo '<h6 class="mb-0"><i class="fas fa-user-plus me-2"></i>'.$lang->forum_management['add_user_as_moderator'].'</h6>';
        echo '</div>';
        echo '<div class="card-body">';

        $form_container = new FormContainer('');
        $form_container->output_row(
            $lang->forum_management['username'].' <span class="text-danger">*</span>', 
            $lang->forum_management['moderator_username_desc'], 
            $form->generate_text_box('username', htmlspecialchars_uni($mybb->get_input('username')), array('id' => 'username', 'class' => 'form-control', 'placeholder' => $lang->forum_management['search_for_a_user'])), 
            'username'
        );
        $form_container->end();

        echo '<div class="text-end">';
        $buttons = array();
        $buttons[] = $form->generate_submit_button($lang->forum_management['add_user_moderator'], array('class' => 'btn btn-primary'));
        $form->output_submit_wrapper($buttons);
        $form->end();
        echo '</div></div></div>';

        
		
		
		
		// Autocompletion for usernames
		echo '
		<link rel="stylesheet" href="../scripts/select2/select2.css">
		<script type="text/javascript" src="../scripts/select2/select2.min.js?ver=1804"></script>
		<script type="text/javascript">
		<!--
		$("#username").select2({
			placeholder: "'.'search_for_a_user'.'",
			minimumInputLength: 2,
			multiple: false,
			ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
				url: "../xmlhttp.php?action=get_users",
				dataType: \'json\',
				data: function (term, page) {
					return {
						query: term, // search term
					};
				},
				results: function (data, page) { // parse the results into the format expected by Select2.
					// since we are using custom formatting functions we do not need to alter remote JSON data
					return {results: data};
				}
			},
			initSelection: function(element, callback) {
				var query = $(element).val();
				if (query !== "") {
					$.ajax("../xmlhttp.php?action=get_users&getone=1", {
						data: {
							query: query
						},
						dataType: "json"
					}).done(function(data) { callback(data); });
				}
			},
		});

		$(\'[for=username]\').on(\'click\', function(){
			$("#username").select2(\'open\');
			return false;
		});
		// -->
		</script>';
		
		
		
		
		
		
		
    echo '</div>';
echo '</div>';

echo "</div></div>"; // закрываем tab_moderators и container
		
		
		
		
		
		
		
		
		
		
		
		
		

		$plugins->run_hooks("admin_forum_management_start_graph");
	}

	stdfoot();
}

/**
 * @param DefaultFormContainer $form_container
 * @param DefaultForm $form
 * @param int $pid
 * @param int $depth
 */
 
 



echo "	<script type=\"text/javascript\" src=\"scripts/bootbox.min.js\"></script>\n";
echo "	<script type=\"text/javascript\" src=\"scripts/deleteForum.js\"></script>\n";



function build_admincp_forums_list(&$form_container, &$form, $pid=0, $depth=1)
{
	global $mybb, $lang, $db, $sub_forums;
	static $forums_by_parent;

	if(!is_array($forums_by_parent))
	{
		$forum_cache = cache_forums();

		foreach($forum_cache as $forum)
		{
			$forums_by_parent[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!isset($forums_by_parent[$pid]) || !is_array($forums_by_parent[$pid]))
	{
		return;
	}
	
	$subforumsindex = "2";

	$donecount = 0;
	$comma = '';
	foreach($forums_by_parent[$pid] as $children)
	{
		foreach($children as $forum)
		{
			$forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['name']); // Fix & but allow unicode

			if($forum['active'] == 0)
			{
				$forum['name'] = "<em>".$forum['name']."</em>";
			}

			if($forum['type'] == "c" && ($depth == 1 || $depth == 2))
			{
				$sub_forums = '';
				if(isset($forums_by_parent[$forum['fid']]) && $depth == 2)
				{
					build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
				}
				if($sub_forums)
				{
					$sub_forums = "<br /><small>sub_forums: {$sub_forums}</small>";
				}

				echo "
				<tr class=first>
				<td class=first><div style=\"padding-left: ".(40*($depth-1))."px;\"><a href=\"index.php?act=management&amp;fid={$forum['fid']}\"><strong>{$forum['name']}</strong></a>{$sub_forums}</div></td>";
				

				//echo '
				//<td class="align_center alt_col"><input type="number" name="disporder['.$forum['fid'].']" value="'.$forum['disporder'].'" min="0" class="text_input align_center" style="width:80%; font-weight:bold" />
				//</td>
				
				//';
				
				
				echo '
				<td class="align_center alt_col">
				<div class="form-outline" data-mdb-input-init>
  <label>
  <input type="number" name="disporder['.$forum['fid'].']" value="'.$forum['disporder'].'" min="0" id="typeNumber" class="form-control" />
  </label>
  
</div></td>
				
				
				
				';
				








$options_link = '



<i class="fa-solid fa-gear"></i> &nbsp;Manage Forum


  <table>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&action=edit&amp;fid='.$forum['fid'].'">Edit Forum</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&fid='.$forum['fid'].'">Subforums</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&fid='.$forum['fid'].'#tab_moderators">Moderators</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&fid='.$forum['fid'].'#tab_permissions">Permissions</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="management.php?module=config-thread_prefixes&amp;fid='.$forum['fid'].'">Thread Prefixes</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&action=add&amp;pid='.$forum['fid'].'">Add Child Forum</a></td>
			  </tr>
			  
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&action=copy&amp;fid='.$forum['fid'].'">Copy Forum</a></td>
			  </tr>
	
	
			  <tr>
			  <td>
		   <a class="delete_employee" data-emp-id="' . $forum['fid'] . '" href="javascript:void(0)">
	         Delete Forum
	       </a>
		</td>
		</tr>
			  
			  
			  
			  
			  
			  
			    
			  
</table>';			  
			  
		  
			  	
echo '
	
		
<td>		
<ul class="list-inline mt-0 mb-0" style="margin-bottom: 0px">
<li class="list-inline-item">
<div class="dropdown"><a href="#" aria-expanded="true" data-bs-toggle="dropdown" type="button"><i class="fa-solid fa-gear"></i> &nbsp;Options <i class="fa-solid fa-angle-down small"></i></a>
<div class="dropdown-menu dropdown-menu-start border rounded" data-bs-popper="none" style="width: 200px">
<div class="row p-2">
<div class="col align-self-center">

'.$options_link.'

</div>


</div>
</div>

</div>
</div> 
</td>';









	
				//$form_container->construct_row();

				// Does this category have any sub forums?
				if(!empty($forums_by_parent[$forum['fid']]))
				{
					build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
				}
			}
			elseif($forum['type'] == "f" && ($depth == 1 || $depth == 2))
			{
				if($forum['description'])
				{
					$forum['description'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['description']);
					$forum['description'] = "<br /><small>".$forum['description']."</small>";
				}

				$sub_forums = '';
				if(isset($forums_by_parent[$forum['fid']]) && $depth == 2)
				{
					build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
				}
				if($sub_forums)
				{
					$sub_forums = "<br /><small>Sub Forums: {$sub_forums}</small>";
				}

				echo "
				
				<tr class=last>
				<td class=first><div style=\"padding-left: ".(40*($depth-1))."px;\"><a href=\"index.php?act=management&amp;fid={$forum['fid']}\">{$forum['name']}</a>{$forum['description']}{$sub_forums}</div>
				
				</td>
				
				";

				//$form_container->output_cell($form->generate_numeric_field("disporder[{$forum['fid']}]", "{$forum['disporder']}", array('min' => 0, 'class' => 'align_center', 'style' => 'width:80%')), array("class" => "align_center"));
				
				//echo '
				
				
				//<td class="align_center alt_col"><input type="number" name="disporder['.$forum['fid'].']" value="'.$forum['disporder'].'" min="0" class="text_input align_center" style="width:80%" />
				
				//</td>';
				
				
				
				
				echo '
				<td class="align_center alt_col">
				<div class="form-outline" data-mdb-input-init>
                <label>
                   <input type="number" name="disporder['.$forum['fid'].']" value="'.$forum['disporder'].'" min="0" id="typeNumber" class="form-control" />
                </label>
                </div></td>';
				
				
				
				
				
				
				
			

				$form_container->construct_row();
				
				
				
	$options_link2 = '

<i class="fa-solid fa-user-gear"></i> &nbsp;Manage Forum



<table>
			
			  
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&action=edit&amp;fid='.$forum['fid'].'" class="popup_item">Edit Forum</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&fid='.$forum['fid'].'" class="popup_item">Subforums</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&fid='.$forum['fid'].'#tab_moderators" class="popup_item">Moderators</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&fid='.$forum['fid'].'#tab_permissions" class="popup_item">Permissions</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="management.php?module=config-thread_prefixes&amp;fid='.$forum['fid'].'" class="popup_item">Thread Prefixes</a></td>
			  </tr>
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&action=add&amp;pid='.$forum['fid'].'" class="popup_item">Add Child Forum</a></td>
			  </tr>
			  
			  <tr>
				<td class="subheader2"><a href="index.php?act=management&action=copy&amp;fid='.$forum['fid'].'" class="popup_item">Copy Forum</a></td>
			  </tr>
			  
			  
			   
				<tr>
			  <td>
		   <a class="delete_employee" data-emp-id="' . $forum['fid'] . '" href="javascript:void(0)">
	          Delete Forum
	       </a>
		</td>
		</tr>
				
				
				
				
			 
				
				
				
				
			  
			  
			  
			  
			  
</table>

';			
				
				
				
				
            echo '
	
		
<td>		
<ul class="list-inline mt-0 mb-0" style="margin-bottom: 0px">
<li class="list-inline-item">
<div class="dropdown"><a href="#" aria-expanded="true" data-bs-toggle="dropdown" type="button"><i class="fa-solid fa-gear"></i> &nbsp;Options <i class="fa-solid fa-angle-down small"></i></a>
<div class="dropdown-menu dropdown-menu-start border rounded" data-bs-popper="none" style="width: 200px">
<div class="row p-2">
<div class="col align-self-center">

'.$options_link2.'

</div>


</div>
</div>

</div>
</div> 


		
		
		</td>
		
		
	




';
				
				
				
				
				
				
				

				if(isset($forums_by_parent[$forum['fid']]) && $depth == 1)
				{
					build_admincp_forums_list($form_container, $form, $forum['fid'], $depth+1);
				}
			}
			else if($depth == 3)
			{
				
				
				
				if($donecount < $subforumsindex)
				{
					$sub_forums .= "{$comma} <a href=\"index.php?act=management&fid={$forum['fid']}\">{$forum['name']}</a>";
					$comma = 'comma';
				}

				// Have we reached our max visible subforums? put a nice message and break out of the loop
				++$donecount;
				if($donecount == $subforumsindex)
				{
					if(subforums_count($forums_by_parent[$pid]) > $donecount)
					{
						$sub_forums .= $comma.sprintf('more_subforums', (subforums_count($forums_by_parent[$pid]) - $donecount));
						return;
					}
				}
			}
		}
	}
}





/**
 * @param int $gid
 * @param int $fid
 *
 * @return string
 */
function retrieve_single_permissions_row($gid, $fid)
{
	global $mybb, $lang, $cache, $db;

	$query = $db->simple_select("usergroups", "*", "gid='{$gid}'");
	$usergroup = $db->fetch_array($query);

	$query = $db->simple_select("tsf_forums", "*", "fid='{$fid}'");
	$forum_data = $db->fetch_array($query);

	$query = $db->simple_select("forumpermissions", "*", "fid='{$fid}'");
	while($existing = $db->fetch_array($query))
	{
		$existing_permissions[$existing['gid']] = $existing;
	}

	$cached_forum_perms = $cache->read("forumpermissions");
	$field_list = array(
		'canview' => 'Can view?',
		'canpostthreads' => 'Can post threads?',
		'canpostreplys' => 'Can post replies?',
		'canpostpolls' => 'Can post polls?',
	);

	$field_list2 = array(
		'canview' => '&#149; View',
		'canpostthreads' => '&#149; Post Threads',
		'canpostreplys' => '&#149; Post Replies',
		'canpostpolls' => '&#149; Post Polls',
	);

	$form = new Form('', '', "", 0, "", true);
	$form_container = new FormContainer();

	$perms = array();

	if(is_array($existing_permissions) && $existing_permissions[$usergroup['gid']])
	{
		$perms = $existing_permissions[$usergroup['gid']];
		$default_checked = false;
	}
	elseif(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['fid']][$usergroup['gid']])
	{
		$perms = $cached_forum_perms[$forum_data['fid']][$usergroup['gid']];
		$default_checked = true;
	}
	else if(is_array($cached_forum_perms) && $cached_forum_perms[$forum_data['pid']][$usergroup['gid']])
	{
		$perms = $cached_forum_perms[$forum_data['pid']][$usergroup['gid']];
		$default_checked = true;
	}

	if(!$perms)
	{
		$perms = $usergroup;
		$default_checked = true;
	}

	foreach($field_list as $forum_permission => $forum_perm_title)
	{
		if($perms[$forum_permission] == 1)
		{
			$perms_checked[$forum_permission] = 1;
		}
		else
		{
			$perms_checked[$forum_permission] = 0;
		}
	}

	$usergroup['title'] = htmlspecialchars_uni($usergroup['title']);

	if($default_checked == 1)
	{
		$inherited_text = 'inherited';
	}
	else
	{
		$inherited_text = 'custom_permission';
	}

	$form_container->output_cell("<strong>{$usergroup['title']}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>");

	$field_select = "<div class=\"quick_perm_fields\">\n";
	$field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_{$usergroup['gid']}\">\n";
	foreach($perms_checked as $perm => $value)
	{
		if($value == 1)
		{
			$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
		}
	}
	$field_select .= "</ul></div>\n";
	$field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_{$usergroup['gid']}\">\n";
	foreach($perms_checked as $perm => $value)
	{
		if($value == 0)
		{
			$field_select .= "<li id=\"field-{$perm}\">{$field_list2[$perm]}</li>";
		}
	}
	$field_select .= "</ul></div></div>\n";
	$field_select .= $form->generate_hidden_field("fields_".$usergroup['gid'], @implode(",", @array_keys($perms_checked, 1)), array('id' => 'fields_'.$usergroup['gid']));
	$field_select = str_replace("\n", "", $field_select);

	foreach($field_list as $forum_permission => $permission_title)
	{
		$field_options[$forum_permission] = $permission_title;
	}
	$form_container->output_cell($field_select, array('colspan' => 2));

	if(!$default_checked)
	{
		$form_container->output_cell("<a href=\"index.php?act=management&action=permissions&amp;pid={$perms['pid']}\" onclick=\"popupWindow('index.php?act=management&action=permissions&pid={$perms['pid']}&ajax=1', null, true); return false;\">edit_permissions</a>", array("class" => "align_center"));
		$form_container->output_cell("<a href=\"index.php?act=management&action=clear_permission&amp;pid={$perms['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, 'confirm_clear_custom_permission')\">clear_custom_perms</a>", array("class" => "align_center"));
	}
	else
	{
		$form_container->output_cell("<a href=\"index.php?act=management&action=permissions&amp;gid={$usergroup['gid']}&amp;fid={$fid}\"  onclick=\"popupWindow('index.php?act=management&action=permissions&gid={$usergroup['gid']}&fid={$fid}&ajax=1', null, true); return false;\">Set Custom Permissions</a>", array("class" => "align_center", "colspan" => 2));
	}
	$form_container->construct_row();
	return $form_container->output_row_cells(0, true);
}

