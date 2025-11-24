var MyBB = {
    init: function() {
        document.addEventListener('DOMContentLoaded', function() {
            MyBB.pageLoaded();
        });
        return true;
    },

    pageLoaded: function() {
		
		// Печатаем в консоль все элементы с атрибутом name="allbox"
    console.log(document.querySelectorAll('[name="allbox"]'));
		
        // Create the Check All feature
        document.querySelectorAll('[name="allbox"]').forEach(function(allbox) {
            var checked = allbox.checked;
            
			
			
        var checkboxes = document.querySelectorAll('input[type="checkbox"][id^="inlinemod_"]');
			
			
			
			
			

            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    if(checked && !this.checked) {
                        checked = false;
                        allbox.dispatchEvent(new CustomEvent('change', {detail: {origin: 'item'}}));
                    }
                });
            });

            allbox.addEventListener('change', function(event) {
                checked = this.checked;
                var origin = event.detail ? event.detail.origin : undefined;

                if(typeof origin == "undefined") {
                    checkboxes.forEach(function(checkbox) {
                        if(checked != checkbox.checked) {
                            checkbox.checked = checked;
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    });
                }
            });
        });

        // Initialise "initial focus" field if we have one
        var initialfocus = document.querySelector(".initial_focus");
        if(initialfocus) {
            initialfocus.focus();
        }

        if(typeof use_xmlhttprequest != "undefined" && use_xmlhttprequest == 1) {
            var mark_read_imgs = document.querySelectorAll(".ajax_mark_read");
            mark_read_imgs.forEach(function(element) {
                if(element.classList.contains('forum_off') || 
                   element.classList.contains('forum_offclose') || 
                   element.classList.contains('forum_offlink') || 
                   element.classList.contains('subforum_minioff') || 
                   element.classList.contains('subforum_minioffclose') || 
                   element.classList.contains('subforum_miniofflink') || 
                   (element.title && element.title == lang.no_new_posts)) return;

                element.addEventListener('click', function() {
                    MyBB.markForumRead(this);
                });

                element.style.cursor = "pointer";
                if(element.title) {
                    element.title = element.title + " - ";
                }
                element.title = element.title + lang.click_mark_read;
            });
        }

        document.querySelectorAll("a.referralLink").forEach(function(link) {
            link.addEventListener('click', MyBB.showReferrals);
        });

        if(document.querySelector('.author_avatar')) {
            document.querySelectorAll(".author_avatar img").forEach(function(img) {
                img.addEventListener('error', function() {
                    this.removeEventListener('error', arguments.callee);
                    var avatar = this.closest('.author_avatar');
                    if(avatar) avatar.remove();
                });
            });
        }
    },

    markForumRead: function(element) {
        if(!element) return false;
        
        var fid = element.id.replace("mark_read_", "");
        if(!fid) return false;

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'misc.php?action=markread&fid=' + fid + '&ajax=1&my_post_key=' + my_post_key, true);
        xhr.onreadystatechange = function() {
            if(xhr.readyState === 4 && xhr.status === 200) {
                MyBB.forumMarkedRead(fid, xhr.responseText);
            }
        };
        xhr.send();
    },

    forumMarkedRead: function(fid, request) {
        if(request == 1) {
            var markreadfid = document.getElementById("mark_read_"+fid);
            if(!markreadfid) return;
            
            if(markreadfid.classList.contains('subforum_minion')) {
                markreadfid.classList.remove('subforum_minion');
                markreadfid.classList.add('subforum_minioff');
            } else {
                markreadfid.classList.remove('forum_on');
                markreadfid.classList.add('forum_off');
            }
            markreadfid.style.cursor = "default";
            markreadfid.title = lang.no_new_posts;
        }
    },

    unHTMLchars: function(text) {
        text = text.replace(/&lt;/g, "<");
        text = text.replace(/&gt;/g, ">");
        text = text.replace(/&nbsp;/g, " ");
        text = text.replace(/&quot;/g, "\"");
        text = text.replace(/&amp;/g, "&");
        return text;
    },

    HTMLchars: function(text) {
        text = text.replace(new RegExp("&(?!#[0-9]+;)", "g"), "&amp;");
        text = text.replace(/</g, "&lt;");
        text = text.replace(/>/g, "&gt;");
        text = text.replace(/"/g, "&quot;");
        return text;
    },

    changeLanguage: function() {
        var form = document.getElementById("lang_select");
        if(!form) return false;
        form.dispatchEvent(new Event('submit'));
    },

    changeTheme: function() {
        var form = document.getElementById("theme_select");
        if(!form) return false;
        form.dispatchEvent(new Event('submit'));
    },

    detectDSTChange: function(timezone_with_dst) {
        var date = new Date();
        var local_offset = date.getTimezoneOffset() / 60;
        if(Math.abs(parseInt(timezone_with_dst) + local_offset) == 1) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'misc.php?action=dstswitch&ajax=1', true);
            xhr.onerror = function() {
                if(use_xmlhttprequest != 1) {
                    var form = document.createElement("form");
                    form.method = "post";
                    form.action = "misc.php";
                    form.style.display = "none";

                    var input = document.createElement("input");
                    input.name = "action";
                    input.type = "hidden";
                    input.value = "dstswitch";
                    form.appendChild(input);

                    document.body.appendChild(form);
                    form.dispatchEvent(new Event('submit'));
                }
            };
            xhr.send();
        }
    }
};

var Cookie = {
    get: function(name) {
        name = cookiePrefix + name;
        return this.getCookie(name);
    },

    set: function(name, value, expires) {
        name = cookiePrefix + name;
        if(!expires) {
            expires = 315360000; // 10*365*24*60*60 => 10 years
        }

        var expire = new Date();
        expire.setTime(expire.getTime() + (expires * 1000));

        var cookieString = name + "=" + encodeURIComponent(value) + 
                          "; expires=" + expire.toUTCString() + 
                          "; path=" + cookiePath;
        
        if(cookieDomain) {
            cookieString += "; domain=" + cookieDomain;
        }
        
        if(cookieSecureFlag) {
            cookieString += "; secure";
        }

        document.cookie = cookieString;
        return true;
    },

    unset: function(name) {
        name = cookiePrefix + name;
        document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=" + cookiePath + 
                         (cookieDomain ? "; domain=" + cookieDomain : "");
        return true;
    },

    getCookie: function(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while(c.charAt(0) == ' ') c = c.substring(1, c.length);
            if(c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        return null;
    }
};

// Lang this!
var lang = {};

MyBB.init();