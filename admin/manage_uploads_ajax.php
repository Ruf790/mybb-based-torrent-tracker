<?php
// Helper functions at the beginning of the file
function getFileTypeClass($ext) 
{
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $docExts = ['doc', 'docx', 'odt'];
    $pdfExts = ['pdf'];
    $zipExts = ['zip', 'rar', '7z'];
    
    if (in_array($ext, $imageExts)) return 'image';
    if (in_array($ext, $docExts)) return 'doc';
    if (in_array($ext, $pdfExts)) return 'pdf';
    if (in_array($ext, $zipExts)) return 'zip';
    return 'other';
}

function isNewFile($date) 
{
    $uploadTime = strtotime($date);
    return (time() - $uploadTime) < 86400; // 24 hours
}





// ВАЖНО: укажи абсолютный путь к папке загрузок
$storage_path = $_SERVER['DOCUMENT_ROOT'] . '/uploads'; // например: D:/web/uploads

function folderSize($dir) 
{
    $size = 0;
    if (!is_dir($dir)) 
	{
        return 0; // если папка не существует
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) 
	{
        if ($file->isFile()) 
		{
            $size += $file->getSize();
        }
    }
    return $size;
}

// Пример: лимит 5 GB
$total_space = 5 * 1024 * 1024 * 1024;
$used_space = folderSize($storage_path);

$storage_percentage = $total_space > 0
    ? round(($used_space / $total_space) * 100, 2)
    : 0;





?>



<div class="card mb-3">
    <div class="card-body">
        <h6 class="card-title d-flex justify-content-between align-items-center">
            Storage Usage
            <span class="badge bg-secondary"><?= $storage_percentage ?>%</span>
        </h6>
        
        <div class="progress mb-1" style="height: 18px;">
            <div class="progress-bar <?= $storage_percentage > 80 ? 'bg-danger' : ($storage_percentage > 50 ? 'bg-warning' : 'bg-success') ?>" 
                 role="progressbar" 
                 style="width: <?= $storage_percentage ?>%" 
                 aria-valuenow="<?= $storage_percentage ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
            </div>
        </div>
        
        <small class="text-muted">
            Used <strong><?= mksize($used_space) ?></strong> of <strong><?= mksize($total_space) ?></strong> 
            (<?= $storage_percentage ?>%)
        </small>
    </div>
</div>






<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                <th>Preview</th>
                <th>
                    <div class="d-flex flex-column">
                        <span>File Info</span>
                        <input type="text" class="form-control form-control-sm mt-1" placeholder="Search by name" id="nameFilter">
                    </div>
                </th>
                <th>Dimensions</th>
                <th>
                    <div class="d-flex flex-column">
                        <span>Linked To</span>
                        <select class="form-select form-select-sm mt-1" id="typeFilter">
                            <option value="">All types</option>
                            <option value="torrent" <?= $typeFilter === 'torrent' ? 'selected' : '' ?>>Torrents</option>
<option value="news" <?= $typeFilter === 'news' ? 'selected' : '' ?>>News</option>
<option value="comment" <?= $typeFilter === 'comment' ? 'selected' : '' ?>>Comments</option>
<option value="post" <?= $typeFilter === 'post' ? 'selected' : '' ?>>Posts</option>
<option value="message" <?= $typeFilter === 'message' ? 'selected' : '' ?>>Messages</option>
                        </select>
                    </div>
                </th>
                <th>
                    <div class="d-flex flex-column">
                        <span>Uploaded</span>
                        <select class="form-select form-select-sm mt-1" id="dateFilter">
                            <option value="">All dates</option>
                            <option value="today">Today</option>
                            <option value="week">This week</option>
                            <option value="month">This month</option>
                        </select>
                    </div>
                </th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): 
                $dimensions = strpos($file['file_type'], 'image/') === 0 ? 
                    getFileDimensions($file['file_path']) : 'N/A';
                $file_ext = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                $file_type_class = getFileTypeClass($file_ext);
            ?>
            
            <tr data-id="<?= $file['id'] ?>" 
                data-file-type="<?= 
                    $file['comment_id'] ? 'comment' : 
                    ($file['news_id'] ? 'news' : 
                    ($file['torrent_id'] ? 'torrent' : 
                    ($file['post_id'] ? 'post' : '')))
                ?>" 
                data-upload-date="<?= $file['uploaded_at'] ?>">
                <td>
                    <input type="checkbox" class="form-check-input file-checkbox" name="selected_files[]" value="<?= $file['id'] ?>">
                </td>
                 
				 
				 
				 
				 
<td>
<?php
$file_path_on_disk = $_SERVER['DOCUMENT_ROOT'] . parse_url($file['file_url'], PHP_URL_PATH);
$image_exists = strpos($file['file_type'], 'image/') === 0 && is_file($file_path_on_disk);
?>
<?php if (strpos($file['file_type'], 'image/') === 0): ?>
    <?php if ($image_exists): ?>
        <img src="<?= htmlspecialchars($file['file_url']) ?>" 
             class="img-preview"
             alt="<?= htmlspecialchars($file['file_name']) ?>"
             data-bs-toggle="modal" 
             data-bs-target="#previewModal"
             data-img-src="<?= htmlspecialchars($file['file_url']) ?>"
             data-img-name="<?= htmlspecialchars($file['file_name']) ?>">
    <?php else: ?>
       
	   
	   
	   
<div class="d-flex flex-column align-items-center justify-content-center text-muted border rounded 
            bg-light hover-shadow transition-all" 
     style="width: 80px; height: 80px; cursor: default;">
    <i class="bi bi-image-fill fs-3 mb-2 text-secondary opacity-75"></i>
    <div class="small text-center text-truncate px-1 w-100">Not Found</div>
</div>

	   
	   
	   
    <?php endif; ?>
<?php else: ?>
    <div class="d-flex align-items-center justify-content-center" 
         style="width:80px; height:80px;">
        <?php if ($file_ext === 'pdf'): ?>
            <i class="bi bi-file-earmark-pdf file-icon pdf"></i>
        <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
            <i class="bi bi-file-earmark-word file-icon doc"></i>
        <?php elseif (in_array($file_ext, ['zip', 'rar', '7z'])): ?>
            <i class="bi bi-file-earmark-zip file-icon zip"></i>
        <?php else: ?>
            <i class="bi bi-file-earmark file-icon"></i>
        <?php endif; ?>
    </div>
<?php endif; ?>
</td>

				 
				 
				 
				 
				 
				 
				 
				 
				 
                <td>
                    <div class="d-flex align-items-center">
                        <span class="file-type-indicator file-type-<?= $file_type_class ?>"></span>
                        <div>
                            <div class="fw-semibold"><?= cutename($file['file_name'], 27) ?></div>
                            <div class="text-muted small">
                                <i class="bi bi-hdd me-1"></i> <?= mksize($file['file_size']) ?>
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-filetype-<?= $file_ext ?> me-1"></i> <?= htmlspecialchars($file['file_type']) ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($dimensions !== 'N/A'): ?>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-aspect-ratio me-1"></i> <?= $dimensions ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-light text-muted">N/A</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($file['comment_id']): ?>
                        <span class="badge text-success bg-success bg-opacity-10">
                            <i class="fas fa-comment me-1"></i> Comment #<?= $file['comment_id'] ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($file['news_id']): ?>
                        <span class="badge text-warning bg-warning bg-opacity-10">
                            <i class="fas fa-newspaper me-1"></i> News #<?= $file['news_id'] ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($file['torrent_id']): ?>
                        <span class="badge text-info bg-info bg-opacity-10">
                            <i class="fas fa-download me-1"></i> Torrent #<?= $file['torrent_id'] ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($file['post_id']): ?>
                        <span class="badge text-primary bg-primary bg-opacity-10">
                            <i class="fas fa-file-alt me-1"></i> Post #<?= $file['post_id'] ?>
                        </span>
                    <?php endif; ?>
					
					
					<?php if ($file['messages_id']): ?>
    <span class="badge text-primary bg-primary bg-opacity-10">
        <i class="fas fa-envelope-open-text me-1"></i> Message #<?= $file['messages_id'] ?>
    </span>
<?php endif; ?>

                    
					
					
					
					
					<?php if ($file['username']): ?>
    <?php 
    $useravatar = format_avatar($file['avatar'], $file['avatardimensions']);
    $avatarUrl = $useravatar['image']; 
    $profileUrl = $BASEURL .'/'.get_profile_link($file['user_id']); 
    ?>
    <div class="d-flex flex-column align-items-center">
        <a href="<?= $profileUrl ?>" class="mb-1">
            <img src="<?= htmlspecialchars($avatarUrl) ?>" 
                 class="rounded-circle" 
                 width="50" 
                 height="50" 
                 alt="<?= htmlspecialchars($file['username']) ?>">
        </a>

        <a href="<?= $profileUrl ?>" 
           class="small text-muted text-center text-decoration-none">
            <?= format_name($file['username'], $file['usergroup']) ?>
        </a>
    </div>
<?php endif; ?>
					
					
					
					
					
                </td>
                
				
				<td>
    <div class="d-flex flex-column align-items-start">
        <?php if (isNewFile($file['uploaded_at'])): ?>
            <span class="badge bg-danger mb-1">NEW</span>
        <?php endif; ?>
        
        <span class="small text-muted">
            <i class="bi bi-calendar me-1"></i> <?= date('M j, Y', strtotime($file['uploaded_at'])) ?>
        </span>
        <span class="small text-muted">
            <i class="bi bi-clock me-1"></i> <?= date('H:i', strtotime($file['uploaded_at'])) ?>
        </span>
    </div>
</td>
			

			
				
				
               <td>
    <div class="dropdown">
        <button class="btn btn-sm btn-light dropdown-toggle" 
                type="button" 
                id="dropdownMenu<?= $file['id'] ?>" 
                data-bs-toggle="dropdown" 
                aria-expanded="false">
            <i class="bi bi-three-dots-vertical"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenu<?= $file['id'] ?>">
            <li>
                <a class="dropdown-item edit-btn" 
                   href="#" 
                   data-bs-toggle="modal" 
                   data-bs-target="#editModal"
                   data-id="<?= $file['id'] ?>"
                   data-comment-id="<?= $file['comment_id'] ?? '' ?>"
                   data-news-id="<?= $file['news_id'] ?? '' ?>"
                   data-torrent-id="<?= $file['torrent_id'] ?? '' ?>"
                   data-post-id="<?= $file['post_id'] ?? '' ?>"
                   data-user-id="<?= $file['user_id'] ?? '' ?>">
                    <i class="bi bi-pencil me-2"></i> Edit
                </a>
            </li>
            <li>
                <a class="dropdown-item btn-delete" 
                   href="#" 
                   data-id="<?= htmlspecialchars($file['id']) ?>">
                    <i class="bi bi-trash me-2 text-danger"></i> Delete
                </a>
            </li>
            <li>
                <a class="dropdown-item" 
                   href="<?= htmlspecialchars($file['file_url']) ?>" 
                   download="<?= htmlspecialchars($file['file_name']) ?>">
                    <i class="bi bi-download me-2 text-success"></i> Download
                </a>
            </li>
        </ul>
    </div>
</td>

				
				
				
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>



<?php
// Update $this_script2 to include all filters (search + type)
$this_script2 = "index.php?act=manage_uploads"
    . ($search ? "&search=" . urlencode($search) : "")
    . ($typeFilter ? "&type=" . urlencode($typeFilter) : "");
?>

<?php if ($total_pages > 1): ?>
<div class="card-footer d-flex justify-content-between align-items-center">
    <div class="text-muted small">
        <i class="bi bi-list-ol me-1"></i>
        Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_files) ?> of <?= number_format($total_files) ?>
    </div>
    <nav>
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $this_script2 ?>&page=<?= $page - 1 ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $this_script2 ?>&page=<?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $this_script2 ?>&page=<?= $page + 1 ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>














<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeFilter = document.getElementById('typeFilter');

    typeFilter.addEventListener('change', function () {
        const selectedType = this.value;
        const nameFilter = document.getElementById('nameFilter')?.value || '';

        const url = new URL(window.location.href);
        if (selectedType) {
            url.searchParams.set('type', selectedType);
        } else {
            url.searchParams.delete('type');
        }

        if (nameFilter) {
            url.searchParams.set('search', nameFilter);
        } else {
            url.searchParams.delete('search');
        }

        url.searchParams.delete('page'); // сбросить на первую страницу
        window.location.href = url.toString();
    });
});
</script>





<script src="<?php echo $BASEURL; ?>/scripts/sweetalert2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtering function
    function filterTable() {
        const nameFilter = document.getElementById('nameFilter').value.toLowerCase();
        const typeFilter = document.getElementById('typeFilter').value;
        const dateFilter = document.getElementById('dateFilter').value;
        
        document.querySelectorAll('tbody tr').forEach(row => {
            const fileName = row.querySelector('.fw-semibold').textContent.toLowerCase();
            const fileType = row.dataset.fileType || '';
            const fileDate = new Date(row.dataset.uploadDate);
            
            const nameMatch = fileName.includes(nameFilter);
            const typeMatch = !typeFilter || fileType === typeFilter;
            const dateMatch = checkDateFilter(fileDate, dateFilter);
            
            row.style.display = (nameMatch && typeMatch && dateMatch) ? '' : 'none';
        });
    }
    
    function checkDateFilter(date, filter) {
        if (!filter) return true;
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        switch(filter) {
            case 'today': 
                return date >= today;
            case 'week':
                const weekAgo = new Date(today);
                weekAgo.setDate(weekAgo.getDate() - 7);
                return date >= weekAgo;
            case 'month':
                const monthAgo = new Date(today);
                monthAgo.setMonth(monthAgo.getMonth() - 1);
                return date >= monthAgo;
            default: 
                return true;
        }
    }
    
    // Add event listeners
    document.getElementById('nameFilter').addEventListener('input', filterTable);
    document.getElementById('typeFilter').addEventListener('change', filterTable);
    document.getElementById('dateFilter').addEventListener('change', filterTable);
    
    // Bulk actions
    const applyBtn = document.getElementById('applyBulkAction');
    const modal = new bootstrap.Modal(document.getElementById('confirmBulkDeleteModal'));
    const confirmBtn = document.getElementById('confirmBulkDelete');
    const filesCountEl = document.getElementById('filesCount');
    const selectedFilesInput = document.getElementById('selectedFilesInput');
    const checkboxes = document.querySelectorAll('.file-checkbox');
    const selectAll = document.getElementById('selectAll');
    const cancelBtn = document.getElementById('cancelBulkAction');

    function updateSelectedFiles() {
        const selected = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);

        selectedFilesInput.innerHTML = '';
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_files[]';
            input.value = id;
            selectedFilesInput.appendChild(input);
        });

        applyBtn.disabled = selected.length === 0;
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedFiles);
    });

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateSelectedFiles();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = false);
            if (selectAll) selectAll.checked = false;
            updateSelectedFiles();
        });
    }

    applyBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const selected = document.querySelectorAll('.file-checkbox:checked');
        if (selected.length === 0) return;
        filesCountEl.textContent = selected.length;
        modal.show();
    });

    confirmBtn.addEventListener('click', () => {
        const selected = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) return;

        const params = new URLSearchParams();
        params.append('bulk_action', 'delete');
        selected.forEach(id => params.append('selected_files[]', id));

        fetch('manage_uploads.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                data.deleted_ids.forEach(id => {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.style.transition = 'opacity 0.4s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 400);
                    }
                });
                updateSelectedFiles();
            } else {
                alert(data.message || 'Error deleting files');
            }
        })
        .catch(() => alert('Server not responding'))
        .finally(() => modal.hide());
    });

    updateSelectedFiles();
});

document.addEventListener('click', function (e) {
    if (e.target.closest('.btn-delete')) {
        e.preventDefault();
        const btn = e.target.closest('.btn-delete');
        const fileId = btn.dataset.id;

        Swal.fire({
            title: 'Delete file?',
            text: "It will also be removed from comments/news",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('manage_uploads.php?delete=' + fileId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('Deleted!', 'File and references have been removed.', 'success');
                            btn.closest('tr')?.remove();
                        } else {
                            Swal.fire('Error!', data.message || 'Failed to delete file.', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error!', 'Server not responding.', 'error'));
            }
        });
    }
});
</script>