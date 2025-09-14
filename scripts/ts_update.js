
var dimagedir = "https://artcore-gangsta.eu/pic"; // Example image directory
        var l_pleasewait = "Please wait";
        var l_ajaxerror = "Error occurred while updating";
        var l_updateerror = "Update failed: ";
        var l_ajaxerror2 = "AJAX request failed";
		
		
var http_request = false;

function UpdateExternalTorrent(url, parameters, tid) {
    var torrentid = tid;
    var oldDiv3 = $('#isexternal_' + torrentid);
    var newDiv3 = $('<' + oldDiv3.prop('tagName') + '></' + oldDiv3.prop('tagName') + '>');
    newDiv3.attr('id', oldDiv3.attr('id'));
    newDiv3.attr('class', oldDiv3.attr('class'));
    newDiv3.html('<i class="fa-solid fa-circle-notch fa-spin" style="color: #0b59e0;"></i>&nbsp;' + l_pleasewait);
    oldDiv3.replaceWith(newDiv3);

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

    http_request.onreadystatechange = tsUpdate;
    http_request.open('POST', url, true);
    http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http_request.setRequestHeader("Content-length", parameters.length);
    http_request.setRequestHeader("Connection", "close");
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
        var oldDiv1 = $('#seeders_' + update[2]);
        var newDiv1 = $('<' + oldDiv1.prop('tagName') + '></' + oldDiv1.prop('tagName') + '>');
        newDiv1.attr('id', oldDiv1.attr('id'));
        newDiv1.attr('class', oldDiv1.attr('class'));
        newDiv1.html(update[0]);
        oldDiv1.replaceWith(newDiv1);

        var oldDiv2 = $('#leechers_' + update[2]);
        var newDiv2 = $('<' + oldDiv2.prop('tagName') + '></' + oldDiv2.prop('tagName') + '>');
        newDiv2.attr('id', oldDiv2.attr('id'));
        newDiv2.attr('class', oldDiv2.attr('class'));
        newDiv2.html(update[1]);
        oldDiv2.replaceWith(newDiv2);

        var oldDiv3 = $('#isexternal_' + update[2]);
        var newDiv3 = $('<' + oldDiv3.prop('tagName') + '></' + oldDiv3.prop('tagName') + '>');
        newDiv3.attr('id', oldDiv3.attr('id'));
        newDiv3.attr('class', oldDiv3.attr('class'));
        newDiv3.html('<i class="fa-solid fa-square-check" style="color: #0b59e0;"></i>');
        oldDiv3.replaceWith(newDiv3);
    }
}

function show_error_message(message) {
    var oldDiv4 = $('#isexternal_' + torrentid);
    var newDiv4 = $('<' + oldDiv4.prop('tagName') + '></' + oldDiv4.prop('tagName') + '>');
    newDiv4.attr('id', oldDiv4.attr('id'));
    newDiv4.attr('class', oldDiv4.attr('class'));
    newDiv4.html('');
    oldDiv4.replaceWith(newDiv4);
    alert(message);
}
