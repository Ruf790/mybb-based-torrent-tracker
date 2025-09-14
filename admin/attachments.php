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



foreach(array('action', 'do', 'module') as $input)
{
	if(!isset($mybb->input[$input]))
	{
		$mybb->input[$input] = '';
	}
}

 
 

//$page->add_breadcrumb_item('attachments', "index.php?act=attachments");

if($mybb->input['action'] == "stats" || $mybb->input['action'] == "orphans" || !$mybb->input['action'])
{
	$sub_tabs['find_attachments'] = array(
		'title' => 'Find Attachments',
		'link' => "index.php?act=attachments",
		'description' => 'Using the attachments search system you can search for specific files users have attached to your forums. Begin by entering some search terms below. All fields are optional and wont be included in the criteria unless they contain a value'
	);

	$sub_tabs['find_orphans'] = array(
		'title' => 'Find Orphaned Attachments',
		'link' => "index.php?act=attachments&amp;action=orphans",
		'description' => 'Orphaned attachments are attachments which are for some reason missing in the database or the file system. This utility will assist you in locating and removing them'
	);

	$sub_tabs['stats'] = array(
		'title' => 'Attachment Statistics',
		'link' => "index.php?act=attachments&amp;action=stats",
		'description' => 'Below are some general statistics for the attachments currently on your forum'
	);
}

$plugins->run_hooks("admin_forum_attachments_begin");

$uploadspath = TSDIR.'/uploads/';

$uploadspath_abs = mk_path_abs22($uploadspath);

$default_perpage = 20;
$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
if(!$perpage)
{
	$perpage = $default_perpage;
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_forum_attachments_delete");

	if(isset($mybb->input['aids']))
	{
		if(!is_array($mybb->input['aids']))
		{
			$mybb->input['aids'] = array($mybb->get_input('aid', MyBB::INPUT_INT));
		}
		else
		{
			$mybb->input['aids'] = array_map("intval", $mybb->input['aids']);
		}
	}
	else
	{
		$mybb->input['aids'] = array();
	}

	if(count($mybb->input['aids']) < 1)
	{
		flash_message('error_nothing_selected', 'error');
		admin_redirect("index.php?act=attachments");
	}

	if($mybb->request_method == "post")
	{
		require_once INC_PATH."/functions_upload.php";

		$query = $db->simple_select("attachments", "aid,pid,posthash,filename", "aid IN (".implode(",", $mybb->input['aids']).")");
		while($attachment = $db->fetch_array($query))
		{
			if(!$attachment['pid'])
			{
				remove_attachment(null, $attachment['posthash'], $attachment['aid']);
				// Log admin action
				//log_admin_action($attachment['aid'], $attachment['filename']);
			}
			else
			{
				remove_attachment($attachment['pid'], null, $attachment['aid']);
				// Log admin action
				//log_admin_action($attachment['aid'], $attachment['filename'], $attachment['pid']);
			}
		}

		$plugins->run_hooks("admin_forum_attachments_delete_commit");

		flash_message('success_deleted', 'success');
		admin_redirect("index.php?act=attachments");
	}
	else
	{
		$aids = array();
		foreach($mybb->input['aids'] as $aid)
		{
			$aids .= "&amp;aids[]=$aid";
		}
		$page->output_confirm_action("index.php?act=attachments&amp;action=delete&amp;aids={$aids}", 'confirm_delete');
	}
}

if($mybb->input['action'] == "stats")
{
	$plugins->run_hooks("admin_forum_attachments_stats");

	$query = $db->simple_select("attachments", "COUNT(*) AS total_attachments, SUM(filesize) as disk_usage, SUM(downloads*filesize) as bandwidthused", "visible='1'");
	$attachment_stats = $db->fetch_array($query);

		//$page->add_breadcrumb_item('Statistics');
		//$page->output_header($lang->stats_attachment_stats);
		
		stdhead('Attachments - Attachment Statistics');
		
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
		
		
		
		
		

	output_nav_tabs($sub_tabs, 'stats');

	if($attachment_stats['total_attachments'] == 0)
	{
		output_inline_error(array('There arent any attachments on your forum yet. Once an attachment is posted you ll be able to access this section'));
		stdfoot();
		exit;
	}


	
	echo '
	
	<div class="container mt-3">
	
	<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">General Statistics</div>
    <div class="card-body">
	
	
    <table>
	
		<tr class="first">
			<td class="first" width="25%"><strong>No. Uploaded Attachments</strong></td>
			<td class="alt_col" width="25%">'.ts_nf($attachment_stats['total_attachments']).'</td>
			<td width="200"><strong>Attachment Space Used</strong></td>
			<td class="last alt_col" width="200">'.mksize($attachment_stats['disk_usage']).'</td>
		</tr>
		<tr class="last alt_row">
			<td class="first" width="25%"><strong>Estimated Bandwidth Usage</strong></td>
			<td class="alt_col" width="25%">'.mksize(round($attachment_stats['bandwidthused'])).'</td>
			<td width="25%"><strong>Average Attachment Size</strong></td>
			<td class="last alt_col" width="25%">'.mksize(round($attachment_stats['disk_usage']/$attachment_stats['total_attachments'])).'</td>
		</tr>
	</table>
	
	
	</div>
	</div>
</div>

';
	
	
	
	
	
	
	
	
	
	
	

	// Fetch the most popular attachments
	echo  '
	<div class="container mt-3">
	
            <div class="card border-0 mb-4">
			
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     Top 5 Most Popular Attachments
	     </div>
		 
	       </div>';
	

	echo '
        
		
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Attachments</th>
        <th>Size</th>
        <th>Posted By</th>
		<th>Thread</th>
		<th>Downloads</th>
		<th>Date Uploaded</th>
      </tr>
    </thead>';
	
	
	

	$query = $db->sql_query("
		SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
		FROM attachments a
		LEFT JOIN tsf_posts p ON (p.pid=a.pid)
		LEFT JOIN tsf_threads t ON (t.tid=p.tid)
		LEFT JOIN users u ON (u.id=a.uid)
		ORDER BY a.downloads DESC
		LIMIT 5
	");
	while($attachment = $db->fetch_array($query))
	{
		
	
	   if($attachment['dateuploaded'] > 0)
	   {
		  $date = my_datee('relative', $attachment['dateuploaded']);
	   }
	   else
	   {
		  $date = 'unknown';
	   }
	   
	   
	   if($attachment['user_username'])
	   {
		  $attachment['username'] = $attachment['user_username'];
	   }
	   
	   $userr = build_profile_link(htmlspecialchars_uni($attachment['username']), $attachment['uid'], "_blank");
	   
	   $sizeee = mksize($attachment['filesize']);
	   
	   $downs = ts_nf($attachment['downloads']);
	   
	   
	   $tat = get_attachment_icon(get_extension($attachment['filename']));
	   
	   
	   $atach_name = '<a href="../attachment.php?aid='.$attachment['aid'].'" target=\"_blank\">'.$attachment['filename'].'</a>';
		
	   $getpost = "<a href=\"../".get_post_link($attachment['pid'])."\" target=\"_blank\">".htmlspecialchars_uni($attachment['subject'])."</a>";
		
		echo '
	    <tr class="first">
			<td class="first" width="1">'.$tat.'
			'.$atach_name.'</td>
			<td class="align_center">'.$sizeee.'</td>
			<td class="align_center alt_col">'.$userr.'</td>
			<td class="align_center">'.$getpost.'</td>
			<td class="align_center alt_col">'.$downs.'</td>
			<td class="align_center last">'.$date.'</td>
		</tr>';
	
	}
	
	
	echo '</table></div></div>';
	
	
	

	// Fetch the largest attachments
	
	echo  '
	<div class="container mt-3">
	
            <div class="card border-0 mb-4">
			
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     Top 5 Largest Attachments
	     </div>
		 
	       </div>
	    ';
		
	echo '
        
		
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Attachments</th>
        <th>Size</th>
        <th>Posted By</th>
		<th>Thread</th>
		<th>Downloads</th>
		<th>Date Uploaded</th>
      </tr>
    </thead>';	
		
	
	
	

	$query = $db->sql_query("
		SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
		FROM attachments a
		LEFT JOIN tsf_posts p ON (p.pid=a.pid)
		LEFT JOIN tsf_threads t ON (t.tid=p.tid)
		LEFT JOIN users u ON (u.id=a.uid)
		ORDER BY a.filesize DESC
		LIMIT 5
	");
	while($attachment = $db->fetch_array($query))
	{
		
		
	   if($attachment['dateuploaded'] > 0)
	   {
		  $date = my_datee('relative', $attachment['dateuploaded']);
	   }
	   else
	   {
		  $date = 'unknown';
	   }
	   
	   
	   if($attachment['user_username'])
	   {
		  $attachment['username'] = $attachment['user_username'];
	   }
	   
	   $userr = build_profile_link(htmlspecialchars_uni($attachment['username']), $attachment['uid'], "_blank");
	   
	   $sizeee = mksize($attachment['filesize']);
	   
	   $downs = ts_nf($attachment['downloads']);
	   
	   
	   $tat = get_attachment_icon(get_extension($attachment['filename']));
	   
	   
	   $atach_name = '<a href="../attachment.php?aid='.$attachment['aid'].'" target=\"_blank\">'.$attachment['filename'].'</a>';
		
	   $getpost = "<a href=\"../".get_post_link($attachment['pid'])."\" target=\"_blank\">".htmlspecialchars_uni($attachment['subject'])."</a>";
		
		echo '
	    <tr class="first">
			<td class="first" width="1">'.$tat.'
			'.$atach_name.'</td>
			<td class="align_center">'.$sizeee.'</td>
			<td class="align_center alt_col">'.$userr.'</td>
			<td class="align_center">'.$getpost.'</td>
			<td class="align_center alt_col">'.$downs.'</td>
			<td class="align_center last">'.$date.'</td>
		</tr>';
		
		
		
	}
	
	
	
	
	
	echo '</table></div></div>';
	
	
	
	
	echo  '
	<div class="container mt-3">
	
            <div class="card border-0 mb-4">
			
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     Top 5 Users Using the Most Disk Space
	     </div>
		 
	       </div>
	    ';
		
	echo '
        
		
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Username</th>
        <th>Total Size</th>
      </tr>
    </thead>';	
	
	
	

	switch($db->type)
	{
		case "pgsql":
			$query = $db->sql_query("
				SELECT a.uid, u.username, SUM(a.filesize) as totalsize
				FROM attachments a
				LEFT JOIN users u ON (u.id=a.uid)
				GROUP BY a.uid, u.username
				ORDER BY totalsize DESC
				LIMIT 5
			");
			break;
		default:
			$query = $db->sql_query("
				SELECT a.uid, u.username, SUM(a.filesize) as totalsize
				FROM attachments a
				LEFT JOIN users u ON (u.id=a.uid)
				GROUP BY a.uid
				ORDER BY totalsize DESC
				LIMIT 5
			");
	}
	while($user = $db->fetch_array($query))
	{
		if(!$user['uid'])
		{
			$user['username'] = 'na';
		}
		
		
		
		$useeer = build_profile_link(htmlspecialchars_uni($user['username']), $user['uid'], "_blank");
		
		$uuser = "<a href=\"index.php?act=attachments&amp;results=1&amp;username=".urlencode($user['username'])."\" target=\"_blank\">".mksize($user['totalsize'])."</a>";
		
		echo '
		<tr class="first">
			<td class="first">'.$useeer.'</td>
			<td class="align_center">'.$uuser.'</td>
		</tr>';
		
		
		
		
		
	}
	
	echo '</table></div></div>';

	stdfoot();
}

if($mybb->input['action'] == "delete_orphans" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_forum_attachments_delete_orphans");

	$success_count = $error_count = 0;

	// Deleting specific attachments from uploads directory
	if(is_array($mybb->input['orphaned_files']))
	{
		foreach($mybb->input['orphaned_files'] as $file)
		{
			$file = str_replace('..', '', $file);
			$path = $uploadspath_abs."/".$file;
			$real_path = realpath($path);

			if($real_path === false || strpos(str_replace('\\', '/', $real_path), str_replace('\\', '/', realpath(TSDIR)).'/') !== 0 || $real_path == realpath(TSDIR.'install/lock'))
			{
				$error_count++;
				continue;
			}

			if(!@unlink($uploadspath_abs."/".$file))
			{
				$error_count++;
			}
			else
			{
				$success_count++;
			}
		}
	}

	// Deleting physical attachments which exist in database
	if(is_array($mybb->input['orphaned_attachments']))
	{
		$mybb->input['orphaned_attachments'] = array_map("intval", $mybb->input['orphaned_attachments']);
		require_once INC_PATH."/functions_upload.php";

		$query = $db->simple_select("attachments", "aid,pid,posthash", "aid IN (".implode(",", $mybb->input['orphaned_attachments']).")");
		while($attachment = $db->fetch_array($query))
		{
			if(!$attachment['pid'])
			{
				remove_attachment(null, $attachment['posthash'], $attachment['aid']);
			}
			else
			{
				remove_attachment($attachment['pid'], null, $attachment['aid']);
			}
			$success_count++;
		}
	}

	$plugins->run_hooks("admin_forum_attachments_delete_orphans_commit");

	// Log admin action
	//log_admin_action();

	$message = '';
	$status = 'success';
	if($error_count > 0)
	{
		$status = 'error';
		$message = sprintf('Unable to remove '.$error_count.' attachment(s)');
	}

	if($success_count > 0)
	{
		if($error_count > 0)
		{
			$message .= '<br />'.sprintf(''.$success_count.' attachment(s) removed successfully');
		}
		else
		{
			$message = 'The selected orphaned attachment(s) have been deleted successfully';
		}
	}
	flash_message($message, $status);
	admin_redirect('index.php?act=attachments');
}

if($mybb->input['action'] == "orphans")
{
	$plugins->run_hooks("admin_forum_attachments_orphans");

	// Oprhans are defined as:
	// - Uploaded files in the uploads directory that don't exist in the database
	// - Attachments for which the uploaded file is missing
	// - Attachments for which the thread or post has been deleted
	// - Files uploaded > 24h ago not attached to a real post

	// This process is quite intensive so we split it up in to 2 steps, one which scans the file system and the other which scans the database.

	$mybb->input['step'] = $mybb->get_input('step', MyBB::INPUT_INT);

	// Finished second step, show results
	if($mybb->input['step'] == 3)
	{
		$plugins->run_hooks("admin_forum_attachments_step3");

		$reults = 0;
		// Incoming attachments which exist as files but not in database
		if(!empty($mybb->input['bad_attachments']))
		{
			$bad_attachments = my_unserialize($mybb->input['bad_attachments']);
			$results = count($bad_attachments);
		}

		$aids = array();
		if(!empty($mybb->input['missing_attachment_files']))
		{
			$missing_attachment_files = my_unserialize($mybb->input['missing_attachment_files']);
			$aids = array_merge($aids, $missing_attachment_files);
		}

		if(!empty($mybb->input['missing_threads']))
		{
			$missing_threads = my_unserialize($mybb->input['missing_threads']);
			$aids = array_merge($aids, $missing_threads);
		}

		if(!empty($mybb->input['incomplete_attachments']))
		{
			$incomplete_attachments = my_unserialize($mybb->input['incomplete_attachments']);
			$aids = array_merge($aids, $incomplete_attachments);
		}

		foreach($aids as $key => $aid)
		{
			$aids[$key] = (int)$aid;
		}

		$results = count($aids);

		if($results == 0)
		{
			flash_message('There are no orphaned attachments on your forum', 'success');
			admin_redirect("index.php?act=attachments");
		}

		
		stdhead('Orphaned Attachments Search - Results');
		
		
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
		
		
		
		
		
		
		output_nav_tabs($sub_tabs, 'find_orphans');

		
		
		
		echo '
		<form action="index.php?act=attachments&amp;action=delete_orphans" method="post">
        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
		
		
		
		// Fetch the most popular attachments
	echo  '
	<div class="container mt-3">
	
            <div class="card border-0 mb-4">
			
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     Orphaned Attachments Search - '.$results.' Results
	     </div>
		 
	       </div>';
	

	echo '
        
		
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>'.generate_check_box('allbox', '1', '', array('class' => 'checkall')).'</th>
		<th>Size</th>
        <th>Reason Orphaned</th>
        <th>Date Uploaded</th>
		
      </tr>
    </thead>';
		
		
		
		
		
		
		
		
		
		
		
		
		
		

		if(is_array($bad_attachments))
		{
			foreach($bad_attachments as $file)
			{
				$file_path = $uploadspath_abs."/".$file;

				if(file_exists($file_path))
				{
					$filename = htmlspecialchars_uni($file);
					$filesize = mksize(filesize($file_path));
					//$table->construct_cell($form->generate_check_box('orphaned_files[]', $file, '', array('checked' => true)));
					//$table->construct_cell(get_attachment_icon(get_extension($attachment['filename'])), array('width' => 1));
					//$table->construct_cell("<span class=\"float_right\">{$filesize}</span>{$filename}");
					//echo 'Not in attachments table';
					//$table->construct_cell(my_datee('relative', filemtime($file_path)), array('class' => 'align_center'));
					//$table->construct_row();
				}
			}
		}

		if(count($aids) > 0)
		{
			$query = $db->simple_select("attachments", "*", "aid IN (".implode(",", $aids).")");
			while($attachment = $db->fetch_array($query))
			{
				$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

				if($missing_attachment_files[$attachment['aid']])
				{
				    $reason = 'Attached file missing';
				}
				else if($missing_threads[$attachment['aid']])
				{
					$reason = 'Thread been deleted';
				}
				else if($incomplete_attachments[$attachment['aid']])
				{
					$reason = 'Post never made';
				}
				
				
				
				if($attachment['dateuploaded'])
				{
					$dateup = my_datee('relative', $attachment['dateuploaded']);
				}
				else
				{
					$dateup = 'Unknown';
				}
				
				
				$tyra = generate_check_box('orphaned_attachments[]', $attachment['aid'], '', array('checked' => true));

				$fas = get_attachment_icon(get_extension($attachment['filename']));
				
				$tera = "<span class=\"float_right\">".mksize($attachment['filesize'])."</span>".$attachment['filename']."";
				
				
				
				
				
				
				echo '
				
				<tr class="first">
			
			<td class="first">
			<label>
			'.$tyra.'
			</label>
			
			</td>
			
			
			<td class="alt_col" width="1">'.$fas.'
			'.$tera.'
			</td>
			
			<td class="align_center alt_col">'.$reason.'</td>
			<td class="align_center last">'.$dateup.'</td>
		</tr>';
				
				
				
				
				
			}
			
			echo '</table></div></div>';
		}
	
		
		echo '
		
		<div class="container mt-3">
		<div class="card-footer text-center">
	     <tr><td colspan=3 align=center>
            <input type="submit" value="Delete Checked Orphans" class="btn btn-primary"> 
         </td></tr>
        </div></div>';
		
		
		
		echo "</form>";
		
		stdfoot();
	}

	// Running second step - scan the database
	else if($mybb->input['step'] == 2)
	{
		$plugins->run_hooks("admin_forum_attachments_orphans_step2");

		
		stdhead('Orphaned Attachments Search - Step 2');
		
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
		
		
		

		output_nav_tabs($sub_tabs, 'find_orphans');
		echo "<div class=\"container mt-3\"><h3>Step 2 of 2 - Database Scan</h3></div>";
		echo "<p class=\"align_center\">Please wait, the database is currently being scanned for orphaned attachments</p>";
		echo "<p class=\"align_center\">You'll automatically be redirected to the next step once this process is complete</p>";
		echo "<p class=\"align_center\"><i class=\"fa-solid fa-spinner fa-spin fa-spin-reverse fa-2xl\" style=\"color: #0a57db;\"></i></p>";

		//$page->output_footer(false);
		stdfoot();
		
		flush();

		$missing_attachment_files = array();
		$missing_threads = array();
		$incomplete_attachments = array();

		$query = $db->sql_query("
			SELECT a.*, a.pid AS attachment_pid, p.pid
			FROM attachments a
			LEFT JOIN tsf_posts p ON (p.pid=a.pid)
			ORDER BY a.aid");
		while($attachment = $db->fetch_array($query))
		{
			// Check if the attachment exists in the file system
			if(!file_exists($uploadspath_abs."/{$attachment['attachname']}"))
			{
				$missing_attachment_files[$attachment['aid']] = $attachment['aid'];
			}
			// Check if the thread/post for this attachment is missing
			else if(!$attachment['pid'] && $attachment['attachment_pid'])
			{
				$missing_threads[$attachment['aid']] = $attachment['aid'];
			}
			// Check if the attachment was uploaded > 24 hours ago but not assigned to a thread
			else if(!$attachment['attachment_pid'] && $attachment['dateuploaded'] < TIMENOW-60*60*24 && $attachment['dateuploaded'] != 0)
			{
				$incomplete_attachments[$attachment['aid']] = $attachment['aid'];
			}
		}

		// Now send the user to the final page
		//$form = new Form("index.php?act=attachments&amp;action=orphans&amp;step=3", "post", "redirect_form", 0, "");
		
		echo '<form action="index.php?act=attachments&amp;action=orphans&amp;step=3" method="post" id="redirect_form">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
		

		
		// Scan complete
		if($mybb->get_input('bad_attachments'))
		{
			echo generate_hidden_field("bad_attachments", $mybb->input['bad_attachments']);
		}
		if(is_array($missing_attachment_files) && count($missing_attachment_files) > 0)
		{
			$missing_attachment_files = my_serialize($missing_attachment_files);
			echo generate_hidden_field("missing_attachment_files", $missing_attachment_files);
		}
		if(is_array($missing_threads) && count($missing_threads) > 0)
		{
			$missing_threads = my_serialize($missing_threads);
			echo generate_hidden_field("missing_threads", $missing_threads);
		}
		if(is_array($incomplete_attachments) && count($incomplete_attachments) > 0)
		{
			$incomplete_attachments = my_serialize($incomplete_attachments);
			echo generate_hidden_field("incomplete_attachments", $incomplete_attachments);
		}
		//$form->end();
		
		echo "</form>";
		
		
		echo "<script type=\"text/javascript\">$(function() {
				window.setTimeout(
					function() {
						$(\"#redirect_form\").trigger('submit');
					}, 100
				);
			});</script>";
		exit;
	}
	// Running first step, scan the file system
	else
	{
		$plugins->run_hooks("admin_forum_attachments_orphans_step1");

		/**
		 * @param string $dir
		 */
		function scan_attachments_directory($dir="")
		{
			global $db, $mybb, $bad_attachments, $attachments_to_check, $uploadspath_abs;

			$real_dir = $uploadspath_abs;
			$false_dir = "";
			if($dir)
			{
				$real_dir .= "/".$dir;
				$false_dir = $dir."/";
			}

			if($dh = opendir($real_dir))
			{
				while(false !== ($file = readdir($dh)))
				{
					if($file == "." || $file == ".." || $file == ".svn")
					{
						continue;
					}

					if(is_dir($real_dir.'/'.$file))
					{
						scan_attachments_directory($false_dir.$file);
					}
					else if(my_substr($file, -7, 7) == ".attach")
					{
						$attachments_to_check["$false_dir$file"] = $false_dir.$file;
						// In allotments of 20, query the database for these attachments
						if(count($attachments_to_check) >= 20)
						{
							$attachments_to_check = array_map(array($db, "escape_string"), $attachments_to_check);
							$attachment_names = "'".implode("','", $attachments_to_check)."'";
							$query = $db->simple_select("attachments", "aid, attachname", "attachname IN ($attachment_names)");
							while($attachment = $db->fetch_array($query))
							{
								unset($attachments_to_check[$attachment['attachname']]);
							}

							// Now anything left is bad!
							if(count($attachments_to_check) > 0)
							{
								if($bad_attachments)
								{
									$bad_attachments = @array_merge($bad_attachments, $attachments_to_check);
								}
								else
								{
									$bad_attachments = $attachments_to_check;
								}
							}
							$attachments_to_check = array();
						}
					}
				}
				closedir($dh);
				// Any reamining to check?
				if(!empty($attachments_to_check))
				{
					$attachments_to_check = array_map(array($db, "escape_string"), $attachments_to_check);
					$attachment_names = "'".implode("','", $attachments_to_check)."'";
					$query = $db->simple_select("attachments", "aid, attachname", "attachname IN ($attachment_names)");
					while($attachment = $db->fetch_array($query))
					{
						unset($attachments_to_check[$attachment['attachname']]);
					}

					// Now anything left is bad!
					if(count($attachments_to_check) > 0)
					{
						if($bad_attachments)
						{
							$bad_attachments = @array_merge($bad_attachments, $attachments_to_check);
						}
						else
						{
							$bad_attachments = $attachments_to_check;
						}
					}
				}
			}
		}

		
		stdhead('Orphaned Attachments Search - Step 1');
		
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
		
		
		

		output_nav_tabs($sub_tabs, 'find_orphans');
		echo "<div class=\"container mt-3\"><h5>Step 1 of 2 - File System Scan</h5></div>";
		echo "<p class=\"align_center\">Please wait, the file system is currently being scanned for orphaned attachments</p>";
		echo "<p class=\"align_center\">You'll automatically be redirected to the next step once this process is complete.</p>";
		echo "<p class=\"align_center\"><i class=\"fa-solid fa-spinner fa-spin fa-spin-reverse fa-2xl\" style=\"color: #0a57db;\"></i></i></p>";

		//$page->output_footer(false);
		stdfoot();

		flush();

		scan_attachments_directory();
		global $bad_attachments;

		//$form = new Form("index.php?act=attachments&amp;action=orphans&amp;step=2", "post", "redirect_form", 0, "");
		
		
		echo '<form action="index.php?act=attachments&amp;action=orphans&amp;step=2" method="post" id="redirect_form">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
		
		
		
		
		
		// Scan complete
		if(is_array($bad_attachments) && count($bad_attachments) > 0)
		{
			$bad_attachments = my_serialize($bad_attachments);
			echo generate_hidden_field("bad_attachments", $bad_attachments);
		}
		//$form->end();
		
		echo "</form>";
		
		
		echo "<script type=\"text/javascript\">$(function() {
				window.setTimeout(
					function() {
						$(\"#redirect_form\").trigger('submit');
					}, 100
				);
			});</script>";
		exit;
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_forum_attachments_start");

	if($mybb->request_method == "post" || $mybb->get_input('results', MyBB::INPUT_INT) == 1)
	{
		$search_sql = '1=1';

		$plugins->run_hooks("admin_forum_attachments_commit_start");

		// Build the search SQL for users

		// List of valid LIKE search fields
		$user_like_fields = array("filename", "filetype");
		foreach($user_like_fields as $search_field)
		{
			if($mybb->get_input($search_field))
			{
				$search_sql .= " AND a.{$search_field} LIKE '%".$db->escape_string_like($mybb->input[$search_field])."%'";
			}
		}

		$errors = array();

		// Normal users only
		if($mybb->get_input('user_types', MyBB::INPUT_INT) == 1)
		{
			$user_types = 1;
		}
		// Guests only
		elseif($mybb->get_input('user_types', MyBB::INPUT_INT) == -1)
		{
			$user_types = -1;
			$search_sql .= " AND a.uid='0'";
		}
		// Users & Guests
		else
		{
			$user_types = 0;
		}

		// Username matching
		if(!empty($mybb->input['username']))
		{
			$user = get_user_by_username($mybb->input['username']);

			if(!$user)
			{
				if($user_types == 1)
				{
					$errors[] = 'The username you entered is invalid';
				}
				else
				{
					// Don't error if we are searching for guests or users & guests
					$search_sql .= " AND p.username LIKE '%".$db->escape_string_like($mybb->input['username'])."%'";
				}

			}
			else
			{
				$search_sql .= " AND a.uid='{$user['id']}'";
			}
		}

		$forum_cache = cache_forums();

		// Searching for attachments in a specific forum, we need to fetch all child forums too
		if(!empty($mybb->input['forum']))
		{
			if(!is_array($mybb->input['forum']))
			{
				$mybb->input['forum'] = array($mybb->input['forum']);
			}

			$fid_in = array();
			foreach($mybb->input['forum'] as $fid)
			{
				if(!$forum_cache[$fid])
				{
					$errors[] = 'One or more forums you selected are invalid';
					break;
				}
				$child_forums = get_child_list($fid);
				$child_forums[] = $fid;
				$fid_in = array_merge($fid_in, $child_forums);
			}

			if(count($fid_in) > 0)
			{
				$search_sql .= " AND p.fid IN (".implode(",", $fid_in).")";
			}
		}

		// LESS THAN or GREATER THAN
		$direction_fields = array(
			"dateuploaded" => $mybb->get_input('dateuploaded', MyBB::INPUT_INT),
			"filesize"     => $mybb->get_input('filesize', MyBB::INPUT_INT),
			"downloads"    => $mybb->get_input('downloads', MyBB::INPUT_INT)
		);

		if(!empty($mybb->input['dateuploaded']))
		{
			$direction_fields['dateuploaded'] = TIMENOW-$direction_fields['dateuploaded']*60*60*24;
		}
		if(!empty($mybb->input['filesize']))
		{
			$direction_fields['filesize'] *= 1024;
		}

		foreach($direction_fields as $field_name => $field_content)
		{
			$direction_field = $field_name."_dir";
			if(!empty($mybb->input[$field_name]) && !empty($mybb->input[$direction_field]))
			{
				switch($mybb->input[$direction_field])
				{
					case "greater_than":
						$direction = ">";
						break;
					case "less_than":
						$direction = "<";
						break;
					default:
						$direction = "=";
				}
				$search_sql .= " AND a.{$field_name}{$direction}'".$field_content."'";
			}
		}
		if(!$errors)
		{
			// Lets fetch out how many results we have
			$query = $db->sql_query("
				SELECT COUNT(a.aid) AS num_results
				FROM attachments a
				LEFT JOIN tsf_posts p ON (p.pid=a.pid)
				WHERE {$search_sql}
			");
			$num_results = $db->fetch_field($query, "num_results");

			// No matching results then show an error
			if(!$num_results)
			{
				$errors[] = 'No attachments were found with the specified search criteria';
			}
		}

		// Now we fetch the results if there were 100% no errors
		if(!$errors)
		{
			$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
			if($mybb->input['page'])
			{
				$start = ($mybb->input['page'] - 1) * $perpage;
			}
			else
			{
				$start = 0;
				$mybb->input['page'] = 1;
			}

			switch($mybb->input['sortby'])
			{
				case "filesize":
					$sort_field = "a.filesize";
					break;
				case "downloads":
					$sort_field = "a.downloads";
					break;
				case "dateuploaded":
					$sort_field = "a.dateuploaded";
					break;
				case "username":
					$sort_field = "u.username";
					break;
				default:
					$sort_field = "a.filename";
					$mybb->input['sortby'] = "filename";
			}

			if($mybb->input['order'] != "desc")
			{
				$mybb->input['order'] = "asc";
			}

			$plugins->run_hooks("admin_forum_attachments_commit");

			
			
			
			stdhead('Attachments - Find Attachments');
			
			
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
			
			

			output_nav_tabs($sub_tabs, 'find_attachments');

			
			echo '
			<form action="index.php?act=attachments&amp;action=delete" method="post">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
			
			
			
			
	echo  '
	<div class="container mt-3">
	
            <div class="card border-0 mb-4">
			
	      <div class="card-header rounded-bottom text-19 fw-bold">
		     Results
	     </div>
		 
	       </div>';
	

	echo '
        
		
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>'.generate_check_box('allbox', '1', '', array('class' => 'checkall')).'</th>
		<th>Attachments</th>
        <th>Size</th>
        <th>Posted By</th>
		<th>Thread</th>
		<th>Downloads</th>
		<th>Date Uploaded</th>
      </tr>
    </thead>';
			
			
			

			// Fetch matching attachments
			$query = $db->sql_query("
				SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
				FROM attachments a
				LEFT JOIN tsf_posts p ON (p.pid=a.pid)
				LEFT JOIN tsf_threads t ON (t.tid=p.tid)
				LEFT JOIN users u ON (u.id=a.uid)
				WHERE {$search_sql}
				ORDER BY {$sort_field} {$mybb->input['order']}
				LIMIT {$start}, {$perpage}
			");
			while($attachment = $db->fetch_array($query))
			{
				
				
				
				
	   if($attachment['dateuploaded'] > 0)
	   {
		  $date = my_datee('relative', $attachment['dateuploaded']);
	   }
	   else
	   {
		  $date = 'unknown';
	   }
	   
	   
	   if($attachment['user_username'])
	   {
		  $attachment['username'] = $attachment['user_username'];
	   }
	   
	   $userr = build_profile_link(htmlspecialchars_uni($attachment['username']), $attachment['uid'], "_blank");
	   
	   $sizeee = mksize($attachment['filesize']);
	   
	   $downs = ts_nf($attachment['downloads']);
	   
	   
	   $tat = get_attachment_icon(get_extension($attachment['filename']));
	   
	   
	   $atach_name = '<a href="../attachment.php?aid='.$attachment['aid'].'" target=\"_blank\">'.$attachment['filename'].'</a>';
		
	   $getpost = "<a href=\"../".get_post_link($attachment['pid'])."\" target=\"_blank\">".htmlspecialchars_uni($attachment['subject'])."</a>";
	   
	   
	   
	   //$tyra = generate_check_box('aids[]', $attachment['aid'], '', array('checked' => true));
	   
	   
	   // Check if the attachment exists in the file system
	   $checked = false;
	   $title = $cell_class = '';
	
	   $uploadspath = "./uploads";
	
	   if(!file_exists(mk_path_abs22($uploadspath)."/{$attachment['attachname']}"))
	   {
		  $cell_class = "bad_attachment";
		  $title = 'Attachment file could not be found in the uploads directory';
		  $checked = true;
	   }
	   elseif(!$attachment['pid'] && $attachment['dateuploaded'] < TIMENOW-60*60*24 && $attachment['dateuploaded'] != 0)
	   {
		  $cell_class = "bad_attachment";
		  $title = 'Attachment was uploaded over 24 hours ago but not attached to a post';
		  $checked = true;
	   }
	   else if(!$attachment['tid'] && $attachment['pid'])
	   {
		  $cell_class = "bad_attachment";
		  $title = 'Thread or post for this attachment no longer exists';
		  $checked = true;
	   }
	   else if($attachment['visible'] == 0)
	   {
		  $cell_class = "invisible_attachment";
	   }

	   if($cell_class)
	   {
		  $cell_class .= " align_center";
	   }
	   else
	   {
		  $cell_class = "align_center";
	   }
	   
	   
	   
	   
	   $tyra = generate_check_box('aids[]', $attachment['aid'], '', array('checked' => $checked));
	   
	   
	   
	
				
			
			
			echo '
	    <tr class="first">
		
		    <td class="first">
			<label>
			'.$tyra.'
			</label>
			
			</td>
			
		
			<td class="first" width="1">'.$tat.'
			'.$atach_name.'</td>
			<td class="align_center">'.$sizeee.'</td>
			<td class="align_center alt_col">'.$userr.'</td>
			<td class="align_center">'.$getpost.'</td>
			<td class="align_center alt_col">'.$downs.'</td>
			<td class="align_center last">'.$date.'</td>
		</tr>';
			
			
			
			
				
				
				
				
				
			}
			
			
			echo '</table></div></div>';	

			// Need to draw pagination for this result set
			$pagination = '';
			if($num_results > $perpage)
			{
				$pagination_url = "index.php?act=attachments&amp;results=1";
				$pagination_vars = array('perpage', 'sortby', 'order', 'filename', 'mimetype', 'username', 'downloads', 'downloads_dir', 'dateuploaded', 'dateuploaded_dir', 'filesize', 'filesize_dir');
				foreach($pagination_vars as $var)
				{
					if($mybb->get_input($var))
					{
						$pagination_url .= "&{$var}=".urlencode($mybb->input[$var]);
					}
				}
				if(!empty($mybb->input['forum']) && is_array($mybb->input['forum']))
				{
					foreach($mybb->input['forum'] as $fid)
					{
						$pagination_url .= "&forum[]=".(int)$fid;
					}
				}
				$pagination = draw_admin_pagination($mybb->input['page'], $perpage, $num_results, $pagination_url);
					
			}

			
			echo '<div class="container mt-3">
			'.$pagination.'
			</div>';
			
			
			
			echo '
		
		<div class="container mt-3">
		<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Delete Checked Attachments" class="btn btn-primary"> 
</td></tr>
</div></div>';
			
			
			
			echo "</form>";
			

			stdfoot();
		}
	}

	
	
	stdhead();
	
	
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
	

	output_nav_tabs($sub_tabs, 'find_attachments');
	

	// If we have any error messages, show them
	if($errors)
	{
		output_inline_error($errors);
	}

	
	
	echo '
	<form action="index.php?act=attachments" method="post">
    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />';
	
	
	
	$more_options = array(
		"less_than" => 'More than',
		"greater_than" => 'Less than'
	);

	$greater_options = array(
		"greater_than" => 'Greater than',
		"is_exactly" => 'Is exactly',
		"less_than" => 'Less than'
	);
	
	
	
	
	$findforum = generate_forum_select('forum[]', $mybb->get_input('forum', MyBB::INPUT_INT), array('multiple' => true, 'size' => 5, 'id' => 'forum'));
	
	$usertypess = generate_select_box('user_types', array('0' => 'User or Guest', '1' => 'Users Only', '-1' => 'Guests Only'), $mybb->get_input('user_types', MyBB::INPUT_INT), array('id' => 'guests'));
	
	
	$form_container66 =  generate_select_box('dateuploaded_dir', $more_options, $mybb->get_input('dateuploaded_dir'), array('id' => 'dateuploaded_dir'))." ".generate_numeric_field('dateuploaded', $mybb->get_input('dateuploaded', MyBB::INPUT_INT), array('id' => 'dateuploaded', 'min' => 0))." days ago";
	
	
	$form_container77 = generate_select_box('filesize_dir', $greater_options, $mybb->get_input('filesize_dir'), array('id' => 'filesize_dir'))." ".generate_numeric_field('filesize', $mybb->get_input('filesize', MyBB::INPUT_INT), array('id' => 'filesize', 'min' => 0))." KB";
	
	
	$form_container88 = generate_select_box('downloads_dir', $greater_options, $mybb->get_input('downloads_dir'), array('id' => 'downloads_dir'))." ".generate_numeric_field('downloads', $mybb->get_input('downloads', MyBB::INPUT_INT), array('id' => 'downloads', 'min' => 0))."";
	
	
	
	
	
	echo '
	
	<div class="container mt-3">
	
	<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">Find attachments where&hellip;</div>
    <div class="card-body">';
	
	
	echo '<tr class="first">
			<td class="first"><label for="filename"><b>File name contains</b></label><div class="form_row"><input type="text" name="filename" value="" class="form-control" id="filename"></div>
</td>
		</tr>
		
		<tr class="first">
			<td class="first"><label for="mimetype"><b>File type contains</b></label><div class="form_row"><input type="text" name="mimetype" value="" class="form-control" id="mimetype"></div>
</td>
		</tr>
		
		
		
		
		<tr class="first">
			<td class="first"><label for="forum"><b>Forum is</b></label><div class="form_row">
			
			'.$findforum.'

</div>
</td>
		</tr>
		
		
		<tr class="first">
			<td class="first"><label for="username"><b>Posters username is</b></label><div class="form_row"><input type="text" name="username" value="" class="form-control" id="username"></div>
</td>
		</tr>
		
		
		
		<tr class="first">
			<td class="first"><label for="user_types"><b>Poster is</b></label>
			<div class="form_row">
			
			'.$usertypess.'


</div>
</td>
		</tr>
		
		<hr>
		
		
		
		
		
		<tr class="first">
			<td class="first"><label for="dateuploaded"><b>Date posted is</b></label>
			
			<div class="form_row">
		
		<label>
   '.$form_container66.'
   </label>


</div>
</td>
		</tr>
		
		
		<hr>
		
		
		
		
		<tr class="first">
			<td class="first"><label for="dateuploaded"><b>File size is</b></label>
			<div class="form_row">
			
			<label>
			'.$form_container77.'
			</label>


 </div>
</td>
		</tr>
		
		
		
		
		
		<hr>
		
		
		
		
		<tr class="last alt_row">
			<td class="first">
			<label for="dateuploaded"><b>Download count is</b></label>
			
			<div class="form_row">
			
			<label>
			'.$form_container88.'
			
             </label>
			 
			  </div>
			 
			
 
 

</td>
		</tr>';
		
		
		
		
		
		
	echo '
	
	</div>
	</div>
</div>
	';	
		
	
	
	$sort_options = array(
		"filename" => 'File Name',
		"filesize" => 'File Size',
		"downloads" => 'Download Count',
		"dateuploaded" => 'Date Uploaded',
		"username" => 'Post Username'
	);
	$sort_directions = array(
		"asc" => 'Ascending',
		"desc" => 'Descending'
	);
	
	
	$sooortby = generate_select_box('sortby', $sort_options, $mybb->get_input('sortby'), array('id' => 'sortby'))." in ".generate_select_box('order', $sort_directions, $mybb->get_input('order'), array('id' => 'order'));

    $peeerspage = generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1));

	
	echo '
	
	<div class="container mt-3">
	
	<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">Display Options</div>
    <div class="card-body">';
	
	
	
	echo '
	
	
	<tr class="first">
			<td class="first"><label for="sortby">Sort results by</label>
			<div class="form_row">
			
			
			'.$sooortby.'


</div>
</td>
		</tr>
		
		
		
		<tr class="first">
			<td class="first"><label for="perpage">Results per page</label><div class="form_row">
			
			<label>
			'.$peeerspage.'
			</label>
			
			
			</div>
</td>
		</tr>';
		
	
	echo '</div></div></div>';
	
	
	
	echo '
		
		<div class="container mt-3">
		<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
<input type="submit" value="Find Attachments" class="btn btn-primary"> 
</td></tr>
</div></div>';
	
	
	
	echo "</form>";

	stdfoot();
}
