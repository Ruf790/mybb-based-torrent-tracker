var MyBB = {
    init: function() {
        document.addEventListener('DOMContentLoaded', function() {
            MyBB.pageLoaded();
        });
        return true;
    },

    pageLoaded: function() {
		


        // Initialise "initial focus" field if we have one
        var initialfocus = document.querySelector(".initial_focus");
        if(initialfocus) {
            initialfocus.focus();
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