<?php




// Admin permission check
// if(!$CURUSER || !$CURUSER['admin']) die("Access denied");

// Pagination settings
$perPage = 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Search and filter parameters
$search = isset($_GET['q']) ? trim($_GET['q']) : "";
$filterFrom = isset($_GET['from']) ? (int)$_GET['from'] : 0;
$filterTo   = isset($_GET['to'])   ? (int)$_GET['to']   : 0;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build WHERE conditions
$where = [];

if (!empty($search)) {
    $esc = $db->sqlesc("%$search%");
    $where[] = "(pm.subject LIKE $esc OR pm.message LIKE $esc)";
}

if ($filterFrom > 0) {
    $where[] = "pm.fromid = " . (int)$filterFrom;
}

if ($filterTo > 0) {
    $where[] = "pm.toid = " . (int)$filterTo;
}

if ($filterStatus !== 'all') {
    $where[] = "pm.status = " . ($filterStatus === 'read' ? 1 : 0);
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total messages count
$totalQuery = "SELECT COUNT(*) as c FROM privatemessages pm $whereClause";
$totalRes   = $db->sql_query($totalQuery);
$totalRow   = $db->fetch_array($totalRes);
$total      = $totalRow['c'];

// Get messages with user names
$query = "SELECT pm.*, 
                 u.username AS sender_name, u.avatar AS sender_avatar, u.avatardimensions AS sender_avatardimensions, 
                 u2.username AS receiver_name, u2.avatar AS receiver_avatar, u2.avatardimensions AS receiver_avatardimensions
          FROM privatemessages pm
          LEFT JOIN users u  ON pm.fromid = u.id
          LEFT JOIN users u2 ON pm.toid   = u2.id
          $whereClause
          ORDER BY pm.dateline DESC
          LIMIT $offset, $perPage";

$res = $db->sql_query($query);








// Если не задан — берём текущий путь + QS
if (empty($_this_script_)) {
    $_this_script_ = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?'.$_SERVER['QUERY_STRING'] : '');
}

/**
 * Собирает URL на основе $_this_script_, $_GET и overrides,
 * с фиксированным порядком ключей: act, page, q, from, to, status.
 */
function build_url(array $overrides = []): string {
    global $_this_script_;
    $base   = $_this_script_;
    $path   = strtok($base, '?'); // путь без QS
    $baseQs = [];
    $qpos   = strpos($base, '?');
    if ($qpos !== false) {
        parse_str(substr($base, $qpos + 1), $baseQs);
    }

    // Базовые дефолты, чтобы получить именно q=&from=0&to=0&status=all
    $defaults = ['q' => '', 'from' => 0, 'to' => 0, 'status' => 'all'];

    // Объединяем: дефолты → QS из $_this_script_ → текущий $_GET → overrides
    $params = array_merge($defaults, $baseQs, $_GET);
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]); // можно удалить ключ, если нужно
        else $params[$k] = $v;
    }

    // Переупорядочим ключи
    $order   = ['act','page','q','from','to','status'];
    $ordered = [];
    foreach ($order as $k) {
        if (array_key_exists($k, $params)) {
            $ordered[$k] = $params[$k];
            unset($params[$k]);
        }
    }
    // Хвост — любые прочие параметры
    $params = $ordered + $params;

    // RFC3986 — чтобы было красиво и стандартизировано
    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return $path . ($qs ? '?'.$qs : '');
}











stdhead();



?>


  <title>Admin: Private Messages</title>

  <link rel="stylesheet" href="<?php echo $BASEURL; ?>/include/templates/default/style/bootstrap-icons.css" type="text/css" media="screen" />
  
  <style>
    .avatar-sm {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
    }
    .message-preview {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 200px;
    }
    .unread {
      background-color: #f8f9fa;
      font-weight: 500;
    }
    .status-badge {
      font-size: 0.75rem;
    }
    .user-link {
      text-decoration: none;
      color: inherit;
    }
    .user-link:hover {
      color: #0d6efd;
    }
  </style>


<div class="container mt-3">





<div class="container-fluid py-4">
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
      <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0">
          <i class="bi bi-chat-left-text me-2"></i>Private Messages
        </h4>
        <span class="badge bg-light text-dark">Total: <?=number_format($total)?></span>
      </div>
    </div>
    
    <div class="card-body">
      <!-- Search and filter form -->
      <form class="mb-4">
        <div class="row g-3">
          <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="Search by subject or text" value="<?=htmlspecialchars($search)?>">
          </div>
          <div class="col-md-2">
            <select name="from" class="form-select">
              <option value="0">All Senders</option>
              <?php if ($filterFrom > 0): ?>
                <option value="<?=$filterFrom?>" selected>UID: <?=$filterFrom?></option>
              <?php endif; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select name="to" class="form-select">
              <option value="0">All Recipients</option>
              <?php if ($filterTo > 0): ?>
                <option value="<?=$filterTo?>" selected>UID: <?=$filterTo?></option>
              <?php endif; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select name="status" class="form-select">
              <option value="all" <?=$filterStatus==='all'?'selected':''?>>All Statuses</option>
              <option value="read" <?=$filterStatus==='read'?'selected':''?>>Read</option>
              <option value="unread" <?=$filterStatus==='unread'?'selected':''?>>Unread</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-funnel"></i> Filter
            </button>
          </div>
        </div>
      </form>

      <!-- Messages table -->
      <div class="table-responsive">
        <table class="table table-hover table-sm">
          <thead class="table-light">
            <tr>
              <th width="80">ID</th>
              <th>Sender</th>
              <th>Recipient</th>
              <th>Subject</th>
              <th width="150">Date</th>
              <th width="120">Status</th>
              <th width="120">IP</th>
              <th width="80"></th>
            </tr>
          </thead>
          <tbody>
          <?php while($row = $db->fetch_array($res)): ?>
            <tr class="<?=$row['status'] == 0 ? 'unread' : ''?>">
              <td>#<?=$row['pmid']?></td>
              <td>
                
				

<?php
$max_dimensions = '34x34';
$avatar = format_avatar($row['sender_avatar'], $row['sender_avatardimensions'], $max_dimensions);

$sender_avatar = $avatar['image'];


?>

<div class="d-flex align-items-center">
 <?php if ($row['sender_avatar']): ?> 
 <img src="<?= $sender_avatar ?>" class="avatar-sm me-2"> 
 <?php else: ?> <div class="avatar-sm bg-secondary me-2 d-flex align-items-center justify-content-center"> 
 <i class="bi bi-person text-white"></i> </div> 
 <?php endif; ?> 
 <a href="index.php?act=spam&from=<?=$row['fromid']?>" class="user-link"> <?=htmlspecialchars($row['sender_name'] ?? "System ")?> </a> 
 

 
 
 </div>
 
 
 
 
 

 
 
 
 
 

 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 




				
				
              </td>
              <td>
			  
			  
			  
			  
			  
			  <?php
$max_dimensions = '34x34';
$avatar = format_avatar($row['receiver_avatar'], $row['receiver_avatardimensions'], $max_dimensions);

$receiver_avatar = $avatar['image'];


?>
			  
			  
			  
			  
                <div class="d-flex align-items-center">
                  <?php if ($row['receiver_avatar']): ?>
                    
					
				<img src="<?= $receiver_avatar ?>" class="avatar-sm me-2"> 
					
					
                  <?php else: ?>
                    <div class="avatar-sm bg-secondary me-2 d-flex align-items-center justify-content-center">
                      <i class="bi bi-person text-white"></i>
                    </div>
                  <?php endif; ?>
                 
				 <a href="<?=htmlspecialchars($_this_script_, ENT_QUOTES)?><?=strpos($_this_script_,'?')!==false?'&':'?'?>to=<?= (int)$row['toid'] ?>"class="user-link">
				 
				 
				 
                    <?=htmlspecialchars($row['receiver_name'] ?? "UID ".$row['toid'])?>
                  </a>
                </div>
              </td>
              <td>
                <div class="message-preview" title="<?=htmlspecialchars($row['subject'])?>">
                  <?=htmlspecialchars($row['subject'])?>
                </div>
              </td>
              <td>
                <span title="<?=date("Y-m-d H:i:s", $row['dateline'])?>">
                  <?=date("d.m.Y H:i", $row['dateline'])?>
                </span>
              </td>
              <td>
                <span class="badge status-badge <?=$row['status'] == 1 ? 'bg-success' : 'bg-warning text-dark'?>">
                  <?=$row['status'] == 1 ? 'Read' : 'Unread'?>
                </span>
              </td>
              <td>
                <small class="text-muted"><?=inet_ntop($row['ipaddress'])?></small>
              </td>
              
			  
			  <td class="text-end">
  <button
    class="btn btn-sm btn-outline-primary"
    data-bs-toggle="modal"
    data-bs-target="#msgModal"
    onclick='loadMessage(<?=$row["pmid"]?>, <?=json_encode((string)$row["subject"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>)'>
    <i class="bi bi-eye"></i>
  </button>
</td>
			  
			  
			  
			  
			  
			  
			  
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      
	  




<!-- Pagination -->
<?php if ($total > $perPage): ?>
  <?php
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $startPage  = max(1, $page - 2);
    $endPage    = min($startPage + 4, $totalPages);
  ?>
  <nav aria-label="Page navigation" class="mt-4">
    <ul class="pagination justify-content-center">
      <li class="page-item <?=$page==1?'disabled':''?>">
        <a class="page-link" href="<?=htmlspecialchars(build_url(['page'=>1]), ENT_QUOTES)?>" title="Go to first page" data-bs-toggle="tooltip">
          <i class="bi bi-chevron-double-left"></i>
        </a>
      </li>
      <li class="page-item <?=$page==1?'disabled':''?>">
        <a class="page-link" href="<?=htmlspecialchars(build_url(['page'=>$page-1]), ENT_QUOTES)?>" title="Go to previous page" data-bs-toggle="tooltip">
          <i class="bi bi-chevron-left"></i>
        </a>
      </li>

      <?php if ($startPage > 1): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
      <?php endif; ?>

      <?php for ($i=$startPage; $i<=$endPage; $i++): ?>
        <li class="page-item <?=$i==$page?'active':''?>">
          <a class="page-link" href="<?=htmlspecialchars(build_url(['page'=>$i]), ENT_QUOTES)?>" title="Go to page <?=$i?>" data-bs-toggle="tooltip">
            <?=$i?>
          </a>
        </li>
      <?php endfor; ?>

      <?php if ($endPage < $totalPages): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
      <?php endif; ?>

      <li class="page-item <?=$page==$totalPages?'disabled':''?>">
        <a class="page-link" href="<?=htmlspecialchars(build_url(['page'=>$page+1]), ENT_QUOTES)?>" title="Go to next page" data-bs-toggle="tooltip">
          <i class="bi bi-chevron-right"></i>
        </a>
      </li>
      <li class="page-item <?=$page==$totalPages?'disabled':''?>">
        <a class="page-link" href="<?=htmlspecialchars(build_url(['page'=>$totalPages]), ENT_QUOTES)?>" title="Go to last page" data-bs-toggle="tooltip">
          <i class="bi bi-chevron-double-right"></i>
        </a>
      </li>
    </ul>
  </nav>


  
  

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el)
      });
    });
  </script>








	  
	  
      <?php endif; ?>
    </div>
  </div>
</div>


</div>




<!-- Modal for viewing a message -->
<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="msgModalTitle">Subject</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="msgModalBody">
        Loading...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast (global) -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080;">
  <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Done</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
// Load full message via AJAX
function loadMessage(pmid, subject) {
  const titleEl = document.getElementById('msgModalTitle');
  const bodyEl  = document.getElementById('msgModalBody');

  titleEl.textContent = subject;
  bodyEl.innerHTML = `
    <div class="d-flex align-items-center justify-content-center py-5">
      <div class="spinner-border me-3" role="status" aria-hidden="true"></div>
      <span>Loading message…</span>
    </div>`;

  fetch(`spam_message.php?id=${encodeURIComponent(pmid)}`, { credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
    .then(html => {
      bodyEl.innerHTML = html;

      // re-init tooltips inside the modal content
      if (window.bootstrap) {
        document.querySelectorAll('#msgModalBody [data-bs-toggle="tooltip"]').forEach(el => {
          new bootstrap.Tooltip(el);
        });
      }
    })
    .catch(err => {
      bodyEl.innerHTML = `<div class="alert alert-danger mb-0">
        Failed to load message. ${String(err).replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]))}
      </div>`;
    });
}

// Global helpers for Raw tab (buttons inside fetched HTML call these)
window.copyRawMessage = function () {
  const pre = document.querySelector('#msgModalBody #rawMessage');
  if (!pre) return;

  const text = pre.innerText || pre.textContent || '';
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => showToast('Copied to clipboard')).catch(fallbackCopy);
  } else {
    fallbackCopy();
  }

  function fallbackCopy() {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { document.execCommand('copy'); showToast('Copied to clipboard'); } catch(e) {}
    document.body.removeChild(ta);
  }
};

window.downloadRawMessage = function () {
  // wrapper with data attributes from get_message.php
  const wrap = document.querySelector('#msgModalBody .message-content[data-pmid][data-sent]') 
            || document.querySelector('#msgModalBody .message-content');
  const pre  = document.querySelector('#msgModalBody #rawMessage');
  if (!pre) return;

  const id   = wrap?.getAttribute('data-pmid')  || 'unknown';
  const sent = wrap?.getAttribute('data-sent')  || new Date().toISOString().replace(/[:T]/g,'-').slice(0,19);
  const name = `pm-${id}-${sent}.txt`;

  const text = pre.innerText || pre.textContent || '';
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
  const url  = URL.createObjectURL(blob);

  const a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a); a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);

  showToast(`Saved ${name}`);
};

// Bootstrap tooltip init (outside modal too)
document.addEventListener('DOMContentLoaded', function() {
  if (window.bootstrap) {
    document.querySelectorAll('[data-bs-toggle="tooltip"], [title]').forEach(el => {
      new bootstrap.Tooltip(el);
    });
  }
});

// Toast helper
function showToast(msg) {
  const toastEl = document.getElementById('copyToast');
  if (!toastEl || !window.bootstrap) return console.log(msg);
  toastEl.querySelector('.toast-body').textContent = msg;
  new bootstrap.Toast(toastEl, { delay: 1400 }).show();
}
</script>


<?
stdfoot();
?>

