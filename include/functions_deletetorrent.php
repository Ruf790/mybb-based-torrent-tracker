<?php


declare(strict_types=1);

/**
 * Delete torrent and all associated files
 */
function deletetorrent(int $id, bool $permission = false): void
{
    global $torrent_dir, $usergroups, $db, $cache;
    
    if ((!$permission && !is_mod($usergroups)) || !is_valid_id($id)) {
        print_no_permission(true);
        return;
    }

    $id = (int)$id;
    
    // Delete torrent file
    $torrent_file = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';
    delete_torrent_files($id, $torrent_file);
    
    // Delete ALL comment files (both torrent_id and comment_id)
    delete_all_torrent_comment_files($id);
    
    // Delete associated data from database
    delete_torrent_database_records($id);
    
    // Clear cache
    $cache->update_torrents();
}

/**
 * Delete all physical files associated with torrent
 */
function delete_torrent_files(int $id, string $torrent_file): void
{
    global $torrent_dir, $db;
    
    $image_types = ['gif', 'jpg', 'png', 'webp'];
    
    // Delete main torrent file
    if (file_exists($torrent_file)) {
        @unlink($torrent_file);
    }
    
    // Delete cover images
    delete_torrent_images($id, $image_types);
    
    // Delete IMDb images
    delete_imdb_images($id, $image_types);
    
    // Delete screenshots
    delete_screenshots($id);
}

/**
 * Delete torrent cover images
 */
function delete_torrent_images(int $id, array $image_types): void
{
    global $torrent_dir;
    
    foreach ($image_types as $image) {
        $image_path = TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image;
        $image_path2 = TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image;
        
        if (file_exists($image_path)) {
            @unlink($image_path);
        }
        if (file_exists($image_path2)) {
            @unlink($image_path2);
        }
    }
}

/**
 * Delete IMDb images
 */
function delete_imdb_images(int $id, array $image_types): void
{
    global $torrent_dir, $db;
    
    $query = $db->simple_select("torrents", "t_link", "id = '{$id}'");
    
    if ($db->num_rows($query)) {
        $result = $db->fetch_array($query);
        $t_link = $result["t_link"] ?? '';
        
        // Extract IMDb ID
        $regex = "#https://www.imdb.com/title/(.*)/#U";
        preg_match($regex, $t_link, $matches);
        
        if (isset($matches[1]) && $matches[1]) {
            $imdb_id = $matches[1];
            delete_imdb_files($imdb_id, $image_types);
        }
    }
}

/**
 * Delete IMDb poster and photos
 */
function delete_imdb_files(string $imdb_id, array $image_types): void
{
    global $torrent_dir;
    
    // Delete main poster
    foreach ($image_types as $image) {
        $poster_path = TSDIR . "/" . $torrent_dir . "/imdb/" . $imdb_id . "." . $image;
        if (file_exists($poster_path)) {
            @unlink($poster_path);
        }
    }
    
    // Delete photos
    for ($i = 0; $i <= 10; $i++) {
        foreach ($image_types as $image) {
            $photo_path = TSDIR . "/" . $torrent_dir . "/imdb/" . $imdb_id . "_photo" . $i . "." . $image;
            if (file_exists($photo_path)) {
                @unlink($photo_path);
            }
        }
    }
}

/**
 * Delete screenshots
 */
function delete_screenshots(int $id): void
{
    global $db;
    
    $screenshots = $db->sql_query("SELECT filename FROM `screenshots` WHERE torrent_id = '{$id}'");
    
    while ($shot = $db->fetch_array($screenshots)) {
        $screenshot_file = $_SERVER['DOCUMENT_ROOT'] . '/torrents/screens/' . ($shot['filename'] ?? '');
        
        if (!empty($shot['filename']) && file_exists($screenshot_file)) {
            @unlink($screenshot_file);
        }
    }
    
    $db->delete_query("screenshots", "torrent_id='{$id}'");
}

/**
 * Delete ALL comment files for a torrent (torrent_id and comment_id)
 */
function delete_all_torrent_comment_files(int $torrent_id): void
{
    // 1. Delete files directly attached to torrent (torrent_id)
    delete_comment_files_by_torrent_id($torrent_id);
    
    // 2. Delete files attached to comments of this torrent (comment_id)
    delete_comment_files_by_torrent_comments($torrent_id);
	
	// 3. Delete attachments from attachments table (наша новая система)
    delete_attachments_by_torrent_comments($torrent_id);
}



/**
 * Delete attachments (attachments table) for all comments of a torrent
 */
function delete_attachments_by_torrent_comments(int $torrent_id): void
{
    global $db;

    require_once INC_PATH . '/functions_upload.php';

    $uploadDir  = TSDIR . '/uploads/attachments/';
    $batch_size = 100;
    $offset     = 0;

    // Получаем comment_ids батчами
    do {
        $comments = $db->sql_query(
            "SELECT id FROM comments WHERE torrent = {$torrent_id}
             LIMIT {$batch_size} OFFSET {$offset}"
        );

        $comment_ids = [];
        while ($row = $db->fetch_array($comments)) {
            $comment_ids[] = (int)$row['id'];
        }

        if (empty($comment_ids)) break;

        $ids_sql = implode(',', $comment_ids);

        // Удаляем файлы батчами
        $att_offset = 0;
        do {
            $result = $db->sql_query(
                "SELECT attachname, thumbnail FROM attachments
                 WHERE comment_id IN ($ids_sql)
                 LIMIT {$batch_size} OFFSET {$att_offset}"
            );

            $count = 0;
            while ($row = $db->fetch_array($result)) {
                delete_uploaded_file($uploadDir . $row['attachname']);
                if (!empty($row['thumbnail']) && $row['thumbnail'] !== 'SMALL') {
                    delete_uploaded_file($uploadDir . $row['thumbnail']);
                }
                $count++;
            }
            $att_offset += $batch_size;
        } while ($count === $batch_size);

        $db->sql_query("DELETE FROM attachments WHERE comment_id IN ($ids_sql)");

        $offset += $batch_size;

    } while (count($comment_ids) === $batch_size);
}




/**
 * Delete files attached directly to torrent (torrent_id)
 */
function delete_comment_files_by_torrent_id(int $torrent_id): void
{
    global $db;
    
    $files = $db->simple_select("comment_files", "*", "torrent_id = " . $torrent_id);
    $deleted_files = 0;
    
    while ($file = $db->fetch_array($files)) {
        $file_path = $file['file_path'] ?? '';
        $file_name = $file['file_name'] ?? '';
        
        // Delete physical file
        if (!empty($file_path) && is_file($file_path)) {
            if (@unlink($file_path)) {
                $deleted_files++;
            }
        } 
        // Try alternative paths if file_path is empty
        elseif (!empty($file_name)) {
            if (delete_file_by_name($file_name, 'comments')) {
                $deleted_files++;
            }
        }
    }
    
    // Delete database records
    $db->delete_query("comment_files", "torrent_id = " . $torrent_id);
    
    error_log("Deleted {$deleted_files} files with torrent_id {$torrent_id}");
}

/**
 * Delete files attached to comments of a torrent (comment_id)
 */
function delete_comment_files_by_torrent_comments(int $torrent_id): void
{
    global $db;
    
    // Get all comment IDs for this torrent
    $comment_ids = get_torrent_comment_ids($torrent_id);
    
    if (empty($comment_ids)) {
        return;
    }
    
    $comment_ids_str = implode(',', $comment_ids);
    $files = $db->simple_select("comment_files", "*", "comment_id IN (" . $comment_ids_str . ")");
    
    $deleted_files = 0;
    
    while ($file = $db->fetch_array($files)) {
        $file_path = $file['file_path'] ?? '';
        $file_name = $file['file_name'] ?? '';
        
        // Delete physical file
        if (!empty($file_path) && is_file($file_path)) {
            if (@unlink($file_path)) {
                $deleted_files++;
            }
        } 
        // Try alternative paths
        elseif (!empty($file_name)) {
            if (delete_file_by_name($file_name, 'comments')) {
                $deleted_files++;
            }
        }
    }
    
    // Delete database records
    $db->delete_query("comment_files", "comment_id IN (" . $comment_ids_str . ")");
    
    error_log("Deleted {$deleted_files} files from comments of torrent {$torrent_id}");
}

/**
 * Get all comment IDs for a torrent
 */
function get_torrent_comment_ids(int $torrent_id): array
{
    global $db;
    
    $comment_ids = [];
    $comments = $db->simple_select("comments", "id", "torrent = " . $torrent_id);
    
    while ($comment = $db->fetch_array($comments)) {
        $comment_ids[] = (int)$comment['id'];
    }
    
    return $comment_ids;
}

/**
 * Delete file by name from standard paths
 */
function delete_file_by_name(string $filename, string $type = 'comments'): bool
{
    if (empty($filename)) {
        return false;
    }
    
    $base_paths = [
        TSDIR . '/uploads/' . $type . '/'
    ];
    
    $deleted = false;
    
    foreach ($base_paths as $path) {
        $full_path = $path . $filename;
        if (is_file($full_path)) {
            if (@unlink($full_path)) {
                $deleted = true;
            }
        }
    }
    
    return $deleted;
}

/**
 * Delete all database records associated with torrent
 */
function delete_torrent_database_records(int $id): void
{
    global $db;
    
    $tables = [
        "peers" => "torrent='$id'",
        "comments" => "torrent='$id'",
        "bookmarks" => "torrentid='$id'",
        "snatched" => "torrentid='$id'",
        "torrents" => "id='$id'",
        "torrents_nfo" => "id='$id'",
		"reports" => "reported_id='$id'",
		"announce_actions" => "torrentid='$id'",
		"cheat_attempts" => "torrentid='$id'",
		"hit_and_run" => "torrentid='$id'",
		"torrent_ratings" => "torrent_id='$id'"
    ];
    
    foreach ($tables as $table => $condition) {
        $db->delete_query($table, $condition);
    }
}


// Security check
if (!defined('APP_INITIALIZED')) {
    exit('<div class="alert alert-danger text-center">Error! Direct initialization of this file is not allowed.</div>');
}