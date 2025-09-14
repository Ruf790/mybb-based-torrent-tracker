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





require_once INC_PATH . '/functions_multipage.php';


// Include our base data handler class
require_once INC_PATH . '/datahandler.php';




$lang->load('user_awaiting_activation');





foreach(array('action', 'do', 'module') as $input)
{
	if(!isset($mybb->input[$input]))
	{
		$mybb->input[$input] = '';
	}
}









$plugins->run_hooks("admin_user_awaiting_activation_begin");

if($mybb->input['action'] == "activate" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_user_awaiting_activation_activate");

	if(!is_array($mybb->input['user']))
	{
		$mybb->input['user'] = array();
	}

	$mybb->input['user'] = array_map('intval', $mybb->input['user']);
	$user_ids = implode(", ", $mybb->input['user']);

	if(empty($user_ids))
	{
		flash_message('no_users_selected', 'error');
		admin_redirect($_this_script_);
	}

	$num_activated = $num_deleted = 0;
	$users_to_delete = array();
	if(!empty($mybb->input['delete'])) // Delete selected user(s)
	{
		require_once INC_PATH.'/datahandlers/user.php';
		$userhandler = new UserDataHandler('delete');

		$query = $db->simple_select("users", "id, ustatus", "id IN ({$user_ids})");
		while($user = $db->fetch_array($query))
		{
			if($user['ustatus'] == 'pending')
			{
				++$num_deleted;
				$users_to_delete[] = (int)$user['id'];
			}
		}

		if(!empty($users_to_delete))
		{
			$userhandler->delete_user($users_to_delete, 1);
		}

		$plugins->run_hooks("admin_user_awaiting_activation_activate_delete_commit");

		// Log admin action
		//log_admin_action('deleted', $num_deleted);

		flash_message('success_users_deleted', 'success');
		admin_redirect($_this_script_);
	}
	else // Activate selected user(s)
	{
		$query = $db->simple_select("users", "id, ustatus, username, email, usergroup", "id IN ({$user_ids})");
		while($user = $db->fetch_array($query))
		{
			++$num_activated;
			if($user['coppauser'])
			{
				$updated_user = array(
					"coppauser" => 0
				);
			}
			else
			{
				$db->delete_query("awaitingactivation", "uid='{$user['id']}'");
			}

			// Move out of awaiting activation if they're in it.
			if($user['ustatus'] == 'pending')
			{
				$updated_user['ustatus'] = 'confirmed';
			}

			$db->update_query("users", $updated_user, "id='{$user['id']}'");

			$message = sprintf($lang->user_awaiting_activation['email_adminactivateaccount'], $user['username'], $SITENAME, $BASEURL); my_mail($user['email'], sprintf($lang->user_awaiting_activation['emailsubject_activateaccount'], $SITENAME), $message);
		}

		$cache->update_awaitingactivation();

		$plugins->run_hooks("admin_user_awaiting_activation_activate_commit");

		// Log admin action
		//log_admin_action('activated', $num_activated);

		flash_message('success_users_activated', 'success');
		admin_redirect($_this_script_);
	}
}
	
if(!$mybb->input['action']) 
{
	$plugins->run_hooks("admin_user_awaiting_activation_start");
	
	
	$query = $db->simple_select("users", "COUNT(*) AS users", "ustatus='pending'");
	$threadcount = $db->fetch_field($query, "users");


	$threadsperpage2 = "20";
	
	if(!$threadsperpage2 || (int)$threadsperpage2 < 1)
	{
		$threadsperpage2 = 20;
	}

	$perpage = $threadsperpage2;
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
		$pages = $threadcount / $perpage;
		$pages = ceil($pages);
		if($page > $pages || $page <= 0)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	if($upper > $threadcount)
	{
		$upper = $threadcount;
	}
	$multipage = multipage($threadcount, $perpage, $page, "" . $_this_script_ ."&amp;page={page}");

	
	

	
	

	stdhead();
	
	
	 echo '<div class="container mt-3">
	'.$multipage.'
    </div>';
	
	
	echo '<form action="' . $_this_script_ . '&action=activate" method="post">
    <input type="hidden" name="my_post_key" value="ac42eb1ce7b575d659aa8b34b1aa0d23" />';

	
	echo '
	
	
	<div class="container mt-3">
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		   Manage Unconfirmed User Accounts (To keep records updated reguarly, all pending accounts will be deleted after X days (X = Setting panel > Cleanupsettings)
	      </div>
	   </div>
	</div>
	

	
	
	
	<div class="container mt-3">
   
  <div class="card">
  
  
  
  
  
  
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th><input type="checkbox" name="allbox" value="1" class="form-check-input" /></th>
		<th>Username</th>
        <th>Registered</th>
        <th>Last Active</th>
        <th>Email</th>
        <th>IP Address</th>
		<th>Type</th>
      </tr>
    </thead>';
	


	$query = $db->sql_query("
		SELECT u.id, u.username, u.added, u.regip, u.lastactive, u.email, a.type AS reg_type, a.validated
		FROM users u
		LEFT JOIN awaitingactivation a ON (a.uid=u.id)
		WHERE u.ustatus='pending'
		ORDER BY u.added DESC
		LIMIT {$start}, {$perpage}
	");
	while($user = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$user['username'] = htmlspecialchars_uni($user['username']);
		$user['profilelink'] = build_profile_link($user['username'], $user['id'], "_blank");
		$user['email'] = htmlspecialchars_uni($user['email']);
		$user['added'] = my_datee('relative', $user['added']);
		$user['lastactive'] = my_datee('relative', $user['lastactive']);

		if($user['reg_type'] == 'r' || $user['reg_type'] == 'b' && $user['validated'] == 0)
		{
			$user['type'] = 'Awaiting Email Activation';
		}
		elseif($user['coppauser'] == 1)
		{
			$user['type'] = 'Awaiting Administrator Activation (COPPA)';
		}
		else
		{
			$user['type'] = 'Awaiting Administrator Activation';
		}

		if(empty($user['regip']))
		{
			$user['regip'] = 'na';
		}
		else
		{
			$user['regip'] = my_inet_ntop($db->unescape_binary($user['regip']));
		}

		
		 echo '<tr>
		 <td><input type="checkbox" name="user['.$user['id'].']" value="'.$user['id'].'" class="form-check-input" /></td>
		 <td>'.$user['profilelink'].'</td>';
		 echo '<td>'.$user['added'].'</td>';
		 echo '<td>'.$user['lastactive'].'</td>';
		 echo '<td>'.$user['email'].'</td>';
		 echo '<td>'.$user['regip'].'</td>';
		 echo '<td>'.$user['type'].'</td>';
         echo '</tr>';
		 
		
		
	}
	
	
	
	
	
	
	
	echo '
	</table>
	</div>
</div>';
	
	
	 
	if ($db->num_rows($query) == 0)
		
	{
		 echo '

<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		There is no unconfirmed user!
	</div>
	 </div>
		</div>';
		
	}
	
	else
	{
		
	 
	 
	 
	 
	 echo '
	 
	
	
	 
	 
     </br>
	 
	 <div class="container mt-3">
	 <div class="card-footer text-center">
<input type="submit" value="Activate Users" class="btn btn-primary" onclick="return confirm(Are you sure you want to activate the selected users?);" /> 
<input type="submit" value="Delete Users" class="btn btn-primary" name="delete" onclick="return confirm(Are you sure you want to delete the selected users?);" />
</div>
</div>
';

    }
		
		
	

	echo '</form>';
	
	
	echo '<div class="container mt-3">
	'.$multipage.'
    </div>';
	
	
	

	stdfoot();
}
