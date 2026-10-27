<?php
declare(strict_types=1);

/* =============================================================================
   functions_compose.php — общая разметка форм написания поста
   (newreply.php, newthread.php, editpost.php) в «новом стиле».
   Классы: st-* из st-ui.css, cp-* из include/templates/default/style/compose.css.
   Подключается: require_once INC_PATH . '/functions_compose.php';

   Что ждёт scripts/post.js (не ломать):
     input[name='attachments[]'] → .parentElement.parentElement прячется,
       .parentElement×3 — контейнер, в конец которого дописываются новые вложения;
     #dropzone → .parentElement.parentElement показывается (display:block);
     #upload_bar, #attachment_{aid} (+ data-filename), #multiquote_unloaded,
     form[name=input] с полями attachmentaid / attachmentact.
   ============================================================================= */

/** Шапка страницы: иконка в мягком квадрате, заголовок, мета-строка */
function compose_head(string $icon, string $soft, string $title, string $meta = ''): string
{
    $meta = $meta !== '' ? '<div class="st-head__meta">' . $meta . '</div>' : '';

    return '<div class="st-card st-head cp-head">'
         . '<span class="st-head__icon ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div class="min-w-0"><h1 class="st-title">' . $title . '</h1>' . $meta . '</div>'
         . '</div>';
}

/** Элемент мета-строки шапки */
function compose_meta(string $icon, string $html): string
{
    return '<span><i class="fa-solid ' . $icon . '"></i>' . $html . '</span>';
}

/** Мягкая плашка-уведомление (модерация, внешние цитаты) */
function compose_notice(string $html, string $soft = 'warning', string $icon = 'fa-hourglass-half', string $id = ''): string
{
    $id = $id !== '' ? ' id="' . $id . '"' : '';

    return '<div class="cp-notice st-soft-' . $soft . '"' . $id . '>'
         . '<i class="fa-solid ' . $icon . '"></i><div class="min-w-0">' . $html . '</div></div>';
}

/** Поле ввода с иконкой слева (тема, причина правки) */
function compose_input(string $icon, string $input): string
{
    return '<div class="input-group st-subject cp-field">'
         . '<span class="input-group-text"><i class="fa-solid ' . $icon . '"></i></span>'
         . $input
         . '</div>';
}

/** Кнопка-переключатель панели в .st-optbar */
function compose_toggle(string $target, string $icon, string $label): string
{
    return '<a class="st-toggle" data-bs-toggle="collapse" href="#' . $target . '" role="button" aria-expanded="false" aria-controls="' . $target . '">'
         . '<i class="fa-solid ' . $icon . '"></i>' . $label . '</a>';
}

/** Раскрывающаяся панель опций (карточка .st-opt + Bootstrap collapse) */
function compose_panel(string $id, string $icon, string $soft, string $title, string $body, string $desc = ''): string
{
    $desc = $desc !== '' ? '<div class="st-opt__desc">' . $desc . '</div>' : '';

    return '<div id="' . $id . '" class="collapse st-card st-opt">'
         . '<div class="st-opt__head"><span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>' . $title . '</div>'
         . '<div class="st-opt__body">' . $desc . $body . '</div>'
         . '</div>';
}

/** Галочка / радио плиткой (.st-check). $attrs — готовая строка checked="checked" или '' */
function compose_check(string $type, string $name, string $value, string $attrs, string $label, string $id = ''): string
{
    $id = $id !== '' ? ' id="' . $id . '"' : '';

    return '<label class="st-check"><input type="' . $type . '" class="form-check-input" name="' . $name . '" value="' . $value . '"' . $id . ' ' . trim($attrs) . ' />'
         . '<span>' . $label . '</span></label>';
}

/** Способ подписки на тему — 4 радио-плитки (поля postoptions[subscriptionmethod]) */
function compose_subscription(string $subscribe, string $none, string $email, string $pm): string
{
    global $lang;
    $g = $lang->global;
    $n = 'postoptions[subscriptionmethod]';

    return '<div class="cp-grid">'
         . compose_check('radio', $n, '', $subscribe, '<i class="fa-regular fa-bell-slash me-1"></i>' . $g['no_subscribe'])
         . compose_check('radio', $n, 'none', $none, '<i class="fa-regular fa-bookmark me-1"></i>' . $g['no_subscribe_notification'])
         . compose_check('radio', $n, 'email', $email, '<i class="fa-regular fa-envelope me-1"></i>' . $g['instant_email_subscribe'])
         . compose_check('radio', $n, 'pm', $pm, '<i class="fa-regular fa-message me-1"></i>' . $g['instant_pm_subscribe'])
         . '</div>';
}

/* ── Вложения ─────────────────────────────────────────────────────────────── */

/** Кнопка «Удалить» (submit rem_{aid} — работает и без JS через detect_attachmentact()) */
function compose_att_remove(string $aid, string $label): string
{
    return '<input type="submit" class="btn btn-sm btn-outline-danger st-pill" name="rem_' . $aid . '" value="' . $label . '"'
         . ' onclick="return Post.removeAttachment(' . $aid . ');" />';
}

/** Кнопка «Вставить в сообщение» */
function compose_att_insert(string $aid, string $label): string
{
    return '<button type="button" class="btn btn-sm btn-outline-primary st-pill" onclick="cpInsertAttachment(' . $aid . ')">'
         . '<i class="fa-solid fa-arrow-turn-down me-1"></i>' . $label . '</button>';
}

/** Кнопка модератора «Одобрить / Снять одобрение» */
function compose_att_mod(string $aid, bool $approve, string $label): string
{
    $act = $approve ? 'approve' : 'unapprove';

    return '<input type="submit" class="btn btn-sm btn-outline-secondary st-pill" name="' . $act . 'attach_' . $aid . '" value="' . $label . '"'
         . " onclick=\"return Post.attachmentAction(" . $aid . ",'" . $act . "');\" />";
}

/** Строка вложения. Годится и как шаблон для AJAX ({1}=aid, {2}=иконка, {3}=имя, {4}=размер) */
function compose_attach_item(string $aid, string $icon, string $filename, string $size, string $actions, bool $unapproved = false, string $link = ''): string
{
    $name = $link !== ''
        ? '<a href="' . $link . '" target="_blank" class="cp-att__name">' . $filename . '</a>'
        : '<span class="cp-att__name">' . $filename . '</span>';

    return '<div class="cp-att' . ($unapproved ? ' cp-att--unapproved' : '') . '" id="attachment_' . $aid . '" data-filename="' . $filename . '">'
         . '<span class="cp-att__ico">' . $icon . '</span>'
         . '<div class="cp-att__text">' . $name . '<span class="cp-att__size">' . $size . '</span></div>'
         . '<div class="cp-att__act">' . $actions . '</div>'
         . '</div>';
}

/** Блок вложений целиком: квота, зона перетаскивания / поле файла, кнопки, список */
function compose_attachbox(string $quota, string $usage_link, string $file_label, string $buttons, string $items): string
{
    $upload = '';
    if ($buttons !== '') {
        // .cp-fileblock = fileInput.parentElement.parentElement (post.js прячет при наличии JS)
        // .cp-dropblock = dropzone.parentElement.parentElement (post.js показывает)
        $upload = '<div class="cp-dropblock"><div class="cp-dropwrap">'
                . '<div id="upload_bar"></div>'
                . '<div id="dropzone"><i class="fa-solid fa-cloud-arrow-up"></i><div></div></div>'
                . '</div></div>'
                . '<div class="cp-fileblock"><div class="cp-file">'
                . '<label class="form-label small" for="cp_attach_input">' . $file_label . '</label>'
                . '<input type="file" name="attachments[]" id="cp_attach_input" class="form-control" multiple="multiple" />'
                . '</div></div>'
                . '<div class="cp-attbtns">' . $buttons . '</div>';
    }

    $usage_link = $usage_link !== '' ? '<span class="cp-quota__link">' . $usage_link . '</span>' : '';

    // Новые вложения post.js дописывает в конец .cp-atts (fileInput.parentElement ×3)
    return '<div class="cp-atts">'
         . '<div class="cp-quota"><i class="fa-solid fa-hard-drive"></i><span>' . $quota . '</span>' . $usage_link . '</div>'
         . $upload
         . $items
         . '</div>';
}

/** Кнопки «Добавить / Обновить вложение» (name — как раньше, их ловит серверная часть) */
function compose_attach_buttons(string $add_label, string $update_label, bool $can_add, bool $can_update): string
{
    $html = '';
    if ($can_add) {
        $html .= '<button type="submit" class="btn btn-sm btn-primary st-pill" name="newattachment" value="' . $add_label . '" tabindex="13">'
               . '<i class="fa-solid fa-upload me-1"></i>' . $add_label . '</button>';
    }
    if ($can_update) {
        $html .= '<button type="submit" class="btn btn-sm btn-outline-primary st-pill" name="updateattachment" value="' . $update_label . '" tabindex="12">'
               . '<i class="fa-solid fa-check me-1"></i>' . $update_label . '</button>';
    }
    return $html;
}

/** Вставка [attachment=ID] в текст — одна функция на страницу вместо скрипта на каждое вложение */
function compose_insert_script(): string
{
    return '<script>function cpInsertAttachment(a){var e=document.getElementById("message");'
         . 'if(!e)return;var t="[attachment="+a+"]",s=e.selectionStart,n=e.selectionEnd;'
         . 'if(typeof s==="number"){e.value=e.value.slice(0,s)+t+e.value.slice(n);e.selectionStart=e.selectionEnd=s+t.length;}'
         . 'else{e.value+=t;}e.focus();}</script>';
}

/** Липкая панель снизу: слева второстепенные кнопки, справа главная */
function compose_submitbar(string $left, string $right): string
{
    return '<div class="st-submitbar cp-submitbar">'
         . '<div class="cp-submitbar__side">' . $left . '</div>'
         . '<div class="cp-submitbar__side">' . $right . '</div>'
         . '</div>';
}

/** Подпись над предпросмотром поста */
function compose_preview(string $postbit, string $label): string
{
    if ($postbit === '') {
        return '';
    }

    return '<div class="cp-preview">'
         . '<div class="cp-label"><i class="fa-solid fa-eye"></i>' . $label . '</div>'
         . $postbit
         . '</div>';
}
