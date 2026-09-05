// dst.js
function detectDSTChange(timezone_with_dst) {
    var date = new Date();
    var local_offset = date.getTimezoneOffset() / 60;

    if (Math.abs(parseInt(timezone_with_dst) + local_offset) === 1) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'misc.php?action=dstswitch&ajax=1', true);

        xhr.onerror = function() {
            // fallback для старых браузеров
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
        };

        xhr.send();
    }
}

// Авто-вызов после полной загрузки страницы
window.addEventListener('load', function() {
    if (typeof USER_TIMEZONE !== 'undefined') {
        detectDSTChange(USER_TIMEZONE);
    }
});
