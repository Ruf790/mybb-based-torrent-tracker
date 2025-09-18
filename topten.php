<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);
define("THIS_SCRIPT", "topten.php");
require "./global.php";
define("T_VERSION", "v.1.3.1 by xam");

if (isset($_GET["type"]) && $_GET["type"] == 6) {
    redirect("stats.php");
    exit;
}

include_once INC_PATH . "/functions_ratio.php";
$is_mod = is_mod($usergroups);
$xbt_active = "no";
$lang->load("topten");
$notin = "7,6,5";
stdhead($lang->topten["head"]);

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
    <link href="'.$BASEURL.'/include/templates/default/style/topten.css" rel="stylesheet">
   
</head>
<body>';

echo "<div class=\"container py-4\">";
    
// Header with stats
echo '<div class="row mb-4">';
echo '<div class="col-12">';
echo '<div class="glass-card p-4 text-center">';
echo '<h1 class="h3 mb-2 text-primary"><i class="bi bi-trophy-fill me-2"></i>' . $lang->topten["head"] . '</h1>';
echo '<p class="text-muted mb-0">' . $lang->topten["subtitle"] . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

$type = isset($_GET["type"]) ? intval($_GET["type"]) : 1;
if (!in_array($type, [1, 2, 3, 4, 5, 7])) {
    $type = 1;
}
$limit = isset($_GET["lim"]) ? 0 + $_GET["lim"] : false;
$subtype = isset($_GET["subtype"]) ? $_GET["subtype"] : false;

echo '
    <div class="glass-card p-3 mb-4">
        <ul class="nav nav-tabs nav-justified">
            <li class="nav-item">
                <a class="nav-link' . ($type == 1 ? ' active' : '') . '" href="topten.php?type=1">
                    <i class="bi bi-people-fill me-2"></i>' . $lang->topten["users"] . '
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link' . ($type == 2 ? ' active' : '') . '" href="topten.php?type=2">
                    <i class="bi bi-collection-play-fill me-2"></i>' . $lang->topten["torrents"] . '
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link' . ($type == 3 ? ' active' : '') . '" href="topten.php?type=3">
                    <i class="bi bi-globe-americas me-2"></i>' . $lang->topten["countries"] . '
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link' . ($type == 4 ? ' active' : '') . '" href="topten.php?type=4">
                    <i class="bi bi-hdd-stack-fill me-2"></i>' . $lang->topten["peers"] . '
                </a>
            </li>



<li class="nav-item">
        <a class="nav-link' . ($type == 5 ? ' active' : '') . '" href="topten.php?type=5">
            <i class="bi bi-tags-fill me-2"></i>' . $lang->topten["categories"] . '
        </a>
      </li>






<li class="nav-item">
        <a class="nav-link' . ($type == 7 ? ' active' : '') . '" href="topten.php?type=7">
            <i class="bi bi-collection me-2"></i>Forum
        </a>
      </li>




        </ul>
    </div>';

$pu = $is_mod ? true : false;
if (!$pu) {
    $limit = 10;
}






if ($type == 1) 
{
    
    if (!$limit || $limit > 250) 
    {
        $limit = 10;
    }
    


    $mainquery = "SELECT u.id as userid, u.username, u.usergroup, u.added, u.uploaded, u.downloaded, 
              u.uploaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(u.added)) AS upspeed,
              u.downloaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(u.added)) AS downspeed,
              u.avatar, u.avatardimensions, -- добавляем поле аватара
              g.namestyle, g.canstaffpanel, g.issupermod, g.cansettingspanel 
              FROM users u 
              LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
              WHERE u.enabled = 'yes' AND u.usergroup NOT IN (" . $notin . ")";






    if ($limit == 10 || $subtype == "ul") {
        $order = "uploaded DESC";
        ($r = $db->sql_query($mainquery . " ORDER BY " . $order . " LIMIT " . $limit));
        usertable($r, sprintf($lang->topten["type1_title1"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=1&lim=100&subtype=ul'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=1&lim=250&subtype=ul'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "dl") {
        $order = "downloaded DESC";
        ($r = $db->sql_query($mainquery . " ORDER BY " . $order . " LIMIT " . $limit));
        usertable($r, sprintf($lang->topten["type1_title2"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=1&lim=100&subtype=dl'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=1&lim=250&subtype=dl'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "uls") {
        $order = "upspeed DESC";
        ($r = $db->sql_query($mainquery . " ORDER BY " . $order . " LIMIT " . $limit));
        usertable($r, sprintf($lang->topten["type1_title3"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=1&lim=100&subtype=uls'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=1&lim=250&subtype=uls'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "dls") {
        $order = "downspeed DESC";
        ($r = $db->sql_query($mainquery . " ORDER BY " . $order . " LIMIT " . $limit));
        usertable($r, sprintf($lang->topten["type1_title4"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=1&lim=100&subtype=dls'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=1&lim=250&subtype=dls'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "bsh") {
        $order = "uploaded / downloaded DESC";
        $extrawhere = " AND downloaded > 1073741824";
        ($r = $db->sql_query($mainquery . $extrawhere . " ORDER BY " . $order . " LIMIT " . $limit));
        usertable($r, sprintf($lang->topten["type1_title5"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=1&lim=100&subtype=bsh'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=1&lim=250&subtype=bsh'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "wsh") {
        $order = "uploaded / downloaded ASC, downloaded DESC";
        $extrawhere = " AND downloaded > 1073741824";
        ($r = $db->sql_query($mainquery . $extrawhere . " ORDER BY " . $order . " LIMIT " . $limit));
        usertable($r, sprintf($lang->topten["type1_title6"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=1&lim=100&subtype=wsh'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=1&lim=250&subtype=wsh'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
} 








elseif ($type == 2) 
{
    if (!$limit || $limit > 50) {
        $limit = 10;
    }
    if ($limit == 10 || $subtype == "act") {
        if ($xbt_active == "yes") {
            $r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN xbt_files_users AS p ON t.id = p.fid WHERE p.`left` > 0 GROUP BY t.id ORDER BY seeders + leechers DESC, seeders DESC, added ASC LIMIT " . $limit) || sqlerr(__FILE__, 287);
        } else {
            ($r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN peers AS p ON t.id = p.torrent WHERE p.seeder = 'no' GROUP BY t.id ORDER BY seeders + leechers DESC, seeders DESC, added ASC LIMIT " . $limit)) || sqlerr(__FILE__, 289);
        }
        _torrenttable($r, sprintf($lang->topten["type2_title1"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=2&lim=25&subtype=act'>" . $lang->topten["top25"] . "</a>] [<a href='topten.php?type=2&lim=50&subtype=act'>" . $lang->topten["top50"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "sna") {
        if ($xbt_active == "yes") {
            $r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN xbt_files_users AS p ON t.id = p.fid WHERE t.times_completed > 0 GROUP BY t.id ORDER BY times_completed DESC, added ASC LIMIT " . $limit) || sqlerr(__FILE__, 296);
        } else {
            ($r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN peers AS p ON t.id = p.torrent WHERE t.times_completed > 0 GROUP BY t.id ORDER BY times_completed DESC, added ASC LIMIT " . $limit)) || sqlerr(__FILE__, 298);
        }
        _torrenttable($r, sprintf($lang->topten["type2_title2"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=2&lim=25&subtype=sna'>" . $lang->topten["top25"] . "</a>] [<a href='topten.php?type=2&lim=50&subtype=sna'>" . $lang->topten["top50"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "mdt") {
        if ($xbt_active == "yes") {
            $r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN xbt_files_users AS p ON t.id = p.fid WHERE times_completed > 0 GROUP BY t.id ORDER BY data DESC, added ASC LIMIT " . $limit) || sqlerr(__FILE__, 305);
        } else {
            ($r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN peers AS p ON t.id = p.torrent WHERE times_completed > 0 GROUP BY t.id ORDER BY data DESC, added ASC LIMIT " . $limit)) || sqlerr(__FILE__, 307);
        }
        _torrenttable($r, sprintf($lang->topten["type2_title3"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=2&lim=25&subtype=mdt'>" . $lang->topten["top25"] . "</a>] [<a href='topten.php?type=2&lim=50&subtype=mdt'>" . $lang->topten["top50"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "bse") {
        if ($xbt_active == "yes") {
            $r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN xbt_files_users AS p ON t.id = p.fid WHERE seeders >= 5 GROUP BY t.id ORDER BY seeders DESC, seeders+leechers DESC, added ASC LIMIT " . $limit) || sqlerr(__FILE__, 314);
        } else {
            ($r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN peers AS p ON t.id = p.torrent WHERE seeders >= 5 GROUP BY t.id ORDER BY seeders DESC, seeders+leechers DESC, added ASC LIMIT " . $limit)) || sqlerr(__FILE__, 316);
        }
        _torrenttable($r, sprintf($lang->topten["type2_title4"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=2&lim=25&subtype=bse'>" . $lang->topten["top25"] . "</a>] [<a href='topten.php?type=2&lim=50&subtype=bse'>" . $lang->topten["top50"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "wse") {
        if ($xbt_active == "yes") {
            $r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN xbt_files_users AS p ON t.id = p.fid WHERE p.`left` > 0 AND leechers >= 5 AND times_completed > 0 GROUP BY t.id ORDER BY seeders / leechers ASC, leechers DESC LIMIT " . $limit) || sqlerr(__FILE__, 323);
        } else {
            ($r = $db->sql_query("SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data FROM torrents AS t LEFT JOIN peers AS p ON t.id = p.torrent WHERE p.seeder = 'no' AND leechers >= 5 AND times_completed > 0 GROUP BY t.id ORDER BY seeders / leechers ASC, leechers DESC LIMIT " . $limit)) || sqlerr(__FILE__, 325);
        }
        _torrenttable($r, sprintf($lang->topten["type2_title5"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=2&lim=25&subtype=wse'>" . $lang->topten["top25"] . "</a>] [<a href='topten.php?type=2&lim=50&subtype=wse'>" . $lang->topten["top50"] . "</a>]</small>" : ""));
    }


   // В раздел type == 2 (torrents) добавить:
    if ($limit == 10 || $subtype == "mcom") {

    $sql = "
        SELECT t.*, COUNT(c.id) AS comment_count
        FROM torrents t
        LEFT JOIN comments c ON t.id = c.torrent
        GROUP BY t.id
        ORDER BY comment_count DESC
        LIMIT ?
    ";

    $params = [(int)$limit];

    $r = $db->sql_query_prepared($sql, $params);


    mostcommentedtable($r, sprintf($lang->topten["type2_title_comments"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=2&lim=25&subtype=mcom'>" . $lang->topten["top25"] . "</a>]</small>" : ""));
}



} 








elseif ($type == 3) 
{
    
    
    
    
    if (!$limit || $limit > 50) {
        $limit = 10;
    }
    
    
    if ($limit == 10 || $subtype == "us") {
        

        $sql = "
           SELECT c.name, c.flagpic, COUNT(u.country) AS num
           FROM countries AS c
           LEFT JOIN users AS u ON u.country = c.id
           GROUP BY c.id, c.name, c.flagpic
           ORDER BY num DESC LIMIT ?";

           $params = [(int)$limit];
           $r = $db->sql_query_prepared($sql, $params);


        if ($r) {
            countriestable($r, sprintf($lang->topten["type3_title1"], $limit) . 
                ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=3&lim=25&subtype=us'>" . $lang->topten["top25"] . "</a>]</small>" : ""), 
                "Users"
            );
        }
    }

    if ($limit == 10 || $subtype == "ul") {
       


        $sql = "
    SELECT c.name, c.flagpic, SUM(u.uploaded) AS ul
    FROM users AS u
    LEFT JOIN countries AS c ON u.country = c.id
    WHERE u.enabled = 'yes'
    GROUP BY c.id, c.name, c.flagpic
    ORDER BY ul DESC
    LIMIT ?
";

$params = [(int)$limit];

$r = $db->sql_query_prepared($sql, $params);






        if ($r) {
            countriestable($r, sprintf($lang->topten["type3_title2"], $limit) . 
                ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=3&lim=25&subtype=ul'>" . $lang->topten["top25"] . "</a>]</small>" : ""), 
                "Uploaded"
            );
        }
    }

    if ($limit == 10 || $subtype == "avg") {
       


        $sql = "
    SELECT c.name, c.flagpic, SUM(u.uploaded)/COUNT(u.id) AS ul_avg
    FROM users AS u
    LEFT JOIN countries AS c ON u.country = c.id
    WHERE u.enabled = 'yes'
    GROUP BY c.id, c.name, c.flagpic
    HAVING SUM(u.uploaded) > 1099511627776 AND COUNT(u.id) >= 100
    ORDER BY ul_avg DESC
    LIMIT ?
";

$params = [(int)$limit];

$r = $db->sql_query_prepared($sql, $params);





        if ($r) {
            countriestable($r, sprintf($lang->topten["type3_title3"], $limit) . 
                ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=3&lim=25&subtype=avg'>" . $lang->topten["top25"] . "</a>]</small>" : ""), 
                "Average"
            );
        }
    }

    if ($limit == 10 || $subtype == "r") {
       



       $sql = "
    SELECT c.name, c.flagpic, SUM(u.uploaded)/SUM(u.downloaded) AS r
    FROM users AS u
    LEFT JOIN countries AS c ON u.country = c.id
    WHERE u.enabled = 'yes'
    GROUP BY c.id, c.name, c.flagpic
    HAVING SUM(u.uploaded) > 1099511627776 
       AND SUM(u.downloaded) > 1099511627776 
       AND COUNT(u.id) >= 100
    ORDER BY r DESC
    LIMIT ?
";

$params = [(int)$limit];

$r = $db->sql_query_prepared($sql, $params);







        if ($r) {
            countriestable($r, sprintf($lang->topten["type3_title4"], $limit) . 
                ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=3&lim=25&subtype=r'>" . $lang->topten["top25"] . "</a>]</small>" : ""), 
                "Ratio"
            );
        }
    }
}





elseif ($type == 4) {
    if (!$limit || $limit > 250) {
        $limit = 10;
    }
    if ($xbt_active == "yes") {
        echo '<div class="glass-card p-4 text-center">';
        echo '<div class="alert alert-warning mb-0">';
        echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>' . $lang->global["notavailable"];
        echo '</div>';
        echo '</div>';
        echo "</div></body></html>";
        stdfoot();
        exit;
    }
    if ($limit == 10 || $subtype == "ul") {
        





$notin = "8,7,6,5";

$sql = "
    SELECT
        users.id AS userid,
        usergroup,
        username,
        avatar,
        avatardimensions,
        IF(peers.uploaded >= peers.uploadoffset, (peers.uploaded - peers.uploadoffset), peers.uploadoffset) / 
            IF(UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started) != 0, UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started), 1) AS uprate,
        IF(seeder = 'yes', 
            (peers.downloaded - peers.downloadoffset) / IF(finishedat - UNIX_TIMESTAMP(started) != 0, finishedat - UNIX_TIMESTAMP(started), 1), 
            (peers.downloaded - peers.downloadoffset) / IF(UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started) != 0, UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started), 1)
        ) AS downrate,
        g.namestyle,
        g.canstaffpanel,
        g.issupermod,
        g.cansettingspanel
    FROM peers
    LEFT JOIN users ON peers.userid = users.id
    LEFT JOIN usergroups g ON users.usergroup = g.gid
    WHERE usergroup NOT IN ({$notin})
    ORDER BY uprate DESC
    LIMIT ".$limit."
";



$r = $db->sql_query($sql);







        peerstable($r, sprintf($lang->topten["type4_title1"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=4&lim=100&subtype=ul'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=4&lim=250&subtype=ul'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
    if ($limit == 10 || $subtype == "dl") {
        ($r = $db->sql_query("SELECT users.id AS userid, avatar, avatardimensions, usergroup, peers.id AS peerid, username, peers.uploaded, peers.downloaded, IF(peers.uploaded >= peers.uploadoffset, (peers.uploaded - peers.uploadoffset), peers.uploadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started)) AS uprate, IF(seeder = 'yes',(peers.downloaded - peers.downloadoffset) / (finishedat - UNIX_TIMESTAMP(started)),(peers.downloaded - peers.downloadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started))) AS downrate, g.namestyle FROM peers LEFT JOIN users ON peers.userid = users.id LEFT JOIN usergroups g ON (users.usergroup=g.gid) ORDER BY downrate DESC LIMIT " . $limit));
        peerstable($r, sprintf($lang->topten["type4_title2"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=4&lim=100&subtype=dl'>" . $lang->topten["top100"] . "</a>] [<a href='topten.php?type=4&lim=250&subtype=dl'>" . $lang->topten["top250"] . "</a>]</small>" : ""));
    }
}







elseif ($type == 5) 
{
    if (!$limit || $limit > 50) {
        $limit = 10;
    }

    if ($limit == 10 || $subtype == "categories") {
       

$sql = "
    SELECT c.id, c.name, c.icon, 
           COUNT(t.id) AS torrents_count,
           SUM(t.seeders) AS total_seeders,
           SUM(t.leechers) AS total_leechers,
           SUM(t.times_completed) AS total_snatches,
           SUM(t.size) AS total_size
    FROM categories c 
    LEFT JOIN torrents t ON c.id = t.category 
    WHERE (t.visible = 'yes' OR t.id IS NULL)
    GROUP BY c.id 
    ORDER BY torrents_count DESC 
    LIMIT ?
";

$params = [(int)$limit];

$r = $db->sql_query_prepared($sql, $params);








        categoriestable($r, sprintf($lang->topten["type5_title_categories"], $limit) . ($limit == 10 && $pu ? " <small class='ms-2'>[<a href='topten.php?type=5&lim=25&subtype=categories'>" . $lang->topten["top25"] . "</a>]</small>" : ""));
    }

}



elseif ($type == 7) 
{
    if (!$limit || $limit > 50) {
        $limit = 10;
    }


if ($limit == 10 || $subtype == "active_threads") {
   


$sql = "
    SELECT t.tid, t.subject, t.uid, t.username, t.views, t.replies, 
           t.dateline, t.lastpost, t.lastposter,
           f.name AS forum_name,
           u.avatar,
           (t.replies + t.views) AS activity_score
    FROM tsf_threads t 
    LEFT JOIN tsf_forums f ON t.fid = f.fid
    LEFT JOIN users u ON t.uid = u.id
    WHERE t.visible = 1
    ORDER BY activity_score DESC 
    LIMIT ?
";

$params = [(int)$limit];

$r = $db->sql_query_prepared($sql, $params);





    activethreadstable($r, "Самые активные темы");
}

}











echo "</div></body></html>";
stdfoot();







function usertable($res, $frame_caption) {
    global $CURUSER, $lang, $regdateformat, $pic_base_url, $BASEURL, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-badge-fill me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 60px;">#</th>
                            <th scope="col" style="min-width: 200px;">' . $lang->topten["user"] . '</th>
                            <th scope="col" class="text-end" style="width: 120px;">' . $lang->topten["uploaded"] . '</th>
                            <th scope="col" class="text-end" style="width: 120px;">' . $lang->topten["ulspeed"] . '</th>
                            <th scope="col" class="text-end" style="width: 120px;">' . $lang->topten["downloaded"] . '</th>
                            <th scope="col" class="text-end" style="width: 120px;">' . $lang->topten["dlspeed"] . '</th>
                            <th scope="col" class="text-end" style="width: 100px;">' . $lang->topten["ratio"] . '</th>
                            <th scope="col" class="text-center" style="width: 120px;">' . $lang->topten["joined"] . '</th>
                        </tr>
                    </thead>
                    <tbody>';
    $num = 0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        
        
        $ratio_value = 0;
        $ratio_display = "∞";
        $color = "#000000";
        
        if ($a["downloaded"] > 0) {
            $ratio_value = $a["uploaded"] / $a["downloaded"];
            $ratio_display = number_format($ratio_value, 2);
            $color = get_ratio_color($ratio_value);
        }
        
        $ratio_badge = $ratio_display == "∞" ? 
            '<span class="badge bg-dark ratio-badge">∞</span>' : 
            '<span class="badge ratio-badge" style="background: ' . $color . '">' . $ratio_display . '</span>';
        
        $joindate = ($a["added"] == "0000-00-00 00:00:00") ? $lang->users["na"] : my_datee($regdateformat, $a["added"]);



$useravatarzz = format_avatar($a['avatar'], $a['avatardimensions']);
$ava22 = '<img class="user-avatar" src="'.$useravatarzz['image'].'" alt="" '.$useravatarzz['width_height'].' />';



        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $num . "</td>
                <td>
                    <div class='d-flex align-items-center'>
                        ".$ava22."
                        <div>
                            <a href='" . get_profile_link($a["userid"]) . "' class='text-decoration-none'>
                                <strong>" . get_user_color($a["username"], $a["namestyle"]) . "</strong>
                            </a>
                        </div>
                    </div>
                </td>
                <td class='text-end fw-bold text-success'>" . mksize($a["uploaded"]) . "</td>
                <td class='text-end text-info'>" . mksize($a["upspeed"]) . "/s</td>
                <td class='text-end fw-bold text-danger'>" . mksize($a["downloaded"]) . "</td>
                <td class='text-end text-warning'>" . mksize($a["downspeed"]) . "/s</td>
                <td class='text-end'>" . $ratio_badge . "</td>
                <td class='text-center text-muted small'>" . $joindate . "</td>
              </tr>";
    }
    echo '</tbody></table></div></div></div>';
}




function _torrenttable($res, $frame_caption) {
    global $lang, $BASEURL, $pic_base_url, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-collection-play-fill me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 60px;">#</th>
                            <th scope="col">' . $lang->topten["name"] . '</th>
                            <th scope="col" class="text-end" style="width: 100px;"><i class="bi bi-download me-1"></i>' . $lang->topten["snatched"] . '</th>
                            <th scope="col" class="text-end" style="width: 120px;">' . $lang->topten["data"] . '</th>
                            <th scope="col" class="text-end" style="width: 100px;"><i class="bi bi-arrow-up-circle me-1"></i>' . $lang->topten["seeders"] . '</th>
                            <th scope="col" class="text-end" style="width: 100px;"><i class="bi bi-arrow-down-circle me-1"></i>' . $lang->topten["leechers"] . '</th>
                            <th scope="col" class="text-end" style="width: 100px;">' . $lang->topten["total"] . '</th>
                            <th scope="col" class="text-end" style="width: 100px;">' . $lang->topten["ratio"] . '</th>
                        </tr>
                    </thead>
                    <tbody>';
    $num = 0;
    while ($a = $db->fetch_array($res))
	{
        $num++;
        $ratio = $a["leechers"] ? number_format($a["seeders"] / $a["leechers"], 2) : "∞";
        $color = $a["leechers"] ? get_ratio_color($a["seeders"] / $a["leechers"]) : "#000";
        $ratio_badge = $ratio == "∞" ? 
            '<span class="badge bg-dark ratio-badge">∞</span>' : 
            '<span class="badge ratio-badge" style="background: ' . $color . '">' . $ratio . '</span>';
        
        $SEOLink = get_torrent_link($a['id']);
        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $num . "</td>
                <td>
                    <a href='" . $SEOLink . "' class='text-decoration-none'>
                        <strong>" . cutename($a["name"], 55) . "</strong>
                    </a>
                </td>
                <td class='text-end fw-bold'>" . number_format($a["times_completed"]) . "</td>
                <td class='text-end text-info fw-bold'>" . mksize($a["data"]) . "</td>
                <td class='text-end text-success'>" . number_format($a["seeders"]) . "</td>
                <td class='text-end text-warning'>" . number_format($a["leechers"]) . "</td>
                <td class='text-end fw-bold'>" . ($a["leechers"] + $a["seeders"]) . "</td>
                <td class='text-end'>" . $ratio_badge . "</td>
              </tr>";
    }
    echo '</tbody></table></div></div></div>';
}

function countriestable($res, $frame_caption, $what) {
    global $CURUSER, $pic_base_url, $lang, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-globe-americas me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 60px;">#</th>
                            <th scope="col">' . $lang->topten["country"] . '</th>
                            <th scope="col" class="text-center" style="width: 150px;">' . $what . '</th>
                        </tr>
                    </thead>
                    <tbody>';
    $num = 0;
    while ($a = $db->fetch_array($res->result)) {
        $num++;
        $value = ($what == "Users") ? number_format($a["num"]) : (($what == "Uploaded") ? mksize($a["ul"]) : (($what == "Average") ? mksize($a["ul_avg"]) : number_format($a["r"], 2)));
        $value_class = ($what == "Ratio") ? "fw-bold" : "";
        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $num . "</td>
                <td>
                    <div class='d-flex align-items-center'>
                        <img src='" . $pic_base_url . "flag/" . $a["flagpic"] . "' class='flag-icon' alt='" . $a["name"] . "'>
                        <strong>" . $a["name"] . "</strong>
                    </div>
                </td>
                <td class='text-center " . $value_class . "'>" . $value . "</td>
              </tr>";
    }
    echo '</tbody></table></div></div></div>';
}

function peerstable($res, $frame_caption) {
    global $lang, $BASEURL, $pic_base_url, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-hdd-stack-fill me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 60px;">#</th>
                            <th scope="col">' . $lang->topten["user"] . '</th>
                            <th scope="col" class="text-end" style="width: 120px;">' . $lang->topten["ulspeed"] . '</th>
                            <th scope="col" class="text-end" style="width: 120px;">' . $lang->topten["dlspeed"] . '</th>
                        </tr>
                    </thead>
                    <tbody>';
    $n = 1;
    while ($arr = $db->fetch_array($res)) 
    {
        
        
        
        $useravatarzz = format_avatar($arr['avatar'], $arr['avatardimensions']);
$ava22 = '<img class="user-avatar" src="'.$useravatarzz['image'].'" alt="" '.$useravatarzz['width_height'].' />';
        
        
        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $n . "</td>
                <td>
                    <div class='d-flex align-items-center'>
                       ".$ava22."
                        <div>
                            <a href='" . get_profile_link($arr["userid"]) . "' class='text-decoration-none'>
                                <strong>" . format_name($arr["username"], $arr["usergroup"]) . "</strong>
                            </a>
                        </div>
                    </div>
                </td>
                <td class='text-end text-success fw-bold'>" . mksize($arr["uprate"]) . "/s</td>
                <td class='text-end text-warning fw-bold'>" . mksize($arr["downrate"]) . "/s</td>
              </tr>";
        $n++;
    }
    echo '</tbody></table></div></div></div>';
}












function commenterstable($res, $frame_caption) {
    global $lang, $pic_base_url, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-chat-dots-fill me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 60px;">#</th>
                            <th scope="col">' . $lang->topten["user"] . '</th>
                            <th scope="col" class="text-center" style="width: 120px;">' . $lang->topten["comments"] . '</th>
                        </tr>
                    </thead>
                    <tbody>';
    $num = 0;
    while ($a = $db->fetch_array($res->result)) {
        $num++;
        $avatar_url = !empty($a["avatar"]) ? $a["avatar"] : $pic_base_url . "user.png";
        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $num . "</td>
                <td>
                    <div class='d-flex align-items-center'>
                        <img src='" . $avatar_url . "' class='user-avatar' alt='User Avatar' onerror=\"this.src='" . $pic_base_url . "user.png'\">
                        <div>
                            <a href='" . get_profile_link($a["userid"]) . "' class='text-decoration-none'>
                                <strong>" . get_user_color($a["username"], $a["namestyle"]) . "</strong>
                            </a>
                        </div>
                    </div>
                </td>
                <td class='text-center fw-bold text-info'>" . number_format($a["comment_count"]) . "</td>
              </tr>";
    }
    echo '</tbody></table></div></div></div>';
}

function mostcommentedtable($res, $frame_caption) {
    global $lang, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-chat-quote-fill me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 60px;">#</th>
                            <th scope="col">' . $lang->topten["torrent"] . '</th>
                            <th scope="col" class="text-center" style="width: 100px;">' . $lang->topten["comments"] . '</th>
                            <th scope="col" class="text-center" style="width: 100px;">' . $lang->topten["snatched"] . '</th>
                        </tr>
                    </thead>
                    <tbody>';
    $num = 0;
    while ($a = $db->fetch_array($res->result)) {
        $num++;
        $SEOLink = get_torrent_link($a['id']);
        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $num . "</td>
                <td>
                    <a href='" . $SEOLink . "' class='text-decoration-none'>
                        <strong>" . cutename($a["name"], 55) . "</strong>
                    </a>
                </td>
                <td class='text-center fw-bold text-info'>" . number_format($a["comment_count"]) . "</td>
                <td class='text-center'>" . number_format($a["times_completed"]) . "</td>
              </tr>";
    }
    echo '</tbody></table></div></div></div>';
}









function categoriestable($res, $frame_caption) {
    global $lang, $pic_base_url, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-tags-fill me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 60px;">#</th>
                            <th scope="col">' . $lang->topten["category"] . '</th>
                            <th scope="col" class="text-center" style="width: 100px;">' . $lang->topten["torrents"] . '</th>
                            <th scope="col" class="text-center" style="width: 100px;">' . $lang->topten["seeders"] . '</th>
                            <th scope="col" class="text-center" style="width: 100px;">' . $lang->topten["leechers"] . '</th>
                            <th scope="col" class="text-center" style="width: 100px;">' . $lang->topten["snatches"] . '</th>
                            <th scope="col" class="text-center" style="width: 120px;">' . $lang->topten["total_size"] . '</th>
                        </tr>
                    </thead>
                    <tbody>';
    $num = 0;
    while ($a = $db->fetch_array($res->result)) {
        $num++;
        
        $category_link = "browse.php?cat=" . $a["id"];
        $icon_classes = !empty($a["icon"]) ? $a["icon"] : "fa-solid fa-folder";
        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $num . "</td>
                <td>
                    <div class='d-flex align-items-center'>
                        <div class='category-icon-wrapper'>
                            <i class='" . $icon_classes . "'></i>
                        </div>
                        <div class='ms-3'>
                            <a href='" . $category_link . "' class='text-decoration-none category-link'>
                                <strong>" . htmlspecialchars($a["name"]) . "</strong>
                            </a>
                        </div>
                    </div>
                </td>
                <td class='text-center fw-bold text-primary'>" . number_format($a["torrents_count"]) . "</td>
                <td class='text-center text-success'>" . number_format((int)($a["total_seeders"] ?? 0)) . "</td>
                <td class='text-center text-warning'>" . number_format((int)($a["total_leechers"] ?? 0)) . "</td>
                <td class='text-center text-info'>" . number_format((int)($a["total_snatches"] ?? 0)) . "</td>
                <td class='text-center'>" . mksize($a["total_size"]) . "</td>
              </tr>";
    }
    echo '</tbody></table></div></div></div>';
    
    // Стили для Font Awesome иконок категорий
    echo '<style>
   
 .category-icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
    }
    .category-icon-wrapper:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 12px rgba(13, 110, 253, 0.3);
    }
    .category-icon-wrapper i {
        transition: all 0.3s ease;
    }
    .category-link {
        color: #495057;
        transition: color 0.2s ease;
    }
    .category-link:hover {
        color: #0d6efd;
    }

    </style>';
}











function activethreadstable($res, $frame_caption) {
    global $lang, $pic_base_url, $db;
    echo '
    <div class="glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-chat-dots-fill me-2"></i>' . $frame_caption . '</span>
            <span class="stats-badge">Top ' . ($GLOBALS['limit'] ?? 10) . '</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center">#</th>
                            <th scope="col">Thread</th>
                            <th scope="col" class="text-center">Forum</th>
                            <th scope="col" class="text-center">Views</th>
                            <th scope="col" class="text-center">Replies</th>
                            <th scope="col" class="text-center">Activity</th>
                        </tr>
                    </thead>
                    <tbody>';
    $num = 0;
    while ($a = $db->fetch_array($res->result)) {
        $num++;
        $thread_link = "forums.php?action=viewthread&tid=" . $a["tid"];
        $avatar_url = !empty($a["avatar"]) ? $a["avatar"] : $pic_base_url . "user.png";
        
        echo "<tr class='hover-shadow'>
                <td class='text-center fw-bold text-muted'>" . $num . "</td>
                <td>
                    <div class='d-flex align-items-center'>
                        <img src='" . $avatar_url . "' class='user-avatar' alt='Avatar'>
                        <div class='ms-2'>
                            <a href='" . $thread_link . "' class='text-decoration-none'>
                                <strong>" . htmlspecialchars($a["subject"]) . "</strong>
                            </a>
                            <div class='text-muted small'>by " . htmlspecialchars($a["username"]) . "</div>
                        </div>
                    </div>
                </td>
                <td class='text-center'>" . htmlspecialchars($a["forum_name"]) . "</td>
                <td class='text-center text-info'>" . number_format($a["views"]) . "</td>
                <td class='text-center text-success'>" . number_format($a["replies"]) . "</td>
                <td class='text-center fw-bold'>" . number_format($a["activity_score"]) . "</td>
              </tr>";
    }
    echo '</tbody></table></div></div></div>';
}














?>