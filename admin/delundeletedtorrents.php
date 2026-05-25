<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger text-center">Error! Direct initialization of this file is not allowed.</div>');
}

function deep_delete(int $id): void
{
    global $torrent_dir, $db;

    if (!is_valid_id($id)) {
        return;
    }

    $image_types = ['gif', 'jpg', 'jpeg', 'png', 'webp'];

    // Delete .torrent file
    $file = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';
    if (file_exists($file)) {
        @unlink($file);
    }

    // Delete cover images
    foreach ($image_types as $image) {
        $image_file = TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image;
        $alt_image_file = TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image;
        
        if (file_exists($image_file)) {
            @unlink($image_file);
        }
        if (file_exists($alt_image_file)) {
            @unlink($alt_image_file);
        }
    }

    // Delete orphaned screenshots
    $screens_dir = TSDIR . '/' . $torrent_dir . '/screens/';
    $screens = scandir($screens_dir);

    // Get all torrent IDs from database
    $torrent_ids = [];
    $sql = $db->sql_query('SELECT id FROM torrents');
    while ($torrent = $db->fetch_array($sql)) {
        $torrent_ids[] = (int)$torrent['id'];
    }

    // Delete files that don't match torrent IDs
    foreach ($screens as $screenshot) {
        if ($screenshot === '.' || $screenshot === '..') {
            continue;
        }

        $filename = pathinfo($screenshot, PATHINFO_FILENAME);
        $check = $db->simple_select("screenshots", "filename", "filename = '{$screenshot}'");

        if (!$db->num_rows($check)) {
            $screenshot_file = $screens_dir . $screenshot;
            if (file_exists($screenshot_file)) {
                @unlink($screenshot_file);
            }
        }
    }

    // Delete IMDb images
    $query = $db->simple_select("torrents", "t_link", "id = '{$id}'");
    if ($db->num_rows($query)) {
        $result = $db->fetch_array($query);
        $t_link = $result["t_link"] ?? '';
        $regex = "#https://www.imdb.com/title/(.*)/#U";
        preg_match($regex, $t_link, $matches);
        
        if (isset($matches[1]) && $matches[1]) {
            $imdb_id = $matches[1];
            foreach ($image_types as $image) {
                $imdb_file = TSDIR . '/' . $torrent_dir . '/imdb/' . $imdb_id . '.' . $image;
                if (file_exists($imdb_file)) {
                    @unlink($imdb_file);
                }
            }

            for ($i = 0; $i <= 10; $i++) {
                foreach ($image_types as $image) {
                    $photo_file = TSDIR . '/' . $torrent_dir . '/imdb/' . $imdb_id . '_photo' . $i . '.' . $image;
                    if (file_exists($photo_file)) {
                        @unlink($photo_file);
                    }
                }
            }
        }
    }

    // Delete database records
    $db->delete_query("peers", "torrent='$id'");
    $db->delete_query("comments", "torrent='$id'");
    $db->delete_query("bookmarks", "torrentid='$id'");
    $db->delete_query("snatched", "torrentid='$id'");
    $db->delete_query("torrents", "id='$id'");
    $db->delete_query("torrents_nfo", "id='$id'");
}

// Get all torrent IDs from database
$torrent_ids = [];
$sql = $db->sql_query('SELECT id FROM torrents');
while ($torrent = $db->fetch_array($sql)) {
    $torrent_ids[] = (int)$torrent['id'];
}

// Find orphaned .torrent files
$files = [];
$handle = opendir(TSDIR . '/' . $torrent_dir);
if ($handle) {
    while (($file = readdir($handle)) !== false) {
        if ($file !== '.' && $file !== '..' && str_ends_with($file, '.torrent')) {
            $file_id = (int)str_replace('.torrent', '', $file);
            $files[] = $file_id;
        }
    }
    closedir($handle);
}

$delete = [];
foreach ($files as $file) {
    if (!in_array($file, $torrent_ids, true)) {
        $delete[] = $file;
    }
}

$deleted_ids = [];

if (isset($_GET['sure']) && $_GET['sure'] === 'yes') {
    foreach ($delete as $file) {
        deep_delete($file);
        $deleted_ids[] = $file;
    }
    $delete = [];
}

stdhead('Delete Undeleted Torrent Files');

echo '
<div class="container-md">
    <div class="card border-0 mb-4">
        <div class="card-header rounded-bottom text-19 fw-bold">
            Delete Undeleted Torrent Files
        </div>
    </div>
</div>

<div class="container mt-3">
    <div class="card">
        <div class="card-body">';

if (!empty($deleted_ids)) {
    echo '<div class="alert alert-success" role="alert">
        ✅ <b>Successfully deleted ' . count($deleted_ids) . ' files.</b>
    </div>';

    echo '<p>Deleted torrent IDs:</p>';
    echo '<div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Torrent ID</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($deleted_ids as $index => $id) {
        echo '<tr>
            <th scope="row">' . ($index + 1) . '</th>
            <td>' . htmlspecialchars((string)$id) . '</td>
        </tr>';
    }
    
    echo '</tbody>
        </table>
    </div>';
}

if (!empty($delete)) {
    echo '<p>Total <b>' . count($delete) . '</b> orphaned .torrent files found in <code>' . $torrent_dir . '</code> folder.</p>';
    echo '<div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Torrent ID</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($delete as $index => $id) {
        echo '<tr>
            <th scope="row">' . ($index + 1) . '</th>
            <td>' . htmlspecialchars((string)$id) . '</td>
        </tr>';
    }
    
    echo '</tbody>
        </table>
    </div>';
    echo '<a href="https://ruff-tracker.eu/admin/index.php?act=delundeletedtorrents&sure=yes" class="btn btn-danger mt-3" onclick="return confirm(\'Are you sure you want to permanently delete these files?\')">Delete All</a>';
} elseif (empty($deleted_ids)) {
    
	echo '
<div class="text-center py-5">
    <i class="fas fa-check-circle fa-4x text-success mb-3 d-block"></i>
    <h5 class="text-success mb-1">All Clean!</h5>
    <p class="text-muted">There are no undeleted torrents found. Everything is clean!</p>
</div>';
	
	
}

echo '</div>
    </div>
</div>';

if (!empty($deleted_ids)) {
    echo '
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="deletedToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ✅ Successfully deleted ' . count($deleted_ids) . ' files!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toastEl = document.getElementById("deletedToast");
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    </script>';
}

stdfoot();
?>