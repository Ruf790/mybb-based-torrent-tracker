<?php

declare(strict_types=1);

/**
 * Torrent Double Upload Manager
 * PHP 8.1+ Enhanced Version
 * 
 * @package StaffPanel
 * @version 2.0
 * @author TSSpecial Edition v5.6
 */

if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert">
            <i class="fas fa-ban me-2"></i>
            <strong>Access Denied!</strong> Direct initialization is not allowed.
          </div>');
}

class TorrentDoubleUploadManager
{
    private const ALLOWED_ACTIONS = ['main', 'setalldouble', 'setallnormal'];
    
    private array $currentUser;
    private object $database;
    private string $scriptUrl;
    
    public function __construct()
    {
        global $db;
        $this->database = $db;
        $this->currentUser = $GLOBALS['CURUSER'] ?? [];
        $this->scriptUrl = $_SERVER['PHP_SELF'] . '?act=doubleupload';
    }
    
    /**
     * Main execution method
     */
    public function execute(): void
    {
        try {
            $action = $this->getAction();
            
            match ($action) {
                'setalldouble' => $this->setAllDoubleUpload(),
                'setallnormal' => $this->setAllNormal(),
                default => $this->showMainInterface()
            };
            
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }
	
	
	
	private function jsonSuccess(string $title, string $message): void
{
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'title'  => $title,
        'message'=> $message
    ]);
    exit;
}

private function jsonError(string $message): void
{
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'status' => 'error',
        'message'=> $message
    ]);
    exit;
}

	
	
	
    
    /**
     * Get and validate action
     */
    private function getAction(): string
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'main';
        $action = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        
        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new InvalidArgumentException('Invalid action specified');
        }
        
        return $action;
    }
    
    /**
     * Set all torrents to double upload
     */
   private function setAllDoubleUpload(): void
{
    $this->validateStaffAccess();

    $query = "UPDATE torrents 
              SET doubleupload = 'yes' 
              WHERE doubleupload = 'no'";

    if (!$this->database->sql_query($query)) {
        $this->jsonError('Database error');
    }

    $affectedRows = $this->database->affected_rows();
    $this->logAction('double', $affectedRows);

    $this->jsonSuccess(
        'Double Upload Enabled',
        "Successfully updated {$affectedRows} torrents"
    );
}


private function setAllNormal(): void
{
    $this->validateStaffAccess();

    $query = "UPDATE torrents 
              SET doubleupload = 'no' 
              WHERE doubleupload = 'yes'";

    if (!$this->database->sql_query($query)) {
        $this->jsonError('Database error');
    }

    $affectedRows = $this->database->affected_rows();
    $this->logAction('normal', $affectedRows);

    $this->jsonSuccess(
        'Normal Upload Restored',
        "Successfully updated {$affectedRows} torrents"
    );
}



    
    /**
     * Show main interface
     */
    private function showMainInterface(): void
    {
        stdhead('44444444444');
		
		// Get current statistics
        $stats = $this->getTorrentStats();
        
        // Start output buffering
        ob_start();
        
        ?>
        <!DOCTYPE html>
        <html lang="en" data-bs-theme="dark">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Torrent Upload Mode Manager</title>
           
            <style>
                
                
                .card-hover {
                    transition: all 0.3s ease;
                    cursor: pointer;
                }
                
                .card-hover:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
                }
                
                .stat-card {
                    border-left: 4px solid;
                    border-radius: 0.375rem;
                }
                
                .stat-double {
                    border-left-color: var(--warning-color);
                }
                
                .stat-normal {
                    border-left-color: var(--primary-color);
                }
                
                .btn-action {
                    padding: 1.5rem;
                    font-size: 1.1rem;
                    font-weight: bold;
                }
                
                .action-icon {
                    font-size: 3rem;
                    margin-bottom: 1rem;
                }
                
	



.confirmation-modal .display-1 i {
    animation: iconPulse 1.6s infinite;
}

#successModal .display-4 i {
    animation: successBounce 1.2s ease-out;
}





@keyframes iconPulse {
    0%, 100% {
        transform: scale(1);
        opacity: 0.85;
    }
    50% {
        transform: scale(1.12);
        opacity: 1;
    }
}

@keyframes successBounce {
    0% {
        transform: scale(0.5);
        opacity: 0;
    }
    60% {
        transform: scale(1.15);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

			

				
				
				
             
                
              
            </style>
			

			
			
			
			
			
			
			
			
			
			
			
			
        </head>
        <body>
            <div class="container mt-3">
                <!-- Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-primary text-white shadow-lg">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h1 class="h3 mb-2">
                                            <i class="fas fa-bolt me-2"></i>
                                            Torrent Upload Mode Manager
                                        </h1>
                                        <p class="text-muted mb-0">
                                            Manage double upload status for all torrents
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary fs-6">v2.0</span>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-user-shield me-1"></i>
                                                <?= htmlspecialchars($this->currentUser['username'] ?? 'Admin') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card stat-card stat-normal h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted mb-2">
                                            <i class="fas fa-upload me-2"></i>Normal Upload Torrents
                                        </h6>
                                        <h2 class="mb-0"><?= number_format($stats['normal_count']) ?></h2>
                                        <small class="text-muted">
                                            <?= number_format($stats['normal_percent'], 1) ?>% of total
                                        </small>
                                    </div>
                                    <div class="display-4 text-primary">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card stat-card stat-double h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-muted mb-2">
                                            <i class="fas fa-forward me-2"></i>Double Upload Torrents
                                        </h6>
                                        <h2 class="mb-0"><?= number_format($stats['double_count']) ?></h2>
                                        <small class="text-muted">
                                            <?= number_format($stats['double_percent'], 1) ?>% of total
                                        </small>
                                    </div>
                                    <div class="display-4 text-warning">
                                        <i class="fas fa-tachometer-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Cards -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card card-hover border-warning" 
                             data-bs-toggle="modal" 
                             data-bs-target="#confirmDoubleModal">
                            <div class="card-body text-center py-5">
                                <div class="action-icon text-warning">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <h3 class="card-title mb-3">Enable Double Upload</h3>
                                <p class="card-text text-muted mb-4">
                                    Set ALL torrents to double upload mode. This will affect 
                                    <strong><?= number_format($stats['normal_count']) ?></strong> torrents.
                                </p>
                                <div class="btn btn-warning btn-lg btn-action">
                                    <i class="fas fa-bolt me-2"></i>
                                    Activate Double Upload
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        This action affects multiple torrents
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card card-hover border-primary" 
                             data-bs-toggle="modal" 
                             data-bs-target="#confirmNormalModal">
                            <div class="card-body text-center py-5">
                                <div class="action-icon text-primary">
                                    <i class="fas fa-sync"></i>
                                </div>
                                <h3 class="card-title mb-3">Revert to Normal</h3>
                                <p class="card-text text-muted mb-4">
                                    Set ALL torrents back to normal upload mode. This will affect 
                                    <strong><?= number_format($stats['double_count']) ?></strong> torrents.
                                </p>
                                <div class="btn btn-primary btn-lg btn-action">
                                    <i class="fas fa-undo me-2"></i>
                                    Revert to Normal
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Restores default upload behavior
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Information Panel -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Information & Guidelines
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-lightbulb me-2"></i>How it works:</h6>
                                        <ul class="mb-0">
                                            <li><strong>Double Upload:</strong> Users get 2x upload credit</li>
                                            <li><strong>Normal Upload:</strong> Standard upload credit</li>
                                            <li>Changes apply to all active torrents</li>
                                            <li>System logs all administrator actions</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Important:</h6>
                                        <ul class="mb-0">
                                            <li>These are system-wide changes</li>
                                            <li>Cannot be undone automatically</li>
                                            <li>Affects user ratios and statistics</li>
                                            <li>Use with caution during peak hours</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Double Upload Confirmation Modal -->
            <div class="modal fade confirmation-modal" id="confirmDoubleModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modal-warning">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Confirm Double Upload Activation
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-4">
                                <div class="display-1 text-warning mb-3">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <h4 class="mb-3">Enable Double Upload?</h4>
                                <p class="lead">
                                    This will set <strong><?= number_format($stats['normal_count']) ?> torrents</strong> 
                                    to double upload mode.
                                </p>
                            </div>
                            
                            <div class="alert alert-warning">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-radiation fa-2x"></i>
                                    </div>
                                    <div>
                                        <h6 class="alert-heading mb-1">System-wide Impact</h6>
                                        <p class="mb-0">This action affects ALL torrents and cannot be undone automatically.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <div class="h4 mb-2">Before</div>
                                        <div class="h2 text-primary"><?= number_format($stats['normal_count']) ?></div>
                                        <small class="text-muted">Normal torrents</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <div class="h4 mb-2">After</div>
                                        <div class="h2 text-warning">0</div>
                                        <small class="text-muted">Normal torrents</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                           <form method="post"
      action="<?= $this->scriptUrl ?>"
      class="d-inline action-form"
      data-action="setalldouble">

    <input type="hidden" name="action" value="setalldouble">

    <button type="submit" class="btn btn-warning">
        <i class="fas fa-check me-1"></i> Confirm & Proceed
    </button>
</form>

                        </div>
                    </div>
                </div>
            </div>
			
			
			<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successTitle">Success</h5>
      </div>
      <div class="modal-body text-center">
        <div class="display-4 text-success mb-3">
          <i class="fas fa-check-circle"></i>
        </div>
        <p id="successMessage"></p>
        <small class="text-muted">
          Page will refresh automatically…
        </small>
      </div>
    </div>
  </div>
</div>



            
            <!-- Normal Upload Confirmation Modal -->
            <div class="modal fade confirmation-modal" id="confirmNormalModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modal-info">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-info-circle me-2"></i>
                                Confirm Normal Upload Reversion
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-4">
                                <div class="display-1 text-primary mb-3">
                                    <i class="fas fa-sync"></i>
                                </div>
                                <h4 class="mb-3">Revert to Normal Upload?</h4>
                                <p class="lead">
                                    This will set <strong><?= number_format($stats['double_count']) ?> torrents</strong> 
                                    back to normal upload mode.
                                </p>
                            </div>
                            
                            <div class="alert alert-info">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-history fa-2x"></i>
                                    </div>
                                    <div>
                                        <h6 class="alert-heading mb-1">System Restoration</h6>
                                        <p class="mb-0">This will restore default upload behavior for all torrents.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <div class="h4 mb-2">Before</div>
                                        <div class="h2 text-warning"><?= number_format($stats['double_count']) ?></div>
                                        <small class="text-muted">Double upload torrents</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 bg-light rounded">
                                        <div class="h4 mb-2">After</div>
                                        <div class="h2 text-primary">0</div>
                                        <small class="text-muted">Double upload torrents</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <form method="post" action="<?= $this->scriptUrl ?>" class="d-inline" data-action="setallnormal">
                                <input type="hidden" name="action" value="setallnormal">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check me-1"></i> Confirm & Revert
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bootstrap JavaScript -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация модальных окон Bootstrap
    const modalElements = document.querySelectorAll('.modal');
    modalElements.forEach(modalEl => {
        if (!bootstrap.Modal.getInstance(modalEl)) {
            new bootstrap.Modal(modalEl);
        }
    });
    
    // Обработка кликов по карточкам для открытия модальных окон
    document.querySelectorAll('.card-hover').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('button') || e.target.closest('form') || e.target.closest('a')) {
                return;
            }
            const target = this.dataset.bsTarget;
            if (target) {
                const modalElement = document.querySelector(target);
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                    modal.show();
                }
            }
        });
    });
    
    // Используйте селектор form[data-action] вместо .action-form
    document.querySelectorAll('form[data-action]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // ВАЖНО: Получаем URL из атрибута формы, а не из свойства
            const formAction = this.getAttribute('action');
            
            if (!formAction) {
                console.error('Form has no action attribute');
                alert('Form configuration error');
                return;
            }
            
            // Создаем FormData из формы
            const formData = new FormData(this);
            const action = this.dataset.action;
            const modalEl = this.closest('.modal');
            
            console.log('Form action URL:', formAction);
            console.log('Form data:', Array.from(formData.entries()));
            
            // Показываем индикатор загрузки
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            const originalDisabled = submitBtn.disabled;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
            submitBtn.disabled = true;
            
            // Отправляем AJAX запрос
            fetch(formAction, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                // Восстанавливаем кнопку
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = originalDisabled;
                
                if (data.status === 'success') {
                    // Закрываем текущую модалку
                    if (modalEl) {
                        const bsModal = bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) {
                            bsModal.hide();
                        }
                    }
                    
                    // Показываем модалку успеха
                    const successTitle = document.getElementById('successTitle');
                    const successMessage = document.getElementById('successMessage');
                    
                    if (successTitle) successTitle.textContent = data.title;
                    if (successMessage) successMessage.innerHTML = data.message;
                    
                    const successModalEl = document.getElementById('successModal');
                    if (successModalEl) {
                        const successModal = bootstrap.Modal.getInstance(successModalEl) || 
                                           new bootstrap.Modal(successModalEl);
                        successModal.show();
                    }
                    
                    // Автообновление через 3 секунды
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                
                // Восстанавливаем кнопку
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = originalDisabled;
                
                alert('An error occurred: ' + error.message + '\nPlease try again.');
            });
            
            return false;
        });
    });
});
</script>


        </body>
        </html>
		
		
		
		
		
		
        <?php
		
		stdfoot();
        
        echo ob_get_clean();
    }
    
    /**
     * Get torrent statistics
     */
    private function getTorrentStats(): array
    {
        // Get total torrents
        $totalQuery = "SELECT COUNT(*) as total FROM torrents";
        $totalResult = $this->database->sql_query($totalQuery);
        $totalData = mysqli_fetch_assoc($totalResult);
        $totalTorrents = (int)($totalData['total'] ?? 0);
        
        // Get double upload count
        $doubleQuery = "SELECT COUNT(*) as double_count FROM torrents WHERE doubleupload = 'yes'";
        $doubleResult = $this->database->sql_query($doubleQuery);
        $doubleData = mysqli_fetch_assoc($doubleResult);
        $doubleCount = (int)($doubleData['double_count'] ?? 0);
        
        // Calculate normal count
        $normalCount = $totalTorrents - $doubleCount;
        
        // Calculate percentages
        $doublePercent = $totalTorrents > 0 ? ($doubleCount / $totalTorrents) * 100 : 0;
        $normalPercent = $totalTorrents > 0 ? ($normalCount / $totalTorrents) * 100 : 0;
        
        return [
            'total' => $totalTorrents,
            'double_count' => $doubleCount,
            'normal_count' => $normalCount,
            'double_percent' => $doublePercent,
            'normal_percent' => $normalPercent
        ];
    }
    
    /**
     * Validate staff access
     */
    private function validateStaffAccess(): void
    {
        $userClass = (int)($this->currentUser['usergroup'] ?? 0);
        
        // Only allow administrators and sysops (adjust as needed)
        if (!in_array($userClass, [6, 7], true)) {
            throw new RuntimeException('Insufficient privileges for this action');
        }
    }
    
    /**
     * Log action to system log
     */
    private function logAction(string $type, int $affectedRows): void
    {
        $username = $this->currentUser['username'] ?? 'System';
        $action = ($type === 'double') ? 'enabled double upload' : 'disabled double upload';
        
        $message = sprintf(
            '%s %s for %d torrents',
            $username,
            $action,
            $affectedRows
        );
        
        // Use existing write_log function if available
        if (function_exists('write_log')) {
            write_log($message);
        }
        
        // Also log to database if needed
        $logQuery = sprintf(
            "INSERT INTO sitelog (added, txt) VALUES (UNIX_TIMESTAMP(), '%s')",
            $this->database->escape_string($message),
            $this->database->escape_string($username)
        );
        
        $this->database->sql_query($logQuery);
    }
    
    /**
     * Show success message
     */
    private function showSuccess(string $title, string $message, string $icon = 'check'): void
    {
        
		
		ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Success - Torrent Manager</title>
           
            <style>
                .success-container {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }
                
                .success-card {
                    max-width: 500px;
                    animation: fadeIn 0.5s ease-out;
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                
                .success-icon {
                    font-size: 5rem;
                    animation: bounce 1s infinite alternate;
                }
                
                @keyframes bounce {
                    from { transform: translateY(0); }
                    to { transform: translateY(-10px); }
                }
            </style>
        </head>
        <body>
            <div class="success-container">
                <div class="card success-card shadow-lg">
                    <div class="card-body text-center p-5">
                        <div class="text-success success-icon mb-4">
                            <i class="fas fa-<?= $icon ?>"></i>
                        </div>
                        <h2 class="card-title mb-3"><?= $title ?></h2>
                        <div class="card-text mb-4">
                            <?= $message ?>
                        </div>
                        <div class="alert alert-light">
                            <i class="fas fa-clock me-2"></i>
                            Action completed at <?= date('H:i:s') ?>
                        </div>
                        <div class="mt-4">
                            <a href="<?= $this->scriptUrl ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i> Return to Manager
                            </a>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-history me-1"></i>
                                This action has been logged in the system
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <script>
            // Auto-redirect after 10 seconds
            setTimeout(() => {
                window.location.href = '<?= $this->scriptUrl ?>';
            }, 10000);
            </script>
        </body>
        </html>
        <?php
        echo ob_get_clean();
        exit;
    }
    
    /**
     * Handle errors gracefully
     */
    private function handleError(Throwable $e): void
    {
        error_log('TorrentManager Error: ' . $e->getMessage());
        
        ob_start();
        ?>
        <div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card border-danger shadow">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                System Error
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-bug fa-4x text-danger mb-3"></i>
                                <h4>An error occurred</h4>
                            </div>
                            
                            <div class="alert alert-danger">
                                <code><?= htmlspecialchars($e->getMessage()) ?></code>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Technical Details:</h6>
                                <ul class="mb-0">
                                    <li>Error Type: <?= get_class($e) ?></li>
                                    <li>File: <?= htmlspecialchars($e->getFile()) ?></li>
                                    <li>Line: <?= $e->getLine() ?></li>
                                    <li>Time: <?= date('Y-m-d H:i:s') ?></li>
                                </ul>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="<?= $this->scriptUrl ?>" class="btn btn-primary">
                                    <i class="fas fa-home me-2"></i> Return to Manager
                                </a>
                                <button onclick="window.location.reload()" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt me-2"></i> Try Again
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        echo ob_get_clean();
        exit;
    }
}

// Initialize and execute
try {
    $manager = new TorrentDoubleUploadManager();
    $manager->execute();
} catch (Throwable $e) {
    // Fallback error display
    http_response_code(500);
    echo '<div class="alert alert-danger m-3" role="alert">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>Fatal Error</h4>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <small>Please contact system administrator.</small>
          </div>';
}