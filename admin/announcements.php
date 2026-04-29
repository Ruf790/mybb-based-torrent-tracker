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

// Проверка прямого доступа
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/class_parser.php';

$parser = new postParser;

$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

require_once __DIR__ . '/../cache/smilies.php';

define('B_VERSION', 'v.0.6');



// Helper functions
function extractKeywords($text) {
    $text = strip_tags($text);
    $words = str_word_count($text, 1);
    $stopWords = ['the', 'and', 'for', 'you', 'your', 'with', 'this', 'that', 'have', 'from'];
    $keywords = array_diff($words, $stopWords);
    $keywords = array_unique($keywords);
    $keywords = array_slice($keywords, 0, 10);
    return $keywords;
}

function countBBCodeTags($text) {
    $tags = ['b', 'i', 'u', 'url', 'img', 'quote', 'code', 'color', 'size', 'font'];
    $counts = [];
    foreach ($tags as $tag) {
        $pattern = '/\[' . preg_quote($tag) . '[^\]]*\]/i';
        $counts[$tag] = preg_match_all($pattern, $text);
    }
    return array_filter($counts);
}

function determineContentType($text) {
    if (preg_match('/\[img\]/i', $text)) {
        return 'With Images';
    } elseif (preg_match('/\[url\]/i', $text)) {
        return 'With Links';
    } elseif (strlen($text) > 500) {
        return 'Long Text';
    } elseif (strlen($text) < 100) {
        return 'Short Text';
    } else {
        return 'Standard';
    }
}

/**
 * Calculate reading time for text
 */
function calculateReadingTime($text): string {
    $text = strip_tags($text);
    $words = str_word_count($text);
    $minutes = ceil($words / 200);
    return $minutes . ' min' . ($minutes !== 1 ? 's' : '');
}




/**
 * Отображение объявления во всплывающем окне
 */
function showAnnouncement(int $aid, string $subject, string $message, int $added, string $by, string $class): void
{
    global $SITENAME, $BASEURL, $parser, $parser_options, $pic_base_url, $charset;
    
     $defaulttemplate = 'default';
    $formattedDate = my_datee('relative', $added);
    $parsedMessage = $parser->parse_message($message, $parser_options);
    
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="<?= htmlspecialchars($charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($SITENAME) ?> - Announcement: <?= htmlspecialchars($subject) ?></title>
    <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/<?= $defaulttemplate ?>/style/style.css">
    <style>
        .announcement-modal {
            position: fixed;
            left: 50%;
            top: -100px;
            transform: translateX(-50%);
            width: 650px;
            max-width: 95vw;
            background: #F5F5F5;
            border: 2px solid #dc3545;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 9999;
            opacity: 0;
            transition: top 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55), opacity 0.3s;
        }
        .announcement-modal.show {
            top: 20px;
            opacity: 1;
        }
        .modal-header {
            background: #dc3545;
            color: white;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body {
            padding: 15px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .meta-info {
            font-size: 0.9em;
            margin-bottom: 10px;
            color: #666;
            border-bottom: 1px dashed #ddd;
            padding-bottom: 8px;
        }
        .meta-info span {
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <div id="announcementModal" class="announcement-modal">
        <div class="modal-header">
            <strong>ANNOUNCEMENT: <?= htmlspecialchars($subject) ?></strong>
            <button class="close-btn" onclick="closeAnnouncement()">×</button>
        </div>
        <div class="modal-body">
            <div class="meta-info">
                <span><strong>Created:</strong> <?= $formattedDate ?></span>
                <span><strong>By:</strong> <?= htmlspecialchars($by) ?></span>
                <span><strong>For:</strong> <?= htmlspecialchars($class) ?></span>
            </div>
            <div class="content">
                <?= $parsedMessage ?>
            </div>
        </div>
    </div>

    <script>
        function showAnnouncement() {
            const modal = document.getElementById('announcementModal');
            modal.classList.add('show');
            
            // Автоматическое закрытие через 30 секунд
            setTimeout(() => {
                if (modal.classList.contains('show')) {
                    closeAnnouncement();
                }
            }, 30000);
        }
        
        function closeAnnouncement() {
            const modal = document.getElementById('announcementModal');
            modal.classList.remove('show');
            setTimeout(() => {
                window.location.href = '<?= $BASEURL ?>/admin/announcements.php';
            }, 300);
        }
        
        // Закрытие по клику вне модалки
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('announcementModal');
            if (!modal.contains(e.target) && modal.classList.contains('show')) {
                closeAnnouncement();
            }
        });
        
        // Закрытие по Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAnnouncement();
            }
        });
        
        window.onload = showAnnouncement;
    </script>
</body>
</html>
<?php
    ob_end_flush();
}

// Обработка параметров
$action = $_POST['action'] ?? $_GET['action'] ?? 'show';
$do = $_POST['do'] ?? $_GET['do'] ?? '';
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

switch ($action) {
    case 'show':
        handleShowAction();
        break;
        
    case 'see':
        handleSeeAction($id);
        break;
        
    case 'add':
        handleAddAction($do);
        break;
        
    case 'edit':
        handleEditAction($id, $do);
        break;
        
    case 'delete':
        handleDeleteAction($id);
        break;
		
	case 'duplicate':
        handleDuplicateAction($id);
        break;	
        
    default:
        redirect('admin/index.php?act=announcements', 'Invalid action specified');
}




/**
 * Показать список объявлений
 */
function handleShowAction(): void
{
    global $db;
    
    stdhead('Announcements ' . B_VERSION);
    
    $countRows = number_format(tsrowcount('id', 'announcements'));
    $page = max(0, (int)($_GET['page'] ?? 0));
    $perPage = 15;
    
    // Пагинация
    $limit = $perPage;
    $offset = $page * $perPage;
    
    $res = $db->sql_query("SELECT * FROM announcements ORDER BY added DESC LIMIT $offset, $limit");
    
    // Кнопка создания нового объявления
    $where = ['New Announcement' => $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=add'];
    ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Announcements Management</h1>
            <div>
                <?= jumpbutton($where) ?>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Announcements List</h5>
                    <span class="badge bg-light text-dark">Total: <?= $countRows ?></span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="60">ID</th>
                            <th>Subject</th>
                            <th width="300">Preview</th>
                            <th width="180">Added</th>
                            <th width="120">Min. Class</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($db->num_rows($res) > 0): ?>
                            <?php while ($arr = mysqli_fetch_assoc($res)): ?>
                                <?php
                                $timeAgo = mkprettytime(TIMENOW - (int)$arr['added']);
                                $preview = substr(strip_tags($arr['message']), 0, 100) . '...';
                                ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= (int)$arr['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($arr['subject']) ?></strong>
                                        <div class="text-muted small">By: <?= htmlspecialchars($arr['by']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($preview) ?>">
                                            <?= htmlspecialchars($preview) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?= my_datee('relative', (int)$arr['added']) ?>
                                            <br>
                                            <span class="badge bg-secondary"><?= $timeAgo ?> ago</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= get_user_class_name((int)$arr['minclassread']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=see&id=<?= (int)$arr['id'] ?>" 
                                               class="btn btn-outline-info" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=edit&id=<?= (int)$arr['id'] ?>" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
											
											<a href="#"
   class="btn btn-outline-danger"
   title="Delete"
   onclick="openDeleteModal(<?= (int)$arr['id'] ?>, '<?= htmlspecialchars(addslashes($arr['subject'])) ?>'); return false;">
    <i class="fas fa-trash"></i>
</a>

											
											
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                        No announcements found
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($countRows > $perPage): ?>
                <div class="card-footer">
                    <nav aria-label="Announcements pagination">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($i = 0; $i < ceil($countRows / $perPage); $i++): ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?act=announcements&action=show&page=<?= $i ?>">
                                        <?= $i + 1 ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
	
	
	<!-- Delete Announcement Modal -->
<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Delete Announcement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="mb-2">
                    You are about to delete the following announcement:
                </p>
                <div class="alert alert-warning fw-bold" id="deleteAnnouncementTitle"></div>
                <p class="text-danger mb-0">
                    <strong>This action cannot be undone.</strong>
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>

                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    let deleteModal;

    document.addEventListener('DOMContentLoaded', function () {
        deleteModal = new bootstrap.Modal(
            document.getElementById('deleteAnnouncementModal')
        );
    });

    function openDeleteModal(id, subject) {
        document.getElementById('deleteAnnouncementTitle').textContent = subject;

        document.getElementById('confirmDeleteBtn').href =
            '<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=delete&id=' + id + '&sure=yes';

        deleteModal.show();
    }
</script>

	
	
	
	
	
	
	
    
    <?php
    stdfoot();
}



/**
 * Дублирование объявления
 */

function handleDuplicateAction(int $id): void
{
    global $db;
    
    // Проверяем, это AJAX запрос?
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($id <= 0) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
            exit;
        } else {
            redirect('admin/index.php?act=announcements', 'Invalid announcement ID');
        }
    }
    
    // Проверяем POST запрос
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'duplicate') {
        // Получаем оригинальное объявление
        $res = $db->sql_query("SELECT * FROM announcements WHERE id = " . $db->sqlesc($id));
        $original = $db->fetch_array($res);
        
        if ($original) {
            // Создаем копию с уникальным названием (чтобы избежать дубликатов)
            $subject = 'Copy of ' . $original['subject'];
            
            // Проверяем, не существует ли уже такая копия
            $counter = 1;
            $checkRes = $db->sql_query("SELECT COUNT(*) as count FROM announcements WHERE subject LIKE '" . $db->escape_string($subject) . "%'");
            $checkRow = $db->fetch_array($checkRes);
            if ($checkRow['count'] > 0) {
                $subject = 'Copy of ' . $original['subject'] . ' (' . ($checkRow['count'] + 1) . ')';
            }
            
            // Создаем копию
            $insert_data = [
                "subject" => $subject,
                "message" => $original['message'],
                "by" => $original['by'],
                "minclassread" => $original['minclassread'],
                "added" => TIMENOW
            ];
            
            // Вставляем новое объявление
            $result = $db->insert_query("announcements", $insert_data);
            
            if ($result) {
                $newId = $db->insert_id();
                
                // Сбрасываем статус прочтения для соответствующих пользователей
                if ($original['minclassread'] == 0) {
                    $db->sql_query("UPDATE users SET announce_read = 'no' WHERE enabled = 'yes' AND ustatus = 'confirmed'");
                } else {
                    $db->sql_query("UPDATE users SET announce_read = 'no' WHERE enabled = 'yes' 
                                   AND ustatus = 'confirmed' AND usergroup = " . (int)$original['minclassread']);
                }
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Announcement duplicated successfully',
                        'new_id' => $newId,
                        'redirect_url' => $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=see&id=' . $newId
                    ]);
                    exit;
                } else {
                    redirect("admin/index.php?act=announcements&action=see&id=$newId", 'Announcement duplicated successfully');
                }
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Failed to duplicate announcement']);
                    exit;
                } else {
                    redirect('admin/index.php?act=announcements', 'Failed to duplicate announcement');
                }
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Original announcement not found']);
                exit;
            } else {
                redirect('admin/index.php?act=announcements', 'Original announcement not found');
            }
        }
    }
    
    // Если не POST запрос и не AJAX, показываем страницу подтверждения
    if (!$isAjax) {
        stdhead('Duplicate Announcement');
        ?>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Duplicate Announcement</h5>
                </div>
                <div class="card-body">
                    <p>Are you sure you want to duplicate this announcement?</p>
                    <form method="POST">
                        <input type="hidden" name="do" value="duplicate">
                        <button type="submit" class="btn btn-success">Yes, Duplicate</button>
                        <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=see&id=<?= $id ?>" 
                           class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
        <?php
        stdfoot();
    } else {
        // AJAX запрос без данных POST
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
}


/**
 * Просмотр отдельного объявления в модальном окне
 */
/**
 * Просмотр отдельного объявления в модальном окне с расширенными фичами
 */
function handleSeeAction(int $id): void
{
    global $db, $parser, $parser_options, $BASEURL, $CURUSER;
    
    if ($id <= 0) {
        redirect('admin/index.php?act=announcements', 'Invalid announcement ID');
    }
    
    // Получаем текущее объявление
    $res = $db->sql_query("SELECT * FROM announcements WHERE id = " . $db->sqlesc($id));
    $current = $db->fetch_array($res);
    
    if (!$current) {
        redirect('admin/index.php?act=announcements', 'Announcement not found');
    }
    
    // Получаем предыдущее и следующее объявления
    $prevRes = $db->sql_query("SELECT id, subject FROM announcements WHERE id < " . $db->sqlesc($id) . " ORDER BY id DESC LIMIT 1");
    $prev = $db->fetch_array($prevRes);
    
    $nextRes = $db->sql_query("SELECT id, subject FROM announcements WHERE id > " . $db->sqlesc($id) . " ORDER BY id ASC LIMIT 1");
    $next = $db->fetch_array($nextRes);
    
    // Получаем статистику
    $totalRes = $db->sql_query("SELECT COUNT(*) as total FROM announcements");
    $totalRow = $db->fetch_array($totalRes);
    $totalCount = $totalRow['total'];
    
    $positionRes = $db->sql_query("SELECT COUNT(*) as pos FROM announcements WHERE id <= " . $db->sqlesc($id));
    $positionRow = $db->fetch_array($positionRes);
    $currentPosition = $positionRow['pos'];
    
    // Получаем связанные объявления (по темам)
    $keywords = extractKeywords($current['subject'] . ' ' . strip_tags($current['message']));
    $relatedQuery = "SELECT id, subject, added FROM announcements WHERE id != " . $db->sqlesc($id);
    if (!empty($keywords)) {
        $relatedQuery .= " AND (";
        $conditions = [];
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 3) {
                $conditions[] = "subject LIKE '%" . $db->escape_string($keyword) . "%' OR message LIKE '%" . $db->escape_string($keyword) . "%'";
            }
        }
        if (!empty($conditions)) {
            $relatedQuery .= implode(' OR ', $conditions) . ") ORDER BY added DESC LIMIT 5";
        }
    }
    $relatedRes = $db->sql_query($relatedQuery);
    $related = [];
    while ($row = $db->fetch_array($relatedRes)) {
        $related[] = $row;
    }
    
    // Получаем статистику просмотров (если есть таблица)
    $viewCount = (int)($current['views'] ?? 0);
    
    stdhead('View Announcement');
    ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Announcement Preview</h5>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-outline-info" onclick="toggleSidebar()">
                                <i class="fas fa-columns"></i>
                            </button>
                            <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Bootstrap Modal -->
                        <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" 
                             aria-hidden="false" data-bs-backdrop="static">
                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content shadow-lg">
                                    <!-- Modal Header with Enhanced Navigation -->
                                    <div class="modal-header bg-gradient-danger text-white d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="btn-group nav-controls">
                                                <?php if ($prev): ?>
                                                    <a href="?act=announcements&action=see&id=<?= $prev['id'] ?>" 
                                                       class="btn btn-light btn-sm" title="Previous (←)">
                                                        <i class="fas fa-chevron-left"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-light btn-sm disabled" title="No previous">
                                                        <i class="fas fa-chevron-left"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-light btn-sm" onclick="toggleFullscreen()" title="Fullscreen (F11)">
                                                    <i class="fas fa-expand"></i>
                                                </button>
                                                
                                                <?php if ($next): ?>
                                                    <a href="?act=announcements&action=see&id=<?= $next['id'] ?>" 
                                                       class="btn btn-light btn-sm" title="Next (→)">
                                                        <i class="fas fa-chevron-right"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-light btn-sm disabled" title="No next">
                                                        <i class="fas fa-chevron-right"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="vr mx-2"></div>
                                            
                                            <h5 class="modal-title mb-0" id="announcementModalLabel">
                                                <i class="fas fa-bullhorn me-2"></i>
                                                <span id="dynamicTitle"><?= htmlspecialchars($current['subject']) ?></span>
                                            </h5>
                                        </div>
                                        
                                        <div class="d-flex align-items-center gap-3">
                                            <!-- Progress Bar -->
                                            <div class="progress position-indicator" style="width: 100px; height: 6px;" 
                                                 title="<?= $currentPosition ?> of <?= $totalCount ?>">
                                                <div class="progress-bar bg-warning" 
                                                     style="width: <?= ($currentPosition / $totalCount) * 100 ?>%"></div>
                                            </div>
                                            
                                            <!-- Quick Stats -->
                                            <div class="text-end">
                                                <small class="d-block">#<?= $currentPosition ?>/<?= $totalCount ?></small>
                                                <small class="d-block">ID: <?= (int)$current['id'] ?></small>
                                            </div>
                                            
                                            <!-- Close Button -->
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" 
                                                    onclick="window.location.href='<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements'">
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal Body with Tabs -->
                                    <div class="modal-body p-0">
                                        <div class="row g-0">
                                            <!-- Main Content Area (75%) -->
                                            <div class="col-lg-9 p-4">
                                                <!-- Tab Navigation -->
                                                <ul class="nav nav-tabs mb-4" id="announcementTabs" role="tablist">
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link active" id="content-tab" data-bs-toggle="tab" 
                                                                data-bs-target="#content" type="button" role="tab">
                                                            <i class="fas fa-file-alt me-1"></i> Content
                                                        </button>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link" id="details-tab" data-bs-toggle="tab" 
                                                                data-bs-target="#details" type="button" role="tab">
                                                            <i class="fas fa-info-circle me-1"></i> Details
                                                        </button>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link" id="stats-tab" data-bs-toggle="tab" 
                                                                data-bs-target="#stats" type="button" role="tab">
                                                            <i class="fas fa-chart-bar me-1"></i> Statistics
                                                        </button>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link" id="related-tab" data-bs-toggle="tab" 
                                                                data-bs-target="#related" type="button" role="tab">
                                                            <i class="fas fa-link me-1"></i> Related (<?= count($related) ?>)
                                                        </button>
                                                    </li>
                                                </ul>
                                                
                                                <!-- Tab Content -->
                                                <div class="tab-content" id="announcementTabContent">
                                                    <!-- Content Tab -->
                                                    <div class="tab-pane fade show active" id="content" role="tabpanel">
                                                        <!-- Content Actions Bar -->
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-secondary" onclick="increaseFontSize()" title="Increase Font Size">
                                                                    <i class="fas fa-search-plus"></i>
                                                                </button>
                                                                <button class="btn btn-outline-secondary" onclick="decreaseFontSize()" title="Decrease Font Size">
                                                                    <i class="fas fa-search-minus"></i>
                                                                </button>
                                                                <button class="btn btn-outline-secondary" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                                                                    <i class="fas fa-moon"></i>
                                                                </button>
                                                                <button class="btn btn-outline-secondary" onclick="copyToClipboard()" title="Copy Content">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </div>
                                                            <div class="text-muted small">
                                                                Font size: <span id="fontSizeIndicator">100%</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Announcement Content -->
                                                        <div class="announcement-content mt-3" id="contentArea">
                                                            <?= $parser->parse_message($current['message'], $parser_options) ?>
                                                        </div>
                                                        
                                                        <!-- Content Meta -->
                                                        <div class="mt-4 pt-3 border-top">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="d-flex flex-wrap gap-2">
                                                                        <span class="badge bg-light text-dark">
                                                                            <i class="fas fa-font me-1"></i>
                                                                            <?= number_format(str_word_count(strip_tags($current['message']))) ?> words
                                                                        </span>
                                                                        <span class="badge bg-light text-dark">
                                                                            <i class="fas fa-ruler me-1"></i>
                                                                            <?= number_format(strlen($current['message'])) ?> chars
                                                                        </span>
                                                                        <span class="badge bg-light text-dark">
                                                                            <i class="fas fa-clock me-1"></i>
                                                                            Reading time: <?= calculateReadingTime($current['message']) ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6 text-end">
                                                                    <button class="btn btn-sm btn-outline-success" onclick="shareAnnouncement()">
                                                                        <i class="fas fa-share-alt me-1"></i> Share
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Details Tab -->
                                                    <div class="tab-pane fade" id="details" role="tabpanel">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="card mb-3">
                                                                    <div class="card-header bg-light">
                                                                        <h6 class="mb-0">Announcement Details</h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <dl class="row mb-0">
                                                                            <dt class="col-sm-4">ID:</dt>
                                                                            <dd class="col-sm-8">#<?= (int)$current['id'] ?></dd>
                                                                            
                                                                            <dt class="col-sm-4">Created:</dt>
                                                                            <dd class="col-sm-8">
                                                                                <?= my_datee('relative', (int)$current['added']) ?><br>
                                                                                <small class="text-muted">
                                                                                    (<?= mkprettytime(TIMENOW - (int)$current['added']) ?> ago)
                                                                                </small>
                                                                            </dd>
                                                                            
                                                                            <dt class="col-sm-4">Author:</dt>
                                                                            <dd class="col-sm-8"><?= htmlspecialchars($current['by']) ?></dd>
                                                                            
                                                                            <dt class="col-sm-4">Target Group:</dt>
                                                                            <dd class="col-sm-8">
                                                                                <span class="badge bg-info"><?= get_user_class_name((int)$current['minclassread']) ?></span>
                                                                            </dd>
                                                                            
                                                                            <dt class="col-sm-4">Subject:</dt>
                                                                            <dd class="col-sm-8"><?= htmlspecialchars($current['subject']) ?></dd>
                                                                        </dl>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card">
                                                                    <div class="card-header bg-light">
                                                                        <h6 class="mb-0">Technical Info</h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <dl class="row mb-0">
                                                                            <dt class="col-sm-5">BBCode Tags:</dt>
                                                                            <dd class="col-sm-7">
                                                                                <?php
                                                                                $tags = countBBCodeTags($current['message']);
                                                                                echo implode(', ', array_keys($tags));
                                                                                ?>
                                                                            </dd>
                                                                            
                                                                            <dt class="col-sm-5">Has Images:</dt>
                                                                            <dd class="col-sm-7">
                                                                                <?= preg_match('/\[img\]/i', $current['message']) ? 'Yes' : 'No' ?>
                                                                            </dd>
                                                                            
                                                                            <dt class="col-sm-5">Has Links:</dt>
                                                                            <dd class="col-sm-7">
                                                                                <?= preg_match('/\[url\]/i', $current['message']) ? 'Yes' : 'No' ?>
                                                                            </dd>
                                                                            
                                                                            <dt class="col-sm-5">Content Type:</dt>
                                                                            <dd class="col-sm-7">
                                                                                <?= determineContentType($current['message']) ?>
                                                                            </dd>
                                                                            
                                                                            
																			
<dt class="col-sm-5">Last Modified:</dt>
<dd class="col-sm-7">
    <?php
    if (isset($current['updated']) && $current['updated'] != 0) {
        echo my_datee('relative', (int)$current['updated']);
        echo '<br><small class="text-muted">';
        echo mkprettytime(TIMENOW - (int)$current['updated']) . ' ago';
        echo '</small>';
    } else {
        echo '<span class="text-success">Never</span>';
        echo '<br><small class="text-muted">This is the original version</small>';
    }
    ?>
</dd>
																			
																			
																			
																			
																			
																			
                                                                        </dl>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Statistics Tab -->
                                                    <div class="tab-pane fade" id="stats" role="tabpanel">
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <div class="card text-center mb-3">
                                                                    <div class="card-body">
                                                                        <h1 class="display-5 text-primary"><?= $viewCount ?></h1>
                                                                        <p class="card-text">Total Views</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="card text-center mb-3">
                                                                    <div class="card-body">
                                                                        <h1 class="display-5 text-success"><?= $totalCount ?></h1>
                                                                        <p class="card-text">Total Announcements</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="card text-center mb-3">
                                                                    <div class="card-body">
                                                                        <h1 class="display-5 text-info"><?= $currentPosition ?></h1>
                                                                        <p class="card-text">Position in List</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($viewCount > 0): ?>
                                                        <div class="card">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0">View History (Last 7 Days)</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <canvas id="viewChart" height="100"></canvas>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Related Tab -->
                                                    <div class="tab-pane fade" id="related" role="tabpanel">
                                                        <?php if (!empty($related)): ?>
                                                            <div class="list-group">
                                                                <?php foreach ($related as $rel): ?>
                                                                    <a href="?act=announcements&action=see&id=<?= $rel['id'] ?>" 
                                                                       class="list-group-item list-group-item-action">
                                                                        <div class="d-flex w-100 justify-content-between">
                                                                            <h6 class="mb-1"><?= htmlspecialchars($rel['subject']) ?></h6>
                                                                            <small><?= my_datee('relative', (int)$rel['added']) ?></small>
                                                                        </div>
                                                                        <small class="text-muted">ID: #<?= $rel['id'] ?></small>
                                                                    </a>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="alert alert-info">
                                                                <i class="fas fa-info-circle me-2"></i>
                                                                No related announcements found.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Sidebar (25%) -->
                                            <div class="col-lg-3 border-start p-4 bg-light" id="sidebar">
                                                <!-- Quick Actions -->
                                                <div class="mb-4">
                                                    <h6 class="border-bottom pb-2 mb-3">
                                                        <i class="fas fa-bolt me-2"></i> Quick Actions
                                                    </h6>
                                                    <div class="d-grid gap-2">
                                                        <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=edit&id=<?= (int)$current['id'] ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="fas fa-edit me-2"></i> Edit Announcement
                                                        </a>
                                                        <button class="btn btn-danger btn-sm" 
                                                                onclick="openDeleteModal2(<?= (int)$current['id'] ?>, '<?= htmlspecialchars(addslashes($current['subject'])) ?>')">
                                                            <i class="fas fa-trash me-2"></i> Delete
                                                        </button>
                                                        <button class="btn btn-success btn-sm" onclick="duplicateAnnouncement()">
                                                            <i class="fas fa-copy me-2"></i> Duplicate
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Navigation Panel -->
                                                <div class="mb-4">
                                                    <h6 class="border-bottom pb-2 mb-3">
                                                        <i class="fas fa-compass me-2"></i> Navigation
                                                    </h6>
                                                    <div class="d-grid gap-2">
                                                        <?php if ($prev): ?>
                                                            <a href="?act=announcements&action=see&id=<?= $prev['id'] ?>" 
                                                               class="btn btn-outline-primary btn-sm text-start">
                                                                <i class="fas fa-arrow-left me-2"></i>
                                                                <div class="small">Previous</div>
                                                                <div class="text-truncate" style="font-size: 0.8em;">
                                                                    <?= htmlspecialchars(mb_substr($prev['subject'], 0, 25)) ?>
                                                                </div>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary btn-sm text-start disabled">
                                                                <i class="fas fa-arrow-left me-2"></i>
                                                                <div class="small">Previous</div>
                                                                <div>No previous</div>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($next): ?>
                                                            <a href="?act=announcements&action=see&id=<?= $next['id'] ?>" 
                                                               class="btn btn-outline-primary btn-sm text-start">
                                                                <i class="fas fa-arrow-right me-2"></i>
                                                                <div class="small">Next</div>
                                                                <div class="text-truncate" style="font-size: 0.8em;">
                                                                    <?= htmlspecialchars(mb_substr($next['subject'], 0, 25)) ?>
                                                                </div>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary btn-sm text-start disabled">
                                                                <i class="fas fa-arrow-right me-2"></i>
                                                                <div class="small">Next</div>
                                                                <div>No next</div>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="mt-3 text-center">
                                                        <input type="range" class="form-range" min="1" max="<?= $totalCount ?>" 
                                                               value="<?= $currentPosition ?>" id="positionSlider"
                                                               onchange="jumpToPosition(this.value)">
                                                        <small class="text-muted">Drag to navigate</small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Timer -->
                                                <div class="mb-4">
                                                    <h6 class="border-bottom pb-2 mb-3">
                                                        <i class="fas fa-hourglass-half me-2"></i> Auto-close Timer
                                                    </h6>
                                                    <div class="text-center">
                                                        <div class="display-4" id="timer">60</div>
                                                        <small class="text-muted">seconds remaining</small>
                                                        <div class="mt-2">
                                                            <button class="btn btn-sm btn-outline-warning" onclick="resetTimer()">
                                                                <i class="fas fa-redo"></i> Reset
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-info" onclick="toggleTimer()">
                                                                <i class="fas fa-pause"></i> Pause
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Export Options -->
                                                <div>
                                                    <h6 class="border-bottom pb-2 mb-3">
                                                        <i class="fas fa-download me-2"></i> Export
                                                    </h6>
                                                    <div class="d-grid gap-2">
                                                        <button class="btn btn-outline-secondary btn-sm" onclick="exportAsText()">
                                                            <i class="fas fa-file-alt me-2"></i> As Text
                                                        </button>
                                                        <button class="btn btn-outline-secondary btn-sm" onclick="exportAsHTML()">
                                                            <i class="fas fa-code me-2"></i> As HTML
                                                        </button>
                                                        <button class="btn btn-outline-secondary btn-sm" onclick="exportAsPDF()">
                                                            <i class="fas fa-file-pdf me-2"></i> As PDF
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="row mt-4">
                            <div class="col-md-8">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">Announcement Preview</h6>
                                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#announcementModal">
                                                <i class="fas fa-external-link-alt me-1"></i> Open in Modal
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title"><?= htmlspecialchars($current['subject']) ?></h5>
                                                <p class="card-text text-muted">
                                                    <small>
                                                        <i class="fas fa-user me-1"></i> <?= htmlspecialchars($current['by']) ?> • 
                                                        <i class="fas fa-calendar me-1"></i> <?= my_datee('relative', (int)$current['added']) ?>
                                                    </small>
                                                </p>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-danger"><?= $viewCount ?> views</span>
                                            </div>
                                        </div>
                                        
                                        <div class="preview-content border rounded p-3 bg-light mb-3" style="max-height: 200px; overflow-y: auto;">
                                            <?= $parser->parse_message($current['message'], $parser_options) ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-info me-2">
                                                    <?= get_user_class_name((int)$current['minclassread']) ?>
                                                </span>
                                                <span class="badge bg-secondary">
                                                    #<?= (int)$current['id'] ?>
                                                </span>
                                            </div>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#announcementModal">
                                                    <i class="fas fa-eye me-1"></i> Full View
                                                </button>
                                                <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=edit&id=<?= (int)$current['id'] ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Quick Stats</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                Position
                                                <span class="badge bg-primary rounded-pill"><?= $currentPosition ?>/<?= $totalCount ?></span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                Views
                                                <span class="badge bg-success rounded-pill"><?= $viewCount ?></span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                Words
                                                <span class="badge bg-info rounded-pill"><?= number_format(str_word_count(strip_tags($current['message']))) ?></span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                Age
                                                <span class="badge bg-warning rounded-pill"><?= mkprettytime(TIMENOW - (int)$current['added']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card shadow-sm mt-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Keyboard Shortcuts</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <kbd class="d-block text-center">← →</kbd>
                                                <small class="d-block text-center">Navigate</small>
                                            </div>
                                            <div class="col-6">
                                                <kbd class="d-block text-center">F</kbd>
                                                <small class="d-block text-center">Fullscreen</small>
                                            </div>
                                            <div class="col-6">
                                                <kbd class="d-block text-center">C</kbd>
                                                <small class="d-block text-center">Copy</small>
                                            </div>
                                            <div class="col-6">
                                                <kbd class="d-block text-center">ESC</kbd>
                                                <small class="d-block text-center">Close</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include Libraries -->
	
	
	
	
	
	
	
	
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	
	
	
	
	<script>
	// Добавьте эти функции в JavaScript секцию

// Функция для открытия модалки удаления
function openDeleteModal2(id, subject) {
    const modal = document.getElementById('deleteAnnouncementModal');
    if (modal) {
        document.getElementById('deleteAnnouncementTitle').textContent = subject;
        document.getElementById('confirmDeleteBtn').href = 
            '<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=delete&id=' + id + '&sure=yes';
        
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } else {
        // Fallback если модалка не найдена
        if (confirm('Are you sure you want to delete "' + subject + '"?')) {
            window.location.href = '<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=delete&id=' + id + '&sure=yes';
        }
    }
}

// Функция для инициализации модалки удаления (если она существует на странице)
function initDeleteModal() {
    const deleteModalElement = document.getElementById('deleteAnnouncementModal');
    if (deleteModalElement) {
        window.deleteModal = new bootstrap.Modal(deleteModalElement);
    }
}

// Добавьте модалку удаления в HTML (перед закрывающим </body>)
document.addEventListener('DOMContentLoaded', function() {
    // Создаем модалку удаления динамически если ее нет
    if (!document.getElementById('deleteAnnouncementModal')) {
        const deleteModalHTML = `
            <div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content shadow">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Delete Announcement
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">You are about to delete the following announcement:</p>
                            <div class="alert alert-warning fw-bold" id="deleteAnnouncementTitle"></div>
                            <p class="text-danger mb-0"><strong>This action cannot be undone.</strong></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Добавляем модалку в конец body
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = deleteModalHTML;
        document.body.appendChild(tempDiv.firstElementChild);
    }
    
    // Инициализируем модалку
    initDeleteModal();
    
    // Отключаем ленивую загрузку для изображений в модалке
    disableLazyLoading();
});

// Функция для отключения ленивой загрузки изображений
function disableLazyLoading() {
    const images = document.querySelectorAll('img[loading="lazy"]');
    images.forEach(img => {
        img.loading = 'eager';
    });
    
    // Для изображений, которые еще не созданы
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        if (node.tagName === 'IMG' && node.loading === 'lazy') {
                            node.loading = 'eager';
                        }
                        node.querySelectorAll?.('img[loading="lazy"]').forEach(img => {
                            img.loading = 'eager';
                        });
                    }
                });
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

// Обновите вызовы openDeleteModal в HTML
// Вместо:
// onclick="openDeleteModal(<?= (int)$current['id'] ?>, '<?= htmlspecialchars(addslashes($current['subject'])) ?>')"
// Сделайте:
// onclick="return openDeleteModal(<?= (int)$current['id'] ?>, '<?= htmlspecialchars(addslashes($current['subject'])) ?>')"

// Или еще лучше - используйте data-атрибуты
function setupDeleteButtons() {
    document.querySelectorAll('[data-delete-id]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-delete-id');
            const subject = this.getAttribute('data-delete-subject');
            openDeleteModal2(id, subject);
        });
    });
}

// Вызов setupDeleteButtons в DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    setupDeleteButtons();
});
</script>
    
    <script>
        // Global variables
        let fontSize = 100;
        let timerInterval;
        let timerSeconds = 60;
        let timerPaused = false;
        let isFullscreen = false;
        let isSidebarVisible = true;
        
        // Initialize on DOM load
        document.addEventListener('DOMContentLoaded', function() {
            // Open modal automatically
            const modal = new bootstrap.Modal(document.getElementById('announcementModal'));
            modal.show();
            
            // Initialize timer
            startTimer();
            
            // Initialize Chart if needed
            <?php if ($viewCount > 0): ?>
            initializeChart();
            <?php endif; ?>
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (!e.target.matches('input, textarea, select')) {
                switch(e.key) {
                    case 'ArrowLeft':
                        <?php if ($prev): ?>navigateTo(<?= $prev['id'] ?>);<?php endif; ?>
                        break;
                    case 'ArrowRight':
                        <?php if ($next): ?>navigateTo(<?= $next['id'] ?>);<?php endif; ?>
                        break;
                    case 'Escape':
                        if (isFullscreen) toggleFullscreen();
                        else window.location.href = '<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements';
                        break;
                    case 'f': case 'F':
                        if (e.key === 'f' || e.key === 'F') toggleFullscreen();
                        break;
                    case 'c': case 'C':
                        if (e.ctrlKey || e.metaKey) copyToClipboard();
                        break;
                    case 'd': case 'D':
                        toggleDarkMode();
                        break;
                    case 's': case 'S':
                        toggleSidebar();
                        break;
                    case 't': case 'T':
                        toggleTimer();
                        break;
                }
            }
        });
        
        // Timer functions
        function startTimer() {
            clearInterval(timerInterval);
            timerInterval = setInterval(function() {
                if (!timerPaused) {
                    timerSeconds--;
                    document.getElementById('timer').textContent = timerSeconds;
                    
                    if (timerSeconds <= 0) {
                        clearInterval(timerInterval);
                        const modal = bootstrap.Modal.getInstance(document.getElementById('announcementModal'));
                        modal.hide();
                        setTimeout(() => {
                            window.location.href = '<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements';
                        }, 500);
                    }
                }
            }, 1000);
        }
        
        function resetTimer() {
            timerSeconds = 60;
            document.getElementById('timer').textContent = timerSeconds;
            if (timerPaused) toggleTimer();
        }
        
        function toggleTimer() {
            timerPaused = !timerPaused;
            const btn = document.querySelector('[onclick="toggleTimer()"]');
            btn.innerHTML = timerPaused ? 
                '<i class="fas fa-play"></i> Resume' : 
                '<i class="fas fa-pause"></i> Pause';
            btn.classList.toggle('btn-outline-info');
            btn.classList.toggle('btn-outline-success');
        }
        
        // Font size control
        function increaseFontSize() {
            fontSize += 10;
            if (fontSize > 200) fontSize = 200;
            updateFontSize();
        }
        
        function decreaseFontSize() {
            fontSize -= 10;
            if (fontSize < 70) fontSize = 70;
            updateFontSize();
        }
        
        function updateFontSize() {
            const content = document.getElementById('contentArea');
            content.style.fontSize = fontSize + '%';
            document.getElementById('fontSizeIndicator').textContent = fontSize + '%';
        }
        
        // Dark mode toggle
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const btn = document.querySelector('[onclick="toggleDarkMode()"]');
            btn.classList.toggle('btn-outline-secondary');
            btn.classList.toggle('btn-outline-dark');
            btn.innerHTML = document.body.classList.contains('dark-mode') ? 
                '<i class="fas fa-sun"></i>' : 
                '<i class="fas fa-moon"></i>';
        }
        
        // Fullscreen toggle
        function toggleFullscreen() {
            const modal = document.querySelector('.modal-dialog');
            if (!isFullscreen) {
                modal.classList.add('modal-fullscreen');
                document.querySelector('[onclick="toggleFullscreen()"]').innerHTML = '<i class="fas fa-compress"></i>';
            } else {
                modal.classList.remove('modal-fullscreen');
                document.querySelector('[onclick="toggleFullscreen()"]').innerHTML = '<i class="fas fa-expand"></i>';
            }
            isFullscreen = !isFullscreen;
        }
        
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (isSidebarVisible) {
                sidebar.style.display = 'none';
                sidebar.parentElement.classList.remove('col-lg-3');
                sidebar.parentElement.classList.add('col-lg-0');
                document.querySelector('.col-lg-9').classList.remove('col-lg-9');
                document.querySelector('.col-lg-9').classList.add('col-lg-12');
            } else {
                sidebar.style.display = 'block';
                sidebar.parentElement.classList.remove('col-lg-0');
                sidebar.parentElement.classList.add('col-lg-3');
                document.querySelector('.col-lg-12').classList.remove('col-lg-12');
                document.querySelector('.col-lg-12').classList.add('col-lg-9');
            }
            isSidebarVisible = !isSidebarVisible;
        }
        
        // Navigation
        function navigateTo(id) {
            window.location.href = '?act=announcements&action=see&id=' + id;
        }
        
        function jumpToPosition(position) {
            // Need to get ID at position - this would require AJAX
            // For now, just show alert
            alert('Jump to position ' + position + ' - requires AJAX implementation');
        }
		
        
        // Copy to clipboard
        function copyToClipboard() {
            const content = document.getElementById('contentArea').innerText;
            navigator.clipboard.writeText(content).then(() => {
                showNotification('Content copied to clipboard!', 'success');
            });
        }
        
        // Share functionality
        function shareAnnouncement() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= htmlspecialchars($current['subject']) ?>',
                    text: 'Check out this announcement',
                    url: window.location.href,
                });
            } else {
                copyToClipboard();
                showNotification('Link copied to clipboard!', 'info');
            }
        }
        
        // Export functions
        function exportAsText() {
            const content = `Announcement: <?= htmlspecialchars($current['subject']) ?>

Date: <?= my_datee('full', (int)$current['added']) ?>
Author: <?= htmlspecialchars($current['by']) ?>
For: <?= get_user_class_name((int)$current['minclassread']) ?>

<?= strip_tags($current['message']) ?>

Exported on <?= date('Y-m-d H:i:s') ?>`;
            
            downloadFile(content, 'announcement-<?= $current['id'] ?>.txt', 'text/plain');
        }
        
        function exportAsHTML() {
            const content = `<!DOCTYPE html>
<html>
<head>
    <title>Announcement: <?= htmlspecialchars($current['subject']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 20px; }
        .meta { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .content { font-size: 14pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Announcement: <?= htmlspecialchars($current['subject']) ?></h1>
    </div>
    <div class="meta">
        <p><strong>Date:</strong> <?= my_datee('full', (int)$current['added']) ?></p>
        <p><strong>Author:</strong> <?= htmlspecialchars($current['by']) ?></p>
        <p><strong>For:</strong> <?= get_user_class_name((int)$current['minclassread']) ?></p>
    </div>
    <div class="content">
        <?= $parser->parse_message($current['message'], $parser_options) ?>
    </div>
</body>
</html>`;
            
            downloadFile(content, 'announcement-<?= $current['id'] ?>.html', 'text/html');
        }
        
        function exportAsPDF() {
            showNotification('PDF export requires server-side implementation', 'warning');
            // window.print() can be used as a fallback
            window.print();
        }
        
        function downloadFile(content, filename, type) {
            const blob = new Blob([content], { type: type });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        
        // Обновите функцию duplicateAnnouncement
function duplicateAnnouncement() {
    if (confirm('Duplicate this announcement?\n\nA copy will be created with "Copy of" prefix.')) {
        // Показываем индикатор загрузки
        const originalBtn = event.target.closest('button');
        const originalText = originalBtn.innerHTML;
        originalBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Duplicating...';
        originalBtn.disabled = true;
        
        // Отправляем AJAX запрос
        fetch('<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=duplicate&id=<?= $current['id'] ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'do=duplicate'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Показываем уведомление об успехе
                const notification = document.createElement('div');
                notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(notification);
                
                // Перенаправляем на новое объявление через 1.5 секунды
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 1500);
            } else {
                // Показываем ошибку
                alert('Error: ' + data.message);
                originalBtn.innerHTML = originalText;
                originalBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            originalBtn.innerHTML = originalText;
            originalBtn.disabled = false;
        });
    }
}
		
		
        
        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Initialize chart
        <?php if ($viewCount > 0): ?>
        function initializeChart() {
            const ctx = document.getElementById('viewChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Views',
                        data: [12, 19, 8, 15, 22, 13, 18],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        <?php endif; ?>
        
        // Helper functions for PHP
        function calculateReadingTime(text) {
            const words = text.split(/\s+/).length;
            const minutes = Math.ceil(words / 200);
            return minutes + ' min' + (minutes !== 1 ? 's' : '');
        }
    </script>
    
    <style>
        .modal-fullscreen {
            max-width: 95vw !important;
            max-height: 95vh !important;
            width: 95vw !important;
            height: 95vh !important;
            margin: 0 !important;
        }
        
        .bg-gradient-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .dark-mode {
            background-color: #1a1a1a;
            color: #ffffff;
        }
        
        .dark-mode .modal-content {
            background-color: #2d2d2d;
            border-color: #444;
        }
        
        .dark-mode .bg-light {
            background-color: #3d3d3d !important;
        }
        
        .dark-mode .text-dark {
            color: #ffffff !important;
        }
        
        .dark-mode .border {
            border-color: #444 !important;
        }
        
        .nav-controls .btn {
            transition: all 0.2s;
        }
        
        .nav-controls .btn:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .announcement-content img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .preview-content {
            transition: all 0.3s;
        }
        
        .preview-content:hover {
            box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
        }
        
        kbd {
            background: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .list-group-item-action:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
            transition: all 0.2s;
        }
        
        #positionSlider::-webkit-slider-thumb {
            background: #0d6efd;
        }
        
        #positionSlider::-moz-range-thumb {
            background: #0d6efd;
        }
        
        .tab-pane {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .progress {
            overflow: hidden;
        }
        
        .progress-bar {
            transition: width 0.3s ease;
        }
        
        .vr {
            width: 2px;
            background-color: rgba(255,255,255,0.5);
        }
    </style>
    
    <?php
    stdfoot();
}





/**
 * Добавление нового объявления
 */
function handleAddAction(string $do): void
{
    global $db;
    
    if ($do === 'save') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $minclassread = $_POST['minclassread'] ?? '-';
        
        if (empty($subject) || empty($message)) {
            redirect('admin/index.php?act=announcements&action=add', 'Please fill in all required fields');
            return;
        }
        
        $added = TIMENOW;
        $by = htmlspecialchars_uni($_POST['by'] ?? 'System');
        
        // ALWAYS create the array first
        $insert_data = [
            "subject" => $subject,
            "message" => $message,
            "added" => $added
        ];
        
        if ($minclassread === '-') {
            // For all users
            $db->sql_query("UPDATE users SET announce_read = 'no' WHERE enabled = 'yes' AND ustatus = 'confirmed'");
            $insert_data["minclassread"] = 0;
            $insert_data["by"] = $by;
        } else {
            // For specific user group
            $minclassread = (int)$minclassread;
            $db->sql_query("UPDATE users SET announce_read = 'no' WHERE enabled = 'yes' 
                           AND ustatus = 'confirmed' AND usergroup = " . $minclassread);
            $insert_data["minclassread"] = $minclassread;
        }
 
        // Insert the announcement
        $result = $db->insert_query("announcements", $insert_data);
        
        redirect('admin/index.php?act=announcements', 'Announcement has been added successfully');
        return;
    }
    
    renderAnnouncementForm('add');
}

/**
 * Редактирование объявления
 */
function handleEditAction(int $id, string $do): void
{
    global $db;
    
    if ($id <= 0) {
        redirect('admin/index.php?act=announcements', 'Invalid announcement ID');
    }
    
    if ($do === 'save' && empty($prvp)) {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $minclassread = $_POST['minclassread'] ?? '-';
        $by = htmlspecialchars_uni($_POST['by'] ?? '');
        
        if (empty($subject) || empty($message) || empty($by)) {
            redirect("admin/index.php?act=announcements&action=edit&id=$id", 'Please fill in all required fields');
        }
        
        $minclassreadValue = ($minclassread === '-') ? 0 : (int)$minclassread;
        
        $db->sql_query("UPDATE announcements 
                       SET `by` = " . $db->sqlesc($by) . ",
                           subject = " . $db->sqlesc($subject) . ",
                           message = " . $db->sqlesc($message) . ",
                           minclassread = " . $db->sqlesc($minclassreadValue) . ",
						   updated = " . TIMENOW . "
                       WHERE id = " . $db->sqlesc($id));
        
        if (($_POST['reset'] ?? '') === 'yes') {
            if ($minclassread === '-') {
                $query = "UPDATE users SET announce_read = 'no' WHERE enabled = 'yes' AND ustatus = 'confirmed'";
            } else {
                $query = "UPDATE users SET announce_read = 'no' WHERE enabled = 'yes' 
                         AND ustatus = 'confirmed' AND usergroup = " . (int)$minclassread;
            }
            $db->sql_query($query);
        }
        
        redirect('admin/index.php?act=announcements', 'Announcement updated successfully');
    }
    
    $res = $db->sql_query("SELECT * FROM announcements WHERE id = " . $db->sqlesc($id));
    $arr = $db->fetch_array($res);
    
    if (!$arr) {
        redirect('admin/index.php?act=announcements', 'Announcement not found');
    }
    
    renderAnnouncementForm('edit', $arr);
}

/**
 * Удаление объявления
 */
function handleDeleteAction(int $id): void
{
    global $db;

    if ($id <= 0) {
        redirect('admin/index.php?act=announcements', 'Invalid announcement ID');
        exit;
    }

    $sure = $_GET['sure'] ?? '';

    if ($sure !== 'yes') {
        redirect('admin/index.php?act=announcements', 'Deletion cancelled');
        exit;
    }

    $db->sql_query(
        "DELETE FROM announcements WHERE id = " . (int)$id
    );

    redirect('admin/index.php?act=announcements', 'Announcement has been deleted');
    exit;
}


/**
 * Рендеринг формы объявления
 */
function renderAnnouncementForm(string $mode, array $data = []): void
{
    global $smilies, $BASEURL;
    
    $isEdit = ($mode === 'edit');
    $title = $isEdit ? 'Edit Announcement' : 'New Announcement';
    
    stdhead($title . ' ' . B_VERSION);
    ?>
    
    <script>
        const smilies = <?= json_encode($smilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bbcode.css" type="text/css">
    <script src="<?= $BASEURL ?>/scripts/bbcode_tools.js"></script>
    
    <div class="container mt-3">
        <div>
            <div>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><?= $title ?></h4>
                    </div>
                    
                    <div class="card-body">
                        <form method="post" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME']) ?>">
                            <input type="hidden" name="act" value="announcements">
                            <input type="hidden" name="action" value="<?= $mode ?>">
                            <input type="hidden" name="do" value="save">
                            <?php if ($isEdit): ?>
                                <input type="hidden" name="id" value="<?= (int)($data['id'] ?? 0) ?>">
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Creator Name</label>
                                    <input type="text" class="form-control" name="by" 
                                           value="<?= htmlspecialchars($data['by'] ?? '') ?>" 
                                           required maxlength="64">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Minimum User Class</label>
                                    <?= _selectbox_(null, 'minclassread', true, 'any usergroup (all)', $data['minclassread'] ?? '-') ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" name="subject" 
                                       value="<?= htmlspecialchars($data['subject'] ?? '') ?>" 
                                       required maxlength="85" placeholder="Announcement Subject">
                            </div>
                            
                            <?php if ($isEdit): ?>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" name="reset" value="yes" id="resetRead">
                                    <label class="form-check-label" for="resetRead">
                                        Mark as unread for all users
                                    </label>
                                    <small class="form-text text-muted">
                                        Check this to force all users to see this announcement again
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                
                                <!-- BBCode Toolbar -->
                                <div class="mb-2 d-flex flex-wrap gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[b]', '[/b]', 'message')"><strong>B</strong></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[i]', '[/i]', 'message')"><em>I</em></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[u]', '[/u]', 'message')"><u>U</u></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[s]', '[/s]', 'message')">S</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[url]', '[/url]', 'message')">URL</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[img]', '[/img]', 'message')">IMG</button>
                                    
                                    <div class="btn-group position-relative">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle bbcode-color-btn" data-textarea="message">🎨 Color</button>
                                        <div class="color-palette d-none"></div>
                                    </div>
                                    
                                    <div class="btn-group position-relative">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="smileyBtn">😊</button>
                                        <div class="smiley-panel d-none border p-2 bg-white shadow-sm position-absolute" id="smileyPanel" style="z-index:1000;"></div>
                                    </div>
                                    
                                    <!-- Size Picker -->
                                    <div class="btn-group position-relative">
                                        <button type="button" class="btn btn-sm btn-outline-secondary size-picker-btn" 
                                                id="sizeBtn-message" data-textarea="message">Size</button>
                                        <div class="size-menu dropdown-menu p-2" id="sizeMenu-message"></div>
                                    </div>
                                    
                                    <!-- Font Picker -->
                                    <div class="btn-group position-relative">
                                        <button type="button" class="btn btn-sm btn-outline-secondary font-picker-btn" 
                                                id="fontBtn-message" data-textarea="message">Font</button>
                                        <div class="font-menu dropdown-menu p-2 shadow" id="fontMenu-message"></div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[center]', '[/center]', 'message')">Center</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[left]', '[/left]', 'message')">Left</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[right]', '[/right]', 'message')">Right</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[quote]', '[/quote]', 'message')">Quote</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[code]', '[/code]', 'message')">Code</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[list]\\n[*]Item 1\\n[*]Item 2\\n[/list]', '', 'message')">List</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[spoiler]', '[/spoiler]', 'message')">Spoiler</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[video=youtube]', '[/video]', 'message')">YouTube</button>
                                </div>
                                
                                <textarea class="form-control" id="message" name="message" rows="12" 
                                          placeholder="Write your announcement using BBCode..."
                                          required><?= htmlspecialchars($data['message'] ?? '') ?></textarea>
                                <div class="form-text text-end">
                                    <span id="charCount">0</span> / 5000 characters
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements" 
                                   class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Save Announcement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Preview</h6>
                    </div>
                    <div class="card-body">
                        <div id="previewArea" class="p-3 border rounded bg-light">
                            <em>Preview will appear here...</em>
                        </div>
                        <button type="button" class="btn btn-outline-info mt-3" onclick="updatePreview()">
                            <i class="fas fa-sync me-2"></i>Update Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Обработчик подсчета символов
        document.getElementById('message').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });
        
        // Инициализация счетчика символов
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('message');
            document.getElementById('charCount').textContent = textarea.value.length;
        });
        
        // Обновление предпросмотра
        function updatePreview() {
            // Здесь должна быть логика AJAX для предпросмотра с парсингом BBCode
            const message = document.getElementById('message').value;
            const previewArea = document.getElementById('previewArea');
            
            // Простой предпросмотр (можно заменить на AJAX запрос к парсеру)
            if (message.trim() === '') {
                previewArea.innerHTML = '<em>No content to preview</em>';
            } else {
                previewArea.innerHTML = '<div class="text-muted mb-2"><small>Note: This is a basic preview. Final rendering may differ.</small></div>' +
                                      '<div class="preview-content">' + 
                                      message.replace(/\[b\](.*?)\[\/b\]/gi, '<strong>$1</strong>')
                                             .replace(/\[i\](.*?)\[\/i\]/gi, '<em>$1</em>')
                                             .replace(/\[u\](.*?)\[\/u\]/gi, '<u>$1</u>')
                                             .replace(/\n/g, '<br>') + 
                                      '</div>';
            }
        }
    </script>
    
    <?php
    stdfoot();
}