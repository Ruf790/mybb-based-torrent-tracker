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


require_once INC_PATH.'/functions_mkprettytime.php';




//$page->add_breadcrumb_item('Banning', "index.php?act=banning2");


$sub_tabs['ips'] = array(
	'title' => 'Banned IPs',
	'link' => "index.php?act=banning",
);

$sub_tabs['bans'] = array(
	'title' => 'Banned Accounts',
	'link' => "index.php?act=banning2",
	'description' => $lang->banned_accounts_desc
);

$sub_tabs['usernames'] = array(
	'title' => 'Disallowed Usernames',
	'link' => "index.php?act=banning&amp;type=usernames",
);

$sub_tabs['emails'] = array(
	'title' => 'Disallowed Email Addresses',
	'link' => "index.php?act=banning&amp;type=emails",
);

// Fetch banned groups
$query = $db->simple_select("usergroups", "gid,title", "isbannedgroup=1", array('order_by' => 'title'));
$banned_groups = array();
while($group = $db->fetch_array($query))
{
	$banned_groups[$group['gid']] = $group['title'];
}

// Fetch ban times
$ban_times = fetch_ban_times();

$plugins->run_hooks("admin_user_banning_begin");

if($mybb->input['action'] == "prune")
{
	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?act=banning2");
	}

	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);

	if(!$ban)
	{
		flash_message('error_invalid_ban', 'error');
		admin_redirect("index.php?act=banning2");
	}

	$user = get_user($ban['uid']);

	if(!$user || (is_super_admin($user['id']) && ($CURUSER['id'] != $CURUSER['id'] && !is_super_admin($CURUSER['id']))))
	{
		flash_message('cannot_perform_action_super_admin_general', 'error');
		admin_redirect("index.php?act=banning2");
	}

	$plugins->run_hooks("admin_user_banning_prune");

	if($mybb->request_method == "post")
	{
		require_once INC_PATH."/class_moderation.php";
		$moderation = new Moderation();

		$query = $db->simple_select("tsf_threads", "tid", "uid='{$user['id']}'");
		while($thread = $db->fetch_array($query))
		{
			$moderation->delete_thread($thread['tid']);
		}

		$query = $db->simple_select("tsf_posts", "pid", "uid='{$user['id']}'");
		while($post = $db->fetch_array($query))
		{
			$moderation->delete_post($post['pid']);
		}

		$plugins->run_hooks("admin_user_banning_prune_commit");

		$cache->update_reportedcontent();

		// Log admin action
		//log_admin_action($user['uid'], $user['username']);

		flash_message('success_pruned', 'success');
		admin_redirect("index.php?act=banning2");
	}
	else
	{
		$page->output_confirm_action("index.php?act=banning2&amp;action=prune&amp;uid={$user['id']}", 'confirm_prune');
	}
}

if($mybb->input['action'] == "lift")
{
	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?act=banning2");
	}

	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);

	if(!$ban)
	{
		flash_message($lang->error_invalid_ban, 'error');
		admin_redirect("index.php?act=banning2");
	}

	$user = get_user($ban['uid']);

	if(!$user || (is_super_admin($user['uid']) && ($mybb->user['uid'] != $user['uid'] && !is_super_admin($mybb->user['uid']))))
	{
		flash_message($lang->cannot_perform_action_super_admin_general, 'error');
		admin_redirect("index.php?module=user-banning");
	}

	$plugins->run_hooks("admin_user_banning_lift");

	if($mybb->request_method == "post")
	{
		$updated_group = array(
			'usergroup' => $ban['oldgroup'],
			'additionalgroups' => $db->escape_string($ban['oldadditionalgroups']),
			'displaygroup' => $ban['olddisplaygroup']
		);
		$db->delete_query("banned", "uid='{$ban['uid']}'");

		$plugins->run_hooks("admin_user_banning_lift_commit");

		$db->update_query("users", $updated_group, "id='{$ban['uid']}'");

		//$cache->update_moderators();

		// Log admin action
		//log_admin_action($ban['uid'], $user['username']);

		flash_message('The selected ban has been lifted successfully', 'success');
		admin_redirect("index.php?act=banning2");
	}
	else
	{
		$page->output_confirm_action("index.php?module=user-banning&amp;action=lift&amp;uid={$ban['uid']}", $lang->confirm_lift_ban);
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("banned", "*", "uid='{$mybb->input['uid']}'");
	$ban = $db->fetch_array($query);

	if(!$ban)
	{
		flash_message($lang->error_invalid_ban, 'error');
		admin_redirect("index.php?act=banning2");
	}

	$user = get_user($ban['uid']);

	$plugins->run_hooks("admin_user_banning_edit");

	if($mybb->request_method == "post")
	{
		if(empty($ban['uid']))
		{
			$errors[] = 'The username you have entered is invalid and does not exist';
		}
		// Is the user we're trying to ban a super admin and we're not?
		else if(is_super_admin($ban['uid']) && !is_super_admin($ban['uid']))
		{
			$errors[] = 'You do not have permission to ban this user';
		}

		if($ban['uid'] == $CURUSER['id'])
		{
			$errors[] = 'You cannot ban yourself';
		}

		// No errors? Update
		if(!$errors)
		{
			// Ban the user
			if($mybb->input['bantime'] == '---')
			{
				$lifted = 0;
			}
			else
			{
				$lifted = ban_date2timestamp($mybb->input['bantime'], $ban['dateline']);
			}

			$reason = my_substr($mybb->input['reason'], 0, 255);

			if(count($banned_groups) == 1)
			{
				$group = array_keys($banned_groups);
				$mybb->input['usergroup'] = $group[0];
			}

			$update_array = array(
				'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
				'dateline' => TIMENOW,
				'bantime' => $db->escape_string($mybb->input['bantime']),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($reason)
			);

			$db->update_query('banned', $update_array, "uid='{$ban['uid']}'");

			// Move the user to the banned group
			$update_array = array(
				'usergroup' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
				'displaygroup' => 0,
				'additionalgroups' => '',
			);
			$db->update_query('users', $update_array, "id = {$ban['uid']}");

			$plugins->run_hooks("admin_user_banning_edit_commit");

			// Log admin action
			//log_admin_action($ban['uid'], $user['username']);

			flash_message($lang->success_ban_updated, 'success');
			admin_redirect("index.php?act=banning2");
		}
	}
	
	
	stdhead('Edit Ban');
	
	
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
	
	
	
	
	
	
	
	
	
	

	$sub_tabs = array();
	$sub_tabs['edit'] = array(
		'title' => 'Edit Ban',
		'description' => 'Here you can edit the reason and length of currently banned users'
	);
	
	
	output_nav_tabs($sub_tabs, "edit");

	
	
	echo '<form action="index.php?act=banning2&amp;action=edit&amp;uid='.$ban['uid'].'" method="post">
    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
	

	
	
	if($errors)
	{
		output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $ban);
	}

	if(!empty($ban['gid']))
	{
		$mybb->input['usergroup'] = $ban['gid'];
	}
	else if(!empty($user['usergroup']))
	{
		$mybb->input['usergroup'] = $user['usergroup'];
	}
	else
	{
		$mybb->input['usergroup'] = 0;
	}


	
	echo '
	          
	      <div class="container mt-3">
		  
		  <div class="card">
            <div class="card-header rounded-bottom text-19 fw-bold">Edit Ban</div>
          <div class="card-body">';
	

	
	 echo '
	   <tr class="first">
			<td class="first"><label for="username">Username <em>*</em></label>
<div class="form_row">

'.htmlspecialchars_uni($user['username']).'

</div>
</td>
</tr>';
	   
	
	 echo '
	   
	   
	   <tr class="alt_row">
			<td class="first">
			<label for="reason">Ban Reason</label><div class="form_row">
			
			<textarea name="reason" class="form-control form-control-sm border" id="reason" maxlength="255" rows="5" cols="45">'.$mybb->input['reason'].'</textarea></div>
			
			</div>
          </td>
		</tr>
	   
	   
	   ';

	
	if(count($banned_groups) > 1)
	{
		
		
		$ban_groupss = generate_select_box('usergroup', $banned_groups, $mybb->input['usergroup'], array('id' => 'usergroup'));
		
		echo '
		<tr>
		<td class="first"><label for="usergroup">Banned Group <em>*</em></label>
         <div class="description">In order for this user to be banned they must be moved to a banned group.</div>
           <div class="form_row">
             '.$ban_groupss.'
       </div>
       </td>
	   </tr>';
		
		
		
		
		
		
		
		
		
	}

	if($mybb->input['bantime'] == 'perm' || $mybb->input['bantime'] == '' || $mybb->input['lifted'] == 'perm' ||$mybb->input['lifted'] == '')
	{
		$mybb->input['bantime'] = '---';
		$mybb->input['lifted'] = '---';
	}

	foreach($ban_times as $time => $period)
	{
		if($time != '---')
		{
			$friendly_time = my_datee("D, jS M Y @ {$timeformat}", ban_date2timestamp($time));
			$period = "{$period} ({$friendly_time})";
		}
		$length_list[$time] = $period;
	}
	
	
	echo '
	
	<tr class="last">
			<td class="first"><label for="bantime">Ban Length <em>*</em></label>
			<div class="form_row">
			
'.generate_select_box('bantime', $length_list, $mybb->input['bantime'], array('id' => 'bantime')).'

</div>
</td>
		</tr>';
		
		
	echo '
	</div>
	</div>
	</div>
	';	
	
	
	
	echo '

<div class="container mt-3">
<div class="card-footer text-center">
	<tr>
	<td colspan=3 align=center>
	
	  <input type="submit" value="Update Ban" class="btn btn-primary"> 
	  
	  
	
	</td>
	</tr>
	</div>
	</div>
	';
	
	
	echo "</form>";
	

	stdfoot();
}

if(!$mybb->input['action'])
{
	$where_sql_full = $where_sql = '';

	$plugins->run_hooks("admin_user_banning_start");

	if($mybb->request_method == "post")
	{
		$options = array(
			'fields' => array('username', 'usergroup', 'additionalgroups', 'displaygroup')
		);

		$user = get_user_by_username($mybb->input['username'], $options);

		// Are we searching a user?
		if(is_array($user) && isset($mybb->input['search']))
		{
			$where_sql = 'uid=\''.(int)$user['id'].'\'';
			$where_sql_full = 'WHERE b.uid=\''.(int)$user['id'].'\'';
		}
		else
		{
			if(!$user)
			{
				$errors[] = 'The username you have entered is invalid and does not exist';
			}
			// Is the user we're trying to ban a super admin and we're not?
			else if(is_super_admin($user['id']) && !is_super_admin($CURUSER['id']))
			{
				$errors[] = 'You do not have permission to ban this user';
			}
			else
			{
				$query = $db->simple_select("banned", "uid", "uid='{$user['id']}'");
				if($db->fetch_field($query, "uid"))
				{
					$errors[] = 'This user already belongs to a banned group and cannot be added to a new one';
				}

				// Get PRIMARY usergroup information
				$usergroups = $cache->read("usergroups");
				if(!empty($usergroups[$user['usergroup']]) && $usergroups[$user['usergroup']]['isbannedgroup'] == 1)
				{
					$errors[] = 'This user already belongs to a banned group and cannot be added to a new one';
				}

				if($user['id'] == $CURUSER['id'])
				{
					$errors[] = 'You cannot ban yourself';
				}
			}

			// No errors? Insert
			if(!$errors)
			{
				// Ban the user
				if($mybb->input['bantime'] == '---')
				{
					$lifted = 0;
				}
				else
				{
					$lifted = ban_date2timestamp($mybb->input['bantime']);
				}

				$reason = my_substr($mybb->input['reason'], 0, 255);

				if(count($banned_groups) == 1)
				{
					$group = array_keys($banned_groups);
					$mybb->input['usergroup'] = $group[0];
				}

				$insert_array = array(
					'uid' => $user['id'],
					'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
					'oldgroup' => $user['usergroup'],
					'oldadditionalgroups' => $db->escape_string($user['additionalgroups']),
					'olddisplaygroup' => $user['displaygroup'],
					'admin' => (int)$CURUSER['id'],
					'dateline' => TIMENOW,
					'bantime' => $db->escape_string($mybb->input['bantime']),
					'lifted' => $db->escape_string($lifted),
					'reason' => $db->escape_string($reason)
				);
				$db->insert_query('banned', $insert_array);

				// Move the user to the banned group
				$update_array = array(
					'usergroup' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
					'displaygroup' => 0,
					'additionalgroups' => '',
				);

				$db->delete_query("tsf_forumsubscriptions", "uid = '{$user['id']}'");
				$db->delete_query("tsf_threadsubscriptions", "uid = '{$user['id']}'");

				$plugins->run_hooks("admin_user_banning_start_commit");

				$db->update_query('users', $update_array, "id = '{$user['id']}'");

				// Log admin action
				//log_admin_action($user['uid'], $user['username'], $lifted);

				flash_message($lang->success_banned, 'success');
				admin_redirect("index.php?act=banning2");
			}
		}
	}

	
	
	stdhead('Banned Accounts');
	
	
	
	
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
	
	
	output_nav_tabs($sub_tabs, "bans");
	

	$query = $db->simple_select("banned", "COUNT(*) AS ban_count", $where_sql);
	$ban_count = $db->fetch_field($query, "ban_count");

	$per_page = 20;

	$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
	if($mybb->input['page'] > 0)
	{
		$current_page = $mybb->input['page'];
		$start = ($current_page-1)*$per_page;
		$pages = $ban_count / $per_page;
		$pages = ceil($pages);
		if($current_page > $pages)
		{
			$start = 0;
			$current_page = 1;
		}
	}
	else
	{
		$start = 0;
		$current_page = 1;
	}

	//$pagination = draw_admin_pagination($current_page, $per_page, $ban_count, "index.php?module=user-banning&amp;page={page}");

	
	echo '
	<form action="index.php?act=banning2" method="post">
    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
	

	
	
	
	
	if($errors)
	{
		output_inline_error($errors);
	}

	$mybb->input['username'] = $mybb->get_input('username');
	$mybb->input['reason'] = $mybb->get_input('reason');
	$mybb->input['bantime'] = $mybb->get_input('bantime');

	if(isset($mybb->input['uid']) && empty($mybb->input['username']))
	{
		$user = get_user($mybb->input['id']);
		$mybb->input['username'] = $user['username'];
	}

	if(empty($mybb->input['usergroup']))
	{
		if(!empty($mybb->settings['purgespammerbangroup']))
		{
			$mybb->input['usergroup'] = $mybb->settings['purgespammerbangroup'];
		}
		else if(count($banned_groups))
		{
			$group = array_keys($banned_groups);
			$mybb->input['usergroup'] = $group[0];
		}
		else
		{
			$mybb->input['usergroup'] = 0;
		}
	}

	
	
	
		echo '
	          
	      <div class="container mt-3">
		  
		  <div class="card">
            <div class="card-header rounded-bottom text-19 fw-bold">Ban a User</div>
          <div class="card-body">';
	
	
	   
	   echo '
	   
	   
	   
	   <tr class="first">
			<td class="first"><label for="username">Username <em>*</em></label>
<div class="description">Auto-complete is enabled in this field.</div>
<div class="form_row">


'.generate_text_box('username', $mybb->input['username'], array('id' => 'username')).'

</div>


</td>
		</tr>
	   
	   
	   
	   
	   ';
	   
	   
	   
	   
	   echo '
	   
	   
	   <tr class="alt_row">
			<td class="first">
			<label for="reason">Ban Reason</label><div class="form_row"><textarea name="reason" class="form-control form-control-sm border" id="reason" maxlength="255" rows="5" cols="45"></textarea></div>
          </td>
		</tr>
	   
	   
	   ';
	
	

	
	
	if(count($banned_groups) > 1)
	{
		
		$ban_groups = generate_select_box('usergroup', $banned_groups, $mybb->input['usergroup'], array('id' => 'usergroup'));
		
		echo '
		<tr>
		<td class="first"><label for="usergroup">Banned Group <em>*</em></label>
         <div class="description">In order for this user to be banned they must be moved to a banned group.</div>
           <div class="form_row">
             '.$ban_groups.'
       </div>
       </td>
	   </tr>';
		
		
		
		
		
		
		
	}
	foreach($ban_times as $time => $period)
	{
		if($time != "---")
		{
			$friendly_time = my_datee("D, jS M Y @ {$timeformat}", ban_date2timestamp($time));
			$period = "{$period} ({$friendly_time})";
		}
		$length_list[$time] = $period;
	}
	
	
	
	echo '
	
	<tr class="last">
			<td class="first"><label for="bantime">Ban Length <em>*</em></label>
			<div class="form_row">
			
'.generate_select_box('bantime', $length_list, $mybb->input['bantime'], array('id' => 'bantime')).'

</div>
</td>
		</tr>';
		
		
	echo '
	</div>
	</div>
	</div>
	';	
	
	

	// Autocompletion for usernames
	echo '
	<link rel="stylesheet" href="../scripts/select2/select2.css">
	<script type="text/javascript" src="../scripts/select2/select2.min.js?ver=1804"></script>
	<script type="text/javascript">
	<!--
	$("#username").select2({
		placeholder: "Search for a user",
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

	
	
	
	
	echo '

<div class="container mt-3">
<div class="card-footer text-center">
	<tr>
	<td colspan=3 align=center>
	
	<input type="submit" value="Ban User" class="btn btn-primary"> 
<input type="submit" value="Search for a user" class="btn btn-primary" name="search">  
	
	</td>
	</tr>
	</div>
	</div>
	</div>';
	
	echo "</form>";
	

	echo '<br />';

	

	 echo '
	 
	 <div class="container mt-3">
	 <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  Banned Accounts
	      </div>
	   </div>
	   </div>';
	
	
	
	echo '
	
	<div class="container mt-3">
	<div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>User</th>
        <th>Ban Lifts On</th>
        <th>Time Left</th>
        <th>Controls</th>
		<th>Moderation</th>

      </tr>
    </thead>';
	
	
	
	
	
	
	
	
	
	

	// Fetch bans
	$query = $db->sql_query("
		SELECT b.*, a.username AS adminuser, u.username
		FROM banned b
		LEFT JOIN users u ON (b.uid=u.id)
		LEFT JOIN users a ON (b.admin=a.id)
		{$where_sql_full}
		ORDER BY dateline DESC
		LIMIT {$start}, {$per_page}
	");

	// Get the banned users
	while($ban = $db->fetch_array($query))
	{
		$profile_link = build_profile_link(htmlspecialchars_uni($ban['username']), $ban['uid'], "_blank");
		$ban_date = my_datee($dateformat, $ban['dateline']);
		if($ban['lifted'] == 'perm' || $ban['lifted'] == '' || $ban['bantime'] == 'perm' || $ban['bantime'] == '---')
		{
			$ban_period = 'permenantly';
			$time_remaining = $lifts_on = 'na';
		}
		else
		{
			$ban_period = $lang->for." ".$ban_times[$ban['bantime']];

			$remaining = $ban['lifted']-TIMENOW;
			//time_remaining = nice_time($remaining, array('short' => 1, 'seconds' => false))."";
			
			$time_remaining = mkprettytime($remaining);

			if($remaining < 3600)
			{
				$time_remaining = "<span style=\"color: red;\">{$time_remaining}</span>";
			}
			else if($remaining < 86400)
			{
				$time_remaining = "<span style=\"color: maroon;\">{$time_remaining}</span>";
			}
			else if($remaining < 604800)
			{
				$time_remaining = "<span style=\"color: green;\">{$time_remaining}</span>";
			}

			$lifts_on = my_datee($dateformat, $ban['lifted']);
		}

		if(!$ban['adminuser'])
		{
			if($ban['admin'] == 0)
			{
				$ban['adminuser'] = 'mybb_engine';
			}
			else
			{
				$ban['adminuser'] = $ban['admin'];
			}
		}

		
		
		$ww = sprintf('<strong>'.$profile_link.'</strong><br /><small>Banned by '.htmlspecialchars_uni($ban['adminuser']).' on '.$ban_date.' '.$ban_period.'</small>');
		
		$editss = '<a href="index.php?act=banning2&amp;action=edit&amp;uid='.$ban['uid'].'">
		
		<i class="fa-solid fa-pen-to-square fa-lg" style="color: #0658e5;" alt="Edit" title="Edit"></i>
		</a>';
		
		$liftsss = "<a href=\"index.php?act=banning2&amp;action=lift&amp;uid={$ban['uid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{confirm_lift_ban}');\">
		
		<i class=\"fa-solid fa-trash-can fa-lg\" style=\"color: #eb0f0f;\" alt=\"Lift\" title=\"Lift\"></i>
		
		</a>";
		
		
		$prunes = "<a href=\"index.php?act=banning2&amp;action=prune&amp;uid={$ban['uid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, 'confirm_prune');\">Prune Threads &amp; Posts</a>";
		
		
		
		echo '
		
		
		<tr class="first">
			
			<td class="first">
			'.$ww.'
			</td>
			<td class="align_center alt_col">'.$lifts_on.'</td>
			
			<td class="align_center"><span style="color: maroon;">'.$time_remaining.'</span></td>
			
			<td class="align_center">
			'.$editss.'
			</td>
			
			<td class="align_center">
			
			'.$liftsss.'
			
			</td>
			
			
			<td class="align_center last alt_col">
			
			'.$prunes.'
			
			</td>
			
			
		</tr>
		
		';
		
		
		
		
		
	}
	
	echo '</table></div></div>';

	//if($table->num_rows() == 0)
	//{
		//echo 'You dont have any banned users at the moment';
		//echo '</table></div></div>';
		//$table->construct_row();
	//}
	//$table->output('banned_accounts');
	

	
	echo $pagination;

	stdfoot();
}
