<?php
declare(strict_types=1);

/**
 * Full-page chat. The same box can be put on any page: echo chat_render_box();
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'chat.php');
define('SCRIPTNAME', 'chat.php');

require_once 'global.php';

$lang->load('chat');

require_once INC_PATH . '/functions_chat.php';

if (empty($CURUSER['id'])) {
    if (function_exists('loggedinorreturn')) {
        loggedinorreturn();
    }
    header('Location: ' . $BASEURL . '/login.php');
    exit;
}

stdhead($lang->chat['pg_title'] ?? 'Chat');
?>
<div class="container my-3">
    <?php if (!chat_can_read($CURUSER)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($lang->chat['err_login'] ?? 'Log in to use the chat.', ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
        <?= chat_render_box(true) ?>
        <p class="text-body-secondary small mb-0">
            <i class="fa-solid fa-circle-info me-1"></i><?= htmlspecialchars(
                str_replace(['{1}', '%1$s'], (string)CHAT_MAX_LENGTH, (string)($lang->chat['hint_rules'] ?? 'BBCode and smilies work. Up to {1} characters. Click a name to mention it.')),
                ENT_QUOTES, 'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>
</div>
<?php
stdfoot();
