<?php
declare(strict_types=1);

if (!defined('IN_TRACKER')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Возвращает отформатированное соотношение upload/download с цветовой индикацией.
 *
 * @param int|float $uploaded
 * @param int|float $downloaded
 * @return string  HTML-строка с цветовым индикатором
 */
function get_user_ratio(int|float $uploaded, int|float $downloaded): string
{
    // FIX: убран параметр $white — цвет текста управляется CSS/темой
    // FIX: <font> теги заменены на <span> с CSS-переменными Bootstrap

    if ($downloaded > 0) {
        $ratio = $uploaded / $downloaded;
        $ratio = number_format($ratio, 2);
        $color = get_ratio_color((float)$ratio);

        return '<span style="color:' . $color . ';font-weight:600">' . $ratio . '</span>';
    }

    if ($uploaded > 0) {
        return '<span style="color:var(--bs-success);font-weight:600">&#x221E;</span>'; // ∞
    }

    return '<span class="text-muted">—</span>';
}

/**
 * Возвращает цвет для отображения соотношения.
 * Градиент от красного (< 0.1) до зелёного (>= 1.0).
 *
 * @param float $ratio
 * @return string  CSS-цвет
 */
function get_ratio_color(float $ratio): string
{
    // Используем CSS-переменные Bootstrap где возможно,
    // для промежуточных значений — явные hex
    return match(true) {
        $ratio < 0.5 => '#dc3545',           // bs-danger
        $ratio < 0.7 => '#fd7e14',           // bs-orange
        $ratio < 0.9 => '#ffc107',           // bs-warning
        $ratio < 1.0 => '#6f9e00',           // желтовато-зелёный
        default      => 'var(--bs-success)', // >= 1.0
    };
}