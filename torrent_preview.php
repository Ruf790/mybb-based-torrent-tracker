<?php
require_once 'global.php';

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




$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$res = $db->sql_query("SELECT name, descr, seeders, leechers, size, added, t_image FROM torrents WHERE id = $id");
$torrent = $db->fetch_array($res);

if (!$torrent) 
{
    echo "<div class='alert alert-danger'>Torrent not found.</div>";
    exit;
}


echo '
<div class="row">
  <div class="col-md-4 text-center">
    <img src="' . htmlspecialchars_uni($torrent['t_image']) . '" alt="Torrent Image" class="img-fluid rounded mb-3 shadow">
  </div>
  <div class="col-md-8">
    <h5>' . htmlspecialchars_uni($torrent['name']) . '</h5>
    <ul class="list-group mb-3">
      <li class="list-group-item"><strong>Seeders:</strong> ' . $torrent['seeders'] . '</li>
      <li class="list-group-item"><strong>Leechers:</strong> ' . $torrent['leechers'] . '</li>
      <li class="list-group-item"><strong>Size:</strong> ' . mksize($torrent['size']) . '</li>
      <li class="list-group-item"><strong>Added:</strong>'.my_datee($dateformat, $torrent['added']).' '.my_datee($timeformat, $torrent['added']).'</li>
    </ul>
  </div>
</div>
<div><strong>Description:</strong><br>' . $parser->parse_message($torrent['descr'],$parser_options) . '</div>';
