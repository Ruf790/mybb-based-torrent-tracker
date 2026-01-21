<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Check if the current user has permission to perform a ModCP action on another user
 *
 * @param int $uid The user ID to perform the action on.
 * @return boolean True if the user has necessary permissions
 */
function modcp_can_manage_user($uid)
{
	global $mybb, $usergroups;

	$user_permissions = user_permissions($uid);

	// Current user is only a local moderator or use with ModCP permissions, cannot manage super mods or admins
	if($usergroups['issupermod'] == 0 && ($user_permissions['issupermod'] == 1 || $user_permissions['cancp'] == 1))
	{
		return false;
	}
	// Current user is a super mod or is an administrator
	else if($user_permissions['cancp'] == 1 && ($mybb->usergroup['cancp'] != 1 || (is_super_admin($uid) && !is_super_admin($mybb->user['id']))))
	{
		return false;
	}
	return true;
}

/**
 * Fetch forums the moderator can manage announcements to
 *
 * @param int $pid (Optional) The parent forum ID
 * @param int $depth (Optional) The depth from parent forum the moderator can manage to
 */
function fetch_forum_announcements($pid=0, $depth=1)
{
	global $mybb, $db, $lang, $theme, $announcements, $templates, $announcements_forum, $moderated_forums, $unviewableforums, $parser, $usergroups;
	static $forums_by_parent, $forum_cache, $parent_forums;

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();
	}
	//if(!is_array($parent_forums) && $mybb->usergroup['issupermod'] != 1)
	if(!is_array($parent_forums) && $usergroups['issupermod'] != '1')	
	
	{
		// Get a list of parentforums to show for normal moderators
		$parent_forums = array();
		foreach($moderated_forums as $mfid)
		{
			$parent_forums = array_merge($parent_forums, explode(',', $forum_cache[$mfid]['parentlist']));
		}
	}
	if(!is_array($forums_by_parent))
	{
		foreach($forum_cache as $forum)
		{
			$forums_by_parent[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!is_array($forums_by_parent[$pid]))
	{
		return;
	}

	foreach($forums_by_parent[$pid] as $children)
	{
		foreach($children as $forum)
		{
			if($forum['linkto'] || (is_array($unviewableforums) && in_array($forum['fid'], $unviewableforums)))
			{
				continue;
			}

			if($forum['active'] == 0)
			{
				// Check if this forum is a parent of a moderated forum
				if(is_array($parent_forums) && in_array($forum['fid'], $parent_forums))
				{
					// A child is moderated, so print out this forum's title.  RECURSE!
					$trow = alt_trow();
				    
					
					$announcements_forum .= '
					
					<tr>
						<td class="'.$trow.'"><div style="padding-left: '.$padding.'px;"><strong>'.$forum['name'].'</strong></div></td>
						<td class="'.$trow.'" colspan="2" align="center">&nbsp;</td>
					</tr>
					
					';
				}
				else
				{
					// No subforum is moderated by this mod, so safely continue
					continue;
				}
			}
			else
			{
				// This forum is moderated by the user, so print out the forum's title, and its announcements
				$trow = alt_trow();

				$padding = 40*($depth-1);

				$announcements_forum .= '
				
				
				<div class="row py-2 border-top">
	<div class="col align-self-center">
		<strong>'.$forum['name'].'</strong>
	</div>
	<div class="col-lg-3 align-self-center text-lg-end">
		<a href="modcp.php?action=new_announcement&amp;fid='.$forum['fid'].'"><i class="fa-solid fa-circle-plus"></i> &nbsp;Add Announcement</a>
	</div>
</div>
				
				
				';
				
				
				

				if(isset($announcements[$forum['fid']]))
				{
					foreach($announcements[$forum['fid']] as $aid => $announcement)
					{
						$trow = alt_trow();

						if($announcement['enddate'] < TIMENOW && $announcement['enddate'] != 0)
						{
							
							$icon = '
							
							
							<div class="subforumicon subforum_minioff" title="Expired Announcement"></div>
							
							';
							
							
							
						}
						else
						{
							$icon = '<div class="subforumicon subforum_minion" title="Active Announcement"></div>';
						}

						$subject = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));

						$announcements_forum .= '
						
						
						<div class="row py-2 border-top">
	<div class="col align-self-center">
		<i class="fa-solid fa-circle smaller text-muted"></i> &nbsp;<a href="announcements.php?aid='.$aid.'">'.$subject.'</a>
	</div>
	<div class="col-lg-3 align-self-center text-lg-end">
		<a href="modcp.php?action=edit_announcement&amp;aid='.$aid.'"><i class="fa-solid fa-pencil"></i> &nbsp;edit</a> &nbsp;&nbsp; <a href="modcp.php?action=delete_announcement&amp;aid='.$aid.'"><i class="fa-solid fa-trash"></i> &nbsp;Delete</a>
	</div>
</div>
						
						
						
						';
					}
				}
			}

			// Build the list for any sub forums of this forum
			if(isset($forums_by_parent[$forum['fid']]))
			{
				fetch_forum_announcements($forum['fid'], $depth+1);
			}
		}
	}
}