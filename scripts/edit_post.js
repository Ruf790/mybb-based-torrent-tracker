// BBCode functions
function wrapBBCode(startTag, endTag, pid) {
    const ta = document.getElementById("editPostTextarea" + pid);
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const selected = ta.value.substring(start, end);
    ta.value = ta.value.substring(0, start) + startTag + selected + endTag + ta.value.substring(end);
    ta.focus();
    ta.setSelectionRange(start + startTag.length, end + startTag.length + selected.length);
    renderPreview(pid);
}

// Live Preview
function renderPreview(pid) {
    const ta = document.getElementById("editPostTextarea" + pid);
    const preview = document.getElementById("editPostPreview" + pid);
    let content = ta.value;
    
    // Simple BBCode to HTML conversion for preview
    content = content
        .replace(/\[b\](.*?)\[\/b\]/gi, "<b>$1</b>")
        .replace(/\[i\](.*?)\[\/i\]/gi, "<i>$1</i>")
        .replace(/\[u\](.*?)\[\/u\]/gi, "<u>$1</u>")
        .replace(/\[s\](.*?)\[\/s\]/gi, "<s>$1</s>")
        .replace(/\[left\](.*?)\[\/left\]/gi, '<div style="text-align: left;">$1</div>')
        .replace(/\[center\](.*?)\[\/center\]/gi, '<div style="text-align: center;">$1</div>')
        .replace(/\[right\](.*?)\[\/right\]/gi, '<div style="text-align: right;">$1</div>')
        .replace(/\[color=(.*?)\](.*?)\[\/color\]/gi, '<span style="color: $1;">$2</span>')
        .replace(/\[size=(.*?)\](.*?)\[\/size\]/gi, '<span style="font-size: $1px;">$2</span>')
        .replace(/\[url\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank">$1</a>')
        .replace(/\[url=(.*?)\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank">$2</a>')
        .replace(/\[img\](.*?)\[\/img\]/gi, '<img src="$1" alt="Image" class="rounded" style="max-width: 400px;">')
        .replace(/\[quote\](.*?)\[\/quote\]/gi, '<blockquote>$1</blockquote>')
        .replace(/\[code\](.*?)\[\/code\]/gi, '<pre>$1</pre>')
        .replace(/\[list\](.*?)\[\/list\]/gi, '<ul>$1</ul>')
        .replace(/\[list=1\](.*?)\[\/list\]/gi, '<ol>$1</ol>')
        .replace(/\[\*\](.*?)(?=\[\*\]|\[\/list\])/gi, '<li>$1</li>')
        .replace(/\n/g, "<br>");
    
    preview.innerHTML = content;
}

// Save function
function savePost(pid) {
    const message = document.getElementById("editPostTextarea" + pid).value;
    const editReason = document.getElementById("editReasonInput" + pid).value;
    
    if (!message.trim()) {
        showToast("Message cannot be empty", "warning");
        return;
    }
    
    const saveBtn = document.getElementById("savePostBtn" + pid);
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = "Saving...";
    saveBtn.disabled = true;
    
    const xhr = new XMLHttpRequest();
    const url = "xmlhttp.php?action=edit_post&do=update_post&pid=" + pid + "&my_post_key=" + my_post_key;
    
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    
    xhr.onload = function() {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        
        if (xhr.status === 200) {
            try {
                const json = JSON.parse(xhr.responseText);
                
                if (json.errors) {
                    showToast("Error: " + json.errors, "error");
                    return;
                }
                
                // Update post content
                const postElement = document.getElementById("pid_" + pid);
                if (postElement && json.message) {
                    postElement.innerHTML = json.message;
                }
                
                // Update edit message
                if (json.editedmsg) {
                    const editedElement = document.getElementById("edited_by_" + pid);
                    if (editedElement) {
                        editedElement.innerHTML = json.editedmsg;
                    }
                }
                
                // Close modal
                const modalElement = document.getElementById("editPostModal" + pid);
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                }
                
                showToast("Post updated successfully!", "success");
                
            } catch (e) {
                showToast("Error processing server response", "error");
            }
        } else {
            showToast("Server error: " + xhr.status, "error");
        }
    };
    
    xhr.onerror = function() {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        showToast("Network error while saving", "error");
    };
    
    // Include edit reason in the data
    const data = "value=" + encodeURIComponent(message) + "&editreason=" + encodeURIComponent(editReason);
    xhr.send(data);
}


// Initialize edit post functionality
function initEditPost(pid) {
    // Set up save button
    const saveBtn = document.getElementById("savePostBtn" + pid);
    if (saveBtn) {
        saveBtn.addEventListener("click", function() {
            savePost(pid);
        });
    }
    
    // Initialize preview
    const textarea = document.getElementById("editPostTextarea" + pid);
    const preview = document.getElementById("editPostPreview" + pid);
    
    if (textarea && preview) {
        textarea.addEventListener("input", function() {
            renderPreview(pid);
        });
        
        // Initial preview
        renderPreview(pid);
    }
}

// Global initialization for all edit modals
document.addEventListener("DOMContentLoaded", function() {
    // Auto-initialize all edit post modals on the page
    const editModals = document.querySelectorAll('[id^="editPostModal"]');
    editModals.forEach(modal => {
        const pid = modal.id.replace('editPostModal', '');
        initEditPost(pid);
    });
});