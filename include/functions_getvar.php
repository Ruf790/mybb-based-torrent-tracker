<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

function getvar($name)
{
    if (is_array($name)) 
	{
        foreach ($name as $var) 
		{
            getvar($var);
        }
        return null;
    }

    if (!isset($_REQUEST[$name])) 
	{
        return false;
    }

    $_REQUEST[$name] = ssr($_REQUEST[$name]); // unescape just in case (legacy)

    $GLOBALS[$name] = $_REQUEST[$name];
    return $GLOBALS[$name];
}

function ssr($arg)
{
    if (is_array($arg)) 
	{
        foreach ($arg as $key => $val) 
		{
            $arg[$key] = ssr($val);
        }
    } 
	elseif (is_string($arg) && strpos($arg, '\\') !== false) 
	{
        // Only strip slashes if they actually exist
        $arg = stripslashes($arg);
    }

    return $arg;
}

if (!defined('IN_SCRIPT_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}
?>
