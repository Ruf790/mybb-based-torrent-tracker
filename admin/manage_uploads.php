<?php



$rootpath = './../';
require_once $rootpath . 'global.php';





if (isset($_GET['delete']) && is_numeric($_GET['delete'])) 
{
    header('Content-Type: application/json; charset=utf-8');

    $file_id = (int)$_GET['delete'];

    $file_result = $db->sql_query("
        SELECT 
            cf.id, 
            cf.file_path, 
            cf.comment_id,
            cf.news_id,
            cf.torrent_id,
            cf.post_id,
			cf.messages_id,
            c.text AS comment_text,
            n.body AS news_text,
            t.descr AS torrent_description,
            p.message AS post_message,
			pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id = cf.comment_id
        LEFT JOIN news n ON n.id = cf.news_id
        LEFT JOIN torrents t ON t.id = cf.torrent_id
        LEFT JOIN tsf_posts p ON p.pid = cf.post_id
		LEFT JOIN privatemessages pm ON pm.pmid = cf.messages_id
        WHERE cf.id = $file_id
    ");

    if ($file = $db->fetch_array($file_result)) 
    {
        // Удаляем файл с диска
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }

        // Паттерн для поиска [img]...файл...[/img]
        $filename = basename($file['file_path']);
        $image_pattern = '/\[img\][^\[]*' . preg_quote($filename, '/') . '[^\[]*\[\/img\]/i';

        // --- Обновляем комментарий
        if ($file['comment_id']) {
            $text = $file['comment_text'];
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $text);

            if ($new_text !== $text) {
                $db->sql_query("
                    UPDATE comments 
                    SET text = '" . $db->escape_string($new_text) . "'
                    WHERE id = " . (int)$file['comment_id']
                );
            }
        }

        // --- Обновляем новость
        if ($file['news_id']) {
            $text = $file['news_text'];
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $text);

            if ($new_text !== $text) {
                $db->sql_query("
                    UPDATE news 
                    SET body = '" . $db->escape_string($new_text) . "'
                    WHERE id = " . (int)$file['news_id']
                );
                $cache->update_news();
            }
        }

        // --- Обновляем описание торрента
        if ($file['torrent_id']) {
            $text = $file['torrent_description'];
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $text);

            if ($new_text !== $text) {
                $db->sql_query("
                    UPDATE torrents 
                    SET descr = '" . $db->escape_string($new_text) . "'
                    WHERE id = " . (int)$file['torrent_id']
                );
            }
        }

        // --- Обновляем пост (tsf_posts)
        if ($file['post_id']) {
            $text = $file['post_message'];
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $text);

            if ($new_text !== $text) {
                $db->sql_query("
                    UPDATE tsf_posts 
                    SET message = '" . $db->escape_string($new_text) . "'
                    WHERE pid = " . (int)$file['post_id']
                );
            }
        }
		
		
		
		
		//// Update Messages 
		if (!empty($file['messages_id'])) 
		{
            $text = $file['pm_message']; // берём текст ЛС из выборки
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $text);

            if ($new_text !== $text) 
			{
                $db->sql_query("
                    UPDATE privatemessages
                    SET message = '" . $db->escape_string($new_text) . "'
                    WHERE pmid = " . (int)$file['messages_id']
                );
             }
        }
		
		
		
		
		
		

        // Удаляем запись о файле
        $db->sql_query("DELETE FROM comment_files WHERE id = $file_id");

        echo json_encode([
            'status' => 'success',
            'id' => $file_id,
            'message' => 'Файл удалён и заменён на [Image Deleted]'
        ]);
        exit();
    } 
    else 
    {
        echo json_encode([
            'status' => 'error',
            'message' => 'Файл не найден'
        ]);
        exit();
    }
}













// Handle bulk actions (AJAX)
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
    header('Content-Type: application/json; charset=utf-8');

    $selected = $_POST['selected_files'] ?? [];

    // Преобразуем в массив
    if (is_string($selected)) {
        $selected = $selected !== '' ? [$selected] : [];
    }
    if (!is_array($selected)) {
        $selected = [];
    }

    // Если пусто
    if (empty($selected)) {
        echo json_encode(['status' => 'error', 'message' => 'Нет выбранных файлов']);
        exit();
    }

    // Преобразуем в целые
    $ids = array_filter(array_map('intval', $selected));
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Некорректные ID файлов']);
        exit();
    }

    $ids_str = implode(',', $ids);

    
	
	// Выбираем файлы
    $files_result = $db->sql_query("
    SELECT 
        cf.id, 
        cf.file_path, 
        cf.comment_id,
        cf.news_id,
        cf.torrent_id,
        cf.post_id,
        cf.messages_id,
        c.text  AS comment_text,
        n.body  AS news_text,
        t.descr AS torrent_description,
        p.message AS post_message,
        pm.message AS pm_message
    FROM comment_files cf
    LEFT JOIN comments        c  ON c.id   = cf.comment_id
    LEFT JOIN news            n  ON n.id   = cf.news_id
    LEFT JOIN torrents        t  ON t.id   = cf.torrent_id
    LEFT JOIN tsf_posts       p  ON p.pid  = cf.post_id
    LEFT JOIN privatemessages pm ON pm.pmid = cf.messages_id
    WHERE cf.id IN ($ids_str)
    ");
	
	
	

    $affected_comments = [];
    $affected_news = [];
    $affected_torrents = [];
    $affected_posts = [];
    $deleted_ids = [];
	$affected_pms = [];

    while ($file = $db->fetch_array($files_result)) {
        $deleted_ids[] = $file['id'];

        // Удаляем физический файл
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }

        // Точный поиск только удаляемого изображения
        $filename = basename($file['file_path']);
        $image_pattern = '/\[img\][^\[]*' . preg_quote($filename, '/') . '[^\]]*\[\/img\]/i';

        // Обновляем тексты
        if ($file['comment_id'] && !empty($file['comment_text'])) {
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $file['comment_text']);
            if ($new_text !== $file['comment_text']) {
                $affected_comments[$file['comment_id']] = $new_text;
            }
        }

        if ($file['news_id'] && !empty($file['news_text'])) {
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $file['news_text']);
            if ($new_text !== $file['news_text']) {
                $affected_news[$file['news_id']] = $new_text;
            }
        }

        if ($file['torrent_id'] && !empty($file['torrent_description'])) {
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $file['torrent_description']);
            if ($new_text !== $file['torrent_description']) {
                $affected_torrents[$file['torrent_id']] = $new_text;
            }
        }

        if ($file['post_id'] && !empty($file['post_message'])) 
		{
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $file['post_message']);
            if ($new_text !== $file['post_message']) {
                $affected_posts[$file['post_id']] = $new_text;
            }
        }
		
		
		if ($file['messages_id'] && !empty($file['pm_message'])) 
		{
            $new_text = preg_replace($image_pattern, '[Image Deleted]', $file['pm_message']);
            if ($new_text !== $file['pm_message']) 
			{
                $affected_pms[(int)$file['messages_id']] = $new_text;
            }
        }
				
		
    }
	
	
	

    // Обновляем комментарии
    foreach ($affected_comments as $comment_id => $new_text) {
        $db->sql_query("
            UPDATE comments 
            SET text = '" . $db->escape_string($new_text) . "'
            WHERE id = " . (int)$comment_id
        );
    }

    // Обновляем новости
    foreach ($affected_news as $news_id => $new_text) {
        $db->sql_query("
            UPDATE news 
            SET body = '" . $db->escape_string($new_text) . "'
            WHERE id = " . (int)$news_id
        );
        $cache->update_news();
    }

    // Обновляем торренты
    foreach ($affected_torrents as $torrent_id => $new_text) {
        $db->sql_query("
            UPDATE torrents 
            SET descr = '" . $db->escape_string($new_text) . "'
            WHERE id = " . (int)$torrent_id
        );
    }

    // Обновляем посты
    foreach ($affected_posts as $post_id => $new_text) {
        $db->sql_query("
            UPDATE tsf_posts 
            SET message = '" . $db->escape_string($new_text) . "'
            WHERE pid = " . (int)$post_id
        );
    }
	
	
	// Обновляем лс
	foreach ($affected_pms as $pmid => $new_text) {
        $db->sql_query("
            UPDATE privatemessages
            SET message = '" . $db->escape_string($new_text) . "'
            WHERE pmid = " . (int)$pmid
        );
    }
	
	
	

    // Удаляем записи о файлах
    $db->sql_query("DELETE FROM comment_files WHERE id IN ($ids_str)");

    echo json_encode([
        'status' => 'success',
        'message' => 'Файлы удалены',
        'deleted_ids' => $deleted_ids
    ]);
    exit();
}











// Check for AJAX request
$is_ajax = isset($_GET['ajax_search']) && $_GET['ajax_search'] == '1';

// If not AJAX, output header
if (!$is_ajax) {
    stdhead();
}



// Pagination
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? $db->escape_string($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? $db->escape_string($_GET['type']) : '';

// Build WHERE conditions
$where = [];
if ($search) {
    $where[] = "(file_name LIKE '%$search%' OR file_type LIKE '%$search%')";
}

if ($typeFilter) {
    switch ($typeFilter) {
        case 'torrent':
            $where[] = "torrent_id IS NOT NULL AND torrent_id != 0";
            break;
        case 'news':
            $where[] = "news_id IS NOT NULL AND news_id != 0";
            break;
        case 'comment':
            $where[] = "comment_id IS NOT NULL AND comment_id != 0";
            break;
        case 'post':
            $where[] = "post_id IS NOT NULL AND post_id != 0";
            break;
		case 'message':
            $where[] = "messages_id IS NOT NULL AND messages_id != 0";
            break;
    }
}

$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Get total file count
$total_result = $db->sql_query("SELECT COUNT(*) as total FROM comment_files $whereClause");
$total_row = $db->fetch_array($total_result);
$total_files = $total_row['total'];
$total_pages = ceil($total_files / $per_page);

// Get files
$result = $db->sql_query("
    SELECT comment_files.*, users.username, users.usergroup, users.avatar, users.avatardimensions
    FROM comment_files 
    LEFT JOIN users ON users.id = comment_files.user_id
    $whereClause
    ORDER BY comment_files.uploaded_at DESC 
    LIMIT $offset, $per_page
");

$files = array();
while ($row = $db->fetch_array($result)) 
{
    $files[] = $row;
}

// Update $this_script2 to include all filters
$this_script2 = "index.php?act=manage_uploads"
    .($search ? "&search=".urlencode($search) : "")
    .($typeFilter ? "&type=".urlencode($typeFilter) : "");














// If AJAX request, return only the table
if ($is_ajax) {
    ob_start();
    include('manage_uploads_ajax.php');
    $table_html = ob_get_clean();
    echo $table_html;
    stdfoot();
    exit();
}

function getFileDimensions($file_path) {
    if (!file_exists($file_path)) return 'N/A';
    
    $info = getimagesize($file_path);
    return $info ? $info[0] . '×' . $info[1] : 'N/A';
}
?>



    <title>Media Library | Admin Panel</title>
    
	
	
	
	<link rel="stylesheet" href="<?= $BASEURL; ?>/include/templates/default/style/bootstrap-icons.css" type="text/css" media="screen" />

	
	
	
    <style>
    :root {
        --primary: #6366f1;
        --primary-light: #818cf8;
        --primary-dark: #4f46e5;
        --secondary: #94a3b8;
        --secondary-light: #e2e8f0;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --light: #f8fafc;
        --dark: #1e293b;
        --gray: #64748b;
        --card-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        --card-shadow-hover: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
	
	
	
	
	
	
	.file-type-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
    }
   .file-type-image { background-color: #4e73df; }
   .file-type-pdf { background-color: #e74a3b; }
   .file-type-doc { background-color: #1cc88a; }
   .file-type-zip { background-color: #f6c23e; }
   .file-type-other { background-color: #858796; }
   
   
   
   .card {
        border: none;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
        background-color: white;
        overflow: hidden;
    }
    .card:hover {
        box-shadow: var(--card-shadow-hover);
        transform: translateY(-2px);
    }
	

    .table {
        --bs-table-bg: transparent;
        margin-bottom: 0;
    }
	
	

    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        color: var(--gray);
        border-bottom-width: 1px;
        padding: 12px 16px;
        background-color: #f8fafc;
    }
    .table td {
        vertical-align: middle;
        padding: 16px;
        border-top: 1px solid #f1f5f9;
    }
    .table tr:last-child td {
        border-bottom: none;
    }

    .img-preview {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        transition: var(--transition);
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .img-preview:hover {
        transform: scale(2.5);
        z-index: 100;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        cursor: zoom-in;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: var(--transition);
        border: none;
        background-color: #f1f5f9;
        color: var(--gray);
    }
    .action-btn:hover {
        transform: translateY(-2px);
        color: white;
    }
    .btn-edit:hover {
        background-color: var(--primary);
    }
    .btn-delete:hover {
        background-color: var(--danger);
    }
    .btn-download:hover {
        background-color: var(--success);
    }

    .badge {
        font-weight: 500;
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        border-radius: 8px;
    }
    .badge-comment {
        background-color: rgba(59, 130, 246, 0.1);
        color: var(--info);
    }
    .badge-news {
        background-color: rgba(99, 102, 241, 0.1);
        color: var(--primary);
    }
    .badge-torrent {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    .badge-user {
        background-color: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .page-header {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .page-icon {
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border-radius: 16px;
        font-size: 1.75rem;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3), 0 2px 4px -1px rgba(79, 70, 229, 0.2);
    }

    .search-box {
        position: relative;
        flex: 1;
    }
    .search-box .form-control {
        padding-left: 3rem;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        transition: var(--transition);
        height: 48px;
    }
    .search-box .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }
    .search-box .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
        font-size: 1.1rem;
    }

    .file-icon {
        font-size: 1.75rem;
    }
    .file-icon.image {
        color: #f59e0b;
    }
    .file-icon.pdf {
        color: #ef4444;
    }
    .file-icon.doc {
        color: #3b82f6;
    }
    .file-icon.zip {
        color: #94a3b8;
    }
    .file-icon.video {
        color: #8b5cf6;
    }
    .file-icon.audio {
        color: #ec4899;
    }

   
    .preview-modal-img {
        max-height: 70vh;
        max-width: 100%;
        object-fit: contain;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
    }
    .modal-header {
        border-bottom: none;
        padding-bottom: 0;
    }
    .modal-footer {
        border-top: none;
    }

    .spinner-border {
        width: 2rem;
        height: 2rem;
        border-width: 0.2em;
    }

    .bulk-actions {
        transition: var(--transition);
        opacity: 0;
        height: 0;
        overflow: hidden;
        margin-bottom: 0;
    }
    .bulk-actions.show {
        opacity: 1;
        height: auto;
        padding: 16px 0;
        margin-bottom: 16px;
    }

    

    .alert {
        border-radius: 12px;
        border: none;
        box-shadow: var(--card-shadow);
    }

    

    .dropdown-menu {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        padding: 8px;
    }
    .dropdown-item {
        border-radius: 8px;
        padding: 8px 12px;
        transition: var(--transition);
    }
    .dropdown-item:hover {
        background-color: #f1f5f9;
    }

    .text-muted {
        color: var(--gray) !important;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .status-badge.active {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    .status-badge.inactive {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }
    .status-badge.pending {
        background-color: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
        border: 2px solid #f1f5f9;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
	
	
	.hover-shadow {
        transition: all 0.2s ease;
    }
    .hover-shadow:hover {
        box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }
    .transition-all {
        transition-property: all;
    }
	
	

    .file-size {
        font-family: 'Roboto Mono', monospace;
        font-size: 0.85rem;
        color: var(--gray);
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        .search-box {
            width: 100%;
        }
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
    }
    </style>


<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div class="page-header">
            <div class="page-icon">
                <i class="bi bi-images"></i>
            </div>
            <div>
                <h1 class="mb-1 fw-bold">Media Library</h1>
                <p class="text-muted mb-0">Manage all uploaded media files and attachments</p>
            </div>
        </div>
        <div>
            <span class="badge bg-light text-dark fs-6">
                <i class="bi bi-database me-1"></i> <?= number_format($total_files) ?> files
            </span>
        </div>
    </div>

    <!-- Success messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div class="fw-medium"><?= str_replace('+', ' ', htmlspecialchars($_GET['success'])) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Search and actions -->
    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                <form class="search-box w-100" id="searchForm">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control form-control-lg" name="search" placeholder="Search files by name, type or user..." 
                           value="<?= htmlspecialchars($search) ?>" id="searchInput">
                    <?php if ($search): ?>
                    <a href="<?= $this_script_ ?>" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                    <?php endif; ?>
                </form>
                
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">File Type</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-image me-2"></i> Images</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-pdf me-2"></i> PDFs</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-word me-2"></i> Documents</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Sort By</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-up me-2"></i> Newest First</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-down me-2"></i> Oldest First</a></li>
                        </ul>
                    </div>
                    
                    <button type="button" class="btn btn-primary" id="bulkSelectBtn">
                        <i class="bi bi-check2-square me-1"></i> Select Files
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk actions -->
  
<div class="bulk-actions card mb-4" id="bulkActions">
    <div class="card-body p-3">
        <form method="POST" action="<?= $_this_script_ ?>" class="d-flex flex-column flex-md-row align-items-md-center gap-3" id="bulkForm">
            <div class="d-flex align-items-center">
                <i class="bi bi-check2-square me-2 fs-5 text-primary"></i>
                <span class="fw-medium" id="selectedCount">0</span>
                <span class="text-muted ms-1">selected</span>
            </div>
            
            <select name="bulk_action" class="form-select flex-grow-1" required>
                <option value="">Choose action...</option>
                <option value="delete">Delete selected files</option>
                <option value="move">Move to folder</option>
                <option value="archive">Archive selected</option>
            </select>
            
            <div class="d-flex gap-2">
                <!-- Нажатие открывает модалку -->
                <button type="button" class="btn btn-danger" id="applyBulkAction" disabled>
                    <i class="bi bi-trash3 me-1"></i> Apply Action
                </button>
                <button type="button" class="btn btn-outline-secondary" id="cancelBulkAction">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
            </div>
            
            <!-- Важно: оставляем как массив -->
            <input type="hidden" name="selected_files[]" id="selectedFilesInput">
        </form>
    </div>
</div>

    <!-- Files table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <div id="filesTableContainer">
                    <?php include('manage_uploads_ajax.php'); ?>
                </div>
            </div>
        </div>
    </div>
</div>





<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i> Edit File Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= $this_script_ ?>">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="update" value="1">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">File Name</label>
                        <input type="text" class="form-control" name="file_name" id="editFileName">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium d-flex align-items-center gap-2">
                                <i class="bi bi-chat-square-text"></i> Comment ID
                            </label>
                            <input type="number" class="form-control" name="comment_id" id="editCommentId">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium d-flex align-items-center gap-2">
                                <i class="bi bi-newspaper"></i> News ID
                            </label>
                            <input type="number" class="form-control" name="news_id" id="editNewsId">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium d-flex align-items-center gap-2">
                                <i class="bi bi-download"></i> Torrent ID
                            </label>
                            <input type="number" class="form-control" name="torrent_id" id="editTorrentId">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium d-flex align-items-center gap-2">
                                <i class="bi bi-card-text"></i> Post ID
                            </label>
                            <input type="number" class="form-control" name="post_id" id="editPostId">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium d-flex align-items-center gap-2">
                                <i class="bi bi-person"></i> User ID
                            </label>
                            <input type="number" class="form-control" name="user_id" id="editUserId">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>







<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="previewModalTitle">
                    <i class="bi bi-image text-primary"></i> Image Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light p-0">
                <div id="imageContainer" style="position: relative; height: 70vh; overflow: hidden;">
                    <img src="" class="preview-modal-img" id="previewModalImage" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div class="text-start">
                        <span class="text-muted fw-medium" id="fileDimensions"></span>
                        <span class="text-muted mx-2">•</span>
                        <span class="text-muted fw-medium" id="fileSize"></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="fullscreenBtn">
                            <i class="bi bi-arrows-angle-expand me-1"></i> Fullscreen
                        </button>
                        <a href="#" class="btn btn-primary" id="downloadPreviewBtn">
                            <i class="bi bi-download me-1"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-arrow-up text-primary"></i> Upload Files
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="border-2 border-dashed rounded-3 p-4 text-center bg-light">
                    <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-3"></i>
                    <h5 class="fw-medium mb-2">Drag & drop files here</h5>
                    <p class="text-muted mb-3">or click to browse</p>
                    <input type="file" class="d-none" id="fileUploadInput" multiple>
                    <button class="btn btn-primary" onclick="document.getElementById('fileUploadInput').click()">
                        <i class="bi bi-folder2-open me-1"></i> Select Files
                    </button>
                </div>
                
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-medium">File type restrictions:</span>
                        <span class="text-muted">Images, PDF, Docs</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-medium">Max file size:</span>
                        <span class="text-muted">10 MB</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-medium">Total uploads:</span>
                        <span class="text-muted">Up to 10 files</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary">
                    <i class="bi bi-upload me-1"></i> Start Upload
                </button>
            </div>
        </div>
    </div>
</div>






<!-- Модалка подтверждения -->
<div class="modal fade" id="confirmBulkDeleteModal" tabindex="-1" aria-labelledby="confirmBulkDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmBulkDeleteModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Confirmation
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center mb-3">
          <i class="bi bi-trash-fill text-danger fs-1 me-3"></i>
          <div>
            <h5>Are you sure you want to delete <span id="filesCount" class="fw-bold">0</span> files?</h5>
            <p class="text-muted mb-0">All related images in content will be replaced with "[Image Deleted]"</p>
          </div>
        </div>
        <div class="alert alert-warning mt-3">
          <i class="bi bi-exclamation-circle-fill me-2"></i>
          This action cannot be undone!
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmBulkDelete">
          <i class="bi bi-trash3 me-1"></i> Yes, Delete
        </button>
      </div>
    </div>
  </div>
</div>
















<script>
// Fullscreen functionality
function toggleFullscreen() {
    const imageContainer = document.getElementById('imageContainer');
    const image = document.getElementById('previewModalImage');
    
    if (!document.fullscreenElement) {
        if (imageContainer.requestFullscreen) {
            imageContainer.requestFullscreen();
        } else if (imageContainer.webkitRequestFullscreen) { /* Safari */
            imageContainer.webkitRequestFullscreen();
        } else if (imageContainer.msRequestFullscreen) { /* IE11 */
            imageContainer.msRequestFullscreen();
        }
        
        // Adjust image styling in fullscreen
        image.style.maxHeight = '100vh';
        image.style.maxWidth = '100vw';
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) { /* Safari */
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { /* IE11 */
            document.msExitFullscreen();
        }
        
        // Reset image styling
        image.style.maxHeight = '100%';
        image.style.maxWidth = '100%';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Fullscreen button
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', toggleFullscreen);
    }
    
    // Handle fullscreen change events
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('msfullscreenchange', handleFullscreenChange);
    
    function handleFullscreenChange() {
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        if (fullscreenBtn) {
            if (document.fullscreenElement || 
                document.webkitFullscreenElement || 
                document.msFullscreenElement) {
                fullscreenBtn.innerHTML = '<i class="bi bi-arrows-angle-contract me-1"></i> Exit Fullscreen';
            } else {
                fullscreenBtn.innerHTML = '<i class="bi bi-arrows-angle-expand me-1"></i> Fullscreen';
            }
        }
    }
    
    // Image preview modal
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const imgSrc = button.getAttribute('data-img-src');
            const imgName = button.getAttribute('data-img-name');
            const imgDimensions = button.getAttribute('data-dimensions') || 'N/A';
            const imgSize = button.getAttribute('data-size') || 'N/A';
            
            document.getElementById('previewModalImage').src = imgSrc;
            document.getElementById('previewModalTitle').textContent = imgName;
            document.getElementById('fileDimensions').textContent = imgDimensions;
            document.getElementById('fileSize').textContent = imgSize;
            document.getElementById('downloadPreviewBtn').href = imgSrc;
            document.getElementById('downloadPreviewBtn').download = imgName;
        });
        
        // Reset fullscreen when modal closes
        previewModal.addEventListener('hidden.bs.modal', function() {
            if (document.fullscreenElement || 
                document.webkitFullscreenElement || 
                document.msFullscreenElement) {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        });
    }
});






// Debounce function
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

// Handle search
function handleSearch() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    const url = new URL(window.location.href);
    
    // Update URL parameters
    url.searchParams.set('search', searchTerm);
    url.searchParams.set('ajax_search', '1');
    url.searchParams.delete('page'); // Reset to first page on new search
    
    // Show loading indicator
    document.getElementById('filesTableContainer').innerHTML = `
        <div class="text-center py-5 my-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Searching files...</p>
        </div>
    `;
    
    // Fetch results
    fetch(url.toString())
        .then(response => response.text())
        .then(html => {
            document.getElementById('filesTableContainer').innerHTML = html;
            // Reinitialize event handlers
            initializeTableEvents();
            updatePaginationLinks(searchTerm);
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('filesTableContainer').innerHTML = `
                <div class="alert alert-danger mx-4 my-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Error loading results. Please try again.
                </div>
            `;
        });
}

// Update pagination links after search
function updatePaginationLinks(searchTerm) {
    document.querySelectorAll('.pagination a').forEach(link => {
        const href = new URL(link.href);
        if (searchTerm) {
            href.searchParams.set('search', searchTerm);
        } else {
            href.searchParams.delete('search');
        }
        link.href = href.toString();
    });
}

// Initialize table event handlers
function initializeTableEvents() {
    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('editId').value = this.dataset.id;
            document.getElementById('editFileName').value = this.dataset.fileName || '';
            document.getElementById('editCommentId').value = this.dataset.commentId || '';
            document.getElementById('editNewsId').value = this.dataset.newsId || '';
            document.getElementById('editTorrentId').value = this.dataset.torrentId || '';
            
			 document.getElementById('editPostId').value = this.dataset.postId || '';
			
			
			
			document.getElementById('editUserId').value = this.dataset.userId || '';
            document.getElementById('editDescription').value = this.dataset.description || '';
        });
    });
    
    // Bulk selection
    const selectAll = document.getElementById('selectAll');
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    
    function updateSelection() {
        const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
            .map(cb => cb.value);
        document.getElementById('selectedCount').textContent = selectedFiles.length;
        document.getElementById('selectedFilesInput').value = selectedFiles.join(',');
        selectAll.checked = selectedFiles.length === fileCheckboxes.length && selectedFiles.length > 0;
        
        // Show/hide bulk actions
        const bulkActions = document.getElementById('bulkActions');
        if (selectedFiles.length > 0) {
            bulkActions.classList.add('show');
        } else {
            bulkActions.classList.remove('show');
        }
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', (e) => {
            fileCheckboxes.forEach(cb => cb.checked = e.target.checked);
            updateSelection();
        });
    }
    
    fileCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelection);
    });
    
    // Image preview
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const imgSrc = button.getAttribute('data-img-src');
            const imgName = button.getAttribute('data-img-name');
            const imgDimensions = button.getAttribute('data-dimensions') || 'N/A';
            const imgSize = button.getAttribute('data-size') || 'N/A';
            
            document.getElementById('previewModalImage').src = imgSrc;
            document.getElementById('previewModalTitle').textContent = imgName;
            document.getElementById('fileDimensions').textContent = imgDimensions;
            document.getElementById('fileSize').textContent = imgSize;
            document.getElementById('downloadPreviewBtn').href = imgSrc;
            document.getElementById('downloadPreviewBtn').download = imgName;
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    initializeTableEvents();
    
    // Delayed search
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');
    const debouncedSearch = debounce(handleSearch, 350);
    
    if (searchInput) {
        searchInput.addEventListener('input', debouncedSearch);
        searchInput.focus();
    }
    
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleSearch();
        });
    }
    
    // Bulk actions
    const bulkSelectBtn = document.getElementById('bulkSelectBtn');
    const cancelBulkAction = document.getElementById('cancelBulkAction');
    const bulkActions = document.getElementById('bulkActions');
    
    if (bulkSelectBtn) {
        bulkSelectBtn.addEventListener('click', () => {
            // Toggle all checkboxes
            const checkboxes = document.querySelectorAll('.file-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => cb.checked = !allChecked);
            
            // Trigger change event to update selection
            checkboxes.forEach(cb => {
                const event = new Event('change');
                cb.dispatchEvent(event);
            });
        });
    }
    
    if (cancelBulkAction && bulkActions) {
        cancelBulkAction.addEventListener('click', () => {
            bulkActions.classList.remove('show');
            document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectedCount').textContent = '0';
            document.getElementById('selectedFilesInput').value = '';
            if (document.getElementById('selectAll')) {
                document.getElementById('selectAll').checked = false;
            }
        });
    }
    
    // File upload drag and drop
    const dropArea = document.querySelector('.border-dashed');
    if (dropArea) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('border-primary', 'bg-primary-soft');
        }
        
        function unhighlight() {
            dropArea.classList.remove('border-primary', 'bg-primary-soft');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            console.log('Files dropped:', files);
            // Handle file upload here
        }
    }
});
</script>

<?php
stdfoot();
?>