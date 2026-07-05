<?php



declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><b>Error!</b> Direct access to this file is not allowed.</div>');
}


define('MR_VERSION', 'v0.4 by xam');

require_once INC_PATH . '/datahandler.php';

class ReseedRequestHandler
{
    private const MAX_TORRENTS_PER_REQUEST = 100;
    
    public function __construct(
        private array $config,
        private object $db,
        private array $curUser
    ) {}
    
    public function handleRequest(): void
    {
        $action = $_GET['do'] ?? $_POST['do'] ?? '';
        
        match ($action) {
            'request_reseed_final' => $this->processReseedRequest(),
            'request_reseed' => $this->showReseedForm(),
            default => $this->showWeakTorrents()
        };
    }
    
    
	
	
	
	
	
	
	private function processReseedRequest(): void
    {
    
	global $_this_script_;
	
	$torrentIds = $this->validateTorrentIds($_POST['torrents'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $senderId = (int)($_POST['sender'] ?? 0);
    $requestFrom = $_POST['requestfrom'] ?? 'owner';
    $enableDoubleUpload = ($_POST['doubleupload'] ?? '') === 'yes';
    
    if (empty($torrentIds) || empty($subject) || empty($message)) {
        $_SESSION['error_message'] = 'Missing required parameters';
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }
    
    require_once INC_PATH . '/functions_pm.php';
    $sentCount = 0;
    
    if ($requestFrom === 'owner') {
        $sentCount = $this->notifyUploaders($torrentIds, $subject, $message, $senderId);
    } else {
        $sentCount = $this->notifyAllSnatchers($torrentIds, $subject, $message, $senderId);
    }
    
    if ($enableDoubleUpload && $sentCount > 0) {
        $this->enableDoubleUpload($torrentIds);
    }
    
    // Сохраняем сообщение в сессии и делаем редирект
    $_SESSION['success_message'] = "Reseed requests sent successfully! {$sentCount} messages dispatched.";
    admin_redirect($_this_script_);
    exit;
    }
	
	
	
	
    
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
    
    private function notifyUploaders(array $torrentIds, string $subject, string $message, int $senderId): int
    {
        $idList = implode(',', array_map('intval', $torrentIds));
        
        $query = $this->db->sql_query("
            SELECT t.owner, t.name, t.id, u.username
            FROM torrents t
            INNER JOIN users u ON t.owner = u.id
            WHERE t.id IN ({$idList})  
            AND seeders = 0
            AND t.owner > 0
        ");
        
        return $this->sendNotifications($query, $subject, $message, $senderId);
    }
    
    private function notifyAllSnatchers(array $torrentIds, string $subject, string $message, int $senderId): int
    {
        $idList = implode(',', array_map('intval', $torrentIds));
        
        $query = $this->db->sql_query("
            SELECT DISTINCT s.userid as owner, s.torrentid as id, t.name, u.username
            FROM snatched s
            INNER JOIN torrents t ON s.torrentid = t.id
            INNER JOIN users u ON s.userid = u.id
            WHERE s.finished = 'yes' 
            AND s.torrentid IN ({$idList})
            AND s.userid > 0
            ORDER BY s.torrentid
        ");
        
        return $this->sendNotifications($query, $subject, $message, $senderId);
    }
    
    private function sendNotifications(object $query, string $subject, string $message, int $senderId): int
    {
        $sentCount = 0;
        $baseUrl = $this->config['baseurl'] ?? $GLOBALS['BASEURL'] ?? '';
        
        while ($torrent = $this->db->fetch_array($query)) {
            $torrentUrl = $baseUrl . '/' . get_torrent_link($torrent['id']);
            $formattedMessage = str_replace(
                ['{username}', '{torrentname}'],
                [
                    htmlspecialchars($torrent['username'], ENT_QUOTES),
                    '[url=' . $torrentUrl . ']' . htmlspecialchars($torrent['name'], ENT_QUOTES) . '[/url]'
                ],
                $message
            );
            
            send_pm([
                'subject' => $this->db->escape_string($subject),
                'message' => $this->db->escape_string($formattedMessage),
                'touid'   => (int)$torrent['owner']
            ], $senderId, true);
            
            $sentCount++;
        }
        
        return $sentCount;
    }
    
    private function enableDoubleUpload(array $torrentIds): void
    {
        $idList = implode(',', array_map('intval', $torrentIds));
        $this->db->sql_query("
            UPDATE torrents 
            SET doubleupload = 'yes' 
            WHERE id IN ({$idList})
        ");
    }
    
    private function showReseedForm(): void
    {
        $torrentIds = $this->validateTorrentIds($_POST['torrents'] ?? []);
        
        if (empty($torrentIds)) {
            $this->showError('No torrents selected');
            return;
        }
        
        require 'include/staff_languages.php';
        stdhead('Request Reseed for Weak Torrents');
        
        $this->renderReseedForm($torrentIds, $mass_reseed ?? []);
        stdfoot();
        exit;
    }
    
    private function showWeakTorrents(): void
    {
        stdhead('Request Reseed for Weak Torrents');
        $this->renderWeakTorrentsTable();
        stdfoot();
    }
    
    private function renderReseedForm(array $torrentIds, array $languageData): void
    {
        $idList = implode(',', $torrentIds);
        $script = $_SERVER['PHP_SELF'] ?? 'index.php';
        
        $defaultSubject = htmlspecialchars($languageData['message']['subject'] ?? 'Reseed Request', ENT_QUOTES);
        $defaultMessage = htmlspecialchars($languageData['message']['body'] ?? 'Hello {username}, please consider re-seeding {torrentname}.', ENT_QUOTES);
        ?>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const doubleUploadSelect = document.querySelector('select[name="doubleupload"]');
            const messageTextarea = document.querySelector('textarea[name="message"]');
            const doubleUploadNote = "\n\nNote: You will receive double upload credits when you re-seed this torrent!";
            
            doubleUploadSelect?.addEventListener('change', function() {
                let currentMessage = messageTextarea.value;
                
                if (this.value === 'yes' && !currentMessage.includes(doubleUploadNote)) {
                    messageTextarea.value = currentMessage + doubleUploadNote;
                } else if (this.value === 'no') {
                    messageTextarea.value = currentMessage.replace(doubleUploadNote, '');
                }
            });
        });
        </script>
        
        <div class="container mt-4">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-seedling me-2"></i>
                            Request Reseed for Selected Torrents
                        </h4>
                        <span class="badge bg-light text-primary fs-6">
                            <?= count($torrentIds) ?> torrent<?= count($torrentIds) !== 1 ? 's' : '' ?>
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <form method="post" action="<?= $_this_script_ ?>" id="reseedForm" class="needs-validation" novalidate>
                        <input type="hidden" name="do" value="request_reseed_final">
                        <input type="hidden" name="torrents" value="<?= htmlspecialchars($idList) ?>">
                        
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" 
                                           name="subject" 
                                           class="form-control form-control-lg" 
                                           value="<?= $defaultSubject ?>" 
                                           required
                                           placeholder="Message Subject">
                                    <label>Subject <span class="text-danger">*</span></label>
                                    <div class="invalid-feedback">Please enter a subject for the message.</div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea name="message" 
                                              class="form-control" 
                                              rows="10" 
                                              required
                                              style="height: 200px;"
                                              placeholder="Your message"><?= $defaultMessage ?></textarea>
                                    <label>Message <span class="text-danger">*</span></label>
                                    <div class="form-text">
                                        Use <code>{username}</code> for the recipient's name and 
                                        <code>{torrentname}</code> for the torrent link.
                                    </div>
                                    <div class="invalid-feedback">Please enter a message.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary mb-3">
                                            <i class="fas fa-bolt me-2"></i>Double Upload
                                        </h6>
                                        <select class="form-select" name="doubleupload">
                                            <option value="no">No (Default)</option>
                                            <option value="yes">Yes - Enable Double Upload</option>
                                        </select>
                                        <small class="text-muted mt-2 d-block">
                                            Users will receive double upload credits for re-seeding.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary mb-3">
                                            <i class="fas fa-user me-2"></i>Sender
                                        </h6>
                                        <select class="form-select" name="sender">
                                            <option value="0">System Message</option>
                                            <option value="<?= (int)$this->curUser['id'] ?>">
                                                <?= htmlspecialchars($this->curUser['username'] ?? 'Current User') ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary mb-3">
                                            <i class="fas fa-users me-2"></i>Recipients
                                        </h6>
                                        <select class="form-select" name="requestfrom">
                                            <option value="owner">Uploader Only</option>
                                            <option value="all">All Snatched Users</option>
                                        </select>
                                        <small class="text-muted mt-2 d-block">
                                            Notify either just the uploader or all users who snatched.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-5 pt-3 border-top">
                            <div class="d-flex justify-content-between">
                                <button type="reset" class="btn btn-outline-secondary px-4">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                                <button type="submit" class="btn btn-success px-5">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reseed Requests
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        // Form validation
        (function() {
            'use strict';
            const form = document.getElementById('reseedForm');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        })();
        </script>
        <?php
    }
    
    private function renderWeakTorrentsTable(): void
    {
        
		
		// В начале метода showWeakTorrents() или renderWeakTorrentsTable()
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            ' . htmlspecialchars($_SESSION['success_message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            ' . htmlspecialchars($_SESSION['error_message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error_message']);
}
		
		
		
		
		
		
		
		$res = $this->db->sql_query("
            SELECT t.id, t.name, t.seeders, t.leechers, 
                   t.times_completed, t.added, t.owner, 
                   u.username, u.usergroup, u.uploaded, u.downloaded
            FROM torrents t
            LEFT JOIN users u ON t.owner = u.id
            WHERE t.seeders = 0
            AND t.visible = 'yes'
            ORDER BY t.added DESC
            LIMIT 500
        ");
        
        $baseUrl = $this->config['baseurl'] ?? $GLOBALS['BASEURL'] ?? '';
        $dateFormat = $GLOBALS['dateformat'] ?? 'd-m-Y';
        $timeFormat = $GLOBALS['timeformat'] ?? 'H:i:s';
        ?>
        
        <div class="container mt-3">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Weak Torrents (No Seeders)
                                </h4>
                                <span class="badge bg-warning text-dark fs-6">
                                    <?= $this->db->num_rows($res) ?> torrent<?= $this->db->num_rows($res) !== 1 ? 's' : '' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <form method="post" action="<?= $_this_script_ ?>" id="torrentSelectionForm">
                                <input type="hidden" name="do" value="request_reseed">
                                
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3" width="40%">Torrent Name</th>
                                                <th class="text-center">Added</th>
                                                <th class="text-center">Uploader</th>
                                                <th class="text-center">Seeders</th>
                                                <th class="text-center">Leechers</th>
                                                <th class="text-center">Snatched</th>
                                                <th class="text-center" width="80">
                                                    <div class="form-check d-inline-block">
                                                        <input class="form-check-input" 
                                                               type="checkbox" 
                                                               id="selectAllTorrents">
                                                        <label class="form-check-label" for="selectAllTorrents"></label>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($this->db->num_rows($res) > 0): ?>
                                                <?php while ($torrent = $this->db->fetch_array($res)): ?>
                                                    <tr class="<?= $torrent['leechers'] > 0 ? 'table-warning' : '' ?>">
                                                        <td class="ps-3">
                                                            <div class="d-flex align-items-center">
                                                                <a href="<?= $baseUrl . '/' . get_torrent_link($torrent['id']) ?>" 
                                                                   class="text-decoration-none text-primary fw-bold">
                                                                    <?= htmlspecialchars($torrent['name']) ?>
                                                                </a>
                                                                <div class="btn-group ms-2">
                                                                    <a href="<?= $baseUrl ?>/upload.php?id=<?= $torrent['id'] ?>" 
                                                                       class="btn btn-sm btn-outline-primary" 
                                                                       title="Edit Torrent">
                                                                        <i class="fas fa-edit fa-xs"></i>
                                                                    </a>
                                                                    <a href="<?= $baseUrl ?>/admin/index.php?act=fastdelete&id=<?= $torrent['id'] ?>" 
                                                                       class="btn btn-sm btn-outline-danger" 
                                                                       title="Delete Torrent"
                                                                       onclick="return confirm('Are you sure you want to delete this torrent?');">
                                                                        <i class="fas fa-trash fa-xs"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-secondary">
                                                                <?= my_datee($dateFormat, $torrent['added']) ?><br>
                                                                <small><?= my_datee($timeFormat, $torrent['added']) ?></small>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <a href="<?= $baseUrl . '/' . get_profile_link($torrent['owner']) ?>" 
                                                               class="text-decoration-none">
                                                                <?= format_name($torrent['username'], $torrent['usergroup']) ?>
                                                            </a>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-danger rounded-pill fs-6">
                                                                <?= ts_nf($torrent['seeders']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?= $torrent['leechers'] > 0 ? 'warning' : 'secondary' ?> rounded-pill fs-6">
                                                                <?= ts_nf($torrent['leechers']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <a href="<?= $baseUrl ?>/viewsnatches.php?id=<?= $torrent['id'] ?>" 
                                                               class="text-decoration-none">
                                                                <span class="badge bg-info rounded-pill fs-6">
                                                                    <?= ts_nf($torrent['times_completed']) ?>
                                                                </span>
                                                            </a>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="form-check">
                                                                <input class="form-check-input torrent-checkbox" 
                                                                       type="checkbox" 
                                                                       name="torrents[]" 
                                                                       value="<?= (int)$torrent['id'] ?>"
                                                                       id="torrent_<?= $torrent['id'] ?>">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-5">
                                                        <div class="text-muted">
                                                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                                                            <h5>No weak torrents found!</h5>
                                                            <p class="mb-0">All torrents have active seeders.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if ($this->db->num_rows($res) > 0): ?>
                                <div class="mt-4 pt-3 border-top">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-text">
                                            Selected: <span id="selectedCount">0</span> torrent(s)
                                        </div>
                                        <button type="submit" 
                                                class="btn btn-success px-4 py-2" 
                                                id="submitButton" 
                                                disabled>
                                            <i class="fas fa-seedling me-2"></i>
                                            Request Reseed for Selected Torrents
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllTorrents');
            const torrentCheckboxes = document.querySelectorAll('.torrent-checkbox');
            const selectedCountSpan = document.getElementById('selectedCount');
            const submitButton = document.getElementById('submitButton');
            
            // Select all functionality
            selectAllCheckbox?.addEventListener('change', function() {
                torrentCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectionCount();
            });
            
            // Individual checkbox change
            torrentCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectionCount);
            });
            
            function updateSelectionCount() {
                const checkedCount = Array.from(torrentCheckboxes).filter(cb => cb.checked).length;
                selectedCountSpan.textContent = checkedCount;
                submitButton.disabled = checkedCount === 0;
                
                // Update select all checkbox state
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = checkedCount === torrentCheckboxes.length;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < torrentCheckboxes.length;
                }
            }
            
            // Form submission confirmation
            document.getElementById('torrentSelectionForm')?.addEventListener('submit', function(e) {
                const checkedCount = Array.from(torrentCheckboxes).filter(cb => cb.checked).length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one torrent.');
                    return;
                }
                
                if (!confirm(`Send reseed requests for ${checkedCount} torrent(s)?`)) {
                    e.preventDefault();
                }
            });
            
            // Initialize count
            updateSelectionCount();
        });
        </script>
        <?php
    }
    
    private function validateTorrentIds($input): array
    {
        if (is_array($input)) {
            $ids = array_map('intval', $input);
        } else {
            $ids = array_map('intval', explode(',', (string)$input));
        }
        
        $ids = array_filter($ids, fn($id) => $id > 0);
        $ids = array_unique($ids);
        
        if (count($ids) > self::MAX_TORRENTS_PER_REQUEST) {
            $ids = array_slice($ids, 0, self::MAX_TORRENTS_PER_REQUEST);
        }
        
        return $ids;
    }
    
    private function showError(string $message): void
    {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    }
    
    private function showSuccess(string $message): void
    {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    }
}

// Initialize and run the handler
$handler = new ReseedRequestHandler(
    [
        'baseurl' => $BASEURL ?? '',
    ],
    $db,
    $CURUSER ?? []
);

$handler->handleRequest();