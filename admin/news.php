<?php

declare(strict_types=1);

/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[AJAX News Panel Update]========*/
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) 
{
    exit("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}

require_once INC_PATH.'/class_parser.php';
$parser = new postParser;

$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

stdhead('Manage Site News');
define('IN_EDITOR', true);
//require_once INC_PATH . '/functions_html.php';

require_once $rootpath . 'cache/smilies.php';
require_once INC_PATH . '/editor.php';
    
$editor = insert_bbcode_editor($smilies, $BASEURL, 'newsMessage');

$str = '<div class="container mt-4">';

$str .= '
' . $editor['toolbar'] . '
<form method="post" id="newsAddForm">
  <div class="mb-3">
    <input type="text" class="form-control" name="subject" placeholder="News Title">
  </div>
  
  <div id="fileIdsContainer"></div>
 
   <div class="mb-3">
  <textarea name="newsMessage" id="newsMessage" class="form-control" rows="6" maxlength="500" aria-describedby="charCount4" placeholder="Write news here..."></textarea>
  <div id="charCount4" class="form-text text-end">0 / 500</div>
  </div>
 

  <button type="submit" name="submit" class="btn btn-primary">Submit News Item</button>
</form>

' . $editor['modal'] . '

<hr />';

// Fetch news with proper error handling
try {
    $res = $db->sql_query('SELECT n.*, u.username, u.usergroup, u.donor FROM news n LEFT JOIN users u ON (u.id=n.userid) ORDER BY n.added DESC');
    
    if ($db->num_rows($res) > 0) 
    {
        require_once INC_PATH . '/functions_mkprettytime.php';
        while ($arr = $db->fetch_array($res)) 
        {
            $newsid = (int)$arr['id'];
            $body2 = $arr['body'] ?? ''; // сырой текст
            $body = $parser->parse_message($body2, $parser_options); // готовый к отображению

            $title = htmlspecialchars($arr['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $userid = (int)$arr['userid'];
            $added = my_datee('relative', $arr['added'] ?? TIME_NOW);
            $postername = format_name($arr['username'] ?? '', $arr['usergroup'] ?? '');
            $by = $postername ? '<a href="'.$BASEURL.'/'.get_profile_link($userid).'"><b>' . $postername . '</b></a>' : 'unknown[' . $userid . ']';

            $escaped_body = htmlspecialchars(str_replace(["\r", "\n"], [' ', ' '], $body2), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            $str .= "<div class='card mb-3' data-newsid='{$newsid}' data-body='{$escaped_body}'>
              <div class='card-header'>
                {$added} by {$by} - 
                <a href='#' class='btn btn-sm btn-outline-primary news-edit'>Edit</a> 
                <a href='#' class='btn btn-sm btn-outline-danger news-delete'>Delete</a>
              </div>
              <div class='card-body'>
                <h5 class='card-title'>{$title}</h5>
                <div class='news-body'>{$body}</div>
              </div>
            </div>";
        }
    } 
    else 
    {
        $str .= '
        <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
        <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">

        <div class="card error-card">
              <div class="card-header22">
                <i class="bi bi-exclamation-triangle-fill error-icon"></i>
                <div>
                  <h2 class="mb-0"></h2>
                  <p class="mb-0 opacity-75"></p>
                </div>
              </div>
              <div class="card-body">
                <div class="alert alert-danger" role="alert">
                 <strong>Sorry, No news available!</strong>
                </div>
              </div>
            </div>';
    }
} catch (Throwable $e) {
    error_log("News panel error: " . $e->getMessage());
    $str .= '<div class="alert alert-danger">Error loading news: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</div>';
}

$str .= '</div>';
echo $str;

// Modal для редактирования
?>



<!-- Edit News Modal with BBCode Editor -->
<div class="modal fade" id="newsEditModal" tabindex="-1" aria-labelledby="newsEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="newsEditModalLabel">Edit News</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="newsid" id="editNewsId">
        
        <div class="mb-3">
          <input type="text" class="form-control" id="editTitle" name="title" placeholder="News Title">
        </div>

        <div class="mb-2">
          <!-- Text Styles -->
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[b]', '[/b]')"><b>B</b></button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[i]', '[/i]')"><i>I</i></button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[u]', '[/u]')"><u>U</u></button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[s]', '[/s]')"><s>S</s></button>

          <!-- Alignment -->
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[left]', '[/left]')">Left</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[center]', '[/center]')">Center</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[right]', '[/right]')">Right</button>

          <!-- Color & Size -->
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[color=red]', '[/color]')">Red</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[size=18]', '[/size]')">Size</button>

          <!-- Links & Media -->
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[url]', '[/url]')">URL</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[img]', '[/img]')">IMG</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[video]', '[/video]')">Video</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[youtube]', '[/youtube]')">YouTube</button>

          <!-- Quote & Code -->
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[quote]', '[/quote]')">Quote</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[code]', '[/code]')">Code</button>

          <!-- Lists -->
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[list]\n[*]', '\n[/list]')">List</button>
          <button class="btn btn-sm btn-light" onclick="wrapNewsBBCode('[list=1]\n[*]', '\n[/list]')">#List</button>
        </div>

        <textarea id="editBody" class="form-control mb-3" name="body" rows="6" placeholder="News Body"></textarea>

        <h6>Live Preview</h6>
        <div id="bbcodeNewsPreview" class="border p-2 bg-light rounded" style="min-height: 100px;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitNewsEdit()">Save Changes</button>
      </div>
    </div>
  </div>
</div>




<script>
// Character counter for textarea
const newsTextarea = document.getElementById('newsMessage');
const charCount = document.getElementById('charCount4');

if (newsTextarea && charCount) {
    newsTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = `${length} / 500`;
        
        if (length > 500) {
            charCount.classList.add('text-danger');
        } else {
            charCount.classList.remove('text-danger');
        }
    });
}

// Add news form handler - ЭТОТ КОД НУЖЕН ДЛЯ ДОБАВЛЕНИЯ НОВОСТЕЙ
document.getElementById('newsAddForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'add');

    fetch('news_ajax.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(res => {
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to add news');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred. Please try again.');
    });
});

// Delete news handler - ЭТОТ ТОЖЕ НУЖЕН
document.querySelectorAll('.news-delete').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const card = this.closest('.card');
        const id = card?.dataset.newsid;
        
        if (!id) {
            alert('Invalid news item');
            return;
        }
        
        if (confirm('Are you sure you want to delete this news item?')) {
            fetch('news_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&newsid=' + encodeURIComponent(id)
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    card.remove();
                } else {
                    alert(data.error || 'Failed to delete news');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred. Please try again.');
            });
        }
    });
});

// BBCode formatting function for news editor
function wrapNewsBBCode(openTag, closeTag) {
    const textarea = document.getElementById('editBody');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const newText = openTag + selectedText + closeTag;
    
    textarea.value = textarea.value.substring(0, start) + newText + textarea.value.substring(end);
    textarea.focus();
    textarea.setSelectionRange(start + openTag.length, start + openTag.length + selectedText.length);
    
    // Update preview
    updateNewsPreview();
}

// Update preview function
function updateNewsPreview() {
    const textarea = document.getElementById('editBody');
    const preview = document.getElementById('bbcodeNewsPreview');
    
    if (textarea && preview) {
        if (textarea.value.trim() === '') {
            preview.innerHTML = '<small class="text-muted">Preview will appear here...</small>';
        } else {
            // Simple BBCode to HTML conversion for preview
            let previewText = textarea.value
                .replace(/\[b\](.*?)\[\/b\]/g, '<strong>$1</strong>')
                .replace(/\[i\](.*?)\[\/i\]/g, '<em>$1</em>')
                .replace(/\[u\](.*?)\[\/u\]/g, '<u>$1</u>')
                .replace(/\[s\](.*?)\[\/s\]/g, '<s>$1</s>')
                .replace(/\[left\](.*?)\[\/left\]/g, '<div style="text-align: left">$1</div>')
                .replace(/\[center\](.*?)\[\/center\]/g, '<div style="text-align: center">$1</div>')
                .replace(/\[right\](.*?)\[\/right\]/g, '<div style="text-align: right">$1</div>')
                .replace(/\[color=(.*?)\](.*?)\[\/color\]/g, '<span style="color: $1">$2</span>')
                .replace(/\[size=(\d+)\](.*?)\[\/size\]/g, '<span style="font-size: $1px">$2</span>')
                .replace(/\[url\](.*?)\[\/url\]/g, '<a href="$1" target="_blank">$1</a>')
                .replace(/\[img\](.*?)\[\/img\]/g, '<img src="$1" class="rounded" style="max-width: 400px; height: auto;" alt="Image">')
                .replace(/\[quote\](.*?)\[\/quote\]/g, '<blockquote class="blockquote">$1</blockquote>')
                .replace(/\[code\](.*?)\[\/code\]/g, '<code>$1</code>')
                .replace(/\[list\](.*?)\[\/list\]/gs, '<ul>$1</ul>')
                .replace(/\[list=1\](.*?)\[\/list\]/gs, '<ol>$1</ol>')
                .replace(/\[\*\](.*?)(?=\n|$)/g, '<li>$1</li>')
                .replace(/\n/g, '<br>');
            
            preview.innerHTML = previewText;
        }
    }
}

// Submit news edit form
function submitNewsEdit() {
    const formData = new FormData();
    formData.append('action', 'edit');
    formData.append('newsid', document.getElementById('editNewsId').value);
    formData.append('title', document.getElementById('editTitle').value);
    formData.append('body', document.getElementById('editBody').value);

    fetch('news_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Edit failed.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred. Please try again.');
    });
}

// Initialize events when modal is shown
document.getElementById('newsEditModal')?.addEventListener('shown.bs.modal', function() {
    const textarea = document.getElementById('editBody');
    if (textarea) {
        textarea.addEventListener('input', updateNewsPreview);
        textarea.addEventListener('keyup', updateNewsPreview);
        textarea.addEventListener('change', updateNewsPreview);
        // Initial update
        updateNewsPreview();
    }
});

// Update existing edit button handlers to use the new modal
document.querySelectorAll('.news-edit').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const card = this.closest('.card');
        const id = card?.dataset.newsid;
        const title = card?.querySelector('.card-title')?.textContent || '';
        const body = card?.dataset.body || '';

        if (!id) {
            alert('Invalid news item');
            return;
        }

        document.getElementById('editNewsId').value = id;
        document.getElementById('editTitle').value = title.trim();
        document.getElementById('editBody').value = body;

        const modal = new bootstrap.Modal(document.getElementById('newsEditModal'));
        modal.show();
    });
});
</script>






<?php
stdfoot();
exit();
?>