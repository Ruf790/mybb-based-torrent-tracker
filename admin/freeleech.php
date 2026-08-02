<?php
declare(strict_types=1);



class TorrentManager
{
    private const VERSION = '0.3';

    public function __construct(
        private object $db,
        private array $currentUser,
        private string $scriptUrl
    ) {
        if (!defined('STAFF_PANEL')) {
            http_response_code(403);
            $this->showError('Direct initialization of this file is not allowed.');
        }

        global $usergroups;
        if (empty($this->currentUser['id']) || !is_mod($usergroups)) {
            http_response_code(403);
            $this->showError('You do not have permission to access this page.');
        }
    }

    public function handleRequest(): void
    {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

        $action = match(true) {
            isset($_POST['action']) => htmlspecialchars((string)$_POST['action'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            isset($_GET['action']) => htmlspecialchars((string)$_GET['action'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            default => 'main'
        };

        // Мутирующие действия принимаем только через POST — GET может дойти
        // через простую ссылку/img-тег, что превращает это в one-click CSRF.
        if (in_array($action, ['setallfree', 'setallnormal'], true) && !$isPost) {
            $action = 'main';
        }

        match($action) {
            'setallfree' => $this->setAllTorrentsFree(),
            'setallnormal' => $this->setAllTorrentsNormal(),
            default => $this->showMainMenu()
        };
    }

    private function validateCsrf(): void
    {
        $token = $_POST['my_post_key'] ?? '';
        // $silent=true — иначе при провале функция может сама вывести HTML
        // (в зависимости от состояния IN_ADMINCP) вместо простого false.
        // Этот эндпоинт всегда отвечает JSON (jsonError/jsonSuccess), и JS
        // ждёт res.json() — примешавшийся HTML сломает парсинг на фронте.
        if (!verify_post_check($token, true)) {
            $this->jsonError('Security check failed. Please refresh the page and try again.');
        }
    }

    private function setAllTorrentsFree(): void
    {
        $this->validateCsrf();

        $result = $this->db->sql_query_prepared("UPDATE torrents SET free = 'yes' WHERE free = 'no'");
        if (!$result) {
            $this->jsonError('Database error while enabling FreeLeech.');
        }

        $this->logAction('All torrents set to Free');
        $this->jsonSuccess('All torrents have been set to free.');
    }

    private function setAllTorrentsNormal(): void
    {
        $this->validateCsrf();

        $result = $this->db->sql_query_prepared("UPDATE torrents SET free = 'no' WHERE free = 'yes'");
        if (!$result) {
            $this->jsonError('Database error while restoring normal mode.');
        }

        $this->logAction('All torrents set to Normal');
        $this->jsonSuccess('All torrents have been set to normal.');
    }

    private function jsonSuccess(string $message): never
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => $message]);
        exit;
    }

    private function jsonError(string $message): never
    {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }

    private function getCounts(): array
    {
        $freeResult = $this->db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM torrents WHERE free='yes'");
        $normalResult = $this->db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM torrents WHERE free='no'");

        if (!$freeResult || !$normalResult) {
            $this->showError('Database error while loading FreeLeech statistics.');
        }

        $freeRow = $this->db->fetch_array($freeResult);
        $normalRow = $this->db->fetch_array($normalResult);

        return [
            'free'   => (int)($freeRow['cnt'] ?? 0),
            'normal' => (int)($normalRow['cnt'] ?? 0),
        ];
    }

    private function showMainMenu(): void
    {
        global $mybb;

        $counts = $this->getCounts();
        $freeCount = $counts['free'];
        $normalCount = $counts['normal'];
        $postKey = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');

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

{$this->renderModals($freeCount, $normalCount, $postKey)}
HTML;
        stdfoot();
    }

    private function renderModals(int $freeCount, int $normalCount, string $postKey): string
    {
        $total = $freeCount + $normalCount;

        return <<<HTML
<style>
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
          <input type="hidden" name="my_post_key" value="{$postKey}">
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
          <input type="hidden" name="my_post_key" value="{$postKey}">
          <button class="btn btn-primary">
            <i class="fas fa-check"></i> Confirm
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Success modal (populated via AJAX response) -->
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
          <i class="fas fa-check-circle animated-icon text-success"></i>
        </div>
        <h4 class="mb-2">Success!</h4>
        <p id="successText"></p>
        <small class="text-muted">The page will refresh automatically…</small>
      </div>
    </div>
  </div>
</div>

<script>
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
        document.querySelectorAll('.modal.show').forEach(m =>
          bootstrap.Modal.getInstance(m)?.hide()
        );

        document.getElementById('successText').innerHTML = data.message;

        const modal = new bootstrap.Modal(document.getElementById('successModal'), {backdrop: 'static', keyboard: false});
        modal.show();

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
HTML;
    }

    private function showError(string $message): never
    {
        echo "<div class=\"alert alert-danger m-3\" role=\"alert\"><strong>Error!</strong> " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</div>";
        exit;
    }

    private function logAction(string $message): void
    {
        if (function_exists('write_log')) {
            $uid = (int)($this->currentUser['id'] ?? 0);
            $username = $this->currentUser['username'] ?? 'Unknown';
            write_log("{$message} by {$username} (UID {$uid})");
        }
    }
}

// Инициализация и запуск
if (isset($db) && isset($CURUSER) && isset($_this_script_)) {
    $torrentManager = new TorrentManager($db, $CURUSER, $_this_script_);
    $torrentManager->handleRequest();
} else {
    http_response_code(500);
    echo "<div class=\"alert alert-danger m-3\" role=\"alert\"><strong>Error!</strong> Required variables are not set.</div>";
}