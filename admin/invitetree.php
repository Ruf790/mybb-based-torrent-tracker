<?php

declare(strict_types=1);


if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="error-message">❌ Error! Direct initialization of this file is not allowed.</div>');
}

define('IT_VERSION', '0.3 by xam');
require_once INC_PATH . '/functions_ratio.php';

/**
 * Invite Tree Manager
 */
class InviteTreeManager
{
    private $db;
    private string $baseUrl;
    
    public function __construct($database, string $baseUrl)
    {
        $this->db = $database;
        $this->baseUrl = $baseUrl;
    }
    
    /**
     * Get tree identifier from request
     */
    public function getTreeIdentifier(): string
    {
        return $_POST['tree'] ?? $_GET['tree'] ?? '';
    }
    
    /**
     * Find user ID by username or validate numeric ID
     */
    public function findUserId(string $identifier): ?int
    {
        if (is_numeric($identifier)) {
            return (int) $identifier;
        }
        
        $query = $this->db->sql_query(
            "SELECT id FROM users WHERE username = " . $this->db->sqlesc($identifier)
        );
        
        if ($this->db->num_rows($query) === 0) {
            return null;
        }
        
        $user = mysqli_fetch_assoc($query);
        return (int) ($user['id'] ?? 0);
    }
    
    /**
     * Calculate user ratio with color coding
     */
    public function calculateRatio(float $uploaded, float $downloaded): string
    {
        if ($downloaded > 0) {
            $ratio = $uploaded / $downloaded;
            $formattedRatio = number_format($ratio, 2);
            $color = get_ratio_color($ratio);
            
            return $color 
                ? sprintf('(<span style="color: %s">%s</span>)', $color, $formattedRatio)
                : sprintf('(%s)', $formattedRatio);
        }
        
        if ($uploaded > 0) {
            return '(∞)';
        }
        
        return '(---)';
    }
    
    /**
     * Generate invite tree for user
     */
    public function generateTree(int $userId): string
    {
        $firstLevel = $this->getInvitedUsers($userId);
        
        if (empty($firstLevel)) {
            return $this->renderNoTreeMessage();
        }
        
        $html = '';
        foreach ($firstLevel as $user) {
            $html .= $this->renderUserWithTree($user);
        }
        
        return $html;
    }
    
    /**
     * Get users invited by specific user
     */
    private function getInvitedUsers($invitedBy, int $level = 1): array
    {
        // Приведение к int для безопасности
        $invitedByInt = (int) $invitedBy;
        
        $query = $this->db->sql_query(
            "SELECT id, username, uploaded, downloaded, invited_by 
             FROM users 
             WHERE ustatus = 'confirmed' 
               AND invited_by = " . $this->db->sqlesc($invitedByInt) . "
               AND invited_by > 0 
             ORDER BY username"
        );
        
        $users = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $row['level'] = $level;
            // Гарантируем, что ID - целое число
            $row['id'] = (int) $row['id'];
            $row['uploaded'] = (float) $row['uploaded'];
            $row['downloaded'] = (float) $row['downloaded'];
            $row['invited_by'] = (int) $row['invited_by'];
            $users[] = $row;
        }
        
        return $users;
    }
    
    /**
     * Render user with their invite tree
     */
    private function renderUserWithTree(array $user): string
    {
        // Гарантируем правильные типы данных
        $userId = (int) $user['id'];
        $userName = htmlspecialchars($user['username'] ?? '');
        $uploaded = (float) ($user['uploaded'] ?? 0);
        $downloaded = (float) ($user['downloaded'] ?? 0);
        $level = (int) ($user['level'] ?? 1);
        
        $ratioHtml = $this->calculateRatio($uploaded, $downloaded);
        
        $html = sprintf(
            '<div class="user-node level-%d">
                <div class="user-info">
                    <span class="user-icon">%s</span>
                    <a href="%s/userdetails.php?id=%d" class="user-link">
                        %s
                    </a>
                    <span class="user-ratio">%s</span>
                </div>',
            $level,
            $this->getLevelIcon($level),
            $this->baseUrl,
            $userId,
            $userName,
            $ratioHtml
        );
        
        // Add second level invites
        $secondLevel = $this->getInvitedUsers($userId, 2);
        if (!empty($secondLevel)) {
            $html .= '<div class="user-children">';
            foreach ($secondLevel as $child) {
                $html .= $this->renderUserNode($child, 2);
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render user node (for nested levels)
     */
    private function renderUserNode(array $user, int $level): string
    {
        // Гарантируем правильные типы данных
        $userId = (int) $user['id'];
        $userName = htmlspecialchars($user['username'] ?? '');
        $uploaded = (float) ($user['uploaded'] ?? 0);
        $downloaded = (float) ($user['downloaded'] ?? 0);
        
        $ratioHtml = $this->calculateRatio($uploaded, $downloaded);
        
        $html = sprintf(
            '<div class="user-node level-%d">
                <div class="user-info">
                    <span class="user-icon">%s</span>
                    <a href="%s/userdetails.php?id=%d" class="user-link">
                        %s
                    </a>
                    <span class="user-ratio">%s</span>
                </div>',
            $level,
            $this->getLevelIcon($level),
            $this->baseUrl,
            $userId,
            $userName,
            $ratioHtml
        );
        
        // Add third level invites
        if ($level === 2) {
            $thirdLevel = $this->getInvitedUsers($userId, 3);
            if (!empty($thirdLevel)) {
                $html .= '<div class="user-children">';
                foreach ($thirdLevel as $child) {
                    $html .= $this->renderUserNode($child, 3);
                }
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Get icon for tree level
     */
    private function getLevelIcon(int $level): string
    {
        $icons = [
            1 => '🌳',  // Tree
            2 => '🌿',  // Branch
            3 => '🍃',  // Leaf
        ];
        
        return $icons[$level] ?? '👤';
    }
    
    /**
     * Render no tree message
     */
    private function renderNoTreeMessage(): string
    {
        return <<<HTML
            <div class="empty-state">
                <div class="empty-icon">🌱</div>
                <h3>No Invite Tree Found</h3>
                <p>This user hasn't invited anyone yet.</p>
            </div>
        HTML;
    }
    
    /**
     * Render search form
     */
    public function renderSearchForm(string $scriptUrl): string
    {
        return <<<HTML
            <div class="search-container">
                <div class="search-header">
                    <h2><i class="fas fa-tree"></i> Invite Tree Explorer</h2>
                    <p class="search-subtitle">Enter a User ID or Username to view their invite tree</p>
                </div>
                
                <form method="post" action="{$_this_script_}" class="search-form">
                    <div class="input-group">
                        <input type="text" 
                               name="tree" 
                               class="search-input" 
                               placeholder="User ID or Username..."
                               required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Get Invite Tree
                        </button>
                    </div>
                    <div class="search-hint">
                        <i class="fas fa-info-circle"></i> You can search by numeric ID or username
                    </div>
                </form>
            </div>
        HTML;
    }
}

// Main execution
function main(): void
{
    global $db, $BASEURL;
    
    // Check if $db and $BASEURL are defined
    if (!isset($db) || !isset($BASEURL)) {
        echo '<div class="error-message">Database connection or base URL not configured.</div>';
        return;
    }
    
    stdhead('Invite Tree', false);
    
    try {
        $manager = new InviteTreeManager($db, $BASEURL);
        $treeIdentifier = $manager->getTreeIdentifier();
        $currentScript = $_this_script_ ?? '';
        
        echo '<div class="container mt-3">';
        echo '<div class="card">';
        echo '<div class="card-header bg-primary text-white">';
        echo '<h1><i class="fas fa-network-wired"></i> Invite Tree</h1>';
        echo '</div>';
        echo '<div class="card-body">';
        
        if (empty($treeIdentifier)) {
            echo $manager->renderSearchForm($currentScript);
        } else {
            $userId = $manager->findUserId($treeIdentifier);
            
            if ($userId === null) {
                echo '<div class="alert alert-danger">';
                echo '<i class="fas fa-user-slash"></i> No user found with this name.';
                echo '</div>';
                echo $manager->renderSearchForm($currentScript);
            } else {
                echo '<div class="tree-header">';
                echo '<h3><i class="fas fa-sitemap"></i> Invite Tree for User #' . $userId . '</h3>';
                echo '<a href="' . $currentScript . '" class="back-link">';
                echo '<i class="fas fa-arrow-left"></i> New Search';
                echo '</a>';
                echo '</div>';
                
                echo '<div class="tree-container">';
                echo $manager->generateTree($userId);
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '<div class="card-footer">';
        echo '<small><i class="fas fa-code"></i> Version ' . IT_VERSION . '</small>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Add CSS styles
        echo <<<CSS
            <style>
              
                
                .glass-card {
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 20px;
                    box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
                    overflow: hidden;
                }
                
               
                
                .card-header h1 {
                    margin: 0;
                    font-size: 1.8rem;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .card-body {
                    padding: 2rem;
                    min-height: 400px;
                }
                
                .search-container {
                    text-align: center;
                    padding: 2rem 0;
                }
                
                .search-header h2 {
                    color: #2d3748;
                    margin-bottom: 0.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }
                
                .search-subtitle {
                    color: #718096;
                    margin-bottom: 2rem;
                    font-size: 1.1rem;
                }
                
                .search-form {
                    max-width: 600px;
                    margin: 0 auto;
                }
                
                .input-group {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 1rem;
                }
                
                .search-input {
                    flex: 1;
                    padding: 15px 20px;
                    border: 2px solid #e2e8f0;
                    border-radius: 12px;
                    font-size: 16px;
                    transition: all 0.3s;
                    background: white;
                }
                
                .search-input:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                .search-button {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 15px 30px;
                    border-radius: 12px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: transform 0.2s, box-shadow 0.2s;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .search-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
                }
                
                .search-hint {
                    color: #a0aec0;
                    font-size: 0.9rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 5px;
                }
                
                .tree-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 2rem;
                    padding-bottom: 1rem;
                    border-bottom: 2px solid #e2e8f0;
                }
                
                .tree-header h3 {
                    color: #2d3748;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .back-link {
                    color: #667eea;
                    text-decoration: none;
                    font-weight: 500;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    transition: color 0.3s;
                }
                
                .back-link:hover {
                    color: #764ba2;
                }
                
                .tree-container {
                    background: #f8fafc;
                    border-radius: 15px;
                    padding: 2rem;
                    border: 1px solid #e2e8f0;
                }
                
                .user-node {
                    margin-bottom: 1.5rem;
                    transition: transform 0.3s;
                }
                
                .user-node:hover {
                    transform: translateX(5px);
                }
                
                .user-node.level-1 {
                    margin-left: 0;
                }
                
                .user-node.level-2 {
                    margin-left: 2rem;
                    border-left: 2px solid #cbd5e0;
                    padding-left: 1.5rem;
                }
                
                .user-node.level-3 {
                    margin-left: 4rem;
                    border-left: 2px dashed #cbd5e0;
                    padding-left: 1.5rem;
                }
                
                .user-info {
                    background: white;
                    padding: 15px 20px;
                    border-radius: 10px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 0.5rem;
                }
                
                .user-icon {
                    font-size: 1.2rem;
                }
                
                .user-link {
                    color: #2d3748;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 1.1rem;
                    transition: color 0.3s;
                }
                
                .user-link:hover {
                    color: #667eea;
                    text-decoration: underline;
                }
                
                .user-ratio {
                    margin-left: auto;
                    font-weight: 500;
                    color: #4a5568;
                }
                
                .user-children {
                    margin-top: 1rem;
                }
                
                .empty-state {
                    text-align: center;
                    padding: 3rem 1rem;
                    color: #718096;
                }
                
                .empty-icon {
                    font-size: 3rem;
                    margin-bottom: 1rem;
                    opacity: 0.5;
                }
                
                .empty-state h3 {
                    color: #4a5568;
                    margin-bottom: 0.5rem;
                }
                
                .alert {
                    padding: 1rem 1.5rem;
                    border-radius: 10px;
                    margin-bottom: 2rem;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .alert-danger {
                    background: #fed7d7;
                    color: #9b2c2c;
                    border: 1px solid #fc8181;
                }
                
                .card-footer {
                    background: #f7fafc;
                    padding: 1rem 2rem;
                    color: #a0aec0;
                    font-size: 0.9rem;
                    border-top: 1px solid #e2e8f0;
                }
                
                .error-message {
                    background: #fed7d7;
                    color: #9b2c2c;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px;
                    border-left: 4px solid #c53030;
                }
                
                @media (max-width: 768px) {
                    .input-group {
                        flex-direction: column;
                    }
                    
                    .search-input,
                    .search-button {
                        width: 100%;
                    }
                    
                    .tree-header {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 1rem;
                    }
                    
                    .user-node.level-2 {
                        margin-left: 1rem;
                    }
                    
                    .user-node.level-3 {
                        margin-left: 2rem;
                    }
                }
            </style>
            
            
CSS;
        
    } catch (Exception $e) {
        echo '<div class="error-message">';
        echo '<i class="fas fa-exclamation-triangle"></i> Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    
    stdfoot();
}

// Run the application
main();

?>