<?php
declare(strict_types=1);

class TorrentManager
{
    private const VERSION   = '0.4';
    private const ASSET_VER = 1;

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
        $action = $isPost ? (string)($_POST['action'] ?? 'main') : 'main';

        // Мутирующие действия — только POST (GET-ссылка/img = one-click CSRF).
        match ($action) {
            'setallfree'   => $this->setAllTorrents('yes'),
            'setallnormal' => $this->setAllTorrents('no'),
            default        => $this->showMainMenu(),
        };
    }

    // ------------------------------------------------------------------
    //  Actions
    // ------------------------------------------------------------------

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    private function setAllTorrents(string $target): never
    {
        $token = (string)($_POST['my_post_key'] ?? '');
        // silent=true — иначе функция может сама вывести HTML вместо false.
        if (!verify_post_check($token, true)) {
            $this->fail('Security check failed. Please refresh the page and try again.');
        }

        $result = $this->db->sql_query_prepared($target === 'yes'
            ? "UPDATE torrents SET free = 'yes' WHERE free = 'no'"
            : "UPDATE torrents SET free = 'no' WHERE free = 'yes'");
        if (!$result) {
            $this->fail($target === 'yes'
                ? 'Database error while enabling FreeLeech.'
                : 'Database error while restoring normal mode.');
        }

        $changed = (int)$this->db->affected_rows();
        $label   = $target === 'yes' ? 'Free' : 'Normal';
        $this->logAction("All torrents set to {$label} ({$changed} changed)");

        $done     = $target === 'yes' ? 'free' : 'normal';
        $redirect = $this->url(['done' => $done, 'n' => $changed]);

        if ($this->isAjax()) {
            $this->json([
                'status'   => 'success',
                'message'  => $this->doneMessage($done, $changed),
                'redirect' => $redirect,
            ]);
        }

        // Post/Redirect/Get — F5 не повторит действие.
        header('Location: ' . $redirect, true, 303);
        exit;
    }

    private function fail(string $message): never
    {
        if ($this->isAjax()) {
            $this->json(['status' => 'error', 'message' => $message], 400);
        }
        header('Location: ' . $this->url(['err' => 1]), true, 303);
        exit;
    }

    private function json(array $payload, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function url(array $params = []): string
    {
        if (!$params) {
            return $this->scriptUrl;
        }
        $sep = str_contains($this->scriptUrl, '?') ? '&' : '?';
        return $this->scriptUrl . $sep . http_build_query($params);
    }

    private function doneMessage(string $done, int $changed): string
    {
        $n = number_format($changed);
        return match ($done) {
            'free'   => "FreeLeech enabled — {$n} torrent(s) are now free.",
            'normal' => "Normal mode restored — {$n} torrent(s) switched back.",
            default  => '',
        };
    }

    // ------------------------------------------------------------------
    //  Page
    // ------------------------------------------------------------------

    private function getCounts(): array
    {
        $res = $this->db->sql_query_prepared(
            "SELECT SUM(free = 'yes') AS free_cnt, SUM(free = 'no') AS normal_cnt FROM torrents"
        );
        if (!$res) {
            $this->showError('Database error while loading FreeLeech statistics.');
        }
        $row = $this->db->fetch_array($res) ?: [];

        return [
            'free'   => (int)($row['free_cnt'] ?? 0),
            'normal' => (int)($row['normal_cnt'] ?? 0),
        ];
    }

    private function showMainMenu(): void
    {
        global $mybb, $BASEURL;

        $counts  = $this->getCounts();
        $free    = $counts['free'];
        $normal  = $counts['normal'];
        $total   = $free + $normal;
        $pct     = $total > 0 ? round($free / $total * 100, 1) : 0.0;
        $pctBar  = $total > 0 ? $free / $total * 100 : 0;
        $postKey = htmlspecialchars((string)$mybb->post_code, ENT_QUOTES, 'UTF-8');
        $action  = htmlspecialchars($this->scriptUrl, ENT_QUOTES, 'UTF-8');
        $base    = htmlspecialchars((string)$BASEURL, ENT_QUOTES, 'UTF-8');
        $v       = self::ASSET_VER;
        $ver     = self::VERSION;

        $fFree   = number_format($free);
        $fNormal = number_format($normal);
        $fTotal  = number_format($total);
        $fPct    = rtrim(rtrim(number_format($pct, 1), '0'), '.');

        $mode = match (true) {
            $total === 0      => ['secondary', 'fa-circle-minus', 'No torrents'],
            $normal === 0     => ['success',   'fa-gift',         'Global FreeLeech active'],
            $free === 0       => ['primary',   'fa-scale-balanced', 'Normal mode'],
            default           => ['warning',   'fa-shuffle',      'Mixed mode'],
        };
        [$modeColor, $modeIcon, $modeText] = $mode;

        $flash = $this->renderFlash();

        $freeDisabled   = $normal === 0 ? ' disabled' : '';
        $normalDisabled = $free === 0 ? ' disabled' : '';

        stdhead('FreeLeech Manager');

        echo <<<HTML
<link rel="stylesheet" href="{$base}/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="{$base}/admin/templates/freeleech.css?ver={$v}">

<div class="fl-page container py-4" id="flPage"
     data-free="{$free}" data-normal="{$normal}" data-total="{$total}">

  {$flash}

  <!-- Header -->
  <div class="fl-card fl-header">
    <div class="fl-header-icon"><i class="fa-solid fa-bolt"></i></div>
    <div class="fl-header-text">
      <h1>FreeLeech Manager</h1>
      <p>Switch every torrent on the tracker to free download, or bring them all back to normal.</p>
    </div>
    <span class="fl-mode fl-soft-{$modeColor}">
      <i class="fa-solid {$modeIcon}"></i> {$modeText}
    </span>
  </div>

  <!-- KPI -->
  <div class="fl-kpis">
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-secondary"><i class="fa-solid fa-database"></i></span>
      <div><div class="fl-kpi-value">{$fTotal}</div><div class="fl-kpi-label">Total torrents</div></div>
    </div>
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-success"><i class="fa-solid fa-gift"></i></span>
      <div><div class="fl-kpi-value">{$fFree}</div><div class="fl-kpi-label">FreeLeech</div></div>
    </div>
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-primary"><i class="fa-solid fa-scale-balanced"></i></span>
      <div><div class="fl-kpi-value">{$fNormal}</div><div class="fl-kpi-label">Normal</div></div>
    </div>
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-warning"><i class="fa-solid fa-chart-pie"></i></span>
      <div><div class="fl-kpi-value">{$fPct}%</div><div class="fl-kpi-label">Free share</div></div>
    </div>
  </div>

  <!-- Distribution -->
  <div class="fl-card fl-dist">
    <div class="fl-dist-head">
      <span><i class="fa-solid fa-gift text-success"></i> Free <strong>{$fFree}</strong></span>
      <span>Normal <strong>{$fNormal}</strong> <i class="fa-solid fa-scale-balanced text-primary"></i></span>
    </div>
    <div class="fl-dist-bar" role="img" aria-label="{$fPct}% of torrents are free">
      <div class="fl-dist-free" style="width: {$pctBar}%"></div>
    </div>
  </div>

  <!-- Actions -->
  <div class="fl-actions">
    <div class="fl-card fl-action fl-action-success">
      <div class="fl-action-top">
        <span class="fl-action-icon fl-soft-success"><i class="fa-solid fa-gift"></i></span>
        <div>
          <h2>Enable FreeLeech</h2>
          <p>Downloads stop counting against ratio for every torrent. Upload is still credited.</p>
        </div>
      </div>
      <div class="fl-preview">
        <div class="fl-preview-col">
          <span class="fl-preview-label">Now</span>
          <span><i class="fa-solid fa-gift"></i> {$fFree}</span>
          <span><i class="fa-solid fa-scale-balanced"></i> {$fNormal}</span>
        </div>
        <i class="fa-solid fa-arrow-right-long fl-preview-arrow"></i>
        <div class="fl-preview-col">
          <span class="fl-preview-label">After</span>
          <span class="text-success"><i class="fa-solid fa-gift"></i> {$fTotal}</span>
          <span><i class="fa-solid fa-scale-balanced"></i> 0</span>
        </div>
      </div>
    </div>

    <div class="fl-card fl-action fl-action-primary">
      <div class="fl-action-top">
        <span class="fl-action-icon fl-soft-primary"><i class="fa-solid fa-rotate-left"></i></span>
        <div>
          <h2>Restore normal</h2>
          <p>Every torrent goes back to regular download accounting.</p>
        </div>
      </div>
      <div class="fl-preview">
        <div class="fl-preview-col">
          <span class="fl-preview-label">Now</span>
          <span><i class="fa-solid fa-gift"></i> {$fFree}</span>
          <span><i class="fa-solid fa-scale-balanced"></i> {$fNormal}</span>
        </div>
        <i class="fa-solid fa-arrow-right-long fl-preview-arrow"></i>
        <div class="fl-preview-col">
          <span class="fl-preview-label">After</span>
          <span><i class="fa-solid fa-gift"></i> 0</span>
          <span class="text-primary"><i class="fa-solid fa-scale-balanced"></i> {$fTotal}</span>
        </div>
      </div>
    </div>
  </div>

  <p class="fl-note"><i class="fa-solid fa-triangle-exclamation"></i>
    Both actions change the whole tracker at once and are written to the staff log.</p>

  <!-- Sticky action bar -->
  <div class="fl-bar">
    <span class="fl-bar-info"><i class="fa-solid fa-circle-info"></i> v{$ver}</span>
    <div class="fl-bar-buttons">
      <form method="post" action="{$action}" class="fl-form" data-kind="free">
        <input type="hidden" name="action" value="setallfree">
        <input type="hidden" name="my_post_key" value="{$postKey}">
        <button type="submit" class="fl-btn fl-btn-success"{$freeDisabled}>
          <i class="fa-solid fa-gift"></i> Enable FreeLeech
        </button>
      </form>
      <form method="post" action="{$action}" class="fl-form" data-kind="normal">
        <input type="hidden" name="action" value="setallnormal">
        <input type="hidden" name="my_post_key" value="{$postKey}">
        <button type="submit" class="fl-btn fl-btn-primary"{$normalDisabled}>
          <i class="fa-solid fa-rotate-left"></i> Restore normal
        </button>
      </form>
    </div>
  </div>
</div>

<script src="{$base}/scripts/sweetalert2.min.js"></script>
<script src="{$base}/admin/scripts/freeleech.js?ver={$v}"></script>
HTML;

        stdfoot();
    }

    private function renderFlash(): string
    {
        if (isset($_GET['err'])) {
            return '<div class="fl-flash fl-soft-danger"><i class="fa-solid fa-circle-xmark"></i>'
                . ' The action failed. Refresh the page and try again.</div>';
        }

        $done = (string)($_GET['done'] ?? '');
        if (!in_array($done, ['free', 'normal'], true)) {
            return '';
        }
        $msg  = htmlspecialchars($this->doneMessage($done, (int)($_GET['n'] ?? 0)), ENT_QUOTES, 'UTF-8');
        $icon = $done === 'free' ? 'fa-gift' : 'fa-rotate-left';

        return "<div class=\"fl-flash fl-soft-success\"><i class=\"fa-solid {$icon}\"></i> {$msg}</div>";
    }

    private function showError(string $message): never
    {
        echo '<div class="alert alert-danger m-3" role="alert"><i class="fa-solid fa-circle-xmark me-1"></i>'
            . '<strong>Error!</strong> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        exit;
    }

    private function logAction(string $message): void
    {
        if (function_exists('write_log')) {
            $uid      = (int)($this->currentUser['id'] ?? 0);
            $username = (string)($this->currentUser['username'] ?? 'Unknown');
            write_log("{$message} by {$username} (UID {$uid})");
        }
    }
}

// Инициализация и запуск
if (isset($db, $CURUSER, $_this_script_)) {
    $torrentManager = new TorrentManager($db, $CURUSER, $_this_script_);
    $torrentManager->handleRequest();
} else {
    http_response_code(500);
    echo '<div class="alert alert-danger m-3" role="alert"><strong>Error!</strong> Required variables are not set.</div>';
}