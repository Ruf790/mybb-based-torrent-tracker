<?php


declare(strict_types=1);

// Security constants
define("IN_MYBB", 1);
define("IN_ADMINCP", 1);
define('TSF_FORUMS_TSSEv56', true);
define('TSF_FORUMS_GLOBAL_TSSEv56', true);
define('TSF_VERSION', 'v1.5 by xam');

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    http_response_code(403);
    die("
        <div class='alert alert-danger text-center' style='max-width: 500px; margin: 100px auto;'>
            <i class='fa-solid fa-shield-exclamation fa-3x mb-3 text-warning'></i>
            <h4>Access Denied</h4>
            <p>Direct initialization of this file is not allowed.</p>
            <small>Please make sure IN_MYBB is defined.</small>
        </div>
    ");
}

/**
 * Advanced Ban Management System
 */
class BanManager
{
    private const BAN_TYPES = [
        'ips' => 1,
        'usernames' => 2,
        'emails' => 3
    ];

    private const TYPE_CONFIGS = [
        1 => [
            'title' => 'Banned IP Addresses', 
            'redirect' => '',
            'icon' => 'fa-network-wired',
            'color' => 'danger',
            'tab_icon' => 'fa-solid fa-network-wired'
        ],
        2 => [
            'title' => 'Disallowed Usernames', 
            'redirect' => 'usernames',
            'icon' => 'fa-user-slash',
            'color' => 'warning',
            'tab_icon' => 'fa-solid fa-user-slash'
        ],
        3 => [
            'title' => 'Disallowed Email Addresses', 
            'redirect' => 'emails',
            'icon' => 'fa-envelope-circle-exclamation',
            'color' => 'info',
            'tab_icon' => 'fa-solid fa-envelope-circle-exclamation'
        ]
    ];

    private const FORM_CONFIGS = [
        1 => [
            'title' => 'Ban IP Address',
            'label' => 'IP Address',
            'description' => 'To ban a range of IP addresses use * (Ex: 127.0.0.*) or CIDR notation (Ex: 127.0.0.0/8)',
            'button' => 'Ban IP Address',
            'icon' => 'fa-ban',
            'placeholder' => 'Enter IP address or range...'
        ],
        2 => [
            'title' => 'Disallow Username',
            'label' => 'Username', 
            'description' => 'To indicate a wild card match, use * (Ex: admin*, *bot, *moderator*)',
            'button' => 'Disallow Username',
            'icon' => 'fa-user-lock',
            'placeholder' => 'Enter username pattern...'
        ],
        3 => [
            'title' => 'Disallow Email Address',
            'label' => 'Email Address',
            'description' => 'To indicate a wild card match, use * (Ex: *@spam.com, user@*.domain)',
            'button' => 'Disallow Email Address', 
            'icon' => 'fa-envelope',
            'placeholder' => 'Enter email pattern...'
        ]
    ];

    public function __construct(
        private $mybb,
        private $db, 
        private $cache,
        private $plugins
    ) {}

    /**
     * Main request router
     */
    public function handleRequest(): void
    {
        $action = $this->mybb->get_input('action');
        
        match ($action) {
            'add' => $this->handleAdd(),
            'delete' => $this->handleDelete(),
            default => $this->displayInterface()
        };
    }

    /**
     * Handle ban addition with validation
     */
    private function handleAdd(): void
    {
        $this->plugins->run_hooks("admin_config_banning_add");

        $filter = $this->mybb->get_input('filter');
        $type = $this->mybb->get_input('type', MyBB::INPUT_INT);

        if ($this->mybb->request_method !== "post") {
            flash_message('Invalid request method', 'error');
            $this->redirectToBanning();
        }

        $errors = $this->validateAdd($filter, $type);

        if (empty($errors)) {
            $this->addBanFilter($filter, $type);
            $this->redirectAfterAdd($type);
        }

        $this->handleErrors($errors, $type);
    }

    /**
     * Handle ban deletion with confirmation
     */
    private function handleDelete(): void
    {
        $fid = $this->mybb->get_input('fid', MyBB::INPUT_INT);
        $filter = $this->getFilterById($fid);

        if (!$filter) {
            $this->flashError('The specified filter does not exist');
            $this->redirectToBanning();
        }

        $this->plugins->run_hooks("admin_config_banning_delete");

        if ($this->mybb->get_input('no')) {
            $typeName = $this->getTypeName((int)$filter['type']);
            admin_redirect("index.php?act=banning&type={$typeName}");
        }

        if ($this->mybb->request_method === "post") {
            $this->deleteBanFilter($filter);
        } else {
            $this->showDeleteConfirmation($filter);
        }
    }

    /**
     * Display main management interface
     */
    private function displayInterface(): void
    {
        $this->plugins->run_hooks("admin_config_banning_start");

        $typeConfig = $this->getCurrentTypeConfig();
        $this->renderInterface($typeConfig);
    }

    /**
     * Validate ban addition data
     */
    private function validateAdd(string $filter, int $type): array
    {
        $errors = [];

        if (empty(trim($filter))) {
            $errors[] = 'Please enter a value to ban';
        }

        if ($this->isDuplicateFilter($filter, $type)) {
            $errors[] = 'This filter is already banned';
        }

        // Additional validation based on type
        if ($type === 1 && !$this->isValidIPFilter($filter)) {
            $errors[] = 'Please enter a valid IP address or range';
        }

        if ($type === 3 && !$this->isValidEmailFilter($filter)) {
            $errors[] = 'Please enter a valid email pattern';
        }

        return $errors;
    }

    private function isValidIPFilter(string $filter): bool
    {
        // Basic IP/CIDR/wildcard validation
        if (str_contains($filter, '/')) {
            return $this->isValidCIDR($filter);
        }
        
        return (bool)preg_match('/^[0-9.*]+$/', $filter);
    }

    private function isValidCIDR(string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) return false;
        
        return filter_var($parts[0], FILTER_VALIDATE_IP) && 
               $parts[1] >= 0 && $parts[1] <= 128;
    }

    private function isValidEmailFilter(string $filter): bool
    {
        // Allow wildcards in email validation
        $pattern = '/^[a-zA-Z0-9.*_%+-]+@[a-zA-Z0-9.*-]+\.[a-zA-Z]{2,}$/';
        return (bool)preg_match($pattern, str_replace('*', 'wildcard', $filter));
    }

    private function isDuplicateFilter(string $filter, int $type): bool
    {
        $query = $this->db->simple_select(
            "banfilters", 
            "fid", 
            "filter = '" . $this->db->escape_string($filter) . "' AND type = '{$type}'"
        );
        return $this->db->num_rows($query) > 0;
    }

    private function addBanFilter(string $filter, int $type): void
    {
        $new_filter = [
            "filter" => $this->db->escape_string(trim($filter)),
            "type" => $type,
            "dateline" => TIMENOW,
            "lastuse" => 0
        ];
        
        $fid = $this->db->insert_query("banfilters", $new_filter);
        $this->plugins->run_hooks("admin_config_banning_add_commit");

        $this->updateCaches($type);
        $this->logAdminAction((int)$fid, $filter, $type);
    }

    private function updateCaches(int $type): void
    {
        match ($type) {
            1 => $this->cache->update_bannedips(),
            3 => $this->cache->update_bannedemails(),
            default => null
        };
    }

    private function redirectAfterAdd(int $type): void
    {
        $config = self::TYPE_CONFIGS[$type];
        $message = match ($type) {
            1 => '🎯 IP address has been banned successfully',
            2 => '👤 Username has been disallowed successfully', 
            3 => '📧 Email address has been disallowed successfully'
        };

        $this->flashSuccess($message);
        admin_redirect("index.php?act=banning" . ($config['redirect'] ? "&type={$config['redirect']}" : ''));
    }

    private function getFilterById(int $fid): ?array
    {
        $query = $this->db->simple_select("banfilters", "*", "fid='{$fid}'");
        $result = $this->db->fetch_array($query);
        
        if ($result) {
            // Ensure proper data types
            $result['fid'] = (int)$result['fid'];
            $result['type'] = (int)$result['type'];
            $result['dateline'] = (int)$result['dateline'];
            $result['lastuse'] = (int)$result['lastuse'];
        }
        
        return $result ?: null;
    }

    private function deleteBanFilter(array $filter): void
    {
        $this->db->delete_query("banfilters", "fid='{$filter['fid']}'");
        $this->plugins->run_hooks("admin_config_banning_delete_commit");

        $this->logAdminAction((int)$filter['fid'], $filter['filter'], (int)$filter['type']);
        $this->updateCaches((int)$filter['type']);

        $typeName = $this->getTypeName((int)$filter['type']);
        $this->flashSuccess('🗑️ Ban has been deleted successfully');
        admin_redirect("index.php?act=banning&type={$typeName}");
    }

    private function getTypeName(int $type): string
    {
        return array_flip(self::BAN_TYPES)[$type] ?? 'ips';
    }

    private function showDeleteConfirmation(array $filter): void
    {
        // Use the global page object if available, otherwise create custom confirmation
        global $page;
        
        $filterText = htmlspecialchars_uni($filter['filter']);
        $typeName = $this->getTypeName((int)$filter['type']);
        $typeConfig = self::TYPE_CONFIGS[$filter['type']];
        
        $message = "
            <div class='text-center'>
                <i class='fa-solid fa-triangle-exclamation fa-3x text-warning mb-3'></i>
                <h4>Confirm Deletion</h4>
                <p>Are you sure you want to delete this ban?</p>
                <div class='alert alert-light border'>
                    <strong>{$filterText}</strong><br>
                    <small class='text-muted'>
                        <i class='fa-solid fa-{$typeConfig['icon']} text-{$typeConfig['color']}'></i>
                        {$typeConfig['title']}
                    </small>
                </div>
            </div>
        ";

        
        $this->outputCustomConfirmation($filter, $message);
        
    }

  


/**
 * Custom confirmation dialog fallback with modal
 */
private function outputCustomConfirmation(array $filter, string $message): void
{
    $deleteUrl = "index.php?act=banning&action=delete&fid={$filter['fid']}&my_post_key={$this->mybb->post_code}";
    $cancelUrl = "index.php?act=banning&type=" . $this->getTypeName((int)$filter['type']);
    
    stdhead();
    echo "
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1040;
            opacity: 0;
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        .confirmation-modal {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            max-width: 500px;
            width: 100%;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            z-index: 1050;
            opacity: 0;
            animation: modalSlideIn 0.4s ease-out 0.1s forwards;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 1.5rem 2rem;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            border: none;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            gap: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .btn-outline-secondary {
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.2);
            background: #6c757d;
            color: white;
        }
        
        .ban-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid #dc3545;
        }
        
        .ban-value {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            font-weight: 600;
            color: #dc3545;
            word-break: break-all;
        }
        
        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }
        
        @keyframes modalSlideIn {
            to {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }
        
        .warning-icon {
            font-size: 4rem;
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .type-badge {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }
        
        /* Responsive design */
        @media (max-width: 576px) {
            .confirmation-modal {
                margin: 1rem;
                max-width: calc(100% - 2rem);
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.9);
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin: 0.25rem 0;
                text-align: center;
            }
        }
    </style>

    <!-- Backdrop -->
    <div class='modal-backdrop'></div>
    
    <!-- Modal -->
    <div class='confirmation-modal'>
        <div class='modal-header text-center'>
            <h4 class='modal-title w-100 mb-0'>
                <i class='fas fa-exclamation-triangle me-2'></i>
                Confirm Deletion
            </h4>
        </div>
        
        <div class='modal-body text-center'>
            <div class='warning-icon'>
                <i class='fas fa-trash-alt'></i>
            </div>
            
            <h5 class='text-dark mb-3'>Are you sure you want to delete this ban?</h5>
            <p class='text-muted mb-4'>This action cannot be undone and will permanently remove the ban filter.</p>
            
            <div class='ban-details text-start'>
                <div class='d-flex justify-content-between align-items-center mb-2'>
                    <span class='text-muted'>Ban Type:</span>
                    <span class='type-badge'>
                        <i class='fas fa-".self::TYPE_CONFIGS[$filter['type']]['icon']." me-1'></i>
                        ".self::TYPE_CONFIGS[$filter['type']]['title']."
                    </span>
                </div>
                <div class='mb-2'>
                    <span class='text-muted'>Filter Value:</span>
                    <div class='ban-value mt-1'>".htmlspecialchars_uni($filter['filter'])."</div>
                </div>
                <div class='small text-muted'>
                    <i class='fas fa-calendar me-1'></i>
                    Created: ".($filter['dateline'] > 0 ? my_datee('relative', $filter['dateline']) : 'N/A')."
                </div>
            </div>
        </div>
        
        <div class='modal-footer justify-content-center'>
            <form action='{$deleteUrl}' method='post' class='d-inline'>
                <button type='submit' class='btn btn-danger btn-lg pulse'>
                    <i class='fas fa-trash-can me-2'></i>
                    Yes, Delete Permanently
                </button>
            </form>
            <a href='{$cancelUrl}' class='btn btn-outline-secondary btn-lg'>
                <i class='fas fa-times me-2'></i>
                Cancel
            </a>
        </div>
    </div>

    <script>
        // Close modal on backdrop click
        document.querySelector('.modal-backdrop').addEventListener('click', function() {
            window.location.href = '{$cancelUrl}';
        });
        
        // Escape key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = '{$cancelUrl}';
            }
        });
        
        // Smooth focus management
        setTimeout(() => {
            document.querySelector('.btn-danger').focus();
        }, 100);
        
        // Add some interactive effects
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Prevent form submission animation issues
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('button');
            btn.innerHTML = '<i class=\"fas fa-spinner fa-spin me-2\"></i>Deleting...';
            btn.disabled = true;
        });
    </script>
    ";
    stdfoot();
    exit;
}
 
 
 
 
 
 
 
 
 
 
 
 

    private function getCurrentTypeConfig(): array
    {
        $inputType = $this->mybb->get_input('type');
        
        return match ($inputType) {
            'emails' => self::TYPE_CONFIGS[3] + ['type' => 3, 'name' => 'emails'],
            'usernames' => self::TYPE_CONFIGS[2] + ['type' => 2, 'name' => 'usernames'],
            default => self::TYPE_CONFIGS[1] + ['type' => 1, 'name' => 'ips']
        };
    }

    private function renderInterface(array $typeConfig): void
    {
        $this->outputHeader($typeConfig);
        $this->outputNavigation($typeConfig['name']);
        $this->outputAddForm($typeConfig);
        $this->outputBanList($typeConfig);
        $this->outputFooter();
    }

    private function outputHeader(array $typeConfig): void
    {
        stdhead($typeConfig['title']);
        $this->outputAssets($typeConfig);
    }

    private function outputAssets(array $typeConfig): void
    {
        $version = '1813';
        
        echo "
        <!-- Ban Manager Assets -->
       
        
        <style>
            .ban-manager-card {
                border: none;
                box-shadow: 0 2px 15px rgba(0,0,0,0.08);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .ban-manager-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            }
            .ban-type-icon {
                font-size: 1.5em;
                margin-right: 10px;
            }
            .ban-row {
                transition: background-color 0.2s ease;
            }
            .ban-row:hover {
                background-color: #f8f9fa !important;
            }
            .empty-state {
                padding: 3rem 1rem;
                text-align: center;
                color: #6c757d;
            }
            .empty-state i {
                font-size: 4rem;
                margin-bottom: 1rem;
                opacity: 0.5;
            }
        </style>
        {$this->getPopupScript()}
        ";
    }

    private function getPopupScript(): string
    {
        return "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced ban management interactions
            const banRows = document.querySelectorAll('.ban-row');
            banRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    if (!e.target.closest('a')) {
                        this.style.backgroundColor = '#f8f9fa';
                        setTimeout(() => {
                            this.style.backgroundColor = '';
                        }, 200);
                    }
                });
            });
            
            // Auto-focus on filter input
            const filterInput = document.getElementById('filter');
            if (filterInput) {
                setTimeout(() => filterInput.focus(), 100);
            }
        });
        </script>
        ";
    }

    private function outputNavigation(string $currentType): void
    {
        $sub_tabs = [
            'ips' => [
                'title' => 'Banned IPs',
                'link' => "index.php?act=banning",
                'description' => 'Manage IP addresses banned from accessing your board. You can ban specific IPs, ranges using wildcards (*), or CIDR notation.',
                'icon' => 'fa-solid fa-network-wired'
            ],
            'users' => [
                'title' => 'Banned Accounts',
                'link' => "index.php?act=banning2",
                'description' => 'Manage user accounts that are currently banned from the forum.',
                'icon' => 'fa-solid fa-user-lock'
            ],
            'usernames' => [
                'title' => 'Disallowed Usernames',
                'link' => "index.php?act=banning&type=usernames",
                'description' => 'Manage usernames that cannot be registered or used. Useful for reserving names and preventing inappropriate usernames.',
                'icon' => 'fa-solid fa-user-slash'
            ],
            'emails' => [
                'title' => 'Disallowed Emails',
                'link' => "index.php?act=banning&type=emails",
                'description' => 'Manage email addresses and domains that cannot be used for registration. Use wildcards to block entire domains.',
                'icon' => 'fa-solid fa-mail-bulk'
            ]
        ];

        output_nav_tabs($sub_tabs, $currentType);
    }

    private function outputAddForm(array $typeConfig): void
    {
        $config = self::FORM_CONFIGS[$typeConfig['type']];
        $icon = $typeConfig['icon'];
        $color = $typeConfig['color'];

        echo "
        <div class='container mt-4'>
            <form action='index.php?act=banning&action=add' method='post' id='add' class='ban-manager-card'>
                <input type='hidden' name='my_post_key' value='{$this->mybb->post_code}' />
                <input type='hidden' name='type' value='{$typeConfig['type']}' />
                
                <div class='card border-0 shadow-sm'>
                    <div class='card-header bg-{$color} text-white rounded-top'>
                        <h5 class='mb-0'>
                            <i class='fa-solid {$icon} me-2'></i>
                            {$config['title']}
                        </h5>
                    </div>
                    <div class='card-body'>
                        <div class='mb-3'>
                            <label for='filter' class='form-label fw-semibold'>
                                {$config['label']} <span class='text-danger'>*</span>
                            </label>
                            <div class='form-text text-muted'>
                                <i class='fa-solid fa-circle-info me-1'></i>
                                {$config['description']}
                            </div>
                            <input type='text' 
                                   name='filter' 
                                   value='' 
                                   class='form-control form-control-lg mt-2' 
                                   id='filter' 
                                   placeholder='{$config['placeholder']}'
                                   required
                                   autofocus>
                        </div>
                    </div>
                    <div class='card-footer bg-light text-center py-3'>
                        <button type='submit' class='btn btn-{$color} btn-lg px-4'>
                            <i class='fa-solid {$config['icon']} me-2'></i>
                            {$config['button']}
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <br />
        ";
    }

    private function outputBanList(array $typeConfig): void
    {
        $totalRows = $this->getTotalRows($typeConfig['type']);
        $filters = $this->getFilters($typeConfig['type']);
        
        echo "
        <div class='container mt-4'>
            <div class='card border-0 shadow-sm ban-manager-card'>
                <div class='card-header bg-light rounded-top py-3'>
                    <div class='d-flex justify-content-between align-items-center'>
                        <h5 class='mb-0'>
                            <i class='fa-solid {$typeConfig['icon']} text-{$typeConfig['color']} me-2'></i>
                            {$typeConfig['title']}
                            <span class='badge bg-{$typeConfig['color']} ms-2'>{$totalRows}</span>
                        </h5>
                        <div class='text-muted small'>
                            <i class='fa-solid fa-clock me-1'></i>
                            Last updated: " . my_datee('relative', TIMENOW) . "
                        </div>
                    </div>
                </div>
                <div class='card-body p-0'>
        ";

        $this->outputFilterTable($filters, $typeConfig);
        
        echo "
                </div>
            </div>
        </div>
        ";

        $this->outputPagination($totalRows, $typeConfig['name']);
    }

    private function outputFilterTable(array $filters, array $typeConfig): void
    {
        $headers = $this->getTableHeaders($typeConfig['type']);
        
        echo "
        <div class='table-responsive'>
            <table class='table table-hover mb-0'>
                <thead class='table-light'>
                    <tr>
                        {$headers}
                        <th width='100' class='text-center'>Actions</th>
                    </tr>
                </thead>
                <tbody>
        ";

        if (empty($filters)) {
            $this->outputEmptyState($typeConfig);
        } else {
            $this->outputFilterRows($filters);
        }
        
        echo "
                </tbody>
            </table>
        </div>
        ";
    }

    private function outputEmptyState(array $typeConfig): void
    {
        $icons = [
            1 => 'fa-network-wired',
            2 => 'fa-user-slash', 
            3 => 'fa-envelope-circle-exclamation'
        ];
        
        echo "
        <tr>
            <td colspan='4' class='empty-state'>
                <i class='fa-solid {$icons[$typeConfig['type']]} text-muted'></i>
                <h5 class='text-muted'>No bans found</h5>
                <p class='text-muted mb-0'>There are no {$typeConfig['title']} at this time</p>
            </td>
        </tr>
        ";
    }

    private function getTableHeaders(int $type): string
    {
        return match ($type) {
            1 => '<th>IP Address</th><th width="200">Ban Date</th><th width="200">Last Access</th>',
            2 => '<th>Username</th><th width="200">Date Disallowed</th><th width="200">Last Attempt</th>',
            3 => '<th>Email Address</th><th width="200">Date Disallowed</th><th width="200">Last Attempt</th>'
        };
    }

    private function getTotalRows(int $type): int
    {
        $query = $this->db->simple_select("banfilters", "COUNT(fid) AS filter_count", "type='{$type}'");
        return (int)$this->db->fetch_field($query, "filter_count");
    }

    private function getFilters(int $type): array
    {
        $start = $this->getStartPosition();
        $query = $this->db->simple_select(
            "banfilters", 
            "*", 
            "type='{$type}'", 
            [
                'limit_start' => $start, 
                'limit' => 20, 
                "order_by" => "dateline", 
                "order_dir" => "desc"
            ]
        );
        
        $filters = [];
        while ($filter = $this->db->fetch_array($query)) {
            // Convert string IDs to integers
            $filter['fid'] = (int)$filter['fid'];
            $filter['type'] = (int)$filter['type'];
            $filter['dateline'] = (int)$filter['dateline'];
            $filter['lastuse'] = (int)$filter['lastuse'];
            $filters[] = $filter;
        }
        
        return $filters;
    }

    private function getStartPosition(): int
    {
        $pagenum = $this->mybb->get_input('page', MyBB::INPUT_INT) ?: 1;
        return ($pagenum - 1) * 20;
    }

    private function outputFilterRows(array $filters): void
    {
        foreach ($filters as $filter) {
            $filterText = htmlspecialchars_uni($filter['filter']);
            $date = $filter['dateline'] > 0 ? my_datee('relative', $filter['dateline']) : 'N/A';
            $lastUse = $filter['lastuse'] > 0 ? my_datee('relative', $filter['lastuse']) : 'Never';
            $isRecent = (TIMENOW - $filter['dateline']) < 86400; // 24 hours
            
            $dateBadge = $isRecent ? "<span class='badge bg-success ms-1'>New</span>" : "";
            
            $deleteUrl = "index.php?act=banning&action=delete&fid={$filter['fid']}&my_post_key={$this->mybb->post_code}";
            $deleteButton = "
                <a href='{$deleteUrl}' 
                   onclick='return AdminCP.deleteConfirmation(this, \"Are you sure you wish to delete this ban?\");'
                   class='btn btn-sm btn-outline-danger'
                   title='Delete Ban'>
                    <i class='fa-solid fa-trash-can'></i>
                </a>
            ";

            echo "
            <tr class='ban-row align-middle'>
                <td>
                    <div class='d-flex align-items-center'>
                        <code class='filter-code'>{$filterText}</code>
                        {$dateBadge}
                    </div>
                </td>
                <td>
                    <span class='text-muted small'>{$date}</span>
                </td>
                <td>
                    <span class='text-muted small'>{$lastUse}</span>
                </td>
                <td class='text-center'>{$deleteButton}</td>
            </tr>
            ";
        }
    }

    private function outputPagination(int $totalRows, string $typeName): void
    {
        if ($totalRows > 20) {
            $pagenum = $this->mybb->get_input('page', MyBB::INPUT_INT) ?: 1;
            echo "
            <div class='container mt-3'>
                <div class='d-flex justify-content-center'>
                    " . draw_admin_pagination(
                        $pagenum, 
                        20, 
                        $totalRows, 
                        "index.php?module=config-banning&type={$typeName}&page={page}"
                    ) . "
                </div>
            </div>
            ";
        }
    }

    private function outputFooter(): void
    {
        echo "
        <script>
        // Enhanced UI interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth animations for cards
            const cards = document.querySelectorAll('.ban-manager-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });
        });
        </script>
        ";
        
        stdfoot();
    }

    /**
     * Log admin action with proper type casting
     */
    private function logAdminAction(int $fid, string $filter, int $type): void
    {
        log_admin_action($fid, $filter, $type);
    }

    private function handleErrors(array $errors, int $type): void
    {
        if ($errors) {
            output_inline_error($errors);
        }
    }

    private function flashSuccess(string $message): void
    {
        flash_message($message, 'success');
    }

    private function flashError(string $message): void
    {
        flash_message($message, 'error');
    }

    private function redirectToBanning(): void
    {
        admin_redirect("index.php?act=banning");
    }
}

// Initialize and execute the ban manager
try {
    $banManager = new BanManager($mybb, $db, $cache, $plugins);
    $banManager->handleRequest();
} catch (Exception $e) {
    error_log("Ban Manager Error: " . $e->getMessage());
    flash_message('An error occurred while processing your request', 'error');
    admin_redirect("index.php?act=banning");
}