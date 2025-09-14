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




//$page->add_breadcrumb_item('Banning', "index.php?act=banning");

$plugins->run_hooks("admin_config_banning_begin");

$mybb->input['filter'] = $mybb->get_input('filter');

if($mybb->input['action'] == "add" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_config_banning_add");

	if(!trim($mybb->input['filter']))
	{
		$errors[] = 'You did not enter a value to ban';
	}

	$query = $db->simple_select("banfilters", "fid", "filter = '".$db->escape_string($mybb->input['filter'])."' AND type = '".$mybb->get_input('type', MyBB::INPUT_INT)."'");
	if($db->num_rows($query))
	{
		$errors[] = 'The filter you entered is already banned';
	}

	if(!$errors)
	{
		$new_filter = array(
			"filter" => $db->escape_string($mybb->input['filter']),
			"type" => $mybb->get_input('type', MyBB::INPUT_INT),
			"dateline" => TIMENOW
		);
		$fid = $db->insert_query("banfilters", $new_filter);

		$plugins->run_hooks("admin_config_banning_add_commit");

		if($mybb->input['type'] == 1)
		{
			$cache->update_bannedips();
		}
		else if($mybb->input['type'] == 3)
		{
			$cache->update_bannedemails();
		}

		// Log admin action
		//log_admin_action($fid, $mybb->input['filter'], (int)$mybb->input['type']);

		if($mybb->input['type'] == 1)
		{
			flash_message('success_ip_banned', 'success');
			admin_redirect("index.php?act=banning");
		}
		else if($mybb->input['type'] == 2)
		{
			flash_message('success_username_disallowed', 'success');
			admin_redirect("index.php?act=banning&type=usernames");
		}
		else if($mybb->input['type'] == 3)
		{
			flash_message('success_email_disallowed', 'success');
			admin_redirect("index.php?act=banning&type=emails");
		}
	}
	else
	{
		if($mybb->input['type'] == 1)
		{
			$mybb->input['type'] = "ips";
		}
		else if($mybb->input['type'] == 2)
		{
			$mybb->input['type'] = "usernames";
		}
		else if($mybb->input['type'] == 3)
		{
			$mybb->input['type'] = "emails";
		}
		$mybb->input['action'] = '';
	}
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("banfilters", "*", "fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'");
	$filter = $db->fetch_array($query);

	// Does the filter not exist?
	if(!$filter)
	{
		flash_message($lang->error_invalid_filter, 'error');
		admin_redirect("index.php?act=banning");
	}

	$plugins->run_hooks("admin_config_banning_delete");

	if($filter['type'] == 3)
	{
		$type = "emails";
	}
	else if($filter['type'] == 2)
	{
		$type = "usernames";
	}
	else
	{
		$type = "ips";
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?act=banning&type={$type}");
	}

	if($mybb->request_method == "post")
	{
		// Delete the ban filter
		$db->delete_query("banfilters", "fid='{$filter['fid']}'");

		$plugins->run_hooks("admin_config_banning_delete_commit");

		// Log admin action
		//log_admin_action($filter['fid'], $filter['filter'], (int)$filter['type']);

		// Banned IP? Rebuild banned IP cache
		if($filter['type'] == 1)
		{
			$cache->update_bannedips();
		}
		else if($filter['type'] == 3)
		{
			$cache->update_bannedemails();
		}

		flash_message('success_ban_deleted', 'success');
		admin_redirect("index.php?act=banning&type={$type}");
	}
	else
	{
		$page->output_confirm_action("index.php?act=banning&amp;action=delete&amp;fid={$filter['fid']}", $lang->confirm_ban_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_banning_start");

	switch($mybb->get_input('type'))
	{
		case "emails":
			$type = "3";
			$title = 'Disallowed Email Addresses';
			break;
		case "usernames":
			$type = "2";
			$title = 'Disallowed Usernames';
			break;
		default:
			$type = "1";
			$title = 'Banned IP Addresses';
			$mybb->input['type'] = "ips";
	}

	//$page->output_header($title);
	
	stdhead($title);
	
	
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
	

	

	$sub_tabs['ips'] = array(
		'title' => 'Banned IPs',
		'link' => "index.php?act=banning",
		'description' => 'Here you can manage IP addresses which are banned from accessing your board'
	);

	$sub_tabs['users'] = array(
		'title' => 'Banned Accounts',
		'link' => "index.php?act=banning2"
	);

	$sub_tabs['usernames'] = array(
		'title' => 'Disallowed Usernames',
		'link' => "index.php?act=banning&amp;type=usernames",
		'description' => 'Here you manage a list of usernames which cannot be registered or used by users. This feature is also particularly useful for reserving usernames'
	);

	$sub_tabs['emails'] = array(
		'title' => 'Disallowed Email Addresses',
		'link' => "index.php?act=banning&amp;type=emails",
		'description' => 'Here you manage a list of email addresses which cannot be registered or used by users'
	);

	output_nav_tabs($sub_tabs, $mybb->input['type']);

	if($errors)
	{
		output_inline_error($errors);
	}

	$query = $db->simple_select("banfilters", "COUNT(fid) AS filter", "type='{$type}'");
	$total_rows = $db->fetch_field($query, "filter");

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

	//$form = new Form("index.php?act=banning&amp;action=add", "post", "add");
	
	
	echo '
	<form action="index.php?act=banning&amp;action=add" method="post" id="add">
    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';

	if($mybb->input['type'] == "usernames")
	{
		//$form_container = new FormContainer();
		//$form_container->output_row('Username'." <em>*</em>", 'Note: To indicate a wild card match, use *', $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		//$buttons[] = $form->generate_submit_button('Disallow Username');
		
		
		
		
		echo '
	          
	      <div class="container mt-3">
		  
		  <div class="card">
            <div class="card-header rounded-bottom text-19 fw-bold">Add a Disallowed Username</div>
          <div class="card-body">';
		
		
		echo '
		
		<tr class="first">
			<td class="first"><label for="filter">Username <em>*</em></label>
<div class="description">Note: To indicate a wild card match, use *</div>
<div class="form_row"><input type="text" name="filter" value="" class="form-control" id="filter"></div>
</td>
		</tr>
		
		';
		
		echo '
	</div>
	</div>
	
	';
	
	
	
	
	echo'
	</br>
	
	
    <div class="card-footer text-center">
	<tr>
	<td colspan=3 align=center>
	
	<input type="submit" class="btn btn-primary" value="Disallow Username"> 
	
	</td>
	</tr>
	</div>
	</div>
	
	';

		
		
	}
	else if($mybb->input['type'] == "emails")
	{
		//$form_container = new FormContainer();
		//$form_container->output_row('Email Address'." <em>*</em>", 'Note: To indicate a wild card match, use *', $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		//$buttons[] = $form->generate_submit_button('Disallow Email Address');
		
		
		
		echo '
	          
	      
		  <div class="container mt-3">
		  
		  <div class="card">
            <div class="card-header rounded-bottom text-19 fw-bold">Add a Disallowed Email Address</div>
          <div class="card-body">';
		
		
		echo '
		
		<tr class="first">
			<td class="first"><label for="filter">Email Address <em>*</em></label>
<div class="description">Note: To indicate a wild card match, use *</div>
<div class="form_row"><input type="text" name="filter" value="" class="form-control" id="filter"></div>
</td>
		</tr>';
		
		
		echo '
	</div>
	</div>';
	
	
	
	echo'
	</br>
    <div class="card-footer text-center">
	<tr>
	<td colspan=3 align=center>
	
	<input type="submit" class="btn btn-primary" value="Disallow Email Address"> 
	
	</td>
	</tr>
	</div>';
		
		
		
		
		
		
		
		
		
		
	}
	else
	{
		//$form_container = new FormContainer();
		//$form_container->output_row('ip_address'." <em>*</em>", 'ip_address_desc', $form->generate_text_box('filter', $mybb->input['filter'], array('id' => 'filter')), 'filter');
		//$buttons[] = $form->generate_submit_button('ban_ip_address');
		
		
		
		
		echo '
	          
	      <div class="container mt-3">
		  
		  <div class="card">
            <div class="card-header rounded-bottom text-19 fw-bold">Ban an IP Address</div>
          <div class="card-body">';
		  
		  
		echo '
		
		<tr class="first">
			<td class="first"><label for="filter">IP Address <em>*</em></label>
<div class="description">Note: To ban a range of IP addresses use * (Ex: 127.0.0.*) or CIDR notation (Ex: 127.0.0.0/8)</div>
<div class="form_row">

<input type="text" name="filter" value="" class="form-control" id="filter">

</div>
</td>
		</tr>
		
		';  
		
		
		echo '
	</div>
	</div>';
		
		

		
		
		echo'
	</br>
    <div class="card-footer text-center">
	<tr>
	<td colspan=3 align=center>
	
	<input type="submit" class="btn btn-primary" value="Ban IP Address"> 
	
	</td>
	</tr>
	</div>';
		
		
  
		
		
		
		
	}

	//$form_container->end();
	//echo generate_hidden_field("type", $type);
	
	echo '<input type="hidden" name="type" value="'.$type.'" />';
	
	//$form->output_submit_wrapper($buttons);
	//$form->end();
	echo "</form>";

	echo '<br />';

	//$table = new Table;
	if($mybb->input['type'] == "usernames")
	{
		//$table->construct_header('Username');
		//$table->construct_header('Date Disallowed', array("class" => "align_center", "width" => 200));
		//$table->construct_header('Last Attempted Use', array("class" => "align_center", "width" => 200));
		
		
		
		
		echo '
		
		
		<div class="container mt-3">
		
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  Disallowed Usernames
	      </div>
	   </div>
	
<div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Username</th>
        <th>Date Disallowed</th>
        <th>Last Attempted Use</th>
        <th>Controls</th>

      </tr>
    </thead>';
		
		
		
		
		
		
		
		
	}
	else if($mybb->input['type'] == "emails")
	{
		//$table->construct_header('Email Address');
		//$table->construct_header('Date Disallowed', array("class" => "align_center", "width" => 200));
		//$table->construct_header('Last Attempted Use', array("class" => "align_center", "width" => 200));
		
		
		
		
		echo '
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  Disallowed Email Addresses
	      </div>
	   </div>
		
   <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Email Address</th>
        <th>Date Disallowed</th>
        <th>Last Attempted Use</th>
        <th>Controls</th>

      </tr>
    </thead>';
		
		
		
		
		
		
		
		
		
		
	}
	else
	{

		echo '<div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>IP Address</th>
        <th>Ban Date</th>
        <th>Last Access</th>
        <th>Controls</th>

      </tr>
    </thead>';
		
		
		
	}
	//$table->construct_header('Controls', array("width" => 1));

	$query = $db->simple_select("banfilters", "*", "type='{$type}'", array('limit_start' => $start, 'limit' => 20, "order_by" => "filter", "order_dir" => "asc"));
	while($filter = $db->fetch_array($query))
	{
		$filter['filter'] = htmlspecialchars_uni($filter['filter']);

		if($filter['lastuse'] > 0)
		{
			$last_use = my_datee('relative', $filter['lastuse']);
		}
		else
		{
			$last_use = 'Never';
		}

		if($filter['dateline'] > 0)
		{
			$date = my_datee('relative', $filter['dateline']);
		}
		else
		{
			$date = 'na';
		}

		//$table->construct_cell($filter['filter']);
		//$table->construct_cell($date, array("class" => "align_center"));
		//$table->construct_cell($last_use, array("class" => "align_center"));
		//$table->construct_cell("<a href=\"index.php?module=config-banning&amp;action=delete&amp;fid={$filter['fid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_ban_deletion}');\"><img src=\"styles/{$page->style}/images/icons/delete.png\" title=\"{$lang->delete}\" alt=\"{$lang->delete}\" /></a>", array("class" => "align_center"));
		//$table->construct_row();
		
		
		
		$za = "<a href=\"index.php?act=banning&amp;action=delete&amp;fid={$filter['fid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{confirm_ban_deletion}');\">
		<i class=\"fa-solid fa-trash-can fa-lg\" style=\"color: #eb0f0f;\" alt=\"Delete\" title=\"Delete\"></i></a>";
		
		
		 echo '<tr class=rowhead><td>' . $filter['filter'] . '<td>
		<span style="float: right;"></span>
		' . $date . '</td><td>' . $last_use . '</td><td align=\'center\'>
		'.$za.'
		
		
		</td></tr>';
		
		
		
	}
	
	echo '</table></div>';

	if($db->num_rows($query) == 0)
	{
		echo 'There are no bans currently set at this time';
		//$table->construct_row();
	}

	//$table->output($title);

	//echo "<br />".draw_admin_pagination($pagenum, "20", $total_rows, "index.php?module=config-banning&amp;type={$mybb->get_input('type')}&amp;page={page}");

	//$page->output_footer();
	
	stdfoot();
}

