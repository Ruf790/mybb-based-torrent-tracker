<?php

declare(strict_types=1);

/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="error-message">❌ Error! Direct initialization of this file is not allowed.</div>');
}

define('IPS_VERSION', 'v0.2 by xam');

/**
 * IP Search Manager with varbinary(16) support
 */
class IPSearchManager
{
    private $db;
    private string $baseUrl;
    private string $picBaseUrl;
    private string $scriptUrl;
    
    public function __construct($database, string $baseUrl, string $picBaseUrl)
    {
        $this->db = $database;
        $this->baseUrl = $baseUrl;
        $this->picBaseUrl = $picBaseUrl;
        $this->scriptUrl = $_SERVER['SCRIPT_NAME'] ?? '';
    }
    
    /**
     * Get action from request
     */
    public function getAction(): string
    {
        return $_POST['do'] ?? $_GET['do'] ?? '';
    }
    
    /**
     * Get IP from request
     */
    public function getIpAddress(): string
    {
        $ip = $_POST['ip'] ?? $_GET['ip'] ?? '';
        return trim($ip);
    }
    
    /**
     * Validate IP address (both IPv4 and IPv6)
     */
    public function validateIp(string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }
        
        // Try IPv4 first
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }
        
        // Try IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert IP to varbinary(16) format for MySQL
     */
    public function ipToBinary(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4 to binary (INET6_ATON compatible)
            $binary = inet_pton($ip);
            return $binary ? $binary : '';
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6 to binary
            $binary = inet_pton($ip);
            return $binary ? $binary : '';
        }
        
        return '';
    }
    
    /**
     * Convert binary IP to readable format
     */
    public function binaryToIp(string $binary): string
    {
        if (empty($binary)) {
            return 'N/A';
        }
        
        $ip = inet_ntop($binary);
        return $ip ? $ip : bin2hex($binary);
    }
    
    /**
     * Search IP in database with varbinary(16) support
     */
    public function searchIp(string $ip): array
    {
        $results = [
            'users_table' => null,
            'ip_log_table' => null,
            'error' => null
        ];
        
        // Convert IP to binary format
        $ipBinary = $this->ipToBinary($ip);
        if (empty($ipBinary)) {
            $results['error'] = 'Invalid IP address format.';
            return $results;
        }
        
        // Escape binary data for SQL
        $escapedBinary = $this->escapeBinary($ipBinary);
        
        // Search in users table (registration IP) - using binary comparison
        $query1 = $this->db->sql_query(
            "SELECT u.*, g.namestyle 
             FROM users u 
             LEFT JOIN usergroups g ON (u.usergroup = g.gid) 
             WHERE u.regip = {$escapedBinary}"
        );
        
        // Search in iplog table (login IP history)
        // Assuming iplog.ip is also varbinary(16)
        $query2 = $this->db->sql_query(
            "SELECT DISTINCT u.*, g.namestyle 
             FROM iplog i 
             LEFT JOIN users u ON (i.userid = u.id) 
             LEFT JOIN usergroups g ON (u.usergroup = g.gid) 
             WHERE i.ip = " . $this->db->sqlesc($ip)
             
        );
        
        if ($this->db->num_rows($query1) === 0 && $this->db->num_rows($query2) === 0) {
            $results['error'] = 'No registered users found with this IP address.';
        } else {
            $results['users_table'] = $query1;
            $results['ip_log_table'] = $query2;
        }
        
        return $results;
    }
    
    /**
     * Escape binary data for SQL query
     */
    private function escapeBinary(string $binary): string
    {
        // Use MySQL's UNHEX function for binary data
        $hex = bin2hex($binary);
        return "UNHEX('{$hex}')";
    }
    
    /**
     * Format date for display
     */
    private function formatDateTime(string $dateTime, string $format = 'Y-m-d H:i:s'): string
    {
        if ($dateTime === '0000-00-00 00:00:00' || empty($dateTime)) {
            return 'N/A';
        }
        
        try {
            $date = new DateTime($dateTime);
            return $date->format($format);
        } catch (Exception $e) {
            return 'Invalid Date';
        }
    }
    
    /**
     * Format IP for display (convert from binary if needed)
     */
    private function formatIpForDisplay($user): string
    {
        // Check if ip field is binary
        if (isset($user['ip']) && !empty($user['ip'])) {
            // If it looks like binary data, convert it
            if (strlen($user['ip']) <= 16 && !preg_match('/^[0-9.]+$/', $user['ip'])) {
                return $this->binaryToIp($user['ip']);
            }
            return htmlspecialchars_uni($user['ip']);
        }
        return 'N/A';
    }
    
    /**
     * Render user table
     */
    public function renderUserTable($query, string $title = ''): string
    {
        global $dateformat, $timeformat;
        
        $html = '';
        
        if ($title) {
            $html .= <<<HTML
                <div class="results-section">
                    <h3 class="section-title">
                        <i class="fas fa-search"></i> {$title}
                    </h3>
            HTML;
        }
        
       $html .= <<<HTML
    <div class="table-responsive">
        <table class="users-table">
            <thead>
                <tr>
                    <th><i class="fas fa-user"></i> Username</th>
                    <th><i class="fas fa-envelope"></i> Email</th>
                    <th><i class="fas fa-network-wired"></i> IP</th>
                    <th><i class="fas fa-key"></i> Passkey</th>
                    <th><i class="fas fa-clock"></i> Last Seen</th>
                    <th><i class="fas fa-calendar-plus"></i> Registered</th>
                    <th><i class="fas fa-arrow-up"></i> Uploaded</th>
                    <th><i class="fas fa-arrow-down"></i> Downloaded</th>
                    <th><i class="fas fa-percentage"></i> Ratio</th>
                </tr>
            </thead>
            <tbody>
HTML;
        
        if ($this->db->num_rows($query) === 0) {
            $html .= <<<HTML
                <tr>
                    <td colspan="9" class="no-results">
                        <i class="fas fa-search-minus"></i> No results found
                    </td>
                </tr>
            HTML;
        } else {
            require_once INC_PATH . '/functions_ratio.php';
            
            while ($user = mysqli_fetch_assoc($query)) {
                $lastSeen = $this->formatDateTime($user['lastactive'], "$dateformat $timeformat");
                $joinDate = $this->formatDateTime($user['added'], "$dateformat $timeformat");
                
                $usernameHtml = get_user_color($user['username'], $user['namestyle']);
                $email = htmlspecialchars_uni($user['email']);
                $ip = $this->formatIpForDisplay($user);
                $passkey = htmlspecialchars_uni($user['passkey']);
                $uploaded = mksize($user['uploaded']);
                $downloaded = mksize($user['downloaded']);
                $ratio = get_user_ratio($user['uploaded'], $user['downloaded']);
                
                $resetPasskeyUrl = "{$this->scriptUrl}&do=2&passkey=" . urlencode($passkey);
                
                $html .= <<<HTML
                    <tr class="user-row">
                        <td class="username-cell">
                            <a href="{$this->baseUrl}/userdetails.php?id={$user['id']}" class="user-link">
                                {$usernameHtml}
                            </a>
                        </td>
                        <td class="email-cell" title="{$email}">{$email}</td>
                        <td class="ip-cell">
                            <span class="ip-address" title="{$ip}">{$ip}</span>
                            <span class="ip-type-badge" data-ip-type="{$this->getIpType($ip)}">
                                {$this->getIpTypeBadge($ip)}
                            </span>
                        </td>
                        <td class="passkey-cell">
                            <div class="passkey-container">
                                <span class="passkey-value" title="{$passkey}">
                                    {$passkey}
                                </span>
                                <a href="{$resetPasskeyUrl}" class="reset-link" title="Reset Passkey">
                                    <i class="fas fa-redo"></i>
                                </a>
                            </div>
                        </td>
                        <td class="lastseen-cell">{$lastSeen}</td>
                        <td class="registered-cell">{$joinDate}</td>
                        <td class="uploaded-cell" data-value="{$user['uploaded']}">{$uploaded}</td>
                        <td class="downloaded-cell" data-value="{$user['downloaded']}">{$downloaded}</td>
                        <td class="ratio-cell">{$ratio}</td>
                    </tr>
                HTML;
            }
        }
        
        $html .= <<<HTML
                    </tbody>
                </table>
            </div>
        HTML;
        
        if ($title) {
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Get IP type (IPv4/IPv6)
     */
    private function getIpType(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'ipv4';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'ipv6';
        }
        return 'unknown';
    }
    
    /**
     * Get badge for IP type
     */
    private function getIpTypeBadge(string $ip): string
    {
        $type = $this->getIpType($ip);
        $badges = [
            'ipv4' => '<span class="ipv4-badge">IPv4</span>',
            'ipv6' => '<span class="ipv6-badge">IPv6</span>',
            'unknown' => '<span class="unknown-badge">?</span>'
        ];
        return $badges[$type] ?? '';
    }
    
    /**
     * Render search form
     */
    public function renderSearchForm(string $currentIp = ''): string
    {
        $loadingLayer = <<<HTML
            <div id="loading-layer" class="loading-overlay">
                <div class="loading-content">
                    <div class="loading-spinner">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </div>
                    <div class="loading-text">Scanning Database...</div>
                </div>
            </div>
        HTML;
        
        return <<<HTML
            <div class="container mt-3">
                <div class="search-header">
                    <h2><i class="fas fa-search-location"></i> IP Address Search</h2>
                    <p class="search-description">
                        Search for users by IP address (supports both IPv4 and IPv6)
                    </p>
                </div>
                
                <div class="search-form-container">
                    {$loadingLayer}
                    
                    <form method="post" action="{$this->scriptUrl}" class="search-form" id="ip-search-form">
                        <input type="hidden" name="act" value="ipsearch">
                        <input type="hidden" name="do" value="1">
                        
                        <div class="form-group">
                            <label for="ip-address">
                                <i class="fas fa-ip-address"></i> IP Address
                            </label>
                            <div class="input-with-button">
                                <input type="text" 
                                       id="ip-address" 
                                       name="ip" 
                                       value="{$currentIp}"
                                       placeholder="Enter IPv4 or IPv6 address"
                                       class="form-control"
                                       required
                                       pattern="^([0-9]{1,3}\.){3}[0-9]{1,3}$|^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$"
                                       title="Enter a valid IPv4 or IPv6 address">
                                <button type="submit" 
                                        class="btn btn-primary"
                                        onclick="showLoadingLayer()">
                                    <i class="fas fa-search"></i> Search IP
                                </button>
                            </div>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i> Supports both IPv4 (192.168.1.1) and IPv6 (2001:db8::1)
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="search-examples">
                    <h4><i class="fas fa-lightbulb"></i> Examples:</h4>
                    <div class="example-container">
                        <div class="ip-type-group">
                            <div class="ip-type-label">IPv4 Examples:</div>
                            <div class="example-ips">
                                <span class="example-ip ipv4-example" data-ip="192.168.1.1">192.168.1.1</span>
                                <span class="example-ip ipv4-example" data-ip="10.0.0.1">10.0.0.1</span>
                                <span class="example-ip ipv4-example" data-ip="172.16.0.1">172.16.0.1</span>
                            </div>
                        </div>
                        <div class="ip-type-group">
                            <div class="ip-type-label">IPv6 Examples:</div>
                            <div class="example-ips">
                                <span class="example-ip ipv6-example" data-ip="2001:db8::1">2001:db8::1</span>
                                <span class="example-ip ipv6-example" data-ip="fe80::1">fe80::1</span>
                                <span class="example-ip ipv6-example" data-ip="::1">::1</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
    
    /**
     * Render error message
     */
    public function renderErrorMessage(string $message): string
    {
        return <<<HTML
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="error-content">
                    <h3>Search Error</h3>
                    <p>{$message}</p>
                </div>
            </div>
        HTML;
    }
    
    /**
     * Get JavaScript for loading layer and IP examples
     */
    public function getLoadingJavaScript(): string
    {
        return <<<JAVASCRIPT
            <script>
                function showLoadingLayer() {
                    const loadingLayer = document.getElementById('loading-layer');
                    if (loadingLayer) {
                        loadingLayer.style.display = 'flex';
                        // Hide after 3 seconds if still showing
                        setTimeout(() => {
                            loadingLayer.style.display = 'none';
                        }, 3000);
                    }
                }
                
                // Hide loading layer when page loads
                document.addEventListener('DOMContentLoaded', function() {
                    const loadingLayer = document.getElementById('loading-layer');
                    if (loadingLayer) {
                        loadingLayer.style.display = 'none';
                    }
                    
                    // Add click handlers to example IPs
                    document.querySelectorAll('.example-ip').forEach(ipElement => {
                        ipElement.addEventListener('click', function() {
                            const ip = this.getAttribute('data-ip');
                            const input = document.getElementById('ip-address');
                            if (input) {
                                input.value = ip;
                                input.focus();
                            }
                        });
                    });
                });
                
                // Form submission handler
                document.getElementById('ip-search-form')?.addEventListener('submit', function(e) {
                    const ipInput = document.getElementById('ip-address');
                    if (ipInput && !ipInput.checkValidity()) {
                        ipInput.reportValidity();
                        e.preventDefault();
                        return false;
                    }
                    showLoadingLayer();
                    return true;
                });
                
                // Auto-format IPv6 addresses
                document.getElementById('ip-address')?.addEventListener('blur', function() {
                    let ip = this.value.trim();
                    if (ip.includes(':') && !ip.includes('::')) {
                        // Check if it's a valid IPv6 without compression
                        if (/^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/.test(ip)) {
                            // Compress the IPv6 address
                            const parts = ip.split(':');
                            let longestZeroStart = -1;
                            let longestZeroLength = 0;
                            let currentZeroStart = -1;
                            let currentZeroLength = 0;
                            
                            for (let i = 0; i < parts.length; i++) {
                                if (parts[i] === '0000' || parts[i] === '0') {
                                    if (currentZeroStart === -1) {
                                        currentZeroStart = i;
                                    }
                                    currentZeroLength++;
                                } else {
                                    if (currentZeroLength > longestZeroLength) {
                                        longestZeroLength = currentZeroLength;
                                        longestZeroStart = currentZeroStart;
                                    }
                                    currentZeroStart = -1;
                                    currentZeroLength = 0;
                                }
                            }
                            
                            if (currentZeroLength > longestZeroLength) {
                                longestZeroLength = currentZeroLength;
                                longestZeroStart = currentZeroStart;
                            }
                            
                            if (longestZeroLength > 1) {
                                const compressedParts = [];
                                for (let i = 0; i < parts.length; i++) {
                                    if (i === longestZeroStart) {
                                        if (i === 0) {
                                            compressedParts.push('');
                                        }
                                        compressedParts.push('');
                                        i += longestZeroLength - 1;
                                        if (i === parts.length - 1) {
                                            compressedParts.push('');
                                        }
                                    } else {
                                        // Remove leading zeros from each part
                                        let part = parts[i];
                                        part = part.replace(/^0+/, '');
                                        compressedParts.push(part || '0');
                                    }
                                }
                                this.value = compressedParts.join(':');
                            }
                        }
                    }
                });
            </script>
        JAVASCRIPT;
    }
}

// Main execution
function main(): void
{
    global $db, $BASEURL, $pic_base_url, $_this_script_;
    
    stdhead('IP Search');
    
    echo '<div class="ip-search-container">';
    
    try {
        $manager = new IPSearchManager($db, $BASEURL, $pic_base_url ?? '');
        $action = $manager->getAction();
        $ip = $manager->getIpAddress();
        
        echo '<div class="container mt-4">';
		echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h1><i class="fas fa-address-card"></i> IP Address Search</h1>';
        echo '<div class="version">' . IPS_VERSION . '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        
        if ($action === '1' || !empty($_GET['ip'])) {
            if (!$manager->validateIp($ip)) {
                echo $manager->renderErrorMessage('Please enter a valid IPv4 or IPv6 address.');
                echo $manager->renderSearchForm($ip);
            } else {
                $results = $manager->searchIp($ip);
                
                if ($results['error']) {
                    echo $manager->renderErrorMessage($results['error']);
                    echo $manager->renderSearchForm($ip);
                } else {
                    // Show results from users table
                    if ($results['users_table']) {
                        echo $manager->renderUserTable(
                            $results['users_table'],
                            "Search Results in Users Table: " . htmlspecialchars($ip)
                        );
                    }
                    
                    // Show results from iplog table
                    if ($results['ip_log_table']) {
                        echo '<div class="results-spacer"></div>';
                        echo $manager->renderUserTable(
                            $results['ip_log_table'],
                            "Search Results in IP Log Table: " . htmlspecialchars($ip)
                        );
                    }
                    
                    echo '<div class="new-search-link">';
                    echo '<a href="' . $_this_script_ . '" class="btn btn-primary">';
                    echo '<i class="fas fa-search-plus"></i> New Search';
                    echo '</a>';
                    echo '</div>';
                }
            }
        } else {
            echo $manager->renderSearchForm();
        }
        
        echo '</div>';
        echo '</div>';
        
        echo $manager->getLoadingJavaScript();
        
    } catch (Exception $e) {
        echo '<div class="error-message">';
        echo '<i class="fas fa-exclamation-triangle"></i> Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    
    echo '</div>';
	 echo '</div>';
    


echo '</div>';

// Add CSS styles with consistent indentation
echo <<<CSS
<style>
    .ip-search-container {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .main-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
        overflow: hidden;
    }
    
   
    
    .card-header h1 {
        margin: 0;
        font-size: 2rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .version {
        background: rgba(255, 255, 255, 0.2);
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
   
    
    .search-container {
        padding: 2rem 0;
    }
    
    .search-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .search-header h2 {
        color: #374151;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .search-description {
        color: #6b7280;
        font-size: 1.1rem;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .search-form-container {
        max-width: 600px;
        margin: 0 auto 3rem;
        position: relative;
    }
    
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(5px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        border-radius: 15px;
    }
    
    .loading-content {
        text-align: center;
    }
    
    .loading-spinner {
        font-size: 3rem;
        color: #6366f1;
        margin-bottom: 1rem;
    }
    
    .loading-text {
        font-weight: 600;
        color: #374151;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .input-with-button {
        display: flex;
        gap: 10px;
        margin-bottom: 0.5rem;
    }
    
    .form-control {
        flex: 1;
        padding: 14px 20px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 16px;
        transition: all 0.3s;
        font-family: 'Courier New', monospace;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }
    
    .search-button {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }
    
    .search-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
    }
    
    .form-hint {
        color: #9ca3af;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 0.5rem;
    }
    
    .search-examples {
        background: #f9fafb;
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .search-examples h4 {
        margin: 0 0 1rem 0;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .example-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .ip-type-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .ip-type-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #6b7280;
    }
    
    .example-ips {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .example-ip {
        background: white;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .ipv4-example {
        color: #3b82f6;
        border-color: #93c5fd;
    }
    
    .ipv4-example:hover {
        background: #dbeafe;
        border-color: #3b82f6;
    }
    
    .ipv6-example {
        color: #8b5cf6;
        border-color: #c4b5fd;
    }
    
    .ipv6-example:hover {
        background: #ede9fe;
        border-color: #8b5cf6;
    }
    
    .results-section {
        margin-bottom: 3rem;
    }
    
    .results-spacer {
        height: 2rem;
    }
    
    .section-title {
        color: #374151;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .table-responsive {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        margin-bottom: 2rem;
    }
    
    .users-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        min-width: 1000px;
    }
    
    .users-table thead {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    }
    
    .users-table th {
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
        white-space: nowrap;
        display: table-cell;
        vertical-align: middle;
    }
    
    .users-table th i {
        margin-right: 8px;
        color: #6366f1;
    }
    
    .users-table tbody tr {
        transition: background-color 0.2s;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .users-table tbody tr:hover {
        background-color: #f9fafb;
    }
    
    .users-table td {
        padding: 14px 20px;
        color: #4b5563;
        vertical-align: middle;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .no-results {
        text-align: center;
        color: #9ca3af;
        padding: 3rem !important;
        display: table-cell;
    }
    
    .no-results i {
        margin-right: 8px;
    }
    
    .username-cell {
        min-width: 150px;
    }
    
    .user-link {
        color: #374151;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }
    
    .user-link:hover {
        color: #6366f1;
    }
    
    .email-cell,
    .ip-cell {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .ip-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .ip-address {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        font-family: 'Courier New', monospace;
    }
    
    .ip-type-badge {
        flex-shrink: 0;
    }
    
    .ipv4-badge {
        background: #dbeafe;
        color: #1d4ed8;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .ipv6-badge {
        background: #ede9fe;
        color: #5b21b6;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .unknown-badge {
        background: #f3f4f6;
        color: #6b7280;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .passkey-cell {
        min-width: 250px;
    }
    
    .passkey-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .passkey-value {
        flex: 1;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        color: #6b7280;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .reset-link {
        color: #ef4444;
        text-decoration: none;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background-color 0.2s;
        display: inline-block;
    }
    
    .reset-link:hover {
        background-color: #fee2e2;
    }
    
    .lastseen-cell,
    .registered-cell {
        min-width: 140px;
        white-space: nowrap;
    }
    
    .uploaded-cell,
    .downloaded-cell {
        font-weight: 500;
        font-family: 'Courier New', monospace;
    }
    
    .uploaded-cell {
        color: #10b981;
    }
    
    .downloaded-cell {
        color: #ef4444;
    }
    
    .ratio-cell {
        font-weight: 600;
        color: #6366f1;
    }
    
    .error-container {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .error-icon {
        color: #dc2626;
        font-size: 2rem;
    }
    
    .error-content h3 {
        margin: 0 0 0.5rem 0;
        color: #dc2626;
    }
    
    .error-content p {
        margin: 0;
        color: #7f1d1d;
    }
    
    .error-message {
        background: #fef2f2;
        color: #dc2626;
        padding: 1.5rem;
        border-radius: 12px;
        border-left: 4px solid #dc2626;
        margin: 2rem 0;
    }
    
    .new-search-link {
        text-align: center;
        margin-top: 3rem;
        padding-top: 2rem;
        border-top: 1px solid #e5e7eb;
    }
    
    .new-search-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #6366f1;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: transform 0.2s, background-color 0.2s;
    }
    
    .new-search-button:hover {
        background: #4f46e5;
        transform: translateY(-2px);
    }
    
    /* Мобильная адаптация */
    @media (max-width: 768px) {
        .card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .version {
            align-self: flex-start;
        }
        
        .input-with-button {
            flex-direction: column;
        }
        
        .search-button {
            width: 100%;
            justify-content: center;
        }
        
        .table-responsive {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .users-table {
            min-width: 1200px;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px 15px;
            font-size: 0.9rem;
        }
        
        .users-table th i {
            margin-right: 6px;
            font-size: 0.9rem;
        }
        
        .passkey-cell {
            min-width: 200px;
        }
        
        .ip-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
    }
    
    @media (max-width: 480px) {
        .card-body {
            padding: 1rem;
        }
        
        .users-table {
            min-width: 1400px;
            font-size: 0.85rem;
        }
        
        .users-table th,
        .users-table td {
            padding: 10px 12px;
        }
        
        .users-table th i {
            font-size: 0.85rem;
        }
        
        .search-examples {
            padding: 1rem;
        }
        
        .example-ips {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .passkey-value {
            font-size: 0.8rem;
        }
    }
</style>


CSS;


    
    stdfoot();
}

// Run the application
main();

?>