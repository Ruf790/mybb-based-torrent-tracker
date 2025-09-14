<?

/**
 * Generate a listing of page - pagination
 *
 * @param int $count The number of items
 * @param int $perpage The number of items to be shown per page
 * @param int $page The current page number
 * @param string $url The URL to have page numbers tacked on to (If {page} is specified, the value will be replaced with the page #)
 * @param boolean $breadcrumb Whether or not the multipage is being shown in the navigation breadcrumb
 * @return string The generated pagination
 */
 
function fetch_page_url($url, $page)
{
	if($page <= 1)
	{
		$find = array(
			"-page-{page}",
			"&amp;page={page}",
			"{page}"
		);

		// Remove "Page 1" to the defacto URL
		$url = str_replace($find, array("", "", $page), $url);
		return $url;
	}
	else if(strpos($url, "{page}") === false)
	{
		// If no page identifier is specified we tack it on to the end of the URL
		if(strpos($url, "?") === false)
		{
			$url .= "?";
		}
		else
		{
			$url .= "&amp;";
		}

		$url .= "page=$page";
	}
	else
	{
		$url = str_replace("{page}", $page, $url);
	}

	return $url;
}

function multipage($count, $perpage, $page, $url, $breadcrumb=false)
{
	global $theme, $templates, $lang, $mybb, $plugins, $BASEURL, $maxmultipagelinks, $jumptopagemultipage;

	if($count <= $perpage)
	{
		return '';
	}

	$args = array(
		'count' => &$count,
		'perpage' => &$perpage,
		'page' => &$page,
		'url' => &$url,
		'breadcrumb' => &$breadcrumb,
	);
	$plugins->run_hooks('multipage', $args);

	$page = (int)$page;

	$url = str_replace("&amp;", "&", $url);
	$url = htmlspecialchars_uni($url);

	$pages = ceil($count / $perpage);

	$prevpage = '';
	if($page > 1)
	{
		$prev = $page-1;
		$page_url = fetch_page_url($url, $prev);
		eval("\$prevpage = \"".$templates->get("multipage_prevpage")."\";");
		
		
	}
	
	//$maxmultipagelinks = "5";
	//$jumptopagemultipage = "1";

	// Maximum number of "page bits" to show
	if(!$maxmultipagelinks)
	{
		$maxmultipagelinks = 5;
	}

	$from = $page-floor($maxmultipagelinks/2);
	$to = $page+floor($maxmultipagelinks/2);

	if($from <= 0)
	{
		$from = 1;
		$to = $from+$maxmultipagelinks-1;
	}

	if($to > $pages)
	{
		$to = $pages;
		$from = $pages-$maxmultipagelinks+1;
		if($from <= 0)
		{
			$from = 1;
		}
	}

	if($to == 0)
	{
		$to = $pages;
	}

	$start = '';
	if($from > 1)
	{
		if($from-1 == 1)
		{
			$lang->multipage_link_start = '';
		}

		$page_url = fetch_page_url($url, 1);
		eval("\$start = \"".$templates->get("multipage_start")."\";");
	}

	$mppage = '';
	for($i = $from; $i <= $to; ++$i)
	{
		$page_url = fetch_page_url($url, $i);
		if($page == $i)
		{
			if($breadcrumb == true)
			{	
				eval("\$mppage .= \"".$templates->get("multipage_page_link_current")."\";");	
			}
			else
			{
				eval("\$mppage .= \"".$templates->get("multipage_page_current")."\";");	
			}
		}
		else
		{
		   
			eval("\$mppage .= \"".$templates->get("multipage_page")."\";");
			
			
		}
	}

	$end = '';
	if($to < $pages)
	{
		if($to+1 == $pages)
		{
			$lang->multipage_link_end = '';
		}

		$page_url = fetch_page_url($url, $pages);
		eval("\$end = \"".$templates->get("multipage_end")."\";");
	}

	$nextpage = '';
	if($page < $pages)
	{
		$next = $page+1;
		$page_url = fetch_page_url($url, $next);
		eval("\$nextpage = \"".$templates->get("multipage_nextpage")."\";");
		
	}

	$jumptopage = '';

	if($pages > ($maxmultipagelinks+1) && $jumptopagemultipage == 1)
	{
		// When the second parameter is set to 1, fetch_page_url thinks it's the first page and removes it from the URL as it's unnecessary
		$jump_url = fetch_page_url($url, 1);
		eval("\$jumptopage = \"".$templates->get("multipage_jump_page")."\";");
	
	}
	

	$multipage_pages = sprintf('Pages ('.$pages.'):');

	if($breadcrumb == true)
	{
		eval("\$multipage = \"".$templates->get("multipage_breadcrumb")."\";");
	}
	else
	{
		eval("\$multipage = \"".$templates->get("multipage")."\";");
	}

	return $multipage;
}


 

?>
