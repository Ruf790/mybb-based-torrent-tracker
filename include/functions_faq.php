<?php

declare(strict_types=1);

function jumpbutton(array|string $where): string
{
    // Если передали строку, оборачиваем в массив
    if (!is_array($where)) {
        $where = [$where];
    }

    $str = '<div class="hoptobuttons d-flex flex-wrap gap-2 justify-content-center">';

    foreach ($where as $value => $jump) {
        if (!empty($value) && !empty($jump)) {
            $str .= '<a href="' . htmlspecialchars($jump ?? '') . '" class="btn btn-primary">'
                 . htmlspecialchars($value) . '</a>';
        }
    }

    $str .= '</div>';

    return $str;
}
