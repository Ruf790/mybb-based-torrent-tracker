<?php
declare(strict_types=1);

/**
 * Maintenance page (site offline, managesettings.php → Main → Site online = off).
 *
 * - 503 Service Unavailable + Retry-After: search engines keep the site indexed,
 *   and "Check status" can really tell whether the site is back
 * - the message from the settings (offline_message) is shown, not a fixed text
 * - live countdown to offline_minutes (end time stamp) or "until switched back on"
 * - when the time is up the page checks by itself every 20 s and reloads once the site is online
 * - light / dark theme by the system setting, no motion if the user asked for reduced motion
 */
function render_maintenance_page(): void
{
    global $SITENAME, $BASEURL, $offline_minutes, $offline_message;

    $e        = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $siteName = (string)($SITENAME ?? 'Our site');
    $base     = rtrim((string)($BASEURL ?? ''), '/');
    $message  = trim((string)($offline_message ?? ''));
    if ($message === '') {
        $message = 'We are making some improvements. The site will be back shortly.';
    }

    $isUnlimited = ($offline_minutes ?? '') === 'unlimited';
    $endTs       = (!$isUnlimited && is_numeric($offline_minutes ?? null)) ? (int)$offline_minutes : 0;
    $left        = $endTs > 0 ? max(0, $endTs - time()) : 0;

    // 503 + Retry-After: correct for maintenance, and lets the JS below detect the end
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: ' . ($left > 0 ? $left : 600));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Content-Type: text/html; charset=UTF-8');
    }

    if ($isUnlimited) {
        $state = ['Until further notice', 'We will be back as soon as the work is done.'];
    } elseif ($left > 0) {
        $state = ['Back at ' . date('H:i', $endTs), date('l, j F', $endTs)];
    } else {
        $state = ['Almost done', 'The site should be back any moment now.'];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Maintenance · <?= $e($siteName) ?></title>
    <link rel="stylesheet" href="<?= $e($base) ?>/include/templates/default/style/bootstrap-icons.css">
    <style>
        :root {
            --bg: #f3f6fb; --card: #ffffff; --text: #1d2433; --muted: #6b7489; --line: #e6eaf2;
            --accent: #2f6bff; --accent-2: #7c4dff; --soft: rgba(47, 107, 255, .08); --ok: #19a463;
            --shadow: 0 30px 80px -30px rgba(31, 60, 140, .35);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0d111a; --card: #151b28; --text: #e7ebf3; --muted: #8e98ad; --line: #232c3d;
                --soft: rgba(96, 140, 255, .12); --shadow: 0 30px 80px -30px rgba(0, 0, 0, .7);
            }
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0; padding: 24px; display: grid; place-items: center;
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, sans-serif;
            color: var(--text); background: var(--bg);
            background-image:
                radial-gradient(1200px 600px at 10% -10%, rgba(47, 107, 255, .14), transparent 60%),
                radial-gradient(900px 500px at 110% 110%, rgba(124, 77, 255, .14), transparent 60%);
        }
        .card {
            width: 100%; max-width: 560px; background: var(--card); border: 1px solid var(--line);
            border-radius: 24px; box-shadow: var(--shadow); overflow: hidden;
            animation: rise .6s cubic-bezier(.2, .8, .2, 1) both;
        }
        .top {
            position: relative; padding: 36px 32px 28px; text-align: center; color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
        }
        .top::after {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(400px 160px at 50% 0%, rgba(255, 255, 255, .25), transparent 70%);
        }
        .badge {
            position: absolute; top: 16px; right: 16px; display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; letter-spacing: .04em;
            background: rgba(255, 255, 255, .18); backdrop-filter: blur(6px);
        }
        .badge .dot { width: 7px; height: 7px; border-radius: 50%; background: #ffd166; animation: blink 1.6s infinite; }
        .gears { position: relative; width: 84px; height: 84px; margin: 0 auto 14px; }
        .gears i { position: absolute; line-height: 1; filter: drop-shadow(0 6px 12px rgba(0, 0, 0, .2)); }
        .gears .g1 { font-size: 58px; left: 0; top: 4px; animation: spin 8s linear infinite; }
        .gears .g2 { font-size: 34px; right: 0; bottom: 0; animation: spin 6s linear infinite reverse; opacity: .85; }
        h1 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -.01em; }
        .top p { margin: 6px 0 0; opacity: .9; font-size: 15px; }

        .body { padding: 28px 32px 30px; }
        .msg {
            display: flex; gap: 12px; padding: 14px 16px; border-radius: 14px;
            background: var(--soft); line-height: 1.55; font-size: 15px;
        }
        .msg i { color: var(--accent); font-size: 18px; margin-top: 1px; }

        .eta { text-align: center; margin: 26px 0 8px; }
        .eta-label { font-size: 13px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .clock { display: flex; justify-content: center; gap: 10px; margin: 10px 0 6px; }
        .unit {
            min-width: 76px; padding: 12px 8px 10px; border-radius: 14px; border: 1px solid var(--line);
            background: linear-gradient(180deg, var(--soft), transparent);
        }
        .unit b { display: block; font-size: 32px; font-variant-numeric: tabular-nums; line-height: 1.1; }
        .unit span { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; }
        .eta-title { font-size: 20px; font-weight: 700; margin-top: 8px; }
        .eta-sub { color: var(--muted); font-size: 14px; margin-top: 2px; }

        .bar { height: 6px; border-radius: 999px; background: var(--line); overflow: hidden; margin: 22px 0 4px; }
        .bar span {
            display: block; height: 100%; width: 40%; border-radius: 999px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            animation: slide 1.8s ease-in-out infinite;
        }

        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 22px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 12px;
            font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none;
            border: 1px solid var(--line); background: var(--card); color: var(--text);
            transition: transform .15s, border-color .15s, background .15s;
        }
        .btn:hover { transform: translateY(-1px); border-color: var(--accent); }
        .btn.primary { background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; border: 0; }
        .btn:disabled { opacity: .7; cursor: progress; transform: none; }

        .status { text-align: center; min-height: 20px; margin-top: 12px; font-size: 13px; color: var(--muted); }
        .status.ok { color: var(--ok); font-weight: 600; }

        .foot {
            display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
            padding: 14px 32px; border-top: 1px solid var(--line); font-size: 13px; color: var(--muted);
        }
        .foot a { color: var(--muted); text-decoration: none; }
        .foot a:hover { color: var(--accent); }

        @keyframes spin  { to { transform: rotate(360deg); } }
        @keyframes blink { 50% { opacity: .3; } }
        @keyframes slide { 0% { transform: translateX(-110%); } 100% { transform: translateX(260%); } }
        @keyframes rise  { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
        @media (max-width: 480px) {
            .top, .body { padding-left: 20px; padding-right: 20px; }
            .unit { min-width: 64px; } .unit b { font-size: 26px; }
            .foot { padding: 14px 20px; }
        }
    </style>
</head>
<body>
<main class="card" role="main">
    <header class="top">
        <span class="badge"><span class="dot"></span><span id="m-badge">MAINTENANCE</span></span>
        <div class="gears" aria-hidden="true">
            <i class="bi bi-gear-fill g1"></i>
            <i class="bi bi-gear-wide-connected g2"></i>
        </div>
        <h1><?= $e($siteName) ?> is under maintenance</h1>
        <p>Thanks for your patience, we will be back shortly</p>
    </header>

    <section class="body">
        <div class="msg"><i class="bi bi-megaphone-fill"></i><div><?= nl2br($e($message)) ?></div></div>

        <div class="eta" aria-live="polite">
            <div class="eta-label">Estimated time left</div>
            <?php if (!$isUnlimited && $left > 0): ?>
                <div class="clock" id="m-clock">
                    <div class="unit"><b id="m-h">00</b><span>hours</span></div>
                    <div class="unit"><b id="m-m">00</b><span>min</span></div>
                    <div class="unit"><b id="m-s">00</b><span>sec</span></div>
                </div>
            <?php endif; ?>
            <div class="eta-title" id="m-title"><?= $e($state[0]) ?></div>
            <div class="eta-sub" id="m-sub"><?= $e($state[1]) ?></div>
        </div>

        <div class="bar" aria-hidden="true"><span></span></div>

        <div class="actions">
            <button type="button" class="btn primary" id="m-check"><i class="bi bi-arrow-repeat"></i>Check status</button>
        </div>
        <div class="status" id="m-status" role="status"></div>
    </section>

    <footer class="foot">
        <span><i class="bi bi-shield-check"></i> <?= $e($siteName) ?></span>
        <span id="m-auto"><?= (!$isUnlimited && $left === 0) ? 'Checking automatically…' : '' ?></span>
    </footer>
</main>

<script>
(() => {
    'use strict';
    const endAt     = <?= $endTs > 0 ? $endTs * 1000 : 0 ?>;   // ms, 0 = unknown / unlimited
    const unlimited = <?= $isUnlimited ? 'true' : 'false' ?>;
    const $ = id => document.getElementById(id);
    const pad = n => String(n).padStart(2, '0');

    // The page answers 503 while maintenance is on; anything else = the site is back
    const check = async (manual) => {
        const btn = $('m-check'), status = $('m-status');
        if (manual) { btn.disabled = true; status.textContent = 'Checking…'; status.className = 'status'; }
        try {
            const r = await fetch(location.href, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin' });
            if (r.status !== 503) {
                status.textContent = 'The site is back online. Reloading…';
                status.className = 'status ok';
                setTimeout(() => location.reload(), 1200);
                return true;
            }
            if (manual) status.textContent = 'Maintenance is still in progress.';
        } catch {
            if (manual) status.textContent = 'Could not check right now. Try again in a moment.';
        } finally {
            if (manual) btn.disabled = false;
        }
        return false;
    };
    $('m-check').addEventListener('click', () => check(true));

    let polling = null;
    const startPolling = () => {
        if (polling) return;
        $('m-auto').textContent = 'Checking automatically…';
        polling = setInterval(() => check(false), 20000);
    };

    if (endAt > 0) {
        const tick = () => {
            const left = Math.max(0, Math.round((endAt - Date.now()) / 1000));
            if ($('m-h')) {
                $('m-h').textContent = pad(Math.floor(left / 3600));
                $('m-m').textContent = pad(Math.floor(left % 3600 / 60));
                $('m-s').textContent = pad(left % 60);
            }
            if (left === 0) {
                clearInterval(timer);
                $('m-clock')?.remove();
                $('m-title').textContent = 'Almost done';
                $('m-sub').textContent = 'The site should be back any moment now.';
                $('m-badge').textContent = 'FINISHING';
                startPolling();
                check(false);
            }
        };
        const timer = setInterval(tick, 1000);
        tick();
    } else if (!unlimited) {
        startPolling();
    } else {
        // Unlimited: check now and then, quietly
        setInterval(() => check(false), 60000);
    }
})();
</script>
</body>
</html>
<?php
}