<?php
declare(strict_types=1);

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… в строку из ланга.
     * $lang->load() превращает {1} в %1$s — поддерживаем оба формата.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

class TorrentManager
{
    private const VERSION   = '0.4';
    private const ASSET_VER = 2;

    public function __construct(
        private object $db,
        private array $currentUser,
        private string $scriptUrl
    ) {
        if (!defined('STAFF_PANEL')) {
            http_response_code(403);
            $this->showError($this->t('err_direct'));
        }

        global $usergroups;
        if (empty($this->currentUser['id']) || !is_mod($usergroups)) {
            http_response_code(403);
            $this->showError($this->t('err_no_permission'));
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
    //  Lang
    // ------------------------------------------------------------------

    private function t(string $key, string|int|float ...$args): string
    {
        global $lang;
        $str = (string)$lang->freeleech[$key];
        return $args ? ags_fmt($str, ...$args) : $str;
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
            $this->fail($this->t('err_csrf'));
        }

        $result = $this->db->sql_query_prepared($target === 'yes'
            ? "UPDATE torrents SET free = 'yes' WHERE free = 'no'"
            : "UPDATE torrents SET free = 'no' WHERE free = 'yes'");
        if (!$result) {
            $this->fail($target === 'yes'
                ? $this->t('err_db_enable')
                : $this->t('err_db_restore'));
        }

        $changed = (int)$this->db->affected_rows();
        // Лог — всегда на английском.
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
            'free'   => $this->t('flash_free', $n),
            'normal' => $this->t('flash_normal', $n),
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
            $this->showError($this->t('err_db_stats'));
        }
        $row = $this->db->fetch_array($res) ?: [];

        return [
            'free'   => (int)($row['free_cnt'] ?? 0),
            'normal' => (int)($row['normal_cnt'] ?? 0),
        ];
    }

    /** Строки ланга для JS: ключи js_* без префикса. */
    private function jsLang(): string
    {
        global $lang;
        $out = [];
        foreach ((array)$lang->freeleech as $k => $val) {
            if (str_starts_with((string)$k, 'js_')) {
                $out[substr((string)$k, 3)] = (string)$val;
            }
        }
        return (string)json_encode(
            $out,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
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
            $total === 0      => ['secondary', 'fa-circle-minus',   'mode_none'],
            $normal === 0     => ['success',   'fa-gift',           'mode_global'],
            $free === 0       => ['primary',   'fa-scale-balanced', 'mode_normal'],
            default           => ['warning',   'fa-shuffle',        'mode_mixed'],
        };
        [$modeColor, $modeIcon, $modeKey] = $mode;

        // Экранированные тексты для heredoc.
        $e = fn(string $key, string|int|float ...$args): string
            => htmlspecialchars($this->t($key, ...$args), ENT_QUOTES, 'UTF-8');

        $L = [
            'sec_title'      => $e('sec_title'),
            'sec_subtitle'   => $e('sec_subtitle'),
            'mode'           => $e($modeKey),
            'lbl_total'      => $e('lbl_total'),
            'lbl_free'       => $e('lbl_free'),
            'lbl_normal'     => $e('lbl_normal'),
            'lbl_free_share' => $e('lbl_free_share'),
            'lbl_dist_free'  => $e('lbl_dist_free'),
            'lbl_dist_norm'  => $e('lbl_dist_normal'),
            'aria_dist'      => $e('aria_dist', $fPct),
            'pane_free'      => $e('pane_free'),
            'hint_free'      => $e('hint_free'),
            'pane_normal'    => $e('pane_normal'),
            'hint_normal'    => $e('hint_normal'),
            'lbl_now'        => $e('lbl_now'),
            'lbl_after'      => $e('lbl_after'),
            'note_whole'     => $e('note_whole'),
            'btn_free'       => $e('btn_free'),
            'btn_normal'     => $e('btn_normal'),
        ];
        $jsLang = $this->jsLang();

        $flash = $this->renderFlash();

        $freeDisabled   = $normal === 0 ? ' disabled' : '';
        $normalDisabled = $free === 0 ? ' disabled' : '';

        stdhead($this->t('page_title'));

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
      <h1>{$L['sec_title']}</h1>
      <p>{$L['sec_subtitle']}</p>
    </div>
    <span class="fl-mode fl-soft-{$modeColor}">
      <i class="fa-solid {$modeIcon}"></i> {$L['mode']}
    </span>
  </div>

  <!-- KPI -->
  <div class="fl-kpis">
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-secondary"><i class="fa-solid fa-database"></i></span>
      <div><div class="fl-kpi-value">{$fTotal}</div><div class="fl-kpi-label">{$L['lbl_total']}</div></div>
    </div>
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-success"><i class="fa-solid fa-gift"></i></span>
      <div><div class="fl-kpi-value">{$fFree}</div><div class="fl-kpi-label">{$L['lbl_free']}</div></div>
    </div>
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-primary"><i class="fa-solid fa-scale-balanced"></i></span>
      <div><div class="fl-kpi-value">{$fNormal}</div><div class="fl-kpi-label">{$L['lbl_normal']}</div></div>
    </div>
    <div class="fl-kpi">
      <span class="fl-kpi-icon fl-soft-warning"><i class="fa-solid fa-chart-pie"></i></span>
      <div><div class="fl-kpi-value">{$fPct}%</div><div class="fl-kpi-label">{$L['lbl_free_share']}</div></div>
    </div>
  </div>

  <!-- Distribution -->
  <div class="fl-card fl-dist">
    <div class="fl-dist-head">
      <span><i class="fa-solid fa-gift text-success"></i> {$L['lbl_dist_free']} <strong>{$fFree}</strong></span>
      <span>{$L['lbl_dist_norm']} <strong>{$fNormal}</strong> <i class="fa-solid fa-scale-balanced text-primary"></i></span>
    </div>
    <div class="fl-dist-bar" role="img" aria-label="{$L['aria_dist']}">
      <div class="fl-dist-free" style="width: {$pctBar}%"></div>
    </div>
  </div>

  <!-- Actions -->
  <div class="fl-actions">
    <div class="fl-card fl-action fl-action-success">
      <div class="fl-action-top">
        <span class="fl-action-icon fl-soft-success"><i class="fa-solid fa-gift"></i></span>
        <div>
          <h2>{$L['pane_free']}</h2>
          <p>{$L['hint_free']}</p>
        </div>
      </div>
      <div class="fl-preview">
        <div class="fl-preview-col">
          <span class="fl-preview-label">{$L['lbl_now']}</span>
          <span><i class="fa-solid fa-gift"></i> {$fFree}</span>
          <span><i class="fa-solid fa-scale-balanced"></i> {$fNormal}</span>
        </div>
        <i class="fa-solid fa-arrow-right-long fl-preview-arrow"></i>
        <div class="fl-preview-col">
          <span class="fl-preview-label">{$L['lbl_after']}</span>
          <span class="text-success"><i class="fa-solid fa-gift"></i> {$fTotal}</span>
          <span><i class="fa-solid fa-scale-balanced"></i> 0</span>
        </div>
      </div>
    </div>

    <div class="fl-card fl-action fl-action-primary">
      <div class="fl-action-top">
        <span class="fl-action-icon fl-soft-primary"><i class="fa-solid fa-rotate-left"></i></span>
        <div>
          <h2>{$L['pane_normal']}</h2>
          <p>{$L['hint_normal']}</p>
        </div>
      </div>
      <div class="fl-preview">
        <div class="fl-preview-col">
          <span class="fl-preview-label">{$L['lbl_now']}</span>
          <span><i class="fa-solid fa-gift"></i> {$fFree}</span>
          <span><i class="fa-solid fa-scale-balanced"></i> {$fNormal}</span>
        </div>
        <i class="fa-solid fa-arrow-right-long fl-preview-arrow"></i>
        <div class="fl-preview-col">
          <span class="fl-preview-label">{$L['lbl_after']}</span>
          <span><i class="fa-solid fa-gift"></i> 0</span>
          <span class="text-primary"><i class="fa-solid fa-scale-balanced"></i> {$fTotal}</span>
        </div>
      </div>
    </div>
  </div>

  <p class="fl-note"><i class="fa-solid fa-triangle-exclamation"></i>
    {$L['note_whole']}</p>

  <!-- Sticky action bar -->
  <div class="fl-bar">
    <span class="fl-bar-info"><i class="fa-solid fa-circle-info"></i> v{$ver}</span>
    <div class="fl-bar-buttons">
      <form method="post" action="{$action}" class="fl-form" data-kind="free">
        <input type="hidden" name="action" value="setallfree">
        <input type="hidden" name="my_post_key" value="{$postKey}">
        <button type="submit" class="fl-btn fl-btn-success"{$freeDisabled}>
          <i class="fa-solid fa-gift"></i> {$L['btn_free']}
        </button>
      </form>
      <form method="post" action="{$action}" class="fl-form" data-kind="normal">
        <input type="hidden" name="action" value="setallnormal">
        <input type="hidden" name="my_post_key" value="{$postKey}">
        <button type="submit" class="fl-btn fl-btn-primary"{$normalDisabled}>
          <i class="fa-solid fa-rotate-left"></i> {$L['btn_normal']}
        </button>
      </form>
    </div>
  </div>
</div>

<script>const AGS_LANG = {$jsLang};</script>
<script src="{$base}/scripts/sweetalert2.min.js"></script>
<script src="{$base}/admin/scripts/freeleech.js?ver={$v}"></script>
HTML;

        stdfoot();
    }

    private function renderFlash(): string
    {
        if (isset($_GET['err'])) {
            return '<div class="fl-flash fl-soft-danger"><i class="fa-solid fa-circle-xmark"></i> '
                . htmlspecialchars($this->t('flash_failed'), ENT_QUOTES, 'UTF-8') . '</div>';
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
            . '<strong>' . htmlspecialchars($this->t('lbl_error'), ENT_QUOTES, 'UTF-8') . '</strong> '
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
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
if (isset($lang)) {
    $lang->load('freeleech');
}

if (isset($db, $CURUSER, $_this_script_, $lang)) {
    $torrentManager = new TorrentManager($db, $CURUSER, $_this_script_);
    $torrentManager->handleRequest();
} else {
    http_response_code(500);
    // $lang может быть не загружен — английский запасной вариант.
    $errLbl = isset($lang->freeleech['lbl_error']) ? (string)$lang->freeleech['lbl_error'] : 'Error!';
    $errMsg = isset($lang->freeleech['err_required_vars'])
        ? (string)$lang->freeleech['err_required_vars']
        : 'Required variables are not set.';
    echo '<div class="alert alert-danger m-3" role="alert"><strong>'
        . htmlspecialchars($errLbl, ENT_QUOTES, 'UTF-8') . '</strong> '
        . htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') . '</div>';
}
