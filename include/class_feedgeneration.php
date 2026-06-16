<?php
declare(strict_types=1);



class FeedGenerator
{
    public string $feed_format = 'rss2.0';
    public string $feed        = '';
    public array  $items       = [];
    public array  $channel     = [];

    public function set_feed_format(string $feed_format): void
    {
        $this->feed_format = match($feed_format) {
            'json'     => 'json',
            'atom1.0'  => 'atom1.0',
            default    => 'rss2.0',
        };
    }

    public function set_channel(array $channel): void
    {
        $this->channel = $channel;
    }

    public function add_item(array $item): void
    {
        $this->items[] = $item;
    }

    public function generate_feed(): void
    {
        global $charset;

        // ── Feed header ───────────────────────────────────────────────
        switch ($this->feed_format) {
            case 'json':
                $this->feed .= "{\n\t\"version\": " . json_encode('https://jsonfeed.org/version/1') . ",\n";
                $this->feed .= "\t\"title\": \"{$this->channel['title']}\",\n";
                $this->feed .= "\t\"home_page_url\": " . json_encode($this->channel['link']) . ",\n";
                $this->feed .= "\t\"feed_url\": " . json_encode($this->channel['link'] . 'syndication.php') . ",\n";
                $this->feed .= "\t\"description\": " . json_encode($this->channel['description']) . ",\n";
                $this->feed .= "\t\"items\": [\n";
                $serial = 0;
                break;

            case 'atom1.0':
                $this->channel['date'] = gmdate("Y-m-d\TH:i:s\Z", (int) $this->channel['date']);
                $this->feed .= "<?xml version=\"1.0\" encoding=\"{$charset}\"?>\n";
                $this->feed .= "<feed xmlns=\"http://www.w3.org/2005/Atom\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
                $this->feed .= "\t<title type=\"html\"><![CDATA[" . $this->sanitize_content($this->channel['title']) . "]]></title>\n";
                $this->feed .= "\t<subtitle type=\"html\"><![CDATA[" . $this->sanitize_content($this->channel['description']) . "]]></subtitle>\n";
                $this->feed .= "\t<link rel=\"self\" href=\"{$this->channel['link']}syndication.php\"/>\n";
                $this->feed .= "\t<id>{$this->channel['link']}</id>\n";
                $this->feed .= "\t<link rel=\"alternate\" type=\"text/html\" href=\"{$this->channel['link']}\"/>\n";
                $this->feed .= "\t<updated>{$this->channel['date']}</updated>\n";
                $this->feed .= "\t<generator uri=\"https://mybb.com\">MyBB</generator>\n";
                break;

            default:
                $this->channel['date'] = gmdate('D, d M Y H:i:s O', (int) $this->channel['date']);
                $this->feed .= "<?xml version=\"1.0\" encoding=\"{$charset}\"?>\n";
                $this->feed .= "<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n";
                $this->feed .= "\t<channel>\n";
                $this->feed .= "\t\t<title><![CDATA[" . $this->sanitize_content($this->channel['title']) . "]]></title>\n";
                $this->feed .= "\t\t<link>{$this->channel['link']}</link>\n";
                $this->feed .= "\t\t<description><![CDATA[" . $this->sanitize_content($this->channel['description']) . "]]></description>\n";
                $this->feed .= "\t\t<pubDate>{$this->channel['date']}</pubDate>\n";
                $this->feed .= "\t\t<generator>MyBB</generator>\n";
        }

        // ── Items ─────────────────────────────────────────────────────
        $total = count($this->items);
        foreach ($this->items as $item) {
            $item['date'] = (int) ($item['date'] ?: TIMENOW);

            switch ($this->feed_format) {
                case 'json':
                    ++$serial;
                    $end      = $serial < $total ? ',' : '';
                    $item_id  = explode('tid=', $item['link']);
                    $item['updated'] ??= $item['date'];

                    $this->feed .= "\t\t{\n";
                    $this->feed .= "\t\t\t\"id\": \"" . end($item_id) . "\",\n";
                    $this->feed .= "\t\t\t\"url\": " . json_encode($item['link']) . ",\n";
                    $this->feed .= "\t\t\t\"title\": " . json_encode($item['title']) . ",\n";
                    if (!empty($item['author'])) {
                        $this->feed .= "\t\t\t\"author\": {\n\t\t\t\t\"name\": " . json_encode($item['author']['name']) . ",\n";
                        $this->feed .= "\t\t\t\t\"url\": " . json_encode($this->channel['link'] . 'member.php?action=profile&uid=' . $item['author']['uid']) . "\n";
                        $this->feed .= "\t\t\t},\n";
                    }
                    $this->feed .= "\t\t\t\"content_html\": " . json_encode($item['description']) . ",\n";
                    $this->feed .= "\t\t\t\"date_published\": \"" . date('c', $item['date']) . "\",\n";
                    $this->feed .= "\t\t\t\"date_modified\": \"" . date('c', (int) $item['updated']) . "\"\n";
                    $this->feed .= "\t\t}{$end}\n";
                    break;

                case 'atom1.0':
                    $date_str    = date("Y-m-d\TH:i:s\Z", $item['date']);
                    $updated_str = !empty($item['updated'])
                        ? date("Y-m-d\TH:i:s\Z", (int) $item['updated'])
                        : $date_str;

                    $this->feed .= "\t<entry xmlns=\"http://www.w3.org/2005/Atom\">\n";
                    if (!empty($item['author'])) {
                        $author = '<a href="' . $this->channel['link'] . 'member.php?action=profile&uid=' . $item['author']['uid'] . '">' . $item['author']['name'] . '</a>';
                        $this->feed .= "\t\t<author>\n";
                        $this->feed .= "\t\t\t<name type=\"html\" xml:space=\"preserve\"><![CDATA[" . $this->sanitize_content($author) . "]]></name>\n";
                        $this->feed .= "\t\t</author>\n";
                    }
                    $this->feed .= "\t\t<published>{$date_str}</published>\n";
                    $this->feed .= "\t\t<updated>{$updated_str}</updated>\n";
                    $this->feed .= "\t\t<link rel=\"alternate\" type=\"text/html\" href=\"{$item['link']}\" />\n";
                    $this->feed .= "\t\t<id>{$item['link']}</id>\n";
                    $this->feed .= "\t\t<title xml:space=\"preserve\"><![CDATA[" . $this->sanitize_content($item['title']) . "]]></title>\n";
                    $this->feed .= "\t\t<content type=\"html\" xml:space=\"preserve\" xml:base=\"{$item['link']}\"><![CDATA[" . $this->sanitize_content($item['description']) . "]]></content>\n";
                    $this->feed .= "\t\t<draft xmlns=\"http://purl.org/atom-blog/ns#\">false</draft>\n";
                    $this->feed .= "\t</entry>\n";
                    break;

                default:
                    $date_str = date('D, d M Y H:i:s O', $item['date']);
                    $this->feed .= "\t\t<item>\n";
                    $this->feed .= "\t\t\t<title><![CDATA[" . $this->sanitize_content($item['title']) . "]]></title>\n";
                    $this->feed .= "\t\t\t<link>{$item['link']}</link>\n";
                    $this->feed .= "\t\t\t<pubDate>{$date_str}</pubDate>\n";
                    if (!empty($item['author'])) {
                        $author = '<a href="' . $this->channel['link'] . 'member.php?action=profile&uid=' . $item['author']['uid'] . '">' . $item['author']['name'] . '</a>';
                        $this->feed .= "\t\t\t<dc:creator><![CDATA[" . $this->sanitize_content($author) . "]]></dc:creator>\n";
                    }
                    $this->feed .= "\t\t\t<guid isPermaLink=\"false\">{$item['link']}</guid>\n";
                    $this->feed .= "\t\t\t<description><![CDATA[{$item['description']}]]></description>\n";
                    $this->feed .= "\t\t\t<content:encoded><![CDATA[{$item['description']}]]></content:encoded>\n";
                    $this->feed .= "\t\t</item>\n";
            }
        }

        // ── Feed footer ───────────────────────────────────────────────
        $this->feed .= match($this->feed_format) {
            'json'    => "\t]\n}",
            'atom1.0' => "</feed>",
            default   => "\t</channel>\n</rss>",
        };
    }

    public function sanitize_content(string $content): string
    {
        $content = preg_replace('#&[^\s]([^\#])(?![a-z1-4]{1,10});#i', '&#x26;$1', $content);
        return str_replace(']]>', ']]]]><![CDATA[>', $content);
    }

    public function output_feed(): void
    {
        global $charset;

        header(match($this->feed_format) {
            'json'    => "Content-Type: application/json; charset=\"{$charset}\"",
            'atom1.0' => "Content-Type: application/atom+xml; charset=\"{$charset}\"",
            default   => "Content-Type: text/xml; charset=\"{$charset}\"",
        });

        if (!$this->feed) {
            $this->generate_feed();
        }

        echo $this->feed;
    }
}