<?php
require_once 'global.php';


//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");


$torrentid = (isset ($_POST['torrentid']) ? intval ($_POST['torrentid']) : (isset ($_GET['torrentid']) ? intval ($_GET['torrentid']) : ''));
 
if(isset($CURUSER))
{
	
	
	$res_bookmark = $db->simple_select("bookmarks", "*", "torrentid = '{$torrentid}' AND userid='{$CURUSER['id']}'");
	
	if ($db->num_rows($res_bookmark) == 1)
	{
	    $bookmarkResult = mysqli_fetch_assoc($res_bookmark);
	
		$db->delete_query("bookmarks", "torrentid = '{$torrentid}' AND userid='{$CURUSER['id']}'");
		
		echo '<i class="fa-regular fa-star" style="color: #0a5ae6;" alt="Unbookmarked" />'; // Return 'deleted' for other torrents
	} 
	else 
	{
		
		$insert_array = array(
			'torrentid' => $db->escape_string($torrentid),
			'userid' => $db->escape_string($CURUSER['id'])
		);
		$db->insert_query("bookmarks", $insert_array);
		
		echo '<i class="fa-solid fa-star" style="color: #0c5be4;" alt="Bookmarked" /></i>'; // Return 'added' for torrent 1
	}
}
else echo "failed";
?>
