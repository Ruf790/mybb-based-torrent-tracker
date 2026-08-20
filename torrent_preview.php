<?php
declare(strict_types=1);

require_once 'global.php';
require_once INC_PATH . '/class_parser.php';

// Валидация ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo "<div class='alert alert-danger'>Invalid torrent ID.</div>";
    exit;
}

// Запрос (id уже int — инъекция невозможна)
$res     = $db->sql_query_prepared("SELECT name, descr, seeders, leechers, size, added, t_image FROM torrents WHERE id = ?", [$id]);
$torrent = $db->fetch_array($res);

if (!$torrent) {
    echo "<div class='alert alert-danger'>Torrent not found.</div>";
    exit;
}

// Парсер — allow_html выключен (пользовательский контент, XSS-риск)
$parser         = new postParser();
$parser_options = [
    'allow_html'       => 0,
    'allow_mycode'     => 1,
    'allow_smilies'    => 1,
    'allow_imgcode'    => 1,
    'allow_videocode'  => 1,
    'filter_badwords'  => 1,
];

echo '
<div class="row">
  <div class="col-md-4 text-center">
    <img src="' . htmlspecialchars_uni($torrent['t_image']) . '" alt="Torrent Image" class="img-fluid rounded mb-3 shadow">
  </div>
  <div class="col-md-8">
    <h5>' . htmlspecialchars_uni($torrent['name']) . '</h5>
    <ul class="list-group mb-3">
      <li class="list-group-item"><strong>Seeders:</strong> '  . (int) $torrent['seeders']  . '</li>
      <li class="list-group-item"><strong>Leechers:</strong> ' . (int) $torrent['leechers'] . '</li>
      <li class="list-group-item"><strong>Size:</strong> '     . mksize($torrent['size'])   . '</li>
      <li class="list-group-item"><strong>Added:</strong> '    . my_datee($dateformat, $torrent['added']) . ' ' . my_datee($timeformat, $torrent['added']) . '</li>
    </ul>
  </div>
</div>
<div><strong>Description:</strong><br>' . $parser->parse_message($torrent['descr'], $parser_options) . '</div>';