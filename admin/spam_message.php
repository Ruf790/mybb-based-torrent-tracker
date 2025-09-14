<?php

$rootpath = './../';
$thispath = './';
define("IN_MYBB", 1);

require_once $rootpath . 'global.php';


require_once(INC_PATH.'/class_parser.php');

$parser = new postParser;
$parser_options = array(
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);



$pmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pmid <= 0) {
    die("Invalid message ID");
}

// Fetch full message
$query = "SELECT pm.*,
                 u.username AS sender_name, u.avatar AS sender_avatar, u.avatardimensions AS sender_avatardimensions, u.email AS sender_email,
                 u2.username AS receiver_name, u2.avatar AS receiver_avatar, u2.avatardimensions AS receiver_avatardimensions, u2.email AS receiver_email
          FROM privatemessages pm
          LEFT JOIN users u ON pm.fromid = u.id
          LEFT JOIN users u2 ON pm.toid = u2.id
          WHERE pm.pmid = $pmid";

$row = $db->fetch_array($db->sql_query($query));

if (!$row) {
    die("Message not found");
}

// Update status to "read" if necessary
if ($row['status'] == 0) {
    $db->sql_query("UPDATE privatemessages SET status = 1, readtime = UNIX_TIMESTAMP() WHERE pmid = $pmid");
}












$max_dimensions = '34x34';
$avatar = format_avatar($row['sender_avatar'], $row['sender_avatardimensions'], $max_dimensions);

$sender_avatar = $avatar['image'];






$avatarz = format_avatar($row['receiver_avatar'], $row['receiver_avatardimensions'], $max_dimensions);

$receiver_avatar = $avatarz['image'];







?>

<div class="message-details">
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="d-flex align-items-center mb-2">
        <?php if ($row['sender_avatar']): ?>
          
		  
		  <img src="<?= $sender_avatar ?>" class="avatar-sm me-2">
		  
		  
        <?php else: ?>
          <div class="avatar-sm bg-secondary me-2 d-flex align-items-center justify-content-center">
            <i class="bi bi-person text-white"></i>
          </div>
        <?php endif; ?>
        <div>
          <h6 class="mb-0">Sender</h6>
          <div>
            <strong><?=htmlspecialchars($row['sender_name'] ?? "System ".$row['fromid'])?></strong>
            <?php if ($row['sender_email']): ?>
              <br><small class="text-muted"><?=htmlspecialchars($row['sender_email'])?></small>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="d-flex align-items-center mb-2">
        <?php if ($row['receiver_avatar']): ?>
          
		  
		  
		  <img src="<?= $receiver_avatar ?>" class="avatar-sm me-2"> 
		  
		  
        <?php else: ?>
          <div class="avatar-sm bg-secondary me-2 d-flex align-items-center justify-content-center">
            <i class="bi bi-person text-white"></i>
          </div>
        <?php endif; ?>
        <div>
          <h6 class="mb-0">Recipient</h6>
          <div>
            <strong><?=htmlspecialchars($row['receiver_name'] ?? "UID ".$row['toid'])?></strong>
            <?php if ($row['receiver_email']): ?>
              <br><small class="text-muted"><?=htmlspecialchars($row['receiver_email'])?></small>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <div class="mb-2">
        <h6 class="mb-0">Sent Date</h6>
        <div><?=date("d.m.Y H:i:s", $row['dateline'])?></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="mb-2">
        <h6 class="mb-0">IP Address</h6>
        <div><?=inet_ntop($row['ipaddress'])?></div>
      </div>
    </div>
  </div>

  <hr>

  
  












<!-- Tabs -->
<ul class="nav nav-tabs" id="msgTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="rendered-tab" data-bs-toggle="tab" data-bs-target="#rendered"
            type="button" role="tab" aria-controls="rendered" aria-selected="true">
      Rendered
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw"
            type="button" role="tab" aria-controls="raw" aria-selected="false">
      Raw
    </button>
  </li>
</ul>

<div class="tab-content pt-3" id="msgTabsContent">
  <!-- Rendered view -->
  <div class="tab-pane fade show active" id="rendered" role="tabpanel" aria-labelledby="rendered-tab">
    <div class="message-content bg-light p-3 rounded">
      <?php
      if (isset($parser) && method_exists($parser, 'parse_message')) {
          echo $parser->parse_message($row['message'], $parser_options ?? []);
      } else {
          echo nl2br(htmlspecialchars($row['message']));
      }
      ?>
    </div>
  </div>

  <!-- Raw view -->
  
  <!-- Raw view -->
<div class="tab-pane fade" id="raw" role="tabpanel" aria-labelledby="raw-tab">
  <div class="message-content bg-light text-black p-3 rounded position-relative"
       data-pmid="<?= (int)$pmid ?>"
       data-sent="<?= date('Y-m-d_H-i-s', (int)$row['dateline']) ?>">
    <div class="position-absolute" style="top:10px; right:10px;">
      <button type="button" class="btn btn-sm btn-outline-light me-2"
              onclick="copyRawMessage()" title="Copy to clipboard">
        Copy
      </button>
      <button type="button" class="btn btn-sm btn-outline-light"
              onclick="downloadRawMessage()" title="Download as .txt">
        Download .txt
      </button>
    </div>
    <pre class="mb-0" id="rawMessage"><?=htmlspecialchars($row['message'])?></pre>
  </div>
</div>
  
  
  
  
</div>




<!-- Toast (global, shown on copy/download) -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080;">
  <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Done</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>





<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
  <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Copied to clipboard</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
  const PM_CONTEXT = {
    id: <?= (int)$pmid ?>,
    sentAt: "<?= date('Y-m-d_H-i-s', (int)$row['dateline']) ?>"
  };

  function copyRawMessage() {
    const el = document.getElementById('rawMessage');
    const text = el.innerText || el.textContent || '';

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(showToast).catch(fallbackCopy);
    } else {
      fallbackCopy();
    }

    function fallbackCopy() {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      try { document.execCommand('copy'); showToast(); } catch(e) {}
      document.body.removeChild(ta);
    }

    function showToast() {
      const toastEl = document.getElementById('copyToast');
      if (window.bootstrap && toastEl) {
        toastEl.querySelector('.toast-body').textContent = 'Copied to clipboard';
        new bootstrap.Toast(toastEl, { delay: 1200 }).show();
      }
    }
  }

  function downloadRawMessage() {
    const el = document.getElementById('rawMessage');
    const text = el.innerText || el.textContent || '';

    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const fname = `pm-${PM_CONTEXT.id}-${PM_CONTEXT.sentAt}.txt`;

    a.href = url;
    a.download = fname;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    const toastEl = document.getElementById('copyToast');
    if (window.bootstrap && toastEl) {
      toastEl.querySelector('.toast-body').textContent = `Saved ${fname}`;
      new bootstrap.Toast(toastEl, { delay: 1400 }).show();
    }
  }
</script>


  
  
  
  
  
  
  
  
  
  
  
  
</div>
