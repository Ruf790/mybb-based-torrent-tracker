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
        
        if(document.querySelector('.author_avatar')) {
            document.querySelectorAll(".author_avatar img").forEach(function(img) {
                function onAvatarError() {
                    img.removeEventListener('error', onAvatarError);            
                    if (window.AVATAR_NOT_FOUND_URL) {
                        img.src = window.AVATAR_NOT_FOUND_URL;
                    }

                    img.classList.remove('rounded');
                    img.classList.add('avatar-ring');
                }
                img.addEventListener('error', onAvatarError);
            });
        }
    }
};
// Lang this!
var lang = {};
MyBB.init();