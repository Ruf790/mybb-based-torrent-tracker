<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */


	// Update thread views
	$query = $db->simple_select("tsf_threadviews", "tid, COUNT(tid) AS views", "", array('group_by' => 'tid'));
	++$CQueryCount;
	
	while($threadview = $db->fetch_array($query))
	{
		$db->update_query("tsf_threads", array('views' => "views+{$threadview['views']}"), "tid='{$threadview['tid']}'", 1, true);
		++$CQueryCount;
	}

	$db->write_query("TRUNCATE TABLE tsf_threadviews");
	 ++$CQueryCount;

	if(is_object($plugins))
	{
		$plugins->run_hooks('task_threadviews', $task);
	}

	savelog('The thread views task successfully ran');
	++$CQueryCount;

