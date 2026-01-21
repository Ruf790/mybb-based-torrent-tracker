<?php
/**
 * ====================================================
 * TS Special Edition v5.6 - Enhanced Mail Sender
 * ====================================================
 * 
 * @package     TSSE_Mailer
 * @version     5.6
 * @author      xam (Modified for PHP 8.5)
 * @copyright   Special Coders Network
 * 
 * Special Thanks:
 * - DrNet       (wWw.SpecialCoders.CoM)
 * - Vinson      (wWw.Decode4u.CoM)
 * - MrDecoder   (wWw.Fearless-Releases.CoM)
 * - Fynnon      (wWw.BvList.CoM)
 * ====================================================
 */

declare(strict_types=1);

/**
 * Strip selected HTML tags from text
 */
function strip_selected_tags(string $text, array $tags = []): string
{
    $args = func_get_args();
    $text = array_shift($args);
    $tags = func_num_args() > 2 ? array_diff($args, [$text]) : $tags;
    
    foreach ($tags as $tag) {
        while (preg_match('/<' . $tag . '(|\W[^>]*)>(.*)<\/' . $tag . '>/iusU', $text, $found)) {
            $text = str_replace($found[0], $found[2], $text);
        }
    }
    
    $pattern = '/(<(' . implode('|', $tags) . ')(|\W.*)\/>)/iusU';
    return preg_replace($pattern, '', $text);
}

/**
 * Convert HTML to plain text
 */
function html2txt(string $document): string
{
    $patterns = [
        '@<script[^>]*?>.*?</script>@si',
        '@<style[^>]*?>.*?</style>@siU',
        '@<![\s\S]*?--[\t\n\r]*>@'
    ];
    
    return preg_replace($patterns, '', $document);
}

// Security check
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert">
        <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

// Constants
define('SM_VERSION', '0.4 by xam');
define('IN_MYBB', 1);

// Initialization
$error = '';
$msgtext = trim($_POST['message'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$email = htmlspecialchars_uni($_GET['email'] ?? $_POST['email'] ?? '', ENT_QUOTES);

// External preview layer
$externalpreview = <<<HTML
<div id="loading-layer" class="loading-overlay">
    <div class="loading-content">
        <div class="loading-text fw-bold small" id="loading-layer-text">
            Sending... Please wait...
        </div>
        <br>
        <img src="{$BASEURL}/{$pic_base_url}await.gif" alt="Loading" class="loading-spinner">
    </div>
</div>
HTML;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_name = ($_POST['sender'] ?? '') === 'system' ? $SITENAME : $CURUSER['username'];
    
    // Validation
    if (empty($msgtext) || empty($subject) || strlen($msgtext) <= 5) {
        $error = 'Please fill in all fields and ensure the message is at least 6 characters long.';
    }
    
    // Send email
    if (empty($error)) {
        $format = "html";
        $text_message = "";
        
        $msendmail = my_mail(
            $email,
            $subject,
            $msgtext,
            "",
            "",
            "",
            false,
            $format,
            $text_message
        );
        
        if ($msendmail) {
            $error = '<div class="alert alert-success" role="alert">
                <strong>Success!</strong> The message has been sent successfully.
            </div>';
        } else {
            $error = '<div class="alert alert-danger" role="alert">
                <strong>Error!</strong> Unable to send email. Please try again.
            </div>';
        }
    }
}

// Start output
stdhead('Send Mail', true, '', '');

// Display status message
if (!empty($error)) {
    echo <<<HTML
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-info-circle me-2"></i>Status
                </div>
                <div class="card-body">
                    {$error}
                </div>
            </div>
        </div>
    </div>
</div>
<br>
HTML;
}

// Display preview if set
if (isset($prvp)) {
    echo $prvp;
}

// Mail form
?>
<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card">
                 <div class="card-header bg-primary text-white rounded-top-3">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-paper-plane fa-2x me-3"></i>
                        <div>
                            <h2 class="h4 mb-0">Send Email</h2>
                            <small class="opacity-75"><?= $SITENAME ?></small>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME']) ?>" 
                      id="mailForm" class="needs-validation" novalidate>
                    <input type="hidden" name="act" value="sendmail">
                    
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label for="emailInput" class="form-label fw-semibold">
                                <i class="fas fa-envelope me-2 text-primary"></i>Recipient Email
                            </label>
                            <input type="email" 
                                   name="email" 
                                   id="emailInput"
                                   value="<?= $email ?>" 
                                   class="form-control form-control-lg" 
                                   placeholder="recipient@example.com"
                                   required>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="subjectInput" class="form-label fw-semibold">
                                <i class="fas fa-heading me-2 text-primary"></i>Subject
                            </label>
                            <input type="text" 
                                   name="subject" 
                                   id="subjectInput"
                                   value="<?= htmlspecialchars_uni($_POST['subject'] ?? '') ?>" 
                                   class="form-control form-control-lg" 
                                   placeholder="Email Subject"
                                   required>
                            <div class="invalid-feedback">
                                Please enter a subject for your email.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="messageInput" class="form-label fw-semibold">
                                <i class="fas fa-comment-alt me-2 text-primary"></i>Message
                            </label>
                            <textarea name="message" 
                                      id="messageInput" 
                                      rows="10" 
                                      class="form-control"
                                      placeholder="Type your message here..."
                                      required><?= htmlspecialchars_uni($_POST['message'] ?? '') ?></textarea>
                            <div class="invalid-feedback">
                                Please enter a message (minimum 6 characters).
                            </div>
                            <div class="form-text mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    HTML formatting is supported.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light py-3">
                        <div class="d-flex justify-content-center gap-3">
                            <button type="submit" 
                                    name="submit" 
                                    class="btn btn-primary btn-lg px-5"
                                    onclick="document.getElementById('loading-layer').style.display='block';">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                            
                            <button type="reset" class="btn btn-outline-secondary btn-lg px-5">
                                <i class="fas fa-redo me-2"></i>Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $externalpreview ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('mailForm');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    // Loading overlay
    window.ts_show = function(elementId) {
        document.getElementById(elementId).style.display = 'block';
    };
});
</script>

<style>
.loading-overlay {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
    text-align: center;
    z-index: 9999;
    display: none;
    border: 1px solid #dee2e6;
}

.loading-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.loading-spinner {
    width: 48px;
    height: 48px;
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.form-control:focus {
    border-color: #4dabf7;
    box-shadow: 0 0 0 0.25rem rgba(77, 171, 247, 0.25);
}

.rounded-top-3 {
    border-top-left-radius: 1rem !important;
    border-top-right-radius: 1rem !important;
}
</style>

<?php
stdfoot();
?>