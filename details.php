<?php



declare(strict_types=1);


define("SCRIPTNAME", "details.php");




require_once('global.php');

require_once 'cache/smilies.php';


require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;



if(!isset($CURUSER))
{
	
	stderr('You are not Logged');
}




require_once INC_PATH . '/functions_icons.php';

require_once(INC_PATH.'/commenttable.php');

require_once INC_PATH . '/functions_multipage.php';

require_once INC_PATH . '/functions_getagent.php';

require_once INC_PATH . '/functions_comment_attachments.php';




require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;
  
  
$parser_options = array(
	"allow_html" => 1,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
);

gzip();
maxsysop();

define('D_VERSION', '3.5.5');
define("IN_ARCHIVE", true);







 // If there is no tid but a pid, trick the system into thinking there was a tid anyway.
if(!empty($mybb->input['pid']) && !$mybb->input['id'])
{
	// see if we already have the post information
	if(isset($style) && $style['pid'] == $mybb->input['pid'] && $style['id'])
	{
		$mybb->input['id'] = $style['id'];
		unset($style['id']); // stop the thread caching code from being tricked
	}
	else
	{
		$options = array(
			"limit" => 1
		);
		$query = $db->simple_select("comments", "torrent", "id=".$mybb->input['pid'], $options);
		$post = $db->fetch_array($query);
		
		if(empty($post))
		{
			// post does not exist --> show error message
			stderr($lang->global['invalid_comm']);
		}
		
		$mybb->input['id'] = $post['torrent'];
	}
}






$is_mod = is_mod($usergroups);



function get_comment($pid)
{
	global $db;
	static $post_cache;

	$pid = (int)$pid;

	if(isset($post_cache[$pid]))
	{
		return $post_cache[$pid];
	}
	else
	{
		$query = $db->simple_select("comments", "*", "id = '{$pid}'");
		$post = $db->fetch_array($query);

		if($post)
		{
			$post_cache[$pid] = $post;
			return $post;
		}
		else
		{
			$post_cache[$pid] = false;
			return false;
		}
	}
}



function get_torrent(int $tid, bool $recache = false): array|false
{
    global $db;
    static $thread_cache = [];

    if (isset($thread_cache[$tid]) && !$recache) {
        return $thread_cache[$tid];
    }

    $query = $db->simple_select("torrents", "*", "id = '{$tid}'");
    $thread = $db->fetch_array($query);

    if ($thread) {
        $thread_cache[$tid] = $thread;
        return $thread;
    }

    $thread_cache[$tid] = false;
    return false;
}



// Get the torrent details from the database.
$Torrent = get_torrent((int)$mybb->input['id']);

if(!$Torrent)
{
	stderr($lang->global['notorrentid']);
}

$id = $Torrent['id'];










$query = $db->sql_query_prepared("
    SELECT t.name, t.banned, t.owner, n.nfo, c.name AS categoryname,
           c.pid, c.type, c.id AS categoryid, c.icon,
           u.id, u.username, u.usergroup, u.enabled, u.donor, u.warned, u.leechwarn,
           u.canupload, u.candownload, u.cancomment,
           (SELECT ROUND(AVG(r.rating), 1) FROM torrent_ratings r WHERE r.torrent_id = t.id) AS rating_avg,
           (SELECT COUNT(r.id) FROM torrent_ratings r WHERE r.torrent_id = t.id) AS rating_count
    FROM torrents t
    LEFT JOIN torrents_nfo n ON (t.id = n.torrent_id)
    LEFT JOIN categories c ON (t.category = c.id)
    LEFT JOIN users u ON (t.owner = u.id)
    WHERE t.id = ?
", [$id]);

// Проверка результата
if (!$query || $db->num_rows($query) == 0 || !($torrent2 = $db->fetch_array($query))) 
{
    stderr($lang->global['notorrentid']);
} 
elseif ($torrent2["banned"] == "yes" && !$is_mod) 
{
    stderr($lang->global['torrentbanned']);
}








	
	
$lang->load('details');
$lang->load('browse');
$lang->load('upload');







require_once(INC_PATH.'/functions_mkprettytime.php');




$SimilarTorrents = '';
$Torrent_name = $Torrent['name'];


$query = "
    SELECT MATCH(t.name) AGAINST(? IN BOOLEAN MODE) AS score,
           t.id, t.name, t.anonymous, t.owner, t.category, t.size, t.added, t.seeders,
           t.leechers, t.t_image, c.icon AS catimage, c.name AS catname, u.username, u.usergroup
    FROM torrents t
    LEFT JOIN categories c ON (c.id = t.category)
    LEFT JOIN users u ON (t.owner = u.id)
    WHERE MATCH(t.name) AGAINST(? IN BOOLEAN MODE)
      AND t.id != ?
      AND t.visible = 'yes'
      AND t.banned = 'no'
    ORDER BY score DESC
    LIMIT 12
";

// Параметры для prepared statement
$params = [$Torrent_name, $Torrent_name, $id];

// Выполнение подготовленного запроса
$query_result = $db->sql_query_prepared($query, $params);

// Проверка результата
if ($query_result && $db->num_rows($query_result) > 0)
{
    $FoundSMTQ = '';
    while ($SMTQ = $db->fetch_array($query_result)) {
        if ($SMTQ['score'] > 1) {
            $SEOLink  = get_torrent_link($SMTQ['id']);
            $SEOLinkC = ts_seo($SMTQ['category'], $SMTQ['catname'], 'c');

            $poster = !empty($SMTQ['t_image'])
                ? htmlspecialchars_uni($SMTQ['t_image'])
                : $BASEURL . '/include/templates/default/images/no_image.png';

            $uploaderHtml = (!$is_mod && $SMTQ['owner'] != $CURUSER['id'] && $SMTQ['anonymous'] == 'yes')
                ? '<span class="text-muted small"><i class="bi bi-eye-slash me-1"></i>' . $lang->global['anonymous'] . '</span>'
                : '<a href="' . get_profile_link($SMTQ['owner']) . '" class="text-decoration-none small">'
                  . format_name($SMTQ['username'], $SMTQ['usergroup']) . '</a>'
                  . ($SMTQ['anonymous'] == 'yes' ? ' <span class="text-muted small">(' . $lang->global['anonymous'] . ')</span>' : '');

            $FoundSMTQ .= '
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 border-0 shadow-sm similar-torrent-card">
                    <a href="' . $SEOLink . '" class="text-decoration-none">
                        <div class="position-relative overflow-hidden" style="height:160px;">
                            <img src="' . $poster . '"
                                 class="card-img-top w-100 h-100"
                                 style="object-fit:cover; transition: transform 0.3s ease;"
                                 alt="' . htmlspecialchars_uni($SMTQ['name']) . '"
                                 onerror="this.src=\'' . $BASEURL . '/include/templates/default/images/no_image.png\'">
                            <!-- Оверлей при наведении -->
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center similar-overlay">
                                <i class="bi bi-play-circle-fill text-white" style="font-size:2.5rem; opacity:0.9;"></i>
                            </div>
                            <!-- Категория -->
                            <a href="' . $SEOLinkC . '" class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-dark bg-opacity-75">
                                    <i class="' . $SMTQ['catimage'] . '"></i>
                                </span>
                            </a>
                            <!-- Сиды/личи -->
                            <div class="position-absolute bottom-0 start-0 m-2 d-flex gap-1">
                                <span class="badge bg-success bg-opacity-90">
                                    <i class="bi bi-arrow-up-circle me-1"></i>' . ts_nf($SMTQ['seeders']) . '
                                </span>
                                <span class="badge bg-danger bg-opacity-90">
                                    <i class="bi bi-arrow-down-circle me-1"></i>' . ts_nf($SMTQ['leechers']) . '
                                </span>
                            </div>
                        </div>
                    </a>
                    <div class="card-body p-2">
                        <a href="' . $SEOLink . '" class="text-decoration-none text-dark">
                            <h6 class="card-title mb-1 text-truncate small fw-semibold"
                                title="' . htmlspecialchars_uni($SMTQ['name']) . '">
                                ' . htmlspecialchars_uni($SMTQ['name']) . '
                            </h6>
                        </a>
                        <div class="d-flex justify-content-between align-items-center">
                            ' . $uploaderHtml . '
                            <span class="text-muted small">' . mksize($SMTQ['size']) . '</span>
                        </div>
                    </div>
                </div>
            </div>';
        }
    }

    if ($FoundSMTQ) {
        
		$SimilarTorrents = '
		
		
		<div class="container mt-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-collection me-2"></i>'.$lang->details['smililartorrents'].'</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                '.$FoundSMTQ.'
            </div>
        </div>
    </div>
</div>
<style>
/* Similar Torrents Cards */
.similar-torrent-card {
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.similar-torrent-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
}

.similar-overlay {
    background: rgba(0,0,0,0.3);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.similar-torrent-card:hover .similar-overlay {
    opacity: 1;
}

.similar-torrent-card:hover img {
    transform: scale(1.05);
}
</style>
<br>
		
		';
    
	
	}
}



	

if (empty($Torrent["tags"])) 
{
    $keywords = 'No Keywords Specified.';
} 
else 
{
    $tags = explode(",", $Torrent['tags']);
    $keywords = "";
    foreach ($tags as $tag) 
	{
        $keywords .= '<a href="'.$BASEURL.'/browse.php?do=search&keywords='.$tag.'&search_type=t_tags" title="'.$tag.'" class="badge bg-primary">'.$tag.'</a> ';
    }
    $keywords = substr($keywords, 0, (strlen($keywords) - 1));
}


if ($torrent2['type'] == 's')
{
	require(TSDIR.'/cache/categories.php');
	foreach ($_categoriesC as $catarray)
	{
		if ($catarray['id'] == $torrent2['pid'])
		{
			$parentcategory = $catarray['name'];
			$parentcatid = $catarray['id'];
			break;
		}
	}
	if ($parentcategory && $parentcatid)
	{
		$seolink = ts_seo($parentcatid,$parentcategory,'c');
		$seolink2 = ts_seo($torrent2['categoryid'],$torrent2['categoryname'],'c');
		$torrent2["categoryname"] = '<a href="'.$seolink.'" target="_self" alt="'.$parentcategory.'" title="'.$parentcategory.'" />'.$parentcategory.'</a> / <a href="'.$seolink2.'" target="_self" alt="'.$torrent2['categoryname'].'" title="'.$torrent2['categoryname'].'" />'.$torrent2['categoryname'].'</a>';
	}
}
else
{

	$seolink2 = ts_seo($torrent2['categoryid'],$torrent2['categoryname'],'c');
	
	$torrent2["categoryname"] = '
	<a href="'.$seolink2.'" target="_self" alt="'.$torrent2['categoryname'].'" title="'.$torrent2['categoryname'].'" />
	<i class="'.$torrent2['icon'].' fa-2x category-icon" title="'.$torrent2['categoryname'].'"></i>
	</a>';
}









$HEAD = sprintf($lang->details['detailsfor'], $Torrent['name']);
stdhead($HEAD);

require_once INC_PATH . '/functions_bookmark.php';


echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/details.css">';
echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/animate.min.css">';

echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/comment_attachments.css">';


echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/bookmark.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/details_modal.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/edit_torrent.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/popover.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/delete_torrent.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/advanced_torrent.js"></script>';


echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/report.js"></script>';

require_once INC_PATH . '/modals.php';

require_once 'details_edit.php';




$gigs = $CURUSER['downloaded'] / (1024*1024*1024);





if ($hitrun == 'yes')
{
	$ratio = ($CURUSER['downloaded'] > 0 ? $CURUSER['uploaded'] / $CURUSER['downloaded'] : 0);
	$percentage = $ratio * 100;
	
	if ($Torrent['free'] != 'yes' AND $usergroups['isvipgroup'] != 'yes' AND $ratio <= ($hitrun_ratio + 0.4) AND $Torrent['owner'] != $CURUSER['id'] AND !$is_mod AND $CURUSER['downloaded'] <> 0)
	{
		
		$warning_message = '<div class="container mt-3">
           <div class="red_alert mb-3" role="alert">
                '.sprintf($lang->details['downloadwarning'], number_format($ratio, 2), mksize($percentage), $hitrun_ratio).'
                </div>
            </div>';
		
	}
}


if (isset($warning_message))
{
	echo $warning_message;
}



$sratio = $Torrent['leechers'] > 0 ? $Torrent['seeders'] / $Torrent['leechers'] : 1;
$lratio = $Torrent['seeders'] > 0 ? $Torrent['leechers'] / $Torrent['seeders'] : 1;





















$showcommenttable = '';

$threadcount = 0;




// Rating
$user_rating = 0;
$rating_data = [
    'avg'         => (float)($torrent2['rating_avg'] ?? 0),
    'count'       => (int)($torrent2['rating_count'] ?? 0),
    'user_rating' => $user_rating,
];
if ($CURUSER['id']) {
    $q2 = $db->sql_query("SELECT rating FROM torrent_ratings WHERE torrent_id = {$id} AND user_id = {$CURUSER['id']} LIMIT 1");
    if ($ur = $db->fetch_array($q2)) $rating_data['user_rating'] = (int)$ur['rating'];
}

// Avg stars
$avg_stars_html = '';
for ($i = 1; $i <= 10; $i++) {
    $filled = $rating_data['avg'] >= $i;
    $half   = !$filled && $rating_data['avg'] >= $i - 0.5;
    if ($filled)    $avg_stars_html .= '<i class="bi bi-star-fill rating-star-filled"></i>';
    elseif ($half)  $avg_stars_html .= '<i class="bi bi-star-half rating-star-filled"></i>';
    else            $avg_stars_html .= '<i class="bi bi-star rating-star-empty"></i>';
}

// User stars
$user_stars_html = '';
if ($CURUSER['id']) {
    for ($i = 1; $i <= 10; $i++) {
        $active = $rating_data['user_rating'] >= $i ? 'active' : '';
        $user_stars_html .= '<i class="bi bi-star-fill user-star ' . $active . '" data-value="' . $i . '" onclick="rateTorrent(' . $i . ')"></i>';
    }
    $user_section = '
        <div class="d-flex align-items-center gap-2">
            <div class="user-stars d-flex gap-1" id="user-stars">' . $user_stars_html . '</div>
            <span class="small text-muted" id="rating-hint">' . ($rating_data['user_rating'] ? $rating_data['user_rating'] . '/10' : 'rate') . '</span>
        </div>';
} else {
    $user_section = '<a href="login.php" class="btn btn-sm btn-outline-primary rounded-pill">Login to rate</a>';
}

$rating_html = '
<style>
.rating-modern { background:#fff; border-radius:16px; padding:16px 20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:all 0.2s; }
.rating-modern:hover { box-shadow:0 4px 16px rgba(0,0,0,0.1); }
.rating-score { font-size:2.2rem; font-weight:800; color:#1a1a2e; line-height:1; }
.rating-stars { display:flex; gap:4px; font-size:1rem; }
.rating-star-filled { color:#f59e0b; }
.rating-star-empty { color:#e9ecef; }
.user-star { font-size:1.5rem; cursor:pointer; transition:0.15s; color:#dee2e6; }
.user-star:hover { transform:scale(1.15); color:#f59e0b !important; }
.user-star.active { color:#f59e0b; }
@media(max-width:768px) { .rating-score { font-size:1.8rem; } .user-star { font-size:1.2rem; } }
</style>
<div class="rating-modern">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="text-center">
                <div class="rating-score">' . ($rating_data['count'] > 0 ? $rating_data['avg'] : '—') . '</div>
                <div class="small text-muted">' . number_format($rating_data['count']) . ' votes</div>
            </div>
            <div class="rating-stars" id="rating-display">' . $avg_stars_html . '</div>
        </div>
        ' . $user_section . '
    </div>
</div>
<script src="' . $BASEURL . '/scripts/rating.js"></script>
<script>ratingInit(' . $rating_data['user_rating'] . ', ' . $id . ', "' . $BASEURL . '");</script>';

















$query = $db->simple_select("comments c", "COUNT(id) AS commentss", "torrent = '$id'");
$threadcount = $db->fetch_field($query, "commentss");

if (!$threadcount)
{
	$showcommenttable .= '
	
	<div class="container mt-3">
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  
		  
		  
		  <div style="display: block;" id="ajax_comment_preview">'.$lang->details['nocommentsyet'].'</div>
		  <div style="display: block;" id="ajax_comment_preview2"></div>
		  
		  
		  
		  
		  
		  
		  
	      </div>
	   </div>
	</div>';
	 
	
}
else
{
	

		$multipage = '';

        // Figure out if we need to display multiple pages.
		$page = 1;
		$perpage = $ts_perpage;
		if(isset($mybb->input['page']) && $mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

  
if (!empty($mybb->input['pid'])) 
{
    $post = get_comment($mybb->input['pid']);
    if ($post) 
	{
        
        $query = "
            SELECT COUNT(c.dateline) AS count 
            FROM comments c
            WHERE c.torrent = ? 
              AND c.dateline <= ?
        ";
        $params = [$id, $post['dateline']];

        
        $res = $db->sql_query_prepared($query, $params);

        if ($res) 
		{
           
            $result = $db->fetch_field($res, "count");

            if (($result % $perpage) == 0) {
                $page = $result / $perpage;
            } 
			else 
			{
                $page = intval($result / $perpage) + 1;
            }
        }
    }
}


        
  
    

$query = $db->simple_select("comments c", "COUNT(*) AS replies", "c.torrent='$id'");
$thread['replies'] = (int)$db->fetch_field($query, 'replies') - 1;
    
$postcount = $thread['replies'] + 1;
$pages = ceil($postcount / $perpage);

if(isset($mybb->input['page']) && $mybb->input['page'] == "last") {
    $page = $pages;
}

$page = (int)$page;
if($page > $pages || $page <= 0) {
    $page = 1;
}

if($page) {
    $start = ($page-1) * $perpage;
} else {
    $start = 0;
    $page = 1;
}

$upper = $start + $perpage;

$postcounter = "";
if(!$postcounter) { 
    if($page > 1) {
        if(!$ts_perpage || (int)$ts_perpage < 1) {
            $ts_perpage = 20;
        }
        $postcounter = $ts_perpage * ($page-1);
    } else {
        $postcounter = 0;
    }
}


$multipage = multipage((int)$postcount, (int)$perpage, (int)$page, str_replace("{id}", $id, TORRENT_URL_PAGED));







$allrows = [];

$query = "
    SELECT 
        c.id, c.torrent AS torrentid, c.text, c.user, c.editreason, c.dateline, c.editedby, c.editedat, 
        uu.username AS editedbyuname, gg.namestyle AS editbynamestyle, 
        u.added AS registered, u.enabled, u.lastactive, u.lastvisit, u.invisible, u.warned, u.leechwarn, u.username, u.usertitle, 
		u.usergroup, u.displaygroup, u.postnum, u.threadnum, u.added, u.comms,u.donor, u.uploaded, u.downloaded, 
		u.avatar AS useravatar, u.avatardimensions, u.signature, 
        g.title AS grouptitle, g.namestyle 
    FROM comments c
    LEFT JOIN users uu ON (c.editedby = uu.id)
    LEFT JOIN usergroups gg ON (uu.usergroup = gg.gid)
    LEFT JOIN users u ON (c.user = u.id)
    LEFT JOIN usergroups g ON (u.usergroup = g.gid)
    WHERE c.torrent = ?
    ORDER BY c.id
    LIMIT ?, ?
";

// Параметры запроса
$params = [
    (int)$id, 
    (int)$start, 
    (int)$perpage
];

$subres = $db->sql_query_prepared($query, $params);

if ($subres && isset($subres)) 
{
    while ($subrow = $db->fetch_array($subres)) 
	{
        $allrows[] = $subrow;
    }
    $db->free_result($subres);
}

// ── Bulk-load attachments for all comments (one query) ───────────────────────
$all_attachments = [];
if (!empty($allrows)) {
    $comment_ids = array_map(fn($r) => (int)$r['id'], $allrows);
    $ids_sql     = implode(',', $comment_ids);
    $att_res = $db->sql_query(
        "SELECT * FROM attachments
         WHERE comment_id IN ($ids_sql) AND visible = 1
         ORDER BY comment_id, dateuploaded ASC"
    );
    while ($att = $db->fetch_array($att_res)) {
        $all_attachments[(int)$att['comment_id']][] = $att;
    }
}
// Pass to commenttable via global
$GLOBALS['all_attachments'] = $all_attachments;







$showcommenttable .= '<div class="container mt-3">'.$multipage.'</div>'.commenttable($allrows,'','',false,true,true).'<div class="container mt-3">'.$multipage.'</div>';

	

	
	
	
	
}

$rowspan = 9;
$reseed = '';
if ($Torrent['seeders'] == 0 && $Torrent['ts_external'] == 'no')
{
	$reseed = '
	<tr>
		<td style="padding-left: 5px;" class="trow2" valign="top" width="147">'.$lang->details['askreseed'].'</td>
		<td valign="top" style="padding-left: 5px;">'.sprintf($lang->details['askreseed2'], $id).'</td>
	</tr>';
	$rowspan++;
}



if (isset($_GET['cerror']))
{
	switch ($_GET['cerror'])
	{
		case 1:
			$cerror = $lang->global['notorrentid'];
		break;
		case 2:
			$cerror = $lang->global['dontleavefieldsblank'];
		break;
		case 3:
			$cerror = sprintf($lang->global['flooderror'], $usergroups['floodlimit'], $lang->comment['floodcomment'], "-");
		break;
		default:
			$cerror = $lang->global['error'];
		break;
	}
}

if ($Torrent['ts_external'] == 'yes')
{
	$peerstable = sprintf($lang->details['peers3'], ts_nf($Torrent['seeders']), ts_nf($Torrent['leechers']), (ts_nf($Torrent['seeders'] + $Torrent['leechers']))).($Torrent['seeders'] == 0 && $Torrent['ts_external'] == 'no' ? '<br />'.sprintf($lang->details['askreseed2'],$id) : '');
}
else
{
	
	

$seeders = [];
$downloaders = [];

// Запрос с ?
$query = "
    SELECT p.seeder, p.finishedat, p.downloadoffset, p.uploadoffset, p.ip, p.port, p.uploaded, p.downloaded, p.to_go, 
           p.started AS st, p.connectable, p.agent, p.peer_id, p.last_action AS la, p.userid,  
           u.id, u.avatar, u.avatardimensions, u.invisible, u.enabled, u.username, u.displaygroup, u.warned, u.donor, g.namestyle 
    FROM peers p
    LEFT JOIN users u ON (p.userid=u.id)
    LEFT JOIN usergroups g ON (u.usergroup=g.gid)
    WHERE p.torrent = ?
";

// Параметры
$params = [$id];

$subres = $db->sql_query_prepared($query, $params);

// Проверка результата
if ($subres && $db->num_rows($subres) > 0) 
{
    while ($subrow = $db->fetch_array($subres)) 
	{
        if ($subrow['seeder'] === 'yes') 
		{
            $seeders[] = $subrow;
        } 
		else 
		{
            $downloaders[] = $subrow;
        }
    }
}











	function leech_sort($a,$b)
	{
		if ( isset( $_GET["usort"] ) ) return seed_sort($a,$b);
		$x = $a["to_go"];
		$y = $b["to_go"];
		if ($x == $y)
			return 0;
		if ($x < $y)
			return -1;
		return 1;
	}
	function seed_sort($a,$b)
	{
		$x = $a["uploaded"];
		$y = $b["uploaded"];
		if ($x == $y)
			return 0;
		if ($x < $y)
			return 1;
		return -1;
	}

	usort($seeders, "seed_sort");
	usort($downloaders, "leech_sort");

	// И исправьте вызовы функций:
$peerstable = dltable($lang->details['seeders2'], $seeders, $Torrent, true); // true - это сиды
$peerstable .= dltable($lang->details['leechers2'], $downloaders, $Torrent, false); // false - это личи
}





		



// Подключите функцию insert_bbcode_editor
require_once INC_PATH . '/editor.php';


// Вызов функции
$editor = insert_bbcode_editor($smilies, $BASEURL, 'message');



			
// Формирование HTML
$posthash = bin2hex(random_bytes(16));
$uploader = render_attachment_uploader($posthash, (int)$CURUSER['id']);
 
// ── Формирование HTML ────────────────────────────────────────────────────────
$showcommenttable .= '
<br />
<div class="container mt-4">
    <h2 class="mb-3">Quick Comment</h2>
    '.(!empty($cerror) ? '<div class="error">'.$cerror.'</div>' : '').'
    ' . ($useajax == 'yes' ? '<script src="' . $BASEURL . '/scripts/quick_comment.js"></script>' : '') . '
    ' . $editor['toolbar'] . '
    <form name="comment" id="comment" method="post" action="comment.php?action=add&tid=' . $id . '" novalidate>
        <input type="hidden" name="ctype" value="quickcomment">
        <input type="hidden" name="page" value="' . intval($page ?? ($_GET['page'] ?? 1)) . '">
        <input type="hidden" name="posthash" value="' . htmlspecialchars($posthash) . '">
        <div id="fileIdsContainer"></div>
        <div class="mb-3">
            <label for="message" class="form-label">Your Comment <small class="text-muted">(макс. 500 символов)</small></label>
            <textarea class="form-control" id="message" name="message" rows="6" placeholder="Write a comment, use BBCode..." maxlength="500" aria-describedby="charCount" required></textarea>
            <div id="charCount" class="form-text text-end">0 / 500</div>
        </div>
        <div id="message_preview" class="form-control mt-3 d-none"></div>
        ' . $uploader . '
        ' . ($useajax == 'yes' ? '
        <div class="d-flex align-items-center justify-content-center mb-3">
            <i id="loading-layer" class="fa-solid fa-circle-notch fa-spin" aria-label="Loading..." style="display:none; color: #0b59e0; width:24px; height:24px; margin-right: 10px;"></i>
            <button type="button" class="btn btn-primary me-2" id="quickcomment" onclick="TSajaxquickcomment(\'' . $id . '\');">' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid='.$id.'" class="btn btn-secondary">' . $lang->global['advancedbutton'] . '</a>
        </div>' : '
        <div class="d-flex gap-2 justify-content-center mb-3">
            <button type="submit" name="submit" class="btn btn-primary">' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid='.$id.'" class="btn btn-secondary">' . $lang->global['advancedbutton'] . '</a>
        </div>') . '
    </form>
    ' . $editor['modal'] . '
</div>';








if($Torrent['anonymous'] == 'yes' AND $Torrent['owner'] != $CURUSER['id'] AND !$is_mod)
{
	$username = '<i class="bi bi-eye-slash display-6 opacity-50 mb-2 d-block"></i>';                            
}
else
{
	$username = '<a href="'.get_profile_link($Torrent['owner']).'">'.format_name($torrent2['username'], $torrent2['usergroup']).'</a>' . get_user_icons ($torrent2) .'';
}








$ShowTLINK = '';

if (!empty($Torrent['t_link'])) 
{
    
    $html = $Torrent['t_link'];
    $hasHtml = (strpos($html, '<') !== false && strpos($html, '>') !== false);

    $Torrent['t_link'] = $html;

    $refresh = !empty($is_mod) ? ($lang->global['refresh'] ?? '') : '';

    $ShowTLINK = '
	
	<div class="container mt-3">
  <div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">
	<span style="float: right;">
		<div id="imdbupdatebutton" name="imdbupdatebutton">
		<a href="#" onclick="TS_IMDB('.$id.'); return false;"><b><u><i>'.$refresh.'</i></u></b></a></div></span>'.$lang->details['t_link'].'

     </div>
	 
    <div class="card-body"><div id="imdbdetails" name="imdbdetails">'.$Torrent['t_link'].'</div></div> 
   
  </div>
</div>
	
	<br />
	
	';


}

		



			

$show_manage = '';
if ($CURUSER['id'] === $torrent2['owner'] OR $is_mod)
{
    
	
	
	
	

$show_manage .= '
<div class="dropdown d-inline-block">
    <!-- Компактная кнопка с иконкой -->
    <a href="#" class="btn btn-light btn-icon rounded-circle p-2 shadow-sm" 
       role="button" id="manageCompactDropdown" data-bs-toggle="dropdown" aria-expanded="false"
       data-bs-toggle="tooltip" title="Manage Torrent">
        <i class="bi bi-three-dots-vertical text-primary"></i>
    </a>
    
    <!-- Компактное меню -->
    <ul class="dropdown-menu shadow border-0 rounded-2 p-1" aria-labelledby="manageCompactDropdown">
        <li>
            <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1" 
               href="#" data-bs-toggle="modal" data-bs-target="#add_data_Modal">
                <i class="bi bi-pencil-square text-success me-2"></i>
                <span>Quick Edit</span>
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1" 
               href="upload.php?id='.$id.'">
                <i class="bi bi-file-earmark-text text-info me-2"></i>
                <span>Full Edit</span>
            </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
            <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1 text-danger" 
           href="#" 
           data-bs-toggle="modal" 
           data-bs-target="#deleteTorrentModal"
           data-torrent-id="'.$id.'" 
           data-torrent-name="'.htmlspecialchars_uni($Torrent['name']).'">
            <i class="bi bi-trash3 me-2"></i>
            <span>Delete</span>
        </a>
        </li>
    </ul>
</div>';

	
  	
}


if ($is_mod)
{
	
	
	
	
	
	
$show_manage .= '
	
	
 <a href="'.$BASEURL.'/admin/index.php?act=hit_and_run&torrentid=='.$id.'">
 <i class="fa-solid fa-person-running fa-xl" style="color: #161718;" alt="Hit & Run" title="Hit & Run"></i></a>
	 
  
  <a href="'.$BASEURL.'/comment.php?tid='.$id.'&action='.($Torrent['allowcomments'] != 'yes' ? 'open' : 'close').'"  onmouseout="window.status=\'\'; return true;" onMouseOver="window.status=\''.($Torrent['allowcomments'] == 'no' ? $lang->details['open'] : $lang->details['close']).'\'; return true;">'.($Torrent['allowcomments'] != 'yes' ? 
  
  '<i class="fa-solid fa-comment-slash fa-xl" style="color: #e91b0c;" alt="'.$lang->details['open'].'" title="'.$lang->details['open'].'"></i>' : 
  
  '<i class="fa-solid fa-comment-slash fa-xl" style="color: #08e74b;" alt="'.$lang->details['close'].'" title="'.$lang->details['close'].'"></i>').'</a>
	
  
	
<a href="'.$BASEURL.'/admin/index.php?act=torrent_info&amp;id='.$id.'"><i class="fa-sharp fa-solid fa-info fa-xl" style="color: #94b4eb;" alt="Torrent Info" title="Torrent Info"></i></a>
  
	
<a href="'.$BASEURL.'/admin/index.php?act=fastdelete&amp;id='.$id.'"><i class="fa-solid fa-trash-can fa-xl" style="color: #eb0f0f;" alt="Delete Torrent" title="Delete Torrent"></i></a>';
	
	
	
	


}

  















function getFileIcon($filename) 
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        'video' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mpeg', 'mpg', '3gp', 'm4v', 'vob', 'ts', 'm2ts', 'ogv', 'rm', 'rmvb'],
        'audio' => ['mp3', 'flac', 'wav', 'ogg', 'm4a', 'aac'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'],
        'doc' => ['nfo', 'txt', 'md', 'log', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        'code' => ['php', 'html', 'css', 'js', 'json', 'xml', 'py', 'java', 'c', 'cpp', 'sh', 'bat'],
        'exec' => ['exe', 'iso', 'apk', 'bin', 'dll', 'app', 'deb', 'rpm'],
    ];

    $colors = [
        'video' => 'bg-danger',    // red
        'audio' => 'bg-primary',   // blue
        'image' => 'bg-success',   // green
        'archive' => 'bg-warning', // orange
        'doc' => 'bg-secondary',   // gray
        'code' => 'bg-info',       // cyan/light blue
        'exec' => 'bg-dark',       // black/dark
        'default' => 'bg-secondary'
    ];

    $iconClasses = [
        'video' => 'fa-solid fa-file-video',
        'audio' => 'fa-solid fa-file-audio',
        'image' => 'fa-solid fa-file-image',
        'archive' => 'fa-solid fa-file-zipper',
        'doc' => 'fa-solid fa-file-lines',
        'code' => 'fa-solid fa-file-code',
        'exec' => 'fa-solid fa-microchip',
        'default' => 'fa-solid fa-file',
    ];

    $type = 'default';
    foreach ($icons as $key => $exts) 
	{
        if (in_array($ext, $exts)) 
		{
            $type = $key;
            break;
        }
    }

    $colorClass = $colors[$type] ?? $colors['default'];
    $iconClass = $iconClasses[$type] ?? $iconClasses['default'];

    return '<span class="badge rounded-pill d-inline-flex align-items-center px-2 py-1 ' . $colorClass . ' text-white" aria-label="'.htmlspecialchars(strtoupper($ext)).'" title="'.htmlspecialchars(strtoupper($ext)).'">
                <i class="' . $iconClass . ' me-1" style="font-size: 1em;"></i>
                <small style="line-height:1;">' . htmlspecialchars(strtoupper($ext)) . '</small>
            </span>';
}




function renderAccordion($tree, $parentId = 'root', $level = 0) 
{
        static $counter = 0;
        $html = '<div class="accordion" id="accordion-' . $parentId . '">';
        foreach ($tree as $name => $content) 
		{
            if (is_array($content)) 
			{
                $accordionId = 'item-' . (++$counter);
				$showClass = '';           // ВСЕ папки свернуты по умолчанию
                $buttonCollapsed = 'collapsed';
                $ariaExpanded = 'false';
				
                $html .= '
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-' . $accordionId . '">
                        <button class="accordion-button ' . $buttonCollapsed . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $accordionId . '" aria-expanded="' . $ariaExpanded . '" aria-controls="collapse-' . $accordionId . '">
                        📁 ' . htmlspecialchars((string)$name) . '
                        </button>
                    </h2>
                    <div id="collapse-' . $accordionId . '" class="accordion-collapse collapse ' . $showClass . '" aria-labelledby="heading-' . $accordionId . '" data-bs-parent="#accordion-' . $parentId . '">
                        <div class="accordion-body">
                        ' . renderAccordion($content, $accordionId, $level + 1) . '
                        </div>
                    </div>
                </div>';
            } 
			else 
			{
                // File with icon and size
                $icon = getFileIcon($name);
                $html .= '<div class="ms-3 py-1"> ' . $icon . ' ' . htmlspecialchars($name) . ' <span class="badge bg-secondary">' . mksize($content) . '</span></div>';
            }
        }
        $html .= '</div>';
        return $html;
}

 



if (is_file(TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent") && ($Data = file_get_contents(TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent"))) 
{
    
	
	$TorrentPath = TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent";
    
    // Загружаем торрент
    $TorrentObj = TorrentFile::load($TorrentPath);

    $files = $TorrentObj->v1()->getFiles();
	

    // Step 1: Build folder structure
    $tree = [];



foreach ($files as $file) 
{
    $path = str_replace('\\', '/', implode('/', array_values((array)$file->path)));
    $size = $file->length;
    $parts = explode('/', $path);
    $current = &$tree;

    foreach ($parts as $i => $part) 
	{
        if ($i === count($parts) - 1) 
		{
            $current[$part] = $size; // file
        } 
		else 
		{
            if (!isset($current[$part])) 
			{
                $current[$part] = [];
            }
            $current = &$current[$part]; // folder
        }
    }
}

 
}






// Descr
$parsedDescr = $parser->parse_message($Torrent['descr'], $parser_options);
$descr = $parsedDescr;





// Screens
$screenshots = [];

// Подготовленный запрос
$query = "SELECT id, filename FROM `screenshots` WHERE torrent_id = ? ORDER BY sort_order ASC, id ASC";
$params = [$id];

$res = $db->sql_query_prepared($query, $params);

// Проверка результата
if ($res && isset($res)) 
{
    while ($row = $db->fetch_array($res)) 
	{
        $screenshots[] = $row;
    }
}



// Формирование HTML
$screensHtml = '<div class="row g-3">';
foreach ($screenshots as $shot) {
    $filename = htmlspecialchars($shot['filename']);
    $screenshotUrl = '/torrents/screens/' . $filename;

    $screensHtml .= '
    <div class="col-6 col-md-4 col-lg-3">
        <a href="#"
           class="screenshot-wrapper d-block position-relative overflow-hidden rounded-4"
           data-bs-toggle="modal"
           data-bs-target="#universalImageModal"
           data-img-src="' . $screenshotUrl . '"
           data-title="Screenshot">
            <img src="' . $screenshotUrl . '"
                 class="img-fluid rounded-4 transition-scale"
                 alt="Screenshot">
        </a>
    </div>';
}
$screensHtml .= '</div>';




















// Пример вывода t_image и t_image2 с одним модалом
$modal_images = ''; // инициализация
$images = [];

if (!empty($Torrent['t_image'])) 
{
    $images[] = $Torrent['t_image'];
}

if (!empty($Torrent['t_image2'])) 
{
    $images[] = $Torrent['t_image2'];
}

foreach ($images as $img) 
{
    $modal_images .= '
    <a href="#"
       data-bs-toggle="modal"
       data-bs-target="#universalImageModal"
       data-img-src="' . htmlspecialchars_uni($img) . '"
       data-title="' . htmlspecialchars_uni($Torrent['name']) . '">
        <img src="' . htmlspecialchars_uni($img) . '"
             class="rounded"
             width="400"
             alt="' . htmlspecialchars_uni($Torrent['name']) . '">
    </a>
    ';
}




















function getHealthColor(mixed $seeders, mixed $leechers): string
{
    $seeders = (int)$seeders;
    $leechers = (int)$leechers;
    
    return match (true) {
        $seeders === 0 => 'danger',
        $seeders >= 10 => 'success',
        $seeders >= 3 => 'warning',
        default => 'danger'
    };
}

function getHealthPercentage(mixed $seeders, mixed $leechers): int
{
    $seeders = (int)$seeders;
    $leechers = (int)$leechers;
    $total = $seeders + $leechers;
    
    return $total === 0 ? 0 : (int)round(($seeders / $total) * 100);
}

function getSeederPercentage(mixed $seeders, mixed $leechers): int
{
    return getHealthPercentage($seeders, $leechers);
}

function getLeecherPercentage(mixed $seeders, mixed $leechers): int
{
    $seeders = (int)$seeders;
    $leechers = (int)$leechers;
    $total = $seeders + $leechers;
    
    return $total === 0 ? 0 : (int)round(($leechers / $total) * 100);
}




$screenTab = '';
$screenContent = '';


if (!empty($screenshots)) 
{
    
    $screenTab = '
<li class="nav-item" role="presentation">
    <button class="nav-link fw-semibold" id="screen-tab" data-bs-toggle="tab" data-bs-target="#screen" type="button" role="tab" aria-controls="screen" aria-selected="false">
        <i class="bi bi-image me-2"></i>Screens
    </button>
</li>';


    $screenContent = '
    <div class="tab-pane fade" id="screen" role="tabpanel" aria-labelledby="screen-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
            ' . $screensHtml . '
        </div>
    </div>';
}



$act = "<span id=\"bookmark" . $Torrent['id'] . "\">" . 
            get_torrent_bookmark_state($CURUSER['id'], (int)$Torrent['id']) . 
       "</span>"; 
			   



$magnetButton = ($Torrent['ts_external'] === 'yes') 
    ? '<li><a class="dropdown-item magnet-btn" href="#" data-magnet-id="' . $id . '"><i class="bi bi-magnet me-2"></i>Magnet Link</a></li>'
    : '';










$nfoTab = '';
$nfoContent = '';

if (!empty($torrent2['nfo'])) {
    $nfoTab = '
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="nfo-tab" data-bs-toggle="tab" data-bs-target="#nfo" type="button" role="tab">
            <i class="bi bi-file-text me-2"></i>NFO
        </button>
    </li>';

    $nfoContent = '
    <div class="tab-pane fade" id="nfo" role="tabpanel" aria-labelledby="nfo-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-file-text me-2"></i>NFO File</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyNfo()">
                <i class="bi bi-clipboard me-1"></i>Copy
            </button>
        </div>
        <div class="card border-0" style="border: 1px solid #dee2e6 !important;">
            <div class="card-header d-flex justify-content-between align-items-center py-2"
                 style="background:#f1f3f5; border-bottom: 1px solid #dee2e6;">
                <span class="text-dark small">
                    <i class="fas fa-file-alt me-1 text-primary"></i>
                    ' . htmlspecialchars($Torrent['name']) . '.nfo
                </span>
            </div>
            <div class="card-body p-0">
                <pre id="nfoText"
                     style="background:#f8f9fa; color:#212529; font-family:\'Courier New\',monospace;
                            font-size:0.75rem; padding:1rem; margin:0; max-height:500px;
                            overflow-y:auto; white-space:pre; border-radius:0 0 8px 8px;">' 
                     . htmlspecialchars($torrent2['nfo']) . 
                '</pre>
            </div>
        </div>
    </div>';
}















$details = '


<!-- Основной контейнер -->
<div id="torrent_details" class="container mt-5">
    <!-- Хлебные крошки с плавной анимацией -->
    <nav aria-label="breadcrumb" class="mb-4 animate__animated animate__fadeIn">
        <ol class="breadcrumb bg-light p-3 rounded-3 shadow-sm">
            <li class="breadcrumb-item">
                <a href="/" class="text-decoration-none text-primary">
                    <i class="bi bi-house-door me-1"></i> Home
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="browse.php" class="text-decoration-none text-primary">
                    <i class="bi bi-folder me-1"></i> Browse
                </a>
            </li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page">
                '.htmlspecialchars_uni(mb_substr($Torrent['name'], 0, 50)).'...
            </li>
        </ol>
    </nav>

    <!-- Заголовок с улучшенным дизайном -->
    <div class="torrent-header mb-5">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div class="flex-grow-1">
                <h1 class="h3 mb-3 fw-bold text-dark animate__animated animate__fadeInDown">
                    <span class="status-badges me-2">'.GetTorrentTags($Torrent).'</span>
                    '.htmlspecialchars_uni($Torrent['name']).'
                </h1>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-light text-dark border shadow-sm">
                        <i class="bi bi-hash me-1"></i>ID: '.$id.'
                    </span>
                    <span class="badge bg-'.getHealthColor($Torrent['seeders'], $Torrent['leechers']).' shadow-sm">
                        <i class="bi bi-activity me-1"></i>Health: '.getHealthPercentage($Torrent['seeders'], $Torrent['leechers']).'%
                    </span>
					 '.$act.'
                </div>
            </div>
			
            <div class="flex-shrink-0 ms-3">
                
            </div>
        </div>
    </div>

    <!-- Карточка загрузки с градиентом и анимацией -->
    <div class="card shadow-lg border-0 mb-5 animate__animated animate__zoomIn">
        <div class="card-header bg-gradient bg-primary text-white py-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-0 fw-semibold">
                        <i class="bi bi-cloud-download me-2"></i>Download Torrent
                    </h4>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group" role="group">
                        <a href="'.get_download_link($id).'"
                           class="btn btn-light btn-lg d-flex align-items-center shadow-sm"
                           alt="'.$lang->details['dltorrent'].'"
                           title="'.$lang->details['dltorrent'].'">
                            <i class="bi bi-download me-2"></i>Download
                        </a>
                        <button type="button" class="btn btn-outline-light dropdown-toggle dropdown-toggle-split"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu shadow">
                            '.$magnetButton.'
                            <li><hr class="dropdown-divider"></li>
                            
							<li>
    <a class="dropdown-item report-btn" 
       href="#reportModal" 
       data-bs-toggle="modal"
       data-report-type="torrent"
       data-report-id="'.$id.'"
       data-report-userid="'.$Torrent['owner'].'"
       data-report-name="'.htmlspecialchars($Torrent['name'] ?? 'Torrent').'">
        <i class="bi bi-flag me-2"></i>Report Torrent
    </a>
</li>
							
							
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Тело карточки -->
        <div class="card-body p-4">
           
                        '.$modal_images.'
						
						
                  

            <!-- Прогресс-бары с улучшенным стилем -->
            <div class="stats-progress mb-4">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between mb-2">
                            
                            <span class="small fw-bold text-success">'.ts_nf($Torrent['seeders']).'</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 8px;">
                            <div class="progress-bar bg-success rounded-pill" style="width: '.getSeederPercentage($Torrent['seeders'], $Torrent['leechers']).'%"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between mb-2">
                            
                            <span class="small fw-bold text-warning">'.ts_nf($Torrent['leechers']).'</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 8px;">
                            <div class="progress-bar bg-warning rounded-pill" style="width: '.getLeecherPercentage($Torrent['seeders'], $Torrent['leechers']).'%"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small text-muted">Snatched</span>
                            <span class="small fw-bold text-info">'.ts_nf($Torrent['times_completed']).'</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 8px;">
                            <div class="progress-bar bg-info rounded-pill" style="width: '.min(100, $Torrent['times_completed']).'%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Табы с информацией -->
    <div class="card shadow-sm border-0 mb-5 animate__animated animate__fadeInUp">
        <div class="card-header bg-light p-0">
            <ul class="nav nav-tabs nav-fill" id="torrentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                        <i class="bi bi-info-circle me-2"></i>Information
                    </button>
                </li>
				
				' . $screenTab . '
				
				' . $nfoTab . '
				
				
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="files-tab" data-bs-toggle="tab" data-bs-target="#files" type="button" role="tab">
                        <i class="bi bi-folder me-2"></i>Files ('.ts_nf($Torrent['numfiles']).')
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="peers-tab" data-bs-toggle="tab" data-bs-target="#peers" type="button" role="tab">
                        <i class="bi bi-people me-2"></i>Peers ('.ts_nf($Torrent['seeders'] + $Torrent['leechers']).')
                    </button>
                </li>
				
				' . ((int)$Torrent['seeders'] === 0 && $CURUSER ? '
                <li class="nav-item align-self-center ms-2">
                    <a href="' . $BASEURL . '/takereseed.php?reseedid=' . (int)$Torrent['id'] . '"
                    class="btn btn-sm btn-outline-warning"
                    onclick="return confirm(\'Send reseed request to all previous downloaders?\')">
                <i class="bi bi-megaphone me-1"></i> Request Reseed
                    </a>
                 </li>' : '') . '

            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="torrentTabsContent">
                <!-- Вкладка информации -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="info-grid">
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-calendar me-1"></i>Uploaded</span>                                   
									<span class="fw-bold">
                                        '.my_datee($dateformat, $Torrent['added']).'
                                        <small class="text-muted ms-2">'.my_datee($timeformat, $Torrent['added']).'</small>
                                    </span>									
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-tag me-1"></i>Category</span>
                                    <span class="fw-bold">'.$torrent2['categoryname'].'</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-hdd me-1"></i>Size</span>
                                    <span class="fw-bold">'.mksize($Torrent['size']).'</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-hash me-1"></i>Hash</span>
                                    <span class="font-monospace small">'.($Torrent['info_hash'] ?? 'N/A').'</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-grid">
                                
								 <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-download me-1"></i>Snatched</span>
                                    <span class="badge bg-light text-dark">
									<a href="viewsnatches.php?id='.$id.'">'.ts_nf($Torrent['times_completed']).'</span></a>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-eye me-1"></i>Views</span>
                                    <span class="badge bg-light text-dark">'.ts_nf($Torrent['hits']).'</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-chat me-1"></i>Comments</span>
                                    <span class="badge bg-light text-dark">'.ts_nf($Torrent['comments']).'</span>
                                </div>
								
								
								
								
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-person me-1"></i>Uploader</span>
                                    <span class="fw-bold">'.$username.'</span>
                                </div>
                                '.($show_manage != '' ? '
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-gear me-1"></i>Manage Torrent</span>
                                    <span class="fw-bold">'.$show_manage.'</span>
                                </div>' : '').'
                            </div>
                        </div>
                    </div>
					
                    <!-- Теги с анимацией при наведении -->
                    '.($keywords ? '
                    <div class="mt-4">
                        <h6 class="fw-semibold"><i class="bi bi-tags me-2"></i>Tags</h6>
                        <div class="d-flex flex-wrap gap-2">'.$keywords.'</div>
                    </div>' : '').'
					
					'.$rating_html.'
					
                </div>
				
				
				
				
				
				

                <!-- Вкладка файлов -->
                <div class="tab-pane fade" id="files" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-semibold">File Structure</h6>
                        <div class="btn-group btn-group-sm">
                             <button type="button" class="btn btn-primary rounded-2 d-flex align-items-center gap-2" onclick="expandAllFiles()">
                                        <i class="bi bi-plus-circle"></i>
                                        <span>Expand All</span>
                                    </button>
                            
							<button type="button" class="btn btn-outline-primary rounded-2 d-flex align-items-center gap-2" onclick="collapseAllFiles()">
                                        <i class="bi bi-dash-circle"></i>
                                        <span>Collapse All</span>
                                    </button>
                       </div>
                    </div>
                    '.(isset($tree) && $tree !== null
    ? '<div class="file-tree">' . renderAccordion($tree) . '</div>'
    : '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Torrent file is missing</div>').'
                </div>
				' . $screenContent . '	
                
				' . $nfoContent . '
				
			
				
                <!-- Вкладка пиров -->
                <div class="tab-pane fade" id="peers" role="tabpanel">
                    '.$peerstable.'
                </div>
            </div>
        </div>
    </div>

    <!-- Описание с улучшенным форматированием -->
    <div class="card shadow-sm border-0 mt-5 animate__animated animate__fadeInUp">
        <div class="card-header bg-light">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-card-text me-2"></i>Description</h5>
        </div>
        <div class="card-body p-4">'.$descr.'</div>
    </div>

    <!-- Дополнительные секции -->
    '.$ShowTLINK.'
    '.$SimilarTorrents.'
    '.$showcommenttable.'
</div>

'.$magnetModal.'

<script type="text/javascript" src="'.$BASEURL.'/scripts/magnet.js"></script>

';




echo '

'.($is_mod ? '
<script type="text/javascript">
	l_updated = "'.$lang->global['imgupdated'].'";
	l_refresh = "'.$lang->global['refresh'].'";
</script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/quick_imdb.js"></script>' : '');


echo $details;




echo '
<script>
function copyNfo() {
    var text = document.getElementById("nfoText").textContent;
    navigator.clipboard.writeText(text).then(function() {
        showToast("NFO copied to clipboard!", "success");
    }).catch(function() {
        showToast("Copy failed", "danger");
    });
}
</script>';






stdfoot();
?>