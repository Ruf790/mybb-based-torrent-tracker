<?php
declare(strict_types=1);

if (!defined('IN_TRACKER')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$lang->load('timezone');
$lang->load('usercp');

// ---------------------------------------------------------------------------
// Данные часовых поясов
// ---------------------------------------------------------------------------

/**
 * Возвращает массив смещений UTC → языковая строка (ключ в $lang->usercp).
 *
 * @return array<string, string>
 */
function get_supported_timezones(): array
{
    global $lang;

    return [
        '-12'   => $lang->usercp['timezone_gmt_minus_1200'],
        '-11'   => $lang->usercp['timezone_gmt_minus_1100'],
        '-10'   => $lang->usercp['timezone_gmt_minus_1000'],
        '-9.5'  => $lang->usercp['timezone_gmt_minus_950'],
        '-9'    => $lang->usercp['timezone_gmt_minus_900'],
        '-8'    => $lang->usercp['timezone_gmt_minus_800'],
        '-7'    => $lang->usercp['timezone_gmt_minus_700'],
        '-6'    => $lang->usercp['timezone_gmt_minus_600'],
        '-5'    => $lang->usercp['timezone_gmt_minus_500'],
        '-4.5'  => $lang->usercp['timezone_gmt_minus_450'],
        '-4'    => $lang->usercp['timezone_gmt_minus_400'],
        '-3.5'  => $lang->usercp['timezone_gmt_minus_350'],
        '-3'    => $lang->usercp['timezone_gmt_minus_300'],
        '-2'    => $lang->usercp['timezone_gmt_minus_200'],
        '-1'    => $lang->usercp['timezone_gmt_minus_100'],
        '0'     => $lang->usercp['timezone_gmt'],
        '1'     => $lang->usercp['timezone_gmt_100'],
        '2'     => $lang->usercp['timezone_gmt_200'],
        '3'     => $lang->usercp['timezone_gmt_300'],
        '3.5'   => $lang->usercp['timezone_gmt_350'],
        '4'     => $lang->usercp['timezone_gmt_400'],
        '4.5'   => $lang->usercp['timezone_gmt_450'],
        '5'     => $lang->usercp['timezone_gmt_500'],
        '5.5'   => $lang->usercp['timezone_gmt_550'],
        '5.75'  => $lang->usercp['timezone_gmt_575'],
        '6'     => $lang->usercp['timezone_gmt_600'],
        '6.5'   => $lang->usercp['timezone_gmt_650'],
        '7'     => $lang->usercp['timezone_gmt_700'],
        '8'     => $lang->usercp['timezone_gmt_800'],
        '8.5'   => $lang->usercp['timezone_gmt_850'],
        '8.75'  => $lang->usercp['timezone_gmt_875'],
        '9'     => $lang->usercp['timezone_gmt_900'],
        '9.5'   => $lang->usercp['timezone_gmt_950'],
        '10'    => $lang->usercp['timezone_gmt_1000'],
        '10.5'  => $lang->usercp['timezone_gmt_1050'],
        '11'    => $lang->usercp['timezone_gmt_1100'],
        '11.5'  => $lang->usercp['timezone_gmt_1150'],
        '12'    => $lang->usercp['timezone_gmt_1200'],
        '12.75' => $lang->usercp['timezone_gmt_1275'],
        '13'    => $lang->usercp['timezone_gmt_1300'],
        '14'    => $lang->usercp['timezone_gmt_1400'],
    ];
}

/**
 * Возвращает все часовые пояса или один по смещению.
 * Используется в старом трекер-коде — оставлена для совместимости.
 *
 * @return array<string, string>|string
 */
function fetch_timezone(string $offset = 'all'): array|string
{
    $timezones = [
        '-12'  => 'timezone_gmt_minus_1200',
        '-11'  => 'timezone_gmt_minus_1100',
        '-10'  => 'timezone_gmt_minus_1000',
        '-9'   => 'timezone_gmt_minus_0900',
        '-8'   => 'timezone_gmt_minus_0800',
        '-7'   => 'timezone_gmt_minus_0700',
        '-6'   => 'timezone_gmt_minus_0600',
        '-5'   => 'timezone_gmt_minus_0500',
        '-4.5' => 'timezone_gmt_minus_0430',
        '-4'   => 'timezone_gmt_minus_0400',
        '-3.5' => 'timezone_gmt_minus_0330',
        '-3'   => 'timezone_gmt_minus_0300',
        '-2'   => 'timezone_gmt_minus_0200',
        '-1'   => 'timezone_gmt_minus_0100',
        '0'    => 'timezone_gmt_plus_0000',
        '1'    => 'timezone_gmt_plus_0100',
        '2'    => 'timezone_gmt_plus_0200',
        '3'    => 'timezone_gmt_plus_0300',
        '3.5'  => 'timezone_gmt_plus_0330',
        '4'    => 'timezone_gmt_plus_0400',
        '4.5'  => 'timezone_gmt_plus_0430',
        '5'    => 'timezone_gmt_plus_0500',
        '5.5'  => 'timezone_gmt_plus_0530',
        '5.75' => 'timezone_gmt_plus_0545',
        '6'    => 'timezone_gmt_plus_0600',
        '6.5'  => 'timezone_gmt_plus_0630',
        '7'    => 'timezone_gmt_plus_0700',
        '8'    => 'timezone_gmt_plus_0800',
        '9'    => 'timezone_gmt_plus_0900',
        '9.5'  => 'timezone_gmt_plus_0930',
        '10'   => 'timezone_gmt_plus_1000',
        '11'   => 'timezone_gmt_plus_1100',
        '12'   => 'timezone_gmt_plus_1200',
    ];

    return $offset === 'all' ? $timezones : ($timezones[$offset] ?? '');
}


// ---------------------------------------------------------------------------
// Построение <select>
// ---------------------------------------------------------------------------

/**
 * Строит HTML-select для выбора часового пояса.
 *
 * @param string     $name     Атрибут name для <select>.
 * @param int|string $selected Текущее выбранное смещение.
 * @param bool       $short    Показывать короткие метки (UTC+X HH:MM) вместо полных названий.
 */
function build_timezone_select(string $name, int|string $selected = 0, bool $short = false): string
{
    global $lang, $timeformat;

    $timezones = get_supported_timezones();
    $selected  = str_replace('+', '', (string)$selected);

    $timezone_option = '';
    foreach ($timezones as $timezone => $label) {
        $selected_add = ((string)$selected === (string)$timezone) ? ' selected="selected"' : '';

        if ($short) {
            $label = _build_short_timezone_label((string)$timezone, $timeformat);
        }

        $timezone_option .= '<option value="'.$timezone.'"'.$selected_add.'>'.$label.'</option>';
    }

    $select = '<div class="mb-3 pb-4 border-bottom">

<select name="'.$name.'" id="'.$name.'" class="form-select form-select-sm border w-100 pe-5">
'.$timezone_option.'
</select>
	<span class="text-desc small mt-1">'.$lang->usercp['time_offset_desc'].'</span>
</div>';

    return $select;
}



// ---------------------------------------------------------------------------
// Внутренние хелперы
// ---------------------------------------------------------------------------

/**
 * Строит короткую метку для часового пояса вида «UTC+3:00 14:25».
 */
function _build_short_timezone_label(string $timezone, string $timeformat): string
{
    global $lang;

    $label = '';
    if ($timezone !== '0') {
        $label = $timezone;
        if ((float)$timezone > 0) {
            $label = '+' . $label;
        }
        if (str_contains($timezone, '.')) {
            $label = str_replace(['.', ':5', ':75'], [':', ':30', ':45'], $label);
        } else {
            $label .= ':00';
        }
    }

    $time_in_zone = my_datee($timeformat, TIMENOW, $timezone);
    return sprintf($lang->usercp['timezone_gmt_short'], $label . ' ', $time_in_zone);
}

/**
 * Возвращает три строки selected="" для опций DST (off/on/auto).
 *
 * @return array{string, string, string}  [dst0, dst1, dst2]
 */
function _build_dst_selected(int $autodst, int $dst): array
{
    $sel = ' selected="selected"';

    if ($autodst) {
        return ['', '', $sel];
    }

    return $dst ? ['', $sel, ''] : [$sel, '', ''];
}