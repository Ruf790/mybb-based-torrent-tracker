<?php
declare(strict_types=1);


/**
 * Define application version
 */
define('RULES_VERSION', '0.6');

/**
 * Include required files
 */
require_once __DIR__ . '/global.php';
require_once INC_PATH . '/functions_security.php';
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
    'allow_html'      => 1,
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

/**
 * Fetch and display rules
 */
try {
    $query = 'SELECT id, title, text, usergroups FROM rules ORDER BY id';
    $result = $db->sql_query($query);
    
    $rulesDisplayed = 0;
    
    while ($rule = $db->fetch_array($result)) {
        $ruleId = (int) ($rule['id'] ?? 0);
        $ruleTitle = htmlspecialchars($rule['title'] ?? 'Untitled Rule', ENT_QUOTES, 'UTF-8');
        $ruleGroups = (string) ($rule['usergroups'] ?? '');
        
        // Skip if user doesn't have access
        if (!hasRuleAccess($CURUSER ?? null, $ruleGroups)) {
            continue;
        }
        
        // Parse rule content
        $parsedContent = $parser->parse_message($rule['text'] ?? '', $parserOptions);
        
        // Generate rule card
        echo <<<HTML
        <div class="container-lg mt-4 rules-container" data-rule-id="{$ruleId}">
            <div class="card rules-card border-0 shadow-sm mb-4">
                <!-- Card Header -->
                <div class="card-header rounded-top-3 bg-gradient rules-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0 fw-semibold">
                            <i class="fas fa-gavel me-2 fa-fw"></i>
                            {$ruleTitle}
                        </h2>
                        <span class="badge bg-white bg-opacity-25 text-uppercase fs-7">
                            Rule #{$ruleId}
                        </span>
                    </div>
                </div>
                
                <!-- Card Body -->
                <div class="card-body rules-content p-4">
                    <div class="rules-text">
                        {$parsedContent}
                    </div>
                </div>
                
                <!-- Card Footer -->
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <div class="d-flex justify-content-between align-items-center">
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
                        <small class="text-muted">
                            <i class="fas fa-check-circle me-1 text-success"></i>
                            Applicable
                        </small>
                    </div>
                </div>
            </div>
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
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="empty-state-icon mb-4">
                        <i class="fas fa-scroll fa-4x text-muted opacity-50"></i>
                    </div>
                    <h3 class="h4 text-muted mb-3">No Rules Found</h3>
                    <p class="text-muted mb-4">
                        There are currently no rules applicable to your account.
                    </p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>
                        Return to Home
                    </a>
                </div>
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
 * Optional: Add CSS styles
 */
echo <<<CSS
<style>
    :root {
        --rules-primary: #4361ee;
        --rules-secondary: #3a0ca3;
        --rules-accent: #7209b7;
    }
    
    .rules-container {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
        animation-delay: calc(var(--rule-index, 0) * 0.1s);
    }
    
    .rules-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .rules-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(67, 97, 238, 0.15) !important;
        border-color: var(--rules-primary);
    }
    
    .rules-header {
        background: linear-gradient(135deg, var(--rules-primary) 0%, var(--rules-secondary) 100%);
        border-bottom: 3px solid rgba(255,255,255,0.15);
        padding: 1.25rem 1.5rem;
    }
    
    .rules-content {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        position: relative;
    }
    
    .rules-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--rules-primary), var(--rules-accent));
    }
    
    .rules-text {
        line-height: 1.7;
        font-size: 1rem;
        color: #374151;
    }
    
    .rules-text h1,
    .rules-text h2,
    .rules-text h3,
    .rules-text h4 {
        color: #1f2937;
        margin-top: 1.75rem;
        margin-bottom: 1rem;
        font-weight: 600;
        position: relative;
        padding-bottom: 0.5rem;
    }
    
    .rules-text h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, var(--rules-primary), transparent);
    }
    
    .rules-text ul {
        list-style: none;
        padding-left: 0;
    }
    
    .rules-text ul li {
        padding-left: 1.75rem;
        position: relative;
        margin-bottom: 0.75rem;
    }
    
    .rules-text ul li::before {
        content: '•';
        position: absolute;
        left: 0.5rem;
        color: var(--rules-primary);
        font-weight: bold;
        font-size: 1.2em;
    }
    
    .rules-text ol {
        counter-reset: item;
        padding-left: 0;
    }
    
    .rules-text ol li {
        counter-increment: item;
        padding-left: 2rem;
        position: relative;
        margin-bottom: 0.75rem;
    }
    
    .rules-text ol li::before {
        content: counter(item) '.';
        position: absolute;
        left: 0.5rem;
        color: var(--rules-primary);
        font-weight: bold;
        min-width: 1.5rem;
    }
    
    .rules-text code {
        background: #f1f5f9;
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 0.9em;
        border: 1px solid #e2e8f0;
    }
    
    .rules-text pre {
        background: #1e293b;
        color: #e2e8f0;
        padding: 1rem;
        border-radius: 8px;
        overflow-x: auto;
        border: 1px solid #334155;
    }
    
    .rules-text blockquote {
        border-left: 4px solid var(--rules-primary);
        padding: 1rem 1.5rem;
        margin: 1.5rem 0;
        background: #f8fafc;
        border-radius: 0 8px 8px 0;
        position: relative;
    }
    
    .rules-text blockquote::before {
        content: '"';
        position: absolute;
        top: -0.5rem;
        left: -0.5rem;
        font-size: 4rem;
        color: var(--rules-primary);
        opacity: 0.1;
        font-family: Georgia, serif;
    }
    
    .user-groups-badge .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        margin: 0 0.125rem;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .empty-state-icon {
        position: relative;
        display: inline-block;
    }
    
    .empty-state-icon::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 120px;
        height: 120px;
        background: radial-gradient(circle, rgba(67, 97, 238, 0.1) 0%, transparent 70%);
        border-radius: 50%;
    }
    
    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        .rules-card {
            background: #1e293b;
            border-color: #334155;
        }
        
        .rules-content {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        
        .rules-text {
            color: #cbd5e1;
        }
        
        .rules-text h1,
        .rules-text h2,
        .rules-text h3,
        .rules-text h4 {
            color: #f1f5f9;
        }
        
        .rules-text code {
            background: #334155;
            border-color: #475569;
        }
        
        .rules-text blockquote {
            background: #0f172a;
        }
    }
</style>
CSS;

/**
 * Optional: Add JavaScript for enhanced interactivity
 */
echo <<<JS
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add animation delays based on rule position
        const ruleContainers = document.querySelectorAll('.rules-container');
        ruleContainers.forEach((container, index) => {
            container.style.setProperty('--rule-index', index);
            container.style.animationDelay = (index * 0.1) + 's';
        });
        
        // Enhanced click effects
        ruleContainers.forEach(container => {
            const card = container.querySelector('.rules-card');
            
            card.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.99) translateY(-2px)';
            });
            
            card.addEventListener('mouseup', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
            
            // Toggle rule details
            const header = card.querySelector('.rules-header');
            header.style.cursor = 'pointer';
            
            header.addEventListener('click', function() {
                const content = card.querySelector('.rules-content');
                const icon = this.querySelector('i');
                
                if (content.style.maxHeight && content.style.maxHeight !== '0px') {
                    content.style.maxHeight = '0px';
                    content.style.opacity = '0';
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    content.style.maxHeight = content.scrollHeight + 'px';
                    content.style.opacity = '1';
                    icon.style.transform = 'rotate(180deg)';
                }
            });
        });
        
        // Add smooth scrolling for anchor links in rule content
        document.querySelectorAll('.rules-text a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
        
        // Lazy load observer
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });
            
            ruleContainers.forEach(container => {
                observer.observe(container);
            });
        }
        
        // Print rules functionality
        const printBtn = document.createElement('button');
        printBtn.className = 'btn btn-outline-primary position-fixed bottom-0 end-0 m-3 shadow';
        printBtn.innerHTML = '<i class="fas fa-print me-2"></i>Print Rules';
        printBtn.style.zIndex = '1000';
        
        printBtn.addEventListener('click', function() {
            window.print();
        });
        
        document.body.appendChild(printBtn);
        
        // Print styles
        const printStyles = document.createElement('style');
        printStyles.innerHTML = \`
            @media print {
                .rules-container {
                    break-inside: avoid;
                    opacity: 1 !important;
                }
                
                .rules-card {
                    box-shadow: none !important;
                    border: 1px solid #ddd !important;
                }
                
                .rules-header {
                    background: #f8f9fa !important;
                    color: #000 !important;
                    -webkit-print-color-adjust: exact;
                }
                
                button, .btn {
                    display: none !important;
                }
            }
        \`;
        document.head.appendChild(printStyles);
    });
</script>
JS;