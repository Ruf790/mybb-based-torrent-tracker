<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization is not allowed.</div>');
}


class MassInviteManager
{
    private const ALLOWED_TYPES = ['+', '-'];
    private const DEFAULT_AMOUNT = 5;

    private object $db;
    private string $currentScript;

    public function __construct(object $db, string $currentScript)
    {
        $this->db = $db;
        $this->currentScript = $currentScript;
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            global $mybb;
            if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => 'Security check failed. Please refresh the page and try again.']);
                exit;
            }

            if (($_POST['preview'] ?? '') === 'yes') {
                $this->previewAffectedUsers();
            } elseif (($_POST['doit'] ?? '') === 'yes') {
                $this->processMassInvite();
            }
        } else {
            $this->renderForm();
        }
    }

    private function sanitizeType(string $type): string
    {
        return in_array($type, self::ALLOWED_TYPES, true) ? $type : '+';
    }

    private function sanitizeUserGroup(string $userGroup): string
    {
        $userGroup = trim($userGroup);
        return ($userGroup === '-' || !ctype_digit($userGroup)) ? '' : $userGroup;
    }

    private function buildQuery(string $userGroup, int $amount = 0, string $type = '+'): string
    {
        $conditions = ["enabled='yes'", "ustatus='confirmed'"];
        if ($userGroup !== '') {
            $conditions[] = "usergroup={$userGroup}";
        }
        if ($type === '-' && $amount > 0) {
            $conditions[] = "invites >= {$amount}";
        }
        return implode(' AND ', $conditions);
    }

    private function previewAffectedUsers(): void
    {
        $amount = abs((int)($_POST['amount'] ?? self::DEFAULT_AMOUNT));
        $type = $this->sanitizeType($_POST['type'] ?? '+');
        $userGroup = $_POST['usergroup'] ?? '-';

        $query = $this->buildQuery($this->sanitizeUserGroup($userGroup), $amount, $type);

        $res = $this->db->sql_query_prepared("SELECT COUNT(id) AS total FROM users WHERE {$query}");
        $row = $res ? $this->db->fetch_array($res) : null;

        echo json_encode(['count' => (int)($row['total'] ?? 0)]);
        exit;
    }

    private function processMassInvite(): void
    {
        $amount = abs((int)($_POST['amount'] ?? self::DEFAULT_AMOUNT));
        $type = $this->sanitizeType($_POST['type'] ?? '+');
        $userGroup = $this->sanitizeUserGroup($_POST['usergroup'] ?? '-');

        $query = $this->buildQuery($userGroup, $amount, $type);

        // Обновление
        $this->db->sql_query_prepared("UPDATE users SET invites = invites {$type} {$amount} WHERE {$query}");

        // Логирование
        $affected = $this->db->sql_query_prepared("SELECT COUNT(id) AS total FROM users WHERE {$query}");
        $row = $affected ? $this->db->fetch_array($affected) : null;
        $count = (int)($row['total'] ?? 0);

        global $CURUSER;
        $username = $CURUSER['username'] ?? 'Unknown';
        write_log("[MASS INVITE] Admin: {$username} (UID " . (int)($CURUSER['id'] ?? 0) . ") | Action: " . ($type === '+' ? 'ADD' : 'REMOVE') . " | Amount: {$amount} | Group: {$userGroup} | Affected users: {$count}");

        //stderr("Success,Invites updated successfully for {$count} users.");
		
		stdok(
    message: sprintf("Invites updated successfully for %d user(s).", $count),
    title:   'Mass Invite Complete',
    subtitle: ($type === '+' ? 'Added' : 'Removed') . " {$amount} invite(s) per affected user."
);
		
		
        stdfoot();
        exit();
    }

    private function renderForm(): void
    {
        global $BASEURL, $mybb;
		
		stdhead('Mass Invite Management');
        $selectBox = _selectbox_('', 'usergroup');
        ?>
        
        <!-- Дополнительные стили для красоты -->
        <style>
        
        
        .form-control:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.25rem rgba(118, 75, 162, 0.25);
        }
        
        
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .badge-count {
            font-size: 1.2rem;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .animated-preview {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        
        
       
        
        
        
        .form-label {
            color: #495057;
            font-weight: 600;
        }
        
        .bi {
            vertical-align: -0.125em;
        }
        </style>
        
        <div class="container mt-4">
            <!-- Статистика сверху -->
           
            
            <!-- Основная карточка -->
            <div class="card">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-magic me-2"></i>Mass Invite Management</h5>
                            <small class="opacity-75">Manage invites efficiently with powerful bulk operations</small>
                        </div>
                        <div class="badge bg-white text-primary px-3 py-2 rounded-pill">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Admin Tool
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <form action="<?= htmlspecialchars($this->currentScript, ENT_QUOTES) ?>" method="post" id="massInviteForm">
                        <input type="hidden" name="doit" value="yes">
                        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>">
                        
                        <div class="row g-4">
                            <!-- Поле количества -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="amount" class="form-label fw-semibold mb-3">
                                        <i class="bi bi-123 me-2 text-primary"></i>
                                        <span class="border-bottom border-primary border-2 pb-1">Invite Amount</span>
                                    </label>
                                    <div class="input-group input-group-lg shadow-sm">
                                        <span class="input-group-text">
                                            <i class="bi bi-ticket-perforated"></i>
                                        </span>
                                        <input type="number" 
                                               id="amount" 
                                               name="amount" 
                                               value="5" 
                                               min="1" 
                                               max="10000"
                                               class="form-control border-start-0" 
                                               required
                                               placeholder="Enter amount">
                                        <span class="input-group-text bg-light">invites</span>
                                    </div>
                                    <div class="form-text mt-2 d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2 text-info"></i>
                                        Enter number of invites to add or remove per user
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Выбор группы -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="usergroup" class="form-label fw-semibold mb-3">
                                        <i class="bi bi-people-fill me-2 text-success"></i>
                                        <span class="border-bottom border-success border-2 pb-1">Target Group</span>
                                    </label>
                                    <div class="input-group input-group-lg shadow-sm">
                                        <span class="input-group-text">
                                            <i class="bi bi-funnel"></i>
                                        </span>
                                        <?= $selectBox ?>
                                    </div>
                                    <div class="form-text mt-2 d-flex align-items-center">
                                        <i class="bi bi-filter me-2 text-success"></i>
                                        Select specific group or "-" for all users
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Кнопки выбора операции -->
                           <div class="col-md-4">
                                <label class="form-label fw-semibold d-block"><i class="bi bi-arrow-left-right me-1"></i> Operation Type</label>
                                <div class="btn-group w-100" role="group" id="type-buttons">
                                    <input type="radio" class="btn-check" name="type" id="type-add" value="+" checked>
                                    <label class="btn btn-outline-success d-flex align-items-center justify-content-center py-2" for="type-add">
                                        <i class="bi bi-plus-circle me-2"></i>
                                        Add Invites
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="type" id="type-remove" value="-">
                                    <label class="btn btn-outline-danger d-flex align-items-center justify-content-center py-2" for="type-remove">
                                        <i class="bi bi-dash-circle me-2"></i>
                                        Remove Invites
                                    </label>
                                </div>
                                <div class="form-text text-center mt-2">Select to add or subtract invites</div>
                            </div>
                        </div>
                        
                        <!-- Блок предварительного просмотра -->
                        <div class="row mt-5">
                            <div class="col-12">
                                <div id="previewBox" class="alert alert-info border-0 shadow-sm d-none animated-preview">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white p-3 rounded-circle me-3">
                                                <i class="bi bi-graph-up-arrow text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Operation Impact Preview</h6>
                                                <p class="mb-0 text-muted">
                                                    This action will affect 
                                                    <span class="badge bg-primary badge-count ms-2" id="previewCount">0</span> 
                                                    users
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block">Estimated change</small>
                                            <h5 class="mb-0 fw-bold" id="estimatedChange">+0 invites</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Кнопки действий -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex gap-3 justify-content-end border-top pt-4">
                                    <button type="reset" class="btn btn-outline-secondary px-4 py-2 d-flex align-items-center">
                                        <i class="bi bi-arrow-repeat me-2"></i>
                                        Reset All
                                    </button>
                                    <button type="button" id="previewBtn" class="btn btn-info px-4 py-2 d-flex align-items-center">
                                        <i class="bi bi-eye me-2"></i>
                                        Update Preview
                                    </button>
                                    <button type="submit" class="btn btn-primary px-5 py-2 d-flex align-items-center shadow-lg">
                                        <i class="bi bi-rocket-takeoff me-2"></i>
                                        Execute Operation
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-warning border-warning border-start border-5 bg-warning bg-opacity-10 mb-0">
                                <div class="d-flex">
                                    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning me-3"></i>
                                    <div>
                                        <h6 class="alert-heading mb-1">Important Notice</h6>
                                        <p class="mb-0 small">This is a permanent bulk operation. Changes cannot be undone automatically.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success border-success border-start border-5 bg-success bg-opacity-10 mb-0">
                                <div class="d-flex">
                                    <i class="bi bi-check-circle-fill fs-4 text-success me-3"></i>
                                    <div>
                                        <h6 class="alert-heading mb-1">Safety Features</h6>
                                        <p class="mb-0 small">Negative invite counts are automatically prevented.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SweetAlert2 + AJAX Preview -->
        
		<script src="<?= $BASEURL; ?>/scripts/sweetalert2.min.js"></script>
		
        <script>
        const form = document.getElementById('massInviteForm');
        const previewBox = document.getElementById('previewBox');
        const previewCount = document.getElementById('previewCount');
        const estimatedChange = document.getElementById('estimatedChange');
        const previewBtn = document.getElementById('previewBtn');
       

        // Функция обновления предварительного просмотра
        function updatePreview() {
            const amount = document.getElementById('amount').value;
            const typeElement = document.querySelector('input[name="type"]:checked');
            
            if (!amount || !typeElement) return;
            
            const data = new FormData(form);
            data.append('preview', 'yes');

            fetch("<?= $this->currentScript ?>", {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(json => {
                const count = json.count;
                const type = typeElement.value;
                
                previewCount.textContent = count;
                previewBox.classList.remove('d-none');
                
                // Обновляем расчетное изменение
                const change = type === '+' ? `+${amount}` : `-${amount}`;
                estimatedChange.textContent = `${change} invites`;
                estimatedChange.className = `mb-0 fw-bold ${type === '+' ? 'text-success' : 'text-danger'}`;
                
                // Обновляем стиль в зависимости от количества пользователей
                if (count > 0) {
                    previewBox.className = 'alert alert-info border-0 shadow-sm animated-preview';
                } else {
                    previewBox.className = 'alert alert-warning border-0 shadow-sm';
                    estimatedChange.textContent = 'No changes';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Функция загрузки статистики
        

        // Обработчики событий
        form.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('change', updatePreview);
            el.addEventListener('input', updatePreview);
        });

        // Кнопка обновления предпросмотра
        previewBtn.addEventListener('click', updatePreview);

        // Обработчик отправки формы
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('amount').value;
            const typeElement = document.querySelector('input[name="type"]:checked');
            const affected = previewCount.textContent;

            if (!typeElement || amount < 1) {
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please fill in all fields correctly.',
                    icon: 'error',
                    confirmButtonColor: '#764ba2'
                });
                return;
            }

            const type = typeElement.value;
            const actionText = type === '+' ? 'ADD' : 'REMOVE';
            const iconType = type === '+' ? 'success' : 'warning';

            Swal.fire({
                title: '🚀 Confirm Bulk Operation',
                html: `
                    <div class="text-center">
                        <div class="mb-4">
                            <div class="display-6 mb-2 ${type === '+' ? 'text-success' : 'text-danger'}">
                                ${type === '+' ? '+' : '-'}${amount}
                            </div>
                            <p class="text-muted">invites per user</p>
                        </div>
                        
                        <div class="alert alert-${type === '+' ? 'success' : 'danger'} border-0 mb-3" style="background: ${type === '+' ? 'rgba(25, 135, 84, 0.1)' : 'rgba(220, 53, 69, 0.1)'}">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-${type === '+' ? 'plus' : 'dash'}-circle-fill fs-3 me-3 ${type === '+' ? 'text-success' : 'text-danger'}"></i>
                                <div>
                                    <h6 class="mb-0">${actionText} Operation</h6>
                                    <small class="text-muted">${affected} users will be affected</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-start mt-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <span>Negative values are prevented</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-clock-history text-info me-2"></i>
                                <span>Operation will be logged</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                                <span>This action is irreversible</span>
                            </div>
                        </div>
                    </div>
                `,
                icon: iconType,
                showCancelButton: true,
                confirmButtonText: type === '+' ? `Add ${amount} Invites` : `Remove ${amount} Invites`,
                cancelButtonText: 'Cancel',
                confirmButtonColor: type === '+' ? '#198754' : '#dc3545',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                width: 500,
                customClass: {
                    confirmButton: `btn btn-${type === '+' ? 'success' : 'danger'} px-4 py-2`,
                    cancelButton: 'btn btn-secondary px-4 py-2',
                    popup: 'rounded-3'
                }
            }).then(result => {
                if (result.isConfirmed) {
                    // Показываем красивый индикатор загрузки
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalHtml = submitBtn.innerHTML;
                    
                    Swal.fire({
                        title: 'Processing...',
                        html: `
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="text-muted">Updating invites for ${affected} users</p>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                                </div>
                            </div>
                        `,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Отправляем форму с небольшой задержкой для красоты
                    setTimeout(() => {
                        form.submit();
                    }, 1500);
                }
            });
        });

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
            
            
            // Добавляем дополнительные стили
            const style = document.createElement('style');
            style.textContent = `
                .btn-check:checked + .btn-outline-success {
                    background: linear-gradient(135deg, #20c997 0%, #198754 100%);
                    color: white !important;
                    border-color: #198754;
                }
                
                .btn-check:checked + .btn-outline-danger {
                    background: linear-gradient(135deg, #ff6b6b 0%, #dc3545 100%);
                    color: white !important;
                    border-color: #dc3545;
                }
                
                .btn-group .btn {
                    border-radius: 8px !important;
                    margin: 0 2px;
                }
                
                .progress-bar {
                    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                }
                
                #previewBox {
                    animation: slideIn 0.5s ease-out;
                }
                
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .form-control:focus {
                    animation: pulseShadow 0.5s ease-out;
                }
                
                @keyframes pulseShadow {
                    0% { box-shadow: 0 0 0 0 rgba(118, 75, 162, 0.4); }
                    70% { box-shadow: 0 0 0 10px rgba(118, 75, 162, 0); }
                    100% { box-shadow: 0 0 0 0 rgba(118, 75, 162, 0); }
                }
            `;
            document.head.appendChild(style);
        });

        // Добавляем звуковой эффект при наведении на кнопки (опционально)
        document.querySelectorAll('#type-buttons .btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                if (typeof Audio !== 'undefined') {
                    const audio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ');
                    audio.volume = 0.1;
                    audio.play().catch(() => {});
                }
            });
        });
        </script>
        <?php
        stdfoot();
    }
}

// Run
$massInviteManager = new MassInviteManager($db, $_this_script_);
$massInviteManager->handleRequest();
?>