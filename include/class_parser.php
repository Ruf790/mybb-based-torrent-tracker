<?php
declare(strict_types=1);

/*
options = array(
    allow_html
    allow_smilies
    allow_mycode
    allow_auto_url
    nl2br
    filter_badwords
    me_username
    shorten_urls
    highlight
    filter_cdata
)
*/

class postParser
{
    // ── Кеши ──────────────────────────────────────────────
    public array|false $mycode_cache   = false;
    public array|false $smilies_cache  = false;
    public array|false $badwords_cache = false;

    // ── Настройки ─────────────────────────────────────────
    public string $base_url        = '';
    public array  $highlight_cache = [];
    public array  $options         = [];
    public array  $list_elements   = [];
    public int    $list_count      = 0;
    public int    $spoiler_count   = 0;
    public array  $torrent_embed_cache = [];
    public bool   $clear_needed    = false;

    // ── Константы ─────────────────────────────────────────
    public const VALIDATION_DISABLE      = 0;
    public const VALIDATION_REPORT_ONLY  = 1;
    public const VALIDATION_REQUIRE      = 2;

    private const ALLOW_CODE_MYCODE = true;
    private const ALLOW_ME_MYCODE   = true;
    private const ALLOW_LIST_MYCODE = true;
    private const ALLOW_AUTO_URL    = true;

    public int $output_validation_policy = self::VALIDATION_REQUIRE;

    // ── Игнорируемые XML-ошибки при валидации ─────────────
    private const IGNORED_XML_ERRORS = [
        'XML_ERR_INVALID_DEC_CHARREF'    => 7,
        'XML_ERR_INVALID_CHAR'           => 9,
        'XML_ERR_UNDECLARED_ENTITY'      => 26,
        'XML_ERR_ATTRIBUTE_WITHOUT_VALUE'=> 41,
        'XML_ERR_TAG_NAME_MISMATCH'      => 76,
    ];

    // ── URL-энтити ────────────────────────────────────────
    private const URL_ENTITIES = [
        '$'    => '%24',
        '&#36;'=> '%24',
        '^'    => '%5E',
        '`'    => '%60',
        '['    => '%5B',
        ']'    => '%5D',
        '{'    => '%7B',
        '}'    => '%7D',
        '"'    => '%22',
        '<'    => '%3C',
        '>'    => '%3E',
        ' '    => '%20',
    ];

    // ── Парсинг сообщения ─────────────────────────────────

    public function parse_message(string $message, array $options = []): string
    {
        global $plugins, $BASEURL;

        $original_message   = $message;
        $this->clear_needed = false;
        $this->base_url     = rtrim($BASEURL ?? '', '/');
        $this->options      = $options;

        $message = $plugins->run_hooks('parse_message_start', $message);
        $message = str_replace("\r", '', $message);

        if (!empty($this->options['filter_badwords'])) {
            $message = $this->parse_badwords($message);
        }

        if (!empty($this->options['filter_cdata'])) {
            $message = $this->parse_cdata($message);
        }

        // Сохраняем блоки [code] и [php]
        $code_matches = [];
        if (!empty($this->options['allow_mycode']) && self::ALLOW_CODE_MYCODE) {
            $message = str_replace("<mybb-code>\n", "<mybb_code>\n", $message);

            preg_match_all("#\[(code|php)\](.*?)(\[/\\1\])+(\r\n?|\n?)#si", $message, $code_matches, PREG_SET_ORDER);
            foreach ($code_matches as $point => $part) {
                if (isset($part[3])) {
                    $part[1]                    = '[' . $part[1] . ']';
                    $code_matches[$point][2]     = substr_replace($part[0], '', strrpos($part[0], $part[3]), strlen($part[3]));
                    $code_matches[$point][2]     = substr_replace($code_matches[$point][2], '', strpos($code_matches[$point][2], $part[1]), strlen($part[1]));
                }
            }
            $message = preg_replace("#\[(code|php)\](.*?)(\[/\\1\])+(\r\n?|\n?)#si", "<mybb-code>\n", $message);
        }

        if (empty($this->options['allow_html'])) {
            $message = $this->parse_html($message);
            $message = str_replace("&lt;mybb-code&gt;\n", "<mybb-code>\n", $message);
        } else {
            $message = preg_replace('#<(/?)(base|meta|script|style|iframe|object|embed|form)([^>]*)>#i', '&lt;$1$2$3&gt;', $message);
            $message = $this->fix_javascript($message);
            $message = str_replace(['<br />' . "\n", "<br>\n"], "\n", $message);
        }

        $message = $plugins->run_hooks('parse_message_htmlsanitized', $message);

        if (!empty($this->options['me_username']) && self::ALLOW_ME_MYCODE) {
            $user    = $this->options['me_username'];
            $message = preg_replace('#(>|^|\r|\n)/me ([^\r\n<]*)#i',   "\\1<span style=\"color: red;\" class=\"mycode_me\">* {$user} \\2</span>",          $message);
            $message = preg_replace('#(>|^|\r|\n)/slap ([^\r\n<]*)#i', "\\1<span style=\"color: red;\" class=\"mycode_slap\">* {$user} {slaps} \\2 {around a bit with a large trout}</span>", $message);
        }

        $message = $plugins->run_hooks('parse_message_me_mycode', $message);

        if (!empty($this->options['allow_smilies'])) {
            $message = $this->parse_smilies($message, (int)($this->options['allow_html'] ?? 0));
        }

        if (!empty($this->options['allow_mycode'])) {
            $message = $this->parse_mycode($message);
        }

        $message = preg_replace("#\[(\/)?url{1}(.*?)\]#i", '', $message);

        if (!empty($this->options['highlight'])) {
            $message = $this->highlight_message($message, $this->options['highlight']);
        }

        $message = $plugins->run_hooks('parse_message', $message);

        if (!empty($this->options['allow_mycode']) && count($code_matches) > 0) {
            foreach ($code_matches as $text) {
                $code    = strtolower($text[1]) === 'code'
                    ? $this->mycode_parse_code($this->parse_html($text[2]))
                    : $this->mycode_parse_php($text[2]);
                $message = preg_replace("#\<mybb-code>\n?#", $code, $message, 1);
            }
        }

        if (!isset($this->options['nl2br']) || $this->options['nl2br'] != 0) {
            $message = nl2br($message);
            $message = preg_replace("#(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)\s*<br />#i", '$1', $message);
            $message = preg_replace("#(&nbsp;)+(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)#i", '$2', $message);
        }

        if ($this->clear_needed) {
            $message .= '<br class="clear" />';
        }

        $message = $plugins->run_hooks('parse_message_end', $message);

        return $this->output_allowed($original_message, $message) ? $message : '';
    }

    // ── HTML ──────────────────────────────────────────────

    public function parse_html(string $message): string
    {
        $message = preg_replace('#&(?!\#[0-9]+;)#si', '&amp;', $message);
        $message = str_replace(['<', '>'], ['&lt;', '&gt;'], $message);
        return $message;
    }

    // ── MyCode ────────────────────────────────────────────

    private function cache_mycode(): void
    {
        global $cache;

        $this->mycode_cache = [];

        $standard         = [];
        $callback         = [];
        $nestable         = [];
        $nestable_callback = [];

        // Стандартный MyCode
        $standard['b']    = ['regex' => '#\[b\](.*?)\[/b\]#si',    'replacement' => '<span style="font-weight: bold;" class="mycode_b">$1</span>'];
        $standard['u']    = ['regex' => '#\[u\](.*?)\[/u\]#si',    'replacement' => '<span style="text-decoration: underline;" class="mycode_u">$1</span>'];
        $standard['i']    = ['regex' => '#\[i\](.*?)\[/i\]#si',    'replacement' => '<span style="font-style: italic;" class="mycode_i">$1</span>'];
        $standard['s']    = ['regex' => '#\[s\](.*?)\[/s\]#si',    'replacement' => '<span style="text-decoration: line-through;" class="mycode_s">$1</span>'];
        $standard['hr']   = ['regex' => '#\[hr\]#si',              'replacement' => '<hr class="mycode_hr" />'];
        $standard['copy'] = ['regex' => '#\(c\)#i',                'replacement' => '&copy;'];
        $standard['tm']   = ['regex' => '#\(tm\)#i',               'replacement' => '&#153;'];
        $standard['reg']  = ['regex' => '#\(r\)#i',                'replacement' => '&reg;'];
        $standard['nfo']  = ['regex' => '#\[nfo\](.*?)\[/nfo\]#si', 'replacement' => '<pre class="mycode_nfo" style="background:#0d0d0d;color:#33ff66;font-family:&quot;Courier New&quot;,monospace;padding:1em;border-radius:6px;overflow-x:auto;white-space:pre;line-height:1.2;">$1</pre>'];

        // Callback MyCode
        $callback['url_simple']    = ['regex' => '#\[url\]((?!javascript)[a-z]+?://)([^\r\n"<]+?)\[/url\]#si', 'replacement' => [$this, 'mycode_parse_url_callback1']];
        $callback['url_simple2']   = ['regex' => '#\[url\]((?!javascript:)[^\r\n"<]+?)\[/url\]#i',             'replacement' => [$this, 'mycode_parse_url_callback2']];
        $callback['url_complex']   = ['regex' => '#\[url=((?!javascript)[a-z]+?://)([^\r\n"<]+?)\](.+?)\[/url\]#si', 'replacement' => [$this, 'mycode_parse_url_callback1']];
        $callback['url_complex2']  = ['regex' => '#\[url=((?!javascript:)[^\r\n"<]+?)\](.+?)\[/url\]#si',      'replacement' => [$this, 'mycode_parse_url_callback2']];
        $callback['email_simple']  = ['regex' => '#\[email\]((?:[a-zA-Z0-9-_\+\.]+?)@[a-zA-Z0-9-]+\.[a-zA-Z0-9\.-]+(?:\?.*?)?)\[/email\]#i', 'replacement' => [$this, 'mycode_parse_email_callback']];
        $callback['email_complex'] = ['regex' => '#\[email=((?:[a-zA-Z0-9-_\+\.]+?)@[a-zA-Z0-9-]+\.[a-zA-Z0-9\.-]+(?:\?.*?)?)\](.*?)\[/email\]#i', 'replacement' => [$this, 'mycode_parse_email_callback']];
        $callback['size_int']      = ['regex' => '#\[size=([0-9\+\-]+?)\](.*?)\[/size\]#si', 'replacement' => [$this, 'mycode_handle_size_callback']];
        $callback['torrent_embed'] = ['regex' => '#\[torrent=(\d+)\]#i', 'replacement' => [$this, 'mycode_parse_torrent_callback']];

        // Nestable MyCode
        $nestable['color'] = ['regex' => '#\[color=([a-zA-Z]*|\#?[\da-fA-F]{3}|\#?[\da-fA-F]{6})](.*?)\[/color\]#si', 'replacement' => '<span style="color: $1;" class="mycode_color">$2</span>'];
        $nestable['size']  = ['regex' => '#\[size=(xx-small|x-small|small|medium|large|x-large|xx-large)\](.*?)\[/size\]#si', 'replacement' => '<span style="font-size: $1;" class="mycode_size">$2</span>'];
        $nestable['align'] = ['regex' => '#\[align=(left|center|right|justify)\](.*?)\[/align\]#si', 'replacement' => '<div style="text-align: $1;" class="mycode_align">$2</div>'];
        $nestable['table'] = ['regex' => '#\[table\](.*?)\[/table\]#si', 'replacement' => '<table class="table table-bordered mycode_table">$1</table>'];
        $nestable['tr']    = ['regex' => '#\[tr\](.*?)\[/tr\]#si',       'replacement' => '<tr>$1</tr>'];
        $nestable['td']    = ['regex' => '#\[td\](.*?)\[/td\]#si',       'replacement' => '<td>$1</td>'];

        // Nestable callback MyCode
        $nestable_callback['font'] = ['regex' => '#\[font=\s*("?)([a-z0-9 ,\-_\'"]+)\1\s*\](.*?)\[/font\]#si', 'replacement' => [$this, 'mycode_parse_font_callback']];
        $nestable_callback['spoiler'] = ['regex' => '#\[spoiler\](.*?)\[/spoiler\]#si', 'replacement' => [$this, 'mycode_parse_spoiler_callback']];

       

        // Заполняем кеш
        foreach ($standard as $code) {
            $this->mycode_cache['standard']['find'][]        = $code['regex'];
            $this->mycode_cache['standard']['replacement'][] = $code['replacement'];
        }
        foreach ($nestable          as $code) $this->mycode_cache['nestable'][]          = ['find' => $code['regex'], 'replacement' => $code['replacement']];
        foreach ($callback          as $code) $this->mycode_cache['callback'][]          = ['find' => $code['regex'], 'replacement' => $code['replacement']];
        foreach ($nestable_callback as $code) $this->mycode_cache['nestable_callback'][] = ['find' => $code['regex'], 'replacement' => $code['replacement']];

        $this->mycode_cache['standard_count']          = count($standard);
        $this->mycode_cache['callback_count']          = count($callback);
        $this->mycode_cache['nestable_count']          = count($nestable);
        $this->mycode_cache['nestable_callback_count'] = count($nestable_callback);
    }

    public function parse_mycode(string $message, array $options = []): string
    {
        if (empty($this->options)) {
            $this->options = $options;
        }

        if (!is_array($this->mycode_cache)) {
            $this->cache_mycode();
        }

        // Пакетная предзагрузка всех [torrent=ID] в сообщении разом —
        // иначе N вставок дают N отдельных SQL-запросов (N+1 проблема).
        $this->cache_torrent_embeds($message);

        $message = $this->mycode_parse_quotes($message);

        // Изображения
        $imgCallbacks = [
            ['#\[img\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is',                                             'mycode_parse_img_callback1'],
            ['#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is',                'mycode_parse_img_callback2'],
            ['#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is',                          'mycode_parse_img_callback3'],
            ['#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is', 'mycode_parse_img_callback4'],
        ];

        $imgDisabledCallbacks = [
            ['#\[img\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is',                                             'mycode_parse_img_disabled_callback1'],
            ['#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is',                'mycode_parse_img_disabled_callback2'],
            ['#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is',                          'mycode_parse_img_disabled_callback3'],
            ['#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is', 'mycode_parse_img_disabled_callback4'],
        ];

        $list = !empty($this->options['allow_imgcode']) ? $imgCallbacks : $imgDisabledCallbacks;
        foreach ($list as [$pattern, $method]) {
            $message = preg_replace_callback($pattern, [$this, $method], $message);
        }

        // Видео
        $videoMethod = !empty($this->options['allow_videocode'])
            ? 'mycode_parse_video_callback'
            : 'mycode_parse_video_disabled_callback';
        $message = preg_replace_callback('#\[video(?:=(.*?))?\](.*?)\[/video\]#i',[$this, $videoMethod],$message);

        $message = str_replace('$', '&#36;', $message);

        if ($this->mycode_cache['standard_count'] > 0) {
            $message = preg_replace($this->mycode_cache['standard']['find'], $this->mycode_cache['standard']['replacement'], $message);
        }

        if ($this->mycode_cache['callback_count'] > 0) {
            foreach ($this->mycode_cache['callback'] as $replace) {
                $message = preg_replace_callback($replace['find'], $replace['replacement'], $message);
            }
        }

        if ($this->mycode_cache['nestable_count'] > 0) {
            foreach ($this->mycode_cache['nestable'] as $mycode) {
                while (preg_match($mycode['find'], $message)) {
                    $message = preg_replace($mycode['find'], $mycode['replacement'], $message);
                }
            }
        }

        if ($this->mycode_cache['nestable_callback_count'] > 0) {
            foreach ($this->mycode_cache['nestable_callback'] as $replace) {
                while (preg_match($replace['find'], $message)) {
                    $prev    = $message;
                    $message = preg_replace_callback($replace['find'], $replace['replacement'], $message);
                    if ($prev === $message) break;
                }
            }
        }

        if (self::ALLOW_LIST_MYCODE) {
            $this->list_elements = [];
            $this->list_count    = 0;
            $message = preg_replace_callback('#(\[list(=(a|A|i|I|1))?\]|\[/list\])#si', [$this, 'mycode_prepare_list'], $message);
            for ($i = $this->list_count; $i > 0; $i--) {
                $message = preg_replace_callback("#\s?\[list(=(a|A|i|I|1))?&{$i}\](.*?)(\[/list&{$i}\]|$)(\r\n?|\n?)#si", [$this, 'mycode_parse_list_callback'], $message, 1);
            }
        }

        if (self::ALLOW_AUTO_URL && (!isset($this->options['allow_auto_url']) || $this->options['allow_auto_url'] == 1)) {
            $message = $this->mycode_auto_url($message);
        }

        return $message;
    }

    // ── Смайлики ──────────────────────────────────────────

    private function cache_smilies(): void
    {
        global $cache, $BASEURL, $pic_base_url;

        $this->smilies_cache = [];
        $smilies = $cache->read('smilies');

        foreach ($smilies as $code => $file) {
            $code   = $this->parse_html($code);
           
		   
	

$tpl = '<img style="cursor: pointer;" src="' . $BASEURL . '/' . $pic_base_url . 'smilies/' . $file . '" class="smilie" alt="' . $file . '" border="0" />';
		   
		   
            $this->smilies_cache[$code] = $tpl;

            if ($file[0] === ';') {
                $this->smilies_cache += [
                    "&amp{$file}" => "&amp{$file}",
                    "&lt{$file}"  => "&lt{$file}",
                    "&gt{$file}"  => "&gt{$file}",
                ];
            }
        }
    }

    public function parse_smilies(string $message, int $allow_html = 0): string
    {
        if (!is_array($this->smilies_cache)) {
            $this->cache_smilies();
        }

        if (!count($this->smilies_cache)) {
            return $message;
        }

        preg_match_all('#\[(url(=[^\]]*)?\]|quote=([^\]]*)?\])|(http|ftp)(s|)://[^\s]*#i', $message, $bad_matches, PREG_PATTERN_ORDER);

        if (count($bad_matches[0]) > 0) {
            $message = preg_replace('#\[(url(=[^\]]*)?\]|quote=([^\]]*)?\])|(http|ftp)(s|)://[^\s]*#si', '<mybb-bad-sm>', $message);
        }

        $message = strtr($message, $this->smilies_cache);

        if (count($bad_matches[0]) > 0) {
            $parts = explode('<mybb-bad-sm>', $message);
            foreach ($bad_matches[0] as $i => $match) {
                $parts[$i] .= $match;
            }
            $message = implode('', $parts);
        }

        return $message;
    }

    // ── Плохие слова ──────────────────────────────────────

    private function cache_badwords(): void
    {
        global $cache;
        $this->badwords_cache = $cache->read('badwords') ?: [];
    }

    public function parse_badwords(string $message, array $options = []): string
    {
        if (empty($this->options)) {
            $this->options = $options;
        }

        if (!is_array($this->badwords_cache)) {
            $this->cache_badwords();
        }

        foreach ($this->badwords_cache as $badword) {
            $badword['replacement'] ??= '*****';
            if (!$badword['regex']) {
                $badword['badword'] = $this->generate_regex($badword['badword']);
            }
            $message = preg_replace('#' . $badword['badword'] . '#is', $badword['replacement'], $message);
        }

        if (!empty($this->options['strip_tags'])) {
            $message = strip_tags($message);
        }

        return $message;
    }

    public function generate_regex(string $bad_word = ''): ?string
    {
        if ($bad_word === '') return null;

        $bad_word = preg_replace(
            ['/\\\\/', '/([\[\^\$\.\|\?\(\)\{\}]{1})/', '/\*\++/', '/\++\*/', '/\*+/'],
            ['\\\\\\\\', '\\\\${1}', '*', '*', '[^\s\n]*'],
            $bad_word
        );

        $parts = explode('+', $bad_word);
        $trap  = '';
        $plus  = 0;

        foreach ($parts as $piece) {
            if ($piece) {
                $trap .= $plus ? '[^\s\n]{' . $plus . '}' . $piece : $piece;
                $plus  = 1;
            } else {
                $plus++;
            }
        }

        if ($plus > 1) {
            $trap .= '[^\s\n]{' . ($plus - 1) . '}';
        }

        return '\b' . $trap . '\b';
    }

    // ── Утилиты ───────────────────────────────────────────

    public function parse_cdata(string $message): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $message);
    }

    public function fix_javascript(string $message): string
    {
        $patterns = [
            "#(&\#(0*)106;?|&\#(0*)74;?|&\#x(0*)4a;?|&\#x(0*)6a;?|j)((&\#(0*)97;?|&\#(0*)65;?|a)(&\#(0*)118;?|&\#(0*)86;?|v)(&\#(0*)97;?|&\#(0*)65;?|a)(\s)?(&\#(0*)115;?|&\#(0*)83;?|s)(&\#(0*)99;?|&\#(0*)67;?|c)(&\#(0*)114;?|&\#(0*)82;?|r)(&\#(0*)105;?|&\#(0*)73;?|i)(&\#112;?|&\#(0*)80;?|p)(&\#(0*)116;?|&\#(0*)84;?|t)(&\#(0*)58;?|\:))#i",
            "#([\s\"']on)([a-z]+\s*=)#i",
        ];
        return preg_replace($patterns, "$1\xE2\x80\x8C$2$6", $message);
    }

    public function encode_url(string $url): string
    {
        return str_replace(array_keys(self::URL_ENTITIES), array_values(self::URL_ENTITIES), $url);
    }

    // ── Size ──────────────────────────────────────────────

    public function mycode_handle_size(int $size, string $text): string
    {
        $size = max(1, min(50, $size));
        $text = str_replace("\'", "'", $text);
        return '<span style="font-size: ' . $size . 'pt;" class="mycode_size">' . $text . '</span>';
    }

    public function mycode_handle_size_callback(array $matches): string
    {
        return $this->mycode_handle_size((int)$matches[1], $matches[2]);
    }

    // ── Цитаты ────────────────────────────────────────────

    public function mycode_parse_quotes(string $message, bool $text_only = false): string
    {
        $pattern          = '#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si';
        $pattern_callback = '#\[quote=(["\']|&quot;|)(.*?)(?:\1)(.*?)(?:["\']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si';

        if (!$text_only) {
            $replace          = '<blockquote class="mycode_quote"><cite>Quote</cite>$1</blockquote>' . "\n";
            $replace_callback = [$this, 'mycode_parse_post_quotes_callback1'];
        } else {
            $replace          = empty($this->options['signature_parse']) ? "\n{Quote}\n--\n$1\n--\n" : '$1';
            $replace_callback = [$this, 'mycode_parse_post_quotes_callback2'];
        }

        do {
            $prev    = $message;
            $message = preg_replace($pattern, $replace, $message, -1, $count) ?? $prev;
            $message = preg_replace_callback($pattern_callback, $replace_callback, $message, -1, $count_callback) ?? $prev;
            if (!$message) { $message = $prev; break; }
        } while ($count || $count_callback);

        if (!$text_only) {
            $message = preg_replace(
                ['#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#', '#(\r\n*|\n*)<\/blockquote>#'],
                ['</cite><br />', '</blockquote>'],
                $message
            );
        }

        return $message;
    }

   
   
   
public function mycode_parse_post_quotes(string $message, string $username, bool $text_only = false): string
{
    global $BASEURL;
    $linkback = $date = '';
    $message  = preg_replace('#(^<br(\s?)(\/?)>|<br(\s?)(\/?)>$)#i', '', trim($message));
    if (!$message) return '';
    $username    .= "'";
    $delete_quote = true;
    preg_match('#pid=(?:&quot;|\"|\')?([0-9]+)["\']?(?:&quot;|\"|\')?#i', $username, $match);
    if (!empty($match[1])) {
        $pid      = (int)$match[1];
        $url2     = $BASEURL . '/' . get_comment_link($pid) . "#pid{$pid}";
        $url      = defined('IN_ARCHIVE') ? $url2 : $BASEURL . '/' . get_post_link($pid) . "#pid{$pid}";
        $title    = defined('IN_ARCHIVE') ? 'Go to comment' : 'Go to post';
        $linkback = '<a href="' . $url . '" class="btn btn-sm btn-outline-light" title="' . $title . '"><i class="fa-solid fa-up-right-from-square"></i></a>';
        $username = preg_replace('#\s*pid=(?:&quot;|\"|\')?[0-9]+["\']?(?:&quot;|\"|\')?#i', '', $username);
        $delete_quote = false;
    }
    unset($match);
    preg_match('#dateline=(?:&quot;|\"|\')?([0-9]+)(?:&quot;|\"|\')?#i', $username, $match);
    if (!empty($match[1]) && (int)$match[1] < TIMENOW) {
        $postdate     = $text_only ? my_datee('normal', (int)$match[1]) : my_datee('relative', (int)$match[1]);
        $date         = " ({$postdate})";
        $username     = preg_replace('#\s*dateline=(?:&quot;|\"|\')?[0-9]+(?:&quot;|\"|\')?#i', '', $username);
        $delete_quote = false;
    }
    if ($delete_quote) {
        $username = substr($username, 0, -1);
    }
    if (!empty($this->options['allow_html'])) {
        $username = htmlspecialchars_uni($username);
    }
    if ($text_only) {
        return "\n{$username} Wrote:{$date}\n--\n{$message}\n--\n";
    }
    $span = $delete_quote ? '' : "<span>{$date}</span>";
    return '
<div class="card border-0 shadow-sm mb-3 mycode_quote">
  <div class="card-header bg-gradient bg-primary text-white d-flex justify-content-between align-items-center py-2 px-3 rounded-top">
    <div class="fw-semibold">
      <i class="fa-solid fa-quote-left me-2 opacity-75"></i>
      ' . htmlspecialchars($username) . ' <span class="opacity-75">wrote:</span> ' . $span . '
    </div>
    <div class="small">' . $linkback . '</div>
  </div>
  <div class="card-body bg-light-subtle text-body rounded-bottom">
    <div class="card-text lh-base" style="white-space: pre-wrap;">' . $message . '</div>
  </div>
</div>';
}
   
   
   
   
	
	
	

    public function mycode_parse_post_quotes_callback1(array $matches): string
    {
        return $this->mycode_parse_post_quotes($matches[4], $matches[2] . $matches[3]);
    }

    public function mycode_parse_post_quotes_callback2(array $matches): string
    {
        return $this->mycode_parse_post_quotes($matches[4], $matches[2] . $matches[3], true);
    }

    // ── Code / PHP ────────────────────────────────────────

    public function mycode_parse_code(string $code, bool $text_only = false): string
    {
        global $lang;

        if ($text_only) {
            return empty($this->options['signature_parse']) ? "\n{$lang->code}\n--\n{$code}\n--\n" : $code;
        }

        $code     = preg_replace('#^(\t*)(\n|\r|\0|\x0B| )*#', '\\1', $code);
        $code     = rtrim($code);
        $original = preg_replace('#^\t*#', '', $code);

        if (empty($original)) return '';

        $code = str_replace('$', '&#36;', $code);
        $code = preg_replace('#\$([0-9])#', '\\\$\\1', $code);
        $code = str_replace(['\\', "\t", '  '], ['&#92;', '&nbsp;&nbsp;&nbsp;&nbsp;', '&nbsp;&nbsp;'], $code);

        return '<div class="codeblock"><div class="title">Code:</div><div class="body" dir="ltr"><code>' . $code . '</code></div></div><br />';
    }

    public function mycode_parse_code_callback(array $matches): string
    {
        return $this->mycode_parse_code($matches[1], true);
    }

    public function mycode_parse_php(string $str, bool $bare_return = false, bool $text_only = false): string
    {
        global $lang;

        if ($text_only) {
            return empty($this->options['signature_parse']) ? "\n{$lang->php_code}\n--\n{$str}\n--\n" : $str;
        }

        $str      = rtrim(preg_replace('#^(\t*)(\n|\r|\0|\x0B| )*#', '\\1', $str));
        $original = preg_replace('#^\t*#', '', $str);
        if (empty($original)) return '';

        $added_open = !preg_match('#^\s*<\?#si', $str);
        $added_end  = !preg_match('#\?>\s*$#si', $str);

        if ($added_open) $str = "<?php \n" . $str;
        if ($added_end)  $str = $str . " \n?>";

        $code = @highlight_string($str, true);
        $code = preg_replace('#<code>\s*<span style="color: \#000000">\s*#i', '<code>', $code);
        $code = preg_replace('#</span>\s*</code>#', '</code>', $code);
        $code = preg_replace('#</span>(\r\n?|\n?)</code>#', '</span></code>', $code);
        $code = str_replace(['\\', '$'], ['&#092;', '&#36;'], $code);
        $code = preg_replace('#&amp;\#([0-9]+);#si', '&#$1;', $code);

        if ($added_open) {
            $code = preg_replace('#<code><span style="color: \#([A-Z0-9]{6})">&lt;\?php( |&nbsp;)(<br />?)#', '<code><span style="color: #$1">', $code);
        }
        if ($added_end) {
            $code = str_replace(['?&gt;</span></code>', '?&gt;</code>'], ['</span></code>', '</code>'], $code);
        }

        $code = preg_replace('#<span style="color: \#([A-Z0-9]{6})"></span>#', '', $code);
        $code = str_replace(['<code>', '</code>'], ['<div dir="ltr"><code>', '</code></div>'], $code);
        $code = preg_replace('# *$#', '', $code);

        if ($bare_return) return $code;

        return '<div class="codeblock phpcodeblock"><div class="title">PHP Code:</div><div class="body">' . $code . '</div></div><br />';
    }

    public function mycode_parse_php_callback(array $matches): string
    {
        return $this->mycode_parse_php($matches[1], false, true);
    }

    // ── URL ───────────────────────────────────────────────

    public function mycode_parse_url(string $url, string $name = ''): string
    {
        if (!preg_match('#^[a-z0-9]+://#i', $url)) {
            $url = 'http://' . $url;
        }

        if (!empty($this->options['allow_html'])) {
            $url = $this->parse_html($url);
        }

        if (!$name) $name = $url;

        if ($name === $url && (!isset($this->options['shorten_urls']) || !empty($this->options['shorten_urls']))) {
            $name = htmlspecialchars_decode($name);
            if (strlen($name) > 55) {
                $name = substr($name, 0, 40) . '...' . substr($name, -10);
            }
            $name = htmlspecialchars_uni($name);
        }

        $rel  = !empty($this->options['nofollow_on']) ? ' rel="noopener nofollow"' : ' rel="noopener"';
        $url  = $this->encode_url($url);
        $name = $this->parse_badwords(preg_replace('#&amp;\#([0-9]+);#si', '&#$1;', $name));

        return '<a href="' . $url . '" target="_blank"' . $rel . ' class="mycode_url">' . $name . '</a>';
    }

    public function mycode_parse_font_callback(array $matches): string
    {
        $fonts = str_replace('"', "'", $matches[2]);
        return '<span style="font-family: ' . $fonts . ';" class="mycode_font">' . $matches[3] . '</span>';
    }

    public function mycode_parse_spoiler_callback(array $matches): string
    {
        $id = 'mycode-spoiler-' . (++$this->spoiler_count);

        return '<div class="mycode_spoiler my-2">'
             . '<a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#' . $id . '" role="button" aria-expanded="false" aria-controls="' . $id . '">'
             . '<i class="fa-solid fa-eye"></i> Spoiler (click to show)'
             . '</a>'
             . '<div class="collapse mt-2 p-2 border rounded bg-light" id="' . $id . '">'
             . $matches[1]
             . '</div>'
             . '</div>';
    }

    /**
     * Публичный метод — позволяет "предзаправить" кэш списком ID снаружи,
     * до того как парсер вообще коснётся текста сообщений. Нужен там, где
     * заранее известны ВСЕ сообщения, которые будут отрендерены за один
     * проход (например, commenttable.php получает сразу все комментарии
     * массивом) — тогда можно просканировать их все и забрать данные всех
     * упомянутых торрентов одним запросом, вместо запроса на каждый
     * комментарий по отдельности.
     */
    public function primeTorrentEmbedCache(array $ids): void
    {
        $this->fetchTorrentEmbeds(array_map('intval', $ids));
    }

    private function cache_torrent_embeds(string $message): void
    {
        if (!preg_match_all('#\[torrent=(\d+)\]#i', $message, $found)) {
            return;
        }

        $this->fetchTorrentEmbeds(array_map('intval', $found[1]));
    }

    private function fetchTorrentEmbeds(array $ids): void
    {
        $ids = array_unique($ids);

        // Кэш накопительный на весь рендер страницы (один $parser на много
        // parse_mycode() вызовов — например, description + N комментариев на
        // details.php). Запрашиваем только те ID, которых ещё нет в кэше.
        $missing = array_values(array_diff($ids, array_keys($this->torrent_embed_cache)));

        if (empty($missing)) {
            return;
        }

        global $db;
        $ph    = implode(',', array_fill(0, count($missing), '?'));
        $query = $db->sql_query_prepared(
            "SELECT t.id, t.name, t.size, t.seeders, t.leechers, t.t_image, c.name AS catname
             FROM torrents t LEFT JOIN categories c ON c.id = t.category
             WHERE t.id IN ({$ph})",
            $missing
        );

        while ($query && ($row = $db->fetch_array($query))) {
            $this->torrent_embed_cache[(int)$row['id']] = $row;
        }
    }

    public function mycode_parse_torrent_callback(array $matches): string
    {
        $tid = (int)$matches[1];
        if ($tid <= 0) {
            return $matches[0];
        }

        $torrent = $this->torrent_embed_cache[$tid] ?? null;

        if (!$torrent) {
            return $matches[0];
        }

        $link = htmlspecialchars(get_torrent_link((int)$torrent['id']), ENT_QUOTES, 'UTF-8');
        $size = function_exists('mksize') ? mksize((int)$torrent['size']) : (int)$torrent['size'] . ' B';

        $poster = !empty($torrent['t_image'])
            ? '<img src="' . htmlspecialchars($torrent['t_image'], ENT_QUOTES, 'UTF-8') . '" class="card-img-top" style="height:420px;object-fit:cover;" alt="" />'
            : '';

        return '<a href="' . $link . '" class="mycode_torrent_card card d-inline-block text-decoration-none my-2" style="max-width:420px;">'
             . $poster
             . '<div class="card-body py-2 px-3">'
             . '<div class="fw-bold text-truncate"><i class="fa-solid fa-magnet me-1"></i>' . htmlspecialchars($torrent['name'], ENT_QUOTES, 'UTF-8') . '</div>'
             . '<div class="text-muted small">'
             . htmlspecialchars($torrent['catname'] ?? '', ENT_QUOTES, 'UTF-8') . ' &#183; ' . $size
             . ' &#183; <span class="text-success">' . (int)$torrent['seeders'] . ' seeders</span>'
             . ' &#183; <span class="text-danger">' . (int)$torrent['leechers'] . ' leechers</span>'
             . '</div>'
             . '</div>'
             . '</a>';
    }

    public function mycode_parse_url_callback1(array $matches): string
    {
        return $this->mycode_parse_url($matches[1] . $matches[2], $matches[3] ?? '');
    }

    public function mycode_parse_url_callback2(array $matches): string
    {
        return $this->mycode_parse_url($matches[1], $matches[2] ?? '');
    }

    // ── IMG ───────────────────────────────────────────────

    public function mycode_parse_img(string $url, array $dimensions = [], string $align = ''): string
    {
        $url = str_replace(["\n", "\r"], '', trim($url));

        if (!empty($this->options['allow_html'])) {
            $url = $this->parse_html($url);
        }

        $css_align = match($align) {
            'right' => ' style="float: right;"',
            'left'  => ' style="float: left;"',
            default => '',
        };

        if ($align) $this->clear_needed = true;

        $alt = $this->encode_url(htmlspecialchars_decode(basename($url)));
        if (strlen($alt) > 55) {
            $alt = substr($alt, 0, 40) . '...' . substr($alt, -10);
        }
        $alt = preg_replace('#&(?!\#[0-9]+;)#si', '&amp;', $alt);
        $alt = '[Image: ' . $alt . ']';

        $url = $this->encode_url($url);

        return '<img src="' . $url . '" loading="lazy" width="450" alt="' . $alt . '"' . $css_align . ' class="rounded" />';
    }

    public function mycode_parse_img_callback1(array $matches): string { return $this->mycode_parse_img($matches[2]); }
    public function mycode_parse_img_callback2(array $matches): string { return $this->mycode_parse_img($matches[4], [(int)$matches[1], (int)$matches[2]]); }
    public function mycode_parse_img_callback3(array $matches): string { return $this->mycode_parse_img($matches[3], [], $matches[1]); }
    public function mycode_parse_img_callback4(array $matches): string { return $this->mycode_parse_img($matches[5], [(int)$matches[1], (int)$matches[2]], $matches[3]); }

    public function mycode_parse_img_disabled(string $url): string
    {
        $url = str_replace(["'", "\n", "\r"], ["'", '', ''], trim($url));
        return '[Image: ' . $this->mycode_parse_url($url) . ']';
    }

    public function mycode_parse_img_disabled_callback1(array $matches): string { return $this->mycode_parse_img_disabled($matches[2]); }
    public function mycode_parse_img_disabled_callback2(array $matches): string { return $this->mycode_parse_img_disabled($matches[4]); }
    public function mycode_parse_img_disabled_callback3(array $matches): string { return $this->mycode_parse_img_disabled($matches[3]); }
    public function mycode_parse_img_disabled_callback4(array $matches): string { return $this->mycode_parse_img_disabled($matches[5]); }

    // ── Email ─────────────────────────────────────────────

    public function mycode_parse_email(string $email, string $name = ''): string
    {
        if (!$name) $name = $email;
        return '<a href="mailto:' . $this->encode_url($email) . '" class="mycode_email">' . $name . '</a>';
    }

    public function mycode_parse_email_callback(array $matches): string
    {
        return $this->mycode_parse_email($matches[1], $matches[2] ?? '');
    }

    // ── Video ─────────────────────────────────────────────

    public function mycode_parse_video(string $video, string $url): string
{
    if (empty($url)) {
        return "[video={$video}]{$url}[/video]";
    }

    $url = trim($url);

    // Проверка URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "[video={$video}]{$url}[/video]";
    }

    // 🎬 MP4 / WEBM / OGG поддержка
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

    if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
        $url = $this->encode_url($url);

        return '
        <video controls="controls" preload="metadata" class="mycode_video rounded" style="max-width:300px; width:100%; height:auto;">
            <source src="' . $url . '" type="video/' . $ext . '" />
            Your browser does not support the video tag.
        </video>';
    }

    // 👉 fallback на старую систему (YouTube и др.)
    $parsed = @parse_url(urldecode($url));
    if ($parsed === false) {
        return "[video={$video}]{$url}[/video]";
    }

    $queries = [];
    foreach (explode('&', $parsed['query'] ?? '') as $q) {
        $pair = explode('=', $q);
        if (count($pair) === 2) {
            $queries[str_replace('amp;', '', $pair[0])] = $pair[1];
        }
    }

    $fragments = !empty($parsed['fragment']) ? explode('&', $parsed['fragment']) : [];
    $path      = !empty($parsed['path']) ? explode('/', $parsed['path']) : [];

    $id = $this->resolveVideoId($video, $path, $queries, $fragments, $parsed);

    if (empty($id)) {
        return "[video={$video}]{$url}[/video]";
    }

    return '<iframe width="660" height="515" src="//www.youtube-nocookie.com/embed/' . $this->encode_url($id) . '" frameborder="0" allowfullscreen="true"></iframe>';
}

    private function resolveVideoId(string $video, array $path, array $queries, array $fragments, array $parsed): string
    {
        return match($video) {
            'dailymotion' => !empty($path[2]) ? explode('_', $path[2], 2)[0] : ($path[1] ?? ''),
            'metacafe'    => $path[2] ?? '',
            'myspacetv'   => $path[4] ?? '',
            'facebook'    => $queries['v'] ?? (isset($path[3]) && str_starts_with($path[3], 'vb.') ? ($path[4] ?? '') : ($path[3] ?? '')),
            'mixer'       => $path[1] ?? '',
            'liveleak'    => $queries['i'] ?? '',
            'yahoo'       => $path[2] ?? ($path[1] ?? ''),
            'vimeo'       => $path[3] ?? ($path[1] ?? ''),
            'youtube'     => !empty($fragments[0]) ? str_replace('!v=', '', $fragments[0]) : ($queries['v'] ?? ($path[1] ?? '')),
            'twitch'      => $this->resolveTwitchId($path),
            default       => '',
        };
    }

    private function resolveTwitchId(array $path): string
    {
        if (count($path) >= 3 && $path[1] === 'videos') return 'video=v' . $path[2];
        if (count($path) >= 4 && $path[2] === 'v')      return 'video=v' . $path[3];
        if (count($path) >= 2)                           return 'channel=' . $path[1];
        return '';
    }

    public function mycode_parse_video_callback(array $matches): string
    {
        return $this->mycode_parse_video($matches[1], $matches[2]);
    }

    public function mycode_parse_video_disabled(string $url): string
    {
        global $lang;
        $url = str_replace(["'", "\n", "\r"], ["'", '', ''], trim($url));
        return $lang->sprintf($lang->posted_video, $this->mycode_parse_url($url));
    }

    public function mycode_parse_video_disabled_callback(array $matches): string
    {
        return $this->mycode_parse_video_disabled($matches[2]);
    }

    // ── Авто-URL ──────────────────────────────────────────

    public function mycode_auto_url(string $message): string
    {
        return preg_replace_callback(
            '~
                <a\s[^>]*>.*?</a>|
                (?<=^|[\s\(\)\[\>])
                (?P<prefix>
                    (?:http|https|ftp|news|irc|ircs|irc6)://|
                    (?:www|ftp)\.
                )
                (?P<link>
                    (?:[^\/"\s\<\[\.]+\.)*[\w]+
                    (?::[0-9]+)?
                    (?:/(?:[^"\s<\[&]|\[\]|&(?:amp|lt|gt);)*)?
                    [\w\/\)]
                )
                (?![^<>]*?>)
            ~iusx',
            [$this, 'mycode_auto_url_callback'],
            $message
        );
    }

    public function mycode_auto_url_callback(array $matches = []): string
    {
        if (count($matches) === 1) return $matches[0];

        $external = '';
        while (str_ends_with($matches['link'], ')')) {
            if (substr_count($matches['link'], ')') > substr_count($matches['link'], '(')) {
                $matches['link'] = substr($matches['link'], 0, -1);
                $external        = ')' . $external;
            } else {
                break;
            }

            $last = substr($matches['link'], -1);
            while (in_array($last, ['.', ',', '?', '!'], true)) {
                $matches['link'] = substr($matches['link'], 0, -1);
                $external        = $last . $external;
                $last            = substr($matches['link'], -1);
            }
        }

        return $this->mycode_parse_url($matches['prefix'] . $matches['link'], $matches['prefix'] . $matches['link']) . $external;
    }

    // ── Списки ────────────────────────────────────────────

    public function mycode_parse_list(string $message, string $type = ''): string
    {
        if (!str_contains($message, '[*]')) {
            $message = "[*]{$message}";
        }

        $parts = preg_split('#[^\S\n\r]*\[\*\]\s*#', $message);
        if (isset($parts[0]) && trim($parts[0]) === '') array_shift($parts);
        $inner = '<li>' . implode("</li>\n<li>", $parts) . "</li>\n";

        $list = $type
            ? "\n<ol type=\"{$type}\" class=\"mycode_list\">{$inner}</ol>\n"
            : "<ul class=\"mycode_list\">{$inner}</ul>\n";

        return preg_replace("#<(ol type=\"{$type}\"|ul)>\s*</li>#", '<$1>', $list);
    }

    public function mycode_parse_list_callback(array $matches): string
    {
        return $this->mycode_parse_list($matches[3], $matches[2]);
    }

    public function mycode_prepare_list(array $matches): string
    {
        if (strcasecmp($matches[1], '[/list]') === 0) {
            $count = array_pop($this->list_elements);
            return $count !== null ? "[/list&{$count}]" : $matches[0];
        }

        $this->list_elements[] = ++$this->list_count;
        return !empty($matches[2])
            ? "[list{$matches[2]}&{$this->list_count}]"
            : "[list&{$this->list_count}]";
    }

    // ── Стриппинг смайлов ─────────────────────────────────

    public function strip_smilies(string $message): string
    {
        if (!is_array($this->smilies_cache)) {
            $this->cache_smilies();
        }
        if (is_array($this->smilies_cache)) {
            $message = str_replace($this->smilies_cache, array_keys($this->smilies_cache), $message);
        }
        return $message;
    }

    // ── Подсветка ─────────────────────────────────────────

    public function highlight_message(string $message, string $highlight): string
    {
        if (empty($this->highlight_cache)) {
            $this->highlight_cache = build_highlight_array($highlight);
        }
        if (is_array($this->highlight_cache) && !empty($this->highlight_cache)) {
            $message = preg_replace(array_keys($this->highlight_cache), $this->highlight_cache, $message);
        }
        return $message;
    }

    // ── Text-only парсинг ─────────────────────────────────

    public function text_parse_message(string $message, array $options = []): string
    {
        global $plugins;

        if (empty($this->options)) {
            $this->options = $options;
        } else {
            $this->options = array_merge($this->options, $options);
        }

        if (!empty($this->options['filter_badwords'])) {
            $message = $this->parse_badwords($message);
        }

        $message = $this->mycode_parse_quotes($message, true);
        $message = preg_replace_callback('#\[php\](.*?)\[/php\](\r\n?|\n?)#is',  [$this, 'mycode_parse_php_callback'],  $message);
        $message = preg_replace_callback('#\[code\](.*?)\[/code\](\r\n?|\n?)#is', [$this, 'mycode_parse_code_callback'], $message);

        $find    = [
            '#\[(b|u|i|s|url|email|color|img)\](.*?)\[/\1\]#is',
            '#\[(email|color|size|font|align|video)=[^\]]*\](.*?)\[/\1\]#is',
            '#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>"\']+?))\[/img\]#is',
            '#\[url=((?!javascript)[a-z]+?://)([^\r\n"<]+?)\](.+?)\[/url\]#si',
            '#\[url=((?!javascript:)[^\r\n"<&\(\)]+?)\](.+?)\[/url\]#si',
            '#\[attachment=([0-9]+?)\]#i',
        ];
        $replace = ['$2', '$2', '$4', '$3 ($1$2)', '$2 ($1)', ''];

        $prev = '';
        for ($i = 0; $i < 20 && $message !== $prev; $i++) {
            $prev    = $message;
            $message = preg_replace($find, $replace, $prev);
        }

        if (!empty($this->options['me_username'])) {
            $user    = $this->options['me_username'];
            $message = preg_replace('#(>|^|\r|\n)/me ([^\r\n<]*)#i',   "\\1* {$user} \\2",              $message);
            $message = preg_replace('#(>|^|\r|\n)/slap ([^\r\n<]*)#i', "\\1* {$user} {slaps} \\2 {with_trout}", $message);
        }

        $this->list_elements = [];
        $this->list_count    = 0;
        $message = preg_replace_callback('#(\[list(=(a|A|i|I|1))?\]|\[/list\])#si', [$this, 'mycode_prepare_list'], $message);
        for ($i = $this->list_count; $i > 0; $i--) {
            $message = preg_replace_callback("#\s?\[list(=(a|A|i|I|1))?&{$i}\](.*?)(\[/list&{$i}\]|$)(\r\n?|\n?)#si", [$this, 'mycode_parse_list_callback'], $message, 1);
        }

        return $plugins->run_hooks('text_parse_message', $message);
    }

    // ── Валидация вывода ──────────────────────────────────

    public function output_allowed(string $source, string $output): bool
    {
        if ($this->output_validation_policy === self::VALIDATION_DISABLE || !empty($this->options['allow_html'])) {
            return true;
        }

        $valid = $this->validate_output($source, $output);

        return $this->output_validation_policy === self::VALIDATION_REPORT_ONLY || $valid;
    }

    public function validate_output(string $source, string $output): bool
    {
        libxml_use_internal_errors(true);

        simplexml_load_string('<root>' . $output . '</root>', 'SimpleXMLElement', LIBXML_NOENT);

        $errors = libxml_get_errors();
        libxml_use_internal_errors(false);

        $filtered = array_diff(
            array_column($errors, 'code'),
            array_values(self::IGNORED_XML_ERRORS)
        );

        if ($errors && $filtered) {
            $msg = "Parser output validation failed\nSource: " . htmlspecialchars_uni($source)
                 . "\nOutput: " . htmlspecialchars_uni($output)
                 . "\nXML Errors: " . print_r($errors, true);

            GlobalErrorHandler(E_USER_WARNING, $msg, __FILE__, __LINE__);
            return false;
        }

        return true;
    }
}