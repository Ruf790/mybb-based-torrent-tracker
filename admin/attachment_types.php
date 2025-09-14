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




function print_selection_javascript()
{
	static $already_printed = false;

	if($already_printed)
	{
		return;
	}

	$already_printed = true;

	echo "<script type=\"text/javascript\">
	function checkAction(id)
	{
		var checked = '';

		$('.'+id+'_forums_groups_check').each(function(e, val)
		{
			if($(this).prop('checked') == true)
			{
				checked = $(this).val();
			}
		});

		$('.'+id+'_forums_groups').each(function(e)
		{
			$(this).hide();
		});

		if($('#'+id+'_forums_groups_'+checked))
		{
			$('#'+id+'_forums_groups_'+checked).show();
		}
	}
</script>";
}





function generate_yes_no_radio($name, $value="1", $int=true, $yes_options=array(), $no_options = array())
{
		global $lang;

		// Checked status
		if($value === "no" || $value === '0' || $value === 0)
		{
			$no_checked = 1;
			$yes_checked = 0;
		}
		else
		{
			$yes_checked = 1;
			$no_checked = 0;
		}
		// Element value
		if($int == true)
		{
			$yes_value = 1;
			$no_value = 0;
		}
		else
		{
			$yes_value = "yes";
			$no_value = "no";
		}

		if(!isset($yes_options['class']))
		{
			$yes_options['class'] = '';
		}

		if(!isset($no_options['class']))
		{
			$no_options['class'] = '';
		}

		// Set the options straight
		$yes_options['class'] = "radio_yes ".$yes_options['class'];
		$yes_options['checked'] = $yes_checked;
		$no_options['class'] = "radio_no ".$no_options['class'];
		$no_options['checked'] = $no_checked;

		$yes = generate_radio_button($name, $yes_value, 'yes', $yes_options);
		$no = generate_radio_button($name, $no_value, 'no', $no_options);
		return $yes." ".$no;
}




function generate_group_select($name, $selected=array(), $options=array())
{
		global $cache;

		$select = "<select name=\"{$name}\"";

		if(isset($options['multiple']))
		{
			$select .= " multiple=\"multiple\"";
		}

		if(isset($options['class']))
		{
			$select .= " class=\"{$options['class']}\"";
		}

		if(isset($options['id']))
		{
			$select .= " id=\"{$options['id']}\"";
		}

		if(isset($options['size']))
		{
			$select .= " size=\"{$options['size']}\"";
		}

		$select .= ">\n";

		$groups_cache = $cache->read('usergroups');

		if(!is_array($selected))
		{
			$selected = array($selected);
		}

		foreach($groups_cache as $group)
		{
			$selected_add = "";


			if(in_array($group['gid'], $selected))
			{
				$selected_add = " selected=\"selected\"";
			}

			$select .= "<option value=\"{$group['gid']}\"{$selected_add}>".htmlspecialchars_uni($group['title'])."</option>";
		}

		$select .= "</select>";

		return $select;
}





$plugins->run_hooks("admin_config_attachment_types_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_attachment_types_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['mimetype']) && !trim($mybb->input['extension']))
		{
			$errors[] = 'You did not enter a MIME type for this attachment type';
		}

		if(!trim($mybb->input['extension']) && !trim($mybb->input['mimetype']))
		{
			$errors[] = 'You did not enter a file extension for this attachment type';
		}

		if(!$errors)
		{
			if($mybb->input['mimetype'] == "pic/attachtypes/")
			{
				$mybb->input['mimetype'] = '';
			}

			if(substr($mybb->input['extension'], 0, 1) == '.')
			{
				$mybb->input['extension'] = substr($mybb->input['extension'], 1);
			}

			foreach(array('groups', 'forums') as $key)
			{
				if($mybb->input[$key] == 'all')
				{
					$mybb->input[$key] = -1;
				}
				elseif($mybb->input[$key] == 'custom')
				{
					if(isset($mybb->input['select'][$key]) && is_array($mybb->input['select'][$key]))
					{
						foreach($mybb->input['select'][$key] as &$val)
						{
							$val = (int)$val;
						}
						unset($val);

						$mybb->input[$key] = implode(',', (array)$mybb->input['select'][$key]);
					}
					else
					{
						$mybb->input[$key] = '';
					}
				}
				else
				{
					$mybb->input[$key] = '';
				}
			}

			$maxsize = $mybb->get_input('maxsize', MyBB::INPUT_INT);

			if($maxsize == 0)
			{
				$maxsize = "";
			}

			$new_type = array(
				"name" => $db->escape_string($mybb->input['name']),
				"mimetype" => $db->escape_string($mybb->input['mimetype']),
				"extension" => $db->escape_string($mybb->input['extension']),
				"maxsize" => $maxsize,
				"icon" => $db->escape_string($mybb->input['icon']),
				'enabled' => $mybb->get_input('enabled', MyBB::INPUT_INT),
				'forcedownload' => $mybb->get_input('forcedownload', MyBB::INPUT_INT),
				'groups' => $db->escape_string($mybb->get_input('groups')),
				'forums' => $db->escape_string($mybb->get_input('forums')),
				'avatarfile' => $mybb->get_input('avatarfile', MyBB::INPUT_INT)
			);

			$atid = $db->insert_query("attachtypes", $new_type);

			$plugins->run_hooks("admin_config_attachment_types_add_commit");

			// Log admin action
			//log_admin_action($atid, $mybb->input['extension']);

			$cache->update_attachtypes();

			flash_message('success_attachment_type_created', 'success');
			admin_redirect("index.php?act=attachment_types");
		}
	}


	stdhead('Attachment Types - Add New Attachment Type');
	
	
	
	echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
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
	
	
	
	
	
	
	
	
	

	$sub_tabs['attachment_types'] = array(
		'title' => 'Attachment Types',
		'link' => "index.php?act=attachment_types"
	);

	$sub_tabs['add_attachment_type'] = array(
		'title' => 'Add New Attachment Type',
		'link' => "index.php?act=attachment_types&amp;action=add",
		'description' => 'Adding a new attachment type will allow members to attach files of this type to their posts. You have the ability to control the extension, MIME type, maximum size and show a small icon for each attachment type'
	);

	output_nav_tabs($sub_tabs, 'add_attachment_type');

	//$form = new Form("index.php?act=attachment_types&amp;action=add", "post", "add");
	
	echo '
	<form action="index.php?act=attachment_types&amp;action=add" method="post" id="add">
    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
	';
	

	if($errors)
	{
		switch($mybb->input['groups'])
		{
			case 'all':
				$mybb->input['groups'] = -1;
				break;
			case 'custom':
				$mybb->input['groups'] = implode(',', (array)$mybb->input['select']['groups']);
				break;
			default:
				$mybb->input['groups'] = '';
				break;
		}

		switch($mybb->input['forums'])
		{
			case 'all':
				$mybb->input['forums'] = -1;
				break;
			case 'custom':
				$mybb->input['forums'] = implode(',', (array)$mybb->input['select']['forums']);
				break;
			default:
				$mybb->input['forums'] = '';
				break;
		}

		output_inline_error($errors);
	}
	else
	{
		$mybb->input['maxsize'] = '1024';
		$mybb->input['icon'] = "pic/attachtypes/";
	}

	if(empty($mybb->input['groups']))
	{
		$mybb->input['groups'] = '';
	}

	if(empty($mybb->input['forums']))
	{
		$mybb->input['forums'] = '';
	}

	// PHP settings
	$upload_max_filesize = @ini_get('upload_max_filesize');
	$post_max_size = @ini_get('post_max_size');
	$limit_string = '';
	if($upload_max_filesize || $post_max_size)
	{
		$limit_string = '<br /><br />'.'Please ensure the maximum file size is below the smallest of the following PHP limits:';
		if($upload_max_filesize)
		{
			$limit_string .= '<br />'.sprintf('Upload Max File Size: '.$upload_max_filesize.'');
		}
		if($post_max_size)
		{
			$limit_string .= '<br />'.sprintf('Max Post Size: '.$post_max_size.'');
		}
	}

	$selected_values = '';
	if($mybb->input['groups'] != '' && $mybb->input['groups'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('groups'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['groups'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['groups'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$groups_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"all\" {$group_checked['all']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>All groups</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"custom\" {$group_checked['custom']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>Select groups</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"groups_forums_groups_custom\" class=\"groups_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>Groups:</small></td>
					<td>".generate_group_select('select[groups][]', $selected_values, array('id' => 'groups', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"none\" {$group_checked['none']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>None</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('groups');
	</script>";

	$selected_values = '';
	if($mybb->input['forums'] != '' && $mybb->input['forums'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('forums'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$forum_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['forums'] == -1)
	{
		$forum_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['forums'] != '')
	{
		$forum_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$forum_checked['none'] = 'checked="checked"';
	}

	$forums_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>All Forums</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>Select forums</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>Forums:</small></td>
					<td>".generate_forum_select('select[forums][]', $selected_values, array('id' => 'forums', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"none\" {$forum_checked['none']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>None</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('forums');
	</script>";
	
	$mybb->input['forcedownload'] = $mybb->get_input('forcedownload', MyBB::INPUT_INT);

	
	
	$attach_name = generate_text_box('name', $mybb->get_input('name'), array('id' => 'name'));
	
	$file_exten = generate_text_box('extension', $mybb->get_input('extension'), array('id' => 'extension'));
	
	$mime_typess = generate_text_box('mimetype', $mybb->get_input('mimetype'), array('id' => 'mimetype'));
	
	$max_file_size = generate_numeric_field('maxsize', $mybb->get_input('maxsize'), array('id' => 'maxsize', 'min' => 0));
	
	
	$attach_iconss = generate_text_box('icon', $mybb->get_input('icon'), array('id' => 'icon'));
	
	$enabledd = generate_yes_no_radio('enabled', $mybb->get_input('enabled'));
	
	$force_download = generate_yes_no_radio('forcedownload', $mybb->get_input('forcedownload'));
	
	
	$avatar_file = generate_yes_no_radio('avatarfile', $mybb->get_input('avatarfile'));
	
	echo '
	
	<div class="container mt-3">
	
	<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">Add New Attachment Type</div>
    <div class="card-body">';
	
	
	echo '
	
	<tr class="first">
			<td class="first"><label for="name">Name</label>
<div class="description">Enter the name of the attachment type</div>
<div class="form_row">

'.$attach_name.'

</div>
</td>
		</tr>
		
		
		
	<tr class="alt_row">
			<td class="first"><label for="extension">File Extension <em>*</em></label>
<div class="description">Enter the file extension you wish to allow uploads for here (Do not include the period before the extension) (Example: txt)</div>
<div class="form_row">

'.$file_exten.'

</div>
</td>
		</tr>	
		
		
		
		
	<tr>
			<td class="first"><label for="mimetype">MIME Type <em>*</em></label>
<div class="description">Enter the MIME type sent by the server when downloading files of this type (<a href=\"http://www.freeformatter.com/mime-types-list.html\">See a list here</a>)</div>
<div class="form_row">

'.$mime_typess.'

</div>
</td>
		</tr>
		
		
		
		
		
		
		
	<tr class="alt_row">
			<td class="first"><label for="maxsize">Maximum File Size (Kilobytes)</label>
<div class="description">The maximum file size for uploads of this attachment type in Kilobytes (1 MB = 1024 KB)

'.$limit_string.'</div>
<div class="form_row">

'.$max_file_size.'

</div>
</td>
		</tr>
		
		
		
		
		
		
		
	<tr>
			<td class="first"><label for="icon">Attachment Icon</label>
<div class="description">If you wish to show a small attachment icon for attachments of this type then enter the path to it here. {theme} will be replaced by the image directory for the viewers theme allowing you to specify per-theme attachment icons</div>
<div class="form_row">

'.$attach_iconss.'


</div>
</td>
		</tr>	
		
		
		
		
		
		
	<tr class="alt_row">
			<td class="first">
			<label for="enabled">Enabled?</label>
			<div class="form_row">
			
			'.$enabledd.'
			
			</div>
</td>
		</tr>	
		
		
		
	
	</br>
	<tr>
	
			</br>
			<td class="first"><label for="forcedownload">Force Download</label>
<div class="description">Enabling this will always force the attachment to be downloaded as a file</div>
<div class="form_row">

'.$force_download.'

</div>
</td>
		</tr>
		
		
	
	
	</br>
<tr id="row_groups" class="alt_row">
			</br>
			<td class="first"><label>Available to groups</label>
			
			<div class="form_row">
			
	
	'.$groups_select_code.'
	
	</div>
</td>
		</tr>	
	
	
	
	<hr>
	
	
<tr id="row_forums">
			<td class="first"><label>Available in forums</label>
			
			<div class="form_row">
			
			'.$forums_select_code.'

	
	</div>
</td>
		</tr>	
	
	
	
	
	
	<tr class="last alt_row">
			<td class="first"><label for="avatarfile">Avatar File</label>
<div class="description">Do you want to allow this attachment type to be used for avatars?</div>
<div class="form_row">

'.$avatar_file.'
 
 </div>
</td>
		</tr>
		
		
		
		
	
	
	
	
	
	
	';
	
	
    echo "</div></div></div>";
	
	
	
	


    echo '
		
		<div class="container mt-3">
		<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Save Attachment Type" class="btn btn-primary"> 
</td></tr>
</div></div>';


	
	
	echo "</form>";

	stdfoot();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("attachtypes", "*", "atid='".$mybb->get_input('atid', MyBB::INPUT_INT)."'");
	$attachment_type = $db->fetch_array($query);

	if(!$attachment_type['atid'])
	{
		flash_message('error_invalid_attachment_type', 'error');
		admin_redirect("index.php?act=attachment_types");
	}

	$plugins->run_hooks("admin_config_attachment_types_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['mimetype']) && !trim($mybb->input['extension']))
		{
			$errors[] = 'You did not enter a MIME type for this attachment type';
		}

		if(!trim($mybb->input['extension']) && !trim($mybb->input['mimetype']))
		{
			$errors[] = 'You did not enter a file extension for this attachment type';
		}

		if(!$errors)
		{
			if($mybb->input['mimetype'] == "images/attachtypes/")
			{
				$mybb->input['mimetype'] = '';
			}

			if(substr($mybb->input['extension'], 0, 1) == '.')
			{
				$mybb->input['extension'] = substr($mybb->input['extension'], 1);
			}

			foreach(array('groups', 'forums') as $key)
			{
				if($mybb->input[$key] == 'all')
				{
					$mybb->input[$key] = -1;
				}
				elseif($mybb->input[$key] == 'custom')
				{
					if(isset($mybb->input['select'][$key]) && is_array($mybb->input['select'][$key]))
					{
						foreach($mybb->input['select'][$key] as &$val)
						{
							$val = (int)$val;
						}
						unset($val);

						$mybb->input[$key] = implode(',', (array)$mybb->input['select'][$key]);
					}
					else
					{
						$mybb->input[$key] = '';
					}
				}
				else
				{
					$mybb->input[$key] = '';
				}
			}

			$updated_type = array(
				"name" => $db->escape_string($mybb->input['name']),
				"mimetype" => $db->escape_string($mybb->input['mimetype']),
				"extension" => $db->escape_string($mybb->input['extension']),
				"maxsize" => $mybb->get_input('maxsize', MyBB::INPUT_INT),
				"icon" => $db->escape_string($mybb->input['icon']),
				'enabled' => $mybb->get_input('enabled', MyBB::INPUT_INT),
				'forcedownload' => $mybb->get_input('forcedownload', MyBB::INPUT_INT),
				'groups' => $db->escape_string($mybb->get_input('groups')),
				'forums' => $db->escape_string($mybb->get_input('forums')),
				'avatarfile' => $mybb->get_input('avatarfile', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_config_attachment_types_edit_commit");

			$db->update_query("attachtypes", $updated_type, "atid='{$attachment_type['atid']}'");

			// Log admin action
			//log_admin_action($attachment_type['atid'], $mybb->input['extension']);

			$cache->update_attachtypes();

			flash_message('success_attachment_type_updated', 'success');
			admin_redirect("index.php?act=attachment_types");
		}
	}


	
	stdhead('Attachment Types'." - ".'Edit Attachment Type');
	
	
	
	echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
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
	
	
	
	
	
	
	
	
	
	

	$sub_tabs['edit_attachment_type'] = array(
		'title' => 'Edit Attachment Type',
		'link' => "index.php?act=attachment_types&amp;action=edit&amp;atid={$attachment_type['atid']}",
		'description' => 'You have the ability to control the extension, MIME type, maximum size and show a small MIME type for this attachment type'
	);

	output_nav_tabs($sub_tabs, 'edit_attachment_type');

	
	
	
	echo '<form action="index.php?act=attachment_types&amp;action=edit&amp;atid='.$attachment_type['atid'].'" method="post" id="add">
          <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
	
	

	if($errors)
	{
		switch($mybb->input['groups'])
		{
			case 'all':
				$mybb->input['groups'] = -1;
				break;
			case 'custom':
				$mybb->input['groups'] = implode(',', (array)$mybb->input['select']['groups']);
				break;
			default:
				$mybb->input['groups'] = '';
				break;
		}

		switch($mybb->input['forums'])
		{
			case 'all':
				$mybb->input['forums'] = -1;
				break;
			case 'custom':
				$mybb->input['forums'] = implode(',', (array)$mybb->input['select']['forums']);
				break;
			default:
				$mybb->input['forums'] = '';
				break;
		}
	
		output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $attachment_type);
	}

	if(empty($mybb->input['groups']))
	{
		$mybb->input['groups'] = '';
	}

	if(empty($mybb->input['forums']))
	{
		$mybb->input['forums'] = '';
	}

	// PHP settings
	$upload_max_filesize = @ini_get('upload_max_filesize');
	$post_max_size = @ini_get('post_max_size');
	$limit_string = '';
	if($upload_max_filesize || $post_max_size)
	{
		$limit_string = '<br /><br />'.$lang->limit_intro;
		if($upload_max_filesize)
		{
			$limit_string .= '<br />'.sprintf('Upload Max File Size: '.$upload_max_filesize.'');
		}
		if($post_max_size)
		{
			$limit_string .= '<br />'.sprintf('Max Post Size: '.$post_max_size.'');
		}
	}

	$selected_values = '';
	if($mybb->input['groups'] != '' && $mybb->input['groups'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('groups'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['groups'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['groups'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$groups_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"all\" {$group_checked['all']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>All groups</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"custom\" {$group_checked['custom']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>Select groups</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"groups_forums_groups_custom\" class=\"groups_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>Groups:</small></td>
					<td>".generate_group_select('select[groups][]', $selected_values, array('id' => 'groups', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"groups\" value=\"none\" {$group_checked['none']} class=\"groups_forums_groups_check\" onclick=\"checkAction('groups');\" style=\"vertical-align: middle;\" /> <strong>None</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('groups');
	</script>";

	$selected_values = '';
	if($mybb->input['forums'] != '' && $mybb->input['forums'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('forums'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$forum_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['forums'] == -1)
	{
		$forum_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['forums'] != '')
	{
		$forum_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$forum_checked['none'] = 'checked="checked"';
	}

	$forums_select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"all\" {$forum_checked['all']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>All Forums</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"custom\" {$forum_checked['custom']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>Select forums</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"forums_forums_groups_custom\" class=\"forums_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>Forums:</small></td>
					<td>".generate_forum_select('select[forums][]', $selected_values, array('id' => 'forums', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forums\" value=\"none\" {$forum_checked['none']} class=\"forums_forums_groups_check\" onclick=\"checkAction('forums');\" style=\"vertical-align: middle;\" /> <strong>None</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('forums');
	</script>";

	$mybb->input['forcedownload'] = $mybb->get_input('forcedownload', MyBB::INPUT_INT);

	
	
	$naame = generate_text_box('name', $mybb->input['name'], array('id' => 'name'));
	
	$filee_extension = generate_text_box('extension', $mybb->input['extension'], array('id' => 'extension'));
	
	$mimmes_type = generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype'));
	
	$maxx_size = generate_numeric_field('maxsize', $mybb->input['maxsize'], array('id' => 'maxsize', 'min' => 0));
	
	$attachs_icon = generate_text_box('icon', $mybb->input['icon'], array('id' => 'icon'));
	
	$enaabled = generate_yes_no_radio('enabled', $mybb->input['enabled']);
	
	$for_down = generate_yes_no_radio('forcedownload', $mybb->input['forcedownload']);
	
	$avas = generate_yes_no_radio('avatarfile', $mybb->input['avatarfile']);
	
	
	echo '
	
	<div class="container mt-3">
	
	<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">Edit Attachment Type</div>
    <div class="card-body">';
	
	
	echo '
	
	
	<tr class="first">
			<td class="first"><label for="name">Name</label>
<div class="description">Enter the name of the attachment type</div>
<div class="form_row">

'.$naame.'

</div>
</td>
</tr>

<hr>


<tr class="alt_row">
			<td class="first"><label for="extension">File Extension <em>*</em></label>
<div class="description">file_extension_desc</div>
<div class="form_row">

'.$filee_extension.'

</div>
</td>
		</tr>


<hr>

<tr>
			<td class="first"><label for="mimetype">MIME Type <em>*</em></label>
<div class="description">Enter the MIME type sent by the server when downloading files of this type (<a href=\"http://www.freeformatter.com/mime-types-list.html\">See a list here</a>)</div>
<div class="form_row">

'.$mimmes_type.'

</div>
</td>
		</tr>
		
		
		
		
	<tr class="alt_row">
			<td class="first"><label for="maxsize">Maximum File Size (Kilobytes)</label>
<div class="description">The maximum file size for uploads of this attachment type in Kilobytes (1 MB = 1024 KB)'.$limit_string.'</div>
<div class="form_row">

'.$maxx_size.'

</div>
</td>
		</tr>	
		



<tr>
			<td class="first"><label for="icon">Attachment Icon</label>
<div class="description">If you wish to show a small attachment icon for attachments of this type then enter the path to it here. {theme} will be replaced by the image directory for the viewers theme allowing you to specify per-theme attachment icons</div>
<div class="form_row">

'.$attachs_icon.'

</div>
</td>
		</tr>



<tr class="alt_row">
			<td class="first">
			
			<label for="enabled">Enabled?</label>
			<div class="form_row">
			
			'.$enaabled.'
			
			</div>
</td>
		</tr>
		
		
		

</br>		
<tr>
			</br>
			<td class="first"><label for="forcedownload">Force Download</label>
<div class="description">Enabling this will always force the attachment to be downloaded as a file</div>
<div class="form_row">

'.$for_down.'

</div>
</td>
		</tr>
		
		
		
		
		
		
	</br>	
	<tr id="row_groups" class="alt_row">
			</br>
			<td class="first"><label>Available to groups</label>
			
			<div class="form_row">
			
			'.$groups_select_code.'
	
	
	
	</div>
</td>
		</tr>	
		
		
	<hr>	
		
		
	<tr id="row_forums">
			<td class="first"><label>Available in forums</label>
			
			<div class="form_row">
			'.$forums_select_code.'
			
	</div>

</td>
				</tr>	
		
		
		
		
		
	<tr class="last alt_row">
			<td class="first"><label for="avatarfile">Avatar File</label>
<div class="description">Do you want to allow this attachment type to be used for avatars?</div>
<div class="form_row">

'.$avas.'

</div>
</td>
		</tr>	
		
		
		
		

	
	
	';
	
	 echo "</div></div></div>";
	

	
	echo '
		
		<div class="container mt-3">
		<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Save Attachment Type" class="btn btn-primary"> 
</td></tr>
</div></div>';
	
	
	echo "</form>";
	

	stdfoot();
}

if($mybb->input['action'] == "delete")
{
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=config-attachment_types");
	}

	$query = $db->simple_select("attachtypes", "*", "atid='".$mybb->get_input('atid', MyBB::INPUT_INT)."'");
	$attachment_type = $db->fetch_array($query);

	if(!$attachment_type['atid'])
	{
		flash_message($lang->error_invalid_attachment_type, 'error');
		admin_redirect("index.php?module=config-attachment_types");
	}

	$plugins->run_hooks("admin_config_attachment_types_delete");

	if($mybb->request_method == "post")
	{
		$db->delete_query("attachtypes", "atid='{$attachment_type['atid']}'");

		$plugins->run_hooks("admin_config_attachment_types_delete_commit");

		$cache->update_attachtypes();

		// Log admin action
		log_admin_action($attachment_type['atid'], $attachment_type['extension']);

		flash_message($lang->success_attachment_type_deleted, 'success');
		admin_redirect("index.php?module=config-attachment_types");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-attachment_types&amp;action=delete&amp;atid={$attachment_type['atid']}", 'Are you sure you wish to delete this attachment type?');
	}
}

if($mybb->input['action'] == 'toggle_status')
{
	//if(!verify_post_check($mybb->get_input('my_post_key')))
	//{
	//	flash_message($lang->invalid_post_verify_key2, 'error');
	//	admin_redirect('index.php?act=attachment_types');
	//}

	$atid = $mybb->get_input('atid', MyBB::INPUT_INT);

	$query = $db->simple_select('attachtypes', '*', "atid='{$atid}'");
	$attachment_type = $db->fetch_array($query);

	if(!$attachment_type['atid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect('index.php?act=attachment_types');
	}

	$plugins->run_hooks('admin_config_attachment_types_toggle_status');

	$update_array = array('enabled' => 1);
	$phrase = 'success_activated_attachment_type';
	if($attachment_type['enabled'] == 1)
	{
		$update_array['enabled'] = 0;
		$phrase = $lang->success_deactivated_attachment_type;
	}

	$plugins->run_hooks('admin_config_attachment_types_toggle_status_commit');

	$db->update_query('attachtypes', $update_array, "atid='{$atid}'");

	$cache->update_attachtypes();

	// Log admin action
	//log_admin_action($atid, $attachment_type['extension'], $update_array['enabled']);

	flash_message($phrase, 'success');
	admin_redirect('index.php?act=attachment_types');
}

if(!$mybb->input['action'])
{
	
	
	stdhead('Attachment Types');
	
	
	echo "	<link rel=\"stylesheet\" href=\"templates/forum.css?ver=1813\" type=\"text/css\" />\n";
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

	

	$sub_tabs['attachment_types'] = array(
		'title' => 'Attachment Types',
		'link' => "index.php?act=attachment_types",
		'description' => 'Here you can create and manage attachment types which define which types of files users can attach to posts. Note: Disabling an attachment type will make existing attachments of this type inaccessible'
	);
	$sub_tabs['add_attachment_type'] = array(
		'title' => 'Add New Attachment Type',
		'link' => "index.php?act=attachment_types&amp;action=add",
	);

	$plugins->run_hooks("admin_config_attachment_types_start");

	output_nav_tabs($sub_tabs, 'attachment_types');

	$query = $db->simple_select("attachtypes", "COUNT(atid) AS attachtypes");
	$total_rows = $db->fetch_field($query, "attachtypes");

	$pagenum = $mybb->get_input('page', MyBB::INPUT_INT);
	if($pagenum)
	{
		$start = ($pagenum - 1) * 20;
		$pages = ceil($total_rows / 20);
		if($pagenum > $pages)
		{
			$start = 0;
			$pagenum = 1;
		}
	}
	else
	{
		$start = 0;
		$pagenum = 1;
	}
	
	
	echo  '<div class="container mt-3">
            <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     Attachment Types
	      </div>
	       </div>
	    </div>';
		
	

    echo '
        <div class="container mt-3">
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Extension</th>
        <th>MIME Type</th>
        <th>Enabled</th>
		<th>Maximum Size</th>
		<th>Controls</th>
      </tr>
    </thead>';	
		
	

	$query = $db->simple_select("attachtypes", "*", "", array('limit_start' => $start, 'limit' => 20, 'order_by' => 'extension'));
	while($attachment_type = $db->fetch_array($query))
	{
		// Just show default icons in ACP
		$attachment_type['icon'] = htmlspecialchars_uni(str_replace("{theme}", "images", $attachment_type['icon']));
		if(my_validate_url($attachment_type['icon'], true))
		{
			$image = $attachment_type['icon'];
		}
		else
		{
			$image = "../".$attachment_type['icon'];
		}

		if(!$attachment_type['icon'] || $attachment_type['icon'] == "pic/attachtypes/")
		{
			$attachment_type['icon'] = "&nbsp;";
		}
		else
		{
			$attachment_type['name'] = htmlspecialchars_uni($attachment_type['name']);
			$attachment_type['icon'] = "<img src=\"{$image}\" title=\"{$attachment_type['name']}\" alt=\"\" />";
		}

		if($attachment_type['enabled'])
		{
			$phrase = 'Disable';
			$icon = "on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}";
		}
		else
		{
			$phrase = 'Enable';
			$icon = "off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}";
		}

		$attachment_type['extension'] = htmlspecialchars_uni($attachment_type['extension']);


		$options_link = '

<i class="fa-solid fa-gear"></i> &nbsp;Manage Attachment Types
  <table>
			  <tr>
				<td class="subheader2">
				<a href="index.php?act=attachment_types&amp;action=edit&amp;atid='.$attachment_type['atid'].'">Edit</a>
	
				</td>
			  </tr>
			  
			  
			  <tr>
				<td class="subheader2">
				<a href=index.php?act=attachment_types&amp;action=toggle_status&amp;atid='.$attachment_type['atid'].'&amp;my_post_key='.$mybb->post_code.'">'.$phrase.'</a>
	
				</td>
			  </tr>
			  
			  
			  
			  
			  
		            			  
</table>';	

		

		
		
		echo '
		
		<tr class="first">
			<td class="first" width="1">'.$attachment_type['icon'].'
			<strong>'.$attachment_type['extension'].'</strong></td>
			<td>'.htmlspecialchars_uni($attachment_type['mimetype']).'</td>
			
			<td class="align_center alt_col">
			<img src="images/bullet_'.$icon.'" alt="()" title="" style="vertical-align: middle;" />
			</td>
			
			<td class="align_center">'.mksize($attachment_type['maxsize']*1024).'</td>
			
			
			
			
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
            </td>
	    </tr>';
		
	
		
		
		
	}
	
	
	echo '</table></div></div>';

	//if($table->num_rows() == 0)
	//{
	//	$table->construct_cell('no_attachment_types', array('colspan' => 6));
	//	$table->construct_row();
	//}
	

	//$table->output('Attachment Types');

	
	echo '<div class="container mt-3">';
	echo "<br />".draw_admin_pagination($pagenum, "20", $total_rows, "index.php?act=attachment_types&amp;page={page}");
	echo '</div>';

	stdfoot();
}
