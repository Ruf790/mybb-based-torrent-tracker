<?php

declare(strict_types=1);

/* =============================================================================
   «Сколько бонусов за сидирование» для details.php и общие функции для mybonus.php.
   Та же формула, что в include/cron/seedbonus.php и mybonus.php:
     в час = min(множитель_раздачи × base_bonus × множитель_кол-ва, hour_cap)
   Множитель раздачи — с точки зрения смотрящего: если он ещё не сидирует,
   сидов станет на одного больше; верность — по его snatched.seedtime.
   ============================================================================= */

if (!defined('IN_TRACKER')) {
    die("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}

/** Настройки seedbonus_settings (один запрос на страницу) */
function sbe_settings(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    global $db;
    $cfg = [];
    $res = $db->sql_query_prepared('SELECT setting_key, setting_value, setting_type FROM seedbonus_settings');
    while ($res && ($row = $db->fetch_array($res))) {
        $v = $row['setting_value'];
        $cfg[$row['setting_key']] = match ($row['setting_type']) {
            'boolean' => in_array(strtolower((string)$v), ['yes', 'true', '1', 'on'], true),
            'integer' => (int)$v,
            'float'   => (float)$v,
            'array'   => json_decode((string)$v, true) ?? [],
            default   => (string)$v,
        };
    }
    return $cfg;
}

/** Множитель за количество сидируемых раздач (как sbc_torrentMultiplier в кроне) */
function sbe_count_multiplier(int $n, string $type, float $flat = 1.0): float
{
    return match ($type) {
        'penalty' => match (true) { $n <= 20 => 1.0, $n <= 50 => 0.9, $n <= 100 => 0.8, default => 0.7 },
        'neutral' => $n <= 100 ? 1.0 : 0.9,
        'reward'  => match (true) { $n >= 100 => 1.2, $n >= 50 => 1.1, $n >= 20 => 1.0, default => 0.9 },
        'flat'    => max(0.0, $flat),
        default   => 1.0,
    };
}

/** Часы эвристики по количеству раздач (как sbc_getHeuristicHours в кроне) */
function sbe_heuristic_hours(int $n, array $cfg): float
{
    return (float)match (true) {
        $n >= 50 => $cfg['heuristic_50'] ?? 24,
        $n >= 40 => $cfg['heuristic_40'] ?? 20,
        $n >= 30 => $cfg['heuristic_30'] ?? 16,
        $n >= 20 => $cfg['heuristic_20'] ?? 12,
        $n >= 10 => $cfg['heuristic_10'] ?? 8,
        $n >= 5  => $cfg['heuristic_5'] ?? 4,
        default  => $cfg['heuristic_1'] ?? 2,
    };
}

/**
 * Оценка BP в час за эту раздачу для пользователя $uid.
 * null — бонусы выключены или раздача не даёт бонус (скрыта, забанена, nuked).
 *
 * @return array{hourly: float, daily: float, seeding: bool, capped: bool, factors: list<array{key: string, mul: float}>}|null
 */
function sbe_estimate(array $t, int $uid): ?array
{
    global $db;

    $cfg = sbe_settings();
    if (empty($cfg['enabled'])) {
        return null;
    }
    if (($t['visible'] ?? 'yes') !== 'yes' || ($t['banned'] ?? 'no') === 'yes' || ($t['isnuked'] ?? 'no') === 'yes') {
        return null;
    }

    $f = static fn(string $k, float $d): float => max(0.0, (float)($cfg[$k] ?? $d));
    $tid = (int)$t['id'];

    // Сидирует ли он сейчас эту раздачу и сколько всего раздач (для множителя количества)
    $seeding = false;
    $count   = 0;
    $res = $db->sql_query_prepared(
        "SELECT COUNT(DISTINCT p.torrent) AS n, MAX(p.torrent = ?) AS here
           FROM peers p
          WHERE p.userid = ? AND p.seeder = 'yes' AND p.last_action >= UNIX_TIMESTAMP() - 2700",
        [$tid, $uid]
    );
    if ($res && ($r = $db->fetch_array($res))) {
        $count   = (int)$r['n'];
        $seeding = (int)$r['here'] === 1;
    }

    $seedtime = 0;
    $res = $db->sql_query_prepared('SELECT MAX(seedtime) AS st FROM snatched WHERE userid = ? AND torrentid = ?', [$uid, $tid]);
    if ($res && ($r = $db->fetch_array($res))) {
        $seedtime = (int)$r['st'];
    }

    $size     = (float)$t['size'];
    $leechers = (int)$t['leechers'];
    $seeders  = (int)$t['seeders'] + ($seeding ? 0 : 1);   // он станет ещё одним сидом
    $age      = TIMENOW - (int)$t['added'];

    $factors = [];
    $add = static function (string $key, float $mul) use (&$factors): void {
        $factors[] = ['key' => $key, 'mul' => $mul];
    };

    $add(match (true) {
        $leechers === 0 => 'leech_none',
        $leechers <= 2  => 'leech_few',
        default         => 'leech_many',
    }, match (true) {
        $leechers === 0 => $f('leech_none', 1.2),
        $leechers <= 2  => $f('leech_few', 1.5),
        default         => $f('leech_many', 1.8),
    });

    [$sk, $sm] = match (true) {
        $size < 536870912   => ['size_small',  $f('size_small', 1.0)],
        $size < 2147483648  => ['size_medium', $f('size_medium', 1.2)],
        $size < 8589934592  => ['size_large',  $f('size_large', 1.5)],
        $size < 21474836480 => ['size_xlarge', $f('size_xlarge', 1.8)],
        default             => ['size_huge',   $f('size_huge', 2.0)],
    };
    $add($sk, $sm);

    [$rk, $rm] = match (true) {
        $seeders > 100 => ['seeders_many',   $f('seeders_many', 0.9)],
        $seeders > 50  => ['seeders_medium', $f('seeders_medium', 0.95)],
        $seeders <= 1  => ['rare_1',         $f('rare_1', 1.0)],
        $seeders <= 3  => ['rare_3',         $f('rare_3', 1.0)],
        $seeders <= 5  => ['rare_5',         $f('rare_5', 1.0)],
        default        => ['', 1.0],
    };
    if ($rk !== '') {
        $add($rk, $rm);
    }

    [$lk, $lm] = match (true) {
        $seedtime >= 15552000 => ['loyal_180', $f('loyal_180', 1.0)],
        $seedtime >= 7776000  => ['loyal_90',  $f('loyal_90', 1.0)],
        $seedtime >= 2592000  => ['loyal_30',  $f('loyal_30', 1.0)],
        default               => ['', 1.0],
    };
    if ($lk !== '') {
        $add($lk, $lm);
    }

    [$ak, $am] = match (true) {
        $age > 15552000 => ['age_old',    $f('age_old', 1.5)],
        $age > 5184000  => ['age_medium', $f('age_medium', 1.3)],
        default         => ['', 1.0],
    };
    if ($ak !== '') {
        $add($ak, $am);
    }

    $promo = 1.0
        + (($t['free'] ?? 'no') === 'yes' ? $f('promo_free', 0.7) : 0.0)
        + (($t['silver'] ?? 'no') === 'yes' ? $f('promo_silver', 0.5) : 0.0)
        + (($t['doubleupload'] ?? 'no') === 'yes' ? $f('promo_double', 0.5) : 0.0);
    if ($promo != 1.0) {
        $add('promo', $promo);
    }

    $mul = 1.0;
    foreach ($factors as $x) {
        $mul *= $x['mul'];
    }

    // Множитель количества раздач — как в кроне
    $n = $count + ($seeding ? 0 : 1);
    $capMul = sbe_count_multiplier($n, (string)($cfg['torrent_multiplier_type'] ?? 'penalty'), (float)($cfg['flat_multiplier'] ?? 1.0));

    $base    = $f('base_bonus', 10.0);
    $cap     = $f('hour_cap', 500.0);
    $theory  = $mul * $base * $capMul;
    $hourly  = min($theory, $cap);

    // Эвристика крона: при многих раздачах начисляет за большее время, чем интервал
    if (!empty($cfg['enable_heuristic'])) {
        $h = sbe_heuristic_hours($n, $cfg);
        $hourly *= max(1.0, $h / 24);
    }

    // Множители 1.0 ничего не говорят пользователю — не показываем
    $factors = array_values(array_filter($factors, static fn(array $x): bool => abs($x['mul'] - 1.0) > 0.001));
    if (abs($capMul - 1.0) > 0.001) {
        $factors[] = ['key' => 'count', 'mul' => $capMul];
    }

    return [
        'hourly'  => round($hourly, 1),
        'daily'   => round($hourly * 24),
        'seeding' => $seeding,
        'capped'  => $theory > $cap,
        'factors' => $factors,
    ];
}

/** Строка под шкалой здоровья на details.php */
function sbe_render(array $est): string
{
    global $lang, $BASEURL;
    $L = static fn(string $k, string $d): string => (string)($lang->details[$k] ?? $d);
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $labels = [
        'leech_none'     => $L('lbl_sbf_leech_none', 'No leechers'),
        'leech_few'      => $L('lbl_sbf_leech_few', 'Leechers waiting'),
        'leech_many'     => $L('lbl_sbf_leech_many', 'In demand'),
        'size_small'     => $L('lbl_sbf_size', 'Size'),
        'size_medium'    => $L('lbl_sbf_size', 'Size'),
        'size_large'     => $L('lbl_sbf_size_large', 'Large'),
        'size_xlarge'    => $L('lbl_sbf_size_large', 'Large'),
        'size_huge'      => $L('lbl_sbf_size_huge', 'Huge'),
        'seeders_many'   => $L('lbl_sbf_seeders_many', 'Many seeders'),
        'seeders_medium' => $L('lbl_sbf_seeders_many', 'Many seeders'),
        'rare_1'         => $L('lbl_sbf_rare', 'Rare'),
        'rare_3'         => $L('lbl_sbf_rare', 'Rare'),
        'rare_5'         => $L('lbl_sbf_rare', 'Rare'),
        'loyal_30'       => $L('lbl_sbf_loyal', 'Loyalty'),
        'loyal_90'       => $L('lbl_sbf_loyal', 'Loyalty'),
        'loyal_180'      => $L('lbl_sbf_loyal', 'Loyalty'),
        'age_old'        => $L('lbl_sbf_age', 'Old release'),
        'age_medium'     => $L('lbl_sbf_age', 'Old release'),
        'promo'          => $L('lbl_sbf_promo', 'Promo'),
        'count'          => $L('lbl_sbf_count', 'Your torrent count'),
    ];

    $chips = '';
    foreach ($est['factors'] as $x) {
        $up     = $x['mul'] > 1.0;
        $chips .= '<span class="dt-bp__f ' . ($up ? 'is-up' : 'is-down') . '">'
                . $e($labels[$x['key']] ?? $x['key'])
                . ' <b>×' . rtrim(rtrim(number_format($x['mul'], 2, '.', ''), '0'), '.') . '</b></span>';
    }

    $num  = '<b>' . $e(number_format($est['hourly'], 1, '.', ' ')) . ' BP</b>';
    $text = $est['seeding']
        ? $L('lbl_bp_seeding', 'You are seeding this torrent and earn about {1} per hour')
        : $L('lbl_bp_estimate', 'Seed this torrent and earn about {1} per hour');
    $text = strtr($e($text), ['{1}' => $num, '%1$s' => $num]);

    $tip = $e(sprintf('≈ %s BP / %s', number_format($est['daily'], 0, '.', ' '), $L('lbl_bp_day', 'day')))
         . ($est['capped'] ? ' · ' . $e($L('lbl_bp_capped', 'hourly limit reached')) : '');

    return '<div class="dt-bp" title="' . $tip . '">'
         . '<span class="dt-bp__ico"><i class="fa-solid fa-coins"></i></span>'
         . '<span class="dt-bp__txt">' . $text . '</span>'
         . ($chips !== '' ? '<span class="dt-bp__fs">' . $chips . '</span>' : '')
         . '<a class="dt-bp__more" href="' . $e(rtrim((string)$BASEURL, '/') . '/mybonus.php') . '" title="' . $e($L('lbl_bp_more', 'My bonus')) . '"><i class="fa-solid fa-chevron-right"></i></a>'
         . '</div>';
}

/**
 * Сколько пользователь зарабатывает сейчас, BP в час — по всем раздачам, которые
 * он сидирует (та же формула, что в кроне: сумма множителей раздач × base_bonus ×
 * множитель количества, не больше hour_cap, плюс эвристика).
 * null — бонусы выключены; 0 — сейчас ничего не сидирует.
 *
 * @return array{hourly: float, daily: float, torrents: int, capped: bool}|null
 */
function sbe_user_hourly(int $uid): ?array
{
    global $db;

    $cfg = sbe_settings();
    if (empty($cfg['enabled']) || $uid <= 0) {
        return null;
    }
    $f = static fn(string $k, float $d): float => max(0.0, (float)($cfg[$k] ?? $d));

    $res = $db->sql_query_prepared(
        "SELECT t.id, t.size, t.added, t.seeders, t.leechers, t.free, t.silver, t.doubleupload,
                (SELECT MAX(s.seedtime) FROM snatched s WHERE s.userid = ? AND s.torrentid = t.id) AS seedtime
           FROM torrents t
          WHERE t.id IN (SELECT DISTINCT p.torrent FROM peers p
                          WHERE p.userid = ? AND p.seeder = 'yes' AND p.last_action >= UNIX_TIMESTAMP() - 2700)
            AND t.visible = 'yes' AND t.banned = 'no' AND t.isnuked = 'no'",
        [$uid, $uid]
    );

    $raw = 0.0;
    $n   = 0;
    while ($res && ($t = $db->fetch_array($res))) {
        $n++;
        $leech = (int)$t['leechers'];
        $seed  = (int)$t['seeders'];
        $size  = (float)$t['size'];
        $st    = (int)$t['seedtime'];
        $age   = TIMENOW - (int)$t['added'];

        $mul = match (true) {
            $leech === 0 => $f('leech_none', 1.2),
            $leech <= 2  => $f('leech_few', 1.5),
            default      => $f('leech_many', 1.8),
        };
        $mul *= match (true) {
            $size < 536870912   => $f('size_small', 1.0),
            $size < 2147483648  => $f('size_medium', 1.2),
            $size < 8589934592  => $f('size_large', 1.5),
            $size < 21474836480 => $f('size_xlarge', 1.8),
            default             => $f('size_huge', 2.0),
        };
        $mul *= match (true) {
            $seed > 100 => $f('seeders_many', 0.9),
            $seed > 50  => $f('seeders_medium', 0.95),
            $seed <= 1  => $f('rare_1', 1.0),
            $seed <= 3  => $f('rare_3', 1.0),
            $seed <= 5  => $f('rare_5', 1.0),
            default     => 1.0,
        };
        $mul *= match (true) {
            $st >= 15552000 => $f('loyal_180', 1.0),
            $st >= 7776000  => $f('loyal_90', 1.0),
            $st >= 2592000  => $f('loyal_30', 1.0),
            default         => 1.0,
        };
        $mul *= match (true) {
            $age > 15552000 => $f('age_old', 1.5),
            $age > 5184000  => $f('age_medium', 1.3),
            default         => 1.0,
        };
        $mul *= 1.0
            + (($t['free'] ?? 'no') === 'yes' ? $f('promo_free', 0.7) : 0.0)
            + (($t['silver'] ?? 'no') === 'yes' ? $f('promo_silver', 0.5) : 0.0)
            + (($t['doubleupload'] ?? 'no') === 'yes' ? $f('promo_double', 0.5) : 0.0);
        $raw += $mul;
    }

    if ($n === 0) {
        return ['hourly' => 0.0, 'daily' => 0.0, 'torrents' => 0, 'capped' => false];
    }

    $capMul = sbe_count_multiplier($n, (string)($cfg['torrent_multiplier_type'] ?? 'penalty'), (float)($cfg['flat_multiplier'] ?? 1.0));
    $cap    = $f('hour_cap', 500.0);
    $theory = $raw * $f('base_bonus', 10.0) * $capMul;
    $hourly = min($theory, $cap);
    if (!empty($cfg['enable_heuristic'])) {
        $hourly *= max(1.0, sbe_heuristic_hours($n, $cfg) / 24);
    }

    return [
        'hourly'   => round($hourly, 1),
        'daily'    => round($hourly * 24),
        'torrents' => $n,
        'capped'   => $theory > $cap,
    ];
}
