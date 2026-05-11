<?php
declare(strict_types=1);


function remove_message_quotes(string &$text, ?int $rmdepth = null): string
{
    if ($text === '') {
        return $text;
    }

    if ($rmdepth === null) {
        $rmdepth = 5; // FIX: было "5" как строка
    }
    $rmdepth = max(0, $rmdepth);

    // ── Найти все открывающие и закрывающие теги ──────────────────────────
    preg_match_all(
        '#\[quote(=(?:&quot;|\"|\')?.*?(?:&quot;|\"|\')?)?]#si',
        $text, $smatches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER
    );
    preg_match_all(
        '#\[/quote]#i',
        $text, $ematches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER
    );

    if (empty($smatches[0]) || empty($ematches[0])) {
        return $text;
    }

    // ── Оставляем только смещения ─────────────────────────────────────────
    $soffsets    = array_column($smatches[0], 1);
    $first_token = $soffsets[0] ?? 0;

    // Убираем закрывающие теги стоящие раньше первого открывающего
    $eoffsets = [];
    foreach ($ematches[0] as $match) {
        if ($match[1] > $first_token) {
            $eoffsets[] = $match[1];
        }
    }
    unset($smatches, $ematches);

    // ── Найти правильно вложенные пары ────────────────────────────────────
    $good_offsets = [];

    while (!empty($soffsets) && !empty($eoffsets)) {
        $last_offset = 0;

        foreach ($soffsets as $sk => $soffset) {
            if ($soffset < $last_offset) continue;

            foreach ($eoffsets as $ek => $eoffset) {
                if ($eoffset > $soffset) {
                    $good_offsets[$soffset] = 1;
                    $good_offsets[$eoffset] = -1;
                    $last_offset            = $eoffset;
                    unset($soffsets[$sk], $eoffsets[$ek]);
                    break;
                }
            }
        }

        // Убрать закрывающие теги раньше первого открывающего
        $first_start = reset($soffsets);
        if ($first_start !== false) {
            foreach ($eoffsets as $ek => $eoffset) {
                if ($eoffset < $first_start) {
                    unset($eoffsets[$ek]);
                } else {
                    break;
                }
            }
        }
    }

    if (empty($good_offsets)) {
        return $text;
    }

    ksort($good_offsets);

    // ── Определить регионы для удаления ──────────────────────────────────
    $depth          = 0;
    $remove_regions = [];
    $tmp_start      = 0;

    foreach ($good_offsets as $offset => $dincr) {
        if ($depth === $rmdepth && $dincr === 1) {
            $tmp_start = $offset;
        }
        $depth += $dincr;
        if ($depth === $rmdepth && $dincr === -1) {
            $remove_regions[] = [$tmp_start, $offset];
        }
    }

    if (empty($remove_regions)) {
        return $text;
    }

    // ── Собрать строку без удалённых регионов ─────────────────────────────
    $newtext   = '';
    $cpy_start = 0;

    foreach ($remove_regions as [$rStart, $rEnd]) {
        $newtext   .= substr($text, $cpy_start, $rStart - $cpy_start);
        $cpy_start  = $rEnd + 8; // 8 = strlen('[/quote]')

        // Убрать перевод строки сразу после закрывающего тега
        $next = $text[$cpy_start] ?? '';
        if ($next === "\r" || $next === "\n") {
            ++$cpy_start;
            if ($next === "\r" && ($text[$cpy_start] ?? '') === "\n") {
                ++$cpy_start;
            }
        }
    }

    if ($cpy_start < strlen($text)) {
        $newtext .= substr($text, $cpy_start);
    }

    return $newtext;
}


function parse_quoted_message(array &$quoted_post, bool $remove_quotes = true): string
{
    global $parser, $lang, $plugins;

    if (!isset($parser)) {
        require_once INC_PATH . '/class_parser.php';
        $parser = new postParser;
    }

    // Имя пользователя
    $quoted_post['username'] = isset($quoted_post['userusername'])
        ? $quoted_post['userusername']
        : ($quoted_post['username'] ?: htmlspecialchars_uni($lang->guest ?? 'Guest'));

    // Очистка сообщения
    $username = $quoted_post['username'];
    $quoted_post['message'] = preg_replace(
        [
            '#(^|\r|\n)/me ([^\r\n<]*)#i',
            '#(^|\r|\n)/slap ([^\r\n<]*)#i',
            '#\[attachment=\d+?]#i',
        ],
        [
            "\\1* {$username} \\2",
            "\\1* {$username} {slaps} \\2 {around a bit with a large trout}",
            '',
        ],
        $quoted_post['message']
    );

    $quoted_post['message'] = $parser->parse_badwords($quoted_post['message']);

    // Удалить вложенные цитаты глубже лимита
    if ($remove_quotes) {
        $max_quote_depth = 5; // FIX: было строкой "5"
        if ($max_quote_depth > 0) {
            // Минус 1 т.к. сами оборачиваем в [quote]
            $quoted_post['message'] = remove_message_quotes(
                $quoted_post['message'],
                $max_quote_depth - 1
            );
        }
    }

    $quoted_post = $plugins->run_hooks('parse_quoted_message', $quoted_post);

    // Дополнительные атрибуты (pid/dateline) только для постов форума
    $extra = empty($quoted_post['quote_is_pm'])
        ? " pid='{$quoted_post['pid']}' dateline='{$quoted_post['dateline']}'"
        : '';

    // Выбираем безопасный символ кавычки для имени
    // FIX: было str_contains но PHP 8.0+ — ок; для 7.x нужен strpos
    $quote_char = str_contains($quoted_post['username'], '"') ? "'" : '"';

    return "[quote={$quote_char}{$quoted_post['username']}{$quote_char}{$extra}]\n"
         . $quoted_post['message']
         . "\n[/quote]\n\n";
}