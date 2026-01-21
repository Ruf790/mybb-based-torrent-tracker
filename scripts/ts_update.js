var dimagedir = "https://artcore-gangsta.eu/pic";
var l_pleasewait = "Please wait";
var l_ajaxerror = "Error occurred while updating";
var l_updateerror = "Update failed: ";
var l_ajaxerror2 = "AJAX request failed";

var http_request = false;

function UpdateExternalTorrent(url, parameters, tid) {
    var torrentid = tid;
    var oldDiv3 = document.getElementById('isexternal_' + torrentid);
    if (oldDiv3) {
        oldDiv3.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" style="color: #0b59e0;"></i>&nbsp;' + l_pleasewait;
    }

    http_request = false;

    if (window.XMLHttpRequest) {
        http_request = new XMLHttpRequest();
        if (http_request.overrideMimeType) {
            http_request.overrideMimeType('text/html');
        }
    } else if (window.ActiveXObject) {
        try {
            http_request = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            try {
                http_request = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {}
        }
    }

    if (!http_request) {
        show_error_message(l_ajaxerror2);
        return false;
    }

    http_request.onreadystatechange = function() {
        tsUpdate.call(this, torrentid);
    };
    http_request.open('POST', url, true);
    http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http_request.send(parameters);
}

function tsUpdate() {
    if (http_request.readyState == 4) {
        if (http_request.status == 200) {
            var result = http_request.responseText;
            changeText(result);
        } else {
            show_error_message(l_ajaxerror);
        }
    }
}

function changeText(ajaxResult) {
    var errorMatch = ajaxResult.match(/<error>(.*)<\/error>/);
    if (errorMatch) {
        var message = errorMatch[1] || l_ajaxerror;
        show_error_message(l_updateerror + message);
    } else {
        var update = ajaxResult.split('|');
        if (update.length >= 3) {
            var torrentid = update[2];
            
            // Update seeders
            var seedersDiv = document.getElementById('seeders_' + torrentid);
            if (seedersDiv) {
                seedersDiv.innerHTML = update[0];
            }
            
            // Update leechers
            var leechersDiv = document.getElementById('leechers_' + torrentid);
            if (leechersDiv) {
                leechersDiv.innerHTML = update[1];
            }
            
            // Update external status
            var externalDiv = document.getElementById('isexternal_' + torrentid);
            if (externalDiv) {
                externalDiv.innerHTML = '<i class="fa-solid fa-square-check" style="color: #0b59e0;"></i>';
            }
        }
    }
}

function show_error_message(message) {
    // Note: 'torrentid' variable needs to be accessible here
    // You might need to make it a global variable or pass it as parameter
    alert(message);
}