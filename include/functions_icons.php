<?php


if (!defined('IN_TRACKER')) 
{
    die("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}

/**
 * Function get_user_icons v.0.8 - Optimized
 * Returns HTML icons for user status indicators
 */
function get_user_icons(array $arr): string
{
    global $lang;

    // Define icon templates
    $icons = [
        'donor' => '<i class="fa-solid fa-star fa-bounce" style="color: #ef2906;" alt="%s" title="%s"></i>',
        'leechwarn' => '<i class="fa-solid fa-triangle-exclamation fa-bounce" style="color: #FFD43B;" alt="LeechWarned" title="LeechWarned"></i>',
        'warned' => '<i class="fa-solid fa-triangle-exclamation fa-bounce" style="color: #ef2906;" alt="%s" title="%s"></i>',
        'disabled' => '<i class="fa-sharp fa-solid fa-user-slash fa-bounce" style="color: #ef2906;" alt="%s" title="%s"></i>',
        'comment_disabled' => '<i class="fa-solid fa-comment-slash fa-bounce" style="color: #ef2906;" alt="%s" title="%s"></i>',
        'download_disabled' => '<i class="fa-solid fa-download fa-bounce" style="color: #ef2906;" alt="%s" title="%s"></i>',
        'upload_disabled' => '<i class="fa-solid fa-eye-slash fa-bounce" style="color: #ef2906;" alt="%s" title="%s"></i>',
        'pm_disabled' => '<span class="badge bg-danger" alt="%s" title="%s">PM Disabled</span>'
    ];

    $pics = '';

    // Donor status
    if ($arr["donor"] == "yes") {
        $pics .= sprintf($icons['donor'], $lang->global['imgdonated'], $lang->global['imgdonated']);
    }

    // User enabled status
    if ($arr["enabled"] == "yes") {
        // Leech warning
        if ($arr["leechwarn"] == "yes") {
            $pics .= $icons['leechwarn'];
        }

        // Warned status
        if ($arr["warned"] == "yes") {
            $pics .= sprintf($icons['warned'], $lang->global['imgwarned'], $lang->global['imgwarned']);
        }

        // Permissions check
        $permissions = [
            'cancomment' => ['comment_disabled', $lang->global['imgcommentpos']],
            'candownload' => ['download_disabled', $lang->global['imgdownloadpos']],
            'canupload' => ['upload_disabled', $lang->global['imguploadpos']],
            'canmessage' => ['pm_disabled', $lang->global['imgsendpmpos']]
        ];

        foreach ($permissions as $key => [$iconType, $langKey]) {
            if (isset($arr[$key]) && $arr[$key] == "0") {
                $pics .= sprintf($icons[$iconType], $langKey, $langKey);
            }
        }
    } else {
        // User disabled
        $pics .= sprintf($icons['disabled'], $lang->global['disabled'], $lang->global['disabled']);
    }

    return $pics;
}
?>


