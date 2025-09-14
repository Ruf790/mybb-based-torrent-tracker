<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function rfc822 ($date, $timezone)
  {
    $fmtdate = gmdate ('D, d M Y H:i:s', $date);
    if ($timezone != '')
    {
      $fmtdate .= ' ' . str_replace (':', '', $timezone);
    }

    return $fmtdate;
  }

  function geturl ()
  {
    $thisURL = $_SERVER['SCRIPT_NAME'];
    $thisURL = str_replace ('/rss.php', '', $thisURL);
    return 'http://' . $_SERVER['HTTP_HOST'] . $thisURL;
  }

  function printrss ($timezone, $showrows, $feedtype, $categories)
  {
    global $SITENAME;
    global $BASEURL;
    global $SITEEMAIL;
    global $charset;
    global $parser_options;
	global $parser;
    $dreamerURL = geturl ();
    $locale = 'en-US';
    $desc = 'Latest Torrents on ' . $SITENAME;
    $title = $SITENAME . ' RSS Syndicator';
    $copyright = 'Copyright &copy; ' . date ('Y') . ' ' . $SITENAME;
    $webmaster = $SITEEMAIL;
    $ttl = 20;
    $allowed_timezones = array ('-12', '-11', '-10', '-9', '-8', '-7', '-6', '-5', '-4', '-3.5', '-3', '-2', '-1', '0', '1', '2', '3', '3.5', '4', '4.5', '5', '5.5', '6', '7', '8', '9', '9.5', '10', '11', '12');
    if (!in_array ($timezone, $allowed_timezones, 1))
    {
      $timezone = 1;
    }

    header ('Content-type: text/xml');
    echo '<?xml version="1.0" encoding="' . $charset . '"?>
';
    echo '<rss version="2.0">
          <channel>
            <title>' . htmlspecialchars_uni (addslashes ($title)) . '</title>
            <link>' . $dreamerURL . '</link>
            <description>' . $parser->parse_message($desc,$parser_options). '</description>
            <language>' . $locale . '</language>';
    echo '<image>
              <title>' . $title . '</title>
              <url>' . $dreamerURL . $imageUrl . '</url>
              <link>' . $dreamerURL . '</link>
              <width>100</width>
              <height>30</height>
              <description>' . $title . '</description>
            </image>';
    echo '      <copyright>' . htmlspecialchars_uni (addslashes ($copyright)) . '</copyright>
            <webMaster>' . htmlspecialchars_uni (addslashes ($webmaster)) . '</webMaster>
            <lastBuildDate>' . rfc822 (TIMENOW, $timezone) . '</lastBuildDate>
            <ttl>' . $ttl . '</ttl>
            <generator>' . $SITENAME . ' RSS Syndicator</generator>';
    printitems ($timezone, $showrows, $feedtype, $categories);
    echo '</channel></rss>';
  }

  function printitems ($timezone, $showrows, $feedtype, $categories)
  {
    global $SITENAME;
    global $BASEURL;
    global $SITEEMAIL;
    global $secret_key;
	global $db;
	global $parser_options;
	global $parser;
    $rowCount = 0;
    if ($categories == 'all')
    {
      $query = 'visible=\'yes\' AND banned=\'no\'';
    }
    else
    {
      $cats = explode (',', $categories);
      if (isset ($cats))
      {
        foreach ($cats as $value)
        {
          if (!is_valid_id ($value))
          {
            exit ();
            continue;
          }
        }

        $query .= 'category IN (' . implode (', ', $cats) . ') AND visible=\'yes\' AND banned=\'no\'';
      }
      else
      {
        $query = 'visible=\'yes\' AND banned=\'no\'';
      }
    }

    $getarticles = $db->sql_query ('' . 'SELECT torrents.seeders, torrents.leechers, torrents.filename, torrents.name, torrents.owner, torrents.descr, torrents.size, torrents.added, torrents.times_completed, torrents.id, torrents.anonymous, categories.name AS cat_name FROM torrents LEFT JOIN categories ON torrents.category = categories.id WHERE ' . $query . ' ORDER BY added DESC LIMIT ' . $showrows);
    if (0 < $db->num_rows ($getarticles))
    {
      while (($article = mysqli_fetch_array ($getarticles) AND $rowCount < $showrows))
      {
        $name = htmlspecialchars_uni (addslashes (strip_tags ($article['name'])));
        $article['descr'] = $parser->parse_message($article['descr'], $parser_options);
        $content = 'Name: ' . $name . ' / Category: ' . $article['cat_name'] . ' / Seeders: ' . intval ($article['seeders']) . ' / Leechers: ' . intval ($article['leechers']) . ' / Size: ' . mksize ($article['size']) . ' / Snatched: ' . intval ($article['times_completed']) . ' x times' . htmlspecialchars_uni ('<br /><br />') . htmlspecialchars_uni ($article['descr']);
        if ($feedtype == 'details')
        {
          $link = $BASEURL . '/details.php?id=' . intval ($article['id']);
        }
        else
        {
          $link = $BASEURL . '/download.php?type=rss' . htmlspecialchars ('&') . 'secret_key=' . $secret_key . htmlspecialchars ('&') . 'id=' . intval ($article['id']);
        }

        if ($article['anonymous'] == 'yes')
        {
          $owner = 'Anonymous';
        }
        else
        {
          $owner = $BASEURL . '/userdetails.php?id=' . htmlspecialchars_uni (addslashes (strip_tags ($article['owner'])));
        }

        $category = htmlspecialchars_uni (addslashes (strip_tags ($article['cat_name'])));
        $added = $article['added'];
        echo '<item>
		<title>' . $name . '</title>
		<description>' . $content . '</description>
		<link>' . $link . '</link>
		<author>' . $owner . '</author>
		<category>' . $category . '</category>
		<pubDate>' . $added . '</pubDate>
		</item>';
        $rowCount = $rowCount + 1;
      }
    }

  }

  require_once 'global.php';
  
  require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;
  
require_once(INC_PATH.'/class_plugins.php');
$plugins = new pluginSystem;

$parser_options = array(
	"allow_html" => 1,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
);
  
  define ('R_VERSION', 'v1.6 ');
  $secret_key = (isset ($_GET['secret_key']) ? htmlspecialchars ($_GET['secret_key']) : '');
  if ((empty ($secret_key) OR strlen ($secret_key) != 32))
  {
    exit ();
  }

  $query = $db->sql_query ('SELECT status, enabled FROM users WHERE passkey = ' . $db->sqlesc ($secret_key));
  if ($db->num_rows ($query) == 0)
  {
    exit ();
  }
  else
  {
    $user_account = mysqli_fetch_assoc ($query);
    if (((!$user_account OR $user_account['enabled'] != 'yes') OR $user_account['status'] != 'confirmed'))
    {
      exit ();
    }
    else
    {
      unset ($user_account);
    }
  }

  $categories = (isset ($_GET['categories']) ? htmlspecialchars_uni ($_GET['categories']) : 'all');
  $feedtype = (isset ($_GET['feedtype']) ? htmlspecialchars_uni ($_GET['feedtype']) : 'details');
  $timezone = (isset ($_GET['timezone']) ? htmlspecialchars_uni ($_GET['timezone']) : 1);
  $allowed_showrows = array ('5', '10', '20', '30', '40', '50');
  $showrows = ((isset ($_GET['showrows']) AND in_array ($_GET['showrows'], $allowed_showrows, 1)) ? intval ($_GET['showrows']) : 10);
  printrss ($timezone, $showrows, $feedtype, $categories);
?>
