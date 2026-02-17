<?php


require_once INC_PATH . '/functions_multipage.php';

$action = $_GET['action'] ?? '';

if ($action === 'mass_delete' && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    header('Content-Type: application/json');
    
    // 1. CSRF защита
    if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка безопасности: неверный токен']);
        exit;
    }

    // 2. Получаем и проверяем ID
    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Не выбрано ни одного скриншота']);
        exit;
    }

    // 3. Получаем данные о файлах
    $result = $db->sql_query("SELECT id, filename FROM screenshots WHERE id IN (".implode(',', $ids).")");
    $to_delete = [];
    while ($row = $db->fetch_array($result)) {
	$to_delete[$row['id']] = $row['filename'];
    }

    // 4. Удаление файлов и записей
    $deleted = [];
    $errors = [];
    
    foreach ($to_delete as $id => $filename) {
        $file_path = get_upload_path($filename);
        $file_error = null;
        $db_error = null;
        
        // Проверка и удаление файла
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                $file_error = "Ошибка удаления файла: ".error_get_last()['message'];
            }
        } else {
            // Файл не существует, но продолжим удаление записи из БД
            $file_error = "Файл не найден, но запись будет удалена из БД";
        }
        
        // Удаление из БД в любом случае
        $db->sql_query("DELETE FROM screenshots WHERE id = ".intval($id));
        if ($db->affected_rows() === 0) {
            $db_error = "Ошибка удаления из БД";
        }
        
        // Записываем результат
        if ($file_error && $db_error) {
            $errors[$id] = "Ошибка файла и БД";
        } elseif ($file_error) {
            $errors[$id] = $file_error;
            $deleted[] = $id; // Запись удалена, файла не было
        } elseif ($db_error) {
            $errors[$id] = $db_error;
        } else {
            $deleted[] = $id;
        }
    }

    // 5. Формируем ответ
    $response = [
        'status' => empty($errors) ? 'success' : (empty($deleted) ? 'error' : 'partial'),
        'deleted' => $deleted,
        'errors' => $errors,
        'message' => empty($deleted) ? 
        'Не удалось удалить скриншоты' : 
        'Deleted: '.count($deleted).(empty($errors) ? '' : ', with errors: '.count($errors))
    ];
	
	
	// 6. Логируем действие
    write_log("Mass Delete Screens: deleted " . count($deleted) . ", Errors: " . count($errors) . ". ID: " . implode(', ', $ids));


    

    echo json_encode($response);
    exit;
}





// Action handler
$action = $_GET['action'] ?? 'list';

switch ($action) {
 
	case 'add':
		handle_add();
        break;
    case 'edit':
        handle_edit();
        break;
    case 'delete':
        handle_delete();
        break;
    case 'view':
        handle_view();
        break;
    default:
        show_list();
}

// Function to get next screenshot number with step
function get_next_screenshot_number($torrent_id, $db, $step = 3) 
{
    $maxNum = 0;
    $res = $db->sql_query("SELECT filename FROM `screenshots` WHERE torrent_id = '{$torrent_id}'");
    
    while ($row = $db->fetch_array($res)) 
	{
        if (preg_match('/^' . $torrent_id . '_(\d+)\./', $row['filename'], $matches)) 
		{
            $num = (int)$matches[1];
            $maxNum = max($maxNum, $num);
        }
    }
    
    return $maxNum + $step;
}







function show_list() 
{
    global $db, $_this_script_, $mybb, $BASEURL;
    
   
    
    // Get search parameters
    $search = $_GET['search'] ?? '';
    $torrent_id = $_GET['torrent_id'] ?? '';
    
    // Build WHERE conditions
    $where = [];
    if (!empty($search)) {
        $where[] = "(filename LIKE '%" . $db->escape_string($search) . "%')";
    }
    if (!empty($torrent_id)) {
        $where[] = "torrent_id = " . (int)$torrent_id;
    }
    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    
	
	
$res = $db->sql_query("SELECT COUNT(*) AS count FROM screenshots $where_clause");
$row = mysqli_fetch_row($res);
$count = $row[0];

$torrentsperpage = ($CURUSER['torrentsperpage'] <> 0 ? intval($CURUSER['torrentsperpage']) : $ts_perpage);

if(!$torrentsperpage || (int)$torrentsperpage < 1) {
    $torrentsperpage = 20;
}

$perpage = $torrentsperpage;

// Рассчитываем общее количество страниц ДО условия
$pages = ceil($count / $perpage);

if($mybb->input['page'] > 0) 
{
    $page = $mybb->input['page'];
    $start = ($page-1) * $perpage;
    
    // Убираем повторный расчет $pages здесь
    // $pages = ceil($count / $perpage); // УДАЛИТЕ ЭТУ СТРОЧКУ
    
    if($page > $pages || $page <= 0) 
	{
        $start = 0;
        $page = 1;
    }
} 
else 
{
    $start = 0;
    $page = 1;
}


	
	
	
	
	
	
	
	
	
    
    // Get data
    $query = "SELECT * FROM screenshots $where_clause ORDER BY uploaded_at DESC LIMIT $start, $perpage";
    $result = $db->sql_query($query);
    
    stdhead('Screenshot Management');
    
    echo '<div class="container mt-3">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h1 class="mt-4"><i class="fas fa-images me-2"></i>Screenshot Management</h1>';
    
    echo '<div class="d-flex gap-2">';
    // Select All button
    echo '<button type="button" class="btn btn-outline-secondary" id="selectAllBtn">';
    echo '<i class="fas fa-check-square me-2"></i>Select All';
    echo '</button>';
    
    // Delete Selected button
    echo '<button type="button" class="btn btn-danger" id="deleteSelectedBtn" data-bs-toggle="modal" data-bs-target="#massDeleteModal" disabled>';
    echo '<i class="fas fa-trash me-2"></i>Delete Selected';
    echo '</button>';
    
    // Add New button
    echo '<a href="'.$_this_script_.'&action=add" class="btn btn-primary">';
    echo '<i class="fas fa-plus me-2"></i>Add New</a>';
    
    echo '</div>'; // .d-flex
    echo '</div>'; // .d-flex justify-content-between
    
    // Search form
    echo '<div class="card mb-4 shadow-sm">';
    echo '<div class="card-body bg-light">';
    echo '<form method="get" action="index.php" class="row g-3">';
    echo '<input type="hidden" name="act" value="manage_screenshots">';
    
    echo '<div class="col-md-4">';
    echo '<div class="input-group">';
    echo '<span class="input-group-text"><i class="fas fa-search"></i></span>';
    echo '<input type="text" class="form-control" id="search" name="search" placeholder="Search..." value="'.htmlspecialchars($search).'">';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-3">';
    echo '<div class="input-group">';
    echo '<span class="input-group-text"><i class="fas fa-hashtag"></i></span>';
    echo '<input type="number" class="form-control" id="torrent_id" name="torrent_id" placeholder="Torrent ID" value="'.htmlspecialchars($torrent_id).'">';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-3 d-flex align-items-end">';
    echo '<button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter me-1"></i>Filter</button>';
    echo '<a href="?" class="btn btn-outline-secondary"><i class="fas fa-sync me-1"></i>Reset</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
	
	
	// Pagination
	if ($pages > 1) 
    {
      $page_url = $_this_script_;

   
      if (!empty($search)) 
	  {
        $page_url .= '&search=' . urlencode($search);
      }
      if (!empty($torrent_id)) 
	  {
        $page_url .= '&torrent_id=' . (int)$torrent_id;
      }

      $page_url .= '&page={page}';
    
      $multipage = multipage($count, $perpage, $page, $page_url);
      echo $multipage;
    } 
	
	
	
	
    
    // Screenshot gallery
    if ($db->num_rows($result) == 0) {
        echo '<div class="alert alert-info">No screenshots found</div>';
    } else {
        echo '<div id="alertContainer" class="mt-3"></div>';
        echo '<form id="massDeleteForm" method="post" action="'.$_this_script_.'&action=mass_delete">';
        echo '<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">';
        echo '<div class="row g-4">';
        
        while ($row = $db->fetch_array($result)) {
            $image_path = get_image_path($row['filename']);
            
            echo '<div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 screenshot-card">';
            echo '<div class="card h-100 shadow-sm border-0 overflow-hidden">';
            echo '<div class="position-relative">';
            
           
		   
		   echo '<div class="form-check position-absolute top-0 start-0 m-2 z-1">';
echo '<input class="form-check-input screenshot-checkbox" type="checkbox" name="ids[]" value="' . $row['id'] . '" data-img-src="/torrents/screens/' . $row['filename'] . '">';
echo '</div>';

		   
		   
		   
            
            echo '
<a href="#" 
   data-bs-toggle="modal" 
   data-bs-target="#universalImageModal"
   data-img-src="' . htmlspecialchars($image_path) . '"
   data-title="Torrent #' . $row['torrent_id'] . '">
    <img src="' . htmlspecialchars($image_path) . '" 
        class="card-img-top object-fit-cover" style="height: 180px;" alt="Screenshot">
</a>';
            
            echo '<div class="position-absolute top-0 end-0 m-2">';
            echo '<span class="badge bg-dark opacity-75">#'.$row['id'].'</span>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="card-body p-3">';
            echo '<div class="d-flex justify-content-between align-items-start mb-2">';
            echo '<div>';
            echo '<h6 class="mb-0 fw-bold">Torrent #'.$row['torrent_id'].'</h6>';
            echo '<small class="text-muted">'.date('M d, Y H:i', $row['uploaded_at']).'</small>';
            echo '</div>';
            
            echo '<div class="dropdown">';
            echo '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">';
            echo '<i class="fas fa-ellipsis-v"></i>';
            echo '</button>';
            echo '<ul class="dropdown-menu dropdown-menu-end">';
            echo '<li><a class="dropdown-item" href="'.$_this_script_.'&action=edit&id='.$row['id'].'"><i class="fas fa-edit me-2"></i>Edit</a></li>';
            
			
 
			
			echo '<li><a class="dropdown-item text-danger single-delete-btn" href="#" 
      data-id="'.$row['id'].'" 
      data-filename="'.htmlspecialchars($row['filename']).'" 
      data-bs-toggle="modal" 
      data-bs-target="#singleDeleteModal">
      <i class="fas fa-trash me-2"></i>Delete</a></li>';
	  
	  
	  
	  
			
			
			echo '</ul>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</form>';
        
  
    echo '<br>';
    // Pagination
	if ($pages > 1) 
    {
      $page_url = $_this_script_;

   
      if (!empty($search)) 
	  {
        $page_url .= '&search=' . urlencode($search);
      }
      if (!empty($torrent_id)) 
	  {
        $page_url .= '&torrent_id=' . (int)$torrent_id;
      }

      $page_url .= '&page={page}';
    
      $multipage = multipage($count, $perpage, $page, $page_url);
      echo $multipage;
    } 


  


echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" type="text/css" media="screen" />';
echo '<script src="'.$BASEURL.'/scripts/details_modal.js"></script>';
        



// Modals
echo <<<HTML
<!-- Single Delete Modal -->
<div class="modal fade" id="singleDeleteModal" tabindex="-1" aria-labelledby="singleDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="singleDeleteModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <!-- Основная иконка и текст -->
        <div class="d-flex align-items-center mb-3">
          <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
            <i class="fas fa-trash-alt text-danger fs-1"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1" id="singleDeleteTitle">Delete Screenshot?</h5>
            <p class="text-muted mb-0" id="singleDeleteFilename"></p>
          </div>
        </div>

        
		 <!-- Контейнер для превью -->
        <div id="singleDeletePreviewContainer" class="single-preview-container mb-3 text-center">
          <div class="preview-wrapper" style="display: inline-block; max-width: 100%;">
            <img id="singleDeleteImage" src="" alt="Preview" 
                 style="max-width: 100%; max-height: 200px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none;" 
                 onerror="this.style.display='none'; document.getElementById('noImagePreview').style.display='flex';">
          </div>
        </div>
		

        <!-- Детали файла -->
        <div class="file-details bg-light p-3 rounded-3 mb-3">
          <div class="d-flex align-items-center">
            <i class="fas fa-file-image text-primary me-3 fa-2x"></i>
            <div class="overflow-hidden">
              <div class="fw-bold" id="singleDeleteFileName">filename.jpg</div>
              <div class="small text-muted" id="singleDeleteFileInfo">
                <i class="fas fa-spinner fa-spin me-1"></i> Loading...
              </div>
            </div>
          </div>
        </div>

        <!-- Предупреждение -->
        <div class="alert alert-warning mt-2 mb-0">
          <div class="d-flex">
            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
            <div>
              <strong>Warning:</strong> This action cannot be undone!
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmSingleDeleteBtn">
          <i class="fas fa-trash-alt me-1"></i> Yes, Delete
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* Стили для превью */
.single-preview-container {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 15px;
  border: 1px solid #dee2e6;
}

.preview-wrapper {
  position: relative;
  display: inline-block;
}

#singleDeleteImage {
  transition: transform 0.3s ease;
}

#singleDeleteImage:hover {
  transform: scale(1.02);
}

.file-details {
  border-left: 4px solid #0d6efd;
}
</style>

<script>
let deleteId = null;
let deleteImageSrc = null;

document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        
        deleteId = this.dataset.id;
        const filename = this.dataset.filename || 'this screenshot';
        
        // Получаем src изображения из карточки
        const card = this.closest('.screenshot-card');
        const img = card?.querySelector('img');
        deleteImageSrc = img?.src || '';
        
        // Обновляем модалку
        document.getElementById('singleDeleteModalLabel').innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion';
        document.getElementById('singleDeleteTitle').textContent = 'Delete Screenshot?';
        document.getElementById('singleDeleteFilename').innerHTML = '<strong>"' + filename + '"</strong>';
        document.getElementById('singleDeleteFileName').textContent = filename;
        
        // Получаем информацию о файле
        const fileInfoEl = document.getElementById('singleDeleteFileInfo');
        fileInfoEl.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
        
        // Имитация получения информации
        setTimeout(() => {
            const randomSize = Math.floor(Math.random() * 500 + 100);
            const today = new Date();
            const dateStr = today.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            fileInfoEl.innerHTML = '<i class="fas fa-database me-1"></i> ' + randomSize + ' KB • ' + 
                                   '<i class="fas fa-calendar me-1"></i> ' + dateStr;
        }, 500);
        
        // Показываем превью если есть изображение
        const previewContainer = document.getElementById('singleDeletePreviewContainer');
        const previewImg = document.getElementById('singleDeleteImage');
        const noImageDiv = document.getElementById('noImagePreview');
        
        if (deleteImageSrc && deleteImageSrc !== '') {
            previewImg.src = deleteImageSrc;
            previewImg.style.display = 'block';
            noImageDiv.style.display = 'none';
            previewContainer.style.display = 'block';
        } else {
            previewImg.style.display = 'none';
            noImageDiv.style.display = 'flex';
            previewContainer.style.display = 'block';
        }
    });
});

document.getElementById('confirmSingleDeleteBtn').addEventListener('click', function () {
    if (!deleteId) return;

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Deleting...';

    fetch('$_this_script_&action=delete&id=' + deleteId, {
        method: 'POST',
        body: new URLSearchParams({ my_post_key: my_post_key }),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('singleDeleteModal'));
        if (modal) modal.hide();

        if (data.status === 'success') {
            const card = document.querySelector('.screenshot-checkbox[value="' + deleteId + '"]')?.closest('.screenshot-card');
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8)';
                setTimeout(() => card.remove(), 300);
            }
            showAlert2('success', data.message || 'Screenshot deleted successfully.');
        } else {
            showAlert2('danger', data.message || 'Failed to delete screenshot.');
        }

        deleteId = null;
        deleteImageSrc = null;
    })
    .catch(err => {
        console.error(err);
        showAlert2('danger', 'An error occurred while deleting the screenshot.');
        deleteId = null;
        deleteImageSrc = null;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
});

function showAlert2(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;
    alertContainer.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

// Очищаем превью при закрытии модалки
document.getElementById('singleDeleteModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('singleDeleteImage').src = '';
    document.getElementById('singleDeleteFileInfo').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
    document.getElementById('singleDeleteFilename').innerHTML = '';
});
</script>

HTML;

		
		
		
		
		
		
		
require_once INC_PATH . '/modals_images.php';		
		
		
		

echo <<<HTML
<!-- Mass Delete Modal -->
<div class="modal fade" id="massDeleteModal" tabindex="-1" aria-labelledby="massDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="massDeleteModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i> Delete Confirmation
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <!-- Основная иконка и текст -->
        <div class="d-flex align-items-center mb-3">
          <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
            <i class="fas fa-trash-alt text-danger fs-1"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1">Delete <span id="deleteCount" class="text-danger">0</span> Screenshots?</h5>
            <p class="text-muted mb-0">All selected screenshots will be permanently removed.</p>
          </div>
        </div>

        <!-- Контейнер для превью -->
        <div id="massDeletePreview" class="selected-previews-container mb-3" style="display: none;">
          <div class="d-flex align-items-center mb-2">
            <i class="fas fa-images text-primary me-2"></i>
            <span class="fw-medium">Selected screenshots:</span>
          </div>
          <div id="previewList" class="previews-grid">
            <!-- Preview items will be inserted here -->
          </div>
        </div>

        <!-- Предупреждение -->
        <div class="alert alert-warning mt-3 mb-0">
          <div class="d-flex">
            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
            <div>
              <strong>Warning:</strong> This action cannot be undone! All selected screenshots will be permanently deleted.
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="fas fa-trash-alt me-1"></i> Yes, Delete
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* Previews grid styles */
.previews-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 12px;
  max-height: 250px;
  overflow-y: auto;
  padding: 8px;
  background: #f8f9fa;
  border-radius: 12px;
  scrollbar-width: thin;
  scrollbar-color: #dc3545 #f1f1f1;
}

.previews-grid::-webkit-scrollbar {
  width: 6px;
}

.previews-grid::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.previews-grid::-webkit-scrollbar-thumb {
  background: #dc3545;
  border-radius: 10px;
}

.previews-grid::-webkit-scrollbar-thumb:hover {
  background: #b02a37;
}

.preview-item {
  position: relative;
  transition: all 0.2s ease;
}

.preview-item:hover {
  transform: translateY(-2px);
}

.preview-item .preview-image {
  aspect-ratio: 16/9;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  background: #e9ecef;
}

.preview-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.preview-item .preview-filename {
  font-size: 10px;
  color: #6c757d;
  text-align: center;
  margin-top: 4px;
  padding: 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.selected-previews-container {
  background: white;
  border-radius: 12px;
  padding: 12px;
  border: 1px solid #dee2e6;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.screenshot-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const form = document.getElementById('massDeleteForm');
    const alertContainer = document.getElementById('alertContainer');
    const previewContainer = document.getElementById('massDeletePreview');
    const deleteCountEl = document.getElementById('deleteCount');
    const modalEl = document.getElementById('massDeleteModal');
    const previewList = document.getElementById('previewList');

    // Создаем экземпляр модалки
    let massDeleteModal;
    if (modalEl) {
        massDeleteModal = new bootstrap.Modal(modalEl);
    }

    function getSelectedCheckboxes() {
        return Array.from(document.querySelectorAll('.screenshot-checkbox:checked'));
    }

    function renderDeletePreview() {
        if (!previewList || !deleteCountEl || !previewContainer) return;
        
        previewList.innerHTML = '';
        const selected = getSelectedCheckboxes();
        deleteCountEl.textContent = selected.length;

        if (selected.length === 0) {
            previewContainer.style.display = 'none';
            return;
        }

        selected.forEach(cb => {
            let src = cb.dataset.imgSrc;
            if (!src) return;

            const item = document.createElement('div');
            item.className = 'preview-item';
            
            // Создаем изображение с обработчиком ошибок
            const img = new Image();
            img.src = src;
            img.alt = 'Screenshot';
            img.style.cssText = 'width:100%; height:100%; object-fit:cover; display:block;';
            
            // Обработчик ошибки загрузки
            img.onerror = function() {
                this.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'56\' viewBox=\'0 0 100 56\'%3E%3Crect width=\'100\' height=\'56\' fill=\'%23e9ecef\'/%3E%3Ctext x=\'50\' y=\'28\' font-size=\'10\' text-anchor=\'middle\' fill=\'%236c757d\'%3ENo image%3C/text%3E%3C/svg%3E';
            };
            
            const imgDiv = document.createElement('div');
            imgDiv.className = 'preview-image';
            imgDiv.appendChild(img);
            
            item.appendChild(imgDiv);
            previewList.appendChild(item);
        });

        previewContainer.style.display = 'block';
    }

    // Открытие модалки через JS
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (this.hasAttribute('disabled')) {
                // Показываем предупреждение
                Swal.fire({
                    title: 'No Selection',
                    text: 'Please select at least one screenshot to delete.',
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            
            renderDeletePreview();
            if (massDeleteModal) {
                massDeleteModal.show();
            }
        });
    }

    // Обновление состояния кнопки
    function updateDeleteBtnState() {
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        
        if (anyChecked) {
            deleteBtn.removeAttribute('disabled');
            deleteBtn.classList.remove('btn-secondary');
            deleteBtn.classList.add('btn-danger');
        } else {
            deleteBtn.setAttribute('disabled', 'disabled');
            deleteBtn.classList.remove('btn-danger');
            deleteBtn.classList.add('btn-secondary');
        }
    }

    // Checkbox event listeners
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateDeleteBtnState);
    });

    // Select All/Deselect All functionality
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            this.innerHTML = allChecked 
                ? '<i class="fas fa-check-square me-2"></i>Select All'
                : '<i class="fas fa-times-circle me-2"></i>Deselect All';
            updateDeleteBtnState();
        });
    }

    // Confirm delete action
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            const selected = getSelectedCheckboxes();
            const selectedCount = selected.length;

            if (selectedCount === 0) return;

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (massDeleteModal) {
                    massDeleteModal.hide();
                }

                if (data.status === 'success' || data.status === 'partial') {
                    setTimeout(() => location.reload(), 500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'An error occurred while deleting screenshots');
            });
        });
    }

    // Show alert message
    function showAlert(type, message) {
        if (alertContainer) {
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        }
    }

    // Initial state
    updateDeleteBtnState();
});
</script>
HTML;




    
	}
    
    stdfoot();
}














function handle_add() 
{
    global $db, $_this_script_;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') 
	{
        $torrent_id = (int)$_POST['torrent_id'];
        $uploaded_at = time();
        $log_details = [];
        
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) 
		{
            $file_info = pathinfo($_FILES['screenshot']['name']);
            $file_ext = strtolower($file_info['extension']);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_ext)) 
			{
                $count = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $count . '.' . $file_ext;
                $upload_path = get_upload_path($filename);
                
                if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $upload_path)) 
				{
                    // Получаем метаданные
                    $file_size = round(filesize($upload_path)/1024, 2);
                    $image_info = getimagesize($upload_path);
                    $dimensions = $image_info ? $image_info[0].'x'.$image_info[1] : 'unknown';
                    
                    // Добавляем в БД
                    $insert_array = [
                        'torrent_id' => $torrent_id,
                        'filename' => $filename,
                        'uploaded_at' => $uploaded_at
                    ];
                    $db->insert_query("screenshots", $insert_array);
                    
                    // Логируем успешную загрузку
                    write_log(sprintf(
                        "Screenshot uploaded: Torrent #%d | File: %s | Size: %s KB | Dimensions: %s | User: %s | IP: %s",
                        $torrent_id,
                        $filename,
                        $file_size,
                        $dimensions,
                        $CURUSER['username'],
                        $_SERVER['REMOTE_ADDR']
                    ));
                    
                    header('Location: '.$_this_script_.'');
                    exit;
                } 
				else 
				{
                    $error = "File upload failed (move_uploaded_file error)";
                }
            } 
			else 
			{
                $error = "Invalid file format. Allowed: " . implode(', ', $allowed_ext);
            }
        } 
		else 
		{
            $error = "No file uploaded or upload error";
        }
        
        // Логирование ошибок
        write_log(sprintf(
            "[SCREENSHOT UPLOAD ERROR] Torrent #%d | User: %s | IP: %s | Error: %s",
            $torrent_id,
            $CURUSER['username'],
            $_SERVER['REMOTE_ADDR'],
            $error
        ));
    }
    
    
	stdhead('Screenshot Management');
	
	echo '<div class="container mt-3">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h1 class="mt-4"><i class="fas fa-plus-circle me-2"></i>Add Screenshot</h1>';
    echo '<a href="?" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>';
    echo '</div>';
    
    if (isset($error)) 
	{
        echo '<div class="alert alert-danger">'.$error.'</div>';
    }
    
    echo '<div class="card border-0 shadow-sm mb-4">';
    echo '<div class="card-body">';
    echo '<form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>';
    echo '<div class="row g-4 mb-4">';
    echo '<div class="col-lg-6">';
    echo '<div class="form-floating mb-3">';
    echo '<input type="number" class="form-control" id="torrent_id" name="torrent_id" required>';
    echo '<label for="torrent_id">Torrent ID</label>';
    echo '<div class="invalid-feedback">Please provide a torrent ID</div>';
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label for="screenshot" class="form-label">Screenshot File</label>';
    echo '<input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/*" required>';
    echo '<div class="form-text">Max size: 5MB. Allowed formats: JPG, PNG, GIF, WEBP</div>';
    echo '<div class="invalid-feedback">Please select a valid image file</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-lg-6">';
    echo '<div class="border-dashed rounded-3 p-4 text-center bg-light" style="min-height: 250px;">';
    echo '<img id="imagePreview" src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 4 3\'%3E%3C/svg%3E" class="img-fluid h-100 w-100 object-fit-contain" style="display: none;">';
    echo '<div id="placeholderText" class="h-100 d-flex flex-column justify-content-center align-items-center text-muted">';
    echo '<i class="fas fa-cloud-upload-alt fa-3x mb-3 opacity-50"></i>';
    echo '<h5 class="mb-1">Drag & drop or click to upload</h5>';
    echo '<small>Image preview will appear here</small>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="d-flex justify-content-end">';
    echo '<button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Screenshot</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // container
    
    // Image preview script with drag & drop
    echo '<script>
    // File input preview
    const fileInput = document.getElementById("screenshot");
    const preview = document.getElementById("imagePreview");
    const placeholder = document.getElementById("placeholderText");
    
    fileInput.addEventListener("change", function(e) {
        const file = e.target.files[0];
        if (file && file.type.match("image.*")) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = "block";
                placeholder.style.display = "none";
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Drag and drop
    const dropArea = document.querySelector(".border-dashed");
    
    ["dragenter", "dragover", "dragleave", "drop"].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ["dragenter", "dragover"].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });
    
    ["dragleave", "drop"].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropArea.classList.add("border-primary", "bg-white");
    }
    
    function unhighlight() {
        dropArea.classList.remove("border-primary", "bg-white");
    }
    
    dropArea.addEventListener("drop", handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        
        // Trigger change event
        const event = new Event("change");
        fileInput.dispatchEvent(event);
    }
    
    // Form validation
    (function() {
        "use strict";
        const forms = document.querySelectorAll(".needs-validation");
        Array.from(forms).forEach(function(form) {
            form.addEventListener("submit", function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add("was-validated");
            }, false);
        });
    })();
    </script>';
    
    stdfoot();
}








function handle_edit() 
{
    global $db, $CURUSER, $_this_script_;
    
    $id = (int)$_GET['id'];
    $query = "SELECT * FROM screenshots WHERE id = $id";
    $result = $db->sql_query($query);
    $row = $db->fetch_array($result);
    
    if (!$row) 
    {
        echo '<div class="container-fluid px-4">';
        echo '<div class="alert alert-danger mt-4">Screenshot not found</div>';
        echo '<a href="?" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>';
        echo '</div>';
        stdfoot();
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') 
    {
        $torrent_id = (int)$_POST['torrent_id'];
        $filename = $row['filename']; // Keep old filename by default
        $changes = [];
        
        // Check if torrent_id was changed
        if ($row['torrent_id'] != $torrent_id) 
		{
            $changes[] = "Torrent ID changed from {$row['torrent_id']} to $torrent_id";
        }

        // If new file uploaded
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) 
        {
            $file_info = pathinfo($_FILES['screenshot']['name']);
            $file_ext = strtolower($file_info['extension']);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_ext)) 
            {
                // Delete old file
                $old_path = get_upload_path($row['filename']);
                if (file_exists($old_path)) 
                {
                    if (!unlink($old_path)) 
					{
                        $error = 'Could not delete old file';
                    }
                }
                
                // Get next available number with step 3
                $count = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $count . '.' . $file_ext;
                $upload_path = get_upload_path($filename);
                
                if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $upload_path)) 
                {
                    $changes[] = "File changed from {$row['filename']} to $filename";
                    
                    // Get new file info for log
                    $new_size = round(filesize($upload_path)/1024, 2);
                    $image_info = getimagesize($upload_path);
                    $dimensions = $image_info ? "{$image_info[0]}x{$image_info[1]}" : "unknown";
                }
                else 
                {
                    $error = 'File upload error';
                    $filename = $row['filename']; // Revert to old filename on error
                }
            } 
            else 
            {
                $error = 'Invalid file format. Allowed: JPG, PNG, GIF, WEBP';
            }
        }
        
        if (!isset($error)) 
        {
            $query = "UPDATE screenshots SET 
                     torrent_id = $torrent_id,
                     filename = '" . $db->escape_string($filename) . "'
                     WHERE id = $id";
            $db->sql_query($query);
            
            // Log changes if any
            if (!empty($changes)) 
			{
                $log_message = "Screenshot updated: ID: $id | User: {$CURUSER['username']} | " . implode(', ', $changes);
                if (isset($new_size)) 
				{
                    $log_message .= " | New size: {$new_size}KB ({$dimensions})";
                }
                write_log($log_message);
            }
            
            header('Location: '.$_this_script_.'');
            exit;
        }
    }
    
    $image_path = get_image_path($row['filename']);
    
    stdhead('Screenshot Management');
    
    echo '<div class="container mt-3">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h1 class="mt-4"><i class="fas fa-edit me-2"></i>Edit Screenshot</h1>';
    echo '<a href="?" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>';
    echo '</div>';
    
    if (isset($error)) 
    {
        echo '<div class="alert alert-danger">'.$error.'</div>';
    }
    
    echo '<div class="card border-0 shadow-sm mb-4">';
    echo '<div class="card-body">';
    echo '<form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>';
    echo '<div class="row g-4 mb-4">';
    echo '<div class="col-lg-6">';
    echo '<div class="form-floating mb-3">';
    echo '<input type="number" class="form-control" id="torrent_id" name="torrent_id" value="'.htmlspecialchars($row['torrent_id']).'" required>';
    echo '<label for="torrent_id">Torrent ID</label>';
    echo '<div class="invalid-feedback">Please provide a torrent ID</div>';
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label for="screenshot" class="form-label">New Screenshot File</label>';
    echo '<input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/*">';
    echo '<div class="form-text">Leave empty to keep current file: '.htmlspecialchars($row['filename']).'</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-lg-6">';
    echo '<div class="border rounded-3 p-4 text-center bg-light" style="min-height: 250px;">';
    echo '<img id="imagePreview" src="'.$image_path.'" class="img-fluid h-100 w-100 object-fit-contain">';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="d-flex justify-content-end">';
    echo '<button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Image preview script
    echo '<script>
    document.getElementById("screenshot").addEventListener("change", function(e) {
        const file = e.target.files[0];
        if (file && file.type.match("image.*")) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById("imagePreview").src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Form validation
    (function() {
        "use strict";
        const forms = document.querySelectorAll(".needs-validation");
        Array.from(forms).forEach(function(form) {
            form.addEventListener("submit", function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add("was-validated");
            }, false);
        });
    })();
    </script>';
    
    stdfoot();
}

















function handle_view() 
{
    global $db, $_this_script_, $mybb;
    
    $id = (int)$_GET['id'];
    $query = "SELECT * FROM screenshots WHERE id = $id";
    $result = $db->sql_query($query);
    $row = $db->fetch_array($result);
    
    if (!$row) {
        echo '<div class="container-fluid px-4">';
        echo '<div class="alert alert-danger mt-4">Screenshot not found</div>';
        echo '<a href="?" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>';
        echo '</div>';
        stdfoot();
        return;
    }
    
    $image_path = get_image_path($row['filename']);
    
    echo '<div class="container-fluid px-4">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h1 class="mt-4"><i class="fas fa-image me-2"></i>Screenshot Details</h1>';
    echo '<a href="?" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>';
    echo '</div>';
    
    echo '<div class="card border-0 shadow-sm mb-4">';
    echo '<div class="card-body p-0">';
    echo '<div class="row g-0">';
    echo '<div class="col-lg-8 p-4">';
    echo '<img src="'.$image_path.'" class="img-fluid rounded-3" alt="Screenshot">';
    echo '</div>';
    echo '<div class="col-lg-4 bg-light p-4">';
    echo '<div class="mb-4">';
    echo '<h5 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Details</h5>';
    echo '<div class="list-group list-group-flush">';
    echo '<div class="list-group-item d-flex justify-content-between align-items-center px-0">';
    echo '<span class="text-muted">Screenshot ID:</span>';
    echo '<span class="fw-bold">#'.$row['id'].'</span>';
    echo '</div>';
    echo '<div class="list-group-item d-flex justify-content-between align-items-center px-0">';
    echo '<span class="text-muted">Torrent ID:</span>';
    echo '<span class="fw-bold">'.$row['torrent_id'].'</span>';
    echo '</div>';
    echo '<div class="list-group-item d-flex justify-content-between align-items-center px-0">';
    echo '<span class="text-muted">Filename:</span>';
    echo '<span class="fw-bold text-truncate" style="max-width: 150px;">'.$row['filename'].'</span>';
    echo '</div>';
    echo '<div class="list-group-item d-flex justify-content-between align-items-center px-0">';
    echo '<span class="text-muted">Uploaded:</span>';
    echo '<span class="fw-bold">'.date('M d, Y H:i', $row['uploaded_at']).'</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="d-grid gap-2">';
    echo '<a href="'.$_this_script_.'&action=edit&id='.$row['id'].'" class="btn btn-primary"><i class="fas fa-edit me-2"></i>Edit</a>';
    echo '<a href="'.$_this_script_.'&action=delete&id='.$row['id'].'" class="btn btn-danger" onclick="return confirm(\'Delete this screenshot?\')"><i class="fas fa-trash me-2"></i>Delete</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>'; // container
    
    stdfoot();
}



		


function handle_delete() 
{
    global $db, $CURUSER, $_this_script_, $mybb;

    
	
	
	 // 🔐 CSRF проверка (работает и для AJAX, и для обычных запросов)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') 
	{
        if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) 
		{
            // AJAX запрос — возвращаем JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
			{
                echo json_encode(['status' => 'error', 'message' => 'Неверный токен безопасности.']);
                exit;
            }

            // Обычный POST — редирект с сообщением
            header('Location: ' . $_this_script_ . '&error=csrf');
            exit;
        }
    }
	
	
	
	
	
	$id = (int)($_GET['id'] ?? 0);
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    $query = "SELECT filename, torrent_id FROM screenshots WHERE id = $id";
    $result = $db->sql_query($query);
    $screenshot = $db->fetch_array($result);

    if (!$screenshot) {
        $msg = "Screenshot ID $id not found (attempt by {$CURUSER['username']})";
        write_log("[SCREENSHOT] $msg");

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Screenshot not found']);
            exit;
        }

        header('Location: '.$_this_script_);
        exit;
    }

    $log = [];
    $file_path = get_upload_path($screenshot['filename']);

    // Удаление файла
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            $log[] = "File '{$screenshot['filename']}' deleted";
        } else {
            $log[] = "Failed to delete file '{$screenshot['filename']}'";
        }
    } else {
        $log[] = "File '{$screenshot['filename']}' not found";
    }

    // Удаление из базы
    $db->sql_query("DELETE FROM screenshots WHERE id = $id");
    $log[] = "DB record deleted";

    write_log(sprintf(
        "Screenshot deleted: Torrent #%d | %s | Deleted by %s | IP: %s | %s",
        $screenshot['torrent_id'],
        $screenshot['filename'],
        $CURUSER['username'],
        $_SERVER['REMOTE_ADDR'],
        implode(', ', $log)
    ));

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Screenshot deleted',
            'deleted_id' => $id
        ]);
        exit;
    }

    header('Location: '.$_this_script_);
    exit;
}





// Helper functions
function get_upload_path($filename) 
{
    global $BASEURL;
	// Set correct path to your uploads folder
    return TSDIR . '/torrents/screens/' . $filename;
}

function get_image_path($filename) 
{
    global $BASEURL;
	// Return path for displaying images
    return $BASEURL. '/torrents/screens/' . $filename;
}
?>