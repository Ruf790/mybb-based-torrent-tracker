<?php


declare(strict_types=1);



function rfc822(int $date, string $timezone): string
{
    $fmtdate = gmdate('D, d M Y H:i:s', $date);
    if ($timezone !== '') {
        $fmtdate .= ' ' . str_replace(':', '', $timezone);
    }
    return $fmtdate;
}

function geturl(): string
{
    $thisURL = $_SERVER['SCRIPT_NAME'] ?? '';
    $thisURL = str_replace('/rss.php', '', $thisURL);
    $protocol = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https://' : 'http://';
    return $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $thisURL;
}

function printrss(string $timezone, int $showrows, string $feedtype, string $categories): void
{
    global $SITENAME, $BASEURL, $SITEEMAIL, $charset, $parser_options, $parser;
    
    $dreamerURL = geturl();
    $locale = 'en-US';
    $desc = 'Latest Torrents on ' . $SITENAME;
    $title = $SITENAME . ' RSS Syndicator';
    $copyright = 'Copyright &copy; ' . date('Y') . ' ' . $SITENAME;
    $webmaster = $SITEEMAIL;
    $ttl = 20;
    
    $allowed_timezones = [
        '-12', '-11', '-10', '-9', '-8', '-7', '-6', '-5', '-4', '-3.5', '-3', 
        '-2', '-1', '0', '1', '2', '3', '3.5', '4', '4.5', '5', '5.5', '6', 
        '7', '8', '9', '9.5', '10', '11', '12'
    ];
    
    if (!in_array($timezone, $allowed_timezones, true)) {
        $timezone = '1';
    }

    header('Content-Type: text/xml; charset=' . $charset);
    header('X-Content-Type-Options: nosniff');
    
    echo '<?xml version="1.0" encoding="' . $charset . '"?>' . "\n";
    echo '<rss version="2.0">' . "\n";
    echo '  <channel>' . "\n";
    echo '    <title>' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, $charset) . '</title>' . "\n";
    echo '    <link>' . htmlspecialchars($dreamerURL, ENT_XML1 | ENT_QUOTES, $charset) . '</link>' . "\n";
    echo '    <description>' . htmlspecialchars($parser->parse_message($desc, $parser_options), ENT_XML1 | ENT_QUOTES, $charset) . '</description>' . "\n";
    echo '    <language>' . htmlspecialchars($locale, ENT_XML1 | ENT_QUOTES, $charset) . '</language>' . "\n";
    
    // Image element
    $imageUrl = '/images/rss_logo.png'; // Default image path
    echo '    <image>' . "\n";
    echo '      <title>' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, $charset) . '</title>' . "\n";
    echo '      <url>' . htmlspecialchars($dreamerURL . $imageUrl, ENT_XML1 | ENT_QUOTES, $charset) . '</url>' . "\n";
    echo '      <link>' . htmlspecialchars($dreamerURL, ENT_XML1 | ENT_QUOTES, $charset) . '</link>' . "\n";
    echo '      <width>100</width>' . "\n";
    echo '      <height>30</height>' . "\n";
    echo '      <description>' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, $charset) . '</description>' . "\n";
    echo '    </image>' . "\n";
    
    echo '    <copyright>' . htmlspecialchars($copyright, ENT_XML1 | ENT_QUOTES, $charset) . '</copyright>' . "\n";
    echo '    <webMaster>' . htmlspecialchars($webmaster, ENT_XML1 | ENT_QUOTES, $charset) . '</webMaster>' . "\n";
    echo '    <lastBuildDate>' . htmlspecialchars(rfc822(TIMENOW, $timezone), ENT_XML1 | ENT_QUOTES, $charset) . '</lastBuildDate>' . "\n";
    echo '    <ttl>' . (int)$ttl . '</ttl>' . "\n";
    echo '    <generator>' . htmlspecialchars($SITENAME . ' RSS Syndicator', ENT_XML1 | ENT_QUOTES, $charset) . '</generator>' . "\n";
    
    printitems($timezone, $showrows, $feedtype, $categories);
    
    echo '  </channel>' . "\n";
    echo '</rss>';
}

function printitems(string $timezone, int $showrows, string $feedtype, string $categories): void
{
    global $SITENAME, $BASEURL, $SITEEMAIL, $secret_key, $db, $parser_options, $parser;
    
    $rowCount = 0;
    
    // Build query based on categories
    if ($categories === 'all') {
        $whereClause = "visible='yes' AND banned='no'";
    } else {
        $cats = explode(',', $categories);
        $validCats = [];
        
        foreach ($cats as $value) {
            if (is_valid_id($value)) {
                $validCats[] = (int)$value;
            }
        }
        
        if (!empty($validCats)) {
            $whereClause = 'category IN (' . implode(', ', $validCats) . ") AND visible='yes' AND banned='no'";
        } else {
            $whereClause = "visible='yes' AND banned='no'";
        }
    }

    $sql = "SELECT 
                torrents.seeders, 
                torrents.leechers, 
                torrents.filename, 
                torrents.name, 
                torrents.owner, 
                torrents.descr, 
                torrents.size, 
                torrents.added, 
                torrents.times_completed, 
                torrents.id, 
                torrents.anonymous, 
                categories.name AS cat_name 
            FROM torrents 
            LEFT JOIN categories ON torrents.category = categories.id 
            WHERE $whereClause 
            ORDER BY added DESC 
            LIMIT " . (int)$showrows;

    $getarticles = $db->sql_query($sql);
    
    if ($db->num_rows($getarticles) > 0) {
        while (($article = $db->fetch_array($getarticles)) && $rowCount < $showrows) {
            $name = htmlspecialchars(strip_tags($article['name'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $parsedDescription = $parser->parse_message($article['descr'] ?? '', $parser_options);
            
            $content = 'Name: ' . $name . 
                      ' / Category: ' . ($article['cat_name'] ?? 'Unknown') . 
                      ' / Seeders: ' . (int)($article['seeders'] ?? 0) . 
                      ' / Leechers: ' . (int)($article['leechers'] ?? 0) . 
                      ' / Size: ' . mksize($article['size'] ?? 0) . 
                      ' / Snatched: ' . (int)($article['times_completed'] ?? 0) . ' x times' . 
                      '<br /><br />' . $parsedDescription;

            // Generate appropriate link
            if ($feedtype === 'details') {
                $link = $BASEURL . '/details.php?id=' . (int)($article['id'] ?? 0);
            } else {
                $link = $BASEURL . '/download.php?type=rss&amp;secret_key=' . $secret_key . '&amp;id=' . (int)($article['id'] ?? 0);
            }

            // Handle anonymous owners
            if (($article['anonymous'] ?? '') === 'yes') {
                $owner = 'Anonymous';
            } else {
                $owner = $BASEURL . '/userdetails.php?id=' . (int)($article['owner'] ?? 0);
            }

            $category = htmlspecialchars($article['cat_name'] ?? 'Unknown', ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $added = rfc822((int)($article['added'] ?? TIMENOW), $timezone);

            echo '    <item>' . "\n";
            echo '      <title>' . $name . '</title>' . "\n";
            echo '      <description>' . htmlspecialchars($content, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</description>' . "\n";
            echo '      <link>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</link>' . "\n";
            echo '      <author>' . htmlspecialchars($owner, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</author>' . "\n";
            echo '      <category>' . $category . '</category>' . "\n";
            echo '      <pubDate>' . $added . '</pubDate>' . "\n";
            echo '      <guid isPermaLink="true">' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</guid>' . "\n";
            echo '    </item>' . "\n";
            
            $rowCount++;
        }
    }
}

// Main execution
require_once 'global.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/class_plugins.php';

$parser = new postParser();
$plugins = new pluginSystem();

$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

define('R_VERSION', 'v1.6');

// Validate secret key
$secret_key = $_GET['secret_key'] ?? '';
if (empty($secret_key) || strlen($secret_key) !== 32 || !ctype_xdigit($secret_key)) {
    http_response_code(401);
    exit('Invalid RSS key');
}

// Validate user
$query = $db->sql_query('SELECT ustatus, enabled FROM users WHERE passkey = ' . $db->sqlesc($secret_key));
if ($db->num_rows($query) === 0) {
    http_response_code(403);
    exit('Access denied');
}

$user_account = $db->fetch_array($query);
if (($user_account['enabled'] ?? '') !== 'yes' || ($user_account['ustatus'] ?? '') !== 'confirmed') {
    http_response_code(403);
    exit('Account not active');
}

// Get and validate parameters
$categories = $_GET['categories'] ?? 'all';
$feedtype = in_array($_GET['feedtype'] ?? '', ['details', 'download'], true) 
    ? $_GET['feedtype'] 
    : 'details';
    
$timezone = $_GET['timezone'] ?? '1';
$allowed_showrows = ['5', '10', '20', '30', '40', '50'];
$showrows = in_array($_GET['showrows'] ?? '', $allowed_showrows, true) 
    ? (int)$_GET['showrows'] 
    : 10;

// Generate RSS
printrss($timezone, $showrows, $feedtype, $categories);
?>