<?php
declare(strict_types=1);

/**
 * My bonus history: every change of my seedbonus (table bonus_logs).
 */

define('THIS_SCRIPT', 'bonuslog.php');
define('SCRIPTNAME', 'bonuslog.php');

require_once 'global.php';
require_once INC_PATH . '/functions_bonuslog.php';

if (empty($CURUSER['id'])) {
    if (function_exists('loggedinorreturn')) loggedinorreturn();
    header('Location: ' . $BASEURL . '/login.php');
    exit;
}

$uid     = (int)$CURUSER['id'];
$e       = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$type    = (string)($_GET['type'] ?? '');
$type    = isset(BONUS_LOG_TYPES[$type]) ? $type : '';
$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));

$month   = bonus_log_totals($uid, null, TIMENOW - 30 * 86400);
$filter  = bonus_log_totals($uid, $type ?: null);
$rows    = bonus_log_fetch($uid, $type ?: null, $perPage, ($page - 1) * $perPage);
$pages   = max(1, (int)ceil($filter['count'] / $perPage));
$url     = static fn(array $p): string => $BASEURL . '/bonuslog.php' . ($p ? '?' . http_build_query(array_filter($p, static fn($v) => $v !== '' && $v !== 1)) : '');

stdhead('Bonus history');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bonuslog.css?ver=111">

<div class="bl">
  <div class="bl-card bl-head">
    <div class="bl-head__icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
    <div class="bl-head__text">
      <h1>Bonus history</h1>
      <p>Every change of your bonus points: what for, how much and the balance after it.</p>
    </div>
    <a class="bl-btn" href="<?= $BASEURL ?>/mybonus.php"><i class="fa-solid fa-coins"></i>My bonuses</a>
  </div>

  <div class="bl-kpis">
    <div class="bl-card bl-kpi"><div class="bl-kpi__icon bl-tone-primary"><i class="fa-solid fa-wallet"></i></div>
      <div><div class="bl-kpi__value"><?= $e(number_format((float)$CURUSER['seedbonus'], 1)) ?></div><div class="bl-kpi__label">Balance now</div></div></div>
    <div class="bl-card bl-kpi"><div class="bl-kpi__icon bl-tone-success"><i class="fa-solid fa-arrow-trend-up"></i></div>
      <div><div class="bl-kpi__value"><?= $month['plus'] > 0 ? $e(bonus_log_amount($month['plus'])) : '0' ?></div><div class="bl-kpi__label">Received, 30 days</div></div></div>
    <div class="bl-card bl-kpi"><div class="bl-kpi__icon bl-tone-danger"><i class="fa-solid fa-arrow-trend-down"></i></div>
      <div><div class="bl-kpi__value"><?= $month['minus'] > 0 ? $e(bonus_log_amount(-$month['minus'])) : '0' ?></div><div class="bl-kpi__label">Spent, 30 days</div></div></div>
  </div>

  <nav class="bl-tabs">
    <a class="<?= $type === '' ? 'is-active' : '' ?>" href="<?= $e($url([])) ?>">All</a>
    <?php foreach (BONUS_LOG_TYPES as $k => [$label, $icon]): if ($k === 'other') continue; ?>
      <a class="<?= $type === $k ? 'is-active' : '' ?>" href="<?= $e($url(['type' => $k])) ?>"><i class="fa-solid <?= $icon ?>"></i><?= $e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="bl-card bl-table-wrap">
    <?php if (!$rows): ?>
      <div class="bl-empty"><i class="fa-solid fa-receipt"></i><p>Nothing here yet.</p></div>
    <?php else: ?>
      <table class="bl-table">
        <thead><tr><th>Date</th><th>What for</th><th class="bl-num">Change</th><th class="bl-num">Balance</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $a = (float)$r['amount']; [$label, $icon] = BONUS_LOG_TYPES[$r['type']] ?? BONUS_LOG_TYPES['other']; ?>
          <tr>
            <td class="bl-date"><?= date('d.m.Y', (int)$r['added']) ?><div class="bl-sub"><?= date('H:i', (int)$r['added']) ?></div></td>
            <td><span class="bl-type"><i class="fa-solid <?= $icon ?>"></i><?= $e($label) ?></span>
              <div><?= $e((string)$r['reason']) ?><?= $r['type'] === 'seeding' ? ' <span class="bl-sub">(whole day)</span>' : '' ?></div></td>
            <td class="bl-num"><strong class="<?= $a >= 0 ? 'bl-plus' : 'bl-minus' ?>"><?= $e(bonus_log_amount($a)) ?></strong></td>
            <td class="bl-num bl-sub"><?= $r['balance'] !== null ? $e(number_format((float)$r['balance'], 1)) : '-' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="bl-pages">
      <?php for ($i = max(1, $page - 5); $i <= min($pages, $page + 5); $i++): ?>
        <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= $e($url(['type' => $type, 'page' => $i])) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
<?php
stdfoot();
