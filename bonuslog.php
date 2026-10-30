<?php
declare(strict_types=1);

/**
 * My bonus history: every change of my seedbonus (table bonus_logs).
 */

define('THIS_SCRIPT', 'bonuslog.php');
define('SCRIPTNAME', 'bonuslog.php');

require_once 'global.php';
$lang->load('bonuslog');
$L = $lang->bonuslog;
require_once INC_PATH . '/functions_bonuslog.php';

if (empty($CURUSER['id'])) {
    if (function_exists('loggedinorreturn')) loggedinorreturn();
    header('Location: ' . $BASEURL . '/login.php');
    exit;
}

$uid     = (int)$CURUSER['id'];
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        foreach ($args as $i => $v) $str = str_replace('{' . ($i + 1) . '}', (string)$v, $str);
        return $str;
    }
}

$e       = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$type    = (string)($_GET['type'] ?? '');
$type    = isset(BONUS_LOG_TYPES[$type]) ? $type : '';
$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));

$month   = bonus_log_totals($uid, null, TIMENOW - 30 * 86400);
$filter  = bonus_log_totals($uid, $type ?: null);
$rows    = bonus_log_fetch($uid, $type ?: null, $perPage, ($page - 1) * $perPage);
$pages   = max(1, (int)ceil($filter['count'] / $perPage));

$reasonText = static function (string $type, string $reason) use ($L): string {
    if ($type === 'seeding' && $reason === 'Seeding') return $L['reason_seeding'];
    if ($type === 'gift') {
        if (preg_match('/^Gift to (.+): ([\d,]+) \+ fee ([\d,]+)$/su', $reason, $m)) return ags_fmt($L['reason_gift_to'], $m[1], $m[2], $m[3]);
        if (preg_match('/^Gift from (.+)$/su', $reason, $m)) return ags_fmt($L['reason_gift_from'], $m[1]);
    }
    return $reason;
};
$url = static fn(array $p): string => $BASEURL . '/bonuslog.php' . ($p ? '?' . http_build_query(array_filter($p, static fn($v) => $v !== '' && $v !== 1)) : '');

stdhead($L['page_title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/st-ui.css?ver=61">

<div class="container-md st-page">

  <!-- Header -->
  <div class="st-card st-head mb-3">
    <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-clock-rotate-left"></i></div>
    <div class="min-w-0 flex-grow-1">
      <h1 class="st-title"><?= $e($L['head_title']) ?></h1>
      <div class="st-meta"><?= $e($L['head_desc']) ?></div>
    </div>
    <a class="btn btn-sm btn-outline-primary st-pill" href="<?= $BASEURL ?>/mybonus.php">
      <i class="fa-solid fa-coins me-1"></i><?= $e($L['btn_my_bonuses']) ?>
    </a>
  </div>

  <!-- KPI -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
      <div class="st-kpi">
        <span class="st-ico st-soft-primary"><i class="fa-solid fa-wallet"></i></span>
        <div class="min-w-0">
          <div class="st-kpi__val"><?= $e(number_format((float)$CURUSER['seedbonus'], 1)) ?></div>
          <div class="st-kpi__lbl"><?= $e($L['lbl_balance_now']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="st-kpi">
        <span class="st-ico st-soft-success"><i class="fa-solid fa-arrow-trend-up"></i></span>
        <div class="min-w-0">
          <div class="st-kpi__val"><?= $month['plus'] > 0 ? $e(bonus_log_amount($month['plus'])) : '0' ?></div>
          <div class="st-kpi__lbl"><?= $e($L['lbl_received_30']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="st-kpi">
        <span class="st-ico st-soft-danger"><i class="fa-solid fa-arrow-trend-down"></i></span>
        <div class="min-w-0">
          <div class="st-kpi__val"><?= $month['minus'] > 0 ? $e(bonus_log_amount(-$month['minus'])) : '0' ?></div>
          <div class="st-kpi__lbl"><?= $e($L['lbl_spent_30']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <nav class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm st-pill <?= $type === '' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= $e($url([])) ?>">
      <?= $e($L['tab_all']) ?>
    </a>
    <?php foreach (BONUS_LOG_TYPES as $k => [$label, $icon]): if ($k === 'other') continue; ?>
      <a class="btn btn-sm st-pill <?= $type === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= $e($url(['type' => $k])) ?>">
        <i class="fa-solid <?= $icon ?> me-1"></i><?= $e($L['type_' . $k]) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Table -->
  <div class="st-card mb-3 overflow-hidden">
    <?php if (!$rows): ?>
      <div class="st-empty">
        <div class="st-ico st-soft-muted"><i class="fa-solid fa-receipt"></i></div>
        <div><?= $e($L['empty_nothing']) ?></div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th><?= $e($L['th_date']) ?></th>
              <th><?= $e($L['th_what_for']) ?></th>
              <th class="text-end"><?= $e($L['th_change']) ?></th>
              <th class="text-end"><?= $e($L['th_balance']) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r):
              $a = (float)$r['amount'];
              [$label, $icon] = BONUS_LOG_TYPES[$r['type']] ?? BONUS_LOG_TYPES['other'];
          ?>
            <tr>
              <td class="text-nowrap">
                <?= date('d.m.Y', (int)$r['added']) ?>
                <div class="st-meta"><?= date('H:i', (int)$r['added']) ?></div>
              </td>
              <td>
                <span class="st-badge st-soft-muted">
                  <i class="fa-solid <?= $icon ?>"></i><?= $e($L['type_' . bonus_log_type((string)$r['type'])]) ?>
                </span>
                <div class="mt-1">
                  <?= $e($reasonText((string)$r['type'], (string)$r['reason'])) ?>
                  <?= $r['type'] === 'seeding' ? ' <span class="st-meta">' . $e($L['lbl_whole_day']) . '</span>' : '' ?>
                </div>
              </td>
              <td class="text-end">
                <strong class="<?= $a >= 0 ? 'text-success' : 'text-danger' ?>">
                  <?= $e(bonus_log_amount($a)) ?>
                </strong>
              </td>
              <td class="text-end st-meta">
                <?= $r['balance'] !== null ? $e(number_format((float)$r['balance'], 1)) : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <nav class="d-flex justify-content-center flex-wrap gap-1">
      <?php for ($i = max(1, $page - 5); $i <= min($pages, $page + 5); $i++): ?>
        <a class="btn btn-sm st-pill <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
           href="<?= $e($url(['type' => $type, 'page' => $i])) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>

</div>
<?php
stdfoot();