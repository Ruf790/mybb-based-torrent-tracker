<?php
declare(strict_types=1);

$rootpath = './../';
require_once $rootpath . 'global.php';

class SmilieManager
{
    private string $smilieDir;
    private string $smilieDirUrl;
    private $db;
    private $cache;
    private string $baseUrl;
    
    public function __construct($db, $cache, $rootpath)
    {
        global $BASEURL, $pic_base_url;
		
		$this->db = $db;
        $this->cache = $cache;
		$this->smilieDir    = TSDIR . '/' . $pic_base_url . 'smilies';
		$this->smilieDirUrl = $BASEURL . '/' . $pic_base_url . 'smilies';
        $this->baseUrl = $_SERVER['PHP_SELF'] ?? 'index.php';
    }
    
    private function getUrl(string $action = '', array $params = []): string
    {
        $query = ['act' => 'smilies'];
        
        if ($action) {
            $query['action'] = $action;
        }
        
        $query = array_merge($query, $params);
        
        $url = $this->baseUrl . '?' . http_build_query($query);
        
        if (isset($_this_script_)) {
            $url = $_this_script_;
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= http_build_query($query);
        }
        
        return $url;
    }
    
    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
    
    public function run(): void
    {
        $action = $_GET['action'] ?? 'manage_smilies';
        
        switch ($action) {
            case 'add_smilie':
            case 'edit_smilie':
                $this->handleForm($action);
                break;
            case 'delete_smilie':
                $this->handleDelete();
                break;
            case 'update_smilies_order':
                $this->handleUpdateOrder();
                break;
            case 'browse_files':
                $this->handleBrowseFiles();
                break;
            case 'export_json':
                $this->handleExport();
                break;
            case 'import_json':
                $this->handleImport();
                break;
            default:
                $this->handleList();
                break;
        }
    }
    
    private function handleForm(string $action): void
    {
        $sid = (int)($_GET['sid'] ?? 0);
        $edit = $action === 'edit_smilie';
        $error = null;
        
        $data = $edit ? $this->getSmilie($sid) : [
            'stitle' => '',
            'stext' => '',
            'spath' => '',
            'sorder' => $this->getNextOrder()
        ];
        
        if ($edit && empty($data)) {
            $this->redirectWithError('Smilie not found');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'stitle' => trim($_POST['stitle'] ?? ''),
                'stext' => trim($_POST['stext'] ?? ''),
                'spath' => trim($_POST['spath'] ?? ''),
                'sorder' => (int)($_POST['sorder'] ?? 0)
            ];
            
            $validation = $this->validate($data, $edit ? $sid : null);
            
            if ($validation['valid']) {
                $this->save($data, $edit ? $sid : null);
                $msg = $edit ? 'Smilie updated successfully' : 'Smilie added successfully';
                $this->redirectWithSuccess($msg);
            } else {
                $error = $this->alert(implode('<br>', $validation['errors']), 'danger');
            }
        }
        
        $this->renderForm($data, $edit, $error, $sid);
    }
    
    private function getSmilie(int $sid): ?array
    {
        $result = $this->db->sql_query_prepared("SELECT * FROM smilies WHERE sid = ?", [$sid]);
        if ($result && $this->db->num_rows($result) > 0) {
            return $this->db->fetch_array($result);
        }
        return null;
    }
    
    private function getNextOrder(): int
    {
        $result = $this->db->sql_query_prepared("SELECT MAX(sorder) as max_order FROM smilies");
        if ($result) {
            $row = $this->db->fetch_array($result);
            return (int)($row['max_order'] ?? 0) + 10;
        }
        return 10;
    }
    
    private function validate(array $data, ?int $sid = null): array
    {
        $errors = [];
        
        if (empty($data['stitle'])) {
            $errors[] = 'Enter title';
        } elseif (mb_strlen($data['stitle']) > 50) {
            $errors[] = 'Title should not exceed 50 characters';
        }
        
        if (empty($data['stext'])) {
            $errors[] = 'Enter replacement text';
        } elseif (mb_strlen($data['stext']) > 20) {
            $errors[] = 'Replacement text should not exceed 20 characters';
        }
        
        if (empty($data['spath'])) {
            $errors[] = 'Select image file';
        } elseif (!file_exists($this->smilieDir . '/' . $data['spath'])) {
            $errors[] = 'File not found';
        } elseif (!preg_match('/\.(gif|png|jpg|jpeg|webp)$/i', $data['spath'])) {
            $errors[] = 'Only image files are allowed (gif, png, jpg, webp)';
        }
        
        if ($data['sorder'] < 0) {
            $errors[] = 'Order cannot be negative';
        }
        
        if (!empty($data['stext'])) {
            if ($sid !== null) {
                $res = $this->db->sql_query_prepared(
                    "SELECT sid FROM smilies WHERE stext = ? AND sid != ?",
                    [$data['stext'], $sid]
                );
            } else {
                $res = $this->db->sql_query_prepared(
                    "SELECT sid FROM smilies WHERE stext = ?",
                    [$data['stext']]
                );
            }
            if ($res && $this->db->num_rows($res) > 0) {
                $errors[] = 'This replacement text is already used';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function save(array $data, ?int $sid = null): void
    {
        if ($sid) {
            $this->db->sql_query_prepared(
                "UPDATE smilies SET stitle = ?, stext = ?, spath = ?, sorder = ? WHERE sid = ?",
                [$data['stitle'], $data['stext'], $data['spath'], $data['sorder'], $sid]
            );
        } else {
            $this->db->sql_query_prepared(
                "INSERT INTO smilies (stitle, stext, spath, sorder) VALUES (?, ?, ?, ?)",
                [$data['stitle'], $data['stext'], $data['spath'], $data['sorder']]
            );
        }
        
        $this->cache->update_smilies();
    }
    
    private function alert(string $text, string $type = 'success'): string
    {
        $icon = match($type) {
            'danger' => 'exclamation-triangle',
            'warning' => 'exclamation-circle',
            default => 'check-circle'
        };
        
        return <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show mt-3" role="alert">
            <i class="fas fa-{$icon} me-2"></i>
            {$text}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        HTML;
    }
    
    private function renderForm(array $data, bool $edit, ?string $error, int $sid = 0): void
    {
        stdhead($edit ? 'Edit Smilie' : 'Add Smilie');
        
        $formAction = $edit 
            ? $this->getUrl('edit_smilie', ['sid' => $sid])
            : $this->getUrl('add_smilie');
        
        $cancelUrl = $this->getUrl();
        $previewSrc = $data['spath'] ? $this->smilieDirUrl . '/' . $data['spath'] : $this->smilieDirUrl . '/blank.png';
        ?>
        
        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-6">
                    
                    <?= $error ?>
                    
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-smile me-2"></i>
                                <?= $edit ? 'Edit' : 'Add' ?> Smilie
                            </h5>
                            <a href="<?= $cancelUrl ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                        
                        <div class="card-body">
                            <form method="post" id="smilieForm">
                                <div class="row g-3">
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-heading text-muted me-1"></i>
                                            Title <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               name="stitle" 
                                               class="form-control" 
                                               value="<?= htmlspecialchars($data['stitle']) ?>" 
                                               required
                                               maxlength="50"
                                               placeholder="Example: Smile">
                                        <div class="form-text small">Displayed in the list</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-font text-muted me-1"></i>
                                            Replacement Text <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               name="stext" 
                                               class="form-control" 
                                               value="<?= htmlspecialchars($data['stext']) ?>" 
                                               required
                                               maxlength="20"
                                               placeholder="Example: :)">
                                        <div class="form-text small">What users type</div>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label class="form-label">
                                            <i class="fas fa-image text-muted me-1"></i>
                                            Image File <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="text" 
                                                   name="spath" 
                                                   id="spath" 
                                                   class="form-control" 
                                                   value="<?= htmlspecialchars($data['spath']) ?>" 
                                                   required
                                                   placeholder="smile.gif">
                                            <button type="button" 
                                                    class="btn btn-outline-secondary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#fileBrowser">
                                                <i class="fas fa-folder-open"></i>
                                            </button>
                                        </div>
                                        <div class="form-text small">
                                            Files in <?= basename($this->smilieDir) ?>/
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">
                                            <i class="fas fa-eye text-muted me-1"></i>
                                            Preview
                                        </label>
                                        <div class="border rounded p-2 text-center bg-light">
                                            <img src="<?= $previewSrc ?>" 
                                                 alt="Preview" 
                                                 id="preview" 
                                                 class="img-fluid" 
                                                 style="max-height: 60px;">
                                            <div class="small text-muted mt-1" id="previewText">
                                                <?= $data['stitle'] ?: 'No title' ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-sort-numeric-down text-muted me-1"></i>
                                            Display Order
                                        </label>
                                        <div class="input-group">
                                            <input type="number" 
                                                   name="sorder" 
                                                   class="form-control" 
                                                   value="<?= $data['sorder'] ?>" 
                                                   min="0" 
                                                   step="10">
                                            <button type="button" 
                                                    class="btn btn-outline-secondary" 
                                                    onclick="this.form.sorder.value = <?= $this->getNextOrder() ?>">
                                                Auto
                                            </button>
                                        </div>
                                        <div class="form-text small">Lower numbers appear first</div>
                                    </div>
                                    
                                    <?php if ($data['spath'] && file_exists($this->smilieDir . '/' . $data['spath'])): ?>
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-info-circle text-muted me-1"></i>
                                            Information
                                        </label>
                                        <div class="small text-muted">
                                            <?php
                                            $path = $this->smilieDir . '/' . $data['spath'];
                                            $size = filesize($path);
                                            $dimensions = getimagesize($path);
                                            ?>
                                            Size: <?= $this->formatBytes($size) ?><br>
                                            Resolution: <?= $dimensions[0] ?? 0 ?>×<?= $dimensions[1] ?? 0 ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                </div>
                                
                                <div class="mt-4 pt-3 border-top">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <button type="submit" class="btn btn-success px-4">
                                                <i class="fas fa-save me-2"></i>
                                                Save
                                            </button>
                                            <a href="<?= $cancelUrl ?>" class="btn btn-secondary">
                                                Cancel
                                            </a>
                                        </div>
                                        
                                        <?php if ($edit): ?>
                                        <div>
                                            <button type="button" 
                                                    class="btn btn-outline-danger"
                                                    onclick="if(confirm('Delete this smilie?')) {
                                                        window.location='<?= $this->getUrl('delete_smilie', ['sid' => $sid]) ?>';
                                                    }">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <script>
        function updatePreview() {
            const path = document.getElementById('spath').value;
            const title = document.getElementsByName('stitle')[0].value;
            const preview = document.getElementById('preview');
            const previewText = document.getElementById('previewText');
            
            preview.src = '<?= $this->smilieDirUrl ?>/' + (path || 'blank.png');
            previewText.textContent = title || 'No title';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('spath').addEventListener('input', updatePreview);
            document.getElementsByName('stitle')[0].addEventListener('input', updatePreview);
        });
        </script>
        
        <?php
        stdfoot();
    }
    
    private function handleDelete(): void
    {
        if (!is_valid_id($_GET['sid'] ?? 0)) {
            $this->redirectWithError('Invalid ID');
            return;
        }
        
        $sid = (int)$_GET['sid'];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            stdhead('Confirmation');
            ?>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Confirmation</h5>
                            </div>
                            <div class="card-body text-center">
                                <p class="lead">Are you sure you want to delete this smilie?</p>
                                <p>This action cannot be undone.</p>
                                
                                <form method="post" action="<?= $this->getUrl('delete_smilie', ['sid' => $sid]) ?>">
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-danger px-4">
                                            <i class="fas fa-trash me-2"></i>Yes, Delete
                                        </button>
                                        <a href="<?= $this->getUrl() ?>" class="btn btn-secondary">
                                            Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            stdfoot();
            exit;
        }
        
        $this->db->sql_query_prepared("DELETE FROM smilies WHERE sid = ?", [$sid]);
        $this->cache->update_smilies();
        $this->redirectWithSuccess('Smilie deleted successfully');
    }
    
    private function handleUpdateOrder(): void
    {
        if (empty($_POST['sorder'])) {
            $this->redirectWithError('No data to update');
            return;
        }
        
        foreach ($_POST['sorder'] as $sid => $order) {
            if (is_valid_id($sid)) {
                $this->db->sql_query_prepared(
                    "UPDATE smilies SET sorder = ? WHERE sid = ?",
                    [(int)$order, (int)$sid]
                );
            }
        }
        
        $this->cache->update_smilies();
        $this->redirectWithSuccess('Display order updated successfully');
    }
    
    private function handleBrowseFiles(): void
    {
        $files = glob($this->smilieDir . '/*.{gif,png,jpg,jpeg,webp}', GLOB_BRACE);
        $html = '';
        
        foreach ($files as $file) {
            $filename = basename($file);
            $html .= '
            <div class="col">
                <div class="file-item text-center p-2 border rounded cursor-pointer"
                     onclick="document.getElementById(\'spath\').value = \'' . addslashes($filename) . '\';
                              updatePreview();
                              bootstrap.Modal.getInstance(document.getElementById(\'fileBrowser\')).hide();">
                    <img src="' . $this->smilieDirUrl . '/' . $filename . '" 
                         class="img-fluid mb-1" 
                         style="max-height: 50px;">
                    <div class="small text-truncate" title="' . $filename . '">
                        ' . $filename . '
                    </div>
                </div>
            </div>';
        }
        
        echo $html ?: '<div class="col-12 text-center text-muted">No files found</div>';
        exit;
    }
    
    private function handleExport(): void
    {
        $res = $this->db->sql_query_prepared("SELECT * FROM smilies ORDER BY sorder");
        $smilies = [];
        
        while ($res && ($row = $this->db->fetch_array($res))) {
            $smilies[] = [
                'title' => $row['stitle'],
                'text' => $row['stext'],
                'file' => $row['spath'],
                'order' => (int)$row['sorder']
            ];
        }
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="smilies_' . date('Y-m-d') . '.json"');
        echo json_encode($smilies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    private function handleImport(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
            $json = file_get_contents($_FILES['import_file']['tmp_name']);
            $data = json_decode($json, true);
            
            if ($data) {
                foreach ($data as $smilie) {
                    $this->db->sql_query_prepared(
                        "INSERT INTO smilies (stitle, stext, spath, sorder) VALUES (?, ?, ?, ?)",
                        [
                            $smilie['title'],
                            $smilie['text'],
                            $smilie['file'],
                            (int)($smilie['order'] ?? 0)
                        ]
                    );
                }
                
                $this->cache->update_smilies();
                $this->redirectWithSuccess('Imported ' . count($data) . ' smilies');
            }
        }
        
        stdhead('Import Smilies');
        ?>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-file-import me-2"></i>Import Smilies</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">JSON File</label>
                            <input type="file" name="import_file" class="form-control" accept=".json" required>
                            <div class="form-text">
                                Format: [{"title":"...","text":"...","file":"...","order":0}]
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Import
                        </button>
                        <a href="<?= $this->getUrl() ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
        <?php
        stdfoot();
        exit;
    }
    
    private function handleList(): void
    {
        stdhead('Smilie Management');
        
        if (!empty($_GET['message'])) {
            echo $this->alert(htmlspecialchars($_GET['message']));
        }
        
        $search = $_GET['search'] ?? '';
        $params = [];
        
        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $where = "WHERE stitle LIKE ? OR stext LIKE ?";
            $params = [$like, $like];
        } else {
            $where = "";
        }
        
        $res = $this->db->sql_query_prepared(
            "SELECT * FROM smilies {$where} ORDER BY sorder, stitle",
            $params
        );
        
        $total = $res ? $this->db->num_rows($res) : 0;
        ?>
        
        <div class="container mt-3">
            
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h4 class="mb-2 mb-md-0">
                    <i class="fas fa-smile-beam text-primary me-2"></i>
                    Smilies
                    <span class="badge bg-secondary ms-2"><?= $total ?></span>
                </h4>
                
                <div class="d-flex gap-2 flex-wrap">
                    <form method="get" action="<?= $this->getUrl() ?>" class="d-flex">
                        <div class="input-group">
                            <input type="text" 
                                   name="search" 
                                   class="form-control form-control-sm" 
                                   placeholder="Search..." 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if ($search): ?>
                            <a href="<?= $this->getUrl() ?>" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                    
                    <div class="btn-group">
                        <a href="<?= $this->getUrl('add_smilie') ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus-circle me-1"></i>Add
                        </a>
                    </div>
                </div>
            </div>
            
            <form method="post" action="<?= $this->getUrl('update_smilies_order') ?>">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2 p-3">
                            <?php while ($res && ($s = $this->db->fetch_array($res))): ?>
                            <div class="col">
                                <div class="card h-100 smilie-card border hover-shadow">
                                    <div class="card-body text-center p-2">
                                        <div class="mb-2">
                                            <img src="<?= $this->smilieDirUrl . '/' . $s['spath'] ?>" 
                                                 alt="<?= htmlspecialchars($s['stitle']) ?>"
                                                 class="img-fluid rounded" 
                                                 style="max-height: 50px;">
                                        </div>
                                        
                                        <div class="mb-2">
                                            <h6 class="mb-1 text-truncate" title="<?= htmlspecialchars($s['stitle']) ?>">
                                                <?= htmlspecialchars($s['stitle']) ?>
                                            </h6>
                                            <code class="small text-muted"><?= htmlspecialchars($s['stext']) ?></code>
                                            <div class="small text-muted mt-1">
                                                ID: <?= $s['sid'] ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">
                                                    <i class="fas fa-sort"></i>
                                                </span>
                                                <input type="number" 
                                                       name="sorder[<?= $s['sid'] ?>]"
                                                       class="form-control" 
                                                       value="<?= $s['sorder'] ?>"
                                                       min="0"
                                                       step="10">
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="<?= $this->getUrl('edit_smilie', ['sid' => $s['sid']]) ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= $this->getUrl('delete_smilie', ['sid' => $s['sid']]) ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               title="Delete"
                                               onclick="return confirm('Delete «<?= addslashes($s['stitle']) ?>»?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            
                            <?php if ($total === 0): ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-smile-wink fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No smilies found</h5>
                                    <?php if ($search): ?>
                                    <p>Try changing your search query</p>
                                    <?php else: ?>
                                    <p>Add your first smilie</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                    
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save me-1"></i>Save Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <?php
        stdfoot();
    }
    
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    private function redirectWithSuccess(string $message): void
    {
        $this->redirect($this->getUrl() . '&message=' . urlencode($message));
    }
    
    private function redirectWithError(string $message): void
    {
        $this->redirect($this->getUrl() . '&error=' . urlencode($message));
    }
}

$smilieManager = new SmilieManager($db, $cache, $rootpath);
$smilieManager->run();