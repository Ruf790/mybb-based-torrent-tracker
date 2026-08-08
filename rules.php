<?php
declare(strict_types=1);


/**
 * Define application version
 */
define('RULES_VERSION', '0.7');

/**
 * Include required files
 */
require_once __DIR__ . '/global.php';
require_once INC_PATH . '/class_parser.php';

/**
 * Enable output compression
 */
gzip();

/**
 * Initialize post parser
 */
$parser = new PostParser();

/**
 * Parser configuration
 */
$parserOptions = [
    'allow_html'      => 0,
    'allow_mycode'    => 1,
    'allow_smilies'   => 1,
    'allow_imgcode'   => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
];

/**
 * Check user permissions function
 */
function hasRuleAccess(?array $user, string $ruleGroups): bool
{
    // Public rule
    if ($ruleGroups === '[0]' || $ruleGroups === '0' || $ruleGroups === '') {
        return true;
    }

    // No user logged in
    if (!$user || !isset($user['usergroup'])) {
        return false;
    }

    $userGroup = (string) $user['usergroup'];

    // Direct group match
    if ($ruleGroups === $userGroup ||
        $ruleGroups === '[' . $userGroup . '].') {
        return true;
    }

    // Check if user group is in the array format
    if (str_contains($ruleGroups, '[') && str_contains($ruleGroups, ']')) {
        // Check if user group exists in pattern like [5][6][7]
        $userGroupPattern = '[' . $userGroup . ']';
        return str_contains($ruleGroups, $userGroupPattern);
    }

    // Legacy format handling
    if (preg_match('/\[' . preg_quote($userGroup, '/') . '\]/', $ruleGroups)) {
        return true;
    }

    return false;
}

/**
 * Start output
 */
stdhead();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">

<div class="rules-masthead">
    <div class="container-lg">
        <span class="rules-masthead__eyebrow">Terms every member agreed to on signup</span>
        <h1 class="rules-masthead__title">The Book of Rules</h1>
        <p class="rules-masthead__sub">Read it. Know it. Break it and the docket below is exactly what gets cited.</p>
    </div>
</div>

<?php
/**
 * Fetch and display rules
 */
try {
    $query = 'SELECT id, title, text, usergroups FROM rules ORDER BY id';
    $result = $db->sql_query_prepared($query);

    $rulesDisplayed = 0;

    while ($result && ($rule = $db->fetch_array($result))) {
        $ruleId = (int) ($rule['id'] ?? 0);
        $ruleNumber = str_pad((string) $ruleId, 3, '0', STR_PAD_LEFT);
        $ruleTitle = htmlspecialchars($rule['title'] ?? 'Untitled Rule', ENT_QUOTES, 'UTF-8');
        $ruleGroups = (string) ($rule['usergroups'] ?? '');

        // Skip if user doesn't have access
        if (!hasRuleAccess($CURUSER ?? null, $ruleGroups)) {
            continue;
        }

        // Parse rule content
        $parsedContent = $parser->parse_message($rule['text'] ?? '', $parserOptions);

        // Deterministic slight tilt per card so the stamp doesn't look copy-pasted
        $tilt = (($ruleId * 7) % 5) - 2;

        // Generate rule card
        echo <<<HTML
        <div class="container-lg mt-4 rules-container" data-rule-id="{$ruleId}">
            <article class="rules-ticket shadow-sm">
                <header class="rules-ticket__header">
                    <div class="rules-ticket__stamp" style="--tilt: {$tilt}deg;" aria-hidden="true">
                        <span class="rules-ticket__stamp-label">Rule</span>
                        <span class="rules-ticket__stamp-number">№{$ruleNumber}</span>
                    </div>
                    <h2 class="rules-ticket__title">{$ruleTitle}</h2>
                    <button type="button" class="rules-ticket__toggle" aria-expanded="true" aria-label="Collapse rule">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </header>

                <div class="rules-ticket__perforation" aria-hidden="true"></div>

                <div class="rules-ticket__body">
                    <div class="rules-text">
                        {$parsedContent}
                    </div>
                </div>

                <footer class="rules-ticket__footer">
                    <div class="user-groups-badge">
        HTML;

        // Display applicable user groups
        if ($ruleGroups && $ruleGroups !== '[0]' && $ruleGroups !== '0') {
            $groups = [];
            if (preg_match_all('/\[(\d+)\]/', $ruleGroups, $matches)) {
                $groups = $matches[1];
            }

            if (!empty($groups)) {
                echo '<small class="text-muted"><i class="fas fa-user-tag me-1"></i> Groups: '
                     . implode(', ', $groups) . '</small>';
            }
        } else {
            echo '<small class="text-muted"><i class="fas fa-globe me-1"></i> All Users</small>';
        }

        echo <<<HTML
                    </div>
                    <small class="rules-ticket__applicable">
                        <i class="fas fa-check-circle me-1"></i>
                        Applicable
                    </small>
                </footer>
            </article>
        </div>
HTML;

        $rulesDisplayed++;
    }

    // Close result set
    $db->free_result($result);

    // No rules message
    if ($rulesDisplayed === 0) {
        echo <<<HTML
        <div class="container-lg mt-5">
            <div class="rules-ticket rules-ticket--empty text-center py-5">
                <div class="empty-state-icon mb-4">
                    <i class="fas fa-scroll fa-4x opacity-50"></i>
                </div>
                <h3 class="h4 mb-3">No Rules Found</h3>
                <p class="text-muted mb-4">
                    There are currently no rules applicable to your account.
                </p>
                <a href="index.php" class="btn rules-btn-primary">
                    <i class="fas fa-home me-2"></i>
                    Return to Home
                </a>
            </div>
        </div>
HTML;
    }

} catch (Throwable $e) {
    // Error handling with better logging
    error_log("Rules display error [{$e->getFile()}:{$e->getLine()}]: " . $e->getMessage());

    // Display user-friendly error message
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorDetails = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    } else {
        $errorDetails = 'Please contact the administrator';
    }

    echo <<<HTML
    <div class="container-lg mt-4">
        <div class="alert alert-danger border-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <div class="alert-icon me-3">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <div class="flex-grow-1">
                    <h4 class="alert-heading mb-2">Unable to Load Rules</h4>
                    <p class="mb-2">We encountered an error while loading the rules. Please try again later.</p>
                    <hr>
                    <p class="mb-0 small">
                        <strong>Error:</strong> {$errorDetails}
                    </p>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="javascript:location.reload()" class="btn btn-outline-primary me-2">
                <i class="fas fa-redo me-1"></i> Retry
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Go Back
            </a>
        </div>
    </div>
HTML;
}

/**
 * End output
 */
stdfoot();

/**
 * Styles
 */
echo <<<CSS
<style>
    :root {
        --rules-accent: #c9871a;
        --rules-accent-strong: #9c6a14;
        --rules-accent-soft: rgba(201, 135, 26, .12);
    }

    /* ── Masthead ─────────────────────────────────────────────────────── */
    .rules-masthead {
        padding: 2.75rem 0 2.25rem;
        border-bottom: 1px solid var(--bs-border-color);
        margin-bottom: 1rem;
    }

    .rules-masthead__eyebrow {
        display: inline-block;
        font-family: 'Oswald', sans-serif;
        font-weight: 600;
        font-size: .72rem;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--rules-accent-strong);
        background: var(--rules-accent-soft);
        border: 1px solid var(--rules-accent);
        border-radius: 999px;
        padding: .3rem .85rem;
        margin-bottom: .9rem;
    }

    .rules-masthead__title {
        font-family: 'Oswald', sans-serif;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .01em;
        font-size: clamp(2rem, 4vw, 2.9rem);
        margin: 0 0 .5rem;
        color: var(--bs-emphasis-color);
    }

    .rules-masthead__sub {
        color: var(--bs-secondary-color);
        max-width: 46rem;
        margin: 0;
        font-size: 1.02rem;
    }

    /* ── Ticket card ──────────────────────────────────────────────────── */
    .rules-container {
        opacity: 0;
        animation: fadeInUp .5s ease-out forwards;
        animation-delay: calc(var(--rule-index, 0) * .08s);
    }

    .rules-ticket {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: .75rem;
        overflow: hidden;
        position: relative;
        transition: border-color .25s ease, box-shadow .25s ease;
    }

    .rules-ticket:hover {
        border-color: var(--rules-accent);
        box-shadow: 0 10px 26px -12px rgba(0, 0, 0, .25) !important;
    }

    .rules-ticket__header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.15rem 1.4rem;
        border-left: 4px solid var(--rules-accent);
        cursor: pointer;
    }

    .rules-ticket__stamp {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        line-height: 1;
        padding: .4rem .6rem;
        border: 2px solid var(--rules-accent);
        border-radius: .4rem;
        color: var(--rules-accent-strong);
        transform: rotate(var(--tilt, 0deg));
        font-family: 'Oswald', sans-serif;
        user-select: none;
    }

    .rules-ticket__stamp-label {
        font-size: .6rem;
        letter-spacing: .12em;
        text-transform: uppercase;
        opacity: .8;
    }

    .rules-ticket__stamp-number {
        font-size: 1.05rem;
        font-weight: 700;
    }

    .rules-ticket__title {
        flex: 1 1 auto;
        margin: 0;
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--bs-emphasis-color);
    }

    .rules-ticket__toggle {
        flex: 0 0 auto;
        width: 2.1rem;
        height: 2.1rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 50%;
        background: transparent;
        color: var(--bs-secondary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform .3s ease, color .2s ease, border-color .2s ease;
    }

    .rules-ticket__toggle:hover {
        color: var(--rules-accent-strong);
        border-color: var(--rules-accent);
    }

    .rules-ticket__toggle[aria-expanded="false"] i {
        transform: rotate(180deg);
    }

    .rules-ticket__toggle i {
        transition: transform .3s ease;
    }

    /* Perforated tear-line between header and content */
    .rules-ticket__perforation {
        position: relative;
        height: 0;
        border-top: 2px dashed var(--bs-border-color);
        margin: 0 1.4rem;
    }

    .rules-ticket__perforation::before,
    .rules-ticket__perforation::after {
        content: '';
        position: absolute;
        top: -7px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: var(--bs-tertiary-bg, var(--bs-body-bg));
        border: 1px solid var(--bs-border-color);
    }

    .rules-ticket__perforation::before { left: -1.4rem; }
    .rules-ticket__perforation::after  { right: -1.4rem; }

    .rules-ticket__body {
        overflow: hidden;
        max-height: 3000px;
        opacity: 1;
        transition: max-height .35s ease, opacity .3s ease, padding .35s ease;
    }

    .rules-ticket__body.is-collapsed {
        max-height: 0;
        opacity: 0;
    }

    .rules-ticket__body .rules-text {
        padding: 1.5rem 1.4rem;
        line-height: 1.7;
        font-size: 1rem;
        color: var(--bs-body-color);
    }

    .rules-ticket__footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .75rem 1.4rem 1.1rem;
    }

    .rules-ticket__applicable {
        color: var(--bs-success, #198754);
        font-weight: 500;
    }

    .rules-btn-primary {
        background: var(--rules-accent);
        border-color: var(--rules-accent);
        color: #1a1200;
        font-weight: 600;
    }

    .rules-btn-primary:hover {
        background: var(--rules-accent-strong);
        border-color: var(--rules-accent-strong);
        color: #fff;
    }

    /* ── Rule text typography ─────────────────────────────────────────── */
    .rules-text h1,
    .rules-text h2,
    .rules-text h3,
    .rules-text h4 {
        font-family: 'Oswald', sans-serif;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: var(--bs-emphasis-color);
        margin-top: 1.75rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .rules-text ul {
        list-style: none;
        padding-left: 0;
    }

    .rules-text ul li {
        padding-left: 1.75rem;
        position: relative;
        margin-bottom: .75rem;
    }

    .rules-text ul li::before {
        content: '—';
        position: absolute;
        left: 0;
        color: var(--rules-accent);
        font-weight: bold;
    }

    .rules-text ol {
        counter-reset: item;
        padding-left: 0;
    }

    .rules-text ol li {
        counter-increment: item;
        padding-left: 2.25rem;
        position: relative;
        margin-bottom: .75rem;
    }

    .rules-text ol li::before {
        content: counter(item);
        position: absolute;
        left: 0;
        top: -.1rem;
        width: 1.6rem;
        height: 1.6rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Oswald', sans-serif;
        font-size: .8rem;
        font-weight: 700;
        color: var(--rules-accent-strong);
        background: var(--rules-accent-soft);
        border-radius: 50%;
    }

    .rules-text code {
        background: var(--bs-tertiary-bg);
        padding: .2rem .4rem;
        border-radius: 4px;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: .9em;
        border: 1px solid var(--bs-border-color);
    }

    .rules-text pre {
        background: #1e1a12;
        color: #f0dfb8;
        padding: 1rem;
        border-radius: 8px;
        overflow-x: auto;
        border: 1px solid var(--rules-accent-strong);
    }

    .rules-text blockquote {
        border-left: 4px solid var(--rules-accent);
        padding: 1rem 1.5rem;
        margin: 1.5rem 0;
        background: var(--rules-accent-soft);
        border-radius: 0 8px 8px 0;
    }

    .user-groups-badge small {
        font-size: .78rem;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(22px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @media (prefers-reduced-motion: reduce) {
        .rules-container {
            animation: none;
            opacity: 1;
        }
        .rules-ticket__body,
        .rules-ticket__toggle,
        .rules-ticket__toggle i {
            transition: none !important;
        }
    }

    .empty-state-icon {
        position: relative;
        display: inline-block;
        color: var(--rules-accent);
    }

    /* ── Print ────────────────────────────────────────────────────────── */
    @media print {
        .rules-masthead__eyebrow,
        .rules-ticket__toggle,
        .rules-print-btn {
            display: none !important;
        }
        .rules-ticket {
            break-inside: avoid;
            box-shadow: none !important;
        }
        .rules-ticket__body {
            max-height: none !important;
            opacity: 1 !important;
        }
    }
</style>
CSS;

/**
 * JavaScript
 */
echo <<<JS
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ruleContainers = document.querySelectorAll('.rules-container');

        ruleContainers.forEach((container, index) => {
            container.style.setProperty('--rule-index', index);

            const header  = container.querySelector('.rules-ticket__header');
            const body    = container.querySelector('.rules-ticket__body');
            const toggle  = container.querySelector('.rules-ticket__toggle');

            if (!header || !body || !toggle) return;

            const setState = (expanded) => {
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                body.classList.toggle('is-collapsed', !expanded);
            };

            header.addEventListener('click', () => {
                const expanded = toggle.getAttribute('aria-expanded') !== 'false';
                setState(!expanded);
            });
        });

        // Smooth scrolling for anchor links inside rule content
        document.querySelectorAll('.rules-text a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });

        // Print rules
        if (ruleContainers.length > 0) {
            const printBtn = document.createElement('button');
            printBtn.type = 'button';
            printBtn.className = 'btn rules-btn-primary rules-print-btn position-fixed bottom-0 end-0 m-3 shadow';
            printBtn.style.zIndex = '1000';
            printBtn.innerHTML = '<i class="fas fa-print me-2"></i>Print Rules';
            printBtn.addEventListener('click', () => window.print());
            document.body.appendChild(printBtn);
        }
    });
</script>
JS;