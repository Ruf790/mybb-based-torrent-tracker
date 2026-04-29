<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

define('TI_VERSION', 'New Arokettu version');

require_once __DIR__ . '../../vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;

$id = (int)$_GET['id'];
if (!$id) {
    print_no_permission();
}

$res = $db->sql_query("SELECT name FROM torrents WHERE id = " . $db->escape_string($id));
$row = $db->fetch_array($res);

$fn = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';
if (!$row || !is_file($fn) || !is_readable($fn)) {
    header('HTTP/1.1 404 Not Found');
    exit();
}

$torrent = TorrentFile::load($fn);
$torrentName = htmlspecialchars($row['name']);

stdhead("Torrent Info");

echo '<div class="container my-4">';
echo '<div class="card shadow-sm">';
echo '<div class="card-header fw-bold fs-5">' . $torrentName . '</div>';
echo '<div class="card-body">';

echo '<h5 class="mt-2 mb-3">Torrent Metadata</h5>';
echo '<ul class="list-group mb-4">';


foreach ($torrent->getRawData()->toArray() as $key => $value) {
    echo '<li class="list-group-item">';
    if (is_string($value)) {
        $escapedValue = htmlspecialchars($value);

        // Если value начинается с http или https — делаем ВСЮ строку кликабельной
        if (preg_match('/^https?:\/\//i', $value)) {
            echo '<a href="' . $escapedValue . '" target="_blank" rel="noopener noreferrer" class="text-decoration-none">';
            echo '<span class="text-primary">[STRING]</span> <strong>' . htmlspecialchars($key) . '</strong>: ' . $escapedValue;
            echo '</a>';
        } else {
            echo '<span class="text-primary">[STRING]</span> <strong>' . htmlspecialchars($key) . '</strong>: ' . $escapedValue;
        }
    } elseif (is_int($value)) {
        echo '<span class="text-success">[INT]</span> <strong>' . htmlspecialchars($key) . '</strong>: ' . $value;
    } elseif (is_array($value) || $value instanceof \Arokettu\Torrent\DataTypes\Internal\DictObject) {
        echo '<span class="text-warning">[DICT]</span> <strong>' . htmlspecialchars($key) . '</strong>';
    } else {
        echo '<span class="text-muted">[UNKNOWN]</span> <strong>' . htmlspecialchars($key) . '</strong>';
    }
    echo '</li>';
}




echo '</ul>';

echo '<h5 class="mb-3">Files Tree</h5>';

$files = $torrent->v1()->getFiles();

function renderFilesTree($files, $level = 0)
{
    $tree = [];
    foreach ($files as $file) {
        $pathList = $file->path;
        if (is_object($pathList) && method_exists($pathList, 'toArray')) {
            $path = $pathList->toArray();
        } else {
            $path = $pathList;
        }

        $current = &$tree;
        foreach ($path as $i => $part) {
            if ($i === count($path) - 1) {
                $current[$part] = $file->length;
            } else {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    echo buildFileTreeHtml($tree, $level);
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
        'video' => 'bg-danger',
        'audio' => 'bg-primary',
        'image' => 'bg-success',
        'archive' => 'bg-warning',
        'doc' => 'bg-secondary',
        'code' => 'bg-info',
        'exec' => 'bg-dark',
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
    foreach ($icons as $key => $exts) {
        if (in_array($ext, $exts)) {
            $type = $key;
            break;
        }
    }

    $colorClass = $colors[$type] ?? $colors['default'];
    $iconClass = $iconClasses[$type] ?? $iconClasses['default'];

    return '<span class="badge rounded-pill d-inline-flex align-items-center px-2 py-1 ' . $colorClass . ' text-white" aria-label="' . htmlspecialchars(strtoupper($ext)) . '" title="' . htmlspecialchars(strtoupper($ext)) . '">
                <i class="' . $iconClass . ' me-1" style="font-size: 1em;"></i>
                <small style="line-height:1;">' . htmlspecialchars(strtoupper($ext)) . '</small>
            </span>';
}

function buildFileTreeHtml($tree, $level = 0, &$idCounter = 0)
{
    $html = '<ul class="list-group ms-' . ($level * 3) . '">';
    foreach ($tree as $name => $content) {
        if (is_array($content)) {
            $idCounter++;
            $collapseId = 'collapse-' . $idCounter;
            $html .= '<li class="list-group-item">';
            $html .= '<a class="fw-bold text-decoration-none" data-bs-toggle="collapse" href="#' . $collapseId . '" role="button" aria-expanded="false" aria-controls="' . $collapseId . '">📁 ' . htmlspecialchars($name) . '</a>';
            $html .= '<div class="collapse ms-3 mt-1" id="' . $collapseId . '">';
            $html .= buildFileTreeHtml($content, $level + 1, $idCounter);
            $html .= '</div>';
            $html .= '</li>';
        } else {
            $icon = getFileIcon($name);
            $html .= '<li class="list-group-item">' . $icon . ' ' . htmlspecialchars($name) . ' <span class="badge bg-secondary">' . mksize($content) . '</span></li>';
        }
    }
    $html .= '</ul>';
    return $html;
}












renderFilesTree($files);

echo '</div>'; // card-body
echo '</div>'; // card
echo '</div>'; // container

stdfoot();
?>
