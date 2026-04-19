<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

declare(strict_types=1);

if (!defined('IN_ADMIN_PANEL')) 
{
    exit('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('M_AVATARS', 'v.2.0 by xam');
define('AVATARS_PER_PAGE', 24);

/**
 * Scan image for malicious code
 */
function scan_image(string $image): bool
{
    global $_adir;
    $image = trim(file_get_contents($_adir . $image));
    if (!$image) {
        return false;
    }

    $pattern = '#(onblur|onchange|onclick|onfocus|onload|onmouseover|onmouseup|onmousedown|onselect|onsubmit|onunload|onkeypress|onkeydown|onkeyup|onresize|alert|applet|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|layer|link|meta|object|plaintext|style|script|textarea|title)#is';
    
    return !preg_match($pattern, $image);
}

/**
 * Get image dimensions and mime type
 */
function get_image_contents(string $image): array|false
{
    global $_adir;
    $image = getimagesize($_adir . $image);
    if (!$image) {
        return false;
    }

    return [
        'width' => $image[0], 
        'height' => $image[1], 
        'mime' => $image['mime']
    ];
}


/**
 * Format file size
 */
function format_file_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Initialize
$_adir = TSDIR . '/uploads/avatars/';
$_filetypes = ['gif', 'jpg', 'png', 'jpeg', 'webp'];
$_avatars = [];

// Process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['avatars']) && in_array($_POST['action_type'] ?? '', ['resize', 'delete'], true)) {
    
    $action_avatars = $_POST['avatars'] ?? [];
    $action_type    = $_POST['action_type'] ?? '';
    
    $ok = $skipped_shared = $not_found = $unlink_failed = [];
    $show_swal = false;
    
    if ($action_type === 'delete') {
        require_once INC_PATH . '/functions_upload.php';
        
        $_adir_real = realpath($_adir) . DIRECTORY_SEPARATOR;
        
        // Build avatar to user mapping
        $map = [];
        $res = $db->sql_query("SELECT id, avatar FROM users WHERE avatar <> ''");
        while ($row = $db->fetch_array($res)) {
            $k = strtolower(basename($row['avatar']));
            $map[$k][] = (int)$row['id'];
        }
        
        foreach (array_unique($action_avatars) as $delete_avatar) {
            $base = strtolower(basename($delete_avatar));
            $ids  = $map[$base] ?? [];
            
            // Fallback: extract ID from filename (avatar_123.jpg)
            if (!$ids && preg_match('/_(\d+)\.(gif|jpe?g|png|webp)$/i', $base, $m)) {
                $ids = [(int)$m[1]];
            }
            
            // Clear user profiles first
            if ($ids) {
                foreach ($ids as $uid) {
                    $db->update_query('users', [
                        'avatar'           => '',
                        'avatardimensions' => '',
                        'avatartype'       => ''
                    ], "id=" . (int)$uid);
                    
                    if (function_exists('remove_avatars')) {
                        remove_avatars($uid);
                    }
                }
            } else {
                $not_found[] = $delete_avatar;
                continue;
            }
            
            // Skip if file is shared by multiple users
            if (!empty($map[$base]) && count($map[$base]) > 1) {
                $skipped_shared[] = $delete_avatar;
                continue;
            }
            
            // Safe path validation
            $full = $_adir . $delete_avatar;
            $real = realpath($full);
            if ($real === false || strpos($real, $_adir_real) !== 0 || !is_file($real)) {
                $not_found[] = $delete_avatar;
                continue;
            }
            
            if (@unlink($real)) {
                $ok[] = $delete_avatar;
            } else {
                $unlink_failed[] = $delete_avatar;
            }
        }
        
        $show_swal = !empty($ok) || !empty($unlink_failed) || !empty($skipped_shared) || !empty($not_found);
    } 
    elseif ($action_type === 'resize') {
        require INC_PATH . '/readconfig_forumcp.php';
        $width = $f_avatar_maxwidth ?? 100;
        $height = $f_avatar_maxheight ?? 100;
        
        foreach ($action_avatars as $filename) {
            $ext = get_extension($filename);
            $filepath = $_adir . $filename;
            
            if (!file_exists($filepath)) {
                continue;
            }
            
            list($width_orig, $height_orig) = getimagesize($filepath);
            $ratio_orig = $width_orig / $height_orig;
            
            if ($ratio_orig < $width / $height) {
                $new_width = $height * $ratio_orig;
                $new_height = $height;
            } else {
                $new_width = $width;
                $new_height = $width / $ratio_orig;
            }
            
            $image_p = imagecreatetruecolor((int)$new_width, (int)$new_height);
            
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($filepath);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($filepath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($filepath);
                    break;
                default:
                    continue 2;
            }
            
            imagecopyresampled($image_p, $image, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $width_orig, $height_orig);
            
            ob_start();
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($image_p, null, 90);
                    break;
                case 'gif':
                    imagegif($image_p);
                    break;
                case 'png':
                    imagepng($image_p, null, 9);
                    break;
            }
            $image_data = ob_get_clean();
            
            file_put_contents($filepath, $image_data);
            imagedestroy($image_p);
            imagedestroy($image);
        }
        
        $show_swal = true;
        $ok = $action_avatars;
    }
}

// Load avatar files
if ($handle = opendir($_adir)) {
    while (false !== ($file = readdir($handle))) {
        if ($file !== '.' && $file !== '..' && in_array(get_extension($file), $_filetypes, true)) {
            $_avatars[] = $file;
        }
    }
    closedir($handle);
}

// Sort and paginate
natsort($_avatars);
$_avatars = array_values($_avatars);

$per_page = AVATARS_PER_PAGE;
$total = count($_avatars);
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $pages);

$offset = ($page - 1) * $per_page;
$avatars_page = array_slice($_avatars, $offset, $per_page);

// Load users with avatars
$avatar_to_user = [];
$sql = "SELECT id, username, usergroup, avatar
        FROM users
        WHERE avatar <> '' AND avatar REGEXP '\\.(gif|jpe?g|png|webp)$'";
$res = $db->sql_query($sql);

while ($u = $db->fetch_array($res)) {
    $key = strtolower(basename($u['avatar']));
    $avatar_to_user[$key] = '<a href="' . htmlspecialchars($BASEURL) . '/' . get_profile_link($u['id']) . '" class="text-decoration-none">
        <i class="fas fa-user-circle me-1"></i>' . format_name($u['username'], $u['usergroup']) . '</a>';
}

// Calculate display range
$from = $total > 0 ? $offset + 1 : 0;
$to = min($offset + $per_page, $total);

// Page header
stdhead('Manage Avatars - ' . M_AVATARS);

echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

?>

<style>
:root {
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
    --gradient-danger: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

/* Увеличенные базовые шрифты */
body {
    font-size: 16px;
}

.fade-in-up {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.avatar-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    border-radius: 20px;
    overflow: hidden;
    cursor: pointer;
    background: white;
}

.avatar-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.2);
}

.avatar-card.selected {
    border: 3px solid #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

.avatar-image-wrapper {
    position: relative;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    padding: 25px;
    text-align: center;
}

.avatar-image {
    width: 140px;
    height: 140px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.avatar-card:hover .avatar-image {
    transform: scale(1.05);
}

.avatar-badge {
    position: absolute;
    top: 15px;
    right: 15px;
}

.avatar-badge .form-check-input {
    width: 24px;
    height: 24px;
    cursor: pointer;
}

.avatar-info {
    padding: 18px;
    background: white;
}

.avatar-filename {
    font-size: 14px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #2d3748;
    background: #f7fafc;
    padding: 6px 10px;
    border-radius: 10px;
    word-break: break-all;
    margin-bottom: 12px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #4a5568;
    margin-bottom: 8px;
}

.stat-item i {
    width: 22px;
    font-size: 15px;
    color: #667eea;
}

.stat-item span, .stat-item a {
    font-size: 14px;
}

.scan-passed {
    color: #10b981;
    font-weight: 600;
}

.scan-failed {
    color: #ef4444;
    font-weight: 600;
}

/* Кнопки */
.btn-gradient {
    background: var(--gradient-primary);
    color: white;
    border: none;
    font-size: 14px;
    font-weight: 600;
    padding: 10px 24px;
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-gradient-sm {
    padding: 6px 16px;
    font-size: 13px;
}

/* Пагинация */
.modern-pagination .page-link {
    margin: 0 4px;
    border-radius: 12px;
    border: none;
    color: #4a5568;
    font-size: 15px;
    font-weight: 500;
    padding: 10px 16px;
    transition: all 0.3s ease;
}

.modern-pagination .page-link:hover {
    background: var(--gradient-primary);
    color: white;
    transform: translateY(-2px);
}

.modern-pagination .page-item.active .page-link {
    background: var(--gradient-primary);
    color: white;
}

/* Панель выбора */
.selection-toolbar {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px;
    padding: 16px 24px;
}

.selection-toolbar .form-check-label {
    font-size: 15px;
    font-weight: 600;
}

.selection-toolbar .form-select {
    font-size: 14px;
    padding: 8px 12px;
}

.stat-card {
    background: white;
    border-radius: 14px;
    padding: 12px 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.stat-card div {
    font-size: 15px;
}

.stat-card strong {
    font-size: 18px;
}

/* Заголовок карточки */
.card-header h4 {
    font-size: 24px;
}

.card-header small {
    font-size: 14px;
}

/* Модальное окно */
.modal-title {
    font-size: 20px;
    font-weight: 600;
}

.modal-body .btn {
    font-size: 14px;
}

/* Формы */
.form-select, .form-control {
    font-size: 14px;
}

/* Пустое состояние */
.text-center h5 {
    font-size: 18px;
}

.text-center p {
    font-size: 15px;
}

/* Адаптивность */
@media (max-width: 768px) {
    body {
        font-size: 14px;
    }
    
    .avatar-image {
        width: 100px;
        height: 100px;
    }
    
    .stat-item {
        font-size: 12px;
    }
    
    .stat-item i {
        font-size: 13px;
    }
    
    .avatar-filename {
        font-size: 11px;
    }
    
    .card-header h4 {
        font-size: 18px;
    }
    
    .btn-gradient {
        font-size: 13px;
        padding: 8px 16px;
    }
    
    .selection-toolbar {
        padding: 12px 16px;
    }
    
    .modern-pagination .page-link {
        padding: 6px 12px;
        font-size: 13px;
    }
}
</style>

<div class="container-md my-4 fade-in-up">

<?php if ($show_swal && isset($ok)): ?>
<script>
Swal.fire({
    title: '<?= $action_type === 'delete' ? 'Avatars Deleted' : 'Avatars Resized' ?>',
    icon: '<?= empty($unlink_failed) ? 'success' : 'warning' ?>',
    html: `
        <div class="text-start" style="font-size: 14px;">
            <?php if (!empty($ok)): ?>
            <div class="mb-2">
                <i class="fas fa-check-circle text-success"></i> <strong>Processed (<?= count($ok) ?>):</strong><br>
                <small class="text-muted"><?= implode(', ', array_map('htmlspecialchars', array_slice($ok, 0, 5))) ?><?= count($ok) > 5 ? '...' : '' ?></small>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($skipped_shared)): ?>
            <div class="mb-2">
                <i class="fas fa-share-alt text-warning"></i> <strong>Skipped - Shared (<?= count($skipped_shared) ?>):</strong><br>
                <small class="text-muted"><?= implode(', ', array_map('htmlspecialchars', array_slice($skipped_shared, 0, 3))) ?><?= count($skipped_shared) > 3 ? '...' : '' ?></small>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($not_found)): ?>
            <div class="mb-2">
                <i class="fas fa-search text-info"></i> <strong>Not Found (<?= count($not_found) ?>):</strong><br>
                <small class="text-muted"><?= implode(', ', array_map('htmlspecialchars', array_slice($not_found, 0, 3))) ?><?= count($not_found) > 3 ? '...' : '' ?></small>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($unlink_failed)): ?>
            <div class="mb-2">
                <i class="fas fa-exclamation-triangle text-danger"></i> <strong>Failed to Delete (<?= count($unlink_failed) ?>):</strong><br>
                <small class="text-danger"><?= implode(', ', array_map('htmlspecialchars', array_slice($unlink_failed, 0, 3))) ?><?= count($unlink_failed) > 3 ? '...' : '' ?></small>
            </div>
            <?php endif; ?>
        </div>
    `,
    confirmButtonText: '<i class="fas fa-check me-2"></i>OK',
    confirmButtonColor: '#667eea'
});
</script>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white py-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-user-astronaut fa-2x"></i>
                <div>
                    <h4 class="mb-0 fw-bold">Manage Avatars</h4>
                    <small class="opacity-75">Manage user avatars - v.2.0</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="d-flex gap-4">
                    <div><i class="fas fa-images text-primary me-1"></i> <strong><?= $total ?></strong> Total</div>
                    <div><i class="fas fa-chart-line text-success me-1"></i> Page <?= $page ?> / <?= $pages ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <form method="post" action="<?= htmlspecialchars($_this_script_ . '&p=' . $page) ?>" id="avatarForm">
        <div class="card-body">
            <div class="selection-toolbar mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="select_all" onchange="toggleAll(this.checked)" style="width: 20px; height: 20px;">
                            <label class="form-check-label fw-semibold" for="select_all">
                                <i class="fas fa-check-double me-1"></i>Select All
                            </label>
                        </div>
                        <div class="vr"></div>
                        <div class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <span id="selectedCount" style="font-weight: 600;">0</span> avatar(s) selected
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <select name="action_type" class="form-select" style="width: auto; font-size: 14px;" required>
                            <option value="" disabled selected>Choose action</option>
                            <option value="resize"><i class="fas fa-expand-alt me-2"></i>Resize Selected</option>
                            <option value="delete"><i class="fas fa-trash-alt me-2"></i>Delete Selected</option>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i>Apply
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach ($avatars_page as $avatar): 
                    $_exp = explode('_', preg_replace('/\.(gif|jpg|jpeg|png|webp)$/i', '', $avatar));
                    $_userid = isset($_exp[1]) ? (int)$_exp[1] : 0;
                    
                    $_ad = get_image_contents($avatar);
                    $passed = scan_image($avatar);
                    $size = file_exists($_adir . $avatar) ? format_file_size(filesize($_adir . $avatar)) : 'Unknown';
                    
                    $key = strtolower(basename($avatar));
                    $owner = $avatar_to_user[$key] ?? '<span class="text-muted"><i class="fas fa-question-circle"></i> Unknown</span>';
                    
                    $cardId = 'card_' . md5($avatar);
                ?>
                <div class="col">
                    <div class="avatar-card" id="<?= $cardId ?>" onclick="toggleCard('<?= $cardId ?>', '<?= md5($avatar) ?>')">
                        <div class="avatar-image-wrapper">
                            <img src="<?= htmlspecialchars($BASEURL . '/uploads/avatars/' . $avatar) ?>" 
                                 class="avatar-image" 
                                 alt="Avatar" 
                                 onerror="this.src='<?= $BASEURL ?>/images/default_avatar.png'">
                            <div class="avatar-badge">
                                <input type="checkbox" name="avatars[]" id="cb_<?= md5($avatar) ?>" 
                                       value="<?= htmlspecialchars($avatar) ?>" class="form-check-input" 
                                       style="width: 22px; height: 22px; cursor: pointer;" 
                                       onclick="event.stopPropagation(); updateSelectedCount()">
                            </div>
                        </div>
                        <div class="avatar-info">
                            <div class="avatar-filename text-truncate" title="<?= htmlspecialchars($avatar) ?>">
                                <i class="fas fa-file-alt me-1"></i><?= htmlspecialchars($avatar) ?>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-weight-hanging"></i>
                                <span><?= $size ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-arrows-alt"></i>
                                <span><?= $_ad ? $_ad['width'] . 'x' . $_ad['height'] : 'N/A' ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-shield-alt"></i>
                                <span class="<?= $passed ? 'scan-passed' : 'scan-failed' ?>">
                                    <i class="fas <?= $passed ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                                    <?= $passed ? 'Secure' : 'Suspicious' ?>
                                </span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-user"></i>
                                <?= $owner ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($avatars_page)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No avatars found</h5>
                <p class="text-muted">Upload avatars to get started</p>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Modern Pagination -->
<?php if ($pages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center modern-pagination">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $page <= 1 ? '#' : htmlspecialchars($_this_script_ . '&p=' . ($page - 1)) ?>">
                <i class="fas fa-chevron-left"></i> <span class="d-none d-sm-inline">Prev</span>
            </a>
        </li>
        
        <?php
        $window = 2;
        $last_printed = 0;
        
        for ($i = 1; $i <= $pages; $i++) {
            if ($i <= 2 || $i > $pages - 2 || abs($i - $page) <= $window) {
                if ($i - $last_printed > 1 && $last_printed > 0) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                $active = $i === $page ? 'active' : '';
                echo '<li class="page-item ' . $active . '">';
                echo '<a class="page-link" href="' . htmlspecialchars($_this_script_ . '&p=' . $i) . '">' . $i . '</a>';
                echo '</li>';
                $last_printed = $i;
            }
        }
        ?>
        
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $page >= $pages ? '#' : htmlspecialchars($_this_script_ . '&p=' . ($page + 1)) ?>">
                <span class="d-none d-sm-inline">Next</span> <i class="fas fa-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- Image Preview Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header bg-gradient-primary text-white border-0 py-3">
                <h5 class="modal-title fs-4"><i class="fas fa-image me-2"></i>Avatar Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="previewImage" src="" alt="Preview" class="img-fluid rounded-3 shadow-sm" style="max-height: 70vh;">
            </div>
            <div class="modal-footer border-0 py-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedCards = new Set();

function toggleCard(cardId, avatarId) {
    const card = document.getElementById(cardId);
    const checkbox = document.getElementById('cb_' + avatarId);
    
    if (checkbox.checked) {
        checkbox.checked = false;
        card.classList.remove('selected');
        selectedCards.delete(avatarId);
    } else {
        checkbox.checked = true;
        card.classList.add('selected');
        selectedCards.add(avatarId);
    }
    updateSelectedCount();
}

function toggleAll(checked) {
    const checkboxes = document.querySelectorAll('input[name="avatars[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
        const cardId = 'card_' + checkbox.id.replace('cb_', '');
        const card = document.getElementById(cardId);
        if (card) {
            if (checked) {
                card.classList.add('selected');
                selectedCards.add(checkbox.id.replace('cb_', ''));
            } else {
                card.classList.remove('selected');
                selectedCards.clear();
            }
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('input[name="avatars[]"]:checked');
    const countSpan = document.getElementById('selectedCount');
    if (countSpan) {
        countSpan.textContent = checked.length;
    }
}

function showPreview(imageUrl, title) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    const img = document.getElementById('previewImage');
    img.src = imageUrl;
    modal.show();
}

// Initialize tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});

// Preview on image click
document.querySelectorAll('.avatar-image').forEach(img => {
    img.addEventListener('click', (e) => {
        e.stopPropagation();
        showPreview(img.src, 'Avatar Preview');
    });
});

// Initialize selected count
updateSelectedCount();

// Form submit validation
document.getElementById('avatarForm')?.addEventListener('submit', function(e) {
    const selected = document.querySelectorAll('input[name="avatars[]"]:checked');
    const action = document.querySelector('select[name="action_type"]').value;
    
    if (selected.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'No selection',
            text: 'Please select at least one avatar to process.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>OK'
        });
        return false;
    }
    
    if (!action) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'No action selected',
            text: 'Please choose an action to perform.',
            confirmButtonText: '<i class="fas fa-check me-2"></i>OK'
        });
        return false;
    }
    
    if (action === 'delete') {
        e.preventDefault();
        Swal.fire({
            title: 'Confirm Deletion',
            text: `Are you sure you want to delete ${selected.length} avatar(s)? This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Yes, delete them!',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    }
});
</script>

<?php stdfoot(); ?>