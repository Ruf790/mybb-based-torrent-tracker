<?php

declare(strict_types=1);

function fetch_page_url(string $url, int $page): string
{
    if ($page <= 1) {
        return str_replace(
            ['-page-{page}', '&amp;page={page}', '{page}'],
            ['', '', (string)$page],
            $url
        );
    }

    return match (str_contains($url, '{page}')) {
        true => str_replace('{page}', (string)$page, $url),
        false => $url . (str_contains($url, '?') ? '&amp;' : '?') . "page=$page"
    };
}

/**
 * Modern pagination function with strict typing
 */
function multipage(
    int $count,
    int $perpage,
    int $page,
    string $url,
    bool $breadcrumb = false,
    array $options = []
): string {
    global $plugins, $maxmultipagelinks, $jumptopagemultipage;

    // Validate basic conditions
    if ($count <= $perpage || $perpage <= 0 || $count <= 0) {
        return '';
    }

    // Run hooks
    $args = [
        'count' => &$count,
        'perpage' => &$perpage, 
        'page' => &$page,
        'url' => &$url,
        'breadcrumb' => &$breadcrumb,
    ];
    $plugins->run_hooks('multipage', $args);

    // Normalize inputs
    $page = max(1, $page);
    $url = htmlspecialchars_uni(str_replace('&amp;', '&', $url));
    $pages = (int)ceil($count / $perpage);
    $page = min($page, $pages);

    // Default options with type safety
    $defaults = [
        'size' => '',
        'alignment' => 'center',
        'show_stats' => true,
        'max_links' => $maxmultipagelinks ?: 5,
        'show_jump' => (bool)($jumptopagemultipage == 1),
        'prev_text' => '<i class="fa-solid fa-angle-left"></i>',
        'next_text' => '<i class="fa-solid fa-angle-right"></i>',
        'first_text' => '<i class="fa-solid fa-angles-left"></i>',
        'last_text' => '<i class="fa-solid fa-angles-right"></i>',
        'use_icons' => true,
    ];
    
    $options = array_merge($defaults, $options);

    // Build navigation elements with type safety
    $navigation = buildNavigationElements($url, $page, $pages, $options);
    
    // Build jump to page component
    $jumptopage = buildJumpToPage($url, $page, $pages, $options, $breadcrumb);

    // Return appropriate pagination type
    return $breadcrumb 
        ? buildBreadcrumbPagination($navigation)
        : buildNormalPagination($navigation, $jumptopage, $options, $count, $perpage, $page, $pages);
}

/**
 * Build navigation elements with type hints
 */
function buildNavigationElements(
    string $url, 
    int $currentPage, 
    int $totalPages, 
    array $options
): array {
    $prevpage = $currentPage > 1 
        ? '<a href="' . fetch_page_url($url, $currentPage - 1) . '" class="btn btn-outline-primary btn-sm">' . $options['prev_text'] . '</a>'
        : '';

    $nextpage = $currentPage < $totalPages 
        ? '<a href="' . fetch_page_url($url, $currentPage + 1) . '" class="btn btn-outline-primary btn-sm">' . $options['next_text'] . '</a>'
        : '';

    // Calculate page range
    $maxLinks = max(1, $options['max_links']);
    $from = max(1, $currentPage - (int)floor($maxLinks / 2));
    $to = min($totalPages, $from + $maxLinks - 1);
    
    // Adjust range if needed
    if ($to - $from + 1 < $maxLinks) {
        $from = max(1, $to - $maxLinks + 1);
    }

    // Build page elements
    $start = $from > 1 
        ? '<a href="' . fetch_page_url($url, 1) . '" class="btn btn-outline-secondary btn-sm">1</a>' . 
          ($from > 2 ? ' <span class="mx-1">...</span> ' : '')
        : '';

    $end = $to < $totalPages
        ? ($to < $totalPages - 1 ? ' <span class="mx-1">...</span> ' : '') .
          '<a href="' . fetch_page_url($url, $totalPages) . '" class="btn btn-outline-secondary btn-sm">' . $totalPages . '</a>'
        : '';

    // Middle pages
    $mppage = implode(' ', array_map(
        fn(int $i): string => $currentPage === $i
            ? '<a href="#" class="btn btn-primary btn-sm active">' . $i . '</a>'
            : '<a href="' . fetch_page_url($url, $i) . '" class="btn btn-outline-secondary btn-sm">' . $i . '</a>',
        range($from, $to)
    ));

    return [
        'prev' => $prevpage,
        'start' => $start,
        'middle' => $mppage,
        'end' => $end,
        'next' => $nextpage,
    ];
}

/**
 * Build jump to page component
 */
function buildJumpToPage(
    string $url, 
    int $currentPage, 
    int $totalPages, 
    array $options, 
    bool $isBreadcrumb
): string {
    if (!$options['show_jump'] || $totalPages <= $options['max_links'] + 1 || $isBreadcrumb) {
        return '';
    }

    $jumpUrl = fetch_page_url($url, 1);
    
    return <<<HTML
    <div class="dropdown d-inline-block ms-2">
        <a class="btn btn-outline-primary btn-sm dropdown-toggle d-flex align-items-center gap-1" 
           data-bs-toggle="dropdown" 
           data-bs-offset="10,5"
           aria-expanded="false">
            <i class="fa-solid fa-file-lines"></i>
        </a>
        
        <div class="dropdown-menu p-3 border-0 shadow-lg" style="
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border-radius: 12px;
            min-width: 260px;
            border: 1px solid rgba(0,0,0,0.1);
        ">
            <form action="{$jumpUrl}" method="post">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="small fw-semibold text-primary mb-0">
                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>
                        Jump to Page
                    </label>
                    <span class="badge bg-light text-dark small">of {$totalPages} total</span>
                </div>
                
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fa-solid fa-hashtag small"></i>
                            </span>
                            <input type="number" 
                                   class="form-control form-control-sm border-start-0 focus-ring focus-ring-primary" 
                                   name="page" 
                                   value="{$currentPage}" 
                                   min="1" 
                                   max="{$totalPages}"
                                   placeholder="Page #"
                                   style="border-radius: 0 6px 6px 0;">
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill">
                            <i class="fa-solid fa-arrow-right me-1"></i>
                            Go
                        </button>
                    </div>
                </div>
                
                <div class="mt-2 text-center">
                    <small class="text-muted">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Enter page number 1-{$totalPages}
                    </small>
                </div>
            </form>
        </div>
    </div>
HTML;
}

/**
 * Build breadcrumb pagination
 */
function buildBreadcrumbPagination(array $navigation): string
{
    return <<<HTML
    <div id="breadcrumb_multipage_popup" style="display: none;">
        <div class="d-flex align-items-center gap-1 flex-wrap">
            {$navigation['prev']}{$navigation['start']}{$navigation['middle']}{$navigation['end']}{$navigation['next']}
        </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        if (typeof use_xmlhttprequest !== "undefined" && use_xmlhttprequest === "1") {
            const breadcrumb = document.getElementById("breadcrumb_multipage");
            const popup = document.getElementById("breadcrumb_multipage_popup");
            
            if (breadcrumb && popup) {
                breadcrumb.addEventListener("click", (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (popup.style.display === "block") {
                        popup.style.display = "none";
                    } else {
                        const rect = breadcrumb.getBoundingClientRect();
                        Object.assign(popup.style, {
                            position: "fixed",
                            top: \`\${rect.bottom + window.scrollY}px\`,
                            left: \`\${rect.left + window.scrollX}px\`,
                            zIndex: "1000",
                            display: "block"
                        });
                    }
                });
                
                document.addEventListener("click", (e) => {
                    if (!popup.contains(e.target) && e.target !== breadcrumb) {
                        popup.style.display = "none";
                    }
                });
                
                document.addEventListener("keydown", (e) => {
                    if (e.key === "Escape") {
                        popup.style.display = "none";
                    }
                });
            }
        }
    });
    </script>
HTML;
}

/**
 * Build normal pagination with stats
 */
function buildNormalPagination(
    array $navigation,
    string $jumptopage,
    array $options,
    int $count,
    int $perpage,
    int $currentPage,
    int $totalPages
): string {
    $pagesText = "Pages ({$totalPages}):";
    
    $stats = '';
    if ($options['show_stats']) {
        $startItem = (($currentPage - 1) * $perpage) + 1;
        $endItem = min($currentPage * $perpage, $count);
        $stats = <<<HTML
        <div class="text-muted small">
            Showing <strong>{$startItem}</strong> to <strong>{$endItem}</strong> of <strong>{$count}</strong> entries
        </div>
HTML;
    }

    return <<<HTML
    <div class="pagination-wrapper">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="pages text-muted small fw-semibold">{$pagesText}</span>
                <div class="d-flex align-items-center gap-1">
                    {$navigation['prev']}{$navigation['start']}{$navigation['middle']}{$navigation['end']}{$navigation['next']}{$jumptopage}
                </div>
            </div>
            {$stats}
        </div>
    </div>
HTML;
}

?>