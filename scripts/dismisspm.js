function dismissPMNotice(bburl)
{
    var pm_notice = $("#pm_notice");
    if(!pm_notice.length) {
        return false;
    }

    if(typeof use_xmlhttprequest === "undefined" || use_xmlhttprequest != 1) {
        // пусть сервер обработает и страница перезагрузится
        return true;
    }

    $.ajax({
        type: 'post',
        url: bburl + 'private.php?action=dismiss_notice',
        data: { ajax: 1, my_post_key: my_post_key },
        async: true
    });

    // триггерим CSS-анимацию: убираем .show => станет полупрозрачным и уедет вверх
    pm_notice.removeClass("show");

    // удаляем из DOM после завершения transition
    setTimeout(function() {
        pm_notice.remove();
    }, 300); // должно совпадать с длительностью transition в CSS

    return false;
}