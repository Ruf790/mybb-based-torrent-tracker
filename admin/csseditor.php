<?php
declare(strict_types=1);




// ===== КОНФИГУРАЦИЯ =====

const ALLOWED_EXTENSIONS = ['css', 'scss', 'less'];
const BASE_DIR = 'D:\\web\\include\\templates\\default\\style';


const ALLOWED_FILES = ['bootstrap.min.css', 'all.min.css', 'bbcode.css'];
const MAX_BACKUPS = 10; // Максимальное количество хранимых бэкапов
const HISTORY_DAYS = 30; // Хранить историю изменений N дней

// ===== КЛАСС ДЛЯ РАБОТЫ С CSS =====
class CssEditor {
    private string $baseDir;
    private string $backupDir;
    private array $allowedFiles;
    
    public function __construct(string $baseDir, array $allowedFiles) {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->backupDir = $this->baseDir . 'backup' . DIRECTORY_SEPARATOR;
        $this->allowedFiles = $allowedFiles;
        
        $this->initBackupDir();
    }
    
    private function initBackupDir(): void {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }
        
        // Создаем подкаталоги для бэкапов и истории
        $subdirs = ['daily', 'history'];
        foreach ($subdirs as $dir) {
            $path = $this->backupDir . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }
    
    public function getFileList(): array {
        return $this->allowedFiles;
    }
    
    public function getFileContent(string $filename): string {
        $this->validateFilename($filename);
        $path = $this->getFullPath($filename);
        
        if (!file_exists($path)) {
            throw new RuntimeException("Файл не найден: {$filename}");
        }
        
        return file_get_contents($path) ?: '';
    }
    
    public function saveFile(string $filename, string $content, bool $makeBackup = true): void {
        $this->validateFilename($filename);
        $path = $this->getFullPath($filename);
        
        if (!is_writable($path)) {
            throw new RuntimeException("Нет прав на запись в файл: {$filename}");
        }
        
        // Валидация CSS
        if (!$this->validateCss($content)) {
            throw new RuntimeException("Некорректный CSS код");
        }
        
        // Создание бэкапа
        if ($makeBackup) {
            $this->createBackup($filename);
        }
        
        // Сохранение файла
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Ошибка сохранения файла");
        }
        
        // Запись в историю
        $this->logHistory($filename, $content);
    }
    
    public function getBackups(string $filename): array {
        $this->validateFilename($filename);
        $backups = [];
        
        $pattern = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $filename . '.*.bak';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            $backups[] = [
                'file' => basename($file),
                'path' => $file,
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => filesize($file)
            ];
        }
        
        // Сортируем по дате (новые сверху)
        usort($backups, fn($a, $b) => filemtime($b['path']) - filemtime($a['path']));
        
        return $backups;
    }
    
    public function cleanupOldBackups(): void {
        $pattern = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . '*.bak';
        $files = glob($pattern);
        
        if (count($files) > MAX_BACKUPS) {
            // Сортируем файлы по дате изменения
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            
            // Удаляем самые старые
            $toDelete = count($files) - MAX_BACKUPS;
            for ($i = 0; $i < $toDelete; $i++) {
                unlink($files[$i]);
            }
        }
    }
	
	
	
	public function getBackupContent(string $backupFilename): string {
    $path = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $backupFilename;
    
    if (!file_exists($path)) {
        throw new RuntimeException("Файл бэкапа не найден");
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Не удалось прочитать файл бэкапа");
    }
    
    return $content;
    }
	




	
    
    private function validateFilename(string $filename): void {
        if (!in_array($filename, $this->allowedFiles, true)) {
            throw new RuntimeException("Доступ к файлу запрещен: {$filename}");
        }
    }
    
    private function getFullPath(string $filename): string {
        return $this->baseDir . $filename;
    }
    
    private function validateCss(string $content): bool {
        // Простая проверка - можно заменить на использование CSS парсера
        return !empty(trim($content));
    }
    
    private function createBackup(string $filename): void {
        $source = $this->getFullPath($filename);
        $backupFile = $this->backupDir . 'daily' . DIRECTORY_SEPARATOR . $filename . '.' . date('Y-m-d_His') . '.bak';
        
        if (!copy($source, $backupFile)) {
            throw new RuntimeException("Не удалось создать бэкап файла");
        }
        
        $this->cleanupOldBackups();
    }
    
    private function logHistory(string $filename, string $content): void {
        $historyFile = $this->backupDir . 'history' . DIRECTORY_SEPARATOR . $filename . '.log';
        $user = $_SESSION['username'] ?? 'anonymous';
        $entry = [
            'date' => date('Y-m-d H:i:s'),
            'user' => $user,
            'size' => strlen($content),
            'hash' => md5($content)
        ];
        
        file_put_contents($historyFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
        $this->cleanupOldHistory();
    }
    
    private function cleanupOldHistory(): void {
        $pattern = $this->backupDir . 'history' . DIRECTORY_SEPARATOR . '*.log';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            $lines = file($file);
            $cutoff = strtotime('-' . HISTORY_DAYS . ' days');
            
            $filtered = array_filter($lines, function($line) use ($cutoff) {
                $data = json_decode(trim($line), true);
                return strtotime($data['date']) >= $cutoff;
            });
            
            file_put_contents($file, implode('', $filtered));
        }
    }
}

// ===== ИНИЦИАЛИЗАЦИЯ =====
$editor = new CssEditor(BASE_DIR, ALLOWED_FILES);
$currentFile = $_GET['file'] ?? ALLOWED_FILES[0];

// ===== AJAX ОБРАБОТЧИКИ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $filename = basename($_POST['file'] ?? '');
        
        switch ($action) {
            case 'save':
                $content = $_POST['content'] ?? '';
                $makeBackup = isset($_POST['backup']) && $_POST['backup'] == '1';
                $editor->saveFile($filename, $content, $makeBackup);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => $makeBackup 
                        ? "Файл сохранен и создан бэкап!" 
                        : "Файл сохранен!"
                ]);
                break;
                
            case 'get_backups':
                $backups = $editor->getBackups($filename);
                echo json_encode([
                    'status' => 'success',
                    'backups' => $backups
                ]);
                break;
				
				
			case 'get_backup_content':
                $backupFile = $_POST['backup_file'] ?? '';
                $content = $editor->getBackupContent($backupFile);
                echo json_encode([
                    'status' => 'success',
                    'content' => $content
                ]);
                break;	
				
                
            case 'restore':
                $backupFile = $_POST['backup_file'] ?? '';
                $this->restoreFromBackup($filename, $backupFile);
                echo json_encode([
                    'status' => 'success',
                    'message' => "Файл восстановлен из бэкапа!"
                ]);
                break;
                
            default:
                throw new RuntimeException("Неизвестное действие");
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ===== ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ =====
try {
    if (!in_array($currentFile, ALLOWED_FILES, true)) {
        $currentFile = ALLOWED_FILES[0];
    }
    
    $currentContent = $editor->getFileContent($currentFile);
    $backups = $editor->getBackups($currentFile);
} catch (Exception $e) {
    die("Ошибка: " . $e->getMessage());
}

// ===== HTML ИНТЕРФЕЙС =====
stdhead();
?>

    <title>Advanced CSS Editor</title>
    
    <!-- CodeMirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
    
   
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
    
   
    
 <style>
    :root {
        --editor-height: 70vh;
        --main-bg: #f8f9fa;
        --card-bg: #ffffff;
        --border-color: #dee2e6;
        --text-color: #212529;
        --primary-color: #0d6efd;
        --hover-bg: #f1f1f1;
    }
    
    .editor-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }
    
    .CodeMirror {
        height: var(--editor-height);
        font-size: 14px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
    }
    
    .CodeMirror-dialog {
        position: absolute;
        left: 0;
        right: 0;
        background: white;
        z-index: 15;
        padding: 5px;
        border-bottom: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    
    .CodeMirror-dialog input {
        border: 1px solid var(--border-color);
        padding: 5px 10px;
        width: 80%;
        outline: none;
    }
    
    .CodeMirror-dialog button {
        border: 1px solid var(--border-color);
        background: var(--primary-color);
        color: white;
        padding: 5px 10px;
        margin-left: 5px;
        cursor: pointer;
    }
    
    /* Стили для превью-контейнера */
    #previewContainer {
        border: 1px solid var(--border-color);
        padding: 15px;
        margin-top: 20px;
        background-color: var(--card-bg);
        display: none;
        border-radius: 4px;
        transition: all 0.3s ease;
    }
    
    .preview-box {
        min-height: 300px;
        padding: 15px;
        background: white;
        border-radius: 4px;
        overflow: auto;
    }
    
    .preview-section {
        margin-bottom: 20px;
        padding-bottom: 15px;
    }
    
    .preview-section:not(:last-child) {
        border-bottom: 1px solid #eee;
    }
    
    .preview-element {
        transition: all 0.3s ease;
        margin: 5px;
    }
    
    /* Примеры стилей для демонстрации */
    .preview-box .btn {
        margin-right: 8px;
        margin-bottom: 8px;
    }
    
    .preview-box .alert {
        margin-bottom: 15px;
    }
    
    .preview-box .card {
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .nav-tabs .nav-link {
        color: var(--text-color);
    }
    
    .nav-tabs .nav-link.active {
        background-color: var(--card-bg);
        border-bottom-color: var(--card-bg);
    }
    
    .backup-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .backup-item:hover {
        background-color: var(--hover-bg);
    }
    
    .template-btn {
        margin-right: 5px;
        margin-bottom: 5px;
        border: 1px solid var(--border-color);
    }
    
    .tab-content {
        background-color: var(--card-bg);
        border-left: 1px solid var(--border-color);
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
        padding: 15px;
    }
	
	
	
    
	.backup-item.active-file {
    background-color: #e7f3ff !important;
    font-weight: bold;
}

.backup-item .badge {
    margin-right: 5px;
    font-size: 0.75rem;
}

/* Анимация мигания */
.animate-active {
    animation: flash 1s ease-in-out;
}

@keyframes flash {
    0%   { background-color: #d4e9ff; }
    50%  { background-color: #bcdfff; }
    100% { background-color: #e7f3ff; }
}

	
	
	

	
	
	
	
</style>

<body>
<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-code me-2"></i>Advanced CSS Editor
            </h5>
            <div class="d-flex align-items-center">
                
				
				
				<form method="get" action="index.php" class="me-3">
    <input type="hidden" name="act" value="csseditor">
    <select name="file" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php foreach (ALLOWED_FILES as $file): ?>
            <option value="<?= htmlspecialchars($file) ?>" <?= $file === $currentFile ? 'selected' : '' ?>>
                <?= htmlspecialchars($file) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>
				
				
				
				
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="livePreviewToggle">
                    <label class="form-check-label" for="livePreviewToggle">Preview</label>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Шаблоны и сниппеты -->
            
			
			<!-- Templates & Snippets Panel -->
            <div class="mb-3">
                <button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="reset">
                    <i class="fas fa-eraser me-1"></i>Reset
                </button>
                <button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="flex-center">
                    <i class="fas fa-align-center me-1"></i>Flex Center
                </button>
                <button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="grid-layout">
                    <i class="fas fa-th me-1"></i>Grid Layout
                </button>
                <button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="media-query">
                    <i class="fas fa-mobile-screen me-1"></i>Media Query
                </button>
                <button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="animation">
                    <i class="fas fa-film me-1"></i>Animation
                </button>
				
				
				
				
				<button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="transition">
    <i class="fas fa-exchange-alt me-1"></i>Transition
</button>
<button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="transform">
    <i class="fas fa-arrows-alt me-1"></i>Transform
</button>
<button class="btn btn-sm btn-outline-secondary template-btn" data-snippet="shadow">
    <i class="fas fa-box me-1"></i>Shadow
</button>
				
				
				
            </div>
			

			
            
            <!-- Основной редактор -->
            <textarea id="codeEditor"><?= htmlspecialchars($currentContent) ?></textarea>
            
            <!-- Панель инструментов -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="d-flex">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" id="backupCheck" checked>
                        <label class="form-check-label" for="backupCheck">
                            <i class="fas fa-copy me-1"></i>BackUp
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="minifyCheck">
                        <label class="form-check-label" for="minifyCheck">
                            <i class="fas fa-compress-alt me-1"></i>Минифицировать
                        </label>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button id="findBtn" class="btn btn-warning me-2" title="Поиск (Ctrl+F)">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <button id="formatBtn" class="btn btn-info me-2" title="Форматировать код">
                        <i class="fas fa-indent me-1"></i>Format
                    </button>
                    <button id="saveBtn" class="btn btn-success me-2" title="Сохранить (Ctrl+S)">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                    <button id="reloadBtn" class="btn btn-primary" title="Перезагрузить">
                        <i class="fas fa-sync-alt me-1"></i>Reload
                    </button>
                </div>
            </div>
            
            <!-- Превью -->
           <div id="previewContainer" class="mt-4">
    <h5><i class="fas fa-eye me-2"></i>Preview Styles</h5>
    <div class="border p-3 mb-3 bg-white rounded">
        <div class="preview-box">
            <div class="preview-section mb-4">
                <h6 class="border-bottom pb-2">Basic Elements</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-primary">Primary Button</button>
                    <button class="btn btn-secondary">Secondary Button</button>
                    <button class="btn btn-success">Success Button</button>
                </div>
                
                <div class="alert alert-warning mb-3">Warning message example</div>
                
                <div class="card mb-3" style="width: 100%">
                    <div class="card-body">
                        <h5 class="card-title">Card Example</h5>
                        <p class="card-text">Sample text inside card to demonstrate styles.</p>
                        <a href="#" class="btn btn-primary">Card Button</a>
                    </div>
                </div>
            </div>
            
            <div class="preview-section">
                <h6 class="border-bottom pb-2">Typography</h6>
                <h1>Heading h1</h1>
                <h2>Heading h2</h2>
                <p>Regular paragraph text with <strong>bold emphasis</strong> and <em>italic text</em>.</p>
                <ul class="mb-3">
                    <li>List item 1</li>
                    <li>List item 2</li>
                </ul>
            </div>
        </div>
    </div>
    <button id="refreshPreview" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-sync-alt me-1"></i>Refresh Preview
    </button>
</div>
			
			
			
			
        </div>
        
        <!-- Вкладки с дополнительной информацией -->
        <div class="card-footer">
            <ul class="nav nav-tabs card-header-tabs" id="editorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="backups-tab" data-bs-toggle="tab" data-bs-target="#backups" type="button">
                        <i class="fas fa-history me-1"></i>BackUps
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button">
                        <i class="fas fa-chart-bar me-1"></i>Statistics
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="help-tab" data-bs-toggle="tab" data-bs-target="#help" type="button">
                        <i class="fas fa-question-circle me-1"></i>Help
                    </button>
                </li>
            </ul>
            
            <div class="tab-content p-3 border-top-0 border" id="editorTabsContent">
                <div class="tab-pane fade show active" id="backups" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">BackUps:</h6>
                        <button id="refreshBackups" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i>Reload
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Size</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            
							<tbody id="backupsList">
    <?php foreach ($backups as $backup): 
        // Извлекаем имя исходного файла (без даты и .bak)
        $fileName = preg_replace('/\.\d{4}-\d{2}-\d{2}.*\.bak$/', '', $backup['file']);
        ?>
        <tr class="backup-item <?= $fileName === $currentFile ? 'active-file' : '' ?>" 
            data-file="<?= htmlspecialchars($backup['file']) ?>">
            <td>
                <span class="badge bg-secondary"><?= htmlspecialchars($fileName) ?></span>
                <?= htmlspecialchars($backup['date']) ?>
            </td>
            <td><?= formatSize($backup['size']) ?></td>
            <td>
                <button class="btn btn-sm btn-outline-primary view-backup" title="Просмотр">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-success restore-backup" title="Восстановить">
                    <i class="fas fa-undo"></i>
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
							
							
							
                        </table>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="stats" role="tabpanel">
    <div class="row">
        <div class="col-md-6">
            <h6>CSS Statistics:</h6>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Selectors
                    <span class="badge bg-primary rounded-pill" id="statsSelectors">0</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Properties
                    <span class="badge bg-primary rounded-pill" id="statsProperties">0</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Media Queries
                    <span class="badge bg-primary rounded-pill" id="statsMediaQueries">0</span>
                </li>
            </ul>
        </div>
        <div class="col-md-6">
            <h6>File Size:</h6>
            <div class="progress mb-3" style="height: 20px;">
                <div class="progress-bar" id="sizeBar" role="progressbar" style="width: 0%"></div>
            </div>
            <div class="d-flex justify-content-between">
                <small>Minified: <span id="minSize">0 KB</span></small>
                <small>Original: <span id="normalSize">0 KB</span></small>
            </div>
        </div>
    </div>
</div>
                
                <div class="tab-pane fade" id="help" role="tabpanel">
    <h6>Keyboard Shortcuts:</h6>
    <ul class="list-unstyled">
        <li><kbd>Ctrl + F</kbd> - Search in code</li>
        <li><kbd>Ctrl + H</kbd> - Replace text</li>
        <li><kbd>Ctrl + S</kbd> - Save file</li>
        <li><kbd>Ctrl + Space</kbd> - Autocomplete</li>
        <li><kbd>Alt + F</kbd> - Format code</li>
    </ul>
    
    <h6 class="mt-3">Templates:</h6>
    <p>Use the buttons above the editor to quickly insert commonly used CSS templates.</p>
</div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для просмотра бэкапа -->
<div class="modal fade" id="backupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Просмотр бэкапа</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea id="backupViewer" class="form-control" style="height: 60vh;"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="restoreFromModal">Восстановить</button>
            </div>
        </div>
    </div>
</div>

<!-- Подключаем необходимые JS библиотеки -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>

<!-- CodeMirror и плагины -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/css-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/brace-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/comment-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.7/beautify-css.min.js"></script>







<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">










<!-- Наш основной JS код -->
<script>
// Инициализация редактора
const editor = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
    lineNumbers: true,
    mode: "text/css",
    theme: "eclipse",
    indentUnit: 4,
    tabSize: 4,
    lineWrapping: true,
    autoCloseBrackets: true,
    matchBrackets: true,
    foldGutter: true,
    gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
    extraKeys: {
        "Ctrl-S": saveFile,
        "Ctrl-F": "findPersistent",
        "Ctrl-H": "replace",
        "Ctrl-G": "findNext",
        "Shift-Ctrl-G": "findPrev",
        "Ctrl-Space": "autocomplete",
        "Alt-F": formatCode,
        "Ctrl-/": "toggleComment"
    },
    hintOptions: {
        completeSingle: false,
        hint: CodeMirror.hint.css
    }
});















// Инициализация модального окна для бэкапов
const backupModal = new bootstrap.Modal(document.getElementById('backupModal'));
const backupViewer = CodeMirror.fromTextArea(document.getElementById('backupViewer'), {
    lineNumbers: true,
    mode: "text/css",
    readOnly: true,
    theme: "eclipse",
});

// Текущий файл
let currentFile = '<?= addslashes($currentFile) ?>';
let currentBackupFile = '';

// Функция для показа уведомлений
function showAlert(type, message, timer = 3000) {
    Swal.fire({
        icon: type,
        title: message,
        timer: timer,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}




// Форматирование кода
function formatCode() {
    const content = editor.getValue();
    const formatted = css_beautify(content, {
        indent_size: 4,
        indent_char: ' ',
        selector_separator_newline: true,
        end_with_newline: true,
        newline_between_rules: true
    });
    editor.setValue(formatted);
    showAlert('success', 'Код отформатирован');
}



// Правильный URL для AJAX (работает и напрямую, и через index.php?act=csseditor)
let ajaxUrl = window.location.pathname + window.location.search;
if (!ajaxUrl.includes('act=csseditor')) {
    ajaxUrl = window.location.pathname; // если открыто напрямую csseditor.php
}


// Сохранение файла
function saveFile() {
    const content = editor.getValue();
    const makeBackup = document.getElementById('backupCheck').checked;
    const minify = document.getElementById('minifyCheck').checked;
    
    let cssToSave = content;
    
    if (minify) {
        // Простая минификация (можно заменить на более продвинутую)
        cssToSave = cssToSave
            .replace(/\/\*[\s\S]*?\*\//g, '') // Удаляем комментарии
            .replace(/\s+/g, ' ') // Удаляем лишние пробелы
            .replace(/\s?([\{\}:;,])\s?/g, '$1') // Удаляем пробелы вокруг специальных символов
            .replace(/;\}/g, '}'); // Удаляем точку с запятой перед закрывающей скобкой
    }
    
    fetch(ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            ajax: '1',
            action: 'save',
            file: currentFile,
            backup: makeBackup ? '1' : '0',
            content: cssToSave
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('success', data.message);
            updateStats();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Ошибка: ' + error.message);
    });
}



// Обновление списка бэкапов
function updateBackupsList() {
    fetch(ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            ajax: '1',
            action: 'get_backups',
            file: currentFile
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const backupsList = document.getElementById('backupsList');
            backupsList.innerHTML = '';
            
            data.backups.forEach(backup => {
                const row = document.createElement('tr');
                row.className = 'backup-item';
                row.dataset.file = backup.file;
                row.innerHTML = `
                    <td>${backup.date}</td>
                    <td>${formatFileSize(backup.size)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-backup" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success restore-backup" title="Восстановить">
                            <i class="fas fa-undo"></i>
                        </button>
                    </td>
                `;
                backupsList.appendChild(row);
            });
            
            // Добавляем обработчики событий для новых кнопок
            addBackupButtonsHandlers();
        }
    });
}







// Восстановление из бэкапа
function restoreFromBackup(backupFile) {
    viewBackup(backupFile);
}

// Просмотр бэкапа в модальном окне
function viewBackup(backupFile) {
    fetch(ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            ajax: '1',
            action: 'get_backup_content',
            backup_file: backupFile,
            file: currentFile
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            backupViewer.setValue(data.content);
            backupModal.show();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Ошибка загрузки бэкапа: ' + error.message);
    });
}

// Обновление статистики
function updateStats() {
    const content = editor.getValue();
    
    // Простой анализ CSS (можно заменить на более точный)
    const selectors = (content.match(/[^{]+\{/g) || []).length;
    const properties = (content.match(/[^:]+:[^;]+;/g) || []).length;
    const mediaQueries = (content.match(/@media[^{]+\{/g) || []).length;
    
    document.getElementById('statsSelectors').textContent = selectors;
    document.getElementById('statsProperties').textContent = properties;
    document.getElementById('statsMediaQueries').textContent = mediaQueries;
    
    // Размер файла
    const normalSize = content.length;
    const minSize = content
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/\s+/g, ' ')
        .replace(/\s?([\{\}:;,])\s?/g, '$1')
        .replace(/;\}/g, '}').length;
    
    document.getElementById('normalSize').textContent = formatFileSize(normalSize);
    document.getElementById('minSize').textContent = formatFileSize(minSize);
    
    const maxSize = Math.max(normalSize, minSize);
    const normalPercent = Math.round((normalSize / maxSize) * 100);
    const minPercent = Math.round((minSize / maxSize) * 100);
    
    document.getElementById('sizeBar').style.width = `${normalPercent}%`;
    document.getElementById('sizeBar').textContent = `${normalPercent}%`;
}

// Форматирование размера файла
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Добавление обработчиков для кнопок бэкапов
function addBackupButtonsHandlers() {
    document.querySelectorAll('.view-backup').forEach(btn => {
        btn.addEventListener('click', function() {
            const backupFile = this.closest('.backup-item').dataset.file;
            viewBackup(backupFile);
        });
    });
    
    document.querySelectorAll('.restore-backup').forEach(btn => {
        btn.addEventListener('click', function() {
            const backupFile = this.closest('.backup-item').dataset.file;
            restoreFromBackup(backupFile);
        });
    });
}

// Обработчики событий
document.getElementById('saveBtn').addEventListener('click', saveFile);
document.getElementById('formatBtn').addEventListener('click', formatCode);
document.getElementById('findBtn').addEventListener('click', () => editor.execCommand('findPersistent'));
document.getElementById('reloadBtn').addEventListener('click', () => location.reload());


// Добавьте этот обработчик в секцию с другими обработчиками событий
document.getElementById('refreshBackups').addEventListener('click', function() {
    updateBackupsList();
    showAlert('success', 'Список бэкапов обновлен');
});


document.addEventListener('DOMContentLoaded', function() {
    updateBackupsList();
});


document.getElementById('restoreFromModal').addEventListener('click', () => {
    editor.setValue(backupViewer.getValue());
    backupModal.hide();
    showAlert('success', 'Содержимое бэкапа загружено в редактор');
});

// Превью стилей

// Обработчик для переключателя предпросмотра
document.getElementById('livePreviewToggle').addEventListener('change', function() {
    const previewContainer = document.getElementById('previewContainer');
    if (this.checked) {
        previewContainer.style.display = 'block';
        updatePreview();
    } else {
        previewContainer.style.display = 'none';
        const oldStyle = document.getElementById('livePreviewStyle');
        if (oldStyle) oldStyle.remove();
    }
});

// Обработчик для кнопки обновления превью
document.getElementById('refreshPreview').addEventListener('click', updatePreview);



// Обработчик изменений для live preview
function livePreviewHandler() {
    if (document.getElementById('livePreviewToggle').checked) {
        updatePreview();
    }
}








function updatePreview() {
    // Удаляем предыдущие стили предпросмотра
    const oldStyle = document.getElementById('livePreviewStyle');
    if (oldStyle) oldStyle.remove();
    
    // Получаем текущий CSS из редактора
    const cssContent = editor.getValue();
    
    // Если CSS не пустой, добавляем его на страницу
    if (cssContent.trim()) {
        try {
            const style = document.createElement('style');
            style.id = 'livePreviewStyle';
            style.textContent = cssContent;
            document.head.appendChild(style);
            
            // Показываем уведомление об успехе
            showAlert('success', 'Стили применены к превью', 2000);
        } catch (e) {
            showAlert('error', 'Ошибка в CSS: ' + e.message, 3000);
        }
    } else {
        showAlert('info', 'Редактор CSS пуст', 2000);
    }
}




const snippets = {
    'reset': `/* Reset margins and paddings */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}`,
    
    'flex-center': `/* Flex centering */
.centered {
    display: flex;
    justify-content: center;
    align-items: center;
}`,
    
    'grid-layout': `/* Basic grid layout */
.grid-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}`,
    
    'media-query': `/* Mobile-first media query */
@media (min-width: 768px) {
    /* Desktop styles here */
}`,
    
    'animation': `/* Simple animation */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.animated {
    animation: fadeIn 0.5s ease-in-out;
}`,
    
    'transition': `/* Smooth transition */
.element {
    transition: all 0.3s ease;
    will-change: transform, opacity;
}`,

    'transform': `/* Transform effects */
.element {
    transform: translateX(10px) rotate(5deg);
    transform-origin: center center;
}`,

    'shadow': `/* Shadow effects */
.shadow-soft {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.shadow-medium {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}`
};

// Вставка сниппетов
document.querySelectorAll('.template-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const snippetKey = this.dataset.snippet;
        
        if (!snippets[snippetKey]) {
            showAlert('error', 'Сниппет не найден', 2000);
            return;
        }
        
        try {
            const doc = editor.getDoc();
            const cursor = doc.getCursor();
            
            // Добавляем два переноса перед сниппетом, если не в начале файла
            const prefix = cursor.ch > 0 || cursor.line > 0 ? '\n\n' : '';
            
            doc.replaceRange(prefix + snippets[snippetKey], cursor);
            showAlert('success', 'Сниппет добавлен', 1500);
            
            // Прокручиваем к месту вставки
            editor.scrollIntoView(cursor);
        } catch (e) {
            showAlert('error', 'Ошибка вставки: ' + e.message, 3000);
        }
    });
});

// Обновляем статистику при загрузке
document.addEventListener('DOMContentLoaded', function() {
    updateStats();
    updateBackupsList();
    addBackupButtonsHandlers();
    
    // Автосохранение каждые 30 секунд (опционально)
    setInterval(() => {
        if (document.getElementById('autoSaveCheck')?.checked) {
            saveFile();
        }
    }, 30000);
});


// Автоматическое обновление при изменении CSS (только когда превью активно)
editor.on('change', function() {
    if (document.getElementById('livePreviewToggle').checked) {
        // Используем задержку, чтобы не обновлять слишком часто
        clearTimeout(window.previewTimeout);
        window.previewTimeout = setTimeout(updatePreview, 500);
    }
});
</script>
</body>
</html>
<?php
stdfoot();

// Вспомогательная функция для форматирования размера
function formatSize(int $bytes): string {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}