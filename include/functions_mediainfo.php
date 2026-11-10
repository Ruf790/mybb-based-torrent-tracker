<?php

declare(strict_types=1);

/* =============================================================================
   MediaInfo в описании раздачи → аккуратная карточка на details.php.
   Ищем в BBCode описания текстовый вывод MediaInfo (английский или русский),
   разбираем секции General / Video / Audio / Text и убираем исходный блок
   из описания (полный текст остаётся в карточке под кнопкой).
   ============================================================================= */

if (!defined('IN_TRACKER')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

const MI_SECTIONS = [
    'general' => 'general', 'общее' => 'general', 'общие' => 'general',
    'video'   => 'video',   'видео' => 'video',
    'audio'   => 'audio',   'аудио' => 'audio',
    'text'    => 'text',    'текст' => 'text',
    'menu'    => 'menu',    'меню'  => 'menu',
];

const MI_KEYS = [
    'format'     => ['format', 'формат'],
    'profile'    => ['format profile', 'профиль формата'],
    'commercial' => ['commercial name', 'коммерческое название'],
    'duration'   => ['duration', 'продолжительность', 'длительность'],
    'size'       => ['file size', 'размер файла'],
    'overall'    => ['overall bit rate', 'общий поток', 'общий битрейт'],
    'bitrate'    => ['bit rate', 'битрейт'],
    'width'      => ['width', 'ширина'],
    'height'     => ['height', 'высота'],
    'fps'        => ['frame rate', 'частота кадров'],
    'depth'      => ['bit depth', 'битовая глубина', 'битность'],
    'hdr'        => ['hdr format', 'формат hdr'],
    'aspect'     => ['display aspect ratio', 'соотношение сторон', 'соотношение сторон экрана'],
    'library'    => ['writing library', 'библиотека кодирования'],
    'channels'   => ['channel(s)', 'канал(ы)', 'каналы'],
    'language'   => ['language', 'язык'],
    'title'      => ['title', 'заголовок', 'название'],
    'default'    => ['default', 'по умолчанию'],
    'forced'     => ['forced', 'принудительно', 'принудительный'],
];

/** Заголовок секции: 'video', 'audio'… или null */
function mi_section_type(string $line): ?string
{
    $line = trim(preg_replace('#\[/?[a-z*]+(?:=[^\]]*)?\]#i', '', $line));
    if (!preg_match('/^([\p{L}]+)(?:\s*#\s*\d+)?\s*:?$/u', $line, $m)) {
        return null;
    }
    return MI_SECTIONS[mb_strtolower($m[1])] ?? null;
}

/** «Ключ   : Значение» → [ключ, значение] */
function mi_key_line(string $line): ?array
{
    // Ключ может содержать «:» — главы в секции Menu: «00:00:00.000   : Chapter 01»
    $line = preg_replace('#(\[/[a-z*]+\])+\s*$#i', '', $line);   // «… : Yes[/code]»
    return preg_match('/^\s*([^\[\]]{1,60}?)\s*:\s?(.*)$/u', $line, $m)
        ? [mb_strtolower(trim($m[1])), trim($m[2])]
        : null;
}

/**
 * Находит вывод MediaInfo в сыром описании.
 * Возвращает ['start' => байт, 'end' => байт, 'raw' => текст, 'sections' => [...]] или null.
 */
function mi_find(string $descr): ?array
{
    return mi_find_report($descr) ?? mi_find_summary($descr);
}

/** Полный отчёт MediaInfo (General / Video / Audio …) */
function mi_find_report(string $descr): ?array
{
    if ($descr === '' || !preg_match('/(general|общее|общие)/iu', $descr)) {
        return null;
    }

    $lines  = preg_split('/(?<=\n)/', $descr);
    $offset = 0;
    $starts = [];
    foreach ($lines as $i => $l) {
        $starts[$i] = $offset;
        $offset    += strlen($l);
    }

    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        if (mi_section_type($lines[$i]) !== 'general') {
            continue;
        }

        $sections = [];
        $cur      = null;
        $lastLine = $i;
        for ($j = $i; $j < $n; $j++) {
            $line = rtrim($lines[$j], "\r\n");
            if (trim($line) === '') {
                continue;
            }
            if (($type = mi_section_type($line)) !== null) {
                $sections[] = ['type' => $type, 'kv' => []];
                $cur        = count($sections) - 1;
                $lastLine   = $j;
                continue;
            }
            if ($cur !== null && ($kv = mi_key_line($line)) !== null) {
                $sections[$cur]['kv'][] = $kv;
                $lastLine = $j;
                continue;
            }
            break;   // строка не из MediaInfo — блок закончился
        }

        $types = array_column($sections, 'type');
        if (count($sections[0]['kv'] ?? []) >= 2 && (in_array('video', $types, true) || in_array('audio', $types, true))) {
            // «[code]General» / «… : Yes[/code]» — теги на тех же строках в блок не включаем
            $start = $starts[$i];
            if (preg_match('#^\s*(\[[a-z*]+(?:=[^\]]*)?\]\s*)+#i', $lines[$i], $lead)) {
                $start += strlen($lead[0]);
            }
            $last = rtrim($lines[$lastLine], "\r\n");
            $last = preg_replace('#(\s*\[/[a-z*]+\])+\s*$#i', '', $last);
            $end  = $starts[$lastLine] + strlen($last);
            return [
                'start'    => $start,
                'end'      => $end,
                'raw'      => substr($descr, $start, $end - $start),
                'sections' => $sections,
            ];
        }
    }

    return null;
}

/* ── Краткая сводка, как на русских трекерах ──────────────────────────────
   Видео: MPEG-H HEVC Video | 24,4 Mbps | 3840x2160 | 23,976 fps | … | SDR
   Аудио 1: 48 kHz | AC3 | Dolby Digital | 5,1 | 384 kbps | | MovieDalen
   Строки «Ключ: значение», значения через «|». Нужны «Видео» и хотя бы одно «Аудио». */

const MI_SUM_KEYS = [
    'video'     => ['видео', 'video'],
    'audio'     => ['аудио', 'audio', 'звук'],
    'subs'      => ['субтитры', 'subtitles', 'subs'],
    'container' => ['контейнер', 'container', 'формат', 'format'],
    'duration'  => ['продолжительность', 'длительность', 'duration', 'runtime'],
    'size'      => ['размер', 'size'],
    'quality'   => ['качество', 'quality', 'тип релиза', 'release type', 'release', 'источник', 'source'],
    'other'     => ['перевод', 'translation'],
];

/** «[b]Аудио 2:[/b] …» / «Аудио Rus: …» → ['audio', '…', 'Rus'] или null */
function mi_sum_line(string $line): ?array
{
    $line = trim(preg_replace('#\[/?[a-z*]+(?:=[^\]]*)?\]#i', '', $line));
    if (!preg_match('/^([\p{L} ]{3,24}?)\s*(?:#?\s*\d{1,2})?\s*:\s*(.*)$/u', $line, $m)) {
        return null;
    }
    $key = mb_strtolower(trim($m[1]));
    foreach (MI_SUM_KEYS as $type => $names) {
        if (in_array($key, $names, true)) {
            return [$type, trim($m[2]), ''];
        }
        // «Аудио Rus», «Audio ENG», «Субтитры Eng» — короткая метка языка после ключа
        foreach ($names as $name) {
            if (preg_match('/^' . preg_quote($name, '/') . '\s+(\p{L}{2,4})$/u', $key, $lm)
                && in_array($type, ['audio', 'subs', 'video'], true)) {
                $lang = (string)preg_replace('/^.*?\s+/u', '', trim($m[1]));
                return [$type, trim($m[2]), mb_convert_case($lang, MB_CASE_TITLE)];
            }
        }
    }
    return null;
}

function mi_find_summary(string $descr): ?array
{
    if ($descr === '' || !preg_match('/(видео|video)\s*:/iu', $descr)) {
        return null;
    }

    $lines  = preg_split('/(?<=\n)/', $descr);
    $starts = [];
    $off    = 0;
    foreach ($lines as $i => $l) {
        $starts[$i] = $off;
        $off       += strlen($l);
    }
    $n = count($lines);

    for ($i = 0; $i < $n; $i++) {
        $vline = mi_sum_line($lines[$i]);
        // Строка «Видео» должна быть технической: разрешение, кодек или битрейт
        if (($vline[0] ?? '') !== 'video'
            || !preg_match('/\d{3,4}\s*[x×]\s*\d{3,4}|hevc|h\.?26[45]|x26[45]|avc|av1|xvid|divx|mpeg|[\d.,]+\s*(?:[kmg]bps|[кмг]бит)/iu', $vline[1])) {
            continue;
        }
        // Расширяем блок вверх и вниз по строкам-ключам (пустые строки внутри допустимы)
        $first = $i;
        while ($first > 0 && mi_sum_line($lines[$first - 1]) !== null) {
            $first--;
        }
        $last = $i;
        for ($j = $i + 1; $j < $n; $j++) {
            if (trim($lines[$j]) === '') {
                continue;
            }
            if (mi_sum_line($lines[$j]) === null) {
                break;
            }
            $last = $j;
        }

        $rows = [];
        for ($j = $first; $j <= $last; $j++) {
            if (($r = mi_sum_line($lines[$j])) !== null) {
                $rows[] = $r;
            }
        }
        // Без аудио принимаем, только если весь текст — это сводка (шаблон из upload.php)
        $whole = trim(implode('', array_slice($lines, 0, $first))) === ''
              && trim(implode('', array_slice($lines, $last + 1))) === '';
        if (!$whole && !in_array('audio', array_column($rows, 0), true)) {
            continue;
        }

        $start = $starts[$first];
        $end   = $starts[$last] + strlen(rtrim($lines[$last], "\r\n"));
        return [
            'start' => $start,
            'end'   => $end,
            'raw'   => substr($descr, $start, $end - $start),
            'data'  => mi_sum_data($rows),
        ];
    }
    return null;
}

/** Значения через «|» → массив непустых частей */
function mi_sum_parts(string $v): array
{
    $out = [];
    foreach (preg_split('/\||,\s+(?![^()]*\))/u', $v) as $p) {
        // «pic» — остаток картинки-иконки из BBCode, «~» — приблизительный битрейт
        $p = trim((string)preg_replace('/^(?:pic|img|icon)\s+|^~\s*/iu', '', trim($p)));
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

function mi_sum_data(array $rows): array
{
    $d = ['general' => ['format' => '', 'duration' => '', 'size' => '', 'bitrate' => ''], 'video' => [], 'audio' => [], 'text' => []];

    foreach ($rows as [$type, $value, $klang]) {
        $parts = mi_sum_parts($value);

        if ($type === 'quality') {
            $d['general']['quality'] = $value;
        } elseif ($type === 'container') {
            $d['general']['format'] = $parts[0] ?? '';
        } elseif ($type === 'duration') {
            $d['general']['duration'] = $parts[0] ?? '';
        } elseif ($type === 'size') {
            $sz = $parts[0] ?? '';
            // «22gb» / «18,4 гб» → «22 GB» / «18,4 GB»
            if (preg_match('/^([\d.,]+)\s*(kb|mb|gb|tb|kib|mib|gib|tib|кб|мб|гб|тб)$/iu', $sz, $m)) {
                $u  = strtr(mb_strtolower($m[2]), ['кб' => 'kb', 'мб' => 'mb', 'гб' => 'gb', 'тб' => 'tb']);
                $sz = $m[1] . ' ' . (str_contains($u, 'i') ? ucfirst(strtoupper($u[0])) . 'iB' : strtoupper($u));
            }
            $d['general']['size'] = $sz;
        } elseif ($type === 'video') {
            $v = ['res' => '', 'size' => '', 'format' => '', 'codec' => '', 'hevc' => false, 'bitrate' => '', 'fps' => '', 'depth' => '', 'hdr' => '', 'aspect' => ''];
            $hdr = [];
            foreach ($parts as $p) {
                if (preg_match('/^(\d{3,4})\s*[x×]\s*(\d{3,4})(?:\s*\((\d+(?:[.,]\d+)?:\d+)\))?/u', $p, $m)) {
                    [$w, $h] = [(int)$m[1], (int)$m[2]];
                    if (!empty($m[3])) {
                        $v['aspect'] = $m[3];
                    }
                    $v['size'] = $w . '×' . $h;
                    $v['res']  = match (true) {
                        $w >= 3800 || $h >= 2100 => '2160p',
                        $w >= 1900 || $h >= 1000 => '1080p',
                        $w >= 1260 || $h >= 700  => '720p',
                        default                  => $h . 'p',
                    };
                } elseif (preg_match('/^[\d.,]+\s*(?:[kmg]bps|[кмг]бит)/iu', $p)) {
                    $v['bitrate'] = $p;
                } elseif (preg_match('/^([\d.,]+)\s*(?:fps|кадр)/iu', $p, $m)) {
                    $v['fps'] = $m[1];
                } elseif (preg_match('/^\d+\s*(?:bits?|бит)/iu', $p)) {
                    $v['depth'] = $p;
                } elseif (preg_match('/^\d+:\d+$|^\d[.,]\d+:1$/', $p) && $v['aspect'] === '' && !str_starts_with($p, '4:')) {
                    $v['aspect'] = $p;
                } elseif (preg_match('/^(main|high|baseline)/i', $p)) {
                    $v['format'] = trim($v['format'] . ' ' . $p);
                } elseif (preg_match('/dolby\s*vision|^dv$|hdr10\+|hdr10|^hdr$|hlg/i', $p)) {
                    $hdr[] = $p;
                } elseif ($v['codec'] === '' && preg_match('/hevc|h\.?265|x265|avc|h\.?264|x264|av1|vp9|mpeg-?[124]|xvid|divx/i', $p)) {
                    $v['codec'] = match (true) {
                        (bool)preg_match('/x265/i', $p)          => 'x265',
                        (bool)preg_match('/x264/i', $p)          => 'x264',
                        (bool)preg_match('/hevc|h\.?265/i', $p)  => 'HEVC',
                        (bool)preg_match('/avc|h\.?264/i', $p)   => 'AVC',
                        (bool)preg_match('/av1/i', $p)           => 'AV1',
                        default                                  => $p,
                    };
                    $v['hevc'] = $v['codec'] === 'HEVC' || $v['codec'] === 'x265';
                }
            }
            $v['format'] = trim($v['codec'] . ' ' . $v['format']);
            $v['hdr']    = implode(', ', $hdr);
            $d['video'][] = $v;
        } elseif ($type === 'audio') {
            $a = ['lang' => '', 'codec' => '', 'channels' => '', 'bitrate' => '', 'title' => '', 'default' => false, 'atmos' => false];
            $codec = $commercial = '';
            $rest  = [];
            foreach ($parts as $p) {
                if (preg_match('/^[\d.,]+\s*k?hz$/i', $p) || preg_match('/^[\d.,]+\s*кгц$/iu', $p)) {
                    continue;
                } elseif (preg_match('/^(\d)[.,](\d)(?:\s*ch)?$/i', $p, $m)) {
                    $a['channels'] = $m[1] . '.' . $m[2];
                } elseif (preg_match('/^(\d{1,2})\s*(?:ch|channels?|кан)/iu', $p, $m)) {
                    $a['channels'] = match ((int)$m[1]) {
                        1 => '1.0', 2 => '2.0', 3 => '2.1', 6 => '5.1', 8 => '7.1',
                        default => $m[1] . ' ch',
                    };
                } elseif (preg_match('/^[\d.,\s]+\s*(?:kbps|kb\/s|кбит)/iu', $p)) {
                    $a['bitrate'] = $p;
                } elseif ($codec === '' && preg_match('/^(e-?ac-?3|ac-?3|aac|dts(?:-hd)?(?:\s*ma)?|dts:?x|truehd|flac|opus|mp3|pcm|lpcm|ddp?)(?:\s*atmos)?$/i', $p)) {
                    $codec = $p;
                } elseif (preg_match('/^(dolby|dts)/i', $p)) {
                    $commercial = $p;
                } elseif (!preg_match('/^audio coding$/i', $p)) {
                    $rest[] = $p;
                }
            }
            $a['codec'] = trim($codec . ($commercial !== '' ? ' ' . $commercial : ''));
            $a['lang']  = $klang;
            // Название озвучки обычно последнее поле; остальное — неизвестные куски, их не показываем
            $a['title'] = (string)end($rest);
            $a['atmos'] = stripos($value, 'atmos') !== false;
            $d['audio'][] = $a;
        } elseif ($type === 'subs') {
            // «Русские (Forced, Full), English (SDH)» — запятые внутри скобок не делим
            foreach (preg_split('/,(?![^()]*\))|\|/u', $value) as $sub) {
                $sub = trim($sub);
                if ($sub === '') {
                    continue;
                }
                $d['text'][] = [
                    'lang'   => trim(preg_replace('/\s*\(.*\)$/u', '', $sub)),
                    'format' => '',
                    'title'  => preg_match('/\((.*)\)$/u', $sub, $m) ? $m[1] : '',
                    'forced' => (bool)preg_match('/forced|форс/iu', $sub),
                ];
            }
        }
    }

    return $d;
}

/** Первое значение по ключу из MI_KEYS */
function mi_get(array $section, string $key): string
{
    foreach ($section['kv'] as [$k, $v]) {
        if (in_array($k, MI_KEYS[$key], true) && $v !== '') {
            return $v;
        }
    }
    return '';
}

/** «1 920 pixels» → 1920 */
function mi_int(string $v): int
{
    return preg_match('/^[\d\s ]+/u', $v, $m) ? (int)preg_replace('/\D/', '', $m[0]) : 0;
}

function mi_yes(string $v): bool
{
    return in_array(mb_strtolower(trim($v)), ['yes', 'да'], true);
}

/** Разобранные данные для карточки и чипов */
function mi_data(array $found): array
{
    if (isset($found['data'])) {
        return $found['data'];   // краткая сводка уже разобрана
    }

    $d = ['general' => [], 'video' => [], 'audio' => [], 'text' => []];

    foreach ($found['sections'] as $s) {
        switch ($s['type']) {
            case 'general':
                $d['general'] = [
                    'format'   => mi_get($s, 'format'),
                    'duration' => mi_get($s, 'duration'),
                    'size'     => mi_get($s, 'size'),
                    'bitrate'  => mi_get($s, 'overall'),
                ];
                break;

            case 'video':
                $w = mi_int(mi_get($s, 'width'));
                $h = mi_int(mi_get($s, 'height'));
                $res = match (true) {
                    $w >= 3800 || $h >= 2100 => '2160p',
                    $w >= 1900 || $h >= 1000 => '1080p',
                    $w >= 1260 || $h >= 700  => '720p',
                    $h > 0                   => $h . 'p',
                    default                  => '',
                };
                $fmt   = mi_get($s, 'format');
                $lib   = mi_get($s, 'library');
                $codec = match (true) {
                    stripos($lib, 'x265') !== false => 'x265',
                    stripos($lib, 'x264') !== false => 'x264',
                    default => match (strtoupper($fmt)) {
                        'HEVC'          => 'HEVC',
                        'AVC'           => 'AVC',
                        'MPEG-4 VISUAL' => 'MPEG-4',
                        default         => $fmt,
                    },
                };
                $fps = mi_get($s, 'fps');
                $d['video'][] = [
                    'res'     => $res,
                    'size'    => $w && $h ? $w . '×' . $h : '',
                    'format'  => trim($fmt . (mi_get($s, 'profile') !== '' ? ' ' . mi_get($s, 'profile') : '')),
                    'codec'   => $codec,
                    'hevc'    => strtoupper($fmt) === 'HEVC',
                    'bitrate' => mi_get($s, 'bitrate'),
                    'fps'     => $fps !== '' ? preg_replace('/\s*\(.*$/u', '', $fps) : '',
                    'depth'   => mi_get($s, 'depth'),
                    'hdr'     => mi_get($s, 'hdr'),
                    'aspect'  => mi_get($s, 'aspect'),
                ];
                break;

            case 'audio':
                $ch    = mi_int(mi_get($s, 'channels'));
                $name  = mi_get($s, 'commercial') ?: mi_get($s, 'format');
                $d['audio'][] = [
                    'lang'     => mi_get($s, 'language'),
                    'codec'    => $name,
                    'channels' => match ($ch) { 0 => '', 1 => '1.0', 2 => '2.0', 6 => '5.1', 8 => '7.1', default => $ch . ' ch' },
                    'bitrate'  => mi_get($s, 'bitrate'),
                    'title'    => mi_get($s, 'title'),
                    'default'  => mi_yes(mi_get($s, 'default')),
                    'atmos'    => stripos($name . mi_get($s, 'format'), 'atmos') !== false,
                ];
                break;

            case 'text':
                $d['text'][] = [
                    'lang'   => mi_get($s, 'language'),
                    'format' => mi_get($s, 'format'),
                    'title'  => mi_get($s, 'title'),
                    'forced' => mi_yes(mi_get($s, 'forced')),
                ];
                break;
        }
    }

    return $d;
}

/** «Dolby Vision, Version 1.0, … / SMPTE ST 2086, HDR10 compatible» → «Dolby Vision, HDR10» */
function mi_hdr(string $hdr): array
{
    $out = [];
    if (stripos($hdr, 'dolby vision') !== false)                                   $out[] = 'Dolby Vision';
    if (stripos($hdr, 'hdr10+') !== false || stripos($hdr, 'st 2094') !== false)   $out[] = 'HDR10+';
    elseif (stripos($hdr, 'hdr10') !== false || stripos($hdr, 'st 2086') !== false) $out[] = 'HDR10';
    if (stripos($hdr, 'hlg') !== false)                                            $out[] = 'HLG';
    return $out ?: ($hdr !== '' ? ['HDR'] : []);
}

/** Описание без блока MediaInfo; обёртку [code]/[spoiler]/… убираем, если внутри больше ничего нет */
function mi_strip(string $descr, array $found): string
{
    $before = substr($descr, 0, $found['start']);
    $after  = substr($descr, $found['end']);

    // Снимаем обёртки по очереди: [spoiler=MediaInfo][code]…[/code][/spoiler]
    while (preg_match('#\[(code|spoiler|nfo|quote|pre|hide|mediainfo)(?:=[^\]]*)?\]\s*$#i', $before, $open)
        && preg_match('#^\s*\[/' . preg_quote($open[1], '#') . '\]#i', $after, $close)) {
        $before = substr($before, 0, -strlen($open[0]));
        $after  = substr($after, strlen($close[0]));
    }

    return rtrim($before) . "\n" . ltrim($after);
}

/** Технические чипы для шапки: 2160p, HDR, HEVC, Atmos */
function mi_chips(array $d): array
{
    $chips = [];
    $v = $d['video'][0] ?? null;
    if ($v) {
        if ($v['res'] !== '')   $chips[] = $v['res'];
        foreach (mi_hdr($v['hdr']) as $h) {
            $chips[] = $h;
        }
        if ($v['codec'] !== '') $chips[] = $v['codec'];
    }
    foreach ($d['audio'] as $a) {
        if ($a['atmos']) {
            $chips[] = 'Atmos';
            break;
        }
    }
    return array_values(array_unique($chips));
}

/** HTML карточки */
function mi_render(array $d, string $raw): string
{
    global $lang;

    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $L = static fn(string $k, string $fb): string => htmlspecialchars($lang->details[$k] ?? $fb, ENT_QUOTES, 'UTF-8');

    // «4 512 kb/s» без разрыва внутри числа
    $nb  = static fn(string $s): string => (string)preg_replace('/(?<=\d) (?=\d{3}\b)/u', "\u{00A0}", $s);
    $row = static fn(string $label, string $value, string $title = ''): string =>
        $value === '' ? '' : '<div class="dt-mi__row"' . ($title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '') . '>'
        . '<span>' . $label . '</span><b>' . htmlspecialchars($nb($value), ENT_QUOTES, 'UTF-8') . '</b></div>';

    // Общее: контейнер · длительность · размер · поток
    $meta = '';
    foreach ([
        ['fa-compact-disc',  $d['general']['quality']  ?? ''],
        ['fa-box',           $d['general']['format']   ?? ''],
        ['fa-clock',         $d['general']['duration'] ?? ''],
        ['fa-hard-drive',    $d['general']['size']     ?? ''],
        ['fa-gauge-high',    $d['general']['bitrate']  ?? ''],
    ] as [$icon, $val]) {
        if ($val !== '') {
            $meta .= '<span><i class="fa-solid ' . $icon . '"></i>' . $e($nb($val)) . '</span>';
        }
    }

    // Видео
    $video = '';
    foreach ($d['video'] as $v) {
        $video .= '<div class="dt-mi__video">'
                . ($v['res'] !== '' ? '<div class="dt-mi__res">' . $e($v['res']) . ($v['size'] !== '' ? '<small>' . $e($v['size']) . '</small>' : '') . '</div>' : '')
                . $row($L('lbl_mi_codec', 'Codec'), $v['format'] . ($v['codec'] !== '' && stripos($v['format'], $v['codec']) === false ? ' (' . $v['codec'] . ')' : ''))
                . $row($L('lbl_mi_bitrate', 'Bit rate'), $v['bitrate'])
                . $row($L('lbl_mi_fps', 'Frame rate'), $v['fps'] !== '' ? $v['fps'] . ' fps' : '')
                . $row($L('lbl_mi_depth', 'Bit depth'), $v['depth'])
                . $row('HDR', implode(', ', mi_hdr($v['hdr'])), $v['hdr'])
                . $row($L('lbl_mi_aspect', 'Aspect ratio'), $v['aspect'])
                . '</div>';
    }

    // Аудио
    $audio = '';
    foreach ($d['audio'] as $i => $a) {
        $audio .= '<li class="dt-mi__track">'
                . '<span class="dt-mi__num">' . ($i + 1) . '</span>'
                . '<div class="min-w-0">'
                .   '<div class="dt-mi__tline"><b>' . $e($a['lang'] !== '' ? $a['lang'] : ($a['title'] !== '' ? $a['title'] : '—')) . '</b>'
                .     ($a['default'] ? '<span class="st-badge st-soft-primary">' . $L('lbl_mi_default', 'Default') . '</span>' : '')
                .   '</div>'
                .   '<div class="dt-mi__tmeta">' . $e($nb(implode(' · ', array_filter([$a['codec'], $a['channels'], $a['bitrate']])))) . '</div>'
                .   ($a['title'] !== '' && $a['lang'] !== '' ? '<div class="dt-mi__ttitle">' . $e($a['title']) . '</div>' : '')
                . '</div>'
                . '</li>';
    }

    // Субтитры
    $subs = '';
    foreach ($d['text'] as $t) {
        $subs .= '<span class="dt-mi__sub" title="' . $e(implode(' · ', array_filter([$t['format'], $t['title']]))) . '">'
               . $e($t['lang'] !== '' ? $t['lang'] : ($t['title'] !== '' ? $t['title'] : $t['format']))
               . ($t['forced'] ? ' <small>' . $L('lbl_mi_forced', 'forced') . '</small>' : '')
               . '</span>';
    }

    $col = static fn(string $icon, string $soft, string $title, int $count, string $body): string =>
        '<div class="dt-mi__col">'
        . '<div class="dt-mi__head"><span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>' . $title
        . ($count > 1 ? ' <span class="st-badge st-soft-muted">' . $count . '</span>' : '') . '</div>'
        . $body
        . '</div>';

    $cols = ($video !== '' ? $col('fa-film', 'st-soft-danger', $L('lbl_mi_video', 'Video'), count($d['video']), $video) : '')
          . ($audio !== '' ? $col('fa-volume-high', 'st-soft-primary', $L('lbl_mi_audio', 'Audio'), count($d['audio']), '<ul class="dt-mi__tracks">' . $audio . '</ul>') : '')
          . ($subs  !== '' ? $col('fa-closed-captioning', 'st-soft-info', $L('lbl_mi_subs', 'Subtitles'), count($d['text']), '<div class="dt-mi__subs">' . $subs . '</div>') : '');

    return '<section class="st-card dt-mi">'
         . '<div class="st-card__head">'
         .   '<span class="st-ico st-soft-success"><i class="fa-solid fa-circle-info"></i></span>MediaInfo'
         .   '<button type="button" class="btn btn-sm btn-outline-secondary st-pill ms-auto" data-bs-toggle="collapse" data-bs-target="#dtMiRaw" aria-expanded="false" aria-controls="dtMiRaw">'
         .     '<i class="fa-solid fa-code me-1"></i>' . $L('btn_mi_raw', 'Full report') . '</button>'
         . '</div>'
         . '<div class="st-card__body">'
         .   ($meta !== '' ? '<div class="dt-mi__meta">' . $meta . '</div>' : '')
         .   '<div class="dt-mi__grid">' . $cols . '</div>'
         .   '<div class="collapse" id="dtMiRaw"><pre class="dt-mi__raw">' . $e(trim($raw)) . '</pre></div>'
         . '</div>'
         . '</section>';
}

/** Текст, который не удалось разобрать: показываем как есть */
function mi_render_raw(string $raw): string
{
    return '<section class="st-card dt-mi">'
         . '<div class="st-card__head"><span class="st-ico st-soft-success"><i class="fa-solid fa-circle-info"></i></span>MediaInfo</div>'
         . '<div class="st-card__body"><pre class="dt-mi__raw mt-0">' . htmlspecialchars(trim($raw), ENT_QUOTES, 'UTF-8') . '</pre></div>'
         . '</section>';
}
