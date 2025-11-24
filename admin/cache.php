<?php


declare(strict_types=1);

define("IN_MYBB", 1);
define("IN_ADMINCP", 1);

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Initialize input parameters
$mybb->input['action'] ??= '';
$mybb->input['do'] ??= '';
$mybb->input['module'] ??= '';
$mybb->input['title'] ??= '';

$plugins->run_hooks("admin_tools_cache_begin");

switch($mybb->input['action']) {
    case 'view':
        handleCacheView();
        break;
        
    case 'rebuild':
    case 'reload':
        handleCacheRebuild();
        break;
        
    case 'rebuild_all':
        handleCacheRebuildAll();
        break;
        
    default:
        handleCacheManager();
        break;
}

/**
 * Handle cache view action
 */
function handleCacheView(): void
{
    global $mybb, $db, $plugins;
    
    if(empty(trim($mybb->input['title'] ?? ''))) {
        flash_message('No cache specified', 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_view");

    // Get cache item
    $cacheItem = getCacheItem($mybb->input['title']);
    
    if(!$cacheItem) {
        flash_message('Cache not found', 'error');
        admin_redirect("index.php?act=cache");
    }

    // Process cache contents
    $cacheContents = processCacheContents($cacheItem['cache']);

    displayCacheView($cacheItem, $cacheContents);
}

/**
 * Get cache item data
 */
function getCacheItem(string $title): ?array
{
    global $db, $mybb;
    
    if($title === 'settings') {
        $cachedSettings = (array)$mybb->settings;
        unset($cachedSettings['internal']);
        
        return [
            'title' => 'settings',
            'cache' => my_serialize($cachedSettings)
        ];
    }
    
    $query = $db->simple_select("datacache", "*", "title = '" . $db->escape_string($title) . "'");
    return $db->fetch_array($query) ?: null;
}

/**
 * Process cache contents for display
 */
function processCacheContents(string $cacheData): string
{
    $cacheContents = native_unserialize($cacheData);
    
    if(empty($cacheContents)) {
        return 'Cache is empty';
    }
    
    ob_start();
    print_r($cacheContents);
    $contents = htmlspecialchars_uni(ob_get_clean());
    
    return $contents;
}

/**
 * Display cache view
 */
function displayCacheView(array $cacheItem, string $cacheContents): void
{
    stdhead();
    
    echo '
    <div class="container mt-3">
       
            
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-database me-2"></i>
                                Cache: ' . htmlspecialchars_uni($cacheItem['title']) . '
                            </h4>
                            <a href="index.php?act=cache" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div>
                            <pre class="mb-0" style="font-size: 0.875rem; line-height: 1.4; max-height: 600px; overflow: auto;">' . $cacheContents . '</pre>
                        </div>
                    </div>
                </div>
           
        
    </div>';
    
    stdfoot();
}

/**
 * Handle cache rebuild/reload actions
 */
function handleCacheRebuild(): void
{
    global $mybb, $cache, $plugins;
    
    $title = $mybb->input['title'] ?? '';
    $action = $mybb->input['action'];
    
    if(empty($title)) {
        flash_message('No cache specified', 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_{$action}");

    // Handle settings cache separately
    if($title === 'settings') {
        rebuild_settings();
        $plugins->run_hooks("admin_tools_cache_rebuild_commit");
        log_admin_action($title);
        flash_message('The cache has been reloaded successfully', 'success');
        admin_redirect("index.php?act=cache");
    }

    // Try different rebuild methods (как в оригинале)
    if(method_exists($cache, "update_{$title}")) {
        $func = "update_{$title}";
        $cache->$func();
    }
    elseif(method_exists($cache, "reload_{$title}")) {
        $func = "reload_{$title}";
        $cache->$func();
    }
    elseif(function_exists("update_{$title}")) {
        $func = "update_{$title}";
        $func();
    }
    elseif(function_exists("reload_{$title}")) {
        $func = "reload_{$title}";
        $func();
    }
    else {
        flash_message('This cache cannot be rebuilt', 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_rebuild_commit");
    log_admin_action($title);
    flash_message('The cache has been ' . ($action === 'rebuild' ? 'rebuilt' : 'reloaded') . ' successfully', 'success');
    admin_redirect("index.php?act=cache");
}

/**
 * Handle rebuild all caches action
 */
function handleCacheRebuildAll(): void
{
    global $db, $cache, $plugins, $mybb;
    
    if(!verify_post_check($mybb->get_input('my_post_key'))) {
        flash_message('Invalid security token', 'error');
        admin_redirect("index.php?act=cache");
    }

    $plugins->run_hooks("admin_tools_cache_rebuild_all");

    // Rebuild all datacache items (как в оригинале)
    $query = $db->simple_select("datacache");
    while($cacheitem = $db->fetch_array($query)) {
        if(method_exists($cache, "update_{$cacheitem['title']}")) {
            $func = "update_{$cacheitem['title']}";
            $cache->$func();
        }
        elseif(method_exists($cache, "reload_{$cacheitem['title']}")) {
            $func = "reload_{$cacheitem['title']}";
            $cache->$func();
        }
        elseif(function_exists("update_{$cacheitem['title']}")) {
            $func = "update_{$cacheitem['title']}";
            $func();
        }
        elseif(function_exists("reload_{$cacheitem['title']}")) {
            $func = "reload_{$cacheitem['title']}";
            $func();
        }
    }

    // Rebuild settings
    rebuild_settings();

    $plugins->run_hooks("admin_tools_cache_rebuild_all_commit");
    log_admin_action();
    flash_message('All caches have been rebuilt successfully', 'success');
    admin_redirect("index.php?act=cache");
}

/**
 * Display main cache manager
 */
function handleCacheManager(): void
{
    global $db, $cache, $plugins, $mybb;
    
    $plugins->run_hooks("admin_tools_cache_start");
    
    stdhead();
    
    echo '
    <div class="container mt-3">
        <div class="row mb-4">
            <div class="col">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3 rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    Cache Manager
                                </h4>
                                <small class="opacity-75">Manage system caches</small>
                            </div>
                            <form method="post" action="index.php?act=cache&action=rebuild_all" class="mb-0">
                                <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
                                <button type="submit" class="btn btn-warning" onclick="return confirm(\'Are you sure you want to rebuild all caches?\')">
                                    <i class="fas fa-sync-alt me-1"></i> Rebuild All Caches
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">
                                            <i class="fas fa-cube me-2"></i>Cache Name
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-weight-hanging me-2"></i>Size
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-cogs me-2"></i>Controls
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>';
    
    // Display settings cache first
    displaySettingsCacheRow();
    
    // Display other caches from database
    $query = $db->simple_select("datacache", "*", "", ["order_by" => "title"]);
    while($cacheitem = $db->fetch_array($query)) {
        displayCacheRow($cacheitem);
    }
    
    echo '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    stdfoot();
}

/**
 * Display settings cache row
 */
function displaySettingsCacheRow(): void
{
    global $mybb;
    
    echo '
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-cogs text-success me-3"></i>
                <div>
                    <a href="index.php?act=cache&action=view&title=settings" 
                       class="fw-bold text-decoration-none text-dark">
                       settings
                    </a>
                    <br>
                    <small class="text-muted">Forum configuration settings</small>
                </div>
            </div>
        </td>
        <td class="text-center">
            <span class="badge bg-secondary">N/A</span>
        </td>
        <td class="text-center">
            <a href="index.php?act=cache&action=view&title=settings" 
               class="btn btn-sm btn-outline-primary me-1" 
               title="View Cache">
               <i class="fas fa-eye"></i>
            </a>
            <a href="index.php?act=cache&action=reload&title=settings&my_post_key=' . $mybb->post_code . '" 
               class="btn btn-sm btn-outline-info" 
               title="Reload Cache">
               <i class="fas fa-sync-alt"></i>
            </a>
        </td>
    </tr>';
}

/**
 * Display cache table row
 */
function displayCacheRow(array $cacheitem): void
{
    global $cache, $mybb;
    
    $size = mksize(strlen($cacheitem['cache']));
    $controls = getCacheControls($cacheitem['title']);
    
    echo '
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-database text-primary me-3"></i>
                <div>
                    <a href="index.php?act=cache&action=view&title=' . urlencode($cacheitem['title']) . '" 
                       class="fw-bold text-decoration-none text-dark">
                       ' . htmlspecialchars_uni($cacheitem['title']) . '
                    </a>
                </div>
            </div>
        </td>
        <td class="text-center">
            <span class="badge bg-secondary">' . $size . '</span>
        </td>
        <td class="text-center">
            ' . $controls . '
        </td>
    </tr>';
}

/**
 * Get cache control buttons (сохраняем логику оригинала)
 */
function getCacheControls(string $title): string
{
    global $cache, $mybb;
    
    $controls = [];
    
    // View button (always available)
    $controls[] = '
    <a href="index.php?act=cache&action=view&title=' . urlencode($title) . '" 
       class="btn btn-sm btn-outline-primary me-1" 
       title="View Cache">
       <i class="fas fa-eye"></i>
    </a>';
    
    // Rebuild/Reload buttons (только если методы существуют - как в оригинале)
    if(method_exists($cache, "update_{$title}")) {
        $controls[] = '
        <a href="index.php?act=cache&action=rebuild&title=' . urlencode($title) . '&my_post_key=' . $mybb->post_code . '" 
           class="btn btn-sm btn-outline-warning" 
           title="Rebuild Cache">
           <i class="fas fa-hammer"></i>
        </a>';
    }
    elseif(method_exists($cache, "reload_{$title}")) {
        $controls[] = '
        <a href="index.php?act=cache&action=reload&title=' . urlencode($title) . '&my_post_key=' . $mybb->post_code . '" 
           class="btn btn-sm btn-outline-info" 
           title="Reload Cache">
           <i class="fas fa-sync-alt"></i>
        </a>';
    }
    elseif(function_exists("update_{$title}")) {
        $controls[] = '
        <a href="index.php?act=cache&action=rebuild&title=' . urlencode($title) . '&my_post_key=' . $mybb->post_code . '" 
           class="btn btn-sm btn-outline-warning" 
           title="Rebuild Cache">
           <i class="fas fa-hammer"></i>
        </a>';
    }
    elseif(function_exists("reload_{$title}")) {
        $controls[] = '
        <a href="index.php?act=cache&action=reload&title=' . urlencode($title) . '&my_post_key=' . $mybb->post_code . '" 
           class="btn btn-sm btn-outline-info" 
           title="Reload Cache">
           <i class="fas fa-sync-alt"></i>
        </a>';
    }
    // Если нет методов - оставляем пусто (как в оригинале)
    
    return implode('', $controls);
}