<?php


/**
 * Detect browser type and version
 * 
 * @param string $browser Browser name to check
 * @param string|int $version Minimum version to check (optional)
 * @return mixed Returns version as string if found and meets criteria, false otherwise
 */
function is_browser(string $browser, $version = 0): mixed
{
    static $is = null;
    
    if ($is === null) {
        $useragent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $is = [
            'opera' => 0, 
            'ie' => 0, 
            'edge' => 0,
            'chrome' => 0,
            'mozilla' => 0, 
            'firebird' => 0, 
            'firefox' => 0, 
            'camino' => 0, 
            'konqueror' => 0, 
            'safari' => 0, 
            'webkit' => 0, 
            'webtv' => 0, 
            'netscape' => 0, 
            'mac' => 0
        ];

        // Detect Opera (including newer versions)
        if (str_contains($useragent, 'opera') || str_contains($useragent, 'opr/')) {
            if (preg_match('#(?:opera|opr)[/ ]([0-9\.]+)#', $useragent, $regs)) {
                $is['opera'] = $regs[1];
            }
        }

        // Detect Internet Explorer
        if (str_contains($useragent, 'msie ') && !$is['opera']) {
            if (preg_match('#msie ([0-9\.]+)#', $useragent, $regs)) {
                $is['ie'] = $regs[1];
            }
        } 
        // Detect IE 11+ with Trident
        elseif (str_contains($useragent, 'trident/')) {
            if (preg_match('#rv:([0-9\.]+)#', $useragent, $regs)) {
                $is['ie'] = $regs[1];
            }
        }

        // Detect Microsoft Edge (Chromium-based and legacy)
        if (str_contains($useragent, 'edge/')) {
            if (preg_match('#edge/([0-9\.]+)#', $useragent, $regs)) {
                $is['edge'] = $regs[1];
            }
        } elseif (str_contains($useragent, 'edg/')) {
            if (preg_match('#edg/([0-9\.]+)#', $useragent, $regs)) {
                $is['edge'] = $regs[1];
            }
        }

        // Detect Chrome
        if (str_contains($useragent, 'chrome/') && !$is['edge']) {
            if (preg_match('#chrome/([0-9\.]+)#', $useragent, $regs)) {
                $is['chrome'] = $regs[1];
            }
        }

        // Detect macOS
        if (str_contains($useragent, 'mac')) {
            $is['mac'] = 1;
        }

        // Detect WebKit-based browsers (Safari, etc.)
        if (str_contains($useragent, 'applewebkit')) {
            if (preg_match('#applewebkit/(\d+)#', $useragent, $regs)) {
                $is['webkit'] = $regs[1];
                if (str_contains($useragent, 'safari') && !$is['chrome']) {
                    if (preg_match('#version/([0-9\.]+)#', $useragent, $regs)) {
                        $is['safari'] = $regs[1];
                    }
                }
            }
        }

        // Detect Konqueror
        if (str_contains($useragent, 'konqueror')) {
            if (preg_match('#konqueror/([0-9\.-]+)#', $useragent, $regs)) {
                $is['konqueror'] = $regs[1];
            }
        }

        // Detect Gecko-based browsers (Firefox, etc.)
        if (str_contains($useragent, 'gecko') && !$is['webkit'] && !$is['konqueror']) {
            if (preg_match('#gecko/(\d+)#', $useragent, $regs)) {
                $is['mozilla'] = $regs[1];
                
                // Detect Firefox and variants
                if (preg_match('#(?:firefox|firebird|phoenix)[ /]([0-9\.]+)#', $useragent, $regs)) {
                    $is['firebird'] = $regs[1];
                    if (str_contains($useragent, 'firefox')) {
                        $is['firefox'] = $regs[1];
                    }
                }
                
                // Detect Camino/Chimera
                if (preg_match('#(?:chimera|camino)/([0-9\.]+)#', $useragent, $regs)) {
                    $is['camino'] = $regs[1];
                }
            }
        }

        // Detect WebTV
        if (str_contains($useragent, 'webtv')) {
            if (preg_match('#webtv/([0-9\.]+)#', $useragent, $regs)) {
                $is['webtv'] = $regs[1];
            }
        }

        // Detect old Netscape
        if (preg_match('#mozilla/([1-4]{1})\.([0-9]{2}|[1-8]{1})#', $useragent, $regs)) {
            $is['netscape'] = $regs[1] . '.' . $regs[2];
        }
    }

    // Normalize browser name
    $browser = strtolower($browser);
    if (str_starts_with($browser, 'is_')) {
        $browser = substr($browser, 3);
    }

    // Check if browser exists in our detection
    if (isset($is[$browser]) && $is[$browser]) {
        if ($version) {
            // Compare versions
            if (version_compare((string)$is[$browser], (string)$version, '>=')) {
                return $is[$browser];
            }
            return false;
        }
        return $is[$browser];
    }

    return false;
}

/**
 * Simplified browser check for common cases
 * 
 * @param string $browser Browser to check
 * @return bool True if browser matches
 */
function is_browser_simple(string $browser): bool
{
    $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    return match(strtolower($browser)) {
        'ie', 'internetexplorer', 'internet_explorer' => 
            str_contains($useragent, 'MSIE') || 
            str_contains($useragent, 'Trident'),
        'edge' => 
            str_contains($useragent, 'Edge') || 
            str_contains($useragent, 'Edg/'),
        'firefox' => str_contains($useragent, 'Firefox'),
        'chrome' => str_contains($useragent, 'Chrome') && 
                   !str_contains($useragent, 'Edg/'),
        'safari' => str_contains($useragent, 'Safari') && 
                   !str_contains($useragent, 'Chrome'),
        'opera' => str_contains($useragent, 'Opera') || 
                  str_contains($useragent, 'OPR/'),
        default => false
    };
}

if (!defined('IN_TRACKER')) {
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}
?>