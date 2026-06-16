<?php
declare(strict_types=1);



class TorrentManager
{
    private const VERSION = '0.2';
    
    public function __construct(
        private object $db,
        private array $currentUser,
        private string $scriptUrl
    ) {
        if (!defined('STAFF_PANEL')) {
            $this->showError('Direct initialization of this file is not allowed.');
        }
    }

    public function handleRequest(): void
    {
        $action = match(true) {
            isset($_POST['action']) => htmlspecialchars((string)$_POST['action'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            isset($_GET['action']) => htmlspecialchars((string)$_GET['action'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            default => 'main'
        };

        match($action) {
            'setallfree' => $this->setAllTorrentsFree(),
            'setallnormal' => $this->setAllTorrentsNormal(),
            default => $this->showMainMenu()
        };
    }

    private function setAllTorrentsFree(): void
    {
        $this->db->sql_query("UPDATE torrents SET free = 'yes' WHERE free = 'no'");
        $this->logAction("All torrents set to Free by {$this->currentUser['username']}");
        $this->showSuccess('All torrents have been set to free.');
    }

    private function setAllTorrentsNormal(): void
    {
        $this->db->sql_query("UPDATE torrents SET free = 'no' WHERE free = 'yes'");
        $this->logAction("All torrents set to Normal by {$this->currentUser['username']}");
        $this->showSuccess('All torrents have been set to normal.');
    }

  
  
 private function showMainMenu(): void
{
    // Получаем количество Free и Normal торрент-файлов
    $freeCount = (int)$this->db->sql_query("SELECT COUNT(*) AS cnt FROM torrents WHERE free='yes'")->fetch_object()->cnt;
    $normalCount = (int)$this->db->sql_query("SELECT COUNT(*) AS cnt FROM torrents WHERE free='no'")->fetch_object()->cnt;

    stdhead('FreeLeech Manager');

    echo <<<HTML
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-bolt me-2"></i> FreeLeech Manager
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-4">

                <div class="col-md-6">
                    <div class="card border-success h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-gift fa-3x text-success mb-3"></i>
                            <h4>Enable FreeLeech</h4>
                            <p class="text-muted">
                                Set <strong>ALL torrents</strong> to free download.
                            </p>
                            <small class="text-success mb-2 d-block">
                                Currently free: <strong>{$freeCount}</strong> / normal: <strong>{$normalCount}</strong>
                            </small>
                            <button class="btn btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#confirmFreeModal">
                                <i class="fas fa-check me-1"></i> Enable
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-sync fa-3x text-primary mb-3"></i>
                            <h4>Restore Normal</h4>
                            <p class="text-muted">
                                Revert all torrents to normal mode.
                            </p>
                            <small class="text-primary mb-2 d-block">
                                Currently free: <strong>{$freeCount}</strong> / normal: <strong>{$normalCount}</strong>
                            </small>
                            <button class="btn btn-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#confirmNormalModal">
                                <i class="fas fa-undo me-1"></i> Restore
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{$this->renderModals($freeCount, $normalCount)}
HTML;
    stdfoot();
}
 




private function renderModals(int $freeCount = 0, int $normalCount = 0): string
{
    $total = $freeCount + $normalCount;

    return <<<HTML
<style>
/* Иконки анимация */
@keyframes bounce {
  0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
  40% { transform: translateY(-15px); }
  60% { transform: translateY(-7px); }
}
@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.15); }
  100% { transform: scale(1); }
}
.animated-icon {
    font-size: 4rem;
    animation: bounce 1s, pulse 1.5s infinite;
}
.count-number {
    font-weight: bold;
    font-size: 1.5rem;
}
</style>

<!-- FreeLeech Confirm -->
<div class="modal fade" id="confirmFreeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">
          <i class="fas fa-exclamation-triangle me-2 animated-icon"></i>
          Confirm FreeLeech
        </h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <i class="fas fa-gift animated-icon text-success mb-3"></i>
        <p class="lead">This will enable <strong>FreeLeech</strong> for all torrents.</p>

        <div class="row mt-3">
            <div class="col-6">
                <div class="p-3 bg-light rounded">
                    <div class="h5 mb-1">Before</div>
                    <div>Free: <span class="count-number" id="freeBefore">{$freeCount}</span></div>
                    <div>Normal: <span class="count-number" id="normalBefore">{$normalCount}</span></div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 bg-light rounded">
                    <div class="h5 mb-1">After</div>
                    <div>Free: <span class="count-number" id="freeAfter">{$total}</span></div>
                    <div>Normal: <span class="count-number" id="normalAfter">0</span></div>
                </div>
            </div>
        </div>

        <div class="alert alert-warning mt-3">
          This action affects the entire tracker.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="post" action="{$this->scriptUrl}" class="action-form">
          <input type="hidden" name="action" value="setallfree">
          <button class="btn btn-success">
            <i class="fas fa-check"></i> Confirm
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Normal Confirm -->
<div class="modal fade" id="confirmNormalModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-primary shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
          <i class="fas fa-info-circle me-2 animated-icon"></i>
          Restore Normal
        </h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <i class="fas fa-sync animated-icon text-primary mb-3"></i>
        <p class="lead">Restore normal upload behavior.</p>

        <div class="row mt-3">
            <div class="col-6">
                <div class="p-3 bg-light rounded">
                    <div class="h5 mb-1">Before</div>
                    <div>Free: <span class="count-number" id="freeBeforeNormal">{$freeCount}</span></div>
                    <div>Normal: <span class="count-number" id="normalBeforeNormal">{$normalCount}</span></div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 bg-light rounded">
                    <div class="h5 mb-1">After</div>
                    <div>Free: <span class="count-number" id="freeAfterNormal">0</span></div>
                    <div>Normal: <span class="count-number" id="normalAfterNormal">{$total}</span></div>
                </div>
            </div>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="post" action="{$this->scriptUrl}" class="action-form">
          <input type="hidden" name="action" value="setallnormal">
          <button class="btn btn-primary">
            <i class="fas fa-check"></i> Confirm
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Плавная анимация чисел
function animateValue(id, start, end, duration) {
    const obj = document.getElementById(id);
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        obj.innerText = Math.floor(progress * (end - start) + start);
        if(progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}
document.addEventListener('DOMContentLoaded', () => {
    animateValue('freeAfter', {$freeCount}, {$total}, 1000);
    animateValue('normalAfterNormal', 0, {$total}, 1000);
});
</script>
HTML;
}










    private function showError(string $message): never
    {
        echo "<font face='verdana' size='2' color='darkred'><b>Error!</b> {$message}</font>";
        exit;
    }

    private function logAction(string $message): void
    {
        if (function_exists('write_log')) {
            write_log($message);
        }
    }

   
   
   private function showSuccess(string $message): void
{
    stdhead('Success');

    echo <<<HTML
<style>
/* Анимация иконки */
@keyframes bounce {
  0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
  40% { transform: translateY(-20px); }
  60% { transform: translateY(-10px); }
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); }
}

.success-icon {
    font-size: 4rem;
    color: #28a745;
    animation: bounce 1s, pulse 1.5s infinite;
}
</style>

<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success shadow-lg rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">
          <i class="fas fa-check-circle me-2"></i> Success
        </h5>
      </div>
      <div class="modal-body text-center py-4">
        <div class="success-icon mb-3">
          <i class="fas fa-check-circle"></i>
        </div>
        <h4 class="mb-2">Success!</h4>
        <p>{$message}</p>
        <small class="text-muted">The page will refresh automatically…</small>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const successModalEl = document.getElementById('successModal');
    const successModal = new bootstrap.Modal(successModalEl, {backdrop: 'static', keyboard: false});
    successModal.show();

    // Авто-обновление через 2.5 секунды
    setTimeout(() => {
        window.location.href = "{$this->scriptUrl}";
    }, 2500);
});
</script>
HTML;

    stdfoot();
    exit;
}



   
   
   
   
   
   
   
}






?>
<script>
document.querySelectorAll('.action-form').forEach(form => {
  form.addEventListener('submit', e => {
    e.preventDefault();

    const btn = form.querySelector('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

    fetch(form.getAttribute('action'), {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams(new FormData(form))
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        // Закрываем все открытые модалки
        document.querySelectorAll('.modal.show').forEach(m =>
          bootstrap.Modal.getInstance(m)?.hide()
        );

        // Вставляем текст в модалку успеха
        document.getElementById('successText').innerHTML = data.message;

        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();

        // Авто-обновление через 2.5 сек
        setTimeout(() => location.reload(), 2500);
      } else {
        alert('Error: ' + (data.message || 'Unknown error'));
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
    })
    .catch(err => {
      console.error(err);
      btn.disabled = false;
      btn.innerHTML = originalHtml;
      alert('AJAX error occurred');
    });
  });
});
</script>
<?


// Инициализация и запуск
if (isset($db) && isset($CURUSER) && isset($_this_script_)) {
    $torrentManager = new TorrentManager($db, $CURUSER, $_this_script_);
    $torrentManager->handleRequest();
} else {
    echo "<font face='verdana' size='2' color='darkred'><b>Error!</b> Required variables are not set.</font>";
}
?>