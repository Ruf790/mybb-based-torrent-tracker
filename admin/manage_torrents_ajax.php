<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('IN_ADMINCP', 1);

$rootpath = './../';
require_once $rootpath . 'global.php';

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger">Error! You do not have permission to access this page.</div>');
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo '<div class="alert alert-danger">Invalid torrent ID</div>';
    exit;
}

$torrentQuery = $db->sql_query_prepared("SELECT * FROM torrents WHERE id = ?", [$id]);
$torrent = $torrentQuery ? $db->fetch_array($torrentQuery) : null;
if (!$torrent) {
    echo '<div class="alert alert-danger">Torrent not found</div>';
    exit;
}

$uploaderQuery = $db->sql_query_prepared("SELECT username FROM users WHERE id = ?", [(int)$torrent['owner']]);
$uploader = ($uploaderQuery ? $db->fetch_field($uploaderQuery, 'username') : null) ?: 'Unknown';
$categoryQuery = $db->sql_query_prepared("SELECT name FROM categories WHERE id = ?", [(int)$torrent['category']]);
$category = ($categoryQuery ? $db->fetch_field($categoryQuery, 'name') : null) ?: 'Uncategorized';

// All categories for select
$allCats = [];
$catRes  = $db->sql_query_prepared("SELECT id, name FROM categories ORDER BY name ASC");
while ($catRes && ($c = $db->fetch_array($catRes))) $allCats[] = $c;

// Poster
$posterImage = htmlspecialchars(
    $torrent['t_image'] ?? $torrent['poster'] ?? $torrent['image'] ?? ''
);

if ($posterImage) {
    $src = filter_var($posterImage, FILTER_VALIDATE_URL)
        ? $posterImage
        : $BASEURL . '/' . $posterImage;

    $noimg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100%25' height='100%25'%3E"
           . "%3Crect width='100%25' height='100%25' fill='%23f8f9fa'/%3E"
           . "%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' "
           . "fill='%236c757d' font-family='Arial' font-size='14'%3ENo Image%3C/text%3E%3C/svg%3E";

    $posterHtml = '<img src="' . $src . '" class="torrent-poster" alt="Poster" onerror="this.src=\'' . $noimg . '\'">'
                . '<div class="poster-overlay"><a href="' . $src . '" target="_blank" class="poster-zoom">'
                . '<i class="fas fa-search-plus"></i></a></div>';
} else {
    $posterHtml = '<div class="poster-placeholder"><i class="fas fa-film"></i><span>No Poster</span></div>';
}

// Magnet
$magnet = 'magnet:?xt=urn:btih:' . $torrent['info_hash'] . '&dn=' . urlencode($torrent['name']);

// Seed ratio
$seedRatio = $torrent['seeders'] > 0
    ? number_format(($torrent['seeders'] / max(1, $torrent['seeders'] + $torrent['leechers'])) * 100, 0) . '%'
    : '0%';
?>
<div class="torrent-info-modal">

    <!-- ── Poster + Stats ──────────────────────────────────── -->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="poster-section">
                <div class="info-label"><i class="fas fa-image me-1"></i>Poster</div>
                <div class="poster-wrapper"><?= $posterHtml ?></div>
                <?php if ($posterImage): ?>
                <div class="mt-2 text-center">
                    <small><a href="<?= $posterImage ?>" target="_blank" class="text-decoration-none text-muted">
                        <i class="fas fa-link me-1"></i>View Full Size
                    </a></small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-8">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-info-circle me-1"></i>Torrent Info</div>
                <h5 class="mb-2"><?= htmlspecialchars($torrent['name']) ?></h5>
                <div class="text-muted small">
                    ID: #<?= $torrent['id'] ?> • Added: <?= date('d M Y H:i', (int)$torrent['added']) ?>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-value"><?= (int)$torrent['seeders'] ?></div>
                        <div class="stats-label">Seeders</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-value"><?= (int)$torrent['leechers'] ?></div>
                        <div class="stats-label">Leechers</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-value"><?= number_format((int)$torrent['times_completed']) ?></div>
                        <div class="stats-label">Completed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Uploader + Category ─────────────────────────────── -->
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-user me-1"></i>Uploader</div>
                <div class="fw-semibold"><?= htmlspecialchars($uploader) ?></div>
                <div class="small text-muted">Owner ID: <?= $torrent['owner'] ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-tag me-1"></i>Category</div>
                <div class="fw-semibold"><?= htmlspecialchars($category) ?></div>
                <div class="small text-muted">Category ID: <?= $torrent['category'] ?></div>
            </div>
        </div>
    </div>

    <!-- ── File Info + Status ──────────────────────────────── -->
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-hdd me-1"></i>File Info</div>
                <div>Size: <strong><?= mksize($torrent['size']) ?></strong></div>
                <div class="small text-muted">
                    Info Hash: <code><?= substr(htmlspecialchars($torrent['info_hash']), 0, 16) ?>...</code>
                </div>
                <?php if (!empty($torrent['t_filename'])): ?>
                <div class="small text-muted mt-1">File: <?= htmlspecialchars(basename($torrent['t_filename'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-flag me-1"></i>Status</div>
                <div class="d-flex flex-wrap gap-1">
                    <?php
                    $badges = [
                        'visible'      => [$torrent['visible'] === 'yes'
                            ? ['bg-success', 'fa-check-circle',  'Active']
                            : ['bg-danger',  'fa-times-circle',  'Dead'], true],
                        'free'         => [['bg-info',      'fa-gift',        'Free'],       $torrent['free']         === 'yes'],
                        'silver'       => [['bg-secondary', 'fa-star',        'Silver'],     $torrent['silver']       === 'yes'],
                        'sticky'       => [['bg-warning text-dark','fa-thumbtack','Sticky'], $torrent['sticky']       === 'yes'],
                        'doubleupload' => [['bg-purple',    'fa-bolt',        '2x Upload'],  $torrent['doubleupload'] === 'yes'],
                        'banned'       => [['bg-dark',      'fa-ban',         'Banned'],     $torrent['banned']       === 'yes'],
                        'anonymous'    => [['bg-secondary', 'fa-user-secret', 'Anonymous'],  $torrent['anonymous']    === 'yes'],
                    ];
                    foreach ($badges as [$cfg, $show]):
                        if (!$show) continue;
                        [$bg, $icon, $label] = $cfg;
                    ?>
                    <span class="badge <?= $bg ?>"><i class="fas <?= $icon ?> me-1"></i><?= $label ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Quick Edit ──────────────────────────────────────── -->
    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-pen me-1"></i>Quick Edit</div>
                <form method="post" action="<?= $BASEURL ?>/admin/index.php?act=manage_torrents&do=quick_edit"
                      class="row g-2 align-items-end">
                    <input type="hidden" name="torrent_id" value="<?= $torrent['id'] ?>">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Torrent Name</label>
                        <input type="text" name="name" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($torrent['name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <?php foreach ($allCats as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $torrent['category'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-save me-1"></i>Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Technical stats ─────────────────────────────────── -->
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="stats-card">
                <div class="stats-value"><?= mksize($torrent['size']) ?></div>
                <div class="stats-label">Total Size</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stats-card">
                <div class="stats-value"><?= $seedRatio ?></div>
                <div class="stats-label">Seed Ratio</div>
            </div>
        </div>
    </div>

    <!-- ── Info Hash + Magnet ──────────────────────────────── -->
    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-fingerprint me-1"></i>Technical Info</div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="small text-muted">Info Hash</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace"
                                   value="<?= htmlspecialchars($torrent['info_hash']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="navigator.clipboard.writeText(this.previousElementSibling.value).then(()=>this.innerHTML='<i class=\'fas fa-check\'></i>')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted">Magnet Link</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace"
                                   value="<?= htmlspecialchars($magnet) ?>" readonly>
                            <a href="<?= htmlspecialchars($magnet) ?>" class="btn btn-outline-primary">
                                <i class="fas fa-magnet"></i>
                            </a>
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="navigator.clipboard.writeText(this.previousElementSibling.previousElementSibling.value).then(()=>this.innerHTML='<i class=\'fas fa-check\'></i>')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Description ─────────────────────────────────────── -->
    <?php if (!empty($torrent['descr'])): ?>
    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="info-card">
                <div class="info-label"><i class="fas fa-align-left me-1"></i>Description</div>
                <div class="small" style="max-height:150px;overflow-y:auto;">
                    <?= nl2br(htmlspecialchars($torrent['descr'])) ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Action buttons ──────────────────────────────────── -->
    <div class="action-buttons">
        <?php
        $actions = [
            ['btn-outline-primary',   'fa-thumbtack', 'Sticky',         "toggleTorrentField({$torrent['id']}, 'sticky')"],
            ['btn-outline-success',   'fa-gift',       'Free',           "toggleTorrentField({$torrent['id']}, 'free')"],
            ['btn-outline-secondary', 'fa-star',       'Silver',         "toggleTorrentField({$torrent['id']}, 'silver')"],
            ['btn-outline-warning',   'fa-bolt',       '2x Upload',      "toggleTorrentField({$torrent['id']}, 'doubleupload')"],
            ['btn-outline-info',      'fa-eye',        'Toggle Visible', "toggleTorrentField({$torrent['id']}, 'visible')"],
            ['btn-outline-danger',    'fa-trash',      'Delete',         "deleteTorrentQuick({$torrent['id']})"],
        ];
        foreach ($actions as [$cls, $icon, $label, $onclick]):
        ?>
        <button class="btn btn-sm <?= $cls ?>" onclick="<?= $onclick ?>">
            <i class="fas <?= $icon ?> me-1"></i><?= $label ?>
        </button>
        <?php endforeach; ?>
        <a href="<?= $BASEURL ?>/<?= get_torrent_link($torrent['id']) ?>" target="_blank"
           class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-external-link-alt me-1"></i>View Page
        </a>
    </div>

</div>