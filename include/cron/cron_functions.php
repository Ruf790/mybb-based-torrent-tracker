<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  class trackerlanguage
  {
    var $path = null;
    var $language = null;
    function set_path ($path)
    {
      $this->path = $path;
    }

    function set_language ($language = 'english')
    {
      $language = str_replace (array ('/', '\\', '..'), '', trim ($language));
      if ($language == '')
      {
        $language = 'english';
      }

      $this->language = $language;
    }

    function load ($section)
    {
      $lfile = $this->path . '/' . $this->language . '/' . $section . '.lang.php';
      if (file_exists ($lfile))
      {
        require_once $lfile;
      }
      else
      {
        define ('errorid', 3);
        include_once TSDIR . '/ts_error.php';
        exit ();
      }

      if ((isset ($language) AND is_array ($language)))
      {
        foreach ($language as $key => $val)
        {
          if ((!isset ($this->$key) OR $this->$key != $val))
          {
            $val = preg_replace ('#\\{([0-9]+)\\}#', '%$1\\$s', $val);
            $this->$key = $val;
            continue;
          }
        }
      }

    }
  }

  function savelog ($Text)
  {
    global $db;
	//$db->sql_query ('INSERT INTO sitelog VALUES (NULL, NOW(), ' . $db->sqlesc ($Text) . ')');
	
	$insert_log = array(
		"added" => TIMENOW,
		"txt" => $db->escape_string($Text)
	);
	$db->insert_query("sitelog", $insert_log);
	
  }

  function tsrowcount ($C, $T, $E = '')
  {
    global $db;
	$Q = $db->sql_query('' . 'SELECT COUNT(' . $C . ') FROM ' . $T . ($E ? '' . ' WHERE ' . $E : ''));
    $R = mysqli_fetch_row ($Q);
    return $R[0];
  }

  function mksize ($bytes)
  {
    if ($bytes < 1000 * 1024)
    {
      return number_format ($bytes / 1024, 2) . ' KB';
    }

    if ($bytes < 1000 * 1048576)
    {
      return number_format ($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes < 1000 * 1073741824)
    {
      return number_format ($bytes / 1073741824, 2) . ' GB';
    }

    return number_format ($bytes / 1099511627776, 2) . ' TB';
  }



  function logcronaction ($filename, $querycount, $executetime)
  {
    global $db;
	$db->sql_query ('REPLACE INTO ts_cron_log (filename, querycount, executetime, runtime) VALUES (\'' . $filename . '\', \'' . $querycount . '\', \'' . $executetime . '\', \'' . TIMENOW . '\')');
  }

 function htmlspecialchars_uni($message)
  {
	$message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message); // Fix & but allow unicode
	$message = str_replace("<", "&lt;", $message);
	$message = str_replace(">", "&gt;", $message);
	$message = str_replace("\"", "&quot;", $message);
	return $message;
  }
  
  
function random_str($length=8, $complex=false)
{
	$set = array_merge(range(0, 9), range('A', 'Z'), range('a', 'z'));
	$str = array();

	// Complex strings have always at least 3 characters, even if $length < 3
	if($complex == true)
	{
		// At least one number
		$str[] = $set[my_rand(0, 9)];

		// At least one big letter
		$str[] = $set[my_rand(10, 35)];

		// At least one small letter
		$str[] = $set[my_rand(36, 61)];

		$length -= 3;
	}

	for($i = 0; $i < $length; ++$i)
	{
		$str[] = $set[my_rand(0, 61)];
	}

	// Make sure they're in random order and convert them to a string
	shuffle($str);

	return implode($str);
}
  
  function my_rand($min=0, $max=PHP_INT_MAX)
{
	// backward compatibility
	if($min === null || $max === null || $max < $min)
	{
		$min = 0;
		$max = PHP_INT_MAX;
	}

	if(version_compare(PHP_VERSION, '7.0', '>='))
	{
		try
		{
			$result = random_int($min, $max);
		} catch (Exception $e) {
		}

		if(isset($result))
		{
			return $result;
		}
	}

	$seed = secure_seed_rng();

	$distance = $max - $min;
	return $min + floor($distance * ($seed / PHP_INT_MAX) );
}

function secure_seed_rng()
{
	$bytes = PHP_INT_SIZE;

	do
	{

		$output = secure_binary_seed_rng($bytes);

		// convert binary data to a decimal number
		if ($bytes == 4)
		{
			$elements = unpack('i', $output);
			$output = abs($elements[1]);
		}
		else
		{
			$elements = unpack('N2', $output);
			$output = abs($elements[1] << 32 | $elements[2]);
		}

	} while($output > PHP_INT_MAX);

	return $output;
}


function secure_binary_seed_rng($bytes)
{
	$output = null;

	if(version_compare(PHP_VERSION, '7.0', '>='))
	{
		try
		{
			$output = random_bytes($bytes);
		} catch (Exception $e) {
		}
	}

	if(strlen($output) < $bytes)
	{
		if(@is_readable('/dev/urandom') && ($handle = @fopen('/dev/urandom', 'rb')))
		{
			$output = @fread($handle, $bytes);
			@fclose($handle);
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(function_exists('mcrypt_create_iv'))
		{
			if (DIRECTORY_SEPARATOR == '/')
			{
				$source = MCRYPT_DEV_URANDOM;
			}
			else
			{
				$source = MCRYPT_RAND;
			}

			$output = @mcrypt_create_iv($bytes, $source);
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(function_exists('openssl_random_pseudo_bytes'))
		{
			// PHP <5.3.4 had a bug which makes that function unusable on Windows
			if ((DIRECTORY_SEPARATOR == '/') || version_compare(PHP_VERSION, '5.3.4', '>='))
			{
				$output = openssl_random_pseudo_bytes($bytes, $crypto_strong);
				if ($crypto_strong == false)
				{
					$output = null;
				}
			}
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(class_exists('COM'))
		{
			try
			{
				$CAPI_Util = new COM('CAPICOM.Utilities.1');
				if(is_callable(array($CAPI_Util, 'GetRandom')))
				{
					$output = $CAPI_Util->GetRandom($bytes, 0);
				}
			} catch (Exception $e) {
			}
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		// Close to what PHP basically uses internally to seed, but not quite.
		$unique_state = microtime().@getmypid();

		$rounds = ceil($bytes / 16);

		for($i = 0; $i < $rounds; $i++)
		{
			$unique_state = md5(microtime().$unique_state);
			$output .= md5($unique_state);
		}

		$output = substr($output, 0, ($bytes * 2));

		$output = pack('H*', $output);

		return $output;
	}
	else
	{
		return $output;
	}
}



function trim_blank_chrs($string, $charlist="")
{
	$hex_chrs = array(
		0x09 => 1, // \x{0009}
		0x0A => 1, // \x{000A}
		0x0B => 1, // \x{000B}
		0x0D => 1, // \x{000D}
		0x20 => 1, // \x{0020}
		0xC2 => array(0x81 => 1, 0x8D => 1, 0x90 => 1, 0x9D => 1, 0xA0 => 1, 0xAD => 1), // \x{0081}, \x{008D}, \x{0090}, \x{009D}, \x{00A0}, \x{00AD}
		0xCC => array(0xB7 => 1, 0xB8 => 1), // \x{0337}, \x{0338}
		0xE1 => array(0x85 => array(0x9F => 1, 0xA0 => 1), 0x9A => array(0x80 => 1), 0xA0 => array(0x8E => 1)), // \x{115F}, \x{1160}, \x{1680}, \x{180E}
		0xE2 => array(0x80 => array(0x80 => 1, 0x81 => 1, 0x82 => 1, 0x83 => 1, 0x84 => 1, 0x85 => 1, 0x86 => 1, 0x87 => 1, 0x88 => 1, 0x89 => 1, 0x8A => 1, 0x8B => 1, 0x8C => 1, 0x8D => 1, 0x8E => 1, 0x8F => 1, // \x{2000} - \x{200F}
			0xA8 => 1, 0xA9 => 1, 0xAA => 1, 0xAB => 1, 0xAC => 1, 0xAD => 1, 0xAE => 1, 0xAF => 1), // \x{2028} - \x{202F}
			0x81 => array(0x9F => 1)), // \x{205F}
		0xE3 => array(0x80 => array(0x80 => 1), // \x{3000}
			0x85 => array(0xA4 => 1)), // \x{3164}
		0xEF => array(0xBB => array(0xBF => 1), // \x{FEFF}
			0xBE => array(0xA0 => 1), // \x{FFA0}
			0xBF => array(0xB9 => 1, 0xBA => 1, 0xBB => 1)), // \x{FFF9} - \x{FFFB}
	);

	$hex_chrs_rev = array(
		0x09 => 1, // \x{0009}
		0x0A => 1, // \x{000A}
		0x0B => 1, // \x{000B}
		0x0D => 1, // \x{000D}
		0x20 => 1, // \x{0020}
		0x81 => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{0081}, \x{2001}
		0x8D => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{008D}, \x{200D}
		0x90 => array(0xC2 => 1), // \x{0090}
		0x9D => array(0xC2 => 1), // \x{009D}
		0xA0 => array(0xC2 => 1, 0x85 => array(0xE1 => 1), 0x81 => array(0xE2 => 1), 0xBE => array(0xEF => 1)), // \x{00A0}, \x{1160}, \x{2060}, \x{FFA0}
		0xAD => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{00AD}, \x{202D}
		0xB8 => array(0xCC => 1), // \x{0338}
		0xB7 => array(0xCC => 1), // \x{0337}
		0x9F => array(0x85 => array(0xE1 => 1), 0x81 => array(0xE2 => 1)), // \x{115F}, \x{205F}
		0x80 => array(0x9A => array(0xE1 => 1), 0x80 => array(0xE2 => 1, 0xE3 => 1)), // \x{1680}, \x{2000}, \x{3000}
		0x8E => array(0xA0 => array(0xE1 => 1), 0x80 => array(0xE2 => 1)), // \x{180E}, \x{200E}
		0x82 => array(0x80 => array(0xE2 => 1)), // \x{2002}
		0x83 => array(0x80 => array(0xE2 => 1)), // \x{2003}
		0x84 => array(0x80 => array(0xE2 => 1)), // \x{2004}
		0x85 => array(0x80 => array(0xE2 => 1)), // \x{2005}
		0x86 => array(0x80 => array(0xE2 => 1)), // \x{2006}
		0x87 => array(0x80 => array(0xE2 => 1)), // \x{2007}
		0x88 => array(0x80 => array(0xE2 => 1)), // \x{2008}
		0x89 => array(0x80 => array(0xE2 => 1)), // \x{2009}
		0x8A => array(0x80 => array(0xE2 => 1)), // \x{200A}
		0x8B => array(0x80 => array(0xE2 => 1)), // \x{200B}
		0x8C => array(0x80 => array(0xE2 => 1)), // \x{200C}
		0x8F => array(0x80 => array(0xE2 => 1)), // \x{200F}
		0xA8 => array(0x80 => array(0xE2 => 1)), // \x{2028}
		0xA9 => array(0x80 => array(0xE2 => 1)), // \x{2029}
		0xAA => array(0x80 => array(0xE2 => 1)), // \x{202A}
		0xAB => array(0x80 => array(0xE2 => 1)), // \x{202B}
		0xAC => array(0x80 => array(0xE2 => 1)), // \x{202C}
		0xAE => array(0x80 => array(0xE2 => 1)), // \x{202E}
		0xAF => array(0x80 => array(0xE2 => 1)), // \x{202F}
		0xA4 => array(0x85 => array(0xE3 => 1)), // \x{3164}
		0xBF => array(0xBB => array(0xEF => 1)), // \x{FEFF}
		0xB9 => array(0xBF => array(0xEF => 1)), // \x{FFF9}
		0xBA => array(0xBF => array(0xEF => 1)), // \x{FFFA}
		0xBB => array(0xBF => array(0xEF => 1)), // \x{FFFB}
	);

	// Start from the beginning and work our way in
	$i = 0;
	do
	{
		// Check to see if we have matched a first character in our utf-8 array
		$offset = match_sequence($string, $hex_chrs);
		if(!$offset)
		{
			// If not, then we must have a "good" character and we don't need to do anymore processing
			break;
		}
		$string = substr($string, $offset);
	}
	while(++$i);

	// Start from the end and work our way in
	$string = strrev($string);
	$i = 0;
	do
	{
		// Check to see if we have matched a first character in our utf-8 array
		$offset = match_sequence($string, $hex_chrs_rev);
		if(!$offset)
		{
			// If not, then we must have a "good" character and we don't need to do anymore processing
			break;
		}
		$string = substr($string, $offset);
	}
	while(++$i);
	$string = strrev($string);

	if($charlist)
	{
		$string = trim($string, $charlist);
	}
	else
	{
		$string = trim($string);
	}

	return $string;
}


function match_sequence($string, $array, $i=0, $n=0)
{
	if($string === "")
	{
		return 0;
	}

	$ord = ord($string[$i]);
	if(array_key_exists($ord, $array))
	{
		$level = $array[$ord];
		++$n;
		if(is_array($level))
		{
			++$i;
			return match_sequence($string, $level, $i, $n);
		}
		return $n;
	}

	return 0;
}



function dec_to_utf8($src)
{
	$dest = '';

	if($src < 0)
	{
		return false;
	}
	elseif($src <= 0x007f)
	{
		$dest .= chr($src);
	}
	elseif($src <= 0x07ff)
	{
		$dest .= chr(0xc0 | ($src >> 6));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	elseif($src <= 0xffff)
	{
		$dest .= chr(0xe0 | ($src >> 12));
		$dest .= chr(0x80 | (($src >> 6) & 0x003f));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	elseif($src <= 0x10ffff)
	{
		$dest .= chr(0xf0 | ($src >> 18));
		$dest .= chr(0x80 | (($src >> 12) & 0x3f));
		$dest .= chr(0x80 | (($src >> 6) & 0x3f));
		$dest .= chr(0x80 | ($src & 0x3f));
	}
	else
	{
		// Out of range
		return false;
	}

	return $dest;
}



function my_strlen($string)
{
	global $lang, $charset;

	$string = preg_replace("#&\#([0-9]+);#", "-", $string);

	if(strtolower($charset) == "utf-8")
	{
		// Get rid of any excess RTL and LTR override for they are the workings of the devil
		$string = str_replace(dec_to_utf8(8238), "", $string);
		$string = str_replace(dec_to_utf8(8237), "", $string);

		// Remove dodgy whitespaces
		$string = str_replace(chr(0xCA), "", $string);
	}
	$string = trim($string);

	if(function_exists("mb_strlen"))
	{
		$string_length = mb_strlen($string);
	}
	else
	{
		$string_length = strlen($string);
	}

	return $string_length;
}


function _safe_serialize( $value )
{
	if(is_null($value))
	{
		return 'N;';
	}

	if(is_bool($value))
	{
		return 'b:'.(int)$value.';';
	}

	if(is_int($value))
	{
		return 'i:'.$value.';';
	}

	if(is_float($value))
	{
		return 'd:'.str_replace(',', '.', $value).';';
	}

	if(is_string($value))
	{
		return 's:'.strlen($value).':"'.$value.'";';
	}

	if(is_array($value))
	{
		$out = '';
		foreach($value as $k => $v)
		{
			$out .= _safe_serialize($k) . _safe_serialize($v);
		}

		return 'a:'.count($value).':{'.$out.'}';
	}

	// safe_serialize cannot my_serialize resources or objects
	return false;
}


function my_serialize($value)
{
	// ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if(function_exists('mb_internal_encoding') && (((int)ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_serialize($value);
	if(isset($mbIntEnc))
	{
		mb_internal_encoding($mbIntEnc);
	}

	return $out;
}





///////////////////////////////////////////////////////////MAIL

function &get_my_mailhandler($use_buitlin = false)
{
	global $mybb, $plugins;
	static $my_mailhandler;
	static $my_mailhandler_builtin;

	$mail_handler = "smtp";
	$mail_message_id = "1";
    $mail_parameters = "";
	
	if($use_buitlin)
	{
		// If our built-in mail handler doesn't exist, create it.
		if(!is_object($my_mailhandler_builtin))
		{
			require_once INC_PATH . "/class_mailhandler.php";

			// Using SMTP.
			if(isset($mail_handler) && $mail_handler == 'smtp')
			{
				require_once INC_PATH . "/mailhandlers/smtp.php";
				$my_mailhandler_builtin = new SmtpMail();
			}
			// Using PHP mail().
			else
			{
				require_once INC_PATH . "/mailhandlers/php.php";
				$my_mailhandler_builtin = new PhpMail();
				if(!empty($mail_parameters))
				{
					$my_mailhandler_builtin->additional_parameters = $mail_parameters;
				}
			}
		}

		if(isset($plugins) && is_object($plugins))
		{
			$plugins->run_hooks('my_mailhandler_builtin_after_init', $my_mailhandler_builtin);
		}

		return $my_mailhandler_builtin;
	}

	// If our mail handler doesn't exist, create it.
	if(!is_object($my_mailhandler))
	{
		require_once INC_PATH . "/class_mailhandler.php";

		if(isset($plugins) && is_object($plugins))
		{
			$plugins->run_hooks('my_mailhandler_init', $my_mailhandler);
		}

		// If no plugin has ever created the mail handler, resort to use the built-in one.
		if(!is_object($my_mailhandler) || !($my_mailhandler instanceof MailHandler))
		{
			$my_mailhandler = &get_my_mailhandler(true);
		}
	}

	return $my_mailhandler;
}






function my_mail($to, $subject, $message, $from="", $charset="", $headers="", $keep_alive=false, $format="text", $message_text="", $return_email="")
{
	global $mybb, $plugins;

	// Get our mail handler.
	$mail = &get_my_mailhandler();
    
	$mail_handler = "smtp";
	
	// If MyBB's built-in SMTP mail handler is used, set the keep alive bit accordingly.
	if($keep_alive == true && isset($mail->keep_alive) && isset($mail_handler) && $mail_handler == 'smtp')
	{
		require_once INC_PATH . "/class_mailhandler.php";
		require_once INC_PATH . "/mailhandlers/smtp.php";
		if($mail instanceof MailHandler && $mail instanceof SmtpMail)
		{
			$mail->keep_alive = true;
		}
	}

	// Following variables will help sequential plugins to determine how to process plugin hooks.
	// Mark this variable true if the hooked plugin has sent the mail, otherwise don't modify it.
	$is_mail_sent = false;
	// Mark this variable false if the hooked plugin doesn't suggest sequential plugins to continue processing.
	$continue_process = true;

	$my_mail_parameters = array(
		'to' => &$to,
		'subject' => &$subject,
		'message' => &$message,
		'from' => &$from,
		'charset' => &$charset,
		'headers' => &$headers,
		'keep_alive' => &$keep_alive,
		'format' => &$format,
		'message_text' => &$message_text,
		'return_email' => &$return_email,
		'is_mail_sent' => &$is_mail_sent,
		'continue_process' => &$continue_process,
	);

	if(isset($plugins) && is_object($plugins))
	{
		$plugins->run_hooks('my_mail_pre_build_message', $my_mail_parameters);
	}

	// Build the mail message.
	$mail->build_message($to, $subject, $message, $from, $charset, $headers, $format, $message_text, $return_email);

	if(isset($plugins) && is_object($plugins))
	{
		$plugins->run_hooks('my_mail_pre_send', $my_mail_parameters);
	}

	// Check if the hooked plugins still suggest to send the mail.
	if($continue_process)
	{
		$is_mail_sent = $mail->send();
	}

	if(isset($plugins) && is_object($plugins))
	{
		$plugins->run_hooks('my_mail_post_send', $my_mail_parameters);
	}

	return $is_mail_sent;
}





function send_mail_queue($count=10)
{
	global $db, $cache, $plugins;

	$plugins->run_hooks("send_mail_queue_start");

	// Check to see if the mail queue has messages needing to be sent
	//$mailcache = $cache->read("mailqueue");
	//if($mailcache !== false && $mailcache['queue_size'] > 0 && ($mailcache['locked'] == 0 || $mailcache['locked'] < TIMENOW-300))
	//{
		// Lock the queue so no other messages can be sent whilst these are (for popular boards)
		//$cache->update_mailqueue(0, TIMENOW);

		// Fetch emails for this page view - and send them
		$query = $db->simple_select("mailqueue", "*", "", array("order_by" => "mid", "order_dir" => "asc", "limit_start" => 0, "limit" => $count));

		while($email = $db->fetch_array($query))
		{
			// Delete the message from the queue
			$db->delete_query("mailqueue", "mid='{$email['mid']}'");

			if($db->affected_rows() == 1)
			{
				my_mail($email['mailto'], $email['subject'], $email['message'], $email['mailfrom'], "", $email['headers'], true);
			}
		}
		// Update the mailqueue cache and remove the lock
		$cache->update_mailqueue(TIMENOW, 0);
	//}

	$plugins->run_hooks("send_mail_queue_end");
}

////////////////////////////////////////////////////// END MAIL



  function deadtime222 ()
  {
    clearstatcache ();
    $Array = unserialize (file_get_contents (CONFIG_DIR . 'ANNOUNCE'));
    extract ($Array, EXTR_PREFIX_SAME, 'wddx');
    unset ($Array);
    return TIMENOW - floor ($announce_interval * 1.30000000000000004440892);
  }
  
  $announce_interval = 900;
  
  function deadtime( )
  {
    global $announce_interval;
     $announce_interval = '900';
	return TIMENOW - floor( $announce_interval * 1.3 );
  }

  if (!defined ('IN_CRON'))
  {
    exit ();
  }

?>
