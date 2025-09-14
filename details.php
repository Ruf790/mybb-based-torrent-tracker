<?php
/*
************************************************
*==========[TS Special Edition v.5.6]==========*
************************************************
*              Special Thanks To               *
*        DrNet - wWw.SpecialCoders.CoM         *
*          Vinson - wWw.Decode4u.CoM           *
*    MrDecoder - wWw.Fearless-Releases.CoM     *
*           Fynnon - wWw.BvList.CoM            *
*==============================================*
*   Note: Don't Modify Or Delete This Credit   *
*     Next Target: TS Special Edition v5.7     *
*     TS SE WILL BE ALWAYS FREE SOFTWARE !     *
************************************************
*/

define("SCRIPTNAME", "details.php");


$templatelist = "imdb_table,peers_table,similartorrents_table,comment_posturl,postbit_online,postbit_offline,postbit_signature,postbit_avatar,commentstable,comment_editreason,comment_edit,comment_quickdelete,multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";


require_once('global.php');

require_once 'cache/smilies.php';


require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;



if(!isset($CURUSER))
{
	
	stderr('You are not Logged');
}


//require_once INC_PATH . '/editor.php';




require_once INC_PATH . '/functions_icons.php';

require_once(INC_PATH.'/commenttable.php');

require_once INC_PATH . '/functions_multipage.php';


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





//$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : '');
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



//if (!is_valid_id($id))
//{
	//print_no_permission(true);
//}


function get_torrent($tid, $recache = false)
{
	global $db;
	static $thread_cache;

	$tid = (int)$tid;

	if(isset($thread_cache[$tid]) && !$recache)
	{
		return $thread_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("torrents", "*", "id = '{$tid}'");
		$thread = $db->fetch_array($query);

		if($thread)
		{
			$thread_cache[$tid] = $thread;
			return $thread;
		}
		else
		{
			$thread_cache[$tid] = false;
			return false;
		}
	}
}



// Get the torrent details from the database.
$torrent = get_torrent($mybb->input['id']);

if(!$torrent)
{
	stderr($lang->global['notorrentid']);
}

$id = $torrent['id'];









if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    
	$name = htmlspecialchars($_POST['name']);
    $descr = htmlspecialchars($_POST['descr']);
    //$image_url = filter_var($_POST['t_image_url'], FILTER_VALIDATE_URL) ? $_POST['t_image_url'] : null;
  
	$id = $torrent['id'];
	
	
	$t_image_file = $_FILES['t_image_file'];
	$t_image_file2 = $_FILES['t_image_file2'];
	  
	$t_image_url = TS_Global('t_image_url');
	$t_image_url2 = TS_Global('t_image_url2');
	  
	$t_link = TS_Global('t_link');
	  
	$category = intval(TS_Global('category'));
	
	
	$free = TS_Global('free');
    $silver = TS_Global('silver');
    $doubleupload = TS_Global('doubleupload');
    $allowcomments = TS_Global('allowcomments');
    $sticky = TS_Global('sticky');
    $isrequest = TS_Global('isrequest');
    $isnuked = TS_Global( 'isnuked');
    $WhyNuked = TS_Global('WhyNuked');
	  
	  
	
	
	      
	
	
	  
	
	     if (!empty($t_image_file)) 
         {
            if (((( 0 < $t_image_file['size'] AND $t_image_file['error'] === 0 ) AND $t_image_file['tmp_name']) AND $t_image_file['name'])) 
			{
               $t_image_url = fix_url($t_image_file['name']);
               $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
               $ImageExt = get_extension($t_image_url);

               if (in_array($ImageExt, $AllowedFileTypes, true)) 
			   {
                  $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
                  $ImageDetails = getimagesize($t_image_file['tmp_name']);

                  if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true ))) 
				  {
                     if ($ImageContents = file_get_contents($t_image_file['tmp_name'])) 
					 {
                        $NewImageURL = $torrent_dir . '/images/' . $id . '.' . $ImageExt;

                        if (file_exists($NewImageURL)) 
						{
                           @unlink($NewImageURL);
                        }


                        if (file_put_contents($NewImageURL, $ImageContents)) 
						{
                           $COVERIMAGEUPDATED = true;
                           
						   $update_image2 = array(
			                 "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                   );
						
						   $db->update_query("torrents", $update_image2, "id='{$id}'");
						   $cache->update_torrents();
						   
						   
                        }
                     }
                  }
               }
            }
         }
		 
		 
		 
	     if (!empty($t_image_file2)) 
         {
            if (((( 0 < $t_image_file2['size'] AND $t_image_file2['error'] === 0 ) AND $t_image_file2['tmp_name']) AND $t_image_file2['name'])) 
			{
               $t_image_url2 = fix_url($t_image_file2['name']);
               $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
               $ImageExt = get_extension( $t_image_url2 );

               if (in_array($ImageExt, $AllowedFileTypes, true)) 
			   {
                  $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
                  $ImageDetails = getimagesize($t_image_file2['tmp_name']);

                  if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true))) 
				  {
                     if ($ImageContents = file_get_contents( $t_image_file2['tmp_name'] )) 
					 {
                        $NewImageURL = $torrent_dir . '/images/' . $id . '_2.' . $ImageExt;

                        if (file_exists($NewImageURL)) 
						{
                           @unlink($NewImageURL);
                        }


                        if (file_put_contents($NewImageURL, $ImageContents)) 
						{
                           $COVERIMAGEUPDATED = true;
                           
						   $update_image22 = array(
			                 "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                   );
						
						   $db->update_query("torrents", $update_image22, "id='{$id}'");
						   $cache->update_torrents();
						   
						   
                        }
                     }
                  }
               }
            }
         }	
		 
		 
		 
		 if (!empty($t_image_url)) 
         {
            $t_image_url = fix_url($t_image_url);
            $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
            $ImageExt = get_extension($t_image_url);

            if (in_array($ImageExt, $AllowedFileTypes, true)) 
			{
               $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
               $ImageDetails = getimagesize($t_image_url);

               if (($ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true) )) 
			   {
                  include_once(INC_PATH . '/functions_ts_remote_connect.php');

                  if ($ImageContents = TS_Fetch_Data($t_image_url, false)) 
				  {
                     $NewImageURL = $torrent_dir . '/images/' . $id . '.' . $ImageExt;

                     if (file_exists($NewImageURL)) 
					 {
                        @unlink($NewImageURL);
                     }


                     if (file_put_contents($NewImageURL, $ImageContents)) 
					 {
                        $COVERIMAGEUPDATED = true;
                       
						$update_image = array(
			                 "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                );
						
						$db->update_query("torrents", $update_image, "id='{$id}'");
						$cache->update_torrents();
								
                     }
                  }
               }
            }
         }




        if (!empty($t_image_url2)) 
         {
            $t_image_url2 = fix_url($t_image_url2);
            $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
            $ImageExt = get_extension($t_image_url2);

            if (in_array($ImageExt, $AllowedFileTypes, true)) 
			{
               $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
               $ImageDetails = getimagesize($t_image_url2);

               if (($ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true) )) 
			   {
                  include_once(INC_PATH . '/functions_ts_remote_connect.php');

                  if ($ImageContents = TS_Fetch_Data($t_image_url2, false)) 
				  {
                     $NewImageURL = $torrent_dir . '/images/' . $id . '_2.' . $ImageExt;

                     if (file_exists($NewImageURL)) 
					 {
                        @unlink($NewImageURL);
                     }


                     if (file_put_contents($NewImageURL, $ImageContents)) 
					 {
                        $COVERIMAGEUPDATED = true;
                       
						$update_image23 = array(
			                 "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                );
						
						$db->update_query("torrents", $update_image23, "id='{$id}'");
						$cache->update_torrents();
								
                     }
                  }
               }
            }
         }
		 
	
       	if (empty($t_image_url)) 
        {
     
			$image_types = array ('gif', 'jpg', 'jpeg', 'png', 'webp');
            foreach ($image_types as $image)
            {
               if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image))
               {
                  @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image);
                  continue;
               }
            }
			
			$UpdateSet['t_image'] = '';
        }
		 
		if (empty($t_image_url2)) 
        {
            
			$image_types2 = array ('gif', 'jpg', 'jpeg', 'png', 'webp');
            foreach ($image_types2 as $image2)
            {
               if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2))
               {
                 @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2);
                 continue;
               }
            }
			
			$UpdateSet['t_image2'] = '';
        }
		
		
		
		if ($free == 'yes') 
        {
               $UpdateSet['free'] = 'yes';
               $UpdateSet['silver'] = 'no';
        } 
        else 
	    {
               if ($silver == 'yes') 
               {
                  $UpdateSet['silver'] = 'yes';
                  $UpdateSet['free'] = 'no';                  
               } 
               else 
			   {
                  $UpdateSet['silver'] = 'no';
                  $UpdateSet['free'] = 'no';                  
               }
        }


        if ($doubleupload == 'yes') 
        {
               $UpdateSet['doubleupload'] = 'yes';
        } 
        else 
	    {
               $UpdateSet['doubleupload'] = 'no';
        }

          

        if ($allowcomments == 'no') 
        {
               $UpdateSet['allowcomments'] = 'no';
        } 
        else 
	    {
            $UpdateSet['allowcomments'] = 'yes';
        }


        if ($sticky == 'yes') 
        {
               $UpdateSet['sticky'] = 'yes';
        } 
        else 
	    {
           $UpdateSet['sticky'] = 'no';
        }


        if ($isnuked == 'yes') 
        {
               $UpdateSet['WhyNuked'] = $db->escape_string($WhyNuked);
               $UpdateSet['isnuked'] = 'yes';
        } 
        else 
	    {
               $UpdateSet['WhyNuked'] = '';
               $UpdateSet['isnuked'] = 'no';
        }
         


        if ($isrequest == 'yes') 
        {
            $UpdateSet['isrequest'] = 'yes';
        } 
        else 
		{
            $UpdateSet['isrequest'] = 'no';
        }
		
		
		
		$res = $db->update_query("torrents", $UpdateSet, "id='".$id."'");
		
		
		
		
		if (preg_match( '@^https:\/\/www.imdb.com\/title\/(.*)\/$@isU', $t_link, $result )) 
        {
               if ($result['0']) 
			   {
                  $t_link = $result['0'];
                  include_once(INC_PATH . '/ts_imdb.php');
                  $Update_tlink  = array ( 
				  
				       "t_link" => $db->escape_string($t_link),
					   "tags" =>  $db->escape_string($Genre)
				  
			     );
				  
				  $res = $db->update_query("torrents", $Update_tlink, "id='{$id}'");

                  unset($result);
				  
		          //$UpdateSet['tags'] = $db->escape_string($Genre);
               }
        }
		
		
		
		$noperm_array = array (
		      "name" => $db->escape_string($name),
		      "descr" => $db->escape_string($descr),
			  "category" => $db->escape_string($category)
	    );

	    $res = $db->update_query("torrents", $noperm_array, "id='{$id}'");
		$cache->update_torrents();
		    
}













// запрос
$query = $db->sql_query_prepared("
    SELECT t.name, n.nfo, c.name as categoryname,
           c.pid, c.type, c.id as categoryid, c.icon,
           p.canupload, p.candownload, p.cancomment,
           u.id, u.username, u.usergroup, u.enabled, u.donor, u.warned, u.leechwarn
    FROM torrents t
    LEFT JOIN ts_nfo n ON (t.id=n.id)
    LEFT JOIN categories c ON (t.category=c.id)
    LEFT JOIN users u ON (t.owner=u.id)
    LEFT JOIN ts_u_perm p ON (u.id=p.userid)
    WHERE t.id = ?
", [$id]);

// проверка результата
if (!$query || $db->num_rows($query) == 0 || !($torrent2 = $db->fetch_array($query))) {
    stderr($lang->global['notorrentid']);
}
elseif ($torrent["banned"] == "yes" && !$is_mod) {
    stderr($lang->global['torrentbanned']);
}

	
	
$lang->load('details');
$lang->load('browse');
$lang->load('upload');
$lang->load('quick_editor');


;
require_once(INC_PATH.'/functions_mkprettytime.php');




$SimilarTorrents = '';
$torrent_name = $torrent['name'];

// Подготавливаем запрос с ?
$query = "
    SELECT MATCH(t.name) AGAINST(? IN BOOLEAN MODE) as score,
           t.id, t.name, t.anonymous, t.owner, t.category, t.size, t.added, t.seeders,
           t.leechers, c.icon as catimage, c.name as catname, u.username, u.usergroup
    FROM torrents t
    LEFT JOIN categories c ON (c.id=t.category)
    LEFT JOIN users u ON (t.owner=u.id)
    WHERE MATCH(t.name) AGAINST(? IN BOOLEAN MODE)
      AND t.id != ?
      AND t.visible = 'yes'
      AND t.banned = 'no'
    ORDER BY score DESC
    LIMIT 10
";

// Параметры для prepared statement
$params = [$torrent_name, $torrent_name, $id];

$query_result = $db->sql_query_prepared($query, $params);

if ($query_result && $db->num_rows($query_result) > 0) {
    $FoundSMTQ = '';
    while ($SMTQ = $db->fetch_array($query_result)) {
        if ($SMTQ['score'] > 1) {
            $SEOLink = get_torrent_link($SMTQ['id']);
            $SEOLinkC = ts_seo($SMTQ['category'], $SMTQ['catname'], 'c');

            $FoundSMTQ .= '
            <tr>
                <td align="center" style="width: 40px; height: 36px;">
                    <a href="'.$SEOLinkC.'" data-toggle="tooltip" data-placement="top" title="'.$SMTQ['catname'].'">
                        <i class="'.$SMTQ['catimage'].' fa-2x category-icon"></i>
                    </a>
                </td>
                <td>
                    <a href="'.$SEOLink.'">'.htmlspecialchars_uni($SMTQ['name']).'</a>
                </td>
                <td>
                    <span class="small text-muted">
                        <i class="bi bi-calendar me-1"></i> '.my_datee($dateformat, $SMTQ['added']).'
                    </span><br>
                    <span class="small text-muted">
                        <i class="bi bi-clock me-1"></i> '.my_datee($timeformat, $SMTQ['added']).'
                    </span>
                </td>
                <td>
                    '.(!$is_mod && $SMTQ['owner'] != $CURUSER['id'] && $SMTQ['anonymous'] == 'yes'
                        ? '<div class="gray">'.$lang->global['anonymous'].'</div>'
                        : '<a href="'.get_profile_link($SMTQ['owner']).'">'.format_name($SMTQ['username'], $SMTQ['usergroup']).'</a>'
                          .($SMTQ['anonymous'] == 'yes' ? '<div class="gray">'.$lang->global['anonymous'].'</div>' : '')
                    ).'
                </td>
            </tr>';
        }
    }

    if ($FoundSMTQ) {
        eval("\$SimilarTorrents = \"".$templates->get("similartorrents_table")."\";");
    }
}


	

if (empty($torrent["tags"])) 
{
    $keywords = 'No Keywords Specified.';
} 
else 
{
    $tags = explode(",", $torrent['tags']);
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





$isfree = ($torrent['free'] == 'yes' ? '<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['freedownload'].'"><span class="badge bg-success">F</span></a>' : '');
$issilver = ($torrent['silver'] == 'yes' ? '

<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['silverdownload'].'">
<span class="badge bg-secondary">S</span></a>



' : '');



$isdoubleupload = ($torrent['doubleupload'] == 'yes' ? '


<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['dupload'].'">
<span class="badge bg-dark">x2</span></a>




' : '');





$HEAD = sprintf($lang->details['detailsfor'], $torrent['name']);
stdhead($HEAD, true, 'supernote','INDETAILS', '');




echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">';
echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/details.css">';



echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/details_modal.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/edit_torrent.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/tooltip.js"></script>';






$gigs = $CURUSER['downloaded'] / (1024*1024*1024);





if ($hitrun == 'yes')
{
	$ratio = ($CURUSER['downloaded'] > 0 ? $CURUSER['uploaded'] / $CURUSER['downloaded'] : 0);
	$percentage = $ratio * 100;
	
	if ($torrent['free'] != 'yes' AND $usergroups['isvipgroup'] != 'yes' AND $ratio <= ($hitrun_ratio + 0.4) AND $torrent['owner'] != $CURUSER['id'] AND !$is_mod AND $CURUSER['downloaded'] <> 0)
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



$sratio = $torrent['leechers'] > 0 ? $torrent['seeders'] / $torrent['leechers'] : 1;
$lratio = $torrent['seeders'] > 0 ? $torrent['leechers'] / $torrent['seeders'] : 1;




$showcommenttable = '';

$threadcount = 0;
//$threadcount = TSRowCount('id', 'comments', 'torrent='.$id);

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
    if ($post) {
        // Подготовленный запрос
        $query = "
            SELECT COUNT(c.dateline) AS count 
            FROM comments c
            WHERE c.torrent = ? 
              AND c.dateline <= ?
        ";
        $params = [$id, $post['dateline']];

        $res = $db->sql_query_prepared($query, $params);

        if ($res) {
            $result = $db->fetch_field($res, "count");

            if (($result % $perpage) == 0) {
                $page = $result / $perpage;
            } else {
                $page = intval($result / $perpage) + 1;
            }
        }
    }
}

        
  
        $query = $db->simple_select("comments c", "COUNT(*) AS replies", "c.torrent='$id'");
        $thread['replies'] = $db->fetch_field($query, 'replies')-1;
			
				
        $postcount = intval($thread['replies'])+1;
		$pages = $postcount / $perpage;
		$pages = ceil($pages);

		if(isset($mybb->input['page']) && $mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$upper = $start+$perpage;
		
		
		$postcounter = "";
		
		if(!$postcounter)
	    { // Used to show the # of the post
		if($page > 1)
		{
			if(!$ts_perpage || (int)$ts_perpage < 1)
			{
				$ts_perpage = 20;
			}

			$postcounter = $ts_perpage*($page-1);
		}
		else
		{
			$postcounter = 0;
		}
		
	    }
		
		
		$multipage = multipage($postcount, $perpage, $page, str_replace("{id}", $id, TORRENT_URL_PAGED));


	
	
$allrows = [];

$query = "
    SELECT c.id, c.torrent as torrentid, c.text, c.user, c.editreason, c.dateline, c.editedby, 
           c.editedat, c.totalvotes, uu.username as editedbyuname, 
           gg.namestyle as editbynamestyle, u.added as registered, u.enabled, u.lastactive, u.lastvisit, u.invisible,
           u.warned, u.leechwarn, u.username, u.usertitle, u.usergroup, u.displaygroup, u.postnum, u.threadnum, u.added, u.comms,
           u.donor, u.uploaded, u.downloaded, u.avatar as useravatar, u.avatardimensions, u.signature, 
           g.title as grouptitle, g.namestyle 
    FROM comments c
    LEFT JOIN users uu ON (c.editedby=uu.id)
    LEFT JOIN usergroups gg ON (uu.usergroup=gg.gid)
    LEFT JOIN users u ON (c.user=u.id)
    LEFT JOIN usergroups g ON (u.usergroup=g.gid)
    WHERE c.torrent = ?
    ORDER BY c.id
    LIMIT ?, ?
";

// Параметры: $id, $start, $perpage
$params = [$id, (int)$start, (int)$perpage];

$subres = $db->sql_query_prepared($query, $params);

if ($subres && $db->num_rows($subres) > 0) {
    while ($subrow = $db->fetch_array($subres)) {
        $allrows[] = $subrow;
    }
}

$showcommenttable .= '<div class="container mt-3">'.$multipage.'</div>'.commenttable($allrows,'','',false,true,true).'<div class="container mt-3">'.$multipage.'</div>';

	

	
	
	
	
}

$rowspan = 9;
$reseed = '';
if ($torrent['seeders'] == 0 && $torrent['ts_external'] == 'no')
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

if ($torrent['ts_external'] == 'yes')
{
	$peerstable = sprintf($lang->details['peers3'], ts_nf($torrent['seeders']), ts_nf($torrent['leechers']), (ts_nf($torrent['seeders'] + $torrent['leechers']))).($torrent['seeders'] == 0 && $torrent['ts_external'] == 'no' ? '<br />'.sprintf($lang->details['askreseed2'],$id) : '');
}
else
{
	function getagent($httpagent='', $peer_id="")
	{
		global $lang;
		return ($httpagent ? $httpagent : ($peer_id ? $peer_id : $lang->global['unknown']));
	}

	function dltable($name, $arr, $torrent)
	{
		global $CURUSER,$pic_base_url, $lang,$usergroups,$is_mod, $BASEURL, $templates;
		
		$totalcount = $arr && is_array($arr) ? count($arr) : 0;
		
		$p = '<b>'.$totalcount.' '.$name.'</b>';
		
		if ($totalcount <= 0) 
		{
           return $p;
        }
		
				
		eval("\$s = \"".$templates->get("peers_table")."\";");		
				
				
				
		$now = TIMENOW;
		include_once(INC_PATH.'/functions_ratio.php');
		foreach ($arr as $e)
		{

			//if ((preg_match('#I3#is', $e['options']) OR preg_match('#I4#is', $e['options'])) AND !$is_mod AND $CURUSER['id'] != $e['id'])
            if (!$is_mod AND $CURUSER['id'] != $e['id'])
			{
				$s .= '
				<tr>
					<td align="center">'.$lang->global['anonymous'].'</td>
					'.str_repeat('<td align="center">---</td>', 10).'
				</tr>';
				continue;
			}

			if (isset($num))
			{
				++$num;
			}
			else
			{
				$num = 1;
			}

			$dnsstuff = "<br /><a href=\"http://dnsstuff.com/tools/whois.ch?ip=".htmlspecialchars_uni($e["ip"])."\" target=_blank><font class=\"small\" color=\"brown\"><b>".htmlspecialchars_uni($e["ip"])."</b></font></a>:<u><font class=\"small\" color=\"red\"><b>". $e['port'] . "</b></font></u></td>\n";
			$pregreplace = "<br /><font class=\"small\" color=\"brown\"><b>".preg_replace('/\.\d+\.\d+$/', "***", htmlspecialchars_uni($e["ip"])) . "</b></font></a>:<u><font class=\"small\" color=\"red\"><b>". (int)$e['port'] . "</b></font></u></td>\n";
			$highlight = $CURUSER["id"] == $e["id"] ? " bgcolor=" : "";
			$s .= "<tr$highlight>\n";
			if (!empty($e["username"]))
			{

				if ($is_mod || $torrent['anonymous'] != 'yes' || $e['id'] != $torrent['owner'])
				{
					$s .= "<td style=\"white-space: nowrap; text-align: center;\"><a href=\"".get_profile_link($e['userid'])."\"><b>".get_user_color($e["username"],$e["namestyle"])."</b></a>" . ($e["donor"] == "yes" ? "<img src=".$pic_base_url."star.gif title='".$lang->global['imgdonated']."'>" : "") . ($e["enabled"] == "no" ? "<img src=".$pic_base_url."disabled.gif title=\"".$lang->global['imgdisabled']."\" style='margin-left: 2px'>" : ($e["warned"] == "yes" ? "<a href=\"rules.php#warning\" class=\"altlink\"><img src=\"".$pic_base_url."warned.gif\" title=\"".$lang->global['imgwarned']."\" border=\"0\"></a>" : ""));
					$s .= ($is_mod ? $dnsstuff : $pregreplace);
				}
				else
					$s .= "<td>".$lang->global['anonymous']."</a></td>\n";
			}
			else
				$s .= "<td>".$lang->global['unknown']."</td>\n";

			$secs = max(1, ($now - $e["st"]) - ($now - $e["la"]));
			$s .= "<td align=\"center\">" . ($e['connectable'] == "yes" ? "<font color=green>".$lang->details['yes']."</font>" : "<font color=red>".$lang->details['no']."</font>") . "</td>\n";
			$s .= "<td align=\"right\">" . mksize($e["uploaded"]) . "</td>\n";
			$s .= "<td align=\"right\"><span style=\"white-space: nowrap;\">" . mksize(($e["uploaded"] - $e["uploadoffset"]) / $secs) . "/s</span></td>\n";
			$s .= "<td align=\"right\">" . mksize($e["downloaded"]) . "</td>\n";
			if ($e["seeder"] == "no")
				$s .= "<td align=\"right\"><span style=\"white-space: nowrap;\">" . mksize(($e["downloaded"] - $e["downloadoffset"]) / $secs) . "/s</span></td>\n";
			else
				$s .= "<td align=\"right\"><span style=\"white-space: nowrap;\">" . mksize(($e["downloaded"] - $e["downloadoffset"]) / max(1, $e["finishedat"] - $e['st'])) .	"/s</span></td>\n";
			if ($e["downloaded"])
			{
			  $ratio = floor(($e["uploaded"] / $e["downloaded"]) * 1000) / 1000;
				$s .= "<td align=\"right\"><font color=" . get_ratio_color($ratio) . ">" . number_format($ratio, 2) . "</font></td>\n";
			}
			   else
			  if ($e["uploaded"])
				$s .= "<td align=\"right\">".$lang->details['inf']."</td>\n";
			  else
				$s .= "<td align=right>---</td>\n";
			$s .= "<td align=\"right\">" . sprintf("%.2f%%", 100 * (1 - ($e["to_go"] / $torrent["size"]))) . "</td>\n";
			$s .= "<td align=\"right\">" . mkprettytime($now - $e["st"]) . "</td>\n";
			$s .= "<td align=\"right\">" . mkprettytime($now - $e["la"]) . "</td>\n";
			$s .= "<td align=\"left\">" . htmlspecialchars_uni(getagent($e["agent"], $e["peer_id"])) . "</td>\n";
			$s .= "</tr>\n";
		}
		$s .= "</table>\n";
		return $s;
	}
	
	



$seeders = [];
$downloaders = [];

// Запрос с ?
$query = "
    SELECT p.seeder, p.finishedat, p.downloadoffset, p.uploadoffset, p.ip, p.port, p.uploaded, p.downloaded, p.to_go, 
           p.started AS st, p.connectable, p.agent, p.peer_id, p.last_action AS la, p.userid,  
           u.id, u.enabled, u.username, u.displaygroup, u.warned, u.donor, g.namestyle 
    FROM peers p
    LEFT JOIN users u ON (p.userid=u.id)
    LEFT JOIN usergroups g ON (u.usergroup=g.gid)
    WHERE p.torrent = ?
";

// Параметры
$params = [$id];

$subres = $db->sql_query_prepared($query, $params);

if ($subres) {
    while ($subrow = $db->fetch_array($subres)) {
        if ($subrow['seeder'] === 'yes') {
            $seeders[] = $subrow;
        } else {
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

	$peerstable = dltable($lang->details['seeders2'], $seeders, $torrent);
	$peerstable .= '<br />'.dltable($lang->details['leechers2'], $downloaders, $torrent);
}





		



// Подключите функцию insert_bbcode_editor
require_once INC_PATH . '/editor.php';


// Вызов функции
$editor = insert_bbcode_editor($smilies, $BASEURL, 'message');


// Формирование HTML
$showcommenttable .= '
<br />
<div class="container mt-4">
    <h2 class="mb-3">Quick Comment</h2>
    '.(!empty($cerror) ? '<div class="error">'.$cerror.'</div>' : '').'
	' . ($useajax == 'yes' ? '<script src="' . $BASEURL . '/scripts/quick_comment.js"></script>' : '') . '
    ' . $editor['toolbar'] . '
    <form name="comment" id="comment" method="post" action="comment.php?action=add&tid=' . $id . '" novalidate>
        <input type="hidden" name="ctype" value="quickcomment">
        <input type="hidden" name="page" value="' . intval(isset($_GET['page']) ? $_GET['page'] : 0) . '">
        <div id="fileIdsContainer"></div>
        <div class="mb-3">
            <label for="message" class="form-label">Your Comment <small class="text-muted">(макс. 500 символов)</small></label>
            <textarea class="form-control" id="message" name="message" rows="6" placeholder="Write a comment, use BBCode..." maxlength="500" aria-describedby="charCount" required></textarea>
            <div id="charCount" class="form-text text-end">0 / 500</div>
        </div>
        <div id="message_preview" class="form-control mt-3 d-none"></div>
        ' . ($useajax == 'yes' ? '
        <div class="d-flex align-items-center justify-content-center mb-3">
            <i id="loading-layer" class="fa-solid fa-circle-notch fa-spin" aria-label="Loading..." style="display:none; color: #0b59e0; width:24px; height:24px; margin-right: 10px;"></i>
            <button type="button" class="btn btn-primary me-2" id="quickcomment" onclick="TSajaxquickcomment(\'' . $id . '\');">' . $lang->global['buttonsubmit'] . '</button>
            <button type="button" class="btn btn-secondary" onclick="jumpto(\'' . $BASEURL . '/comment.php?action=add&tid=' . $id . '\');">' . $lang->global['advancedbutton'] . '</button>
        </div>' : '
        <div class="d-flex gap-2 justify-content-center mb-3">
            <button type="submit" name="submit" class="btn btn-primary">' . $lang->global['buttonsubmit'] . '</button>
            <button type="button" class="btn btn-secondary" onclick="jumpto(\'' . $BASEURL . '/comment.php?action=add&tid=' . $id . '\');">' . $lang->global['advancedbutton'] . '</button>
        </div>') . '
    </form>
    ' . $editor['modal'] . '
</div>';








if($torrent['anonymous'] == 'yes' AND $torrent['owner'] != $CURUSER['id'] AND !$is_mod)
{
		$username = $lang->global['anonymous'];
}
else
{
	$username = '<a href="'.get_profile_link($torrent['owner']).'">'.format_name($torrent2['username'], $torrent2['usergroup']).'</a>' . get_user_icons ($torrent2) .'';
}








$ShowTLINK = '';

if (!empty($torrent['t_link'])) 
{
    require_once INC_PATH . '/functions_imdb_rating.php';

    $html = $torrent['t_link']; // исходный HTML блока
    $hasHtml = (strpos($html, '<') !== false && strpos($html, '>') !== false);

    // 1) Пытаемся вытащить IMDb URL из блока
    $imdbUrl = null;
    if (preg_match('#https?://www\.imdb\.com/title/(tt\d+)/#i', $html, $m)) 
	{
        $imdbUrl = $m[0]; // полная ссылка с ttXXXXXXX
    }

    // 2) Получаем картинку рейтинга (передаём URL, а не HTML!)
    $IMDBRating = $imdbUrl ? TSSEGetIMDBRatingImage($imdbUrl) : false;

    if ($IMDBRating && !empty($IMDBRating['image'])) 
	{
        // 3) Вклеиваем картинку после "User Rating:" — гибкий паттерн
        $pattern = '#(<b>\s*User\s*Rating:\s*</b>\s*)#i';
        if (preg_match($pattern, $html)) 
		{
            $html = preg_replace($pattern, '$1' . $IMDBRating['image'] . ' ', $html, 1);
        } 
		else 
		{
            // если маркера нет — добавим блок в начало рейтингового раздела или просто в начало
            $html = $IMDBRating['image'] . ' ' . $html;
        }
    } 
	else 
	{
        // 4) НЕ трогаем размеченный HTML!
        if (!$hasHtml) 
		{
            // если вдруг тут голый текст — можно автолинковать
            $html = format_urls($html, '_blank');
        }
    }

    // 5) Возвращаем собранное
    $torrent['t_link'] = $html;

    // $refresh только для модераторов (если нужно в шаблоне)
    $refresh = !empty($is_mod) ? ($lang->global['refresh'] ?? '') : '';

    eval("\$ShowTLINK = \"".$templates->get("imdb_table")."\";");
}

		






 
 
 
 
 
$srrr = '<script type="text/javascript">
   function file_get_ext(filename)
   {
      return typeof filename != "undefined" ? filename.substring(filename.lastIndexOf(".")+1, filename.length).toLowerCase() : false;
   }

   

   function ChangeBox(BoxValue)
   {
      TSGetID("nothingtopost1").style.display = "none";
      TSGetID("nothingtopost2").style.display = "none";
	  TSGetID("nothingtopost3").style.display = "none";
      TSGetID("nothingtopost4").style.display = "none";
      TSGetID("nothingtopost"+BoxValue).style.display = "inline";
   }
   
 </script>';
 
 echo $srrr;
 
 
 
 
 
 
 
 
 
 
 
$t_link = $torrent['t_link'];
if (($t_link AND preg_match( '@https:\/\/www.imdb.com\/title\/(.*)\/@isU', $t_link, $result))) 
{
    $t_link = $result['0'];
    unset($result);
}


;
			




$t_link = $torrent['t_link'];
if (($t_link AND preg_match( '@https:\/\/www.imdb.com\/title\/(.*)\/@isU', $t_link, $result))) 
{
    $t_link = $result['0'];
    unset($result);
}





			

$show_manage = '';
if ($CURUSER['id'] === $torrent['owner'] OR $is_mod)
{
    
	require( INC_PATH . '/functions_category.php' );
	
	$category = intval($torrent['category']);
    $caats = ts_category_list('category', (isset($category) ? $category : ''));
	
	
	
	
	
	
	$show_manage .= '
<div class="dropdown d-inline">
  
  <a href="#" class="btn btn-link p-0" role="button" id="editDropdown" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fa-solid fa-pen-to-square fa-xl" style="color: #0658e5;"></i>
  </a>
  <ul class="dropdown-menu" aria-labelledby="editDropdown">
    <li>
      
	  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-toggle="modal" data-bs-target="#add_data_Modal">
        <i class="fa-solid fa-pen-to-square me-2"></i> Quick Edit
      </a> 
    </li>
    <li>
      <a class="dropdown-item" href="upload.php?id='.$id.'">
        <i class="fa-solid fa-file-pen me-2"></i> Full Edit
      </a>
    </li>
  </ul>
</div>
';

	
	
	
	
	
	
	
	
	
	echo '


<!-- Modal -->
<div class="modal fade" id="add_data_Modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
         <h5 class="modal-title" id="exampleModalLabel">Edit Torrent '.htmlspecialchars_uni($torrent['name']).'</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Form -->
        <form method="post" id="insert_form" enctype="multipart/form-data">

          <!-- Name -->
          <label for="name">Enter Torrent Name</label>
          <input type="text" name="name" id="name" class="form-control" value="' . (isset( $torrent['name'] ) ? htmlspecialchars_uni( $torrent['name'] ) : '') . '" required />
          <br />

          <!-- Description -->
          <label for="descr">Enter Description</label>
         <textarea style="height: 200px; resize: none" class="form-control form-control-sm border" name="descr" id="descr" required>'.htmlspecialchars_uni($torrent['descr']).'</textarea>
          <br />
		  
		  
		  
		  <!-- Category -->
          <label for="descr">Select Torrent Category</label>
		  
						  '.$caats.'
						  <br />
						  
						  
					
			<label for="imdb">IMDB Link</label>		
			
						  <input type="text" class="form-control mt-3" name="t_link" size="70" value="' . (isset($t_link) ? htmlspecialchars_uni($t_link) : '') . '" />
						  </label><br />' . $lang->upload['t_link2'].' 			  
						  
		  
		  
		  
		  

          <input type="radio" class="form-check-input" name="nothingtopost" value="1" onclick="ChangeBox(this.value);" checked="checked" /> ' . $lang->upload['cover1'] . '
      <div style="display: inline;" id="nothingtopost1">
         <br />
		  
		  
		  
		  <input type="text" class="form-control mt-3" name="t_image_url" id="t_image_url" value="' . (isset($torrent['t_image']) ? htmlspecialchars_uni($torrent['t_image']) : '') . '" oninput="previewURLImage(this.value)" />
		  
		  
          <div class="image-area5 mt-2">
            <img id="urlImagePreview" src="#" alt="URL Preview" style="display:none;" class="img-thumbnail">
          </div>
		   
      
	  </div>
	  
	  
	  
	  
	  
      <br />
      
      <input type="radio" class="form-check-input" name="nothingtopost" value="2" onclick="ChangeBox(this.value);" /> ' . $lang->upload['cover2'] . '
      <div style="display: none;" id="nothingtopost2">
         <br />
		 
		 
		 
		 
		   <!-- Upload Image -->
          <label for="t_image_file">Upload Image</label>
        
		  
		  
		  <input type="file" class="form-control" name="t_image_file" id="t_image_file" accept="image/*" onchange="readFileImage(this)">
		  
		  
		  
		  

          <div class="image-area5 mt-2">
            <img id="fileImagePreview" src="#" alt="File Preview" style="display:none;" class="img-thumbnail">
          </div>
		  
		  
		  
          <br />
		  
		  

 </div>
 
 

	  
	  
	  
	  <br />
	  <input type="radio" class="form-check-input" name="nothingtopost" value="3" onclick="ChangeBox(this.value);" checked="checked" /> ' . $lang->upload['cover3'] . '
      <div style="display: inline;" id="nothingtopost3">
         <br />
		  
		  
		  <input type="text" class="form-control mt-3" name="t_image_url2" id="t_image_url2" size="70" value="' . (isset($torrent['t_image2']) ? htmlspecialchars_uni($torrent['t_image2']) : '') . '" oninput="previewURLImage2(this.value)" />
		  
		  <div class="image-area5 mt-2">
            <img id="urlImagePreview2" src="#" alt="URL Preview" style="display:none;" class="img-thumbnail">
          </div>
		  
      </div>
	  
	  
	  
      <br />
      
      <input type="radio" class="form-check-input" name="nothingtopost" value="4" onclick="ChangeBox(this.value);" /> ' . $lang->upload['cover4'] . '
      <div style="display: none;" id="nothingtopost4">
         <br />
		 
		 
		 

         <!-- Upload Image 2-->
          <label for="t_image_file2">Upload Image 2</label>
          <input type="file" class="form-control" name="t_image_file2" id="t_image_file2" accept="image/*" onchange="readFileImage2(this)">
          <div class="image-area5 mt-2">
            <img id="fileImagePreview2" src="#" alt="File Preview" style="display:none;" class="img-thumbnail">
          </div>
          <br />

 
		 
      </div>
	  
	  </br>
	  
	  
	  
	 
    '.($is_mod ? '
	
	
      <div class="container-mt3">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->upload['moptions'] . '
	</div>
	 </div>
		</div>
		
		
      
	  
	  <table>
	  
      <tr>
         <td class="none">
            <b>' . $lang->upload['free1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="free" value="yes"' . ((isset($torrent['free']) AND $torrent['free'] == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['free2'] . '
         </td>
		 
         <td class="none">
            <b>' . $lang->upload['silver1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="silver" value="yes"' . ((isset($torrent['silver']) AND $torrent['silver'] == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['silver2'] . '
         </td>
      </tr>
      
      <tr>
         <td class="none">
            <b>' . $lang->upload['doubleupload1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="doubleupload" value="yes"' . ((isset($torrent['doubleupload']) AND $torrent['doubleupload'] == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['doubleupload2'] . '
         </td>
         <td class="none">
            <b>' . $lang->upload['allowcomments1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="allowcomments" value="no"' . ((isset($torrent['allowcomments']) AND $torrent['allowcomments'] == 'no' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['allowcomments2'] . '
         </td>
      </tr>

      <tr>
         <td class="none">
            <b>' . $lang->upload['sticky1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="sticky" value="yes"' . ((isset($torrent['sticky']) AND $torrent['sticky'] == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['sticky2'] . '
         </td>
         <td class="none">
            <b>' . $lang->upload['nuked1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="isnuked" value="yes"' . (( isset( $torrent['isnuked'] ) AND $torrent['isnuked'] == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" onclick="ShowHideField(\'nukereason\');" /> ' . $lang->upload['nuked2'] . '
            <div style="display:' . (( isset( $torrent['isnuked'] ) AND $torrent['isnuked'] == 'yes' ) ? 'inline' : 'none') . ';" id="nukereason">
               <br /><b>' . $lang->upload['nreason'] . '</b> <input type="text" class="form-control mt-3" name="WhyNuked" value="' . (isset( $torrent['WhyNuked'] ) ? htmlspecialchars_uni( $torrent['WhyNuked'] ) : '') . '" size="40" />
            </div>
         </td>
      </tr>
   </table>
   
    ' : '').'
		  
	
		  
		  
		  
		  
		  
		  
		  
		  
		  

          <input type="submit" name="insert" id="insert" value="Insert" class="btn btn-primary" />
        </form>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>';
 
 
 
	
	
	
	
	
	
	
	
	 
   	
}
if ($is_mod)
{
	$show_manage .= '
	
	
 <a href="'.$BASEURL.'/admin/index.php?act=hit_and_run&torrentid=='.$id.'">
 <i class="fa-solid fa-person-running fa-xl" style="color: #161718;" alt="Hit & Run" title="Hit & Run"></i></a>
	 
  
  <a href="'.$BASEURL.'/comment.php?tid='.$id.'&action='.($torrent['allowcomments'] != 'yes' ? 'open' : 'close').'"  onmouseout="window.status=\'\'; return true;" onMouseOver="window.status=\''.($torrent['allowcomments'] == 'no' ? $lang->details['open'] : $lang->details['close']).'\'; return true;">'.($torrent['allowcomments'] != 'yes' ? 
  
  '<i class="fa-solid fa-comment-slash fa-xl" style="color: #e91b0c;" alt="'.$lang->details['open'].'" title="'.$lang->details['open'].'"></i>' : 
  
  '<i class="fa-solid fa-comment-slash fa-xl" style="color: #08e74b;" alt="'.$lang->details['close'].'" title="'.$lang->details['close'].'"></i>').'</a>
	
  
	
<a href="'.$BASEURL.'/admin/index.php?act=torrent_info&amp;id='.$id.'"><i class="fa-sharp fa-solid fa-info fa-xl" style="color: #94b4eb;" alt="Torrent Info" title="Torrent Info"></i></a>
  
	
<a href="'.$BASEURL.'/admin/index.php?act=fastdelete&amp;id='.$id.'"><i class="fa-solid fa-trash-can fa-xl" style="color: #eb0f0f;" alt="Delete Torrent" title="Delete Torrent"></i></a>';


}

  


















if (is_file(TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent") && ($Data = file_get_contents(TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent"))) 
{
    
	
	$torrentPath = TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent";
    
    // Загружаем торрент
    $torrentObj = TorrentFile::load($torrentPath);

    $files = $torrentObj->v1()->getFiles();
	

    // Step 1: Build folder structure
    $tree = [];



foreach ($files as $file) 
{
    $path = str_replace('\\', '/', implode('/', $file->path)); // ✅ тут исправлено
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






    // Step 2: Icon helper
	
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

	
	

    // Step 3: Recursive render function
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
                        📁 ' . htmlspecialchars($name) . '
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

    //echo renderAccordion($tree);
}






// Descr
$parsedDescr = $parser->parse_message($torrent['descr'], $parser_options);






// Скрины
$screenshots = [];

// Подготовленный запрос
$query = "SELECT id, filename FROM `screenshots` WHERE torrent_id = ? ORDER BY id ASC";
$params = [$id];

$res = $db->sql_query_prepared($query, $params);

if ($res) {
    while ($row = $db->fetch_array($res)) {
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
           class="screenshot-wrapper d-block position-relative overflow-hidden shadow-lg rounded-4"
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





$descr = '<div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">'
        . sprintf($lang->details['detailsfor'], $torrent['name']) .
    '</div>
    <div class="card-body">'
        . $parsedDescr;

if (!empty($screenshots)) 
{
    $descr .= '
        <br>
		<br>
        <h5 class="mb-3"><i class="fa-solid fa-image me-2"></i>Screenshots</h5>
        ' . $screensHtml;
}

$descr .= '
    </div>
</div>';










// Пример вывода t_image и t_image2 с одним модалом
$modal_images = ''; // инициализация
$images = [];

if (!empty($torrent['t_image'])) 
{
    $images[] = $torrent['t_image'];
}

if (!empty($torrent['t_image2'])) 
{
    $images[] = $torrent['t_image2'];
}

foreach ($images as $img) 
{
    $modal_images .= '
    <a href="#"
       data-bs-toggle="modal"
       data-bs-target="#universalImageModal"
       data-img-src="' . htmlspecialchars_uni($img) . '"
       data-title="' . htmlspecialchars_uni($torrent['name']) . '">
        <img src="' . htmlspecialchars_uni($img) . '"
             class="rounded"
             width="400"
             alt="' . htmlspecialchars_uni($torrent['name']) . '">
    </a>
    ';
}













$details = '

	
	
	<div id="employee_table" class="container mt-3">
	
	
	
	
	
  <h2>'.$isfree.$issilver.$isdoubleupload.' '.htmlspecialchars_uni($torrent['name']).'</h2>
  
 
  <div class="card">
  
  
	<div class="card-header">
		
		
	<button type="button" class="btn btn-secondary">
		
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16">
           <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"></path>
           <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z"></path>
        </svg>

           <a href="'.get_download_link($id).'" alt="'.$lang->details['dltorrent'].'" title="'.$lang->details['dltorrent'].'">'.htmlspecialchars_uni($torrent['name']).'</a>
		   
    </button>

    </div>
		
		
		

	<div class="card-body">

	
		
	<div class="container mt-3">	
		


    <!-- Universal Image Preview Modal -->
<div class="modal fade" id="universalImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="universalImageModalTitle">
                    <i class="bi bi-image text-primary"></i> Image Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light p-0">
                <div style="position: relative; height: 70vh; overflow: hidden;">
                    <img src="" id="universalImagePreview" class="img-fluid"
                         style="max-height: 100%; max-width: 100%; object-fit: contain;">
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div class="text-start">
                        <span class="text-muted fw-medium" id="universalImageDimensions"></span>
                        <span class="text-muted mx-2">•</span>
                        <span class="text-muted fw-medium" id="universalImageSize"></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="universalFullscreenBtn">
                            <i class="bi bi-arrows-angle-expand me-1"></i> Fullscreen
                        </button>
                        <a href="#" class="btn btn-primary" id="universalDownloadBtn" download>
                            <i class="bi bi-download me-1"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


'.$modal_images.'






     </div>
		
		
	
	
	<ul class="list-group list-group-flush">
	
	<li class="list-group-item"></li>
    


	 
	 
	 
	 
<li class="list-group-item">
    <span>'.$lang->details['added'].' <i class="bi bi-calendar text-primary ms-1"></i></span>
    <span class="badge bg-primary ms-1">'.my_datee($dateformat, $torrent['added']).'</span>
    <span class="badge bg-primary ms-1"><i class="bi bi-clock"></i> '.my_datee($timeformat, $torrent['added']).'</span>
    <span class="small text-muted ms-2">'.
        sprintf(
            $lang->details['laction'],
            '<i class="bi bi-calendar text-primary"></i> <span class="badge bg-primary">'.my_datee($dateformat, $torrent['mtime']).'</span>'.
            ' <i class="bi bi-clock text-primary"></i> <span class="badge bg-primary">'.my_datee($timeformat, $torrent['mtime']).'</span>'
        ).'
    </span>
</li>

	 
	 
	 
	 
	 


    <li class="list-group-item">'.$lang->details['type'].'
		


 <span class="bg-light text-primary border">
   
	
	
	
	'.$torrent2['categoryname'].'
	
</span>


		</li>
			
		
		
		
		
		
    <li class="list-group-item">'.$lang->details['snatched'].'
	
	<span class="badge bg-primary">
  <i class="fa-solid fa-check-circle me-1"></i>
  <a href="viewsnatches.php?id='.$id.'" style="color: white; text-decoration: none;">
    '.ts_nf($torrent['times_completed']).'
  </a>
</span>
	'.$lang->details['snatched2'].'
	</li>
    
	
	
	
	
	
	<li class="list-group-item" data-bs-toggle="collapse" data-bs-target="#fileAccordion" aria-expanded="false" style="cursor: pointer;">
    '.$lang->details['size'].' 
	
	<span class="badge bg-light text-primary border me-1">
  <i class="fa-solid fa-database me-1"></i>' . mksize($torrent['size']) . '
</span>
	
	
	
    <span class="text-muted">'.sprintf('in %s file(s)', ts_nf($torrent['numfiles'])).'</span>
</li>
<div id="fileAccordion" class="collapse mt-2">
    '.renderAccordion($tree).'
</div>
	
	

	
	 <li class="list-group-item">'.'Tags'.'
		'.$keywords.'
		</li>
		
		
		
		
	  <li class="list-group-item">'.$lang->details['comments'].'
		<span class="badge bg-primary">'.ts_nf($torrent['comments']).'</span>
	  </li>
	  
	   
	   
	  <li class="list-group-item">'.$lang->details['hits'].'</td>
		<span class="badge bg-primary">'.ts_nf($torrent['hits']).'</span>
	  </li>
		

		
		
	   
	   <li class="list-group-item">'.$lang->details['uppedby'].'
		'.$username.'</li>
	   
	   
	   '.($show_manage != '' ? '<li class="list-group-item">Manage Torrent'.$show_manage.'</li>' : '').'
	  
	   
	
  </ul>
		
		
	
	</br>
	

	
	'.(isset($reseed) ? $reseed : '').'
	
	
	
</div> 
    <div class="card-footer">
	
	<div class="accordion accordion-flush" id="accordionFlushExample">
  <div class="accordion-item">
    <h2 class="accordion-header" id="flush-headingOne">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
       '.$lang->details['peersb'].'</td>
	   
	   '.sprintf($lang->details['peers2'], 
		ts_nf($torrent['seeders']),
		ts_nf($torrent['leechers']), 
		ts_nf($torrent['seeders']+$torrent['leechers']),
		$peerstable).'
      </button>
    </h2>
    <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
      <div class="accordion-body">
	  
	  '.$peerstable.'
	  
	  </div>
    </div>
  </div>
</div>
	
	</div>
  </div>
</div>


	
	
	<br />
	'.$ShowTLINK.'
	
	
	
	
     <div class="container mt-3">
       <div class="card">'.$descr.' </div>
    </div>
    

 
	
	
	
	<br />
	'.$SimilarTorrents.'
	'.$showcommenttable.'
';






echo '
'.($is_mod ? '
<script type="text/javascript">
	l_updated = "'.$lang->global['imgupdated'].'";
	l_refresh = "'.$lang->global['refresh'].'";
</script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/quick_imdb.js"></script>' : '');



$show_nfo = '';
if (!empty($torrent['nfo']))
{
	$show_nfo .= '<img src="'.$BASEURL.'/viewnfo.php?id='.$id.'" border="0" alt="'.$torrent['name'].'" title="'.$torrent['name'].'" />';
}



echo $details;



function get_slr_color($ratio)
{
	if ($ratio < 0.025) return "#ff0000";
	if ($ratio < 0.05) return "#ee0000";
	if ($ratio < 0.075) return "#dd0000";
	if ($ratio < 0.1) return "#cc0000";
	if ($ratio < 0.125) return "#bb0000";
	if ($ratio < 0.15) return "#aa0000";
	if ($ratio < 0.175) return "#990000";
	if ($ratio < 0.2) return "#880000";
	if ($ratio < 0.225) return "#770000";
	if ($ratio < 0.25) return "#660000";
	if ($ratio < 0.275) return "#550000";
	if ($ratio < 0.3) return "#440000";
	if ($ratio < 0.325) return "#330000";
	if ($ratio < 0.35) return "#220000";
	if ($ratio < 0.375) return "#110000";
	return "#000000";
}
stdfoot();
?>
