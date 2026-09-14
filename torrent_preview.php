<?php
declare(strict_types=1);

require_once 'global.php';
require_once INC_PATH . '/class_parser.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo "<div class='alert alert-danger'>Invalid torrent ID.</div>";
    exit;
}

$res = $db->sql_query_prepared(
    "SELECT name, descr, seeders, leechers, size, added, t_image, visible, banned 
     FROM torrents WHERE id = ?",
    [$id]
);
$torrent = $db->fetch_array($res);

if (!$torrent) {
    echo "<div class='alert alert-danger'>Torrent not found.</div>";
    exit;
}

if ($torrent['visible'] !== 'yes' || $torrent['banned'] === 'yes') {
    echo "<div class='alert alert-danger'>Torrent is not available.</div>";
    exit;
}

$parser = new postParser();
$parser_options = [
    'allow_html'       => 0,
    'allow_mycode'     => 1,
    'allow_smilies'    => 1,
    'allow_imgcode'    => 1,
    'allow_videocode'  => 1,
    'filter_badwords'  => 1,
];

$descrPreview = (string)$torrent['descr'];
$isTruncated  = false;
if (mb_strlen($descrPreview) > 1500) {
    $descrPreview = mb_substr($descrPreview, 0, 1500) . '…';
    $isTruncated  = true;
}

echo '<style>
.torrent-description-preview {
    max-height: 400px;
    overflow-y: auto;
    padding: .75rem 1rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #fafbfc;
    line-height: 1.6;
}
.torrent-description-preview img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
}
.list-group-item { border-left: 0; border-right: 0; }
</style>';

echo '
<div class="row">
  <div class="col-md-4 text-center">'
    . (!empty($torrent['t_image'])
        ? '<img src="' . htmlspecialchars_uni($torrent['t_image']) . '" '
          . 'alt="' . htmlspecialchars_uni($torrent['name']) . '" '
          . 'class="img-fluid rounded mb-3 shadow" loading="lazy" '
          . 'onerror="this.style.display=\'none\'">'
        : '<div class="text-muted py-5"><i class="bi bi-image" style="font-size:3rem;"></i><br>No poster</div>'
      ) . '
  </div>
  <div class="col-md-8">
    <h5>' . htmlspecialchars_uni($torrent['name']) . '</h5>
    <div class="mb-3 d-flex gap-2 flex-wrap">
      <a href="' . htmlspecialchars_uni(get_torrent_link($id)) . '" class="btn btn-primary btn-sm" rel="nofollow">
        <i class="bi bi-box-arrow-up-right me-1"></i>View torrent
      </a>
      <a href="' . htmlspecialchars_uni(get_download_link($id)) . '" class="btn btn-success btn-sm" rel="nofollow">
        <i class="bi bi-download me-1"></i>Download .torrent
      </a>
    </div>
    <ul class="list-group mb-3">
      <li class="list-group-item"><strong>Seeders:</strong> '  . (int) $torrent['seeders']  . '</li>
      <li class="list-group-item"><strong>Leechers:</strong> ' . (int) $torrent['leechers'] . '</li>
      <li class="list-group-item"><strong>Size:</strong> '     . mksize($torrent['size'])   . '</li>
      <li class="list-group-item"><strong>Added:</strong> '    . my_datee('relative', $torrent['added']) . '</li>
    </ul>
  </div>
</div>

<div class="mt-3">
  <strong>Description:</strong><br>
  <div class="torrent-description-preview">'
    . $parser->parse_message($descrPreview, $parser_options) . '
  </div>';

if ($isTruncated) {
    echo '<div class="text-muted small mt-2">
            <i class="bi bi-info-circle me-1"></i>
            Description truncated.
            <a href="' . htmlspecialchars_uni(get_torrent_link($id)) . '">Read full description →</a>
          </div>';
}

echo '</div>';