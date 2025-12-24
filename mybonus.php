<?php
declare(strict_types=1);

/***********************************************/
/*   MYBONUS.PHP — PHP 8.4 — CLEAN BEAUTIFUL   */           
/***********************************************/

define("IN_MYBB", 1);

require_once 'global.php';

require_once INC_PATH . '/functions_pm.php';
  
// Include our base data handler class
require_once INC_PATH . '/datahandler.php';


// Authorization check
if (!$CURUSER || ($CURUSER['id'] ?? 0) == 0) {
    print_no_permission();
}

$lang->load('mybonus');
$points = (int)($CURUSER['seedbonus'] ?? 0);
$userid = (int)$CURUSER['id'];

$errors = [];
$messages = [];

// === PURCHASE PROCESSING ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $query = $db->simple_select("bonus", "*", "id = " . (int)$id);
    $bonus = $db->fetch_array($query);

    if (!$bonus) {
        $errors[] = $lang->mybonus['error1'] ?? 'Bonus not found';
    } elseif ($points < $bonus['points']) {
        $errors[] = sprintf($lang->mybonus['error2'] ?? 'Not enough points: %d out of %d', $points, $bonus['points']);
    } else {
        $used = false;
        
        // Determine bonus type and process
        switch ($bonus['art']) {
            case 'traffic':
                purchase($db, $userid, "uploaded = uploaded + {$bonus['menge']}", $bonus, $used);
                break;
            case 'invite':
                purchase($db, $userid, "invites = invites + {$bonus['menge']}", $bonus, $used);
                break;
            case 'title':
                // For title show form if not submitted
                if (isset($_POST['update_title']) && $_POST['update_title'] === 'yes') {
                    handleTitle($db, $userid, $bonus, $used);
                } else {
                    showTitleForm($bonus);
                    exit;
                }
                break;
            case 'gift_1':
                // For gift show form if not submitted
                if (isset($_POST['send_gift']) && $_POST['send_gift'] === 'yes') {
                    handleGift($db, $userid, $bonus, $used);
                } else {
                    showGiftForm($bonus);
                    exit;
                }
                break;
            case 'warning':
                purchase($db, $userid, "timeswarned = GREATEST(0, timeswarned - {$bonus['menge']})", $bonus, $used);
                break;
            case 'ratiofix':
                // For ratiofix show form if not submitted
                if (isset($_POST['ratiofix']) && $_POST['ratiofix'] === 'yes') {
                    handleRatioFix($db, $userid, $bonus, $used);
                } else {
                    showRatioFixForm($bonus);
                    exit;
                }
                break;
            default:
                $errors[] = "Unknown bonus";
        }

        if ($used && empty($errors)) {
            $messages[] = sprintf($lang->mybonus['message1'] ?? 'Purchased: %s', htmlspecialchars_uni($bonus['bonusname']));
            $points -= $bonus['points'];
            // Update user cache
            $CURUSER['seedbonus'] = $points;
        }
    }
}

// === FUNCTIONS ===
function purchase($db, $uid, $field, $b, &$used): void {
    $db->sql_query("UPDATE users SET $field, seedbonus = seedbonus - {$b['points']} WHERE id = " . (int)$uid);
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
    }
}

function logBonus($db, $uid, $b): void {
    $comment = TIMENOW . ' - ' . $b['bonusname'] . ' (-' . $b['points'] . " pts)\n";
    $db->sql_query("UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment, ''), '" . $db->escape_string($comment) . "') WHERE id = " . (int)$uid);
}

function handleTitle($db, $uid, $b, &$used): void {
    global $errors, $CURUSER;
    
    $title = trim($_POST['title'] ?? '');
    if (strlen($title) < 2) {
        $errors[] = "Title too short!";
        return;
    }
    
    $db->sql_query("UPDATE users SET title = " . $db->sqlesc(htmlspecialchars_uni($title)) . ", seedbonus = seedbonus - {$b['points']} WHERE id = " . (int)$uid);
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
    }
}

function showTitleForm($b): void {
    global $CURUSER, $mybb;
    
    stdhead("Buy title");
    echo '<div class="container py-5"><div class="card shadow"><div class="card-body">
        <form method="post">
            <input type="hidden" name="id" value="'.$b['id'].'">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
            <input type="hidden" name="update_title" value="yes">
            <div class="mb-3">
                <label class="form-label fw-bold">New title:</label>
                <input type="text" name="title" class="form-control" value="'.htmlspecialchars_uni($CURUSER['title'] ?? '').'" required>
            </div>
            <button type="submit" class="btn btn-success">Buy for '.$b['points'].' points</button>
            <a href="mybonus.php" class="btn btn-secondary">Cancel</a>
        </form>
        </div></div></div>';
    stdfoot();
    exit;
}

function handleGift($db, $uid, $b, &$used): void {
    global $errors, $CURUSER, $BASEURL, $points, $lang;
    
    $gift = (int)($_POST['gift'] ?? 0);
    $to = trim($_POST['username'] ?? '');
    
    if ($gift < 1) {
        $errors[] = "Invalid gift amount!";
        return;
    }
    
    if ($to === $CURUSER['username']) {
        $errors[] = "Cannot gift to yourself!";
        return;
    }
    
    $query = $db->simple_select("users", "id, seedbonus", "username = " . $db->sqlesc($to));
    $target = $db->fetch_array($query);
    
    if (!$target) {
        $errors[] = "User not found!";
        return;
    }
    
    $total = $b['points'] + $gift;
    
    if ($points < $total) {
        $errors[] = "Not enough points! Needed: {$total}, you have: {$points}";
        return;
    }
    
    // Gift points to recipient
    $db->sql_query("UPDATE users SET seedbonus = seedbonus + {$gift} WHERE id = " . (int)$target['id']);
    // Deduct from sender
    $db->sql_query("UPDATE users SET seedbonus = seedbonus - {$total} WHERE id = " . (int)$uid);
    
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
        
        // Log for recipient
        $comment = "Gift: {$gift} points from " . $CURUSER['username'];
        $db->sql_query("UPDATE users SET bonuscomment = CONCAT(COALESCE(bonuscomment, ''), '" . $db->escape_string($comment) . "') WHERE id = " . (int)$target['id']);
		
	    $profilelink = $BASEURL . '/'.get_profile_link((int)$uid).'';
		
		// ===== Send PM =====
        $pm = array(
            'subject' => sprintf($lang->mybonus['giftsubject']),
            'message' => sprintf(
                $lang->mybonus['giftmsg'],
                '[b]' . $target['username'] . '[/b]',
                '[URL='.$profilelink.'][b]' . $CURUSER['username'] . '[/b][/URL]',
                $gift
            ),
            'touid' => (int)$target['id']
        );
		
        // Sender - System (-1)
        $pm['sender']['uid'] = -1;

        if (function_exists('send_pm')) {
            send_pm($pm, -1, true);
        }	
		
    }
}

function showGiftForm($b): void {
    global $mybb, $points;
    
    stdhead("Gift points");
    echo '<div class="container py-5"><div class="card shadow"><div class="card-body">
        <form method="post">
            <input type="hidden" name="id" value="'.$b['id'].'">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
            <input type="hidden" name="send_gift" value="yes">
            
            <div class="alert alert-info">
                <strong>Information:</strong> You have <strong>'.$points.'</strong> points available<br>
                Transfer fee: <strong>'.$b['points'].'</strong> points<br>
                Maximum you can gift: <strong>'.($points - $b['points']).'</strong> points
            </div>
            
            <div class="mb-3">
                <label class="form-label">To (username):</label>
                <input type="text" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">How many points to gift:</label>
                <input type="number" name="gift" class="form-control" min="1" max="'.($points - $b['points']).'" required>
            </div>
            
            <button type="submit" class="btn btn-success">Gift points</button>
            <a href="mybonus.php" class="btn btn-secondary">Cancel</a>
        </form>
        </div></div></div>';
    stdfoot();
    exit;
}

function handleRatioFix($db, $uid, $b, &$used): void {
    global $errors;
    
    $tid = (int)($_POST['torrentid'] ?? 0);
    
    if ($tid <= 0) {
        $errors[] = "Invalid torrent ID!";
        return;
    }
    
    $query = $db->simple_select("snatched", "uploaded, downloaded, seedtime", "torrentid = '{$tid}' AND userid = '{$uid}' AND finished = 'yes'");
    $snatch = $db->fetch_array($query);
    
    if (!$snatch) {
        $errors[] = "Torrent not found!";
        return;
    }
    
    $db->sql_query("UPDATE snatched SET uploaded = downloaded, seedtime = GREATEST(seedtime, 3600*24) WHERE torrentid = '{$tid}' AND userid = '{$uid}'");
    $db->sql_query("UPDATE users SET seedbonus = seedbonus - {$b['points']} WHERE id = '{$uid}'");
    
    if ($db->affected_rows()) {
        logBonus($db, $uid, $b);
        $used = true;
    }
}

function showRatioFixForm($b): void {
    global $mybb;
    
    stdhead("Fix ratio");
    echo '<div class="container py-5"><div class="card shadow"><div class="card-body">
        <form method="post">
            <input type="hidden" name="id" value="'.$b['id'].'">
            <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
            <input type="hidden" name="ratiofix" value="yes">
            <div class="mb-3">
                <label class="form-label">Torrent ID:</label>
                <input type="number" name="torrentid" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">Fix</button>
            <a href="mybonus.php" class="btn btn-secondary">Cancel</a>
        </form>
        </div></div></div>';
    stdfoot();
    exit;
}

// === CARD OUTPUT ===
$query = $db->simple_select("bonus", "*", "", ["order_by" => "points"]);
$cards = '';

while ($b = $db->fetch_array($query)) {
    $disabled = $points < $b['points'];
    $bg = match($b['art']) {
        'traffic' => 'success',
        'invite'  => 'info',
        'title'   => 'warning',
        'gift_1'  => 'danger',
        'warning' => 'secondary',
        'ratiofix'=> 'dark',
        default   => 'primary'
    };
    
    $cards .= '
    <div class="col-md-6 col-xl-4 mb-4">
        <div class="card h-100 shadow-sm border-0 hover-lift '.($disabled?'opacity-75':'').'">
            <div class="card-header bg-'.$bg.' text-white">
                <h5 class="mb-0"><i class="fas fa-gem me-2"></i> '.htmlspecialchars_uni($b['bonusname']).'</h5>
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted small flex-grow-1">'.nl2br(htmlspecialchars_uni($b['description'])).'</p>
                <div class="mt-auto">
                    <span class="badge bg-dark fs-6 px-3 py-2">'.$b['points'].' points</span>
                    <form method="post" class="d-inline float-end">
                        <input type="hidden" name="id" value="'.$b['id'].'">
                        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">';
    
    // Do NOT add hidden fields for special bonuses - they will be processed in switch
    
    $cards .= '<button type="submit" class="btn btn-outline-'.$bg.' btn-sm" '.($disabled?'disabled':'').'>
                            Buy
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>';
}

// === HTML ===
stdhead("My Bonuses — {$points} points");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .hover-lift { transition: all .3s; }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,.15)!important; }
        .card-header { border-bottom: none; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary">
            <i class="fas fa-coins me-3"></i>My Bonuses
        </h1>
        <p class="lead">You have: <strong class="text-success fs-3"><?php echo $points; ?> points</strong></p>
    </div>

    <?php
    // Display errors and messages via toast with animations
    if (!empty($errors) || !empty($messages)) {
        echo '
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
            <div id="toastContainer">';
        
        $toastCount = 0;
        foreach ($errors as $e) {
            echo '
            <div class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000">
                <div class="toast-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong class="me-auto">Error</strong>
                    <small>just now</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    '.htmlspecialchars($e).'
                </div>
            </div>';
            $toastCount++;
        }

        foreach ($messages as $m) {
            echo '
            <div class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="toast-header bg-success text-white">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong class="me-auto">Success</strong>
                    <small>just now</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    '.htmlspecialchars($m).'
                </div>
            </div>';
            $toastCount++;
        }
        
        echo '</div>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toasts = document.querySelectorAll(".toast");
            toasts.forEach((toast, index) => {
                const bsToast = new bootstrap.Toast(toast);
                // Delay for sequential display
                setTimeout(() => {
                    bsToast.show();
                }, index * 300);
                
                // Auto-hide on hover
                toast.addEventListener("mouseenter", () => {
                    bsToast._config.delay = 10000;
                });
                toast.addEventListener("mouseleave", () => {
                    bsToast._config.delay = 5000;
                });
            });
        });
        </script>';
    }
    ?>

    <div class="row g-4">
        <?php echo $cards; ?>
    </div>
</div>
</body>
</html>
<?php
stdfoot();