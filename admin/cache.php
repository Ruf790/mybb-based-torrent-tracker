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





$plugins->run_hooks("admin_tools_cache_begin");

if($mybb->input['action'] == 'view')
{
	
	
	
	if(!trim($mybb->input['title']))
	{
		flash_message('error_no_cache_specified', 'error');
		admin_redirect("index.php?act=cache");
	}

	$plugins->run_hooks("admin_tools_cache_view");

	// Rebuilds forum settings
	if($mybb->input['title'] == 'settings')
	{
		$cachedsettings = (array)$mybb->settings;
		if(isset($cachedsettings['internal']))
		{
			unset($cachedsettings['internal']);
		}

		$cacheitem = array(
			'title'	=> 'settings',
			'cache'	=> my_serialize($cachedsettings)
		);
	}
	else
	{
		$query = $db->simple_select("datacache", "*", "title = '".$db->escape_string($mybb->input['title'])."'");
		$cacheitem = $db->fetch_array($query);
	}

	if(!$cacheitem)
	{
		flash_message('Incorrect cache specified', 'error');
		admin_redirect("index.php?act=cache");
	}

	// use native_unserialize() over my_unserialize() for performance reasons
	$cachecontents = native_unserialize($cacheitem['cache']);

	if(empty($cachecontents))
	{
		$cachecontents = 'error_empty_cache';
	}
	ob_start();
	print_r($cachecontents);
	$cachecontents = htmlspecialchars_uni(ob_get_contents());
	ob_end_clean();


	stdhead();
	
	echo '
	  
	   <div class="container mt-3">

       <div class="card">
  
       <div class="card-header text-19 fw-bold">'.$cacheitem['title'].'</div>

       <div class="card-body">
          <table border=0 cellspacing=0 cellpadding=5 width=100%>
           <pre>'.$cachecontents.'</pre>
       </td></tr>
       </td></tr>
       </table>
       </div>';
	
	stdfoot();	   
	   
	

}

if($mybb->input['action'] == "rebuild" || $mybb->input['action'] == "reload")
{
	
	
	//if(!verify_post_check($mybb->get_input('my_post_key')))
	//{
		//flash_message('invalid_post_verify_key', 'error');
		//admin_redirect("index.php?act=cache");
	//}

	$plugins->run_hooks("admin_tools_cache_rebuild");

	// Rebuilds forum settings
	if($mybb->input['title'] == 'settings')
	{
		rebuild_settings();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message('The cache has been reloaded successfully', 'success');
		admin_redirect("index.php?act=cache");
	}

	if(method_exists($cache, "update_{$mybb->input['title']}"))
	{
		$func = "update_{$mybb->input['title']}";
		$cache->$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message('The cache has been rebuilt successfully', 'success');
		admin_redirect("index.php?act=cache");
	}
	elseif(method_exists($cache, "reload_{$mybb->input['title']}"))
	{
		$func = "reload_{$mybb->input['title']}";
		$cache->$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message('The cache has been reloaded successfully', 'success');
		admin_redirect("index.php?act=cache");
	}
	elseif(function_exists("update_{$mybb->input['title']}"))
	{
		$func = "update_{$mybb->input['title']}";
		$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message('The cache has been rebuilt successfully', 'success');
		admin_redirect("index.php?act=cache");
	}
	elseif(function_exists("reload_{$mybb->input['title']}"))
	{
		$func = "reload_{$mybb->input['title']}";
		$func();

		$plugins->run_hooks("admin_tools_cache_rebuild_commit");

		// Log admin action
		log_admin_action($mybb->input['title']);

		flash_message('The cache has been reloaded successfully', 'success');
		admin_redirect("index.php?act=cache");
	}
	else
	{
		flash_message('This cache cannot be rebuilt', 'error');
		admin_redirect("index.php?act=cache");
	}
}

if($mybb->input['action'] == "rebuild_all")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message('invalid_post_verify_key2', 'error');
		admin_redirect("index.php?act=cache");
	}

	$plugins->run_hooks("admin_tools_cache_rebuild_all");

	$query = $db->simple_select("datacache");
	while($cacheitem = $db->fetch_array($query))
	{
		if(method_exists($cache, "update_{$cacheitem['title']}"))
		{
			$func = "update_{$cacheitem['title']}";
			$cache->$func();
		}
		elseif(method_exists($cache, "reload_{$cacheitem['title']}"))
		{
			$func = "reload_{$cacheitem['title']}";
			$cache->$func();
		}
		elseif(function_exists("update_{$cacheitem['title']}"))
		{
			$func = "update_{$cacheitem['title']}";
			$func();
		}
		elseif(function_exists("reload_{$cacheitem['title']}"))
		{
			$func = "reload_{$cacheitem['title']}";
			$func();
		}
	}

	// Rebuilds forum settings
	rebuild_settings();

	$plugins->run_hooks("admin_tools_cache_rebuild_all_commit");

	// Log admin action
	log_admin_action();

	flash_message('The cache has been reloaded successfully', 'success');
	admin_redirect("index.php?act=cache");
}

if(!$mybb->input['action'])
{
	
   $plugins->run_hooks("admin_tools_cache_start");

   stdhead();
   
   
   echo '
   
   
   <div class="container mt-3">
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  Cache Manager
	      </div>
	   </div>
	</div>
	
	

	<div class="container mt-3">
                 <div class="card">
        <table class="table table-hover">
            <thead>
			<tr>
               <th>name</th>
               <th>Size</th>
               <th>Controls</th>

			 </tr>
            </thead>';
   
   

	$query = $db->simple_select("datacache", "*", "", array("order_by" => "title"));
	while($cacheitem = $db->fetch_array($query))
	{
		
	  
	  
	    echo "<tr><td><strong><a href=\"index.php?act=cache&action=view&amp;title=".urlencode($cacheitem['title'])."\">{$cacheitem['title']}</a></strong></td>";
		
		echo '<td>'.mksize(strlen($cacheitem['cache'])).'</td>';

		if(method_exists($cache, "update_".$cacheitem['title']))
		{
			echo "<td align=center><a href=\"index.php?act=cache&action=rebuild&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">Rebuild Cache</a></td>";
		}
		elseif(method_exists($cache, "reload_".$cacheitem['title']))
		{
			echo "<td align=center><a href=\"index.php?act=cache&action=reload&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">Reload Cache</a></td>";
		}
		elseif(function_exists("update_".$cacheitem['title']))
		{
			echo "<td align=center><a href=\"index.php?act=cache&action=rebuild&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">Rebuild Cache</a></td>";
		}
		elseif(function_exists("reload_".$cacheitem['title']))
		{
			echo "<td align=center><a href=\"index.php?act=cache&action=reload&amp;title=".urlencode($cacheitem['title'])."&amp;my_post_key={$mybb->post_code}\">Reload Cache</a></td>";
		}
		else
		{
			echo '';
		}
		
		echo "</tr>";
	  
	  
	  
		
		
	
	}
	
	echo '</table>';
	echo '</div></div>';
	
	stdfoot();

	
	
}

