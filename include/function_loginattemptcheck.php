<?php

function login_attempt_check($uid = 0, $fatal = true)
{
	global $mybb, $lang, $db;

	$attempts = array();
	$uid = (int)$uid;
	$now = TIMENOW;

	// Get this user's login attempts and eventual lockout, if a uid is provided
	if($uid > 0)
	{
		$query = $db->simple_select("users", "loginattempts, loginlockoutexpiry", "id='{$uid}'", 1);
		$attempts = $db->fetch_array($query);

		if($attempts['loginattempts'] <= 0)
		{
			return 0;
		}
	}
	// This user has a cookie lockout, show waiting time
	elseif(!empty($mybb->cookies['lockoutexpiry']) && $mybb->cookies['lockoutexpiry'] > $now)
	{
		if($fatal)
		{
			$secsleft = (int)($mybb->cookies['lockoutexpiry'] - $now);
			$hoursleft = floor($secsleft / 3600);
			$minsleft = floor(($secsleft / 60) % 60);
			$secsleft = floor($secsleft % 60);

			stderr(sprintf('failed_login_wait', $hoursleft, $minsleft, $secsleft));
		}

		return false;
	}
	
	$failedlogincount = "1";
	$failedlogintime = "1";

	if($failedlogincount > 0 && isset($attempts['loginattempts']) && $attempts['loginattempts'] >= $failedlogincount)
	{
		// Set the expiry dateline if not set yet
		if($attempts['loginlockoutexpiry'] == 0)
		{
			$attempts['loginlockoutexpiry'] = $now + ((int)$failedlogintime * 60);

			// Add a cookie lockout. This is used to prevent access to the login page immediately.
			// A deep lockout is issued if he tries to login into a locked out account
			my_setcookie('lockoutexpiry', $attempts['loginlockoutexpiry']);

			$db->update_query("users", array(
				"loginlockoutexpiry" => $attempts['loginlockoutexpiry']
			), "id='{$uid}'");
		}

		if(empty($mybb->cookies['lockoutexpiry']))
		{
			$failedtime = $attempts['loginlockoutexpiry'];
		}
		else
		{
			$failedtime = $mybb->cookies['lockoutexpiry'];
		}

		// Are we still locked out?
		if($attempts['loginlockoutexpiry'] > $now)
		{
			if($fatal)
			{
				$secsleft = (int)($attempts['loginlockoutexpiry'] - $now);
				$hoursleft = floor($secsleft / 3600);
				$minsleft = floor(($secsleft / 60) % 60);
				$secsleft = floor($secsleft % 60);

				stderr(sprintf($lang->member['failed_login_wait'], $hoursleft, $minsleft, $secsleft));
			}

			return false;
		}
		// Unlock if enough time has passed
		else {

			if($uid > 0)
			{
				$db->update_query("users", array(
					"loginattempts" => 0,
					"loginlockoutexpiry" => 0
				), "id='{$uid}'");
			}

			// Wipe the cookie, no matter if a guest or a member
			my_unsetcookie('lockoutexpiry');

			return 0;
		}
	}

	if(!isset($attempts['loginattempts']))
	{
		$attempts['loginattempts'] = 0;
	}

	// User can attempt another login
	return $attempts['loginattempts'];
}
